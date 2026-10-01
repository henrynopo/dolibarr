<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        class/payrollcalc.class.php
 * \ingroup sghr
 * \brief       Singapore payroll calculation engine.
 *              CPF, SDL, FWL, SHG, OT, UPL, Net Pay.
 */

/**
 * Class SghrCalc
 *
 * All methods are static. Pass in raw figures; receive calculated amounts.
 * Rates are loaded from llx_sgpayroll_cpfrates (versioned by effective_date).
 */
class SghrCalc
{
	/**
	 * Derive PR tier (PR1Y/PR2Y/PR3Y) based on PR start date and an "as of" date.
	 *
	 * Rule (simple, deterministic):
	 * - < 1 full year since PR start: PR1Y
	 * - < 2 full years since PR start: PR2Y
	 * - >= 2 full years since PR start: PR3Y
	 *
	 * @param  mixed   $prStartDate  Timestamp, 'YYYY-MM-DD', or any strtotime()-parseable string
	 * @param  string  $asOfDate     'YYYY-MM-DD' (e.g. payroll payment date)
	 * @return string|null           PR1Y|PR2Y|PR3Y or null if cannot derive
	 */
	public static function derivePRTier($prStartDate, $asOfDate)
	{
		if (empty($asOfDate)) return null;
		if (empty($prStartDate)) return null;

		try {
			// Normalize start date
			if (is_numeric($prStartDate)) {
				$start = (new DateTimeImmutable('@'.(int)$prStartDate))->setTimezone(new DateTimeZone(date_default_timezone_get()));
			} else {
				$start = new DateTimeImmutable((string)$prStartDate);
			}
			$asof = new DateTimeImmutable((string)$asOfDate);
		} catch (Exception $e) {
			return null;
		}

		// CPF Board convention: 2nd/3rd year SPR rates apply from the 1st day of the month
		// following the 1st/2nd anniversary of SPR conversion (not on the anniversary date itself).
		//
		// Example (public guidance):
		// - SPR date: 13 Aug 2021
		// - 2nd year rates from: 1 Sep 2022
		// - 3rd year rates from: 1 Sep 2023
		//
		// Therefore, there is no "mid-month" tier switch; tier changes only at a month boundary.

		// If data is future-dated, treat as first year.
		if ($asof < $start) return 'PR1Y';

		$ann1 = $start->modify('+1 year');
		$ann2 = $start->modify('+2 year');
		if (!$ann1 || !$ann2) return 'PR1Y';

		$startPR2 = $ann1->modify('first day of next month')->setTime(0, 0, 0);
		$startPR3 = $ann2->modify('first day of next month')->setTime(0, 0, 0);
		if (!$startPR2 || !$startPR3) return 'PR1Y';

		if ($asof < $startPR2) return 'PR1Y';
		if ($asof < $startPR3) return 'PR2Y';
		return 'PR3Y';
	}

	/**
	 * Normalize citizenship for CPF purposes, deriving PR tier as-of a given date.
	 *
	 * @param  string  $citizenship  SC|PR1Y|PR2Y|PR3Y|PR|FIN|...
	 * @param  mixed   $prStartDate  Timestamp or date string
	 * @param  string  $asOfDate     'YYYY-MM-DD' (pay date)
	 * @return string                SC|PR1Y|PR2Y|PR3Y|FIN|...
	 */
	public static function normalizeCitizenshipTier($citizenship, $prStartDate, $asOfDate)
	{
		$citizenship = (string)$citizenship;
		if ($citizenship === '') return 'FIN';
		if ($citizenship === 'FIN' || $citizenship === 'SC') return $citizenship;

		// If PR in any form, derive tier using PR start date and the relevant date.
		if (strpos($citizenship, 'PR') === 0) {
			$tier = self::derivePRTier($prStartDate, $asOfDate);
			if ($tier) return $tier;
			// If caller stored only "PR" but has no start date, default to PR1Y
			if ($citizenship === 'PR') return 'PR1Y';
			return $citizenship;
		}

		return $citizenship;
	}

	/** CPF wage thresholds (SGD) — monthly OW below which different rules apply. */
	const CPF_THRESHOLD_NONE = 50;   // ≤ $50: no CPF
	const CPF_THRESHOLD_LOW  = 500;  // $50–$500: employer only
	const CPF_THRESHOLD_MID  = 750;  // $500–$750: graduated; above $750: full rate

	/**
	 * Fetch the applicable CPF rate row from the database.
	 *
	 * @param  DoliDB  $db           Database handler
	 * @param  int     $age          Employee age in years
	 * @param  string  $citizenship  SC|PR1Y|PR2Y|PR3Y|FIN
	 * @param  string  $payDate      'YYYY-MM-DD' of payment date
	 * @param  string  $wageBand     'above750'|'501to750'|'500andbelow'
	 * @return array|false           Rate row array or false on failure
	 */
	public static function getCPFRateRow($db, $age, $citizenship, $payDate, $wageBand = 'above750')
	{
		// Map FIN (Foreigner) – no CPF
		if ($citizenship === 'FIN') {
			return false;
		}
		// PR3Y uses SC rates
		$tier = ($citizenship === 'PR3Y') ? 'SC' : $citizenship;
		$band = in_array($wageBand, array('above750', '501to750', '500andbelow'), true) ? $wageBand : 'above750';

		$sql  = "SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_cpfrates";
		$sql .= " WHERE citizenship_tier = '".$db->escape($tier)."'";
		$sql .= " AND age_from <= ".(int)$age;
		$sql .= " AND (age_to = 0 OR age_to >= ".(int)$age.")";
		$sql .= " AND wage_band = '".$db->escape($band)."'";
		$sql .= " AND effective_date <= '".$db->escape($payDate)."'";
		$sql .= " ORDER BY effective_date DESC LIMIT 1";

		$res = $db->query($sql);
		if ($res && $db->num_rows($res) > 0) {
			return $db->fetch_array($res);
		}
		return false;
	}

	/**
	 * Round CPF contribution to nearest dollar per CPF Board:
	 * "Cents should be dropped for amounts less than 50 cents. Amounts of 50 cents and above
	 * should be treated as an additional dollar." Applied to employer and employee amounts separately.
	 *
	 * @param  float  $amount  Raw amount
	 * @return float  Whole-dollar amount
	 */
	protected static function roundCPFToDollar($amount)
	{
		$amount = (float) $amount;
		$dollars = floor($amount);
		$cents   = $amount - $dollars;
		if ($cents < 0.50) {
			return (float) $dollars;
		}
		return (float) ($dollars + 1);
	}

	/**
	 * CPF Board: total rounded to nearest dollar; employee rounded DOWN (cents dropped); employer = total − employee.
	 * Use after computing raw employer and raw employee from Rate Table.
	 */
	protected static function applyCPFBoardRounding($rawEmployer, $rawEmployee)
	{
		$total    = self::roundCPFToDollar($rawEmployer + $rawEmployee);
		$employee = (float) floor($rawEmployee);
		$employer = $total - $employee;
		return array('employer' => $employer, 'employee' => $employee, 'total' => $total);
	}

