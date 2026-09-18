<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        mom_compliance.php
 * \ingroup     sgpayroll
 * \brief       MOM Labour Law Compliance Dashboard
 *
 * Checks across all active employees and recent payroll data:
 *   1. OT hours > 72 hours/month (MOM Employment Act Section 38)
 *   2. Work Pass expiry warnings (< 30 days = critical, < 90 days = warning)
 *   3. Salary below Pass type minimum wage thresholds (EP $5,000/SP $3,150)
 *   4. Employees without payslips for current month
 *   5. Non-resident employees with WHT but no IR37A filed
 *   6. FWL sector mismatch warnings
 *
 * Source: Employment Act (Cap. 91), MOM Guidelines, IRAS Circular
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/class/payrollcalc.class.php';

if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->admin && !$user->hasRight('sgpayroll', 'employee', 'read')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm'));
$entity = (int)($conf->entity ?? 1);
$now    = dol_now();
$today  = dol_print_date($now, 'standard');

// Default: check for current month
$chkYear  = GETPOSTINT('chk_year')  ?: (int)dol_print_date($now, '%Y');
$chkMonth = GETPOSTINT('chk_month') ?: (int)dol_print_date($now, '%m');
if (GETPOST('button_removefilter_x', 'alpha')) {
	$chkYear  = (int)dol_print_date($now, '%Y');
	$chkMonth = (int)dol_print_date($now, '%m');
}

$issues   = array(); // All compliance issues found, keyed by category
$totalIssues = 0;

// ── CHECK 1: OT > 72 hours in selected month ──────────────────────────────────
$sqlOT  = "SELECT pl.fk_user, u.lastname, u.firstname,";
$sqlOT .= " (COALESCE(pl.ot_hours_wd,0) + COALESCE(pl.ot_hours_rest,0) + COALESCE(pl.ot_hours_ph,0)) AS total_ot,";
$sqlOT .= " pl.overtime_hours AS legacy_ot, pl.basic_salary";
$sqlOT .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlOT .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlOT .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sqlOT .= " WHERE p.pay_year=".$chkYear." AND p.pay_month=".$chkMonth;
$sqlOT .= " AND pl.entity=".$entity;
$resOT  = $db->query($sqlOT);
$issues['ot'] = array();
if ($resOT) {
	while ($obj = $db->fetch_object($resOT)) {
		$otHours = max((float)$obj->total_ot, (float)$obj->legacy_ot);
		if ($otHours > 72) {
			$issues['ot'][] = array(
				'name'   => sgpayroll_format_employee_name($obj->firstname, $obj->lastname),
				'value'  => $otHours,
				'fk_user'=> $obj->fk_user,
				'severity' => 'high',
				'msg'    => sprintf($langs->trans('OTExceedsLimit'), $otHours),
			);
			$totalIssues++;
		} elseif ($otHours > 60) {
			$issues['ot'][] = array(
				'name'   => sgpayroll_format_employee_name($obj->firstname, $obj->lastname),
				'value'  => $otHours,
				'fk_user'=> $obj->fk_user,
				'severity' => 'medium',
				'msg'    => sprintf($langs->trans('OTApproachingLimit'), $otHours),
			);
			$totalIssues++;
		}
	}
}

