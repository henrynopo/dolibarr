<?php
/* Copyright (C) 2014-2016	Alexandre Spangaro	<aspangaro@open-dsi.fr>
 * Copyright (C) 2015-2024  Frédéric France     <frederic.france@free.fr>
 * Copyright (C) 2020       Maxime DEMAREST     <maxime@indelog.fr>
 * Copyright (C) 2024-2025	MDW					<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *      \file       htdocs/core/lib/loan.lib.php
 *      \ingroup    loan
 *      \brief      Library for loan module
 */


/**
 * Prepare array with list of tabs
 *
 * @param   Object	$object		Object related to tabs
 * @return	array<array{0:string,1:string,2:string}>	Array of tabs to show
 */
function loan_prepare_head($object)
{
	global $db, $langs, $conf;

	$tab = 0;
	$head = array();

	$head[$tab][0] = DOL_URL_ROOT.'/loan/card.php?id='.$object->id;
	$head[$tab][1] = $langs->trans('Card');
	$head[$tab][2] = 'card';
	$tab++;

	$head[$tab][0] = DOL_URL_ROOT.'/loan/schedule.php?loanid='.$object->id;
	$head[$tab][1] = $langs->trans('FinancialCommitment');
	$head[$tab][2] = 'FinancialCommitment';
	$tab++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	// $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
	// $this->tabs = array('entity:-tabname);   												to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $tab, 'loan', 'add', 'core');

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->loan->dir_output."/".dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
	$nbLinks = Link::count($db, $object->element, $object->id);
	$head[$tab][0] = DOL_URL_ROOT.'/loan/document.php?id='.$object->id;
	$head[$tab][1] = $langs->trans("Documents");
	if (($nbFiles + $nbLinks) > 0) {
		$head[$tab][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
	}
	$head[$tab][2] = 'documents';
	$tab++;

	if (!getDolGlobalString('MAIN_DISABLE_NOTES_TAB')) {
		$nbNote = (empty($object->note_private) ? 0 : 1) + (empty($object->note_public) ? 0 : 1);
		$head[$tab][0] = DOL_URL_ROOT."/loan/note.php?id=".$object->id;
		$head[$tab][1] = $langs->trans("Notes");
		if ($nbNote > 0) {
			$head[$tab][1] .= '<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
		}
		$head[$tab][2] = 'note';
		$tab++;
	}

	$head[$tab][0] = DOL_URL_ROOT.'/loan/info.php?id='.$object->id;
	$head[$tab][1] = $langs->trans("Info");
	$head[$tab][2] = 'info';
	$tab++;

	complete_head_from_modules($conf, $langs, $object, $head, $tab, 'loan', 'add', 'external');

	complete_head_from_modules($conf, $langs, $object, $head, $tab, 'loan', 'remove');

	return $head;
}

/**
 * Calculate remaining loan mensuality and interests
 *
 * @param   float   $mens				Value of this mensuality (interests include, set 0 if we don't paid interests for this mensuality)
 * @param   float   $capital    		Remaining capital for this mensuality
 * @param   float   $rate				Loan rate
 * @param   int     $numactualloadterm	Actual loan term
 * @param   int   	$nbterm  			Total number of term for this loan
 * @return array<array{cap_rest:float,cap_rest_str:string,interet:float,interet_str:string,mens:string}>		Array with remaining capital, interest, and mensuality for each remaining terms
 */
function loanCalcMonthlyPayment($mens, $capital, $rate, $numactualloadterm, $nbterm)
{
	global $conf, $db;
	require_once DOL_DOCUMENT_ROOT.'/loan/class/loanschedule.class.php';
	$object = new LoanSchedule($db);
	$output = array();

	// Sanitize data in case of
	$mens = price2num($mens);
	$capital = price2num($capital);
	$rate = price2num($rate);
	$numactualloadterm = ((int) $numactualloadterm);
	$nbterm = ((int) $nbterm);

	// If mensuality is 0 we don't pay interests and remaining capital not modified
	if ($mens == 0) {
		$int = 0;
		$cap_rest = $capital;
	} else {
		$int = ((float) $capital * ((float) $rate / 12));
		$int = round($int, 2, PHP_ROUND_HALF_UP);
		$cap_rest = round((float) $capital - ((float) $mens - $int), 2, PHP_ROUND_HALF_UP);
	}
	$output[$numactualloadterm] = array(
		'cap_rest' => $cap_rest,
		'cap_rest_str' => price($cap_rest, 0, '', 1, -1, -1, $conf->currency),
		'interet' => $int,
		'interet_str' => price($int, 0, '', 1, -1, -1, $conf->currency),
		'mens' => price($mens),
	);

	$numactualloadterm++;
	$capital = $cap_rest;
	while ($numactualloadterm <= $nbterm) {
		$mens = round($object->calcMonthlyPayments($capital, (float) $rate, $nbterm - $numactualloadterm + 1), 2, PHP_ROUND_HALF_UP);

		$int = ($capital * ((float) $rate / 12));
		$int = round($int, 2, PHP_ROUND_HALF_UP);
		$cap_rest = round($capital - ($mens - $int), 2, PHP_ROUND_HALF_UP);

		$output[$numactualloadterm] = array(
			'cap_rest' => $cap_rest,
			'cap_rest_str' => price($cap_rest, 0, '', 1, -1, -1, $conf->currency),
			'interet' => $int,
			'interet_str' => price($int, 0, '', 1, -1, -1, $conf->currency),
			'mens' => price($mens),
		);
		$capital = $cap_rest;
		$numactualloadterm++;
	}

	return $output;
}

/**
 * Return number of days in the year containing the given timestamp (365 or 366 for leap year).
 *
 * @param int $timestamp Unix timestamp (UTC)
 * @return int 365 or 366
 */
function loan_days_in_year_for_timestamp($timestamp)
{
	$year = (int) date('Y', $timestamp);
	return (date('L', mktime(0, 0, 0, 1, 1, $year)) ? 366 : 365);
}

/**
 * Effective days-in-year for an interest period (actual/actual with cross-year weighting).
 * When the period spans two calendar years (e.g. 03/12/2023–03/01/2024), use weighted average
 * so that 2024 leap year (366) is reflected for the days falling in 2024. Matches bank statements.
 *
 * @param int $periodStartTs Period start (Unix timestamp)
 * @param int $periodEndTs   Period end (Unix timestamp)
 * @return float Effective days in year (e.g. 365.0 or 365.1 when spanning into leap year)
 */
function loan_effective_days_in_year_for_period($periodStartTs, $periodEndTs)
{
	$days = (int) floor(($periodEndTs - $periodStartTs) / 86400);
	if ($days <= 0) {
		return (float) loan_days_in_year_for_timestamp($periodStartTs);
	}
	$dayCountInYear = array();
	for ($d = 0; $d < $days; $d++) {
		$ts = $periodStartTs + $d * 86400;
		$y = (int) date('Y', $ts);
		if (!isset($dayCountInYear[$y])) {
			$dayCountInYear[$y] = 0;
		}
		$dayCountInYear[$y]++;
	}
	$weighted = 0;
	foreach ($dayCountInYear as $year => $count) {
		$daysInThatYear = loan_days_in_year_for_timestamp(mktime(0, 0, 0, 1, 1, $year));
		$weighted += $count * $daysInThatYear;
	}
	return $weighted / $days;
}

/**
 * Build a repayment schedule with a fixed monthly payment and bank-style interest.
 * Options control rounding, days-in-year (leap year), and balance basis for interest.
 *
 * @param float   $fixedPayment       Fixed amount paid each term (e.g. 8874)
 * @param float   $capital            Initial loan capital
 * @param float   $ratePercent        Annual interest rate in % (e.g. 2.5 for 2.5%)
 * @param int     $dateStartTimestamp Start date of loan (first period starts here)
 * @param int     $nbterm             Number of terms
 * @param float   $insurancePerTerm   Optional insurance per term (default 0)
 * @param array   $options            Optional. Keys: 'rounding', 'days_in_year', 'balance_for_interest'; 'rates' => array(term_number => rate_percent) for floating rate (one rate per term)
 * @return array<int,array{cap_rest:float,cap_rest_str:string,interet:float,interet_str:string,mens:string,datep:int}>  Term number => data
 */
function loanCalcScheduleWithFixedPayment($fixedPayment, $capital, $ratePercent, $dateStartTimestamp, $nbterm, $insurancePerTerm = 0, $options = array())
{
	global $conf;

	$fixedPayment = (float) price2num($fixedPayment);
	$capital = (float) price2num($capital);
	$ratePercent = (float) price2num($ratePercent);
	$nbterm = (int) $nbterm;
	$insurancePerTerm = (float) price2num($insurancePerTerm);

	$roundMode = (isset($options['rounding']) && $options['rounding'] === 'half_even') ? PHP_ROUND_HALF_EVEN : PHP_ROUND_HALF_UP;
	$daysInYearMode = (isset($options['days_in_year']) && $options['days_in_year'] === 'period_start') ? 'period_start' : 'cross_year_weighted';
	$balanceMode = (isset($options['balance_for_interest']) && $options['balance_for_interest'] === 'exact') ? 'exact' : 'displayed';

	$output = array();
	$balanceDisplay = round($capital, 2, $roundMode);
	$sumPrincipal = 0.0;

	$dates = array();
	for ($k = 0; $k <= $nbterm; $k++) {
		$dates[$k] = dol_time_plus_duree($dateStartTimestamp, $k, 'm');
	}

	for ($i = 1; $i <= $nbterm; $i++) {
		$prev_ts = $dates[$i - 1];
		$curr_ts = $dates[$i];

		$days = (int) floor(($curr_ts - $prev_ts) / 86400);
		if ($daysInYearMode === 'period_start') {
			$days_in_year = (float) loan_days_in_year_for_timestamp($prev_ts);
		} else {
			$days_in_year = loan_effective_days_in_year_for_period($prev_ts, $curr_ts);
		}

		if ($balanceMode === 'exact') {
			$balanceForInterest = $capital - $sumPrincipal;
		} else {
			$balanceForInterest = $balanceDisplay;
		}

		// Per-term rate (floating) or single loan rate (fixed)
		$rateForTerm = (isset($options['rates']) && is_array($options['rates']) && isset($options['rates'][$i])) ? (float) price2num($options['rates'][$i]) : $ratePercent;

		$interest = $balanceForInterest * ($rateForTerm / 100) * ($days / $days_in_year);
		$interest = round($interest, 2, $roundMode);

		$principal = round($fixedPayment - $interest, 2, $roundMode);
		if ($principal > $balanceForInterest) {
			$principal = $balanceForInterest;
		}
		if ($principal < 0) {
			$principal = 0;
		}

		$sumPrincipal += $principal;
		if ($balanceMode === 'exact') {
			$cap_rest_display = round($capital - $sumPrincipal, 2, $roundMode);
		} else {
			$balanceDisplay = round($balanceForInterest - $principal, 2, $roundMode);
			if ($balanceDisplay < 0) {
				$balanceDisplay = 0;
			}
			$cap_rest_display = $balanceDisplay;
		}
		if ($cap_rest_display < 0) {
			$cap_rest_display = 0;
		}

		$mens = $principal + $interest;

		$output[$i] = array(
			'cap_rest' => $cap_rest_display,
			'cap_rest_str' => price($cap_rest_display, 0, '', 1, -1, -1, $conf->currency),
			'interet' => $interest,
			'interet_str' => price($interest, 0, '', 1, -1, -1, $conf->currency),
			'mens' => price($mens),
			'datep' => $curr_ts,
		);
	}

	return $output;
}

/**
 * Build a repayment schedule with equal principal per term (等额本金).
 * Each term: principal = capital / nbterm; interest = previous balance * rate * (days/days_in_year).
 *
 * @param float   $capital            Initial loan capital for this segment
 * @param float   $ratePercent        Annual interest rate in %
 * @param int     $dateStartTimestamp Start of first period (e.g. loan start for phase 1)
 * @param int     $nbterm             Number of terms in this segment
 * @param float   $insurancePerTerm   Optional insurance per term (not included in return, for compatibility)
 * @param array   $options            Optional. Same as loanCalcScheduleWithFixedPayment: rounding, days_in_year, balance_for_interest, rates (floating)
 * @return array<int,array{cap_rest:float,cap_rest_str:string,interet:float,interet_str:string,mens:string,datep:int}>
 */
function loanCalcScheduleEqualPrincipal($capital, $ratePercent, $dateStartTimestamp, $nbterm, $insurancePerTerm = 0, $options = array())
{
	global $conf;

	$capital = (float) price2num($capital);
	$ratePercent = (float) price2num($ratePercent);
	$nbterm = (int) $nbterm;
	if ($nbterm <= 0) {
		return array();
	}

	$roundMode = (isset($options['rounding']) && $options['rounding'] === 'half_even') ? PHP_ROUND_HALF_EVEN : PHP_ROUND_HALF_UP;
	$daysInYearMode = (isset($options['days_in_year']) && $options['days_in_year'] === 'period_start') ? 'period_start' : 'cross_year_weighted';
	$balanceMode = (isset($options['balance_for_interest']) && $options['balance_for_interest'] === 'exact') ? 'exact' : 'displayed';

	$principalPerTerm = round($capital / $nbterm, 2, $roundMode);
	$output = array();
	$balanceDisplay = round($capital, 2, $roundMode);
	$sumPrincipal = 0.0;

	$dates = array();
	for ($k = 0; $k <= $nbterm; $k++) {
		$dates[$k] = dol_time_plus_duree($dateStartTimestamp, $k, 'm');
	}

	for ($i = 1; $i <= $nbterm; $i++) {
		$prev_ts = $dates[$i - 1];
		$curr_ts = $dates[$i];
		$days = (int) floor(($curr_ts - $prev_ts) / 86400);
		if ($daysInYearMode === 'period_start') {
			$days_in_year = (float) loan_days_in_year_for_timestamp($prev_ts);
		} else {
			$days_in_year = loan_effective_days_in_year_for_period($prev_ts, $curr_ts);
		}

		if ($balanceMode === 'exact') {
			$balanceForInterest = $capital - $sumPrincipal;
		} else {
			$balanceForInterest = $balanceDisplay;
		}

		$rateForTerm = (isset($options['rates']) && is_array($options['rates']) && isset($options['rates'][$i])) ? (float) price2num($options['rates'][$i]) : $ratePercent;
		$interest = $balanceForInterest * ($rateForTerm / 100) * ($days / $days_in_year);
		$interest = round($interest, 2, $roundMode);

		$principal = $principalPerTerm;
		if ($i == $nbterm) {
			$principal = round($balanceForInterest, 2, $roundMode);
		}
		if ($principal > $balanceForInterest) {
			$principal = $balanceForInterest;
		}
		if ($principal < 0) {
			$principal = 0;
		}

		$sumPrincipal += $principal;
		if ($balanceMode === 'exact') {
			$cap_rest_display = round($capital - $sumPrincipal, 2, $roundMode);
		} else {
			$balanceDisplay = round($balanceForInterest - $principal, 2, $roundMode);
			if ($balanceDisplay < 0) {
				$balanceDisplay = 0;
			}
			$cap_rest_display = $balanceDisplay;
		}
		if ($cap_rest_display < 0) {
			$cap_rest_display = 0;
		}

		$mens = $principal + $interest;

		$output[$i] = array(
			'cap_rest' => $cap_rest_display,
			'cap_rest_str' => price($cap_rest_display, 0, '', 1, -1, -1, $conf->currency),
			'interet' => $interest,
			'interet_str' => price($interest, 0, '', 1, -1, -1, $conf->currency),
			'mens' => price($mens),
			'datep' => $curr_ts,
		);
	}

	return $output;
}

/**
 * Build one unified repayment schedule from multiple phases (no new table).
 * Each phase: start_term, end_term (inclusive), rate_type (fixed|floating), rate_value (%), rates (optional array term=>%), repayment (equal_instalment|equal_principal|fixed_payment), fixed_payment_amount (optional).
 * Phases must cover 1..nbterm without gaps. Capital at start of each phase = previous phase end balance (or loan capital for first).
 *
 * @param array   $phases             Array of phase definitions (see above)
 * @param float   $capital            Initial loan capital
 * @param float   $loanRatePercent    Default rate % when phase has no rate
 * @param int     $dateStartTimestamp Loan start (first period start)
 * @param int     $nbtermTotal        Total number of terms
 * @param float   $insurancePerTerm   Insurance per term (not applied inside this function; caller adds to schedule lines)
 * @param array   $defaultOptions     Options for all phases: rounding, days_in_year, balance_for_interest
 * @return array<int,array{cap_rest:float,cap_rest_str:string,interet:float,interet_str:string,mens:string,datep:int}>  Term index 1..nbtermTotal, or empty on error
 */
function loanCalcScheduleFromPhases($phases, $capital, $loanRatePercent, $dateStartTimestamp, $nbtermTotal, $insurancePerTerm = 0, $defaultOptions = array())
{
	global $conf;
	if (!is_array($phases) || empty($phases)) {
		return array();
	}

	$capital = (float) price2num($capital);
	$loanRatePercent = (float) price2num($loanRatePercent);
	$nbtermTotal = (int) $nbtermTotal;
	$fullSchedule = array();
	$runningCapital = $capital;
	$globalTermIndex = 1;

	foreach ($phases as $phase) {
		$start_term = (int) (isset($phase['start_term']) ? $phase['start_term'] : $globalTermIndex);
		$end_term = (int) (isset($phase['end_term']) ? $phase['end_term'] : $nbtermTotal);
		$phaseNbterm = $end_term - $start_term + 1;
		if ($phaseNbterm <= 0 || $runningCapital <= 0) {
			break;
		}

		$rate_type = isset($phase['rate_type']) ? $phase['rate_type'] : 'fixed';
		$rate_value = isset($phase['rate_value']) ? (float) price2num($phase['rate_value']) : $loanRatePercent;
		$repayment = isset($phase['repayment']) ? $phase['repayment'] : 'equal_instalment';
		$fixed_payment_amount = isset($phase['fixed_payment_amount']) ? (float) price2num($phase['fixed_payment_amount']) : 0;

		$phaseStartTs = dol_time_plus_duree($dateStartTimestamp, $start_term - 1, 'm');

		$options = array_merge($defaultOptions, array());
		if ($rate_type === 'floating' && !empty($phase['rates']) && is_array($phase['rates'])) {
			$options['rates'] = array();
			for ($local = 1; $local <= $phaseNbterm; $local++) {
				$globalTerm = $start_term + $local - 1;
				if (isset($phase['rates'][$globalTerm])) {
					$options['rates'][$local] = $phase['rates'][$globalTerm];
				}
			}
		}

		$segment = array();
		if ($repayment === 'fixed_payment' && $fixed_payment_amount > 0) {
			$segment = loanCalcScheduleWithFixedPayment($fixed_payment_amount, $runningCapital, $rate_value, $phaseStartTs, $phaseNbterm, 0, $options);
		} elseif ($repayment === 'equal_principal') {
			$segment = loanCalcScheduleEqualPrincipal($runningCapital, $rate_value, $phaseStartTs, $phaseNbterm, 0, $options);
		} else {
			$segment = loanCalcScheduleEqualInstalment($runningCapital, $rate_value, $phaseStartTs, $phaseNbterm, 0, $options);
		}

		if (empty($segment)) {
			return array();
		}

		foreach ($segment as $localTerm => $row) {
			$fullSchedule[$globalTermIndex] = $row;
			$globalTermIndex++;
		}
		$lastRow = end($segment);
		$runningCapital = (float) $lastRow['cap_rest'];
	}

	return $fullSchedule;
}

/**
 * Equal instalment (等额本息) schedule for a segment. Uses monthly rate approximation; options for rounding, days_in_year, rates.
 *
 * @param float   $capital            Capital for this segment
 * @param float   $ratePercent        Annual rate %
 * @param int     $dateStartTimestamp Period start for term 1 of segment
 * @param int     $nbterm             Number of terms
 * @param float   $insurancePerTerm   Unused
 * @param array   $options            rounding, days_in_year, balance_for_interest, rates (floating)
 * @return array<int,array{cap_rest:float,cap_rest_str:string,interet:float,interet_str:string,mens:string,datep:int}>
 */
function loanCalcScheduleEqualInstalment($capital, $ratePercent, $dateStartTimestamp, $nbterm, $insurancePerTerm = 0, $options = array())
{
	global $conf;

	$capital = (float) price2num($capital);
	$ratePercent = (float) price2num($ratePercent);
	$nbterm = (int) $nbterm;
	if ($nbterm <= 0) {
		return array();
	}

	$roundMode = (isset($options['rounding']) && $options['rounding'] === 'half_even') ? PHP_ROUND_HALF_EVEN : PHP_ROUND_HALF_UP;
	$daysInYearMode = (isset($options['days_in_year']) && $options['days_in_year'] === 'period_start') ? 'period_start' : 'cross_year_weighted';
	$balanceMode = (isset($options['balance_for_interest']) && $options['balance_for_interest'] === 'exact') ? 'exact' : 'displayed';

	$monthlyRate = $ratePercent / 100 / 12;
	if (abs($monthlyRate) < 1e-9) {
		$mensFixed = $capital / $nbterm;
	} else {
		$mensFixed = $capital * ($monthlyRate * pow(1 + $monthlyRate, $nbterm)) / (pow(1 + $monthlyRate, $nbterm) - 1);
	}
	$mensFixed = round($mensFixed, 2, $roundMode);

	$output = array();
	$balanceDisplay = round($capital, 2, $roundMode);
	$sumPrincipal = 0.0;

	$dates = array();
	for ($k = 0; $k <= $nbterm; $k++) {
		$dates[$k] = dol_time_plus_duree($dateStartTimestamp, $k, 'm');
	}

	for ($i = 1; $i <= $nbterm; $i++) {
		$prev_ts = $dates[$i - 1];
		$curr_ts = $dates[$i];
		$days = (int) floor(($curr_ts - $prev_ts) / 86400);
		if ($daysInYearMode === 'period_start') {
			$days_in_year = (float) loan_days_in_year_for_timestamp($prev_ts);
		} else {
			$days_in_year = loan_effective_days_in_year_for_period($prev_ts, $curr_ts);
		}

		if ($balanceMode === 'exact') {
			$balanceForInterest = $capital - $sumPrincipal;
		} else {
			$balanceForInterest = $balanceDisplay;
		}

		$rateForTerm = (isset($options['rates']) && is_array($options['rates']) && isset($options['rates'][$i])) ? (float) price2num($options['rates'][$i]) : $ratePercent;
		$interest = $balanceForInterest * ($rateForTerm / 100) * ($days / $days_in_year);
		$interest = round($interest, 2, $roundMode);

		$principal = round($mensFixed - $interest, 2, $roundMode);
		if ($i == $nbterm) {
			$principal = round($balanceForInterest, 2, $roundMode);
		}
		if ($principal > $balanceForInterest) {
			$principal = $balanceForInterest;
		}
		if ($principal < 0) {
			$principal = 0;
		}

		$sumPrincipal += $principal;
		if ($balanceMode === 'exact') {
			$cap_rest_display = round($capital - $sumPrincipal, 2, $roundMode);
		} else {
			$balanceDisplay = round($balanceForInterest - $principal, 2, $roundMode);
			if ($balanceDisplay < 0) {
				$balanceDisplay = 0;
			}
			$cap_rest_display = $balanceDisplay;
		}
		if ($cap_rest_display < 0) {
			$cap_rest_display = 0;
		}

		$mens = $principal + $interest;

		$output[$i] = array(
			'cap_rest' => $cap_rest_display,
			'cap_rest_str' => price($cap_rest_display, 0, '', 1, -1, -1, $conf->currency),
			'interet' => $interest,
			'interet_str' => price($interest, 0, '', 1, -1, -1, $conf->currency),
			'mens' => price($mens),
			'datep' => $curr_ts,
		);
	}

	return $output;
}
