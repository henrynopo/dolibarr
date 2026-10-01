<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        iras_wht_review.php
 * \ingroup sghr
 * \brief       IRAS Withholding Tax (WHT) Monthly Review — IR37A
 *              Applicable to non-resident employees only.
 *              Employer must declare and remit WHT by the 15th of the following month.
 *
 *  Singapore WHT rates for employment income (Section 45):
 *   - Non-resident employee: 15% on gross income (or progressive rates, whichever higher)
 *   - Director's fee (non-resident): 24%
 *   Source: IRAS website, Section 45 Income Tax Act
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('sghr/lib/sghr.lib.php');

if (!isModEnabled('sghr')) accessforbidden();
if (!$user->admin && !$user->hasRight('sghr', 'ais', 'review')) accessforbidden();

$langs->loadLangs(array('sghr@sghr', 'hrm'));
$hookmanager->initHooks(array('sghrwhtreview'));

$entity     = (int)($conf->entity ?? 1);
$action     = GETPOST('action', 'aZ09');
$searchYear = GETPOSTINT('search_year')  ?: (int)dol_print_date(dol_now(), '%Y');
$searchMonth= GETPOSTINT('search_month') ?: (int)dol_print_date(dol_now(), '%m');

if (GETPOST('button_removefilter_x', 'alpha')) {
	$searchYear  = (int)dol_print_date(dol_now(), '%Y');
	$searchMonth = (int)dol_print_date(dol_now(), '%m');
}

// ── CSV Export (IR37A data) ───────────────────────────────────────────────────
if ($action === 'export_wht') {
	$sql  = "SELECT u.lastname, u.firstname, e.nric_fin, e.tax_residency, e.citizenship,";
	$sql .= " pl.ordinary_wages, pl.additional_wages,";
	$sql .= " pl.gross_salary, pl.withholding_tax, pl.status";
	$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = ".$entity;
	$sql .= " WHERE p.pay_year = ".(int)$searchYear." AND p.pay_month = ".(int)$searchMonth;
	$sql .= " AND pl.withholding_tax > 0 AND pl.entity = ".$entity;
	$sql .= " ORDER BY u.lastname, u.firstname";

	$resql  = $db->query($sql);
	$lines  = array();
	$lines[] = implode(',', array('"Full Name"','"NRIC/FIN"','"Tax Residency"','"Citizenship"','"OW"','"AW"','"Gross Income"','"WHT Amount ($)"','"WHT Rate"','"Status"'));
	$dueDate = sprintf('%04d-%02d-15', $searchMonth == 12 ? $searchYear + 1 : $searchYear, $searchMonth == 12 ? 1 : $searchMonth + 1);
	while ($obj = $db->fetch_object($resql)) {
		$whtRate = $obj->gross_salary > 0 ? round(($obj->withholding_tax / $obj->gross_salary) * 100, 1).'%' : '—';
		$lines[] = implode(',', array(
			'"'.dol_string_unaccent(sgpayroll_csv_safe(sgpayroll_format_employee_name($obj->firstname, $obj->lastname))).'"',
			'"'.($obj->nric_fin ?? '').'"',
			'"'.ucfirst($obj->tax_residency ?? 'non_resident').'"',
			'"'.($obj->citizenship ?? '').'"',
			number_format((float)$obj->ordinary_wages, 2, '.', ''),
			number_format((float)$obj->additional_wages, 2, '.', ''),
			number_format((float)$obj->gross_salary, 2, '.', ''),
			number_format((float)$obj->withholding_tax, 2, '.', ''),
			'"'.$whtRate.'"',
			'"'.ucfirst($obj->status ?? '').'"',
		));
	}
	$filename = 'IRAS_WHT_IR37A_'.sprintf('%04d%02d', $searchYear, $searchMonth).'.csv';
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo "\xEF\xBB\xBF";
	echo implode("\n", $lines);
	exit;
}

