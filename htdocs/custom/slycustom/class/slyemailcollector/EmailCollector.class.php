<?php
/* Copyright (C) 2026 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    custom/slycustom/class/slyemailcollector/EmailCollector.class.php
 * \ingroup slyemailcollector
 * \brief   Polls a configured IMAP mailbox and routes credit notifications
 *          into the same wise_webhook/accepted/ bucket the Wise webhook
 *          writes to.
 *
 * Design (2.5.0):
 *
 *   1. cron/email_collector.php calls pullCron(0) every 5 minutes (the cron
 *      frequency set in modSlyCustom.class.php).
 *   2. For each enabled entity with `slyemail_collector_enabled=1`, pull
 *      unseen messages from the IMAP mailbox configured via
 *      slyemail_imap_host / _user / _pass / _port / _folder.
 *   3. For each unseen message: download raw .eml to
 *      documents/wise_webhook/email/<msgid>.eml, then run the registered
 *      EmailTemplate list until one matches.
 *   4. On match: write a main dump JSON to
 *      documents/wise_webhook/accepted/ with body=normalised event payload
 *      and sidecar `<basename>.meta.json` with status=ENRICHED (template
 *      already extracted the field set, so no further enrich is needed).
 *      Mark the IMAP message Seen.
 *   5. On no match: write the dump to wise_webhook/rejected/ with
 *      event_type=email.unmatched and sidecar status=NEW + note explaining
 *      which templates were tried. Mark Seen so the same message doesn't
 *      get retried forever.
 *
 * The class deliberately does NOT depend on Wise_IncomingPayment's
 * private helpers (writeDump etc.) — instead it talks to the same
 * filesystem layout (DOL_DATA_ROOT/wise_webhook/{accepted,rejected,email})
 * and sidecar convention, so the reconcile page renders both sources
 * uniformly.
 *
 * Extension: more templates can be added by implementing EmailTemplate
 * and pushing the instance into $templates (or, in 2.5.1+, a hook).
 */

require_once __DIR__.'/EmailTemplate.class.php';
require_once __DIR__.'/TemplateWise.php';

class EmailCollector
{
	/** @var DoliDB */
	private $db;
	/** @var Translate */
	private $langs;

	/**
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
		global $langs;
		if (!is_object($langs)) {
			$langs = new Translate('', $conf);
		}
		$this->langs = $langs;
	}

	/**
	 * Cron entry point. 0 = all enabled entities.
	 *
	 * @param  int $entityFilter
	 * @return array<string,int>  per-entity counts:
	 *                           ok, rejected, error, skipped
	 */
	public function pullCron($entityFilter = 0)
	{
		$entities = $this->resolveEntities((int) $entityFilter);
		$totals = array('ok' => 0, 'rejected' => 0, 'error' => 0, 'skipped' => 0);
		foreach ($entities as $entity) {
			$res = $this->pullOnce((int) $entity);
			foreach ($totals as $k => $_) $totals[$k] += isset($res[$k]) ? (int) $res[$k] : 0;
		}
		return $totals;
	}

	/**
	 * Pull once for one entity. Returns a counts array; logs any error
	 * to dol_syslog and continues.
	 *
	 * @param  int $entity
	 * @return array{ok:int,rejected:int,error:int,skipped:int}
	 */
	public function pullOnce($entity)
	{
		$entity = (int) $entity;
		$out = array('ok' => 0, 'rejected' => 0, 'error' => 0, 'skipped' => 0);

		if (!$this->isEnabledForEntity($entity)) {
			$out['skipped']++;
			return $out;
		}
		if (!function_exists('imap_open')) {
			dol_syslog('slyemailcollector: PHP imap extension missing, entity='.$entity, LOG_ERR);
			$out['error']++;
			return $out;
		}

		$host = $this->getConst('SLYEMAIL_IMAP_HOST', $entity);
		$user = $this->getConst('SLYEMAIL_IMAP_USER', $entity);
		$pass = $this->getConst('SLYEMAIL_IMAP_PASS', $entity);
		$port = (int) $this->getConst('SLYEMAIL_IMAP_PORT', $entity, 993);
		$folder = $this->getConst('SLYEMAIL_IMAP_FOLDER', $entity, 'INBOX');
		if ($host === '' || $user === '' || $pass === '') {
			dol_syslog('slyemailcollector: missing host/user/pass for entity='.$entity, LOG_WARNING);
			$out['skipped']++;
			return $out;
		}

		$connStr = '{'.$host.':'.$port.'/imap/ssl/novalidate-cert}'.$folder;
		$mbox = @imap_open($connStr, $user, $pass, 0, 1);
		if (!$mbox) {
			dol_syslog('slyemailcollector: imap_open failed for entity='.$entity.' err='.implode('; ', imap_errors() ?: array('unknown')), LOG_ERR);
			$out['error']++;
			return $out;
		}

		// unseen only — marks them as read on the wire
		$uids = imap_search($mbox, 'UNSEEN', SE_UID);
		if (!is_array($uids)) $uids = array();
		foreach ($uids as $uid) {
			$raw = imap_fetchbody($mbox, (int) $uid, '', FT_UID);
			if (!is_string($raw) || $raw === '') {
				$out['error']++;
				continue;
			}
			$r = $this->ingestMessage($raw, $entity, (int) $uid);
			if ($r['ok']) {
				$out['ok']++;
			} elseif ($r['rejected']) {
				$out['rejected']++;
			} else {
				$out['error']++;
			}
			// Mark Seen so the next pull doesn't double-process.
			imap_setflag_full($mbox, (string) $uid, '\\Seen', ST_UID);
		}
		imap_close($mbox);
		return $out;
	}

