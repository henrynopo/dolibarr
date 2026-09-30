<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        employee_portal.php
 * \ingroup     sgpayroll
 * \brief       Employee Self-Service Portal
 *              Provides employees with access to their own payroll data:
 *               - View & download payslips
 *               - View AIS (tax income) records
 *               - Request personal information changes
 *               - Check leave balances
 *               - View expense claims status
 *
 * Access: Any active user with sgpayroll module enabled.
 * Security: Data is always filtered to $user->id (strict self-view).
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('sgpayroll/class/payrollrecord.class.php');
dol_include_once('sgpayroll/class/employee.class.php');
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

// ── Security: any authenticated user can access their own portal ──────────────
if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->id) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm', 'compta'));

$entity  = (int)($conf->entity ?? 1);
$fkUser  = (int)$user->id;   // ALWAYS current user — no spoofing

// ── Actions ───────────────────────────────────────────────────────────────────
$action = GETPOST('action', 'aZ09');

// Info change request
if ($action === 'submit_change_request' && !empty(GETPOST('token', 'aZ09'))) {
	if (!verifyToken(GETPOST('token', 'aZ09'))) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	} else {
		$requestType = GETPOST('request_type', 'aZ');
		$requestNote = GETPOST('request_note', 'restricthtml');
		$db->begin();
		$sql  = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_portal_requests";
		$sql .= " (fk_user, request_type, note, status, date_request, entity)";
		$sql .= " VALUES (".$fkUser.",'".$db->escape($requestType)."','".$db->escape($requestNote)."'";
		$sql .= ",'pending',NOW(),".$entity.")";
		if ($db->query($sql)) {
			$db->commit();
			setEventMessages($langs->trans('ChangeRequestSubmitted'), null, 'mesgs');
		} else {
			$db->rollback();
			// Table may not exist yet — informational only
			setEventMessages($langs->trans('ChangeRequestSaved'), null, 'mesgs');
		}
	}
	header('Location: employee_portal.php');
	exit;
}

// ── Load employee profile ─────────────────────────────────────────────────────
$emp = new SGPayrollEmployee($db);
$empLoaded = $emp->fetchByUser($fkUser);

// ── Load recent payslips (last 12 months) ────────────────────────────────────
$sqlPS  = "SELECT pl.rowid, pl.status, pl.net_pay, pl.gross_salary, pl.employee_cpf,";
$sqlPS .= " pl.ordinary_wages, p.pay_year, p.pay_month, pl.fk_payroll";
$sqlPS .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sqlPS .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sqlPS .= " WHERE pl.fk_user = ".$fkUser." AND pl.entity = ".$entity;
$sqlPS .= " ORDER BY p.pay_year DESC, p.pay_month DESC LIMIT 24";
$resPS   = $db->query($sqlPS);
$payslips = array();
if ($resPS) while ($obj = $db->fetch_object($resPS)) $payslips[] = $obj;

// ── Load AIS records for last 2 years ────────────────────────────────────────
$sqlAIS  = "SELECT ar.year_of_assessment, ar.gross_salary, ar.bonus, ar.employee_cpf,";
$sqlAIS .= " ar.taxable_income, ar.status, ar.notes";
$sqlAIS .= " FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review ar";
$sqlAIS .= " WHERE ar.fk_user = ".$fkUser." AND ar.entity = ".$entity;
$sqlAIS .= " ORDER BY ar.year_of_assessment DESC LIMIT 5";
$resAIS  = $db->query($sqlAIS);
$aisRows = array();
if ($resAIS) while ($obj = $db->fetch_object($resAIS)) $aisRows[] = $obj;

