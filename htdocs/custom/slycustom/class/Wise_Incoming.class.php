<?php

/**
 * \file    custom/slycustom/class/Wise_Incoming.class.php
 * \ingroup slycustom
 * \brief   Wise incoming payments: webhook ingestion, statement enrichment,
 *          SO-reference matching and Dolibarr payment recording.
 *
 * Flow (see webhook/wise.php for the receiver):
 *   1. balances#credit webhook -> event stored (md5 dedupe) + incoming row (NEW)
 *      The v2.0.0 credit payload carries NO payment reference, only
 *      amount / currency / occurred_at / balance id.
 *   2. enrichPending() pulls the balance statement around occurred_at via the
 *      Wise API to recover the payer-entered reference (usually the SO number),
 *      counterparty and fees -> status ENRICHED.
 *   3. findInvoiceCandidates(): SO ref -> llx_commande -> llx_element_element
 *      -> open customer invoices; fallback amount+currency+date window.
 *   4. A human works the queue on the reconcile page (wise/reconcile.php).
 *      The allocation can be parked as a draft first (saveDraft -> status
 *      PREPARED, stored in match_data['allocation'], no accounting impact),
 *      then confirmed later: recordPayment() creates the Paiement + bank line
 *      and closes invoices (-> RECORDED). The draft is validated with exactly
 *      the same rules as the recording, so a draft that saves is a draft that
 *      records.
 *
 * Timestamps in Wise tables are stored in UTC (Wise sends ISO 8601 Z);
 * Dolibarr business dates are converted with server TZ at recording time only.
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
	/**
	 * Allocation saved by a human but NOT yet posted: no Paiement, no bank
	 * line, no invoice closed. The draft lives in match_data['allocation'] so
	 * the credit can sit in the queue for review without any accounting impact.
	 */
	const STATUS_PREPARED = 'PREPARED';
	const STATUS_RECORDED = 'RECORDED';
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

	/**
	 * Ingest a verified webhook envelope: store the event (dedupe on raw-body
	 * md5) and queue balances#credit deliveries into the incoming table.
	 *
	 * Multicompany: when the payload carries a real resource.profile_id, the
	 * entity is resolved by matching it against the per-entity
	 * WISE_PROFILE_ID constants, so several entities can share one webhook
	 * URL. Falls back to the receiver-resolved entity (WISE_WEBHOOK_ENTITY).
	 *
	 * @param  DoliDB $db      Database handler
	 * @param  int    $entity  Company entity (receiver default)
	 * @param  array  $body    Decoded envelope (data/event_type/...)
	 * @param  string $raw     Raw request body (hash source)
	 * @param  array  $meta    delivery_id, is_test, remote_ip
	 * @return array  array('event_id' => int, 'duplicate' => bool, 'incoming_id' => int|null, 'error' => string|null)
	 */
	public static function createFromWebhook($db, $entity, array $body, $raw, array $meta)
	{
		global $conf;

		$eventType = isset($body['event_type']) && is_string($body['event_type']) ? $body['event_type'] : '';
		$subscriptionId = isset($body['subscription_id']) && is_string($body['subscription_id']) ? $body['subscription_id'] : '';
		$schemaVersion = isset($body['schema_version']) && is_string($body['schema_version']) ? $body['schema_version'] : '';
		$data = isset($body['data']) && is_array($body['data']) ? $body['data'] : array();
		$isTest = !empty($meta['is_test']) || $subscriptionId === self::ZERO_UUID;

		// Resolve the owning entity from the payload profile id when possible.
		$resource0 = isset($data['resource']) && is_array($data['resource']) ? $data['resource'] : array();
		if (isset($resource0['profile_id']) && (int) $resource0['profile_id'] > 0) {
			$payloadProfileId = (int) $resource0['profile_id'];
			$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.'const';
			$sql .= " WHERE name = 'WISE_PROFILE_ID' AND value = '".$db->escape((string) $payloadProfileId)."' AND entity >= 1";
			$resql = $db->query($sql);
			if ($resql) {
				$obj = $db->fetch_object($resql);
				$db->free($resql);
				if ($obj && (int) $obj->entity !== (int) $entity) {
					dol_syslog('wise_webhook entity routed by profile_id to entity='.(int) $obj->entity.' (was '.(int) $entity.')', LOG_INFO);
					$entity = (int) $obj->entity;
				}
			}
		}

		$md5 = md5((string) $raw);

		// Dedupe: same event body re-delivered by Wise retries.
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'slycustom_wise_event WHERE payload_md5 = \''.$db->escape($md5).'\'';
		$resql = $db->query($sql);
		if (!$resql) {
			return array('event_id' => 0, 'duplicate' => false, 'incoming_id' => null, 'error' => 'event select: '.$db->lasterror);
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if ($obj) {
			return array('event_id' => (int) $obj->rowid, 'duplicate' => true, 'incoming_id' => null, 'error' => null);
		}

		$occurredAt = self::isoToSqlUtc(isset($data['occurred_at']) ? $data['occurred_at'] : '');
		$sentAt = self::isoToSqlUtc(isset($body['sent_at']) ? $body['sent_at'] : '');

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'slycustom_wise_event';
		$sql .= ' (entity, event_type, subscription_id, schema_version, delivery_id, is_test, occurred_at, sent_at, payload_md5, payload_json, processing_note, date_creation)';
		$sql .= ' VALUES ('.(int) $entity.', \''.$db->escape($eventType).'\', \''.$db->escape($subscriptionId).'\', \''.$db->escape($schemaVersion).'\',';
		$sql .= ' \''.$db->escape(isset($meta['delivery_id']) ? $meta['delivery_id'] : '').'\', '.($isTest ? 1 : 0).',';
		$sql .= ($occurredAt ? '\''.$db->escape($occurredAt).'\'' : 'NULL').',';
		$sql .= ($sentAt ? '\''.$db->escape($sentAt).'\'' : 'NULL').',';
		$sql .= ' \''.$md5.'\', \''.$db->escape($raw).'\',';
		// An unverified delivery (no verification_key.pem) is still trusted enough
		// to ingest when the operator opted in (IPCHECK on or ENFORCE_SIG=0), but
		// every reconciler on the queue page should know.
		$procNote = !empty($meta['unverified']) ? 'Ingested from an unverified bootstrap delivery (no RSA signature check).' : '';
		$sql .= ' '.(empty($meta['unverified']) ? 'NULL' : '\''.($procNote !== '' ? $db->escape(substr($procNote, 0, 250)) : '').'\'').',';
		$sql .= ' \''.$db->idate(dol_now()).'\')';
		$resql = $db->query($sql);
		if (!$resql) {
			return array('event_id' => 0, 'duplicate' => false, 'incoming_id' => null, 'error' => 'event insert: '.$db->lasterror);
		}
		$eventId = (int) $db->last_insert_id(MAIN_DB_PREFIX.'slycustom_wise_event');

		$incomingId = null;
		$txnType = isset($data['transaction_type']) && is_string($data['transaction_type']) ? strtolower($data['transaction_type']) : '';
		$isCredit = ($eventType === 'balances#credit' || ($eventType === 'balances#update' && $txnType === 'credit'));
		$isTransferState = ($eventType === 'transfers#state-change');
		if (!$isTest && $isTransferState) {
			// Flow A write-back: update the transfer mapping; when the money
			// leaves Wise, record the supplier payment automatically. Wise_Payment
			// is required lazily to keep the webhook usable standalone.
			require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Payment.class.php';
			$wiseTransferId = isset($resource0['id']) ? (int) $resource0['id'] : 0;
			$state = isset($data['current_state']) && is_string($data['current_state']) ? $data['current_state'] : '';
			if ($wiseTransferId > 0 && $state !== '') {
				$op = new WiseOutgoingPayment($db);
				$newStatus = $op->applyTransferState($wiseTransferId, $state, $occurredAt);
				if ($newStatus === WiseOutgoingPayment::STATUS_SENT) {
					// Resolve a user for the bookkeeping write: invoice author, else first admin
					$sql = 'SELECT t.rowid, t.entity FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer t WHERE t.wise_transfer_id = '.(int) $wiseTransferId.' ORDER BY t.rowid DESC LIMIT 1';
					$resql2 = $db->query($sql);
					$transferRowId = 0;
					$transferEntity = 0;
					if ($resql2) {
						$obj2 = $db->fetch_object($resql2);
						if ($obj2) {
							$transferRowId = (int) $obj2->rowid;
							$transferEntity = (int) $obj2->entity;
						}
						$db->free($resql2);
					}
					if ($transferRowId > 0) {
						require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
						$bookUser = null;
						$sql = 'SELECT u.rowid FROM '.MAIN_DB_PREFIX.'user AS u';
						$sql .= ' JOIN '.MAIN_DB_PREFIX.'facture_fourn AS f ON f.fk_user_author = u.rowid AND f.rowid =';
						$sql .= ' (SELECT fk_facture_fourn FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer WHERE rowid = '.$transferRowId.')';
						$resql2 = $db->query($sql);
						if ($resql2) {
							$obj2 = $db->fetch_object($resql2);
							if ($obj2) {
								$bookUser = new User($db);
								$bookUser->fetch((int) $obj2->rowid);
							}
							$db->free($resql2);
						}
						if (!is_object($bookUser) || empty($bookUser->id)) {
							// Fallback admin must belong to the transfer's company (superadmins live in entity 0)
							$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin = 1 AND statut = 1'
								.' AND entity IN (0, '.(int) $transferEntity.')'
								.' ORDER BY (entity = '.(int) $transferEntity.') DESC, rowid ASC LIMIT 1';
							$resql2 = $db->query($sql);
							if ($resql2) {
								$obj2 = $db->fetch_object($resql2);
								if ($obj2) {
									$bookUser = new User($db);
									$bookUser->fetch((int) $obj2->rowid);
								}
								$db->free($resql2);
							}
						}
						if (is_object($bookUser) && !empty($bookUser->id)) {
							// The payment must land in the transfer's own company: $conf may
							// still point at the webhook receiver entity, while
							// PaiementFourn::create writes entity = $conf->entity.
							$prevEntity = (int) $conf->entity;
							if ($transferEntity >= 1 && $transferEntity !== $prevEntity) {
								$conf->setEntityValues($db, $transferEntity);
							}
							$pid = $op->recordSupplierPayment($transferRowId, $bookUser);
							if ((int) $conf->entity !== $prevEntity) {
								$conf->setEntityValues($db, $prevEntity);
							}
							dol_syslog('wise_webhook auto supplier payment for transfer '.$wiseTransferId.' result='.$pid.' user='.$bookUser->id.' entity='.$transferEntity, LOG_INFO);
						} else {
							dol_syslog('wise_webhook no user resolvable for auto payment, transfer row '.$transferRowId.' stays SENT', LOG_WARNING);
						}
					}
				}
			}
			// Event stored, no queue row for outgoing
			return array('event_id' => $eventId, 'duplicate' => false, 'incoming_id' => null, 'error' => null);
		}
		if (!$isTest && $isCredit) {
			if (!self::isIncomingEnabled($db, $entity)) {
				// Feature toggle OFF: keep the event (audit) but do not queue the credit.
				dol_syslog('wise_webhook incoming queue skipped: WISE_INCOMING_ENABLED=0 entity='.$entity, LOG_INFO);
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_event';
				$sql .= " SET processing_note = 'Incoming flow disabled (WISE_INCOMING_ENABLED=0)'";
				$sql .= ' WHERE rowid = '.$eventId;
				$db->query($sql);
				return array('event_id' => $eventId, 'duplicate' => false, 'incoming_id' => null, 'error' => null);
			}
			// Near-duplicate guard: the statement-fallback sync may already have
			// queued this credit (e.g. the webhook is a late retry).
			$resource = isset($data['resource']) && is_array($data['resource']) ? $data['resource'] : array();
			$creditCurrency = isset($data['currency']) ? strtoupper((string) $data['currency']) : '';
			$creditAmount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
			if ($occurredAt && $creditCurrency !== '') {
				$checker = new self($db);
				if ($checker->incomingRowExists($entity, $creditCurrency, $creditAmount, $occurredAt)) {
					dol_syslog('wise_webhook credit already queued by statement sync, skipping entity='.$entity, LOG_INFO);
					$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_event';
					$sql .= " SET processing_note = 'Credit already queued by statement sync'";
					$sql .= ' WHERE rowid = '.$eventId;
					$db->query($sql);
					return array('event_id' => $eventId, 'duplicate' => false, 'incoming_id' => null, 'error' => null);
				}
			}
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
			$sql .= ' (entity, fk_event, wise_balance_id, wise_profile_id, currency, amount, occurred_at, post_balance, status, date_creation)';
			$sql .= ' VALUES ('.(int) $entity.', '.$eventId.',';
			$sql .= ' '.(isset($resource['id']) ? (int) $resource['id'] : 'NULL').',';
			// The delivering profile is kept on the row: balance ids are scoped to
			// their profile, so the statement API must be queried with the profile
			// the credit actually landed in, not the configured default.
			$sql .= ' '.(isset($resource0['profile_id']) ? (int) $resource0['profile_id'] : 'NULL').',';
			$sql .= ' \''.$db->escape(isset($data['currency']) ? strtoupper((string) $data['currency']) : '').'\',';
			$sql .= ' '.(isset($data['amount']) ? (float) $data['amount'] : 0).',';
			$sql .= ($occurredAt ? '\''.$db->escape($occurredAt).'\'' : 'NULL').',';
			$sql .= (isset($data['post_transaction_balance_amount']) ? (float) $data['post_transaction_balance_amount'] : 'NULL').',';
			$sql .= ' \''.self::STATUS_NEW.'\', \''.$db->idate(dol_now()).'\')';
			$resql = $db->query($sql);
			if (!$resql) {
				return array('event_id' => $eventId, 'duplicate' => false, 'incoming_id' => null, 'error' => 'incoming insert: '.$db->lasterror);
			}
			$incomingId = (int) $db->last_insert_id(MAIN_DB_PREFIX.'slycustom_wise_incoming');
		}

		return array('event_id' => $eventId, 'duplicate' => false, 'incoming_id' => $incomingId, 'error' => null);
	}

	/**
	 * Enrich NEW incoming rows with statement details (reference, counterparty, fees).
	 * Safe to call repeatedly; rows whose statement is not available yet stay NEW.
	 *
	 * @param  int $entity Company entity
	 * @param  int $limit  Max rows per call
	 * @return array array('enriched' => n, 'kept' => n, 'errors' => n)
	 */
	public function enrichPending($entity, $limit = 10)
	{
		$stats = array('enriched' => 0, 'kept' => 0, 'errors' => 0);

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
		$sql .= ' WHERE entity = '.(int) $entity." AND status = '".self::STATUS_NEW."'";
		$sql .= ' ORDER BY rowid ASC LIMIT '.max(1, (int) $limit);
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('Wise_Incoming enrichPending select failed: '.$this->db->lasterror, LOG_ERR);
			return $stats;
		}
		$ids = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ids[] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		foreach ($ids as $id) {
			$result = $this->enrichRow($id);
			if ($result === true) {
				$stats['enriched']++;
			} elseif ($result === false) {
				$stats['errors']++;
			} else {
				$stats['kept']++; // null = not available yet, retry next run
			}
		}

		return $stats;
	}

	/**
	 * Enrich one incoming row from the balance statement around occurred_at.
	 *
	 * @param  int $rowid llx_slycustom_wise_incoming.rowid
	 * @return bool|null  true=enriched, false=error, null=statement/txn not available yet
	 */
	public function enrichRow($rowid)
	{
		$row = $this->fetchRow($rowid);
		if (!$row) {
			return false;
		}
		if ($row['status'] !== self::STATUS_NEW) {
			return true; // already enriched or terminal
		}

		$api = Wise_API::fromEntity($this->db, (int) $row['entity']);
		$profileId = self::getProfileIdForEntity($this->db, (int) $row['entity']);
		if (!$api || $profileId === '') {
			$this->setNote($rowid, 'Enrichment skipped: WISE_API_TOKEN / WISE_PROFILE_ID not configured.');
			return false;
		}
		// Prefer the profile the credit was delivered into: balance ids are only
		// valid under their own profile, and a Wise account can hold several
		// (per currency, per region). Statement rows created by the cron fallback
		// carry the configured profile, since that is where they were read from.
		$rowProfileId = isset($row['wise_profile_id']) ? (int) $row['wise_profile_id'] : 0;
		if ($rowProfileId > 0) {
			$profileId = $rowProfileId;
		}

		$balanceId = (int) $row['wise_balance_id'];
		if ($balanceId <= 0) {
			$balanceId = $api->findBalanceIdForCurrency((int) $profileId, $row['currency']);
			if (!$balanceId) {
				$this->setNote($rowid, 'Enrichment failed: no Wise balance found for currency '.$row['currency']);
				return false;
			}
		}

		// Statement window: +/- 5 minutes around the credit.
		$ts = self::sqlUtcToTimestamp($row['occurred_at']);
		if (!$ts) {
			$this->setNote($rowid, 'Enrichment failed: unparsable occurred_at.');
			return false;
		}
		$startIso = gmdate('Y-m-d\TH:i:s\Z', $ts - 300);
		$endIso = gmdate('Y-m-d\TH:i:s\Z', $ts + 300);

		$result = $api->getStatementTransactions((int) $profileId, $balanceId, $startIso, $endIso);
		if (isset($result['error'])) {
			$this->setNote($rowid, 'Statement lookup failed: '.substr((string) $result['error'], 0, 200));
			return null; // API problem: retry later, not a permanent error
		}

		// Find the matching CREDIT transaction: same amount, closest time.
		$best = null;
		$bestDelta = null;
		$matchCount = 0;
		foreach ($result['transactions'] as $txn) {
			if (!is_array($txn) || !isset($txn['type']) || strtoupper((string) $txn['type']) !== 'CREDIT') {
				continue;
			}
			$txnAmount = isset($txn['totalValue']) ? (float) $txn['totalValue'] : (isset($txn['amount']) ? (float) $txn['amount'] : null);
			if ($txnAmount === null || abs($txnAmount - (float) $row['amount']) > 0.0001) {
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
			// Statement may lag behind the webhook; leave NEW for a retry.
			dol_syslog('Wise_Incoming enrichRow no matching statement txn for row '.$rowid, LOG_INFO);
			return null;
		}

		$details = isset($best['details']) && is_array($best['details']) ? $best['details'] : array();
		// Tolerant extraction: field names observed vary across statement versions
		// (ShipsGo lesson: never trust a single assumed schema).
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

		$note = ($matchCount > 1 ? 'Ambiguity: '.$matchCount.' statement transactions matched, closest kept. ' : '');

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming SET';
		// Re-enriching a row that carries a draft must not knock it back to
		// ENRICHED: the draft stays the row's state until it is recorded or
		// explicitly discarded.
		$current = $this->fetchRow($rowid);
		$targetStatus = (!empty($current) && $current['status'] === self::STATUS_PREPARED) ? self::STATUS_PREPARED : self::STATUS_ENRICHED;
		$sql .= " status = '".$targetStatus."'";
		$sql .= ", ref_text = ".($refText !== '' ? '\''.$this->db->escape(substr($refText, 0, 250)).'\'' : "''");
		$sql .= ", counterparty = ".($counterparty !== '' ? '\''.$this->db->escape(substr($counterparty, 0, 250)).'\'' : "''");
		$sql .= ", fees = ".($fees !== null ? (float) $fees : 'NULL');
		$sql .= ", wise_balance_id = ".$balanceId;
		$sql .= ", statement_txn_json = '".$this->db->escape(json_encode($best))."'";
		$sql .= ", note_private = CONCAT(".$this->quoteOrNull($note).", IFNULL(note_private, ''))";
		$sql .= ' WHERE rowid = '.(int) $rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Incoming enrichRow update failed: '.$this->db->lasterror, LOG_ERR);
			return false;
		}

		// Snapshot the invoice candidates computed from the recovered reference.
		// A draft saved by a human is carried over untouched — re-enriching must
		// never wipe an allocation that is waiting for confirmation.
		$row = $this->fetchRow($rowid);
		$candidates = $this->findInvoiceCandidates($row);
		if (!empty($row['match_data'])) {
			$decoded = json_decode($row['match_data'], true);
			if (is_array($decoded) && !empty($decoded['allocation'])) {
				$candidates['allocation'] = $decoded['allocation'];
			}
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming SET';
		$sql .= " match_data = '".$this->db->escape(json_encode($candidates))."'";
		$sql .= ', fk_soc = '.(!empty($candidates['soc']['id']) ? (int) $candidates['soc']['id'] : 'NULL');
		$sql .= ' WHERE rowid = '.(int) $rowid;
		$this->db->query($sql);

		dol_syslog('Wise_Incoming enriched row '.$rowid.' ref='.($refText ?: '(empty)').' counterparty='.($counterparty ?: '(empty)'), LOG_INFO);
		return true;
	}

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

	/** Permissive default pattern (used when no override and no derivable mask) */
	const DEFAULT_SO_PATTERN = '/\b([A-Z]{0,4}[0-9]{2,5}[-\/]?[0-9]{1,6})\b/i';

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

	/**
	 * Compute invoice candidates for an incoming payment row.
	 *
	 * Strategy (business rule: payers quote the SO number, not the invoice ref):
	 *   1. Extract SO refs from ref_text -> llx_commande (exact, then separator-insensitive)
	 *   2. SO -> invoices via llx_element_element, keep open standard invoices
	 *   3. Fallback when no SO matched: open invoices matching amount+currency+/-45 days
	 *
	 * @param  array $row Incoming row (associative)
	 * @return array array('mode'=>'so'|'amount'|'none', 'soc'=>..., 'sos'=>..., 'invoices'=>...)
	 */
	public function findInvoiceCandidates($row)
	{
		global $conf;

		$entity = (int) $row['entity'];
		$amount = (float) $row['amount'];
		$currency = (string) $row['currency'];
		$out = array('mode' => 'none', 'soc' => null, 'sos' => array(), 'invoices' => array());

		// --- 1. SO refs -> orders -------------------------------------------------
		$sos = array();
		$refs = $this->extractSoRefs($row['ref_text'], (int) $row['entity']);
		if (!empty($refs)) {
			$in = array();
			foreach ($refs as $r) {
				$in[] = '\''.$this->db->escape($r).'\'';
			}
			$sql = 'SELECT rowid, ref, ref_client, fk_soc FROM '.MAIN_DB_PREFIX.'commande';
			$sql .= ' WHERE entity = '.$entity.' AND ref IN ('.implode(',', $in).')';
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$sos[(int) $obj->rowid] = array(
						'id' => (int) $obj->rowid,
						'ref' => $obj->ref,
						'ref_client' => $obj->ref_client,
						'socid' => (int) $obj->fk_soc,
					);
				}
				$this->db->free($resql);
			}
			if (empty($sos)) {
				// Separator-insensitive match: SO2501-0012 vs SO25010012 vs SO2501_0012
				$in = array();
				foreach ($refs as $r) {
					$in[] = '\''.$this->db->escape($r).'\'';
				}
				$sql = 'SELECT rowid, ref, ref_client, fk_soc FROM '.MAIN_DB_PREFIX.'commande';
				$sql .= ' WHERE entity = '.$entity." AND REPLACE(UPPER(REPLACE(ref, '_', '-')), '-', '') IN (".implode(',', $in).')';
				$resql = $this->db->query($sql);
				if ($resql) {
					while ($obj = $this->db->fetch_object($resql)) {
						$sos[(int) $obj->rowid] = array(
							'id' => (int) $obj->rowid,
							'ref' => $obj->ref,
							'ref_client' => $obj->ref_client,
							'socid' => (int) $obj->fk_soc,
						);
					}
					$this->db->free($resql);
				}
			}
		}

		// --- 2. SO -> open invoices -----------------------------------------------
		if (!empty($sos)) {
			$soIds = implode(',', array_keys($sos));
			$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.multicurrency_code, f.datef,';
			$sql .= " CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN f.multicurrency_total_ttc ELSE f.total_ttc END AS total_cur,";
			$sql .= " (SELECT COALESCE(SUM(CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN pf.multicurrency_amount ELSE pf.amount END), 0) FROM ".MAIN_DB_PREFIX.'paiement_facture pf WHERE pf.fk_facture = f.rowid) AS paid';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'facture f';
			$sql .= ' WHERE f.entity = '.$entity.' AND f.paye = 0 AND f.fk_statut IN (1, 2) AND f.type = 0';
			$sql .= ' AND f.rowid IN (SELECT fk_target FROM '.MAIN_DB_PREFIX.'element_element WHERE sourcetype = \'commande\' AND fk_source IN ('.$soIds.') AND targettype = \'facture\')';
			$sql .= ' ORDER BY f.datef ASC';
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$invCur = !empty($obj->multicurrency_code) ? strtoupper($obj->multicurrency_code) : strtoupper((string) $conf->currency);
					$out['invoices'][] = array(
						'id' => (int) $obj->rowid,
						'ref' => $obj->ref,
						'socid' => (int) $obj->fk_soc,
						'total_ttc' => (float) $obj->total_cur,
						'paid' => (float) $obj->paid,
						'remaining' => (float) $obj->total_cur - (float) $obj->paid,
						'currency' => $invCur,
						'datef' => $obj->datef,
						'confidence' => 'so',
					);
				}
				$this->db->free($resql);
			}
			$out['mode'] = 'so';
			$out['sos'] = array_values($sos);
			$firstSo = reset($sos);
			$out['soc'] = array('id' => $firstSo['socid']);
			if ($firstSo['socid'] > 0) {
				require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
				$soc = new Societe($this->db);
				if ($soc->fetch($firstSo['socid']) > 0) {
					$out['soc'] = array('id' => $soc->id, 'name' => $soc->name);
				}
			}
			return $out;
		}

		// --- 3. Fallback: amount + currency + date window -------------------------
		$ts = self::sqlUtcToTimestamp($row['occurred_at']);
		if ($ts) {
			$from = dol_print_date($ts - 45 * 86400, 'standard', 'tzserver'); // %Y-%m-%d %H:%M:%S in server TZ
			$to = dol_print_date($ts + 2 * 86400, 'standard', 'tzserver');
			$totalCurExpr = "CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN f.multicurrency_total_ttc ELSE f.total_ttc END";
			$paidCurExpr = "(SELECT COALESCE(SUM(CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN pf.multicurrency_amount ELSE pf.amount END), 0) FROM ".MAIN_DB_PREFIX.'paiement_facture pf WHERE pf.fk_facture = f.rowid)';
			$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.multicurrency_code, f.datef,';
			$sql .= ' '.$totalCurExpr.' AS total_cur, '.$paidCurExpr.' AS paid';
			$sql .= ' FROM '.MAIN_DB_PREFIX.'facture f';
			$sql .= ' WHERE f.entity = '.$entity.' AND f.paye = 0 AND f.fk_statut IN (1, 2) AND f.type = 0';
			$sql .= " AND ((f.multicurrency_code <> '' AND f.multicurrency_code IS NOT NULL AND UPPER(f.multicurrency_code) = '".$this->db->escape($currency)."')";
			if (strtoupper((string) $conf->currency) === $currency) {
				$sql .= " OR (f.multicurrency_code IS NULL OR f.multicurrency_code = ''))";
			} else {
				$sql .= ')';
			}
			$sql .= " AND ABS((".$totalCurExpr." - ".$paidCurExpr.") - ".(float) $amount.') < 0.01';
			$sql .= " AND f.datef >= '".$this->db->escape($from)."' AND f.datef <= '".$this->db->escape($to)."'";
			$sql .= ' ORDER BY f.datef ASC LIMIT 20';
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$invCur = !empty($obj->multicurrency_code) ? strtoupper($obj->multicurrency_code) : strtoupper((string) $conf->currency);
					$out['invoices'][] = array(
						'id' => (int) $obj->rowid,
						'ref' => $obj->ref,
						'socid' => (int) $obj->fk_soc,
						'total_ttc' => (float) $obj->total_cur,
						'paid' => (float) $obj->paid,
						'remaining' => (float) $obj->total_cur - (float) $obj->paid,
						'currency' => $invCur,
						'datef' => $obj->datef,
						'confidence' => 'amount', // low confidence: human MUST review
					);
				}
				$this->db->free($resql);
			}
			if (!empty($out['invoices'])) {
				$out['mode'] = 'amount';
			}
		}

		return $out;
	}

	/**
	 * Validate a proposed allocation (invoice rowid => amount in the credit
	 * currency) against the invoices that are open right now. Shared by
	 * recordPayment() and saveDraft() so a draft can never be saved with an
	 * allocation that recording would reject.
	 *
	 * Error codes: -3 no allocation, -4 select failed, -5 unknown/zero amount,
	 * -6 invoice not open in this entity, -7 currency mismatch, -8 over the
	 * invoice remaining, -9 allocations exceed the received credit.
	 *
	 * @param  array $row         Incoming row (fetchRow result)
	 * @param  array $allocations invoice rowid => amount
	 * @return array array('ok'=>bool,'code'=>int,'total'=>float,'invoices'=>array)
	 */
	private function validateAllocations($row, array $allocations)
	{
		global $conf;

		if (empty($allocations)) {
			return array('ok' => false, 'code' => -3, 'total' => 0.0, 'invoices' => array());
		}

		$currency = strtoupper((string) $row['currency']);
		$companyCur = strtoupper((string) $conf->currency);
		$foreignPayment = ($currency !== $companyCur);

		// Batch-load the invoices: no query may run inside the allocation loop.
		$inIds = array();
		foreach (array_keys($allocations) as $facid) {
			$inIds[] = (int) $facid;
		}
		// Invoice-currency amounts, not company-currency ones: for a multicurrency
		// invoice f.total_ttc holds the company-currency conversion while the face
		// value lives in multicurrency_total_ttc; paiements mirror that split
		// (pf.amount company-currency, pf.multicurrency_amount face value). Mixing
		// the two once produced Remain-to-pay figures that were wrong by the FX rate.
		$totalCurExpr = "CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN f.multicurrency_total_ttc ELSE f.total_ttc END";
		$paidCurExpr = "(SELECT COALESCE(SUM(CASE WHEN COALESCE(f.multicurrency_code, '') <> '' THEN pf.multicurrency_amount ELSE pf.amount END), 0) FROM ".MAIN_DB_PREFIX.'paiement_facture pf WHERE pf.fk_facture = f.rowid)';
		$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.multicurrency_code, f.multicurrency_tx, f.paye, f.fk_statut, f.type, f.entity,';
		$sql .= ' '.$totalCurExpr.' AS total_cur, '.$paidCurExpr.' AS paid';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture f WHERE f.rowid IN ('.implode(',', $inIds).')';
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('Wise_Incoming validateAllocations invoice select failed: '.$this->db->lasterror, LOG_ERR);
			return array('ok' => false, 'code' => -4, 'total' => 0.0, 'invoices' => array());
		}
		$invoices = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$invoices[(int) $obj->rowid] = $obj;
		}
		$this->db->free($resql);

		$total = 0.0;
		foreach ($allocations as $facid => $amt) {
			$facid = (int) $facid;
			$amt = (float) $amt;
			if ($amt <= 0 || empty($invoices[$facid])) {
				return array('ok' => false, 'code' => -5, 'total' => 0.0, 'invoices' => $invoices);
			}
			$obj = $invoices[$facid];
			if ((int) $obj->entity !== (int) $row['entity'] || (int) $obj->paye !== 0 || !in_array((int) $obj->fk_statut, array(1, 2)) || (int) $obj->type !== 0) {
				return array('ok' => false, 'code' => -6, 'total' => 0.0, 'invoices' => $invoices);
			}
			// Multicurrency rules:
			//  - foreign-currency credit: invoice must be in the same currency (paid in its own currency)
			//  - company-currency credit: any invoice; a foreign-currency invoice is settled through
			//    the company-currency column (Paiement::create converts with the invoice rate)
			$invCur = !empty($obj->multicurrency_code) ? strtoupper($obj->multicurrency_code) : $companyCur;
			$invTx = isset($obj->multicurrency_tx) && (float) $obj->multicurrency_tx > 0 ? (float) $obj->multicurrency_tx : 1.0;
			if ($foreignPayment && $invCur !== $currency) {
				dol_syslog('Wise_Incoming allocation currency mismatch invoice '.$obj->ref.' ('.$invCur.') vs foreign credit ('.$currency.')', LOG_WARNING);
				return array('ok' => false, 'code' => -7, 'total' => 0.0, 'invoices' => $invoices);
			}
			$remaining = (float) $obj->total_cur - (float) $obj->paid; // in invoice currency
			$cap = (!$foreignPayment && $invCur !== $companyCur) ? $remaining * $invTx : $remaining;
			if ($amt - $cap > 0.01) {
				return array('ok' => false, 'code' => -8, 'total' => 0.0, 'invoices' => $invoices);
			}
			$total += $amt;
		}
		if ($total - (float) $row['amount'] > 0.01) {
			return array('ok' => false, 'code' => -9, 'total' => $total, 'invoices' => $invoices);
		}

		return array('ok' => true, 'code' => 0, 'total' => $total, 'invoices' => $invoices);
	}

	/**
	 * Save a proposed allocation as a draft on the queue row and move it to
	 * STATUS_PREPARED. Nothing is written to accounting: no Paiement, no bank
	 * line, no invoice closed. The draft can be re-edited or discarded as long
	 * as the row has not been recorded.
	 *
	 * @param  int   $rowid       Incoming row id
	 * @param  array $allocations invoice rowid => amount
	 * @param  int   $userid      User saving the draft (for the audit trail)
	 * @return int                0 on success, negative error code (see validateAllocations)
	 */
	public function saveDraft($rowid, array $allocations, $userid = 0)
	{
		$row = $this->fetchRow($rowid);
		if (!$row) {
			return -1;
		}
		if ($row['status'] === self::STATUS_RECORDED) {
			return -2;
		}

		$check = $this->validateAllocations($row, $allocations);
		if (!$check['ok']) {
			return $check['code'];
		}

		// Keep the candidate snapshot written at enrichment time; the draft
		// lives beside it under its own key.
		$match = array();
		if (!empty($row['match_data'])) {
			$decoded = json_decode($row['match_data'], true);
			if (is_array($decoded)) {
				$match = $decoded;
			}
		}
		$items = array();
		foreach ($allocations as $facid => $amt) {
			$facid = (int) $facid;
			$items[] = array(
				'facid' => $facid,
				'amount' => (float) $amt,
				'ref' => isset($check['invoices'][$facid]->ref) ? $check['invoices'][$facid]->ref : '',
			);
		}
		$match['allocation'] = array(
			'currency' => strtoupper((string) $row['currency']),
			'credit' => (float) $row['amount'],
			'total' => (float) $check['total'],
			'unallocated' => round((float) $row['amount'] - (float) $check['total'], 2),
			'items' => $items,
			'date_draft' => dol_now(),
			'user' => (int) $userid,
		);

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming SET';
		$sql .= " status = '".self::STATUS_PREPARED."'";
		$sql .= ", match_data = '".$this->db->escape(json_encode($match))."'";
		$sql .= ' WHERE rowid = '.(int) $rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Incoming saveDraft update failed: '.$this->db->lasterror, LOG_ERR);
			return -11;
		}
		dol_syslog('Wise_Incoming draft saved row '.$rowid.' total='.$check['total'].' by user '.(int) $userid, LOG_INFO);
		return 0;
	}

	/**
	 * Read the saved draft of a row, if any.
	 *
	 * @param  int|array $row Row id or fetchRow result
	 * @return array|null array('currency','credit','total','unallocated','items','date_draft','user')
	 */
	public function getDraft($row)
	{
		$row = is_array($row) ? $row : $this->fetchRow($row);
		if (!$row || empty($row['match_data'])) {
			return null;
		}
		$match = json_decode($row['match_data'], true);		if (!is_array($match) || empty($match['allocation']) || !is_array($match['allocation'])) {
			return null;
		}
		$alloc = $match['allocation'];
		return array(
			'currency' => isset($alloc['currency']) ? (string) $alloc['currency'] : '',
			'credit' => isset($alloc['credit']) ? (float) $alloc['credit'] : 0.0,
			'total' => isset($alloc['total']) ? (float) $alloc['total'] : 0.0,
			'unallocated' => isset($alloc['unallocated']) ? (float) $alloc['unallocated'] : 0.0,
			'items' => !empty($alloc['items']) && is_array($alloc['items']) ? $alloc['items'] : array(),
			'date_draft' => isset($alloc['date_draft']) ? (string) $alloc['date_draft'] : '',
			'user' => isset($alloc['user']) ? (int) $alloc['user'] : 0,
		);
	}

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
	 * @param  array $row   fetchRow() result
	 * @param  int   $days  Date window each side, default 3
	 * @param  float $tol   Amount tolerance in row currency, default 30
	 * @return array        List of bank candidates (each: rowid, datev, amount, label, fk_account, diff)
	 */
	public function findBankMatches($row, $days = 3, $tol = 30.0)
	{
		global $conf;
		$row = is_array($row) ? $row : $this->fetchRow($row);
		if (!$row) {
			return array();
		}
		$amount = (float) $row['amount'];
		if ($amount <= 0) {
			return array();
		}
		$cur = strtoupper((string) $row['currency']);
		$occTs = self::sqlUtcToTimestamp(isset($row['occurred_at']) ? $row['occurred_at'] : '');
		if ($occTs <= 0) {
			// Fall back to row creation date when the webhook did not give one.
			$occTs = !empty($row['date_creation']) ? self::sqlUtcToTimestamp($row['date_creation']) : dol_now();
		}
		$from = (int) ($occTs - $days * 86400);
		$to = (int) ($occTs + $days * 86400);

		$min = $amount - $tol;
		$max = $amount + $tol;

		$sql = 'SELECT b.rowid, b.datev, b.amount, b.label, b.fk_account, ba.currency_code, ba.label AS account_label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'bank b';
		$sql .= ' JOIN '.MAIN_DB_PREFIX.'bank_account ba ON ba.rowid = b.fk_account';
		$sql .= ' WHERE ba.entity = '.(int) $conf->entity;
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
	 * Look up Paiement rows that likely match this Wise credit. The customer
	 * may have already recorded the payment from the invoice screen — in that
	 * case llx_paiement has the right amount + date, but we never knew about
	 * it because Wise cannot tell us which invoice row the credit covers.
	 *
	 * @param  array $row fetchRow() result
	 * @param  int   $days Date window each side, default 3
	 * @param  float $tol Amount tolerance in row currency, default 30
	 * @return array     Paiement candidates (rowid, datep, amount, invoice_refs)
	 */
	public function findPaiementMatches($row, $days = 3, $tol = 30.0)
	{
		global $conf;
		$row = is_array($row) ? $row : $this->fetchRow($row);
		if (!$row) {
			return array();
		}
		$amount = (float) $row['amount'];
		if ($amount <= 0) {
			return array();
		}
		$occTs = self::sqlUtcToTimestamp(isset($row['occurred_at']) ? $row['occurred_at'] : '');
		if ($occTs <= 0) {
			$occTs = !empty($row['date_creation']) ? self::sqlUtcToTimestamp($row['date_creation']) : dol_now();
		}
		$from = (int) ($occTs - $days * 86400);
		$to = (int) ($occTs + $days * 86400);
		$min = $amount - $tol;
		$max = $amount + $tol;

		$sql = 'SELECT p.rowid, p.datep, p.amount';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'paiement p';
		$sql .= ' WHERE p.entity = '.(int) $conf->entity;
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

	/**
	 * Mark the row as reconciled against an external bank/paiement entry.
	 * The credit is now considered settled (the operator recorded the payment
	 * from the invoice screen, so we must not post it again from this queue).
	 * Read-only with respect to llx_bank / llx_paiement — this method only
	 * touches the local wise_incoming row.
	 *
	 * @param  int   $rowid      Incoming row id
	 * @param  int   $bankId     llx_bank.rowid that matched, 0 if none
	 * @param  int   $paiementId llx_paiement.rowid that matched, 0 if none
	 * @param  int   $userid     Acting user
	 * @return int               0 on success, -1 not found, -2 already recorded
	 */
	public function markReconciled($rowid, $bankId, $paiementId, $userid)
	{
		$row = $this->fetchRow($rowid);
		if (!$row) {
			return -1;
		}
		if ($row['status'] === self::STATUS_RECORDED) {
			return -2;
		}
		$noteParts = array();
		if ($paiementId > 0) {
			$noteParts[] = 'paiement #'.$paiementId;
		}
		if ($bankId > 0) {
			$noteParts[] = 'bank #'.$bankId;
		}
		$note = 'auto-reconciled by user #'.(int) $userid.' on '.date('Y-m-d H:i:s');
		if (!empty($noteParts)) {
			$note .= ' — '.implode(', ', $noteParts);
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
		$sql .= " SET status = '".self::STATUS_IGNORED."',";
		$sql .= " note_private = CONCAT(COALESCE(note_private, ''), ' ', '".$this->db->escape($note)."'),";
		$sql .= " tms = tms";
		$sql .= ' WHERE rowid = '.(int) $rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Incoming markReconciled update failed: '.$this->db->lasterror, LOG_ERR);
			return -11;
		}
		dol_syslog('Wise_Incoming row '.$rowid.' reconciled to '.implode(',', $noteParts), LOG_INFO);
		return 0;
	}

	/**
	 * Drop the saved draft and put the row back to ENRICHED (or NEW when it was
	 * never enriched). Used to abandon a draft without ignoring the credit.
	 *
	 * @param  int $rowid Incoming row id
	 * @return int       0 on success, -1 row not found, -2 already recorded
	 */
	public function discardDraft($rowid)
	{
		$row = $this->fetchRow($rowid);
		if (!$row) {
			return -1;
		}
		if ($row['status'] === self::STATUS_RECORDED) {
			return -2;
		}

		$match = array();
		if (!empty($row['match_data'])) {
			$decoded = json_decode($row['match_data'], true);
			if (is_array($decoded)) {
				$match = $decoded;
			}
		}
		unset($match['allocation']);

		// Back to whatever the row was before the draft: IGNORED stays ignored,
		// otherwise the enrichment state decides (no ref and no counterparty
		// means the statement lookup never succeeded, so it is still NEW).
		if ($row['status'] === self::STATUS_IGNORED) {
			$fallback = self::STATUS_IGNORED;
		} elseif (!empty($row['ref_text']) || !empty($row['counterparty'])) {
			$fallback = self::STATUS_ENRICHED;
		} else {
			$fallback = self::STATUS_NEW;
		}
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming SET';
		$sql .= " status = '".$fallback."'";
		$sql .= ", match_data = '".$this->db->escape(json_encode($match))."'";
		$sql .= ' WHERE rowid = '.(int) $rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Incoming discardDraft update failed: '.$this->db->lasterror, LOG_ERR);
			return -11;
		}
		dol_syslog('Wise_Incoming draft discarded row '.$rowid, LOG_INFO);
		return 0;
	}

	/**
	 * Create the Dolibarr payment for a recorded credit.
	 *
	 * @param  int   $rowid       Incoming row id
	 * @param  array $allocations invoice rowid => amount
	 * @param  User  $user        Acting user
	 * @return int Payment id, or negative error code
	 */
	/**
	 * Record a confirmed payment into Dolibarr: creates the Paiement, links the
	 * bank line to the mapped Wise bank account and closes fully paid invoices.
	 *
	 * @param  int   $rowid       llx_slycustom_wise_incoming.rowid
	 * @param  array $allocations array(fk_facture => amount) in payment currency
	 * @param  User  $user        Operator doing the confirmation
	 * @return int   Paiement id, or <0 on error
	 */
	public function recordPayment($rowid, array $allocations, $user)
	{
		global $conf;

		$row = $this->fetchRow($rowid);
		if (!$row) {
			return -1;
		}
		if ($row['status'] === self::STATUS_RECORDED) {
			return -2; // already recorded
		}
		if (empty($allocations)) {
			return -3;
		}

		$currency = strtoupper((string) $row['currency']);
		$companyCur = strtoupper((string) $conf->currency);
		$foreignPayment = ($currency !== $companyCur);

		require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$check = $this->validateAllocations($row, $allocations);
		if (!$check['ok']) {
			return $check['code'];
		}
		$invoices = $check['invoices'];
		$total = $check['total'];

		// Payment mode: configurable code, default bank transfer (per-entity const).
		$modeCode = dolibarr_get_const($this->db, 'WISE_PAYMENT_MODE', (int) $row['entity']);
		if ($modeCode === null || trim((string) $modeCode) === '') {
			$modeCode = 'VIR';
		}
		$modeCode = trim((string) $modeCode);
		$modeId = 0;
		$sql = 'SELECT id FROM '.MAIN_DB_PREFIX.'c_paiement WHERE code = \''.$this->db->escape($modeCode).'\' AND entity IN (0, '.(int) $row['entity'].') AND active = 1';
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$modeId = (int) $obj->id;
			}
			$this->db->free($resql);
		}

		$pay = new Paiement($this->db);
		$pay->datepaye = self::sqlUtcToTimestamp($row['occurred_at']);
		if (!$pay->datepaye) {
			$pay->datepaye = dol_now();
		}
		$pay->paiementid = $modeId;
		$pay->num_payment = 'WISE '.trim((string) $row['ref_text']);
		// Paiement::create() persists note_private ?: note, and the payment card reads
		// note_private — note_public is never stored, so it must not be used here.
		$pay->note_private = 'Wise credit '.$currency.' '.price((float) $row['amount'])
			.(!empty($row['counterparty']) ? ' from '.$row['counterparty'] : '');
		if ($foreignPayment) {
			$pay->multicurrency_code = $currency;
			$pay->multicurrency_amounts = array();
			foreach ($allocations as $facid => $amt) {
				$pay->multicurrency_amounts[(int) $facid] = (float) $amt;
			}
		} else {
			$pay->amounts = array();
			foreach ($allocations as $facid => $amt) {
				$pay->amounts[(int) $facid] = (float) $amt;
			}
		}

		$paymentId = $pay->create($user, 1); // 1 = auto-close fully paid invoices
		if ($paymentId <= 0) {
			dol_syslog('Wise_Incoming recordPayment Paiement::create failed for row '.$rowid.' error='.($pay->error ? json_encode($pay->error) : '?'), LOG_ERR);
			return -10;
		}

		// Bank line on the mapped Wise account (per-currency const, else default; per-entity).
		$bankId = (int) dolibarr_get_const($this->db, 'WISE_BANK_ACCOUNT_'.$currency, (int) $row['entity']);
		if ($bankId <= 0) {
			$bankId = (int) dolibarr_get_const($this->db, 'WISE_BANK_ACCOUNT_DEFAULT', (int) $row['entity']);
		}
		if ($bankId > 0) {
			$result = $pay->addPaymentToBank($user, 'payment', '(CustomerInvoicePayment)', $bankId, (string) $row['counterparty'], 'Wise');
			if ($result < 0) {
				dol_syslog('Wise_Incoming addPaymentToBank failed for paiement '.$paymentId.': '.json_encode($pay->error), LOG_ERR);
				// Payment exists; keep row ENRICHED-visible via note instead of failing silently.
				$this->setNote($rowid, 'Paiement '.$paymentId.' created but bank line failed: '.json_encode($pay->error));
			}
		} else {
			$this->setNote($rowid, 'Paiement '.$paymentId.' created without bank line: no WISE_BANK_ACCOUNT_'.$currency.' or WISE_BANK_ACCOUNT_DEFAULT configured.');
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming SET';
		$sql .= " status = '".self::STATUS_RECORDED."'";
		$sql .= ', fk_paiement = '.(int) $paymentId;
		$sql .= ', fk_soc = '.(!empty($invoices[(int) key($allocations)]->fk_soc) ? (int) $invoices[(int) key($allocations)]->fk_soc : 'NULL');
		$sql .= ' WHERE rowid = '.(int) $rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Incoming recordPayment status update failed: '.$this->db->lasterror, LOG_ERR);
		}

		dol_syslog('Wise_Incoming recorded paiement '.$paymentId.' for row '.$rowid.' total='.price($total), LOG_INFO);
		return $paymentId;
	}

	/**
	 * Fallback reconciliation: scan Wise balance statements of the last N days
	 * and queue CREDIT transactions that are missing from the incoming table
	 * (e.g. a webhook delivery lost to a 4xx rejection — Wise does not retry
	 * permanent failures). Also prefills the payer reference from the
	 * statement. Runs from the cron entry before enrichment.
	 *
	 * @param  int $entity Company entity
	 * @param  int $days   Statement window in days
	 * @return array array('inserted' => n, 'skipped' => n, 'error' => string|null)
	 */
	public function syncFromStatements($entity, $days = 2)
	{
		$stats = array('inserted' => 0, 'skipped' => 0, 'error' => null);

		$api = Wise_API::fromEntity($this->db, (int) $entity);
		$profileId = self::getProfileIdForEntity($this->db, (int) $entity);
		if (!$api || $profileId === '') {
			$stats['error'] = 'WISE_API_TOKEN / WISE_PROFILE_ID not configured';
			return $stats;
		}

		$balances = $api->getBalances((int) $profileId);
		if (isset($balances['error']) || (isset($balances['httpCode']) && $balances['httpCode'] >= 400)) {
			$stats['error'] = 'balances failed: '.substr((string) json_encode($balances), 0, 200);
			return $stats;
		}
		unset($balances['httpCode']);

		$startIso = gmdate('Y-m-d\TH:i:s\Z', dol_now() - max(1, (int) $days) * 86400 - 3600);
		$endIso = gmdate('Y-m-d\TH:i:s\Z', dol_now() + 3600);

		foreach ($balances as $bal) {
			if (!is_array($bal) || empty($bal['id']) || empty($bal['currency'])) {
				continue;
			}
			$result = $api->getStatementTransactions((int) $profileId, (int) $bal['id'], $startIso, $endIso, 10);
			if (isset($result['error'])) {
				dol_syslog('Wise_Incoming sync statement failed balance='.$bal['id'].': '.substr((string) $result['error'], 0, 200), LOG_WARNING);
				continue;
			}
			foreach ($result['transactions'] as $txn) {
				if (!is_array($txn) || !isset($txn['type']) || strtoupper((string) $txn['type']) !== 'CREDIT') {
					continue;
				}
				$amt = isset($txn['totalValue']) ? (float) $txn['totalValue'] : (isset($txn['amount']) ? (float) $txn['amount'] : null);
				$occ = self::isoToSqlUtc(isset($txn['date']) ? $txn['date'] : (isset($txn['dateTime']) ? $txn['dateTime'] : ''));
				if ($amt === null || $amt == 0 || !$occ) {
					continue;
				}
				$currency = strtoupper((string) $bal['currency']);
				if ($this->incomingRowExists($entity, $currency, $amt, $occ)) {
					$stats['skipped']++;
					continue;
				}
				$details = isset($txn['details']) && is_array($txn['details']) ? $txn['details'] : array();
				$refText = '';
				foreach (array('reference', 'paymentReference', 'payment_reference', 'description') as $f) {
					if (isset($details[$f]) && is_string($details[$f]) && trim($details[$f]) !== '') {
						$refText = trim($details[$f]);
						break;
					}
				}
				$postBalance = isset($txn['runningBalance']['value']) ? (float) $txn['runningBalance']['value'] : null;

				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
				$sql .= ' (entity, fk_event, wise_balance_id, wise_profile_id, currency, amount, occurred_at, post_balance, status, ref_text, note_private, date_creation)';
				$sql .= ' VALUES ('.(int) $entity.', NULL, '.(int) $bal['id'].', '.(int) $profileId.', \''.$this->db->escape($currency).'\', '.(float) $amt.',';
				$sql .= ' \''.$this->db->escape($occ).'\',';
				$sql .= ' '.($postBalance !== null ? (float) $postBalance : 'NULL').',';
				$sql .= ' \''.self::STATUS_NEW.'\',';
				$sql .= ' '.($refText !== '' ? '\''.$this->db->escape(substr($refText, 0, 250)).'\'' : "''").',';
				$sql .= ' \'Synced from statement fallback.\', \''.$this->db->idate(dol_now()).'\')';
				if (!$this->db->query($sql)) {
					dol_syslog('Wise_Incoming sync insert failed: '.$this->db->lasterror, LOG_ERR);
					continue;
				}
				$stats['inserted']++;
				dol_syslog('Wise_Incoming sync queued statement credit cur='.$currency.' amount='.price2num($amt).' occ='.$occ, LOG_INFO);
			}
		}

		return $stats;
	}

	/**
	 * Does an incoming row already exist for this credit (same entity/currency,
	 * amount within tolerance, occurred_at within +/- 5 minutes)? Used to keep
	 * the statement fallback and (possibly delayed) webhook deliveries from
	 * queueing the same credit twice.
	 *
	 * @param  int    $entity    Company entity
	 * @param  string $currency  ISO 4217 code
	 * @param  float  $amount    Credited amount
	 * @param  string $occSqlUtc 'Y-m-d H:i:s' UTC
	 * @return bool
	 */
	private function incomingRowExists($entity, $currency, $amount, $occSqlUtc)
	{
		$ts = self::sqlUtcToTimestamp($occSqlUtc);
		if (!$ts) {
			return false;
		}
		$from = gmdate('Y-m-d H:i:s', $ts - 300);
		$to = gmdate('Y-m-d H:i:s', $ts + 300);
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
		$sql .= ' WHERE entity = '.(int) $entity.' AND currency = \''.$this->db->escape($currency).'\'';
		$sql .= ' AND ABS(amount - '.(float) $amount.') < 0.0001';
		$sql .= " AND occurred_at >= '".$this->db->escape($from)."' AND occurred_at <= '".$this->db->escape($to)."'";
		$sql .= ' LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false; // on doubt, let the caller insert (enrichment will surface duplicates for manual merge)
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj !== null;
	}

	/**
	 * Cron entry point: enrich pending incoming rows. Registered in
	 * modSlyCustom cronjobs ("Wise incoming payments enrichment").
	 *
	 * Cron "method" jobs expect an int return (non-zero = error); stats are
	 * logged instead of returned. Failures (missing token, API errors) are
	 * non-fatal — rows stay NEW and are retried on the next run.
	 *
	 * @param  string|int $entity Company entity ("1" style from cron params; falls back to cron-assigned $this->entity)
	 * @param  string|int $limit  Max rows per run
	 * @return int 0
	 */
	public function enrichPendingCron($entity = 0, $limit = 10)
	{
		global $conf;

		$e = (int) $entity;
		if ($e <= 0) {
			$e = !empty($this->entity) ? (int) $this->entity : (int) $conf->entity;
		}
		// Entity context must match the rows being processed: the cron job is
		// registered with entity=0 so core never switches $conf, and currency
		// inference (MAIN_MONNAIE per entity) would otherwise use the wrong company.
		$prevEntity = (int) $conf->entity;
		if ($e >= 1 && $e !== $prevEntity) {
			$conf->setEntityValues($this->db, $e);
		}
		// Statement fallback first: recovers credits whose webhook was lost
		// (Wise does not retry permanent 4xx rejections).
		$sync = $this->syncFromStatements($e, 2);
		dol_syslog('Wise_Incoming cron sync entity='.$e.' stats='.json_encode($sync), LOG_INFO);
		$stats = $this->enrichPending($e, (int) $limit);
		dol_syslog('Wise_Incoming cron enrich entity='.$e.' stats='.json_encode($stats), LOG_INFO);
		if ((int) $conf->entity !== $prevEntity) {
			$conf->setEntityValues($this->db, $prevEntity);
		}
		return 0;
	}

	/**
	 * Fetch one incoming row as associative array.
	 *
	 * @param  int $rowid Row id
	 * @return array|null
	 */
	public function fetchRow($rowid)
	{
		$sql = 'SELECT t.rowid, t.entity, t.fk_event, t.wise_balance_id, t.wise_profile_id, t.currency, t.amount, t.occurred_at,';
		$sql .= ' t.post_balance, t.status, t.ref_text, t.counterparty, t.fees, t.fk_soc, t.fk_paiement, t.note_private, t.match_data';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'slycustom_wise_incoming t WHERE t.rowid = '.(int) $rowid;
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return null;
		}
		return array(
			'rowid' => (int) $obj->rowid,
			'entity' => (int) $obj->entity,
			'fk_event' => $obj->fk_event !== null ? (int) $obj->fk_event : null,
			'wise_balance_id' => $obj->wise_balance_id !== null ? (int) $obj->wise_balance_id : null,
			'wise_profile_id' => isset($obj->wise_profile_id) && $obj->wise_profile_id !== null ? (int) $obj->wise_profile_id : null,
			'currency' => (string) $obj->currency,
			'amount' => (float) $obj->amount,
			'occurred_at' => $obj->occurred_at,
			'post_balance' => $obj->post_balance !== null ? (float) $obj->post_balance : null,
			'status' => (string) $obj->status,
			'ref_text' => (string) $obj->ref_text,
			'counterparty' => (string) $obj->counterparty,
			'fees' => $obj->fees !== null ? (float) $obj->fees : null,
			'fk_soc' => $obj->fk_soc !== null ? (int) $obj->fk_soc : null,
			'fk_paiement' => $obj->fk_paiement !== null ? (int) $obj->fk_paiement : null,
			'note_private' => (string) $obj->note_private,
			'match_data' => (string) $obj->match_data,
		);
	}

	/**
	 * Append a note to an incoming row (best effort).
	 *
	 * @param  int    $rowid Row id
	 * @param  string $note  Text to append
	 * @return void
	 */
	private function setNote($rowid, $note)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_incoming';
		$sql .= ' SET note_private = CONCAT('.$this->quoteOrNull($note).', \' \', IFNULL(note_private, \'\'))';
		$sql .= ' WHERE rowid = '.(int) $rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Incoming setNote failed: '.$this->db->lasterror, LOG_WARNING);
		}
	}

	/**
	 * SQL-quoted string or NULL for empty values.
	 *
	 * @param  string $v Value
	 * @return string
	 */
	private function quoteOrNull($v)
	{
		$v = trim((string) $v);
		return $v === '' ? 'NULL' : '\''.$this->db->escape($v).'\'';
	}

	// ---- Webhook dump management (accepted/ + rejected/ buckets) ---------------

	/**
	 * One-click record: enrich, then record — but only when the match is
	 * unambiguous. The v2.0.0 credit payload carries no reference, so the
	 * statement lookup must first recover one; anything that would require
	 * guessing (no reference, order not found, several open invoices, currency
	 * mismatch, credit above the invoice remaining) refuses and leaves the row
	 * in the queue for the normal manual allocation flow.
	 *
	 * Error codes (own -20 range, validateAllocations/recordPayment keep theirs):
	 *  -20 row already processed or has a draft, -21 no reference recovered,
	 *  -22 reference matched no sales order, -23 not exactly one open invoice,
	 *  -24 invoice currency differs, -25 credit exceeds the invoice remaining.
	 *
	 * @param  int  $rowid Incoming row id
	 * @param  User $user  Recording user
	 * @return int         Payment id (>0) on success, negative error otherwise
	 */
	public function autoRecord($rowid, $user)
	{
		$row = $this->fetchRow($rowid);
		if (!$row) {
			return -1;
		}
		if (in_array($row['status'], array(self::STATUS_PREPARED, self::STATUS_RECORDED, self::STATUS_IGNORED), true)) {
			return -20;
		}
		// Pull the payment reference (and counterparty/fees) from the statement
		// API, then re-read: enrichRow writes ref_text the matching below needs.
		$this->enrichRow($rowid);
		$row = $this->fetchRow($rowid);
		if (!$row) {
			return -1;
		}
		if (trim((string) $row['ref_text']) === '') {
			return -21;
		}
		$match = $this->findInvoiceCandidates($row);
		if (!isset($match['mode']) || $match['mode'] !== 'so') {
			return -22;
		}
		if (!isset($match['invoices'][0]) || count($match['invoices']) !== 1) {
			return -23;
		}
		$inv = $match['invoices'][0];
		if (strcasecmp((string) $inv['currency'], (string) $row['currency']) !== 0) {
			return -24;
		}
		if ((float) $row['amount'] > (float) $inv['remaining'] + 0.01) {
			return -25;
		}
		return $this->recordPayment($rowid, array((int) $inv['id'] => (float) $row['amount']), $user);
	}

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
	 * List webhook delivery dumps with their ingest state, newest first.
	 *
	 * Ingest state comes from one batched payload_md5 lookup (never a query per
	 * row). A dump is replayable only when the stored body is complete JSON —
	 * bootstrap dumps keep a 2 KB head slice, which never re-parses.
	 *
	 * @param  int   $entity Only dumps captured for this entity (0 = all)
	 * @return array         array('accepted' => [...], 'rejected' => [...])
	 */
	public function listDumps($entity = 0)
	{
		$out = array('accepted' => array(), 'rejected' => array());
		$md5ToDump = array();
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
					'replayable' => false,
					'ingested' => false,
				);
				$body = isset($dump['body']) && is_string($dump['body']) ? $dump['body'] : '';
				$parsed = json_decode($body, true);
				if (is_array($parsed)) {
					// Complete body: the md5 of the stored body equals the md5 the
					// webhook computed over the raw delivery, so the dedupe key and
					// the replay entry point are the exact original ones.
					$summary['replayable'] = true;
					$summary['md5'] = md5($body);
					$summary['event_type'] = isset($parsed['event_type']) ? (string) $parsed['event_type'] : '';
					$summary['currency'] = isset($parsed['data']['currency']) ? (string) $parsed['data']['currency'] : '';
					$summary['amount'] = isset($parsed['data']['amount']) ? (float) $parsed['data']['amount'] : null;
					$summary['occurred_at'] = isset($parsed['data']['occurred_at']) ? (string) $parsed['data']['occurred_at'] : '';
					$md5ToDump[$summary['md5']] = $bucket.'/'.$name;
				}
				$out[$bucket][$bucket.'/'.$name] = $summary;
			}
		}
		if (!empty($md5ToDump)) {
			// One query for the whole page: which dumps already made it into the event table.
			$allMd5 = array_keys($md5ToDump);
			$allMd5 = array_map(array($this->db, 'escape'), $allMd5);
			$sql = 'SELECT payload_md5 FROM '.MAIN_DB_PREFIX.'slycustom_wise_event';
			$sql .= " WHERE payload_md5 IN ('".implode("','", $allMd5)."')";
			$resql = $this->db->query($sql);
			if ($resql) {
				while ($obj = $this->db->fetch_object($resql)) {
					$key = isset($md5ToDump[$obj->payload_md5]) ? $md5ToDump[$obj->payload_md5] : '';
					if ($key !== '' && isset($out[dirname($key)][basename($key)])) {
						$bucket = dirname($key);
						$out[$bucket][$bucket.'/'.basename($key)]['ingested'] = true;
					}
				}
				$this->db->free($resql);
			}
		}
		return $out;
	}

	/**
	 * Re-ingest one stored dump through the exact webhook entry point.
	 *
	 * Idempotent by construction: createFromWebhook() dedupes on payload_md5,
	 * so replaying an already-ingested dump changes nothing.
	 *
	 * @param  string $bucket 'accepted' or 'rejected'
	 * @param  string $file   Dump file name
	 * @return array          array('ok' => bool, 'message' => string)
	 */
	public function replayDump($bucket, $file)
	{
		$path = self::dumpPath($bucket, $file);
		if ($path === null) {
			return array('ok' => false, 'message' => 'invalid dump reference');
		}
		$dump = json_decode((string) @file_get_contents($path), true);
		if (!is_array($dump) || empty($dump['body']) || !is_string($dump['body'])) {
			return array('ok' => false, 'message' => 'dump unreadable');
		}
		$body = $dump['body'];
		$parsed = json_decode($body, true);
		if (!is_array($parsed)) {
			return array('ok' => false, 'message' => 'body truncated, cannot replay');
		}
		$entity = isset($dump['entity']) && (int) $dump['entity'] >= 1 ? (int) $dump['entity'] : 1;
		$meta = array(
			'delivery_id' => isset($dump['headers']['X_DELIVERY_ID']) ? (string) $dump['headers']['X_DELIVERY_ID'] : '',
			'is_test' => !empty($dump['headers']['X_TEST_NOTIFICATION']),
			'unverified' => !empty($dump['bootstrap']),
		);
		$result = self::createFromWebhook($this->db, $entity, $parsed, $body, $meta);
		if ($result['error'] !== null) {
			return array('ok' => false, 'message' => 'ingest error: '.$result['error']);
		}
		if ($result['duplicate']) {
			return array('ok' => true, 'message' => 'already ingested (duplicate), nothing queued');
		}
		return array('ok' => true, 'message' => 'ingested event row #'.$result['event_id']
			.(empty($result['incoming_id']) ? '' : ', queued as incoming #'.$result['incoming_id']));
	}

	/**
	 * Delete one stored dump (file only; already-ingested events stay in DB).
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
		return array('ok' => true, 'message' => 'deleted');
	}
}
}
