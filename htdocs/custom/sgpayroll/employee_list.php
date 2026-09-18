<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        employee_list.php
 * \ingroup     sgpayroll
 * \brief       Employee list with Dolibarr-standard sorting, filtering, pagination.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php"))       { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/class/employee.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

// Load translations
$langs->loadLangs(array('sgpayroll@sgpayroll', 'users', 'hrm'));

// Security check
if (!isModEnabled('sgpayroll')) accessforbidden('Module not enabled');
$result = restrictedArea($user, 'sgpayroll', 0, '', 'employee', '', 'read_all');

// Initialize hooks
$hookmanager->initHooks(array('sgpayrollemployeelist'));

// Get parameters
$action     = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'sgpayrollemployeelist';
$optioncss  = GETPOST('optioncss', 'aZ');

// Load pagination variables
$limit     = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;
if (!$sortorder) $sortorder = 'ASC';
if (!$sortfield) $sortfield = 'u.lastname';

// Search filters
$search_name        = GETPOST('search_name', 'alpha');
$search_citizenship = GETPOST('search_citizenship', 'alpha');
$search_pass_type   = GETPOST('search_pass_type', 'alpha');
$search_emp_type    = GETPOST('search_emp_type', 'alpha');
$search_status      = GETPOST('search_status', 'int');
$search_currency    = GETPOST('search_currency', 'alpha');
$search_cost_centre = GETPOST('search_cost_centre', 'int');
$search_schedule_preset = GETPOST('search_schedule_preset', 'int');

// Purge search criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_name = '';
	$search_citizenship = '';
	$search_pass_type = '';
	$search_emp_type = '';
	$search_status = '';
	$search_currency = '';
	$search_cost_centre = '';
	$search_schedule_preset = '';
}

// ── SQL query ─────────────────────────────────────────────────────────────────
$sql = "SELECT u.rowid as user_id, e.rowid as emp_id,";
$sql .= " e.employment_type, e.basic_salary, e.contract_currency, e.contract_salary,";
$sql .= " e.exchange_rate, e.status as emp_status, e.fk_cost_centre, cc.code as cc_code, cc.label as cc_label, e.weekly_schedule,";
$sql .= " e.citizenship, e.pass_type, e.pass_expiry,";
$sql .= " u.lastname, u.firstname, u.login, u.email, u.statut as user_status, u.photo, u.dateemployment, u.dateemploymentend";
$sqlfields = $sql;

$sql .= " FROM ".MAIN_DB_PREFIX."user u";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = u.rowid AND e.entity = ".(int)$conf->entity;
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_cost_centre cc ON cc.rowid = e.fk_cost_centre AND cc.entity = ".(int)$conf->entity;
$sql .= " WHERE u.statut = 1 AND u.entity IN (0, ".(int)$conf->entity.")";

if ($search_name) {
	$sql .= natural_search(array('u.lastname', 'u.firstname', 'u.login'), $search_name);
}
if ($search_citizenship && $search_citizenship != '-1') {
	$sql .= " AND e.citizenship = '".$db->escape($search_citizenship)."'";
}
if ($search_pass_type && $search_pass_type != '-1') {
	$sql .= " AND e.pass_type = '".$db->escape($search_pass_type)."'";
}
if ($search_emp_type && $search_emp_type != '-1') {
	$sql .= " AND e.employment_type = '".$db->escape($search_emp_type)."'";
}
if ($search_status !== '' && $search_status != '-1') {
	$sql .= " AND e.status = ".(int)$search_status;
}
if ($search_currency && $search_currency != '-1') {
	$sql .= " AND e.contract_currency = '".$db->escape($search_currency)."'";
}
if ($search_cost_centre > 0) {
	$sql .= " AND e.fk_cost_centre = ".(int)$search_cost_centre;
}
if ($search_schedule_preset > 0) {
	$res_preset = $db->query("SELECT schedule_value FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE rowid = ".(int)$search_schedule_preset." AND entity IN (0, ".(int)$conf->entity.")");
	if ($res_preset && $db->num_rows($res_preset) > 0) {
		$row_p = $db->fetch_object($res_preset);
		$preset_schedule_norm = sgpayroll_normalize_weekly_schedule($row_p->schedule_value);
		$sql .= " AND e.weekly_schedule = '".$db->escape($preset_schedule_norm)."'";
	}
}

