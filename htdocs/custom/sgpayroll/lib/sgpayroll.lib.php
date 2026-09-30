<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        lib/sgpayroll.lib.php
 * \ingroup     sgpayroll
 * \brief       Shared utility functions for sgpayroll module
 */

/**
 * Build the breadcrumb array for module pages.
 *
 * @param  Translate  $langs
 * @param  string     $pagename  Key for current page label
 * @return array
 */
function sgpayroll_prepare_head($langs, $pagename = '')
{
	$h = 0;
	$head = array();
	// Add tabs if needed later
	return $head;
}

/**
 * Prepare head/tabs for SGPayroll admin pages.
 *
 * This is used by admin pages calling dol_fiche_head(). If this function is missing,
 * Dolibarr may show a blank page when display_errors is disabled (fatal error).
 *
 * @return array
 */
function sgpayrollAdminPrepareHead()
{
	global $langs;

	$head = array();
	$h = 0;

	$head[$h][0] = 'setup.php';
	$head[$h][1] = '<span class="fas fa-cogs fa-fw"></span> '.$langs->trans('GeneralSettings');
	$head[$h][2] = 'setup';
	$h++;

	$head[$h][0] = 'cpfrates.php';
	$head[$h][1] = '<span class="fas fa-percent fa-fw"></span> '.$langs->trans('CpfRateTable');
	$head[$h][2] = 'cpfrates';
	$h++;

	$head[$h][0] = 'setup_statutory_rates.php';
	$head[$h][1] = '<span class="fas fa-coins fa-fw"></span> '.$langs->trans('StatutoryRates');
	$head[$h][2] = 'statutory_rates';
	$h++;

	$head[$h][0] = 'setup_costcentres.php';
	$head[$h][1] = '<span class="fas fa-sitemap fa-fw"></span> '.$langs->trans('CostCentres');
	$head[$h][2] = 'costcentres';
	$h++;

	$head[$h][0] = 'setup_schedule_presets.php';
	$head[$h][1] = '<span class="fas fa-calendar-week fa-fw"></span> '.$langs->trans('SchedulePresets');
	$head[$h][2] = 'schedule_presets';
	$h++;

	$head[$h][0] = 'upgrade_sql.php';
	$head[$h][1] = '<span class="fas fa-database fa-fw"></span> '.$langs->trans('SqlUpgradeRunner');
	$head[$h][2] = 'upgrade';
	$h++;

	return $head;
}

/**
 * Prepare head for employee profile tabs
 *
 * @param  int  $fkUser  Employee user ID
 * @return array         Tabs array for dol_fiche_head
 */
function sgpayroll_employee_prepare_head($fkUser)
{
	global $langs;
	$head = array();
	$head[0] = array('employee_card.php?fk_user='.$fkUser, $langs->trans('SGPayroll'), 'hr');
	$head[1] = array('payslip_list.php?mode=own&fk_user='.$fkUser, $langs->trans('MyPayslips'), 'payslips');
	$head[2] = array(dol_buildpath('/holiday/list.php', 1).'?fk_user='.$fkUser, $langs->trans('Leave'), 'leave');
	$head[3] = array(dol_buildpath('/expensereport/list.php', 1).'?fk_user_author='.$fkUser, $langs->trans('ExpenseClaims'), 'claims');
	$head[4] = array(dol_buildpath('/user/card.php', 1).'?id='.$fkUser.'&action=view&mainmenu=hrm', $langs->trans('HRProfile'), 'profile');
	if (isModEnabled('hrm') || isModEnabled('skills')) {
		$head[5] = array(dol_buildpath('/hrm/skill_tab.php', 1).'?id='.$fkUser.'&objecttype=user', $langs->trans('Skills'), 'skills');
	}
	$head[7] = array('documents_list.php?fk_user='.$fkUser, $langs->trans('Documents'), 'docs');
	return $head;
}

/**
 * Return month selector HTML.
 *
 * @param  int     $selected   Currently selected month (1–12)
 * @param  string  $name       HTML element name
 * @return string  HTML select element
 */
function sgpayroll_select_month($selected, $name = 'pay_month')
{
	global $langs;
	$months = array(1=>'January',2=>'February',3=>'March',4=>'April',
	                5=>'May',6=>'June',7=>'July',8=>'August',
	                9=>'September',10=>'October',11=>'November',12=>'December');
	$html = '<select name="'.$name.'" class="flat">';
	foreach ($months as $num => $label) {
		$html .= '<option value="'.$num.'"'.($num == $selected ? ' selected' : '').'>'.$langs->trans($label).'</option>';
	}
	$html .= '</select>';
	return $html;
}