	/**
	 * Calculate CPF contributions with wage thresholds (per CPF Board).
	 *
	 * Thresholds: OW ≤ $50 no CPF; $50–$500 employer only; $500–$750 graduated; > $750 full rate.
	 * OW capped at monthly OW ceiling; AW capped at (Annual ceiling − OW contributed YTD).
	 *
	 * @param  DoliDB  $db             Database handler
	 * @param  float   $ordinaryWage   OW for this month (before cap)
	 * @param  float   $additionalWage AW for this month (bonus, commission, etc.)
	 * @param  int     $age            Employee age
	 * @param  string  $citizenship    SC|PR1Y|PR2Y|PR3Y|FIN
	 * @param  string  $payDate        'YYYY-MM-DD'
	 * @param  float   $owContribYTD   OW already contributed this calendar year
	 * @return array   ['employer'=>x, 'employee'=>x, 'total'=>x, 'ow_cpf_base'=>x, 'aw_cpf_base'=>x, ...]
	 */
	public static function calculateCPF($db, $ordinaryWage, $additionalWage, $age, $citizenship, $payDate, $owContribYTD = 0)
	{
		$result = array(
			'employer'             => 0, 'employee' => 0, 'total' => 0,
			'ow_cpf_base'          => 0, 'aw_cpf_base' => 0,
			'aw_ceiling_remaining' => 0, 'aw_capped' => false,
			'ow_ceiling'           => 0, 'annual_ceiling' => 0,
			'applicable'           => false,
		);

		// Freelancers / foreigners: no CPF
		if (in_array($citizenship, array('FIN', 'FREELANCER'))) {
			return $result;
		}

		$ow = (float)$ordinaryWage;
		$aw = (float)$additionalWage;

		// CPF Board: no contribution when monthly OW ≤ $50
		if ($ow <= self::CPF_THRESHOLD_NONE) {
			dol_syslog('SghrCalc::calculateCPF OW ≤ '.self::CPF_THRESHOLD_NONE.' — no CPF', LOG_DEBUG);
			return $result;
		}

		// Need full-rate row for ceiling and (when OW > 750) for calculation
		$rateFull = self::getCPFRateRow($db, $age, $citizenship, $payDate, 'above750');
		if (!$rateFull) {
			dol_syslog('SghrCalc::calculateCPF no above750 rate for age='.$age.' citizenship='.$citizenship.' date='.$payDate, LOG_WARNING);
			return $result;
		}

		$owCeiling     = (float)$rateFull['ow_ceiling'];
		$annualCeiling = (float)$rateFull['aw_annual_ceiling'];
		$owBase        = min($ow, $owCeiling);
		$awCeilingRemaining = max(0.0, $annualCeiling - (float)$owContribYTD - $owBase);
		$awBase        = min($aw, $awCeilingRemaining);
		$awWasCapped   = ($aw > $awCeilingRemaining) && $awCeilingRemaining >= 0;
		if ($awWasCapped) {
			dol_syslog('SghrCalc::calculateCPF AW capped by annual ceiling. AW='.$aw.' ceiling_remaining='.$awCeilingRemaining, LOG_INFO);
		}

		$result['ow_cpf_base']           = $owBase;
		$result['aw_cpf_base']           = $awBase;
		$result['aw_ceiling_remaining']  = $awCeilingRemaining;
		$result['aw_capped']            = $awWasCapped;
		$result['ow_ceiling']           = $owCeiling;
		$result['annual_ceiling']        = $annualCeiling;
		$result['applicable']           = true;

		// Band: $50–$500 → employer only (use 500andbelow); $500–$750 → graduated; > $750 → full. Rates from CPF Rate Table.
		// CPF Board rounding: total to nearest dollar; employee rounded down; employer = total − employee.
		if ($ow <= self::CPF_THRESHOLD_LOW) {
			$rateLow = self::getCPFRateRow($db, $age, $citizenship, $payDate, '500andbelow');
			if ($rateLow) {
				$erRate = (float)$rateLow['employer_rate'];
				$rawEr = $owBase * $erRate + $awBase * $erRate;
				$rounded = self::applyCPFBoardRounding($rawEr, 0.0);
			} else {
				$rawEr = ($owBase + $awBase) * 0.17;
				$rounded = self::applyCPFBoardRounding($rawEr, 0.0);
			}
			$employer = $rounded['employer'];
			$employee = $rounded['employee'];
			$total    = $rounded['total'];
		} elseif ($ow <= self::CPF_THRESHOLD_MID) {
			$rateLow  = self::getCPFRateRow($db, $age, $citizenship, $payDate, '500andbelow');
			$rateMid  = self::getCPFRateRow($db, $age, $citizenship, $payDate, '501to750');
			$owFirst500 = min($owBase, self::CPF_THRESHOLD_LOW);
			$owExcess   = max(0.0, $owBase - self::CPF_THRESHOLD_LOW);
			$erLow = $rateLow ? (float)$rateLow['employer_rate'] : 0.17;
			$erMid = $rateMid ? (float)$rateMid['employer_rate'] : 0.006;
			$eeMid = $rateMid ? (float)$rateMid['employee_rate'] : 0.006;
			$erFull = (float)$rateFull['employer_rate'];
			$eeFull = (float)$rateFull['employee_rate'];
			$rawEr = $owFirst500 * $erLow + $owExcess * $erMid + $awBase * $erFull;
			$rawEe = $owExcess * $eeMid + $awBase * $eeFull;
			$rounded = self::applyCPFBoardRounding($rawEr, $rawEe);
			$employer = $rounded['employer'];
			$employee = $rounded['employee'];
			$total    = $rounded['total'];
		} else {
			$erFull = (float)$rateFull['employer_rate'];
			$eeFull = (float)$rateFull['employee_rate'];
			$cpfBase = $owBase + $awBase;
			$rawEr = $cpfBase * $erFull;
			$rawEe = $cpfBase * $eeFull;
			$rounded = self::applyCPFBoardRounding($rawEr, $rawEe);
			$employer = $rounded['employer'];
			$employee = $rounded['employee'];
			$total    = $rounded['total'];
		}

		$result['employer'] = $employer;
		$result['employee'] = $employee;
		$result['total']    = $total;

		dol_syslog('SghrCalc::calculateCPF age='.$age.' citizenship='.$citizenship.' OW='.$owBase.' band='.($ow<=self::CPF_THRESHOLD_LOW?'50-500':($ow<=self::CPF_THRESHOLD_MID?'500-750':'above750')).' emp='.$employee.' er='.$employer, LOG_DEBUG);

		return $result;
	}

	/**
	 * Fetch the applicable statutory rate row from the database.
	 * Falls back to hardcoded defaults if no record found.
	 *
	 * @param  DoliDB  $db         Database handler
	 * @param  string  $rateType  SDL_RATE|SDL_CEILING|SDL_MIN|SDL_MAX|SHG_CDAC|SHG_ECF|SHG_MBMF|SHG_SINDA
	 * @param  string  $payDate    'YYYY-MM-DD' of payment date
	 * @param  string  $wageBand   Wage bracket for SHG fixed amounts (e.g. '0-2000', '5001-7500')
	 * @return array|false         Rate row array or false if not found
	 */
	public static function getStatutoryRateRow($db, $rateType, $payDate, $wageBand = '')
	{
		$sql  = "SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_statutory_rates";
		$sql .= " WHERE rate_type = '".$db->escape($rateType)."'";
		$sql .= " AND effective_date <= '".$db->escape($payDate)."'";
		if (!empty($wageBand)) {
			$sql .= " AND (wage_band = '".$db->escape($wageBand)."' OR wage_band = '')";
		}
		$sql .= " ORDER BY effective_date DESC, wage_band DESC LIMIT 1";

		$res = $db->query($sql);
		if ($res && $db->num_rows($res) > 0) {
			return $db->fetch_array($res);
		}
		return false;
	}

