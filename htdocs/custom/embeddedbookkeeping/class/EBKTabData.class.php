<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/EBKTabData.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Read-side helper for the "Accounting entries" tab:
 *	             - lineRows():     one JOIN per render, per-line bound/suggested accounts
 *	             - counterRow():   thirdparty/user counter-account (debit/credit side head)
 *	             - vatGroups():    VAT/localtax amounts grouped by VAT account
 *	             - defaultDraft(): Odoo-style N-row balanced EBKEntryDraft
 *	             - postedEntries(): llx_accounting_bookkeeping rows of the document
 *
 *	            Account derivation REUSES core AccountingAccount::getAccountingCodeToBind()
 *            (the same resolver the core ventilation pages use) — never re-invented here.
 */

require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
// Core getAccountingCodeToBind() accesses $facture::TYPE_DEPOSIT and does
// `new $facture($db)` — the invoice argument MUST be a real Facture /
// FactureFournisseur instance (unfetched is fine), never a stdClass.
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/factureligne.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.ligne.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKEntryDraft.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKAccountLookup.class.php';

if (!class_exists('EBKTabData', false)) {

class EBKTabData
{
	/**
	 * Per-line account map for one invoice / expense report. ONE JOIN query,
	 * mirroring accountancy/customer/index.php (and its supplier twin):
	 * bound account (fk_code_ventilation) + product/thirdparty codes + the
	 * four accounting_account rowid joins used by getAccountingCodeToBind().
	 *
	 * @param  DoliDB        $db
	 * @param  CommonObject  $object   Fetched Facture | FactureFournisseur | ExpenseReport (lines loaded)
	 * @param  string        $docType  'customer_invoice' | 'supplier_invoice' | 'expense_report'
	 * @return array<int,array<string,mixed>> Rows; keys see body. 'status' in bound|suggested|missing.
	 */
	public static function lineRows($db, $object, $docType)
	{
		global $conf, $mysoc;

		$entity = (int) $conf->entity;
		$isSupplier = ($docType === 'supplier_invoice');
		$isExpense  = ($docType === 'expense_report');
		$buySide = $isSupplier || $isExpense; // income accounts for customer, expense accounts otherwise

		// Chart of accounts rowid in llx_accounting_system (empty = chart not configured → no suggestions)
		$chartaccountcode = dol_getIdFromCode($db, getDolGlobalString('CHARTOFACCOUNTS'), 'accounting_system', 'rowid', 'pcg_version');

		if ($isExpense) {
			$sql = "SELECT d.rowid, d.fk_product, d.product_type, d.comments AS description, d.qty,";
			$sql .= " d.total_ht, d.total_tva, d.total_localtax1, d.total_localtax2, d.total_ttc,";
			$sql .= " d.tva_tx, d.vat_src_code, 0 AS info_bits, d.fk_code_ventilation";
			$sql .= ", p.accountancy_code_buy, p.accountancy_code_buy_intra, p.accountancy_code_buy_export";
			$sql .= ", ab.account_number AS bound_number, ab.label AS bound_label";
			$sql .= " FROM ".MAIN_DB_PREFIX."expensereport_det AS d";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS ab ON ab.rowid = d.fk_code_ventilation AND ab.active = 1 AND ab.entity IN (0, ".((int) $entity).")";
			$sql .= " WHERE d.fk_expensereport = ".((int) $object->id);
			$sql .= " ORDER BY d.rang, d.rowid";
		} else {
			$lineTable = $isSupplier ? "facture_fourn_det" : "facturedet";
			$fkColumn  = $isSupplier ? "fk_facture_fourn" : "fk_facture";
			$codeCol   = $isSupplier ? "accountancy_code_buy" : "accountancy_code_sell";

			$sql = "SELECT fd.rowid, fd.fk_product, fd.product_type, fd.description, fd.qty,";
			$sql .= " fd.total_ht, fd.total_tva, fd.total_localtax1, fd.total_localtax2, fd.total_ttc,";
			$sql .= " fd.tva_tx, fd.vat_src_code, fd.info_bits, fd.fk_code_ventilation";
			$sql .= ", p.".$codeCol.", p.".$codeCol."_intra, p.".$codeCol."_export";
			$sql .= ", s.accountancy_code_sell AS company_code_sell, s.accountancy_code_buy AS company_code_buy";
			$sql .= ", ab.account_number AS bound_number, ab.label AS bound_label";
			$sql .= " FROM ".MAIN_DB_PREFIX.$lineTable." AS fd";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = fd.fk_product";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = ".((int) $object->socid);
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS ab ON ab.rowid = fd.fk_code_ventilation AND ab.active = 1 AND ab.entity IN (0, ".((int) $entity).")";
			if ($chartaccountcode > 0) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS aa ON aa.account_number = p.".$codeCol." AND aa.fk_pcg_version = ".((int) $chartaccountcode)." AND aa.active = 1 AND aa.entity IN (0, ".((int) $entity).")";
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS aa2 ON aa2.account_number = p.".$codeCol."_intra AND aa2.fk_pcg_version = ".((int) $chartaccountcode)." AND aa2.active = 1 AND aa2.entity IN (0, ".((int) $entity).")";
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS aa3 ON aa3.account_number = p.".$codeCol."_export AND aa3.fk_pcg_version = ".((int) $chartaccountcode)." AND aa3.active = 1 AND aa3.entity IN (0, ".((int) $entity).")";
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS aa4 ON aa4.account_number = s.".$codeCol." AND aa4.fk_pcg_version = ".((int) $chartaccountcode)." AND aa4.active = 1 AND aa4.entity IN (0, ".((int) $entity).")";
			} else {
				$sql .= ", NULL AS aa, NULL AS aa2, NULL AS aa3, NULL AS aa4"; // chart not configured — keep column shape
			}
			$sql .= " WHERE fd.".$fkColumn." = ".((int) $object->id);
			$sql .= " ORDER BY fd.rang, fd.rowid";
		}

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__." err=".$db->lasterror, LOG_ERR);
			return array();
		}

		// Lightweight societe objects for the core resolver (no fetch loop — property
		// assignment only, exactly the accountancy/customer/index.php trick).
		$thirdparty = is_object($object->thirdparty) ? $object->thirdparty : null;
		$buyer  = new Societe($db);
		$seller = new Societe($db);
		if ($isSupplier || $isExpense) {
			// supplier: buyer = my company, seller = vendor; expense: both my company
			$buyer->country_code  = $mysoc->country_code;
			$seller->country_code = $isExpense ? $mysoc->country_code : (string) ($thirdparty ? $thirdparty->country_code : '');
		} else {
			$buyer->country_code  = (string) ($thirdparty ? $thirdparty->country_code : '');
			$seller->country_code = $mysoc->country_code;
		}
		$thirdpartyProductCode = '';
		if ($thirdparty && property_exists($thirdparty, $buySide ? 'accountancy_code_buy' : 'accountancy_code_sell')) {
			$thirdpartyProductCode = (string) ($buySide ? $thirdparty->accountancy_code_buy : $thirdparty->accountancy_code_sell);
		}
		$buyer->code_compta_product  = $isSupplier || $isExpense ? '' : $thirdpartyProductCode;
		$seller->code_compta_product = $isSupplier ? $thirdpartyProductCode : '';

		$aaResolver = new AccountingAccount($db);
		$type = $isSupplier ? 'supplier' : 'customer';

		$rows = array();
		$i = 0;
		while ($obj = $db->fetch_object($resql)) {
			$row = array(
				'rowid'            => (int) $obj->rowid,
				'fk_product'       => (int) $obj->fk_product,
				'product_type'     => (int) $obj->product_type,
				'description'      => (string) ($obj->description !== null && $obj->description !== '' ? $obj->description : ''),
				'qty'              => (float) $obj->qty,
				'total_ht'         => (float) $obj->total_ht,
				'total_tva'        => (float) $obj->total_tva,
				'total_localtax1'  => (float) $obj->total_localtax1,
				'total_localtax2'  => (float) $obj->total_localtax2,
				'total_ttc'        => (float) $obj->total_ttc,
				'tva_tx'           => (float) $obj->tva_tx,
				'vat_src_code'     => (string) $obj->vat_src_code,
				'npr'              => ((int) $obj->info_bits & 1) == 1,
				'bound_number'     => (string) ($obj->bound_number ?? ''),
				'bound_label'      => (string) ($obj->bound_label ?? ''),
				'suggested_number' => '',
				'suggested_label'  => '',
				'status'           => 'missing',
			);

			if (!empty($row['bound_number'])) {
				$row['status'] = 'bound';
			} elseif (!$isExpense && $chartaccountcode > 0) {
				// Suggested account via the CORE resolver — same chain as the ventilation pages:
				// global default constant -> product code -> thirdparty product code (opt-in).
				$product = new Product($db);
				$product->id = $row['fk_product'];
				$product->product_type = $row['product_type'] >= 0 ? $row['product_type'] : 0;
				$codeCol = $buySide ? 'accountancy_code_buy' : 'accountancy_code_sell';
				$product->{$codeCol}          = (string) $obj->{$codeCol};
				$product->{$codeCol.'_intra'} = (string) $obj->{$codeCol.'_intra'};
				$product->{$codeCol.'_export'} = (string) $obj->{$codeCol.'_export'};

				// Real (unfetched) invoice instance — required by the core resolver:
				// it reads $facture::TYPE_DEPOSIT / TYPE_CREDIT_NOTE and may do
				// `new $facture($db)` for credit-note-of-deposit lookups.
				$factureLite = $isSupplier ? new FactureFournisseur($db) : new Facture($db);
				$factureLite->id = (int) $object->id;
				$factureLite->type = isset($object->type) ? $object->type : 0;
				if (!empty($object->fk_facture_source)) {
					$factureLite->fk_facture_source = $object->fk_facture_source;
				}
				$lineLite = $isSupplier ? new SupplierInvoiceLine($db) : new FactureLigne($db);
				$lineLite->desc = $row['description'];
				$lineLite->product_type = $row['product_type'];

				$accountingAccount = array(
					'dom' => isset($obj->aa) ? (int) $obj->aa : 0,
					'intra' => isset($obj->aa2) ? (int) $obj->aa2 : 0,
					'export' => isset($obj->aa3) ? (int) $obj->aa3 : 0,
					'thirdparty' => isset($obj->aa4) ? (int) $obj->aa4 : 0,
				);
				$resolved = $aaResolver->getAccountingCodeToBind($buyer, $seller, $product, $factureLite, $lineLite, $accountingAccount, $type);
				$number = '';
				if (is_array($resolved)) {
					$number = (string) (!empty($resolved['code_p']) ? $resolved['code_p']
						: (!empty($resolved['code_t']) ? $resolved['code_t']
						: (!empty($resolved['code_l']) ? $resolved['code_l'] : '')));
				}
				if ($number !== '') {
					$row['suggested_number'] = $number;
					$row['status'] = 'suggested';
				}
			}
			$rows[] = $row;
			$i++;
		}
		$db->free($resql);

		// Batch-resolve labels of all suggested accounts (ONE query, not one per line).
		$suggestedNumbers = array();
		foreach ($rows as $r) {
			if ($r['suggested_number'] !== '') {
				$suggestedNumbers[$r['suggested_number']] = true;
			}
		}
		if (!empty($suggestedNumbers)) {
			$labels = self::labelsForAccounts($db, array_keys($suggestedNumbers), $entity);
			foreach ($rows as $k => $r) {
				if ($r['suggested_number'] !== '') {
					$rows[$k]['suggested_label'] = isset($labels[$r['suggested_number']]) ? $labels[$r['suggested_number']] : '';
				}
			}
		}

		return $rows;
	}

	/**
	 * Counter-account row (the thirdparty / employee side of the entry).
	 *
	 * @param  DoliDB       $db
	 * @param  CommonObject $object
	 * @param  string       $docType
	 * @return array{account:string,label:string,subledger:string,subledger_label:string}
	 */
	public static function counterRow($db, $object, $docType)
	{
		global $langs;
		$out = array('account' => '', 'label' => '', 'subledger' => '', 'subledger_label' => '');

		if ($docType === 'customer_invoice') {
			$tp = is_object($object->thirdparty) ? $object->thirdparty : null;
			$account = $tp ? trim((string) $tp->accountancy_code_customer_general) : '';
			if ($account === '') {
				$account = getDolGlobalString('ACCOUNTING_ACCOUNT_CUSTOMER');
			}
			$out['account'] = $account;
			$out['subledger'] = $tp ? trim((string) $tp->code_compta) : '';
			$out['subledger_label'] = $tp ? trim((string) $tp->name) : '';
		} elseif ($docType === 'supplier_invoice') {
			$tp = is_object($object->thirdparty) ? $object->thirdparty : null;
			$account = $tp ? trim((string) $tp->accountancy_code_supplier_general) : '';
			if ($account === '') {
				$account = getDolGlobalString('ACCOUNTING_ACCOUNT_SUPPLIER');
			}
			$out['account'] = $account;
			$out['subledger'] = $tp ? trim((string) $tp->code_compta_fournisseur) : '';
			$out['subledger_label'] = $tp ? trim((string) $tp->name) : '';
		} else {
			// expense report: debit/credit side is the employee's account
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
			$u = new User($db);
			if ($u->fetch(isset($object->fk_user_author) ? $object->fk_user_author : $object->user_author) > 0 && !empty($u->accountancy_code)) {
				$out['account'] = trim((string) $u->accountancy_code);
				$out['subledger_label'] = trim((string) $u->getFullName($langs));
			}
			if ($out['account'] === '') {
				$out['account'] = getDolGlobalString('ACCOUNTING_ACCOUNT_EXPENSEREPORT');
			}
		}

		if ($out['account'] !== '' && $out['label'] === '') {
			$out['label'] = EBKAccountLookup::labelForAccount($db, $out['account'], 0);
		}
		return $out;
	}

	/**
	 * VAT + localtax amounts grouped by resolved VAT account, one group per
	 * account. Uses core getTaxesFromId() (cached per tax key) exactly like
	 * the core journals; NPR lines are skipped (their taxes fold into the
	 * product line row in defaultDraft()).
	 *
	 * @param  DoliDB       $db
	 * @param  array        $lineRows  Output of lineRows()
	 * @param  string       $docType
	 * @param  Societe      $buyer
	 * @param  Societe      $seller
	 * @return array<int,array{account:string,label:string,amount:float,label_operation:string}> Credit- or debit-positive amounts
	 */
	public static function vatGroups($db, $lineRows, $docType, $buyer, $seller)
	{
		$buySide = ($docType !== 'customer_invoice');
		$constFallback = $buySide ? 'ACCOUNTING_VAT_BUY_ACCOUNT' : 'ACCOUNTING_VAT_SOLD_ACCOUNT';
		$taxCache = array();
		$groups = array();

		foreach ($lineRows as $line) {
			if ($line['npr']) {
				continue; // NPR: taxes carried by the product line itself
			}
			$taxAmount = (float) $line['total_tva'] + (float) $line['total_localtax1'] + (float) $line['total_localtax2'];
			if (abs($taxAmount) < 0.005) {
				continue;
			}
			$taxKey = $line['tva_tx'].(!empty($line['vat_src_code']) ? ' ('.$line['vat_src_code'].')' : '');
			if (!isset($taxCache[$taxKey])) {
				$vatdata = getTaxesFromId($taxKey, $buyer, $seller, 0);
				$account = '';
				if (is_array($vatdata) && empty($vatdata['error'])) {
					$account = trim((string) ($buySide ? ($vatdata['accountancy_code_buy'] ?? '') : ($vatdata['accountancy_code_sell'] ?? '')));
				}
				if ($account === '') {
					$account = getDolGlobalString($constFallback);
				}
				$taxCache[$taxKey] = $account;
			}
			$account = $taxCache[$taxKey];
			if ($account === '') {
				continue;
			}
			if (!isset($groups[$account])) {
				$groups[$account] = array(
					'account' => $account,
					'label' => '',
					'amount' => 0.0,
					'label_operation' => 'VAT',
				);
			}
			$groups[$account]['amount'] += $taxAmount;
			$groups[$account]['label_operation'] .= (substr($groups[$account]['label_operation'], -1) === '%') ? ', '.$line['tva_tx'].'%' : ' '.$line['tva_tx'].'%';
		}

		$out = array();
		foreach ($groups as $account => $g) {
			$g['label'] = EBKAccountLookup::labelForAccount($db, $account, 0);
			$out[] = $g;
		}
		return $out;
	}

	/**
	 * Assemble the default balanced draft: customer = 1 debit counter row +
	 * per-line credit rows + grouped VAT credit rows; supplier/expense are the
	 * mirror. Amounts come from STORED per-line values (total_ht / total_tva /
	 * total_localtax* / total_ttc) so the per-line identity ttc = ht + taxes
	 * holds at MT precision ⇒ balanced by construction; any residual drift is
	 * folded onto the last line row.
	 *
	 * @param  DoliDB       $db
	 * @param  CommonObject $object  Fetched source object (lines + thirdparty loaded)
	 * @param  string       $docType
	 * @return array{draft:EBKEntryDraft,lineRows:array,counter:array,vat:array,roundingAdjusted:bool}
	 */
	public static function defaultDraft($db, $object, $docType)
	{
		global $conf, $mysoc;

		$lineRows = self::lineRows($db, $object, $docType);
		$counter = self::counterRow($db, $object, $docType);

		$thirdparty = is_object($object->thirdparty) ? $object->thirdparty : null;
		$buyer = new Societe($db);
		$seller = new Societe($db);
		if ($docType === 'customer_invoice') {
			$buyer->country_code = (string) ($thirdparty ? $thirdparty->country_code : '');
			$seller->country_code = $mysoc->country_code;
		} elseif ($docType === 'supplier_invoice') {
			$buyer->country_code = $mysoc->country_code;
			$seller->country_code = (string) ($thirdparty ? $thirdparty->country_code : '');
		} else {
			$buyer->country_code = $mysoc->country_code;
			$seller->country_code = $mysoc->country_code;
		}
		$vatGroups = self::vatGroups($db, $lineRows, $docType, $buyer, $seller);

		$draft = new EBKEntryDraft();
		$draft->doc_type = $docType;
		$draft->fk_doc = (int) $object->id;
		$draft->doc_ref = isset($object->ref) ? (string) $object->ref : '';
		$draft->thirdparty_code = ($thirdparty && isset($thirdparty->code_client)) ? (string) $thirdparty->code_client : '';
		$draft->label_operation = $draft->doc_ref;
		$draft->date_doc = isset($object->date) ? (int) $object->date : 0;
		$draft->date_lim_reglement = isset($object->date_lim_reglement) ? (int) $object->date_lim_reglement : 0;
		$draft->currency = isset($object->multicurrency_code) ? (string) $object->multicurrency_code : '';

		$isCustomer = ($docType === 'customer_invoice');
		$lineSide = $isCustomer ? 'C' : 'D';
		$counterSide = $isCustomer ? 'D' : 'C';

		// 1. Counter row: |sum(total_ttc)| — sign applied by addRow(side)
		$sumTtc = 0.0;
		foreach ($lineRows as $line) {
			$sumTtc += (float) $line['total_ttc'];
		}
		$draft->addRow(
			$counter['account'],
			$counter['label'],
			abs($sumTtc),
			$counterSide,
			$draft->doc_ref,
			0,
			$counter['subledger'],
			$counter['subledger_label']
		);

		// 2. Per-line rows (HT; NPR lines carry their own taxes)
		$lastLineIndex = -1;
		foreach ($lineRows as $line) {
			$account = '';
			$label = '';
			if ($line['status'] === 'bound') {
				$account = $line['bound_number'];
				$label = $line['bound_label'];
			} elseif ($line['status'] === 'suggested') {
				$account = $line['suggested_number'];
				$label = $line['suggested_label'];
			}
			$amount = (float) $line['total_ht'];
			if ($line['npr']) {
				$amount += (float) $line['total_tva'] + (float) $line['total_localtax1'] + (float) $line['total_localtax2'];
			}
			if (abs($amount) < 0.005 && $account === '') {
				continue; // fully empty line (null amounts), nothing to book
			}
			$draft->addRow(
				$account,
				$label,
				abs($amount),
				$lineSide,
				($line['description'] !== '' ? dol_trunc($line['description'], 40, 'right', 'UTF-8', 1) : 'L'.((int) $line['rowid'])).' #'.((int) $line['rowid']),
				(int) $line['rowid']
			);
			$lastLineIndex = count($draft->rows) - 1;
		}

		// 3. Grouped VAT rows
		foreach ($vatGroups as $g) {
			$draft->addRow(
				$g['account'],
				$g['label'],
				abs($g['amount']),
				$lineSide,
				dol_trunc($g['label_operation'], 60, 'right', 'UTF-8', 1),
				0
			);
		}

		// 4. Fold any residual (data drift) onto the last line row so the entry
		//    stays exactly balanced; flag it so the UI can warn.
		$roundingAdjusted = false;
		$sum = 0.0;
		foreach ($draft->rows as $r) {
			$sum += (float) $r['amount'];
		}
		$residual = price2num($sum, 'MT');
		if (abs($residual) >= 0.005 && $lastLineIndex >= 0) {
			$draft->rows[$lastLineIndex]['amount'] = price2num($draft->rows[$lastLineIndex]['amount'] - $residual, 'MT');
			$roundingAdjusted = true;
		}

		return array(
			'draft' => $draft,
			'lineRows' => $lineRows,
			'counter' => $counter,
			'vat' => $vatGroups,
			'roundingAdjusted' => $roundingAdjusted,
		);
	}

	/**
	 * Related dates offered as quick-picks for the accounting date, semantics
	 * per document type:
	 *   - all: document date
	 *   - expense report: validation date, approval date, payment date
	 *     (from llx_payment_expensereport — ExpenseReport has no date_pmt property)
	 *   - invoices: payment due date
	 *   - customer invoice with an Incoterm: revenue-recognition date derived
	 *     from the linked shipment (D-group terms deliver at destination ->
	 *     planned delivery date; E/F/C-group terms transfer at shipment ->
	 *     real shipment date)
	 *
	 * The 'value' field is the stable semantic key used by the admin preset
	 * constants (EMBEDDEDBOOKKEEPING_DEFAULT_DATE_*) and by the setup page.
	 *
	 * @param  DoliDB       $db
	 * @param  CommonObject $object  Fetched source object
	 * @param  string       $docType
	 * @return array<int,array{value:string,key:string,ts:int,suffix:string}> Each item: semantic key, lang key, timestamp, label suffix
	 */
	public static function relatedDates($db, $object, $docType)
	{
		$out = array();
		if (!empty($object->date)) {
			$out[] = array('value' => 'document', 'key' => 'EBKTabDateDocument', 'ts' => (int) $object->date, 'suffix' => '');
		}

		if ($docType === 'expense_report') {
			// Historical quirk: ExpenseReport carries both $date_approve and
			// $date_approbation; fetch() fills $date_approve on modern versions.
			$approveTs = 0;
			if (!empty($object->date_approve)) {
				$approveTs = (int) $object->date_approve;
			} elseif (!empty($object->date_approbation)) {
				$approveTs = (int) $object->date_approbation;
			}
			if (!empty($object->date_valid)) {
				$out[] = array('value' => 'validation', 'key' => 'EBKTabDateValid', 'ts' => (int) $object->date_valid, 'suffix' => '');
			}
			if ($approveTs > 0) {
				$out[] = array('value' => 'approval', 'key' => 'EBKTabDateApprove', 'ts' => $approveTs, 'suffix' => '');
			}
			// Payment date: latest payment of the report.
			$sql = "SELECT MAX(p.datep) AS lastpay FROM ".MAIN_DB_PREFIX."payment_expensereport AS p";
			$sql .= " WHERE p.fk_expensereport = ".((int) $object->id);
			$resql = $db->query($sql);
			if ($resql) {
				$objp = $db->fetch_object($resql);
				$db->free($resql);
				if ($objp && !empty($objp->lastpay)) {
					$out[] = array('value' => 'payment', 'key' => 'EBKTabDatePayment', 'ts' => (int) $objp->lastpay, 'suffix' => '');
				}
			}
		} else {
			if (!empty($object->date_lim_reglement)) {
				$out[] = array('value' => 'due', 'key' => 'EBKTabDateDue', 'ts' => (int) $object->date_lim_reglement, 'suffix' => '');
			}

			$isCustomerInvoice = ($docType === 'customer_invoice');
			$invType = isset($object->type) ? (int) $object->type : 0;

			// Deposit invoice: the natural recognition date is the cash receipt
			// (unearned revenue liability arises at payment).
			if ($invType === Facture::TYPE_DEPOSIT) {
				if ($isCustomerInvoice) {
					$sql = "SELECT MAX(p.datep) AS lastpay FROM ".MAIN_DB_PREFIX."paiement AS p";
					$sql .= " JOIN ".MAIN_DB_PREFIX."paiement_facture AS pf ON pf.fk_paiement = p.rowid";
					$sql .= " WHERE pf.fk_facture = ".((int) $object->id);
				} else {
					$sql = "SELECT MAX(p.datep) AS lastpay FROM ".MAIN_DB_PREFIX."paiementfourn AS p";
					$sql .= " JOIN ".MAIN_DB_PREFIX."paiementfourn_facturefourn AS pff ON pff.fk_paiementfourn = p.rowid";
					$sql .= " WHERE pff.fk_facturefourn = ".((int) $object->id);
				}
				$resql = $db->query($sql);
				if ($resql) {
					$objp = $db->fetch_object($resql);
					$db->free($resql);
					if ($objp && !empty($objp->lastpay)) {
						$out[] = array('value' => 'payment', 'key' => 'EBKTabDatePayment', 'ts' => (int) $objp->lastpay, 'suffix' => '');
					}
				}
			}

			// Credit note / replacement invoice: restatement semantics — offer
			// the SOURCE invoice date so the entry can hit the original period.
			if (($invType === Facture::TYPE_CREDIT_NOTE || $invType === Facture::TYPE_REPLACEMENT)
				&& !empty($object->fk_facture_source)) {
				$srcTable = $isCustomerInvoice ? 'facture' : 'facture_fourn';
				$sql = "SELECT f.datef FROM ".MAIN_DB_PREFIX.$srcTable." AS f WHERE f.rowid = ".((int) $object->fk_facture_source);
				$resql = $db->query($sql);
				if ($resql) {
					$objp = $db->fetch_object($resql);
					$db->free($resql);
					if ($objp && !empty($objp->datef)) {
						$out[] = array('value' => 'source', 'key' => 'EBKTabDateSource', 'ts' => (int) strtotime((string) $objp->datef), 'suffix' => '');
					}
				}
			}

			if ($isCustomerInvoice) {
				$incoterm = self::incotermRecognitionDate($db, $object, $docType);
				if ($incoterm !== null && $incoterm['ts'] > 0) {
					$out[] = array(
						'value' => ($incoterm['mode'] === 'delivery') ? 'incoterm_delivery' : 'incoterm_shipment',
						'key' => ($incoterm['mode'] === 'delivery') ? 'EBKTabDateIncotermDelivery' : 'EBKTabDateIncotermShipment',
						'ts' => (int) $incoterm['ts'],
						'suffix' => (string) $incoterm['code'],
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Resolve the default accounting date from the admin preset constant for
	 * the doc type (EMBEDDEDBOOKKEEPING_DEFAULT_DATE_{CUSTOMER|SUPPLIER|EXPENSE}).
	 * Falls back to the document date when the preset is unknown, 'document',
	 * or currently unavailable for this document (e.g. preset 'payment' but the
	 * expense report is not paid yet).
	 *
	 * @param  DoliDB       $db
	 * @param  CommonObject $object
	 * @param  string       $docType
	 * @return array{ts:int,value:string} Resolved timestamp + the semantic key actually used
	 */
	public static function resolveDateByPreference($db, $object, $docType)
	{
		if ($docType === 'customer_invoice') {
			$constName = 'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CUSTOMER';
		} elseif ($docType === 'supplier_invoice') {
			$constName = 'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_SUPPLIER';
		} else {
			$constName = 'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_EXPENSE';
		}
		$preset = strtolower(trim((string) getDolGlobalString($constName)));

		// Invoice-type presets (deposit / credit note / replacement) override the
		// generic customer/supplier preset — recognition semantics differ by type.
		if ($docType === 'customer_invoice' || $docType === 'supplier_invoice') {
			$typeConst = '';
			$invType = isset($object->type) ? (int) $object->type : 0;
			if ($invType === Facture::TYPE_DEPOSIT) {
				$typeConst = 'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_DEPOSIT';
			} elseif ($invType === Facture::TYPE_CREDIT_NOTE) {
				$typeConst = 'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CREDIT_NOTE';
			} elseif ($invType === Facture::TYPE_REPLACEMENT) {
				$typeConst = 'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_REPLACEMENT';
			}
			if ($typeConst !== '') {
				$typePreset = strtolower(trim((string) getDolGlobalString($typeConst)));
				if ($typePreset !== '') {
					$preset = $typePreset;
				}
			}
		}

		$dates = self::relatedDates($db, $object, $docType);

		if ($preset === 'today') {
			return array('ts' => (int) dol_now(), 'value' => 'today');
		}

		if ($preset !== '' && $preset !== 'document') {
			foreach ($dates as $d) {
				if ($d['value'] === $preset) {
					return array('ts' => (int) $d['ts'], 'value' => $d['value']);
				}
			}
		}

		// Fallback: document date (always first in $dates when the object has one).
		if (isset($dates[0]) && $dates[0]['value'] === 'document') {
			return array('ts' => (int) $dates[0]['ts'], 'value' => 'document');
		}
		return array('ts' => (int) dol_now(), 'value' => 'today');
	}

	/**
	 * Derive the Incoterm-based recognition date of an invoice from its linked
	 * shipment (customer, revenue side) or reception (supplier, cost side):
	 *   - D-group terms (DAP/DPU/DDP + legacy DAF/DES/DEQ/DDU): delivery at
	 *     destination -> planned delivery date (date_delivery; Dolibarr has no
	 *     confirmed-POD field), falling back to the real shipment/receipt date.
	 *   - E/F/C-group terms (EXW/FCA/FAS/FOB/CFR/CIF/CPT/CIP): risk transfers at
	 *     shipment -> real shipment date (customer) / actual reception date
	 *     (supplier: the seller's loading date is not in Dolibarr, the receipt
	 *     date is the closest available proxy).
	 * The invoice's own Incoterm wins; the shipment/reception's is the fallback.
	 *
	 * Customer: latest llx_expedition linked to the invoice (element_element
	 * 'facture' <-> 'shipping', both directions). Supplier: latest llx_reception
	 * linked directly ('invoice_supplier' <-> 'reception') or two-hop via the
	 * supplier order ('invoice_supplier' <-> 'order_supplier' <-> 'reception').
	 *
	 * @param  DoliDB       $db
	 * @param  CommonObject $object Invoice (Facture or FactureFournisseur)
	 * @param  string       $docType 'customer_invoice' | 'supplier_invoice'
	 * @return array{ts:int,mode:string,code:string}|null
	 */
	private static function incotermRecognitionDate($db, $object, $docType)
	{
		global $conf;

		$isSupplier = ($docType === 'supplier_invoice');
		$invId = (int) $object->id;

		if ($isSupplier) {
			$sql = "SELECT r.date_reception, r.date_delivery, r.date_valid, r.fk_incoterms,";
			$sql .= " i.code AS inv_code, s.code AS rec_code";
			$sql .= " FROM ".MAIN_DB_PREFIX."reception AS r";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_incoterms AS i ON i.rowid = ".((int) $object->fk_incoterms);
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_incoterms AS s ON s.rowid = r.fk_incoterms";
			$sql .= " WHERE r.entity IN (0, ".((int) $conf->entity).")";
			$sql .= " AND (";
			// Direct invoice <-> reception link (both directions)
			$sql .= " (EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee WHERE ee.fk_source = ".$invId." AND ee.sourcetype = 'invoice_supplier' AND ee.targettype = 'reception' AND ee.fk_target = r.rowid))";
			$sql .= " OR (EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee WHERE ee.fk_target = ".$invId." AND ee.targettype = 'invoice_supplier' AND ee.sourcetype = 'reception' AND ee.fk_source = r.rowid))";
			// Two-hop via the supplier order (reception created from order, invoice created from order)
			$sql .= " OR (EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee1";
			$sql .= " JOIN ".MAIN_DB_PREFIX."element_element ee2 ON ee2.fk_source = ee1.fk_source AND ee2.sourcetype = 'order_supplier'";
			$sql .= " WHERE ee1.fk_target = r.rowid AND ee1.targettype = 'reception' AND ee1.sourcetype = 'order_supplier'";
			$sql .= " AND ee2.targettype = 'invoice_supplier' AND ee2.fk_target = ".$invId."))";
			$sql .= " OR (EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee1";
			$sql .= " JOIN ".MAIN_DB_PREFIX."element_element ee2 ON ee2.fk_target = ee1.fk_target AND ee2.targettype = 'order_supplier'";
			$sql .= " WHERE ee1.fk_source = r.rowid AND ee1.sourcetype = 'reception' AND ee1.targettype = 'order_supplier'";
			$sql .= " AND ee2.sourcetype = 'invoice_supplier' AND ee2.fk_source = ".$invId."))";
			$sql .= " )";
			$sql .= " ORDER BY r.rowid DESC";
			$sql .= $db->plimit(1, 0);
		} else {
			$sql = "SELECT e.date_expedition, e.date_delivery, e.fk_incoterms,";
			$sql .= " i.code AS inv_code, s.code AS ship_code";
			$sql .= " FROM ".MAIN_DB_PREFIX."expedition AS e";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_incoterms AS i ON i.rowid = ".((int) $object->fk_incoterms);
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_incoterms AS s ON s.rowid = e.fk_incoterms";
			$sql .= " WHERE e.entity IN (0, ".((int) $conf->entity).")";
			$sql .= " AND (";
			$sql .= " (EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee WHERE ee.fk_source = ".$invId." AND ee.sourcetype = 'facture' AND ee.targettype = 'shipping' AND ee.fk_target = e.rowid))";
			$sql .= " OR (EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."element_element ee WHERE ee.fk_target = ".$invId." AND ee.targettype = 'facture' AND ee.sourcetype = 'shipping' AND ee.fk_source = e.rowid))";
			$sql .= " )";
			$sql .= " ORDER BY e.rowid DESC";
			$sql .= $db->plimit(1, 0);
		}

		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__." err=".$db->lasterror, LOG_ERR);
			return null;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (!$obj) {
			return null;
		}

		$code = strtoupper(trim((string) ($obj->inv_code !== '' && $obj->inv_code !== null ? $obj->inv_code : $obj->ship_code)));
		if ($code === '') {
			return null;
		}

		if ($isSupplier) {
			$receptionTs = !empty($obj->date_reception) ? strtotime((string) $obj->date_reception) : 0;
			$deliveryTs = !empty($obj->date_delivery) ? strtotime((string) $obj->date_delivery) : 0;
			if (substr($code, 0, 1) === 'D') {
				$ts = ($deliveryTs > 0) ? $deliveryTs : $receptionTs;
				$mode = 'delivery';
			} else {
				// E/F/C: seller's loading date is unknown — actual reception is the proxy.
				$ts = ($receptionTs > 0) ? $receptionTs : $deliveryTs;
				$mode = 'shipment';
			}
		} else {
			$shipTs = !empty($obj->date_expedition) ? strtotime((string) $obj->date_expedition) : 0;
			$deliveryTs = !empty($obj->date_delivery) ? strtotime((string) $obj->date_delivery) : 0;
			if (substr($code, 0, 1) === 'D') {
				$ts = ($deliveryTs > 0) ? $deliveryTs : $shipTs;
				$mode = 'delivery';
			} else {
				$ts = $shipTs;
				$mode = 'shipment';
			}
		}
		if ($ts <= 0) {
			return null;
		}
		return array('ts' => (int) $ts, 'mode' => $mode, 'code' => $code);
	}

	/**
	 * Posted bookkeeping rows of the document, grouped by piece_num.
	 *
	 * @param  DoliDB  $db
	 * @param  string  $docType
	 * @param  int     $fkDoc
	 * @return array<int,array{piece_num:int,doc_date:string,code_journal:string,lines:array<int,object>,debit:float,credit:float}>
	 */
	public static function postedEntries($db, $docType, $fkDoc)
	{
		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

		$bk = new BookKeeping($db);
		$filter = "(t.doc_type:=:'".$db->escape($docType)."') AND (t.fk_doc:=:".((int) $fkDoc).")";
		$bk->fetchAll('ASC,ASC', 'piece_num,rowid', 0, 0, $filter);
		$lines = is_array($bk->lines) ? $bk->lines : array();

		$groups = array();
		foreach ($lines as $l) {
			$pn = (int) $l->piece_num;
			if (!isset($groups[$pn])) {
				$groups[$pn] = array(
					'piece_num' => $pn,
					'doc_date' => isset($l->doc_date) ? dol_print_date($l->doc_date, 'day') : '',
					'code_journal' => isset($l->code_journal) ? (string) $l->code_journal : '',
					'lines' => array(),
					'debit' => 0.0,
					'credit' => 0.0,
				);
			}
			$groups[$pn]['lines'][] = $l;
			$groups[$pn]['debit'] += (float) $l->debit;
			$groups[$pn]['credit'] += (float) $l->credit;
		}
		ksort($groups);
		return $groups;
	}

	/**
	 * Batch label lookup: ONE query for N account numbers.
	 *
	 * @param  DoliDB            $db
	 * @param  array<int,string> $numbers
	 * @param  int               $entity
	 * @return array<string,string> number => label
	 */
	private static function labelsForAccounts($db, $numbers, $entity)
	{
		$out = array();
		$clean = array();
		foreach ($numbers as $n) {
			$n = trim((string) $n);
			if ($n !== '') {
				$clean[$db->escape($n)] = $n;
			}
		}
		if (empty($clean)) {
			return $out;
		}
		$sql = "SELECT a.account_number, a.label FROM ".MAIN_DB_PREFIX."accounting_account AS a";
		$sql .= " WHERE a.account_number IN ('".implode("','", array_keys($clean))."')";
		$sql .= " AND a.active = 1 AND a.entity IN (0, ".((int) $entity).")";
		$resql = $db->query($sql);
		if (!$resql) {
			return $out;
		}
		while ($obj = $db->fetch_object($resql)) {
			$out[(string) $obj->account_number] = (string) $obj->label;
		}
		$db->free($resql);
		return $out;
	}
}

} // if (!class_exists('EBKTabData', false))
