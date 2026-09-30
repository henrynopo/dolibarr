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
 * \file   htdocs/custom/sfrs_reports/pages/index.php
 * \brief  Entry page for SFRS Reports module — links to BS / P&L / CF
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
	die('Include of main.inc.php failed for SFRS Reports index.php');
}
require_once __DIR__.'/../class/sfrsreport.class.php';

$langs->loadLangs(array('sfrs_reports@sfrs_reports', 'accountancy'));

if (!$user->hasRight('sfrsreports', 'reports', 'read')) {
	accessforbidden();
}

$title = $langs->trans('SFRSReportsHome');

llxHeader('', $title, '');

print load_fiche_titre($title, '', 'accountancy');

print '<div class="fichecenter">';

print '<p>'.$langs->trans('SFRSReportsHomeDesc').'</p>';

print '<table class="border centpercent">';

print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Report').'</th>';
print '<th>'.$langs->trans('Description').'</th>';
print '<th>'.$langs->trans('Action').'</th>';
print '</tr>';

// Balance Sheet row
print '<tr class="oddeven">';
print '<td><strong>'.$langs->trans('SFRSBalanceSheet').'</strong></td>';
print '<td>'.$langs->trans('SFRSBalanceSheetDesc').'</td>';
print '<td><a class="button" href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/balance_sheet.php">';
print img_picto('', 'balance', 'class="pictofixedwidth"').$langs->trans('Open').'</a></td>';
print '</tr>';

// Profit & Loss row
print '<tr class="oddeven">';
print '<td><strong>'.$langs->trans('SFRSProfitLoss').'</strong></td>';
print '<td>'.$langs->trans('SFRSProfitLossDesc').'</td>';
print '<td><a class="button" href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/profit_loss.php">';
print img_picto('', 'accountancy', 'class="pictofixedwidth"').$langs->trans('Open').'</a></td>';
print '</tr>';

// Cash Flow row
print '<tr class="oddeven">';
print '<td><strong>'.$langs->trans('SFRSCashFlow').'</strong></td>';
print '<td>'.$langs->trans('SFRSCashFlowDesc').'</td>';
print '<td><a class="button" href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/cash_flow.php">';
print img_picto('', 'payment', 'class="pictofixedwidth"').$langs->trans('Open').'</a></td>';
print '</tr>';

// AR Aging row
print '<tr class="oddeven">';
print '<td><strong>'.$langs->trans('SFRSARAgingReport').'</strong></td>';
print '<td>'.$langs->trans('SFRSARAgingReportDesc').'</td>';
print '<td><a class="button" href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/aging.php?type=ar">';
print img_picto('', 'bill', 'class="pictofixedwidth"').$langs->trans('Open').'</a></td>';
print '</tr>';

// AP Aging row
print '<tr class="oddeven">';
print '<td><strong>'.$langs->trans('SFRSAPAgingReport').'</strong></td>';
print '<td>'.$langs->trans('SFRSAPAgingReportDesc').'</td>';
print '<td><a class="button" href="'.DOL_URL_ROOT.'/custom/sfrs_reports/pages/aging.php?type=ap">';
print img_picto('', 'supplier_invoice', 'class="pictofixedwidth"').$langs->trans('Open').'</a></td>';
print '</tr>';

print '</table>';

// Show framework currently in use
$framework = getDolGlobalString('SFRS_FRAMEWORK', 'FRS');
$cf_method = getDolGlobalString('SFRS_CASHFLOW_METHOD', 'direct');
$currency = getDolGlobalString('SFRS_PRESENTATION_CURRENCY', 'SGD');

print '<br>';
print '<div class="info">';
print '<strong>'.$langs->trans('CurrentConfiguration').':</strong><br>';
print $langs->trans('Framework').': <em>'.$framework.'</em><br>';
print $langs->trans('CashFlowMethod').': <em>'.$cf_method.'</em><br>';
print $langs->trans('PresentationCurrency').': <em>'.$currency.'</em><br>';
print '</div>';

print '</div>';

llxFooter();
$db->close();