	/**
	 * Get wage band key for SHG lookup from wage amount.
	 *
	 * @param  float   $wage  Total monthly wage
	 * @param  string $type  SHG_CDAC|SHG_ECF|SHG_MBMF|SHG_SINDA
	 * @return string        Wage band key (e.g. '2001-3500')
	 */
	protected static function getSHGWageBand($wage, $type)
	{
		$w = (float)$wage;
		switch ($type) {
			case 'SHG_CDAC':
				if ($w <= 2000)   return '0-2000';
				if ($w <= 3500)   return '2001-3500';
				if ($w <= 5000)   return '3501-5000';
				if ($w <= 7500)   return '5001-7500';
				return '7501+';
			case 'SHG_ECF':
				if ($w <= 1000)   return '0-1000';
				if ($w <= 1500)   return '1001-1500';
				if ($w <= 2500)   return '1501-2500';
				if ($w <= 4000)   return '2501-4000';
				if ($w <= 7000)   return '4001-7000';
				if ($w <= 10000)  return '7001-10000';
				return '10001+';
			case 'SHG_MBMF':
				if ($w <= 1000)   return '0-1000';
				if ($w <= 2000)   return '1001-2000';
				if ($w <= 3000)   return '2001-3000';
				if ($w <= 4000)   return '3001-4000';
				if ($w <= 6000)   return '4001-6000';
				if ($w <= 8000)   return '6001-8000';
				if ($w <= 10000)  return '8001-10000';
				return '10001+';
			case 'SHG_SINDA':
				if ($w <= 1000)   return '0-1000';
				if ($w <= 1500)   return '1001-1500';
				if ($w <= 2500)   return '1501-2500';
				if ($w <= 4500)   return '2501-4500';
				if ($w <= 7500)   return '4501-7500';
				if ($w <= 10000)  return '7501-10000';
				if ($w <= 15000)  return '10001-15000';
				return '15001+';
			default:
				return '';
		}
	}

	/**
	 * Calculate SDL (Skills Development Levy) per employee.
	 *
	 * Rules (CPF / GoBusiness): 0.25% of monthly wages, applied to the first $4,500 only.
	 * - Min: $2.00/month when wages < $800.
	 * - Max: $11.25/month (0.25% × $4,500).
	 * For the employer's total monthly SDL (sum of all employees), rounding is applied
	 * at organisation level (e.g. rounding down the total) per official practice;
	 * use getOrganisationSDLTotalRounded() when submitting or reporting company total.
	 *
	 * Rates are loaded from llx_sgpayroll_statutory_rates (versioned by effective_date).
	 * Falls back to hardcoded defaults if no database record found.
	 *
	 * @param  DoliDB  $db         Database handler
	 * @param  float   $totalWages Monthly total wages (OW + AW) for this employee
	 * @param  string  $payDate    'YYYY-MM-DD' of payment date
	 * @return float               SDL amount (2 decimal places)
	 */
	public static function calculateSDL($db, $totalWages, $payDate = '')
	{
		$totalWages = (float)$totalWages;
		if ($totalWages <= 0) {
			return 0.00;
		}

		// Default fallback values
		$rate       = 0.0025;  // 0.25%
		$ceiling    = 4500.00;
		$minAmount  = 2.00;
		$maxAmount  = 11.25;

		// Try to load from database
		if (is_object($db) && method_exists($db, 'query')) {
			$rateRow = self::getStatutoryRateRow($db, 'SDL_RATE', $payDate);
			if ($rateRow) {
				$rate = (float)$rateRow['rate_value'] / 100; // Stored as percentage (e.g. 0.25 for 0.25%)
			}

			$ceilRow = self::getStatutoryRateRow($db, 'SDL_CEILING', $payDate);
			if ($ceilRow) {
				$ceiling = (float)$ceilRow['ceiling'];
			}

			$minRow = self::getStatutoryRateRow($db, 'SDL_MIN', $payDate);
			if ($minRow) {
				$minAmount = (float)$minRow['min_amount'];
			}

			$maxRow = self::getStatutoryRateRow($db, 'SDL_MAX', $payDate);
			if ($maxRow) {
				$maxAmount = (float)$maxRow['max_amount'];
			}
		}

		$sdl = min($totalWages, $ceiling) * $rate;
		if ($totalWages < 800) {
			$sdl = max($sdl, $minAmount);
		}
		$sdl = min($sdl, $maxAmount);
		return round($sdl, 2);
	}

	/**
	 * Organisation-level SDL total for employer's monthly submission.
	 *
	 * Official practice (MOM/SSG): round down the total SDL for the whole
	 * organisation to the nearest dollar (cents dropped). Use when reporting
	 * or submitting the employer's total monthly SDL (e.g. CPFEzPay / GoBusiness).
	 *
	 * @param  float  $sumOfEmployeeSDL  Sum of per-employee sdl_amount for the month
	 * @return float                     Total rounded down to whole dollars
	 */
	public static function getOrganisationSDLTotalRounded($sumOfEmployeeSDL)
	{
		$sum = (float)$sumOfEmployeeSDL;
		// Official: round down to nearest dollar (drop cents)
		return (float) floor($sum);
	}

	/**
	 * Calculate SHG (Self-Help Group) contributions.
	 *
	 * Each fund is keyed to race/religion. All amounts are monthly fixed brackets.
	 * Employee can opt out per fund.
	 *
	 * Rates are loaded from llx_sgpayroll_statutory_rates (versioned by effective_date).
	 * Falls back to hardcoded defaults if no database record found.
	 *
	 * @param  DoliDB  $db         Database handler
	 * @param  float   $totalWages Monthly total wages
	 * @param  string  $race       Chinese|Malay|Indian|Eurasian|Others
	 * @param  bool    $isMuslim  MBMF applies to Muslims
	 * @param  array   $optOut    ['cdac'=>bool, 'ecf'=>bool, 'mbmf'=>bool, 'sinda'=>bool]
	 * @param  string  $payDate   'YYYY-MM-DD' of payment date
	 * @return array   ['cdac'=>x, 'ecf'=>x, 'mbmf'=>x, 'sinda'=>x, 'total'=>x]
	 */
	public static function calculateSHG($db, $totalWages, $race, $isMuslim, $optOut = array(), $payDate = '')
	{
		$w = (float)$totalWages;
		$result = array('cdac' => 0, 'ecf' => 0, 'mbmf' => 0, 'sinda' => 0, 'total' => 0);

		// Default SHG amounts (fallback if no DB record)
		$cdacDefaults = array(
			'0-2000' => 0.50, '2001-3500' => 1.00, '3501-5000' => 1.50,
			'5001-7500' => 2.00, '7501+' => 3.00,
		);
		$ecfDefaults = array(
			'0-1000' => 2.00, '1001-1500' => 4.00, '1501-2500' => 6.00,
			'2501-4000' => 9.00, '4001-7000' => 12.00, '7001-10000' => 16.00, '10001+' => 20.00,
		);
		$mbmfDefaults = array(
			'0-1000' => 3.00, '1001-2000' => 4.50, '2001-3000' => 6.50,
			'3001-4000' => 15.00, '4001-6000' => 19.50, '6001-8000' => 22.00,
			'8001-10000' => 24.00, '10001+' => 26.00,
		);
		$sindaDefaults = array(
			'0-1000' => 1.00, '1001-1500' => 3.00, '1501-2500' => 5.00,
			'2501-4500' => 7.00, '4501-7500' => 9.00, '7501-10000' => 12.00,
			'10001-15000' => 18.00, '15001+' => 30.00,
		);


		// CDAC – Chinese
		if ($race === 'Chinese' && empty($optOut['cdac'])) {
			$band = self::getSHGWageBand($w, 'SHG_CDAC');
			$row = self::getStatutoryRateRow($db, 'SHG_CDAC', $payDate, $band);
			$result['cdac'] = $row ? (float)$row['rate_value'] : ($cdacDefaults[$band] ?? 0);
		}

		// ECF – Eurasian
		if ($race === 'Eurasian' && empty($optOut['ecf'])) {
			$band = self::getSHGWageBand($w, 'SHG_ECF');
			$row = self::getStatutoryRateRow($db, 'SHG_ECF', $payDate, $band);
			$result['ecf'] = $row ? (float)$row['rate_value'] : ($ecfDefaults[$band] ?? 0);
		}

		// MBMF – Muslim employees (any race)
		if ($isMuslim && empty($optOut['mbmf'])) {
			$band = self::getSHGWageBand($w, 'SHG_MBMF');
			$row = self::getStatutoryRateRow($db, 'SHG_MBMF', $payDate, $band);
			$result['mbmf'] = $row ? (float)$row['rate_value'] : ($mbmfDefaults[$band] ?? 0);
		}

		// SINDA – Indian
		if ($race === 'Indian' && empty($optOut['sinda'])) {
			$band = self::getSHGWageBand($w, 'SHG_SINDA');
			$row = self::getStatutoryRateRow($db, 'SHG_SINDA', $payDate, $band);
			$result['sinda'] = $row ? (float)$row['rate_value'] : ($sindaDefaults[$band] ?? 0);
		}

		$result['total'] = $result['cdac'] + $result['ecf'] + $result['mbmf'] + $result['sinda'];
		return $result;
	}

