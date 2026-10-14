<?php

/**
 * \file    custom/slycustom/class/Wise_Incoming.class.php
 * \ingroup slycustom
 * \brief   Wise incoming payments — file-only inbox backed by
 *          DOL_DATA_ROOT/wise_webhook/accepted/*.json and per-dump sidecar
 *          metadata files (*.meta.json).
 *
 * 2.5.0 architectural pivot: the previous design kept a row in
 * llx_slycustom_wise_incoming for every accepted webhook delivery, plus a
 * matching row in llx_slycustom_wise_event for the raw envelope. Both tables
 * were redundant — every reconcile list row was just a JSON dump file the
 * webhook receiver had already written, decorated with a status column.
 * The 2.5.0 redesign drops both tables: the reconcile list now scans
 * wise_webhook/accepted/*.json, and per-dump state (status / source / note /
 * enriched fields / match) lives in a sidecar `<basename>.meta.json` next to
 * the dump file. dumpPath()'s regex (Ymd-His-eN-8hex.json) deliberately does
 * not match the .meta.json suffix, so listDumps() naturally ignores sidecars.
 *
 * Outgoing flow A (transfers#state-change → applyTransferState →
 * recordSupplierPayment) is unchanged: the webhook receiver still drives
 * slycustom_wise_transfer.last_state from the same payload, but the
 * `createFromWebhook` event-table write is gone — the sidecar pattern
 * handles dedupe, and `handleTransferStateChange()` is called directly
 * from webhook/wise.php for the transfers#state-change event.
 *
 * Timestamps in dump body / sidecar are stored in UTC (Wise sends ISO 8601 Z);
 * UI display uses sqlUtcToTimestamp() + dol_print_date('tzuserrel').
 */

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_API.class.php';

