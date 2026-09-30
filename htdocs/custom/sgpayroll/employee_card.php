<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        employee_card.php
 * \ingroup     sgpayroll
 * \brief       Employee HR profile — view and edit Singapore-specific fields
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Cannot load main.inc.php'); }

dol_include_once('sgpayroll/class/employee.class.php');
dol_include_once('sgpayroll/class/payrollcalc.class.php');
dol_include_once('sgpayroll/lib/sgpayroll.lib.php');

if (!isModEnabled("sgpayroll")) accessforbidden();
$langs->loadLangs(array('sgpayroll@sgpayroll', 'users'));

$fkUser = GETPOST('fk_user', 'int');
if (empty($fkUser) && $action !== 'create' && $action !== 'add') {
	$fkUser = $user->id;
}

$action = GETPOST('action', 'alpha');

// Hooks
$hookmanager->initHooks(array('sgpayrollemployeecard'));

// Self or HR check
$isSelf      = ($fkUser == $user->id);
$canEditFull = $user->admin || $user->hasRight('sgpayroll', 'employee', 'write');
$canEditSelf = ($user->hasRight('sgpayroll', 'employee', 'self_write') || $user->admin) && $isSelf;
$canEdit     = $canEditFull || $canEditSelf;
$canView     = $canEditFull || $user->hasRight('sgpayroll', 'employee', 'read') || $isSelf;
if (!$canView) accessforbidden();

// Load Dolibarr User
$empUser = new User($db);
$empUser->fetch($fkUser);

// Load SG payroll employee profile
$emp = new SGPayrollEmployee($db);
$emp->fetchByUser($fkUser);
if (empty($emp->fk_user)) {
	$emp->fk_user = $fkUser;
}

// Job Position history (latest record drives current job title + salary display)
$latestJobpos = null;
$jobposHistory = array();
if ($fkUser > 0) {
	$latestJobpos = $emp->fetchLatestJobPosition($fkUser);
	$jobposHistory = $emp->fetchJobPositionHistory($fkUser, 20);
}

// Normalize dates to timestamps for consistent rendering/saving behavior
$dateProps = array('pr_start_date','dob','pass_expiry','passport_expiry','work_contract_date','probation_end_date','cessation_date');
foreach($dateProps as $p) {
    if (!empty($emp->$p) && !is_numeric($emp->$p)) {
        $emp->$p = $db->jdate($emp->$p);
    }
}

// ── APPROVAL ACTIONS (HR ONLY) ───────────────────────────────────────────────
if ($canEditFull && !empty($emp->pending_json)) {
	if (in_array($action, array('approve_changes', 'reject_changes'), true) && !verifyToken(GETPOST('token', 'aZ09'))) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
		$action = '';
	}
	if ($action === 'approve_changes') {
		$db->begin();
		if ($emp->approveChangeRequest($user) > 0) {
			$db->commit();
			setEventMessages($langs->trans('ChangesApproved'), null, 'mesgs');
		} else {
			$db->rollback();
			setEventMessages($emp->error, null, 'errors');
		}
		header('Location: employee_card.php?fk_user='.$fkUser); exit;
	}
	if ($action === 'reject_changes') {
		if ($emp->rejectChangeRequest() > 0) {
			setEventMessages($langs->trans('ChangesRejected'), null, 'mesgs');
		}
		header('Location: employee_card.php?fk_user='.$fkUser); exit;
	}
}

// ── SAVE / ADD ──────────────────────────────────────────────────────────────
if ($action === 'add' && $canEdit) {
	$newUser = new User($db);
	$newUser->firstname = GETPOST('firstname', 'alphanohtml');
	$newUser->lastname  = GETPOST('lastname', 'alphanohtml');
	$newUser->email     = GETPOST('email', 'alphanohtml');
	$newUser->login     = GETPOST('login', 'alphanohtml');
	$newUser->pass      = GETPOST('password', 'none');
	$newUser->statut    = 1;
	$newUser->entity    = $conf->entity;

	$db->begin();
	$resUser = $newUser->create($user);
	if ($resUser <= 0) {
		$db->rollback();
		setEventMessages($newUser->error, $newUser->errors, 'errors');
		$action = 'create';
	} else {
		$db->commit();
		$fkUser = $resUser;
		$emp->fk_user = $fkUser;
		$empUser = $newUser;
		$action = 'save';
	}
}