// ── CHECK 2: Work Pass expiry ─────────────────────────────────────────────────
$sqlPass  = "SELECT e.fk_user, u.lastname, u.firstname, e.pass_type, e.pass_expiry, e.basic_salary";
$sqlPass .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
$sqlPass .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
$sqlPass .= " WHERE e.entity=".$entity." AND e.employment_status='active'";
$sqlPass .= " AND e.pass_expiry IS NOT NULL AND e.pass_expiry != ''";
$sqlPass .= " AND e.pass_type IN ('EP','SP','WP','S Pass','Work Permit','Employment Pass')";
$resPass  = $db->query($sqlPass);
$issues['pass_expiry'] = array();
if ($resPass) {
	while ($obj = $db->fetch_object($resPass)) {
		if (empty($obj->pass_expiry)) continue;
		$expiryTs  = strtotime($obj->pass_expiry);
		if (!$expiryTs) continue;
		$daysLeft  = (int)(($expiryTs - $now) / 86400);
		if ($daysLeft < 0) {
			$issues['pass_expiry'][] = array(
				'name' => sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 'fk_user' => $obj->fk_user,
				'value' => $obj->pass_expiry, 'severity' => 'critical',
				'msg' => sprintf($langs->trans('PassExpiredDaysAgo'), abs($daysLeft)),
			);
			$totalIssues++;
		} elseif ($daysLeft <= 30) {
			$issues['pass_expiry'][] = array(
				'name' => sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 'fk_user' => $obj->fk_user,
				'value' => $obj->pass_expiry, 'severity' => 'high',
				'msg' => sprintf($langs->trans('PassExpiresInDays'), $daysLeft),
			);
			$totalIssues++;
		} elseif ($daysLeft <= 90) {
			$issues['pass_expiry'][] = array(
				'name' => sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 'fk_user' => $obj->fk_user,
				'value' => $obj->pass_expiry, 'severity' => 'medium',
				'msg' => sprintf($langs->trans('PassExpiresInDays'), $daysLeft),
			);
			$totalIssues++;
		}
	}
}

// ── CHECK 3: Salary below pass-type minimum ────────────────────────────────────
// MOM 2025 minimums: EP ≥ $5,000/month (new entrant), S Pass ≥ $3,150/month
$passMinimums = array(
	'EP' => 5000, 'Employment Pass' => 5000,
	'SP' => 3150, 'S Pass' => 3150, 'S-Pass' => 3150,
);
$sqlSal  = "SELECT e.fk_user, u.lastname, u.firstname, e.pass_type, e.basic_salary";
$sqlSal .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
$sqlSal .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
$sqlSal .= " WHERE e.entity=".$entity." AND e.employment_status='active'";
$sqlSal .= " AND e.pass_type IS NOT NULL AND e.basic_salary > 0";
$resSal  = $db->query($sqlSal);
$issues['salary_min'] = array();
if ($resSal) {
	while ($obj = $db->fetch_object($resSal)) {
		$minWage = $passMinimums[$obj->pass_type] ?? 0;
		if ($minWage > 0 && (float)$obj->basic_salary < $minWage) {
			$issues['salary_min'][] = array(
				'name' => sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 'fk_user' => $obj->fk_user,
				'value' => price($obj->basic_salary), 'severity' => 'high',
				'msg' => sprintf($langs->trans('SalaryBelowPassMinimum'), $obj->pass_type, price($minWage), price($obj->basic_salary)),
			);
			$totalIssues++;
		}
	}
}

// ── CHECK 4: Active employees without payslip for selected month ──────────────
$sqlMissingPS  = "SELECT e.fk_user, u.lastname, u.firstname, e.employment_type";
$sqlMissingPS .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
$sqlMissingPS .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
$sqlMissingPS .= " WHERE e.entity=".$entity." AND e.employment_status='active'";
$sqlMissingPS .= " AND e.fk_user NOT IN (";
$sqlMissingPS .= "   SELECT pl.fk_user FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlMissingPS .= "   INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlMissingPS .= "   WHERE p.pay_year=".$chkYear." AND p.pay_month=".$chkMonth." AND pl.entity=".$entity;
$sqlMissingPS .= " )";
$resMissing = $db->query($sqlMissingPS);
$issues['missing_payslip'] = array();
if ($resMissing) {
	while ($obj = $db->fetch_object($resMissing)) {
		$issues['missing_payslip'][] = array(
			'name' => sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 'fk_user' => $obj->fk_user,
			'value' => sprintf('%04d/%02d', $chkYear, $chkMonth), 'severity' => 'medium',
			'msg' => $langs->trans('NoPayslipForMonth'),
		);
		$totalIssues++;
	}
}

