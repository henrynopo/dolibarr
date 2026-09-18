<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        payroll_dashboard.php
 * \ingroup     sgpayroll
 * \brief       Payroll Analytics & Summary Dashboard
 *
 * Provides management-level overview:
 *  - Annual headcount trend
 *  - Monthly gross/net pay chart data (JSON for Chart.js)
 *  - CPF & SDL/FWL annual contributions
 *  - Payroll cost breakdown (basic / OT / bonus / allowances / employer CPF)
 *  - Citizenship mix (SC / PR / EP / SP / WP)
 *  - Top 5 payroll months by total gross
 *  - Pending / draft payslips count
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->admin && !$user->hasRight('sgpayroll', 'payroll', 'read')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm', 'compta'));
$entity = (int)($conf->entity ?? 1);
$now    = dol_now();

$dashYear = GETPOSTINT('dash_year') ?: (int)dol_print_date($now, '%Y');
if (GETPOST('button_removefilter_x', 'alpha')) $dashYear = (int)dol_print_date($now, '%Y');

// ── DATA: Monthly totals for selected year ──────────────────────────────────
$sqlMonthly  = "SELECT p.pay_month,";
$sqlMonthly .= " ROUND(SUM(pl.gross_salary),2) AS gross_total,";
$sqlMonthly .= " ROUND(SUM(pl.net_pay),2) AS net_total,";
$sqlMonthly .= " ROUND(SUM(pl.employee_cpf),2) AS emp_cpf,";
$sqlMonthly .= " ROUND(SUM(pl.employer_cpf),2) AS er_cpf,";
$sqlMonthly .= " ROUND(SUM(pl.sdl_amount),2) AS sdl,";
$sqlMonthly .= " ROUND(SUM(pl.fwl_amount),2) AS fwl,";
$sqlMonthly .= " ROUND(SUM(pl.overtime_pay),2) AS ot_pay,";
$sqlMonthly .= " ROUND(SUM(pl.bonus),2) AS bonus,";
$sqlMonthly .= " COUNT(DISTINCT pl.fk_user) AS headcount";
$sqlMonthly .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlMonthly .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlMonthly .= " WHERE p.pay_year=".$dashYear." AND pl.entity=".$entity;
$sqlMonthly .= " AND pl.status='approved'";
$sqlMonthly .= " GROUP BY p.pay_month ORDER BY p.pay_month";
$resMonthly  = $db->query($sqlMonthly);
$monthlyData = array_fill(1, 12, array('gross' => 0, 'net' => 0, 'emp_cpf' => 0, 'er_cpf' => 0, 'sdl' => 0, 'fwl' => 0, 'ot_pay' => 0, 'bonus' => 0, 'headcount' => 0));
if ($resMonthly) while ($obj = $db->fetch_object($resMonthly)) {
	$monthlyData[(int)$obj->pay_month] = array(
		'gross' => (float)$obj->gross_total, 'net' => (float)$obj->net_total,
		'emp_cpf' => (float)$obj->emp_cpf,   'er_cpf' => (float)$obj->er_cpf,
		'sdl' => (float)$obj->sdl,           'fwl' => (float)$obj->fwl,
		'ot_pay' => (float)$obj->ot_pay,     'bonus' => (float)$obj->bonus,
		'headcount' => (int)$obj->headcount,
	);
}

// Annual totals
$annualGross   = array_sum(array_column($monthlyData, 'gross'));
$annualNet     = array_sum(array_column($monthlyData, 'net'));
$annualEmpCPF  = array_sum(array_column($monthlyData, 'emp_cpf'));
$annualErCPF   = array_sum(array_column($monthlyData, 'er_cpf'));
$annualSDL     = array_sum(array_column($monthlyData, 'sdl'));
$annualFWL     = array_sum(array_column($monthlyData, 'fwl'));
$annualOT      = array_sum(array_column($monthlyData, 'ot_pay'));
$annualBonus   = array_sum(array_column($monthlyData, 'bonus'));
$annualCPFTotal = $annualEmpCPF + $annualErCPF;
$annualEmployerCost = $annualGross + $annualErCPF + $annualSDL + $annualFWL - $annualEmpCPF;

