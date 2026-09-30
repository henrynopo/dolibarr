<?php

/**
 * \file    custom/slycustom/class/Wise_Payment.class.php
 * \ingroup slycustom
 * \brief   Wise outgoing payments (flow A): prepare UNFUNDED transfers from
 *          supplier invoices, track their Wise state and write the Dolibarr
 *          supplier payment back once the money leaves Wise.
 *
 * Flow (gated by WISE_OUTGOING_ENABLED, default off):
 *   1. Operator opens a validated, unpaid supplier invoice -> hook adds a
 *      "Pay via Wise" button -> wise/prepare.php shows what will be sent.
 *   2. prepareTransfer(): quote -> IBAN recipient -> UNFUNDED transfer.
 *      The funding endpoint is NEVER called: funding manually in the Wise
 *      dashboard IS the human approval gate. Unfunded transfers auto-cancel
 *      after 5 business days (natural review timeout).
 *   3. transfers#state-change webhook (or manual refresh) updates the state;
 *      outgoing_payment_sent -> supplier payment is auto-recorded.
 *
 * Reference rule (SLY business decision): the transfer reference sent to the
 * recipient is the VENDOR'S own order reference — linked supplier order's
 * ref_supplier, then the invoice's ref_supplier, then our invoice ref —
 * never our internal PO ref.
 *
 * Idempotency: each mapping row carries a customerTransactionId uuid; Wise
 * rejects duplicate ids, so a double-clicked prepare cannot double-pay.
 */

require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_API.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/class/Wise_Incoming.class.php';

