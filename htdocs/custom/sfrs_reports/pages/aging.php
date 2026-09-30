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
 * \file   htdocs/custom/sfrs_reports/pages/aging.php
 * \brief  Render the SFRS AR / AP Aging report (one page, type=ar|ap)
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
	die('Include of main.inc.php failed for SFRS Reports aging.php');
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__.'/../class/sfrsreport.class.php';

$langs->loadLangs(array('sfrs_reports@sfrs_reports', 'accountancy'));

if (!$user->hasRight('sfrsreports', 'reports', 'read')) {
	accessforbidden();
}

// Report type: 'ar' (aged receivables, default) | 'ap' (aged payables)
$type = GETPOST('type', 'aZ');
if ($type !== 'ap') {
	$type = 'ar';
}

// As-of date (default: today)
$asof_str = GETPOST('asof', 'alpha');
if (empty($asof_str)) {
	$asof_str = date('Y-m-d');
}
$asof = dol_stringtotime($asof_str);

$sfrs = new SfrsReport($db);
$data = $sfrs->getAgingReport($type, $asof);

$currency = getDolGlobalString('SFRS_PRESENTATION_CURRENCY', 'SGD');
$uen = getDolGlobalString('SFRS_COMPANY_UEN', '');

// $mysoc (Societe object for current entity) is already populated by main.inc.php.
global $mysoc;

llxHeader('', $langs->trans($type === 'ar' ? 'SFRSARAgingReport' : 'SFRSAPAgingReport'), '');

print load_fiche_titre($langs->trans($type === 'ar' ? 'SFRSARAgingReport' : 'SFRSAPAgingReport'), '', $type === 'ar' ? 'bill' : 'supplier_invoice');

if ($data < 0) {
	print '<div class="error">'.$sfrs->error.'</div>';
	llxFooter();
	$db->close();
	exit;
}

print '<div class="fichecenter">';

// Filter form
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border centpercent">';
print '<tr class="oddeven">';
print '<td width="20%">'.$langs->trans('ReportType').'</td>';
print '<td>';
print '<select name="type" class="flat">';
print '<option value="ar"'.($type === 'ar' ? ' selected' : '').'>'.$langs->trans('SFRSARAgingReport').'</option>';
print '<option value="ap"'.($type === 'ap' ? ' selected' : '').'>'.$langs->trans('SFRSAPAgingReport').'</option>';
print '</select>';
print '</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('AsOfDate').'</td>';
print '<td><input type="date" name="asof" value="'.$asof_str.'" class="flat"></td>';
print '</tr>';
print '</table>';
print '</form>';

print '<br>';

// Company header
print '<table class="border centpercent">';
print '<tr><td class="titlefield" width="20%">'.$langs->trans('Company').'</td><td>'.dol_escape_htmltag($mysoc->name).'</td></tr>';
if (!empty($uen)) {
	print '<tr><td>'.$langs->trans('UEN').'</td><td>'.dol_escape_htmltag($uen).'</td></tr>';
}
print '<tr><td>'.$langs->trans('AsOfDate').'</td><td>'.dol_print_date($asof, 'day').'</td></tr>';
print '<tr><td>'.$langs->trans('PresentationCurrency').'</td><td>'.dol_escape_htmltag($currency).'</td></tr>';
print '</table>';

print '<br>';

// Aging table — one row per third party, buckets by invoice age
print '<table class="border centpercent">';
print '<tr class="liste_titre">';
print '<th class="left">'.$langs->trans('Thirdparty').'</th>';
print '<th class="right" width="14%">'.$langs->trans('OpenAmount').' ('.$currency.')</th>';
print '<th class="right" width="12%">'.$langs->trans('BucketCurrent').'</th>';
print '<th class="right" width="12%">'.$langs->trans('Bucket1to30').'</th>';
print '<th class="right" width="12%">'.$langs->trans('Bucket31to60').'</th>';
print '<th class="right" width="12%">'.$langs->trans('Bucket61to90').'</th>';
print '<th class="right" width="12%">'.$langs->trans('BucketOver90').'</th>';
print '<th class="right" width="14%">'.$langs->trans('OpenAmountOriginal').'</th>';
print '</tr>';

$fmt = function ($v) {
	return ($v != 0) ? price($v, 0, '', 1, -1, 2, $conf->currency) : '-';
};

if (empty($data['thirdparties'])) {
	print '<tr class="oddeven"><td colspan="8" class="center opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
} else {
	foreach ($data['thirdparties'] as $tp) {
		// Original-currency column: "USD 1,234.56; CNY 500.00" (only non-functional invoices)
		$cur_parts = array();
		foreach ($tp['currencies'] as $code => $amt) {
			$cur_parts[] = $code.' '.price($amt, 0, '', 1, -1, 2, $conf->currency);
		}
		$cur_display = !empty($cur_parts) ? implode('; ', $cur_parts) : '-';

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($tp['name']).'</td>';
		print '<td class="right">'.$fmt($tp['open']).'</td>';
		print '<td class="right">'.$fmt($tp['current']).'</td>';
		print '<td class="right">'.$fmt($tp['b30']).'</td>';
		print '<td class="right">'.$fmt($tp['b60']).'</td>';
		print '<td class="right">'.$fmt($tp['b90']).'</td>';
		print '<td class="right">'.$fmt($tp['b90p']).'</td>';
		print '<td class="right">'.$cur_display.'</td>';
		print '</tr>';
	}

	$t = $data['totals'];
	print '<tr class="liste_total">';
	print '<td>'.$langs->trans('Total').'</td>';
	print '<td class="right">'.$fmt($t['open']).'</td>';
	print '<td class="right">'.$fmt($t['current']).'</td>';
	print '<td class="right">'.$fmt($t['b30']).'</td>';
	print '<td class="right">'.$fmt($t['b60']).'</td>';
	print '<td class="right">'.$fmt($t['b90']).'</td>';
	print '<td class="right">'.$fmt($t['b90p']).'</td>';
	print '<td></td>';
	print '</tr>';
}

print '</table>';

print '<br>';

// Ledger reconciliation info — aging total vs control-account balance.
// Differences come from FX revaluation entries (FXREV), manual OD entries and
// postings outside the business subledger; the note explains this.
$range = ($type === 'ar') ? array('1600', '1699') : array('2000', '2099');
$bk = $sfrs->sumByRange($range[0], $range[1], $asof, 'ALL');
// AR control account carries a debit balance, AP a credit balance — report both as positive
$bk_balance = ($type === 'ar') ? $bk['balance'] : -$bk['balance'];
$aging_total = $data['totals']['open'];
$diff = round($aging_total - $bk_balance, 2);

print '<div class="info">';
print '<strong>'.$langs->trans('BookkeepingBalance', $range[0].'-'.$range[1]).':</strong> '.price($bk_balance, 0, '', 1, -1, 2, $conf->currency);
print ' &nbsp;|&nbsp; <strong>'.$langs->trans('AgingTotal').':</strong> '.price($aging_total, 0, '', 1, -1, 2, $conf->currency);
print ' &nbsp;|&nbsp; <strong>'.$langs->trans('Diff').':</strong> '.price($diff, 0, '', 1, -1, 2, $conf->currency);
print '</div>';
print '<br>';
print '<div class="opacitymedium small">';
print $langs->trans('AgingDifferenceNote');
print '<br>';
print $langs->trans('AgingReportNote');
print '</div>';

print '</div>';

llxFooter();
$db->close();
