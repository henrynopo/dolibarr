<?php
/* Copyright (C) 2017		Franck Moreau				<franck.moreau@theobald.com>
 * Copyright (C) 2018-2024	Alexandre Spangaro			<alexandre@inovea-conseil.com>
 * Copyright (C) 2020		Maxime DEMAREST				<maxime@indelog.fr>
 * Copyright (C) 2024-2025	MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Frédéric France				<frederic.france@free.fr>
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
 *  \file       htdocs/loan/schedule.php
 *  \ingroup    loan
 *  \brief      Schedule card
 */

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/loan/class/loan.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/loan.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
// Fallback if core/lib/loan.lib.php does not contain fixed-payment helpers (e.g. official release not patched)
if (!function_exists('loan_days_in_year_for_timestamp')) {
	function loan_days_in_year_for_timestamp($timestamp)
	{
		$year = (int) date('Y', $timestamp);
		return (date('L', mktime(0, 0, 0, 1, 1, $year)) ? 366 : 365);
	}
}
if (!function_exists('loan_effective_days_in_year_for_period')) {
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
}
if (!function_exists('loanCalcScheduleWithFixedPayment')) {
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
			$days_in_year = ($daysInYearMode === 'period_start') ? (float) loan_days_in_year_for_timestamp($prev_ts) : loan_effective_days_in_year_for_period($prev_ts, $curr_ts);
			$balanceForInterest = ($balanceMode === 'exact') ? ($capital - $sumPrincipal) : $balanceDisplay;
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
}
require_once DOL_DOCUMENT_ROOT.'/loan/class/loanschedule.class.php';
require_once DOL_DOCUMENT_ROOT.'/loan/class/paymentloan.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
if (isModEnabled('project')) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

$loanid = GETPOSTINT('loanid');
$action = GETPOST('action', 'aZ09');

// Security check
$socid = 0;
if (GETPOSTISSET('socid')) {
	$socid = GETPOSTINT('socid');
}
if ($user->socid) {
	$socid = $user->socid;
}
if (!$user->hasRight('loan', 'calc')) {
	accessforbidden();
}

// Load translation files required by the page
$langs->loadLangs(array("compta", "bills", "loan"));

$object = new Loan($db);
$object->fetch($loanid);

$echeances = new LoanSchedule($db);
$echeances->fetchAll($object->id);

// Fixed monthly payment (preview or create): read from GET only (method 1) so Preview link param is never lost
$fixed_monthly_payment = price2num(GETPOST('fixed_monthly_payment', 'alphanohtml', 1));

// Schedule calculation options (GET for preview, POST for create; method 0 = both)
$schedule_rounding = GETPOST('schedule_rounding', 'alphanohtml', 0);
$schedule_days_in_year = GETPOST('schedule_days_in_year', 'alphanohtml', 0);
$schedule_balance_for_interest = GETPOST('schedule_balance_for_interest', 'alphanohtml', 0);
$schedule_options = array();
if ($schedule_rounding === 'half_even') {
	$schedule_options['rounding'] = 'half_even';
} else {
	$schedule_options['rounding'] = 'half_up';
}
if ($schedule_days_in_year === 'period_start') {
	$schedule_options['days_in_year'] = 'period_start';
} else {
	$schedule_options['days_in_year'] = 'cross_year_weighted';
}
if ($schedule_balance_for_interest === 'exact') {
	$schedule_options['balance_for_interest'] = 'exact';
} else {
	$schedule_options['balance_for_interest'] = 'displayed';
}
// Floating rate: per-term rates (comma or newline separated; line N = rate % for term N)
$schedule_rate_type = GETPOST('schedule_rate_type', 'alphanohtml', 0);
$schedule_rates_raw = GETPOST('schedule_rates', 'alphanohtml', 0);
if ($schedule_rate_type === 'floating' && $schedule_rates_raw !== '') {
	$normalized = preg_replace('/[\r\n]+/', ',', $schedule_rates_raw);
	$parts = array_map('trim', explode(',', $normalized));
	$rates_arr = array();
	foreach ($parts as $p) {
		if ($p === '') {
			continue;
		}
		$rates_arr[] = (float) price2num($p);
	}
	if (!empty($rates_arr)) {
		$schedule_options['rates'] = array();
		foreach ($rates_arr as $idx => $r) {
			$schedule_options['rates'][$idx + 1] = $r;
		}
	}
}

