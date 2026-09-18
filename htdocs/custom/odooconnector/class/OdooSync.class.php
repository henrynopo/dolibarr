<?php
/* Copyright (C) 2025  Odoo Connector (Dolibarr)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Sync customer invoices, vendor bills and expenses from Dolibarr to Odoo.
 * Called by cron or manually from setup.
 *
 * Principles:
 * - Push only (Dolibarr is the master, Odoo is used for accounting).
 * - Sync on validate AND on later modification (Dolibarr tms > last_sync).
 * - Amounts are pushed in the invoice ORIGINAL currency (multicurrency_* fields);
 *   no exchange rate is transferred, Odoo uses its own rates.
 * - Odoo records are created as DRAFT (not posted) so Dolibarr edits can be re-pushed.
 */
class OdooSync
{
	/** @var DoliDB */
	public $db;

	/** @var OdooConnector */
	protected $odoo;

	/** @var Conf */
	protected $conf;

	/** @var int Entity (public: the Dolibarr cron runner writes it from outside
	 * on entity-specific jobs, cron/class/cronjob.class.php run_jobs) */
	public $entity;

	/** @var string Messages for cron/manual-run output (one line per entry, "\n" separated) */
	public $output = '';

	/** @var int Result count for cron */
	public $result = 0;

	/** @var string Last error message (read by the cron runner when a method returns non-zero) */
	public $error = '';

	/** @var array Last error messages (read by the cron runner when a method returns non-zero) */
	public $errors = array();

	/** @var array cache: currency code => res.currency id */
	protected $currencyCache = array();

	/** @var array cache: journal type => account.journal id */
	protected $journalCache = array();

	/** @var array cache: Dolibarr c_country rowid => res.country id (or null) */
	protected $countryCache = array();

	/** @var array cache: Dolibarr societe rowid => res.partner id */
	protected $partnerCache = array();

	/** @var array cache: Odoo model => list of field names (fields_get, once per run) */
	protected $odooFieldsCache = array();

	/** @var bool Whether the sync failure log table exists (guard: a missing table must not break the sync itself) */
	protected $synclogEnabled = true;