if (!class_exists('WiseOutgoingPayment', false)) {
class WiseOutgoingPayment
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var int|null Entity, assigned by the cron runner */
	public $entity;

	const STATUS_DRAFT = 'DRAFT';       // transfer created in Wise, awaiting manual funding
	const STATUS_SENT = 'SENT';         // outgoing_payment_sent seen
	const STATUS_RECORDED = 'RECORDED'; // Dolibarr supplier payment written
	const STATUS_CANCELLED = 'CANCELLED';
	const STATUS_ERROR = 'ERROR';

	/** Wise terminal-ish states we treat as cancelled/aborted */
	const CANCEL_STATES = 'cancelled,bounced_back,funds_refunded,charged_back';

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
	 * Is the outgoing flow enabled for the entity? Single source of truth is
	 * WiseIncomingPayment::isOutgoingEnabled (default OFF).
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $entity Company entity
	 * @return bool
	 */
	public static function isEnabled($db, $entity)
	{
		return WiseIncomingPayment::isOutgoingEnabled($db, $entity);
	}

	/**
	 * Resolve the reference to show to the recipient: vendor's own order
	 * reference first, then the vendor's invoice number, then our ref.
	 *
	 * @param  DoliDB $db        Database handler
	 * @param  int    $factureId llx_facture_fourn.rowid
	 * @return array  array('reference' => string, 'source' => string)
	 */
	public static function resolveVendorReference($db, $factureId)
	{
		$sql = 'SELECT f.ref_supplier AS inv_ref_supplier, f.ref AS inv_ref,';
		$sql .= ' (SELECT cf.ref_supplier FROM '.MAIN_DB_PREFIX.'commande_fournisseur cf';
		$sql .= '   WHERE cf.rowid IN (SELECT ee.fk_source FROM '.MAIN_DB_PREFIX.'element_element ee';
		$sql .= "     WHERE ee.sourcetype = 'order_supplier' AND ee.targettype = 'invoice_supplier' AND ee.fk_target = ".(int) $factureId.')';
		$sql .= '   ORDER BY cf.rowid DESC LIMIT 1) AS po_ref_supplier';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture_fourn f WHERE f.rowid = '.(int) $factureId;
		$resql = $db->query($sql);
		if (!$resql) {
			return array('reference' => '', 'source' => 'db-error');
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (!$obj) {
			return array('reference' => '', 'source' => 'not-found');
		}
		$poRef = !empty($obj->po_ref_supplier) ? trim((string) $obj->po_ref_supplier) : '';
		$invRefSupplier = !empty($obj->inv_ref_supplier) ? trim((string) $obj->inv_ref_supplier) : '';
		if ($poRef !== '') {
			return array('reference' => substr($poRef, 0, 100), 'source' => 'order_ref_supplier');
		}
		if ($invRefSupplier !== '') {
			return array('reference' => substr($invRefSupplier, 0, 100), 'source' => 'invoice_ref_supplier');
		}
		return array('reference' => substr((string) $obj->inv_ref, 0, 100), 'source' => 'our_ref');
	}

	/**
	 * Load the supplier's default IBAN (llx_societe_rib, default first, latest with IBAN as fallback).
	 *
	 * @param  int   $socid llx_societe.rowid
	 * @return array array('iban' =>, 'bic' =>, 'holder' => '') or empty array
	 */
	public static function fetchSupplierIban($db, $socid)
	{
		$sql = 'SELECT s.nom AS holder, r.iban_prefix, r.bic, r.label';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'societe_rib r';
		$sql .= ' JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid = r.fk_soc';
		$sql .= ' WHERE r.fk_soc = '.(int) $socid." AND r.status = 1 AND r.iban_prefix IS NOT NULL AND r.iban_prefix <> ''";
		$sql .= ' ORDER BY r.default_rib DESC, r.datec DESC LIMIT 1';
		$resql = $db->query($sql);
		if (!$resql) {
			return array();
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (!$obj) {
			return array();
		}
		return array(
			'iban' => trim((string) $obj->iban_prefix),
			'bic' => trim((string) $obj->bic),
			'holder' => trim((string) $obj->holder),
			'label' => trim((string) $obj->label),
		);
	}

	/**
	 * Is there an active (non-terminal) Wise transfer for the invoice?
	 *
	 * @param  int $factureId llx_facture_fourn.rowid
	 * @param  int $entity    Company entity
	 * @return array|null Active row or null
	 */
	public function fetchActiveForInvoice($factureId, $entity)
	{
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer';
		$sql .= ' WHERE fk_facture_fourn = '.(int) $factureId.' AND entity = '.(int) $entity;
		$sql .= " AND status IN ('".$this->db->escape(self::STATUS_DRAFT)."', '".$this->db->escape(self::STATUS_SENT)."')";
		$sql .= ' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? $obj : null;
	}

	/**
	 * Static convenience wrapper for hooks.
	 *
	 * @param  DoliDB $db        Database handler
	 * @param  int    $factureId Supplier invoice id
	 * @param  int    $entity    Company entity
	 * @return array|null
	 */
	public static function fetchStaticActiveForInvoice($db, $factureId, $entity)
	{
		$tmp = new self($db);
		return $tmp->fetchActiveForInvoice($factureId, $entity);
	}

	/**
	 * Prepare an unfunded transfer for a validated, unpaid supplier invoice.
	 *
	 * @param  int  $factureId llx_facture_fourn.rowid
	 * @param  User $user      Operator
	 * @return array array('ok' => bool, 'transfer_row' => int, 'wise_transfer_id' => int, 'error' => string, 'detail' => string)
	 */
	public function prepareTransfer($factureId, $user)
	{
		global $conf;

		$out = array('ok' => false, 'transfer_row' => 0, 'wise_transfer_id' => 0, 'error' => '', 'detail' => '');
		$entity = (int) $conf->entity;

		if (!self::isEnabled($this->db, $entity)) {
			$out['error'] = 'Wise outgoing flow disabled (WISE_OUTGOING_ENABLED=0)';
			return $out;
		}

		// Invoice checks
		$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.total_ttc, f.multicurrency_code, f.multicurrency_tx, f.paye, f.fk_statut, f.entity';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture_fourn f WHERE f.rowid = '.(int) $factureId;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$out['error'] = 'DB error: '.$this->db->lasterror;
			return $out;
		}
		$inv = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$inv || (int) $inv->entity !== $entity) {
			$out['error'] = 'Invoice not found for this entity';
			return $out;
		}
		if ((int) $inv->fk_statut !== 1 || (int) $inv->paye !== 0) {
			$out['error'] = 'Invoice must be validated and unpaid';
			return $out;
		}

		if ($this->fetchActiveForInvoice((int) $factureId, $entity)) {
			$out['error'] = 'An active Wise transfer already exists for this invoice';
			return $out;
		}

		// Remaining amount (supplier payments made so far)
		$sql = 'SELECT COALESCE(SUM(pf.amount), 0) AS paid FROM '.MAIN_DB_PREFIX.'paiementfourn_facturefourn pf';
		$sql .= ' WHERE pf.fk_facturefourn = '.(int) $factureId;
		$resql = $this->db->query($sql);
		$paid = 0.0;
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$paid = (float) $obj->paid;
			$this->db->free($resql);
		}
		$remaining = (float) $inv->total_ttc - $paid;
		if ($remaining <= 0.01) {
			$out['error'] = 'Invoice remaining amount is zero';
			return $out;
		}

		$api = Wise_API::fromEntity($this->db, $entity);
		$profileId = WiseIncomingPayment::getProfileIdForEntity($this->db, $entity);
		if (!$api || $profileId === '') {
			$out['error'] = 'WISE_API_TOKEN / WISE_PROFILE_ID not configured';
			return $out;
		}

		// Target = invoice currency; source = configured balance currency
		$targetCurrency = !empty($inv->multicurrency_code) ? strtoupper((string) $inv->multicurrency_code) : strtoupper((string) $conf->currency);
		$sourceCurrency = strtoupper(dolibarr_get_const($this->db, 'WISE_SOURCE_CURRENCY', $entity));
		if ($sourceCurrency === '') {
			$sourceCurrency = strtoupper((string) $conf->currency);
		}

		$rib = self::fetchSupplierIban($this->db, (int) $inv->fk_soc);
		if (empty($rib['iban'])) {
			$out['error'] = 'Supplier has no IBAN bank account (llx_societe_rib)';
			return $out;
		}

		$refInfo = self::resolveVendorReference($this->db, (int) $factureId);

		// 1. Quote
		$quote = $api->createQuote((int) $profileId, $sourceCurrency, $targetCurrency, $remaining);
		if (isset($quote['error']) || $quote['httpCode'] >= 400 || empty($quote['id'])) {
			$out['error'] = 'Quote creation failed';
			$out['detail'] = substr((string) json_encode($quote), 0, 500);
			return $out;
		}

		// 2. Recipient
		$recipient = $api->createRecipientIban((int) $profileId, $targetCurrency, $rib['holder'], $rib['iban'], $rib['bic']);
		if (isset($recipient['error']) || $recipient['httpCode'] >= 400 || empty($recipient['id'])) {
			$out['error'] = 'Recipient creation failed (currency may need extra fields)';
			$out['detail'] = substr((string) json_encode($recipient), 0, 500);
			return $out;
		}

		// 3. UNFUNDED transfer (idempotency uuid, RFC 4122 v4)
		$uuidData = random_bytes(16);
		$uuidData[6] = chr((ord($uuidData[6]) & 0x0f) | 0x40);
		$uuidData[8] = chr((ord($uuidData[8]) & 0x3f) | 0x80);
		$customerTxId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($uuidData), 4));
		$transfer = $api->createTransfer((int) $recipient['id'], (string) $quote['id'], $customerTxId, $refInfo['reference']);
		if (isset($transfer['error']) || $transfer['httpCode'] >= 400 || empty($transfer['id'])) {
			$out['error'] = 'Transfer creation failed';
			$out['detail'] = substr((string) json_encode($transfer), 0, 500);
			return $out;
		}

		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'slycustom_wise_transfer';
		$sql .= ' (entity, fk_facture_fourn, fk_user_creat, wise_quote_id, wise_recipient_id, wise_transfer_id,';
		$sql .= ' customer_transaction_id, source_currency, target_currency, target_amount, source_amount, rate, fee,';
		$sql .= ' reference_sent, reference_source, last_state, status, date_creation)';
		$sql .= ' VALUES ('.$entity.', '.(int) $factureId.', '.(int) $user->id.',';
		$sql .= ' \''.$this->db->escape((string) $quote['id']).'\', '.(int) $recipient['id'].', '.(int) $transfer['id'].',';
		$sql .= ' \''.$this->db->escape($customerTxId).'\',';
		$sql .= ' \''.$this->db->escape($sourceCurrency).'\', \''.$this->db->escape($targetCurrency).'\', '.(float) $remaining.',';
		$sql .= ' '.(isset($quote['sourceAmount']) ? (float) $quote['sourceAmount'] : 0).',';
		$sql .= ' '.(isset($quote['rate']) ? (float) $quote['rate'] : 0).',';
		$sql .= ' '.(isset($quote['fee']) && is_numeric($quote['fee']) ? (float) $quote['fee'] : 0).',';
		$sql .= ' \''.$this->db->escape($refInfo['reference']).'\', \''.$this->db->escape($refInfo['source']).'\',';
		$sql .= ' \''.$this->db->escape(isset($transfer['status']) ? (string) $transfer['status'] : '').'\',';
		$sql .= ' \''.self::STATUS_DRAFT.'\', \''.$this->db->escape(dol_now()).'\')';
		if (!$this->db->query($sql)) {
			// Transfer exists at Wise but mapping failed: surface loudly, keep ids in the message
			$out['error'] = 'Transfer created at Wise (#'.$transfer['id'].') but mapping row failed: '.$this->db->lasterror;
			return $out;
		}
		$rowId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'slycustom_wise_transfer');

		dol_syslog('Wise_Payment prepared unfunded transfer wise_id='.$transfer['id'].' invoice='.$inv->ref.' amount='.$targetCurrency.' '.price2num($remaining).' ref='.($refInfo['reference'] ?: '(none)'), LOG_INFO);
		$out['ok'] = true;
		$out['transfer_row'] = $rowId;
		$out['wise_transfer_id'] = (int) $transfer['id'];
		return $out;
	}

	/**
	 * Apply a Wise transfer state to a mapping row (webhook or manual refresh).
	 * Only moves forward: a stale event never regresses the row.
	 *
	 * @param  int    $wiseTransferId Wise transfer id
	 * @param  string $state          current_state from the event / API
	 * @param  string $occurredSqlUtc 'Y-m-d H:i:s' UTC (ordering field) or '' for manual refresh
	 * @return string|null New status applied, null when nothing changed / row not found
	 */
	public function applyTransferState($wiseTransferId, $state, $occurredSqlUtc = '')
	{
		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer WHERE wise_transfer_id = '.(int) $wiseTransferId.' ORDER BY rowid DESC LIMIT 1';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return null;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$row) {
			return null;
		}

		$state = trim((string) $state);
		if ($state === '' || $state === $row->last_state) {
			return null;
		}

		// Only-forward guard on the event timestamp
		if ($occurredSqlUtc !== '' && !empty($row->last_event_at) && $occurredSqlUtc <= $row->last_event_at) {
			dol_syslog('Wise_Payment stale transfer event ignored wise_id='.$wiseTransferId.' state='.$state, LOG_INFO);
			return null;
		}

		$newStatus = $row->status;
		if ($state === 'outgoing_payment_sent' && $row->status === self::STATUS_DRAFT) {
			$newStatus = self::STATUS_SENT;
		} elseif (in_array($state, explode(',', self::CANCEL_STATES)) && $row->status !== self::STATUS_RECORDED) {
			$newStatus = self::STATUS_CANCELLED;
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_transfer SET';
		$sql .= " last_state = '".$this->db->escape($state)."'";
		$sql .= ', status = \''.$this->db->escape($newStatus).'\'';
		$sql .= ($occurredSqlUtc !== '' ? ", last_event_at = '".$this->db->escape($occurredSqlUtc)."'" : ', tms = tms');
		$sql .= ' WHERE rowid = '.(int) $row->rowid;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Payment transfer state update failed: '.$this->db->lasterror, LOG_ERR);
			return null;
		}

		dol_syslog('Wise_Payment transfer wise_id='.$wiseTransferId.' state='.$state.' status='.$row->status.' -> '.$newStatus, LOG_INFO);
		return $newStatus;
	}

	/**
	 * Record the Dolibarr supplier payment for a SENT transfer and close the invoice.
	 * Called automatically from the webhook on outgoing_payment_sent, or manually
	 * from wise/prepare.php as a fallback when the event was lost.
	 *
	 * @param  int  $transferRowId llx_slycustom_wise_transfer.rowid
	 * @param  User $user          Operator performing the write (webhook uses a resolved user)
	 * @return int  PaiementFourn id, or <0 error
	 */
	public function recordSupplierPayment($transferRowId, $user)
	{
		global $conf;

		$sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'slycustom_wise_transfer WHERE rowid = '.(int) $transferRowId;
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$row = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$row) {
			return -1;
		}
		if ($row->status === self::STATUS_RECORDED && $row->fk_paiement_fourn) {
			return -2; // already recorded
		}
		if ($row->status !== self::STATUS_SENT) {
			return -3; // must be SENT (money left Wise) before recording
		}

		// Invoice still open? (may have been paid manually meanwhile)
		$sql = 'SELECT f.rowid, f.ref, f.fk_soc, f.total_ttc, f.multicurrency_code, f.multicurrency_tx, f.paye, f.fk_statut';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'facture_fourn f WHERE f.rowid = '.(int) $row->fk_facture_fourn;
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -4;
		}
		$inv = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$inv || (int) $inv->paye !== 0) {
			$this->setStatus((int) $transferRowId, self::STATUS_RECORDED, 'Invoice already paid/closed, marking transfer recorded');
			return -5;
		}

		$sql = 'SELECT COALESCE(SUM(pf.amount), 0) AS paid FROM '.MAIN_DB_PREFIX.'paiementfourn_facturefourn pf';
		$sql .= ' WHERE pf.fk_facturefourn = '.(int) $inv->rowid;
		$resql = $this->db->query($sql);
		$paid = 0.0;
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$paid = (float) $obj->paid;
			$this->db->free($resql);
		}
		$remaining = (float) $inv->total_ttc - $paid;
		$amount = min((float) $row->target_amount, $remaining);
		if ($amount <= 0.01) {
			$this->setStatus((int) $transferRowId, self::STATUS_RECORDED, 'Nothing left to pay');
			return -5;
		}

		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';

		// Currency context must follow the transfer's own company: the webhook may
		// still run with the receiver default entity while the transfer (and its
		// MAIN_MONNAIE) belongs to another one. Fallback keeps old behaviour.
		$companyCur = strtoupper(trim((string) dolibarr_get_const($this->db, 'MAIN_MONNAIE', (int) $row->entity)));
		if ($companyCur === '') {
			$companyCur = strtoupper((string) $conf->currency);
		}
		$invCur = !empty($inv->multicurrency_code) ? strtoupper((string) $inv->multicurrency_code) : $companyCur;
		$invTx = isset($inv->multicurrency_tx) && (float) $inv->multicurrency_tx > 0 ? (float) $inv->multicurrency_tx : 1.0;

		// Payment mode (per-entity const, default VIR) and bank account (per-currency)
		$modeCode = dolibarr_get_const($this->db, 'WISE_PAYMENT_MODE', (int) $row->entity);
		if ($modeCode === null || trim((string) $modeCode) === '') {
			$modeCode = 'VIR';
		}
		$modeCode = trim((string) $modeCode);
		$modeId = 0;
		$sql = 'SELECT id FROM '.MAIN_DB_PREFIX.'c_paiement WHERE code = \''.$this->db->escape($modeCode).'\' AND entity IN (0, '.(int) $row->entity.') AND active = 1';
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$modeId = (int) $obj->id;
			}
			$this->db->free($resql);
		}

		$bankId = (int) dolibarr_get_const($this->db, 'WISE_BANK_ACCOUNT_'.strtoupper((string) $row->source_currency), (int) $row->entity);
		if ($bankId <= 0) {
			$bankId = (int) dolibarr_get_const($this->db, 'WISE_BANK_ACCOUNT_DEFAULT', (int) $row->entity);
		}

		$pay = new PaiementFourn($this->db);
		$pay->datepaye = dol_now();
		$pay->paiementid = $modeId;
		$pay->num_payment = 'WISE '.trim((string) $row->reference_sent);
		$pay->note_public = 'Wise transfer #'.$row->wise_transfer_id.' ('.$row->target_currency.' '.price2num((float) $row->target_amount).')';
		$pay->fk_account = $bankId; // used by create() for the bank-currency check
		if ($invCur !== $companyCur) {
			// Foreign-currency invoice: pay in its own currency (arrays keyed by invoice id)
			$pay->multicurrency_amounts = array((int) $inv->rowid => (float) $amount);
			$pay->multicurrency_code = array((int) $inv->rowid => $invCur);
			$pay->multicurrency_tx = array((int) $inv->rowid => $invTx);
		} else {
			$pay->amounts = array((int) $inv->rowid => (float) $amount);
		}

		$paymentId = $pay->create($user, 1); // auto-close fully paid supplier invoices
		if ($paymentId <= 0) {
			dol_syslog('Wise_Payment PaiementFourn::create failed: '.json_encode($pay->error), LOG_ERR);
			return -6;
		}

		if ($bankId > 0) {
			$result = $pay->addPaymentToBank($user, 'payment_supplier', '(SupplierInvoicePayment)', $bankId, (string) $row->reference_sent, 'Wise');
			if ($result < 0) {
				dol_syslog('Wise_Payment addPaymentToBank failed for paiement '.$paymentId.': '.json_encode($pay->error), LOG_ERR);
			}
		}

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_transfer SET';
		$sql .= ' status = \''.self::STATUS_RECORDED.'\', fk_paiement_fourn = '.(int) $paymentId.', tms = tms';
		$sql .= ' WHERE rowid = '.(int) $transferRowId;
		$this->db->query($sql);

		dol_syslog('Wise_Payment recorded supplier paiement '.$paymentId.' for transfer row '.$transferRowId, LOG_INFO);
		return $paymentId;
	}

	/**
	 * Update a mapping row status + append a note (best effort).
	 *
	 * @param  int    $rowId Row id
	 * @param  string $status New status
	 * @param  string $note  Note to append
	 * @return void
	 */
	private function setStatus($rowId, $status, $note)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'slycustom_wise_transfer SET';
		$sql .= ' status = \''.$this->db->escape($status).'\'';
		$sql .= ", note = CONCAT(".$this->db->escape($note ? "'".$this->db->escape($note)." '" : 'NULL').", IFNULL(note, ''))";
		$sql .= ' WHERE rowid = '.(int) $rowId;
		if (!$this->db->query($sql)) {
			dol_syslog('Wise_Payment setStatus failed: '.$this->db->lasterror, LOG_WARNING);
		}
	}
}
}
