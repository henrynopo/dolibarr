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
	 * Per-line account map for one invoice / expense report. Builds rows from
	 * the $object->lines array (already fetched by core with correct column
	 * names) — no manual SQL on the detail tables. The bound account is loaded
	 * via a single batched JOIN on fk_code_ventilation; suggested accounts
	 * are resolved via the same AccountingAccount::getAccountingCodeToBind()
	 * that the core ventilation pages use.
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
		$buySide = $isSupplier || $isExpense;

		// Chart of accounts rowid in llx_accounting_system (empty = chart not configured → no suggestions)
		$chartaccountcode = dol_getIdFromCode($db, getDolGlobalString('CHARTOFACCOUNTS'), 'accounting_system', 'rowid', 'pcg_version');

		// Collect line rowids for a single batched JOIN to get bound account numbers.
		$lineIds = array();
		$lineIndex = array(); // rowid => array key
		$idx = 0;
		foreach ($object->lines as $line) {
			$lid = (int) ($line->rowid ?? $line->id ?? 0);
			if ($lid > 0) {
				$lineIds[] = $lid;
				$lineIndex[$lid] = $idx;
			}
			$idx++;
		}

		// Batched lookup of bound accounts: one query for all lines, keyed by rowid.
		$boundAccounts = array(); // rowid => array(number, label)
		if (!empty($lineIds)) {
			$table = $isExpense ? 'expensereport_det' : ($isSupplier ? 'facture_fourn_det' : 'facturedet');
			$sql = "SELECT d.rowid, ab.account_number, ab.label";
			$sql .= " FROM ".MAIN_DB_PREFIX.$table." AS d";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."accounting_account AS ab ON ab.rowid = d.fk_code_ventilation AND ab.active = 1 AND ab.entity IN (0, ".$entity.")";
			$sql .= " WHERE d.rowid IN (".implode(',', $lineIds).")";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$boundAccounts[(int) $obj->rowid] = array(
						'number' => ($obj->account_number ?? ''),
						'label'  => ($obj->label ?? ''),
					);
				}
				$db->free($resql);
			}
		}

		// --- Build row arrays from $object->lines (core-fetched, correct column names) ---
		$rows = array();
		foreach ($object->lines as $line) {
			$lid = (int) ($line->rowid ?? $line->id ?? 0);
			if ($lid <= 0) {
				continue;
			}

			// Description: expense uses 'comments', invoices use 'description'
			$description = '';
			if ($isExpense) {
				$description = (string) ($line->comments ?? '');
			} else {
				$description = (string) ($line->description ?? '');
			}

			$bound = isset($boundAccounts[$lid]) ? $boundAccounts[$lid] : array('number' => '', 'label' => '');

			// Read tax amounts from the line using property_exists (graceful for missing columns)
			// Core InvoiceLine/SupplierInvoiceLine have: total_tva, total_localtax1, total_localtax2
			// Core ExpenseReport line has: total_tva, total_localtax1, total_localtax2
			$totalTva      = property_exists($line, 'total_tva')      ? (float) $line->total_tva      : 0.0;
			$totalLocalTax1 = property_exists($line, 'total_localtax1') ? (float) $line->total_localtax1 : 0.0;
			$totalLocalTax2 = property_exists($line, 'total_localtax2') ? (float) $line->total_localtax2 : 0.0;
			$totalHt       = property_exists($line, 'total_ht')        ? (float) $line->total_ht        : 0.0;
			$totalTtc      = property_exists($line, 'total_ttc')       ? (float) $line->total_ttc       : 0.0;
			$qty           = property_exists($line, 'qty')              ? (float) $line->qty              : 0.0;
			$tvaTx         = property_exists($line, 'tva_tx')          ? (float) $line->tva_tx          : 0.0;
			$vatSrcCode    = property_exists($line, 'vat_src_code')    ? (string) $line->vat_src_code   : '';
			$infoBits      = property_exists($line, 'info_bits')       ? (int) $line->info_bits        : 0;
			$fkProduct     = property_exists($line, 'fk_product')      ? (int) $line->fk_product        : 0;
			$productType   = property_exists($line, 'product_type')    ? (int) $line->product_type      : 0;
			$fkTypeFees    = property_exists($line, 'fk_c_type_fees')  ? (int) $line->fk_c_type_fees  : 0;

			$row = array(
				'rowid'            => $lid,
				'fk_product'       => $fkProduct,
				'fk_c_type_fees'   => $fkTypeFees,
				'product_type'     => $productType,
				'description'      => $description,
				'qty'              => $qty,
				'total_ht'         => $totalHt,
				'total_tva'        => $totalTva,
				'total_localtax1'  => $totalLocalTax1,
				'total_localtax2'  => $totalLocalTax2,
				'total_ttc'        => $totalTtc,
				'tva_tx'           => $tvaTx,
				'vat_src_code'     => $vatSrcCode,
				'npr'              => ($infoBits & 1) === 1,
				'bound_number'     => $bound['number'],
				'bound_label'      => $bound['label'],
				'suggested_number' => '',
				'suggested_label'  => '',
				'status'           => 'missing',
			);

			if (!empty($row['bound_number'])) {
				$row['status'] = 'bound';
			} elseif (!$isExpense && !empty($chartaccountcode)) {
				// Resolve suggested account via the CORE resolver.
				$product = new Product($db);
				$product->id = $fkProduct;
				$product->product_type = $productType;

				$codeCol = $buySide ? 'accountancy_code_buy' : 'accountancy_code_sell';
				if ($fkProduct > 0 && property_exists($line, $codeCol)) {
					$product->{$codeCol}          = (string) ($line->{$codeCol} ?? '');
					$product->{$codeCol.'_intra'} = (string) ($line->{$codeCol.'_intra'} ?? '');
					$product->{$codeCol.'_export'} = (string) ($line->{$codeCol.'_export'} ?? '');
				}

				$factureLite = $isSupplier ? new FactureFournisseur($db) : new Facture($db);
				$factureLite->id = (int) $object->id;
				$factureLite->type = isset($object->type) ? (int) $object->type : 0;
				if (!empty($object->fk_facture_source)) {
					$factureLite->fk_facture_source = (int) $object->fk_facture_source;
				}
				$lineLite = $isSupplier ? new SupplierInvoiceLine($db) : new FactureLigne($db);
				$lineLite->desc = $description;
				$lineLite->product_type = $productType;

				$thirdparty = is_object($object->thirdparty) ? $object->thirdparty : null;
				$buyer  = new Societe($db);
				$seller = new Societe($db);
				if ($isSupplier) {
					$buyer->country_code  = $mysoc->country_code;
					$seller->country_code = (string) ($thirdparty ? $thirdparty->country_code : '');
				} else {
					$buyer->country_code  = (string) ($thirdparty ? $thirdparty->country_code : '');
					$seller->country_code = $mysoc->country_code;
				}
				$buyer->code_compta_product  = '';
				$seller->code_compta_product = (string) ($thirdparty && property_exists($thirdparty, $buySide ? 'accountancy_code_buy' : 'accountancy_code_sell')
					? ($buySide ? $thirdparty->accountancy_code_buy : $thirdparty->accountancy_code_sell) : '');

				$accountingAccount = array('dom' => 0, 'intra' => 0, 'export' => 0, 'thirdparty' => 0);

				$aaResolver = new AccountingAccount($db);
				$resolved = $aaResolver->getAccountingCodeToBind($buyer, $seller, $product, $factureLite, $lineLite, $accountingAccount, $isSupplier ? 'supplier' : 'customer');
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
		}

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
					$rows[$k]['suggested_label'] = $labels[$r['suggested_number']] ?? '';
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
	 * Tax amounts grouped by (account, tax_rate) key, one row per unique pair —
	 * the same logic the core journals use. Resolves VAT / LT1 / LT2 accounts
	 * individually from the tax dictionary, skipping any component whose amount is
	 * below the rounding threshold. NPR lines are excluded (their taxes travel
	 * with the product row in defaultDraft()).
	 *
	 * Grouping key format: "account_number|rate[|ltN]"  (ltN suffix = localtax)
	 *
	 * @param  DoliDB  $db
	 * @param  array   $lineRows  Output of lineRows()
	 * @param  string  $docType
	 * @param  Societe $buyer
	 * @param  Societe $seller
	 * @return array<int,array{account:string,label:string,amount:float,label_operation:string}>
	 */
	public static function vatGroups($db, $lineRows, $docType, $buyer, $seller)
	{
		global $conf;

		$buySide = ($docType !== 'customer_invoice');

		// Per-localtax fallback accounts (same constants the core journals read)
		$lt1Fallback = $buySide ? 'ACCOUNTING_LT1_BUY_ACCOUNT' : 'ACCOUNTING_LT1_SOLD_ACCOUNT';
		$lt2Fallback = $buySide ? 'ACCOUNTING_LT2_BUY_ACCOUNT' : 'ACCOUNTING_LT2_SOLD_ACCOUNT';

		$vatCache = array(); // tax_key => account_number from getTaxesFromId()
		$groups   = array(); // "account|rate" => array(amount, label_operation)

		foreach ($lineRows as $line) {
			if (!empty($line['npr'])) {
				continue; // NPR: taxes travel with the product line
			}

			$taxKey = $line['tva_tx'].(!empty($line['vat_src_code']) ? ' ('.$line['vat_src_code'].')' : '');

			// --- VAT (total_tva) ---
			$vat = (float) $line['total_tva'];
			if (abs($vat) >= 0.005) {
				if (!isset($vatCache[$taxKey])) {
					$vatdata = getTaxesFromId($taxKey, $buyer, $seller, 0);
					$acct = '';
					if (is_array($vatdata) && empty($vatdata['error'])) {
						$acct = trim((string) ($buySide
							? ($vatdata['accountancy_code_buy'] ?? '')
							: ($vatdata['accountancy_code_sell'] ?? '')));
					}
					$vatCache[$taxKey] = $acct;
				}
				$acct = $vatCache[$taxKey];
				if ($acct !== '') {
					$gk = $acct.'|'.$line['tva_tx'];
					if (!isset($groups[$gk])) {
						$groups[$gk] = array('account' => $acct, 'amount' => 0.0, 'label_operation' => 'VAT '.$line['tva_tx'].'%');
					}
					$groups[$gk]['amount'] += $vat;
				}
			}

			// --- Localtax 1 (total_localtax1) ---
			$lt1 = (float) $line['total_localtax1'];
			if (abs($lt1) >= 0.005) {
				if (!isset($vatCache[$taxKey.'|lt1'])) {
					$vatdata = getTaxesFromId($taxKey, $buyer, $seller, 0);
					$acct = trim((string) ($vatdata['accountancy_code_buy'] ?? $vatdata['accountancy_code_sell'] ?? ''));
					if ($acct === '') {
						$acct = getDolGlobalString($lt1Fallback, '');
					}
					$vatCache[$taxKey.'|lt1'] = $acct;
				}
				$acct = $vatCache[$taxKey.'|lt1'];
				if ($acct !== '') {
					$gk = $acct.'|lt1|'.$line['tva_tx'];
					if (!isset($groups[$gk])) {
						$groups[$gk] = array('account' => $acct, 'amount' => 0.0, 'label_operation' => 'VAT '.$line['tva_tx'].'% LT1');
					}
					$groups[$gk]['amount'] += $lt1;
				}
			}

			// --- Localtax 2 (total_localtax2) ---
			$lt2 = (float) $line['total_localtax2'];
			if (abs($lt2) >= 0.005) {
				if (!isset($vatCache[$taxKey.'|lt2'])) {
					$vatdata = getTaxesFromId($taxKey, $buyer, $seller, 0);
					$acct = trim((string) ($vatdata['accountancy_code_buy'] ?? $vatdata['accountancy_code_sell'] ?? ''));
					if ($acct === '') {
						$acct = getDolGlobalString($lt2Fallback, '');
					}
					$vatCache[$taxKey.'|lt2'] = $acct;
				}
				$acct = $vatCache[$taxKey.'|lt2'];
				if ($acct !== '') {
					$gk = $acct.'|lt2|'.$line['tva_tx'];
					if (!isset($groups[$gk])) {
						$groups[$gk] = array('account' => $acct, 'amount' => 0.0, 'label_operation' => 'VAT '.$line['tva_tx'].'% LT2');
					}
					$groups[$gk]['amount'] += $lt2;
				}
			}
		}

		// Resolve account labels in one batch.
		$accountNumbers = array();
		foreach ($groups as $g) {
			$accountNumbers[$g['account']] = true;
		}
		$labels = self::labelsForAccounts($db, array_keys($accountNumbers), (int) $conf->entity);

		$out = array();
		foreach ($groups as $g) {
			$g['label'] = $labels[$g['account']] ?? '';
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

		// US/non-VAT-country mode: when the constant is set, taxes stay on the product
		// row instead of being dispatched to their own tax account rows. This mirrors
		// the same flag that the core journals respect (purchasesjournal /
		// expensereportsjournal). For customer invoices the flag does not exist in
		// core, so it is always false there.
		$doNotDispatchTaxes = false;
		if ($docType === 'expense_report') {
			$doNotDispatchTaxes = getDolGlobalInt('ACCOUNTING_EXPENSEREPORT_DO_NOT_DISPATCH_TAXES') === 1;
		} elseif ($docType === 'supplier_invoice') {
			$doNotDispatchTaxes = getDolGlobalInt('ACCOUNTING_PURCHASES_DO_NOT_DISPATCH_TAXES') === 1;
		}

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

		// 2. Per-line rows — in US/non-VAT mode taxes stay on the product row;
		//   otherwise only NPR lines carry their own taxes (non-NPR taxes go to vatGroups rows)
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
			if (!empty($line['npr']) || $doNotDispatchTaxes) {
				// NPR: taxes travel with product row; US mode: all taxes stay with product row
				$amount += (float) $line['total_tva']
					+ (float) $line['total_localtax1']
					+ (float) $line['total_localtax2'];
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

		// 3. Grouped VAT rows — skipped when US/non-VAT mode is active
		if (!$doNotDispatchTaxes) {
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