if ($object->paid > 0 && count($echeances->lines) == 0) {
	$pay_without_schedule = 1;
} else {
	$pay_without_schedule = 0;
}

$permissiontoadd = $user->hasRight('loan', 'write');


/*
 * Actions
 */

if ($action == 'createecheancier' && empty($pay_without_schedule) && $permissiontoadd) {
	$db->begin();
	$result = 0;
	$insurance_per_term = (float) $object->insurance_amount / $object->nbterm;
	$insurance_per_term = price2num($insurance_per_term, 'MT');
	$regul_insurance = price2num((float) $object->insurance_amount - ((float) $insurance_per_term * $object->nbterm));

	$schedule_phases_json = GETPOST('schedule_phases', 'none', 0);
	$use_phases = false;
	$phases_decoded = array();
	if ($schedule_phases_json !== '' && function_exists('loanCalcScheduleFromPhases')) {
		$phases_decoded = json_decode($schedule_phases_json, true);
		if (is_array($phases_decoded) && count($phases_decoded) > 0) {
			$use_phases = true;
		}
	}

	$fixed_on_create = price2num(GETPOST('fixed_monthly_payment'));
	if ($use_phases) {
		$datestart_ts = is_numeric($object->datestart) ? (int) $object->datestart : dol_stringtotime($object->datestart, 0);
		$schedule = loanCalcScheduleFromPhases($phases_decoded, $object->capital, $object->rate, $datestart_ts, (int) $object->nbterm, $insurance_per_term, $schedule_options);
		if (!empty($schedule) && count($schedule) == (int) $object->nbterm) {
			foreach ($schedule as $i => $row) {
				$insu = (float) $insurance_per_term + (($i == 1) ? (float) $regul_insurance : 0);
				$new_echeance = new LoanSchedule($db);
				$new_echeance->fk_loan = $object->id;
				$new_echeance->datec = dol_now();
				$new_echeance->tms = dol_now();
				$new_echeance->datep = $row['datep'];
				$new_echeance->amount_capital = (float) price2num($row['mens']) - (float) $row['interet'];
				$new_echeance->amount_insurance = $insu;
				$new_echeance->amount_interest = (float) $row['interet'];
				$new_echeance->fk_typepayment = 3;
				$new_echeance->fk_bank = 0;
				$new_echeance->fk_user_creat = $user->id;
				$new_echeance->fk_user_modif = $user->id;
				$result = $new_echeance->create($user);
				if ($result < 0) {
					setEventMessages($new_echeance->error, $new_echeance->errors, 'errors');
					$db->rollback();
					$echeances->lines = [];
					break;
				}
				$echeances->lines[] = $new_echeance;
			}
			if ($result > 0) {
				$object->schedule_phases = $schedule_phases_json;
				$object->update($user);
			}
		} else {
			setEventMessages(null, array($langs->trans("SchedulePhasesInvalidOrMismatch")), 'errors');
			$db->rollback();
			$echeances->lines = array();
		}
	} elseif ($fixed_on_create > 0) {
		// Create schedule from fixed monthly payment (bank-style) with selected calculation options
		$datestart_ts = is_numeric($object->datestart) ? (int) $object->datestart : dol_stringtotime($object->datestart, 0);
		$schedule = loanCalcScheduleWithFixedPayment($fixed_on_create, $object->capital, $object->rate, $datestart_ts, $object->nbterm, $insurance_per_term, $schedule_options);
		foreach ($schedule as $i => $row) {
			$insu = (float) $insurance_per_term + (($i == 1) ? (float) $regul_insurance : 0);
			$new_echeance = new LoanSchedule($db);
			$new_echeance->fk_loan = $object->id;
			$new_echeance->datec = dol_now();
			$new_echeance->tms = dol_now();
			$new_echeance->datep = $row['datep'];
			$new_echeance->amount_capital = (float) price2num($row['mens']) - (float) $row['interet'];
			$new_echeance->amount_insurance = $insu;
			$new_echeance->amount_interest = (float) $row['interet'];
			$new_echeance->fk_typepayment = 3;
			$new_echeance->fk_bank = 0;
			$new_echeance->fk_user_creat = $user->id;
			$new_echeance->fk_user_modif = $user->id;
			$result = $new_echeance->create($user);
			if ($result < 0) {
				setEventMessages($new_echeance->error, $new_echeance->errors, 'errors');
				$db->rollback();
				$echeances->lines = [];
				break;
			}
			$echeances->lines[] = $new_echeance;
		}
	} else {
		$i = 1;
		while ($i < $object->nbterm + 1) {
			$date = GETPOSTINT('hi_date'.$i);
			$mens = price2num(GETPOST('mens'.$i));
			$int = price2num(GETPOST('hi_interets'.$i));
			$insurance = price2num(GETPOST('hi_insurance'.$i));

			$new_echeance = new LoanSchedule($db);

			$new_echeance->fk_loan = $object->id;
			$new_echeance->datec = dol_now();
			$new_echeance->tms = dol_now();
			$new_echeance->datep = $date;
			$new_echeance->amount_capital = (float) $mens - (float) $int;
			$new_echeance->amount_insurance = $insurance;
			$new_echeance->amount_interest = $int;
			$new_echeance->fk_typepayment = 3;
			$new_echeance->fk_bank = 0;
			$new_echeance->fk_user_creat = $user->id;
			$new_echeance->fk_user_modif = $user->id;
			$result = $new_echeance->create($user);
			if ($result < 0) {
				setEventMessages($new_echeance->error, $new_echeance->errors, 'errors');
				$db->rollback();
				$echeances->lines = [];
				break;
			}
			$echeances->lines[] = $new_echeance;
			$i++;
		}
	}
	if ($result > 0) {
		$db->commit();
	}
}

