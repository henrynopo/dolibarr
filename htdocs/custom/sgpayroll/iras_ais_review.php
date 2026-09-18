<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        iras_ais_review.php
 * \ingroup     sgpayroll
 * \brief       IRAS Auto-Inclusion Scheme (AIS) Employment Income Review Dashboard.
 *              Allows HR Manager to review/flag and HR Approver to confirm before XML export.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) { die("Error: could not load Dolibarr main.inc.php"); }

require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/class/employee.class.php';

// Security
if (!isModEnabled("sgpayroll")) accessforbidden();
$fkUser  = GETPOST('fk_user', 'int'); // This is needed for the security check below
$isSelf  = ($fkUser == $user->id);
$canEdit = $user->admin || $user->hasRight('sgpayroll', 'employee', 'write');
$canView = $canEdit || $user->hasRight('sgpayroll', 'employee', 'read_all') || $user->hasRight('sgpayroll', 'employee', 'read') || $isSelf;
if (!$canView) accessforbidden();
// Specific security for AIS review
$canManageAIS = $user->admin || $user->hasRight('sgpayroll', 'ais', 'review');
// If not manager, they can only see their own
$ownOnlyAIS = !$canManageAIS;

if ($ownOnlyAIS) {
	// If employee, they can only see their own record. No right needed other than being an employee.
}

$langs->loadLangs(array('sgpayroll@sgpayroll'));

$action  = GETPOST('action', 'alpha');
$ya      = GETPOST('ya', 'int') ?: ((int)date('Y') + 1);  // Year of Assessment default = current year + 1 (i.e. if 2026, YA=2027)
$fkUser  = GETPOST('fk_user', 'int');
$rowid   = GETPOST('rowid', 'int');
$canApprove = $user->admin || $user->hasRight('sgpayroll', 'ais', 'confirm');

// CSRF protection for state-changing AIS actions (Dolibarr has no automatic token check by default)
if (in_array($action, array('refresh_ya', 'confirm_all'), true) && !verifyToken(GETPOST('token', 'aZ09'))) {
	setEventMessages($langs->trans('InvalidToken'), null, 'errors');
	$action = '';
}

// Hooks
$hookmanager->initHooks(array('sgpayrollaisreview'));

// ── ACTIONS ──────────────────────────────────────────────────────────────────

if ($action === 'refresh_ya' && $canManageAIS) {
	// Recompute all rows from payroll records for this YA (income year = YA-1)
	$incomeYear = $ya - 1;
	sgpayroll_rebuild_ais_rows($db, $incomeYear, $ya, $user);
	setEventMessages($langs->trans('AisRowsRefreshed'), null, 'mesgs');
	header('Location: iras_ais_review.php?ya='.$ya);
	exit;
}