	/**
	 * Calculate Unpaid Leave (UPL) deduction.
	 *
	 * Formula (MOM): Deduction = Basic Salary / Working Days in Month × UPL Days
	 *
	 * @param  float  $basicSalary        Monthly basic salary
	 * @param  int    $workingDaysInMonth  Calendar working days this month
	 * @param  float  $uplDays            Number of unpaid leave days
	 * @return float  Deduction amount
	 */
	public static function calculateUnpaidLeaveDeduction($basicSalary, $workingDaysInMonth, $uplDays)
	{
		$workingDaysInMonth = max(0.001, (float)$workingDaysInMonth);
		if ($uplDays <= 0 || $basicSalary <= 0) {
			return 0.00;
		}
		return round(((float)$basicSalary / $workingDaysInMonth) * (float)$uplDays, 2);
	}

	/**
	 * Calculate Annual Leave (AL) encashment from number of days.
	 * Same daily rate as UPL: Basic Salary / Working Days in Month × AL days encashed.
	 *
	 * @param  float  $basicSalary        Monthly basic salary (SGD)
	 * @param  float  $workingDaysInMonth  Working days in the month
	 * @param  float  $alDays             Number of AL days to encash
	 * @return float  Encashment amount (2 decimals)
	 */
	public static function calculateALEncashment($basicSalary, $workingDaysInMonth, $alDays)
	{
		$workingDaysInMonth = max(0.001, (float)$workingDaysInMonth);
		if ($alDays <= 0 || $basicSalary <= 0) {
			return 0.00;
		}
		return round(((float)$basicSalary / $workingDaysInMonth) * (float)$alDays, 2);
	}

	/**
	 * Calculate Overtime (OT) pay.
	 *
	 * MOM formula: OT pay = Hourly Basic Rate × OT multiplier × OT hours
	 * Hourly basic rate = Monthly Basic Salary / (52 × 44 / 12)  [MOM formula]
	 * Note: Employees earning >$2,600/month are not entitled to MOM OT pay regime,
	 * but employers may still choose to pay. Flag is handled in UI.
	 *
	 * @param  float  $monthlyBasicSalary  Employee's monthly basic salary
	 * @param  float  $otHours             OT hours worked
	 * @param  float  $multiplier          1.0 | 1.5 | 2.0 | 3.0
	 * @return array  ['hourly_rate'=>x, 'ot_pay'=>x]
	 */
	public static function calculateOvertimePay($monthlyBasicSalary, $otHours, $multiplier = 1.5)
	{
		if ($otHours <= 0 || $monthlyBasicSalary <= 0) {
			return array('hourly_rate' => 0, 'ot_pay' => 0);
		}
		// MOM hourly rate = Monthly Basic / (52 × 44 / 12) = Monthly Basic / 190.67
		$hourlyRate = round((float)$monthlyBasicSalary / 190.6667, 4);
		$otPay      = round($hourlyRate * (float)$multiplier * (float)$otHours, 2);
		return array('hourly_rate' => $hourlyRate, 'ot_pay' => $otPay);
	}

	/**
	 * Calculate Overtime by MOM-defined day types (Singapore Employment Act).
	 *
	 * MOM OT multipliers (Section 37–38 Employment Act):
	 *  - WD  (Ordinary Workday):  HBR × 1.5 × hours
	 *  - REST (Rest Day):         HBR × 1.5 × hours  (≤ normal day hours; >normal day = 2.0)
	 *  - PH  (Public Holiday):    HBR × 2.0 × hours
	 *
	 * Note: Employees earning > $2,600/mth are exempt from EA OT provisions,
	 * but employers may still choose to compensate; flag this in UI.
	 *
	 * @param  float  $monthlyBasicSalary
	 * @param  float  $otHoursWD   OT hours on ordinary workdays
	 * @param  float  $otHoursREST OT hours on rest days
	 * @param  float  $otHoursPH   OT hours on public holidays
	 * @return array  [
	 *   'hourly_rate'  => float,   // MOM basic hourly rate
	 *   'wd_pay'       => float,   // WD OT pay (1.5x)
	 *   'rest_pay'     => float,   // Rest Day OT pay (1.5x)
	 *   'ph_pay'       => float,   // Public Holiday OT pay (2.0x)
	 *   'total_ot_pay' => float,   // Sum of all OT pay
	 *   'total_hours'  => float,   // Total OT hours
	 *   'wd_hours'     => float,
	 *   'rest_hours'   => float,
	 *   'ph_hours'     => float,
	 * ]
	 */
	public static function calculateOvertimeByType($monthlyBasicSalary, $otHoursWD = 0, $otHoursREST = 0, $otHoursPH = 0)
	{
		$hourlyRate = ($monthlyBasicSalary > 0) ? round((float)$monthlyBasicSalary / 190.6667, 4) : 0;

		$wdPay   = round($hourlyRate * 1.5 * (float)$otHoursWD,   2);
		$restPay = round($hourlyRate * 1.5 * (float)$otHoursREST, 2);
		$phPay   = round($hourlyRate * 2.0 * (float)$otHoursPH,   2);

		return array(
			'hourly_rate'  => $hourlyRate,
			'wd_pay'       => $wdPay,
			'rest_pay'     => $restPay,
			'ph_pay'       => $phPay,
			'total_ot_pay' => round($wdPay + $restPay + $phPay, 2),
			'total_hours'  => (float)$otHoursWD + (float)$otHoursREST + (float)$otHoursPH,
			'wd_hours'     => (float)$otHoursWD,
			'rest_hours'   => (float)$otHoursREST,
			'ph_hours'     => (float)$otHoursPH,
		);
	}

	/**
	 * Return Singapore Public Holidays for a given year.
	 *
	 * Reads from Dolibarr dictionary llx_c_hrm_public_holiday (Setup → Dictionary → Public holidays)
	 * for country Singapore (SG). You can maintain dates per year there; payroll uses them for
	 * working-day count and PH OT rate. If no dictionary entries exist, falls back to built-in list.
	 * Where a PH falls on Sunday, the next Monday is a substitute holiday (MOM rule).
	 *
	 * @param  int $year Calendar year (e.g. 2025)
	 * @return array List of 'YYYY-MM-DD' date strings
	 */
	public static function getSingaporePublicHolidays($year)
	{
		global $db;
		$fromDb = self::getSingaporePublicHolidaysFromDict($db, $year);
		if (!empty($fromDb)) {
			return $fromDb;
		}
		return self::getSingaporePublicHolidaysFallback($year);
	}