if ($action == 'updateecheancier' && empty($pay_without_schedule) && $permissiontoadd) {
	$db->begin();
	$i = 1;
	while ($i < $object->nbterm + 1) {
		$mens = price2num(GETPOST('mens'.$i));
		$int = price2num(GETPOST('hi_interets'.$i));
		$id = GETPOSTINT('hi_rowid'.$i);
		$insurance = price2num(GETPOST('hi_insurance'.$i));

		$new_echeance = new LoanSchedule($db);
		$new_echeance->fetch($id);
		$new_echeance->tms = dol_now();
		$new_echeance->amount_capital = (float) $mens - (float) $int;
		$new_echeance->amount_insurance = $insurance;
		$new_echeance->amount_interest = $int;
		$new_echeance->fk_user_modif = $user->id;
		$result = $new_echeance->update($user, 0);
		if ($result < 0) {
			setEventMessages(null, $new_echeance->errors, 'errors');
			$db->rollback();
			$echeances->fetchAll($object->id);
			break;
		}

		$echeances->lines[$i - 1] = $new_echeance;
		$i++;
	}
	if ($result > 0) {
		$db->commit();
	}
}


/*
 * View
 */

$form = new Form($db);
$formproject = new FormProjets($db);

$title = $langs->trans("Loan").' - '.$langs->trans("FinancialCommitment");
$help_url = 'EN:Module_Loan|FR:Module_Emprunt';

llxHeader("", $title, $help_url, '', 0, 0, '', '', '', 'mod-loan page-card_schedule');

$head = loan_prepare_head($object);
print dol_get_fiche_head($head, 'FinancialCommitment', $langs->trans("Loan"), -1, 'money-bill-alt');

