<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        setup_schedule_presets.php
 * \ingroup     sgpayroll
 * \brief       Weekly schedule presets (e.g. Full-Time, Part-Time 3D) for employee assignment
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))      { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))    { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Cannot load main.inc.php'); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

$langs->loadLangs(array('admin', 'sgpayroll@sgpayroll'));

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ');
$rowid  = GETPOST('rowid', 'int');
$code   = GETPOST('code', 'alpha');
$label  = GETPOST('label', 'alphanohtml');
$schedule_value = GETPOST('schedule_value', 'alphanohtml');
$active = GETPOST('active', 'int');

// ── ACTIONS ──────────────────────────────────────────────────────────────────
if ($action == 'add' && $code !== '' && $label !== '' && $schedule_value !== '') {
	if ($user->admin) {
		$schedule_value = sgpayroll_normalize_weekly_schedule($schedule_value);
		$db->begin();
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_schedule_preset (entity, code, label, schedule_value, active) VALUES ";
		$sql .= "(".(int)$conf->entity.", '".$db->escape($code)."', '".$db->escape($label)."', '".$db->escape($schedule_value)."', ".(isset($_POST['active']) ? 1 : 0).")";
		$resql = $db->query($sql);
		if ($resql) {
			$db->commit();
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
		header("Location: setup_schedule_presets.php");
		exit;
	}
}
if ($action == 'update' && $rowid && $code !== '' && $label !== '' && $schedule_value !== '') {
	if ($user->admin) {
		$schedule_value = sgpayroll_normalize_weekly_schedule($schedule_value);
		$db->begin();
		$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_schedule_preset SET code = '".$db->escape($code)."', label = '".$db->escape($label)."', schedule_value = '".$db->escape($schedule_value)."', active = ".(isset($_POST['active']) ? 1 : 0)." WHERE rowid = ".(int)$rowid." AND entity = ".(int)$conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$db->commit();
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
		header("Location: setup_schedule_presets.php");
		exit;
	}
}
if ($action == 'delete' && $rowid) {
	if ($user->admin && verifyToken(GETPOST('token', 'aZ09'))) {
		$db->begin();
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE rowid=".(int)$rowid." AND entity=".(int)$conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$db->commit();
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
		header("Location: setup_schedule_presets.php");
		exit;
	}
}

// Load preset for edit mode
$editPreset = null;
if ($action == 'edit' && $rowid && $user->admin) {
	$sql = "SELECT rowid, code, label, schedule_value, active FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE rowid = ".(int)$rowid." AND entity IN (0, ".(int)$conf->entity.")";
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$editPreset = $db->fetch_object($resql);
	}
}

// ── VIEW ─────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('SGPayrollSetup'), '');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('SGPayrollSetup'), $linkback, 'sgpayroll@sgpayroll');

$head = sgpayrollAdminPrepareHead();
dol_fiche_head($head, 'schedule_presets', $langs->trans('SGPayrollSetup'), -1, 'sgpayroll@sgpayroll');

print '<span class="opacitymedium">'.$langs->trans('SchedulePresetsDescription').'</span><br><br>';

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($editPreset) {
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="rowid" value="'.(int)$editPreset->rowid.'">';
}
else {
	print '<input type="hidden" name="action" value="add">';
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Code").'</td><td>'.$langs->trans("Label").'</td><td>'.$langs->trans("ScheduleValue").'</td><td class="center">'.$langs->trans("Active").'</td><td class="center">'.$langs->trans("Actions").'</td>';
print '</tr>';

$codeVal = $editPreset ? dol_escape_htmltag($editPreset->code) : '';
$labelVal = $editPreset ? dol_escape_htmltag($editPreset->label) : '';
$scheduleVal = $editPreset ? dol_escape_htmltag($editPreset->schedule_value) : '';
$activeChecked = ($editPreset && $editPreset->active) || !$editPreset ? ' checked' : '';

print '<tr class="oddeven">';
print '<td><input class="flat" type="text" name="code" value="'.$codeVal.'" size="12" placeholder="e.g. FULL" required></td>';
print '<td><input class="flat" type="text" name="label" value="'.$labelVal.'" size="28" placeholder="e.g. Full-Time" required></td>';
print '<td><input class="flat" type="text" name="schedule_value" value="'.$scheduleVal.'" size="45" placeholder="Mon:1;Tue:1;Wed:1;Thu:1;Fri:1" required title="'.dol_escape_htmltag($langs->trans('GlobalWeeklyScheduleHelp')).'"></td>';
print '<td class="center"><input type="checkbox" name="active" value="1"'.$activeChecked.'></td>';
print '<td class="center">';
if ($editPreset) {
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Update").'"> ';
	print '<a href="'.$_SERVER["PHP_SELF"].'" class="butActionRefused">'.$langs->trans("Cancel").'</a>';
} else {
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Add").'">';
}
print '</td>';
print '</tr>';

$sql = "SELECT rowid, code, label, schedule_value, active FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE entity IN (0,".(int)$conf->entity.") ORDER BY code";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->code).'</td>';
		print '<td>'.dol_escape_htmltag($obj->label).'</td>';
		print '<td class="small"><code>'.dol_escape_htmltag($obj->schedule_value).'</code></td>';
		print '<td class="center">'.($obj->active ? $langs->trans('Yes') : $langs->trans('No')).'</td>';
		print '<td class="center">';
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=edit&rowid='.$obj->rowid.'" class="reposition" title="'.$langs->trans("Modify").'">'.img_picto($langs->trans('Modify'), 'edit').'</a> ';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="display:inline" onsubmit="return confirm(\''.dol_escape_js($langs->trans('ConfirmDelete')).'\');">';
		print '<input type="hidden" name="rowid" value="'.(int)$obj->rowid.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button type="submit" name="action" value="delete" style="border:none;background:none;cursor:pointer;padding:0;" title="'.$langs->trans('Delete').'">'.img_picto($langs->trans('Delete'), 'delete').'</button></form>';
		print '</td>';
		print '</tr>';
	}
}
print '</table>';
print '</div>';
print '</form>';

dol_fiche_end();
llxFooter();
$db->close();
