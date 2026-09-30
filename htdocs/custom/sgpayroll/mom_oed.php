<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        mom_oed.php
 * \ingroup     sgpayroll
 * \brief       MOM Employment Statistics (OED — Occupational Employment Dataset)
 *              Lists all active employees with job/salary data for MOM reporting.
 *              Used for Singapore National Employment Survey and MOM compliance.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load Dolibarr main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('sgpayroll/class/employee.class.php');
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

if (!isModEnabled('sgpayroll')) accessforbidden();
if (!$user->admin && !$user->hasRight('sgpayroll', 'employee', 'read')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll', 'hrm'));

$entity     = (int)($conf->entity ?? 1);
$action     = GETPOST('action', 'aZ09');
$searchYear = GETPOSTINT('search_year') ?: (int)dol_print_date(dol_now(), '%Y');

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($action === 'export_oed') {
	$sql  = "SELECT e.nric_fin, u.lastname, u.firstname, e.citizenship, e.pass_type,";
	$sql .= " e.employment_type, e.job_title, e.basic_salary, e.date_employment,";
	$sql .= " e.race";
	$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
	$sql .= " WHERE e.entity = ".$entity." AND u.statut = 1";
	$sql .= " ORDER BY u.lastname, u.firstname";

	$resql = $db->query($sql);
	$lines  = array();
	$lines[] = implode(',', array('"NRIC/FIN"','"Full Name"','"Citizenship"','"Pass Type"','"Employment Type"','"Job Title"','"Monthly Basic ($)"','"Date Joined"','"Race"'));
	while ($obj = $db->fetch_object($resql)) {
		$lines[] = implode(',', array(
			'"'.dol_string_unaccent($obj->nric_fin ?? '').'"',
			'"'.dol_string_unaccent(sgpayroll_csv_safe(sgpayroll_format_employee_name($obj->firstname, $obj->lastname))).'"',
			'"'.($obj->citizenship ?? '').'"',
			'"'.($obj->pass_type ?? 'N/A').'"',
			'"'.($obj->employment_type ?? '').'"',
			'"'.dol_string_unaccent($obj->job_title ?? '').'"',
			number_format((float)$obj->basic_salary, 2, '.', ''),
			'"'.($obj->date_employment ?? '').'"',
			'"'.($obj->race ?? '').'"',
		));
	}
	$filename = 'MOM_OED_Employment_'.sprintf('%04d', $searchYear).'.csv';
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
	echo implode("\n", $lines);
	exit;
}

// ── Query: Active employees ───────────────────────────────────────────────────
$sql  = "SELECT e.rowid, e.fk_user, e.nric_fin, e.citizenship, e.pass_type, e.pass_expiry,";
$sql .= " e.employment_type, e.job_title, e.basic_salary, e.date_employment, e.race,";
$sql .= " u.lastname, u.firstname, u.statut";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
$sql .= " WHERE e.entity = ".$entity." AND u.statut = 1";
$sql .= " ORDER BY u.lastname, u.firstname";

$resql = $db->query($sql);
$rows  = array();
$stats = array('total' => 0, 'sc' => 0, 'pr' => 0, 'ep' => 0, 'sp' => 0, 'wp' => 0, 'other' => 0);

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		$stats['total']++;
		$cit = strtolower($obj->citizenship ?? '');
		if ($cit === 'singapore citizen' || $cit === 'sc') $stats['sc']++;
		elseif (strpos($cit, 'pr') !== false) $stats['pr']++;
		else {
			$pt = strtoupper($obj->pass_type ?? '');
			if (strpos($pt, 'EP') !== false) $stats['ep']++;
			elseif (strpos($pt, 'SP') !== false) $stats['sp']++;
			elseif (strpos($pt, 'WP') !== false || strpos($pt, 'S PASS') !== false) $stats['wp']++;
			else $stats['other']++;
		}
	}
}

// ── HTML Output ───────────────────────────────────────────────────────────────
$form = new Form($db);
llxHeader('', $langs->trans('MomOed'), '');

