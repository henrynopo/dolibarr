<?php
/* Copyright (C) 2025  Odoo Connector (Dolibarr)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

if (!$user->admin) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array('admin', 'odoo_connector@odooconnector'));

$action = GETPOST('action', 'aZ09');
$error = 0;

/*
 * Save settings
 * The token check matters here as much as on the other actions: a forged
 * cross-site post could repoint ODOO_CONNECTOR_URL at an attacker server,
 * and the hourly cron would then send it the Odoo API key.
 */
if ($action == 'update' && GETPOST('cancel', 'alpha') === '' && GETPOST('token', 'none') === newToken()) {
	$db->begin();
	// Empty password means "keep the stored one": the password input is never
	// prefilled with the stored value (it must not leak through the page source)
	$passwordVal = GETPOST('ODOO_CONNECTOR_PASSWORD', 'alphanohtml');
	if ($passwordVal === '') {
		$passwordVal = getDolGlobalString('ODOO_CONNECTOR_PASSWORD');
	}
	$consts = array(
		'ODOO_CONNECTOR_URL' => GETPOST('ODOO_CONNECTOR_URL', 'alphanohtml'),
		'ODOO_CONNECTOR_DB' => GETPOST('ODOO_CONNECTOR_DB', 'alphanohtml'),
		'ODOO_CONNECTOR_USER' => GETPOST('ODOO_CONNECTOR_USER', 'alphanohtml'),
		'ODOO_CONNECTOR_PASSWORD' => $passwordVal,
		'ODOO_CONNECTOR_COMPANY_ID' => (string) GETPOST('ODOO_CONNECTOR_COMPANY_ID', 'int'),
		'ODOO_CONNECTOR_TAX_ACCOUNT_ID' => (string) GETPOST('ODOO_CONNECTOR_TAX_ACCOUNT_ID', 'int'),
		'ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID' => (string) GETPOST('ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID', 'int'),
		'ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID' => (string) GETPOST('ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID', 'int'),
		'ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID' => (string) GETPOST('ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID', 'int'),
		'ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID' => (string) GETPOST('ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID', 'int'),
		'ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID' => (string) GETPOST('ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID', 'int'),
		'ODOO_CONNECTOR_SALES_JOURNAL_ID' => (string) GETPOST('ODOO_CONNECTOR_SALES_JOURNAL_ID', 'int'),
		'ODOO_CONNECTOR_PURCHASE_JOURNAL_ID' => (string) GETPOST('ODOO_CONNECTOR_PURCHASE_JOURNAL_ID', 'int'),
		'ODOO_CONNECTOR_EXPENSE_JOURNAL_ID' => (string) GETPOST('ODOO_CONNECTOR_EXPENSE_JOURNAL_ID', 'int'),
		'ODOO_CONNECTOR_SYNC_INVOICES' => (string) GETPOST('ODOO_CONNECTOR_SYNC_INVOICES', 'int'),
		'ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS' => (string) GETPOST('ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS', 'int'),
		'ODOO_CONNECTOR_SYNC_EXPENSES' => (string) GETPOST('ODOO_CONNECTOR_SYNC_EXPENSES', 'int'),
		'ODOO_CONNECTOR_ACCOUNTING_DATE_SOURCE' => GETPOST('ODOO_CONNECTOR_ACCOUNTING_DATE_SOURCE', 'alphanohtml'),
		'ODOO_CONNECTOR_DELIVERY_DATE_FIELD' => GETPOST('ODOO_CONNECTOR_DELIVERY_DATE_FIELD', 'alphanohtml'),
		'ODOO_CONNECTOR_DELIVERY_DATE_FIELD_CUSTOM' => GETPOST('ODOO_CONNECTOR_DELIVERY_DATE_FIELD_CUSTOM', 'alphanohtml'),
		'ODOO_CONNECTOR_SYNC_SHIPMENT_DATES' => (string) GETPOST('ODOO_CONNECTOR_SYNC_SHIPMENT_DATES', 'int'),
		'ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS' => (string) GETPOST('ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS', 'int'),
	);
	foreach ($consts as $key => $val) {
		if (dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity) < 0) {
			$error++;
			break;
		}
	}
	if (!$error) {
		$db->commit();
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($langs->trans('SetupNotSaved'), null, 'errors');
	}
	$action = '';
}

