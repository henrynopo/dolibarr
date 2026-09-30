<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        employee_jobpos.php
 * \ingroup     sgpayroll
 * \brief       Manage employee Job Position history (HR only)
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Cannot load main.inc.php'); }

dol_include_once('sgpayroll/class/employee.class.php');
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

if (!isModEnabled("sgpayroll")) accessforbidden();
$langs->loadLangs(array('sgpayroll@sgpayroll', 'users'));

$fkUser = GETPOST('fk_user', 'int');
$action = GETPOST('action', 'alpha');
$rowid  = GETPOST('rowid', 'int');
$editRowFromPost = null;
$addFormPrefill = null;

// Rights: HR only
$canEditFull = $user->admin || $user->hasRight('sgpayroll', 'employee', 'write');
if (!$canEditFull) accessforbidden();

// Load user
$empUser = new User($db);
$empUser->fetch($fkUser);
if (empty($empUser->id)) accessforbidden();

$form = new Form($db);
$emp = new SGPayrollEmployee($db);
$emp->fetchByUser($fkUser);

// Detect if history table exists (for friendly message)
$sqlCheck = "SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'sgpayroll_employee_jobpos')."'";
$resCheck = $db->query($sqlCheck);
$jobposTableExists = ($resCheck && $db->num_rows($resCheck) > 0);

// Handle actions
if (in_array($action, array('add_jobpos', 'update_jobpos', 'delete_jobpos'), true)) {
	$tok = GETPOST('token', 'aZ09');
	if (empty($tok) || !verifyToken($tok)) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
		header('Location: employee_jobpos.php?fk_user='.$fkUser);
		exit;
	}
}

if ($action === 'add_jobpos') {
	$title = trim(GETPOST('job_position', 'alphanohtml'));
	$contractCcy = trim(GETPOST('contract_currency', 'alphanohtml')) ?: 'SGD';
	$contractSal = price2num(GETPOST('contract_salary', 'alpha'), 'MU');
	$salarySgd = (strtoupper($contractCcy) === 'SGD') ? (float)$contractSal : 0;
	$startTs = dol_mktime(0, 0, 0, GETPOSTINT('datestartmonth'), GETPOSTINT('datestartday'), GETPOSTINT('datestartyear')) ?: null;
	$endTs   = dol_mktime(0, 0, 0, GETPOSTINT('dateendmonth'),   GETPOSTINT('dateendday'),   GETPOSTINT('dateendyear')) ?: null;

	$resAdd = $emp->addJobPositionHistory($fkUser, $title, $startTs, $endTs, $salarySgd, $user, $contractCcy, (float)$contractSal);
	if ($resAdd > 0) {
		$emp->syncCurrentJobPositionFromHistory($fkUser, $user);
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: employee_jobpos.php?fk_user='.$fkUser);
		exit;
	}
	setEventMessages($emp->error ?: $langs->trans('Error'), null, 'errors');
	$addFormPrefill = array('job_position' => $title, 'contract_currency' => $contractCcy, 'contract_salary' => $contractSal, 'date_start' => $startTs, 'date_end' => $endTs);
}

if ($action === 'update_jobpos') {
	$title = trim(GETPOST('job_position', 'alphanohtml'));
	$contractCcy = trim(GETPOST('contract_currency', 'alphanohtml')) ?: 'SGD';
	$contractSal = price2num(GETPOST('contract_salary', 'alpha'), 'MU');
	$existing = $emp->fetchJobPositionHistoryRow($fkUser, $rowid);
	$salarySgd = (strtoupper($contractCcy) === 'SGD') ? (float)$contractSal : (isset($existing['salary_sgd']) ? (float)$existing['salary_sgd'] : 0);
	$startTs = dol_mktime(0, 0, 0, GETPOSTINT('datestartmonth'), GETPOSTINT('datestartday'), GETPOSTINT('datestartyear')) ?: null;
	$endTs   = dol_mktime(0, 0, 0, GETPOSTINT('dateendmonth'),   GETPOSTINT('dateendday'),   GETPOSTINT('dateendyear')) ?: null;

	$resUp = $emp->updateJobPositionHistory($fkUser, $rowid, $title, $startTs, $endTs, $salarySgd, $user, $contractCcy, (float)$contractSal);
	if ($resUp > 0) {
		$emp->syncCurrentJobPositionFromHistory($fkUser, $user);
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: employee_jobpos.php?fk_user='.$fkUser);
		exit;
	}
	setEventMessages($emp->error ?: $langs->trans('Error'), null, 'errors');
	$editRowFromPost = array('rowid' => $rowid, 'job_position' => $title, 'contract_currency' => $contractCcy, 'contract_salary' => $contractSal, 'date_start' => $startTs, 'date_end' => $endTs);
}