	/** @var bool Whether the mapping table exists (guard: without it every run re-pushes documents as duplicates) */
	protected $syncTableEnabled = true;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->conf = null;
		$this->entity = 1;
		$this->output = '';
		$this->result = 0;
	}

	/**
	 * Load Odoo connection from Dolibarr config
	 *
	 * @return bool True if config present and connection OK
	 */
	protected function loadConfig()
	{
		global $conf;

		$url = getDolGlobalString('ODOO_CONNECTOR_URL');
		$db = getDolGlobalString('ODOO_CONNECTOR_DB');
		$user = getDolGlobalString('ODOO_CONNECTOR_USER');
		$pass = getDolGlobalString('ODOO_CONNECTOR_PASSWORD');

		if (empty($url) || empty($db) || empty($user) || empty($pass)) {
			$this->output .= 'Odoo Connector: URL, DB, User or Password not configured.' . "\n";
			if (function_exists('dol_syslog')) {
				dol_syslog(__METHOD__ . ': Missing ODOO_CONNECTOR_* config', LOG_WARNING);
			}
			return false;
		}

		require_once __DIR__ . '/OdooConnector.class.php';
		$this->odoo = new OdooConnector($url, $db, $user, $pass);
		$this->conf = $conf;
		// Clamp entity to >= 1: a cron job declared with entity 0 runs with $conf->entity = 0,
		// which would store mappings under entity 0 and miss records synced manually under entity 1
		$entity = (int) ($conf->entity ?? 1);
		$this->entity = $entity > 0 ? $entity : 1;

		if ($this->odoo->authenticate() === false) {
			$this->output .= 'Odoo Connector: Authentication failed - ' . $this->odoo->error . "\n";
			return false;
		}

		// A missing synclog table must never break the sync itself (failed queries
		// poison the mysqli connection and abort the whole run): detect it once
		// and skip the failure log until the module SQL is executed.
		$this->synclogEnabled = false;
		$sql = "SHOW TABLES LIKE '" . $this->db->escape(MAIN_DB_PREFIX . 'odoo_connector_synclog') . "'";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$this->synclogEnabled = true;
		}

		// Same guard for the mapping table: without it getMapping silently returns
		// null and saveMapping silently fails on every document, so every sync
		// re-creates each invoice in Odoo as a duplicate draft.
		$this->syncTableEnabled = false;
		$sql = "SHOW TABLES LIKE '" . $this->db->escape(MAIN_DB_PREFIX . 'odoo_connector_sync') . "'";
		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			$this->syncTableEnabled = true;
		}

		return true;
	}

	/**
	 * Main entry: run full sync (invoices, vendor bills, expenses).
	 * Used by cron and by "Sync now" in setup.
	 *
	 * @param string $dateFrom  Optional range start (Y-m-d), '' = no lower bound
	 * @param string $dateTo    Optional range end (Y-m-d, inclusive), '' = no upper bound
	 * @param string $dateField Which Dolibarr date column the range applies to: invoice_date, due_date, creation, last_update
	 * @param string $only      Restrict to one entity type: '', invoices, bills, expenses
	 * @return int 0 on success (or nothing to do), <0 on error
	 */
	public function runSync($dateFrom = '', $dateTo = '', $dateField = 'invoice_date', $only = '')
	{
		$this->output = '';
		$this->result = 0;

		if (!$this->loadConfig()) {
			return 0; // cron expects 0 when disabled/misconfigured
		}
		if (!$this->synclogEnabled) {
			$this->output .= 'Odoo Connector: sync failure table is missing (run the module SQL), failures will not be logged.' . "\n";
		}
		if (!$this->syncTableEnabled) {
			$this->output .= 'Odoo Connector: MAPPING TABLE IS MISSING (run the module SQL in phpMyAdmin) - every sync will re-push each document to Odoo as a duplicate draft.' . "\n";
		}

		$syncInvoices = getDolGlobalInt('ODOO_CONNECTOR_SYNC_INVOICES', 1);
		$syncBills = getDolGlobalInt('ODOO_CONNECTOR_SUPPLIER_BILLS', 1);
		$syncExpenses = getDolGlobalInt('ODOO_CONNECTOR_SYNC_EXPENSES', 1);

		if (($only === '' || $only === 'invoices') && $syncInvoices) {
			$this->syncCustomerInvoices($dateFrom, $dateTo, $dateField);
			$this->cleanupRevokedMoves('facture', 'facture', 'ref');
		}
		if (($only === '' || $only === 'bills') && $syncBills) {
			$this->syncVendorBills($dateFrom, $dateTo, $dateField);
			$this->cleanupRevokedMoves('facture_fourn', 'facture_fourn', 'ref');
		}
		if (($only === '' || $only === 'expenses') && $syncExpenses) {
			$this->syncExpenses($dateFrom, $dateTo, $dateField);
		}

		if (function_exists('dol_syslog')) {
			dol_syslog(__METHOD__ . ': Sync done. Result=' . $this->result . '. ' . $this->output, LOG_DEBUG);
		}

		return 0;
	}

	/**
	 * Push one just-validated document to Odoo right away (validate trigger).
	 * Reuses the same pipeline as the cron batch, restricted to a single record,
	 * so dedup rules, deductions and failure logging behave identically.
	 *
	 * @param string $type     'invoice' (Facture) or 'bill' (FactureFournisseur)
	 * @param int    $sourceId Dolibarr rowid of the validated document
	 * @return void
	 */
	public function syncDocumentNow($type, $sourceId)
	{
		$sourceId = (int) $sourceId;
		if ($sourceId <= 0 || ($type !== 'invoice' && $type !== 'bill')) {
			return;
		}

		$this->output = '';
		$this->result = 0;

		if (!$this->loadConfig()) {
			return; // connector disabled/misconfigured: cron will pick the document up later
		}

		if ($type === 'invoice') {
			$this->syncCustomerInvoices('', '', 'invoice_date', $sourceId);
		} else {
			$this->syncVendorBills('', '', 'invoice_date', $sourceId);
		}

		if (function_exists('dol_syslog')) {
			dol_syslog(__METHOD__ . ': push after validate. ' . $this->output, LOG_DEBUG);
		}
	}

	/**
	 * Independent cron entry: refresh the accounting date of the Odoo DRAFT
	 * invoices and vendor bills linked to shipments whose data changed within
	 * the lookback window (ATA/ETA updates, e.g. ShipsGo). The main sync also
	 * includes these documents in its batches (full push/update via the same
	 * window), but per type only within its 500-documents batch ceiling and
	 * inside the manual date ranges; this job is the cheap, unbounded date-only
	 * alignment. Only draft moves are touched: posted entries are never
	 * modified here.
	 *
	 * @return int 0 (count of updated moves is in $this->result)
	 */
	public function runShipmentDateSync()
	{
		$this->output = '';
		$this->result = 0;

		if (!$this->loadConfig()) {
			return 0; // cron expects 0 when disabled/misconfigured
		}
		if (!getDolGlobalInt('ODOO_CONNECTOR_SYNC_SHIPMENT_DATES', 1)) {
			return 0; // disabled in the module setup
		}
		$syncInvoices = getDolGlobalInt('ODOO_CONNECTOR_SYNC_INVOICES', 1);
		$syncBills = getDolGlobalInt('ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS', 1);

		// Lookback window: shipments whose extrafields changed in the last N
		// hours. Overlap with the previous run is harmless (already-correct
		// dates are skipped), the window just has to cover the cron interval.
		$hours = (int) getDolGlobalInt('ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS', 25);
		if ($hours < 1) {
			$hours = 25;
		}

		// 1) Shipments updated within the window
		$expIds = $this->getRecentlyUpdatedExpeditionIds($hours);
		if ($expIds === false) {
			return 0; // query error already traced in the output
		}
		// Every run leaves a trace in the cron output, so "empty output" can
		// always be distinguished from "job did not run".
		$this->output .= 'Shipment date refresh: ' . count($expIds) . ' shipment(s) changed within the last ' . $hours . ' h.' . "\n";
		if (empty($expIds)) {
			return 0; // no shipment changed
		}

		// 2) Linked customer invoices and vendor bills (reverse of the
		// getExpeditionIdFor* lookups the accounting date computation uses)
		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
		$candidates = array('facture' => array(), 'facture_fourn' => array());
		foreach (array_keys($expIds) as $expId) {
			if ($syncInvoices) {
				foreach ($this->getInvoiceIdsForExpedition((int) $expId, true) as $id) {
					$candidates['facture'][(int) $id] = true;
				}
			}
			if ($syncBills) {
				foreach ($this->getInvoiceIdsForExpedition((int) $expId, false) as $id) {
					$candidates['facture_fourn'][(int) $id] = true;
				}
			}
		}

		// 3) Recompute the accounting date and align the mapped Odoo drafts.
		// Every document is accounted for in the closing summary (updated /
		// already correct / posted / not pushed), for auditability.
		$statCorrect = 0;
		$statPosted = 0;
		$statNotPushed = 0;
		$invoice = new Facture($this->db);
		$bill = new FactureFournisseur($this->db);
		foreach ($candidates as $elementType => $ids) {
			if (empty($ids)) {
				continue;
			}
			$odooInfo = $this->getOdooMoveInfo($elementType, array_keys($ids));
			if ($odooInfo === null) {
				continue; // Odoo API failure already traced in the output
			}
			$isCustomer = ($elementType === 'facture');
			foreach (array_keys($ids) as $invoiceId) {
				$mapped = $this->getMapping($elementType, (int) $invoiceId);
				if ($mapped === null || !isset($odooInfo[(int) $mapped->odoo_id])) {
					$statNotPushed++; // not pushed yet (the main sync will create it) or deleted in Odoo
					continue;
				}
				$info = $odooInfo[(int) $mapped->odoo_id];
				if ($info['state'] !== 'draft') {
					$statPosted++; // posted moves are never modified by this job
					continue;
				}
				$obj = $isCustomer ? $invoice : $bill;
				if ($obj->fetch((int) $invoiceId) <= 0) {
					$this->output .= 'Shipment date refresh: FAILED to fetch ' . $elementType . ' #' . (int) $invoiceId . ', skipped.' . "\n";
					continue;
				}
				$dateInv = $isCustomer ? $this->getAccountingDateForCustomerInvoice($obj) : $this->getAccountingDateForSupplierInvoice($obj);
				if ($dateInv === $info['date']) {
					$statCorrect++; // already correct (overlapping runs are no-ops)
					continue;
				}
				$dispRef = $isCustomer ? $obj->ref : ($obj->ref_supplier ?: $obj->ref);
				if ($this->odoo->write('account.move', array((int) $mapped->odoo_id), array('date' => $dateInv))) {
					$this->output .= ($isCustomer ? 'Invoice ' : 'Vendor bill ') . $dispRef . ': accounting date ' . ($info['date'] !== '' ? $info['date'] : 'not set') . ' -> ' . $dateInv . ' (Odoo move ' . (int) $mapped->odoo_id . ', shipment data changed).' . "\n";
					$this->result++;
				} else {
					$this->output .= ($isCustomer ? 'Invoice ' : 'Vendor bill ') . $dispRef . ': accounting date update failed - ' . $this->odoo->error . "\n";
					$this->logFailure($elementType, (int) $invoiceId, $dispRef, (int) $mapped->odoo_id, 'update', 'Shipment date cron - Odoo date update failed: ' . $this->odoo->error);
				}
			}
		}
		$checked = 0;
		foreach ($candidates as $ids) {
			$checked += count($ids);
		}
		$this->output .= 'Shipment date refresh: ' . $checked . ' linked document(s) checked - ' . $this->result . ' updated, ' . $statCorrect . ' already correct, ' . $statPosted . ' posted (skipped), ' . $statNotPushed . ' not pushed yet.' . "\n";

		if (function_exists('dol_syslog')) {
			dol_syslog(__METHOD__ . ': Shipment date refresh done. Result=' . $this->result . '. ' . $this->output, LOG_DEBUG);
		}
		return 0;
	}

	/**
	 * Get mapping row for a Dolibarr record
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $fkSourceId  Dolibarr ID
	 * @return object|null object with ->odoo_id and ->last_sync, or null
	 */
	protected function getMapping($elementType, $fkSourceId)
	{
		$sql = 'SELECT odoo_id, last_sync FROM ' . MAIN_DB_PREFIX . 'odoo_connector_sync';
		$sql .= ' WHERE entity = ' . ((int) $this->entity);
		$sql .= " AND element_type = '" . $this->db->escape($elementType) . "'";
		$sql .= ' AND fk_source_id = ' . ((int) $fkSourceId);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				return $obj;
			}
		}
		return null;
	}

	/**
	 * Save mapping after creating/updating record in Odoo
	 *
	 * @param string   $elementType facture, facture_fourn, expensereport
	 * @param int      $fkSourceId  Dolibarr ID
	 * @param string   $odooModel   account.move, etc.
	 * @param int      $odooId      Odoo record ID
	 * @param string|null $odooWriteDate Odoo write_date if known
	 * @return bool
	 */
	protected function saveMapping($elementType, $fkSourceId, $odooModel, $odooId, $odooWriteDate = null)
	{
		$now = $this->db->idate(dol_now());
		$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . 'odoo_connector_sync';
		$sql .= ' (entity, element_type, fk_source_id, odoo_model, odoo_id, odoo_write_date, last_sync)';
		$sql .= ' VALUES (' . ((int) $this->entity) . ", '" . $this->db->escape($elementType) . "', " . ((int) $fkSourceId);
		$sql .= ", '" . $this->db->escape($odooModel) . "', " . ((int) $odooId);
		$sql .= ', ' . ($odooWriteDate ? "'" . $this->db->escape($odooWriteDate) . "'" : 'NULL');
		$sql .= ", '" . $now . "')";
		$sql .= ' ON DUPLICATE KEY UPDATE odoo_id = ' . ((int) $odooId) . ', odoo_write_date = ' . ($odooWriteDate ? "'" . $this->db->escape($odooWriteDate) . "'" : 'NULL') . ", last_sync = '" . $now . "'";

		$res = $this->db->query($sql) !== false;
		if ($res) {
			$this->clearFailure($elementType, $fkSourceId); // retry succeeded: drop the pending failure entry
		} else {
			// A failed mapping save is otherwise invisible: the document gets re-pushed
			// (and duplicated in Odoo) on every run without any trace in the output.
			$this->output .= 'Odoo Connector: FAILED to save mapping for ' . $elementType . ' #' . (int) $fkSourceId
				. ' (' . $this->db->lasterror . ') - the document will be re-pushed as a duplicate on every sync.' . "\n";
		}
		return $res;
	}

	/**
	 * Delete mapping row for a Dolibarr record
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $fkSourceId  Dolibarr record ID
	 * @return bool
	 */
	protected function deleteMapping($elementType, $fkSourceId)
	{
		$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'odoo_connector_sync';
		$sql .= " WHERE element_type = '" . $this->db->escape($elementType) . "' AND fk_source_id = " . ((int) $fkSourceId);
		$res = $this->db->query($sql) !== false;
		if ($res) {
			$this->clearFailure($elementType, $fkSourceId); // handled: drop the pending failure entry
		}
		return $res;
	}

	/**
	 * Record a failed sync attempt for one document, so it can be listed and
	 * repaired manually in the module setup. The unique key keeps only the
	 * latest failure per document; a later success removes the row.
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $fkSourceId  Dolibarr record ID
	 * @param string $ref         Document reference, for display
	 * @param int    $odooId      Odoo move id when the failure was on an existing move, else 0
	 * @param string $action      create, update, delete, partner, currency, journal
	 * @param string $error       Error message
	 * @return void
	 */
	protected function logFailure($elementType, $fkSourceId, $ref, $odooId, $action, $error)
	{
		if (!$this->synclogEnabled) {
			return;
		}
		$sql = 'REPLACE INTO ' . MAIN_DB_PREFIX . 'odoo_connector_synclog';
		$sql .= ' (entity, element_type, fk_source_id, ref, odoo_id, action, error, date_try)';
		$sql .= ' VALUES (' . ((int) $this->entity);
		$sql .= ", '" . $this->db->escape($elementType) . "'";
		$sql .= ', ' . ((int) $fkSourceId) . ',';
		$sql .= ($ref !== '' ? "'" . $this->db->escape(dol_trunc($ref, 128)) . "'" : 'NULL') . ',';
		$sql .= ($odooId > 0 ? ((int) $odooId) : 'NULL') . ',';
		$sql .= " '" . $this->db->escape($action) . "',";
		$sql .= " '" . $this->db->escape(dol_trunc($error, 2000)) . "',";
		$sql .= " '" . $this->db->idate(dol_now()) . "')";
		$this->db->query($sql);
	}

	/**
	 * Remove the pending failure entry of a document (called when a retry succeeds)
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $fkSourceId  Dolibarr record ID
	 * @return void
	 */
	protected function clearFailure($elementType, $fkSourceId)
	{
		if (!$this->synclogEnabled) {
			return;
		}
		$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . 'odoo_connector_synclog';
		$sql .= ' WHERE entity = ' . ((int) $this->entity);
		$sql .= " AND element_type = '" . $this->db->escape($elementType) . "'";
		$sql .= ' AND fk_source_id = ' . ((int) $fkSourceId);
		$this->db->query($sql);
	}

	/**
	 * Tell whether a document currently has a pending failure entry
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $fkSourceId  Dolibarr record ID
	 * @return bool
	 */
	protected function hasFailure($elementType, $fkSourceId)
	{
		if (!$this->synclogEnabled) {
			return false;
		}
		$sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'odoo_connector_synclog';
		$sql .= ' WHERE entity = ' . ((int) $this->entity);
		$sql .= " AND element_type = '" . $this->db->escape($elementType) . "'";
		$sql .= ' AND fk_source_id = ' . ((int) $fkSourceId);
		$resql = $this->db->query($sql);
		if ($resql) {
			return $this->db->fetch_object($resql) !== null;
		}
		return false;
	}

	/**
	 * Post a note on the Odoo move chatter (mail.thread message)
	 *
	 * @param int    $odooMoveId account.move id
	 * @param string $body       Message body
	 * @return bool
	 */
	protected function postChatterNote($odooMoveId, $body)
	{
		return $this->odoo->executeKw('account.move', 'message_post', array(array((int) $odooMoveId)), array('body' => $body)) !== false;
	}

	/**
	 * References of the orders linked to the invoice: customer order (SO) for
	 * customer invoices; supplier order (PO) and customer order (SO) for vendor
	 * bills. Empty array when none.
	 *
	 * @param Facture|FactureFournisseur $invoice  Loaded invoice
	 * @param bool                       $customer true for Facture
	 * @return string[]
	 */
	protected function getLinkedOrderRefs($invoice, $customer)
	{
		$refs = array();
		$invoice->fetchObjectLinked();
		if ($customer && !empty($invoice->linkedObjects['commande'])) {
			foreach ($invoice->linkedObjects['commande'] as $order) {
				$refs[] = $order->ref;
			}
		}
		if (!$customer) {
			if (!empty($invoice->linkedObjects['order_supplier'])) {
				foreach ($invoice->linkedObjects['order_supplier'] as $order) {
					$refs[] = $order->ref;
				}
			}
			// A vendor bill may also be linked to the customer order it fulfils
			if (!empty($invoice->linkedObjects['commande'])) {
				foreach ($invoice->linkedObjects['commande'] as $order) {
					$refs[] = $order->ref;
				}
			}
		}
		return array_values(array_unique($refs));
	}

	/**
	 * Tell whether a payment was registered on the document since the last sync.
	 * Paying refreshes the source tms (MySQL ON UPDATE) without changing the
	 * pushed data: used to distinguish payment-only changes from real edits.
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $sourceId    Dolibarr record ID
	 * @param string $lastSync    Mapping last_sync (Y-m-d H:i:s)
	 * @return bool True when a payment row was written after the last sync
	 */
	protected function hasPaymentSince($elementType, $sourceId, $lastSync)
	{
		$prefix = $this->db->prefix();
		if ($elementType === 'facture') {
			$sql = "SELECT p.rowid FROM ".$prefix."paiement p";
			$sql .= " JOIN ".$prefix."paiement_facture pf ON pf.fk_paiement = p.rowid";
			$sql .= " WHERE pf.fk_facture = ".((int) $sourceId);
		} elseif ($elementType === 'facture_fourn') {
			$sql = "SELECT p.rowid FROM ".$prefix."paiementfourn p";
			$sql .= " JOIN ".$prefix."paiementfourn_facturefourn pf ON pf.fk_paiementfourn = p.rowid";
			$sql .= " WHERE pf.fk_facturefourn = ".((int) $sourceId);
		} elseif ($elementType === 'expensereport') {
			$sql = "SELECT p.rowid FROM ".$prefix."payment_expensereport p";
			$sql .= " JOIN ".$prefix."paymentexpensereport_expensereport pe ON pe.fk_payment = p.rowid";
			$sql .= " WHERE pe.fk_expensereport = ".((int) $sourceId);
		} else {
			return false;
		}
		$sql .= " AND p.tms > '".$this->db->escape($lastSync)."' LIMIT 1";
		$resql = $this->db->query($sql);
		if ($resql) {
			return $this->db->fetch_object($resql) !== null;
		}
		return false;
	}

	/**
	 * Absolute discounts consumed by the invoice (societe_remise_except rows,
	 * source = deposit or credit note converted to a discount in Dolibarr).
	 * The currency set follows the INVOICE, not the row: the discount table has
	 * multicurrency amount columns but no usable currency code.
	 *
	 * @param Facture|FactureFournisseur $invoice  Loaded invoice
	 * @param bool                       $customer true for Facture
	 * @param bool                       $useMc    True when the invoice itself pushes in a foreign currency
	 * @return array[] each: ht, tva, ttc, source_type, source_ref
	 */
	protected function getInvoiceDiscounts($invoice, $customer, $useMc = false)
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT re.amount_ht, re.amount_tva, re.amount_ttc,";
		$sql .= " re.multicurrency_amount_ht, re.multicurrency_amount_tva, re.multicurrency_amount_ttc, re.multicurrency_code,";
		if ($customer) {
			$sql .= " f.type AS source_type, f.ref AS source_ref";
			$sql .= " FROM ".$prefix."societe_remise_except re";
			$sql .= " LEFT JOIN ".$prefix."facture f ON f.rowid = re.fk_facture_source";
			$sql .= " WHERE re.fk_facture = ".((int) $invoice->id);
		} else {
			$sql .= " f.type AS source_type, f.ref AS source_ref";
			$sql .= " FROM ".$prefix."societe_remise_except re";
			$sql .= " LEFT JOIN ".$prefix."facture_fourn f ON f.rowid = re.fk_invoice_supplier_source";
			$sql .= " WHERE re.fk_invoice_supplier = ".((int) $invoice->id);
		}
		$discounts = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			// A broken lookup must never look like "no discounts": say why loudly
			$this->output .= ($customer ? 'Customer invoices' : 'Vendor bills') . ': discount lookup failed - ' . $this->db->lasterror . "\n";
			return $discounts;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$ht = (double) ($useMc ? $obj->multicurrency_amount_ht : $obj->amount_ht);
			$tva = (double) ($useMc ? $obj->multicurrency_amount_tva : $obj->amount_tva);
			$ttc = (double) ($useMc ? $obj->multicurrency_amount_ttc : $obj->amount_ttc);
			if ($ht == 0 && $tva == 0 && $ttc == 0) {
				// legacy row without the selected currency set filled: take whatever is there
				$ht = (double) $obj->amount_ht;
				$tva = (double) $obj->amount_tva;
				$ttc = (double) $obj->amount_ttc;
			}
			$discounts[] = array(
				'ht' => $ht,
				'tva' => $tva,
				'ttc' => $ttc,
				'source_type' => $obj->source_type !== null ? (int) $obj->source_type : -1,
				'source_ref' => $obj->source_ref !== null ? $obj->source_ref : '',
			);
		}
		return $discounts;
	}

	/**
	 * Third party that actually bills/pays a customer invoice: the company of
	 * its external BILLING contact when it differs from the invoice customer.
	 * Returns array(socid, name), or null to keep the invoice customer.
	 *
	 * @param Facture $invoice Loaded customer invoice
	 * @return array|null
	 */
	protected function getInvoiceBillingPartner($invoice)
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT s.rowid, s.nom";
		$sql .= " FROM ".$prefix."element_contact ec";
		$sql .= " JOIN ".$prefix."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact";
		$sql .= " AND tc.element = 'facture' AND tc.code = 'BILLING' AND tc.source = 'external' AND tc.active = 1";
		$sql .= " JOIN ".$prefix."contact c ON c.rowid = ec.fk_socpeople";
		$sql .= " JOIN ".$prefix."societe s ON s.rowid = c.fk_soc";
		$sql .= " WHERE ec.element_id = ".((int) $invoice->id)." AND ec.statut = 4";
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj && (int) $obj->rowid > 0 && (int) $obj->rowid != (int) $invoice->socid) {
				return array((int) $obj->rowid, $obj->nom);
			}
		}
		return null;
	}

	/**
	 * Handle a Dolibarr change on a document whose Odoo move is already posted
	 * (reconciled): the move is never overwritten. A chatter warning is posted
	 * on the move the first time (until the document is handled), and the change
	 * is kept in the failure log. Resetting the move to draft in Odoo lets the
	 * next sync apply the change and clear the entry.
	 *
	 * @param string $elementType facture, facture_fourn, expensereport
	 * @param int    $sourceId    Dolibarr record ID
	 * @param string $ref         Document reference
	 * @param int    $odooId      Odoo move id
	 * @param string $summary     Short description of the new values
	 * @return void
	 */
	protected function notifyPostedMoveChanged($elementType, $sourceId, $ref, $odooId, $summary)
	{
		if (!$this->hasFailure($elementType, $sourceId)) {
			$this->postChatterNote($odooId, 'Dolibarr document ' . $ref . ' was modified after this entry was posted (' . $summary . ') '
				. 'Review it in Dolibarr, then either reset this entry to draft so the Dolibarr sync applies the change, or post a manual adjustment.');
		}
		// Flag the entry To Review in Odoo (works on posted entries too, unlike the data itself)
		if (!$this->odoo->write('account.move', array((int) $odooId), array('review_state' => 'todo'))) {
			$this->output .= 'Odoo move ' . (int) $odooId . ': could not set review state to To Review - ' . $this->odoo->error . "\n";
		}
		$this->logFailure($elementType, $sourceId, $ref, $odooId, 'update_posted', 'Odoo move is already posted, Dolibarr change not pushed (' . $summary . ')');
	}

	// ---------------------------------------------------------------
	// Odoo lookup helpers
	// ---------------------------------------------------------------

	/**
	 * Get Odoo company id to write records into (fixed, not user default)
	 *
	 * @return int
	 */
	protected function getOdooCompanyId()
	{
		$id = (int) getDolGlobalInt('ODOO_CONNECTOR_COMPANY_ID');
		return $id > 0 ? $id : 1;
	}

	/**
	 * Get or create Odoo res.currency from ISO code
	 *
	 * @param string $code ISO currency code (USD, SGD, ...)
	 * @return int|null res.currency id or null on failure
	 */
	protected function getOrCreateCurrencyId($code)
	{
		$code = strtoupper(trim((string) $code));
		if ($code === '') {
			return null;
		}
		if (isset($this->currencyCache[$code])) {
			return $this->currencyCache[$code];
		}

		$found = $this->odoo->searchRead('res.currency', array(array('name', '=', $code)), array('id'), 1);
		if (!empty($found) && isset($found[0]['id'])) {
			return $this->currencyCache[$code] = (int) $found[0]['id'];
		}

		$newId = $this->odoo->create('res.currency', array('name' => $code, 'symbol' => $code, 'full_name' => $code));
		if ($newId) {
			return $this->currencyCache[$code] = (int) $newId;
		}
		return null;
	}

	/**
	 * Get Odoo journal id for the journal type ('sale' or 'purchase').
	 * Config const overrides, else first journal of that type in the company.
	 *
	 * @param string $journalType sale|purchase
	 * @return int|null account.journal id or null
	 */
	protected function getJournalId($journalType)
	{
		if (isset($this->journalCache[$journalType])) {
			return $this->journalCache[$journalType];
		}

		if ($journalType === 'purchase') {
			$configKey = 'ODOO_CONNECTOR_PURCHASE_JOURNAL_ID';
		} elseif ($journalType === 'expense') {
			$configKey = 'ODOO_CONNECTOR_EXPENSE_JOURNAL_ID';
		} else {
			$configKey = 'ODOO_CONNECTOR_SALES_JOURNAL_ID';
		}
		$journalId = (int) getDolGlobalInt($configKey);
		if ($journalId > 0) {
			return $this->journalCache[$journalType] = $journalId;
		}

		// Expense reports post on a 'general' journal in Odoo, fallback to 'purchase'
		$searchTypes = ($journalType === 'expense') ? array('general', 'purchase') : array($journalType);
		foreach ($searchTypes as $searchType) {
			$found = $this->odoo->searchRead(
				'account.journal',
				array(array('type', '=', $searchType), array('company_id', '=', $this->getOdooCompanyId())),
				array('id'),
				1
			);
			if (!empty($found) && isset($found[0]['id'])) {
				return $this->journalCache[$journalType] = (int) $found[0]['id'];
			}
		}
		return null;
	}

	/**
	 * Get amounts in the invoice ORIGINAL currency.
	 * Uses multicurrency totals only when the multicurrency module is enabled
	 * and the invoice is in a foreign currency; otherwise falls back to
	 * main-currency totals and the system default currency.
	 *
	 * @param Facture|FactureFournisseur|ExpenseReport $object
	 * @return array{code:string, ht:float, tva:float, ttc:float}
	 */
	protected function getAmountsInInvoiceCurrency($object)
	{
		$mainCode = strtoupper((string) ($this->conf ? ($this->conf->currency ?? '') : ''));
		if ($mainCode === '') {
			$mainCode = 'USD';
		}

		$code = strtoupper(trim((string) ($object->multicurrency_code ?? '')));
		$mcHt = (float) ($object->multicurrency_total_ht ?? 0);
		$mcTva = (float) ($object->multicurrency_total_tva ?? 0);
		$mcTtc = (float) ($object->multicurrency_total_ttc ?? 0);

		// Use original-currency amounts only when multicurrency module is enabled,
		// the invoice currency is a foreign one and totals are filled (legacy rows may be empty)
		if (isModEnabled('multicurrency') && $code !== '' && $code !== $mainCode && ($mcTtc != 0 || $mcHt != 0)) {
			return array('code' => $code, 'ht' => $mcHt, 'tva' => $mcTva, 'ttc' => $mcTtc, 'mc' => true);
		}

		return array(
			'code' => $mainCode,
			'ht' => (float) ($object->total_ht ?? 0),
			'tva' => (float) ($object->total_tva ?? 0),
			'ttc' => (float) ($object->total_ttc ?? 0),
			'mc' => false,
		);
	}

	/**
	 * Get or create Odoo res.partner from Dolibarr societe.
	 * Match chain (first hit wins): exact Odoo ref = Dolibarr code_client or
	 * code_fournisseur (role-preferred order), then VAT number, then a unique
	 * company email. A partner matched by VAT/email gets the Dolibarr code
	 * written back into its empty ref so the next run takes the fast path.
	 * Name is NEVER a match key (a homonym would silently merge two
	 * companies); same-name partners are only reported in the output for a
	 * manual merge in Odoo.
	 *
	 * @param int  $socid    Dolibarr societe rowid
	 * @param bool $customer true for a customer invoice, false for a vendor bill (code preference)
	 * @return int|null Odoo partner_id or null on failure
	 */
	protected function getOrCreatePartner($socid, $customer = true)
	{
		if ($socid <= 0) {
			return null;
		}
		if (isset($this->partnerCache[$socid])) {
			return $this->partnerCache[$socid];
		}

		require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
		$soc = new Societe($this->db);
		if ($soc->fetch($socid) <= 0) {
			return null;
		}

		// Dolibarr codes to look up in the Odoo ref field, role-preferred first
		$codeClient = trim((string) $soc->code_client);
		$codeFourn = trim((string) $soc->code_fournisseur);
		$refs = $customer
			? array_merge($codeClient !== '' ? array($codeClient) : array(), $codeFourn !== '' ? array($codeFourn) : array())
			: array_merge($codeFourn !== '' ? array($codeFourn) : array(), $codeClient !== '' ? array($codeClient) : array());
		// Ref written on create and on backfill: the role-preferred code, else a stable synthetic one
		$primaryRef = !empty($refs) ? $refs[0] : ('DOL-' . $socid);

		// 1) Exact ref match: the partner may exist with either Dolibarr code
		// (created from the other role or backfilled earlier)
		if (!empty($refs)) {
			$existing = $this->odoo->searchRead('res.partner', array(array('ref', 'in', $refs)), array('id'), 2, 0, 'id asc');
			if (!empty($existing) && isset($existing[0]['id'])) {
				return $this->partnerCache[$socid] = (int) $existing[0]['id'];
			}
		}

		// 2) VAT number: Dolibarr stores it with or without the country prefix,
		// Odoo always with - try both spellings
		$vat = strtoupper(preg_replace('/\s+/', '', (string) $soc->tva_intra));
		if ($vat !== '') {
			$vatCandidates = array($vat);
			$cc = strtoupper(trim((string) $soc->country_code));
			if ($cc !== '' && strpos($vat, $cc) !== 0) {
				$vatCandidates[] = $cc . $vat;
			}
			foreach ($vatCandidates as $vatCand) {
				$found = $this->odoo->searchRead('res.partner', array(array('vat', '=', $vatCand)), array('id', 'ref'), 2, 0, 'id asc');
				if (!empty($found) && isset($found[0]['id'])) {
					$id = (int) $found[0]['id'];
					$this->backfillPartnerRef($id, $found[0], $primaryRef, 'VAT ' . $vatCand, $soc);
					return $this->partnerCache[$socid] = $id;
				}
			}
		}

		// 3) Email: only an unambiguous single company hit is adopted (generic
		// addresses like info@... are shared by several partners)
		$email = trim((string) $soc->email);
		if ($email !== '') {
			$found = $this->odoo->searchRead('res.partner', array(array('email', '=', $email), array('is_company', '=', true)), array('id', 'ref'), 2, 0, 'id asc');
			if (is_array($found) && count($found) === 1 && isset($found[0]['id'])) {
				$id = (int) $found[0]['id'];
				$this->backfillPartnerRef($id, $found[0], $primaryRef, 'email ' . $email, $soc);
				return $this->partnerCache[$socid] = $id;
			}
		}

		// 4) Create. Same-name partners (typical of the pre-connector Odoo base,
		// with an empty ref) are reported for a manual merge in Odoo instead of
		// being silently adopted.
		$sameName = $this->odoo->searchRead('res.partner', array(array('name', '=', (string) $soc->name)), array('id'), 3, 0, 'id asc');
		if (!empty($sameName) && isset($sameName[0]['id'])) {
			$this->output .= 'Partner ' . $soc->name . ' (Dolibarr #' . (int) $socid . '): ' . count($sameName) . ' same-name partner(s) exist in Odoo but no code/VAT/email matched; a new partner was created. Merge the duplicates in Odoo (Contacts > Merge) into the partner holding ref ' . $primaryRef . '.' . "\n";
		}

		$data = array(
			'name' => $soc->name,
			'ref' => $primaryRef,
			'is_company' => true,
		);
		if (!empty($soc->email)) {
			$data['email'] = $soc->email;
		}
		if (!empty($soc->tva_intra)) {
			$data['vat'] = $soc->tva_intra;
		}
		if (!empty($soc->address)) {
			$data['street'] = $soc->address;
		}
		if (!empty($soc->zip)) {
			$data['zip'] = $soc->zip;
		}
		if (!empty($soc->town)) {
			$data['city'] = $soc->town;
		}
		$countryId = $this->getOdooCountryId((int) ($soc->country_id ?? 0));
		if ($countryId !== null) {
			$data['country_id'] = $countryId;
		}
		if (!empty($soc->phone)) {
			$data['phone'] = $soc->phone;
		}
		// customer_rank/supplier_rank were removed in Odoo 18: write only when the model still has the field
		$rankField = $customer ? 'customer_rank' : 'supplier_rank';
		if ($this->odooFieldExists('res.partner', $rankField)) {
			$data[$rankField] = 1;
		}
		$newId = $this->odoo->create('res.partner', $data);
		return $newId ? ($this->partnerCache[$socid] = (int) $newId) : null;
	}

	/**
	 * Write the Dolibarr code into the (empty) ref of a partner matched by
	 * VAT or email, so the next sync takes the exact-ref fast path, and set
	 * the customer/supplier flag for the roles the third party has.
	 *
	 * @param int     $partnerId Odoo res.partner id
	 * @param array   $foundRow  search_read row (must contain 'ref')
	 * @param string  $ref       Dolibarr code to backfill
	 * @param string  $via       How the partner was matched, for the output trace
	 * @param Societe $soc       Dolibarr third party
	 * @return void
	 */
	protected function backfillPartnerRef($partnerId, $foundRow, $ref, $via, $soc)
	{
		$write = array();
		if (empty($foundRow['ref'])) {
			$write['ref'] = $ref;
		}
		if (!empty($soc->client) && $this->odooFieldExists('res.partner', 'customer_rank')) {
			$write['customer_rank'] = 1;
		}
		if (!empty($soc->fournisseur) && $this->odooFieldExists('res.partner', 'supplier_rank')) {
			$write['supplier_rank'] = 1;
		}
		if (empty($write)) {
			return;
		}
		if ($this->odoo->write('res.partner', array((int) $partnerId), $write)) {
			if (isset($write['ref'])) {
				$this->output .= 'Partner ' . $soc->name . ': matched existing Odoo partner #' . (int) $partnerId . ' by ' . $via . ', ref backfilled with ' . $ref . '.' . "\n";
			}
		} else {
			$this->output .= 'Partner ' . $soc->name . ': matched existing Odoo partner #' . (int) $partnerId . ' by ' . $via . ', but the ref backfill failed - ' . $this->odoo->error . "\n";
		}
	}

	/**
	 * Get or create the Odoo partner used for expense report moves
	 * (expense reports have no third party in Dolibarr).
	 *
	 * @return int|null Odoo partner_id or null on failure
	 */
	protected function getOrCreateExpensePartner()
	{
		$ref = 'DOL-EXPENSES';
		$existing = $this->odoo->searchRead('res.partner', array(array('ref', '=', $ref)), array('id'), 1);
		if (!empty($existing) && isset($existing[0]['id'])) {
			return (int) $existing[0]['id'];
		}
		$data = array(
			'name' => 'Employee expenses (Dolibarr)',
			'ref' => $ref,
			'is_company' => false,
		);
		// supplier_rank was removed in Odoo 18: write only when the model still has the field
		if ($this->odooFieldExists('res.partner', 'supplier_rank')) {
			$data['supplier_rank'] = 1;
		}
		$newId = $this->odoo->create('res.partner', $data);
		return $newId ? (int) $newId : null;
	}

	/**
	 * Tell whether a field exists on an Odoo model (fields_get, cached per
	 * model for the run). Odoo removes fields across versions (e.g.
	 * res.partner customer_rank/supplier_rank removed in Odoo 18): writing a
	 * removed field fails the whole create/write, so version-sensitive fields
	 * are written only when present.
	 *
	 * @param string $model Odoo model name
	 * @param string $field Field name
	 * @return bool False also when the fields_get call itself fails (safe default: skip the write)
	 */
	protected function odooFieldExists($model, $field)
	{
		if (!isset($this->odooFieldsCache[$model])) {
			$fields = $this->odoo->executeKw($model, 'fields_get', array(), array('attributes' => array('string', 'type')));
			$this->odooFieldsCache[$model] = is_array($fields) ? array_keys($fields) : array();
		}
		return in_array($field, $this->odooFieldsCache[$model], true);
	}

	// ---------------------------------------------------------------
	// Sync: invoices and vendor bills
	// ---------------------------------------------------------------

	/**
	 * Return the delivery address of a Dolibarr invoice: its SHIPPING external
	 * contact, as selected on the invoice "Contacts/Addresses" tab.
	 *
	 * @param CommonObject $invoice Customer invoice
	 * @return array|null array('id', 'name', 'street', 'zip', 'city', 'country_id') or null when none
	 */
	protected function getInvoiceShippingContact($invoice)
	{
		$contacts = $invoice->liste_contact(-1, 'external', 0, 'SHIPPING');
		if (!is_array($contacts) || empty($contacts)) {
			return null;
		}
		foreach ($contacts as $contact) {
			if (!empty($contact['status']) && $contact['status'] != 4) {
				continue; // link disabled on the invoice (ec.statut: 4 actif, 5 inactif)
			}
			$name = trim(trim($contact['firstname'] . ' ' . $contact['lastname']));
			return array(
				'id' => (int) $contact['id'],
				'name' => $name !== '' ? $name : 'Delivery address',
				'street' => (string) ($contact['address'] ?? ''),
				'zip' => (string) ($contact['zip'] ?? ''),
				'city' => (string) ($contact['town'] ?? ''),
				'country_id' => (int) ($contact['country_id'] ?? 0),
			);
		}
		return null;
	}

	/**
	 * Map a Dolibarr country (c_country.rowid) to the Odoo res.country id.
	 * Both sides use the ISO alpha-2 code.
	 *
	 * @param int $doliCountryId Dolibarr c_country rowid
	 * @return int|null Odoo country id or null when no match
	 */
	protected function getOdooCountryId($doliCountryId)
	{
		if ($doliCountryId <= 0) {
			return null;
		}
		if (!array_key_exists($doliCountryId, $this->countryCache)) {
			$this->countryCache[$doliCountryId] = null;
			$sql = "SELECT code FROM " . $this->db->prefix() . "c_country WHERE rowid = " . (int) $doliCountryId;
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql)) && $obj->code !== null) {
				$found = $this->odoo->searchRead('res.country', array(array('code', '=', $obj->code)), array('id'), 1);
				if (!empty($found) && isset($found[0]['id'])) {
					$this->countryCache[$doliCountryId] = (int) $found[0]['id'];
				}
			}
		}
		return $this->countryCache[$doliCountryId];
	}

	/**
	 * Get or create the Odoo delivery-address contact (res.partner child with
	 * type 'delivery') matching the SHIPPING contact of the Dolibarr invoice.
	 * Match by Dolibarr ref 'DOL-CT-{contact id}'; address fields are refreshed
	 * on every push (Dolibarr is the source of truth).
	 *
	 * @param CommonObject $invoice   Customer invoice
	 * @param int          $partnerId Odoo res.partner id of the invoiced customer
	 * @return int|null Odoo partner id, or null when no SHIPPING contact / on failure
	 */
	protected function getOrCreateDeliveryAddress($invoice, $partnerId)
	{
		$contact = $this->getInvoiceShippingContact($invoice);
		if ($contact === null) {
			return null;
		}

		$ref = 'DOL-CT-' . $contact['id'];
		$data = array(
			'name' => $contact['name'],
			'type' => 'delivery',
			'parent_id' => $partnerId,
			'ref' => $ref,
		);
		if ($contact['street'] !== '') {
			$data['street'] = $contact['street'];
		}
		if ($contact['zip'] !== '') {
			$data['zip'] = $contact['zip'];
		}
		if ($contact['city'] !== '') {
			$data['city'] = $contact['city'];
		}
		$countryId = $this->getOdooCountryId($contact['country_id']);
		if ($countryId !== null) {
			$data['country_id'] = $countryId;
		}

		$existing = $this->odoo->searchRead('res.partner', array(array('ref', '=', $ref)), array('id'), 1);
		if (!empty($existing) && isset($existing[0]['id'])) {
			$this->odoo->write('res.partner', array((int) $existing[0]['id']), $data);
			return (int) $existing[0]['id'];
		}

		$newId = $this->odoo->create('res.partner', $data);
		return $newId ? (int) $newId : null;
	}

	/**
	 * Sync customer invoices (Facture) to Odoo account.move (out_invoice)
	 *
	 * @param string $dateFrom  Optional date filter (Y-m-d), '' = no limit
	 * @param string $dateTo    Optional date filter (Y-m-d, inclusive), '' = no limit
	 * @param string $dateField Date field used by the filter: invoice_date, due_date, creation, last_update
	 * @param int    $singleId  Restrict to one Dolibarr invoice rowid (0 = no restriction)
	 */
	protected function syncCustomerInvoices($dateFrom = '', $dateTo = '', $dateField = 'invoice_date', $singleId = 0)
	{
		if (!isModEnabled('invoice')) {
			return;
		}
		$this->syncInvoices(true, $dateFrom, $dateTo, $dateField, $singleId);
	}

	/**
	 * Sync vendor bills (FactureFournisseur) to Odoo account.move (in_invoice)
	 *
	 * @param string $dateFrom  Optional date filter (Y-m-d), '' = no limit
	 * @param string $dateTo    Optional date filter (Y-m-d, inclusive), '' = no limit
	 * @param string $dateField Date field used by the filter: invoice_date, due_date, creation, last_update
	 * @param int    $singleId  Restrict to one Dolibarr vendor bill rowid (0 = no restriction)
	 */
	protected function syncVendorBills($dateFrom = '', $dateTo = '', $dateField = 'invoice_date', $singleId = 0)
	{
		if (!isModEnabled('supplier_invoice')) {
			return;
		}
		$this->syncInvoices(false, $dateFrom, $dateTo, $dateField, $singleId);
	}

	/**
	 * Common sync of invoices or vendor bills to Odoo account.move (draft).
	 * Creates when not mapped, updates the Odoo draft move when Dolibarr tms > last_sync.
	 *
	 * @param bool   $customer  true for Facture, false for FactureFournisseur
	 * @param string $dateFrom  Optional date filter (Y-m-d), '' = no limit
	 * @param string $dateTo    Optional date filter (Y-m-d, inclusive), '' = no limit
	 * @param string $dateField Date field used by the filter: invoice_date, due_date, creation, last_update
	 * @param int    $singleId  Restrict to one Dolibarr invoice/bill rowid (0 = no restriction)
	 */
	protected function syncInvoices($customer, $dateFrom = '', $dateTo = '', $dateField = 'invoice_date', $singleId = 0)
	{
		require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
		require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';

		if ($customer) {
			$elementType = 'facture';
			$entityKey = 'invoice';
			$table = 'facture';
		} else {
			$elementType = 'facture_fourn';
			$entityKey = 'facture_fourn';
			$table = 'facture_fourn';
		}

		// Shared validity conditions (entity, status, consumed-credit-note
		// exclusion): used by the main tms-ranked selection below and again by
		// the shipment-driven inclusion that follows it.
		$sqlWhere = ' WHERE f.entity IN (' . getEntity($entityKey) . ')';
		$sqlWhere .= " AND f.fk_statut IN (1, 2)"; // validated or paid (both are valid documents)
		// A credit note consumed as a discount is NOT pushed: its amount is already
		// inside the consuming invoice/bill net totals (Dolibarr books consumed
		// discounts as invoice lines), so pushing it too would deduct the amount
		// twice in Odoo. Discounts coming from a standard invoice (type = 1)
		// must NOT exclude their source, hence the src.type = 2 condition.
		if ($customer) {
			$sqlWhere .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'societe_remise_except re';
			$sqlWhere .= ' JOIN ' . MAIN_DB_PREFIX . 'facture src ON src.rowid = re.fk_facture_source';
			$sqlWhere .= ' WHERE re.fk_facture = f.rowid AND src.type = ' . Facture::TYPE_CREDIT_NOTE . ')';
		} else {
			$sqlWhere .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'societe_remise_except re';
			$sqlWhere .= ' JOIN ' . MAIN_DB_PREFIX . 'facture_fourn src ON src.rowid = re.fk_invoice_supplier_source';
			$sqlWhere .= ' WHERE re.fk_invoice_supplier = f.rowid AND src.type = ' . FactureFournisseur::TYPE_CREDIT_NOTE . ')';
		}
		if ($singleId > 0) {
			$sqlWhere .= ' AND f.rowid = ' . ((int) $singleId);
		}
		$sql = 'SELECT f.rowid, f.tms FROM ' . MAIN_DB_PREFIX . $table . ' f' . $sqlWhere;
		// Optional date range filter; the date column depends on the selected field
		// (facture: date / date_lim_reglement, facture_fourn: datef / date_echeance)
		// Date column used by the range filter (facture: datef, facture_fourn: datef in Dolibarr 21+)
		$dateColumn = 'f.datef';
		if (!$customer) {
			$dateColumn = ($dateField === 'due_date') ? 'f.date_lim_reglement' : 'f.datef';
			if ($dateField === 'creation') {
				$dateColumn = 'f.datec';
			} elseif ($dateField === 'last_update') {
				$dateColumn = 'f.tms';
			}
		} elseif ($dateField === 'due_date') {
			$dateColumn = 'f.date_lim_reglement';
		} elseif ($dateField === 'creation') {
			$dateColumn = 'f.datec';
		} elseif ($dateField === 'last_update') {
			$dateColumn = 'f.tms';
		}
		if ($dateFrom !== '') {
			$sql .= " AND " . $dateColumn . " >= '" . $this->db->escape($dateFrom) . "'";
		}
		if ($dateTo !== '') {
			$sql .= " AND " . $dateColumn . " < '" . $this->db->escape(date('Y-m-d', strtotime($dateTo . ' +1 day'))) . "'";
		}
		$sql .= ' ORDER BY f.tms DESC';
		$sql .= ' LIMIT 500';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->output .= ($customer ? 'Customer invoices' : 'Vendor bills') . ': query error - ' . $this->db->lasterror . "\n";
			return;
		}
		// The limit protects the cron run: tell the user to narrow the manual range
		if ($this->db->num_rows($resql) >= 500) {
			$this->output .= ($customer ? 'Customer invoices' : 'Vendor bills') . ': batch limit of 500 reached, narrow the date range and run again.' . "\n";
		}

		// Main selection: the most recently modified documents (rows keyed by rowid)
		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[(int) $obj->rowid] = $obj;
		}

		// Shipment-driven inclusion: when the accounting date follows the
		// shipment, a document whose linked shipment changed within the lookback
		// window must be pushed even when the document itself is old and fell
		// out of the tms-ranked top-500 window above (sea freight: the ATA
		// arrives months after the invoice date). Same window and settings as
		// the shipment date refresh cron job.
		if ($singleId <= 0
			&& $this->getAccountingDateSource() === 'delivery_reception'
			&& getDolGlobalInt('ODOO_CONNECTOR_SYNC_SHIPMENT_DATES', 1)) {
			$hours = (int) getDolGlobalInt('ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS', 25);
			if ($hours < 1) {
				$hours = 25;
			}
			$expIds = $this->getRecentlyUpdatedExpeditionIds($hours);
			if ($expIds === false) {
				$expIds = array(); // query error already traced: degrade to the tms window only
			}
			$extraIds = array();
			foreach ($expIds as $expId) {
				foreach ($this->getInvoiceIdsForExpedition((int) $expId, $customer) as $id) {
					$extraIds[(int) $id] = true;
				}
			}
			if (!empty($extraIds)) {
				$sql2 = 'SELECT f.rowid, f.tms FROM ' . MAIN_DB_PREFIX . $table . ' f' . $sqlWhere;
				$sql2 .= ' AND f.rowid IN (' . implode(',', array_map('intval', array_keys($extraIds))) . ')';
				$resql2 = $this->db->query($sql2);
				if (!$resql2) {
					$this->output .= ($customer ? 'Customer invoices' : 'Vendor bills') . ': shipment-window query error - ' . $this->db->lasterror . "\n";
				} else {
					$added = 0;
					while ($obj = $this->db->fetch_object($resql2)) {
						if (!isset($rows[(int) $obj->rowid])) {
							$rows[(int) $obj->rowid] = $obj;
							$added++;
						}
					}
					if ($added > 0) {
						$this->output .= ($customer ? 'Customer invoices' : 'Vendor bills') . ': ' . $added . ' document(s) added to the batch: their shipment changed within the last ' . $hours . ' h.' . "\n";
					}
				}
			}
		}

		$invoice = $customer ? new Facture($this->db) : new FactureFournisseur($this->db);
		$count = 0;

		// When the accounting date comes from shipment delivery dates, it can change
		// without touching the invoice (ShipsGo updates the expedition extrafields,
		// not the invoice), so the invoice tms test alone is not enough: preload the
		// dates currently stored in the Odoo drafts and resync the moves whose
		// computed date no longer matches. The states are also used to skip updates
		// of moves already posted in Odoo.
		$checkOdooDate = ($this->getAccountingDateSource() === 'delivery_reception');
		$odooInfo = $this->getOdooMoveInfo($elementType, array_keys($rows));
		if ($odooInfo === null) {
			return;
		}

		foreach ($rows as $obj) {
			$mapped = $this->getMapping($elementType, (int) $obj->rowid);
			$unchanged = ($mapped !== null && $obj->tms <= $mapped->last_sync);
			if ($unchanged && !$checkOdooDate) {
				continue; // unchanged since last sync
			}

			if ($invoice->fetch($obj->rowid) <= 0) {
				continue;
			}

			if ($customer) {
				$dateInv = $this->getAccountingDateForCustomerInvoice($invoice);
			} else {
				$dateInv = $this->getAccountingDateForSupplierInvoice($invoice);
			}
			// Invoice date: always the Dolibarr invoice date, whatever the accounting date source
			$tsInvoice = is_numeric($invoice->date) ? $invoice->date : ($invoice->date ? strtotime($invoice->date) : 0);
			$dateInvoice = $tsInvoice > 0 ? date('Y-m-d', $tsInvoice) : $dateInv;
			// Shipment dates (ATA/ETA) for the due and delivery dates in Odoo.
			// Vendor deposits/credit notes carry none: they are money documents
			// with no shipment of their own (an expedition found through the PO
			// belongs to the goods bill).
			$shipDates = null;
			$isVendorMoneyDoc = !$customer && ($invoice->type == FactureFournisseur::TYPE_DEPOSIT || $invoice->type == FactureFournisseur::TYPE_CREDIT_NOTE);
			if ($this->getAccountingDateSource() === 'delivery_reception' && !$isVendorMoneyDoc) {
				$shipDates = $this->getShipmentDates($invoice, $customer);
			}
			if ($unchanged && isset($odooInfo[(int) $mapped->odoo_id]) && $odooInfo[(int) $mapped->odoo_id]['date'] === $dateInv && $odooInfo[(int) $mapped->odoo_id]['invoice_date'] === $dateInvoice) {
				continue; // invoice unchanged and the Odoo draft already has the same accounting and invoice dates
			}
			$amounts = $this->getAmountsInInvoiceCurrency($invoice);

			// The Odoo move was deleted manually after the first push (odooInfo only
			// contains live records): forget the stale mapping and push a fresh draft
			if ($mapped !== null && !isset($odooInfo[(int) $mapped->odoo_id])) {
				$this->deleteMapping($elementType, (int) $invoice->id);
				$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': Odoo move ' . (int) $mapped->odoo_id . ' was deleted in Odoo, pushing a new draft.' . "\n";
				$mapped = null;
			}

			// Reconciled/posted moves are never overwritten: warn in the Odoo chatter
			// (once, until handled) and keep the document in the Dolibarr failure log.
			if ($mapped !== null && isset($odooInfo[(int) $mapped->odoo_id]) && $odooInfo[(int) $mapped->odoo_id]['state'] === 'posted') {
				$dateUnchanged = ($odooInfo[(int) $mapped->odoo_id]['date'] === $dateInv);
				if ($dateUnchanged && $this->hasPaymentSince($elementType, (int) $invoice->id, $mapped->last_sync)) {
					// Only paid in Dolibarr, pushed data unchanged: nothing to overwrite,
					// flag the Odoo entry To Review so the payment gets registered/checked
					// there, then refresh last_sync so this does not repeat on every run.
					$this->odoo->write('account.move', array((int) $mapped->odoo_id), array('review_state' => 'todo'));
					$this->saveMapping($elementType, (int) $invoice->id, 'account.move', (int) $mapped->odoo_id);
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': paid in Dolibarr, Odoo entry flagged To Review.' . "\n";
				} else {
					$summary = 'new date ' . $dateInv . ', untaxed ' . $amounts['ht'] . ' ' . $amounts['code'] . ', tax ' . $amounts['tva'] . '.';
					// Auto reset: un-post the move so the Dolibarr change can be pushed.
					// The entry stays in draft, flagged To Review; re-posting remains a
					// manual review step. On Odoo refusal (locked period, reconciled
					// lines), fall back to the manual workflow below.
					$reset = $this->odoo->executeKw('account.move', 'button_draft', array(array((int) $mapped->odoo_id)));
					if ($reset === false) {
						$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': Odoo move ' . (int) $mapped->odoo_id . ' is posted and could not be reset to draft (' . $this->odoo->error . '), manual review required.' . "\n";
						$this->notifyPostedMoveChanged($elementType, (int) $invoice->id, $invoice->ref, (int) $mapped->odoo_id, $summary);
						continue;
					}
					$this->odoo->write('account.move', array((int) $mapped->odoo_id), array('review_state' => 'todo'));
					$this->postChatterNote((int) $mapped->odoo_id, 'Auto reset to draft: Dolibarr document ' . $invoice->ref . ' changed after posting (' . $summary . ') The updated values are being pushed, review them then re-post.');
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': posted Odoo move auto reset to draft, pushing the update.' . "\n";
					// no continue: fall through to the normal update path, which saves
					// the mapping (last_sync) only once the update has succeeded
				}
				continue;
			}

			// Customer invoices are often paid by the company of the BILLING contact,
			// different from the contract customer. The Odoo partner stays the
			// contract customer (AR aging, partner ledger and dunning follow the
			// legal debtor, and one billing company can serve several customers);
			// the billing company is shown in the invoice Terms and chatter.
			$billingPartner = $customer ? $this->getInvoiceBillingPartner($invoice) : null;
			$partnerId = $this->getOrCreatePartner($invoice->socid, $customer);
			if ($partnerId === null) {
				$msg = 'Partner ' . $invoice->socid . ' not found/created in Odoo.';
				$this->output .= ($customer ? 'Invoice ' . $invoice->ref : 'Vendor bill ' . $invoice->ref) . ': ' . $msg . "\n";
				$this->logFailure($elementType, (int) $invoice->id, $invoice->ref, 0, 'partner', $msg);
				continue;
			}

			$currencyId = $this->getOrCreateCurrencyId($amounts['code']);
			if ($currencyId === null) {
				$msg = 'Currency ' . $amounts['code'] . ' not found/created in Odoo.';
				$this->output .= ($customer ? 'Invoice ' . $invoice->ref : 'Vendor bill ' . $invoice->ref) . ': ' . $msg . "\n";
				$this->logFailure($elementType, (int) $invoice->id, $invoice->ref, 0, 'currency', $msg);
				continue;
			}

			if ($customer) {
				$ref = $invoice->ref;
				$isCreditNote = ($invoice->type == Facture::TYPE_CREDIT_NOTE);
				// Deposit invoices are customer prepayments (liability), not revenue.
				// Standard invoices that deduct deposits through lines/discount carry the net revenue.
				$lineAccount = ($invoice->type == Facture::TYPE_DEPOSIT) ? (int) getDolGlobalInt('ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID') : (int) getDolGlobalInt('ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID');
			} else {
				$ref = $invoice->ref_supplier ?: $invoice->ref;
				$isCreditNote = ($invoice->type == FactureFournisseur::TYPE_CREDIT_NOTE);
				// Supplier deposit invoices are prepayments made to vendors (asset),
				// not expenses; vendor bills that deduct deposits carry the net expense.
				$lineAccount = ($invoice->type == FactureFournisseur::TYPE_DEPOSIT) ? (int) getDolGlobalInt('ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID') : (int) getDolGlobalInt('ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID');
			}
			// The pure invoice number (before the order refs are appended):
			// used to search Odoo for a move already pushed for this invoice
			$invoiceRef = $ref;
			// Append the linked order reference(s) to the Odoo Reference column
			// (SO for customer invoices, PO/SO for vendor bills), for reconciliation
			$orderRefs = $this->getLinkedOrderRefs($invoice, $customer);
			if (!empty($orderRefs)) {
				$ref .= ' / ' . implode(' / ', $orderRefs);
			}
			$moveType = $customer ? ($isCreditNote ? 'out_refund' : 'out_invoice') : ($isCreditNote ? 'in_refund' : 'in_invoice');
			$journalId = $this->getJournalId($customer ? 'sale' : 'purchase');
			if ($journalId === null) {
				$msg = 'No ' . ($customer ? 'sale' : 'purchase') . ' journal in Odoo.';
				$this->output .= ($customer ? 'Invoice ' . $invoice->ref : 'Vendor bill ' . $invoice->ref) . ': ' . $msg . "\n";
				$this->logFailure($elementType, (int) $invoice->id, $ref, 0, 'journal', $msg);
				continue;
			}

			$label = $ref . ' - ' . dol_trunc($invoice->note_public, 100);
			// Deduction discounts consumed by this invoice (converted to discount in
			// Dolibarr). The move is pushed NET: the Dolibarr totals already carry
			// every consumed deduction (consumed discounts are booked as invoice
			// lines), and the negative deposit lines below bring the Odoo total down
			// to the amount still payable (Dolibarr remaining unpaid).
			// - Deposit deductions keep their representation: each consumed deposit
			//   gets its own negative line on the deposit account (the deposit
			//   invoice itself was pushed with a matching positive line, so the
			//   deposit account nets to zero). The main line stays at the Dolibarr
			//   net total = the revenue actually billed — no gross-up.
			// - Credit-note deductions add NO line: the consumed credit note is
			//   skipped by the sync entirely (its effect is already inside the net
			//   totals) — pushing it too would deduct the amount twice in Odoo.
			$downPayments = array();
			$depositConst = $customer ? Facture::TYPE_DEPOSIT : FactureFournisseur::TYPE_DEPOSIT;
			$cnConst = $customer ? Facture::TYPE_CREDIT_NOTE : FactureFournisseur::TYPE_CREDIT_NOTE;
			$dpAccount = $customer ? (int) getDolGlobalInt('ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID') : (int) getDolGlobalInt('ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID');
			$cnRefs = array();
			$skippedDisc = 0;  // discounts that cannot be represented in the pushed move
			foreach ($this->getInvoiceDiscounts($invoice, $customer, !empty($amounts['mc'])) as $disc) {
				if ($disc['source_type'] == $cnConst) {
					$cnRefs[] = $disc['source_ref'];
				} elseif ($disc['source_type'] == $depositConst && $dpAccount > 0) {
					// ref = number of the source deposit invoice (facnumber / supplier ref)
					$downPayments[] = array('kind' => 'down payment', 'ref' => $disc['source_ref'], 'ht' => $disc['ht'], 'account' => $dpAccount);
				} else {
					$skippedDisc++;
				}
			}
			// Make deduction gaps loud instead of silent: these outputs identify WHY
			// a move ends up without deduction lines (config gap vs data shape).
			if (!empty($downPayments)) {
				$parts = array();
				foreach ($downPayments as $dp) {
					$num = trim((string) $dp['ref']);
					$parts[] = $dp['kind'] . ' ' . ($num !== '' ? $num : '?') . ': ' . $dp['ht'];
				}
				$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': pushing ' . count($downPayments) . ' deposit deduction line(s) [' . implode('; ', $parts) . ']'
					. ($skippedDisc > 0 ? ', ' . $skippedDisc . ' more skipped' : '') . '.' . "\n";
			}
			if (!empty($cnRefs)) {
				$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': ' . count($cnRefs) . ' credit-note deduction(s) [CN ' . implode('; ', $cnRefs) . '] stay inside the pushed net total; the consumed credit note itself is not pushed to Odoo.' . "\n";
			}
			if (empty($downPayments) && empty($cnRefs)) {
				if ($skippedDisc > 0) {
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': WARNING - ' . $skippedDisc . ' discount(s) found but no Odoo deduction line can be built'
						. ($dpAccount <= 0 ? ' (deposit account NOT configured in the module setup)' : '')
						. '; the move is pushed net without deduction lines.' . "\n";
				} else {
					// No matched discount at all: check whether the invoice itself carries negative lines
					// that should have been recognized (data-shape mismatch probe).
					$negLines = 0;
					foreach (($invoice->lines ?? array()) as $ln) {
						if ((double) $ln->subprice < 0 || ((int) ($ln->info_bits ?? 0) & 2)) {
							$negLines++;
						}
					}
					if ($negLines > 0) {
						$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $invoice->ref . ': WARNING - invoice has ' . $negLines . ' negative/discount line(s) but no matching societe_remise_except row; nothing will be deducted in Odoo.' . "\n";
					}
				}
			}
			$lineData = $this->buildMoveLines($ref, $label, $amounts, $lineAccount, $downPayments);

			$moveData = array(
				'partner_id' => $partnerId,
				'company_id' => $this->getOdooCompanyId(),
				'journal_id' => $journalId,
				'move_type' => $moveType,
				'currency_id' => $currencyId,
				'date' => $dateInv,
				'invoice_date' => $dateInvoice,
				'ref' => $ref,
			);
			if ($shipDates !== null) {
				// Due date and delivery date follow the linked shipment:
				// due = ATA then ETA, delivery = ETA
				if ($shipDates['ata'] !== null || $shipDates['eta'] !== null) {
					$moveData['invoice_date_due'] = $shipDates['ata'] !== null ? $shipDates['ata'] : $shipDates['eta'];
				}
				// delivery_date: not on every Odoo version/accounting setup - write only when the model has it
				if ($shipDates['eta'] !== null && $this->odooFieldExists('account.move', 'delivery_date')) {
					$moveData['delivery_date'] = $shipDates['eta'];
				}
			}
			if ($billingPartner !== null) {
				// Invoice Terms (printed on the PDF): who actually pays the invoice
				$moveData['narration'] = 'Paid by: ' . $billingPartner[1];
			}
			if ($customer) {
				// Delivery address: the SHIPPING contact on the invoice becomes an
				// Odoo delivery contact of the customer (account.move partner_shipping_id,
				// written only when the model has the field)
				$shippingPartnerId = $this->getOrCreateDeliveryAddress($invoice, $partnerId);
				if ($shippingPartnerId !== null && $this->odooFieldExists('account.move', 'partner_shipping_id')) {
					$moveData['partner_shipping_id'] = $shippingPartnerId;
				}
			}
			// Number: the Dolibarr invoice number (kept by Odoo when posting, only
			// empty "/" numbers get a sequence). facnumber is unique, no clash risk.
			$moveData['name'] = $invoice->ref;
			// payment_reference is the visible "Reference" on the vendor bill form
			// ("Use Bill Reference") and the payment communication on customer
			// invoices ("Standard communication"): in our practice the PO/SO
			// numbers (SO for customer invoices, PO/SO for vendor bills), not the
			// invoice number which lives in the Number field. Empty when the
			// invoice has no linked order.
			$moveData['payment_reference'] = !empty($orderRefs) ? implode(' / ', $orderRefs) : '';

			if ($mapped === null) {
				// Duplicate guard: with no mapping row, a move may already exist in Odoo
				// (e.g. the first sync crashed after the Odoo create, before the mapping
				// was saved). Check the invoice number and adopt the existing move
				// instead of pushing a second copy.
				$dupIds = $this->findOdooMovesByRef($invoiceRef, $moveType, $partnerId, $invoice->ref);
				if ($dupIds === false) {
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $ref . ': duplicate check failed (API error), skipping create.' . "\n";
					$this->logFailure($elementType, (int) $invoice->id, $ref, 0, 'duplicate', 'Duplicate check API error: ' . $this->odoo->error);
					continue;
				}
				if (!empty($dupIds)) {
					$mapped = (object) array('odoo_id' => (int) $dupIds[0], 'last_sync' => '');
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $ref . ': already in Odoo (move ' . (int) $dupIds[0] . '), mapping restored.' . "\n";
					if (count($dupIds) > 1) {
						$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $ref . ': ' . count($dupIds) . ' Odoo moves exist for this invoice number, remove the extra ones in Odoo.' . "\n";
						$this->logFailure($elementType, (int) $invoice->id, $ref, (int) $dupIds[0], 'duplicate', count($dupIds) . ' Odoo moves exist for this invoice number, remove the extra ones in Odoo');
					}
					if (isset($odooInfo[(int) $dupIds[0]]) && $odooInfo[(int) $dupIds[0]]['state'] === 'posted') {
						// Already posted and reconciled in Odoo: restore the mapping only
						$this->saveMapping($elementType, (int) $invoice->id, 'account.move', (int) $dupIds[0]);
						continue;
					}
					// fall through to the update branch to refresh the adopted draft
				}
			}

			if ($mapped === null) {
				// Create in Odoo as draft
				$moveData['invoice_line_ids'] = $lineData;
				$newId = $this->odoo->create('account.move', $moveData);
				if ($newId) {
					$this->saveMapping($elementType, (int) $invoice->id, 'account.move', (int) $newId);
					if ($billingPartner !== null) {
						// Keep the contract customer visible although the entry is billed to the billing company
						$this->postChatterNote((int) $newId, 'Billed/paid by the billing contact company: ' . $billingPartner[1]
							. '. Contract customer in Dolibarr: third party #' . (int) $invoice->socid . '.');
					}
					$count++;
					$this->result++;
				} else {
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $ref . ': Odoo create failed - ' . $this->odoo->error . "\n";
					$this->logFailure($elementType, (int) $invoice->id, $ref, 0, 'create', 'Odoo create failed - ' . $this->odoo->error);
				}
			} else {
				// Update existing Odoo draft move (replace all invoice lines).
				// Dolibarr changed since the previous push: flag the entry To Review in Odoo.
				$writeData = array_merge($moveData, array('review_state' => 'todo', 'invoice_line_ids' => array_merge(array(array(5, 0, 0)), $lineData)));
				if ($this->odoo->write('account.move', array((int) $mapped->odoo_id), $writeData)) {
					$this->saveMapping($elementType, (int) $invoice->id, 'account.move', (int) $mapped->odoo_id);
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $ref . ': draft updated in Odoo (move ' . (int) $mapped->odoo_id . ', ' . count($lineData) . ' lines).' . "\n";
					$count++;
					$this->result++;
				} else {
					$this->output .= ($customer ? 'Invoice ' : 'Vendor bill ') . $ref . ': Odoo update failed - ' . $this->odoo->error . "\n";
					$this->logFailure($elementType, (int) $invoice->id, $ref, (int) $mapped->odoo_id, 'update', 'Odoo update failed - ' . $this->odoo->error);
				}
			}
		}

		if ($count > 0) {
			$this->output .= ($customer ? 'Customer invoices' : 'Vendor bills') . ': ' . $count . ' synced.' . "\n";
		}
	}

	/**
	 * Build the summary invoice lines pushed to Odoo: the first line carries the
	 * Dolibarr NET total (the amount actually billed, no gross-up) on the
	 * account matching the document type (revenue, deposit liability,
	 * expense...), one negative line per consumed DEPOSIT deduction follows on
	 * the deposit account (its positive side lives on the pushed deposit
	 * invoice, so the account nets to zero and the move total equals the amount
	 * still payable). Credit-note deductions never produce a line: the
	 * consuming move is pushed at the Dolibarr net total (the deduction already
	 * lives inside those totals) and the consumed credit note is skipped by the
	 * sync entirely. An explicit tax line is added when a tax account is
	 * configured (the tax is net: the deposit invoice carries its own tax line,
	 * and the credit note is not pushed at all).
	 *
	 * @param string $ref          Reference used for line labels
	 * @param string $label        Label of the untaxed line
	 * @param array  $amounts      code/ht/tva/ttc in the original currency (net Dolibarr totals, as computed by the caller)
	 * @param int    $accountId    Explicit Odoo account for the untaxed line, 0 = journal default
	 * @param array  $downPayments Consumed deposit deductions: array of array('kind' => label text, 'ref' => source ref, 'ht' => amount, 'account' => odoo id or null)
	 * @return array invoice_line_ids commands for account.move
	 */
	protected function buildMoveLines($ref, $label, $amounts, $accountId = 0, $downPayments = array())
	{
		// First line = the Dolibarr NET invoice total = the amount actually
		// billed (consumed credit-note/deposit deductions already live inside
		// those totals). The negative deposit lines below bring the Odoo total
		// due down to the amount still payable.
		$untaxed = (double) $amounts['ht'];
		$untaxedLine = array(
			'name' => $label,
			'quantity' => 1,
			'price_unit' => $untaxed,
		);
		if ($accountId > 0) {
			$untaxedLine['account_id'] = $accountId;
		}
		$lineData = array(array(0, 0, $untaxedLine));
		foreach ($downPayments as $dp) {
			// kind + number of the source deposit/credit note invoice, for reconciliation
			$srcNum = trim((string) $dp['ref']);
			$dedLine = array(
				'name' => $ref . ' (' . (isset($dp['kind']) ? $dp['kind'] : 'down payment')
					. ($srcNum !== '' ? ' ' . $srcNum : ' - no source number') . ')',
				'quantity' => 1,
				'price_unit' => -((double) $dp['ht']),
			);
			if (!empty($dp['account'])) {
				$dedLine['account_id'] = (int) $dp['account'];
			}
			$lineData[] = array(0, 0, $dedLine);
		}
		$taxAccountId = (int) getDolGlobalInt('ODOO_CONNECTOR_TAX_ACCOUNT_ID');
		if ($taxAccountId > 0 && $amounts['tva'] != 0) {
			$lineData[] = array(0, 0, array(
				'name' => $ref . ' (tax)',
				'quantity' => 1,
				'price_unit' => $amounts['tva'],
				'account_id' => $taxAccountId,
			));
		}
		return $lineData;
	}

	/**
	 * Find Odoo moves already pushed for an invoice number (duplicate guard).
	 * Matches the exact invoice number or the combined reference starting with
	 * it ("invoice / SO-PO") or the Number field, within the same company and
	 * move type. Deliberately NOT filtered by partner: a move created manually
	 * in Odoo often sits on a different partner record (same name, historical
	 * duplicate...) and filtering by partner would miss it and push a duplicate;
	 * the Dolibarr number is globally unique, so the same ref means the same
	 * document. Partner mismatches are reported in the output instead.
	 *
	 * @param string $invoiceRef Pure invoice number (Dolibarr facnumber / supplier ref)
	 * @param string $moveType   out_invoice, in_invoice, out_refund, in_refund
	 * @param int    $partnerId  Odoo res.partner id of the Dolibarr-mapped partner (mismatch warnings only, not a filter)
	 * @param string $nameRef    Value pushed into the Number (name) field
	 * @return array|false Odoo move ids (oldest first), false on Odoo API failure
	 */
	protected function findOdooMovesByRef($invoiceRef, $moveType, $partnerId, $nameRef = '')
	{
		// Ref/name conditions, OR-ed (prefix-OR Odoo domain). Built conditionally
		// so an empty operand can never match every move with an empty field.
		// payment_reference matters for moves entered manually in Odoo: the visible
		// "Reference" field of the vendor bill form is payment_reference, not ref,
		// so that is where the bookkeeper types the invoice number.
		$or = array();
		if ($invoiceRef !== '') {
			$or[] = array('ref', '=', $invoiceRef);
			$or[] = array('ref', '=like', $invoiceRef . ' / %');
			$or[] = array('payment_reference', '=', $invoiceRef);
			$or[] = array('payment_reference', '=like', $invoiceRef . ' / %');
		}
		if ($nameRef !== '') {
			$or[] = array('name', '=', $nameRef);
		}
		if (empty($or)) {
			return array();
		}
		$domain = array(
			array('company_id', '=', $this->getOdooCompanyId()),
			array('move_type', '=', $moveType),
		);
		for ($i = 0; $i < count($or) - 1; $i++) {
			$domain[] = '|';
		}
		$domain = array_merge($domain, $or);

		$found = $this->odoo->searchRead('account.move', $domain, array('id', 'partner_id'), 2, 0, 'id asc');
		if ($found === false) {
			return false; // API failure: caller must skip create
		}
		$ids = array();
		foreach ($found as $row) {
			if (!isset($row['id'])) {
				continue;
			}
			$ids[] = (int) $row['id'];
			// Match on a different partner record than the Dolibarr-mapped one:
			// typical for manually created moves. Drafts get the mapped partner
			// restored by the update branch; posted moves are adopted as-is.
			$foundPartner = is_array(isset($row['partner_id']) ? $row['partner_id'] : null) ? (int) $row['partner_id'][0] : 0;
			if ($partnerId > 0 && $foundPartner > 0 && $foundPartner !== $partnerId) {
				$foundPartnerName = isset($row['partner_id'][1]) ? (string) $row['partner_id'][1] : ('#' . $foundPartner);
				$this->output .= 'Duplicate guard: Odoo move ' . (int) $row['id'] . ' for ' . $invoiceRef . ' belongs to partner ' . $foundPartnerName . ', not the mapped partner #' . $partnerId . '.' . "\n";
			}
		}
		if (empty($ids)) {
			// Trace every miss: with posted invoices getting duplicate drafts, this
			// line shows exactly which values were searched before each create.
			$this->output .= 'Duplicate guard MISS: no Odoo move matches ref/payment_reference \'' . $invoiceRef . '\''
				. ($nameRef !== '' ? ' or name \'' . $nameRef . '\'' : '')
				. ' (company #' . $this->getOdooCompanyId() . ', move_type ' . $moveType . ').' . "\n";
		}
		return $ids;
	}

	/**
	 * Preload the accounting dates and states of the Odoo moves already mapped
	 * for an element type. Used to detect delivery-date changes made on the
	 * shipment (ShipsGo) without any change on the Dolibarr invoice, and to
	 * skip updates of moves that were already posted in Odoo.
	 *
	 * @param string $elementType   facture, facture_fourn, expensereport
	 * @param array  $fkSourceIds   Optional Dolibarr rowids: restrict the mapping
	 *                              lookup to the current batch instead of loading
	 *                              every odoo_id ever mapped (grows without bound)
	 * @return array|null odoo_id => array('date' => Y-m-d, 'state' => draft|posted); null on Odoo API failure
	 */
	protected function getOdooMoveInfo($elementType, $fkSourceIds = array())
	{
		$info = array();
		$sql = "SELECT odoo_id FROM ".$this->db->prefix()."odoo_connector_sync WHERE element_type = '".$this->db->escape($elementType)."'";
		if (!empty($fkSourceIds)) {
			$sql .= ' AND fk_source_id IN (' . implode(',', array_map('intval', $fkSourceIds)) . ')';
		}
		$resql = $this->db->query($sql);
		$ids = array();
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$ids[] = (int) $obj->odoo_id;
			}
		}
		if (empty($ids)) {
			return $info;
		}
		$moves = $this->odoo->searchRead('account.move', array(array('id', 'in', $ids)), array('id', 'date', 'invoice_date', 'state'), 0);
		if ($moves === false) {
			$this->output .= 'Odoo Connector: failed to preload move info (API error), aborting this batch to avoid stale mappings.' . "\n";
			return null;
		}
		if (is_array($moves)) {
			foreach ($moves as $m) {
				$info[(int) $m['id']] = array(
					'date' => isset($m['date']) ? $m['date'] : '',
					'invoice_date' => isset($m['invoice_date']) ? $m['invoice_date'] : '',
					'state' => isset($m['state']) ? $m['state'] : '',
				);
			}
		}
		return $info;
	}

	/**
	 * Remove the Odoo moves of invoices that are no longer valid in Dolibarr:
	 * deleted, reopened to draft or abandoned (e.g. refunded then cancelled
	 * without a credit note). Draft moves are deleted together with the mapping;
	 * posted moves cannot be deleted, a warning asks for manual handling.
	 *
	 * @param string $elementType facture, facture_fourn
	 * @param string $table       llx facture / facture_fourn table name
	 * @param string $refColumn   Reference column of the source table (facnumber / ref), for the failure log
	 * @return void
	 */
	protected function cleanupRevokedMoves($elementType, $table, $refColumn = 'ref')
	{
		$prefix = $this->db->prefix();
		$sql = "SELECT m.fk_source_id, m.odoo_id, f." . $this->db->escape($refColumn) . " AS sourceref";
		$sql .= " FROM ".$prefix."odoo_connector_sync m";
		$sql .= " LEFT JOIN ".$prefix.$table." f ON f.rowid = m.fk_source_id";
		$sql .= " WHERE m.element_type = '".$this->db->escape($elementType)."' AND m.entity = ".((int) $this->entity);
		$sql .= " AND (f.rowid IS NULL OR f.fk_statut IN (0, 3))";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return;
		}
		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[] = $obj;
		}
		// Moves already deleted manually in Odoo must not spam the failure log on
		// every run (unlink of a missing id is an Odoo error): preload which of
		// them are still live, the missing ones are simply unmapped.
		$liveIds = array();
		$odooIds = array();
		foreach ($rows as $obj) {
			$odooIds[(int) $obj->odoo_id] = true;
		}
		if (!empty($odooIds)) {
			$found = $this->odoo->searchRead('account.move', array(array('id', 'in', array_keys($odooIds))), array('id'), 0);
			if ($found === false) {
				$this->output .= ucfirst($elementType) . ': revoked-move existence check failed (' . $this->odoo->error . '), nothing cleaned this run.' . "\n";
				return;
			}
			foreach ($found as $f) {
				$liveIds[(int) $f['id']] = true;
			}
		}
		foreach ($rows as $obj) {
			if (!isset($liveIds[(int) $obj->odoo_id])) {
				$this->deleteMapping($elementType, (int) $obj->fk_source_id);
				$this->output .= ucfirst($elementType) . ' ' . (int) $obj->fk_source_id . ': revoked in Dolibarr, Odoo move already gone, mapping dropped.' . "\n";
				continue;
			}
			if ($this->odoo->unlink('account.move', array((int) $obj->odoo_id))) {
				$this->deleteMapping($elementType, (int) $obj->fk_source_id);
				$this->output .= ucfirst($elementType) . ' ' . (int) $obj->fk_source_id . ': revoked in Dolibarr, Odoo draft deleted.' . "\n";
			} else {
				// Already posted (or shared): cannot be deleted automatically
				$msg = 'Revoked in Dolibarr but Odoo move ' . (int) $obj->odoo_id . ' could not be deleted (' . $this->odoo->error . ') - reverse it manually in Odoo.';
				$this->output .= ucfirst($elementType) . ' ' . (int) $obj->fk_source_id . ': ' . $msg . "\n";
				$this->logFailure($elementType, (int) $obj->fk_source_id, $obj->sourceref, (int) $obj->odoo_id, 'delete', $msg);
			}
		}
	}

	// ---------------------------------------------------------------
	// Sync: expense reports
	// ---------------------------------------------------------------

	/**
	 * Sync expense reports (ExpenseReport) to Odoo account.move (in_invoice, draft),
	 * same two-line layout as vendor bills (untaxed + optional tax line), original currency.
	 * Expense reports have no third party in Dolibarr: a dedicated partner is used.
	 *
	 * @param string $dateFrom  Optional date filter (Y-m-d), '' = no limit
	 * @param string $dateTo    Optional date filter (Y-m-d, inclusive), '' = no limit
	 * @param string $dateField Date field used by the filter: invoice_date, due_date, creation, last_update
	 */
	protected function syncExpenses($dateFrom = '', $dateTo = '', $dateField = 'invoice_date')
	{
		if (!isModEnabled('expensereport')) {
			return;
		}

		require_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';

		$sql = 'SELECT e.rowid, e.tms FROM ' . MAIN_DB_PREFIX . 'expensereport e';
		$sql .= ' WHERE e.entity IN (' . getEntity('expensereport') . ')';
		$sql .= " AND e.fk_statut >= 1"; // at least submitted
		// Expense reports have no due date: due_date falls back to the period start
		$dateColumn = 'e.date_debut';
		if ($dateField === 'creation') {
			$dateColumn = 'e.date_create';
		} elseif ($dateField === 'last_update') {
			$dateColumn = 'e.tms';
		}
		if ($dateFrom !== '') {
			$sql .= " AND " . $dateColumn . " >= '" . $this->db->escape($dateFrom) . "'";
		}
		if ($dateTo !== '') {
			$sql .= " AND " . $dateColumn . " < '" . $this->db->escape(date('Y-m-d', strtotime($dateTo . ' +1 day'))) . "'";
		}
		$sql .= ' ORDER BY e.tms DESC';
		$sql .= ' LIMIT 200';

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->output .= 'Expenses: query error.' . "\n";
			return;
		}
		if ($this->db->num_rows($resql) >= 200) {
			$this->output .= 'Expenses: batch limit of 200 reached, narrow the date range and run again.' . "\n";
		}

		$exp = new ExpenseReport($this->db);
		$count = 0;
		// Collect the batch first so the Odoo move preload can be narrowed to it
		$rows = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$rows[(int) $obj->rowid] = $obj;
		}
		// States of the mapped Odoo moves, to skip updates of posted entries
		$odooInfo = $this->getOdooMoveInfo('expensereport', array_keys($rows));
		foreach ($rows as $obj) {
			$mapped = $this->getMapping('expensereport', (int) $obj->rowid);
			if ($mapped !== null && $obj->tms <= $mapped->last_sync) {
				continue; // unchanged since last sync
			}

			if ($exp->fetch($obj->rowid) <= 0) {
				continue;
			}

			// ExpenseReport::fetch() does not load multicurrency fields, read them directly
			$sqlmc = "SELECT multicurrency_code, multicurrency_total_ht, multicurrency_total_tva, multicurrency_total_ttc";
			$sqlmc .= " FROM ".MAIN_DB_PREFIX."expensereport WHERE rowid = ".(int) $exp->id;
			$resqlmc = $this->db->query($sqlmc);
			if ($resqlmc && ($objmc = $this->db->fetch_object($resqlmc))) {
				$exp->multicurrency_code = $objmc->multicurrency_code;
				$exp->multicurrency_total_ht = $objmc->multicurrency_total_ht;
				$exp->multicurrency_total_tva = $objmc->multicurrency_total_tva;
				$exp->multicurrency_total_ttc = $objmc->multicurrency_total_ttc;
			}

			$partnerId = $this->getOrCreateExpensePartner();
			if ($partnerId === null) {
				$msg = 'Expense partner not found/created in Odoo.';
				$this->output .= 'Expense ' . $exp->ref . ': ' . $msg . "\n";
				$this->logFailure('expensereport', (int) $exp->id, $exp->ref, 0, 'partner', $msg);
				continue;
			}

			$amounts = $this->getAmountsInInvoiceCurrency($exp);

			// The Odoo move was deleted manually after the first push: forget the
			// stale mapping and push a fresh draft
			if ($mapped !== null && !isset($odooInfo[(int) $mapped->odoo_id])) {
				$this->deleteMapping('expensereport', (int) $exp->id);
				$this->output .= 'Expense ' . $exp->ref . ': Odoo move ' . (int) $mapped->odoo_id . ' was deleted in Odoo, pushing a new draft.' . "\n";
				$mapped = null;
			}

			// Posted moves are never overwritten: warn once in the Odoo chatter and log
			if ($mapped !== null && isset($odooInfo[(int) $mapped->odoo_id]) && $odooInfo[(int) $mapped->odoo_id]['state'] === 'posted') {
				$dateUnchanged = ($odooInfo[(int) $mapped->odoo_id]['date'] === $this->getAccountingDateForExpenseReport($exp));
				if ($dateUnchanged && $this->hasPaymentSince('expensereport', (int) $exp->id, $mapped->last_sync)) {
					// Only paid in Dolibarr: flag To Review once and refresh last_sync
					$this->odoo->write('account.move', array((int) $mapped->odoo_id), array('review_state' => 'todo'));
					$this->saveMapping('expensereport', (int) $exp->id, 'account.move', (int) $mapped->odoo_id);
					$this->output .= 'Expense ' . $exp->ref . ': paid in Dolibarr, Odoo entry flagged To Review.' . "\n";
				} else {
					$summary = 'new date ' . $this->getAccountingDateForExpenseReport($exp) . ', untaxed ' . $amounts['ht'] . ' ' . $amounts['code'] . ', tax ' . $amounts['tva'] . '.';
					$this->notifyPostedMoveChanged('expensereport', (int) $exp->id, $exp->ref, (int) $mapped->odoo_id, $summary);
				}
				continue;
			}

			$currencyId = $this->getOrCreateCurrencyId($amounts['code']);
			if ($currencyId === null) {
				$msg = 'Currency ' . $amounts['code'] . ' not found/created in Odoo.';
				$this->output .= 'Expense ' . $exp->ref . ': ' . $msg . "\n";
				$this->logFailure('expensereport', (int) $exp->id, $exp->ref, 0, 'currency', $msg);
				continue;
			}

			$journalId = $this->getJournalId('expense');
			if ($journalId === null) {
				$msg = 'No expense (general/purchase) journal in Odoo.';
				$this->output .= 'Expense ' . $exp->ref . ': ' . $msg . "\n";
				$this->logFailure('expensereport', (int) $exp->id, $exp->ref, 0, 'journal', $msg);
				continue;
			}

			$dateExp = $this->getAccountingDateForExpenseReport($exp);
			$label = $exp->ref . ' - ' . dol_trunc($exp->note_private, 100);
			$lineData = $this->buildMoveLines($exp->ref, $label, $amounts, (int) getDolGlobalInt('ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID'));

			$moveData = array(
				'partner_id' => $partnerId,
				'company_id' => $this->getOdooCompanyId(),
				'journal_id' => $journalId,
				'move_type' => 'in_invoice',
				'currency_id' => $currencyId,
				'date' => $dateExp,
				'invoice_date' => $dateExp,
				'ref' => $exp->ref,
			);

			if ($mapped === null) {
				// Create in Odoo as draft
				$moveData['invoice_line_ids'] = $lineData;
				$newId = $this->odoo->create('account.move', $moveData);
				if ($newId) {
					$this->saveMapping('expensereport', (int) $exp->id, 'account.move', (int) $newId);
					$count++;
					$this->result++;
				} else {
					$this->output .= 'Expense ' . $exp->ref . ': Odoo create failed - ' . $this->odoo->error . "\n";
					$this->logFailure('expensereport', (int) $exp->id, $exp->ref, 0, 'create', 'Odoo create failed - ' . $this->odoo->error);
				}
			} else {
				// Update existing Odoo draft move (replace all invoice lines).
				// Dolibarr changed since the previous push: flag the entry To Review in Odoo.
				$writeData = array_merge($moveData, array('review_state' => 'todo', 'invoice_line_ids' => array_merge(array(array(5, 0, 0)), $lineData)));
				if ($this->odoo->write('account.move', array((int) $mapped->odoo_id), $writeData)) {
					$this->saveMapping('expensereport', (int) $exp->id, 'account.move', (int) $mapped->odoo_id);
					$count++;
					$this->result++;
				} else {
					$this->output .= 'Expense ' . $exp->ref . ': Odoo update failed - ' . $this->odoo->error . "\n";
					$this->logFailure('expensereport', (int) $exp->id, $exp->ref, (int) $mapped->odoo_id, 'update', 'Odoo update failed - ' . $this->odoo->error);
				}
			}
		}

		if ($count > 0) {
			$this->output .= 'Expenses: ' . $count . ' synced.' . "\n";
		}
	}

	// ---------------------------------------------------------------
	// Accounting date resolution
	// ---------------------------------------------------------------

	/**
	 * Get configured accounting date source: invoice_date, due_date, or delivery_reception.
	 *
	 * @return string
	 */
	protected function getAccountingDateSource()
	{
		$src = getDolGlobalString('ODOO_CONNECTOR_ACCOUNTING_DATE_SOURCE');
		if (in_array($src, array('invoice_date', 'due_date', 'delivery_reception'), true)) {
			return $src;
		}
		return 'invoice_date';
	}

	/**
	 * Get configured delivery/reception date field (expedition_extrafields column name).
	 * Empty = use ATA then ETA. Values: ata, eta, atd, etd, updatedtime, or custom when "other".
	 *
	 * @return string
	 */
	protected function getDeliveryDateField()
	{
		$field = getDolGlobalString('ODOO_CONNECTOR_DELIVERY_DATE_FIELD');
		$field = $field ? trim($field) : '';
		if ($field === 'other') {
			$field = getDolGlobalString('ODOO_CONNECTOR_DELIVERY_DATE_FIELD_CUSTOM');
			$field = $field ? trim($field) : '';
		}
		if ($field !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
			return $field;
		}
		return '';
	}

	/**
	 * Get expedition (shipment) id linked to a customer invoice (origin or element_element).
	 *
	 * @param Facture $invoice
	 * @return int 0 or expedition rowid
	 */
	protected function getExpeditionIdForCustomerInvoice($invoice)
	{
		if (!empty($invoice->origin) && (strtolower($invoice->origin) === 'shipping' || strtolower($invoice->origin) === 'expedition') && !empty($invoice->origin_id)) {
			return (int) $invoice->origin_id;
		}
		$prefix = $this->db->prefix();
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE (fk_target = ".(int) $invoice->id." AND targettype IN ('facture','invoice')) AND sourcetype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if ($resql && $obj = $this->db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE (fk_source = ".(int) $invoice->id." AND sourcetype IN ('facture','invoice')) AND targettype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if ($resql && $obj = $this->db->fetch_object($resql)) {
			return (int) $obj->id;
		}
		return 0;
	}

	/**
	 * Get expedition id for a supplier invoice: prefer direct invoice↔shipment or PO↔shipment (dropshipping), else PO → SO → expedition.
	 *
	 * @param FactureFournisseur $invoice
	 * @return int 0 or expedition rowid
	 */
	protected function getExpeditionIdForSupplierInvoice($invoice)
	{
		$prefix = $this->db->prefix();
		// 1) Direct facture_fourn <-> expedition link (e.g. user linked invoice to shipment)
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $invoice->id." AND targettype IN ('invoice_supplier','facture_fourn') AND sourcetype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->id > 0) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $invoice->id." AND sourcetype IN ('invoice_supplier','facture_fourn') AND targettype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->id > 0) {
			return (int) $obj->id;
		}
		// 2) PO linked to this invoice
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $invoice->id." AND targettype IN ('invoice_supplier','facture_fourn') AND sourcetype IN ('order_supplier','commande_fournisseur')";
		$resql = $this->db->query($sql);
		if (!$resql || !($obj = $this->db->fetch_object($resql))) {
			$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $invoice->id." AND sourcetype IN ('invoice_supplier','facture_fourn') AND targettype IN ('order_supplier','commande_fournisseur')";
			$resql = $this->db->query($sql);
			if (!$resql || !($obj = $this->db->fetch_object($resql))) {
				return 0;
			}
		}
		$poid = (int) $obj->id;
		if ($poid <= 0) {
			return 0;
		}
		// 3) Dropshipping: PO directly linked to expedition (slycustom trigger on SHIPPING_CREATE)
		$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$poid." AND targettype IN ('order_supplier','commande_fournisseur') AND sourcetype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->id > 0) {
			return (int) $obj->id;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$poid." AND sourcetype IN ('order_supplier','commande_fournisseur') AND targettype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql)) && (int) $obj->id > 0) {
			return (int) $obj->id;
		}
		// 4) PO → SO → expedition
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$poid." AND sourcetype IN ('order_supplier','commande_fournisseur') AND targettype IN ('commande','order')";
		$resql = $this->db->query($sql);
		if (!$resql || !($obj = $this->db->fetch_object($resql))) {
			$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$poid." AND targettype IN ('order_supplier','commande_fournisseur') AND sourcetype IN ('commande','order')";
			$resql = $this->db->query($sql);
			if (!$resql || !($obj = $this->db->fetch_object($resql))) {
				return 0;
			}
		}
		$soid = (int) $obj->id;
		if ($soid <= 0) {
			return 0;
		}
		$sql = "SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$soid." AND sourcetype IN ('commande','order') AND targettype IN ('expedition','shipping')";
		$resql = $this->db->query($sql);
		if (!$resql || !($obj = $this->db->fetch_object($resql))) {
			$sql = "SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$soid." AND targettype IN ('commande','order') AND sourcetype IN ('expedition','shipping')";
			$resql = $this->db->query($sql);
			if (!$resql || !($obj = $this->db->fetch_object($resql))) {
				return 0;
			}
		}
		return (int) $obj->id;
	}

	/**
	 * Ids of the shipments whose data changed within the last N hours
	 * (expedition_extrafields.tms: ATA/ETA updates, e.g. ShipsGo).
	 *
	 * @param int $hours Lookback window in hours (>= 1, default 25)
	 * @return array|false Array of expedition rowids; false on query error (traced in the output)
	 */
	protected function getRecentlyUpdatedExpeditionIds($hours)
	{
		$hours = (int) $hours;
		if ($hours < 1) {
			$hours = 25;
		}
		$cutoff = date('Y-m-d H:i:s', dol_now() - $hours * 3600);
		$ids = array();
		$sql = "SELECT fk_object FROM " . $this->db->prefix() . "expedition_extrafields WHERE tms >= '" . $this->db->escape($cutoff) . "'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->output .= 'Odoo Connector: expedition lookback query error - ' . $this->db->lasterror . "\n";
			return false;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$ids[(int) $obj->fk_object] = true;
		}
		return array_keys($ids);
	}

	/**
	 * Run an id-collecting query and return the ids as a unique map (id => true).
	 *
	 * @param string $sql Query selecting the ids in a column aliased "id"
	 * @return array id => true
	 */
	protected function queryIds($sql)
	{
		$ids = array();
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				if ((int) $obj->id > 0) {
					$ids[(int) $obj->id] = true;
				}
			}
		}
		return $ids;
	}

	/**
	 * Reverse of getExpeditionIdForCustomerInvoice/getExpeditionIdForSupplierInvoice:
	 * the customer invoices or vendor bills linked to a shipment through every
	 * linkage path the forward lookups use (invoice origin, direct
	 * element_element links; for vendor bills also the dropshipping PO and the
	 * PO → SO chains). Over-matching is safe: the accounting date is always
	 * recomputed from the forward lookup, so an invoice that would not resolve
	 * to this shipment is simply recomputed (and skipped when already correct).
	 *
	 * @param int  $expId    Expedition rowid
	 * @param bool $customer true for Facture, false for FactureFournisseur
	 * @return array of Dolibarr rowids
	 */
	protected function getInvoiceIdsForExpedition($expId, $customer)
	{
		$prefix = $this->db->prefix();
		$expId = (int) $expId;
		if ($expId <= 0) {
			return array();
		}
		if ($customer) {
			// Invoices generated from the shipment (origin) + direct links
			$ids = $this->queryIds("SELECT rowid AS id FROM ".$prefix."facture WHERE origin IN ('shipping','expedition') AND origin_id = ".$expId);
			$ids += $this->queryIds("SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$expId." AND sourcetype IN ('expedition','shipping') AND targettype IN ('facture','invoice')");
			$ids += $this->queryIds("SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$expId." AND targettype IN ('expedition','shipping') AND sourcetype IN ('facture','invoice')");
			return array_keys($ids);
		}
		// Vendor bills: direct invoice ↔ shipment links...
		$ids = $this->queryIds("SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$expId." AND sourcetype IN ('expedition','shipping') AND targettype IN ('invoice_supplier','facture_fourn')");
		$ids += $this->queryIds("SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$expId." AND targettype IN ('expedition','shipping') AND sourcetype IN ('invoice_supplier','facture_fourn')");
		// ... plus bills of the POs directly linked to the shipment (dropshipping)...
		$poIds = $this->queryIds("SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$expId." AND sourcetype IN ('expedition','shipping') AND targettype IN ('order_supplier','commande_fournisseur')");
		$poIds += $this->queryIds("SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$expId." AND targettype IN ('expedition','shipping') AND sourcetype IN ('order_supplier','commande_fournisseur')");
		// ... plus POs reached through the customer order (PO → SO → shipment)
		$soIds = $this->queryIds("SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".$expId." AND sourcetype IN ('expedition','shipping') AND targettype IN ('commande','order')");
		$soIds += $this->queryIds("SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".$expId." AND targettype IN ('expedition','shipping') AND sourcetype IN ('commande','order')");
		foreach (array_keys($soIds) as $soid) {
			$poIds += $this->queryIds("SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $soid." AND targettype IN ('order_supplier','commande_fournisseur') AND sourcetype IN ('commande','order')");
			$poIds += $this->queryIds("SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $soid." AND sourcetype IN ('commande','order') AND targettype IN ('order_supplier','commande_fournisseur')");
		}
		foreach (array_keys($poIds) as $poid) {
			$ids += $this->queryIds("SELECT fk_source AS id FROM ".$prefix."element_element WHERE fk_target = ".(int) $poid." AND targettype IN ('invoice_supplier','facture_fourn') AND sourcetype IN ('order_supplier','commande_fournisseur')");
			$ids += $this->queryIds("SELECT fk_target AS id FROM ".$prefix."element_element WHERE fk_source = ".(int) $poid." AND sourcetype IN ('order_supplier','commande_fournisseur') AND targettype IN ('invoice_supplier','facture_fourn')");
		}
		return array_keys($ids);
	}

	/**
	 * Get delivery/reception date from expedition_extrafields. Column name must be alphanumeric + underscore.
	 * If field is empty, uses ATA then ETA.
	 *
	 * @param int    $expId    Expedition rowid
	 * @param string $fieldName Column name (e.g. ata, eta) or empty for default ata/eta
	 * @return int|null Unix timestamp or null
	 */
	protected function getDeliveryDateFromExpedition($expId, $fieldName)
	{
		$prefix = $this->db->prefix();
		$expId = (int) $expId;
		if ($expId <= 0) {
			return null;
		}
		if ($fieldName !== '') {
			// $fieldName already validated (alphanumeric + underscore only)
			$sql = "SELECT `".$fieldName."` AS val FROM ".$prefix."expedition_extrafields WHERE fk_object = ".$expId;
			$resql = $this->db->query($sql);
			if (!$resql || !($obj = $this->db->fetch_object($resql)) || $obj->val === null || $obj->val === '' || $obj->val === '0000-00-00') {
				return null;
			}
			$t = strtotime($obj->val);
			return $t ? $t : null;
		}
		$sql = "SELECT ata, eta FROM ".$prefix."expedition_extrafields WHERE fk_object = ".$expId;
		$resql = $this->db->query($sql);
		if (!$resql || !($obj = $this->db->fetch_object($resql))) {
			return null;
		}
		if (!empty($obj->ata) && $obj->ata !== '0000-00-00') {
			$t = strtotime($obj->ata);
			if ($t) {
				return $t;
			}
		}
		if (!empty($obj->eta) && $obj->eta !== '0000-00-00') {
			$t = strtotime($obj->eta);
			if ($t) {
				return $t;
			}
		}
		return null;
	}

	/**
	 * Get the ATA and ETA of the shipment linked to an invoice (Y-m-d or null
	 * when no shipment or no date). Used for the due date (ATA then ETA) and
	 * the Odoo delivery date (ETA).
	 *
	 * @param Facture|FactureFournisseur $invoice  Loaded invoice
	 * @param bool                       $customer True for customer invoice
	 * @return array array('ata' => string|null, 'eta' => string|null)
	 */
	protected function getShipmentDates($invoice, $customer)
	{
		$expId = $customer ? $this->getExpeditionIdForCustomerInvoice($invoice) : $this->getExpeditionIdForSupplierInvoice($invoice);
		$ata = null;
		$eta = null;
		if ($expId > 0) {
			$sql = "SELECT ata, eta FROM ".$this->db->prefix()."expedition_extrafields WHERE fk_object = ".(int) $expId;
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					foreach (array('ata', 'eta') as $f) {
						$v = $obj->$f;
						if (!empty($v) && $v !== '0000-00-00' && strtotime($v) > 0) {
							$$f = date('Y-m-d', strtotime($v));
						}
					}
				}
			}
		}
		return array('ata' => $ata, 'eta' => $eta);
	}

	/**
	 * Get accounting date for customer invoice (Y-m-d) from configured source.
	 *
	 * @param Facture $invoice
	 * @return string
	 */
	protected function getAccountingDateForCustomerInvoice($invoice)
	{
		$source = $this->getAccountingDateSource();
		if ($source === 'due_date' && !empty($invoice->date_lim_reglement)) {
			$ts = is_numeric($invoice->date_lim_reglement) ? $invoice->date_lim_reglement : strtotime($invoice->date_lim_reglement);
			if ($ts > 0) {
				return date('Y-m-d', $ts);
			}
		}
		if ($source === 'delivery_reception') {
			$expId = $this->getExpeditionIdForCustomerInvoice($invoice);
			if ($expId > 0) {
				$ts = $this->getDeliveryDateFromExpedition($expId, $this->getDeliveryDateField());
				if ($ts !== null) {
					return date('Y-m-d', $ts);
				}
			}
		}
		$ts = !empty($invoice->date) ? (is_numeric($invoice->date) ? $invoice->date : strtotime($invoice->date)) : 0;
		return $ts > 0 ? date('Y-m-d', $ts) : date('Y-m-d');
	}

	/**
	 * Get accounting date for supplier invoice (Y-m-d) from configured source.
	 * Vendor deposits and credit notes are money documents, not goods
	 * movements: they always book at the invoice date — the ETA/ATA and
	 * due-date sources belong to the goods bill (a deposit created from the
	 * same PO would otherwise inherit the shipment dates).
	 *
	 * @param FactureFournisseur $invoice
	 * @return string
	 */
	protected function getAccountingDateForSupplierInvoice($invoice)
	{
		$isMoneyDoc = ($invoice->type == FactureFournisseur::TYPE_DEPOSIT || $invoice->type == FactureFournisseur::TYPE_CREDIT_NOTE);
		$source = $this->getAccountingDateSource();
		if (!$isMoneyDoc && $source === 'due_date' && !empty($invoice->date_lim_reglement)) {
			$ts = is_numeric($invoice->date_lim_reglement) ? $invoice->date_lim_reglement : strtotime($invoice->date_lim_reglement);
			if ($ts > 0) {
				return date('Y-m-d', $ts);
			}
		}
		if (!$isMoneyDoc && $source === 'delivery_reception') {
			$expId = $this->getExpeditionIdForSupplierInvoice($invoice);
			if ($expId > 0) {
				$ts = $this->getDeliveryDateFromExpedition($expId, $this->getDeliveryDateField());
				if ($ts !== null) {
					return date('Y-m-d', $ts);
				}
			}
		}
		$ts = is_numeric($invoice->date) ? $invoice->date : ($invoice->date ? strtotime($invoice->date) : 0);
		if ($ts <= 0 && !empty($invoice->date_facture)) {
			$ts = strtotime($invoice->date_facture);
		}
		return $ts > 0 ? date('Y-m-d', $ts) : date('Y-m-d');
	}

	/**
	 * Get accounting date for expense report (Y-m-d). Delivery/reception = approval date.
	 *
	 * @param ExpenseReport $exp
	 * @return string
	 */
	protected function getAccountingDateForExpenseReport($exp)
	{
		$source = $this->getAccountingDateSource();
		if ($source === 'delivery_reception') {
			foreach (array('date_approbation', 'date_approve', 'date_validation') as $f) {
				if (!empty($exp->$f)) {
					$t = is_numeric($exp->$f) ? $exp->$f : strtotime($exp->$f);
					if ($t > 0) {
						return date('Y-m-d', $t);
					}
				}
			}
		}
		if ($source === 'due_date' && !empty($exp->date_lim_reglement)) {
			$t = is_numeric($exp->date_lim_reglement) ? $exp->date_lim_reglement : strtotime($exp->date_lim_reglement);
			if ($t > 0) {
				return date('Y-m-d', $t);
			}
		}
		$ts = !empty($exp->date_debut) ? (is_numeric($exp->date_debut) ? $exp->date_debut : strtotime($exp->date_debut)) : 0;
		return $ts > 0 ? date('Y-m-d', $ts) : date('Y-m-d');
	}
}