/*
 * Test connection: authenticate with posted credentials and fetch
 * companies / journals / accounts lists for the mapping dropdowns.
 */
if ($action == 'test_connection' && GETPOST('token', 'none') === newToken()) {
	$tUrl = GETPOST('ODOO_CONNECTOR_URL', 'alphanohtml');
	$tDb = GETPOST('ODOO_CONNECTOR_DB', 'alphanohtml');
	$tUser = GETPOST('ODOO_CONNECTOR_USER', 'alphanohtml');
	$tPass = GETPOST('ODOO_CONNECTOR_PASSWORD', 'alphanohtml');
	if ($tPass === '') {
		$tPass = getDolGlobalString('ODOO_CONNECTOR_PASSWORD'); // password input may be left empty
	}

	dol_include_once('odooconnector/class/OdooConnector.class.php');
	$conn = new OdooConnector($tUrl, $tDb, $tUser, $tPass);
	$uid = $conn->authenticate();
	if ($uid === false) {
		setEventMessages($langs->trans('OdooConnectorTestFailed', dol_escape_htmltag($conn->error)), null, 'errors');
	} else {
		$companies = $conn->searchRead('res.company', array(), array('id', 'name', 'currency_id'));
		$journals = $conn->searchRead('account.journal', array(array('type', 'in', array('sale', 'purchase', 'general'))), array('id', 'name', 'type', 'company_id'), 100);
		// Note: 'deprecated' and 'company_id' were removed from account.account in Odoo 19,
		// the company relation is the many2many 'company_ids'
		$accounts = $conn->searchRead('account.account', array(), array('id', 'code', 'name', 'account_type', 'company_ids'), 500);
		if ($companies === false || $journals === false || $accounts === false) {
			setEventMessages($langs->trans('OdooConnectorTestFailed', dol_escape_htmltag($conn->error)), null, 'errors');
		} else {
			$_SESSION['ODOO_CONNECTOR_COMPANIES'] = $companies;
			$_SESSION['ODOO_CONNECTOR_JOURNALS'] = is_array($journals) ? $journals : array();
			$_SESSION['ODOO_CONNECTOR_ACCOUNTS'] = is_array($accounts) ? $accounts : array();
			setEventMessages($langs->trans('OdooConnectorTestOk', $uid), null, 'mesgs');
		}
	}
	$action = '';
}

/*
 * Sync now (manual run)
 */