// ── Query: WHT summary for selected month ─────────────────────────────────────
$sql  = "SELECT pl.rowid, pl.fk_user, pl.status,";
$sql .= " u.lastname, u.firstname,";
$sql .= " e.nric_fin, e.tax_residency, e.citizenship, e.pass_type,";
$sql .= " pl.ordinary_wages, pl.additional_wages, pl.gross_salary,";
$sql .= " pl.withholding_tax, pl.net_pay,";
$sql .= " p.pay_year, p.pay_month";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = ".$entity;
$sql .= " WHERE p.pay_year = ".(int)$searchYear." AND p.pay_month = ".(int)$searchMonth;
$sql .= " AND pl.withholding_tax > 0 AND pl.entity = ".$entity;
$sql .= " ORDER BY u.lastname, u.firstname";

$resql  = $db->query($sql);
$rows   = array();
$totals = array('gross' => 0, 'wht' => 0, 'net' => 0, 'approved' => 0, 'total' => 0);

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		$totals['gross']  += (float)$obj->gross_salary;
		$totals['wht']    += (float)$obj->withholding_tax;
		$totals['net']    += (float)$obj->net_pay;
		$totals['total']++;
		if ($obj->status === 'approved') $totals['approved']++;
	}
}

// Deadline: 15th of following month
$dueMonth = $searchMonth == 12 ? 1 : $searchMonth + 1;
$dueYear  = $searchMonth == 12 ? $searchYear + 1 : $searchYear;
$dueDate  = sprintf('%04d-%02d-15', $dueYear, $dueMonth);
$daysLeft = (int)((strtotime($dueDate) - dol_now()) / 86400);

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
$monthLabel = dol_print_date(dol_mktime(0, 0, 0, $searchMonth, 1, $searchYear), '%B %Y');

llxHeader('', $langs->trans('IrasWhtReview').' — '.$monthLabel, '');

print load_fiche_titre(
	'<i class="fas fa-percentage text-danger"></i> '.$langs->trans('IrasWhtReview'),
	'', 'bill'
);

// ── Compliance deadline banner ────────────────────────────────────────────────
$deadlineClass = $daysLeft <= 5 ? 'error' : ($daysLeft <= 15 ? 'warning' : 'info');
print '<div class="'.$deadlineClass.'">';
print '<i class="fas fa-clock"></i> ';
print sprintf($langs->trans('WhtDeadline'), dol_print_date(dol_stringtotime($dueDate), 'day'));
if ($daysLeft >= 0) {
	print ' &nbsp;<b>('.$daysLeft.' '.$langs->trans('DaysLeft').')</b>';
} else {
	print ' &nbsp;<b style="color:red">('.$langs->trans('Overdue').')</b>';
}
print '</div><br>';

// ── Filter bar ────────────────────────────────────────────────────────────────
print '<form method="GET" action="iras_wht_review.php">';
print '<div class="fichecenter"><table class="border centpercent"><tr>';
print '<td class="titlefield">'.$langs->trans('Year').'</td>';
print '<td><input type="number" name="search_year" value="'.$searchYear.'" min="2020" max="2050" class="flat width75"></td>';
print '<td>'.$langs->trans('Month').'</td>';
print '<td>'.sghr_select_month($searchMonth, 'search_month').'</td>';
print '<td><input type="submit" class="button" value="'.$langs->trans('Search').'"></td>';
print '<td><input type="submit" name="button_removefilter_x" class="button" value="'.$langs->trans('Reset').'"></td>';
print '</tr></table></div>';
print '</form><br>';

if (empty($rows)) {
	print '<div class="info"><i class="fas fa-check-circle"></i> '.$langs->trans('NoWHTEmployees').'</div>';
	print '<p style="color:#666;font-size:0.9em">'.$langs->trans('NoWHTExplain').'</p>';
	llxFooter();
	$db->close();
	exit;
}

// ── Summary boxes ─────────────────────────────────────────────────────────────
print '<div class="fichecenter"><table class="border centpercent"><tr>';
$summaryItems = array(
	$langs->trans('NonResidentEmployees') => array($totals['total'], '#e74c3c'),
	$langs->trans('TotalGrossIncome') => array(price($totals['gross']), '#2c3e50'),
	$langs->trans('WHTPayable').' (IR37A)' => array(price($totals['wht']), '#e67e22'),
	$langs->trans('NetPayAfterWHT') => array(price($totals['net']), '#27ae60'),
);
foreach ($summaryItems as $label => $data) {
	print '<td class="center"><div style="font-size:1.5em;font-weight:bold;color:'.$data[1].'">'.$data[0].'</div>';
	print '<div style="color:#666;font-size:0.9em">'.$label.'</div></td>';
}
print '</tr></table></div><br>';