	/**
	 * Public hook for tests + the manual page: feed in a raw RFC822 .eml
	 * (or its body) and let the collector parse + write to disk. Returns
	 * the same shape as pullOnce's per-message result.
	 *
	 * @param  string $eml     raw RFC822 message (or just the body — we
	 *                         try imap_open-less parsing first)
	 * @param  int    $entity
	 * @param  int    $uid     optional IMAP UID, used for filenames
	 * @return array{ok:bool,rejected:bool,message:string,basename?:string,file?:string}
	 */
	public function ingestMessage($eml, $entity, $uid = 0)
	{
		$parsed = $this->parseEml((string) $eml);
		if (!is_object($parsed)) {
			return array('ok' => false, 'rejected' => false, 'message' => 'parse failed');
		}
		$entity = (int) $entity;
		$acceptedDir = $this->bucketDir('accepted', $entity);
		$rejectedDir = $this->bucketDir('rejected', $entity);
		$emailDir = $this->bucketDir('email', $entity);

		// Persist raw .eml first — this is the audit trail. Fail-fast
		// on disk write so we never silently drop a message.
		$msgId = isset($parsed->message_id) ? $this->sanitiseBasename((string) $parsed->message_id) : '';
		if ($msgId === '') $msgId = 'uid-'.(int) $uid.'-'.bin2hex(random_bytes(4));
		$emlPath = $emailDir.$msgId.'.eml';
		if (file_put_contents($emlPath, (string) $eml) === false) {
			return array('ok' => false, 'rejected' => false, 'message' => 'failed to write eml to '.$emailDir);
		}

		// Run template chain.
		$matched = null;
		$tried = array();
		foreach ($this->getTemplates() as $tpl) {
			$tried[] = $tpl->id();
			if ($tpl->matches($parsed)) {
				$matched = $tpl;
				break;
			}
		}

		$basename = $this->buildBasename('email', $entity);
		$occurredAt = isset($parsed->date) ? strtotime((string) $parsed->date) : 0;
		$receivedAt = time();
		if ($occurredAt === false || $occurredAt <= 0) $occurredAt = $receivedAt;

		$now = date('Y-m-d H:i:s');

		if ($matched === null) {
			$dump = array(
				'received_at' => $receivedAt,
				'entity' => $entity,
				'event_type' => 'email.unmatched',
				'body' => json_encode(array(
					'event_type' => 'email.unmatched',
					'tried_templates' => $tried,
					'subject' => isset($parsed->subject) ? (string) $parsed->subject : '',
					'from' => isset($parsed->from) ? (string) $parsed->from : '',
					'date' => isset($parsed->date) ? (string) $parsed->date : '',
				), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			);
			$dumpPath = $rejectedDir.$basename.'.json';
			$sidecar = $this->buildSidecar(array(
				'status' => 'NEW',
				'source' => 'email',
				'note' => 'no template matched (tried: '.implode(',', $tried).')',
				'tms' => $now,
			));
			$writeOk = $this->writeDumpAndSidecar($dumpPath, json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sidecar);
			if (!$writeOk) {
				return array('ok' => false, 'rejected' => false, 'message' => 'rejected write failed');
			}
			return array('ok' => true, 'rejected' => true, 'message' => 'no template matched', 'basename' => $basename, 'file' => $dumpPath);
		}

		try {
			$payload = $matched->extract($parsed);
		} catch (Exception $e) {
			$dump = array(
				'received_at' => $receivedAt,
				'entity' => $entity,
				'event_type' => 'email.unmatched',
				'body' => json_encode(array(
					'event_type' => 'email.unmatched',
					'template' => $matched->id(),
					'error' => $e->getMessage(),
				), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			);
			$dumpPath = $rejectedDir.$basename.'.json';
			$sidecar = $this->buildSidecar(array(
				'status' => 'NEW',
				'source' => 'email',
				'note' => 'template '.$matched->id().' failed: '.$e->getMessage(),
				'tms' => $now,
			));
			$this->writeDumpAndSidecar($dumpPath, json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sidecar);
			return array('ok' => true, 'rejected' => true, 'message' => $e->getMessage(), 'basename' => $basename, 'file' => $dumpPath);
		}

		$body = array(
			'event_type' => 'email.notification',
			'template_id' => $matched->id(),
			'data' => $payload,
		);
		$dump = array(
			'received_at' => $receivedAt,
			'entity' => $entity,
			'event_type' => 'email.notification',
			'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		);
		$dumpPath = $acceptedDir.$basename.'.json';
		$enriched = array(
			'ref_text' => isset($payload['ref_text']) ? (string) $payload['ref_text'] : '',
			'counterparty' => isset($payload['counterparty']) ? (string) $payload['counterparty'] : '',
			'fees' => isset($payload['fees']) ? (float) $payload['fees'] : null,
			'fk_soc' => 0,
		);
		$sidecar = $this->buildSidecar(array(
			'status' => 'ENRICHED',
			'source' => 'email',
			'note' => '',
			'event_type' => 'email.notification',
			'occurrence_count' => 1,
			'enriched' => $enriched,
			'tms' => $now,
		));
		$writeOk = $this->writeDumpAndSidecar($dumpPath, json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $sidecar);
		if (!$writeOk) {
			return array('ok' => false, 'rejected' => false, 'message' => 'accepted write failed');
		}
		return array('ok' => true, 'rejected' => false, 'message' => 'ingested via '.$matched->id(), 'basename' => $basename, 'file' => $dumpPath);
	}

	/**
	 * Template list. Keep this method as the single extension point for
	 * new templates. 2.5.1+ can read additional templates from
	 * dolibarr_get_const('SLYEMAIL_TEMPLATES', $entity) and
	 * include_once them.
	 *
	 * @return EmailTemplate[]
	 */
	public function getTemplates()
	{
		return array(
			new TemplateWise(),
		);
	}

	/**
	 * Whether the collector is enabled for the given entity. Falls
	 * back to the global constant (entity 0).
	 *
	 * @param  int $entity
	 * @return bool
	 */
	public function isEnabledForEntity($entity)
	{
		$flag = $this->getConst('SLYEMAIL_COLLECTOR_ENABLED', (int) $entity, '');
		if ($flag === '' || $flag === '0' || $flag === 'false') {
			// fall back to global
			$flag = $this->getConst('SLYEMAIL_COLLECTOR_ENABLED', 0, '');
		}
		return ($flag === '1' || $flag === 'true');
	}

	/**
	 * Entities to iterate. entity=0 means "all enabled".
	 *
	 * @param  int $entityFilter
	 * @return int[]
	 */
	private function resolveEntities($entityFilter)
	{
		if ((int) $entityFilter > 0) {
			return array((int) $entityFilter);
		}
		// In a multi-company install the user has $conf->entity set. We
		// iterate over all entities the current user can see — for the
		// cron job that's typically just the active one. To keep things
		// simple and match the previous webhook behavior, we use the
		// currently active entity plus, when MULTICOMPANY is on, the
		// list of entities the user has rights on. The cron is supposed
		// to be configured per-entity anyway.
		global $conf, $user;
		$entities = array();
		if (is_object($conf) && isset($conf->entity) && (int) $conf->entity > 0) {
			$entities[] = (int) $conf->entity;
		}
		// Allow a multi-entity sweep by listing entities from the
		// llx_societe / llx_user_param table; but for the MVP we just
		// keep "active entity only" to keep the cron safe and
		// idempotent.
		return $entities;
	}

	/**
	 * Minimal RFC822 parser — pulls Subject, From, Date, Message-ID and
	 * a plain-text body. We don't need full MIME: Wise notifications
	 * arrive as text/plain and our templates are text-only.
	 *
	 * @param  string $eml
	 * @return stdClass|null
	 */
	private function parseEml($eml)
	{
		if ($eml === '') return null;
		$lines = preg_split("/\r\n|\n|\r/", $eml);
		$headerDone = false;
		$headers = array();
		$body = '';
		$current = '';
		foreach ($lines as $ln) {
			if (!$headerDone) {
				if ($ln === '') {
					$headerDone = true;
					continue;
				}
				// header continuation: starts with whitespace
				if (preg_match('/^\s+/', $ln) && $current !== '') {
					$headers[$current] .= ' '.trim($ln);
				} elseif (preg_match('/^([A-Za-z0-9\-]+)\s*:\s*(.*)$/', $ln, $m)) {
					$current = strtolower($m[1]);
					$headers[$current] = $m[2];
				}
			} else {
				$body .= $ln."\n";
			}
		}

		if (empty($headers)) return null;
		$o = new stdClass();
		$o->subject = isset($headers['subject']) ? $this->decodeHeader($headers['subject']) : '';
		$o->from = isset($headers['from']) ? $this->decodeHeader($headers['from']) : '';
		$o->date = isset($headers['date']) ? trim($headers['date']) : '';
		$o->message_id = isset($headers['message-id']) ? trim($headers['message-id']) : '';
		$o->body = $this->stripMimeText($body);
		return $o;
	}

	/**
	 * Decode RFC2047 encoded-words (=?UTF-8?B?...?= etc.). 2.5.0 keeps
	 * this dependency-free; we ship our own micro-decoder to avoid
	 * pulling mb_decode_mimeheader when it's not available.
	 *
	 * @param  string $h
	 * @return string
	 */
	private function decodeHeader($h)
	{
		$h = (string) $h;
		$out = preg_replace_callback('/=\?([^?]+)\?([qb])\?([^?]*)\?=/i', function ($m) {
			$enc = strtoupper($m[1]);
			$mode = strtolower($m[2]);
			$data = $m[3];
			$decoded = '';
			if ($mode === 'b') {
				$decoded = base64_decode($data, true);
				if ($decoded === false) $decoded = $data;
			} else {
				$decoded = str_replace('_', ' ', quoted_printable_decode($data));
			}
			if (function_exists('mb_convert_encoding') && strtoupper($enc) !== 'UTF-8') {
				$decoded = mb_convert_encoding($decoded, 'UTF-8', $enc);
			}
			return $decoded;
		}, $h);
		return trim((string) $out);
	}

	/**
	 * If the body is multipart, take the text/plain part. Otherwise
	 * return as-is.
	 *
	 * @param  string $body
	 * @return string
	 */
	private function stripMimeText($body)
	{
		if (stripos($body, 'Content-Type: text/plain') === false) {
			return $body;
		}
		// Multi-part: find the first boundary, take the chunk between
		// the first and second boundary, strip its inner headers.
		if (!preg_match('/boundary="?([A-Za-z0-9_\-]+)"?/i', $body, $bm)) {
			return $body;
		}
		$boundary = $bm[1];
		$parts = explode('--'.$boundary, $body);
		foreach ($parts as $p) {
			if (stripos($p, 'Content-Type: text/plain') !== false) {
				$idx = strpos($p, "\r\n\r\n");
				if ($idx === false) $idx = strpos($p, "\n\n");
				if ($idx !== false) {
					return trim(substr($p, $idx + (strpos($p, "\r\n\r\n") !== false ? 4 : 2)));
				}
				return trim($p);
			}
		}
		return $body;
	}

	/**
	 * Resolve DOL_DATA_ROOT/wise_webhook/<bucket>/ with entity subdir.
	 * We use a per-entity subdir so multi-company installs don't
	 * share credit notifications across entities.
	 *
	 * @param  string $bucket  accepted|rejected|email
	 * @param  int    $entity
	 * @return string  absolute path, trailing /
	 */
	private function bucketDir($bucket, $entity)
	{
		global $conf;
		$root = DOL_DATA_ROOT;
		if (is_object($conf) && isset($conf->smart_entity_subdir) && $conf->smart_entity_subdir > 0 && (int) $entity > 0) {
			$root .= '/'.(int) $entity;
		}
		$path = $root.'/wise_webhook/'.$bucket.'/';
		if (!is_dir($path)) {
			@mkdir($path, 0775, true);
		}
		return $path;
	}

	/**
	 * Basename pattern shared with WiseWebhook for parity:
	 *   Ymd-His-eN-md5(raw)[0:8].json
	 * (e = entity, the same convention wise.php uses)
	 *
	 * @param  string $kind  webhook|email
	 * @param  int    $entity
	 * @return string  filename WITHOUT extension
	 */
	private function buildBasename($kind, $entity)
	{
		$seed = $kind.'-'.(int) $entity.'-'.microtime(true).'-'.bin2hex(random_bytes(4));
		return date('Ymd-His').'-e'.(int) $entity.'-'.substr(md5($seed), 0, 8);
	}

	/**
	 * Atomic dump + sidecar write. sidecar is written AFTER the main
	 * dump so a sidecar without its dump is harmless (reconcile renders
	 * the dump regardless of sidecar presence).
	 *
	 * @param  string $dumpPath  absolute
	 * @param  string $dumpJson
	 * @param  string $sidecarJson
	 * @return bool
	 */
	private function writeDumpAndSidecar($dumpPath, $dumpJson, $sidecarJson)
	{
		// main dump
		$tmp = $dumpPath.'.tmp';
		if (file_put_contents($tmp, $dumpJson) === false) {
			dol_syslog('slyemailcollector: failed to write tmp dump '.$tmp, LOG_ERR);
			return false;
		}
		if (!@rename($tmp, $dumpPath)) {
			@unlink($tmp);
			return false;
		}
		// sidecar
		$sidecarPath = preg_replace('/\.json$/', '.meta.json', $dumpPath);
		$tmpS = $sidecarPath.'.tmp';
		if (file_put_contents($tmpS, $sidecarJson) === false) {
			dol_syslog('slyemailcollector: sidecar tmp write failed for '.$sidecarPath, LOG_WARNING);
			return true; // main dump already written, sidecar is best-effort
		}
		if (!@rename($tmpS, $sidecarPath)) {
			@unlink($tmpS);
			return true;
		}
		return true;
	}

	/**
	 * Build the sidecar JSON. We always write a JSON object — empty
	 * subobjects use {} so the file parses cleanly.
	 *
	 * @param  array $overrides
	 * @return string
	 */
	private function buildSidecar(array $overrides)
	{
		$base = array(
			'status' => 'NEW',
			'source' => 'email',
			'note' => '',
			'event_type' => '',
			'occurrence_count' => 1,
			'enriched' => new stdClass(),
			'match' => new stdClass(),
			'fk_paiement' => 0,
			'fk_bank' => 0,
			'tms' => date('Y-m-d H:i:s'),
		);
		$merged = array_merge($base, $overrides);
		// Encode empty stdClass as {}: json_encode turns it into []
		// otherwise.
		$json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$json = str_replace('"enriched":[]', '"enriched":{}', $json);
		$json = str_replace('"match":[]', '"match":{}', $json);
		return $json;
	}

	/**
	 * Const getter that handles the function_exists dolibarr_get_const
	 * guard (admin.lib.php is the only place it lives; pages in
	 * custom/ don't always autoload it).
	 *
	 * @param  string $name
	 * @param  int    $entity
	 * @param  string $default
	 * @return string
	 */
	private function getConst($name, $entity, $default = '')
	{
		$val = '';
		if (function_exists('dolibarr_get_const')) {
			$val = dolibarr_get_const($this->db, $name, $entity);
		} else {
			// SQL fallback: read directly from llx_const.
			$sql = 'SELECT value FROM '.MAIN_DB_PREFIX.'const WHERE name = '.$this->db->escape($name);
			$sql .= ' AND entity IN ('.(int) $entity.', 0) ORDER BY entity DESC LIMIT 1';
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) $val = (string) $obj->value;
				$this->db->free($resql);
			}
		}
		return $val !== null && $val !== false ? (string) $val : $default;
	}

	/**
	 * Make a string safe for use as part of a filesystem path. We
	 * strip the angle brackets Message-ID uses and any non-alnum
	 * characters outside [A-Za-z0-9._-].
	 *
	 * @param  string $s
	 * @return string
	 */
	private function sanitiseBasename($s)
	{
		$s = trim((string) $s, " <>\t\r\n");
		$s = preg_replace('/[^A-Za-z0-9._\-]/', '_', $s);
		if ($s === '' || $s === null) $s = 'msg-'.bin2hex(random_bytes(4));
		return substr((string) $s, 0, 80);
	}
}
