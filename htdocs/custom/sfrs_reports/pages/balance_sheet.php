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
 * \file   htdocs/custom/sfrs_reports/pages/balance_sheet.php
 * \brief  Render the SFRS Balance Sheet
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
	die('Include of main.inc.php failed for SFRS Reports balance_sheet.php');
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../class/sfrsreport.class.php';
require_once __DIR__.'/../class/sfrsbalancebuilder.class.php';

$langs->loadLangs(array('sfrs_reports@sfrs_reports', 'accountancy'));

if (!$user->hasRight('sfrsreports', 'reports', 'read')) {
	accessforbidden();
}

// Read parameters
$date_str = GETPOST('date', 'alpha');
if (empty($date_str)) {
	$date_str = date('Y-m-d');
}
$date_limit = dol_stringtotime($date_str);

// Reporting currency filter: 'FUNC' (default — entries without multicurrency_code)
// | 'ALL' (no filter) | 'USD' / 'SGD' / 'EUR' / ... (only this currency)
$mc_code = GETPOST('mc_code', 'alpha');
if ($mc_code === '') {
	$mc_code = 'FUNC';
}

$action = GETPOST('action', 'aZ09');

// Build report
$sfrs = new SfrsReport($db);
$builder = new SfrsBalanceBuilder($sfrs, $date_limit, $mc_code);
$lines = $builder->build();
$balance_check = $builder->checkBalance($lines);

$framework = $sfrs->getFramework();
$currency = getDolGlobalString('SFRS_PRESENTATION_CURRENCY', 'SGD');
$uen = getDolGlobalString('SFRS_COMPANY_UEN', '');

// $mysoc (Societe object for current entity) is already populated by main.inc.php.
// It exposes ->name (company name) and other company-level fields.
global $mysoc;

// Page rendering
llxHeader('', $langs->trans('SFRSBalanceSheet'), '');

print load_fiche_titre($langs->trans('SFRSBalanceSheet').' — '.$framework, '', 'balance');

// Chart compatibility check — the SFRS category ranges assume the SFRS-BASE chart
$chart_version = $sfrs->getActiveChartVersion();
if ($chart_version !== '' && $chart_version !== 'SFRS-BASE') {
	print '<div class="warning">';
	print $langs->trans('ChartMismatchWarning', $chart_version);
	print '</div>';
	print '<br>';
}

// Filter form
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border centpercent">';
print '<tr class="oddeven">';
print '<td width="20%">'.$langs->trans('BalanceDate').'</td>';
print '<td><input type="date" name="date" value="'.$date_str.'" class="flat"></td>';
print '</tr>';
include __DIR__.'/../tpl/report_currency_filter.tpl.php';
print '</table>';
print '</form>';

print '<br>';

// Company header
print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">'.$langs->trans('Company').'</td><td>'.dol_escape_htmltag($mysoc->name).'</td></tr>';
if (!empty($uen)) {
	print '<tr><td>'.$langs->trans('UEN').'</td><td>'.dol_escape_htmltag($uen).'</td></tr>';
}
print '<tr><td>'.$langs->trans('Framework').'</td><td>'.dol_escape_htmltag($framework).'</td></tr>';
print '<tr><td>'.$langs->trans('AsOfDate').'</td><td>'.dol_print_date($date_limit, 'day').'</td></tr>';
print '<tr><td>'.$langs->trans('PresentationCurrency').'</td><td>'.dol_escape_htmltag($currency).'</td></tr>';
print '<tr><td>'.$langs->trans('ReportingCurrency').'</td><td>'.dol_escape_htmltag($_sfrs_current_mc_label).'</td></tr>';
// Show applied exchange rate when using foreign currency translation
if (!empty($mc_code) && $mc_code !== 'FUNC' && $mc_code !== 'ALL') {
	$applied_rate = $builder->getExchangeRate();
	$rate_method = getDolGlobalString('SFRS_MC_BS_RATE_METHOD', 'closing');
	$rate_method_label = $langs->trans(ucfirst($rate_method).'Rate');
	print '<tr><td>'.$langs->trans('RateApplied').'</td><td>'.price($applied_rate, 0, '', 1, -1, 4, $conf->currency).' ('.$rate_method_label.')</td></tr>';
}
print '</table>';

print '<br>';

// Balance check banner
if (!$balance_check['balanced']) {
	print '<div class="warning">';
	print $langs->trans('BSDoesNotBalance').': ';
	print price($balance_check['diff'], 0, '', 1, -1, 2, $conf->currency);
	print ' ('.$langs->trans('Diff').' = '.price($balance_check['total_assets'], 0, '', 1, -1, 2, $conf->currency).' - '.price($balance_check['total_equity_liab'], 0, '', 1, -1, 2, $conf->currency).')';
	print '</div>';
	print '<br>';
} else {
	print '<div class="ok">'.$langs->trans('BSBalanced').'</div>';
	print '<br>';
}

// Report table
print '<table class="border centpercent">';
print '<tr class="liste_titre">';
print '<th class="left" width="20%">'.$langs->trans('Code').'</th>';
print '<th class="left">'.$langs->trans('Label').'</th>';
print '<th class="right" width="20%">'.$langs->trans('Amount').' ('.$currency.')</th>';
print '</tr>';

foreach ($lines as $line) {
	$css = '';
	$amount_display = '';
	switch ($line['kind']) {
		case 'header':
			$css = 'liste_titre';
			$amount_display = '';
			break;
		case 'subtotal':
			$css = 'oddeven bold';
			$amount_display = ($line['amount'] != 0) ? price($line['amount'], 0, '', 1, -1, 2, $conf->currency) : '-';
			break;
		case 'total':
			$css = 'liste_total';
			$amount_display = ($line['amount'] != 0) ? price($line['amount'], 0, '', 1, -1, 2, $conf->currency) : '-';
			break;
		default:
			$css = 'oddeven';
			$amount_display = ($line['amount'] != 0) ? price($line['amount'], 0, '', 1, -1, 2, $conf->currency) : '-';
			break;
	}

	print '<tr class="'.$css.'">';
	print '<td>'.dol_escape_htmltag($line['code']).'</td>';
	print '<td>'.dol_escape_htmltag($line['label']).'</td>';
	print '<td class="right">'.$amount_display.'</td>';
	print '</tr>';
}

print '</table>';

print '<br>';
print '<div class="opacitymedium small">';
print $langs->trans('BSReportNote');
print '</div>';

print '</div>';

llxFooter();
$db->close();