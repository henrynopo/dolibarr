<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        mom_levy.php
 * \ingroup     sgpayroll
 * \brief       MOM Foreign Worker Levy (FWL) Statistics & Payment Tracking
 *              Aggregates FWL amounts from approved payslips by month.
 *              NOTE: FWL is collected by MOM via GIRO, separate from CPFEzPay.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->admin && !$user->hasRight('sgpayroll', 'employee', 'read')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm'));

$entity     = (int)($conf->entity ?? 1);
$action     = GETPOST('action', 'aZ09');
$searchYear = GETPOSTINT('search_year') ?: (int)dol_print_date(dol_now(), '%Y');

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($action === 'export_fwl') {
	$sql  = "SELECT p.pay_year, p.pay_month, u.lastname, u.firstname,";
	$sql .= " e.nric_fin, e.pass_type, pl.fwl_amount, pl.sdl_amount,";
	$sql .= " pl.ordinary_wages, pl.status";
	$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = ".$entity;
	$sql .= " WHERE p.pay_year = ".(int)$searchYear." AND pl.fwl_amount > 0";
	$sql .= " AND pl.entity = ".$entity." AND pl.status = 'approved'";
	$sql .= " ORDER BY p.pay_month, u.lastname";

	$resql = $db->query($sql);
	$lines = array();
	$lines[] = implode(',', array('"Year"','"Month"','"Full Name"','"NRIC/FIN"','"Pass Type"','"OW"','"FWL Amount ($)"','"SDL Amount ($)"'));
	while ($obj = $db->fetch_object($resql)) {
		$lines[] = implode(',', array(
			$obj->pay_year,
			$obj->pay_month,
			'"'.dol_string_unaccent(sgpayroll_csv_safe(sgpayroll_format_employee_name($obj->firstname, $obj->lastname))).'"',
			'"'.($obj->nric_fin ?? '').'"',
			'"'.($obj->pass_type ?? '').'"',
			number_format((float)$obj->ordinary_wages, 2, '.', ''),
			number_format((float)$obj->fwl_amount, 2, '.', ''),
			number_format((float)$obj->sdl_amount, 2, '.', ''),
		));
	}
	$filename = 'MOM_FWL_'.sprintf('%04d', $searchYear).'.csv';
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo "\xEF\xBB\xBF";
	echo implode("\n", $lines);
	exit;
}

// ── Query: Monthly FWL/SDL totals for the year ────────────────────────────────
$sql  = "SELECT p.pay_month, COUNT(pl.fk_user) AS worker_count,";
$sql .= " SUM(pl.fwl_amount) AS total_fwl, SUM(pl.sdl_amount) AS total_sdl,";
$sql .= " SUM(CASE WHEN pl.status = 'approved' THEN pl.fwl_amount ELSE 0 END) AS approved_fwl";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql .= " WHERE p.pay_year = ".(int)$searchYear." AND pl.fwl_amount > 0";
$sql .= " AND pl.entity = ".$entity;
$sql .= " GROUP BY p.pay_month ORDER BY p.pay_month";

$resql    = $db->query($sql);
$monthly  = array();
$yearTotals = array('fwl' => 0, 'sdl' => 0, 'workers' => 0);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$monthly[(int)$obj->pay_month] = $obj;
		$yearTotals['fwl']     += (float)$obj->total_fwl;
		$yearTotals['sdl']     += (float)$obj->total_sdl;
		$yearTotals['workers'] = max($yearTotals['workers'], (int)$obj->worker_count);
	}
}

// ── Query: Employee-level FWL detail ─────────────────────────────────────────
$sqlEmp  = "SELECT u.lastname, u.firstname, e.nric_fin, e.pass_type,";
$sqlEmp .= " SUM(pl.fwl_amount) AS annual_fwl, SUM(pl.sdl_amount) AS annual_sdl,";
$sqlEmp .= " COUNT(pl.rowid) AS months_count";
$sqlEmp .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlEmp .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlEmp .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sqlEmp .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = ".$entity;
$sqlEmp .= " WHERE p.pay_year = ".(int)$searchYear." AND pl.fwl_amount > 0";
$sqlEmp .= " AND pl.entity = ".$entity." AND pl.status = 'approved'";
$sqlEmp .= " GROUP BY pl.fk_user, u.lastname, u.firstname, e.nric_fin, e.pass_type";
$sqlEmp .= " ORDER BY u.lastname";

$resEmp = $db->query($sqlEmp);
$empRows = array();
if ($resEmp) {
	while ($obj = $db->fetch_object($resEmp)) $empRows[] = $obj;
}

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
llxHeader('', $langs->trans('MomLevy').' '.$searchYear, '');

print load_fiche_titre(
	'<i class="fas fa-money-bill-wave text-warning"></i> '.$langs->trans('MomLevy'),
	'', 'salary'
);

