<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file export/bank_giro_export.php — Bank salary GIRO file (DBS / UOB / OCBC) */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';
if (!isModEnabled("sgpayroll")) accessforbidden();
if (!$user->hasRight('sgpayroll', 'payroll', 'approve')) accessforbidden();

$payYear   = GETPOST('pay_year',  'int') ?: (int)date('Y');
$payMonth  = GETPOST('pay_month', 'int') ?: (int)date('m');
$bankFmt   = GETPOST('bank_format', 'aZ') ?: 'dbs'; // dbs | uob | ocbc

// Fetch approved payroll lines with bank details
$sql  = "SELECT pl.net_pay, pl.fk_user,";
$sql .= " e.bank_name, e.bank_branch_code, e.bank_account, e.payment_mode,";
$sql .= " u.lastname, u.firstname";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = pl.fk_user";
$sql .= " WHERE p.pay_year=".(int)$payYear." AND p.pay_month=".(int)$payMonth;
$sql .= " AND pl.status IN ('approved', 'paid') AND e.payment_mode='bank'";
$sql .= " AND e.bank_account != '' ORDER BY u.lastname";
$res  = $db->query($sql); $rows = array();
while ($res && $obj = $db->fetch_object($res)) $rows[] = $obj;

if (empty($rows)) {
	setEventMessages('No approved bank salary records found.', null, 'errors');
	header('Location: ../payslip_list.php'); exit;
}

$payDate   = $payYear.str_pad($payMonth, 2, '0', STR_PAD_LEFT).'28'; // YYYYMMDD
$totalAmt  = array_sum(array_column((array)$rows, 'net_pay'));
$totalAmt  = (float)$totalAmt;
$numRec    = count($rows);

// ── Format generators ─────────────────────────────────────────────────────────

function giro_dbs($rows, $payDate, $totalAmt, $companyUen)
{
	// DBS IDEAL PayNow/GIRO batch CSV (simplified format)
	$lines = array();
	$lines[] = 'DEBIT_REF,PAY_DATE,CREDIT_TYPE,NRIC_PASSPORT,CREDIT_ACCOUNT,BANK_CODE,CREDIT_AMOUNT,RECIPIENT_NAME,REFERENCE';
	foreach ($rows as $r) {
		$lines[] = implode(',', array(
			'SAL-'.$payDate,
			$payDate,
			'GIRO',
			'',
			$r->bank_account,
			$r->bank_branch_code,
			number_format((float)$r->net_pay, 2, '.', ''),
			'"'.str_replace('"','""', trim(sgpayroll_csv_safe(sgpayroll_format_employee_name($r->firstname, $r->lastname)))).'"',
			'Salary '.$payDate,
		));
	}
	return implode("\r\n", $lines)."\r\n";
}

function giro_uob($rows, $payDate, $totalAmt)
{
	// UOB BIBPlus format (fixed-width inspired)
	$lines = array();
	$lines[] = sprintf('H%-10s%-8s%08.2f%06d', 'PAYROLL', $payDate, $totalAmt, count($rows));
	foreach ($rows as $r) {
		$name = str_pad(substr(strtoupper(trim(sgpayroll_csv_safe(sgpayroll_format_employee_name($r->firstname, $r->lastname)))), 0, 35), 35);
		$acct = str_pad($r->bank_account, 16);
		$brnc = str_pad($r->bank_branch_code, 3);
		$amt  = sprintf('%012.2f', (float)$r->net_pay);
		$lines[] = 'D'.$name.$brnc.$acct.$amt.'Salary';
	}
	$lines[] = sprintf('T%012.2f%06d', $totalAmt, count($rows));
	return implode("\r\n", $lines)."\r\n";
}

function giro_ocbc($rows, $payDate, $totalAmt, $companyUen)
{
	// OCBC Velocity CSV
	$lines = array();
	$lines[] = 'Transaction_Date,Debit_Account,Payment_Type,Beneficiary_Name,Beneficiary_Bank_Code,Beneficiary_Account,Amount,Reference';
	foreach ($rows as $r) {
		$lines[] = implode(',', array(
			$payDate,
			'',
			'FAST',
			'"'.str_replace('"','""', trim(sgpayroll_csv_safe(sgpayroll_format_employee_name($r->firstname, $r->lastname)))).'"',
			$r->bank_branch_code,
			$r->bank_account,
			number_format((float)$r->net_pay, 2, '.', ''),
			'Salary'.substr($payDate, 0, 6),
		));
	}
	return implode("\r\n", $lines)."\r\n";
}

$companyUen = getDolGlobalString('SGPAYROLL_COMPANY_UEN');
switch ($bankFmt) {
	case 'uob':
		$output   = giro_uob($rows, $payDate, $totalAmt);
		$ext      = 'txt';
		break;
	case 'ocbc':
		$output   = giro_ocbc($rows, $payDate, $totalAmt, $companyUen);
		$ext      = 'csv';
		break;
	default: // dbs
		$output   = giro_dbs($rows, $payDate, $totalAmt, $companyUen);
		$ext      = 'csv';
}

$filename = 'GIRO_'.strtoupper($bankFmt).'_'.str_pad($payMonth,2,'0',STR_PAD_LEFT).'_'.$payYear.'.'.($ext);
header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo $output;
$db->close(); exit;