print load_fiche_titre(
	'<i class="fas fa-building text-primary"></i> '.$langs->trans('MomOed'),
	'', 'building'
);

// Stats boxes
print '<div class="fichecenter">';
print '<table class="border centpercent"><tr>';
$statItems = array(
	$langs->trans('TotalActive') => $stats['total'],
	'Singapore Citizens (SC)' => $stats['sc'],
	'Singapore PR (SG PR)' => $stats['pr'],
	'Employment Pass (EP)' => $stats['ep'],
	'S Pass (SP)' => $stats['sp'],
	'Work Permit / Others' => $stats['wp'] + $stats['other'],
);
foreach ($statItems as $label => $val) {
	print '<td class="center"><div style="font-size:1.8em;font-weight:bold;color:#1976d2">'.$val.'</div>';
	print '<div style="font-size:0.85em;color:#666">'.$label.'</div></td>';
}
print '</tr></table>';
print '</div>';

// Export button + year filter
print '<br><div>';
print '<form method="GET" action="mom_oed.php" style="display:inline">';
print '<input type="hidden" name="action" value="export_oed">';
print '<input type="number" name="search_year" value="'.$searchYear.'" min="2020" max="2050" class="flat width75"> ';
print '<button type="submit" class="butAction"><i class="fas fa-download"></i> '.$langs->trans('ExportOED').' (CSV)</button>';
print '</form>';
print '</div><br>';

// Employee table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Employee').'</td>';
print '<td>'.$langs->trans('NRIC').'</td>';
print '<td>'.$langs->trans('Citizenship').'</td>';
print '<td>'.$langs->trans('PassType').'</td>';
print '<td>'.$langs->trans('PassExpiry').'</td>';
print '<td>'.$langs->trans('EmploymentType').'</td>';
print '<td>'.$langs->trans('JobTitle').'</td>';
print '<td class="right">'.$langs->trans('BasicSalary').'</td>';
print '<td>'.$langs->trans('DateEmployment').'</td>';
print '</tr>';

$i = 0;
foreach ($rows as $r) {
	$expiry = !empty($r->pass_expiry) ? dol_print_date(dol_stringtotime($r->pass_expiry), 'day') : '—';
	$expiryClass = '';
	if (!empty($r->pass_expiry)) {
		$daysLeft = (dol_stringtotime($r->pass_expiry) - dol_now()) / 86400;
		if ($daysLeft < 30)  $expiryClass = 'style="color:red;font-weight:bold"';
		elseif ($daysLeft < 90) $expiryClass = 'style="color:orange"';
	}
	print '<tr class="'.($i++ % 2 ? 'pair' : 'impair').'">';
	print '<td><a href="employee_card.php?fk_user='.(int)$r->fk_user.'">'.dol_escape_htmltag(sgpayroll_format_employee_name($r->firstname, $r->lastname)).'</a></td>';
	print '<td>'.dol_escape_htmltag($r->nric_fin ?? '—').'</td>';
	print '<td>'.dol_escape_htmltag($r->citizenship ?? '—').'</td>';
	print '<td>'.dol_escape_htmltag($r->pass_type ?? ($r->citizenship === 'Singapore Citizen' ? 'SC' : '—')).'</td>';
	print '<td '.$expiryClass.'>'.$expiry.'</td>';
	print '<td>'.dol_escape_htmltag(ucfirst($r->employment_type ?? '')).'</td>';
	print '<td>'.dol_escape_htmltag($r->job_title ?? '—').'</td>';
	print '<td class="right">'.price($r->basic_salary).'</td>';
	print '<td>'.($r->date_employment ? dol_print_date(dol_stringtotime($r->date_employment), 'day') : '—').'</td>';
	print '</tr>';
}
print '</table>';

print '<br><div class="info"><i class="fas fa-info-circle"></i> '.
	$langs->trans('MomOedNote').'</div>';

llxFooter();
$db->close();
