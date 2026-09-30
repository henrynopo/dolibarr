<?php
/* Copyright (C) 2026 Henry Guo <hbg@hbg.sg>
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
 * \file   htdocs/custom/sfrs_reports/admin/setup.php
 * \brief  Setup page for SFRS Reports module
 *
 * Allows admin to:
 *  - Choose active SFRS framework (FRS / SFRS(I) / SFRS for SE)
 *  - Choose Cash Flow Statement method (direct / indirect)
 *  - Set presentation currency and Singapore UEN
 *  - Import Singapore chart of accounts (~200 rows) once
 *  - Import SFRS report categories (BS / P&L / CF) once
 *  - View module info
 */

// Load Dolibarr environment — main.inc.php may sit 3 levels up (deployed under /custom)
$res = 0;
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SFRS Reports setup.php');
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$form = new Form($db);

$langs->loadLangs(array('admin', 'sfrs_reports@sfrs_reports', 'accountancy'));

if (!$user->hasRight('sfrsreports', 'reports', 'setup')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

$title = $langs->trans('SFRSSetup');

llxHeader('', $title, '');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'accountancy');



// ----------------------------------------------------------------------------
// Handle actions
// ----------------------------------------------------------------------------

// Re-bind default accounts (re-applies the SFRS account numbers to the
// standard Dolibarr default-account constants in case the user wants to
// re-apply after a reset).
if ($action === 'rebinding_default_accounts' && $confirm === 'yes') {
	if (!$user->hasRight('sfrsreports', 'reports', 'setup')) {
		accessforbidden();
	}
	if (!verifyToken()) {
		accessforbidden();
	}
	require_once __DIR__.'/../class/sfrsreport.class.php';
	$sfrs = new SfrsReport($db);
	// Use reflection to call the private bindDolibarrDefaultAccounts via a small public wrapper
	// — easier path: replicate the bindings here.
	$bindings = array(
		'ACCOUNTING_ACCOUNT_CUSTOMER'         => '1600',
		'ACCOUNTING_ACCOUNT_SUPPLIER'         => '2000',
		'SALARIES_ACCOUNTING_ACCOUNT_PAYMENT'=> '2410',
		'ACCOUNTING_ACCOUNT_EXPENSEREPORT'    => '2100',
		'ACCOUNTING_ACCOUNT_TRANSFER_CASH'    => '1899',
		'ACCOUNTING_ACCOUNT_SUSPENSE'         => '8500',
		'ACCOUNTING_PRODUCT_SOLD_ACCOUNT'     => '4000',
		'ACCOUNTING_PRODUCT_SOLD_EXPORT_ACCOUNT' => '4001',
		'ACCOUNTING_SERVICE_SOLD_ACCOUNT'     => '4100',
		'ACCOUNTING_SERVICE_SOLD_EXPORT_ACCOUNT' => '4101',
		'ACCOUNTING_PRODUCT_BUY_ACCOUNT'      => '5000',
		'ACCOUNTING_SERVICE_BUY_ACCOUNT'      => '6430',
		'ACCOUNTING_VAT_SOLD_ACCOUNT'         => '2200',
		'ACCOUNTING_VAT_BUY_ACCOUNT'          => '1730',
		'ACCOUNTING_VAT_PAY_ACCOUNT'          => '2240',
		'ACCOUNTING_ACCOUNT_CUSTOMER_DEPOSIT' => '2450',
		'ACCOUNTING_ACCOUNT_SUPPLIER_DEPOSIT' => '1720',
	);
	$count = 0;
	foreach ($bindings as $k => $v) {
		$existing = getDolGlobalString($k);
		if ($existing === '' || $existing === null) {
			dolibarr_set_const($db, $k, $v, 'chaine', 0, '', $conf->entity);
			$count++;
		}
	}
	setEventMessages($langs->trans('DefaultAccountsRebound').' ('.$count.' '.$langs->trans('NewBindings').')', null, 'mesgs');
	$action = '';
}