// Status check
if ($totals['approved'] < $totals['total']) {
	$pending = $totals['total'] - $totals['approved'];
	print '<div class="warning"><i class="fas fa-exclamation-triangle"></i> '.
		sprintf($langs->trans('WHTNotAllApproved'), $pending).'</div><br>';
}

// ── Export button ─────────────────────────────────────────────────────────────
print '<form method="GET" action="iras_wht_review.php">';
print '<input type="hidden" name="action" value="export_wht">';
print '<input type="hidden" name="search_year" value="'.$searchYear.'">';
print '<input type="hidden" name="search_month" value="'.$searchMonth.'">';
print '<button type="submit" class="butAction"><i class="fas fa-download"></i> '.
	$langs->trans('ExportWHT').' (IR37A) — '.sprintf('%04d/%02d', $searchYear, $searchMonth).'</button>';
print '</form><br>';

// ── Per-employee table ────────────────────────────────────────────────────────
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Employee').'</td>';
print '<td>'.$langs->trans('NRIC').'</td>';
print '<td>'.$langs->trans('Citizenship').' / '.$langs->trans('PassType').'</td>';
print '<td class="right">'.$langs->trans('GrossIncome').'</td>';
print '<td class="right">'.$langs->trans('WHTRate').'</td>';
print '<td class="right">'.$langs->trans('WHTAmount').'</td>';
print '<td class="right">'.$langs->trans('NetPay').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '</tr>';

$i = 0;
foreach ($rows as $r) {
	$whtRate = $r->gross_salary > 0
		? round(((float)$r->withholding_tax / (float)$r->gross_salary) * 100, 1).'%'
		: '—';
	$statusClass = $r->status === 'approved' ? 'badge-status4' : 'badge-status1';
	$statusLabel = $langs->trans(ucfirst($r->status));

	print '<tr class="'.($i++ % 2 ? 'pair' : 'impair').'">';
	print '<td><a href="payslip_card.php?id='.(int)$r->rowid.'">'.
		dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</a></td>';
	print '<td>'.dol_escape_htmltag($r->nric_fin ?? '—').'</td>';
	print '<td>'.dol_escape_htmltag($r->citizenship ?? '—').
		($r->pass_type ? ' <span class="badge badge-status1">'.dol_escape_htmltag($r->pass_type).'</span>' : '').'</td>';
	print '<td class="right">'.price($r->gross_salary).'</td>';
	print '<td class="right"><b>'.$whtRate.'</b></td>';
	print '<td class="right"><b class="text-danger">'.price($r->withholding_tax).'</b></td>';
	print '<td class="right">'.price($r->net_pay).'</td>';
	print '<td class="center"><span class="badge '.$statusClass.'">'.$statusLabel.'</span></td>';
	print '</tr>';
}

// Totals row
print '<tr class="liste_total">';
print '<td colspan="3"><b>'.$langs->trans('Total').' ('.$totals['total'].' '.$langs->trans('NonResidents').')</b></td>';
print '<td class="right"><b>'.price($totals['gross']).'</b></td>';
print '<td></td>';
print '<td class="right"><b class="text-danger">'.price($totals['wht']).'</b></td>';
print '<td class="right"><b>'.price($totals['net']).'</b></td>';
print '<td></td>';
print '</tr>';
print '</table><br>';

// Regulatory note
print '<div class="info">';
print '<i class="fas fa-gavel"></i> <b>'.$langs->trans('WHTRegulatoryNote').'</b><br>';
print '<ul style="margin:5px 0 0 20px">';
print '<li>'.$langs->trans('WHTNote1').'</li>';
print '<li>'.$langs->trans('WHTNote2').'</li>';
print '<li>'.$langs->trans('WHTNote3').'</li>';
print '</ul>';
print '</div>';

llxFooter();
$db->close();
