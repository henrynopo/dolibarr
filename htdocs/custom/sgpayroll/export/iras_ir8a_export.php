<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file export/iras_ir8a_export.php — IRAS AIS IR8A XML export (AIS-API 2.0)
 *  Includes Appendix 8A (Benefits in Kind) and Appendix 8B (Stock Options) sections
 *  when data exists for the employee.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))      { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))    { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

dol_include_once('sgpayroll/lib/sgpayroll.lib.php');
if (!isModEnabled('sgpayroll'))                              accessforbidden('Module not enabled');
if (!$user->hasRight('sgpayroll', 'export', 'iras'))         accessforbidden();

dol_syslog('sgpayroll/export/iras_ir8a_export.php called by user '.$user->id, LOG_INFO);

$ya         = GETPOST('ya', 'int') ?: (int)date('Y');
$incomeYear = $ya - 1;
$companyUen  = getDolGlobalString('SGPAYROLL_COMPANY_UEN');
$companyName = $mysoc->name;

// ── 1. Fetch confirmed AIS review rows ───────────────────────────────────────
$sql  = "SELECT ar.*, e.nric_fin, e.id_type, e.dob, e.cessation_date,";
$sql .= " u.lastname, u.firstname, u.address, u.zip, u.town, u.rowid AS user_id";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review ar";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ar.fk_user";
$sql .= " LEFT JOIN  ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = ar.fk_user AND e.entity = ar.entity";
$sql .= " WHERE ar.year_of_assessment = ".(int)$ya." AND ar.entity = ".(int)$conf->entity;
$sql .= " AND ar.status = 'approver_confirmed'";
$sql .= " ORDER BY u.lastname, u.firstname";
$res  = $db->query($sql);
$rows = array();
while ($res && $obj = $db->fetch_object($res)) $rows[] = $obj;

if (empty($rows)) {
	setEventMessages('No confirmed AIS records found for YA '.$ya, null, 'errors');
	header('Location: ../iras_ais_review.php?ya='.$ya);
	exit;
}

// ── 2. Pre-load Appendix 8A data keyed by user_id ───────────────────────────
$a8a = array();
$sql8a = "SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_appendix8a WHERE income_year = ".(int)$incomeYear." AND entity = ".(int)$conf->entity;
$res8a = $db->query($sql8a);
while ($res8a && $obj = $db->fetch_object($res8a)) {
	$a8a[(int)$obj->fk_user] = $obj;
}

// ── 3. Pre-load Appendix 8B data keyed by user_id (multiple rows per employee) ─
$a8b = array();
$sql8b = "SELECT * FROM ".MAIN_DB_PREFIX."sgpayroll_appendix8b WHERE income_year = ".(int)$incomeYear." AND entity = ".(int)$conf->entity." ORDER BY fk_user, exercise_date";
$res8b = $db->query($sql8b);
while ($res8b && $obj = $db->fetch_object($res8b)) {
	$a8b[(int)$obj->fk_user][] = $obj;
}

// ── 4. Build XML (AIS-API 2.0 format) ────────────────────────────────────────
$xml  = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
$xml .= '<AIS xmlns="http://www.iras.gov.sg/AIS/v2.0">'."\n";
$xml .= '  <SubmissionBatch>'."\n";
$xml .= '    <SubmitterUEN>'.htmlspecialchars($companyUen, ENT_XML1).'</SubmitterUEN>'."\n";
$xml .= '    <SubmitterName>'.htmlspecialchars($companyName, ENT_XML1).'</SubmitterName>'."\n";
$xml .= '    <PeriodOfEmployment>'.$incomeYear.'</PeriodOfEmployment>'."\n";
$xml .= '    <DateGenerated>'.date('Y-m-d').'</DateGenerated>'."\n";
$xml .= '    <TotalRecords>'.count($rows).'</TotalRecords>'."\n";
$xml .= '    <Records>'."\n";

foreach ($rows as $r) {
	$uid         = (int)$r->user_id;
	$fullAddress = trim(($r->address ?? '').' '.($r->zip ?? '').' '.($r->town ?? ''));
	$ceaseDate   = !empty($r->cessation_date) && $r->cessation_date != '0000-00-00'
	               ? $r->cessation_date : $incomeYear.'-12-31';

	$xml .= '      <IR8A>'."\n";
	$xml .= '        <EmployeeIdType>'.htmlspecialchars($r->id_type ?? 'NRIC', ENT_XML1).'</EmployeeIdType>'."\n";
	$xml .= '        <EmployeeId>'.htmlspecialchars($r->nric_fin ?? '', ENT_XML1).'</EmployeeId>'."\n";
	$xml .= '        <EmployeeName>'.htmlspecialchars(trim(sgpayroll_format_employee_name($r->firstname, $r->lastname)), ENT_XML1).'</EmployeeName>'."\n";
	$xml .= '        <EmployeeAddress>'.htmlspecialchars($fullAddress, ENT_XML1).'</EmployeeAddress>'."\n";
	if (!empty($r->dob) && $r->dob != '0000-00-00') {
		$xml .= '        <DateOfBirth>'.htmlspecialchars($r->dob, ENT_XML1).'</DateOfBirth>'."\n";
	}
	// Employment period
	$xml .= '        <PeriodStart>'.$incomeYear.'-01-01</PeriodStart>'."\n";
	$xml .= '        <PeriodEnd>'.htmlspecialchars($ceaseDate, ENT_XML1).'</PeriodEnd>'."\n";
	// IRAS: income round-down, deductions round-up (submit-employment-income-records); output 2 decimals for schema
	$xml .= '        <GrossEmploymentIncome>'.number_format(sgpayroll_round_iras_income($r->gross_salary), 2, '.', '').'</GrossEmploymentIncome>'."\n";
	$xml .= '        <Bonus>'.number_format(sgpayroll_round_iras_income($r->bonus), 2, '.', '').'</Bonus>'."\n";
	$xml .= '        <DirectorFees>'.number_format(sgpayroll_round_iras_income($r->director_fees ?? 0), 2, '.', '').'</DirectorFees>'."\n";
	$xml .= '        <Commission>'.number_format(sgpayroll_round_iras_income($r->commission), 2, '.', '').'</Commission>'."\n";
	$xml .= '        <OvertimePay>'.number_format(sgpayroll_round_iras_income($r->overtime_pay), 2, '.', '').'</OvertimePay>'."\n";
	$xml .= '        <TransportAllowance>'.number_format(sgpayroll_round_iras_income($r->transport_allowance), 2, '.', '').'</TransportAllowance>'."\n";
	$xml .= '        <OtherAllowances>'.number_format(sgpayroll_round_iras_income($r->other_allowances), 2, '.', '').'</OtherAllowances>'."\n";
	$xml .= '        <BenefitsInKind>'.number_format(sgpayroll_round_iras_income($r->bik_value), 2, '.', '').'</BenefitsInKind>'."\n";
	$xml .= '        <Gratuity>'.number_format(sgpayroll_round_iras_income($r->gratuity ?? 0), 2, '.', '').'</Gratuity>'."\n";
	$xml .= '        <EmployeeCPF>'.number_format(sgpayroll_round_iras_deduction($r->employee_cpf), 2, '.', '').'</EmployeeCPF>'."\n";

	// ── Appendix 8A (BIK details) — include only when data exists ───────────
	if (!empty($a8a[$uid])) {
		$bik = $a8a[$uid];
		$xml .= '        <Appendix8A>'."\n";
		if ($bik->car_benefit > 0) {
			$xml .= '          <CarBenefit>'.number_format(sgpayroll_round_iras_income($bik->car_benefit), 2, '.', '').'</CarBenefit>'."\n";
			$xml .= '          <CarPetrol>'.number_format(sgpayroll_round_iras_income($bik->car_petrol), 2, '.', '').'</CarPetrol>'."\n";
			$xml .= '          <DriverBenefit>'.number_format(sgpayroll_round_iras_income($bik->driver_benefit), 2, '.', '').'</DriverBenefit>'."\n";
		}
		if ($bik->accommodation_value > 0) {
			$xml .= '          <AccommodationType>'.htmlspecialchars($bik->accommodation_type ?? '', ENT_XML1).'</AccommodationType>'."\n";
			$xml .= '          <AccommodationValue>'.number_format(sgpayroll_round_iras_income($bik->accommodation_value), 2, '.', '').'</AccommodationValue>'."\n";
		}
		if ($bik->home_leave_passage > 0) {
			$xml .= '          <HomeLeavePassage>'.number_format(sgpayroll_round_iras_income($bik->home_leave_passage), 2, '.', '').'</HomeLeavePassage>'."\n";
		}
		if ($bik->education_benefit > 0) {
			$xml .= '          <EducationBenefit>'.number_format(sgpayroll_round_iras_income($bik->education_benefit), 2, '.', '').'</EducationBenefit>'."\n";
		}
		if ($bik->club_membership > 0) {
			$xml .= '          <ClubMembership>'.number_format(sgpayroll_round_iras_income($bik->club_membership), 2, '.', '').'</ClubMembership>'."\n";
		}
		if ($bik->other_bik_value > 0) {
			$xml .= '          <OtherBIKDescription>'.htmlspecialchars($bik->other_bik_desc ?? '', ENT_XML1).'</OtherBIKDescription>'."\n";
			$xml .= '          <OtherBIKValue>'.number_format(sgpayroll_round_iras_income($bik->other_bik_value), 2, '.', '').'</OtherBIKValue>'."\n";
		}
		$xml .= '          <TotalBIK>'.number_format(sgpayroll_round_iras_income($bik->total_bik), 2, '.', '').'</TotalBIK>'."\n";
		$xml .= '        </Appendix8A>'."\n";
	}

	// ── Appendix 8B (Stock Options) — include only when data exists ──────────
	if (!empty($a8b[$uid])) {
		$xml .= '        <Appendix8B>'."\n";
		foreach ($a8b[$uid] as $opt) {
			$xml .= '          <ESOPGrant>'."\n";
			$xml .= '            <SchemeName>'.htmlspecialchars($opt->scheme_name ?? '', ENT_XML1).'</SchemeName>'."\n";
			$xml .= '            <SchemeType>'.htmlspecialchars($opt->scheme_type ?? '', ENT_XML1).'</SchemeType>'."\n";
			if (!empty($opt->grant_date)    && $opt->grant_date != '0000-00-00')    $xml .= '            <GrantDate>'.htmlspecialchars($opt->grant_date, ENT_XML1).'</GrantDate>'."\n";
			if (!empty($opt->vesting_date)  && $opt->vesting_date != '0000-00-00')  $xml .= '            <VestingDate>'.htmlspecialchars($opt->vesting_date, ENT_XML1).'</VestingDate>'."\n";
			if (!empty($opt->exercise_date) && $opt->exercise_date != '0000-00-00') $xml .= '            <ExerciseDate>'.htmlspecialchars($opt->exercise_date, ENT_XML1).'</ExerciseDate>'."\n";
			$xml .= '            <SharesExercised>'.((int)$opt->shares_exercised).'</SharesExercised>'."\n";
			$xml .= '            <TaxableGain>'.number_format(sgpayroll_round_iras_income($opt->total_taxable_gain), 2, '.', '').'</TaxableGain>'."\n";
			$xml .= '          </ESOPGrant>'."\n";
		}
		$xml .= '        </Appendix8B>'."\n";
	}

	$xml .= '      </IR8A>'."\n";
}

$xml .= '    </Records>'."\n";
$xml .= '  </SubmissionBatch>'."\n";
$xml .= '</AIS>'."\n";

dol_syslog('sgpayroll IR8A XML generated: '.count($rows).' records YA='.$ya, LOG_INFO);

// ── 5. Stream download ────────────────────────────────────────────────────────
$filename = 'IR8A_YA'.$ya.'_'.date('Ymd_His').'.xml';
header('Content-Type: application/xml; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($xml));
echo $xml;
$db->close();
exit;