// Re-register SG GST rates (idempotent: skips rows that already exist)
if ($action === 'reregister_gst' && $confirm === 'yes') {
	if (!$user->hasRight('sfrsreports', 'reports', 'setup')) {
		accessforbidden();
	}
	if (!verifyToken()) {
		accessforbidden();
	}
	$sg_rates = array(
		array('code'=>'SG-S9','label'=>'SG GST 9% (Standard-rated, 2024+)','taux'=>9.0,'type_vat'=>0,'sell'=>'2200','buy'=>'1730'),
		array('code'=>'SG-S0','label'=>'SG GST 0% (Zero-rated, exports)','taux'=>0.0,'type_vat'=>1,'sell'=>'2210','buy'=>'1730'),
		array('code'=>'SG-EX','label'=>'SG GST Exempt','taux'=>0.0,'type_vat'=>1,'sell'=>'2220','buy'=>''),
		array('code'=>'SG-S7','label'=>'SG GST 7% (2023 historical)','taux'=>7.0,'type_vat'=>0,'sell'=>'2200','buy'=>'1730'),
		array('code'=>'SG-S8','label'=>'SG GST 8% (2023-2024 transitional)','taux'=>8.0,'type_vat'=>0,'sell'=>'2200','buy'=>'1730'),
	);
	$inserted = 0;
	foreach ($sg_rates as $r) {
		$sql_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_tva";
		$sql_check .= " WHERE code = '".$db->escape($r['code'])."' AND fk_pays = 29";
		$sql_check .= " AND entity = ".((int) $conf->entity);
		$res = $db->query($sql_check);
		if ($res && $db->num_rows($res) > 0) {
			continue;
		}
		$sql_ins = "INSERT INTO ".MAIN_DB_PREFIX."c_tva";
		$sql_ins .= " (entity, fk_pays, code, type_vat, taux, localtax1, localtax1_type, localtax2, localtax2_type,";
		$sql_ins .= "  use_default, recuperableonly, note, active, accountancy_code_sell, accountancy_code_buy)";
		$sql_ins .= " VALUES (".((int) $conf->entity).", 29, '".$db->escape($r['code'])."', ".(int) $r['type_vat'].", ".(float) $r['taux'].",";
		$sql_ins .= " '0','0','0','0', 0, 0, '".$db->escape($r['label'])."', 1,";
		$sql_ins .= " ".($r['sell'] ? "'".$db->escape($r['sell'])."'" : 'NULL').",";
		$sql_ins .= " ".($r['buy'] ? "'".$db->escape($r['buy'])."'" : 'NULL').")";
		$db->query($sql_ins);
		$inserted++;
	}
	setEventMessages($langs->trans('GSTRatesReRegistered').' ('.$inserted.' '.$langs->trans('New').')', null, 'mesgs');
	$action = '';
}

// Refresh the module's hook list so newly added hook contexts (e.g.
// bookkeepingCreateBefore) are picked up by HookManager without requiring
// a disable / re-enable cycle. The const value is a JSON-encoded array of
// hook context names. We read from modSfrsReports::HOOKS (single source of
// truth shared with $module_parts['hooks']) so this page can never drift
// from what the module actually declares.
if ($action === 'refresh_hooks' && $confirm === 'yes') {
	if (!$user->hasRight('sfrsreports', 'reports', 'setup')) {
		accessforbidden();
	}
	if (!verifyToken()) {
		accessforbidden();
	}
	require_once __DIR__.'/../core/modules/modSfrsReports.class.php';
	$hooks_json = json_encode(modSfrsReports::HOOKS);
	dolibarr_set_const($db, 'MAIN_MODULE_SFRSREPORTS_HOOKS', $hooks_json, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('ModuleHooksRefreshed').' (MAIN_MODULE_SFRSREPORTS_HOOKS = '.dol_escape_htmltag($hooks_json).')', null, 'mesgs');
	$action = '';
}

