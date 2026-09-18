<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        cpf_review.php
 * \ingroup     sgpayroll
 * \brief       Monthly CPF & Statutory Levies Review (CPF / SDL / SHG)
 *              Aggregates approved payslips for CPFEzPay submission.
 *              Note: FWL is collected by MOM separately (mom_levy.php).
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/class/payrollrecord.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/class/payrollcalc.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

// ── Security ──────────────────────────────────────────────────────────────────
if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->admin && !$user->hasRight('sgpayroll', 'payroll', 'approve')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm'));

// ── Parameters ────────────────────────────────────────────────────────────────
$action     = GETPOST('action', 'aZ09');
$searchYear = GETPOSTINT('search_year')  ?: (int) dol_print_date(dol_now(), '%Y');
$searchMonth= GETPOSTINT('search_month') ?: (int) dol_print_date(dol_now(), '%m');
$entity     = (int) ($conf->entity ?? 1);

// Align period navigation with payslip_list.php (Prev / Next month controls)
if (GETPOST('button_prev_month', 'alpha')) {
	$searchMonth = $searchMonth ?: (int) dol_print_date(dol_now(), '%m');
	$searchMonth--;
	if ($searchMonth < 1) { $searchMonth = 12; $searchYear--; }
} elseif (GETPOST('button_next_month', 'alpha')) {
	$searchMonth = $searchMonth ?: (int) dol_print_date(dol_now(), '%m');
	$searchMonth++;
	if ($searchMonth > 12) { $searchMonth = 1; $searchYear++; }
}

if (GETPOST('button_removefilter_x', 'alpha')) {
	$searchYear  = (int) dol_print_date(dol_now(), '%Y');
	$searchMonth = (int) dol_print_date(dol_now(), '%m');
}

// ── CPFEzPay Export Action ────────────────────────────────────────────────────
if ($action === 'export_cpfezpay') {
	// Query approved payslips for the selected month/year
	$sql  = "SELECT pl.fk_user, u.lastname, u.firstname, e.nric_fin, e.citizenship,";
	$sql .= " pl.ordinary_wages, pl.additional_wages,";
	$sql .= " pl.employee_cpf, pl.employer_cpf,";
	$sql .= " pl.sdl_amount, pl.shg_cdac, pl.shg_ecf, pl.shg_mbmf, pl.shg_sinda";
	$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = ".$entity;
	$sql .= " WHERE pl.status = 'approved'";
	$sql .= " AND p.pay_year = ".(int)$searchYear." AND p.pay_month = ".(int)$searchMonth;
	$sql .= " AND pl.entity = ".$entity;
	$sql .= " ORDER BY u.lastname, u.firstname";

	$res = $db->query($sql);
	if (!$res) {
		setEventMessages($db->lasterror(), null, 'errors');
	} else {
		// ── Build CPFEzPay flat-text file ──────────────────────────────────
		// Format: based on CPF Board CPFEzPay v2 spec (tab-delimited)
		$lines   = array();
		$recCount = 0;
		$totalEmpCPF = 0; $totalErCPF = 0; $totalSDL = 0; $totalSHG = 0;

		while ($obj = $db->fetch_object($res)) {
			$nric    = str_pad(strtoupper($obj->nric_fin ?? ''), 9);
			$name    = str_pad(sgpayroll_csv_safe(dol_string_unaccent(sgpayroll_format_employee_name($obj->firstname, $obj->lastname))), 66);
			$ow      = number_format((float)$obj->ordinary_wages,    2, '.', '');
			$aw      = number_format((float)$obj->additional_wages,  2, '.', '');
			$empCpf  = number_format((float)$obj->employee_cpf,      2, '.', '');
			$erCpf   = number_format((float)$obj->employer_cpf,      2, '.', '');
			$sdl     = number_format((float)$obj->sdl_amount,        2, '.', '');
			$cdac    = number_format((float)$obj->shg_cdac,          2, '.', '');
			$ecf     = number_format((float)$obj->shg_ecf,           2, '.', '');
			$mbmf    = number_format((float)$obj->shg_mbmf,          2, '.', '');
			$sinda   = number_format((float)$obj->shg_sinda,         2, '.', '');
			$totalShg = number_format(
				(float)$obj->shg_cdac + (float)$obj->shg_ecf + (float)$obj->shg_mbmf + (float)$obj->shg_sinda,
				2, '.', ''
			);

			// CPFEzPay record line:
			// NRIC | Name | OW | AW | EmpCPF | ErCPF | SDL | CDAC | ECF | MBMF | SINDA
			$lines[] = implode("\t", array($nric, trim($name), $ow, $aw, $empCpf, $erCpf, $sdl, $cdac, $ecf, $mbmf, $sinda));
			$recCount++;
			$totalEmpCPF += (float)$obj->employee_cpf;
			$totalErCPF  += (float)$obj->employer_cpf;
			$totalSDL    += (float)$obj->sdl_amount;
			$totalSHG    += (float)$obj->shg_cdac + (float)$obj->shg_ecf + (float)$obj->shg_mbmf + (float)$obj->shg_sinda;
		}

		// Official: SDL organisation total rounded down to nearest dollar
		$totalSDLRounded = SGPayrollCalc::getOrganisationSDLTotalRounded($totalSDL);

		// Header + trailer
		$header  = "CPFEZPAY\t".sprintf('%04d%02d', $searchYear, $searchMonth)."\t".$recCount;
		$trailer = "TOTAL\t\t\t\t".number_format($totalEmpCPF,2,'.','')."\t".number_format($totalErCPF,2,'.','')."\t".number_format($totalSDLRounded,2,'.','')."\t\t\t\t\t".number_format($totalSHG,2,'.','');

		$content = $header."\n".implode("\n", $lines)."\n".$trailer."\n";
		$filename = 'CPFEzPay_'.sprintf('%04d%02d', $searchYear, $searchMonth).'.txt';

		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Content-Length: '.strlen($content));
		echo $content;
		exit;
	}
}