/**
 * Return CSS badge class for a payroll status string.
 *
 * @param  string  $status  draft|submitted|approved|paid
 * @return string  Dolibarr badge class
 */
function sgpayroll_status_class($status)
{
	$map = array(
		'draft'     => 'badge-status0',
		'submitted' => 'badge-status1', 
		'approved'  => 'badge-status4', // User explicitly stated badge-status4 is the standard green
		'paid'      => 'badge-status5', 
	);
	return $map[$status] ?? 'badge-status0';
}

/**
 * Get the holiday type rowid for Annual Leave (AL) from c_holiday_types.
 * Used to read/update AL balance in llx_holiday_users.
 *
 * @param  DoliDB  $db
 * @return int     0 if not found or holiday module disabled
 */
function sgpayroll_get_al_type_id($db)
{
	if (!isModEnabled('holiday')) return 0;
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."c_holiday_types WHERE code IN ('AL','al') AND active = 1 LIMIT 1";
	$res = $db->query($sql);
	if ($res && $obj = $db->fetch_object($res)) return (int)$obj->rowid;
	return 0;
}

/**
 * Get the Annual Leave (AL) balance for a user from HRM holiday_users.
 * Returns the nb_holiday for the AL type (balance of leave).
 *
 * @param  DoliDB  $db
 * @param  int     $userId  Dolibarr user ID
 * @return float   AL balance in days (0 if no holiday module or no row)
 */
function sgpayroll_get_al_balance_from_hrm($db, $userId)
{
	$userId = (int)$userId;
	if ($userId <= 0) return 0.0;
	$alTypeId = sgpayroll_get_al_type_id($db);
	if ($alTypeId <= 0) return 0.0;
	$sql = "SELECT nb_holiday FROM ".MAIN_DB_PREFIX."holiday_users";
	$sql .= " WHERE fk_user = ".$userId." AND fk_type = ".$alTypeId;
	$res = $db->query($sql);
	if ($res && $obj = $db->fetch_object($res)) return (float)$obj->nb_holiday;
	return 0.0;
}

/**
 * Deduct Annual Leave balance in HRM after AL encashment in payroll.
 * Call after successfully saving a payslip that has al_days_encash > 0.
 * For edits: pass delta (new_days - old_days) so balance is adjusted by the difference.
 *
 * @param  DoliDB  $db
 * @param  int     $userId   Dolibarr user ID
 * @param  float   $daysDelta  Days to deduct (positive) or add back (negative)
 * @return int     1=OK, 0=no change, -1=error
 */
function sgpayroll_deduct_al_balance_hrm($db, $userId, $daysDelta)
{
	$userId = (int)$userId;
	if ($userId == 0 || (float)$daysDelta == 0) return 0;
	$alTypeId = sgpayroll_get_al_type_id($db);
	if ($alTypeId <= 0) return 0;
	if (!class_exists('Holiday')) require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
	$holiday = new Holiday($db);
	$current = $holiday->getCPforUser($userId, $alTypeId);
	if ($current === null) $current = 0;
	$newBalance = max(0.0, (float)$current - (float)$daysDelta);
	$res = $holiday->updateSoldeCP($userId, $newBalance, $alTypeId);
	return $res;
}

/**
 * Round-down to nearest dollar for IRAS IR8A income fields (Salary, Bonus, Director's Fees, etc.).
 * @see https://www.iras.gov.sg/.../submit-employment-income-records
 *
 * @param  float|string  $amount  Raw amount
 * @return float  Amount rounded down to whole dollar
 */
function sgpayroll_round_iras_income($amount)
{
	return floor((float) $amount);
}

/**
 * Round-up to nearest dollar for IRAS IR8A deduction fields (CPF, Donations, etc.).
 * @see https://www.iras.gov.sg/.../submit-employment-income-records
 *
 * @param  float|string  $amount  Raw amount
 * @return float  Amount rounded up to whole dollar
 */
function sgpayroll_round_iras_deduction($amount)
{
	return ceil((float) $amount);
}

