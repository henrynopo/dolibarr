<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file export/cpf_export.php — CPF Board submission flat-file export */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

dol_include_once('sgpayroll/lib/sgpayroll.lib.php');
if (!isModEnabled("sgpayroll")) accessforbidden();
if (!$user->hasRight('sgpayroll', 'export', 'cpf')) accessforbidden();

$payYear   = GETPOST('pay_year',  'int') ?: (int)date('Y');
$payMonth  = GETPOST('pay_month', 'int') ?: (int)date('m');
$cpfAcct   = getDolGlobalString('SGPAYROLL_CPF_ACCOUNT');
$companyUen= getDolGlobalString('SGPAYROLL_COMPANY_UEN');

// Fetch approved payroll lines
$sql  = "SELECT pl.*, e.nric_fin, e.citizenship, e.pass_type, u.lastname, u.firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user";
$sql .= " WHERE p.pay_year = ".(int)$payYear." AND p.pay_month = ".(int)$payMonth;
$sql .= " AND p.entity = ".(int)$conf->entity;
$sql .= " AND pl.status IN ('approved','paid')";
// Only employees with CPF obligation (SC or PR)
$sql .= " AND e.citizenship IN ('SC','PR1Y','PR2Y','PR3Y')";
$sql .= " ORDER BY u.lastname, u.firstname";
$res  = $db->query($sql);
$rows = array();
while ($res && $obj = $db->fetch_object($res)) $rows[] = $obj;

if (empty($rows)) {
	setEventMessages('No approved CPF records for '.str_pad($payMonth,2,'0',STR_PAD_LEFT).'/'.$payYear, null, 'errors');
	header('Location: ../payslip_list.php'); exit;
}

// ── CPF Board CSV format ──────────────────────────────────────────────────────
// Reference: CPF Board e-Submission (simplified column set)
// Header record
$lines   = array();
$lines[] = implode(',', array(
	'RecordType', 'EmployerRefNo', 'SubmissionMonth', 'SubmissionYear', 'TotalRecords'
));
$lines[] = implode(',', array(
	'H',
	$cpfAcct,
	str_pad($payMonth, 2, '0', STR_PAD_LEFT),
	$payYear,
	count($rows)
));
// Column header
$lines[] = implode(',', array(
	'RecordType','NRIC_FIN','EmployeeName','OrdinaryWage','AdditionalWage',
	'EmployeeCPF','EmployerCPF','SDL','Citizenship','PassType'
));

$totalOW = 0; $totalAW = 0; $totalEmpCPF = 0; $totalErCPF = 0; $totalSDL = 0;
foreach ($rows as $r) {
	$ow  = number_format((float)$r->ordinary_wages,  2, '.', '');
	$aw  = number_format((float)$r->additional_wages, 2, '.', '');
	$ec  = number_format((float)$r->employee_cpf,     2, '.', '');
	$erc = number_format((float)$r->employer_cpf,     2, '.', '');
	$sdl = number_format((float)$r->sdl_amount,       2, '.', '');
	$totalOW    += (float)$r->ordinary_wages;
	$totalAW    += (float)$r->additional_wages;
	$totalEmpCPF+= (float)$r->employee_cpf;
	$totalErCPF += (float)$r->employer_cpf;
	$totalSDL   += (float)$r->sdl_amount;
	$lines[] = implode(',', array(
		'D',
		$r->nric_fin ?? '',
		'"'.str_replace('"', '""', trim(sgpayroll_csv_safe(sgpayroll_format_employee_name($r->firstname, $r->lastname)))).'"',
		$ow, $aw, $ec, $erc, $sdl,
		$r->citizenship ?? '',
		$r->pass_type   ?? '',
	));
}

// Trailer
$lines[] = implode(',', array(
	'T',
	number_format($totalOW, 2, '.', ''),
	number_format($totalAW, 2, '.', ''),
	number_format($totalEmpCPF, 2, '.', ''),
	number_format($totalErCPF, 2, '.', ''),
	number_format($totalSDL, 2, '.', ''),
));

$output   = implode("\r\n", $lines)."\r\n";
$filename = 'CPF_'.str_pad($payMonth,2,'0',STR_PAD_LEFT).'_'.$payYear.'_'.date('Ymd').'.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.strlen($output));
echo $output;
$db->close(); exit;