	/**
	 * Load Singapore public holidays for a year from llx_c_hrm_public_holiday (country = SG).
	 * Resolves dayrule (easter, goodfriday, etc.) and applies Sunday → Monday substitute.
	 *
	 * @param  DoliDB $db  Database handler (can be null; then returns empty)
	 * @param  int    $year Calendar year
	 * @return array  List of 'YYYY-MM-DD' or empty if not configured
	 */
	public static function getSingaporePublicHolidaysFromDict($db, $year)
	{
		if (!is_object($db) || !method_exists($db, 'query')) {
			return array();
		}
		$sgId = dol_getIdFromCode($db, 'SG', 'c_country', 'code', 'rowid');
		if (empty($sgId)) {
			return array();
		}
		$sql = "SELECT id, code, dayrule, year, month, day";
		$sql .= " FROM ".MAIN_DB_PREFIX."c_hrm_public_holiday";
		$sql .= " WHERE active = 1 AND fk_country IN (0, ".(int)$sgId.")";
		$sql .= " AND (year = 0 OR year = ".(int)$year.")";
		$sql .= " AND entity IN (0, ".getEntity('c_hrm_public_holiday').")";
		$resql = $db->query($sql);
		if (!$resql) {
			return array();
		}
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$all = array();
		while ($obj = $db->fetch_object($resql)) {
			$y = !empty($obj->year) ? (int)$obj->year : $year;
			$m = (int)$obj->month;
			$d = (int)$obj->day;
			$dayrule = trim($obj->dayrule ?? '');
			if ($dayrule !== '' && $dayrule !== 'date') {
				$ts = self::resolveDayruleToTimestamp($dayrule, $y);
				if ($ts !== null) {
					$all[] = date('Y-m-d', $ts);
				}
			} elseif ($m >= 1 && $m <= 12 && $d >= 1 && $d <= 31) {
				$all[] = sprintf('%04d-%02d-%02d', $y, $m, $d);
			}
		}
		// Substitute: PH on Sunday → add following Monday
		$result = array();
		foreach ($all as $dateStr) {
			$result[] = $dateStr;
			$ts = strtotime($dateStr);
			if ((int)date('w', $ts) === 0) {
				$result[] = date('Y-m-d', strtotime('+1 day', $ts));
			}
		}
		$result = array_unique($result);
		sort($result);
		return $result;
	}

	/**
	 * Resolve a dayrule (easter, goodfriday, etc.) to a timestamp in the given year.
	 * Matches logic in core date.lib.php num_public_holiday().
	 *
	 * @param  string $dayrule e.g. 'goodfriday', 'eastermonday'
	 * @param  int    $year    Year
	 * @return int|null       Unix timestamp or null
	 */
	protected static function resolveDayruleToTimestamp($dayrule, $year)
	{
		if (!function_exists('getGMTEasterDatetime')) {
			require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		}
		$easter = getGMTEasterDatetime($year);
		$dayrule = strtolower($dayrule);
		switch ($dayrule) {
			case 'easter':
				return $easter;
			case 'eastermonday':
				return $easter + 86400;
			case 'goodfriday':
				return $easter - (2 * 86400);
			case 'ascension':
				return $easter + (39 * 86400);
			case 'pentecost':
				return $easter + (49 * 86400);
			case 'pentecotemonday':
				return $easter + (50 * 86400);
			case 'viernessanto':
				return $easter - (2 * 86400);
			case 'fronleichnam':
				return $easter + (60 * 86400);
			default:
				return null;
		}
	}

	/**
	 * Fallback list when dictionary has no Singapore public holidays (same as former hardcoded list).
	 *
	 * @param  int $year Calendar year
	 * @return array List of 'YYYY-MM-DD'
	 */
	protected static function getSingaporePublicHolidaysFallback($year)
	{
		$fixed = array(
			sprintf('%04d-01-01', $year),
			sprintf('%04d-05-01', $year),
			sprintf('%04d-08-09', $year),
			sprintf('%04d-12-25', $year),
		);
		$variable = array(
			2024 => array('2024-02-10', '2024-02-11', '2024-03-29', '2024-04-10', '2024-05-22', '2024-06-17', '2024-10-31'),
			2025 => array('2025-01-29', '2025-01-30', '2025-03-31', '2025-04-18', '2025-05-12', '2025-06-07', '2025-10-20'),
			2026 => array('2026-02-17', '2026-02-18', '2026-03-20', '2026-04-03', '2026-05-31', '2026-05-28', '2026-11-08'),
		);
		$yearDates = isset($variable[$year]) ? $variable[$year] : array();
		$all = array_merge($fixed, $yearDates);
		$result = array();
		foreach ($all as $dateStr) {
			$result[] = $dateStr;
			$ts = strtotime($dateStr);
			if ((int)date('w', $ts) === 0) {
				$result[] = date('Y-m-d', strtotime('+1 day', $ts));
			}
		}
		$result = array_unique($result);
		sort($result);
		return $result;
	}

	/**
	 * Check if a given date is a Singapore Public Holiday.
	 *
	 * @param  string $date  'YYYY-MM-DD'
	 * @return bool
	 */
	public static function isSingaporePublicHoliday($date)
	{
		$year = (int)substr($date, 0, 4);
		return in_array($date, self::getSingaporePublicHolidays($year), true);
	}

	/**
	 * Fetch approved Unpaid Leave (UPL) days from core Dolibarr Holiday module.
	 *
	 * @param  DoliDB  $db     Database handler
	 * @param  int     $userId Dolibarr user ID
	 * @param  int     $year   Year
	 * @param  int     $month  Month
	 * @return float           Total UPL days
	 */
	public static function getUplDaysFromHRM($db, $userId, $year, $month)
	{
		$total = 0;
		$startDate = sprintf('%04d-%02d-01', $year, $month);
		$endDate   = date('Y-m-t', strtotime($startDate));

		$sql  = "SELECT h.date_debut, h.date_fin, h.halfday";
		$sql .= " FROM ".MAIN_DB_PREFIX."holiday h";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_holiday_types t ON t.rowid = h.fk_type";
		$sql .= " WHERE h.fk_user = ".(int)$userId;
		$sql .= " AND t.code = 'UPL'";
		$sql .= " AND h.statut = 3"; // Approved
		$sql .= " AND (";
		$sql .= "      (h.date_debut >= '".$db->idate(strtotime($startDate))."' AND h.date_debut <= '".$db->idate(strtotime($endDate))."')";
		$sql .= "   OR (h.date_fin >= '".$db->idate(strtotime($startDate))."' AND h.date_fin <= '".$db->idate(strtotime($endDate))."')";
		$sql .= "   OR (h.date_debut < '".$db->idate(strtotime($startDate))."' AND h.date_fin > '".$db->idate(strtotime($endDate))."')";
		$sql .= " )";

		$res = $db->query($sql);
		if ($res) {
			require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
			while ($obj = $db->fetch_object($res)) {
				// We use the holiday class's logic for calculating days if possible, 
				// but here we need to slice it by month.
				$start = max(strtotime($startDate), $db->jdate($obj->date_debut));
				$end   = min(strtotime($endDate), $db->jdate($obj->date_fin));
				
				if ($start <= $end) {
					// Simple day difference for now. 
					// Note: core holiday system handles half days. 
					// If start and end are same and halfday > 0, it's 0.5.
					$days = (($end - $start) / 86400) + 1;
					if ($obj->halfday > 0 && $db->jdate($obj->date_debut) == $db->jdate($obj->date_fin)) {
						$days = 0.5;
					}
					$total += $days;
				}
			}
		}

		return (float)$total;
	}