// ── Query — monthly CPF/SDL/SHG summary ──────────────────────────────────────
$sql  = "SELECT pl.rowid, pl.fk_user, u.lastname, u.firstname,";
$sql .= " e.nric_fin, e.citizenship,";
$sql .= " pl.ordinary_wages, pl.additional_wages,";
$sql .= " pl.employee_cpf, pl.employer_cpf,";
$sql .= " pl.sdl_amount,";
$sql .= " (pl.shg_cdac + pl.shg_ecf + pl.shg_mbmf + pl.shg_sinda) AS shg_total,";
$sql .= " pl.shg_cdac, pl.shg_ecf, pl.shg_mbmf, pl.shg_sinda,";
$sql .= " pl.status, p.pay_year, p.pay_month";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = ".$entity;
$sql .= " WHERE p.pay_year = ".(int)$searchYear." AND p.pay_month = ".(int)$searchMonth;
$sql .= " AND pl.entity = ".$entity;
$sql .= " ORDER BY u.lastname, u.firstname";

$resql = $db->query($sql);
$rows  = array();
$totals = array(
	'ow' => 0, 'aw' => 0,
	'emp_cpf' => 0, 'er_cpf' => 0,
	'sdl' => 0, 'shg' => 0,
	'shg_cdac' => 0, 'shg_ecf' => 0, 'shg_mbmf' => 0, 'shg_sinda' => 0,
	'approved_count' => 0, 'total_count' => 0,
);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		$totals['ow']       += (float)$obj->ordinary_wages;
		$totals['aw']       += (float)$obj->additional_wages;
		$totals['emp_cpf']  += (float)$obj->employee_cpf;
		$totals['er_cpf']   += (float)$obj->employer_cpf;
		$totals['sdl']      += (float)$obj->sdl_amount;
		$totals['shg']      += (float)$obj->shg_total;
		$totals['shg_cdac'] += (float)$obj->shg_cdac;
		$totals['shg_ecf']  += (float)$obj->shg_ecf;
		$totals['shg_mbmf'] += (float)$obj->shg_mbmf;
		$totals['shg_sinda']+= (float)$obj->shg_sinda;
		$totals['total_count']++;
		if ($obj->status === 'approved') $totals['approved_count']++;
	}
}

$pendingCount = $totals['total_count'] - $totals['approved_count'];

// Official SDL total: round down to nearest dollar (MOM/SSG)
$sdlTotalRounded = SGPayrollCalc::getOrganisationSDLTotalRounded($totals['sdl']);

// ── Output: HTML ──────────────────────────────────────────────────────────────
$form = new Form($db);
$monthLabel = dol_print_date(dol_mktime(0, 0, 0, $searchMonth, 1, $searchYear), '%B %Y');

llxHeader('', $langs->trans('CpfStatutory').' — '.$monthLabel, '');

print load_fiche_titre(
	'<i class="fas fa-circle text-primary"></i> '.$langs->trans('CpfStatutory'),
	'', 'salary'
);

// ── Filter bar (Period selector aligned with payslip_list.php) ───────────────
print '<div class="div-table-responsive-filter" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e1e5eb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
print '<form method="GET" action="cpf_review.php" style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end;">';