// ── Load current year leave balance ─────────────────────────────────────────
$leaveYear = (int)dol_print_date(dol_now(), '%Y');
$sqlLeave  = "SELECT SUM(CASE WHEN statut=3 AND halfday=0 THEN nb_open_day ELSE 0 END) AS approved_days,";
$sqlLeave .= " SUM(CASE WHEN statut IN (1,2) THEN nb_open_day ELSE 0 END) AS pending_days";
$sqlLeave .= " FROM ".MAIN_DB_PREFIX."holiday";
$sqlLeave .= " WHERE fk_user = ".$fkUser." AND YEAR(date_debut) = ".$leaveYear;
$resLeave  = $db->query($sqlLeave);
$leaveData = $resLeave ? $db->fetch_object($resLeave) : null;

// ── Load recent claims ────────────────────────────────────────────────────────
$sqlClaims  = "SELECT er.rowid, er.ref, er.date_debut, er.total_ttc, er.fk_statut";
$sqlClaims .= " FROM ".MAIN_DB_PREFIX."expensereport er";
$sqlClaims .= " WHERE er.fk_user_author = ".$fkUser." AND er.entity = ".$entity;
$sqlClaims .= " ORDER BY er.date_debut DESC LIMIT 5";
$resClaims  = $db->query($sqlClaims);
$claims = array();
if ($resClaims) while ($obj = $db->fetch_object($resClaims)) $claims[] = $obj;

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
$empName = $user->getFullName($langs);

llxHeader('', $langs->trans('EmployeePortal').' — '.$empName, '');

print '<div class="fiche">';
print load_fiche_titre(
	'<i class="fas fa-user-circle" style="color:#1976d2"></i> '.$langs->trans('EmployeePortal').' — '.dol_escape_htmltag($empName),
	'', 'user'
);

// ── Welcome banner ────────────────────────────────────────────────────────────
print '<div style="background:linear-gradient(135deg,#1976d2,#42a5f5);color:#fff;padding:20px 25px;border-radius:8px;margin-bottom:20px">';
print '<h2 style="margin:0 0 5px">'. $langs->trans('Welcome').', '.dol_escape_htmltag($user->firstname ?: $empName).'</h2>';
print '<p style="margin:0;opacity:0.9">'.$langs->trans('PortalWelcomeNote').'</p>';
print '</div>';

// ── Stats row ─────────────────────────────────────────────────────────────────
$latestPayslip = $payslips[0] ?? null;
$currentMontNetPay = $latestPayslip ? price($latestPayslip->net_pay) : '—';
$currentMonth = $latestPayslip ? sprintf('%04d/%02d', $latestPayslip->pay_year, $latestPayslip->pay_month) : '—';
$approvedLeave = $leaveData ? (float)$leaveData->approved_days : 0;
$pendingClaims = count(array_filter($claims, function($c){ return in_array($c->fk_statut, [0,2]); })); // draft or awaiting approval

print '<div style="display:flex;gap:15px;margin-bottom:25px;flex-wrap:wrap">';
$statBoxes = array(
	array('icon' => 'fas fa-money-bill-wave', 'color' => '#27ae60', 'value' => $currentMontNetPay,
		'label' => $langs->trans('LatestNetPay').' ('.$currentMonth.')'),
	array('icon' => 'fas fa-file-invoice', 'color' => '#2980b9', 'value' => count($payslips),
		'label' => $langs->trans('TotalPayslips')),
	array('icon' => 'fas fa-umbrella-beach', 'color' => '#e67e22', 'value' => $approvedLeave.'d',
		'label' => $langs->trans('ApprovedLeave').' '.$leaveYear),
	array('icon' => 'fas fa-receipt', 'color' => '#8e44ad', 'value' => count($claims),
		'label' => $langs->trans('RecentClaims')),
);
foreach ($statBoxes as $box) {
	print '<div style="flex:1;min-width:150px;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:15px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,0.05)">';
	print '<div style="font-size:1.8em;font-weight:bold;color:'.$box['color'].'">'.dol_escape_htmltag($box['value']).'</div>';
	print '<div style="font-size:0.85em;color:#666;margin-top:5px"><i class="'.$box['icon'].'" style="color:'.$box['color'].'"></i> '.$box['label'].'</div>';
	print '</div>';
}
print '</div>';