/**
 * Rebuild (upsert) AIS review rows from payroll records for a given income year.
 * All income and deduction amounts are rounded to nearest dollar per IRAS IR8A requirements.
 *
 * Called from iras_ais_review.php "Refresh from Payroll" action.
 *
 * @param  DoliDB  $db
 * @param  int     $incomeYear   e.g. 2025 (income earned)
 * @param  int     $ya           e.g. 2026 (Year of Assessment)
 * @param  User    $user
 * @return void
 */
function sgpayroll_rebuild_ais_rows($db, $incomeYear, $ya, $user)
{
	global $conf;
	$entity = (int)$conf->entity;

	// Aggregate payroll lines for all employees for the income year
	$sql  = "SELECT pl.fk_user,";
	$sql .= " SUM(COALESCE(pl.prorated_salary,0) + COALESCE(pl.al_encashment,0) + COALESCE(pl.other_aw,0)) AS ir8a_gross,";
	$sql .= " SUM(COALESCE(pl.bonus,0))               AS bonus,";
	$sql .= " SUM(COALESCE(pl.commission,0))          AS commission,";
	$sql .= " SUM(COALESCE(pl.overtime_pay,0))        AS overtime_pay,";
	$sql .= " SUM(COALESCE(pl.employee_cpf,0))        AS employee_cpf,";
	$sql .= " SUM(COALESCE(pl.shg_mbmf,0))            AS shg_mbmf,";
	$sql .= " SUM(COALESCE(pl.shg_sinda,0))           AS shg_sinda,";
	$sql .= " SUM(COALESCE(pl.shg_cdac,0))            AS shg_cdac,";
	$sql .= " SUM(COALESCE(pl.shg_ecf,0))             AS shg_ecf,";
	$sql .= " SUM(COALESCE(pl.bik_value,0))           AS bik_value,";
	// Transport & other from allowances_json: handle in PHP below
	$sql .= " GROUP_CONCAT(pl.allowances_json) AS all_allowances_json";
	$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
	$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
	$sql .= " WHERE p.pay_year = ".(int)$incomeYear;
	$sql .= " AND pl.status IN ('approved','paid')";
	$sql .= " AND p.entity = ".$entity;
	$sql .= " GROUP BY pl.fk_user";

	$res = $db->query($sql);
	if (!$res) return;

	while ($obj = $db->fetch_object($res)) {
		// Parse allowance JSON for transport vs other
		$transportTotal = 0;
		$otherTotal     = 0;
		$allJsonStr     = $obj->all_allowances_json ?? '';
		// The GROUP_CONCAT produces multiple JSON arrays; parse each
		$parts = explode('[', str_replace('],[', '[|[', $allJsonStr));
		foreach ($parts as $part) {
			if (empty(trim($part))) continue;
			$part = '[' . ltrim($part, '|');
			$arr  = @json_decode($part, true);
			if (is_array($arr)) {
				foreach ($arr as $a) {
					$label  = strtolower($a['label'] ?? '');
					$amount = (float)($a['amount'] ?? 0);
					if (strpos($label, 'transport') !== false) {
						$transportTotal += $amount;
					} else {
						$otherTotal += $amount;
					}
				}
			}
		}

		// IRAS: income fields round-down, deduction fields round-up (submit-employment-income-records)
		$gross   = sgpayroll_round_iras_income($obj->ir8a_gross);
		$bonus   = sgpayroll_round_iras_income($obj->bonus);
		$comm    = sgpayroll_round_iras_income($obj->commission);
		$ot      = sgpayroll_round_iras_income($obj->overtime_pay);
		$trans   = sgpayroll_round_iras_income($transportTotal);
		$other   = sgpayroll_round_iras_income($otherTotal);
		$bik     = sgpayroll_round_iras_income($obj->bik_value);
		$empCpf  = sgpayroll_round_iras_deduction($obj->employee_cpf);
		$mbmf    = sgpayroll_round_iras_deduction($obj->shg_mbmf);
		$sinda   = sgpayroll_round_iras_deduction($obj->shg_sinda);
		$cdac    = sgpayroll_round_iras_deduction($obj->shg_cdac);
		$ecf     = sgpayroll_round_iras_deduction($obj->shg_ecf);
		$taxable = sgpayroll_round_iras_income((float)$obj->ir8a_gross + (float)$obj->bonus + (float)$obj->commission + (float)$obj->overtime_pay + $transportTotal + $otherTotal + (float)$obj->bik_value);

		// UPSERT
		$checkSql = "SELECT rowid, status FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review"
		          . " WHERE fk_user=".(int)$obj->fk_user." AND year_of_assessment=".(int)$ya
		          . " AND entity = ".$entity;
		$checkRes = $db->query($checkSql);
		$existing = ($checkRes && $db->num_rows($checkRes) > 0) ? $db->fetch_object($checkRes) : null;

		if ($existing && $existing->status === 'approver_confirmed') {
			// Do not overwrite already-confirmed rows
			continue;
		}

		$fields = "gross_salary=".(float)$gross.",";
		$fields .= "bonus=".(float)$bonus.",";
		$fields .= "commission=".(float)$comm.",";
		$fields .= "overtime_pay=".(float)$ot.",";
		$fields .= "transport_allowance=".(float)$trans.",";
		$fields .= "other_allowances=".(float)$other.",";
		$fields .= "employee_cpf=".(float)$empCpf.",";
		$fields .= "mbmf=".(float)$mbmf.",";
		$fields .= "sinda=".(float)$sinda.",";
		$fields .= "cdac=".(float)$cdac.",";
		$fields .= "ecf=".(float)$ecf.",";
		$fields .= "bik_value=".(float)$bik.",";
		$fields .= "taxable_income=".(float)$taxable;

		if ($existing) {
			$upd = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_ais_review SET ".$fields.",status='draft'"
			     . " WHERE rowid=".(int)$existing->rowid;
			$db->query($upd);
		} else {
			$ins = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_ais_review"
				." (fk_user, year_of_assessment, gross_salary, bonus, commission, overtime_pay,"
				." transport_allowance, other_allowances, employee_cpf, mbmf, sinda, cdac, ecf, bik_value, taxable_income, status, entity)"
				." VALUES (".(int)$obj->fk_user.",".(int)$ya.","
				.(float)$gross.",".(float)$bonus.",".(float)$comm.","
				.(float)$ot.",".(float)$trans.",".(float)$other.","
				.(float)$empCpf.",".(float)$mbmf.",".(float)$sinda.",".(float)$cdac.",".(float)$ecf.","
				.(float)$bik.",".(float)$taxable.",'draft', ".(int)$entity.")";
			$db->query($ins);
		}
	}
}

