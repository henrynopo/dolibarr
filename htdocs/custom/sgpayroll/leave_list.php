<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/**
 * \file        leave_list.php
 * \ingroup     sgpayroll
 * \brief       Leave application and approval — integrates with Dolibarr Holiday module.
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
// Load Holiday class so we use the ORM instead of raw SQL
if (isModEnabled('holiday')) {
	require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
}
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

// ── Security ──────────────────────────────────────────────────────────────────
if (!isModEnabled('sgpayroll')) accessforbidden();

$canApprove = $user->admin || $user->hasRight('sgpayroll', 'leave', 'approve');
$canApply   = $user->admin || $user->hasRight('sgpayroll', 'leave', 'apply');
$viewAll    = $canApprove || $user->hasRight('sgpayroll', 'employee', 'read');

if (!$canApply && !$viewAll) {
    // If not admin and no specific apply/approve rights, at least check if they can see their own
    $result = restrictedArea($user, 'sgpayroll', 0, '', 'leave', '', 'apply');
}

// ── Hooks ─────────────────────────────────────────────────────────────────────
$hookmanager->initHooks(array('sgpayrollleavelist'));

// ── Translations ──────────────────────────────────────────────────────────────
$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm', 'Holiday@holiday'));

$action     = GETPOST('action', 'aZ09');

$form = new Form($db);

// ── ACTIONS ───────────────────────────────────────────────────────────────────
if ($action === 'apply' && $user->hasRight('sgpayroll', 'leave', 'apply')) {
	$leaveType = GETPOST('leave_type', 'aZ');
	$dateFrom  = dol_mktime(0, 0, 0, GETPOST('date_from_month', 'int'), GETPOST('date_from_day', 'int'), GETPOST('date_from_year', 'int'));
	$dateTo    = dol_mktime(0, 0, 0, GETPOST('date_to_month', 'int'), GETPOST('date_to_day', 'int'), GETPOST('date_to_year', 'int'));
	$numDays   = GETPOST('num_days', 'float');
	$reason    = GETPOST('reason', 'restricthtml');

	if (empty($dateFrom) || empty($dateTo) || $numDays <= 0) {
		setEventMessages($langs->trans('ErrorMissingMandatoryValue'), null, 'errors');
	} else {
		// Use Holiday class if module is enabled; otherwise direct insert with transactions
		$leave = new Holiday($db);
		$leave->fk_user    = $user->id;
		$leave->statut     = Holiday::STATUS_DRAFT;
		$leave->date_debut = $dateFrom;
		$leave->date_fin   = $dateTo;
		// Note: day count (nb_open_day) is computed by core on approval, not set at creation
		$leave->description = $reason;
		$leave->fk_type    = (int)GETPOST('leave_type_id', 'int') ?: 1;
		$leave->entity     = (int)$conf->entity; // Ensure entity is set for multi-company

		$db->begin();
		$res = $leave->create($user);
		if ($res > 0) {
			$db->commit();
			dol_syslog('sgpayroll leave_list.php: leave created id='.$res.' user='.$user->id, LOG_INFO);
			setEventMessages($langs->trans('LeaveApplied'), null, 'mesgs');
		} else {
			$db->rollback();
			dol_syslog('sgpayroll leave_list.php: leave create failed: '.$leave->error, LOG_ERR);
			setEventMessages($leave->error ?: $langs->trans('Error'), null, 'errors');
		}
	}
	header('Location: leave_list.php');
	exit;
}

if ($action === 'approve' && $canApprove) {
	$rid = GETPOSTINT('rowid');
	if ($rid > 0) {
		$leave = new Holiday($db);
		$leave->fetch($rid);
		$db->begin();
		$res = $leave->approve($user);
		if ($res > 0) {
			$db->commit();
			dol_syslog('sgpayroll leave_list.php: leave approved id='.$rid.' by user='.$user->id, LOG_INFO);
			setEventMessages($langs->trans('LeaveApproved'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($leave->error ?: $langs->trans('Error'), null, 'errors');
		}
	}
	header('Location: leave_list.php'); exit;
}

if ($action === 'reject' && $canApprove) {
	$rid = GETPOSTINT('rowid');
	if ($rid > 0) {
		$leave = new Holiday($db);
		$leave->fetch($rid);
		$db->begin();
		$res = $leave->refuse($user);
		if ($res > 0) {
			$db->commit();
			dol_syslog('sgpayroll leave_list.php: leave refused id='.$rid.' by user='.$user->id, LOG_INFO);
		} else {
			$db->rollback();
			setEventMessages($leave->error ?: $langs->trans('Error'), null, 'errors');
		}
	}
	header('Location: leave_list.php'); exit;
}

// Parameters
$fk_user_param = GETPOST('fk_user', 'int');
$fk_user_for_tabs = $fk_user_param ?: $user->id;

// ── FETCH LEAVE LIST ──────────────────────────────────────────────────────────
$sql  = "SELECT h.rowid, h.fk_user, h.statut, h.date_debut, h.date_fin, h.nb_open_day, h.description, h.fk_type,";
$sql .= " u.lastname, u.firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."holiday h";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = h.fk_user";
$sql .= " WHERE h.entity = ".(int)$conf->entity;

if ($fk_user_param > 0) {
    if (!$user->admin && $fk_user_param != $user->id && !$user->hasRight('sgpayroll', 'employee', 'read')) {
        accessforbidden();
    }
    $sql .= " AND h.fk_user = ".(int)$fk_user_param;
} elseif (!$viewAll) {
	$sql .= " AND h.fk_user = ".(int)$user->id;
}
$sql .= " ORDER BY h.date_debut DESC LIMIT 300";
$res = $db->query($sql);
$rows = array();
while ($res && $obj = $db->fetch_object($res)) $rows[] = $obj;

// ── PAGE OUTPUT ───────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('Leave'), '', '', 0, 0, '', '', '', 'bodyforlist mod-sgpayroll page-leave-list');

if ($fk_user_for_tabs > 0) {
	$user_for_title = new User($db);
	$user_for_title->fetch($fk_user_for_tabs);
	print load_fiche_titre($langs->trans('EmployeeProfile').' — '.$user_for_title->getFullName($langs), '', 'title_hrm');

	$head = sgpayroll_employee_prepare_head($fk_user_for_tabs);
	dol_fiche_head($head, 'leave', '', 0, '');
}

print load_fiche_titre($langs->trans('Leave'), '', 'title_hrm');

// ── Apply Leave Form ──────────────────────────────────────────────────────────
print '<div class="fichecenter">'."\n";
print '<form method="POST" action="leave_list.php">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="apply">';
print '<table class="border centpercent tableforfield">'."\n";
print '<tr class="liste_titre"><td colspan="6"><b>'.$langs->trans('ApplyLeave').'</b></td></tr>';
print '<tr>';

// Leave type using Form selectarray
$leaveTypes = array(
	'AL'=>$langs->trans('AnnualLeave'),
	'SL'=>$langs->trans('SickLeave'),
	'HL'=>$langs->trans('HospitalisationLeave'),
	'ML'=>$langs->trans('MaternityLeave'),
	'PL'=>$langs->trans('PaternityLeave'),
	'SPL'=>$langs->trans('SharedParentalLeave'),
	'CL'=>$langs->trans('ChildcareLeave'),
	'NS'=>$langs->trans('NSReservistLeave'),
	'NPL'=>$langs->trans('UnpaidLeave'),
);
// In many Dolibarr versions, types are IDs. Let's provide a better mapping or use native types if available.
$typeIds = array(1=>'Annual', 2=>'Sick', 3=>'Maternity', 8=>'Unpaid');
print '<td>'.$form->selectarray('leave_type_id', $typeIds, 1, 0, 0, 0, '', 0, 0, 0, '', 'flat').'</td>';

// From/To dates using Dolibarr's Form::selectDate
print '<td>'.$langs->trans('DateStart').': '.$form->selectDate(-1, 'date_from', 0, 0, 0, '', 1, 1).'</td>';
print '<td>'.$langs->trans('DateEnd').':   '.$form->selectDate(-1, 'date_to', 0, 0, 0, '', 1, 1).'</td>';
print '<td>'.$langs->trans('Days').': <input type="number" name="num_days" value="1" min="0.5" step="0.5" class="flat" style="width:60px">  </td>';
print '<td><input type="text" name="reason" placeholder="'.$langs->trans('Reason').'" class="flat maxwidth200"></td>';
print '<td><input type="submit" value="'.$langs->trans('Apply').'" class="butAction"></td>';
print '</tr>';
print '</table>';
print '</form>';
print '</div>'."\n";

// MOM leave guidance link
print '<div class="info" style="margin-bottom:8px">'.img_picto('', 'info', 'class="paddingright"');
printf(
	$langs->trans('MomLeaveGuidanceNote', '<a href="https://www.mom.gov.sg/employment-practices/leave-and-holidays" target="_blank" rel="noopener">MOM Leave Guide</a>'),
	''
);
print '<br>'.sgpayroll_portal_links('leave');
print '</div>';

// ── Leave List ────────────────────────────────────────────────────────────────
$statusMap  = array(0=>'Draft', 1=>'Pending', 2=>'Approved', 4=>'Rejected');
$badgeMap   = array(0=>'badge-status0', 1=>'badge-status4', 2=>'badge-status1', 4=>'badge-status8');

print '<div class="div-table-responsive">'."\n";
print '<table class="tagtable nobottomiftotal liste">'."\n";
print '<tr class="liste_titre">';
foreach (array('Employee','LeaveType','DateFrom','DateTo','Days','Reason','Status','') as $h) {
	print '<td>'.($h ? $langs->trans($h) : '&nbsp;').'</td>';
}
print '</tr>'."\n";

foreach ($rows as $r) {
	$st    = (int)$r->statut;
	$badge = $badgeMap[$st] ?? 'badge-status0';
	$label = $langs->transnoentities($statusMap[$st] ?? 'Unknown');
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</td>';
	print '<td>'.dol_escape_htmltag($leaveTypes[$r->fk_type] ?? dol_escape_htmltag($r->fk_type)).'</td>';
	print '<td>'.dol_print_date($db->jdate($r->date_debut), 'day').'</td>';
	print '<td>'.dol_print_date($db->jdate($r->date_fin), 'day').'</td>';
	print '<td class="center">'.((float)$r->nb_open_day).'</td>';
	print '<td class="tdoverflowmax150">'.dol_escape_htmltag($r->description ?? '').'</td>';
	print '<td class="center"><span class="badge '.$badge.'">'.dol_escape_htmltag($label).'</span></td>';
	print '<td class="center">';
	if ($canApprove && $st == 1) {
		print '<form method="POST" action="leave_list.php" style="display:inline">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="rowid" value="'.(int)$r->rowid.'">';
		print '<button name="action" value="approve" class="button buttongen smallpaddingimp">'.img_picto('', 'check', 'class="paddingright pictofixedwidth"').$langs->trans('Approve').'</button> ';
		print '<button name="action" value="reject"  class="button smallpaddingimp buttonRefused">'.img_picto('', 'delete', 'class="paddingright pictofixedwidth"').$langs->trans('Refuse').'</button>';
		print '</form>';
	}
	print '</td></tr>'."\n";
}
if (empty($rows)) {
	print '<tr><td colspan="8" class="opacitymedium center">'.$langs->trans('NoRecordFound').'</td></tr>';
}
print '</table></div>';

if ($fk_user_for_tabs > 0) {
	dol_fiche_end();
	print '</div>';
}

llxFooter();
$db->close();
