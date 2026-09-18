<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/**
 * \file        admin/setup_statutory_rates.php
 * \ingroup     sgpayroll
 * \brief       Admin CRUD for statutory rates (SDL, SHG).
 *              HR admins update rates annually without code changes.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

if (!$user->admin) accessforbidden();
$langs->loadLangs(array('sgpayroll@sgpayroll', 'admin'));

$action = GETPOST('action', 'aZ');
$rowid  = GETPOST('rowid',  'int');
$tab    = GETPOST('tab', 'aZ') ?: 'sdl';

// ── ACTIONS ──────────────────────────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
	$fields = array(
		'rate_type'      => GETPOST('rate_type', 'aZ'),
		'effective_date' => GETPOST('effective_date', 'alpha'),
		'rate_value'     => GETPOST('rate_value', 'float'),
		'ceiling'       => GETPOST('ceiling', 'float'),
		'min_amount'     => GETPOST('min_amount', 'float'),
		'max_amount'     => GETPOST('max_amount', 'float'),
		'wage_band'      => GETPOST('wage_band', 'alphanohtml'),
		'note'          => GETPOST('note', 'alphanohtml'),
	);

	$db->begin();
	if ($rowid && $action === 'edit') {
		$set = array();
		foreach ($fields as $k => $v) {
			$set[] = $k."='".$db->escape($v)."'";
		}
		if ($db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_statutory_rates SET ".implode(',', $set)." WHERE rowid=".(int)$rowid)) {
			$db->commit();
			dol_syslog('sgpayroll statutory_rates: updated rowid='.$rowid, LOG_INFO);
			setEventMessages($langs->trans('SDLRateUpdated'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		$cols = implode(',', array_keys($fields));
		$vals = implode(',', array_map(function($v) use ($db) { return "'".$db->escape($v)."'"; }, $fields));
		if ($db->query("INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_statutory_rates ($cols) VALUES ($vals)")) {
			$db->commit();
			dol_syslog('sgpayroll statutory_rates: inserted new rate', LOG_INFO);
			setEventMessages($langs->trans('SDLRateAdded'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	}
	header('Location: setup_statutory_rates.php?tab='.$tab); exit;
}

if ($action === 'delete' && $rowid) {
	$db->begin();
	if ($db->query("DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_statutory_rates WHERE rowid=".(int)$rowid)) {
		$db->commit();
		dol_syslog('sgpayroll statutory_rates: deleted rowid='.$rowid, LOG_INFO);
		setEventMessages($langs->trans('SDLRateDeleted'), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($db->lasterror(), null, 'errors');
	}
	header('Location: setup_statutory_rates.php?tab='.$tab); exit;
}

// ── Load ─────────────────────────────────────────────────────────────────────
$editRow = null;
if ($action === 'show_edit' && $rowid) {
	$res  = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_statutory_rates WHERE rowid=".(int)$rowid);
	if ($res) $editRow = $db->fetch_object($res);
}

// ── PAGE ──────────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('StatutoryRates'), '');
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('StatutoryRates'), $linkback, 'sgpayroll@sgpayroll');

$head = sgpayrollAdminPrepareHead();
dol_fiche_head($head, 'statutory_rates', $langs->trans('StatutoryRates'), -1, 'sgpayroll@sgpayroll');

// Tabs for different rate types
$tabs = array(
	'sdl' => $langs->trans('SDL'),
	'cdac' => $langs->trans('SHG_CDAC'),
	'ecf' => $langs->trans('SHG_ECF'),
	'mbmf' => $langs->trans('SHG_MBMF'),
	'sinda' => $langs->trans('SHG_SINDA'),
);

print '<div style="margin-bottom: 15px;">';
print '<ul class="nav nav-tabs" role="tablist">';
foreach ($tabs as $key => $label) {
	$active = ($tab === $key) ? ' class="active"' : '';
	print '<li'.$active.'><a href="?tab='.$key.'" style="padding: 8px 16px;">'.$label.'</a></li>';
}
print '</ul>';
print '</div>';

// ── SDL Tab ──────────────────────────────────────────────────────────────────
if ($tab === 'sdl') {
	$rateTypeFilter = "rate_type IN ('SDL_RATE', 'SDL_CEILING', 'SDL_MIN', 'SDL_MAX')";

	// Show SDL description
	print '<div class="info-box" style="background:#f5f5f5;padding:10px 15px;border-radius:4px;margin-bottom:15px;">';
	print '<strong>Skills Development Levy (SDL)</strong><br>';
	print '<small>Formula: <code>min(wages, ceiling) × rate%</code>, subject to min/max limits.<br>';
	print 'Current formula: 0.25% of wages (first $4,500), min $2.00 when wages &lt; $800, max $11.25/month.</small>';
	print '</div>';

	// Add/Edit form
	$formTitle = $editRow ? $langs->trans('EditStatutoryRate') : $langs->trans('AddStatutoryRate');
	$isEditing = ($editRow && in_array($editRow->rate_type, array('SDL_RATE', 'SDL_CEILING', 'SDL_MIN', 'SDL_MAX')));

	if ($isEditing || !$rowid) {
		print '<div class="div-table-responsive-no-min" style="max-width:700px">';
		print '<form method="POST" action="setup_statutory_rates.php">';
		print '<input type="hidden" name="action" value="'.($editRow ? 'edit' : 'add').'">';
		if ($editRow) print '<input type="hidden" name="rowid" value="'.$editRow->rowid.'">';
		print '<input type="hidden" name="tab" value="sdl">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<table class="border tableforfield centpercent">';
		print '<tr class="liste_titre"><td colspan="4"><b>'.$formTitle.'</b></td></tr>';

		$d = $editRow;
		$defaultType = $d ? $d->rate_type : 'SDL_RATE';

		print '<tr>';
		print '<td><b>Rate Type</b></td><td>';
		print '<select name="rate_type" class="flat">';
		foreach (array('SDL_RATE'=>$langs->trans('SDL_RATE'), 'SDL_CEILING'=>$langs->trans('SDL_CEILING'), 'SDL_MIN'=>$langs->trans('SDL_MIN'), 'SDL_MAX'=>$langs->trans('SDL_MAX')) as $k => $v) {
			print '<option value="'.$k.'"'.($defaultType===$k?' selected':'').'>'.$v.'</option>';
		}
		print '</select>';
		print '</td>';
		print '<td><b>Effective Date</b></td><td><input type="date" name="effective_date" value="'.($d?dol_escape_htmltag($d->effective_date):date('Y-m-d')).'" class="flat"></td></tr>';

		print '<tr>';
		print '<td><b>Rate Value (%)</b></td><td><input type="number" name="rate_value" value="'.($d?dol_escape_htmltag($d->rate_value):'0.25').'" step="0.01" class="flat width100 right"></td>';
		print '<td><b>Ceiling (S$)</b></td><td><input type="number" name="ceiling" value="'.($d?dol_escape_htmltag($d->ceiling):'4500').'" step="100" class="flat width100 right"></td></tr>';

		print '<tr>';
		print '<td><b>Min Amount (S$)</b></td><td><input type="number" name="min_amount" value="'.($d?dol_escape_htmltag($d->min_amount):'2.00').'" step="0.01" class="flat width100 right"></td>';
		print '<td><b>Max Amount (S$)</b></td><td><input type="number" name="max_amount" value="'.($d?dol_escape_htmltag($d->max_amount):'11.25').'" step="0.01" class="flat width100 right"></td></tr>';

		print '<tr><td><b>Note</b></td><td colspan="3"><input type="text" name="note" value="'.($d?dol_escape_htmltag($d->note):'').'" class="flat" style="width:100%"></td></tr>';
		print '</table>';
		print '<div class="tabsAction"><input type="submit" value="'.$langs->trans('Save').'" class="butAction">';
		if ($editRow) print ' <a href="setup_statutory_rates.php?tab=sdl" class="butActionRefused">Cancel</a>';
		print '</div></form></div>';
	}

	// Rate table
	$res = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_statutory_rates WHERE $rateTypeFilter ORDER BY effective_date DESC, rate_type");
	$rates = array();
	while ($res && $obj = $db->fetch_object($res)) $rates[] = $obj;

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste">';
	$cols = array('Effective','RateType','RateValue','Ceiling','Min','Max','Note','');
	print '<tr class="liste_titre">';
	foreach ($cols as $c) print '<td>'.$langs->trans($c).'</td>';
	print '</tr>';
	foreach ($rates as $r) {
		$typeLabel = $r->rate_type;
		if ($r->rate_type === 'SDL_RATE') $typeLabel = $langs->trans('SDL_RATE');
		if ($r->rate_type === 'SDL_CEILING') $typeLabel = $langs->trans('SDL_CEILING');
		if ($r->rate_type === 'SDL_MIN') $typeLabel = $langs->trans('SDL_MIN');
		if ($r->rate_type === 'SDL_MAX') $typeLabel = $langs->trans('SDL_MAX');

		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($r->effective_date).'</td>';
		print '<td><b>'.$typeLabel.'</b></td>';
		print '<td class="right">'.number_format((float)$r->rate_value,4).'%</td>';
		print '<td class="right">$'.number_format((float)$r->ceiling,2).'</td>';
		print '<td class="right">$'.number_format((float)$r->min_amount,2).'</td>';
		print '<td class="right">$'.number_format((float)$r->max_amount,2).'</td>';
		print '<td>'.dol_escape_htmltag($r->note).'</td>';
		print '<td>';
		print '<a href="setup_statutory_rates.php?action=show_edit&rowid='.$r->rowid.'&tab=sdl">'.img_picto('Edit','edit').'</a> ';
		print '<form method="POST" action="setup_statutory_rates.php" style="display:inline" onsubmit="return confirm(\'Delete this rate row?\');">';
		print '<input type="hidden" name="action" value="delete"><input type="hidden" name="rowid" value="'.$r->rowid.'">';
		print '<input type="hidden" name="tab" value="sdl">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<button type="submit" class="buttongen smallpaddingimp">'.img_picto('Delete','delete').'</button></form>';
		print '</td></tr>';
	}
	if (empty($rates)) print '<tr><td colspan="8" class="opacitymedium center">No rates found. Click Add to seed.</td></tr>';
	print '</table></div>';
}

// ── SHG Tabs (CDAC, ECF, MBMF, SINDA) ────────────────────────────────────────
$shgTypes = array(
	'cdac'   => array('rate_type' => 'SHG_CDAC', 'label' => 'SHG_CDAC', 'title' => 'CDAC (Chinese Development Association)'),
	'ecf'    => array('rate_type' => 'SHG_ECF',  'label' => 'SHG_ECF',  'title' => 'ECF (Eurasian Community Fund)'),
	'mbmf'   => array('rate_type' => 'SHG_MBMF', 'label' => 'SHG_MBMF', 'title' => 'MBMF (Mendaki Muslim Welfare Fund)'),
	'sinda'  => array('rate_type' => 'SHG_SINDA','label' => 'SHG_SINDA','title' => 'SINDA (Singapore Indian Development Association)'),
);

if (isset($shgTypes[$tab])) {
	$shgInfo = $shgTypes[$tab];

	print '<div class="info-box" style="background:#f5f5f5;padding:10px 15px;border-radius:4px;margin-bottom:15px;">';
	print '<strong>'.($langs->trans($shgInfo['label'])).' - '.$shgInfo['title'].'</strong><br>';
	print '<small>SHG contributions are fixed monthly amounts based on wage brackets. Employee can opt out per fund.</small>';
	print '</div>';

	// Add/Edit form
	$formTitle = $editRow ? $langs->trans('EditStatutoryRate') : $langs->trans('AddStatutoryRate');
	$isEditing = ($editRow && $editRow->rate_type === $shgInfo['rate_type']);

	if ($isEditing || (!$rowid && $tab !== 'sdl')) {
		print '<div class="div-table-responsive-no-min" style="max-width:700px">';
		print '<form method="POST" action="setup_statutory_rates.php">';
		print '<input type="hidden" name="action" value="'.($editRow ? 'edit' : 'add').'">';
		if ($editRow) print '<input type="hidden" name="rowid" value="'.$editRow->rowid.'">';
		print '<input type="hidden" name="tab" value="'.$tab.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<table class="border tableforfield centpercent">';
		print '<tr class="liste_titre"><td colspan="4"><b>'.$formTitle.'</b></td></tr>';

		$d = $editRow;
		$defaultBand = $d ? $d->wage_band : '';

		print '<tr>';
		print '<td><b>Wage Band</b></td><td>';
		print '<select name="wage_band" class="flat">';
		// Common wage bands
		$wageBands = array('0-1000','1001-1500','1501-2000','2001-2500','2501-3000','3001-3500','3501-4000','4001-4500','4501-5000','5001-6000','6001-7000','6001-8000','7001-10000','7501-10000','8001-10000','10001-15000','10001+','15001+','7501+');
		foreach ($wageBands as $wb) {
			print '<option value="'.$wb.'"'.($defaultBand===$wb?' selected':'').'>$'.$wb.'</option>';
		}
		print '</select>';
		print '</td>';
		print '<td><b>Effective Date</b></td><td><input type="date" name="effective_date" value="'.($d?dol_escape_htmltag($d->effective_date):date('Y-m-d')).'" class="flat"></td></tr>';

		print '<tr>';
		print '<td><b>Amount per month (S$)</b></td><td><input type="number" name="rate_value" value="'.($d?dol_escape_htmltag($d->rate_value):'0').'" step="0.01" class="flat width100 right"></td>';
		print '<td><b>Rate Type</b></td><td><input type="text" value="'.$langs->trans($shgInfo['label']).'" class="flat" disabled></td></tr>';
		print '<input type="hidden" name="rate_type" value="'.$shgInfo['rate_type'].'">';

		print '<tr><td><b>Note</b></td><td colspan="3"><input type="text" name="note" value="'.($d?dol_escape_htmltag($d->note):'').'" class="flat" style="width:100%"></td></tr>';
		print '</table>';
		print '<div class="tabsAction"><input type="submit" value="'.$langs->trans('Save').'" class="butAction">';
		if ($editRow) print ' <a href="setup_statutory_rates.php?tab='.$tab.'" class="butActionRefused">Cancel</a>';
		print '</div></form></div>';
	}

	// Rate table
	$res = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_statutory_rates WHERE rate_type='".$db->escape($shgInfo['rate_type'])."' ORDER BY effective_date DESC, wage_band");
	$rates = array();
	while ($res && $obj = $db->fetch_object($res)) $rates[] = $obj;

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste">';
	$cols = array('Effective','WageBand','AmountPerMonth','Note','');
	print '<tr class="liste_titre">';
	foreach ($cols as $c) print '<td>'.$langs->trans($c).'</td>';
	print '</tr>';
	foreach ($rates as $r) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($r->effective_date).'</td>';
		print '<td><b>$'.dol_escape_htmltag($r->wage_band).'</b></td>';
		print '<td class="right">$'.number_format((float)$r->rate_value,2).'</td>';
		print '<td>'.dol_escape_htmltag($r->note).'</td>';
		print '<td>';
		print '<a href="setup_statutory_rates.php?action=show_edit&rowid='.$r->rowid.'&tab='.$tab.'">'.img_picto('Edit','edit').'</a> ';
		print '<form method="POST" action="setup_statutory_rates.php" style="display:inline" onsubmit="return confirm(\'Delete this rate row?\');">';
		print '<input type="hidden" name="action" value="delete"><input type="hidden" name="rowid" value="'.$r->rowid.'">';
		print '<input type="hidden" name="tab" value="'.$tab.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<button type="submit" class="buttongen smallpaddingimp">'.img_picto('Delete','delete').'</button></form>';
		print '</td></tr>';
	}
	if (empty($rates)) print '<tr><td colspan="5" class="opacitymedium center">No rates found. Click Add to seed.</td></tr>';
	print '</table></div>';
}

dol_fiche_end();
llxFooter(); $db->close();
