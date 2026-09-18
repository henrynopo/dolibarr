<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        portal_requests.php
 * \ingroup     sgpayroll
 * \brief       HR Management page for Employee Self-Service Change Requests.
 *              Allows HR to review, respond to, and close employee info change requests.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->admin && !$user->hasRight('sgpayroll', 'employee', 'write')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm'));
$entity = (int)($conf->entity ?? 1);
$action = GETPOST('action', 'aZ09');
$rowid  = GETPOSTINT('rowid');

// ── ACTIONS ───────────────────────────────────────────────────────────────────
if (in_array($action, array('mark_done', 'mark_rejected')) && $rowid) {
	$newStatus    = $action === 'mark_done' ? 'done' : 'rejected';
	$reviewerNote = GETPOST('reviewer_note', 'restricthtml');
	$sql  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_portal_requests";
	$sql .= " SET status='".$db->escape($newStatus)."'";
	$sql .= ", date_reviewed=NOW(), fk_user_reviewer=".(int)$user->id;
	$sql .= ", reviewer_note='".$db->escape($reviewerNote)."'";
	$sql .= " WHERE rowid=".$rowid." AND entity=".$entity;
	if ($db->query($sql)) {
		setEventMessages($langs->trans('StatusUpdated'), null, 'mesgs');
	} else {
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: portal_requests.php');
	exit;
}

// ── Filter ────────────────────────────────────────────────────────────────────
$searchStatus = GETPOST('search_status', 'aZ') ?: 'pending';
if (GETPOST('button_removefilter_x', 'alpha')) $searchStatus = '';

// ── Query ─────────────────────────────────────────────────────────────────────
$sql  = "SELECT pr.rowid, pr.fk_user, pr.request_type, pr.note, pr.status,";
$sql .= " pr.date_request, pr.reviewer_note, pr.fk_user_reviewer,";
$sql .= " u.lastname, u.firstname, rv.lastname AS rev_lastname, rv.firstname AS rev_firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_portal_requests pr";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pr.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user rv ON rv.rowid = pr.fk_user_reviewer";
$sql .= " WHERE pr.entity = ".$entity;
if ($searchStatus) $sql .= " AND pr.status = '".$db->escape($searchStatus)."'";
$sql .= " ORDER BY pr.date_request DESC";

$resql = $db->query($sql);
$rows  = array();
if ($resql) while ($obj = $db->fetch_object($resql)) $rows[] = $obj;

// Summary counts
$sqlCnt  = "SELECT status, COUNT(*) AS cnt FROM ".MAIN_DB_PREFIX."sgpayroll_portal_requests";
$sqlCnt .= " WHERE entity =".$entity." GROUP BY status";
$resCnt  = $db->query($sqlCnt);
$counts  = array('pending' => 0, 'done' => 0, 'rejected' => 0);
if ($resCnt) while ($obj = $db->fetch_object($resCnt)) $counts[$obj->status] = (int)$obj->cnt;

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
llxHeader('', $langs->trans('PortalRequests'), '');

print load_fiche_titre('<i class="fas fa-inbox"></i> '.$langs->trans('PortalRequests'), '', 'user');

// Summary badges
print '<div style="display:flex;gap:15px;margin-bottom:20px">';
$statItems = array(
	'pending'  => array('label' => $langs->trans('Pending'), 'color' => '#e67e22', 'icon' => 'fas fa-clock'),
	'done'     => array('label' => $langs->trans('Done'),    'color' => '#27ae60', 'icon' => 'fas fa-check-circle'),
	'rejected' => array('label' => $langs->trans('Rejected'),'color' => '#e74c3c', 'icon' => 'fas fa-times-circle'),
);
foreach ($statItems as $s => $info) {
	print '<a href="portal_requests.php?search_status='.$s.'" style="text-decoration:none;flex:1">';
	print '<div style="background:#fff;border:2px solid '.($searchStatus===$s?$info['color']:'#e0e0e0').';border-radius:8px;padding:12px;text-align:center">';
	print '<div style="font-size:1.6em;font-weight:bold;color:'.$info['color'].'">'.$counts[$s].'</div>';
	print '<div style="color:#666;font-size:0.85em"><i class="'.$info['icon'].'"></i> '.$info['label'].'</div>';
	print '</div></a>';
}
print '</div>';

// Filter bar
print '<form method="GET" action="portal_requests.php" style="margin-bottom:15px">';
print '<select name="search_status" class="flat">';
foreach (array('' => $langs->trans('AllStatuses'), 'pending' => $langs->trans('Pending'), 'done' => $langs->trans('Done'), 'rejected' => $langs->trans('Rejected')) as $v => $l) {
	print '<option value="'.$v.'"'.($searchStatus===$v?' selected':'').'>'.$l.'</option>';
}
print '</select> ';
print '<input type="submit" value="'.$langs->trans('Search').'" class="button"> ';
print '<input type="submit" name="button_removefilter_x" value="'.$langs->trans('Reset').'" class="button">';
print '</form>';

if (empty($rows)) {
	print '<div class="info">'.$langs->trans('NoChangeRequests').'</div>';
	llxFooter(); $db->close(); exit;
}

// ── Request list ──────────────────────────────────────────────────────────────
$typeLabels = array(
	'bank_info'     => $langs->trans('BankInfoChange'),
	'address'       => $langs->trans('AddressChange'),
	'emergency'     => $langs->trans('EmergencyContactChange'),
	'tax_residency' => $langs->trans('TaxResidencyChange'),
	'other'         => $langs->trans('OtherChange'),
);
$statusClass = array('pending' => 'badge-status1', 'done' => 'badge-status4', 'rejected' => 'badge-status8');

foreach ($rows as $req) {
	$sClass = $statusClass[$req->status] ?? 'badge-status0';
	print '<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:15px 20px;margin-bottom:15px;box-shadow:0 2px 4px rgba(0,0,0,0.04)">';

	// Header row
	print '<div style="display:flex;justify-content:space-between;align-items:center">';
	print '<div><b>'.dol_escape_htmltag(sgpayroll_format_employee_name($req->firstname, $req->lastname)).'</b>';
	print ' &nbsp;<span class="badge badge-status1">'.dol_escape_htmltag($typeLabels[$req->request_type] ?? $req->request_type).'</span>';
	print '</div>';
	print '<div style="color:#666;font-size:0.85em">'.dol_print_date($db->jdate($req->date_request), 'dayhour').'</div>';
	print '<span class="badge '.$sClass.'">'.dol_escape_htmltag(ucfirst($req->status)).'</span>';
	print '</div>';

	// Request note
	print '<div style="margin:10px 0;color:#333;background:#f9f9f9;padding:8px 12px;border-left:3px solid #2980b9;border-radius:0 4px 4px 0">';
	print nl2br(dol_escape_htmltag($req->note ?? ''));
	print '</div>';

	// If pending: action form
	if ($req->status === 'pending') {
		print '<form method="POST" action="portal_requests.php" style="display:inline-block">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="rowid" value="'.(int)$req->rowid.'">';
		print '<textarea name="reviewer_note" class="flat" rows="2" style="width:60%;margin-right:10px" placeholder="'.$langs->trans('ReviewerNote').'..."></textarea>';
		print '<button name="action" value="mark_done" class="butAction" style="background:#27ae60"><i class="fas fa-check"></i> '.$langs->trans('MarkDone').'</button>';
		print '<button name="action" value="mark_rejected" class="butActionDelete" style="margin-left:5px"><i class="fas fa-times"></i> '.$langs->trans('MarkRejected').'</button>';
		print '</form>';
	} else {
		// Show reviewer response
		print '<div style="color:#666;font-size:0.9em">';
		print '<i class="fas fa-user-tie"></i> <b>'.$langs->trans('ReviewedBy').':</b> '.
			dol_escape_htmltag(sgpayroll_format_employee_name($req->rev_firstname, $req->rev_lastname));
		if ($req->reviewer_note) {
			print ' — '.dol_escape_htmltag($req->reviewer_note);
		}
		print '</div>';
	}

	print '</div>';
}

llxFooter();
$db->close();