// Hook for WHERE
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters);
$sql .= $hookmanager->resPrint;

// Count total
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
	}
	if (($page * $limit) > $nbtotalofrecords) { $page = 0; $offset = 0; }
	$db->free($resql);
}

// Sort + limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) $sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) { dol_print_error($db); exit; }
$num = $db->num_rows($resql);

// ── PAGE OUTPUT ──────────────────────────────────────────────────────────────
$form = new Form($db);

$title = $langs->trans('Employees').' — SG Payroll';
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'bodyforlist mod-sgpayroll page-employee-list');

// Params for links
$param = '';
if ($search_name)        $param .= '&search_name='.urlencode($search_name);
if ($search_citizenship) $param .= '&search_citizenship='.urlencode($search_citizenship);
if ($search_pass_type)   $param .= '&search_pass_type='.urlencode($search_pass_type);
if ($search_emp_type)    $param .= '&search_emp_type='.urlencode($search_emp_type);
if ($search_status !== '') $param .= '&search_status='.urlencode($search_status);
if ($search_currency)    $param .= '&search_currency='.urlencode($search_currency);
if ($search_cost_centre) $param .= '&search_cost_centre='.(int)$search_cost_centre;
if ($search_schedule_preset) $param .= '&search_schedule_preset='.(int)$search_schedule_preset;
if ($optioncss != '')    $param .= '&optioncss='.urlencode($optioncss);
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.((int)$limit);