// Generate the period-end unrealised FX revaluation journal entry (OD journal).
// See SfrsReport::generateFxRevaluation() — idempotent per date, aborts when a
// closing rate is missing.
if ($action === 'generate_fxrev' && $confirm === 'yes') {
	if (!$user->hasRight('sfrsreports', 'reports', 'setup')) {
		accessforbidden();
	}
	if (!verifyToken()) {
		accessforbidden();
	}
	require_once __DIR__.'/../class/sfrsreport.class.php';
	$fx_date_str = GETPOST('fx_date', 'alpha');
	$fx_date = dol_stringtotime($fx_date_str);
	if (empty($fx_date_str) || $fx_date <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->trans('FXRevaluationDate')), null, 'errors');
	} else {
		$sfrs = new SfrsReport($db);
		$result = $sfrs->generateFxRevaluation($fx_date, $user);
		if ($result > 0) {
			setEventMessages($langs->trans('FXRevaluationGenerated', $result), null, 'mesgs');
		} elseif ($result === 0) {
			setEventMessages($langs->trans('FXRevaluationNothingToDo'), null, 'warnings');
		} else {
			setEventMessages($sfrs->error, null, 'errors');
		}
	}
	$action = '';
}

// Save general config form
if ($action === 'update' && $user->hasRight('sfrsreports', 'reports', 'setup')) {
	$framework = GETPOST('SFRS_FRAMEWORK', 'alpha');
	$cf_method = GETPOST('SFRS_CASHFLOW_METHOD', 'alpha');
	$currency = GETPOST('SFRS_PRESENTATION_CURRENCY', 'alpha');
	$uen = GETPOST('SFRS_COMPANY_UEN', 'alpha');
	$gst = GETPOST('SFRS_GST_STANDARD_RATE', 'alpha');

	// Multi-currency settings
	$bs_rate_method = GETPOST('SFRS_MC_BS_RATE_METHOD', 'alpha');
	$pl_rate_method = GETPOST('SFRS_MC_PL_RATE_METHOD', 'alpha');
	$fixed_rates_raw = GETPOST('SFRS_MC_FIXED_RATES', 'none');
	$fx_gain_account = GETPOST('SFRS_MC_FX_GAIN_ACCOUNT', 'alpha');
	$fx_loss_account = GETPOST('SFRS_MC_FX_LOSS_ACCOUNT', 'alpha');

	if (!in_array($framework, array('FRS', 'SFRS(I)', 'SFRS_FOR_SE'))) {
		$framework = 'FRS';
	}
	if (!in_array($cf_method, array('direct', 'indirect'))) {
		$cf_method = 'direct';
	}
	if (!in_array($bs_rate_method, array('closing', 'average', 'historical', 'fixed'))) {
		$bs_rate_method = 'closing';
	}
	if (!in_array($pl_rate_method, array('average', 'closing', 'historical', 'fixed'))) {
		$pl_rate_method = 'average';
	}
	// Fixed rates map: one "CODE=RATE" line per currency -> JSON const.
	// A multi-currency ledger holds many foreign currencies, so the pinned
	// rates must be per currency (a single global number serves none of them).
	$fixed_rates = array();
	$skipped_lines = array();
	foreach (preg_split('/\r\n|\r|\n/', $fixed_rates_raw) as $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		if (preg_match('/^([A-Za-z]{3})\s*=\s*([0-9]*\.?[0-9]+)$/', $line, $m) && (float) $m[2] > 0) {
			$fixed_rates[strtoupper($m[1])] = (float) $m[2];
		} else {
			$skipped_lines[] = $line;
		}
	}

	dolibarr_set_const($db, 'SFRS_FRAMEWORK', $framework, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_CASHFLOW_METHOD', $cf_method, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_PRESENTATION_CURRENCY', $currency, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_COMPANY_UEN', $uen, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_GST_STANDARD_RATE', $gst, 'chaine', 0, '', $conf->entity);

	// Save multi-currency settings
	dolibarr_set_const($db, 'SFRS_MC_BS_RATE_METHOD', $bs_rate_method, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_MC_PL_RATE_METHOD', $pl_rate_method, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_MC_FIXED_RATES', json_encode($fixed_rates), 'chaine', 0, '', $conf->entity);
	// Drop the legacy single-value const (written by pre per-currency versions).
	dolibarr_del_const($db, 'SFRS_MC_FIXED_RATE', $conf->entity);
	if (!empty($skipped_lines)) {
		setEventMessages($langs->trans('FixedRateLinesSkipped', dol_escape_htmltag(implode(' | ', $skipped_lines))), null, 'warnings');
	}
	dolibarr_set_const($db, 'SFRS_MC_FX_GAIN_ACCOUNT', $fx_gain_account, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'SFRS_MC_FX_LOSS_ACCOUNT', $fx_loss_account, 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	$action = '';
}


// ----------------------------------------------------------------------------
// Show configuration form
// ----------------------------------------------------------------------------

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="border centpercent">';

// SFRS Framework
$framework = getDolGlobalString('SFRS_FRAMEWORK', 'FRS');
print '<tr class="oddeven">';
print '<td width="40%">'.$langs->trans('Framework').'</td>';
print '<td>';
print Form::selectarray('SFRS_FRAMEWORK', array(
	'FRS' => 'FRS — '.$langs->trans('FrameworkFRS'),
	'SFRS(I)' => 'SFRS(I) — '.$langs->trans('FrameworkSFRSI'),
	'SFRS_FOR_SE' => 'SFRS for SE — '.$langs->trans('FrameworkSFRSForSE'),
), $framework);
print '</td>';
print '</tr>';

// Cash Flow method
$cf_method = getDolGlobalString('SFRS_CASHFLOW_METHOD', 'direct');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('CashFlowMethod').'</td>';
print '<td>';
print Form::selectarray('SFRS_CASHFLOW_METHOD', array(
	'direct' => $langs->trans('DirectMethod'),
	'indirect' => $langs->trans('IndirectMethod'),
), $cf_method);
print ' <span class="opacitymedium"> &nbsp; '.$langs->trans('FRS7AllowsBothMethods').'</span>';
print '</td>';
print '</tr>';

// Presentation currency
$currency = getDolGlobalString('SFRS_PRESENTATION_CURRENCY', 'SGD');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('PresentationCurrency').'</td>';
print '<td><input type="text" name="SFRS_PRESENTATION_CURRENCY" value="'.dol_escape_htmltag($currency).'" maxlength="3" class="flat maxwidth4" autocomplete="off"> '.$langs->trans('ISO4217').'</td>';
print '</tr>';

// Singapore UEN
$uen = getDolGlobalString('SFRS_COMPANY_UEN', '');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('CompanyUEN').'</td>';
print '<td><input type="text" name="SFRS_COMPANY_UEN" value="'.dol_escape_htmltag($uen).'" maxlength="20" class="flat maxwidth20" autocomplete="off"></td>';
print '</tr>';

// GST rate
$gst = getDolGlobalString('SFRS_GST_STANDARD_RATE', '9');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('GSTStandardRate').'</td>';
print '<td><input type="number" name="SFRS_GST_STANDARD_RATE" value="'.dol_escape_htmltag($gst).'" min="0" max="100" step="0.5" class="flat maxwidth5" autocomplete="off"> %</td>';
print '</tr>';

print '</table>';

// ============================================================================
// Multi-currency FX settings section
// ============================================================================
print '<br>';
print load_fiche_titre($langs->trans('SFRSMCSettings'), '', 'accountancy');

print '<table class="border centpercent">';

// BS exchange rate method
$bs_rate_method = getDolGlobalString('SFRS_MC_BS_RATE_METHOD', 'closing');
print '<tr class="oddeven">';
print '<td width="40%">'.$langs->trans('BSRateMethod').'</td>';
print '<td>';
print Form::selectarray('SFRS_MC_BS_RATE_METHOD', array(
	'closing' => $langs->trans('ClosingRate'),
	'average' => $langs->trans('AverageRate'),
	'historical' => $langs->trans('HistoricalRate'),
	'fixed' => $langs->trans('FixedRate'),
), $bs_rate_method);
print '</td>';
print '</tr>';

// PL exchange rate method
$pl_rate_method = getDolGlobalString('SFRS_MC_PL_RATE_METHOD', 'average');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('PLRateMethod').'</td>';
print '<td>';
print Form::selectarray('SFRS_MC_PL_RATE_METHOD', array(
	'average' => $langs->trans('AverageRate'),
	'closing' => $langs->trans('ClosingRate'),
	'historical' => $langs->trans('HistoricalRate'),
	'fixed' => $langs->trans('FixedRate'),
), $pl_rate_method);
print '</td>';
print '</tr>';

// Fixed rates map — one "CODE=RATE" line per currency
$fixed_rates_json = getDolGlobalString('SFRS_MC_FIXED_RATES', '');
$fixed_rates_map = ($fixed_rates_json !== '') ? json_decode($fixed_rates_json, true) : null;
$fixed_rates_text = '';
if (is_array($fixed_rates_map)) {
	$lines = array();
	foreach ($fixed_rates_map as $c => $r) {
		$lines[] = strtoupper((string) $c).'='.(float) $r;
	}
	$fixed_rates_text = implode("\n", $lines);
}
print '<tr class="oddeven">';
print '<td>'.$langs->trans('FixedRateValue').'</td>';
print '<td>';
print '<textarea name="SFRS_MC_FIXED_RATES" class="flat" style="max-width:300px;" rows="4">'.dol_escape_htmltag($fixed_rates_text, 0, 1).'</textarea>';
print '<br><span class="opacitymedium">('.$langs->trans('UsedWhenFixedMethodSelected').')</span>';
print '</td>';
print '</tr>';

// FX gain account
$fx_gain_account = getDolGlobalString('SFRS_MC_FX_GAIN_ACCOUNT', '7010');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('FXGainAccount').'</td>';
print '<td><input type="text" name="SFRS_MC_FX_GAIN_ACCOUNT" value="'.dol_escape_htmltag($fx_gain_account).'" maxlength="10" class="flat maxwidth10" autocomplete="off">';
print ' <span class="opacitymedium">('.$langs->trans('FXGainAccountExample').')</span>';
print '</td>';
print '</tr>';

// FX loss account
$fx_loss_account = getDolGlobalString('SFRS_MC_FX_LOSS_ACCOUNT', '7110');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('FXLossAccount').'</td>';
print '<td><input type="text" name="SFRS_MC_FX_LOSS_ACCOUNT" value="'.dol_escape_htmltag($fx_loss_account).'" maxlength="10" class="flat maxwidth10" autocomplete="off">';
print ' <span class="opacitymedium">('.$langs->trans('FXLossAccountExample').')</span>';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';


// ----------------------------------------------------------------------------
// Show data-import section
// ----------------------------------------------------------------------------
// Note: COA + categories + default-account bindings + GST rates are all
// applied automatically when the module is enabled (see modSfrsReports::init()).
// The status display below lets the user verify what was done. The "Re-apply"
// buttons are there in case the user wants to re-run after a manual reset.

print '<br>';
print load_fiche_titre($langs->trans('SingaporeChartOfAccounts').' (entity '.(int) $conf->entity.')', '', 'accountancy');

// Per-entity detection: count SFRS-BASE rows in the current entity. This is
// the same criterion init() uses, so the badge accurately reflects whether
// enable would import or skip on the next reload.
$res = $db->query("SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."accounting_account WHERE fk_pcg_version = 'SFRS-BASE' AND entity = ".(int) $conf->entity);
$obj = $res ? $db->fetch_object($res) : null;
$coa_n = (int) ($obj->n ?? 0);
if ($coa_n > 0) {
	print '<div class="ok">';
	print img_picto('', 'tick').' '.$langs->trans('SingaporeChartOfAccountsAlreadyLoaded');
	print ' — <strong>'.$coa_n.'</strong> '.$langs->trans('AccountsLoaded');
	print '</div>';
} else {
	print '<div class="warning">'.$langs->trans('SingaporeChartOfAccountsNotLoadedForEntity').' (entity '.(int) $conf->entity.')</div>';
}

print '<br>';

$res = $db->query("SELECT COUNT(*) AS n FROM ".MAIN_DB_PREFIX."c_accounting_category WHERE fk_country = 29 AND entity = ".(int) $conf->entity);
$obj = $res ? $db->fetch_object($res) : null;
$cat_n = (int) ($obj->n ?? 0);
if ($cat_n > 0) {
	print '<div class="ok">';
	print img_picto('', 'tick').' '.$langs->trans('SfrsCategoriesAlreadyLoaded');
	print ' — <strong>'.$cat_n.'</strong> '.$langs->trans('CategoriesLoaded');
	print '</div>';
} else {
	print '<div class="warning">'.$langs->trans('SfrsCategoriesNotLoadedForEntity').' (entity '.(int) $conf->entity.')</div>';
}

print '<br>';
print load_fiche_titre($langs->trans('DolibarrIntegrationStatus'), '', 'bank');

// Show which default-account bindings are currently set
$bindings_display = array(
	'ACCOUNTING_ACCOUNT_CUSTOMER'         => '1600',
	'ACCOUNTING_ACCOUNT_SUPPLIER'         => '2000',
	'ACCOUNTING_VAT_SOLD_ACCOUNT'         => '2200',
	'ACCOUNTING_VAT_BUY_ACCOUNT'          => '1730',
	'ACCOUNTING_VAT_PAY_ACCOUNT'          => '2240',
	'ACCOUNTING_PRODUCT_SOLD_ACCOUNT'     => '4000',
	'ACCOUNTING_SERVICE_SOLD_ACCOUNT'     => '4100',
	'ACCOUNTING_PRODUCT_BUY_ACCOUNT'      => '5000',
	'ACCOUNTING_SERVICE_BUY_ACCOUNT'      => '6430',
	'ACCOUNTING_ACCOUNT_TRANSFER_CASH'    => '1899',
	'ACCOUNTING_ACCOUNT_SUSPENSE'         => '8500',
);
print '<table class="border centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('DolibarrConstant').'</th>';
print '<th>'.$langs->trans('CurrentValue').'</th>';
print '<th>'.$langs->trans('ExpectedSFRSValue').'</th>';
print '<th>'.$langs->trans('Status').'</th>';
print '</tr>';
foreach ($bindings_display as $const => $expected) {
	$current = getDolGlobalString($const, '');
	$status_class = ($current === $expected) ? 'ok' : (($current === '' || $current === null) ? 'warning' : 'warning');
	$status_label = ($current === $expected) ? $langs->trans('OK') : (($current === '' || $current === null) ? $langs->trans('Empty') : $langs->trans('Custom'));
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($const).'</td>';
	print '<td>'.($current !== '' ? dol_escape_htmltag($current) : '<em>—</em>').'</td>';
	print '<td>'.dol_escape_htmltag($expected).'</td>';
	print '<td><span class="'.$status_class.'">'.$status_label.'</span></td>';
	print '</tr>';
}
print '</table>';

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" style="margin-top:10px;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="rebinding_default_accounts">';
print '<input type="hidden" name="confirm" value="yes">';
print '<input type="submit" class="button" value="'.$langs->trans('ReApplyDefaultAccounts').'" onclick="return confirm(\''.$langs->trans('ConfirmReBinding').'?\');">';
print '</form>';

// Journal set status — the 7 standard journals (OD/VT/AC/BQ/ER/INV/AN) that
// modSfrsReports::ensureAccountingJournals() guarantees on module enable.
print '<br>';
print load_fiche_titre($langs->trans('AccountingJournalsStatus').' (entity '.(int) $conf->entity.')', '', 'accountancy');

$res = $db->query("SELECT code, label, nature, active FROM ".MAIN_DB_PREFIX."accounting_journal WHERE entity = ".((int) $conf->entity)." ORDER BY nature, code");
$existing_natures = array();
if ($res) {
	print '<table class="border centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('Code').'</th>';
	print '<th>'.$langs->trans('Label').'</th>';
	print '<th>'.$langs->trans('Nature').'</th>';
	print '<th>'.$langs->trans('Active').'</th>';
	print '</tr>';
	while ($obj = $db->fetch_object($res)) {
		$existing_natures[] = (int) $obj->nature;
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->code).'</td>';
		print '<td>'.dol_escape_htmltag($langs->trans($obj->label)).'</td>';
		print '<td>'.dol_escape_htmltag($langs->trans('AccountingJournalType'.$obj->nature)).' ('.(int) $obj->nature.')</td>';
		print '<td>'.((int) $obj->active ? img_picto('', 'tick') : img_picto('', 'off')).'</td>';
		print '</tr>';
	}
	print '</table>';
}
$missing_natures = array_diff(array(1, 2, 3, 4, 5, 8, 9), $existing_natures);
if (!empty($missing_natures)) {
	print '<div class="warning">'.$langs->trans('JournalsMissingForEntity', implode(', ', $missing_natures)).'</div>';
}

print '<br>';
print load_fiche_titre($langs->trans('GSTRatesRegistration'), '', 'bill');

// Show registered SG GST rates (current entity only — c_tva has an entity column)
$res = $db->query("SELECT rowid, code, taux, accountancy_code_sell, accountancy_code_buy, active FROM ".MAIN_DB_PREFIX."c_tva WHERE fk_pays = 29 AND entity = ".((int) $conf->entity)." ORDER BY code");
if ($res) {
	print '<table class="border centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('Code').'</th>';
	print '<th>'.$langs->trans('Rate').' (%)</th>';
	print '<th>'.$langs->trans('AccountSell').'</th>';
	print '<th>'.$langs->trans('AccountBuy').'</th>';
	print '<th>'.$langs->trans('Active').'</th>';
	print '</tr>';
	while ($obj = $db->fetch_object($res)) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->code).'</td>';
		print '<td>'.(float) $obj->taux.'</td>';
		print '<td>'.dol_escape_htmltag($obj->accountancy_code_sell).'</td>';
		print '<td>'.dol_escape_htmltag($obj->accountancy_code_buy).'</td>';
		print '<td>'.((int) $obj->active ? img_picto('', 'tick') : img_picto('', 'off')).'</td>';
		print '</tr>';
	}
	print '</table>';
}

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" style="margin-top:10px;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="reregister_gst">';
print '<input type="hidden" name="confirm" value="yes">';
print '<input type="submit" class="button" value="'.$langs->trans('ReRegisterGSTRates').'">';
print '</form>';