$linkback = '<a href="'.DOL_URL_ROOT.'/loan/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

$morehtmlref = '<div class="refidno">';
// Ref loan
$morehtmlref .= $form->editfieldkey("Label", 'label', $object->label, $object, 0, 'string', '', 0, 1);
$morehtmlref .= $form->editfieldval("Label", 'label', $object->label, $object, 0, 'string', '', null, null, '', 1);
// Project
if (isModEnabled('project')) {
	$langs->loadLangs(array("projects"));
	$morehtmlref .= '<br>'.$langs->trans('Project').' : ';
	if ($user->hasRight('loan', 'write')) {
		if ($action != 'classify') {
			//$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> : ';
			if ($action == 'classify') {
				//$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
				$morehtmlref .= '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'">';
				$morehtmlref .= '<input type="hidden" name="action" value="classin">';
				$morehtmlref .= '<input type="hidden" name="token" value="'.newToken().'">';
				$morehtmlref .= $formproject->select_projects(-1, (string) $object->fk_project, 'projectid', 16, 0, 1, 0, 1, 0, 0, '', 1);
				$morehtmlref .= '<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">';
				$morehtmlref .= '</form>';
			} else {
				$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, -1, (string) $object->fk_project, 'none', 0, 0, 0, 1, '', 'maxwidth300');
			}
		}
	} else {
		if (!empty($object->fk_project)) {
			$proj = new Project($db);
			$proj->fetch($object->fk_project);
			$morehtmlref .= ' : '.$proj->getNomUrl(1);
			if ($proj->title) {
				$morehtmlref .= ' - '.$proj->title;
			}
		} else {
			$morehtmlref .= '';
		}
	}
}
$morehtmlref .= '</div>';

$morehtmlstatus = '';

dol_banner_tab($object, 'loanid', $linkback, 1, 'rowid', 'ref', $morehtmlref, '', 0, '', $morehtmlstatus);

?>
<script type="text/javascript">
$(document).ready(function() {
	var timeout = null;
	var delay = 750;   // 0.75 seconds
	$('[name^="mens"]').keyup(function() {
		clearTimeout(timeout);
		timeout = setTimeout(() => {
			var echeance = $(this).attr('ech');
			var mens = $(this).val();
			calculateMens(echeance, mens);
		}, delay);
	});
	function calculateMens(echeance, mens) {
		var table = $('[name^="mens"]');
		var idcap = echeance-1;
		idcap = '#hi_capital'+idcap;
		var capital = price2numjs($(idcap).val());
		console.log("Change monthly amount echeance="+echeance+" idcap="+idcap+" capital="+capital);
		$.ajax({
			method: "GET",
			dataType: 'json',
			url: 'calcmens.php',
			data: {
				echeance: echeance,
				mens: price2numjs(mens),
				capital: capital,
				rate: <?php echo $object->rate / 100; ?>,
				nbterm: <?php echo $object->nbterm; ?>,
				token: '<?php echo currentToken(); ?>'
			},
			success: function(data) {
				$.each(data, function(index, element) {
					$('#hi_capital'+index).val(element.cap_rest);
					$('#capital'+index).text(element.cap_rest_str);
					$('#hi_interets'+index).val(element.interet);
					$('#interets'+index).text(element.interet_str);
					$('#mens'+index).val(element.mens);
				});
			}
		});
	}
});
</script>
<?php

if ($pay_without_schedule == 1) {
	print '<div class="warning">'.$langs->trans('CantUseScheduleWithLoanStartedToPaid').'</div>'."\n";
}

print '<form name="createecheancier" action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="loanid" value="'.$loanid.'">';
if (count($echeances->lines) > 0) {
	print '<input type="hidden" name="action" value="updateecheancier">';
} else {
	print '<input type="hidden" name="action" value="createecheancier">';
}