// ── DATA: Citizenship mix ────────────────────────────────────────────────────
$sqlMix  = "SELECT COALESCE(e.citizenship, 'Unknown') AS cit,";
$sqlMix .= " COALESCE(e.pass_type, '') AS pass_type, COUNT(*) AS cnt";
$sqlMix .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
$sqlMix .= " WHERE e.entity=".$entity." AND e.employment_status='active'";
$sqlMix .= " GROUP BY e.citizenship, e.pass_type ORDER BY cnt DESC";
$resMix  = $db->query($sqlMix);
$citizenCounts = array('SC' => 0, 'PR' => 0, 'EP' => 0, 'SP' => 0, 'WP' => 0, 'Other' => 0);
$totalActive   = 0;
if ($resMix) while ($obj = $db->fetch_object($resMix)) {
	$totalActive += (int)$obj->cnt;
	$cit = strtoupper($obj->cit ?? '');
	$pass = strtoupper($obj->pass_type ?? '');
	if ($cit === 'SC')      $citizenCounts['SC']    += (int)$obj->cnt;
	elseif ($cit === 'PR')  $citizenCounts['PR']    += (int)$obj->cnt;
	elseif (strpos($pass, 'EP') !== false || $cit === 'EP')  $citizenCounts['EP'] += (int)$obj->cnt;
	elseif (strpos($pass, 'SP') !== false || strpos($pass,'S PASS') !== false) $citizenCounts['SP'] += (int)$obj->cnt;
	elseif (strpos($pass, 'WP') !== false || strpos($pass,'WORK') !== false)  $citizenCounts['WP'] += (int)$obj->cnt;
	else    $citizenCounts['Other'] += (int)$obj->cnt;
}

// ── DATA: Draft payslips pending ─────────────────────────────────────────────
$sqlPending  = "SELECT COUNT(*) AS cnt FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlPending .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlPending .= " WHERE p.pay_year=".$dashYear." AND pl.entity=".$entity." AND pl.status='draft'";
$resPend    = $db->query($sqlPending);
$draftCount  = $resPend ? (int)$db->fetch_object($resPend)->cnt : 0;

// ── Build Chart.js JSON ───────────────────────────────────────────────────────
$months_labels = array();
$gross_series  = array();
$net_series    = array();
$cpf_series    = array();
$hc_series     = array();
for ($m = 1; $m <= 12; $m++) {
	$months_labels[] = date('M', mktime(0,0,0,$m,1,$dashYear));
	$gross_series[]  = $monthlyData[$m]['gross'];
	$net_series[]    = $monthlyData[$m]['net'];
	$cpf_series[]    = round($monthlyData[$m]['emp_cpf'] + $monthlyData[$m]['er_cpf'], 2);
	$hc_series[]     = $monthlyData[$m]['headcount'];
}

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
llxHeader('', $langs->trans('PayrollDashboard').' '.$dashYear, '');

print load_fiche_titre(
	'<i class="fas fa-chart-line" style="color:#1976d2"></i> '.$langs->trans('PayrollDashboard').' — '.$dashYear,
	'', 'salary'
);

// Year selector
print '<form method="GET" action="payroll_dashboard.php" style="margin-bottom:20px">';
print '<select name="dash_year" class="flat">';
for ($y = (int)dol_print_date($now,'%Y'); $y >= 2023; $y--) {
	print '<option value="'.$y.'"'.($y==$dashYear?' selected':'').'>'.$y.'</option>';
}
print '</select> ';
print '<input type="submit" class="button" value="'.$langs->trans('Apply').'"> ';
print '<input type="submit" name="button_removefilter_x" class="button" value="'.$langs->trans('CurrentYear').'">';
print '</form>';

// Draft warning
if ($draftCount > 0) {
	print '<div class="warning"><i class="fas fa-exclamation-triangle"></i> '.
		sprintf($langs->trans('DraftPayslipsWarning'), $draftCount).' '.
		'<a href="payslip_list.php?search_status=draft&search_year='.$dashYear.'">'.$langs->trans('ViewDrafts').'</a></div><br>';
}

// ── KPI row ───────────────────────────────────────────────────────────────────
print '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:25px">';
$kpis = array(
	array('label' => $langs->trans('AnnualGrossPayroll'),   'value' => price($annualGross),       'icon' => 'fas fa-money-bill-wave', 'color' => '#1976d2'),
	array('label' => $langs->trans('AnnualNetPay'),         'value' => price($annualNet),         'icon' => 'fas fa-wallet',          'color' => '#27ae60'),
	array('label' => $langs->trans('TotalCPFContributions'),'value' => price($annualCPFTotal),    'icon' => 'fas fa-piggy-bank',      'color' => '#e67e22'),
	array('label' => $langs->trans('TotalSDLFWL'),          'value' => price($annualSDL + $annualFWL), 'icon' => 'fas fa-receipt',  'color' => '#8e44ad'),
	array('label' => $langs->trans('TotalActiveEmployees'), 'value' => $totalActive,              'icon' => 'fas fa-users',          'color' => '#2c3e50'),
);
foreach ($kpis as $kpi) {
	print '<div style="flex:1;min-width:150px;background:#fff;border-left:4px solid '.$kpi['color'].';border-radius:0 8px 8px 0;padding:15px;box-shadow:0 2px 6px rgba(0,0,0,0.08)">';
	print '<div style="font-size:1.6em;font-weight:bold;color:'.$kpi['color'].'">'.$kpi['value'].'</div>';
	print '<div style="color:#666;font-size:0.85em"><i class="'.$kpi['icon'].'"></i> '.$kpi['label'].'</div>';
	print '</div>';
}
print '</div>';