print '<div class="filter_field"><label style="display:block; font-weight:600; margin-bottom:5px; color:#444;">'.$langs->trans('Period').':</label>';
print '<div style="display:inline-flex; align-items:center; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; background: #fff;">';
print '<button type="submit" name="button_prev_month" value="1" title="'.$langs->trans('Previous').'" style="background:#f4f4f4; border:none; border-right:1px solid #ccc; padding:6px 12px; cursor:pointer; color:#555;"><i class="fas fa-chevron-left"></i></button>';

// Month select
print '<select name="search_month" class="flat" style="border:none; outline:none; color:#333; min-width:95px; padding:6px; font-weight:bold; cursor:pointer;" onchange="this.form.submit();">';
print '<option value="0"></option>';
foreach(array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December') as $m=>$label) {
	print '<option value="'.$m.'"'.((int)$searchMonth===$m?' selected':'').'>'.$langs->trans($label).'</option>';
}
print '</select>';

// Year select
print '<select name="search_year" class="flat" style="border:none; outline:none; color:#333; min-width:75px; padding:6px; font-weight:bold; cursor:pointer; border-left:1px solid #eee;" onchange="this.form.submit();">';
for($y = 2020; $y <= 2050; $y++) {
	print '<option value="'.$y.'"'.((int)$searchYear==$y?' selected':'').'>'.$y.'</option>';
}
print '</select>';

print '<button type="submit" name="button_next_month" value="1" title="'.$langs->trans('Next').'" style="background:#f4f4f4; border:none; border-left:1px solid #ccc; padding:6px 12px; cursor:pointer; color:#555;"><i class="fas fa-chevron-right"></i></button>';
print '</div></div>';

// Reset / Apply buttons
print '<div style="margin-bottom:2px;">';
print '<input type="submit" class="button" value="'.$langs->trans('Search').'"> ';
print '<input type="submit" name="button_removefilter_x" class="button" value="'.$langs->trans('Reset').'">';
print '</div>';

print '</form>';
print '</div>';

// ── Summary Boxes ─────────────────────────────────────────────────────────────
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('CPFSummary').'</td></tr>';
print '<tr><td>'.($langs->trans('OrdinaryWages')).'</td><td class="right"><b>'.price($totals['ow']).'</b></td></tr>';
print '<tr><td>'.($langs->trans('AdditionalWages')).'</td><td class="right"><b>'.price($totals['aw']).'</b></td></tr>';
print '<tr><td>'.$langs->trans('EmployeeCPF').'</td><td class="right"><b>'.price($totals['emp_cpf']).'</b></td></tr>';
print '<tr><td>'.$langs->trans('EmployerCPF').'</td><td class="right"><b>'.price($totals['er_cpf']).'</b></td></tr>';
print '<tr class="liste_titre"><td>'.$langs->trans('TotalCPF').'</td><td class="right"><b>'.price($totals['emp_cpf'] + $totals['er_cpf']).'</b></td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('StatutoryLevies').'</td></tr>';
print '<tr><td>SDL '.$langs->trans('SkillsDevelopmentLevy').'</td><td class="right"><b>'.price($sdlTotalRounded).'</b></td></tr>';
// SHG sub-items: indent and lighter style to distinguish from main categories
$shgSubStyle = 'padding-left: 1.4em; font-size: 0.95em; color: #555;';
print '<tr><td style="'.$shgSubStyle.'">SHG — CDAC</td><td class="right">'.price($totals['shg_cdac']).'</td></tr>';
print '<tr><td style="'.$shgSubStyle.'">SHG — ECF</td><td class="right">'.price($totals['shg_ecf']).'</td></tr>';
print '<tr><td style="'.$shgSubStyle.'">SHG — MBMF</td><td class="right">'.price($totals['shg_mbmf']).'</td></tr>';
print '<tr><td style="'.$shgSubStyle.'">SHG — SINDA</td><td class="right">'.price($totals['shg_sinda']).'</td></tr>';
print '<tr><td>'.$langs->trans('SHGDonations').' '.$langs->trans('Total').'</td><td class="right"><b>'.price($totals['shg']).'</b></td></tr>';
print '<tr class="liste_titre"><td>'.$langs->trans('GrandTotal').'</td><td class="right"><b class="text-primary">'.price($totals['emp_cpf'] + $totals['er_cpf'] + $sdlTotalRounded + $totals['shg']).'</b></td></tr>';
print '</table>';
print '<br>';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SubmissionStatus').'</td></tr>';
print '<tr><td>'.$langs->trans('TotalPayslips').'</td><td class="right">'.$totals['total_count'].'</td></tr>';
print '<tr><td><span class="badge badge-status4">'.$langs->trans('Approved').'</span></td><td class="right">'.$totals['approved_count'].'</td></tr>';
if ($pendingCount > 0) {
	print '<tr><td><span class="badge badge-status1">'.$langs->trans('Draft').'/'.$langs->trans('Pending').'</span></td><td class="right text-warning">'.$pendingCount.'</td></tr>';
}
print '</table>';
print '</div>';
print '</div>'; // fichecenter