// Calculation options and fixed monthly payment (when no schedule lines yet)
if (count($echeances->lines) == 0 && $object->nbterm > 0) {
	print '<div class="fichecenter">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("ScheduleCalculationOptions").'</td></tr>';
	// Schedule type: standard vs fixed monthly payment
	print '<tr><td class="width300">'.$langs->trans("ScheduleType").'</td><td>';
	print $langs->trans("ScheduleTypeStandard").' ('.$langs->trans("EqualInstalment").')';
	print ' &nbsp;|&nbsp; <strong>'.$langs->trans("FixedMonthlyPayment").'</strong>: ';
	print '<input type="number" step="0.01" min="0" name="fixed_monthly_payment" id="fixed_monthly_payment" value="'.dol_escape_htmltag($fixed_monthly_payment ? $fixed_monthly_payment : '').'" placeholder="'.$langs->trans("OptionalBankAmount").'" class="width100">';
	print ' <span class="opacitymedium">'.$langs->trans("FixedMonthlyPaymentHelp").'</span>';
	print '</td></tr>';
	// Rounding
	print '<tr><td>'.$langs->trans("ScheduleRounding").'</td><td>';
	print '<select name="schedule_rounding" id="schedule_rounding">';
	print '<option value="half_up"'.($schedule_rounding !== 'half_even' ? ' selected="selected"' : '').'>'.$langs->trans("RoundingHalfUp").'</option>';
	print '<option value="half_even"'.($schedule_rounding === 'half_even' ? ' selected="selected"' : '').'>'.$langs->trans("RoundingHalfEven").'</option>';
	print '</select>';
	print '</td></tr>';
	// Days in year / leap year
	print '<tr><td>'.$langs->trans("ScheduleDaysInYear").'</td><td>';
	print '<select name="schedule_days_in_year" id="schedule_days_in_year">';
	print '<option value="cross_year_weighted"'.($schedule_days_in_year !== 'period_start' ? ' selected="selected"' : '').'>'.$langs->trans("DaysInYearCrossYearWeighted").'</option>';
	print '<option value="period_start"'.($schedule_days_in_year === 'period_start' ? ' selected="selected"' : '').'>'.$langs->trans("DaysInYearPeriodStart").'</option>';
	print '</select>';
	print '</td></tr>';
	// Balance for next period interest
	print '<tr><td>'.$langs->trans("ScheduleBalanceForInterest").'</td><td>';
	print '<select name="schedule_balance_for_interest" id="schedule_balance_for_interest">';
	print '<option value="displayed"'.($schedule_balance_for_interest !== 'exact' ? ' selected="selected"' : '').'>'.$langs->trans("BalanceDisplayed").'</option>';
	print '<option value="exact"'.($schedule_balance_for_interest === 'exact' ? ' selected="selected"' : '').'>'.$langs->trans("BalanceExact").'</option>';
	print '</select>';
	print '</td></tr>';
	// Rate type: fixed (loan rate for all) or floating (one rate per term)
	print '<tr><td>'.$langs->trans("ScheduleRateType").'</td><td>';
	print '<select name="schedule_rate_type" id="schedule_rate_type">';
	print '<option value="fixed"'.($schedule_rate_type !== 'floating' ? ' selected="selected"' : '').'>'.$langs->trans("RateTypeFixed").'</option>';
	print '<option value="floating"'.($schedule_rate_type === 'floating' ? ' selected="selected"' : '').'>'.$langs->trans("RateTypeFloating").'</option>';
	print '</select>';
	print '</td></tr>';
	print '<tr id="schedule_rates_row" style="'.($schedule_rate_type === 'floating' ? '' : 'display:none;').'"><td>'.$langs->trans("ScheduleRatesPerTerm").'</td><td>';
	print '<textarea name="schedule_rates" id="schedule_rates" rows="4" class="flat" placeholder="'.$langs->trans("ScheduleRatesPlaceholder").'">'.dol_escape_htmltag($schedule_rates_raw).'</textarea>';
	print ' <span class="opacitymedium">'.$langs->trans("ScheduleRatesHelp").'</span>';
	print '</td></tr>';
	// Multi-phase: optional JSON to split schedule into phases (different rate/repayment per range)
	if (function_exists('loanCalcScheduleFromPhases')) {
		$schedule_phases_value = GETPOST('schedule_phases', 'none', 0) !== '' ? GETPOST('schedule_phases', 'none', 0) : (isset($object->schedule_phases) ? $object->schedule_phases : '');
		print '<tr><td>'.$langs->trans("SchedulePhases").'</td><td>';
		print '<textarea name="schedule_phases" id="schedule_phases" rows="5" class="flat" placeholder="'.$langs->trans("SchedulePhasesPlaceholder").'">'.dol_escape_htmltag($schedule_phases_value).'</textarea>';
		print ' <span class="opacitymedium">'.$langs->trans("SchedulePhasesHelp").'</span>';
		print '</td></tr>';
	}
	print '<tr><td></td><td>';
	$previewUrl = DOL_URL_ROOT.'/loan/schedule.php?loanid='.((int) $loanid).'&token='.newToken();
	print ' <a href="'.dol_escape_htmltag($previewUrl).'" class="button" id="preview_fixed_link">'.$langs->trans("Preview").'</a>';
	print '</td></tr>';
	print '</table>';
	print '</div>';
	print '<script type="text/javascript">';
	print "document.getElementById('preview_fixed_link').addEventListener('click', function(e) { e.preventDefault();";
	print " var v = document.getElementById('fixed_monthly_payment').value;";
	print " var u = this.href + '&schedule_rounding=' + encodeURIComponent(document.getElementById('schedule_rounding').value) + '&schedule_days_in_year=' + encodeURIComponent(document.getElementById('schedule_days_in_year').value) + '&schedule_balance_for_interest=' + encodeURIComponent(document.getElementById('schedule_balance_for_interest').value) + '&schedule_rate_type=' + encodeURIComponent(document.getElementById('schedule_rate_type').value);";
	print " if (v) u += '&fixed_monthly_payment=' + encodeURIComponent(v);";
	print " var ratesEl = document.getElementById('schedule_rates'); if (ratesEl && ratesEl.value) u += '&schedule_rates=' + encodeURIComponent(ratesEl.value);";
	print " window.location = u; });";
	print "document.getElementById('schedule_rate_type').addEventListener('change', function() { var row = document.getElementById('schedule_rates_row'); row.style.display = this.value === 'floating' ? '' : 'none'; });";
	print '</script>';
	print '<br>';
}

