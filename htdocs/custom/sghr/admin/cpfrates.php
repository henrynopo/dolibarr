<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/**
 * \file        admin/cpfrates.php
 * \ingroup sghr
 * \brief       Admin CRUD for versioned CPF rate table.
 *              HR admins update rates annually without code changes.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('sghr/lib/sghr.lib.php');

if (!$user->admin) accessforbidden();
$langs->loadLangs(array('sghr@sghr', 'admin'));

$action = GETPOST('action', 'aZ');
$rowid  = GETPOST('rowid',  'int');

// ── ACTIONS ──────────────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
	$fields = array(
		'effective_date'    => GETPOST('effective_date',    'alpha'),
		'citizenship_tier'  => GETPOST('citizenship_tier',  'aZ'),
		'age_from'          => GETPOST('age_from',          'int'),
		'age_to'            => GETPOST('age_to',            'int'),
		'wage_band'         => GETPOST('wage_band',         'aZ'),
		'employer_rate'     => GETPOST('employer_rate',     'float'),
		'employee_rate'     => GETPOST('employee_rate',     'float'),
		'ow_ceiling'        => GETPOST('ow_ceiling',        'float'),
		'aw_annual_ceiling' => GETPOST('aw_annual_ceiling', 'float'),
		'oa_pct'            => GETPOST('oa_pct',            'float'),
		'sa_pct'            => GETPOST('sa_pct',            'float'),
		'ma_pct'            => GETPOST('ma_pct',            'float'),
		'note'              => GETPOST('note',              'alphanohtml'),
	);

	$db->begin();
	if ($rowid && $action === 'edit') {
		$set = array();
		foreach ($fields as $k => $v) {
			$set[] = $k."='".$db->escape($v)."'";
		}
		if ($db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_cpfrates SET ".implode(',', $set)." WHERE rowid=".(int)$rowid)) {
			$db->commit();
			dol_syslog('sgpayroll cpfrates: updated rowid='.$rowid, LOG_INFO);
			setEventMessages($langs->trans('CPFRateUpdated'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		$cols = implode(',', array_keys($fields));
		$vals = implode(',', array_map(function($v) use ($db) { return "'".$db->escape($v)."'"; }, $fields));
		if ($db->query("INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_cpfrates ($cols) VALUES ($vals)")) {
			$db->commit();
			dol_syslog('sgpayroll cpfrates: inserted new rate', LOG_INFO);
			setEventMessages($langs->trans('CPFRateAdded'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header('Location: cpfrates.php'); exit;
}

if ($action === 'delete' && $rowid) {
	$db->begin();
	if ($db->query("DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_cpfrates WHERE rowid=".(int)$rowid)) {
		$db->commit();
		dol_syslog('sgpayroll cpfrates: deleted rowid='.$rowid, LOG_INFO);
		setEventMessages($langs->trans('CPFRateDeleted'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: cpfrates.php'); exit;
}

// ── Load ─────────────────────────────────────────────────────────────────────
$editRow = null;
if ($action === 'show_edit' && $rowid) {
	$res  = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_cpfrates WHERE rowid=".(int)$rowid);
	if ($res) $editRow = $db->fetch_object($res);
}

$res  = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_cpfrates ORDER BY effective_date DESC, citizenship_tier, age_from");
$rates = array();
while ($res && $obj = $db->fetch_object($res)) $rates[] = $obj;

// ── PAGE (same structure as other Setup tabs: title + tab bar) ─────────────────
llxHeader('', $langs->trans('SGPayrollSetup'), '');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('SGPayrollSetup'), $linkback, 'sghr@sghr');

$head = sghrAdminPrepareHead();
dol_fiche_head($head, 'cpfrates', $langs->trans('SGPayrollSetup'), -1, 'sghr@sghr');

// ── Add/Edit form ─────────────────────────────────────────────────────────────
$formTitle = $editRow ? 'Edit CPF Rate Row' : 'Add New CPF Rate Row';
print '<div class="div-table-responsive-no-min" style="max-width:900px">';
print '<form method="POST" action="cpfrates.php">';
print '<input type="hidden" name="action" value="'.($editRow ? 'edit' : 'add').'">';
if ($editRow) print '<input type="hidden" name="rowid" value="'.$editRow->rowid.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="4"><b>'.$formTitle.'</b></td></tr>';

$d  = $editRow;
$fieldDef = function($name, $label, $type='text', $extra='') use ($d) {
	$val = $d ? dol_escape_htmltag($d->$name ?? '') : '';
	print '<td><b>'.$label.'</b></td>';
	if ($type === 'select') {
		// $extra = options array
	} else {
		print '<td><input type="'.($type==='number'?'number':'text').'" name="'.$name.'" value="'.$val.'" class="flat" '.$extra.'></td>';
	}
};

print '<tr>';
print '<td><b>Effective Date</b></td><td><input type="date" name="effective_date" value="'.($d?dol_escape_htmltag($d->effective_date):'').'" class="flat"></td>';
print '<td><b>Citizenship Tier</b></td><td><select name="citizenship_tier" class="flat">';
foreach (array('SC','PR1Y','PR2Y','PR3Y') as $t) print '<option value="'.$t.'"'.($d&&$d->citizenship_tier==$t?' selected':'').'>'.$t.'</option>';
print '</select></td></tr>';

print '<tr>';
print '<td><b>Age From</b></td><td><input type="number" name="age_from" value="'.($d?dol_escape_htmltag($d->age_from):'0').'" min="0" max="99" class="flat width75"></td>';
print '<td><b>Age To</b></td><td><input type="number" name="age_to" value="'.($d?dol_escape_htmltag($d->age_to):'99').'" min="0" max="99" class="flat width75"> <small>(0=no upper limit)</small></td></tr>';

print '<tr>';
print '<td><b>Wage Band</b></td><td><select name="wage_band" class="flat">';
foreach (array('above750','501to750','500andbelow') as $wb) print '<option value="'.$wb.'"'.($d&&$d->wage_band==$wb?' selected':'').'>'.$wb.'</option>';
print '</select></td>';
print '<td><b>OW Ceiling (S$)</b></td><td><input type="number" name="ow_ceiling" value="'.($d?dol_escape_htmltag($d->ow_ceiling):'7400').'" step="100" class="flat width100 right"></td></tr>';
print '<tr><td colspan="4" class="opacitymedium"><small><strong>CPF wage thresholds (monthly OW):</strong> ≤$50 no CPF; $50–$500 use <code>500andbelow</code> (employer only); $500–$750 use <code>500andbelow</code> on first $500 + <code>501to750</code> on excess; &gt;$750 use <code>above750</code>. Payslip calculation picks band automatically.</small></td></tr>';

print '<tr>';
print '<td><b>Employer Rate (e.g. 0.17)</b></td><td><input type="number" name="employer_rate" value="'.($d?dol_escape_htmltag($d->employer_rate):'0.1700').'" step="0.0001" class="flat width100 right"></td>';
print '<td><b>Employee Rate (e.g. 0.20)</b></td><td><input type="number" name="employee_rate" value="'.($d?dol_escape_htmltag($d->employee_rate):'0.2000').'" step="0.0001" class="flat width100 right"></td></tr>';

print '<tr>';
print '<td><b>OA % of Total</b></td><td><input type="number" name="oa_pct" value="'.($d?dol_escape_htmltag($d->oa_pct):'0.6217').'" step="0.0001" class="flat width100 right"></td>';
print '<td><b>SA %</b></td><td><input type="number" name="sa_pct" value="'.($d?dol_escape_htmltag($d->sa_pct):'0.2162').'" step="0.0001" class="flat width100 right"></td></tr>';

print '<tr>';
print '<td><b>MA %</b></td><td><input type="number" name="ma_pct" value="'.($d?dol_escape_htmltag($d->ma_pct):'0.1621').'" step="0.0001" class="flat width100 right"></td>';
print '<td><b>AW Annual Ceiling</b></td><td><input type="number" name="aw_annual_ceiling" value="'.($d?dol_escape_htmltag($d->aw_annual_ceiling):'102000').'" class="flat width100 right"></td></tr>';

print '<tr><td><b>Note</b></td><td colspan="3"><input type="text" name="note" value="'.($d?dol_escape_htmltag($d->note):'').'" class="flat" style="width:100%"></td></tr>';
print '</table>';
print '<div class="tabsAction"><input type="submit" value="'.($editRow ? 'Save Changes' : 'Add Rate Row').'" class="butAction">';
if ($editRow) print ' <a href="cpfrates.php" class="butActionRefused">Cancel</a>';
print '</div></form></div>';

// ── Rate table grid ───────────────────────────────────────────────────────────
print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';
$cols = array('Effective','Tier','Age','WageBand','Employer%','Employee%','OW Ceil','OA%','SA%','MA%','Note','');
print '<tr class="liste_titre">';
foreach ($cols as $c) print '<td>'.$c.'</td>';
print '</tr>';
foreach ($rates as $r) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($r->effective_date).'</td>';
	print '<td><b>'.dol_escape_htmltag($r->citizenship_tier).'</b></td>';
	print '<td>'.dol_escape_htmltag($r->age_from).'-'.($r->age_to?dol_escape_htmltag($r->age_to):'∞').'</td>';
	print '<td>'.dol_escape_htmltag($r->wage_band).'</td>';
	print '<td class="right">'.number_format((float)$r->employer_rate*100,2).'%</td>';
	print '<td class="right">'.number_format((float)$r->employee_rate*100,2).'%</td>';
	print '<td class="right">$'.number_format((float)$r->ow_ceiling,0).'</td>';
	print '<td class="right">'.number_format((float)$r->oa_pct*100,2).'%</td>';
	print '<td class="right">'.number_format((float)$r->sa_pct*100,2).'%</td>';
	print '<td class="right">'.number_format((float)$r->ma_pct*100,2).'%</td>';
	print '<td>'.dol_escape_htmltag($r->note).'</td>';
	print '<td>';
	print '<a href="cpfrates.php?action=show_edit&rowid='.$r->rowid.'">'.img_picto('Edit','edit').'</a> ';
	print '<form method="POST" action="cpfrates.php" style="display:inline" onsubmit="return confirm(\'Delete this rate row?\');">';
	print '<input type="hidden" name="action" value="delete"><input type="hidden" name="rowid" value="'.$r->rowid.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<button type="submit" class="buttongen smallpaddingimp">'.img_picto('Delete','delete').'</button></form>';
	print '</td></tr>';
}
if (empty($rates)) print '<tr><td colspan="12" class="opacitymedium center">No rates found. Click Add to seed.</td></tr>';
print '</table></div>';

dol_fiche_end();
llxFooter(); $db->close();