	/**
	 * Count working days in a month based on the weekly schedule.
	 *
	 * @param  int     $year                   Year
	 * @param  int     $month                  Month
	 * @param  string  $schedule               Schedule string (e.g. "Mon:1;Tue:1;Wed:0.5")
	 * @param  bool    $excludePublicHolidays  If true, PH on schedule days are excluded; if false, PH count as working days (denominator rule: 公假加回去)
	 * @return float   Total working days (can be partial)
	 */
	public static function countWorkingDays($year, $month, $schedule = '', $excludePublicHolidays = true)
	{
		$phList = $excludePublicHolidays ? self::getSingaporePublicHolidays($year) : array();
		$days   = cal_days_in_month(CAL_GREGORIAN, $month, $year);

		// Parse schedule
		$schedArr = array();
		if ($schedule) {
			if (strpos($schedule, ':') !== false) {
				$pairs = explode(';', $schedule);
				foreach ($pairs as $p) {
					$parts = explode(':', $p);
					if (count($parts) == 2) {
						$schedArr[$parts[0]] = (float)$parts[1];
					}
				}
			} else {
				$daysArr = explode(',', $schedule);
				foreach ($daysArr as $d) {
					$d = trim($d);
					if ($d) $schedArr[$d] = 1.0;
				}
			}
		} else {
			$schedArr = array('Mon'=>1, 'Tue'=>1, 'Wed'=>1, 'Thu'=>1, 'Fri'=>1);
		}

		$total = 0;
		for ($d = 1; $d <= $days; $d++) {
			$time = mktime(0, 0, 0, $month, $d, $year);
			$date = date('Y-m-d', $time);
			$dayName = date('D', $time);
			if (in_array($date, $phList, true)) {
				continue;
			}
			$val = $schedArr[$dayName] ?? 0;
			if ($val > 0) {
				$total += $val;
			}
		}
		return (float)$total;
	}

	/**
	 * Count working days in a date range based on weekly schedule (and exclude PH).
	 * Used for prorated months when employee starts/ends mid-month.
	 *
	 * @param  string  $startDate  'Y-m-d'
	 * @param  string  $endDate    'Y-m-d' (inclusive)
	 * @param  string  $schedule   Same as countWorkingDays (e.g. "Mon,Wed,Fri" or "Mon:1;Wed:1;Fri:1")
	 * @param  array   $phList     Optional list of 'Y-m-d' PH dates; if null, fetched for year of startDate
	 * @return float
	 */
	public static function countWorkingDaysInRange($startDate, $endDate, $schedule = '', $phList = null)
	{
		if ($phList === null) {
			$year = (int) substr($startDate, 0, 4);
			$phList = self::getSingaporePublicHolidays($year);
		}
		$schedArr = array();
		if ($schedule) {
			if (strpos($schedule, ':') !== false) {
				$pairs = explode(';', $schedule);
				foreach ($pairs as $p) {
					$parts = explode(':', $p);
					if (count($parts) == 2) {
						$schedArr[trim($parts[0])] = (float)$parts[1];
					}
				}
			} else {
				$daysArr = explode(',', $schedule);
				foreach ($daysArr as $d) {
					$d = trim($d);
					if ($d) $schedArr[$d] = 1.0;
				}
			}
		} else {
			$schedArr = array('Mon'=>1, 'Tue'=>1, 'Wed'=>1, 'Thu'=>1, 'Fri'=>1);
		}
		$total = 0;
		$current = strtotime($startDate);
		$endTS = strtotime($endDate);
		while ($current <= $endTS) {
			$date = date('Y-m-d', $current);
			$dayName = date('D', $current);
			if (!in_array($date, $phList, true)) {
				$val = isset($schedArr[$dayName]) ? $schedArr[$dayName] : 0;
				if ($val > 0) $total += $val;
			}
			$current = strtotime('+1 day', $current);
		}
		return (float)$total;
	}

	/**
	 * Calculate withholding tax for non-resident employees.
	 *
	 * Non-residents without director fee: 15% flat on employment income
	 * (or resident rates if that gives a higher tax – whichever is higher,
	 *  but for simplicity we flag for manual review).
	 *
	 * @param  float   $grossEmploymentIncome
	 * @param  string  $taxResidency   'resident' | 'non_resident'
	 * @return float   Withholding tax amount
	 */
	public static function calculateWithholdingTax($grossEmploymentIncome, $taxResidency)
	{
		if ($taxResidency !== 'non_resident') {
			return 0.00;
		}
		return round((float)$grossEmploymentIncome * 0.15, 2);
	}

	/**
	 * Calculate gross salary.
	 *
	 * @param  float  $proratedBasic   Basic after UPL deduction
	 * @param  float  $allowancesTotal Fixed + ad-hoc allowances
	 * @param  float  $awTotal         Additional Wages (OT, bonus, commission, etc.)
	 * @return float
	 */
	public static function calculateGross($proratedBasic, $allowancesTotal, $awTotal)
	{
		return round((float)$proratedBasic + (float)$allowancesTotal + (float)$awTotal, 2);
	}

	/**
	 * Calculate net pay.
	 *
	 * @param  float  $gross            Gross salary
	 * @param  float  $employeeCPF      Employee CPF contribution
	 * @param  float  $shgTotal         Total SHG donation deductions
	 * @param  float  $withholdingTax   Withholding tax (non-residents)
	 * @param  float  $otherDeductions  Advance recovery, etc.
	 * @param  float  $claimsTotal      Approved expense reimbursements (added back)
	 * @return float
	 */
	public static function calculateNetPay($gross, $employeeCPF, $shgTotal, $withholdingTax, $otherDeductions, $claimsTotal)
	{
		$net = (float)$gross
			 - (float)$employeeCPF
			 - (float)$shgTotal
			 - (float)$withholdingTax
			 - (float)$otherDeductions
			 + (float)$claimsTotal;
		return round(max(0, $net), 2);
	}

	/**
	 * Calculate Foreign Worker Levy (FWL).
	 *
	 * FWL is a flat monthly levy per worker, NOT a percentage.
	 * Rates vary by sector (Services, Process, Marine, Construction, Manufacturing)
	 * and skill level (R1/R2 for Work Permit, S-Pass levy).
	 *
	 * @param  string  $citizenship    SC|PR1Y|PR2Y|PR3Y|FIN
	 * @param  string  $passType       EP|SP|WP|LTVP|None
	 * @param  string  $fwlSector      Services|Construction|Process|Marine|Manufacturing
	 * @param  string  $skillLevel     R1|R2|Higher-skilled|Basic-skilled (for WP)
	 * @return float   FWL monthly amount in SGD
	 */
	public static function calculateFWL($citizenship, $passType, $fwlSector = '', $skillLevel = '')
	{
		// FWL only applies to foreign workers on WP or S-Pass
		if (!in_array($citizenship, array('FIN'))) {
			return 0.00;
		}
		if (!in_array(strtoupper($passType), array('WP', 'SP', 'S-PASS'))) {
			return 0.00; // EP holders are exempt from FWL
		}

		$fwlSector = ucfirst(strtolower($fwlSector));

		// S-Pass levy (effective Jan 2025)
		if (in_array(strtoupper($passType), array('SP', 'S-PASS'))) {
			$spassRates = array(
				'Services'      => 550,
				'Construction'  => 550,
				'Process'       => 550,
				'Marine'        => 550,
				'Manufacturing' => 550,
			);
			return (float)($spassRates[$fwlSector] ?? 550);
		}

		// WP levy by sector and skill level (2025 rates)
		$wpRates = array(
			'Services' => array(
				'R1' => 700, 'R2' => 950,
				'Higher-skilled' => 700, 'Basic-skilled' => 950,
			),
			'Construction' => array(
				'R1' => 250, 'R2' => 750,
				'Higher-skilled' => 250, 'Basic-skilled' => 750,
			),
			'Process' => array(
				'R1' => 250, 'R2' => 750,
				'Higher-skilled' => 250, 'Basic-skilled' => 750,
			),
			'Marine' => array(
				'R1' => 250, 'R2' => 400,
				'Higher-skilled' => 250, 'Basic-skilled' => 400,
			),
			'Manufacturing' => array(
				'R1' => 250, 'R2' => 750,
				'Higher-skilled' => 250, 'Basic-skilled' => 750,
			),
		);

		$sectorRates = $wpRates[$fwlSector] ?? $wpRates['Services'];
		$level = empty($skillLevel) ? 'R2' : $skillLevel;
		return (float)($sectorRates[$level] ?? $sectorRates['R2']);
	}