//print_fiche_titre($langs->trans("FinancialCommitment"));
print '<br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="border centpercent">';

$colspan = 6;
if (count($echeances->lines) > 0) {
	$colspan++;
}

print '<tr class="liste_titre">';
print '<th class="center">'.$langs->trans("Term").'</th>';
print '<th class="center">'.$langs->trans("Date").'</th>';
print '<th class="center">'.$langs->trans("Insurance");
print '<th class="center">'.$langs->trans("InterestAmount").'</th>';
print '<th class="center">'.$langs->trans("Amount").'</th>';
print '<th class="center">'.$langs->trans("CapitalRemain");
print '<br>('.price($object->capital, 0, '', 1, -1, -1, $conf->currency).')';
print '<input type="hidden" name="hi_capital0" id ="hi_capital0" value="'.$object->capital.'">';
print '</th>';
if (count($echeances->lines) > 0) {
	print '<th class="center">'.$langs->trans('DoPayment').'</th>';
}
print '</tr>'."\n";

if (empty($object->id) || $object->nbterm <= 0) {
	if (empty($object->id)) {
		print '<tr><td colspan="'.($colspan).'" class="opacitymedium">'.$langs->trans("SelectLoanFirst").'</td></tr>';
	} else {
		print '<tr><td colspan="'.($colspan).'" class="opacitymedium">'.$langs->trans("SetNbTermsOnLoanCard").'</td></tr>';
	}
} elseif ($object->nbterm > 0 && count($echeances->lines) == 0) {
	// Single date conversion for both standard and fixed-payment display (same as standard branch)
	$datestart_ts_std = is_numeric($object->datestart) ? (int) $object->datestart : dol_stringtotime($object->datestart, 0);
	if (empty($datestart_ts_std) || $datestart_ts_std <= 0) {
		$datestart_ts_std = dol_now();
	}
	$insurance = (float) $object->insurance_amount / $object->nbterm;
	$insurance = price2num($insurance, 'MT');
	$regulInsurance = price2num((float) $object->insurance_amount - ((float) $insurance * $object->nbterm));

	if ($fixed_monthly_payment > 0) {
		// Bank-style schedule: fixed payment + actual days interest (actual/actual, rounding)
		$schedule = loanCalcScheduleWithFixedPayment($fixed_monthly_payment, $object->capital, $object->rate, $datestart_ts_std, $object->nbterm, $insurance, $schedule_options);
		if (!is_array($schedule) || count($schedule) == 0) {
			print '<tr><td colspan="'.($colspan).'" class="warning">'.$langs->trans("NoScheduleComputed").'</td></tr>';
		} else {
			$i = 1;
			foreach ($schedule as $term => $row) {
				$insu = ((float) $insurance + (($i == 1) ? (float) $regulInsurance : 0));
				$datep = $row['datep'];
				$int = $row['interet'];
				$mens = (float) price2num($row['mens']);
				$cap_rest = $row['cap_rest'];
				print '<tr>';
				print '<td class="center" id="n'.$i.'">'.$i.'</td>';
				print '<td class="center" id ="date'.$i.'"><input type="hidden" name="hi_date'.$i.'" id ="hi_date'.$i.'" value="'.$datep.'">'.dol_print_date($datep, 'day').'</td>';
				print '<td class="center amount" id="insurance'.$i.'">'.price($insu, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_insurance'.$i.'" id ="hi_insurance'.$i.'" value="'.$insu.'">';
				print '<td class="center amount" id="interets'.$i.'">'.price($int, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_interets'.$i.'" id ="hi_interets'.$i.'" value="'.$int.'">';
				print '<td class="center"><input class="width75 right" name="mens'.$i.'" id="mens'.$i.'" value="'.price($mens).'" ech="'.$i.'"></td>';
				print '<td class="center amount" id="capital'.$i.'">'.price($cap_rest).'</td><input type="hidden" name="hi_capital'.$i.'" id ="hi_capital'.$i.'" value="'.$cap_rest.'">';
				print '</tr>'."\n";
				$i++;
			}
		}
	} else {
		$i = 1;
		$capital = $object->capital;
		while ($i < $object->nbterm + 1) {
			$mens = price2num($echeances->calcMonthlyPayments($capital, $object->rate / 100, $object->nbterm - $i + 1), 'MT');
			$int = ($capital * ($object->rate / 12)) / 100;
			$int = price2num($int, 'MT');
			$insu = ((float) $insurance + (($i == 1) ? (float) $regulInsurance : 0));
			$cap_rest = price2num((float) $capital - ((float) $mens - (float) $int), 'MT');
			$term_date_ts = dol_time_plus_duree($datestart_ts_std, $i, 'm');
			print '<tr>';
			print '<td class="center" id="n'.$i.'">'.$i.'</td>';
			// Term i due date = loan start + i months (e.g. start 6/3 → Term 1 = 7/3, Term 2 = 8/3)
			print '<td class="center" id ="date'.$i.'"><input type="hidden" name="hi_date'.$i.'" id ="hi_date'.$i.'" value="'.$term_date_ts.'">'.dol_print_date($term_date_ts, 'day').'</td>';
			print '<td class="center amount" id="insurance'.$i.'">'.price($insu, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_insurance'.$i.'" id ="hi_insurance'.$i.'" value="'.$insu.'">';
			print '<td class="center amount" id="interets'.$i.'">'.price($int, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_interets'.$i.'" id ="hi_interets'.$i.'" value="'.$int.'">';
			print '<td class="center"><input class="width75 right" name="mens'.$i.'" id="mens'.$i.'" value="'.price($mens).'" ech="'.$i.'"></td>';
			print '<td class="center amount" id="capital'.$i.'">'.price($cap_rest).'</td><input type="hidden" name="hi_capital'.$i.'" id ="hi_capital'.$i.'" value="'.$cap_rest.'">';
			print '</tr>'."\n";
			$i++;
			$capital = $cap_rest;
		}
	}
} elseif (count($echeances->lines) > 0) {
	$i = 1;
	$capital = $object->capital;
	$insurance = (float) $object->insurance_amount / $object->nbterm;
	$insurance = price2num($insurance, 'MT');
	$regulInsurance = price2num((float) $object->insurance_amount - ((float) $insurance * $object->nbterm));
	$printed = false;
	foreach ($echeances->lines as $line) {
		$mens = $line->amount_capital + $line->amount_interest;
		$int = $line->amount_interest;
		$insu = ((float) $insurance + (($i == 1) ? (float) $regulInsurance : 0));
		$cap_rest = price2num($capital - ($mens - $int), 'MT');

		print '<tr>';
		print '<td class="center" id="n'.$i.'"><input type="hidden" name="hi_rowid'.$i.'" id ="hi_rowid'.$i.'" value="'.$line->id.'">'.$i.'</td>';
		print '<td class="center" id ="date'.$i.'"><input type="hidden" name="hi_date'.$i.'" id ="hi_date'.$i.'" value="'.$line->datep.'">'.dol_print_date($line->datep, 'day').'</td>';
		print '<td class="center amount" id="insurance'.$i.'">'.price($insu, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_insurance'.$i.'" id ="hi_insurance'.$i.'" value="'.$insu.'">';
		print '<td class="center amount" id="interets'.$i.'">'.price($int, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_interets'.$i.'" id ="hi_interets'.$i.'" value="'.$int.'">';
		if (empty($line->fk_bank)) {
			print '<td class="center"><input class="right width75" name="mens'.$i.'" id="mens'.$i.'" value="'.price($mens).'" ech="'.$i.'"></td>';
		} else {
			print '<td class="center amount">'.price($mens, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="mens'.$i.'" id ="mens'.$i.'" value="'.$mens.'">';
		}

		print '<td class="center amount" id="capital'.$i.'">'.price($cap_rest, 0, '', 1, -1, -1, $conf->currency).'</td><input type="hidden" name="hi_capital'.$i.'" id ="hi_capital'.$i.'" value="'.$cap_rest.'">';
		print '<td class="center">';
		if (!empty($line->fk_bank)) {
			print $langs->trans('Paid');
			if (!empty($line->fk_payment_loan)) {
				print '&nbsp;<a href="'.DOL_URL_ROOT.'/loan/payment/card.php?id='.$line->fk_payment_loan.'">('.img_object($langs->trans("Payment"), "payment").' '.$line->fk_payment_loan.')</a>';
			}
		} elseif (!$printed) {
			print '<a class="butAction smallpaddingimp" href="'.DOL_URL_ROOT.'/loan/payment/payment.php?id='.$object->id.'&action=create">'.$langs->trans('DoPayment').'</a>';
			$printed = true;
		}
		print '</td>';
		print '</tr>'."\n";
		$i++;
		$capital = $cap_rest;
	}
}

print '</table>';
print '</div>';

print '</br>';

if (count($echeances->lines) == 0) {
	$label = $langs->trans("Create");
} else {
	$label = $langs->trans("Save");
}
print '<div class="center"><input type="submit" class="button button-add" value="'.$label.'" '.(($pay_without_schedule == 1) ? 'disabled title="'.$langs->trans('CantUseScheduleWithLoanStartedToPaid').'"' : '').'title=""></div>';
print '</form>';

// End of page
llxFooter();
$db->close();