/**
 * Calculate number of working days in a given month/year (Mon–Fri, excludes SG PH).
 *
 * @param  int  $year
 * @param  int  $month  1–12
 * @param  float $customWorkDays  If > 0, return this instead
 * @return float  Working days
 */
/**
 * Return working days for a month. Always by schedule (align with common payroll software).
 * Priority:
 * 1. Employee-specific monthly override (workdays_per_month), when explicitly set > 0
 * 2. By weekly schedule: employee schedule if set, otherwise default Mon–Fri (excluding SG PH)
 * Global SGPAYROLL_WORK_DAYS_PER_WEEK / SGPAYROLL_WORK_DAYS_PER_MONTH are not used here.
 */
function sgpayroll_working_days($year, $month, $customWorkDays = 0, $schedule = '')
{
	dol_include_once('sgpayroll/class/payrollcalc.class.php');

	// 1. Employee-level monthly override (explicit days per month)
	if ($customWorkDays > 0) {
		return (float)$customWorkDays;
	}

	// 2. By schedule; 公假加回去: denominator includes PH on working days (excludePublicHolidays = false)
	$scheduleToUse = $schedule !== '' && $schedule !== null ? $schedule : '';
	$days = SGPayrollCalc::countWorkingDays($year, $month, $scheduleToUse, false);

	// Fallback if countWorkingDays returned 0 (e.g. invalid schedule)
	if ($days <= 0) {
		$days = SGPayrollCalc::countWorkingDays($year, $month, 'Mon,Tue,Wed,Thu,Fri', false);
	}
	if ($days <= 0) {
		$days = 22;
	}

	return (float)$days;
}

/**
 * Format a number of days for display (e.g., 20, 21.5).
 * Safely removes trailing zeros ONLY if there's a decimal point.
 *
 * @param  float|string  $days
 * @return string
 */
function sgpayroll_format_days($days)
{
	$val = (float)$days;
	if (floor($val) == $val) {
		return (string)(int)$val;
	}
	return rtrim(rtrim(number_format($val, 2, '.', ''), '0'), '.');
}

/**
 * Format employee full name according to module setting (Singapore default: Last + First).
 *
 * @param  string  $firstname
 * @param  string  $lastname
 * @return string  Trimmed display name (e.g. "Tan John" or "John Tan")
 */