if ($action === 'delete_jobpos') {
	$resDel = $emp->deleteJobPositionHistory($fkUser, $rowid);
	if ($resDel > 0) {
		$emp->syncCurrentJobPositionFromHistory($fkUser, $user);
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($emp->error ?: $langs->trans('Error'), null, 'errors');
	}
	header('Location: employee_jobpos.php?fk_user='.$fkUser);
	exit;
}

// Selected row for edit (or re-show form with POST data after save failure)
$editRow = null;
if (!empty($editRowFromPost) && !empty($editRowFromPost['rowid'])) {
	$editRow = $editRowFromPost;
} elseif ($action === 'edit' && $rowid > 0) {
	$editRow = $emp->fetchJobPositionHistoryRow($fkUser, $rowid);
}

// Page
$title = $langs->trans('PositionHistory').' — '.$empUser->getFullName($langs);
llxHeader('', $title, '');

$linkback = '<a href="employee_card.php?fk_user='.$fkUser.'">'.$langs->trans('Back').'</a>';
print load_fiche_titre($title, $linkback, 'user');

if (!$emp->fetchLatestJobPosition($fkUser) && empty($empUser->job) && !$emp->fetchJobPositionHistory($fkUser, 1) && !$emp->fetchJobPositionHistory($fkUser, 1)) {
	// No-op; keep page quiet
}

// Warning when table missing or contract columns missing (upgrade 12a)
if (!$jobposTableExists) {
	print '<div class="warning">'.dol_escape_htmltag('Missing table: '.MAIN_DB_PREFIX.'sgpayroll_employee_jobpos (run SG Payroll → Setup → Install/Upgrade Tables)').'</div><br>';
} elseif ($jobposTableExists && !$emp->hasJobposContractColumns()) {
	print '<div class="warning">'.$langs->trans('RunSetupUpgradeForContractFields').'</div><br>';
}

// One token for the whole page (form + delete links) so Save and Delete both pass verifyToken
$pageToken = newToken();
// Form (Add/Update)
print '<div class="fichecenter">';
print '<form method="POST" action="employee_jobpos.php">';
print '<input type="hidden" name="fk_user" value="'.$fkUser.'">';
print '<input type="hidden" name="token" value="'.dol_escape_htmltag($pageToken).'">';
if (!empty($editRow)) {
	print '<input type="hidden" name="action" value="update_jobpos">';
	print '<input type="hidden" name="rowid" value="'.(int)$editRow['rowid'].'">';
} else {
	print '<input type="hidden" name="action" value="add_jobpos">';
}

$jobPosVal = !empty($editRow) ? $editRow['job_position'] : (!empty($addFormPrefill['job_position']) ? $addFormPrefill['job_position'] : ($empUser->job ?? ''));
$contractCcyVal = 'SGD';
$contractSalVal = 0;
if (!empty($editRow)) {
	$contractCcyVal = isset($editRow['contract_currency']) && $editRow['contract_currency'] !== '' ? $editRow['contract_currency'] : 'SGD';
	$contractSalVal = isset($editRow['contract_salary']) ? (float)$editRow['contract_salary'] : (isset($editRow['salary_sgd']) ? (float)$editRow['salary_sgd'] : 0);
} elseif (!empty($addFormPrefill)) {
	$contractCcyVal = isset($addFormPrefill['contract_currency']) && $addFormPrefill['contract_currency'] !== '' ? $addFormPrefill['contract_currency'] : 'SGD';
	$contractSalVal = isset($addFormPrefill['contract_salary']) ? (float)$addFormPrefill['contract_salary'] : 0;
} else {
	$contractCcyVal = $emp->contract_currency ?? 'SGD';
	$contractSalVal = $emp->contract_salary ?? 0;
}
$startTs = dol_now();
$endTs = null;
if (!empty($editRow)) {
	if (!empty($editRow['date_start'])) {
		$startTs = is_numeric($editRow['date_start']) ? (int)$editRow['date_start'] : $db->jdate($editRow['date_start']);
	}
	if (array_key_exists('date_end', $editRow) && $editRow['date_end'] !== '' && $editRow['date_end'] !== null) {
		$endTs = is_numeric($editRow['date_end']) ? (int)$editRow['date_end'] : $db->jdate($editRow['date_end']);
	}
} elseif (!empty($addFormPrefill)) {
	if (!empty($addFormPrefill['date_start'])) $startTs = $addFormPrefill['date_start'];
	if (isset($addFormPrefill['date_end']) && $addFormPrefill['date_end'] !== '') $endTs = $addFormPrefill['date_end'];
}