// New button
$newcardbutton = '';
if ($user->hasRight('sgpayroll', 'employee', 'write')) {
	$newcardbutton = dolGetButtonTitle($langs->trans('NewEmployee'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/custom/sgpayroll/employee_card.php?action=create', '', 1);
}

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'title_accountancy.png', 0, $newcardbutton, '', $limit);

// ── Search form ──────────────────────────────────────────────────────────────
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">'."\n";

// ── Filter row ──────────────────────────────────────────────────────────────
print '<tr class="liste_titre_filter">';
// Name
print '<td class="liste_titre"><input class="flat maxwidth200" type="text" name="search_name" value="'.dol_escape_htmltag($search_name).'"></td>';
// Employment period filter placeholder (date filtering intentionally omitted)
print '<td class="liste_titre"></td>';
// Citizenship
print '<td class="liste_titre">';
print $form->selectarray('search_citizenship', array('SC'=>'SC','PR1Y'=>'PR1Y','PR2Y'=>'PR2Y','PR3Y'=>'PR3Y','FIN'=>'FIN'), $search_citizenship, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
// Pass type
print '<td class="liste_titre">';
print $form->selectarray('search_pass_type', array('EP'=>'EP','SP'=>'SP','WP'=>'WP','LTVP'=>'LTVP','None'=>'None'), $search_pass_type, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth75');
print '</td>';
// Pass expiry
print '<td class="liste_titre"></td>';
// Emp type
print '<td class="liste_titre">';
$emp_type_options = array('fulltime'=>$langs->trans('fulltime'),'parttime'=>$langs->trans('parttime'),'contract'=>$langs->trans('contract'),'freelancer'=>$langs->trans('freelancer'),'intern'=>$langs->trans('intern'));
print $form->selectarray('search_emp_type', $emp_type_options, $search_emp_type, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
// Cost Centre
print '<td class="liste_titre">';
$sql_cc = "SELECT rowid, code FROM ".MAIN_DB_PREFIX."sgpayroll_cost_centre WHERE entity IN (0, ".(int)$conf->entity.") AND active = 1 ORDER BY code";
$res_cc = $db->query($sql_cc);
$cc_array = array();
if ($res_cc) {
	while ($obj_cc = $db->fetch_object($res_cc)) {
		$cc_array[$obj_cc->rowid] = $obj_cc->code;
	}
}
print $form->selectarray('search_cost_centre', $cc_array, $search_cost_centre, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
// Schedule preset
print '<td class="liste_titre">';
$sql_sp = "SELECT rowid, code, label FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE entity IN (0, ".(int)$conf->entity.") AND active = 1 ORDER BY code";
$res_sp = $db->query($sql_sp);
$sp_array = array();
if ($res_sp) {
	while ($obj_sp = $db->fetch_object($res_sp)) {
		$sp_array[$obj_sp->rowid] = $obj_sp->label;
	}
}
print $form->selectarray('search_schedule_preset', $sp_array, $search_schedule_preset, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth100');
print '</td>';
// Salary
print '<td class="liste_titre"></td>';
// Currency
print '<td class="liste_titre">';
print $form->selectarray('search_currency', array('SGD'=>'SGD','USD'=>'USD','CNY'=>'CNY','MYR'=>'MYR','EUR'=>'EUR','GBP'=>'GBP','INR'=>'INR','JPY'=>'JPY','AUD'=>'AUD'), $search_currency, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth75');
print '</td>';
// Status
print '<td class="liste_titre center">';
print $form->selectarray('search_status', array('1'=>$langs->trans('Active'),'0'=>$langs->trans('Inactive')), $search_status, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth75');
print '</td>';
// Filter buttons
print '<td class="liste_titre center maxwidthsearch">';
print $form->showFilterButtons();
print '</td>';
print '</tr>'."\n";

// ── Header row ──────────────────────────────────────────────────────────────
print '<tr class="liste_titre">';
print_liste_field_titre('Employee', $_SERVER["PHP_SELF"], 'u.lastname', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Employment', $_SERVER["PHP_SELF"], 'u.dateemployment', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('Citizenship', $_SERVER["PHP_SELF"], 'e.citizenship', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PassType', $_SERVER["PHP_SELF"], 'e.pass_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('PassExpiry', $_SERVER["PHP_SELF"], 'e.pass_expiry', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('EmploymentType', $_SERVER["PHP_SELF"], 'e.employment_type', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('CostCentre', $_SERVER["PHP_SELF"], 'cc.code', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('WeeklySchedule', $_SERVER["PHP_SELF"], 'e.weekly_schedule', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre('Salary', $_SERVER["PHP_SELF"], 'e.basic_salary', '', $param, '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('Currency', $_SERVER["PHP_SELF"], 'e.contract_currency', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('Status', $_SERVER["PHP_SELF"], 'e.status', '', $param, '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', $param, '', $sortfield, $sortorder, 'center maxwidthsearch ');
print '</tr>'."\n";

// Build normalized schedule -> preset label lookup (so list shows preset name instead of "Custom")
$schedule_preset_labels = array();
$res_sp_list = $db->query("SELECT schedule_value, label FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE entity IN (0, ".(int)$conf->entity.") AND active = 1");
if ($res_sp_list) {
	while ($row_sp = $db->fetch_object($res_sp_list)) {
		$norm = sgpayroll_normalize_weekly_schedule($row_sp->schedule_value);
		$schedule_preset_labels[$norm] = $row_sp->label;
	}
}

// ── Data rows ───────────────────────────────────────────────────────────────
$i = 0;
$now = dol_now();
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (!$obj) break;

	$empName = sgpayroll_format_employee_name($obj->firstname, $obj->lastname);
	$editUrl = DOL_URL_ROOT.'/custom/sgpayroll/employee_card.php?fk_user='.$obj->user_id;

	// Pass expiry warning
	$expiryClass = '';
	$expiryLabel = '';
	if (!empty($obj->pass_expiry) && $obj->pass_expiry != '0000-00-00') {
		$daysLeft = (int)(($db->jdate($obj->pass_expiry) - $now) / 86400);
		$expiryLabel = dol_print_date($db->jdate($obj->pass_expiry), 'day');
		if ($daysLeft <= 30 && $daysLeft >= 0) {
			$expiryClass = 'sgpayroll-expiry-red';
		} elseif ($daysLeft <= 60 && $daysLeft >= 0) {
			$expiryClass = 'sgpayroll-expiry-yellow';
		}
	} else {
		$expiryLabel = '—';
	}

	// Salary display
	$currency = $obj->contract_currency ?: 'SGD';
	$salaryDisplay = '—';
	if ($obj->emp_id > 0) {
		if ($currency !== 'SGD' && $obj->contract_salary > 0) {
			$salaryDisplay = price($obj->contract_salary, 0, $langs, 1, 0, 0, $currency);
			$salaryDisplay .= ' <span class="opacitymedium small">(SGD '.price($obj->basic_salary, 0, $langs, 1, 0).')</span>';
		} else {
			$salaryDisplay = price($obj->basic_salary, 0, $langs, 1, 0, 0, 'SGD');
		}
	}

	// Status badge
	$statusToUse = ($obj->emp_id > 0) ? $obj->user_status : $obj->user_status; // Always use user_status as source of truth
	if ($obj->emp_id > 0) {
		$statusLabel = $statusToUse ? '<span class="badge badge-status4">'.$langs->trans('Active').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Inactive').'</span>';
	} else {
		$statusLabel = '<span class="badge badge-status0 opacitymedium" title="No SG Payroll profile created yet">Not Onboarded</span>';
		if (!$statusToUse) $statusLabel = '<span class="badge badge-status8">'.$langs->trans('Inactive').'</span>';
	}

	print '<tr class="oddeven">';
	// Name (display order from General settings; link to employee card)
	print '<td class="tdoverflowmax200"><a href="'.dol_escape_htmltag($editUrl).'">'.dol_escape_htmltag($empName).'</a></td>';
	// Employment period (Start → End/Ongoing)
	$startTs = !empty($obj->dateemployment) ? $db->jdate($obj->dateemployment) : 0;
	$endTs   = !empty($obj->dateemploymentend) ? $db->jdate($obj->dateemploymentend) : 0;
	if ($startTs > 0) {
		$startStr = dol_print_date($startTs, '%d.%m.%Y');
		$endStr   = ($endTs > 0) ? dol_print_date($endTs, '%d.%m.%Y') : $langs->trans('Ongoing');
		$periodStr = $startStr.' &rarr; '.$endStr;
	} else {
		$periodStr = '—';
	}
	print '<td class="center nowrap">'.$periodStr.'</td>';
	// Citizenship
	print '<td><b>'.dol_escape_htmltag($obj->citizenship).'</b></td>';
	// Pass type
	print '<td>'.dol_escape_htmltag($obj->pass_type ?: '—').'</td>';
	// Pass expiry
	print '<td class="center '.dol_escape_htmltag($expiryClass).'">'.$expiryLabel.'</td>';
	// Employment type
	// Employment type: show translated label (Full-Time, Part-Time, 全职, 兼职, etc.)
	$emp_type_label = $langs->trans($obj->employment_type ?: 'fulltime');
	if ($emp_type_label === ($obj->employment_type ?: 'fulltime')) {
		$emp_type_fallback = array('fulltime'=>'Full-Time','parttime'=>'Part-Time','contract'=>'Contract','freelancer'=>'Freelancer','intern'=>'Intern');
		$emp_type_label = $emp_type_fallback[$obj->employment_type] ?? ucfirst((string)$obj->employment_type);
	}
	print '<td>'.dol_escape_htmltag($emp_type_label).'</td>';
	// Cost Centre
	print '<td>'.dol_escape_htmltag($obj->cc_code ?: '—').'</td>';
	// Schedule (preset label or Custom; match by normalized string so preset-applied employees show preset name)
	$emp_schedule_norm = sgpayroll_normalize_weekly_schedule($obj->weekly_schedule ?? '');
	$scheduleLabel = isset($schedule_preset_labels[$emp_schedule_norm]) ? dol_escape_htmltag($schedule_preset_labels[$emp_schedule_norm]) : ($obj->weekly_schedule ? '<span class="opacitymedium">'.dol_escape_htmltag($langs->trans('Custom')).'</span>' : '—');
	print '<td>'.$scheduleLabel.'</td>';
	// Salary
	print '<td class="right nowraponall">'.$salaryDisplay.'</td>';
	// Currency
	print '<td class="center">';
	if ($currency !== 'SGD') {
		print sgpayroll_currency_badge($currency, $obj->exchange_rate);
	} else {
		print '<span class="opacitymedium">SGD</span>';
	}
	print '</td>';
	// Status
	print '<td class="center">'.$statusLabel.'</td>';
	// Edit link
	print '<td class="center">';
	print '<a href="'.$editUrl.'">'.img_picto($langs->trans('Edit'), 'edit').'</a>';
	print '</td>';
	print '</tr>'."\n";
	$i++;
}

if ($num == 0) {
	print '<tr><td colspan="12" class="opacitymedium center">'.$langs->trans('NoRecordFound').'</td></tr>';
}

print '</table>';
print '</div>'; // div-table-responsive
print '</form>';

// Portal links
print '<div class="tabsAction">';
print sgpayroll_portal_links('all');
print '</div>';

llxFooter();
$db->close();