// ============================================================================
// FX revaluation journal (OD) — SFRS 21 period-end unrealised revaluation
// ============================================================================
print '<br>';
print load_fiche_titre($langs->trans('FXRevaluationSection'), '', 'currency');

print '<p class="opacitymedium small">'.$langs->trans('FXRevaluationNote').'</p>';

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="generate_fxrev">';
print '<input type="hidden" name="confirm" value="yes">';
print '<table class="border centpercent">';
print '<tr class="oddeven">';
print '<td width="40%">'.$langs->trans('FXRevaluationDate').'</td>';
print '<td><input type="date" name="fx_date" value="'.date('Y-m-d').'" class="flat"></td>';
print '</tr>';
print '</table>';
print '<div class="center" style="margin-top:6px;">';
print '<input type="submit" class="button" value="'.$langs->trans('GenerateFXRevaluation').'" onclick="return confirm(\''.$langs->trans('ConfirmGenerateFXRevaluation').'?\');">';
print '</div>';
print '</form>';

print '<br>';
print load_fiche_titre($langs->trans('ModuleHooks'), '', 'code');

// Show currently registered hooks.
// Dolibarr 22 stores MAIN_MODULE_XXX_HOOKS as a JSON array (insert_module_parts
// json_encodes it), so decode for display; fall back to the raw value when it
// is not valid JSON.
$current_hooks_json = getDolGlobalString('MAIN_MODULE_SFRSREPORTS_HOOKS', '');
$current_hooks = ($current_hooks_json !== '') ? json_decode($current_hooks_json, true) : null;
if (is_array($current_hooks) && !empty($current_hooks)) {
	print '<p>'.dol_escape_htmltag(implode(', ', $current_hooks)).'</p>';
} else {
	print '<p>'.dol_escape_htmltag($current_hooks_json !== '' ? $current_hooks_json : '(none)').'</p>';
}

print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" style="margin-top:10px;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="refresh_hooks">';
print '<input type="hidden" name="confirm" value="yes">';
print '<input type="submit" class="button" value="'.$langs->trans('RefreshModuleHooks').'">';
print '</form>';

llxFooter();
$db->close();