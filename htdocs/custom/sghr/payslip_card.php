<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        payslip_card.php
 * \ingroup sghr
 * \brief       Single employee payslip create / view / edit
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))      { $res = @include '../main.inc.php'; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include '../../../main.inc.php'; }
if (!$res) { die('Cannot load Dolibarr main.inc.php'); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('sghr/class/payrollcalc.class.php');
dol_include_once('sghr/class/employee.class.php');
dol_include_once('sghr/class/payrollrecord.class.php');
dol_include_once('sghr/lib/sghr.lib.php');
if (!isModEnabled('sghr')) accessforbidden();
$langs->loadLangs(array('sghr@sghr'));

// Hooks
$hookmanager->initHooks(array('sghrpayslipcard'));

$id     = GETPOST('id', 'int');
$fkUser = GETPOST('fk_user', 'int');
$action = GETPOST('action', 'aZ');

// Load payroll line or init
$payLine = new SghrRecord($db);
if ($id > 0) {
	$payLine->fetch($id);
	$fkUser = $payLine->fk_user;
}

$isSelf = ($fkUser > 0 && $fkUser == $user->id);
$canView = $user->admin 
		|| $user->hasRight('sghr', 'payroll', 'approve') 
		|| $user->hasRight('sghr', 'payroll', 'read')
		|| ($isSelf && ($user->hasRight('sghr', 'employee', 'self_write') || $user->hasRight('sghr', 'payroll', 'read')));

if (!$canView) accessforbidden();

// Load employee
$emp = new SghrEmployee($db);
$empLoaded = ($fkUser > 0) ? $emp->fetchByUser($fkUser) : 0;

// Load user name
$empUser = new User($db);
if ($fkUser > 0) $empUser->fetch($fkUser);

// ── Period selector: resolve search_year/search_month to id or pay_year/pay_month ─
$searchYear  = GETPOST('search_year', 'int');
$searchMonth = GETPOST('search_month', 'int');
if ($fkUser > 0 && $searchYear > 0 && $searchMonth >= 1 && $searchMonth <= 12) {
	$sqlPeriod = "SELECT pl.rowid FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
	$sqlPeriod .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
	$sqlPeriod .= " WHERE pl.fk_user = ".(int)$fkUser." AND p.pay_year = ".(int)$searchYear." AND p.pay_month = ".(int)$searchMonth;
	$sqlPeriod .= " AND pl.entity = ".(int)$conf->entity;
	$resPeriod = $db->query($sqlPeriod);
	if ($resPeriod && $db->num_rows($resPeriod) > 0) {
		$row = $db->fetch_object($resPeriod);
		header('Location: payslip_card.php?id='.(int)$row->rowid);
		exit;
	}
	// No payslip for this period: show create form for that period
	header('Location: payslip_card.php?fk_user='.(int)$fkUser.'&pay_year='.(int)$searchYear.'&pay_month='.(int)$searchMonth);
	exit;
}

// ── ACTIONS ──────────────────────────────────────────────────────────────────

// Tamper guard: recompute is only allowed on draft lines; approved/paid/submitted
// payslips must be unapproved (approve right) before they can be changed again.
if ($action === 'compute' && $id > 0 && in_array($payLine->status, array('approved', 'paid', 'submitted'))) {
	setEventMessages($langs->trans('ErrorPayslipNotDraft'), null, 'errors');
	$action = '';
}

if ($action === 'compute' && ($user->admin || $user->hasRight('sghr', 'payroll', 'create'))) {
	// Gather inputs from POST
	$payYear  = GETPOST('pay_year', 'int');
	$payMonth = GETPOST('pay_month', 'int');
	$workDaysInput_raw = GETPOST('work_days', 'alpha');
	$workDaysInput = ($workDaysInput_raw !== '' && $workDaysInput_raw !== null) ? (float) price2num($workDaysInput_raw) : 0;
	$refreshWD = GETPOST('refresh_work_days', 'int');
	$uplDaysInput = GETPOST('upl_days', 'alpha');
	
	// If refresh requested or work_days not in POST, calculate it
	$totalPossibleDays = sgpayroll_working_days($payYear, $payMonth, $emp->workdays_per_month, $emp->weekly_schedule);
	$actualWorkedDays  = sgpayroll_worked_days_in_month($payYear, $payMonth, $emp);
	// UPL base = working days excluding PH (公假不算无薪假)
	$scheduleForUpl = ($emp->weekly_schedule !== '' && $emp->weekly_schedule !== null) ? $emp->weekly_schedule : '';
	$uplBaseDays = SghrCalc::countWorkingDays($payYear, $payMonth, $scheduleForUpl, true);
	if ($uplBaseDays <= 0) {
		$uplBaseDays = SghrCalc::countWorkingDays($payYear, $payMonth, 'Mon,Tue,Wed,Thu,Fri', true);
	}
	if ($uplBaseDays <= 0) {
		$uplBaseDays = $totalPossibleDays;
	}
	if ($refreshWD || empty($workDaysInput)) {
		$workDays = $totalPossibleDays;
		$autoUpl  = max(0, $uplBaseDays - $actualWorkedDays);
	} else {
		$workDays = $workDaysInput;
		$autoUpl  = 0;
	}
	
	$uplDaysInput = GETPOST('upl_days', 'alpha');
	if ($refreshWD) {
		$uplDays = $autoUpl;
	} elseif ($uplDaysInput !== '' && $uplDaysInput !== null) {
		$uplDays = (float) price2num($uplDaysInput);
	} else {
		$uplDays = (float)$payLine->upl_days;
	}
	
	$payDate  = sprintf('%04d-%02d-28', $payYear, $payMonth);

	// Parse allowances table
	$allowances = array();
	$aLabels    = GETPOST('allow_label', 'array:restricthtml');
	$aAmounts   = GETPOST('allow_amount', 'array:float');
	$aCpf       = GETPOST('allow_cpf', 'array:int');
	foreach ((array)$aLabels as $i => $lab) {
		if (!empty($lab) && isset($aAmounts[$i]) && $aAmounts[$i] != 0) {
			$allowances[] = array(
				'label'     => $lab,
				'amount'    => (float)$aAmounts[$i],
				'cpf_liable'=> !empty($aCpf[$i]),
			);
		}
	}

	// Use price2num for amounts so locale (comma/dot) and empty string are handled correctly
	$basicRaw    = GETPOST('basic_salary', 'alpha');
	$bonusRaw    = GETPOST('bonus', 'alpha');
	$commissionRaw = GETPOST('commission', 'alpha');
	$claimsRaw   = GETPOST('claims_total', 'alpha');
	$inputs = array(
		'pay_date'         => $payDate,
		// Allow manual override of basic salary when payslip is still in draft
		'basic_salary'     => (float) price2num($basicRaw !== '' && $basicRaw !== null ? $basicRaw : 0),
		'work_days'        => (float)$workDays,
		'upl_days'         => (float)$uplDays,
		'bonus'            => (float) price2num($bonusRaw !== '' && $bonusRaw !== null ? $bonusRaw : 0),
		'commission'      => (float) price2num($commissionRaw !== '' && $commissionRaw !== null ? $commissionRaw : 0),
		// MOM OT classification (WD 1.5x / REST 1.5x / PH 2.0x)
		'ot_hours_wd'      => (float)GETPOST('ot_hours_wd',   'float'),
		'ot_hours_rest'    => (float)GETPOST('ot_hours_rest',  'float'),
		'ot_hours_ph'      => (float)GETPOST('ot_hours_ph',    'float'),
		// Legacy ot_hours = sum of all types (for computePayrollLine compatibility)
		'ot_hours'         => ((float)GETPOST('ot_hours_wd', 'float') + (float)GETPOST('ot_hours_rest', 'float') + (float)GETPOST('ot_hours_ph', 'float'))
		                      ?: (float)GETPOST('ot_hours', 'float'),
		'ot_multiplier'    => (float)GETPOST('ot_multiplier', 'float') ?: 1.5,
		'al_days_encash'   => (float) price2num(GETPOST('al_days_encash', 'alpha')),
		'al_encashment'    => (float) price2num(GETPOST('al_encashment', 'alpha')),
		'other_aw'         => (float) price2num(GETPOST('other_aw', 'alpha')),
		'advance_recovery' => (float) price2num(GETPOST('salary_advance_recovery', 'alpha')),
		'other_deductions' => (float) price2num(GETPOST('other_deductions', 'alpha')),
		'claims_total'     => (float) price2num($claimsRaw !== '' && $claimsRaw !== null ? $claimsRaw : 0),
		'bik_value'        => (float) price2num(GETPOST('bik_value', 'alpha')),
		'allowances'       => $allowances,
		'ow_contrib_ytd'   => (float) price2num(GETPOST('ow_contrib_ytd', 'alpha')),
		// For robustness, construct timestamp from parts
		'payment_date'     => dol_mktime(12, 0, 0, (int)GETPOST('payment_datemonth', 'int'), (int)GETPOST('payment_dateday', 'int'), (int)GETPOST('payment_dateyear', 'int')),
		'exchange_rate'    => (float) price2num(GETPOST('exchange_rate', 'alpha')) ?: (float)($emp->exchange_rate ?? 1.0),
		'note'             => GETPOST('note', 'restricthtml'),
	);

	// New payslip, or draft + refresh work days: set payment date to default (e.g. last day of pay month)
	$applyDefaultPaymentDate = ($id <= 0) || ($payLine->status === 'draft' && $refreshWD);
	if ($applyDefaultPaymentDate && $payYear > 0 && $payMonth >= 1 && $payMonth <= 12) {
		$defaultDay = (int) getDolGlobalString('SGHR_DEFAULT_PAYMENT_DAY');
		if ($defaultDay >= 31 || $defaultDay <= 0) {
			$lastDay = (int) date('t', mktime(0, 0, 0, $payMonth, 1, $payYear));
			$inputs['payment_date'] = dol_mktime(12, 0, 0, $payMonth, $lastDay, $payYear);
		} else {
			if ($defaultDay <= 10) {
				$nextM = ($payMonth == 12) ? 1 : $payMonth + 1;
				$nextY = ($payMonth == 12) ? $payYear + 1 : $payYear;
				$inputs['payment_date'] = dol_mktime(12, 0, 0, $nextM, $defaultDay, $nextY);
			} else {
				$inputs['payment_date'] = dol_mktime(12, 0, 0, $payMonth, $defaultDay, $payYear);
			}
		}
	}

	// AL encashment: validate against HRM Annual Leave balance (must have sufficient days)
	$alDaysToEncash = (float)($inputs['al_days_encash'] ?? 0);
	$computeAlValid = true;
	$alBalanceHRMCompute = ($fkUser > 0) ? sghr_get_al_balance_from_hrm($db, $fkUser) : 0.0;
	if ($alDaysToEncash > 0) {
		if ($alDaysToEncash > $alBalanceHRMCompute) {
			setEventMessages($langs->trans('AlEncashmentInsufficientBalance', $alDaysToEncash, $alBalanceHRMCompute), null, 'errors');
			$computeAlValid = false;
			// Keep user's entered days for re-display: set payLine so form shows requested days
			$payLine->al_encashment = SghrCalc::calculateALEncashment((float)($payLine->basic_salary ?? $emp->basic_salary ?? 0), (float)$workDays, $alDaysToEncash);
		}
	}

	if ($computeAlValid) {
	dol_syslog('sgpayroll payslip_card: starting compute fk_user='.$fkUser.' bonus='.($inputs['bonus'] ?? 0).' aw_total_input='.(($inputs['bonus'] ?? 0) + ($inputs['commission'] ?? 0) + ($inputs['al_encashment'] ?? 0) + ($inputs['other_aw'] ?? 0)), LOG_INFO);
	$result = SghrCalc::computePayrollLine($db, $emp, $inputs);
	dol_syslog('sgpayroll payslip_card: computed payslip fk_user='.$fkUser.' gross='.($result['gross_salary'] ?? 0), LOG_INFO);

	// Old AL encashment days (for HRM balance delta when updating existing payslip)
	$oldAlDays = 0.0;
	if ($id > 0 && (float)($payLine->al_encashment ?? 0) > 0) {
		$wd = (float)$workDays;
		$basic = (float)($payLine->basic_salary ?? 0);
		if ($wd > 0 && $basic > 0) $oldAlDays = round((float)$payLine->al_encashment / ($basic / $wd), 2);
	}

	// Persist
	$payLine->fk_user       = $fkUser;
	$payLine->pay_year      = $payYear;
	$payLine->pay_month     = $payMonth;
	$payLine->status        = 'draft';
	foreach ($result as $k => $v) {
		if (!is_array($v)) $payLine->$k = $v;  // skip fx_source string arrays
	}
	$payLine->work_days = $workDays;
	$payLine->payment_date = $inputs['payment_date'] > 0 ? $db->idate($inputs['payment_date']) : null;
	$payLine->note = $inputs['note'];

	$db->begin();
	dol_syslog('sgpayroll payslip_card: saving payslip line', LOG_INFO);
	$newId = $payLine->savePayslipLine($user);
	if ($newId > 0) {
		// Auto-save default payment day if not set
		if ($inputs['payment_date'] > 0) {
			$defaultDay = (string)getDolGlobalString('SGHR_DEFAULT_PAYMENT_DAY');
			if ($defaultDay === '') {
				$day = (int)dol_print_date($inputs['payment_date'], '%d');
				dolibarr_set_const($db, 'SGHR_DEFAULT_PAYMENT_DAY', $day, 'chaine', 0, '', $conf->entity);
			}
		}

		$db->commit();
		// Deduct AL encashment days from HRM Annual Leave balance
		$newAlDays = (float)($inputs['al_days_encash'] ?? 0);
		$alDelta = $newAlDays - $oldAlDays;
		if ($alDelta != 0) {
			$deductRes = sghr_deduct_al_balance_hrm($db, $fkUser, $alDelta);
			if ($deductRes < 0) {
				setEventMessages($langs->trans('AlEncashmentBalanceUpdateFailed'), null, 'warnings');
			}
		}
		// Show AW ceiling warning if bonus was capped
		if (!empty($result['aw_capped'])) {
			setEventMessages($langs->trans('AwCeilingCapWarning'), null, 'warnings');
		}
		setEventMessages($langs->trans('PayslipComputed'), null, 'mesgs');
		// Confirm key amounts so user can verify bonus/AW affected the result
		if ((float)($result['bonus'] ?? 0) != 0 || (float)($result['aw_total'] ?? 0) != 0) {
			setEventMessages(
				$langs->trans('Bonus').': '.price($result['bonus'] ?? 0).' | '
				.$langs->trans('GrossSalary').': '.price($result['gross_salary'] ?? 0).' | '
				.$langs->trans('NetPay').': '.price($result['net_pay'] ?? 0),
				null,
				'mesgs'
			);
		}
		header('Location: payslip_card.php?id='.$newId);
		exit;
	} else {
		$db->rollback();
		dol_syslog('sgpayroll payslip_card: save failed '.$payLine->error, LOG_ERR);
		setEventMessages($payLine->error ?: $langs->trans('Error'), null, 'errors');
	}
	} // end if ($computeAlValid)
}

if ($action === 'approve' && $user->hasRight('sghr', 'payroll', 'approve') && $id > 0) {
	$db->begin();
	$payLine->status = 'approved';
	$res = $payLine->saveStatus($user);
	if ($res >= 0) {
		$db->commit();
		setEventMessages($langs->trans('PayslipApproved'), null, 'mesgs');

		// Post-commit: never abort redirect. PDF vs email isolated so failures are not blamed together.
		// Generate PDF before email so SGPayroll_Send can attach Payslip_MM_YYYY.pdf on first approve.
		try {
			dol_include_once('sghr/core/modules/sghr/pdf/pdf_payslip_sgpayroll.class.php');
			$pdfGen = new pdf_payslip_sgpayroll($db);
			$payYear  = $payLine->pay_year;
			$payMonth = str_pad((string)(int)$payLine->pay_month, 2, '0', STR_PAD_LEFT);
			$entity   = (int)($conf->entity ?? 1);
			$destDir  = DOL_DATA_ROOT.'/sghr/'.$entity.'/'.(int)$payLine->fk_user.'/';
			if (!is_dir($destDir)) dol_mkdir($destDir);
			$destFile = $destDir.'Payslip_'.$payMonth.'_'.$payYear.'.pdf';
			$lineIdPdf = (int)($payLine->id ?: $payLine->rowid ?: $id);
			$pdfGen->generate($lineIdPdf, 'save', $destFile);
		} catch (Throwable $e) {
			dol_syslog('sgpayroll payslip_card approve pdf: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine(), LOG_ERR);
			setEventMessages($langs->trans('PayslipApprovePdfFailed'), null, 'warnings');
		}

		if (getDolGlobalInt('SGHR_SEND_PAYSLIP_EMAIL') == 1) {
			try {
				$today_jdate = dol_mktime(0, 0, 0, dol_print_date(dol_now(), '%m'), dol_print_date(dol_now(), '%d'), dol_print_date(dol_now(), '%Y'));
				$payment_jdate = $payLine->payment_date ? $db->jdate($payLine->payment_date) : 0;
				if ($payment_jdate && $today_jdate < $payment_jdate) {
					setEventMessages($langs->trans('PayslipEmailDelayed'), null, 'warnings');
				} else {
					$emailResult = sgpayroll_send_payslip_email($db, $payLine, $empUser, $langs, $conf);
					if ($emailResult > 0) {
						setEventMessages($langs->trans('PayslipEmailSent', $empUser->email), null, 'mesgs');
					} elseif ($emailResult < 0) {
						setEventMessages($langs->trans('PayslipEmailFailed'), null, 'warnings');
					}
				}
			} catch (Throwable $e) {
				dol_syslog('sgpayroll payslip_card approve email: '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine(), LOG_ERR);
				setEventMessages($langs->trans('PayslipApproveEmailTechnical'), null, 'warnings');
			}
		}
	} else {
		$db->rollback();
		setEventMessages('Approval failed: '.$payLine->error, null, 'errors');
	}
	header('Location: payslip_card.php?id='.$id);
	exit;
}

// ── Manual re-send email ──────────────────────────────────────────────────────
if ($action === 'send_email' && $id > 0 && $payLine->status === 'approved' &&
	($user->admin || $user->hasRight('sghr', 'payroll', 'approve'))) {
	
	$today_jdate = dol_mktime(0, 0, 0, dol_print_date(dol_now(), '%m'), dol_print_date(dol_now(), '%d'), dol_print_date(dol_now(), '%Y'));
	$payment_jdate = $payLine->payment_date ? $db->jdate($payLine->payment_date) : 0;
	if ($payment_jdate && $today_jdate < $payment_jdate) {
		setEventMessages($langs->trans('PayslipEmailDelayed'), null, 'errors');
	} else {
		$emailResult = sgpayroll_send_payslip_email($db, $payLine, $empUser, $langs, $conf);
		if ($emailResult > 0) {
			setEventMessages($langs->trans('PayslipEmailSent', $empUser->email), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('PayslipEmailFailed'), null, 'errors');
		}
	}
	header('Location: payslip_card.php?id='.$id);
	exit;
}


// Fetch Claims from CORE expensereport table if in draft mode
$readOnly  = ($payLine->status !== 'draft');
if (!$readOnly && $fkUser > 0) {
	$sqlC = "SELECT SUM(total_ttc) as total FROM ".MAIN_DB_PREFIX."expensereport";
	$sqlC .= " WHERE fk_user_author = ".(int)$fkUser;
	$sqlC .= " AND fk_statut = 5"; // Approved (D22: 5=approved, 4=cancelled)
	// Filter by the month of this payroll run
	$payYear  = $payLine->pay_year  ?: (int) dol_print_date(dol_now(), '%Y');
	$payMonth = $payLine->pay_month ?: (int) dol_print_date(dol_now(), '%m');
	$sqlC .= " AND date_debut >= '".$db->idate(dol_get_first_day($payYear, $payMonth))."'";
	$sqlC .= " AND date_debut <= '".$db->idate(dol_get_last_day($payYear, $payMonth))."'";
	$resC = $db->query($sqlC);
	if ($resC) {
		$objC = $db->fetch_object($resC);
		$auto_claims_total = (float)$objC->total;
		// Only pre-fill for brand new records. For existing, user must use refresh or manual edit.
		if ($payLine && $id <= 0 && !isset($_POST['claims_total'])) {
			$payLine->claims_total = $auto_claims_total;
		}
	}

	// Fetch Unpaid Leave (UPL) from CORE holiday table
	$sqlH = "SELECT SUM(nb_open_day) as nb_days FROM ".MAIN_DB_PREFIX."holiday";
	$sqlH .= " WHERE fk_user = ".(int)$fkUser;
	$sqlH .= " AND statut = 3"; // Approved
	$sqlH .= " AND date_debut >= '".$db->idate(dol_get_first_day($payYear, $payMonth))."'";
	$sqlH .= " AND date_debut <= '".$db->idate(dol_get_last_day($payYear, $payMonth))."'";
	// We assume type of leave with code 'UNPAID' or similar exists. 
	// For robustness, we check the type code if possible, or just assume the user categorized it.
	// In Dolibarr, fk_type links to llx_c_holiday_types.
	$sqlH .= " AND fk_type IN (SELECT rowid FROM ".MAIN_DB_PREFIX."c_holiday_types WHERE code IN ('unpaid', 'UPL'))";
	
	$resH = $db->query($sqlH);
	if ($resH) {
		$objH = $db->fetch_object($resH);
		$auto_upl_days = (float)$objH->nb_days;
		// Only pre-fill for brand new records. For existing, user must use refresh or manual edit.
		if ($payLine && $id <= 0 && !isset($_POST['upl_days'])) {
			$payLine->upl_days = $auto_upl_days;
		}
	}

	// llx_timesheet_per_month is NOT a core Dolibarr table — only present when a third-party
	// timesheet module is installed, so probe for it and skip OT auto-fetch gracefully.
	$resTbl = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX."timesheet_per_month")."'");
	if ($resTbl && $db->num_rows($resTbl) > 0) {
		$sqlT = "SELECT SUM(total_duration) as total_seconds FROM ".MAIN_DB_PREFIX."timesheet_per_month";
		$sqlT .= " WHERE fk_user = ".(int)$fkUser;
		$sqlT .= " AND month = '".$db->escape($payYear . sprintf('%02d', $payMonth))."'";

		$resT = $db->query($sqlT);
		if ($resT) {
			$objT = $db->fetch_object($resT);
			$total_hours = (float)($objT->total_seconds / 3600);
			// If total hours exceed standard hours (e.g., 44h/week * 4.33 weeks = 190.5h), the rest is OT
			// For now, we'll fetch explicitly marked OT if the schema supports it, or just use a placeholder
			if ($payLine && !isset($_POST['ot_hours'])) {
				$workingDays = sgpayroll_worked_days_in_month($payYear, $payMonth, $emp);
				$hoursPerDay = getDolGlobalInt('SGHR_WORKING_HOURS_PER_DAY') ?: 8;
				$standardHours = $workingDays * $hoursPerDay;
				$payLine->ot_hours = max(0, round($total_hours - $standardHours, 2));
			}
		}
	}
}