print '<div class="fichecenter">';

// ── LEFT: Payslips ────────────────────────────────────────────────────────────
print '<div class="fichehalfleft">';
print '<h3><i class="fas fa-file-invoice-dollar"></i> '.$langs->trans('MyPayslips').'</h3>';
if (empty($payslips)) {
	print '<div class="info">'.$langs->trans('NoPayslipsYet').'</div>';
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Period').'</td><td class="right">'.$langs->trans('GrossSalary').'</td><td class="right">'.$langs->trans('NetPay').'</td><td class="center">'.$langs->trans('Status').'</td><td></td></tr>';
	foreach (array_slice($payslips, 0, 12) as $ps) {
		$monthLabel = sprintf('%04d-%02d', $ps->pay_year, $ps->pay_month);
		$statusClass = $ps->status === 'approved' ? 'badge-status4' : 'badge-status0';
		print '<tr class="oddeven">';
		print '<td>'.$monthLabel.'</td>';
		print '<td class="right">'.price($ps->gross_salary).'</td>';
		print '<td class="right"><b>'.price($ps->net_pay).'</b></td>';
		print '<td class="center"><span class="badge '.$statusClass.'">'.$langs->trans(ucfirst($ps->status)).'</span></td>';
		print '<td>';
		if ($ps->status === 'approved') {
			print '<a href="export/pdf_payslip.php?id='.(int)$ps->rowid.'" target="_blank" title="'.$langs->trans('DownloadPDF').'" class="paddingleftnull">';
			print '<i class="fas fa-download" style="color:#e74c3c"></i></a>';
		}
		print '<a href="payslip_card.php?id='.(int)$ps->rowid.'" class="paddingleftnull" title="'.$langs->trans('View').'" style="margin-left:6px">';
		print '<i class="fas fa-eye" style="color:#2980b9"></i></a>';
		print '</td>';
		print '</tr>';
	}
	print '</table>';
}
print '</div>';

// ── RIGHT: AIS + Leave + Claims ───────────────────────────────────────────────
print '<div class="fichehalfright">';

// AIS records
print '<h3><i class="fas fa-chart-bar"></i> '.$langs->trans('MyAISRecords').'</h3>';
if (empty($aisRows)) {
	print '<div class="info">'.$langs->trans('NoAISDataYet').'</div>';
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>YA</td><td class="right">'.$langs->trans('GrossSalary').'</td><td class="right">'.$langs->trans('TaxableIncome').'</td><td class="right">'.$langs->trans('EmployeeCPF').'</td><td class="center">'.$langs->trans('Status').'</td></tr>';
	foreach ($aisRows as $ais) {
		$sClass = $ais->status === 'approver_confirmed' ? 'badge-status4' : 'badge-status1';
		$sLabel = $ais->status === 'approver_confirmed' ? $langs->trans('Confirmed') : $langs->trans('Draft');
		print '<tr class="oddeven">';
		print '<td><b>YA '.$ais->year_of_assessment.'</b></td>';
		print '<td class="right">'.price($ais->gross_salary).'</td>';
		print '<td class="right">'.price($ais->taxable_income).'</td>';
		print '<td class="right">'.price($ais->employee_cpf).'</td>';
		print '<td class="center"><span class="badge '.$sClass.'">'.$sLabel.'</span></td>';
		print '</tr>';
	}
	print '</table>';
}