function sgpayroll_format_employee_name($firstname, $lastname)
{
	$order = getDolGlobalString('SGPAYROLL_NAME_DISPLAY_ORDER', 'lastname_firstname');
	$f = trim((string)$firstname);
	$l = trim((string)$lastname);
	if ($order === 'firstname_lastname') {
		return trim($f.' '.$l);
	}
	return trim($l.' '.$f);
}

/**
 * Normalize weekly schedule string to canonical form for consistent comparison.
 * Output: Mon:V;Tue:V;Wed:V;Thu:V;Fri:V;Sat:V;Sun:V (all 7 days, fixed order, V = 0|0.5|1).
 *
 * @param  string  $schedule  e.g. "Mon:1;Tue:1;Fri:1" or "Mon:1;Tue:1;Wed:1;Thu:1;Fri:1;Sat:0;Sun:0"
 * @return string  Canonical form so employee list can match preset labels
 */
function sgpayroll_normalize_weekly_schedule($schedule)
{
	$days = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
	$map = array();
	foreach ($days as $d) $map[$d] = 0;
	if (!empty($schedule)) {
		$pairs = explode(';', $schedule);
		foreach ($pairs as $p) {
			$part = explode(':', trim($p), 2);
			if (count($part) === 2 && isset($map[$part[0]])) {
				$v = (float)$part[1];
				if ($v != 0 && $v != 0.5 && $v != 1) $v = ($v > 0.5) ? 1 : 0.5;
				$map[$part[0]] = $v;
			}
		}
	}
	$out = array();
	foreach ($days as $d) $out[] = $d.':'.$map[$d];
	return implode(';', $out);
}

/**
 * Calculate actual working days inside a given month, truncated by employment dates.
 *
 * @param  int               $year
 * @param  int               $month
 * @param  SGPayrollEmployee $emp
 * @return float
 */
function sgpayroll_worked_days_in_month($year, $month, $emp)
{
	$totalMonthWorkingDays = sgpayroll_working_days($year, $month, $emp->workdays_per_month, $emp->weekly_schedule);

	$startOfMonth = sprintf('%04d-%02d-01', $year, $month);
	$endOfMonth = date('Y-m-t', strtotime($startOfMonth));

	$empStartTS = is_numeric($emp->work_contract_date) ? (int)$emp->work_contract_date : strtotime((string)$emp->work_contract_date);
	$empStart = (!empty($emp->work_contract_date) && $empStartTS > 0) ? date('Y-m-d', $empStartTS) : $startOfMonth;

	$empEndTS = is_numeric($emp->cessation_date) ? (int)$emp->cessation_date : strtotime((string)$emp->cessation_date);
	$empEnd = (!empty($emp->cessation_date) && $empEndTS > 0) ? date('Y-m-d', $empEndTS) : $endOfMonth;

	if ($empStart > $endOfMonth || $empEnd < $startOfMonth) {
		return 0.0;
	}

	$workStart = max($startOfMonth, $empStart);
	$workEnd = min($endOfMonth, $empEnd);

	if ($workStart === $startOfMonth && $workEnd === $endOfMonth) {
		return $totalMonthWorkingDays;
	}

	dol_include_once('sgpayroll/class/payrollcalc.class.php');
	$phList = SGPayrollCalc::getSingaporePublicHolidays($year);

	// When employee has a weekly schedule, count only schedule days in the truncated range (not all weekdays)
	if (!empty($emp->weekly_schedule)) {
		$workedDays = SGPayrollCalc::countWorkingDaysInRange($workStart, $workEnd, $emp->weekly_schedule, $phList);
		return min(round((float)$workedDays, 2), $totalMonthWorkingDays);
	}

	$workedDays = 0;
	$current = strtotime($workStart);
	$endTS = strtotime($workEnd);
	while ($current <= $endTS) {
		$date = date('Y-m-d', $current);
		$dow = (int)date('w', $current);
		if ($dow !== 0 && $dow !== 6 && !in_array($date, $phList, true)) {
			$workedDays++;
		}
		$current = strtotime('+1 day', $current);
	}

	if ($emp->workdays_per_month > 0) {
		$standardMonthWorkingDays = sgpayroll_working_days($year, $month, 0, '');
		if ($standardMonthWorkingDays > 0) {
			$workedDays = round(($workedDays / $standardMonthWorkingDays) * $emp->workdays_per_month, 2);
		}
	}

	return min((float)$workedDays, $totalMonthWorkingDays);
}