if (!class_exists('WiseIncomingPayment', false)) {
class WiseIncomingPayment
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var int|null Entity, assigned by the cron runner (declared to avoid dynamic-property deprecation on PHP 8.2+) */
	public $entity;

	const STATUS_NEW = 'NEW';
	const STATUS_ENRICHED = 'ENRICHED';
	// 2.5.0: RECONCILED is the new constant name for what 2.4.x called
	// STATUS_RECORDED. The value is the same string ("RECONCILED") so the
	// old constant name keeps working for any code that still references it.
	const STATUS_RECONCILED = 'RECONCILED';
	const STATUS_RECORDED = 'RECONCILED';
	const STATUS_IGNORED = 'IGNORED';

	/** Zero subscription_id of test notifications */
	const ZERO_UUID = '00000000-0000-0000-0000-000000000000';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	// ---- Per-entity config helpers (unchanged from 2.4.x) --------------------

	/**
	 * Is the incoming-payments flow enabled for an entity? Default ON when the
	 * constant is not set (matches the behaviour before the toggle existed).
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $entity Company entity
	 * @return bool
	 */
	public static function isIncomingEnabled($db, $entity)
	{
		$v = dolibarr_get_const($db, 'WISE_INCOMING_ENABLED', (int) $entity);
		return ($v === null || $v === '') ? true : (int) $v !== 0;
	}

	/**
	 * Is the outgoing-payments flow (Wise transfer preparation from supplier
	 * invoices) enabled for an entity? Default OFF until the flow is built.
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $entity Company entity
	 * @return bool
	 */
	public static function isOutgoingEnabled($db, $entity)
	{
		$v = dolibarr_get_const($db, 'WISE_OUTGOING_ENABLED', (int) $entity);
		return ($v === null || $v === '') ? false : (int) $v !== 0;
	}

	/**
	 * Per-entity Wise API token (Dolibarr constant, same pattern as SHIPSGO_WEBHOOK_SECRET).
	 *
	 * @param  DoliDB $db       Database handler
	 * @param  int    $entityId Company entity
	 * @return string
	 */
	public static function getApiTokenForEntity($db, $entityId)
	{
		return trim((string) dolibarr_get_const($db, 'WISE_API_TOKEN', (int) $entityId));
	}

	/**
	 * Per-entity Wise profile id.
	 *
	 * @param  DoliDB $db       Database handler
	 * @param  int    $entityId Company entity
	 * @return string
	 */
	public static function getProfileIdForEntity($db, $entityId)
	{
		return trim((string) dolibarr_get_const($db, 'WISE_PROFILE_ID', (int) $entityId));
	}

	// ---- Timestamp helpers (unchanged) --------------------------------------

	/**
	 * Convert a Wise ISO 8601 timestamp (Z) to SQL datetime in UTC.
	 *
	 * @param  string $iso ISO 8601 timestamp
	 * @return string|null 'Y-m-d H:i:s' or null when unparsable
	 */
	public static function isoToSqlUtc($iso)
	{
		if (!is_string($iso) || $iso === '') {
			return null;
		}
		try {
			$dt = new DateTime($iso);
			return $dt->format('Y-m-d H:i:s');
		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * Convert a stored UTC datetime to a Unix timestamp in server TZ (dolibarr functions expect it).
	 *
	 * @param  string $sqlUtc 'Y-m-d H:i:s' in UTC
	 * @return int|null
	 */
	public static function sqlUtcToTimestamp($sqlUtc)
	{
		if (empty($sqlUtc) || !is_string($sqlUtc)) {
			return null;
		}
		$dt = DateTime::createFromFormat('Y-m-d H:i:s', $sqlUtc, new DateTimeZone('UTC'));
		return $dt ? $dt->getTimestamp() : null;
	}

	// ---- Sidecar path / I/O --------------------------------------------------
	//
	// The sidecar file sits next to the dump file. The dumpPath() regex only
	// accepts *.json, so listDumps() / deleteDump() / the rotation logic never
	// see *.meta.json files — the sidecar is effectively invisible to the
	// existing dump machinery. This keeps the 2.4.x rotation code unchanged.

	/**
	 * Compute the absolute path of the sidecar file for one dump.
	 *
	 * @param  string $bucket 'accepted' or 'rejected'
	 * @param  string $file   Dump file name (e.g. 20261014-103045-e3-1a2b3c4d.json)
	 * @return string|null    Absolute path, null when the dump name is not valid
	 */
	public static function sidecarPath($bucket, $file)
	{
		$dumpPath = self::dumpPath($bucket, $file);
		if ($dumpPath === null) {
			return null;
		}
		return preg_replace('/\.json$/', '.meta.json', $dumpPath);
	}

	/**
	 * Read the sidecar for one dump, returning a normalised associative array.
	 * Missing sidecar returns a synthetic "orphan" record (status NEW, source
	 * inferred from the bucket). Corrupt sidecar (bad JSON) also returns the
	 * orphan record so the reconcile list still renders.
	 *
	 * @param  string $bucket 'accepted' or 'rejected'
	 * @param  string $file   Dump file name
	 * @return array          Sidecar payload (always has at least status/source/tms keys)
	 */
	public function readSidecar($bucket, $file)
	{
		$path = self::sidecarPath($bucket, $file);
		// `enriched` and `match` are arrays in the sidecar JSON. The orphan
		// default must be the same shape — otherwise `$r['enriched']['ref']`
		// in reconcile.php blows up with "Cannot use object of type stdClass
		// as array" on PHP 8.2 for the rows that have no sidecar yet
		// (e.g. 2.4.x accepted/*.json dropped in before 2.5.0 was deployed).
		$orphan = array(
			'status' => self::STATUS_NEW,
			'source' => $bucket === 'rejected' ? 'email' : 'webhook',
			'note' => '',
			'enriched' => array(),
			'match' => array(),
			'fk_paiement' => 0,
			'tms' => '',
		);
		if ($path === null || !is_file($path)) {
			return $orphan;
		}
		$raw = (string) @file_get_contents($path);
		if ($raw === '') {
			return $orphan;
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return $orphan;
		}
		// Normalise: ensure the keys reconcile.php relies on always exist.
		$out = $orphan;
		if (isset($decoded['status']) && is_string($decoded['status'])) {
			$out['status'] = $decoded['status'];
		}
		if (isset($decoded['source']) && is_string($decoded['source'])) {
			$out['source'] = $decoded['source'];
		}
		if (isset($decoded['note']) && is_string($decoded['note'])) {
			$out['note'] = $decoded['note'];
		}
		if (isset($decoded['enriched']) && is_array($decoded['enriched'])) {
			$out['enriched'] = $decoded['enriched'];
		}
		if (isset($decoded['match']) && is_array($decoded['match'])) {
			$out['match'] = $decoded['match'];
		}
		if (isset($decoded['fk_paiement'])) {
			$out['fk_paiement'] = (int) $decoded['fk_paiement'];
		}
		if (isset($decoded['tms']) && is_string($decoded['tms'])) {
			$out['tms'] = $decoded['tms'];
		}
		return $out;
	}

	/**
	 * Atomically write a sidecar next to a dump. Uses temp + rename so a
	 * partial write never leaves a half-written JSON the reconcile list
	 * would try to render. Returns the merged payload actually written.
	 *
	 * @param  string $bucket  'accepted' or 'rejected'
	 * @param  string $file    Dump file name
	 * @param  array  $merge   Associative array of fields to merge over the
	 *                         existing sidecar (or the orphan default).
	 * @return array|false     Final sidecar payload, or false on I/O failure
	 */
	public function writeSidecar($bucket, $file, array $merge)
	{
		$path = self::sidecarPath($bucket, $file);
		if ($path === null) {
			return false;
		}
		$current = $this->readSidecar($bucket, $file);
		$merged = array_merge($current, $merge);
		$merged['tms'] = date('Y-m-d H:i:s');
		$json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return false;
		}
		$dir = dirname($path);
		if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
			dol_syslog('Wise_Incoming writeSidecar mkdir failed for '.$dir, LOG_WARNING);
			return false;
		}
		$tmp = $path.'.tmp.'.getmypid();
		if (@file_put_contents($tmp, $json) === false) {
			dol_syslog('Wise_Incoming writeSidecar temp write failed for '.$tmp, LOG_WARNING);
			return false;
		}
		if (!@rename($tmp, $path)) {
			@unlink($tmp);
			dol_syslog('Wise_Incoming writeSidecar rename failed for '.$path, LOG_WARNING);
			return false;
		}
		return $merged;
	}

	// ---- SO pattern helpers (unchanged from 2.4.x) --------------------------

	/** Permissive default pattern (used when no override and no derivable mask) */
	const DEFAULT_SO_PATTERN = '/\b([A-Z]{0,4}[0-9]{2,5}[-\/]?[0-9]{1,6})\b/i';

	/**
	 * Extract candidate SO references from a payment reference text.
	 *
	 * Pattern resolution (getSoPattern): manual WISE_SO_REF_PATTERN override >
	 * derived from the configured order numbering mask > permissive default.
	 *
	 * @param  string $text   Reference text from the statement
	 * @param  int    $entity Company entity (for const lookups), defaults to current
	 * @return array  Unique uppercase candidates
	 */
	public function extractSoRefs($text, $entity = null)
	{
		global $conf;

		$refs = array();
		$text = trim((string) $text);
		if ($text === '') {
			return $refs;
		}
		if ($entity === null) {
			$entity = (int) $conf->entity;
		}
		$pattern = self::getSoPattern($this->db, (int) $entity);
		if (@preg_match($pattern, '') === false) {
			// Broken pattern (bad manual override or derivation): fall back instead of fatal preg error
			dol_syslog('Wise_Incoming invalid SO pattern ('.json_encode($pattern).'), using default', LOG_WARNING);
			$pattern = self::DEFAULT_SO_PATTERN;
		}
		if (preg_match_all($pattern, $text, $m) && !empty($m[1])) {
			foreach ($m[1] as $cand) {
				$cand = strtoupper(trim($cand));
				if ($cand !== '' && !in_array($cand, $refs)) {
					$refs[] = $cand;
				}
			}
		}
		// Raw text as an additional exact candidate (customer may paste the plain ref)
		$plain = strtoupper(preg_replace('/\s+/', '', $text));
		if ($plain !== '' && !in_array($plain, $refs)) {
			$refs[] = $plain;
		}
		return $refs;
	}

	/**
	 * Resolve the effective SO reference extraction pattern.
	 *
	 * Priority: WISE_SO_REF_PATTERN (manual override) > mask-derived > default.
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $entity Company entity
	 * @return string PCRE pattern
	 */
	public static function getSoPattern($db, $entity)
	{
		$manual = trim((string) dolibarr_get_const($db, 'WISE_SO_REF_PATTERN', (int) $entity));
		if ($manual !== '' && @preg_match($manual, '') !== false) {
			return $manual;
		}
		$derived = self::deriveSoPatternFromNumbering($db, (int) $entity);
		if ($derived !== '' && @preg_match($derived, '') !== false) {
			return $derived;
		}
		return self::DEFAULT_SO_PATTERN;
	}

	/**
	 * Derive an extraction pattern from the active order numbering model mask
	 * (Sales Orders setup > Orders numbering). The mask constant follows the
	 * active addon: COMMANDE_<NAME>_MASK (e.g. mod_commande_saphir ->
	 * COMMANDE_SAPHIR_MASK).
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $entity Company entity
	 * @return string PCRE pattern, '' when no mask available / parsable
	 */
	public static function deriveSoPatternFromNumbering($db, $entity)
	{
		$mask = '';
		$addon = '';
		foreach (array(0, (int) $entity) as $e) {
			$v = trim((string) dolibarr_get_const($db, 'COMMANDE_ADDON', $e));
			if ($v !== '') {
				$addon = $v;
				break;
			}
		}
		if ($addon !== '' && preg_match('/^mod_commande_(.+)$/i', $addon, $m)) {
			foreach (array(0, (int) $entity) as $e) {
				$v = trim((string) dolibarr_get_const($db, 'COMMANDE_'.strtoupper($m[1]).'_MASK', $e));
				if ($v !== '') {
					$mask = $v;
					break;
				}
			}
		}
		if ($mask === '') {
			// Fallback: the only order mask constant stored (works whatever the addon name)
			$sql = 'SELECT value FROM '.MAIN_DB_PREFIX.'const';
			$sql .= " WHERE name LIKE 'COMMANDE\\_%\\_MASK' AND entity IN (0, ".(int) $entity.")";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					if (trim((string) $obj->value) !== '') {
						$mask = trim((string) $obj->value);
						break;
					}
				}
				$db->free($resql);
			}
		}
		if ($mask === '') {
			return '';
		}
		return self::maskToPattern($mask);
	}

	/**
	 * Convert a Dolibarr numbering mask (get_next_value syntax) into a PCRE
	 * extraction pattern. Counter width is kept flexible (payer text may drop
	 * leading zeros). Returns '' when the mask contains an unsupported token.
	 *
	 * Supported tokens (core/lib/functions2.lib.php):
	 *   {0000} (+@offset/+raz suffixes) -> \d{1,N}
	 *   {yy} {yyyy} {mm} {dd}           -> fixed digit widths
	 *   {cccc} {cc0000} {tttt} {uuuu}   -> [A-Z0-9]{1,N} (\d{1,N} for cc-counter)
	 *   {KEY-n}                         -> [A-Z0-9]{1,n}
	 *
	 * @param  string $mask Numbering mask, e.g. CG{yy}{mm}-{0000}
	 * @return string PCRE pattern
	 */
	public static function maskToPattern($mask)
	{
		$mask = trim((string) $mask);
		if ($mask === '' || strpos($mask, '{') === false) {
			return '';
		}
		$p = '';
		$len = dol_strlen($mask);
		for ($i = 0; $i < $len; $i++) {
			if ($mask[$i] !== '{') {
				$p .= preg_quote($mask[$i], '/');
				continue;
			}
			$end = strpos($mask, '}', $i);
			if ($end === false) {
				$p .= preg_quote(substr($mask, $i), '/');
				break;
			}
			$token = substr($mask, $i + 1, $end - $i - 1);
			$i = $end;
			// Strip @offset / +raz value suffixes of the counter token
			$core = preg_replace('/[@+][0-9=+\-]+$/i', '', $token);
			if ($core === '') {
				return '';
			}
			if (preg_match('/^0+$/', $core)) {
				$p .= '\d{1,'.strlen($core).'}';
			} elseif (strcasecmp($core, 'yyyy') === 0) {
				$p .= '\d{4}';
			} elseif (strcasecmp($core, 'yy') === 0 || strcasecmp($core, 'mm') === 0 || strcasecmp($core, 'dd') === 0) {
				$p .= '\d{2}';
			} elseif (preg_match('/^([cC]+)(0*)$/', $core, $m2) && $m2[1] !== '') {
				// client code part + optional per-client counter
				$p .= '[A-Z0-9]{1,'.strlen($m2[1]).'}'.($m2[2] !== '' ? '\d{1,'.strlen($m2[2]).'}' : '');
			} elseif (preg_match('/^[ctuTU]+$/', $core)) {
				$p .= '[A-Z0-9]{1,'.strlen($core).'}';
			} elseif (preg_match('/^[A-Z]+-[0-9]+$/i', $core, $m2)) {
				$p .= '[A-Z0-9]{1,'.(int) substr($core, strpos($core, '-') + 1).'}';
			} else {
				return ''; // unknown token family: refuse rather than mismatch
			}
		}
		return '/(?<![A-Za-z0-9])('.$p.')(?![0-9])/i';
	}

	// ---- Bank / Paiement matchers (read-only; 2.4.x behaviour) ---------------
	//
	// 2.5.0 keeps the same chip logic: still read-only, never writes
	// llx_bank / llx_paiement / llx_facture. The only change: instead of
	// taking a DB row from fetchRow(), findBankMatches / findPaiementMatches
	// now take a normalised dump summary produced by listDumps() (a plain
	// array with 'amount' / 'currency' / 'occurred_at' / 'entity' keys).
	// The signature is compatible: both old ($row) and new (array) shapes
	// work because the accessors only read those four keys.

	/**
	 * Look up bank-account entries that likely match this Wise credit. Used by
	 * the reconciliation list to flag rows that the operator has already paid
	 * through the bank / invoice screens. Read-only: nothing is written here.
	 *
	 * Match rules:
	 *   - Doli 24 stores bank.amount as a signed value (credit = negative,
	 *     debit = positive). The webhook delivers a positive number, so we
	 *     compare against ABS(b.amount) on the bank side.
	 *   - Currency is matched against bank_account.currency_code; falls back
	 *     to the company-currency bank if the row has no multicurrency code.
	 *   - Date window is ±3 days around the webhook event (bank posting date
	 *     vs notification date drift on weekends/holidays).
	 *   - Amount tolerance is ±30 (USD or row currency) to absorb bank fees
	 *     rolled into the credit. Set tight enough that two distinct invoices
	 *     in the same week will not collide.
	 *
	 * @param  array $row   dump summary (amount / currency / occurred_at / entity)
	 * @param  int   $days  Date window each side, default 3
	 * @param  float $tol   Amount tolerance in row currency, default 30
	 * @return array        List of bank candidates (each: rowid, datev, amount, label, fk_account, diff)
	 */
	public function findBankMatches($row, $days = 3, $tol = 30.0)
	{
		global $conf;
		$row = is_array($row) ? $row : array();
		if (empty($row) || !isset($row['amount'])) {
			return array();
		}
		$amount = (float) $row['amount'];
		if ($amount <= 0) {
			return array();
		}
		$cur = strtoupper((string) (isset($row['currency']) ? $row['currency'] : ''));
		$occTs = self::sqlUtcToTimestamp(isset($row['occurred_at']) ? $row['occurred_at'] : '');
		if ($occTs <= 0) {
			$occTs = dol_now();
		}
		$entity = !empty($row['entity']) ? (int) $row['entity'] : (int) $conf->entity;
		$from = (int) ($occTs - $days * 86400);
		$to = (int) ($occTs + $days * 86400);

		$min = $amount - $tol;
		$max = $amount + $tol;

		$sql = 'SELECT b.rowid, b.datev, b.amount, b.label, b.fk_account, ba.currency_code, ba.label AS account_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'bank b';
		$sql .= ' JOIN '.MAIN_DB_PREFIX.'bank_account ba ON ba.rowid = b.fk_account';
		$sql .= ' WHERE ba.entity = '.(int) $entity;
		$sql .= ' AND ABS(b.amount) BETWEEN '.((float) $min).' AND '.((float) $max);
		// datev is DATE, not datetime: FromUnixTime returns the date-of the instant
		// in UTC. Pass through DATE() so MySQL doesn't reinterpret the int as
		// 'YYYYMMDD' and miss every row.
		$sql .= ' AND b.datev BETWEEN DATE(FROM_UNIXTIME('.(int) $from.')) AND DATE(FROM_UNIXTIME('.(int) $to.'))';
		if ($cur !== '') {
			$sql .= " AND UPPER(ba.currency_code) = '".$this->db->escape($cur)."'";
		}
		// Tightest diff first: exact match sorts to 0; the 6.11 fee delta
		// pattern (a few dollars off) sorts right after.
		$sql .= ' ORDER BY ABS(ABS(b.amount) - '.((float) $amount).') ASC, b.datev ASC LIMIT 5';

		$out = array();
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$dv = is_numeric($obj->datev) ? (int) $obj->datev : strtotime((string) $obj->datev);
				$out[] = array(
					'rowid' => (int) $obj->rowid,
					'datev' => (int) $dv,
					'amount' => (float) $obj->amount,
					'label' => (string) $obj->label,
					'fk_account' => (int) $obj->fk_account,
					'currency_code' => strtoupper((string) $obj->currency_code),
					'account_label' => (string) $obj->account_label,
					'diff' => abs(abs((float) $obj->amount) - $amount),
				);
			}
			$this->db->free($resql);
		}
		return $out;
	}

	/**
	 * Batch variant of findBankMatches for the reconcile list. The per-row
	 * version triggers one SQL per dump; with up to 200 files per bucket that
	 * becomes 200+1 queries per page load, which violates the
	 * "no queries in a loop" rule. This entry point groups input rows by
	 * (entity, currency), takes the union of their date windows, runs ONE
	 * SELECT per (entity, currency) bucket, and partitions the result set
	 * back to each row in PHP memory.
	 *
	 * Output is keyed by the original `$rows` array key so callers can
	 * splice it into the render loop without bookkeeping. Rows with no
	 * amount or out-of-window are simply absent from the result.
	 *
	 * @param  array<int, array>  $rows  listDumps() summaries (must carry
	 *                                   amount/currency/occurred_at/entity)
	 * @param  int                $days  Date window each side, default 3
	 * @param  float              $tol   Amount tolerance in row currency, default 30
	 * @param  int                $limit Per-row cap on returned candidates, default 5
	 * @return array<string, array>     Map: row-key => candidates[] (same shape as findBankMatches)
	 */
	public function findBankMatchesBatch(array $rows, $days = 3, $tol = 30.0, $limit = 5)
	{
		global $conf;
		$out = array();
		if (empty($rows)) {
			return $out;
		}
		// Bucket rows by (entity, currency). Rows missing amount or with
		// non-positive amount are skipped — they can never match anyway.
		$buckets = array();
		$rowKeys = array();
		foreach ($rows as $key => $row) {
			if (!is_array($row) || !isset($row['amount'])) {
				continue;
			}
			$amount = (float) $row['amount'];
			if ($amount <= 0) {
				continue;
			}
			$cur = strtoupper((string) (isset($row['currency']) ? $row['currency'] : ''));
			$entity = !empty($row['entity']) ? (int) $row['entity'] : (int) $conf->entity;
			$occTs = self::sqlUtcToTimestamp(isset($row['occurred_at']) ? $row['occurred_at'] : '');
			if ($occTs <= 0) {
				$occTs = dol_now();
			}
			$bk = $entity.'|'.$cur;
			if (!isset($buckets[$bk])) {
				$buckets[$bk] = array(
					'entity' => $entity,
					'cur' => $cur,
					'rows' => array(),
				);
			}
			$buckets[$bk]['rows'][$key] = array(
				'row' => $row,
				'occTs' => (int) $occTs,
				'amount' => $amount,
			);
			$rowKeys[$key] = true;
		}
		if (empty($buckets)) {
			return $out;
		}
		// One SELECT per (entity, currency). Each SELECT takes the union
		// of the date windows in that bucket, with amount widened to
		// (min_amount - tol, max_amount + tol). A 200-row list typically
		// collapses to 1-3 buckets.
		foreach ($buckets as $bk => $bucket) {
			$entity = $bucket['entity'];
			$cur = $bucket['cur'];
			$minOcc = PHP_INT_MAX; $maxOcc = 0;
			$minAmt = PHP_FLOAT_MAX; $maxAmt = 0.0;
			foreach ($bucket['rows'] as $rr) {
				if ($rr['occTs'] < $minOcc) $minOcc = $rr['occTs'];
				if ($rr['occTs'] > $maxOcc) $maxOcc = $rr['occTs'];
				if ($rr['amount'] < $minAmt) $minAmt = $rr['amount'];
				if ($rr['amount'] > $maxAmt) $maxAmt = $rr['amount'];
			}
			$from = (int) ($minOcc - $days * 86400);
			$to = (int) ($maxOcc + $days * 86400);
			$min = $minAmt - $tol;
			$max = $maxAmt + $tol;
			$sql = 'SELECT b.rowid, b.datev, b.amount, b.label, b.fk_account, ba.currency_code, ba.label AS account_label';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'bank b';
			$sql .= ' JOIN '.MAIN_DB_PREFIX.'bank_account ba ON ba.rowid = b.fk_account';
			$sql .= ' WHERE ba.entity = '.(int) $entity;
			$sql .= ' AND ABS(b.amount) BETWEEN '.((float) $min).' AND '.((float) $max);
			$sql .= ' AND b.datev BETWEEN DATE(FROM_UNIXTIME('.(int) $from.')) AND DATE(FROM_UNIXTIME('.(int) $to.'))';
			if ($cur !== '') {
				$sql .= " AND UPPER(ba.currency_code) = '".$this->db->escape($cur)."'";
			}
			$sql .= ' ORDER BY b.datev DESC, b.rowid DESC LIMIT 5000';
			$resql = $this->db->query($sql);
			if (!$resql) {
				continue;
			}
			// Pull every candidate row into memory, then partition per
			// dump row by amount tolerance + date window. The 5000 cap is
			// a safety net — at sane bank volumes (<500 credits in 6
			// days per currency) this is well within a single page.
			$candidates = array();
			while ($obj = $this->db->fetch_object($resql)) {
				$dv = is_numeric($obj->datev) ? (int) $obj->datev : strtotime((string) $obj->datev);
				$candidates[] = array(
					'rowid' => (int) $obj->rowid,
					'datev' => (int) $dv,
					'amount' => (float) $obj->amount,
					'label' => (string) $obj->label,
					'fk_account' => (int) $obj->fk_account,
					'currency_code' => strtoupper((string) $obj->currency_code),
					'account_label' => (string) $obj->account_label,
				);
			}
			$this->db->free($resql);
			foreach ($bucket['rows'] as $key => $rr) {
				$rowFrom = (int) ($rr['occTs'] - $days * 86400);
				$rowTo = (int) ($rr['occTs'] + $days * 86400);
				$rowMin = $rr['amount'] - $tol;
				$rowMax = $rr['amount'] + $tol;
				$picks = array();
				foreach ($candidates as $c) {
					$cAmount = abs($c['amount']);
					if ($cAmount < $rowMin || $cAmount > $rowMax) {
						continue;
					}
					if ($c['datev'] < $rowFrom || $c['datev'] > $rowTo) {
						continue;
					}
					$c['diff'] = abs($cAmount - $rr['amount']);
					$picks[] = $c;
				}
				// Tightest diff first, then date asc — same ordering as
				// the per-row findBankMatches.
				usort($picks, function ($a, $b) {
					if ($a['diff'] === $b['diff']) {
						return $a['datev'] - $b['datev'];
					}
					return $a['diff'] < $b['diff'] ? -1 : 1;
				});
				$out[$key] = array_slice($picks, 0, $limit);
			}
		}
		return $out;
	}

	/**
	 * Look up Paiement rows that likely match this Wise credit. The customer
	 * may have already recorded the payment from the invoice screen — in that
	 * case llx_paiement has the right amount + date, but we never knew about
	 * it because Wise cannot tell us which invoice row the credit covers.
	 *
	 * @param  array $row   dump summary (amount / currency / occurred_at / entity)
	 * @param  int   $days  Date window each side, default 3
	 * @param  float $tol   Amount tolerance in row currency, default 30
	 * @return array     Paiement candidates (rowid, datep, amount, invoice_refs)
	 */
	public function findPaiementMatches($row, $days = 3, $tol = 30.0)
	{
		global $conf;
		$row = is_array($row) ? $row : array();
		if (empty($row) || !isset($row['amount'])) {
			return array();
		}
		$amount = (float) $row['amount'];
		if ($amount <= 0) {
			return array();
		}
		$occTs = self::sqlUtcToTimestamp(isset($row['occurred_at']) ? $row['occurred_at'] : '');
		if ($occTs <= 0) {
			$occTs = dol_now();
		}
		$entity = !empty($row['entity']) ? (int) $row['entity'] : (int) $conf->entity;
		$from = (int) ($occTs - $days * 86400);
		$to = (int) ($occTs + $days * 86400);
		$min = $amount - $tol;
		$max = $amount + $tol;

		$sql = 'SELECT p.rowid, p.datep, p.amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'paiement p';
		$sql .= ' WHERE p.entity = '.(int) $entity;
		$sql .= ' AND p.amount BETWEEN '.((float) $min).' AND '.((float) $max);
		// datep is DATETIME; the unix ints must be wrapped or MySQL coerces them
		// to 'YYYYMMDDHHMMSS' shape and misses every row.
		$sql .= ' AND p.datep BETWEEN FROM_UNIXTIME('.(int) $from.') AND FROM_UNIXTIME('.(int) $to.')';
		$sql .= ' ORDER BY ABS(p.amount - '.((float) $amount).') ASC, p.datep ASC LIMIT 5';

		$out = array();
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$dp = is_numeric($obj->datep) ? (int) $obj->datep : strtotime((string) $obj->datep);
				$out[] = array(
					'rowid' => (int) $obj->rowid,
					'datep' => (int) $dp,
					'amount' => (float) $obj->amount,
					'diff' => abs((float) $obj->amount - $amount),
				);
			}
			$this->db->free($resql);
		}
		return $out;
	}

	// ---- Webhook entry: transfer state change only ---------------------------

	/**
	 * Handle a `transfers#state-change` webhook event end-to-end: write the
	 * new state onto slycustom_wise_transfer, and when the new state is SENT
	 * call recordSupplierPayment under the transfer's own entity.
	 *
	 * 2.5.0 simplification: the event table is GONE. The dedupe concern that
	 * used to justify event-table md5 storage is moot — webhook dumps are
	 * named after md5(raw)[0:8], so a re-delivery of the same payload writes
	 * the same `<basename>.json` and the same `<basename>.meta.json`. A
	 * re-delivery that arrived after a sidecar was already written with a
	 * non-NEW status (ENRICHED / RECONCILED / IGNORED) is suppressed
	 * upstream by the receiver; this method only deals with the live
	 * transfer-state branch.
	 *
	 * Behaviour is otherwise identical to the 2.4.x createFromWebhook
	 * transfer branch: applyTransferState() drives the slycustom_wise_transfer
	 * row's status, and when the new state is SENT we look up the invoice
	 * author (or any active admin) as the bookkeeping user and call
	 * recordSupplierPayment under the transfer's own entity. The webhook
	 * receiver's $conf->entity is restored afterwards. No queue row, no list
	 * appearance.
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $entity Resolved entity (profile-routed when possible)
	 * @param  array  $body   Decoded envelope
	 * @return int            Last slycustom_wise_transfer.rowid touched (0 when nothing matched)
	 */
	public static function handleTransferStateChange($db, $entity, array $body)
	{
		global $conf;

		$eventType = isset($body['event_type']) ? (string) $body['event_type'] : '';
		if ($eventType !== 'transfers#state-change') {
			return 0;
		}
		$subscriptionId = isset($body['subscription_id']) ? (string) $body['subscription_id'] : '';
		$isTest = $subscriptionId === self::ZERO_UUID;
		if ($isTest) {
			return 0;
		}
		$data = isset($body['data']) && is_array($body['data']) ? $body['data'] : array();
		$resource0 = isset($data['resource']) && is_array($data['resource']) ? $data['resource'] : array();
		$occurredAt = self::isoToSqlUtc(isset($data['occurred_at']) ? $data['occurred_at'] : '');
		$state = isset($data['current_state']) ? (string) $data['current_state'] : '';
		$wiseTransferId = isset($resource0['id']) ? (int) $resource0['id'] : 0;
		if ($wiseTransferId <= 0 || $state === '') {
			return 0;
		}

		// Resolve the transfer row to charge the payment against.
		$sql = 'SELECT t.rowid, t.entity FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer t WHERE t.wise_transfer_id = '.(int) $wiseTransferId.' ORDER BY t.rowid DESC LIMIT 1';
		$resql = $db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (!$obj) {
			dol_syslog('wise_webhook handleTransferStateChange no transfer row for wise_transfer_id='.$wiseTransferId, LOG_INFO);
			return 0;
		}
		$transferRowId = (int) $obj->rowid;
		$transferEntity = (int) $obj->entity;

		require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Payment.class.php';
		$op = new WiseOutgoingPayment($db);
		$newStatus = $op->applyTransferState($wiseTransferId, $state, $occurredAt);
		if ($newStatus !== WiseOutgoingPayment::STATUS_SENT) {
			return $transferRowId;
		}

		// Bookkeeping user: invoice author first, then any active admin in
		// the transfer's own entity. Superadmins live in entity 0 and are
		// preferred over the per-company admin when both match.
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$bookUser = null;
		$sql = 'SELECT u.rowid FROM '.MAIN_DB_PREFIX.'user AS u';
		$sql .= ' JOIN '.MAIN_DB_PREFIX.'facture_fourn AS f ON f.fk_user_author = u.rowid AND f.rowid =';
		$sql .= ' (SELECT fk_facture_fourn FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer WHERE rowid = '.$transferRowId.')';
		$resql = $db->query($sql);
		if ($resql) {
			$obj2 = $db->fetch_object($resql);
			if ($obj2) {
				$bookUser = new User($db);
				$bookUser->fetch((int) $obj2->rowid);
			}
			$db->free($resql);
		}
		if (!is_object($bookUser) || empty($bookUser->id)) {
			$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin = 1 AND statut = 1'
				.' AND entity IN (0, '.(int) $transferEntity.')'
				.' ORDER BY (entity = '.(int) $transferEntity.') DESC, rowid ASC LIMIT 1';
			$resql = $db->query($sql);
			if ($resql) {
				$obj2 = $db->fetch_object($resql);
				if ($obj2) {
					$bookUser = new User($db);
					$bookUser->fetch((int) $obj2->rowid);
				}
				$db->free($resql);
			}
		}
		if (!is_object($bookUser) || empty($bookUser->id)) {
			dol_syslog('wise_webhook no user resolvable for auto payment, transfer row '.$transferRowId.' stays SENT', LOG_WARNING);
			return $transferRowId;
		}

		// Switch $conf to the transfer's company while we create the payment,
		// so PaiementFourn::create writes entity = $transferEntity, not the
		// webhook receiver's entity.
		$prevEntity = (int) $conf->entity;
		if ($transferEntity >= 1 && $transferEntity !== $prevEntity) {
			$conf->setEntityValues($db, $transferEntity);
		}
		$pid = $op->recordSupplierPayment($transferRowId, $bookUser);
		if ((int) $conf->entity !== $prevEntity) {
			$conf->setEntityValues($db, $prevEntity);
		}
		dol_syslog('wise_webhook auto supplier payment for transfer '.$wiseTransferId.' result='.$pid.' user='.$bookUser->id.' entity='.$transferEntity, LOG_INFO);

		return $transferRowId;
	}

	// ---- Dump file management (accepted / rejected) -------------------------

	/**
	 * Build the absolute path of one dump file after validating its name.
	 *
	 * The name check is the path-traversal guard: only files this module
	 * created (Ymd-His-eN-8hex.json) can ever be read or unlinked.
	 *
	 * @param  string      $bucket 'accepted' or 'rejected'
	 * @param  string      $file   Dump file name
	 * @return string|null         Absolute path, null when the name is not a dump
	 */
	public static function dumpPath($bucket, $file)
	{
		if (!in_array($bucket, array('accepted', 'rejected'), true)) {
			return null;
		}
		if (!preg_match('/^\d{8}-\d{6}-e\d+-[a-f0-9]{8}\.json$/', (string) $file)) {
			return null;
		}
		return rtrim(DOL_DATA_ROOT, '/').'/wise_webhook/'.$bucket.'/'.$file;
	}

	/**
	 * List webhook delivery dumps with their sidecar state, newest first.
	 * Each row in the returned arrays is shaped so the reconcile list
	 * renders it directly: amount / currency / occurred_at / event_type /
	 * source / status come from the dump body or the sidecar.
	 *
	 * 2.5.0: the previous listDumps keyed ingestion on a batched payload_md5
	 * lookup against llx_slycustom_wise_event. That table is still kept
	 * (for the transfers#state-change flow's dedupe) but is no longer the
	 * source of truth for "is this in the queue" — the file itself is. The
	 * batched lookup is dropped, removing one query per page render.
	 *
	 * @param  int   $entity Only dumps captured for this entity (0 = all)
	 * @return array         array('accepted' => [...], 'rejected' => [...])
	 */
	public function listDumps($entity = 0)
	{
		$out = array('accepted' => array(), 'rejected' => array());
		foreach (array_keys($out) as $bucket) {
			$dir = rtrim(DOL_DATA_ROOT, '/').'/wise_webhook/'.$bucket;
			if (!is_dir($dir)) {
				continue;
			}
			$files = glob($dir.'/*.json');
			if (!is_array($files)) {
				continue;
			}
			sort($files);
			foreach (array_reverse($files) as $path) {
				$name = basename($path);
				if (self::dumpPath($bucket, $name) === null) {
					continue;
				}
				$dump = json_decode((string) @file_get_contents($path), true);
				if (!is_array($dump)) {
					continue;
				}
				if ($entity >= 1 && isset($dump['entity']) && (int) $dump['entity'] !== (int) $entity) {
					continue;
				}
				$sidecar = $this->readSidecar($bucket, $name);
				$summary = array(
					'file' => $name,
					'received_at' => isset($dump['received_at']) ? (int) $dump['received_at'] : 0,
					'remote_ip' => isset($dump['remote_ip']) ? (string) $dump['remote_ip'] : '',
					'bootstrap' => !empty($dump['bootstrap']),
					'truncated' => !empty($dump['body_truncated']),
					'entity' => isset($dump['entity']) ? (int) $dump['entity'] : 0,
					'event_type' => '',
					'amount' => null,
					'currency' => '',
					'occurred_at' => '',
					'delivery_id' => isset($dump['headers']['X_DELIVERY_ID']) ? (string) $dump['headers']['X_DELIVERY_ID'] : '',
					'source' => $sidecar['source'],
					'status' => $sidecar['status'],
					'note' => $sidecar['note'],
					'enriched' => $sidecar['enriched'],
					'match' => $sidecar['match'],
					'fk_paiement' => $sidecar['fk_paiement'],
					'sidecar_tms' => $sidecar['tms'],
				);
				$body = isset($dump['body']) && is_string($dump['body']) ? $dump['body'] : '';
				$parsed = json_decode($body, true);
				if (is_array($parsed)) {
					$summary['event_type'] = isset($parsed['event_type']) ? (string) $parsed['event_type'] : '';
					$summary['currency'] = isset($parsed['data']['currency']) ? (string) $parsed['data']['currency'] : '';
					$summary['amount'] = isset($parsed['data']['amount']) ? (float) $parsed['data']['amount'] : null;
					$summary['occurred_at'] = isset($parsed['data']['occurred_at']) ? (string) $parsed['data']['occurred_at'] : '';
				}
				$out[$bucket][$bucket.'/'.$name] = $summary;
			}
		}
		return $out;
	}

	/**
	 * Delete one stored dump (file only; events stay in DB for the transfer
	 * flow's dedupe). The sidecar is removed in the same call so an orphan
	 * meta file does not survive the dump it belongs to.
	 *
	 * @param  string $bucket 'accepted' or 'rejected'
	 * @param  string $file   Dump file name
	 * @return array          array('ok' => bool, 'message' => string)
	 */
	public function deleteDump($bucket, $file)
	{
		$path = self::dumpPath($bucket, $file);
		if ($path === null) {
			return array('ok' => false, 'message' => 'invalid dump reference');
		}
		if (!is_file($path)) {
			return array('ok' => false, 'message' => 'dump no longer exists');
		}
		if (!@unlink($path)) {
			dol_syslog('Wise_Incoming deleteDump failed for '.$path, LOG_WARNING);
			return array('ok' => false, 'message' => 'unlink failed (permissions?)');
		}
		$sidecar = self::sidecarPath($bucket, $file);
		if ($sidecar !== null && is_file($sidecar)) {
			@unlink($sidecar);
		}
		return array('ok' => true, 'message' => 'deleted');
	}

	// ---- File-based enrich / ignore / reconcile actions ----------------------

	/**
	 * Pull the balance statement around occurred_at and update the sidecar
	 * with ref_text / counterparty / fees + any SO refs found in the
	 * reference. 2.5.0 replacement for the old enrichRow() that wrote
	 * llx_slycustom_wise_incoming; same input/output contract — the row
	 * is just a dump file now.
	 *
	 * @param  string $bucket 'accepted' or 'rejected'
	 * @param  string $file   Dump file name
	 * @return array          array('ok' => bool, 'message' => string, 'enriched' => bool)
	 */
	public function enrichDumpFromStatement($bucket, $file)
	{
		$dumpPath = self::dumpPath($bucket, $file);
		if ($dumpPath === null || !is_file($dumpPath)) {
			return array('ok' => false, 'message' => 'dump not found', 'enriched' => false);
		}
		$sidecar = $this->readSidecar($bucket, $file);
		if ($sidecar['status'] === self::STATUS_RECONCILED) {
			return array('ok' => false, 'message' => 'already reconciled', 'enriched' => false);
		}
		$entity = 0;
		$amount = null;
		$currency = '';
		$occurredAt = '';
		$rawDump = json_decode((string) @file_get_contents($dumpPath), true);
		if (is_array($rawDump)) {
			$entity = isset($rawDump['entity']) ? (int) $rawDump['entity'] : 0;
			$body = isset($rawDump['body']) ? (string) $rawDump['body'] : '';
			$parsed = json_decode($body, true);
			if (is_array($parsed)) {
				$amount = isset($parsed['data']['amount']) ? (float) $parsed['data']['amount'] : null;
				$currency = isset($parsed['data']['currency']) ? strtoupper((string) $parsed['data']['currency']) : '';
				$occurredAt = self::isoToSqlUtc(isset($parsed['data']['occurred_at']) ? $parsed['data']['occurred_at'] : '');
			}
		}
		if ($entity <= 0 || $amount === null || $currency === '' || !$occurredAt) {
			return array('ok' => false, 'message' => 'dump missing amount/currency/date/entity', 'enriched' => false);
		}

		$api = Wise_API::fromEntity($this->db, $entity);
		$profileId = self::getProfileIdForEntity($this->db, $entity);
		if (!$api || $profileId === '') {
			return array('ok' => false, 'message' => 'WISE_API_TOKEN / WISE_PROFILE_ID not configured', 'enriched' => false);
		}

		$ts = self::sqlUtcToTimestamp($occurredAt);
		$startIso = gmdate('Y-m-d\TH:i:s\Z', $ts - 300);
		$endIso = gmdate('Y-m-d\TH:i:s\Z', $ts + 300);
		$result = $api->getStatementTransactions((int) $profileId, 0, $startIso, $endIso);
		if (isset($result['error'])) {
			return array('ok' => false, 'message' => 'statement lookup failed: '.substr((string) $result['error'], 0, 200), 'enriched' => false);
		}
		// 2.5.0 simplification: a single statement pull covers the time window
		// for the credit. The closest CREDIT-by-amount wins; ties broken by
		// the timestamp delta. Statement rows with the wrong amount are
		// skipped — fee deltas in the credit total (which is the only thing
		// the webhook carries) would otherwise get a chance to match.
		$best = null;
		$bestDelta = null;
		$matchCount = 0;
		foreach ($result['transactions'] as $txn) {
			if (!is_array($txn) || !isset($txn['type']) || strtoupper((string) $txn['type']) !== 'CREDIT') {
				continue;
			}
			$txnAmount = isset($txn['totalValue']) ? (float) $txn['totalValue'] : (isset($txn['amount']) ? (float) $txn['amount'] : null);
			if ($txnAmount === null || abs($txnAmount - $amount) > 0.0001) {
				continue;
			}
			$txnTs = self::isoToSqlUtc(isset($txn['date']) ? $txn['date'] : (isset($txn['dateTime']) ? $txn['dateTime'] : ''));
			$delta = $txnTs ? abs((int) self::sqlUtcToTimestamp($txnTs) - $ts) : PHP_INT_MAX;
			$matchCount++;
			if ($bestDelta === null || $delta < $bestDelta) {
				$bestDelta = $delta;
				$best = $txn;
			}
		}
		if ($best === null) {
			return array('ok' => true, 'message' => 'statement not available yet', 'enriched' => false);
		}

		$details = isset($best['details']) && is_array($best['details']) ? $best['details'] : array();
		$refText = '';
		foreach (array('reference', 'paymentReference', 'payment_reference', 'description') as $f) {
			if (isset($details[$f]) && is_string($details[$f]) && trim($details[$f]) !== '') {
				$refText = trim($details[$f]);
				break;
			}
		}
		$counterparty = '';
		foreach (array('senderName', 'counterparty', 'payerName') as $f) {
			if (isset($details[$f]) && is_string($details[$f]) && trim($details[$f]) !== '') {
				$counterparty = trim($details[$f]);
				break;
			}
		}
		$fees = isset($best['fees']['total']) ? (float) $best['fees']['total'] : null;
		$note = ($matchCount > 1 ? 'Ambiguity: '.$matchCount.' statement transactions matched, closest kept. ' : '').$sidecar['note'];

		$soRefs = $this->extractSoRefs($refText, $entity);
		$socInfo = null;
		$soRows = array();
		if (!empty($soRefs)) {
			$inList = array();
			foreach ($soRefs as $r) {
				$inList[] = "'".$this->db->escape($r)."'";
			}
			$sql = 'SELECT c.rowid, c.ref, c.fk_soc, s.nom FROM '.MAIN_DB_PREFIX.'commande c';
			$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid = c.fk_soc';
			$sql .= ' WHERE c.entity = '.(int) $entity;
			$sql .= ' AND UPPER(c.ref) IN ('.implode(',', $inList).')';
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$soRows[] = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref);
					if ($socInfo === null && !empty($obj->fk_soc)) {
						$socInfo = array('id' => (int) $obj->fk_soc, 'name' => (string) $obj->nom);
					}
				}
				$this->db->free($resql);
			}
		}

		$merged = $this->writeSidecar($bucket, $file, array(
			'status' => self::STATUS_ENRICHED,
			'enriched' => array(
				'ref_text' => $refText,
				'counterparty' => $counterparty,
				'fees' => $fees,
				'fk_soc' => $socInfo['id'] ?? 0,
			),
			'match' => array(
				'sos' => $soRows,
				'soc' => $socInfo,
			),
			'note' => $note,
		));
		if ($merged === false) {
			return array('ok' => false, 'message' => 'sidecar write failed', 'enriched' => false);
		}
		return array('ok' => true, 'message' => 'enriched', 'enriched' => true);
	}

	/**
	 * Mark a dump as ignored. The reconcile list still renders it (greyed),
	 * and the row can be retired via the bulk-delete action.
	 *
	 * @param  string $bucket 'accepted' or 'rejected'
	 * @param  string $file   Dump file name
	 * @return array          array('ok' => bool, 'message' => string)
	 */
	public function ignoreDump($bucket, $file)
	{
		$sidecar = $this->readSidecar($bucket, $file);
		if ($sidecar['status'] === self::STATUS_RECONCILED) {
			return array('ok' => false, 'message' => 'already reconciled');
		}
		$merged = $this->writeSidecar($bucket, $file, array('status' => self::STATUS_IGNORED));
		if ($merged === false) {
			return array('ok' => false, 'message' => 'sidecar write failed');
		}
		return array('ok' => true, 'message' => 'ignored');
	}

	/**
	 * Mark a dump as reconciled. fk_paiement / bank_id are stored as a
	 * reference for the chip renderer — the page NEVER writes to
	 * llx_paiement / llx_bank / llx_facture (per the 2.4.0 hard rule:
	 * "不要直接写入bank，因为dolibarr不允许从bank倒回来补充payment链接").
	 * 2.5.0 keeps that rule and just adds a banker-ref field for the chip.
	 *
	 * @param  string $bucket      'accepted' or 'rejected'
	 * @param  string $file        Dump file name
	 * @param  int    $fkPaiement Paiement rowid (0 when only bank_id is set)
	 * @param  int    $fkBank      Bank line rowid (0 when only paiement is set)
	 * @return array               array('ok' => bool, 'message' => string)
	 */
	public function markReconciledDump($bucket, $file, $fkPaiement, $fkBank)
	{
		$sidecar = $this->readSidecar($bucket, $file);
		if ($sidecar['status'] === self::STATUS_RECONCILED) {
			return array('ok' => false, 'message' => 'already reconciled');
		}
		$merged = $this->writeSidecar($bucket, $file, array(
			'status' => self::STATUS_RECONCILED,
			'fk_paiement' => (int) $fkPaiement,
			'fk_bank' => (int) $fkBank,
		));
		if ($merged === false) {
			return array('ok' => false, 'message' => 'sidecar write failed');
		}
		return array('ok' => true, 'message' => 'reconciled');
	}
}
}