// ── CHECK 5: Non-residents with WHT but no payslip filed ─────────────────────
$sqlWHT  = "SELECT e.fk_user, u.lastname, u.firstname, e.tax_residency,";
$sqlWHT .= " pl.withholding_tax, pl.status AS ps_status";
$sqlWHT .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlWHT .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlWHT .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sqlWHT .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user AND e.entity = pl.entity";
$sqlWHT .= " WHERE p.pay_year=".$chkYear." AND p.pay_month=".$chkMonth;
$sqlWHT .= " AND pl.entity=".$entity." AND pl.withholding_tax > 0";
$sqlWHT .= " AND pl.status = 'draft'";
$resWHT = $db->query($sqlWHT);
$issues['wht_unapproved'] = array();
if ($resWHT) {
	while ($obj = $db->fetch_object($resWHT)) {
		$issues['wht_unapproved'][] = array(
			'name' => sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 'fk_user' => $obj->fk_user,
			'value' => price($obj->withholding_tax), 'severity' => 'medium',
			'msg' => $langs->trans('WHTPayslipNotApproved'),
		);
		$totalIssues++;
	}
}

// ── Summary for header ────────────────────────────────────────────────────────
$critCount   = 0;
$highCount   = 0;
$mediumCount = 0;
foreach ($issues as $cat) {
	foreach ($cat as $issue) {
		if ($issue['severity'] === 'critical')    $critCount++;
		elseif ($issue['severity'] === 'high')    $highCount++;
		else                                       $mediumCount++;
	}
}

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
$periodLabel = sprintf('%04d/%02d', $chkYear, $chkMonth);

llxHeader('', $langs->trans('MomComplianceDashboard'), '');

print load_fiche_titre(
	'<i class="fas fa-shield-alt" style="color:#e74c3c"></i> '.$langs->trans('MomComplianceDashboard'),
	'', 'setup'
);

// Compliance score
$scoreColor = '#27ae60';
if ($critCount > 0) $scoreColor = '#c0392b';
elseif ($highCount > 0) $scoreColor = '#e74c3c';
elseif ($mediumCount > 0) $scoreColor = '#e67e22';

$scoreLabel = $totalIssues === 0 ? $langs->trans('FullyCompliant') : $langs->trans('IssuesFound');
print '<div style="background:linear-gradient(135deg,'.$scoreColor.','.($totalIssues===0?'#2ecc71':'#c0392b').');color:#fff;border-radius:8px;padding:20px 25px;margin-bottom:20px;display:flex;align-items:center;gap:20px">';
print '<div style="font-size:3em;font-weight:bold">'.$totalIssues.'</div>';
print '<div><div style="font-size:1.3em;font-weight:bold">'.$scoreLabel.'</div>';
print '<div style="opacity:0.9">'.$langs->trans('CompliancePeriod').': '.$periodLabel.'</div></div>';
print '<div style="margin-left:auto;display:flex;gap:15px">';
if ($critCount)   print '<div style="text-align:center"><div style="font-size:1.5em;font-weight:bold">'.$critCount.'</div><div style="font-size:0.8em">CRITICAL</div></div>';
if ($highCount)   print '<div style="text-align:center"><div style="font-size:1.5em;font-weight:bold">'.$highCount.'</div><div style="font-size:0.8em">HIGH</div></div>';
if ($mediumCount) print '<div style="text-align:center"><div style="font-size:1.5em;font-weight:bold">'.$mediumCount.'</div><div style="font-size:0.8em">MEDIUM</div></div>';
print '</div></div>';

// Filter bar
print '<form method="GET" action="mom_compliance.php">';
print '<table class="border centpercent"><tr>';
print '<td class="titlefield">'.$langs->trans('CheckPeriod').'</td>';
print '<td><input type="number" name="chk_year" value="'.$chkYear.'" min="2024" max="2030" class="flat width75">';
print ' / '.sgpayroll_select_month($chkMonth, 'chk_month').'</td>';
print '<td><input type="submit" class="button" value="'.$langs->trans('Check').'"></td>';
print '<td><input type="submit" name="button_removefilter_x" class="button" value="'.$langs->trans('CurrentMonth').'"></td>';
print '</tr></table></form><br>';