print '<div style="display:flex;gap:20px;flex-wrap:wrap">';

// ── LEFT: Monthly trend chart ─────────────────────────────────────────────────
print '<div style="flex:2;min-width:400px;background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.08)">';
print '<h3 style="margin:0 0 15px"><i class="fas fa-chart-bar" style="color:#1976d2"></i> '.$langs->trans('MonthlyPayrollTrend').'</h3>';
print '<canvas id="payrollTrendChart" style="width:100%;max-height:280px"></canvas>';
print '</div>';

// ── RIGHT top: Citizenship mix donut ─────────────────────────────────────────
print '<div style="flex:1;min-width:220px;background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.08)">';
print '<h3 style="margin:0 0 15px"><i class="fas fa-globe-asia" style="color:#1976d2"></i> '.$langs->trans('WorkforceMix').'</h3>';
print '<canvas id="citizenChart" style="max-height:220px"></canvas>';
print '<table class="noborder centpercent" style="margin-top:10px;font-size:0.85em">';
$mixColors = array('SC' => '#2980b9','PR' => '#27ae60','EP' => '#e67e22','SP' => '#8e44ad','WP' => '#c0392b','Other' => '#95a5a6');
foreach ($citizenCounts as $type => $cnt) {
	if ($cnt <= 0) continue;
	print '<tr><td><span style="display:inline-block;width:10px;height:10px;background:'.$mixColors[$type].';border-radius:2px;margin-right:5px"></span>'.$type.'</td><td class="right"><b>'.$cnt.'</b></td><td class="right">'.($totalActive > 0 ? round($cnt/$totalActive*100,1) : 0).'%</td></tr>';
}
print '</table></div>';

print '</div>'; // flex row

// ── Second row: Headcount + Cost breakdown ────────────────────────────────────
print '<div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:20px">';

// Headcount line chart
print '<div style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.08)">';
print '<h3 style="margin:0 0 15px"><i class="fas fa-users" style="color:#27ae60"></i> '.$langs->trans('MonthlyHeadcount').'</h3>';
print '<canvas id="headcountChart" style="max-height:200px"></canvas>';
print '</div>';

// Payroll cost breakdown
print '<div style="flex:1;min-width:300px;background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.08)">';
print '<h3 style="margin:0 0 15px"><i class="fas fa-chart-pie" style="color:#e67e22"></i> '.$langs->trans('AnnualCostBreakdown').'</h3>';
print '<canvas id="costPieChart" style="max-height:200px"></canvas>';
$annualBasic = $annualGross - $annualOT - $annualBonus;
print '<table class="noborder centpercent" style="margin-top:10px;font-size:0.85em">';
$costItems = array(
	array($langs->trans('BasicSalary'), $annualBasic, '#2980b9'),
	array($langs->trans('Bonus'), $annualBonus, '#27ae60'),
	array($langs->trans('OvertimePay'), $annualOT, '#e74c3c'),
	array($langs->trans('EmployerCPF'), $annualErCPF, '#e67e22'),
	array('SDL + FWL', $annualSDL + $annualFWL, '#8e44ad'),
);
foreach ($costItems as $ci) {
	$pct = $annualEmployerCost > 0 ? round($ci[1]/$annualEmployerCost*100, 1) : 0;
	print '<tr><td><span style="display:inline-block;width:10px;height:10px;background:'.$ci[2].';border-radius:2px;margin-right:5px"></span>'.$ci[0].'</td>';
	print '<td class="right">'.price($ci[1]).'</td><td class="right">'.$pct.'%</td></tr>';
}
print '</table></div>';

print '</div>'; // second flex row