if ($action === 'batch_review' && $canManageAIS) {
	$tms = GETPOST('tms', 'array');
	if (!empty($tms) && is_array($tms)) {
		$db->begin();
		$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_ais_review";
		$sql .= " SET status='manager_reviewed', reviewed_by=".(int)$user->id.", reviewed_date=NOW()";
		$sql .= " WHERE year_of_assessment=".(int)$ya." AND rowid IN (".$db->sanitize(implode(',', array_filter($tms, 'is_numeric'))).")";
		if ($db->query($sql)) {
			$db->commit();
			dol_syslog('sgpayroll ais_review: batch reviewed by '.$user->id, LOG_INFO);
			setEventMessages($langs->trans('AisBatchReviewed'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		setEventMessages($langs->trans('NoRecordSelected'), null, 'warnings');
	}
	header('Location: iras_ais_review.php?ya='.$ya);
	exit;
}

if ($action === 'mark_reviewed' && $rowid && $canManageAIS) {
	$db->begin();
	$notes = GETPOST('notes', 'restricthtml');
	$flag  = GETPOST('discrepancy_flag', 'int');
	$sql   = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_ais_review";
	$sql  .= " SET status='manager_reviewed', reviewed_by=".(int)$user->id.",";
	$sql  .= " reviewed_date=NOW(), discrepancy_flag=".(int)$flag.",";
	$sql  .= " notes='".$db->escape($notes)."'";
	$sql  .= " WHERE rowid=".(int)$rowid." AND year_of_assessment=".(int)$ya;
	if ($db->query($sql)) {
		$db->commit();
		dol_syslog('sgpayroll ais_review: marked reviewed rowid='.$rowid.' by user='.$user->id, LOG_INFO);
		setEventMessages($langs->trans('AisRowReviewed'), null, 'mesgs');
	} else {
		$db->rollback();
		dol_syslog('sgpayroll ais_review: db error '.$db->lasterror(), LOG_ERR);
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: iras_ais_review.php?ya='.$ya);
	exit;
}

if ($action === 'confirm_all' && $canApprove) {
	$db->begin();
	$sql  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_ais_review";
	$sql .= " SET status='approver_confirmed', confirmed_by=".(int)$user->id.", confirmed_date=NOW()";
	$sql .= " WHERE year_of_assessment=".(int)$ya." AND status='manager_reviewed'";
	if ($db->query($sql)) {
		$db->commit();
		dol_syslog('sgpayroll ais_review: confirmed all for ya='.$ya.' by user='.$user->id, LOG_INFO);
		setEventMessages($langs->trans('AisAllConfirmed'), null, 'mesgs');

		// ── Auto-archive IR8A PDFs for all confirmed employees ────────────────
		$sqlEmp  = "SELECT ar.fk_user, u.lastname, u.firstname, e.nric_fin";
		$sqlEmp .= " FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review ar";
		$sqlEmp .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ar.fk_user";
		$sqlEmp .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = ar.fk_user AND e.entity = ar.entity";
		$sqlEmp .= " WHERE ar.year_of_assessment = ".(int)$ya." AND ar.status = 'approver_confirmed'";
		$sqlEmp .= " AND ar.entity = ".(int)$conf->entity;
		$resEmp  = $db->query($sqlEmp);
		$pdfOk   = 0;
		$pdfFail = 0;
		if ($resEmp) {
			// Load PDF class if available
			$pdfClassFile = DOL_DOCUMENT_ROOT.'/custom/sgpayroll/pdf/pdf_ir8a_sgpayroll.class.php';
			$hasPdfClass  = file_exists($pdfClassFile);
			if ($hasPdfClass) {
				dol_include_once('/custom/sgpayroll/pdf/pdf_ir8a_sgpayroll.class.php');
			}

			while ($emp = $db->fetch_object($resEmp)) {
				$fkUser = (int)$emp->fk_user;

				// Build archive path: DOL_DATA_ROOT/sgpayroll/{entity}/{fk_user}/IR8A_{YA}.pdf
				$dirPath = DOL_DATA_ROOT.'/sgpayroll/'.(int)$conf->entity.'/'.$fkUser;
				dol_mkdir($dirPath);
				$pdfPath = $dirPath.'/IR8A_'.(int)$ya.'.pdf';

				$generated = false;
				if ($hasPdfClass && class_exists('pdf_ir8a_sgpayroll')) {
					try {
						$doc = new pdf_ir8a_sgpayroll($db);
						if (method_exists($doc, 'write_file_for_user')) {
							$ret = $doc->write_file_for_user($fkUser, (int)$ya, $pdfPath, $langs);
							$generated = ($ret > 0);
						}
					} catch (Exception $e) {
						dol_syslog('sgpayroll IR8A pdf error fk_user='.$fkUser.': '.$e->getMessage(), LOG_WARNING);
					}
				}

				if (!$generated) {
					// Fallback: create a minimal placeholder text record
					$placeholder = "IR8A YA {$ya} — ".sgpayroll_format_employee_name($emp->firstname, $emp->lastname).' ('.(string)($emp->nric_fin ?? 'N/A').")\nGenerated: ".date('Y-m-d H:i:s')."\nStatus: Confirmed\nNote: Full PDF requires pdf_ir8a_sgpayroll class.";
					file_put_contents($pdfPath.'.txt', $placeholder);
					$pdfPath .= '.txt';
					$generated = true;
				}

				if ($generated) {
					// Record in sgpayroll_documents table
					$label   = 'IR8A YA '.(int)$ya;
					$docType = 'ir8a';
					$sqlDoc  = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."sgpayroll_documents";
					$sqlDoc .= " (fk_user, document_type, label, file_path, upload_date, entity)";
					$sqlDoc .= " VALUES (".$fkUser.",'".($db->escape($docType))."','".($db->escape($label))."'";
					$sqlDoc .= ",'".($db->escape($pdfPath))."',NOW(),".(int)$conf->entity.")";
					$db->query($sqlDoc); // Ignore errors (table may vary)
					$pdfOk++;
				} else {
					$pdfFail++;
				}
			}

			if ($pdfOk > 0) {
				setEventMessages(sprintf($langs->trans('IR8AArchivedCount'), $pdfOk), null, 'mesgs');
			}
			if ($pdfFail > 0) {
				setEventMessages(sprintf($langs->trans('IR8AArchiveFailed'), $pdfFail), null, 'warnings');
			}
		}
		// ── End IR8A archiving ────────────────────────────────────────────────

	} else {
		$db->rollback();
		dol_syslog('sgpayroll ais_review: error '.$db->lasterror(), LOG_ERR);
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: iras_ais_review.php?ya='.$ya);
	exit;
}

if ($action === 'confirm_one' && $rowid && $canApprove) {
	$db->begin();
	$sql  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_ais_review";
	$sql .= " SET status='approver_confirmed', confirmed_by=".(int)$user->id.", confirmed_date=NOW()";
	$sql .= " WHERE rowid=".(int)$rowid." AND year_of_assessment=".(int)$ya;
	if ($db->query($sql)) {
		$db->commit();
		dol_syslog('sgpayroll ais_review: confirmed one rowid='.$rowid.' by user='.$user->id, LOG_INFO);
	} else {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: iras_ais_review.php?ya='.$ya);
	exit;
}

// ── FETCH DATA ────────────────────────────────────────────────────────────────
$sql  = "SELECT ar.*, u.lastname, u.firstname, u.login,";
$sql .= " e.citizenship, e.id_type, e.nric_fin, e.employment_type";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review ar";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ar.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = ar.fk_user AND e.entity = ar.entity";
$sql .= " WHERE ar.year_of_assessment = ".(int)$ya." AND ar.entity = ".(int)$conf->entity;
if ($ownOnlyAIS) {
	$sql .= " AND ar.fk_user = ".(int)$user->id;
}
$sql .= " ORDER BY u.lastname, u.firstname";
$res  = $db->query($sql);
$rows = array();
while ($res && $obj = $db->fetch_object($res)) {
	$rows[] = $obj;
}

// Count statuses
$cntDraft     = 0; $cntReviewed = 0; $cntConfirmed = 0; $cntFlag = 0;
foreach ($rows as $r) {
	if ($r->status === 'draft')                $cntDraft++;
	elseif ($r->status === 'manager_reviewed') $cntReviewed++;
	elseif ($r->status === 'approver_confirmed') $cntConfirmed++;
	if (!empty($r->discrepancy_flag)) $cntFlag++;
}

// ── PAGE ─────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('IrasAisReview'), '');

$title = $langs->trans('IrasAisReview').' — YA '.$ya.' ('.$langs->trans('IncomeYear').': '.($ya-1).')';
print load_fiche_titre($title, '', 'sgpayroll@sgpayroll');

// ── CUSTOM CSS FOR DASHBOARD ───────────────────────────────────────────────
print '
<style>
/* Dashboard Container */
.ir8a-dashboard {
	margin-bottom: 20px;
}
/* Unified Header Bar */
.ir8a-header-bar {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: #fff;
	padding: 15px 20px;
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	margin-bottom: 20px;
	border: 1px solid #e1e5eb;
}
.ir8a-header-left {
	display: flex;
	align-items: center;
	gap: 15px;
}
.ir8a-header-left span {
	font-weight: 500;
	color: #333;
}
.ir8a-header-right {
	display: flex;
	gap: 10px;
	align-items: center;
}
.ir8a-header-right .butAction {
	margin: 0;
	padding: 8px 16px;
}
/* Horizontal Stat Cards */
.ir8a-stats-container {
	display: flex;
	gap: 15px;
	flex-wrap: wrap;
	margin-bottom: 20px;
}
.ir8a-stat-card {
	flex: 1;
	min-width: 140px;
	background: #fff;
	border-radius: 8px;
	padding: 15px 20px;
	border: 1px solid #e1e5eb;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	position: relative;
	overflow: hidden;
}
/* Colored left edge for cards */
.ir8a-stat-card::before {
	content: "";
	position: absolute;
	left: 0;
	top: 0;
	height: 100%;
	width: 4px;
}
.ir8a-card-total::before { background-color: #4b88e5; }
.ir8a-card-draft::before { background-color: #8c9ba5; }
.ir8a-card-reviewed::before { background-color: #27a8e0; }
.ir8a-card-confirmed::before { background-color: #2ab871; }
.ir8a-card-flagged::before { background-color: #e54b4b; }

.ir8a-stat-title {
	font-size: 0.85em;
	color: #6c757d;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	margin-bottom: 8px;
}
.ir8a-stat-value {
	font-size: 1.8em;
	font-weight: 600;
	color: #333;
	line-height: 1;
}
/* Table enhancements */
.div-table-responsive {
	background: #fff;
	border-radius: 8px;
	padding: 10px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.05);
	border: 1px solid #e1e5eb;
}
table.tagtable.liste tr.liste_titre td {
	background: #f8f9fa;
	border-bottom: 2px solid #e1e5eb;
	padding: 12px 10px;
	font-weight: 600;
	color: #495057;
}
table.tagtable.liste tr.oddeven td {
	padding: 12px 10px;
	border-bottom: 1px solid #f1f3f5;
	vertical-align: middle;
}
</style>
';

print '<div class="ir8a-dashboard">';

// Unified Header Bar (YA Selector & Actions)
print '<div class="ir8a-header-bar">';
print '<div class="ir8a-header-left">';
print '<form method="GET" action="iras_ais_review.php" style="display:flex; align-items:center; gap:10px;">';
print '<span>'.$langs->trans('YearOfAssessment').' ('.$langs->trans('IncomeYear').' '.($ya-1).'):</span>';
print '<input type="number" name="ya" value="'.$ya.'" min="2024" max="2035" class="flat" style="width:80px; padding: 5px;">';
print '<button type="submit" class="button buttongen" style="margin:0; padding:4px 12px;">'.$langs->trans('Apply').'</button>';
print '</form>';
print '</div>'; // end left

print '<div class="ir8a-header-right">';
if ($canManageAIS) {
	print '<form method="POST" action="iras_ais_review.php" style="display:inline">';
	print '<input type="hidden" name="ya" value="'.$ya.'"><input type="hidden" name="token" value="'.newToken().'">';
	print '<button name="action" value="refresh_ya" class="butAction">'.img_picto('','refresh','class="paddingright"').$langs->trans("RefreshFromPayroll").'</button></form>';
	if ($canApprove && $cntReviewed > 0) {
		print '<form method="POST" action="iras_ais_review.php" style="display:inline">';
		print '<input type="hidden" name="ya" value="'.$ya.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button name="action" value="confirm_all" class="butAction">'.$langs->trans("ConfirmAllReviewed").' ('.$cntReviewed.')</button></form>';
	}
}
if ($cntConfirmed > 0) {
	print '<a href="export/iras_ir8a_export.php?ya='.$ya.'" class="butAction">'.img_picto('', 'download', 'class="paddingright"').$langs->trans('ExportIR8AXML').'</a>';
}
// Add portal links logic
print sgpayroll_portal_links('ais');
print '</div>'; // end right
print '</div>'; // end header bar

if ($ownOnlyAIS && empty($rows)) {
	print '<div class="info">'.img_picto('', 'info', 'class="paddingright"').$langs->trans('NoAisDataForYouYet').'</div>';
	print '</div>'; // end dashboard
	llxFooter(); exit;
}

// Short workflow hint (only for managers; matches Dolibarr/SG Payroll info style)
if ($canManageAIS) {
	print '<div class="info" style="margin-bottom:16px">';
	print '<i class="fas fa-info-circle" style="margin-right:6px"></i> ';
	print $langs->trans('AISWorkflowGuideDesc');
	print '</div>';
}

// Horizontal Stat Cards

print '<div class="ir8a-stats-container">';

print '<div class="ir8a-stat-card ir8a-card-total">';
print '<div class="ir8a-stat-title">'.$langs->trans('TotalEmployees').'</div>';
print '<div class="ir8a-stat-value">'.count($rows).'</div>';
print '</div>';

print '<div class="ir8a-stat-card ir8a-card-draft">';
print '<div class="ir8a-stat-title">'.$langs->trans('Draft').'</div>';
print '<div class="ir8a-stat-value">'.($cntDraft>0 ? '<span style="color:#6c757d">'.$cntDraft.'</span>' : '0').'</div>';
print '</div>';

print '<div class="ir8a-stat-card ir8a-card-reviewed">';
print '<div class="ir8a-stat-title">'.$langs->trans('ManagerReviewed').'</div>';
print '<div class="ir8a-stat-value">'.($cntReviewed>0 ? '<span style="color:#27a8e0">'.$cntReviewed.'</span>' : '0').'</div>';
print '</div>';

print '<div class="ir8a-stat-card ir8a-card-confirmed">';
print '<div class="ir8a-stat-title">'.$langs->trans('ApproverConfirmed').'</div>';
print '<div class="ir8a-stat-value">'.($cntConfirmed>0 ? '<span style="color:#2ab871">'.$cntConfirmed.'</span>' : '0').'</div>';
print '</div>';

print '<div class="ir8a-stat-card ir8a-card-flagged">';
print '<div class="ir8a-stat-title">'.$langs->trans('DiscrepancyFlagged').'</div>';
print '<div class="ir8a-stat-value">'.($cntFlag>0 ? '<span style="color:#e54b4b">'.$cntFlag.'</span>' : '0').'</div>';
print '</div>';

print '</div>'; // end stats container

// Main review table
$hasDrafts = false;
print '<form method="POST" action="iras_ais_review.php" id="form_ais">';
print '<input type="hidden" name="action" value="batch_review">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="ya" value="'.$ya.'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
print '<tr class="liste_titre">';
if ($canManageAIS) print '<td><input type="checkbox" id="checkall_ais" onclick="toggleAllAIS(this)"></td>';
$cols = array('Employee','IdType','Citizenship','Employment',
               'GrossSalary','Bonus','Commission','TransportAllow','OtherAllow','OvertimePay',
               'BIK','EmployeeCPF','TaxableIncome','Status','');
foreach ($cols as $c) {
	print '<td'.($c==='' ? '' : '').'>'.$langs->trans($c?:'&nbsp;').'</td>';
}
print '</tr>';

foreach ($rows as $r) {
	// Status CSS Mapping
	$badgeClass = 'badge-status0'; // Default draft (grey)
	if ($r->status === 'approver_confirmed')   $badgeClass = 'badge-status4'; // Standard Green
	elseif ($r->status === 'manager_reviewed') $badgeClass = 'badge-status1'; // Standard Blue
	$flagHtml = !empty($r->discrepancy_flag) ? ' '.img_picto($langs->trans('DiscrepancyFlagged'), 'warning', 'class="opacitymedium"') : '';

	print '<tr class="oddeven">';
	if ($canManageAIS) {
		print '<td>';
		if ($r->status === 'draft') {
			print '<input type="checkbox" name="tms[]" value="'.$r->rowid.'" class="ais_checkbox">';
			$hasDrafts = true;
		}
		print '</td>';
	}
	print '<td><a href="employee_card.php?fk_user='.$r->fk_user.'">'.dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</a>'.$flagHtml.'</td>';
	print '<td>'.dol_escape_htmltag($r->id_type).'</td>';
	print '<td>'.dol_escape_htmltag($r->citizenship).'</td>';
	print '<td>'.dol_escape_htmltag($r->employment_type).'</td>';
	print '<td class="right">'.price($r->gross_salary).'</td>';
	print '<td class="right">'.price($r->bonus).'</td>';
	print '<td class="right">'.price($r->commission).'</td>';
	print '<td class="right">'.price($r->transport_allowance).'</td>';
	print '<td class="right">'.price($r->other_allowances).'</td>';
	print '<td class="right">'.price($r->overtime_pay).'</td>';
	print '<td class="right">'.price($r->bik_value).'</td>';
	print '<td class="right">'.price($r->employee_cpf).'</td>';
	print '<td class="right"><strong>'.price($r->taxable_income).'</strong></td>';
	print '<td><span class="badge '.$badgeClass.'">'.dol_escape_htmltag(ucfirst(str_replace('_',' ',$r->status))).'</span></td>';
	print '<td>';
	// Review action (HR Manager)
	if ($r->status === 'draft' && $canManageAIS) {
		print '<a href="#" onclick="showReviewDialog('.$r->rowid.','.(int)$r->discrepancy_flag.',\''.dol_escape_js($r->notes??'').'\');" class="button buttongen smallpaddingimp">'.$langs->trans('Review').'</a> ';
	}
	// Confirm action (HR Approver)
	if ($r->status === 'manager_reviewed' && $canApprove) {
		print '<form method="POST" action="iras_ais_review.php" style="display:inline">';
		print '<input type="hidden" name="action" value="confirm_one">';
		print '<input type="hidden" name="rowid" value="'.$r->rowid.'">';
		print '<input type="hidden" name="ya" value="'.$ya.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="submit" value="'.$langs->trans('Confirm').'" class="button buttongen smallpaddingimp">';
		print '</form>';
	}
	// Monthly breakdown drill-down
	$payslip_list_url = 'payslip_list.php?mode=own&fk_user='.$r->fk_user.'&search_year='.($ya - 1);
	print '<a href="'.$payslip_list_url.'" class="button buttongen smallpaddingimp" title="'.$langs->trans('ViewPayslips').'">';
	print img_picto('', 'search').'</a>';
	// IR8A PDF download
	$ir8a_url = './export/pdf_ir8a.php?user_id='.$r->fk_user.'&ya='.$ya;
	print '<a href="'.$ir8a_url.'" class="button buttongen smallpaddingimp" target="_blank" title="'.$langs->trans('DownloadIR8A').'">';
	print img_picto('', 'pdf').'</a>';
	print '</td>';
	print '</tr>';
}

if (empty($rows)) {
	print '<tr><td colspan="15" class="opacitymedium center">'.$langs->trans('NoAisDataFound').' — '.$langs->trans('ClickRefreshFromPayroll').'</td></tr>';
}

print '</table></div>';

if ($canManageAIS && !empty($rows) && $hasDrafts) {
	print '<div class="tabsAction">';
	print '<button type="submit" class="butAction">'.$langs->trans('MarkSelectedAsReviewed').'</button>';
	print '</div>';
}
print '</form>';

print '<script>
function toggleAllAIS(master) {
	var cb = document.querySelectorAll(".ais_checkbox");
	cb.forEach(function(c) { c.checked = master.checked; });
}
</script>';

// Hidden review dialog (inline modal via Dolibarr dialog)
?>
<div id="reviewDialog" style="display:none">
  <form id="reviewForm" method="POST" action="iras_ais_review.php">
    <input type="hidden" name="action" value="mark_reviewed">
    <input type="hidden" name="ya" value="<?php echo $ya; ?>">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="rowid" id="reviewRowid" value="">
    <table class="border centpercent">
      <tr>
        <td class="titlefield"><?php echo $langs->trans('DiscrepancyFlag'); ?></td>
        <td>
          <input type="checkbox" name="discrepancy_flag" id="discrepancyFlag" value="1"> 
          <?php echo $langs->trans('MarkDiscrepancy'); ?>
        </td>
      </tr>
      <tr>
        <td><?php echo $langs->trans('Notes'); ?></td>
        <td><textarea name="notes" id="reviewNotes" rows="4" style="width:100%"></textarea></td>
      </tr>
      <tr>
        <td colspan="2" class="center">
          <input type="submit" value="<?php echo $langs->trans('MarkAsReviewed'); ?>" class="butAction">
        </td>
      </tr>
    </table>
  </form>
</div>
<script>
function showReviewDialog(rowid, flag, notes) {
    document.getElementById('reviewRowid').value = rowid;
    document.getElementById('discrepancyFlag').checked = (flag == 1);
    document.getElementById('reviewNotes').value = notes;
    jQuery('#reviewDialog').dialog({
        title: '<?php echo dol_escape_js($langs->trans("ReviewEmployeeAIS")); ?>',
        width: 500,
        modal: true,
        buttons: {}
    });
}
</script>
<?php
llxFooter();
$db->close();