	/**
	 * Convert a foreign-currency amount to SGD.
	 *
	 * Uses Dolibarr's multi-currency tables if available, otherwise falls back
	 * to the exchange_rate stored on the employee profile.
	 *
	 * Dolibarr table rate: company (SGD) amount = foreign amount × rate (same as invoice multicurrency_tx).
	 * Manual profile rate: foreign currency units per 1 SGD (e.g. 5 → 1 SGD = 5 CNY → SGD = CNY ÷ 5).
	 *
	 * @param  DoliDB  $db
	 * @param  float   $amount        Amount in foreign currency
	 * @param  string  $fromCurrency  ISO 4217 code (e.g. USD, CNY)
	 * @param  float   $manualRate    Manual rate: FCY per 1 SGD (employee profile fallback)
	 * @param  string  $payDate       'YYYY-MM-DD' to find closest rate
	 * @return array   ['sgd_amount'=>float, 'rate'=>float, 'source'=>string]
	 */
	public static function convertToSGD($db, $amount, $fromCurrency, $manualRate = 1.0, $payDate = '')
	{
		$amount = (float)$amount;
		if ($fromCurrency === 'SGD' || empty($fromCurrency)) {
			return array('sgd_amount' => $amount, 'rate' => 1.0, 'source' => 'base');
		}

		// Try Dolibarr multicurrency module rates first
		$rate   = 0;
		$source = 'manual';
		if (isModEnabled('multicurrency')) {
			$sql  = "SELECT rate FROM ".MAIN_DB_PREFIX."multicurrency_rate";
			$sql .= " WHERE fk_multicurrency = ("
			      . "   SELECT rowid FROM ".MAIN_DB_PREFIX."multicurrency"
			      . "   WHERE code = '".$db->escape($fromCurrency)."' LIMIT 1"
			      . " )";
			if ($payDate) {
				$sql .= " AND date_sync <= '".$db->escape($payDate)."'";
			}
			$sql .= " ORDER BY date_sync DESC LIMIT 1";
			$res = $db->query($sql);
			if ($res && $db->num_rows($res) > 0) {
				$obj  = $db->fetch_object($res);
				$rate = (float)$obj->rate;
				$source = 'dolibarr_multicurrency';
			}
		}

		// Fallback to manual rate from employee profile
		if ($rate <= 0) {
			$rate = (float)$manualRate > 0 ? (float)$manualRate : 1.0;
			$source = 'manual';
		}

		if ($source === 'dolibarr_multicurrency') {
			$sgdAmount = round($amount * $rate, 2);
		} else {
			// manual: rate = units of FCY for 1 SGD
			$sgdAmount = ($rate > 0) ? round($amount / $rate, 2) : round($amount, 2);
		}
		return array('sgd_amount' => $sgdAmount, 'rate' => $rate, 'source' => $source);
	}