/**
 * Return OT multiplier dropdown HTML.
 *
 * @param  float   $selected  Currently selected multiplier
 * @param  string  $name      Form field name
 * @return string  HTML select element
 */
function sgpayroll_ot_multiplier_select($selected = 1.5, $name = 'ot_multiplier')
{
	$options = array(
		'1.0'  => '1.0× (Normal rate)',
		'1.5'  => '1.5× (Weekday OT — Employment Act)',
		'2.0'  => '2.0× (Rest day / PH)',
		'3.0'  => '3.0× (PH + OT)',
	);
	$html = '<select name="'.dol_escape_htmltag($name).'" class="flat">';
	foreach ($options as $val => $label) {
		$html .= '<option value="'.$val.'"'.((float)$val == (float)$selected ? ' selected' : '').'>'.dol_escape_htmltag($label).'</option>';
	}
	$html .= '</select>';
	return $html;
}

/**
 * Return statutory portal direct-link buttons for display on relevant pages.
 *
 * @param  string  $context  'ais'|'cpf'|'payslip'|'leave'|'all'
 * @return string  HTML with action links
 */
function sgpayroll_portal_links($context = 'all')
{
	$links = array(
		'iras_mytax' => array(
			'url'     => 'https://mytax.iras.gov.sg/ESVWeb/default.aspx',
			'label'   => 'IRAS myTax Portal',
			'icon'    => 'globe',
			'context' => array('ais', 'all'),
		),
		'cpf_esubmit' => array(
			'url'     => 'https://www.cpf.gov.sg/employer/submission',
			'label'   => 'CPF e-Submit',
			'icon'    => 'globe',
			'context' => array('cpf', 'payslip', 'all'),
		),
		'cpf_calculator' => array(
			'url'     => 'https://www.cpf.gov.sg/employer/tools-and-services/calculators/cpf-contribution-calculator',
			'label'   => 'CPF Calculator',
			'icon'    => 'globe',
			'context' => array('cpf', 'all'),
		),
		'mom_payslip' => array(
			'url'     => 'https://www.mom.gov.sg/employment-practices/salary/itemised-payslips',
			'label'   => 'MOM Payslip Requirements',
			'icon'    => 'globe',
			'context' => array('payslip', 'all'),
		),
		'mom_employment_act' => array(
			'url'     => 'https://www.mom.gov.sg/employment-practices/employment-act',
			'label'   => 'MOM Employment Act',
			'icon'    => 'globe',
			'context' => array('leave', 'all'),
		),
		'mom_overtime' => array(
			'url'     => 'https://www.mom.gov.sg/employment-practices/hours-of-work-overtime-and-rest-days',
			'label'   => 'MOM Overtime Guide',
			'icon'    => 'globe',
			'context' => array('payslip', 'all'),
		),
	);

	$html = '<div class="sgpayroll-portal-links inline-block">';
	foreach ($links as $key => $link) {
		if (in_array($context, $link['context'])) {
			$html .= ' <a href="'.dol_escape_htmltag($link['url']).'" target="_blank" rel="noopener" class="butAction button-outline small" title="'.dol_escape_htmltag($link['label']).'">';
			$html .= img_picto($link['label'], $link['icon'], 'class="pictofixedwidth"');
			$html .= dol_escape_htmltag($link['label']);
			$html .= '</a>';
		}
	}
	$html .= '</div>';
	return $html;
}

/**
 * Return a small currency badge for multi-currency display.
 *
 * @param  string  $currency   ISO 4217 code
 * @param  float   $rate       Dolibarr: SGD per 1 FCY; manual profile: FCY per 1 SGD
 * @param  string  $source     'dolibarr_multicurrency'|'manual'
 * @return string  HTML snippet
 */
function sgpayroll_currency_badge($currency, $rate = 1.0, $source = '')
{
	if ($currency === 'SGD' || empty($currency)) {
		return '';
	}
	if ($source === 'dolibarr_multicurrency') {
		$tooltip = 'SGD/'.$currency.' @ '.number_format($rate, 4).' (auto from Dolibarr)';
	} elseif ($source === 'manual') {
		$tooltip = '1 SGD = '.number_format($rate, 4).' '.$currency.' (manual)';
	} else {
		$tooltip = $currency.' @ '.number_format($rate, 4);
	}
	return '<span class="sgpayroll-currency-tag" title="'.dol_escape_htmltag($tooltip).'">'.dol_escape_htmltag($currency).'</span>';
}