if ($action == 'sync_now' && GETPOST('token', 'none') === newToken()) {
	$syncFrom = GETPOST('sync_from', 'alphanohtml');
	$syncTo = GETPOST('sync_to', 'alphanohtml');
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $syncFrom)) {
		$syncFrom = '';
	}
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $syncTo)) {
		$syncTo = '';
	}
	// Which Dolibarr date column the range applies to, and which entity types to sync
	$syncField = GETPOST('sync_date_field', 'aZ09');
	if (!in_array($syncField, array('invoice_date', 'due_date', 'creation', 'last_update'))) {
		$syncField = 'invoice_date';
	}
	$syncOnly = GETPOST('sync_only', 'aZ09');
	if (!in_array($syncOnly, array('', 'invoices', 'bills', 'expenses'))) {
		$syncOnly = '';
	}
	dol_include_once('odooconnector/class/OdooSync.class.php');
	$sync = new OdooSync($db);
	$sync->runSync($syncFrom, $syncTo, $syncField, $syncOnly);
	// The output embeds Odoo error strings (which contain user-controlled names):
	// escape it, setEventMessages renders its content as raw HTML
	$msg = nl2br(dol_escape_htmltag($sync->output));
	if ($sync->result > 0) {
		setEventMessages($msg, null, 'mesgs');
	} elseif (!empty($msg)) {
		setEventMessages($msg, null, 'warnings');
	} else {
		setEventMessages($langs->trans('OdooConnectorSyncDone'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

/*
 * Failure log: dismiss one entry, or clear all (after the documents were
 * checked / fixed manually in Odoo).
 */
if (GETPOST('token', 'none') === newToken() && in_array($action, array('del_synclog', 'clear_synclog'))) {
	$sqllog = 'DELETE FROM '.MAIN_DB_PREFIX.'odoo_connector_synclog WHERE entity = '.((int) $conf->entity);
	if ($action == 'del_synclog') {
		$sqllog .= ' AND rowid = '.((int) GETPOST('rowid', 'int'));
	}
	$db->query($sqllog);
	setEventMessages($langs->trans('OdooConnectorSyncLogCleared'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

$form = new Form($db);

// Lists fetched by "Test connection" (kept in session), used for mapping dropdowns
$odooCompanies = isset($_SESSION['ODOO_CONNECTOR_COMPANIES']) ? $_SESSION['ODOO_CONNECTOR_COMPANIES'] : null;
$odooJournals = isset($_SESSION['ODOO_CONNECTOR_JOURNALS']) ? $_SESSION['ODOO_CONNECTOR_JOURNALS'] : null;
$odooAccounts = isset($_SESSION['ODOO_CONNECTOR_ACCOUNTS']) ? $_SESSION['ODOO_CONNECTOR_ACCOUNTS'] : null;
if (is_array($odooAccounts)) {
	usort($odooAccounts, function ($a, $b) {
		return strcmp($a['code'], $b['code']);
	});
}

// Map company id => name (from the companies fetched by "Test connection"),
// used to label accounts whose only company relation is the company_ids m2m
$odooCompanyNames = array();
if (is_array($odooCompanies)) {
	foreach ($odooCompanies as $c) {
		$odooCompanyNames[(int) $c['id']] = $c['name'];
	}
}

// Dolibarr entity label, to show the entity <-> Odoo company mapping
$entityLabel = getDolGlobalString('MAIN_INFO_SOCIETE_NOM');

/**
 * Form value: posted value first (re-render after test), saved const otherwise.
 *
 * @param string $key      Const name
 * @param string $fallback Saved value
 * @return string
 */
function odoo_form_val($key, $fallback)
{
	return GETPOSTISSET($key) ? GETPOST($key, 'alphanohtml') : $fallback;
}

/**
 * Print an Odoo account dropdown: only accounts of the expected type are offered
 * (suggestion by document type), all accounts as fallback when none match.
 *
 * @param string $name       Select name
 * @param int    $selected   Selected account id
 * @param array  $accounts   Accounts fetched by "Test connection" (may be null)
 * @param string $typePrefix Odoo account_type prefix (income, expense, liability), '' = no filter
 * @param string $autoLabel  Label of the empty option
 * @param array  $companies  Odoo company id => name map, to label shared accounts
 * @return void
 */
function odoo_account_select($name, $selected, $accounts, $typePrefix, $autoLabel, $companies = array())
{
	if (!is_array($accounts) || count($accounts) === 0) {
		print '<input type="text" name="'.$name.'" value="'.dol_escape_htmltag((string) ($selected ?: '')).'" class="minwidth100">';
		return;
	}
	print '<select name="'.$name.'" class="flat minwidth400">';
	print '<option value="">'.$autoLabel.'</option>';
	$filtered = array();
	foreach ($accounts as $a) {
		if ($typePrefix === '' || (isset($a['account_type']) && strpos($a['account_type'], $typePrefix) === 0)) {
			$filtered[] = $a;
		}
	}
	if (empty($filtered)) {
		$filtered = $accounts; // no account of the expected type: show all
	}
	foreach ($filtered as $a) {
		$aid = (int) $a['id'];
		$acompany = '';
		if (isset($a['company_ids']) && is_array($a['company_ids']) && count($a['company_ids']) > 0) {
			$cid = (int) $a['company_ids'][0];
			$acompany = isset($companies[$cid]) ? $companies[$cid] : (string) $cid;
			if (count($a['company_ids']) > 1) {
				$acompany .= ' +'.(count($a['company_ids']) - 1);
			}
		}
		$label = dol_escape_htmltag(trim(($a['code'] ? $a['code'].' - ' : '').$a['name'])).($acompany !== '' ? ' ('.dol_escape_htmltag($acompany).')' : '');
		print '<option value="'.$aid.'"'.($selected === $aid ? ' selected' : '').'>'.$label.'</option>';
	}
	print '</select>';
}

llxHeader('', $langs->trans('OdooConnectorSetup'), '', '', 0, 0, '', '', '', 'page-odoo-connector-setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('OdooConnectorSetup'), $linkback, 'title_setup');

print '<form action="'.$_SERVER['PHP_SELF'].'" method="post">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update" id="odoo_form_action">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OdooConnectorConnection').'</td></tr>';
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('OdooConnectorURL').'</td><td><input type="text" name="ODOO_CONNECTOR_URL" value="'.dol_escape_htmltag(odoo_form_val('ODOO_CONNECTOR_URL', getDolGlobalString('ODOO_CONNECTOR_URL'))).'" class="minwidth400" placeholder="https://yourcompany.odoo.com"></td></tr>';
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('OdooConnectorDB').'</td><td><input type="text" name="ODOO_CONNECTOR_DB" value="'.dol_escape_htmltag(odoo_form_val('ODOO_CONNECTOR_DB', getDolGlobalString('ODOO_CONNECTOR_DB'))).'" class="minwidth200" placeholder="yourcompany"></td></tr>';
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('OdooConnectorUser').'</td><td><input type="text" name="ODOO_CONNECTOR_USER" value="'.dol_escape_htmltag(odoo_form_val('ODOO_CONNECTOR_USER', getDolGlobalString('ODOO_CONNECTOR_USER'))).'" class="minwidth200"></td></tr>';
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('OdooConnectorApiKey').'</td><td><input type="password" name="ODOO_CONNECTOR_PASSWORD" value="" class="minwidth200" autocomplete="new-password"'.(getDolGlobalString('ODOO_CONNECTOR_PASSWORD') !== '' ? ' placeholder="'.$langs->trans('OdooConnectorApiKeyKeep').'"' : '').'><br><span class="opacitymedium">'.$langs->trans('OdooConnectorApiKeyHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorTestConnection').'</td><td><input type="submit" class="button" id="odoo_test_connection" value="'.$langs->trans('OdooConnectorTestConnection').'"></td></tr>';
print '</table></div>';

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OdooConnectorAccounting').'</td></tr>';
$entityName = ((int) $conf->entity).($entityLabel !== '' ? ' ('.dol_escape_htmltag($entityLabel).')' : '');
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorEntityMapping').'</td><td>';
print $langs->trans('OdooConnectorEntityMappingHelp', $entityName);
print '</td></tr>';
$companySel = (int) odoo_form_val('ODOO_CONNECTOR_COMPANY_ID', (string) (getDolGlobalInt('ODOO_CONNECTOR_COMPANY_ID') ?: 1));
print '<tr class="oddeven"><td class="fieldrequired">'.$langs->trans('OdooConnectorCompany').'</td><td>';
if (is_array($odooCompanies) && count($odooCompanies) > 0) {
	print '<select name="ODOO_CONNECTOR_COMPANY_ID" class="flat minwidth300">';
	foreach ($odooCompanies as $c) {
		$cid = (int) $c['id'];
		$cur = isset($c['currency_id']) && is_array($c['currency_id']) ? $c['currency_id'][1] : '';
		$label = '#'.$cid.' '.dol_escape_htmltag($c['name']).($cur !== '' ? ' ('.dol_escape_htmltag($cur).')' : '');
		print '<option value="'.$cid.'"'.($companySel === $cid ? ' selected' : '').'>'.$label.'</option>';
	}
	print '</select>';
} else {
	print '<input type="text" name="ODOO_CONNECTOR_COMPANY_ID" value="'.dol_escape_htmltag((string) ($companySel ?: 1)).'" class="minwidth100">';
}
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorCompanyHelp').'</span></td></tr>';
$taxAccountSel = (int) odoo_form_val('ODOO_CONNECTOR_TAX_ACCOUNT_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_TAX_ACCOUNT_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorTaxAccount').'</td><td>';
odoo_account_select('ODOO_CONNECTOR_TAX_ACCOUNT_ID', $taxAccountSel, $odooAccounts, '', $langs->trans('OdooConnectorNoTaxSplit'), $odooCompanyNames);
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorTaxAccountHelp').'</span></td></tr>';
$revenueAccountSel = (int) odoo_form_val('ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorRevenueAccount').'</td><td>';
odoo_account_select('ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID', $revenueAccountSel, $odooAccounts, 'income', $langs->trans('OdooConnectorAutoAccount'), $odooCompanyNames);
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorRevenueAccountHelp').'</span></td></tr>';
$depositAccountSel = (int) odoo_form_val('ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorDepositAccount').'</td><td>';
odoo_account_select('ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID', $depositAccountSel, $odooAccounts, 'liability', $langs->trans('OdooConnectorAutoAccount'), $odooCompanyNames);
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorDepositAccountHelp').'</span></td></tr>';
$billAccountSel = (int) odoo_form_val('ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorBillExpenseAccount').'</td><td>';
odoo_account_select('ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID', $billAccountSel, $odooAccounts, 'expense', $langs->trans('OdooConnectorAutoAccount'), $odooCompanyNames);
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorBillExpenseAccountHelp').'</span></td></tr>';
$supplierDepositAccountSel = (int) odoo_form_val('ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSupplierDepositAccount').'</td><td>';
odoo_account_select('ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID', $supplierDepositAccountSel, $odooAccounts, 'asset', $langs->trans('OdooConnectorAutoAccount'), $odooCompanyNames);
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorSupplierDepositAccountHelp').'</span></td></tr>';
$expenseAccountSel = (int) odoo_form_val('ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorExpenseAccount').'</td><td>';
odoo_account_select('ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID', $expenseAccountSel, $odooAccounts, 'expense', $langs->trans('OdooConnectorAutoAccount'), $odooCompanyNames);
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorExpenseAccountHelp').'</span></td></tr>';
$salesJournalSel = (int) odoo_form_val('ODOO_CONNECTOR_SALES_JOURNAL_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_SALES_JOURNAL_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSalesJournal').'</td><td>';
if (is_array($odooJournals) && count($odooJournals) > 0) {
	print '<select name="ODOO_CONNECTOR_SALES_JOURNAL_ID" class="flat minwidth400">';
	print '<option value="">'.$langs->trans('OdooConnectorAuto').'</option>';
	foreach ($odooJournals as $j) {
		if ($j['type'] !== 'sale') {
			continue;
		}
		$jid = (int) $j['id'];
		$jcompany = isset($j['company_id']) && is_array($j['company_id']) ? $j['company_id'][1] : '';
		$label = '#'.$jid.' '.dol_escape_htmltag($j['name']).($jcompany !== '' ? ' ('.dol_escape_htmltag($jcompany).')' : '');
		print '<option value="'.$jid.'"'.($salesJournalSel === $jid ? ' selected' : '').'>'.$label.'</option>';
	}
	print '</select>';
} else {
	print '<input type="text" name="ODOO_CONNECTOR_SALES_JOURNAL_ID" value="'.dol_escape_htmltag((string) ($salesJournalSel ?: '')).'" class="minwidth100">';
}
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorJournalAutoHelp').'</span></td></tr>';
$purchaseJournalSel = (int) odoo_form_val('ODOO_CONNECTOR_PURCHASE_JOURNAL_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_PURCHASE_JOURNAL_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorPurchaseJournal').'</td><td>';
if (is_array($odooJournals) && count($odooJournals) > 0) {
	print '<select name="ODOO_CONNECTOR_PURCHASE_JOURNAL_ID" class="flat minwidth400">';
	print '<option value="">'.$langs->trans('OdooConnectorAuto').'</option>';
	foreach ($odooJournals as $j) {
		if ($j['type'] !== 'purchase') {
			continue;
		}
		$jid = (int) $j['id'];
		$jcompany = isset($j['company_id']) && is_array($j['company_id']) ? $j['company_id'][1] : '';
		$label = '#'.$jid.' '.dol_escape_htmltag($j['name']).($jcompany !== '' ? ' ('.dol_escape_htmltag($jcompany).')' : '');
		print '<option value="'.$jid.'"'.($purchaseJournalSel === $jid ? ' selected' : '').'>'.$label.'</option>';
	}
	print '</select>';
} else {
	print '<input type="text" name="ODOO_CONNECTOR_PURCHASE_JOURNAL_ID" value="'.dol_escape_htmltag((string) ($purchaseJournalSel ?: '')).'" class="minwidth100">';
}
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorJournalAutoHelp').'</span></td></tr>';
$expenseJournalSel = (int) odoo_form_val('ODOO_CONNECTOR_EXPENSE_JOURNAL_ID', (string) getDolGlobalInt('ODOO_CONNECTOR_EXPENSE_JOURNAL_ID'));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorExpenseJournal').'</td><td>';
if (is_array($odooJournals) && count($odooJournals) > 0) {
	print '<select name="ODOO_CONNECTOR_EXPENSE_JOURNAL_ID" class="flat minwidth400">';
	print '<option value="">'.$langs->trans('OdooConnectorAuto').'</option>';
	foreach ($odooJournals as $j) {
		if ($j['type'] !== 'general') {
			continue;
		}
		$jid = (int) $j['id'];
		$jcompany = isset($j['company_id']) && is_array($j['company_id']) ? $j['company_id'][1] : '';
		$label = '#'.$jid.' '.dol_escape_htmltag($j['name']).($jcompany !== '' ? ' ('.dol_escape_htmltag($jcompany).')' : '');
		print '<option value="'.$jid.'"'.($expenseJournalSel === $jid ? ' selected' : '').'>'.$label.'</option>';
	}
	print '</select>';
} else {
	print '<input type="text" name="ODOO_CONNECTOR_EXPENSE_JOURNAL_ID" value="'.dol_escape_htmltag((string) ($expenseJournalSel ?: '')).'" class="minwidth100">';
}
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorExpenseJournalHelp').'</span></td></tr>';
print '</table></div>';

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OdooConnectorSyncOptions').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncInvoices').'</td><td>'.$form->selectyesno('ODOO_CONNECTOR_SYNC_INVOICES', getDolGlobalInt('ODOO_CONNECTOR_SYNC_INVOICES', 1), 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncSupplierBills').'</td><td>'.$form->selectyesno('ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS', getDolGlobalInt('ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS', 1), 1).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncExpenses').'</td><td>'.$form->selectyesno('ODOO_CONNECTOR_SYNC_EXPENSES', getDolGlobalInt('ODOO_CONNECTOR_SYNC_EXPENSES', 1), 1).'</td></tr>';
print '</table></div>';

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OdooConnectorAccountingDate').'</td></tr>';
$src = getDolGlobalString('ODOO_CONNECTOR_ACCOUNTING_DATE_SOURCE') ?: 'invoice_date';
$srcOptions = array(
	'invoice_date' => $langs->trans('OdooConnectorAccountingDateInvoice'),
	'due_date' => $langs->trans('OdooConnectorAccountingDateDue'),
	'delivery_reception' => $langs->trans('OdooConnectorAccountingDateDelivery'),
);
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorAccountingDateSource').'</td><td>';
print '<select name="ODOO_CONNECTOR_ACCOUNTING_DATE_SOURCE" class="flat">';
foreach ($srcOptions as $k => $label) {
	print '<option value="'.dol_escape_htmltag($k).'"'.($src === $k ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
}
print '</select></td></tr>';
$deliveryField = getDolGlobalString('ODOO_CONNECTOR_DELIVERY_DATE_FIELD');
$deliveryFieldCustom = getDolGlobalString('ODOO_CONNECTOR_DELIVERY_DATE_FIELD_CUSTOM');
$deliveryOptions = array(
	'' => $langs->trans('OdooConnectorDeliveryDateDefault'),
	'ata' => $langs->trans('OdooConnectorDeliveryDateATA'),
	'eta' => $langs->trans('OdooConnectorDeliveryDateETA'),
	'atd' => $langs->trans('OdooConnectorDeliveryDateATD'),
	'etd' => $langs->trans('OdooConnectorDeliveryDateETD'),
	'updatedtime' => $langs->trans('OdooConnectorDeliveryDateUpdatedTime'),
	'other' => $langs->trans('OdooConnectorDeliveryDateOther'),
);
if (!isset($deliveryOptions[$deliveryField])) {
	$deliveryField = '';
}
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorDeliveryDateField').'</td><td>';
print '<select name="ODOO_CONNECTOR_DELIVERY_DATE_FIELD" id="odoo_delivery_date_field" class="flat">';
foreach ($deliveryOptions as $k => $label) {
	print '<option value="'.dol_escape_htmltag($k).'"'.($deliveryField === $k ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print ' <span id="odoo_delivery_custom_wrap" style="'.($deliveryField === 'other' ? '' : 'display:none').'">';
print '<input type="text" name="ODOO_CONNECTOR_DELIVERY_DATE_FIELD_CUSTOM" value="'.dol_escape_htmltag($deliveryFieldCustom).'" class="minwidth150" placeholder="e.g. mydate">';
print '</span>';
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorDeliveryDateFieldHelp').'</span></td></tr>';
$scanHours = (int) odoo_form_val('ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS', (string) (getDolGlobalInt('ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS') ?: 25));
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncShipmentDates').'</td><td>'.$form->selectyesno('ODOO_CONNECTOR_SYNC_SHIPMENT_DATES', getDolGlobalInt('ODOO_CONNECTOR_SYNC_SHIPMENT_DATES', 1), 1).'<br><span class="opacitymedium">'.$langs->trans('OdooConnectorSyncShipmentDatesHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorShipmentDateScanHours').'</td><td><input type="text" name="ODOO_CONNECTOR_SHIPMENT_DATE_SCAN_HOURS" value="'.dol_escape_htmltag((string) $scanHours).'" class="minwidth100"><br><span class="opacitymedium">'.$langs->trans('OdooConnectorShipmentDateScanHoursHelp').'</span></td></tr>';
print '</table></div>';
print '<script type="text/javascript">';
print 'document.getElementById("odoo_delivery_date_field").onchange=function(){ var w=document.getElementById("odoo_delivery_custom_wrap"); w.style.display=this.value==="other"?"":"none"; };';
print 'document.getElementById("odoo_test_connection").onclick=function(){ document.getElementById("odoo_form_action").value="test_connection"; };';
print '</script>';

print '<br><div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div></form>';

// Manual sync, with an optional document date range (the cron job always syncs everything)
print '<br><form action="'.$_SERVER['PHP_SELF'].'" method="post">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="sync_now">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OdooConnectorManualSync').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncDateField').'</td><td>';
print '<select name="sync_date_field" class="flat minwidth200">';
$dateFieldOptions = array(
	'invoice_date' => $langs->trans('OdooConnectorSyncDateFieldInvoice'),
	'due_date' => $langs->trans('OdooConnectorSyncDateFieldDue'),
	'creation' => $langs->trans('OdooConnectorSyncDateFieldCreation'),
	'last_update' => $langs->trans('OdooConnectorSyncDateFieldUpdate'),
);
foreach ($dateFieldOptions as $k => $label) {
	print '<option value="'.dol_escape_htmltag($k).'"'.($k === 'invoice_date' ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
}
print '</select></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncDateRange').'</td><td>';
print '<input type="date" name="sync_from" class="flat"> &ndash; <input type="date" name="sync_to" class="flat">';
print '<br><span class="opacitymedium">'.$langs->trans('OdooConnectorSyncRangeHelp').'</span></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('OdooConnectorSyncOnly').'</td><td>';
print '<select name="sync_only" class="flat minwidth200">';
$onlyOptions = array(
	'' => $langs->trans('OdooConnectorSyncOnlyAll'),
	'invoices' => $langs->trans('OdooConnectorSyncInvoices'),
	'bills' => $langs->trans('OdooConnectorSyncSupplierBills'),
	'expenses' => $langs->trans('OdooConnectorSyncExpenses'),
);
foreach ($onlyOptions as $k => $label) {
	print '<option value="'.dol_escape_htmltag($k).'"'.($k === '' ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
}
print '</select></td></tr>';
print '<tr class="oddeven"><td class="center" colspan="2"><input type="submit" class="button" value="'.$langs->trans('OdooConnectorSyncNow').'"></td></tr>';
print '</table></div></form>';

// Sync failure log: documents whose last push attempt failed. Fix the cause in
// Dolibarr or Odoo then run sync again (a successful retry clears the entry
// automatically); entries handled manually in Odoo can be dismissed here.
$sqllog = 'SELECT rowid, element_type, fk_source_id, ref, odoo_id, action, error, date_try';
$sqllog .= ' FROM '.MAIN_DB_PREFIX.'odoo_connector_synclog';
$sqllog .= ' WHERE entity = '.((int) $conf->entity);
$sqllog .= ' ORDER BY date_try DESC, rowid DESC LIMIT 200';
$reslog = $db->query($sqllog);

print '<br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('OdooConnectorSyncLog').'</td><td class="right">';
print '<form action="'.$_SERVER['PHP_SELF'].'" method="post" style="display:inline">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="clear_synclog">';
if ($reslog !== false && $db->num_rows($reslog) > 0) {
	print '<input type="submit" class="button small" value="'.$langs->trans('OdooConnectorClearSyncLog').'">';
}
print '</form></td></tr>';
if ($reslog === false) {
	print '<tr class="oddeven"><td colspan="2">'.$langs->trans('OdooConnectorSyncLogTableMissing').'</td></tr>';
} elseif ($db->num_rows($reslog) == 0) {
	print '<tr class="oddeven"><td colspan="2">'.$langs->trans('OdooConnectorNoSyncFailures').'</td></tr>';
} else {
	$elementTypeLabels = array(
		'facture' => $langs->trans('OdooConnectorSyncInvoices'),
		'facture_fourn' => $langs->trans('OdooConnectorSyncSupplierBills'),
		'expensereport' => $langs->trans('OdooConnectorSyncExpenses'),
	);
	$actionLabels = array(
		'create' => 'create',
		'update' => 'update',
		'delete' => 'delete',
		'partner' => 'partner',
		'currency' => 'currency',
		'journal' => 'journal',
		'update_posted' => $langs->trans('OdooConnectorActionPosted'),
		'duplicate' => $langs->trans('OdooConnectorActionDuplicate'),
	);
	$elementCardUrls = array(
		'facture' => DOL_URL_ROOT.'/compta/facture/card.php?facid=',
		'facture_fourn' => DOL_URL_ROOT.'/fourn/facture/card.php?facid=',
		'expensereport' => DOL_URL_ROOT.'/expensereport/card.php?id=',
	);
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OdooConnectorSyncLogHelp').'</td></tr>';
	while ($objlog = $db->fetch_object($reslog)) {
		$typeLabel = isset($elementTypeLabels[$objlog->element_type]) ? $elementTypeLabels[$objlog->element_type] : $objlog->element_type;
		$refLabel = dol_escape_htmltag($objlog->ref !== '' && $objlog->ref !== null ? $objlog->ref : 'ID '.$objlog->fk_source_id);
		if (isset($elementCardUrls[$objlog->element_type])) {
			$refLabel = '<a href="'.$elementCardUrls[$objlog->element_type].((int) $objlog->fk_source_id).'">'.$refLabel.'</a>';
		}
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($objlog->date_try), '%Y-%m-%d %H:%M').' &ndash; '.dol_escape_htmltag($typeLabel).' &ndash; '.$refLabel;
		print ' &ndash; '.dol_escape_htmltag(isset($actionLabels[$objlog->action]) ? $actionLabels[$objlog->action] : $objlog->action);
		if ($objlog->odoo_id > 0) {
			print ' (Odoo move #'.(int) $objlog->odoo_id.')';
		}
		print '<br><span class="opacitymedium">'.dol_escape_htmltag($objlog->error).'</span></td>';
		print '<td class="right">';
		print '<form action="'.$_SERVER['PHP_SELF'].'" method="post" style="display:inline">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="del_synclog">';
		print '<input type="hidden" name="rowid" value="'.(int) $objlog->rowid.'">';
		print '<input type="submit" class="button small" value="'.$langs->trans('OdooConnectorDismissSyncLog').'">';
		print '</form></td></tr>';
	}
}
print '</table></div>';

print '<br><p class="opacitymedium">'.$langs->trans('OdooConnectorCronHelp').'</p>';

llxFooter();
$db->close();