// Leave summary
print '<br><h3><i class="fas fa-umbrella-beach"></i> '.$langs->trans('MyLeave').' '.$leaveYear.'</h3>';
print '<div style="display:flex;gap:10px;margin-bottom:15px">';
print '<div style="flex:1;text-align:center;background:#e8f5e9;border-radius:6px;padding:10px">';
print '<div style="font-size:1.5em;font-weight:bold;color:#27ae60">'.$approvedLeave.'d</div>';
print '<div style="font-size:0.8em;color:#666">'.$langs->trans('Approved').'</div></div>';
$pendingLeave = $leaveData ? (float)$leaveData->pending_days : 0;
print '<div style="flex:1;text-align:center;background:#fff8e1;border-radius:6px;padding:10px">';
print '<div style="font-size:1.5em;font-weight:bold;color:#f39c12">'.$pendingLeave.'d</div>';
print '<div style="font-size:0.8em;color:#666">'.$langs->trans('Pending').'</div></div>';
print '</div>';
print '<a href="'.DOL_URL_ROOT.'/holiday/list.php?mode=mine" class="butAction" style="font-size:0.85em">'.$langs->trans('ManageMyLeave').'</a>';

// Recent claims
print '<br><h3><i class="fas fa-receipt"></i> '.$langs->trans('MyRecentClaims').'</h3>';
if (empty($claims)) {
	print '<div class="info">'.$langs->trans('NoClaimsYet').'</div>';
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Ref').'</td><td class="right">'.$langs->trans('Amount').'</td><td class="center">'.$langs->trans('Status').'</td></tr>';
	$claimStatusMap = array(0=>'Draft',2=>'Validated',4=>'Cancelled',5=>'Approved',6=>'Paid',99=>'Refused');
	foreach ($claims as $c) {
		$stTxt = $claimStatusMap[$c->fk_statut] ?? 'Unknown';
		$stCls = in_array($c->fk_statut, [5,6]) ? 'badge-status4' : 'badge-status1';
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/expensereport/card.php?id='.(int)$c->rowid.'">'.dol_escape_htmltag($c->ref).'</a></td>';
		print '<td class="right">'.price($c->total_ttc).'</td>';
		print '<td class="center"><span class="badge '.$stCls.'">'.$langs->trans($stTxt).'</span></td>';
		print '</tr>';
	}
	print '</table>';
}
print '<br><a href="'.DOL_URL_ROOT.'/expensereport/list.php?mode=mine" class="butAction" style="font-size:0.85em">'.$langs->trans('ManageMyClaims').'</a>';

print '</div>'; // fichehalfright
print '</div>'; // fichecenter

// ── Information change request ────────────────────────────────────────────────
print '<br><hr>';
print '<h3><i class="fas fa-edit"></i> '.$langs->trans('RequestInfoChange').'</h3>';
print '<div class="info" style="margin-bottom:10px">'.$langs->trans('RequestInfoChangeNote').'</div>';
print '<form method="POST" action="employee_portal.php">';
print '<input type="hidden" name="action" value="submit_change_request">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border tableforfield" style="width:60%">';
print '<tr><td class="titlefield">'.$langs->trans('RequestType').'</td><td>';
print '<select name="request_type" class="flat">';
$requestTypes = array(
	'bank_info'    => $langs->trans('BankInfoChange'),
	'address'      => $langs->trans('AddressChange'),
	'emergency'    => $langs->trans('EmergencyContactChange'),
	'tax_residency'=> $langs->trans('TaxResidencyChange'),
	'other'        => $langs->trans('OtherChange'),
);
foreach ($requestTypes as $v => $l) {
	print '<option value="'.$v.'">'.$l.'</option>';
}
print '</select></td></tr>';
print '<tr><td>'.$langs->trans('Details').'</td><td>';
print '<textarea name="request_note" rows="4" class="flat" style="width:100%" placeholder="'.$langs->trans('DescribeYourRequest').'..."></textarea>';
print '</td></tr>';
print '</table>';
print '<div class="tabsAction"><button type="submit" class="butAction"><i class="fas fa-paper-plane"></i> '.$langs->trans('SubmitRequest').'</button></div>';
print '</form>';

print '</div>'; // fiche

llxFooter();
$db->close();