// ── Monthly details table ─────────────────────────────────────────────────────
print '<br><div style="background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 6px rgba(0,0,0,0.08)">';
print '<h3 style="margin:0 0 15px"><i class="fas fa-table" style="color:#2c3e50"></i> '.$langs->trans('MonthlyBreakdownTable').'</h3>';
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Month').'</td><td class="right">'.$langs->trans('Headcount').'</td><td class="right">'.$langs->trans('GrossSalary').'</td><td class="right">'.$langs->trans('NetPay').'</td><td class="right">'.$langs->trans('EmployeeCPF').'</td><td class="right">'.$langs->trans('EmployerCPF').'</td><td class="right">SDL</td><td class="right">FWL</td></tr>';
$mNames = array(1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec');
for ($m = 1; $m <= 12; $m++) {
	$d = $monthlyData[$m];
	$noData = ($d['gross'] == 0 && $d['headcount'] == 0);
	print '<tr class="'.($m%2?'pair':'impair').'"'.($noData?' style="color:#ccc"':'').'>';
	print '<td><a href="payslip_list.php?search_year='.$dashYear.'&search_month='.$m.'">'.$mNames[$m].' '.$dashYear.'</a></td>';
	print '<td class="right">'.($d['headcount'] ?: '—').'</td>';
	print '<td class="right">'.($d['gross'] > 0 ? price($d['gross']) : '—').'</td>';
	print '<td class="right">'.($d['net'] > 0 ? price($d['net']) : '—').'</td>';
	print '<td class="right">'.($d['emp_cpf'] > 0 ? price($d['emp_cpf']) : '—').'</td>';
	print '<td class="right">'.($d['er_cpf'] > 0 ? price($d['er_cpf']) : '—').'</td>';
	print '<td class="right">'.($d['sdl'] > 0 ? price($d['sdl']) : '—').'</td>';
	print '<td class="right">'.($d['fwl'] > 0 ? price($d['fwl']) : '—').'</td>';
	print '</tr>';
}
// Total row
print '<tr class="liste_total"><td><b>'.$langs->trans('Total').'</b></td>';
print '<td class="right"><b>—</b></td>';
print '<td class="right"><b>'.price($annualGross).'</b></td>';
print '<td class="right"><b>'.price($annualNet).'</b></td>';
print '<td class="right"><b>'.price($annualEmpCPF).'</b></td>';
print '<td class="right"><b>'.price($annualErCPF).'</b></td>';
print '<td class="right"><b>'.price($annualSDL).'</b></td>';
print '<td class="right"><b>'.price($annualFWL).'</b></td>';
print '</tr>';
print '</table></div></div>';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const months = <?php echo json_encode(array_values($months_labels)); ?>;
const grossData = <?php echo json_encode(array_values($gross_series)); ?>;
const netData   = <?php echo json_encode(array_values($net_series)); ?>;
const cpfData   = <?php echo json_encode(array_values($cpf_series)); ?>;
const hcData    = <?php echo json_encode(array_values($hc_series)); ?>;

// Monthly trend bar chart
new Chart(document.getElementById('payrollTrendChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            { label: '<?php echo $langs->trans('GrossSalary'); ?>', data: grossData, backgroundColor: 'rgba(25,118,210,0.7)', borderRadius: 4 },
            { label: '<?php echo $langs->trans('NetPay'); ?>',      data: netData,   backgroundColor: 'rgba(39,174,96,0.7)',  borderRadius: 4 },
            { label: '<?php echo $langs->trans('CPF'); ?>',         data: cpfData,   backgroundColor: 'rgba(230,126,34,0.7)', borderRadius: 4 },
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});

// Citizenship donut
const citLabels = <?php echo json_encode(array_keys(array_filter($citizenCounts))); ?>;
const citData   = <?php echo json_encode(array_values(array_filter($citizenCounts))); ?>;
const citColors = <?php echo json_encode(array_values(array_intersect_key($mixColors, array_filter($citizenCounts)))); ?>;
if (citData.length > 0) {
    new Chart(document.getElementById('citizenChart'), {
        type: 'doughnut',
        data: { labels: citLabels, datasets: [{ data: citData, backgroundColor: citColors }] },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
}

// Headcount line
new Chart(document.getElementById('headcountChart'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{ label: '<?php echo $langs->trans('Headcount'); ?>', data: hcData, borderColor: '#27ae60', backgroundColor: 'rgba(39,174,96,0.1)', fill: true, tension: 0.3 }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

// Cost pie
const costLabels = <?php echo json_encode(array_column($costItems, 0)); ?>;
const costVals   = <?php echo json_encode(array_column($costItems, 1)); ?>;
const costColors = <?php echo json_encode(array_column($costItems, 2)); ?>;
new Chart(document.getElementById('costPieChart'), {
    type: 'pie',
    data: { labels: costLabels, datasets: [{ data: costVals, backgroundColor: costColors }] },
    options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>
<?php
llxFooter();
$db->close();
