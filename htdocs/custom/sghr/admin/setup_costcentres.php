<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        setup_costcentres.php
 * \ingroup sghr
 * \brief       Cost Centres dictionary setup page
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))      { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))    { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Cannot load main.inc.php'); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('sghr/lib/sghr.lib.php');

$langs->loadLangs(array('admin', 'sghr@sghr'));

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ');
$rowid  = GETPOST('rowid', 'int');
$code   = GETPOST('code', 'alpha');
$label  = GETPOST('label', 'alphanohtml');
$active = GETPOST('active', 'int');

// ── ACTIONS ──────────────────────────────────────────────────────────────────
if ($action == 'add' && $code && $label) {
    if ($user->admin) {
        $db->begin();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_cost_centre (entity, code, label, active) VALUES ";
        $sql.= "(".(int)$conf->entity.", '".$db->escape($code)."', '".$db->escape($label)."', ".(isset($_POST['active']) ? 1 : 0).")";
        $resql = $db->query($sql);
        if ($resql) {
            $db->commit();
            setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages($db->lasterror(), null, 'errors');
        }
        header("Location: setup_costcentres.php");
        exit;
    }
}
if ($action == 'update' && $rowid && $code !== '' && $label !== '') {
	if ($user->admin) {
		$db->begin();
		$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_cost_centre SET code = '".$db->escape($code)."', label = '".$db->escape($label)."', active = ".(isset($_POST['active']) ? 1 : 0)." WHERE rowid = ".(int)$rowid." AND entity = ".(int)$conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$db->commit();
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
		header("Location: setup_costcentres.php");
		exit;
	}
}
if ($action == 'delete' && $rowid) {
    if ($user->admin && verifyToken(GETPOST('token', 'aZ09'))) {
        $db->begin();
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_cost_centre WHERE rowid=".(int)$rowid." AND entity=".(int)$conf->entity;
        $resql = $db->query($sql);
        if ($resql) {
            $db->commit();
            setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages($db->lasterror(), null, 'errors');
        }
        header("Location: setup_costcentres.php");
        exit;
    }
}

// Load record for edit mode
$editRecord = null;
if ($action == 'edit' && $rowid && $user->admin) {
	$sql = "SELECT rowid, code, label, active FROM ".MAIN_DB_PREFIX."sgpayroll_cost_centre WHERE rowid = ".(int)$rowid." AND entity IN (0, ".(int)$conf->entity.")";
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$editRecord = $db->fetch_object($resql);
	}
}

// ── VIEW ─────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('SGPayrollSetup'), '');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('SGPayrollSetup'), $linkback, 'sghr@sghr');

$head = sghrAdminPrepareHead();
dol_fiche_head($head, 'costcentres', $langs->trans('SGPayrollSetup'), -1, 'sghr@sghr');

print '<span class="opacitymedium">'.$langs->trans('CostCentresDescription').'</span><br><br>';

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($editRecord) {
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="rowid" value="'.(int)$editRecord->rowid.'">';
} else {
	print '<input type="hidden" name="action" value="add">';
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Code").'</td><td>'.$langs->trans("Label").'</td><td class="center">'.$langs->trans("Active").'</td><td class="center">'.$langs->trans("Actions").'</td>';
print '</tr>';

$codeVal = $editRecord ? dol_escape_htmltag($editRecord->code) : '';
$labelVal = $editRecord ? dol_escape_htmltag($editRecord->label) : '';
$activeChecked = ($editRecord && $editRecord->active) || !$editRecord ? ' checked' : '';

print '<tr class="oddeven">';
print '<td><input class="flat" type="text" name="code" value="'.$codeVal.'" size="10" required></td>';
print '<td><input class="flat" type="text" name="label" value="'.$labelVal.'" size="30" required></td>';
print '<td class="center"><input type="checkbox" name="active" value="1"'.$activeChecked.'></td>';
print '<td class="center">';
if ($editRecord) {
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Update").'"> ';
	print '<a href="'.$_SERVER["PHP_SELF"].'" class="butActionRefused">'.$langs->trans("Cancel").'</a>';
} else {
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Add").'">';
}
print '</td>';
print '</tr>';

$sql = "SELECT rowid, code, label, active FROM ".MAIN_DB_PREFIX."sgpayroll_cost_centre WHERE entity IN (0,".(int)$conf->entity.") ORDER BY code";
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($obj->code).'</td>';
        print '<td>'.dol_escape_htmltag($obj->label).'</td>';
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