// Currency list: simple code => label so option value is the code (ensures POST contract_currency is correct)
$currencies = array('SGD'=>'SGD','USD'=>'USD','EUR'=>'EUR','GBP'=>'GBP','MYR'=>'MYR','CNY'=>'CNY','HKD'=>'HKD','JPY'=>'JPY','AUD'=>'AUD');
if (!empty($conf->multicurrency->enabled) && file_exists(DOL_DOCUMENT_ROOT.'/core/class/html.formmulticurrency.class.php')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmulticurrency.class.php';
	$formmc = new FormMultiCurrency($db);
	$list = $formmc->get_list_currencies();
	if (is_array($list)) {
		$currencies = array();
		foreach ($list as $code => $item) {
			$currencies[$code] = is_array($item) ? ($item['label'] ?? $code) : $item;
		}
	}
}
$contractCcyHtml = '<select name="contract_currency" id="contract_currency" class="flat">';
foreach ($currencies as $code => $label) {
	$sel = (strtoupper((string)$contractCcyVal) === strtoupper((string)$code)) ? ' selected="selected"' : '';
	$contractCcyHtml .= '<option value="'.dol_escape_htmltag($code).'"'.$sel.'>'.dol_escape_htmltag($label).'</option>';
}
$contractCcyHtml .= '</select>';

print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.($editRow ? $langs->trans('Edit') : $langs->trans('Add')).'</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('JobPosition').'</td><td><input type="text" name="job_position" class="flat minwidth300" value="'.dol_escape_htmltag($jobPosVal).'"></td></tr>';
print '<tr><td>'.$langs->trans('Period').'</td><td>';
ob_start(); $form->select_date($startTs, 'datestart', 0, 0, 1, 'datestart'); $startHtml = ob_get_clean();
ob_start(); $form->select_date($endTs, 'dateend', 0, 0, 1, 'dateend'); $endHtml = ob_get_clean();
print $langs->trans('DateStart').' '.$startHtml.' &nbsp; '.$langs->trans('DateEnd').' '.$endHtml;
print '</td></tr>';
print '<tr><td>'.$langs->trans('ContractCurrency').'</td><td>'.$contractCcyHtml.'</td></tr>';
print '<tr><td>'.$langs->trans('ContractSalary').'</td><td><input type="text" name="contract_salary" id="contract_salary" class="flat width150 right" value="'.dol_escape_htmltag(price2num($contractSalVal)).'"></td></tr>';
print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
if (!empty($editRow)) {
	print '<a class="butActionRefused" href="employee_jobpos.php?fk_user='.$fkUser.'">'.$langs->trans('Cancel').'</a>';
}
print '</div>';
print '</form>';

// History table — reuse $pageToken so delete links use same token as form
$rows = $emp->fetchJobPositionHistory($fkUser, 200);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('DateStart').'</td><td>'.$langs->trans('DateEnd').'</td><td>'.$langs->trans('JobPosition').'</td><td class="right">'.$langs->trans('ContractSalary').'</td><td class="right">'.$langs->trans('Action').'</td></tr>';
if (!empty($rows)) {
	foreach ($rows as $r) {
		$ds = !empty($r['date_start']) ? dol_print_date($db->jdate($r['date_start']), 'day') : '';
		$de = !empty($r['date_end']) ? dol_print_date($db->jdate($r['date_end']), 'day') : $langs->trans('Ongoing');
		$ccy = isset($r['contract_currency']) && $r['contract_currency'] !== '' ? $r['contract_currency'] : 'SGD';
		$csal = isset($r['contract_salary']) ? (float)$r['contract_salary'] : (float)$r['salary_sgd'];
		print '<tr class="oddeven">';
		print '<td>'.$ds.'</td>';
		print '<td>'.$de.'</td>';
		print '<td>'.dol_escape_htmltag($r['job_position']).'</td>';
		print '<td class="right">'.price($csal).' '.dol_escape_htmltag($ccy).'</td>';
		print '<td class="right">';
		print '<a class="reposition" href="employee_jobpos.php?fk_user='.$fkUser.'&action=edit&rowid='.(int)$r['rowid'].'">'.img_picto($langs->trans('Edit'), 'edit').'</a> ';
		print '<form method="POST" action="employee_jobpos.php" style="display:inline" onsubmit="return confirm(\''.dol_escape_js($langs->trans('ConfirmDelete')).'\');">';
		print '<input type="hidden" name="fk_user" value="'.$fkUser.'"><input type="hidden" name="rowid" value="'.(int)$r['rowid'].'">';
		print '<input type="hidden" name="token" value="'.dol_escape_htmltag($pageToken).'">';
		print '<button type="submit" name="action" value="delete_jobpos" style="border:none;background:none;cursor:pointer;padding:0;" title="'.$langs->trans('Delete').'">'.img_picto($langs->trans('Delete'), 'delete').'</button></form>';
		print '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
print '</table>';

print '</div>';

llxFooter();
$db->close();

