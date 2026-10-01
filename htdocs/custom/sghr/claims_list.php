<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file claims_list.php — Expense claims submit & approve */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

dol_include_once('sghr/lib/sghr.lib.php');

if (!isModEnabled("sghr")) accessforbidden();

$canApprove  = $user->admin || $user->hasRight('sghr', 'claims', 'approve');
$canSubmit   = $user->admin || $user->hasRight('sghr', 'claims', 'submit');

// Identify subordinates if viewer is a supervisor
$subordinateIds = array();
$sqlSub = "SELECT fk_user FROM ".MAIN_DB_PREFIX."sgpayroll_employee WHERE fk_supervisor = ".(int)$user->id." AND entity = ".(int)$conf->entity;
$resSub = $db->query($sqlSub);
while ($resSub && $objS = $db->fetch_object($resSub)) {
	$subordinateIds[] = (int)$objS->fk_user;
}
$isSupervisor = !empty($subordinateIds);

$viewAll     = $canApprove || $user->hasRight('sghr', 'employee', 'read');
$viewSub     = $isSupervisor;

$ownOnly = !$viewAll && !$viewSub;
if ($ownOnly) {
	// Simple employee: only self
	$restrictSql = " AND c.fk_user = ".(int)$user->id;
} elseif ($viewAll) {
	// HR Manager: see all
	$restrictSql = "";
} else {
	// Supervisor: see self + subordinates
	$ids = $subordinateIds;
	$ids[] = $user->id;
	$restrictSql = " AND c.fk_user IN (".implode(',', $ids).")";
}

if (!$canSubmit && !$viewAll && !$viewSub && !$user->hasRight('sghr', 'employee', 'read_own')) {
	accessforbidden();
}

$langs->loadLangs(array('sghr@sghr'));
$action      = GETPOST('action', 'aZ');
$hookmanager->initHooks(array('sghrclaimslist'));