// ── Export Button ─────────────────────────────────────────────────────────────
print '<br>';
if ($totals['approved_count'] > 0) {
	print '<form method="GET" action="cpf_review.php">';
	print '<input type="hidden" name="action" value="export_cpfezpay">';
	print '<input type="hidden" name="search_year" value="'.$searchYear.'">';
	print '<input type="hidden" name="search_month" value="'.$searchMonth.'">';
	print '<button type="submit" class="butAction"><i class="fas fa-download"></i> '.$langs->trans('ExportCPFEzPay').' ('.$totals['approved_count'].' '.$langs->trans('Employees').')</button>';
	print '</form>';
} else {
	print '<div class="info">'.$langs->trans('NoCPFApprovedPayslips').'</div>';
}

if ($pendingCount > 0) {
	print '<div class="warning">'.sprintf($langs->trans('CPFPendingWarning'), $pendingCount).'</div>';
}

// ── Per-Employee Table ────────────────────────────────────────────────────────
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Employee').'</td>';
print '<td>'.$langs->trans('NRIC').'</td>';
print '<td class="right">'.$langs->trans('OrdinaryWages').'</td>';
print '<td class="right">'.$langs->trans('AdditionalWages').'</td>';
print '<td class="right">'.$langs->trans('EmployeeCPF').'</td>';
print '<td class="right">'.$langs->trans('EmployerCPF').'</td>';
print '<td class="right">SDL</td>';
print '<td class="right">CDAC</td>';
print '<td class="right">ECF</td>';
print '<td class="right">MBMF</td>';
print '<td class="right">SINDA</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '</tr>';

$i = 0;
foreach ($rows as $r) {
	$statusClass = $r->status === 'approved' ? 'badge-status4' : 'badge-status1';
	$statusLabel = $langs->trans(ucfirst($r->status));

	print '<tr class="'.($i++ % 2 ? 'pair' : 'impair').'">';
	print '<td><a href="payslip_card.php?id='.(int)$r->rowid.'">'.dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</a></td>';
	print '<td>'.dol_escape_htmltag($r->nric_fin ?? '—').'</td>';
	print '<td class="right">'.price($r->ordinary_wages).'</td>';
	print '<td class="right">'.price($r->additional_wages).'</td>';
	print '<td class="right"><b>'.price($r->employee_cpf).'</b></td>';
	print '<td class="right"><b>'.price($r->employer_cpf).'</b></td>';
	print '<td class="right">'.price($r->sdl_amount).'</td>';
	print '<td class="right">'.price($r->shg_cdac).'</td>';
	print '<td class="right">'.price($r->shg_ecf).'</td>';
	print '<td class="right">'.price($r->shg_mbmf).'</td>';
	print '<td class="right">'.price($r->shg_sinda).'</td>';
	print '<td class="center"><span class="badge '.$statusClass.'">'.$statusLabel.'</span></td>';
	print '</tr>';
}

// Totals row
print '<tr class="liste_total">';
print '<td colspan="2"><b>'.$langs->trans('Total').'</b></td>';
print '<td class="right"><b>'.price($totals['ow']).'</b></td>';
print '<td class="right"><b>'.price($totals['aw']).'</b></td>';
print '<td class="right"><b>'.price($totals['emp_cpf']).'</b></td>';
print '<td class="right"><b>'.price($totals['er_cpf']).'</b></td>';
print '<td class="right"><b>'.price($sdlTotalRounded).'</b></td>';
print '<td class="right"><b>'.price($totals['shg_cdac']).'</b></td>';
print '<td class="right"><b>'.price($totals['shg_ecf']).'</b></td>';
print '<td class="right"><b>'.price($totals['shg_mbmf']).'</b></td>';
print '<td class="right"><b>'.price($totals['shg_sinda']).'</b></td>';
print '<td></td></tr>';
print '</table>';

print '<br><div class="info"><i class="fas fa-info-circle"></i> '.
	$langs->trans('CPFEzPayNote').'</div>';

llxFooter();
$db->close();