/**
 * Return a list of major Singapore banks.
 * 
 * @return array  Bank Code => Bank Name
 */
function sgpayroll_get_banks()
{
	return array(
		'DBS Bank / POSB' => 'DBS Bank / POSB',
		'OCBC Bank' => 'OCBC Bank',
		'UOB (United Overseas Bank)' => 'UOB (United Overseas Bank)',
		'Standard Chartered Bank' => 'Standard Chartered Bank',
		'HSBC (Hongkong and Shanghai Banking Corporation)' => 'HSBC (Hongkong and Shanghai Banking Corporation)',
		'Citibank Singapore' => 'Citibank Singapore',
		'Bank of America' => 'Bank of America',
		'Bank of China' => 'Bank of China',
		'Maybank Singapore' => 'Maybank Singapore',
		'CIMB Bank' => 'CIMB Bank',
		'RHB Bank' => 'RHB Bank',
		'ICBC (Industrial and Commercial Bank of China)' => 'ICBC (Industrial and Commercial Bank of China)',
		'BNP Paribas' => 'BNP Paribas',
		'Goldman Sachs' => 'Goldman Sachs',
		'Morgan Stanley' => 'Morgan Stanley',
		'J.P. Morgan' => 'J.P. Morgan',
		'OTH' => '-- Other --',
	);
}

/**
 * Return the default Social Help Group (SHG) based on race.
 * 
 * @param  string  $race  Chinese|Malay|Indian|Eurasian|Others
 * @return string  cdac|mbmf|sinda|ecf|none
 */
function sgpayroll_get_shg_for_race($race)
{
	$race = strtolower($race);
	if ($race === 'chinese')  return 'cdac';
	if ($race === 'malay')    return 'mbmf';
	if ($race === 'indian')   return 'sinda';
	if ($race === 'eurasian') return 'ecf';
	return 'none';
}

/**
 * Send payslip email notification to employee.
 * Uses Dolibarr CMailFile API. Attaches PDF if found.
 *
 * @param  DoliDB          $db      Database handle
 * @param  object          $payLine Payroll line object (must have pay_year, pay_month, net_pay, fk_user)
 * @param  User            $empUser Employee User object (must have email, firstname, lastname)
 * @param  Translate       $langs   Translation object
 * @param  Conf            $conf    Global configuration
 * @return int             1=sent OK, 0=skipped (no email), -1=error
 */
