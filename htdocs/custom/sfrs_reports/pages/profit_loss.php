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
 * \file   htdocs/custom/sfrs_reports/pages/profit_loss.php
 * \brief  Render the SFRS Profit & Loss Statement
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
	die('Include of main.inc.php failed for SFRS Reports profit_loss.php');
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../class/sfrsreport.class.php';
require_once __DIR__.'/../class/sfrsplbuilder.class.php';

$langs->loadLangs(array('sfrs_reports@sfrs_reports', 'accountancy'));

if (!$user->hasRight('sfrsreports', 'reports', 'read')) {
	accessforbidden();
}

// Read period
$date_start_str = GETPOST('date_start', 'alpha');
$date_end_str = GETPOST('date_end', 'alpha');
if (empty($date_start_str) || empty($date_end_str)) {
	// Default: current year 1-Jan to today
	$date_start_str = date('Y-01-01');
	$date_end_str = date('Y-m-d');
}
$date_start = dol_stringtotime($date_start_str);
$date_end = dol_stringtotime($date_end_str);

// Reporting currency filter: 'FUNC' (default) | 'ALL' | ISO code
$mc_code = GETPOST('mc_code', 'alpha');
if ($mc_code === '') {
	$mc_code = 'FUNC';
}

$sfrs = new SfrsReport($db);
$builder = new SfrsProfitLossBuilder($sfrs, $date_start, $date_end, $mc_code);
$lines = $builder->build();

$framework = $sfrs->getFramework();
$currency = getDolGlobalString('SFRS_PRESENTATION_CURRENCY', 'SGD');
$uen = getDolGlobalString('SFRS_COMPANY_UEN', '');

// $mysoc (Societe object for current entity) is already populated by main.inc.php.
global $mysoc;

llxHeader('', $langs->trans('SFRSProfitLoss'), '');

print load_fiche_titre($langs->trans('SFRSProfitLoss').' — '.$framework, '', 'accountancy');

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
print '<td width="20%">'.$langs->trans('PeriodStart').'</td>';
print '<td><input type="date" name="date_start" value="'.$date_start_str.'" class="flat"></td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('PeriodEnd').'</td>';
print '<td><input type="date" name="date_end" value="'.$date_end_str.'" class="flat"></td>';
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
print '<tr><td>'.$langs->trans('Period').'</td><td>'.dol_print_date($date_start, 'day').' — '.dol_print_date($date_end, 'day').'</td></tr>';
print '<tr><td>'.$langs->trans('PresentationCurrency').'</td><td>'.dol_escape_htmltag($currency).'</td></tr>';
print '<tr><td>'.$langs->trans('ReportingCurrency').'</td><td>'.dol_escape_htmltag($_sfrs_current_mc_label).'</td></tr>';
// Show applied exchange rate when using foreign currency translation
if (!empty($mc_code) && $mc_code !== 'FUNC' && $mc_code !== 'ALL') {
	$applied_rate = $builder->getAverageRate();
	$rate_method = getDolGlobalString('SFRS_MC_PL_RATE_METHOD', 'average');
	$rate_method_label = $langs->trans(ucfirst($rate_method).'Rate');
	print '<tr><td>'.$langs->trans('RateApplied').'</td><td>'.price($applied_rate, 0, '', 1, -1, 4, $conf->currency).' ('.$rate_method_label.')</td></tr>';
}
print '</table>';

print '<br>';

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
print $langs->trans('PLReportNote');
print '</div>';

print '</div>';

llxFooter();
$db->close();