// Year filter
print '<form method="GET" action="mom_levy.php" style="margin-bottom:10px">';
print $langs->trans('Year').': <input type="number" name="search_year" value="'.$searchYear.'" min="2020" max="2050" class="flat width75"> ';
print '<button type="submit" class="button">'.$langs->trans('Apply').'</button>';
print '</form>';

// Annual summary
print '<div class="fichecenter"><table class="border centpercent"><tr>';
print '<td class="center"><div style="font-size:1.6em;font-weight:bold;color:#e67e22">'.price($yearTotals['fwl']).'</div>';
print '<div style="color:#999">'.$langs->trans('AnnualFWL').' '.$searchYear.'</div></td>';
print '<td class="center"><div style="font-size:1.6em;font-weight:bold;color:#2980b9">'.price($yearTotals['sdl']).'</div>';
print '<div style="color:#999">'.$langs->trans('AnnualSDL').' '.$searchYear.'</div></td>';
print '<td class="center"><div style="font-size:1.6em;font-weight:bold">'.$yearTotals['workers'].'</div>';
print '<div style="color:#999">'.$langs->trans('ForeignWorkers').'</div></td>';
print '</tr></table></div><br>';

// Monthly breakdown
print '<h3>'.$langs->trans('MonthlyBreakdown').'</h3>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Month').'</td>';
print '<td class="right">'.$langs->trans('ForeignWorkers').'</td>';
print '<td class="right">'.$langs->trans('FWLAmount').'</td>';
print '<td class="right">'.$langs->trans('SDLAmount').'</td>';
print '<td class="right">'.$langs->trans('TotalMOM').'</td>';
print '</tr>';

$monthNames = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
                    7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December');
$i = 0;
for ($m = 1; $m <= 12; $m++) {
	$row = $monthly[$m] ?? null;
	$fwl = $row ? (float)$row->total_fwl : 0;
	$sdl = $row ? (float)$row->total_sdl : 0;
	$cnt = $row ? (int)$row->worker_count : 0;
	$cls = $fwl > 0 ? '' : 'style="color:#ccc"';
	print '<tr class="'.($i++ % 2 ? 'pair' : 'impair').'">';
	print '<td '.$cls.'>'.$monthNames[$m].' '.$searchYear.'</td>';
	print '<td class="right" '.$cls.'>'.($cnt ?: '—').'</td>';
	print '<td class="right" '.$cls.'>'.($fwl > 0 ? price($fwl) : '—').'</td>';
	print '<td class="right" '.$cls.'>'.($sdl > 0 ? price($sdl) : '—').'</td>';
	print '<td class="right" '.($fwl > 0 ? 'style="font-weight:bold"' : $cls).'>'.($fwl + $sdl > 0 ? price($fwl + $sdl) : '—').'</td>';
	print '</tr>';
}
print '<tr class="liste_total"><td><b>'.$langs->trans('Total').' '.$searchYear.'</b></td>';
print '<td></td>';
print '<td class="right"><b>'.price($yearTotals['fwl']).'</b></td>';
print '<td class="right"><b>'.price($yearTotals['sdl']).'</b></td>';
print '<td class="right"><b>'.price($yearTotals['fwl'] + $yearTotals['sdl']).'</b></td>';
print '</tr></table><br>';

// Export + Per-employee table
if (!empty($empRows)) {
	print '<form method="GET" action="mom_levy.php">';
	print '<input type="hidden" name="action" value="export_fwl">';
	print '<input type="hidden" name="search_year" value="'.$searchYear.'">';
	print '<button type="submit" class="butAction"><i class="fas fa-download"></i> '.$langs->trans('ExportFWL').' CSV</button>';
	print '</form><br>';

	print '<h3>'.$langs->trans('PerEmployeeFWL').'</h3>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Employee').'</td>';
	print '<td>'.$langs->trans('NRIC').'</td>';
	print '<td>'.$langs->trans('PassType').'</td>';
	print '<td class="right">'.$langs->trans('MonthsOnPayroll').'</td>';
	print '<td class="right">'.$langs->trans('AnnualFWL').'</td>';
	print '<td class="right">'.$langs->trans('AnnualSDL').'</td>';
	print '</tr>';
	$i = 0;
	foreach ($empRows as $r) {
		print '<tr class="'.($i++ % 2 ? 'pair' : 'impair').'">';
		print '<td>'.dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</td>';
		print '<td>'.dol_escape_htmltag($r->nric_fin ?? '—').'</td>';
		print '<td>'.dol_escape_htmltag($r->pass_type ?? '—').'</td>';
		print '<td class="right">'.(int)$r->months_count.'</td>';
		print '<td class="right"><b>'.price($r->annual_fwl).'</b></td>';
		print '<td class="right">'.price($r->annual_sdl).'</td>';
		print '</tr>';
	}
	print '</table>';
}

print '<br><div class="info"><i class="fas fa-info-circle"></i> '.$langs->trans('FWLNote').'</div>';

llxFooter();
$db->close();