	/**
	 * Run full payroll computation for a single employee.
	 *
	 * @param  DoliDB  $db
	 * @param  array   $emp        Employee data row (from sgpayroll_employee + user)
	 * @param  array   $inputs     [
	 *                               'allowances'       => array of {label, amount, cpf_liable},
	 *                               'bonus'            => float,
	 *                               'commission'       => float,
	 *                               'ot_hours'         => float,
	 *                               'ot_multiplier'    => float,
	 *                               'al_days_encash'   => float (optional; if set, al_encashment is computed from days),
	 *                               'al_encashment'    => float (used when al_days_encash not set),
	 *                               'other_aw'         => float,
	 *                               'upl_days'         => float,
	 *                               'work_days'        => int,
	 *                               'advance_recovery' => float,
	 *                               'other_deductions' => float,
	 *                               'claims_total'     => float,
	 *                               'bik_value'        => float,
	 *                               'ow_contrib_ytd'   => float,
	 *                               'pay_date'         => 'YYYY-MM-DD',
	 *                             ]
	 * @return array   Full payroll line computation result
	 */
	public static function computePayrollLine($db, $emp, $inputs)
	{
		// ── Robust property access (handle object or array) ────────────────
		$get = function($k, $default = null) use ($emp) {
			if (is_object($emp)) return isset($emp->$k) ? $emp->$k : $default;
			return isset($emp[$k]) ? $emp[$k] : $default;
		};

		// ── Multi-currency: convert contract salary to SGD ──────────
		$contractCurrency = $get('contract_currency', 'SGD');
		$contractSalary   = (float)$get('contract_salary', 0);
		$manualRate       = isset($inputs['exchange_rate']) ? (float)$inputs['exchange_rate'] : (float)$get('exchange_rate', 1.0);
		$payDate          = !empty($inputs['pay_date']) ? $inputs['pay_date'] : date('Y-m-d');

		// If employee has a foreign-currency contract, convert to SGD
		if ($contractCurrency !== 'SGD' && $contractSalary > 0) {
			$fx = self::convertToSGD($db, $contractSalary, $contractCurrency, $manualRate, $payDate);
			$basicSalary  = $fx['sgd_amount'];
			$fxRate       = $fx['rate'];
			$fxSource     = $fx['source'];
		} else {
			$basicSalary  = (float)$get('basic_salary', 0);
			$fxRate       = 1.0;
			$fxSource     = 'base';
		}

		// 如果前端有传入手动Basic Salary（仅用于草稿态调整），则优先使用该值参与后续所有计算。
		// 这样在draft状态下用户可以微调基本工资，而不必修改员工主档。
		if (isset($inputs['basic_salary']) && $inputs['basic_salary'] !== '' && $inputs['basic_salary'] !== null) {
			$basicSalary = (float)$inputs['basic_salary'];
		}

		$citizenship   = $get('citizenship', 'FIN');
		$prStartDate   = $get('pr_start_date', null);
		$taxResidency  = $get('tax_residency', 'resident');
		$race          = $get('race', '');
		$isMuslim      = (int)$get('is_muslim', 0);
		$optOut        = array(
			'cdac'  => (int)$get('shg_opt_out_cdac', 0),
			'ecf'   => (int)$get('shg_opt_out_ecf', 0),
			'mbmf'  => (int)$get('shg_opt_out_mbmf', 0),
			'sinda' => (int)$get('shg_opt_out_sinda', 0),
		);
		$workDays      = (float)($inputs['work_days'] ?? 26);
		// CPF age: use payslip belonging year/month (not actual payDate which may be any date)
		// Extract from payDate format 'YYYY-MM-DD'
		$payYearFromDate  = (int)substr($payDate, 0, 4);
		$payMonthFromDate = (int)substr($payDate, 5, 2);
		$age              = self::calcAge($get('dob'), $payYearFromDate, $payMonthFromDate);

		// --- UPL deduction: auto-fetch from HRM if no manual override provided
		$fkUser   = (int)$get('fk_user', 0);
		$payMonth = substr($payDate, 0, 7); // 'YYYY-MM'
		// 0. Auto-fetch Unpaid Leave (UPL) from HRM if not manually overridden
		if (!isset($inputs['upl_days']) || $inputs['upl_days'] === null || $inputs['upl_days'] === '') {
			$payTimestamp = strtotime($payDate);
			$payYear  = (int)date('Y', $payTimestamp);
			$payMonth = (int)date('m', $payTimestamp);
			$inputs['upl_days'] = self::getUplDaysFromHRM($db, $fkUser, $payYear, $payMonth);
		}

		$uplDays       = (float)($inputs['upl_days'] ?? 0);
		$uplDeduction   = self::calculateUnpaidLeaveDeduction($basicSalary, $workDays, $uplDays);
		$proratedSalary = max(0, $basicSalary - $uplDeduction);

		// --- Allowances
		$allowances = $inputs['allowances'] ?? array();
		$allowancesTotal = 0;
		foreach ($allowances as $a) {
			$allowancesTotal += (float)($a['amount'] ?? 0);
		}

		// --- OT Pay
		$otResult  = self::calculateOvertimePay($basicSalary, (float)($inputs['ot_hours'] ?? 0), (float)($inputs['ot_multiplier'] ?? 1.5));
		$otPay     = $otResult['ot_pay'];

		// --- Additional Wages total (AL encashment: from days or direct amount)
		$bonus       = (float)($inputs['bonus'] ?? 0);
		$commission  = (float)($inputs['commission'] ?? 0);
		$alDaysEncash = (float)($inputs['al_days_encash'] ?? 0);
		if ($alDaysEncash > 0) {
			$alEncash = self::calculateALEncashment($basicSalary, $workDays, $alDaysEncash);
		} else {
			$alEncash = (float)($inputs['al_encashment'] ?? 0);
		}
		$otherAW     = (float)($inputs['other_aw'] ?? 0);
		$awTotal     = $bonus + $commission + $otPay + $alEncash + $otherAW;

		// --- Gross
		$gross = self::calculateGross($proratedSalary, $allowancesTotal, $awTotal);

		// --- CPF
		// OW = prorated basic + CPF-liable allowances
		$owForCPF = $proratedSalary;
		foreach ($allowances as $a) {
			if (!empty($a['cpf_liable'])) {
				$owForCPF += (float)($a['amount'] ?? 0);
			}
		}
		$awForCPF = $bonus + $commission + $otPay + $alEncash + $otherAW;
		$owContribYTD = (float)($inputs['ow_contrib_ytd'] ?? 0);
		$citizenshipForCPF = self::normalizeCitizenshipTier($citizenship, $prStartDate, $payDate);
		$cpf = self::calculateCPF($db, $owForCPF, $awForCPF, $age, $citizenshipForCPF, $payDate, $owContribYTD);

		// --- SDL (total wages incl. allowances)
		$sdl = self::calculateSDL($db, $gross, $payDate);

		// --- FWL (Foreign Worker Levy)
		$fwlSector   = getDolGlobalString('SGHR_FWL_SECTOR');
		$skillLevel  = $get('skill_level', '');
		$passType    = $get('pass_type', '');
		$fwl = self::calculateFWL($citizenship, $passType, $fwlSector, $skillLevel);

		// --- SHG
		$shg = self::calculateSHG($db, $gross, $race, $isMuslim, $optOut, $payDate);

		// --- Withholding tax
		$withTax = self::calculateWithholdingTax($gross, $taxResidency);

		// --- Deductions
		$advanceRecovery = (float)($inputs['advance_recovery'] ?? 0);
		$otherDedux      = (float)($inputs['other_deductions'] ?? 0);
		// Note: we do NOT include uplDeduction here if we are starting netPay calculation from $gross, 
		// because $gross is already prorated ($basic - $uplDeduction).
		$totalDeductions = (float)($cpf['employee'] ?? 0) + (float)($shg['total'] ?? 0) + (float)$withTax + (float)$advanceRecovery + (float)$otherDedux;

		// --- Claims
		$claimsTotal = (float)($inputs['claims_total'] ?? 0);

		// --- Net Pay (in SGD)
		$netPay = self::calculateNetPay($gross, (float)($cpf['employee'] ?? 0), (float)($shg['total'] ?? 0), (float)$withTax, (float)($advanceRecovery + $otherDedux), (float)$claimsTotal);

		// --- Net pay back-converted to foreign currency (for reference on payslip)
		if ($fxRate > 0 && $contractCurrency !== 'SGD') {
			if ($fxSource === 'dolibarr_multicurrency') {
				$netPayFC = round($netPay / $fxRate, 2);
			} else {
				// manual: fxRate = FCY per 1 SGD → FCY = SGD × rate
				$netPayFC = round($netPay * $fxRate, 2);
			}
		} else {
			$netPayFC = $netPay;
		}

		return array(
			'basic_salary'       => $basicSalary,
			'basic_salary_fc'    => ($contractCurrency !== 'SGD') ? $contractSalary : $basicSalary,
			'contract_currency'  => $contractCurrency,
			'exchange_rate'      => $fxRate,
			'fx_source'          => $fxSource,
			'upl_days'           => $uplDays,
			'upl_deduction'      => $uplDeduction,
			'prorated_salary'    => $proratedSalary,
			'allowances_total'   => $allowancesTotal,
			'allowances_json'    => json_encode($allowances),
			'bonus'              => $bonus,
			'commission'         => $commission,
			'overtime_pay'       => $otPay,
			'overtime_hours'     => (float)($inputs['ot_hours'] ?? 0),
			'al_encashment'      => $alEncash,
			'other_aw'           => $otherAW,
			'aw_total'           => $awTotal,
			'gross_salary'       => $gross,
			'ordinary_wages'     => $cpf['ow_cpf_base'],
			'additional_wages'   => $cpf['aw_cpf_base'],
			'employee_cpf'       => $cpf['employee'],
			'employer_cpf'       => $cpf['employer'],
			'employee_cpf_oa'    => 0,
			'employee_cpf_sa'    => 0,
			'employee_cpf_ma'    => 0,
			'sdl_amount'         => $sdl,
			'fwl_amount'         => $fwl,
			'shg_cdac'           => $shg['cdac'],
			'shg_ecf'            => $shg['ecf'],
			'shg_mbmf'           => $shg['mbmf'],
			'shg_sinda'          => $shg['sinda'],
			'withholding_tax'    => $withTax,
			'salary_advance_recovery' => $advanceRecovery,
			'other_deductions'   => $otherDedux,
			'claims_total'       => $claimsTotal,
			'total_deductions'   => $totalDeductions,
			'net_pay'            => $netPay,
			'net_pay_fc'         => $netPayFC,
			'bik_value'          => (float)($inputs['bik_value'] ?? 0),
		);
	}

	/**
	 * Calculate age from date of birth for CPF purposes.
	 *
	 * CPF Board rule: age band adjustments take effect from the 1st day of the month
	 * FOLLOWING the month in which the birthday falls (not on the birthday itself).
	 *
	 * Example: if birthday is 15 Jan, the new age band applies from 1 Feb.
	 * During January (any date), the employee is still counted as one year younger.
	 *
	 * @param  string  $dob       'YYYY-MM-DD'
	 * @param  int     $payYear   Payslip year (e.g. 2026)
	 * @param  int     $payMonth  Payslip month (1-12)
	 * @return int     Age in years (adjusted for CPF rule)
	 */
	public static function calcAge($dob, $payYear = 0, $payMonth = 0)
	{
		if (empty($dob) || $dob === '0000-00-00') {
			return 35; // Default if unknown
		}
		try {
			// Use payslip year/month to determine the age band
			// This is the payslip's belonging month, NOT the actual payment date
			if ($payYear > 0 && $payMonth >= 1 && $payMonth <= 12) {
				$refDate = sprintf('%04d-%02d-15', (int)$payYear, (int)$payMonth); // middle of month
			} else {
				$refDate = date('Y-m-15'); // fallback to current month
			}

			$born = new DateTime($dob);
			$now  = new DateTime($refDate);
			$age  = (int)$born->diff($now)->y;

			// CPF rule: if we are in the birthday MONTH (day of month doesn't matter),
			// the age adjustment has not yet taken effect — keep the previous age bracket.
			// Only compare month, not year (birthday year is 1971, payslip year is 2026).
			if ((int)$now->format('m') === (int)$born->format('m')) {
				$age = max(0, $age - 1);
			}

			return $age;
		} catch (Exception $e) {
			dol_syslog('SghrCalc::calcAge error with dob='.$dob.' : '.$e->getMessage(), LOG_WARNING);
			return 35;
		}
	}
}