// ── SUBMIT ───────────────────────────────────────────────────────────────────
if ($action === 'submit' && $canSubmit) {
	$db->begin();
	$sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_claims"
	     . " (fk_user, claim_date, category, description, amount, is_cpf_liable, status, entity, date_creation, fk_user_creat)"
	     . " VALUES (".(int)$user->id.",'".$db->escape(GETPOST('claim_date','alpha'))."',"
	     . "'".$db->escape(GETPOST('category','aZ'))."',"
	     . "'".$db->escape(GETPOST('description','restricthtml'))."',"
	     . (float)price2num(GETPOST('amount','alpha'),'MU').","
	     . (GETPOST('is_cpf_liable','int')?1:0).",'submitted',".(int)$conf->entity.",NOW(),".(int)$user->id.")";
	if ($db->query($sql)) {
		$db->commit();
		dol_syslog('sgpayroll claims_list: claim submitted user='.$user->id, LOG_INFO);
		setEventMessages($langs->trans('ClaimSubmitted'), null, 'mesgs');
	} else {
		$db->rollback();
		dol_syslog('sgpayroll claims_list: submit error '.$db->lasterror(), LOG_ERR);
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: claims_list.php'); exit;
}
if ($action === 'approve' && $canApprove) {
	$rid = GETPOST('rowid','int');
	$db->begin();
	if ($db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_claims SET status='approved', fk_user_approve=".(int)$user->id.", date_approve=NOW() WHERE rowid=".(int)$rid)) {
		$db->commit();
		dol_syslog('sgpayroll claims_list: approved claim rowid='.$rid, LOG_INFO);
	} else {
		$db->rollback();
	}
	header('Location: claims_list.php'); exit;
}
if ($action === 'reject' && $canApprove) {
	$rid = GETPOST('rowid','int');
	$db->begin();
	if ($db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_claims SET status='rejected', fk_user_approve=".(int)$user->id." WHERE rowid=".(int)$rid)) {
		$db->commit();
		dol_syslog('sgpayroll claims_list: rejected claim rowid='.$rid, LOG_INFO);
	} else {
		$db->rollback();
	}
	header('Location: claims_list.php'); exit;
}
if ($action === 'unapprove' && $canApprove) {
	$rid = GETPOST('rowid','int');
	$db->begin();
	if ($db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_claims SET status='submitted' WHERE rowid=".(int)$rid)) {
		$db->commit();
		setEventMessages($langs->trans('RecordUnapproved'), null, 'mesgs');
	} else {
		$db->rollback();
	}
	header('Location: claims_list.php'); exit;
}

if ($action === 'delete') {
	$rid = GETPOST('rowid','int');
	// Fetch to check ownership
	$sqlF = "SELECT fk_user, status FROM ".MAIN_DB_PREFIX."sgpayroll_claims WHERE rowid = ".(int)$rid;
	$resF = $db->query($sqlF);
	$objF = $db->fetch_object($resF);
	
	$isOwner = ($objF && $objF->fk_user == $user->id);
	$canDelete = $user->admin || ($isOwner && $objF->status === 'submitted') || $user->hasRight('sghr', 'claims', 'approve');

	if ($canDelete && $rid > 0) {
		$db->begin();
		if ($db->query("DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_claims WHERE rowid = ".(int)$rid)) {
			$db->commit();
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		accessforbidden('Not allowed to delete this claim');
	}
	
	$backUrl = 'claims_list.php';
	if (GETPOST('fk_user', 'int')) $backUrl .= '?fk_user='.GETPOST('fk_user', 'int');
	header('Location: '.$backUrl); 
	exit;
}

// ── BATCH ACTIONS ─────────────────────────────────────────────────────────────
if ($action === 'batch_approve' && ($canApprove || $isSupervisor)) {
	$tids = GETPOST('tms', 'array:int');
	if (!empty($tids)) {
		$db->begin();
		$count = 0;
		foreach ($tids as $rid) {
			// Security: if not admin/HR, check if subordinate
			if (!$canApprove) {
				$sqlSec = "SELECT fk_user FROM ".MAIN_DB_PREFIX."sgpayroll_claims WHERE rowid = ".(int)$rid;
				$resSec = $db->query($sqlSec);
				$objSec = $db->fetch_object($resSec);
				if (!$objSec || !in_array((int)$objSec->fk_user, $subordinateIds)) continue;
			}
			
			if ($db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_claims SET status='approved', fk_user_approve=".(int)$user->id.", date_approve=NOW() WHERE rowid=".(int)$rid." AND status='submitted'")) {
				$count++;
			}
		}
		$db->commit();
		setEventMessages($langs->trans('BatchApprovedSuccess', $count), null, 'mesgs');
	}
	header('Location: claims_list.php'); exit;
}

// ── FETCH ─────────────────────────────────────────────────────────────────────
$sql  = "SELECT c.*, u.lastname, u.firstname FROM ".MAIN_DB_PREFIX."sgpayroll_claims c";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = c.fk_user";
$sql .= " WHERE c.entity = ".(int)$conf->entity;
$sql .= $restrictSql;
$sql .= " ORDER BY c.claim_date DESC LIMIT 200";
$res  = $db->query($sql); $rows = array();
while ($res && $obj = $db->fetch_object($res)) $rows[] = $obj;

// ── PAGE ─────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('ExpenseClaims'), '');

$fk_user_for_tabs = GETPOST('fk_user', 'int') ?: ($ownOnly ? $user->id : 0);
if ($fk_user_for_tabs > 0) {
	$user_for_title = new User($db);
	$user_for_title->fetch($fk_user_for_tabs);
	print load_fiche_titre($langs->trans('EmployeeProfile').' — '.$user_for_title->getFullName($langs), '', 'title_hrm');

	$head = sghr_employee_prepare_head($fk_user_for_tabs);
	dol_fiche_head($head, 'claims', '', 0, '');
}

print load_fiche_titre($langs->trans('ExpenseClaims'), '', 'sghr@sghr');

$categories = array('Transport','Meal','Medical','Entertainment','Other');
print '<form method="POST" action="claims_list.php">';
print '<input type="hidden" name="action" value="submit"><input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder" style="width:auto"><tr class="liste_titre"><td colspan="7">'.$langs->trans('NewClaim').'</td></tr><tr>';
print '<td><input type="date" name="claim_date" value="'.dol_print_date(dol_now(),'%Y-%m-%d').'" class="flat"></td>';
print '<td><select name="category" class="flat">';
foreach ($categories as $c) print '<option value="'.$c.'">'.$c.'</option>';
print '</select></td>';
print '<td><input type="text" name="description" placeholder="'.$langs->trans('Description').'" class="flat" style="width:200px"></td>';
print '<td>S$<input type="number" name="amount" value="0.00" step="0.01" class="flat width100 right"></td>';
print '<td><input type="checkbox" name="is_cpf_liable" value="1"> CPF-liable</td>';
print '<td><input type="submit" value="'.$langs->trans('Submit').'" class="button"></td>';
print '</tr></table></form><br>';

$badgeMap = array('draft'=>'badge-status0','submitted'=>'badge-status4','approved'=>'badge-status1','rejected'=>'badge-status8','paid'=>'badge-status9');
print '<form method="POST" action="claims_list.php">';
print '<input type="hidden" name="action" value="batch_approve">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<div class="div-table-responsive"><table class="tagtable liste">';
print '<tr class="liste_titre">';
if ($canApprove || $isSupervisor) print '<td><input type="checkbox" id="checkall_claims" onclick="toggleAllClaims(this)"></td>';
print '<td>'.$langs->trans('Employee').'</td>';
print '<td>'.$langs->trans('ClaimDate').'</td>';
print '<td>'.$langs->trans('Category').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td class="right">'.$langs->trans('ClaimAmount').'</td>';
print '<td class="center">'.$langs->trans('IsCpfLiable').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td class="center">'.$langs->trans('Action').'</td>';
print '</tr>';

foreach ($rows as $r) {
	print '<tr class="oddeven">';
	if ($canApprove || $isSupervisor) {
		print '<td>';
		if ($r->status === 'submitted' && ($canApprove || in_array((int)$r->fk_user, $subordinateIds))) {
			print '<input type="checkbox" name="tms[]" value="'.$r->rowid.'" class="claim_checkbox">';
		}
		print '</td>';
	}
	print '<td>'.dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</td>';
	print '<td>'.dol_escape_htmltag($r->claim_date).'</td>';
	print '<td>'.dol_escape_htmltag($r->category).'</td>';
	print '<td>'.dol_escape_htmltag($r->description).'</td>';
	print '<td class="right">'.price($r->amount).'</td>';
	print '<td class="center">'.($r->is_cpf_liable?'✓':'').'</td>';
	print '<td class="center"><span class="badge '.($badgeMap[$r->status]??'badge-status0').'">'.dol_escape_htmltag(ucfirst($r->status)).'</span></td>';
	print '<td class="center">';
	if (($canApprove || $isSupervisor) && $r->status === 'submitted') {
		print '<form method="POST" action="claims_list.php" style="display:inline">';
		print '<input type="hidden" name="rowid" value="'.$r->rowid.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button name="action" value="approve" class="button buttongen smallpaddingimp">'.$langs->trans('Approve').'</button> ';
		if ($canApprove) print '<button name="action" value="reject"  class="button buttongen smallpaddingimp">'.$langs->trans('Refuse').'</button>';
		print '</form>';
	} elseif ($canApprove && $r->status === 'approved') {
		print '<form method="POST" action="claims_list.php" style="display:inline">';
		print '<input type="hidden" name="rowid" value="'.$r->rowid.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button name="action" value="unapprove" class="button buttongen smallpaddingimp">'.$langs->trans('BackToDraft').'</button>';
		print '</form>';
	}
	
	$isOwner = ((int)$r->fk_user === (int)$user->id);
	$canDeleteThis = $user->admin || $user->hasRight('sghr', 'claims', 'approve') || ($isOwner && $r->status === 'submitted');
	if ($canDeleteThis) {
		print ' <form method="POST" action="claims_list.php" style="display:inline">';
		print '<input type="hidden" name="action" value="delete"><input type="hidden" name="rowid" value="'.$r->rowid.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<button type="submit" class="button buttongen smallpaddingimp" onclick="return confirm(\''.$langs->transnoentities('ConfirmDelete').'?\')">'.img_picto('', 'delete').'</button>';
		print '</form>';
	}
	print '</td></tr>';
}
if (empty($rows)) print '<tr><td colspan="'.(($canApprove || $isSupervisor)?9:8).'" class="opacitymedium center">'.$langs->trans('NoRecordFound').'</td></tr>';
print '</table></div>';

if (($canApprove || $isSupervisor) && !empty($rows)) {
	print '<div class="tabsAction">';
	print '<button type="submit" class="butAction">'.$langs->trans('ApproveSelected').'</button>';
	print '</div>';
}
print '</form>';

print '<script>
function toggleAllClaims(master) {
	var cb = document.querySelectorAll(".claim_checkbox");
	cb.forEach(function(c) { c.checked = master.checked; });
}
</script>';
llxFooter();
$db->close();