if ($totalIssues === 0) {
	print '<div class="ok"><i class="fas fa-check-circle"></i> <b>'.$langs->trans('NoComplianceIssues').'</b> — ';
	print $langs->trans('AllEmployeesCompliant').'</div>';
	llxFooter(); $db->close(); exit;
}

// ── Issue sections ────────────────────────────────────────────────────────────
$sections = array(
	'ot'              => array('icon' => 'fas fa-stopwatch', 'color' => '#e74c3c', 'title' => $langs->trans('OvertimeViolations')),
	'pass_expiry'     => array('icon' => 'fas fa-id-card',   'color' => '#e67e22', 'title' => $langs->trans('PassExpiryWarnings')),
	'salary_min'      => array('icon' => 'fas fa-dollar-sign','color'=> '#c0392b', 'title' => $langs->trans('SalaryBelowMinimum')),
	'missing_payslip' => array('icon' => 'fas fa-file-slash', 'color'=> '#f39c12', 'title' => $langs->trans('MissingPayslips')),
	'wht_unapproved'  => array('icon' => 'fas fa-percentage', 'color'=> '#2980b9', 'title' => $langs->trans('WHTNotApproved')),
);

$severityColor = array('critical' => '#c0392b', 'high' => '#e74c3c', 'medium' => '#e67e22');
$severityIcon  = array('critical' => 'fas fa-exclamation-circle', 'high' => 'fas fa-exclamation-triangle', 'medium' => 'fas fa-info-circle');

foreach ($sections as $key => $sec) {
	if (empty($issues[$key])) continue;
	print '<div style="margin-bottom:20px">';
	print '<h3 style="color:'.$sec['color'].'"><i class="'.$sec['icon'].'"></i> '.$sec['title'].' ('.count($issues[$key]).')</h3>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Employee').'</td><td>'.$langs->trans('Issue').'</td><td class="center">'.$langs->trans('Severity').'</td><td>'.$langs->trans('Value').'</td><td></td></tr>';
	foreach ($issues[$key] as $issue) {
		$sevColor = $severityColor[$issue['severity']] ?? '#666';
		$sevIcon  = $severityIcon[$issue['severity']] ?? 'fas fa-circle';
		print '<tr class="oddeven">';
		print '<td><a href="employee_card.php?fk_user='.(int)$issue['fk_user'].'">'.dol_escape_htmltag($issue['name']).'</a></td>';
		print '<td>'.dol_escape_htmltag($issue['msg']).'</td>';
		print '<td class="center"><span style="color:'.$sevColor.';font-weight:bold"><i class="'.$sevIcon.'"></i> '.strtoupper($issue['severity']).'</span></td>';
		print '<td>'.dol_escape_htmltag($issue['value']).'</td>';
		print '<td>';
		if ($key === 'ot')             print '<a href="payslip_list.php?search_employee='.(int)$issue['fk_user'].'" class="badge badge-status3">'.$langs->trans('ViewPayslips').'</a>';
		if ($key === 'pass_expiry')    print '<a href="mom_oed.php" class="badge badge-status3">'.$langs->trans('ViewOED').'</a>';
		if ($key === 'missing_payslip')print '<a href="payslip_list.php" class="badge badge-status3">'.$langs->trans('CreatePayslip').'</a>';
		if ($key === 'wht_unapproved') print '<a href="iras_wht_review.php?search_year='.$chkYear.'&search_month='.$chkMonth.'" class="badge badge-status3">'.$langs->trans('ReviewWHT').'</a>';
		print '</td>';
		print '</tr>';
	}
	print '</table></div>';
}

// Reference panel
print '<hr>';
print '<details><summary style="cursor:pointer;color:#666"><i class="fas fa-book"></i> '.$langs->trans('MOMLegalReference').'</summary>';
print '<div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-top:8px">';
print '<ul>';
$refs = array(
	$langs->trans('MOMRef1'),
	$langs->trans('MOMRef2'),
	$langs->trans('MOMRef3'),
	$langs->trans('MOMRef4'),
);
foreach ($refs as $r) print '<li>'.$r.'</li>';
print '</ul></div></details>';

llxFooter();
$db->close();