if ($action === 'save' && $canEdit) {
	$emp->nric_fin          = GETPOST('nric_fin',           'alphanohtml');
	$emp->id_type           = GETPOST('id_type',            'aZ');
	$emp->citizenship       = GETPOST('citizenship',        'aZ');
	if (GETPOSTISSET('fk_cost_centre')) {
		$emp->fk_cost_centre    = (int) GETPOST('fk_cost_centre', 'int');
	}
	$emp->pr_start_date     = dol_mktime(0, 0, 0, GETPOSTINT('pr_start_datemonth'), GETPOSTINT('pr_start_dateday'), GETPOSTINT('pr_start_dateyear')) ?: null;
	// PR tier should be auto-derived from PR start date as-of today (UI requirement).
	if (strpos($emp->citizenship, 'PR') === 0) {
		$derivedTier = SGPayrollCalc::derivePRTier($emp->pr_start_date, date('Y-m-d'));
		$emp->citizenship = $derivedTier ?: 'PR1Y';
	}
	$emp->race              = GETPOST('race',               'alpha');
	$emp->is_muslim         = GETPOST('is_muslim', 'int')   ? 1 : 0;
	$emp->dob               = dol_mktime(0, 0, 0, GETPOSTINT('dobmonth'), GETPOSTINT('dobday'), GETPOSTINT('dobyear')) ?: null;
	$emp->gender            = GETPOST('gender',             'aZ');
	$emp->employment_type   = GETPOST('employment_type',    'aZ');
	$emp->pass_type         = GETPOST('pass_type',          'alphanohtml');
	$emp->pass_number       = GETPOST('pass_number',        'alphanohtml');
	$emp->pass_expiry       = dol_mktime(0, 0, 0, GETPOSTINT('pass_expirymonth'), GETPOSTINT('pass_expiryday'), GETPOSTINT('pass_expiryyear')) ?: null;
	$emp->passport_number   = GETPOST('passport_number',    'alphanohtml');
	$emp->passport_expiry   = dol_mktime(0, 0, 0, GETPOSTINT('passport_expirymonth'), GETPOSTINT('passport_expiryday'), GETPOSTINT('passport_expiryyear')) ?: null;
	$emp->work_contract_date= dol_mktime(0, 0, 0, GETPOSTINT('work_contract_datemonth'), GETPOSTINT('work_contract_dateday'), GETPOSTINT('work_contract_dateyear')) ?: null;
	$emp->probation_end_date= dol_mktime(0, 0, 0, GETPOSTINT('probation_end_datemonth'), GETPOSTINT('probation_end_dateday'), GETPOSTINT('probation_end_dateyear')) ?: null;
	$emp->cessation_date    = dol_mktime(0, 0, 0, GETPOSTINT('cessation_datemonth'), GETPOSTINT('cessation_dateday'), GETPOSTINT('cessation_dateyear')) ?: null;
	$emp->tax_residency     = GETPOST('tax_residency',      'aZ');
	$emp->fk_supervisor     = GETPOST('fk_supervisor',      'int')     ?: null;
	$emp->workdays_per_month = price2num(GETPOST('workdays_per_month', 'alpha'));

	// Job Position (HR only) — stored as history records; latest is synced to core user.job
	$jobpos_title = null;
	$jobpos_start_ts = null;
	$jobpos_end_ts = null;
	$jobpos_salary = null;
	$jobpos_should_insert = false;
	if ($canEditFull && GETPOSTISSET('job_position')) {
		$jobpos_title = trim(GETPOST('job_position', 'alphanohtml'));
		$jobpos_start_ts = dol_mktime(0, 0, 0, GETPOSTINT('jobpos_startmonth'), GETPOSTINT('jobpos_startday'), GETPOSTINT('jobpos_startyear')) ?: null;
		$jobpos_end_ts   = dol_mktime(0, 0, 0, GETPOSTINT('jobpos_endmonth'),   GETPOSTINT('jobpos_endday'),   GETPOSTINT('jobpos_endyear')) ?: null;
		$emp->job_position_current = ($jobpos_title !== '') ? $jobpos_title : null;
	}

	$emp->basic_salary      = price2num(GETPOST('basic_salary',  'alpha'), 'MU');
	$emp->hourly_rate       = price2num(GETPOST('hourly_rate',   'alpha'), 'MU');
	$emp->payment_mode      = GETPOST('payment_mode',       'aZ');
	$emp->bank_name         = GETPOST('bank_name',          'alphanohtml');
	$emp->bank_branch_code  = GETPOST('bank_branch_code',   'alphanohtml');
	$emp->bank_account      = GETPOST('bank_account',       'alphanohtml');
	$emp->shg_opt_out_cdac  = GETPOST('shg_opt_out_cdac',  'int') ? 1 : 0;
	$emp->shg_opt_out_ecf   = GETPOST('shg_opt_out_ecf',   'int') ? 1 : 0;
	$emp->shg_opt_out_mbmf  = GETPOST('shg_opt_out_mbmf',  'int') ? 1 : 0;
	$emp->shg_opt_out_sinda = GETPOST('shg_opt_out_sinda', 'int') ? 1 : 0;
	$emp->status            = GETPOSTINT('status');
	$raw_schedule = GETPOST('schedule', 'array'); // Updated name for refactored schedule
	$scheduleStr = '';
	if (is_array($raw_schedule)) {
		$scheduleStr = sgpayroll_normalize_weekly_schedule(implode(';', array_map(function ($d) use ($raw_schedule) {
			$v = isset($raw_schedule[$d]) ? (float)$raw_schedule[$d] : 0;
			return $d.':'.$v;
		}, array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'))));
	}
	$emp->weekly_schedule  = $scheduleStr;
	dol_syslog('sgpayroll employee_card: saving weekly_schedule='.$emp->weekly_schedule, LOG_DEBUG);
	$emp->contract_currency = GETPOST('contract_currency', 'alpha') ?: 'SGD';
	$emp->contract_salary   = price2num(GETPOST('contract_salary', 'alpha'), 'MU');
	$emp->exchange_rate     = price2num(GETPOST('exchange_rate', 'alpha'));
	$emp->exchange_rate     = ($emp->exchange_rate > 0) ? $emp->exchange_rate : 1.0;
	$emp->entity            = (int) $conf->entity;
	
	// Persistent Allowances
	$allowLabels  = GETPOST('allow_label', 'array');
	$allowAmounts = GETPOST('allow_amount', 'array');
	$allowCpfs    = GETPOST('allow_cpf', 'array');
	$allowRows    = array();
	if (is_array($allowLabels)) {
		foreach ($allowLabels as $idx => $lbl) {
			if (empty($lbl) && empty($allowAmounts[$idx])) continue;
			$allowRows[] = array(
				'label'      => $lbl,
				'amount'     => (float)($allowAmounts[$idx] ?? 0),
				'cpf_liable' => !empty($allowCpfs[$idx]) ? 1 : 0
			);
		}
	}
	$emp->allowances_json = count($allowRows) ? json_encode($allowRows) : '';

	// Sync with Profile handled in $emp->save

	$error = 0;
	$db->begin();

	if (!$error) {
		// Sync Claims total if needed (for sensitive field threshold checks) — use current month when not in payslip context
		$payYear  = (int) dol_print_date(dol_now(), '%Y');
		$payMonth = (int) dol_print_date(dol_now(), '%m');
		$sqlC = "SELECT SUM(total_ttc) as total FROM ".MAIN_DB_PREFIX."expensereport";
		$sqlC .= " WHERE fk_user = ".(int)$fkUser;
		$sqlC .= " AND fk_statut = 5"; // Approved (D22: 5=approved, 4=cancelled)
		$sqlC .= " AND date_debut >= '".$db->idate(dol_get_first_day($payYear, $payMonth))."'";
		$sqlC .= " AND date_debut <= '".$db->idate(dol_get_last_day($payYear, $payMonth))."'";
		$resC = $db->query($sqlC);
		if ($resC) {
			$objC = $db->fetch_object($resC);
			$claims_total = (float)$objC->total;
		}

		// Change Request logic
		if ($canEditSelf && !$canEditFull) {
			$origEmp = new SGPayrollEmployee($db);
			$origEmp->fetchByUser($fkUser);
			// Normalize original employee dates too for proper comparison
			foreach ($dateProps as $p) {
				if (!empty($origEmp->$p) && !is_numeric($origEmp->$p)) {
					$origEmp->$p = $db->jdate($origEmp->$p);
				}
			}
			// Sensitive / pay-affecting fields need approval before taking effect on self-service edits.
			// Numeric fields compare numerically (DB DECIMAL and price2num string formats differ).
			$sensitiveFields = array('nric_fin', 'basic_salary', 'hourly_rate', 'bank_name', 'bank_branch_code', 'bank_account',
				'allowances_json', 'contract_salary', 'workdays_per_month', 'exchange_rate', 'status', 'fk_supervisor');
			$changesToQueue = array();
			foreach ($sensitiveFields as $fld) {
				// Compare current object against original (since $emp is already mutated by GETPOST above)
				$oldVal = (string)$origEmp->$fld;
				$newVal = (string)$emp->$fld;
				$changed = (is_numeric($oldVal) && is_numeric($newVal)) ? ((float)$oldVal != (float)$newVal) : ($oldVal !== $newVal);
				if ($changed) {
					$changesToQueue[$fld] = $emp->$fld;
					// Restore original value so it doesn't get saved to database immediately
					$emp->$fld = $origEmp->$fld;
				}
			}
			if (!empty($changesToQueue)) {
				if ($emp->submitChangeRequest($changesToQueue) < 0) {
					$error++;
					setEventMessages($emp->error, null, 'errors');
				} else {
					setEventMessages($langs->trans('ChangesQueuedForApproval'), null, 'warnings');
				}
			}
		}
	}

	if (!$error) {
		if ($emp->save($user) > 0) {
			// Persist Job Position history only for HR saves (avoid creating history for self-service requests)
			if ($canEditFull && $jobpos_title !== null) {
				$jobpos_salary = (float) $emp->basic_salary;
				$latestBefore = $emp->fetchLatestJobPosition($fkUser);
				$latestStartTs = $latestBefore ? $db->jdate($latestBefore['date_start']) : null;
				$latestEndTs   = ($latestBefore && !empty($latestBefore['date_end'])) ? $db->jdate($latestBefore['date_end']) : null;
				$latestSalary  = $latestBefore ? (float)$latestBefore['salary_sgd'] : null;
				$latestTitle   = $latestBefore ? (string)$latestBefore['job_position'] : '';
				$latestCcy     = $latestBefore && isset($latestBefore['contract_currency']) ? (string)$latestBefore['contract_currency'] : 'SGD';
				$latestCsal    = $latestBefore && isset($latestBefore['contract_salary']) ? (float)$latestBefore['contract_salary'] : null;

				$changed = false;
				if (!$latestBefore) $changed = true;
				if (trim((string)$jobpos_title) !== trim($latestTitle)) $changed = true;
				if ($latestSalary === null || abs($jobpos_salary - $latestSalary) > 0.0001) $changed = true;
				if ($jobpos_start_ts && $latestStartTs && (int)$jobpos_start_ts !== (int)$latestStartTs) $changed = true;
				if (($jobpos_end_ts ?: 0) !== ($latestEndTs ?: 0)) $changed = true;
				if (strtoupper((string)$emp->contract_currency) !== strtoupper($latestCcy)) $changed = true;
				if ($latestCsal === null || abs((float)$emp->contract_salary - $latestCsal) > 0.0001) $changed = true;

				if ($changed && trim((string)$jobpos_title) !== '') {
					$resJob = $emp->addJobPositionHistory($fkUser, $jobpos_title, $jobpos_start_ts, $jobpos_end_ts, $jobpos_salary, $user, $emp->contract_currency, (float)$emp->contract_salary);
					if ($resJob < 0) {
						setEventMessages($langs->trans('PositionHistory').': '.$emp->error, null, 'warnings');
					}
				}
			}

			$db->commit();
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		} else {
			$error++;
			setEventMessages($emp->error, null, 'errors');
		}
	}

	if ($error) {
		$db->rollback();
	}

	header('Location: employee_card.php?fk_user='.$fkUser);
	exit;
}

// ── PAGE ─────────────────────────────────────────────────────────────────────
$title = $langs->trans('EmployeeProfile');
if ($action === 'create') {
	$title = $langs->trans('NewEmployee');
}

llxHeader('', $title, '');

$ficheTitle = $langs->trans('EmployeeProfile').' — '.dol_escape_htmltag($empUser->getFullName($langs));
if ($action === 'create') {
	$ficheTitle = $langs->trans('NewEmployee');
}

print load_fiche_titre($ficheTitle, '', 'user');

$form = new Form($db);

// Tabs
$head = array();
if ($fkUser > 0) {
	$head = sgpayroll_employee_prepare_head($fkUser);
	dol_fiche_head($head, 'hr', '', 0, '');
} else {
	dol_fiche_head($head, 'hr', '', 0, '');
}

// ── PENDING CHANGES BANNER ──────────────────────────────────────────────────
if (!empty($emp->pending_json)) {
	$changes = json_decode($emp->pending_json, true);
	print '<div class="warning">';
	if ($canEditFull) {
		print '<strong>'.$langs->trans('PendingChangesFromEmployee').'</strong><ul>';
		foreach ($changes as $k => $v) {
			print '<li>'.dol_escape_htmltag($langs->trans($k)).': <span class="opacitymedium">'.dol_escape_htmltag((string)$emp->$k).'</span> &rarr; <strong>'.(is_numeric($v)?price($v):dol_escape_htmltag((string)$v)).'</strong></li>';
		}
		print '</ul>';
		print '<form method="POST" action="employee_card.php" style="display:inline">';
		print '<input type="hidden" name="fk_user" value="'.$fkUser.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button name="action" value="approve_changes" class="button">'.$langs->trans('Approve').'</button></form> ';
		print '<form method="POST" action="employee_card.php" style="display:inline">';
		print '<input type="hidden" name="fk_user" value="'.$fkUser.'"><input type="hidden" name="token" value="'.newToken().'">';
		print '<button name="action" value="reject_changes" class="button buttonRefused">'.$langs->trans('Reject').'</button></form>';
	} else {
		print $langs->trans('YourChangesArePendingApproval');
	}
	print '</div><br>';
}

// ── FORM ─────────────────────────────────────────────────────────────────────
print '<form method="POST" action="employee_card.php">';
if ($action === 'create') {
	print '<input type="hidden" name="action" value="add">';
} else {
	print '<input type="hidden" name="action" value="save">';
	print '<input type="hidden" name="fk_user" value="'.$fkUser.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';

// Helper macro
function f($label, $field_html, $required = false, $id = '', $shared = false) {
	$shared_icon = $shared ? ' <span class="opacitymedium" title="Shared with Core Profile">'.img_picto('', 'refresh').'</span>' : '';
	print '<tr'.($id?' id="'.$id.'"':'').'><td class="titlefield">'.$label.$shared_icon.($required?'<span class="fieldrequired"> *</span>':'').'</td><td>'.$field_html.'</td></tr>';
}

$ro     = ($canEdit && ($action === 'edit' || $action === 'create')) ? 0 : 1;
if (!$canEdit) $ro = 1;

// Password link for self
if ($isSelf) {
	print '<div class="right"><a href="'.DOL_URL_ROOT.'/user/password.php?id='.$user->id.'">'.img_picto('', 'edit-password').' '.$langs->trans('ChangePassword').'</a></div>';
}

// ── Section: System Account (Creation Only) ──────────────────────────────────
if ($action === 'create') {
	print '<div class="fichecenter">';
	print '<table class="border tableforfield centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SystemAccount').' (Creates Core User)</td></tr>';
	f($langs->trans('FirstName'), '<input type="text" name="firstname" value="'.dol_escape_htmltag(GETPOST('firstname', 'alphanohtml')).'" class="flat" required autofocus>', true);
	f($langs->trans('LastName'), '<input type="text" name="lastname" value="'.dol_escape_htmltag(GETPOST('lastname', 'alphanohtml')).'" class="flat" required>', true);
	f($langs->trans('Login'), '<input type="text" name="login" value="'.dol_escape_htmltag(GETPOST('login', 'alphanohtml')).'" class="flat" required>', true);
	f($langs->trans('Password'), '<input type="password" name="password" value="" class="flat" required autocomplete="new-password">', true);
	f($langs->trans('Email'), '<input type="email" name="email" value="'.dol_escape_htmltag(GETPOST('email', 'alphanohtml')).'" class="flat">');
	print '</table>';
	print '</div><br>';
}

// ── Section: Identity ─────────────────────────────────────────────────────────
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('IdentityInfo').'</td></tr>';

// NRIC/FIN
$html_nric = $ro ? dol_escape_htmltag($emp->nric_fin) : '<input type="text" name="nric_fin" value="'.dol_escape_htmltag($emp->nric_fin).'" class="flat" style="width:180px">';
f($langs->trans('NricFin'), $html_nric);

// ID Type
$id_types = array('NRIC'=>'NRIC','FIN'=>'FIN','PASSPORT'=>'Passport','WP'=>'WP','EP'=>'EP','SP'=>'S-Pass','MIC'=>'Malay IC');
$html_id_type = $ro ? dol_escape_htmltag($id_types[$emp->id_type] ?? $emp->id_type) : $form->selectarray('id_type', $id_types, $emp->id_type, 0, 0, 0, 'class="flat"');
f($langs->trans('IdType'), $html_id_type);

// Citizenship
$todayYmd = date('Y-m-d');
$prTierLabels = array('PR1Y'=>'SG PR (1st Year)', 'PR2Y'=>'SG PR (2nd Year)', 'PR3Y'=>'SG PR (3rd Year+)');
$prTierToday = SGPayrollCalc::derivePRTier($emp->pr_start_date, $todayYmd);
$prTierLabelToday = $prTierLabels[$prTierToday] ?? '';
$citizenships = array(
	'SC'  => 'Singapore Citizen',
	'PR'  => 'SG PR'.($prTierLabelToday ? ' — '.$prTierLabelToday : ''),
	'FIN' => 'Foreigner'
);
$selectedCit = (strpos((string)$emp->citizenship, 'PR') === 0) ? 'PR' : $emp->citizenship;
$html_citizenship = $ro
	? dol_escape_htmltag((strpos((string)$emp->citizenship, 'PR') === 0) ? ($prTierLabels[$emp->citizenship] ?? $emp->citizenship) : ($citizenships[$emp->citizenship] ?? $emp->citizenship))
	: $form->selectarray('citizenship', $citizenships, $selectedCit, 0, 0, 0, 'class="flat"');
f($langs->trans('Citizenship'), $html_citizenship);

// PR Start Date
$ts = is_numeric($emp->pr_start_date) ? $emp->pr_start_date : ((!empty($emp->pr_start_date) && !in_array($emp->pr_start_date, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->pr_start_date) : '');
if ($ro) {
	$html_pr_start = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'pr_start_date', 0, 0, 1, 'pr_start_date');
	$html_pr_start = ob_get_clean();
}
f($langs->trans('PrStartDate'), $html_pr_start);

// DOB
$ts = is_numeric($emp->dob) ? $emp->dob : ((!empty($emp->dob) && !in_array($emp->dob, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->dob) : '');
if ($ro) {
	$html_dob = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'dob', 0, 0, 1, 'dob');
	$html_dob = ob_get_clean();
}
f($langs->trans('Dob'), $html_dob, false, '', true);

// Gender
$genders = array('man'=>$langs->trans('Male'),'woman'=>$langs->trans('Female'));
$html_gender = $ro ? dol_escape_htmltag($genders[$emp->gender] ?? $emp->gender) : $form->selectarray('gender', $genders, $emp->gender, 1, 0, 0, 'class="flat"');
f($langs->trans('Gender'), $html_gender, false, '', true);

// Race
$races = array('Chinese'=>'Chinese','Malay'=>'Malay','Indian'=>'Indian','Eurasian'=>'Eurasian','Others'=>'Others');
$html_race = $ro ? dol_escape_htmltag($races[$emp->race] ?? $emp->race) : $form->selectarray('race', $races, $emp->race, 0, 0, 0, 'class="flat"');
f($langs->trans('Race'), $html_race);

// Is Muslim
$muslimChk = $ro ? ($emp->is_muslim ? '✓ Yes' : 'No') : '<input type="checkbox" name="is_muslim" value="1"'.($emp->is_muslim?' checked':'').'>';
f($langs->trans('IsMuslim'), $muslimChk);

// Tax Residency
$residencies = array('resident'=>'Tax Resident','non_resident'=>'Non-Resident');
$html_residency = $ro ? dol_escape_htmltag($residencies[$emp->tax_residency] ?? $emp->tax_residency) : $form->selectarray('tax_residency', $residencies, $emp->tax_residency, 0, 0, 0, 'class="flat"');
f($langs->trans('TaxResidency'), $html_residency);

// Cost Centre
$sql_cc = "SELECT rowid, label, code FROM ".MAIN_DB_PREFIX."sgpayroll_cost_centre WHERE entity IN (0, ".(int)$conf->entity.") AND active = 1 ORDER BY code";
$res_cc = $db->query($sql_cc);
$cc_array = array(0 => ''); // 0 means unassigned
if ($res_cc) {
	while ($obj_cc = $db->fetch_object($res_cc)) {
		$cc_array[$obj_cc->rowid] = $obj_cc->code.' - '.$obj_cc->label;
	}
}
$html_cc = $ro ? dol_escape_htmltag($cc_array[$emp->fk_cost_centre] ?? '') : $form->selectarray('fk_cost_centre', $cc_array, $emp->fk_cost_centre, 0, 0, 0, 'class="flat minwidth200"');
f($langs->trans('CostCentre'), $html_cc);

print '</table>';

// ── Section: SHG Opt-Out ─────────────────────────────────────────────────────
print '<table class="border tableforfield centpercent" style="margin-top: 12px;">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('ShgOptOut').'</td></tr>';
foreach (array('cdac'=>'CDAC','ecf'=>'ECF','mbmf'=>'MBMF','sinda'=>'SINDA') as $key => $lbl) {
	$fld  = 'shg_opt_out_'.$key;
	$val  = $emp->$fld;

	// Visibility logic for view mode
	if ($ro) {
		$visible = false;
		if ($key === 'mbmf'  && ($emp->is_muslim || $emp->race === 'Malay')) $visible = true;
		elseif ($key === 'cdac'  && $emp->race === 'Chinese' && !$emp->is_muslim) $visible = true;
		elseif ($key === 'sinda' && $emp->race === 'Indian'  && !$emp->is_muslim) $visible = true;
		elseif ($key === 'ecf'   && $emp->race === 'Eurasian' && !$emp->is_muslim) $visible = true;

		if (!$visible) continue;
	}

	$html = $ro ? ($val ? '✓ Opted Out' : 'Contributing') : '<input type="checkbox" name="'.$fld.'" value="1"'.($val?' checked':'').'> '.$langs->trans('Optout');
	f($lbl, $html, false, 'tr_shg_'.$key);
}
print '</table></div>';

// ── Section: Employment ───────────────────────────────────────────────────────
print '<div class="fichehalfright">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('EmploymentInfo').'</td></tr>';

// Employment Type
$emp_types = array('fulltime'=>'Full-Time','parttime'=>'Part-Time','contract'=>'Contract','freelancer'=>'Freelancer','intern'=>'Intern');
$html_emp_type = $ro ? dol_escape_htmltag($emp_types[$emp->employment_type] ?? $emp->employment_type) : $form->selectarray('employment_type', $emp_types, $emp->employment_type, 0, 0, 0, 'class="flat"');
f($langs->trans('EmploymentType'), $html_emp_type);

// Pass Type
$pass_types = array(''=>'None','EP'=>'Employment Pass','SP'=>'S-Pass','WP'=>'Work Permit','LTVP'=>'LTVP','DP'=>'Dependent Pass');
$html_pass_type = $ro ? dol_escape_htmltag($pass_types[$emp->pass_type] ?? $emp->pass_type) : $form->selectarray('pass_type', $pass_types, $emp->pass_type, 0, 0, 0, 'class="flat"');
f($langs->trans('PassType'), $html_pass_type);

// Pass Number
$html_pass_num = $ro ? dol_escape_htmltag($emp->pass_number) : '<input type="text" name="pass_number" value="'.dol_escape_htmltag($emp->pass_number).'" class="flat">';
f($langs->trans('PassNumber'), $html_pass_num);

// Pass Expiry
$ts = is_numeric($emp->pass_expiry) ? $emp->pass_expiry : ((!empty($emp->pass_expiry) && !in_array($emp->pass_expiry, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->pass_expiry) : '');
if ($ro) {
	$html_pass_exp = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'pass_expiry', 0, 0, 1, 'pass_expiry');
	$html_pass_exp = ob_get_clean();
}
f($langs->trans('PassExpiry'), $html_pass_exp);

// Passport Number
$html_passport_num = $ro ? dol_escape_htmltag($emp->passport_number) : '<input type="text" name="passport_number" value="'.dol_escape_htmltag($emp->passport_number).'" class="flat">';
f($langs->trans('PassportNumber'), $html_passport_num);

// Passport Expiry
$ts = is_numeric($emp->passport_expiry) ? $emp->passport_expiry : ((!empty($emp->passport_expiry) && !in_array($emp->passport_expiry, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->passport_expiry) : '');
if ($ro) {
	$html_passport_exp = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'passport_expiry', 0, 0, 1, 'passport_expiry');
	$html_passport_exp = ob_get_clean();
}
f($langs->trans('PassportExpiry'), $html_passport_exp);

// Work Contract Date (Employment Date)
$ts = is_numeric($emp->work_contract_date) ? $emp->work_contract_date : ((!empty($emp->work_contract_date) && !in_array($emp->work_contract_date, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->work_contract_date) : '');
if ($ro) {
	$html_contract_date = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'work_contract_date', 0, 0, 1, 'work_contract_date');
	$html_contract_date = ob_get_clean();
}
f($langs->trans('WorkContractDate'), $html_contract_date, false, '', true);

// Probation End Date
$ts = is_numeric($emp->probation_end_date) ? $emp->probation_end_date : ((!empty($emp->probation_end_date) && !in_array($emp->probation_end_date, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->probation_end_date) : '');
if ($ro) {
	$html_probation_end = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'probation_end_date', 0, 0, 1, 'probation_end_date');
	$html_probation_end = ob_get_clean();
}
f($langs->trans('ProbationEndDate'), $html_probation_end);

// Cessation Date (Termination Date)
$ts = is_numeric($emp->cessation_date) ? $emp->cessation_date : ((!empty($emp->cessation_date) && !in_array($emp->cessation_date, array('0000-00-00', '0000-00-00 00:00:00'))) ? $db->jdate($emp->cessation_date) : '');
if ($ro) {
	$html_cessation_date = ($ts !== '') ? dol_print_date($ts, 'day') : '';
} else {
	ob_start();
	$form->select_date($ts, 'cessation_date', 0, 0, 1, 'cessation_date');
	$html_cessation_date = ob_get_clean();
}
f($langs->trans('CessationDate'), $html_cessation_date, false, '', true);

// Supervisor
if ($ro) {
    if ($emp->fk_supervisor > 0) {
        $sup = new User($db);
        $sup->fetch($emp->fk_supervisor);
        $html_sup = $sup->getNomUrl(1);
    } else {
        $html_sup = $langs->trans('None');
    }
} else {
    $html_sup = $form->select_dolusers($emp->fk_supervisor, 'fk_supervisor', 1, array($fkUser));
}
f($langs->trans('Supervisor'), $html_sup, false, '', true);

// Weekly Schedule (Refactored for Half-Days)
$days = array('Mon','Tue','Wed','Thu','Fri','Sat','Sun');
// Schedule format: Day:Value;Day:Value (e.g. Mon:1;Tue:1;Sat:0.5)
$currentScheduleRaw = $emp->weekly_schedule;
if (empty($currentScheduleRaw) && $action === 'create') {
	$currentScheduleRaw = getDolGlobalString('SGPAYROLL_GLOBAL_WEEKLY_SCHEDULE', 'Mon:1;Tue:1;Wed:1;Thu:1;Fri:1');
}
$scheduleArr = array();
if ($currentScheduleRaw) {
    $pairs = explode(';', $currentScheduleRaw);
    foreach ($pairs as $p) {
        $parts = explode(':', $p);
        if (count($parts) == 2) $scheduleArr[$parts[0]] = (float)$parts[1];
    }
} else {
    // Migration fallback for old comma-separated list (defaults to Full for all listed days)
    $fallback = explode(',', $currentScheduleRaw);
    foreach ($fallback as $d) {
        if ($d) $scheduleArr[trim($d)] = 1.0;
    }
}

// Schedule presets dropdown (edit mode only)
$schedule_presets = array();
if (!$ro) {
	$sql_sp = "SELECT rowid, code, label, schedule_value FROM ".MAIN_DB_PREFIX."sgpayroll_schedule_preset WHERE entity IN (0, ".(int)$conf->entity.") AND active = 1 ORDER BY code";
	$res_sp = $db->query($sql_sp);
	if ($res_sp) {
		while ($row_sp = $db->fetch_object($res_sp)) {
			$schedule_presets[] = array('id' => $row_sp->rowid, 'label' => $row_sp->label, 'code' => $row_sp->code, 'schedule_value' => $row_sp->schedule_value);
		}
	}
}

$html_schedule = '';
if (!$ro && !empty($schedule_presets)) {
	$html_schedule .= '<div class="schedule-preset-row" style="margin-bottom:10px;">';
	$html_schedule .= '<label>'.$langs->trans('ApplySchedulePreset').' </label>';
	$html_schedule .= '<select id="schedule_preset_select" class="flat minwidth200">';
	$html_schedule .= '<option value="">— '.$langs->trans('SelectPreset').' —</option>';
	foreach ($schedule_presets as $pre) {
		$html_schedule .= '<option value="'.(int)$pre['id'].'" data-schedule="'.dol_escape_htmltag($pre['schedule_value']).'">'.dol_escape_htmltag($pre['label']).' ('.$pre['code'].')</option>';
	}
	$html_schedule .= '</select>';
	$html_schedule .= '</div>';
}
$html_schedule .= '<div class="schedule-container" style="display:flex; flex-wrap:wrap; gap:10px;">';
foreach ($days as $day) {
    $val = $scheduleArr[$day] ?? 0;
    $html_schedule .= '<div class="schedule-day" style="border:1px solid #ddd; padding:5px; border-radius:4px; background:#f9f9f9; min-width:80px;">';
    $html_schedule .= '<div style="font-weight:bold; border-bottom:1px solid #eee; margin-bottom:5px;">'.$langs->trans('Day'.$day).'</div>';
    if ($ro) {
        if ($val == 1) $html_schedule .= '<span class="badge badge-status4">'.$langs->trans('Full').'</span>';
        elseif ($val == 0.5) $html_schedule .= '<span class="badge badge-status4">'.$langs->trans('Half').'</span>';
        else $html_schedule .= '<span class="opacitymedium">'.$langs->trans('Off').'</span>';
    } else {
        $options = array('0'=>$langs->trans('Off'), '0.5'=>$langs->trans('Half'), '1'=>$langs->trans('Full'));
        foreach ($options as $ov => $ol) {
            $checked = ((string)$val === (string)$ov) ? ' checked' : '';
            $html_schedule .= '<label style="display:block; cursor:pointer;"><input type="radio" name="schedule['.$day.']" value="'.$ov.'"'.$checked.'> '.$ol.'</label>';
        }
    }
    $html_schedule .= '</div>';
}
$html_schedule .= '</div>';
f($langs->trans('WeeklySchedule'), $html_schedule);

// Average Working Days (Legacy/Override)
$html_workdays = $ro ? price($emp->workdays_per_month) : '<input type="number" name="workdays_per_month" value="'.price2num($emp->workdays_per_month).'" step="0.5" class="flat width50"> '.$langs->trans('DaysPerMonthDefault');
f($langs->trans('WorkDaysOverride'), $html_workdays);

// Status
$html_status = $ro ? ($emp->status ? '<span class="badge badge-status4">'.$langs->trans('Active').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Inactive').'</span>') : $form->selectarray('status', array('1'=>$langs->trans('Active'), '0'=>$langs->trans('Inactive')), $emp->status, 0, 0, 0, 'class="flat"');
f($langs->trans('EmployeeStatus'), $html_status, false, '', true);

print '</table></div></div>';

// ── Section: Job Position + Bank (split) ──────────────────────────────────────
$current_job_title = '';
$current_job_salary = $emp->basic_salary;
$current_job_start_ts = null;
$current_job_end_ts = null;
if (!empty($latestJobpos)) {
	$current_job_title = (string) $latestJobpos['job_position'];
	$current_job_salary = (float) $latestJobpos['salary_sgd'];
	$current_job_start_ts = !empty($latestJobpos['date_start']) ? $db->jdate($latestJobpos['date_start']) : null;
	$current_job_end_ts = !empty($latestJobpos['date_end']) ? $db->jdate($latestJobpos['date_end']) : null;
} else {
	$current_job_title = (string) ($empUser->job ?? '');
}
if (empty($current_job_start_ts)) {
	$current_job_start_ts = (!empty($emp->work_contract_date) ? (is_numeric($emp->work_contract_date) ? $emp->work_contract_date : $db->jdate($emp->work_contract_date)) : dol_now());
}

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('JobPositionGroup').'</td></tr>';

// Job position
$html_jobpos = ($ro || !$canEditFull) ? dol_escape_htmltag($current_job_title) : '<input type="text" name="job_position" value="'.dol_escape_htmltag($current_job_title).'" class="flat minwidth200">';
f($langs->trans('JobPosition'), $html_jobpos, false, '', true);

// Period (effective date range)
if ($ro || !$canEditFull) {
	$period = '';
	if (!empty($current_job_start_ts)) $period .= dol_print_date($current_job_start_ts, 'day');
	if (!empty($current_job_end_ts)) $period .= ' → '.dol_print_date($current_job_end_ts, 'day');
	else $period .= ' → '.$langs->trans('Ongoing');
	f($langs->trans('Period'), $period, false, '', true);
} else {
	ob_start();
	$form->select_date($current_job_start_ts, 'jobpos_start', 0, 0, 1, 'jobpos_start');
	$startHtml = ob_get_clean();
	ob_start();
	$form->select_date($current_job_end_ts, 'jobpos_end', 0, 0, 1, 'jobpos_end');
	$endHtml = ob_get_clean();
	f($langs->trans('Period'), $langs->trans('DateStart').' '.$startHtml.' &nbsp; '.$langs->trans('DateEnd').' '.$endHtml, false, '', true);
}

// Salary (SGD) -> basic_salary (kept for core sync + payroll calc)
$salaryToEdit = ($latestJobpos ? (float)$latestJobpos['salary_sgd'] : (float)$emp->basic_salary);
$html_basic = $ro ? price($current_job_salary) : '<input type="text" name="basic_salary" value="'.price2num($salaryToEdit).'" class="flat width150 right"> SGD';
f($langs->trans('Salary'), $html_basic, false, '', true);

// Multi-currency contract salary (kept for existing workflows)
if ($ro) {
	if ($emp->contract_currency !== 'SGD' || $emp->contract_salary > 0) {
		f($langs->trans('ContractCurrency'), dol_escape_htmltag($emp->contract_currency));
		f($langs->trans('ContractSalary'), price($emp->contract_salary).' '.dol_escape_htmltag($emp->contract_currency));
		f($langs->trans('ExchangeRate'), number_format($emp->exchange_rate, 4));
	}
} else {
	$mc_path = DOL_DOCUMENT_ROOT.'/core/class/html.formmulticurrency.class.php';
	ob_start();
	if (!empty($conf->multicurrency->enabled) && file_exists($mc_path)) {
		require_once $mc_path;
		$formmc = new FormMultiCurrency($db);
		$res_curr = $form->selectarray('contract_currency', $formmc->get_list_currencies(), $emp->contract_currency, 1, 0, 0, 'class="flat" style="min-width: 200px;"');
		if ($res_curr) echo $res_curr;
	} else {
		$res_curr = $form->select_currency($emp->contract_currency, 'contract_currency');
		if ($res_curr) echo $res_curr;
	}
	$html_curr = ob_get_clean();
	f($langs->trans('ContractCurrency'), $html_curr);

	$html_contract = '<input type="text" name="contract_salary" value="'.price2num($emp->contract_salary).'" class="flat width150 right">';
	f($langs->trans('ContractSalary'), $html_contract);

	$html_rate = '<span class="opacitymedium">1 SGD =</span> <input type="text" name="exchange_rate" value="'.number_format($emp->exchange_rate, 4).'" class="flat width80 right"><span class="opacitymedium ms_ccy_suffix"> '.dol_escape_htmltag($emp->contract_currency).'</span>';
	f($langs->trans('ExchangeRate'), $html_rate);
}

// Hourly Rate (legacy)
$html_hourly = $ro ? price($emp->hourly_rate) : '<input type="text" name="hourly_rate" value="'.price2num($emp->hourly_rate).'" class="flat width100 right"> SGD';
f($langs->trans('HourlyRate'), $html_hourly, false, '', true);

// History table
print '<tr><td colspan="2">';
print '<div style="margin-top:6px;">';
print '<div class="opacitymedium" style="margin-bottom:6px; display:flex; align-items:center; justify-content:space-between;">';
print '<strong>'.$langs->trans('PositionHistory').'</strong>';
if ($canEditFull) {
	$btn = img_picto('', 'edit', 'class="paddingright"').' '.$langs->trans('ManagePositionHistory');
	print '<a class="butAction" style="padding:4px 10px; font-size:12px; line-height:18px;" href="employee_jobpos.php?fk_user='.$fkUser.'" title="'.dol_escape_htmltag($langs->trans('ManagePositionHistory')).'">'.$btn.'</a>';
}
print '</div>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('DateStart').'</td><td>'.$langs->trans('DateEnd').'</td><td>'.$langs->trans('JobPosition').'</td><td class="right">'.$langs->trans('ContractSalary').'</td></tr>';
if (!empty($jobposHistory)) {
	foreach ($jobposHistory as $row) {
		$ds = !empty($row['date_start']) ? dol_print_date($db->jdate($row['date_start']), 'day') : '';
		$de = !empty($row['date_end']) ? dol_print_date($db->jdate($row['date_end']), 'day') : $langs->trans('Ongoing');
		$ccy = isset($row['contract_currency']) && $row['contract_currency'] !== '' ? $row['contract_currency'] : 'SGD';
		$csal = isset($row['contract_salary']) ? (float)$row['contract_salary'] : (float)$row['salary_sgd'];
		print '<tr class="oddeven">';
		print '<td>'.$ds.'</td>';
		print '<td>'.$de.'</td>';
		print '<td>'.dol_escape_htmltag($row['job_position']).'</td>';
		print '<td class="right">'.price($csal).' '.dol_escape_htmltag($ccy).'</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="4" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
print '</table></div>';
print '</td></tr>';

print '</table></div>';

// Bank
print '<div class="fichehalfright">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('BankGroup').'</td></tr>';

// Payment Mode
$pay_modes = array('bank'=>'Bank Transfer','cash'=>'Cash','cheque'=>'Cheque');
$html_pay_mode = $ro ? dol_escape_htmltag($pay_modes[$emp->payment_mode] ?? $emp->payment_mode) : $form->selectarray('payment_mode', $pay_modes, $emp->payment_mode, 0, 0, 0, 'class="flat"');
f($langs->trans('PaymentMode'), $html_pay_mode);

// Bank Details
$banks = sgpayroll_get_banks();
if ($emp->bank_name && !isset($banks[$emp->bank_name]) && !in_array($emp->bank_name, $banks)) {
	$banks[$emp->bank_name] = $emp->bank_name;
}
$full_bank_display = $banks[$emp->bank_name] ?? $emp->bank_name;
$html_bank_name = $ro ? dol_escape_htmltag($full_bank_display) : $form->selectarray('bank_name', $banks, $emp->bank_name, 1, 0, 0, 'class="flat"');
f($langs->trans('BankName'), $html_bank_name, false, '', true);

$html_bank_branch = $ro ? dol_escape_htmltag($emp->bank_branch_code) : '<input type="text" name="bank_branch_code" value="'.dol_escape_htmltag($emp->bank_branch_code).'" class="flat" style="width:80px">';
f($langs->trans('BankBranchCode').' (BIC)', $html_bank_branch, false, '', true);

$html_bank_acc = $ro ? dol_escape_htmltag($emp->bank_account) : '<input type="text" name="bank_account" value="'.dol_escape_htmltag($emp->bank_account).'" class="flat">';
f($langs->trans('BankAccount'), $html_bank_acc, false, '', true);

print '</table></div></div>';

// ── Section: Standard Allowances (Persistent) ─────────────────────────────────
print '<div class="fichecenter">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('StandardAllowances').' <span class="opacitymedium">('.$langs->trans('AutoPopulateInPayslip').')</span></td></tr>';

// Common Singapore Allowances for Dropdown
$commonAllowances = array(
	'Transport Allowance',
	'Meal Allowance',
	'Mobile / Phone Allowance',
	'Fixed Allowance',
	'Laundry Allowance',
	'Housing Allowance',
	'Entertainment Allowance',
	'Shift Allowance',
	'Attendance Allowance'
);

$allowRows = !empty($emp->allowances_json) ? json_decode($emp->allowances_json, true) : array();
if (!is_array($allowRows)) $allowRows = array();
if (empty($allowRows) && !$ro) $allowRows = array(array('label'=>'','amount'=>0,'cpf_liable'=>0));

foreach ($allowRows as $i => $a) {
	$row_id = 'row_'.$i;
	print '<tr class="allowance-row" id="'.$row_id.'">';
	print '<td>';
	if ($ro) {
		print dol_escape_htmltag($a['label'] ?? '');
	} else {
		print '<input list="allowance_list" name="allow_label['.$row_id.']" value="'.dol_escape_htmltag($a['label']??'').'" class="flat" style="width:250px" placeholder="'.$langs->trans('AllowanceLabel').'">';
	}
	print '</td>';
	print '<td class="right">'.($ro ? price($a['amount']??0) : '<input type="number" name="allow_amount['.$row_id.']" value="'.(float)($a['amount']??0).'" step="0.01" class="flat width100 right">').'</td>';
	print '<td>'.($ro ? (!empty($a['cpf_liable']) ? '✓ CPF' : '') : '<input type="checkbox" name="allow_cpf['.$row_id.']" value="1"'.(!empty($a['cpf_liable'])?' checked':'').'> CPF');
	if (!$ro) {
		print ' <a href="#" onclick="deleteRow(\''.$row_id.'\'); return false;">'.img_picto($langs->trans('Delete'), 'delete').'</a>';
	}
	print '</td>';
	print '</tr>';
}
if (!$ro) {
	print '<tr><td colspan="3"><a href="#" onclick="addAllowRow();return false;" class="paddingleft">+ '.$langs->trans('AddAllowance').'</a></td></tr>';
	
	// Datalist for common allowances
	print '<datalist id="allowance_list">';
	foreach ($commonAllowances as $ca) {
		print '<option value="'.dol_escape_htmltag($ca).'">';
	}
	print '</datalist>';
}
print '</table></div>';

// Submit
print '<div class="tabsAction">';
if ($canEdit) {
	if ($ro) {
		print '<a href="employee_card.php?fk_user='.$fkUser.'&action=edit" class="butAction">'.$langs->trans('Modify').'</a>';
	} else {
		print '<input type="submit" value="'.$langs->trans('Save').'" class="butAction">';
		print '<a href="employee_card.php?fk_user='.$fkUser.'" class="butActionRefused">'.$langs->trans('Cancel').'</a>';
	}
}
print '<a href="employee_list.php" class="butActionRefused">'.$langs->trans('Back').'</a>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
?>
<script>
function autoSelectShg() {
    const raceSelect = document.getElementsByName('race')[0];
    const muslimCheck = document.getElementsByName('is_muslim')[0];
    if (!raceSelect) return;

    const race = raceSelect.value.toLowerCase();
    const isMuslim = muslimCheck ? muslimCheck.checked : false;
    
    const shgs = {
        'cdac': document.getElementsByName('shg_opt_out_cdac')[0],
        'mbmf': document.getElementsByName('shg_opt_out_mbmf')[0],
        'sinda': document.getElementsByName('shg_opt_out_sinda')[0],
        'ecf': document.getElementsByName('shg_opt_out_ecf')[0]
    };
    
    const getRow = (k) => document.getElementById('tr_shg_' + k);

    // Default: Check all (Opt-out) and hide rows
    for (let k in shgs) {
        if (shgs[k]) shgs[k].checked = true;
        let row = getRow(k);
        if (row) row.style.display = 'none';
    }
    
    // Uncheck (Make active) and show based on race/religion
    if (isMuslim) {
        if (shgs['mbmf']) shgs['mbmf'].checked = false;
        if (getRow('mbmf')) getRow('mbmf').style.display = '';
    } else if (race === 'chinese') {
        if (shgs['cdac']) shgs['cdac'].checked = false;
        if (getRow('cdac')) getRow('cdac').style.display = '';
    } else if (race === 'malay') {
        if (shgs['mbmf']) shgs['mbmf'].checked = false;
        if (getRow('mbmf')) getRow('mbmf').style.display = '';
    } else if (race === 'indian') {
        if (shgs['sinda']) shgs['sinda'].checked = false;
        if (getRow('sinda')) getRow('sinda').style.display = '';
    } else if (race === 'eurasian') {
        if (shgs['ecf']) shgs['ecf'].checked = false;
        if (getRow('ecf')) getRow('ecf').style.display = '';
    }
}

// Re-evaluating: The user wants SHG to be "selected".
// Let's add a JS helper that highlights or suggests.
document.addEventListener('DOMContentLoaded', function() {
    const raceSelect = document.getElementsByName('race')[0];
    const muslimCheck = document.getElementsByName('is_muslim')[0];
    
    if (raceSelect) {
        raceSelect.addEventListener('change', autoSelectShg);
    }
    if (muslimCheck) {
        muslimCheck.addEventListener('change', autoSelectShg);
    }

    // Auto-calculate Basic Salary (SGD)
    const basicInput = document.getElementsByName('basic_salary')[0];
    const contractInput = document.getElementsByName('contract_salary')[0];
    const rateInput = document.getElementsByName('exchange_rate')[0];
    const currSelect = document.getElementsByName('contract_currency')[0];

    function updateCurrSuffix() {
        var suf = document.querySelector('.ms_ccy_suffix');
        if (suf && currSelect) suf.textContent = ' ' + currSelect.value;
    }

    function updateBasic() {
        if (!basicInput || !contractInput || !rateInput) return;
        const contract = parseFloat(contractInput.value.replace(/[^0-9.]/g, '')) || 0;
        const rate = parseFloat(rateInput.value.replace(/[^0-9.]/g, '')) || 0;
        updateCurrSuffix();
        if (currSelect && currSelect.value === 'SGD') {
            rateInput.value = '1.0000';
            basicInput.value = contractInput.value;
        } else {
            basicInput.value = (rate > 0 ? (contract / rate) : 0).toFixed(2);
        }
    }

    if (contractInput) contractInput.addEventListener('input', updateBasic);
    if (rateInput) rateInput.addEventListener('input', updateBasic);
    if (currSelect) currSelect.addEventListener('change', updateBasic);
    updateCurrSuffix();

    // Apply schedule preset to weekly schedule radios
    var presetSelect = document.getElementById('schedule_preset_select');
    if (presetSelect) {
        presetSelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            if (!opt || !opt.value) return;
            var raw = opt.getAttribute('data-schedule');
            if (!raw) return;
            var pairs = raw.split(';');
            for (var i = 0; i < pairs.length; i++) {
                var part = pairs[i].split(':');
                if (part.length === 2) {
                    var day = part[0].trim();
                    var val = part[1].trim();
                    var radio = document.querySelector('input[name="schedule[' + day + ']"][value="' + val + '"]');
                    if (radio) radio.checked = true;
                }
            }
        });
    }
});

function addAllowRow() {
    var footerRow = document.querySelector('a[onclick*="addAllowRow"]').parentNode.parentNode;
    var rowId = 'row_' + Date.now();
    var row = document.createElement('tr');
    row.className = 'allowance-row';
    row.id = rowId;
    row.innerHTML = '<td>' +
        '<input list="allowance_list" name="allow_label['+rowId+']" class="flat" style="width:250px" placeholder="<?php echo dol_escape_js($langs->trans('AllowanceLabel')); ?>">' +
        '</td>' +
        '<td class="right"><input type="number" name="allow_amount['+rowId+']" value="0" step="0.01" class="flat width100 right"></td>' +
        '<td><input type="checkbox" name="allow_cpf['+rowId+']" value="1"> CPF ' +
        ' <a href="#" onclick="deleteRow(\''+rowId+'\'); return false;"><?php echo dol_escape_js(img_picto($langs->trans('Delete'), 'delete')); ?></a></td>';
    footerRow.parentNode.insertBefore(row, footerRow);
}
function deleteRow(rowId) {
    var row = document.getElementById(rowId);
    if (row) row.parentNode.removeChild(row);
}
</script>
<?php