if ($action === 'unapprove' && $user->hasRight('sghr', 'payroll', 'approve') && $id > 0) {
	$db->begin();
	$payLine->status = 'draft';
	$res = $payLine->saveStatus($user);
	if ($res >= 0) {
		$db->commit();
		setEventMessages($langs->trans('PayslipUnapproved'), null, 'mesgs');
	} else $db->rollback();
	header('Location: payslip_card.php?id='.$id); exit;
}

if ($action === 'delete' && ($user->admin || $user->hasRight('sghr', 'payroll', 'create')) && $id > 0) {
	if ($payLine->status === 'draft') {
		$db->begin();
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE rowid = ".(int)$id;
		if ($db->query($sql)) {
			// Update run header totals
			$db->query(
				"UPDATE ".MAIN_DB_PREFIX."sgpayroll_payroll p SET"
				." total_gross=COALESCE((SELECT SUM(gross_salary) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid), 0),"
				." total_net=COALESCE((SELECT SUM(net_pay) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid), 0),"
				." total_employer_cpf=COALESCE((SELECT SUM(employer_cpf) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid), 0),"
				." total_employee_cpf=COALESCE((SELECT SUM(employee_cpf) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid), 0),"
				." total_sdl=COALESCE((SELECT SUM(sdl_amount) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid), 0)"
				." WHERE p.rowid=".(int)$payLine->fk_payroll
			);
			$db->commit();
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
			header('Location: payslip_list.php'); exit;
		} else {
			$db->rollback();
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		setEventMessages('You must revert the payslip to Draft before deleting it.', null, 'warnings');
	}
}

// ── PAGE OUTPUT ───────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('Payslip'), '');

$empName = dol_escape_htmltag(sgpayroll_format_employee_name($empUser->firstname, $empUser->lastname));
print load_fiche_titre($langs->trans('Payslip').' — '.$empName, '', 'sghr@sghr');

if ($fkUser > 0) {
	$head = sghr_employee_prepare_head($fkUser);
	dol_fiche_head($head, 'payslips', '', 0, '');
}

// Status bar
if ($id > 0) {
	print '<div class="statusline">';
	print '<span class="badge '.sghr_status_class($payLine->status).'">'.ucfirst($payLine->status).'</span>';
	print '</div><br>';
}

// ── Period selector (same style as Payrun list): switch payslip by year/month ───
if ($fkUser > 0) {
	$now = dol_now();
	$selYear  = $payLine->pay_year  ?: (GETPOST('pay_year', 'int') ?: (int) dol_print_date($now, '%Y'));
	$selMonth = $payLine->pay_month ?: (GETPOST('pay_month', 'int') ?: (int) dol_print_date($now, '%m'));
	$prevMonth = $selMonth <= 1 ? 12 : $selMonth - 1;
	$prevYear  = $selMonth <= 1 ? $selYear - 1 : $selYear;
	$nextMonth = $selMonth >= 12 ? 1 : $selMonth + 1;
	$nextYear  = $selMonth >= 12 ? $selYear + 1 : $selYear;
	$monthLabels = array(1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December');
	print '<div class="filter_field" style="margin-bottom:12px;">';
	print '<form method="GET" action="payslip_card.php" style="display:inline-flex; align-items:center; flex-wrap:wrap; gap:8px;">';
	print '<input type="hidden" name="fk_user" value="'.(int)$fkUser.'">';
	print '<label style="font-weight:600; color:#444;">'.$langs->trans('Period').':</label>';
	print '<div style="display:inline-flex; align-items:center; border:1px solid #ccc; border-radius:4px; overflow:hidden; background:#fff;">';
	print '<a href="payslip_card.php?fk_user='.(int)$fkUser.'&search_year='.(int)$prevYear.'&search_month='.(int)$prevMonth.'" title="'.$langs->trans('Previous').'" style="background:#f4f4f4; border:none; border-right:1px solid #ccc; padding:6px 12px; color:#555; text-decoration:none;"><i class="fas fa-chevron-left"></i></a>';
	print '<select name="search_month" class="flat" style="border:none; outline:none; color:#333; min-width:95px; padding:6px; font-weight:bold; cursor:pointer;" onchange="this.form.submit();">';
	print '<option value="0"></option>';
	foreach ($monthLabels as $m => $label) {
		print '<option value="'.$m.'"'.((int)$selMonth === $m ? ' selected' : '').'>'.$langs->trans($label).'</option>';
	}
	print '</select>';
	print '<select name="search_year" class="flat" style="border:none; outline:none; color:#333; min-width:75px; padding:6px; font-weight:bold; cursor:pointer; border-left:1px solid #eee;" onchange="this.form.submit();">';
	for ($y = 2020; $y <= 2050; $y++) print '<option value="'.$y.'"'.((int)$selYear === $y ? ' selected' : '').'>'.$y.'</option>';
	print '</select>';
	print '<a href="payslip_card.php?fk_user='.(int)$fkUser.'&search_year='.(int)$nextYear.'&search_month='.(int)$nextMonth.'" title="'.$langs->trans('Next').'" style="background:#f4f4f4; border:none; border-left:1px solid #ccc; padding:6px 12px; color:#555; text-decoration:none;"><i class="fas fa-chevron-right"></i></a>';
	print '</div>';
	print '</form></div>';
}

// ── FORM ─────────────────────────────────────────────────────────────────────
$readOnly = (!$user->admin && !$user->hasRight('sghr', 'payroll', 'create')) || ($payLine->status === 'approved' || $payLine->status === 'paid');
$formAction = $readOnly ? '' : 'payslip_card.php';
// Unify display: amounts as price() (e.g. 0.00); hours as "X.XX h" so "0" is not confused with OT hours
$fmtAmount = function($v) { return price((float)$v); };
$fmtHours  = function($v) { return number_format((float)$v, 1, '.', '').' h'; };

print '<form method="POST" action="'.$formAction.'">';
print '<input type="hidden" name="action" value="compute">';
print '<input type="hidden" name="fk_user" value="'.$fkUser.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($id > 0) print '<input type="hidden" name="id" value="'.$id.'">';

// ──── Pay Period ─────────────────────────────────────────────────────────────
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="border tableforfield centpercent">';
print '<colgroup><col style="width:72%"><col style="width:28%"></colgroup>';
print '<colgroup><col style="width:65%"><col style="width:35%"></colgroup>';
print '<tr><td class="titlefield">'.$langs->trans('Employee').'</td><td>'.$empName.'</td></tr>';
print '<tr><td>'.$langs->trans('Citizenship').'</td><td>'.dol_escape_htmltag($emp->citizenship).'</td></tr>';
print '<tr><td>'.$langs->trans('EmploymentType').'</td><td>'.dol_escape_htmltag($emp->employment_type).'</td></tr>';

$now = dol_now();
// Respect URL parameters for new payslips
$payYearParam  = GETPOST('pay_year', 'int');
$payMonthParam = GETPOST('pay_month', 'int');

$yr  = $payLine->pay_year  ?: ($payYearParam ?: (int) dol_print_date($now, '%Y'));
$mo  = $payLine->pay_month ?: ($payMonthParam ?: (int) dol_print_date($now, '%m'));

// Ensure the object knows its period for dependent logic
if ($id <= 0) {
	$payLine->pay_year = $yr;
	$payLine->pay_month = $mo;
}

// Fetch leave records for this pay month (for "Leave in this month" block)
$leaveRecordsMonth = array();
$phDaysInMonth = 0;
if ($fkUser > 0 && $yr > 0 && $mo >= 1 && $mo <= 12) {
	$startDate = sprintf('%04d-%02d-01', $yr, $mo);
	$endDate   = date('Y-m-t', strtotime($startDate));
	$phList = SghrCalc::getSingaporePublicHolidays($yr);
	$schedule = ($emp->weekly_schedule !== '' && $emp->weekly_schedule !== null) ? $emp->weekly_schedule : 'Mon,Tue,Wed,Thu,Fri';
	$workDaysExclPh = (float) SghrCalc::countWorkingDays($yr, $mo, $schedule, true);
	$workDaysInclPh = (float) SghrCalc::countWorkingDays($yr, $mo, $schedule, false);
	$phDaysInMonth = max(0, $workDaysInclPh - $workDaysExclPh);
	$sqlLeave  = "SELECT h.date_debut, h.date_fin, h.halfday, h.statut, t.code, t.label";
	$sqlLeave .= " FROM ".MAIN_DB_PREFIX."holiday h";
	$sqlLeave .= " INNER JOIN ".MAIN_DB_PREFIX."c_holiday_types t ON t.rowid = h.fk_type";
	$sqlLeave .= " WHERE h.fk_user = ".(int)$fkUser;
	$sqlLeave .= " AND h.entity = ".(int)$conf->entity;
	$sqlLeave .= " AND (";
	$sqlLeave .= "      (h.date_debut >= '".$db->idate(strtotime($startDate))."' AND h.date_debut <= '".$db->idate(strtotime($endDate))."')";
	$sqlLeave .= "   OR (h.date_fin >= '".$db->idate(strtotime($startDate))."' AND h.date_fin <= '".$db->idate(strtotime($endDate))."')";
	$sqlLeave .= "   OR (h.date_debut < '".$db->idate(strtotime($startDate))."' AND h.date_fin > '".$db->idate(strtotime($endDate))."')";
	$sqlLeave .= " )";
	$sqlLeave .= " ORDER BY h.date_debut, t.code";
	$resLeave = $db->query($sqlLeave);
	if ($resLeave) {
		while ($obj = $db->fetch_object($resLeave)) {
			$dStart = is_numeric($obj->date_debut) ? (int)$obj->date_debut : strtotime($obj->date_debut);
			$dEnd   = is_numeric($obj->date_fin)   ? (int)$obj->date_fin   : strtotime($obj->date_fin);
			$monthStart = strtotime($startDate);
			$monthEnd   = strtotime($endDate.' 23:59:59');
			$sliceStart = max($dStart, $monthStart);
			$sliceEnd   = min($dEnd, $monthEnd);
			$daysInMonth = 0;
			$workingDaysInMonth = 0;
			if ($sliceStart <= $sliceEnd) {
				$daysInMonth = (($sliceEnd - $sliceStart) / 86400) + 1;
				$sliceStartStr = date('Y-m-d', $sliceStart);
				$sliceEndStr   = date('Y-m-d', $sliceEnd);
				if (!empty($obj->halfday) && $dStart == $dEnd) {
					$daysInMonth = 0.5;
					$dayStr = date('Y-m-d', $dStart);
					$workingDaysInMonth = in_array($dayStr, $phList, true) ? 0 : 0.5;
				} else {
					$workingDaysInMonth = SghrCalc::countWorkingDaysInRange($sliceStartStr, $sliceEndStr, $schedule, $phList);
				}
			}
			$typeLabel = ($obj->code && $langs->trans($obj->code) != $obj->code) ? $langs->trans($obj->code) : $obj->label;
			$leaveRecordsMonth[] = array(
				'type'   => $typeLabel,
				'date_from' => $dStart,
				'date_to'   => $dEnd,
				'days'   => (float)$daysInMonth,
				'working_days' => (float)$workingDaysInMonth,
				'statut' => (int)$obj->statut
			);
		}
	}
}

// Working Days in Month:
// 1) If we just computed in this request, keep $workDays from POST/compute.
// 2) Otherwise, prefer the value saved on the payslip line (so re‑compute uses same denominator).
// 3) Fallback to schedule-based default for brand new payslips.
if (!isset($workDays)) {
	if (!empty($payLine->work_days)) {
		$workDays = (float)$payLine->work_days;
	} else {
		$workDays = sgpayroll_working_days($yr, $mo, $emp->workdays_per_month, $emp->weekly_schedule);
	}
}

print '<tr><td>'.$langs->trans('Year').'</td><td>';
if ($readOnly) print dol_escape_htmltag($yr);
else print '<input type="number" name="pay_year" value="'.$yr.'" min="2020" max="2050" class="flat width75">';
print '</td></tr>';
print '<tr><td>'.$langs->trans('Month').'</td><td>';
if ($readOnly) print dol_escape_htmltag(dol_print_date(dol_mktime(0, 0, 0, $mo, 1, $yr), '%B'));
else print sghr_select_month($mo, 'pay_month');
print '</td></tr>';
print '<tr><td>'.$langs->trans('WorkDaysInMonth').'</td><td>';
if ($readOnly) print dol_escape_htmltag($workDays);
else {
	print '<input type="number" name="work_days" value="'.$workDays.'" min="1" max="31" class="flat width50" id="work_days_input">';
	print ' <button type="submit" name="refresh_work_days" value="1" class="butAction" style="padding: 2px 5px" title="'.$langs->trans('RefreshDaysFromSchedule').'">'.img_picto('', 'refresh').'</button>';
}
print '</td></tr>';

// ── Payment bank & date ───────────────────────────────────────────────────
$bankDisplay = '—';
if (!empty($emp->bank_name)) {
	$bankDisplay = dol_escape_htmltag($emp->bank_name);
	if (!empty($emp->bank_account) && strlen((string)$emp->bank_account) >= 4) {
		$bankDisplay .= ' ****'.substr($emp->bank_account, -4);
	}
}
// Payment Date: in draft (or new) always show default from pay period; only approved/paid use stored date
$defaultDay = (int) getDolGlobalString('SGHR_DEFAULT_PAYMENT_DAY');
$paymentDateVal = 0;
$useStoredPaymentDate = ($id > 0 && $payLine->status !== 'draft' && !empty($payLine->payment_date));
if ($useStoredPaymentDate) {
	$paymentDateVal = $db->jdate($payLine->payment_date);
}
if ($paymentDateVal <= 0 || $paymentDateVal === '' || $paymentDateVal === false) {
	// New or draft: always compute from current pay period (Year/Month) + Default Payment Day
	$useYr = ($yr > 0 && $yr <= 9999) ? $yr : (int) dol_print_date(dol_now(), '%Y');
	$useMo = ($mo >= 1 && $mo <= 12) ? $mo : (int) dol_print_date(dol_now(), '%m');
	if ($defaultDay >= 31 || $defaultDay <= 0) {
		$lastDay = (int) date('t', mktime(0, 0, 0, $useMo, 1, $useYr));
		$paymentDateVal = dol_mktime(12, 0, 0, $useMo, $lastDay, $useYr);
	} else {
		if ($defaultDay <= 10) {
			$nextM = ($useMo == 12) ? 1 : $useMo + 1;
			$nextY = ($useMo == 12) ? $useYr + 1 : $useYr;
			$paymentDateVal = dol_mktime(12, 0, 0, $nextM, $defaultDay, $nextY);
		} else {
			$paymentDateVal = dol_mktime(12, 0, 0, $useMo, $defaultDay, $useYr);
		}
	}
}
// Ensure we never pass empty to selectDate (it would show "today")
if ($paymentDateVal <= 0 || $paymentDateVal === '' || $paymentDateVal === false) {
	$useYr = (int) dol_print_date(dol_now(), '%Y');
	$useMo = (int) dol_print_date(dol_now(), '%m');
	$lastDay = (int) date('t', mktime(0, 0, 0, $useMo, 1, $useYr));
	$paymentDateVal = dol_mktime(12, 0, 0, $useMo, $lastDay, $useYr);
}

// Optional Debug if user still has issues (uncomment if needed)
// setEventMessages("Debug: mo=$mo yr=$yr defDay=$defaultDay resVal=".dol_print_date($paymentDateVal, 'day'), null, 'warnings');
print '<tr><td>'.$langs->trans('PaymentBank').'</td><td>'.$bankDisplay.'</td></tr>';
print '<tr><td>'.$langs->trans('PaymentDate').'</td><td>';
if ($readOnly) print dol_print_date($paymentDateVal, 'day');
else {
	$form = new Form($db);
	// selectDate(..., $gm): use 'tzserver' so our computed date (pay period last day) is not shifted by user TZ
	print $form->selectDate($paymentDateVal, 'payment_date', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver');
}
print '</td></tr>';
print '</table></div>';

// ──── Basic & Allowances ─────────────────────────────────────────────────────
print '<div class="fichehalfright">';
print '<table class="border tableforfield centpercent">';
print '<colgroup><col style="width:72%"><col style="width:28%"></colgroup>';
print '<colgroup><col style="width:65%"><col style="width:35%"></colgroup>';
print '<tr class="liste_titre"><td colspan="3">'.$langs->trans('BasicAndAllowances').'</td></tr>';
$currency    = $payLine->contract_currency ?: ($emp->contract_currency ?: 'SGD');
$salary_fc   = $payLine->basic_salary_fc ?: ($emp->contract_salary ?: 0);
$actualRate  = (float)($payLine->exchange_rate ?: ($emp->exchange_rate ?? 1.0));

	if ($currency !== 'SGD') {
		print '<tr><td class="titlefield">'.$langs->trans('ContractSalary').'</td><td colspan="2">';
		if ($readOnly) {
			print $fmtAmount($salary_fc).' '.$currency;
		} else {
			// 合同工资一般在员工资料里维护，这里只读展示，实际用于换算Basic Salary (SGD)
			print $fmtAmount($salary_fc).' '.$currency;
		}
		print '</td></tr>';
	print '<tr><td class="titlefield">'.$langs->trans('ExchangeRate').'</td><td colspan="2">';
	if ($readOnly) print number_format($actualRate, 4);
	else print '<span class="opacitymedium">1 SGD =</span> <input type="number" name="exchange_rate" value="'.$actualRate.'" step="0.0001" class="flat width100 right"><span class="opacitymedium"> '.dol_escape_htmltag($currency).'</span>';
	print '</td></tr>';
		// 对于多币种合同，Basic Salary (SGD) 为本次薪资单的实际基本工资，草稿状态下允许手动调整
		$basicVal = (float)($payLine->basic_salary ?? 0);
		print '<tr><td class="titlefield">'.$langs->trans('BasicSalary').' (SGD)</td><td colspan="2">';
		if ($readOnly) {
			print $fmtAmount($basicVal);
		} else {
			if ($basicVal <= 0) {
				// 首次计算前，使用根据合同工资换算出的basic_salary作为默认值（汇率：1 SGD = rate FCY）
				$basicVal = ($actualRate > 0) ? (float)($salary_fc / $actualRate) : 0.0;
			}
			print '<input type="number" name="basic_salary" value="'.dol_escape_htmltag($basicVal).'" step="0.01" class="flat width150 right" placeholder="0.00">';
		}
		print '</td></tr>';
} else {
		// SGD 合同：薪资单上的 Basic Salary 以本条薪资记录为准，草稿状态下可手动调整
		$basicVal = (float)($payLine->basic_salary ?? $emp->basic_salary ?? 0);
		print '<tr><td class="titlefield">'.$langs->trans('BasicSalary').'</td><td colspan="2">';
		if ($readOnly) {
			print $fmtAmount($basicVal);
		} else {
			print '<input type="number" name="basic_salary" value="'.dol_escape_htmltag($basicVal).'" step="0.01" class="flat width150 right" placeholder="0.00">';
		}
		print '</td></tr>';
}
// “Basic Salary (A)” 之下这块就是 Allowances (B)
print '<tr><td colspan="3"><b>'.$langs->trans('Allowances').' (B)</b></td></tr>';

// Allowance rows (editable)
$allowRows = !empty($payLine->allowances_json) ? json_decode($payLine->allowances_json, true) : array();
if (!is_array($allowRows)) $allowRows = array();
if (empty($allowRows) && !$readOnly) {
	if (!empty($emp->allowances_json)) {
		$allowRows = json_decode($emp->allowances_json, true);
	} else {
		$allowRows = array(array('label'=>'','amount'=>0,'cpf_liable'=>0));
	}
}

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

foreach ($allowRows as $i => $a) {
	$row_id = 'row_'.$i;
	print '<tr class="allowance-row" id="'.$row_id.'">';
	print '<td>';
	if ($readOnly) {
		print dol_escape_htmltag($a['label'] ?? '');
	} else {
		print '<input list="allowance_list" name="allow_label['.$row_id.']" value="'.dol_escape_htmltag($a['label']??'').'" class="flat" style="width:250px" placeholder="'.$langs->trans('AllowanceLabel').'">';
	}
	print '</td>';
	print '<td class="right">'.($readOnly ? $fmtAmount($a['amount']??0) : '<input type="number" name="allow_amount['.$row_id.']" value="'.(float)($a['amount']??0).'" step="0.01" class="flat width100 right" placeholder="0.00">').'</td>';
	print '<td>'.($readOnly ? (!empty($a['cpf_liable']) ? '✓ CPF' : '') : '<input type="checkbox" name="allow_cpf['.$row_id.']" value="1"'.(!empty($a['cpf_liable'])?' checked':'').'> CPF');
	if (!$readOnly) {
		print ' <a href="#" onclick="deleteRow(\''.$row_id.'\'); return false;">'.img_picto($langs->trans('Delete'), 'delete').'</a>';
	}
	print '</td>';
	print '</tr>';
}
if (!$readOnly) {
	print '<tr><td colspan="3"><a href="#" onclick="addAllowRow();return false;" class="paddingleft">+ '.$langs->trans('AddAllowance').'</a></td></tr>';
	
	// Datalist for common allowances
	print '<datalist id="allowance_list">';
	foreach ($commonAllowances as $ca) {
		print '<option value="'.dol_escape_htmltag($ca).'">';
	}
	print '</datalist>';
}
print '</table></div></div>'; // end fichecenter

// ──── Additional Wages & Deductions ──────────────────────────────────────────
print '<div class="fichecenter"><div class="fichehalfleft">';
print '<table class="border tableforfield centpercent">';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('AdditionalWages').'</td></tr>';
$awFields = array(
	'bonus' => $langs->trans('BonusAmount'),
	'commission' => $langs->trans('CommissionAmount'),
	'other_aw' => $langs->trans('OtherAW'),
);
foreach ($awFields as $key => $label) {
	$val = $payLine->$key ?? 0;
	print '<tr><td class="titlefield">'.$label.'</td><td class="right">';
	if ($readOnly) {
		print $fmtAmount($val);
	} else {
		print '<input type="number" name="'.$key.'" value="'.(float)$val.'" step="0.01" class="flat width150 right" placeholder="0.00">';
	}
	print '</td></tr>';
}
// AL Encashment: days from HRM Annual Leave balance; system computes amount (daily rate = basic_salary / work_days)
if (!isset($alBalanceHRM)) $alBalanceHRM = ($fkUser > 0) ? sghr_get_al_balance_from_hrm($db, $fkUser) : 0.0;
$basicForAL = (float)($payLine->basic_salary ?? 0);
$workDaysForAL = (float)($workDays ?? 0);
$alDaysDisplay = '';
if ($workDaysForAL > 0 && $basicForAL > 0 && (float)($payLine->al_encashment ?? 0) > 0) {
	$alDaysDisplay = round((float)$payLine->al_encashment / ($basicForAL / $workDaysForAL), 2);
}
$alEncashComputed = ($workDaysForAL > 0 && $basicForAL > 0 && $alDaysDisplay !== '') ? SghrCalc::calculateALEncashment($basicForAL, $workDaysForAL, (float)$alDaysDisplay) : (float)($payLine->al_encashment ?? 0);
print '<tr><td class="titlefield">'.$langs->trans('AlEncashment').'</td><td class="right">';
if ($readOnly) {
	print $langs->trans('AlDaysToEncash').': '.dol_escape_htmltag($alDaysDisplay !== '' ? $alDaysDisplay : '—').' '.$langs->trans('Days').' &nbsp; = &nbsp; '.$fmtAmount($payLine->al_encashment ?? 0);
} else {
	$alDaysVal = $alDaysDisplay !== '' ? $alDaysDisplay : '';
	$alMaxAttr = ($alBalanceHRM > 0) ? ' max="'.(float)$alBalanceHRM.'"' : '';
	print '<span class="opacitymedium">'.$langs->trans('AlBalanceFromHRM').' '.number_format((float)$alBalanceHRM, 1, '.', '').' '.$langs->trans('Days').'</span><br>';
	print '<input type="number" name="al_days_encash" id="al_days_encash" value="'.dol_escape_htmltag($alDaysVal).'" step="0.5" min="0"'.$alMaxAttr.' class="flat width80 right" placeholder="0"> '.$langs->trans('Days');
	print ' <span id="al_encash_amount_display" class="opacitymedium"> = '.$fmtAmount($alEncashComputed).'</span>';
}
print '</td></tr>';
// OT — MOM 3-Type Classification (WD 1.5x / Rest Day 1.5x / PH 2.0x)
$otHoursWD   = (float)($payLine->ot_hours_wd   ?? $payLine->overtime_hours ?? 0);
$otHoursREST = (float)($payLine->ot_hours_rest ?? 0);
$otHoursPH   = (float)($payLine->ot_hours_ph   ?? 0);
$otResult    = SghrCalc::calculateOvertimeByType($payLine->basic_salary ?? 0, $otHoursWD, $otHoursREST, $otHoursPH);
$totalOtHours = $otResult['total_hours'];
$mom72Warn   = $totalOtHours > 72;

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('OvertimeMOM').'</td></tr>';
print '<tr><td title="'.$langs->trans('OTWDTitle').'"><span style="white-space:nowrap">'.$langs->trans('OTWorkday').' &times;1.5</span></td><td class="right">';
if ($readOnly) {
	print $fmtHours($otHoursWD).' &nbsp;= &nbsp; '.$fmtAmount($otResult['wd_pay']);
} else {
	print '<input type="number" name="ot_hours_wd" value="'.dol_escape_htmltag($otHoursWD).'" step="0.25" min="0" class="flat width80 right" placeholder="0.00"> <span class="sgpayroll-unit">h</span>';
	print ' <span class="opacitymedium">= '.$fmtAmount($otResult['wd_pay']).'</span>';
}
print '</td></tr>';
print '<tr><td title="'.$langs->trans('OTRESTTitle').'"><span style="white-space:nowrap">'.$langs->trans('OTRestDay').' &times;1.5</span></td><td class="right">';
if ($readOnly) {
	print $fmtHours($otHoursREST).' &nbsp;= &nbsp; '.$fmtAmount($otResult['rest_pay']);
} else {
	print '<input type="number" name="ot_hours_rest" value="'.dol_escape_htmltag($otHoursREST).'" step="0.25" min="0" class="flat width80 right" placeholder="0.00"> <span class="sgpayroll-unit">h</span>';
	print ' <span class="opacitymedium">= '.$fmtAmount($otResult['rest_pay']).'</span>';
}
print '</td></tr>';
print '<tr><td title="'.$langs->trans('OTPHTitle').'"><span style="white-space:nowrap">'.$langs->trans('OTPublicHoliday').' &times;2.0</span></td><td class="right">';
if ($readOnly) {
	print $fmtHours($otHoursPH).' &nbsp;= &nbsp; '.$fmtAmount($otResult['ph_pay']);
} else {
	print '<input type="number" name="ot_hours_ph" value="'.dol_escape_htmltag($otHoursPH).'" step="0.25" min="0" class="flat width80 right" placeholder="0.00"> <span class="sgpayroll-unit">h</span>';
	print ' <span class="opacitymedium">= '.$fmtAmount($otResult['ph_pay']).'</span>';
}
print '</td></tr>';
print '<tr><td><b>'.$langs->trans('OvertimePay').'</b></td><td class="right"><b>'.$fmtAmount($payLine->overtime_pay ?? $otResult['total_ot_pay']).'</b>';
if ($totalOtHours > 0) print ' <small class="opacitymedium">('.$fmtHours($totalOtHours).')</small>';
print '</td></tr>';
if ($mom72Warn) {
	print '<tr><td colspan="2"><span class="warning small">&#9888; MOM: '.$langs->trans('OT72hWarning').' ('.$totalOtHours.'h)</span></td></tr>';
}
print '</table></div>';


print '<div class="fichehalfright">';
print '<table class="border tableforfield centpercent">';
print '<colgroup><col style="width:72%"><col style="width:28%"></colgroup>';
print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('DeductionsAndClaims').'</td></tr>';

// 先输入 Unpaid Leave Days，再展示由此计算出的 UPL Deduction，逻辑更直观
$valUplDays = $payLine->upl_days ?? 0;
print '<tr><td class="titlefield">'.$langs->trans('UplDays').'</td><td class="right">';
if ($readOnly) {
	print number_format((float)$valUplDays, 2, '.', '').' <span class="sgpayroll-unit">'.$langs->trans('Days').'</span>';
} else {
	print '<input type="number" name="upl_days" value="'.(float)$valUplDays.'" step="0.25" class="flat width80 right" placeholder="0.00">';
}
print '</td></tr>';

// UPL Deduction：作为从工资中扣减的项目，在界面上以负数+红色标识（只让金额变红，标签保持统一风格）
$uplAmount = (float)($payLine->upl_deduction ?? 0);
print '<tr><td class="titlefield">'.$langs->trans('UplDeduction').'</td><td class="right">';
if ($uplAmount > 0) {
	print '<span style="color:#c00">'.$fmtAmount(-$uplAmount).'</span>';
} else {
	print $fmtAmount(-$uplAmount);
}
print '</td></tr>';

// 其他扣减项目：保持同一右对齐和输入宽度，金额统一用 price 格式
$dedFields = array(
	'salary_advance_recovery' => $langs->trans('AdvanceRecovery'),
	'other_deductions'        => $langs->trans('OtherDeductions'),
);
foreach ($dedFields as $key => $label) {
	$val = $payLine->$key ?? 0;
	print '<tr><td class="titlefield"><span style="white-space:nowrap">'.$label.'</span></td><td class="right">';
	if ($readOnly) {
		print $fmtAmount($val);
	} else {
		print '<input type="number" name="'.$key.'" value="'.(float)$val.'" step="0.01" class="flat width150 right" placeholder="0.00">';
	}
	print '</td></tr>';
}

print '<tr><td><span style="white-space:nowrap">'.$langs->trans('ClaimsReimbursement').' (I)</span></td><td class="right">';
if ($readOnly) print $fmtAmount($payLine->claims_total ?? 0);
else print '<input type="number" name="claims_total" value="'.(float)($payLine->claims_total??0).'" step="0.01" class="flat width150 right" placeholder="0.00">';
print '</td></tr>';
print '<tr><td class="titlefield"><span style="white-space:nowrap">'.$langs->trans('BIK').'</span></td><td class="right">';
if ($readOnly) {
	print $fmtAmount($payLine->bik_value ?? 0);
} else {
	print '<input type="number" name="bik_value" value="'.(float)($payLine->bik_value??0).'" step="0.01" class="flat width150 right" placeholder="0.00">';
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('Remark').'</td><td>';
if ($readOnly) print nl2br(dol_escape_htmltag($payLine->note ?? ''));
else print '<textarea name="note" class="flat" style="width:95%" rows="2">'.dol_escape_htmltag($payLine->note ?? '').'</textarea>';
print '</td></tr>';
print '</table></div></div>';

// ──── Leave records for this month ─────────────────────────────────────────────
if ($fkUser > 0 && ($yr > 0 && $mo >= 1 && $mo <= 12)) {
	// Days Worked = work days in month minus approved leave on working days only (excl. public holidays)
	$totalLeaveDaysApproved = 0;
	foreach ($leaveRecordsMonth as $lr) {
		if ((int)$lr['statut'] === 3) $totalLeaveDaysApproved += (float)($lr['working_days'] ?? $lr['days']);
	}
	$daysWorkedThisMonth = max(0, (float)$workDays - $totalLeaveDaysApproved);

	// Dolibarr Holiday: 1=Draft, 2=Validated, 3=Approved, 4=Canceled, 5=Refused
	$statusLeaveMap = array(0 => 'Draft', 1 => 'Draft', 2 => 'Validated', 3 => 'Approved', 4 => 'Canceled', 5 => 'Refused');
	print '<div class="fichecenter" style="margin-top:12px">';
	print '<table class="border tableforfield centpercent">';
	print '<tr class="liste_titre"><td colspan="5">'.$langs->trans('LeaveRecordsThisMonth');
	$dwNote = $langs->trans('DaysWorkedThisMonth').': '.number_format($daysWorkedThisMonth, 2, '.', '').' = '.number_format((float)$workDays, 2, '.', '').' − '.number_format($totalLeaveDaysApproved, 2, '.', '').' '.$langs->trans('Days');
	if ($phDaysInMonth > 0) {
		$phLabel = $langs->trans('PublicHolidays');
		if ($phLabel === 'PublicHolidays') $phLabel = 'Public holidays';
		$dwNote .= ' ('.$langs->trans('Inc').' '.number_format($phDaysInMonth, 2, '.', '').' '.$phLabel.')';
	}
	print ' <span class="opacitymedium">('.$dwNote.')</span>';
	print '</td></tr>';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('LeaveType').'</td>';
	print '<td>'.$langs->trans('DateFrom').'</td>';
	print '<td>'.$langs->trans('DateTo').'</td>';
	print '<td class="right">'.$langs->trans('Days').'</td>';
	print '<td>'.$langs->trans('Status').'</td>';
	print '</tr>';
	if (count($leaveRecordsMonth) > 0) {
		foreach ($leaveRecordsMonth as $lr) {
			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($lr['type']).'</td>';
			print '<td>'.dol_print_date($lr['date_from'], 'day').'</td>';
			print '<td>'.dol_print_date($lr['date_to'], 'day').'</td>';
			print '<td class="right">'.number_format($lr['days'], 2, '.', '').'</td>';
			$stLabel = isset($statusLeaveMap[$lr['statut']]) ? $statusLeaveMap[$lr['statut']] : (string)$lr['statut'];
			print '<td><span class="badge">'.dol_escape_htmltag($stLabel).'</span></td>';
			print '</tr>';
		}
	}
	if (count($leaveRecordsMonth) === 0) {
		print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.$langs->trans('NoLeaveThisMonth').'</td></tr>';
	}
	print '</table></div>';
}

// ──── Summary / Results ───────────────────────────────────────────────────────
if ($id > 0) {
	print '<div class="fichecenter">';
	print '<table class="border tableforfield" style="width:60%">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('PayslipSummary').'</td></tr>';
	$summaryRows = array(
		'GrossSalary'        => $payLine->gross_salary,
		'EmployeeCPF'        => -$payLine->employee_cpf,
		'CpfOA'              => null, // indent (kept for future CPF split)
		'CpfSA'              => null,
		'CpfMA'              => null,
		'SHG'                => -($payLine->shg_cdac + $payLine->shg_ecf + $payLine->shg_mbmf + $payLine->shg_sinda),
		// UPL Deduction 已经在 Basic Salary & Allowances (A+B) 里折算成当月应发，不再在 Gross→Net 路径中单独扣减
		'WithholdingTax'     => -$payLine->withholding_tax,
		'AdvanceRecovery'    => -($payLine->salary_advance_recovery ?? 0),
		'OtherDeductions'    => -($payLine->other_deductions ?? 0),
		'ClaimsReimbursement'=> $payLine->claims_total,
	);
	foreach ($summaryRows as $lbl => $val) {
		if ($val === null) continue;
		$style = $val < 0 ? 'color:#c00' : '';
		print '<tr><td'.($style ? ' style="'.$style.'"' : '').'>'.$langs->trans($lbl).'</td>';
		print '<td class="right" style="'.$style.'">'.$fmtAmount($val).'</td></tr>';
	}
	print '<tr style="border-top:2px solid #333"><td><b>'.$langs->trans('NetPay').'</b></td>';
	print '<td class="right"><b>'.$fmtAmount($payLine->net_pay).'</b></td></tr>';
	// Employer cost section
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('EmployerCost').'</td></tr>';
	print '<tr><td>'.$langs->trans('EmployerCPF').'</td><td class="right">'.$fmtAmount($payLine->employer_cpf ?? 0).'</td></tr>';
	print '<tr><td>'.$langs->trans('SdlAmount').'</td><td class="right">'.$fmtAmount($payLine->sdl_amount ?? 0).'</td></tr>';
	print '<tr><td>'.$langs->trans('FwlAmount').'</td><td class="right">'.$fmtAmount($payLine->fwl_amount ?? 0).'</td></tr>';

	// SHG details (sub-items indented to distinguish from main category)
	if (($payLine->shg_cdac + $payLine->shg_ecf + $payLine->shg_mbmf + $payLine->shg_sinda) > 0) {
		$shgSubStyle = 'padding-left: 1.4em; font-size: 0.95em; color: #555;';
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SHGDonations').'</td></tr>';
		if ($payLine->shg_cdac > 0)  print '<tr><td style="'.$shgSubStyle.'">CDAC</td><td class="right">'.$fmtAmount($payLine->shg_cdac).'</td></tr>';
		if ($payLine->shg_ecf > 0)   print '<tr><td style="'.$shgSubStyle.'">ECF</td><td class="right">'.$fmtAmount($payLine->shg_ecf).'</td></tr>';
		if ($payLine->shg_mbmf > 0)  print '<tr><td style="'.$shgSubStyle.'">MBMF</td><td class="right">'.$fmtAmount($payLine->shg_mbmf).'</td></tr>';
		if ($payLine->shg_sinda > 0) print '<tr><td style="'.$shgSubStyle.'">SINDA</td><td class="right">'.$fmtAmount($payLine->shg_sinda).'</td></tr>';
	}
	print '</table></div>';
}

// ──── Action buttons ──────────────────────────────────────────────────────────
print '<div class="tabsAction">';
if (!$readOnly) {
	print '<input type="submit" value="'.$langs->trans('Save').' / '.$langs->trans('Compute').'" class="butAction">';
}
print '</form>';
if ($id > 0 && $payLine->status === 'draft' && ($user->admin || $user->hasRight('sghr', 'payroll', 'approve'))) {
	print '<form method="POST" action="payslip_card.php" style="display:inline">';
	print '<input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="submit" value="'.$langs->trans('Approve').'" class="butAction">';
	print '</form>';
}
if ($id > 0 && $payLine->status === 'approved' && ($user->admin || $user->hasRight('sghr', 'payroll', 'approve'))) {
	print '<form method="POST" action="payslip_card.php" style="display:inline">';
	print '<input type="hidden" name="action" value="unapprove"><input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="submit" value="'.$langs->trans('Modify').'" class="butAction">';
	print '</form>';
}
if ($id > 0 && $payLine->status === 'draft' && ($user->admin || $user->hasRight('sghr', 'payroll', 'create'))) {
	print '<form method="POST" action="payslip_card.php" style="display:inline" onsubmit="return confirm(\'Are you sure you want to delete this record?\');">';
	print '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="submit" value="'.$langs->trans('Delete').'" class="butActionDelete">';
	print '</form>';
}
if ($id > 0 && ($payLine->status === 'approved' || $payLine->status === 'paid')) {
	print '<a href="export/pdf_payslip.php?id='.$id.'&mode=inline" class="butAction" target="_blank">'.img_picto('','pdf','class="paddingright"').$langs->trans('GeneratePDF').'</a>';
	// Manual re-send payslip email
	print '<form method="POST" action="payslip_card.php" style="display:inline">';
	print '<input type="hidden" name="action" value="send_email"><input type="hidden" name="id" value="'.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<button type="submit" class="butAction" title="'.$langs->trans('PayslipEmailTooltip').'"><i class="fas fa-envelope fa-sm paddingright"></i>'.$langs->trans('ResendEmail').'</button>';
	print '</form>';
}
print '<a href="payslip_list.php" class="butActionRefused">'.$langs->trans('Back').'</a>';
print '</div>';

// Portal links
if ($id > 0) {
	print '<div class="tabsAction">'.sgpayroll_portal_links('payslip').'</div>';
}

// Multi-currency display note
if ($id > 0 && !empty($payLine->contract_currency) && $payLine->contract_currency !== 'SGD') {
	print '<div class="info" style="margin-top:8px">'.img_picto('', 'info', 'class="paddingright"');
	print $langs->trans('MultiCurrencyNote', dol_escape_htmltag($payLine->contract_currency));
	print ' '.sgpayroll_currency_badge($payLine->contract_currency, $payLine->exchange_rate);
	print '</div>';
}

// JS helper
?>
<script>
var allowIdx = <?php echo count($allowRows); ?>;
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

// Multi-currency: keep Basic Salary (SGD) synced with Contract Salary / Exchange Rate
(function() {
    var basicInput = document.getElementsByName('basic_salary')[0];
    var rateInput = document.getElementsByName('exchange_rate')[0];
    if (!basicInput || !rateInput) return; // SGD contracts have no exchange_rate input here

    var contractSalaryFC = <?php echo json_encode((float)$salary_fc); ?>;
    function updateBasicFromRate() {
        var rate = parseFloat(rateInput.value);
        if (!isFinite(rate) || rate <= 0) return;
        // Manual rate semantics: 1 SGD = rate FCY  =>  SGD = FCY / rate
        basicInput.value = (contractSalaryFC / rate).toFixed(2);
    }

    rateInput.addEventListener('input', updateBasicFromRate);
    rateInput.addEventListener('change', updateBasicFromRate);
})();

// AL Encashment: live amount from days (daily rate = basic / work_days)
(function() {
    var alDaysInput = document.getElementById('al_days_encash');
    if (!alDaysInput) return;
    var workDaysForAL = <?php echo json_encode((float)($workDays ?? 0)); ?>;
    function updateALEncashDisplay() {
        var days = parseFloat(alDaysInput.value) || 0;
        var basicInput = document.getElementsByName('basic_salary')[0];
        var basicForAL = basicInput ? (parseFloat(basicInput.value) || 0) : 0;
        var amount = (workDaysForAL > 0 && basicForAL > 0 && days > 0) ? (basicForAL / workDaysForAL) * days : 0;
        var el = document.getElementById('al_encash_amount_display');
        if (el) el.textContent = amount > 0 ? ' = ' + amount.toFixed(2) : '';
    }
    alDaysInput.addEventListener('input', updateALEncashDisplay);
    alDaysInput.addEventListener('change', updateALEncashDisplay);
})();
</script>
<?php
if ($fkUser > 0) {
	dol_fiche_end();
	print '</div>';
}
llxFooter();
$db->close();
?>