function sgpayroll_send_payslip_email($db, $payLine, $empUser, $langs, $conf)
{
	global $mysoc;

	// Guard: employee must have an email address
	if (empty($empUser->email)) {
		dol_syslog('sgpayroll_send_payslip_email: employee '.$empUser->id.' has no email — skipped', LOG_INFO);
		return 0;
	}

	require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

	$payYear   = (int)$payLine->pay_year;
	$payMonth  = (int)$payLine->pay_month;
	$periodLbl = date('F Y', mktime(0, 0, 0, $payMonth, 1, $payYear));
	$netPay    = price($payLine->net_pay ?? 0);
	$empName   = sgpayroll_format_employee_name($empUser->firstname, $empUser->lastname);
	$compName  = getDolGlobalString('MAIN_INFO_SOCIETE_NOM') ?: ($mysoc->name ?? 'Your Employer');

	// ── Build email body ─────────────────────────────────────────────────────
	$subjectKey = getDolGlobalString('SGPAYROLL_EMAIL_SUBJECT') ?:
		'[Payslip] {COMPANY} - {employee} - {PERIOD}';
	$subject = str_replace(array('{PERIOD}', '{COMPANY}', '{employee}'), array($periodLbl, $compName, $empName), $subjectKey);

	$bodyTxt = "Dear ".$empName.",\n\n".
		"Your payslip for ".$periodLbl." is now available.\n\n".
		"  Net Pay: SGD ".$netPay."\n".
		"  Period : ".$periodLbl."\n\n".
		"Please log in to the Employee Portal to view the full breakdown.\n\n".
		"If you have any questions, please contact HR.\n\n".
		"Regards,\n".$compName." Payroll Team";

	$bodyHtml = "<p>Dear ".dol_escape_htmltag($empName).",</p>".
		"<p>Your payslip for <b>".dol_escape_htmltag($periodLbl)."</b> is now available.</p>".
		"<table style='border-collapse:collapse;font-family:Arial,sans-serif'>".
		"<tr><td style='padding:6px 20px 6px 0;color:#666'>Net Pay</td>".
		"<td style='padding:6px 0;font-weight:bold;font-size:1.2em'>SGD ".dol_escape_htmltag($netPay)."</td></tr>".
		"<tr><td style='padding:6px 20px 6px 0;color:#666'>Period</td>".
		"<td style='padding:6px 0'>".dol_escape_htmltag($periodLbl)."</td></tr>".
		"</table>".
		"<p>Please log in to the <b>Employee Portal</b> to view the full payslip and download the PDF.</p>".
		"<p style='color:#999;font-size:0.85em'>This is an automated notification from ".dol_escape_htmltag($compName)." Payroll System. Do not reply to this email.</p>";

	// Override with custom template if configured
	$customBody = getDolGlobalString('SGPAYROLL_EMAIL_BODY');
	if ($customBody) {
		$bodyHtml = str_replace(
			array('{NAME}', '{PERIOD}', '{NET_PAY}', '{COMPANY}'),
			array(dol_escape_htmltag($empName), dol_escape_htmltag($periodLbl), dol_escape_htmltag($netPay), dol_escape_htmltag($compName)),
			$customBody
		);
	}

	// ── Find PDF attachment ───────────────────────────────────────────────────
	$attachment = array();
	$entity = (int)($conf->entity ?? 1);
	$pdfDir  = DOL_DATA_ROOT.'/sgpayroll/'.$entity.'/'.(int)$empUser->id.'/';
	$pdfFile = $pdfDir.'Payslip_'.str_pad($payMonth, 2, '0', STR_PAD_LEFT).'_'.$payYear.'.pdf';
	if (file_exists($pdfFile)) {
		$attachment[] = $pdfFile;
	}

	// ── From address ──────────────────────────────────────────────────────────
	$fromEmail = getDolGlobalString('MAIN_MAIL_EMAIL_FROM') ?: getDolGlobalString('MAIN_INFO_SOCIETE_MAIL');
	$fromName  = getDolGlobalString('MAIN_MAIL_EMAIL_FROM_NAME') ?: $compName;

	// ── Send ──────────────────────────────────────────────────────────────────
	$from = ($fromName !== '' && $fromEmail ? $fromName.' <'.$fromEmail.'>' : $fromEmail);
	try {
		$mail = new CMailFile(
			$subject,
			$empUser->email,
			$from,
			$bodyHtml,
			$attachment,    // attachments
			array(),        // mime types
			array(),        // attachment names
			'',             // cc
			'',             // bcc
			0,              // delivery receipt
			1,              // is HTML
			''              // errors to
		);
		$sendOk = $mail->sendfile();
		if ($sendOk) {
			dol_syslog('sgpayroll_send_payslip_email: sent to '.$empUser->email.' for '.$periodLbl, LOG_INFO);
			// Log in email history
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_email_log";
			$sql .= " (fk_user, pay_year, pay_month, email_to, date_sent, pdf_attached, entity)";
			$sql .= " VALUES (".(int)$payLine->fk_user.",".$payYear.",".$payMonth;
			$sql .= ",'".$db->escape($empUser->email)."',NOW(),".(!empty($attachment)?1:0).",".(int)$entity.")";
			$db->query($sql); // best-effort, ignore failure (table may not exist yet)
			return 1;
		} else {
			dol_syslog('sgpayroll_send_payslip_email: CMailFile error '.$mail->error, LOG_ERR);
			return -1;
		}
	} catch (Throwable $e) {
		dol_syslog('sgpayroll_send_payslip_email: exception '.$e->getMessage(), LOG_ERR);
		return -1;
	}
}

/**
 * Verify CSRF token.
 * 
 * @param  string  $token  Token to verify
 * @return bool            True if token is valid
 */
function verifyToken($token)
{
	if (empty($token)) return false;
	if (!function_exists('currentToken')) return false; // Fail closed: no verification possible, no pass
	return ($token === (string) currentToken());
}

/**
 * Neutralise CSV/spreadsheet formula injection in exported cell values.
 * Prefixes values starting with = + - @ or a control char with a single quote
 * and flattens embedded tabs/newlines. Apply to free-text fields (names,
 * labels) only — never to numeric amount columns.
 */
function sgpayroll_csv_safe($value)
{
	$v = (string)$value;
	if ($v === '') return $v;
	$first = $v[0];
	if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r" || $first === "\n") {
		$v = "'".$v;
	}
	return str_replace(array("\t", "\r", "\n"), ' ', $v);
}
