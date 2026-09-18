<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        export/iras_ir21_export.php
 * \ingroup     sgpayroll
 * \brief       IR21 Tax Clearance data export for departing foreign employees.
 *
 * Employers must notify IRAS at least 1 month before the employee's last day
 * (or immediately if notice < 1 month). The export produces a formatted PDF
 * data sheet with all required IR21 fields, ready for manual entry on
 * IRAS myTax Portal.
 *
 * Covered employees: FIN holders (EP/SP/WP/LTVP) with a cessation_date set.
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include '../../main.inc.php'; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include '../../../main.inc.php'; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include '../../../../main.inc.php'; }
if (!$res) die('Cannot load main.inc.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/class/employee.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

if (!isModEnabled("sgpayroll")) accessforbidden();
if (!$user->hasRight('sgpayroll', 'export', 'iras')) accessforbidden();

$langs->loadLangs(array('sgpayroll@sgpayroll'));

$fkUser = GETPOST('fk_user', 'int');
if (!$fkUser) {
	setEventMessages('Employee ID required', null, 'errors');
	header('Location: ../employee_card.php');
	exit;
}

// ── Load data ──────────────────────────────────────────────────────────────
$emp = new SGPayrollEmployee($db);
$emp->fetchByUser($fkUser);

$empUser = new User($db);
$empUser->fetch($fkUser);

if (empty($emp->cessation_date)) {
	setEventMessages('Cessation date not set on employee profile.', null, 'errors');
	header('Location: ../employee_card.php?fk_user='.$fkUser);
	exit;
}

// Aggregate income for the cessation year from payroll lines
$cessYear = (int)substr($emp->cessation_date, 0, 4);
$sql  = "SELECT SUM(gross_salary) AS gross, SUM(bonus) AS bonus,";
$sql .= " SUM(commission) AS commission, SUM(overtime_pay) AS ot,";
$sql .= " SUM(allowances_total) AS allowances, SUM(employee_cpf) AS emp_cpf,";
$sql .= " SUM(bik_value) AS bik, SUM(net_pay) AS net";
$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
$sql .= " WHERE pl.fk_user = ".(int)$fkUser." AND p.pay_year = ".(int)$cessYear;
$sql .= " AND pl.status IN ('approved','paid')";
$res2  = $db->query($sql);
$inc   = ($res2 && $db->num_rows($res2) > 0) ? $db->fetch_object($res2) : null;

// ── Build PDF ──────────────────────────────────────────────────────────────
$pdf = pdf_getInstance(array(210, 297)); // A4
if (class_exists('TCPDF')) {
	$pdf->SetFont('', '', 9);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
}
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$empName = sgpayroll_format_employee_name($empUser->firstname, $empUser->lastname);

// ── Header ─────────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'IR21 — TAX CLEARANCE DATA SHEET', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'For Employer Submission via IRAS myTax Portal (mytax.iras.gov.sg)', 0, 1, 'C');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 4, 'This document is prepared by HR using SG Payroll module. It is NOT a final submission.', 0, 1, 'C');
$pdf->Ln(4);

// ── Section helper ──────────────────────────────────────────────────────────
$sh = function($title) use ($pdf) {
	$pdf->SetFillColor(220, 230, 245);
	$pdf->SetFont('helvetica', 'B', 9);
	$pdf->Cell(0, 6, $title, 1, 1, 'L', true);
	$pdf->SetFont('helvetica', '', 9);
};
$row2 = function($l1, $v1, $l2, $v2) use ($pdf) {
	$pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(45, 5, $l1.':', 0, 0);
	$pdf->SetFont('helvetica', '', 8);  $pdf->Cell(50, 5, $v1, 0, 0);
	$pdf->SetFont('helvetica', 'B', 8); $pdf->Cell(40, 5, $l2.':', 0, 0);
	$pdf->SetFont('helvetica', '', 8);  $pdf->Cell(40, 5, $v2, 0, 1);
};
$rowAmt = function($label, $amt, $bold=false) use ($pdf) {
	$pdf->SetFont('helvetica', $bold?'B':'', 9);
	$pdf->Cell(140, 5, $label, 0, 0);
	$pdf->Cell(35,  5, number_format(abs((float)$amt), 2), 0, 1, 'R');
};

// ── Part 1: Employer Details ────────────────────────────────────────────────
$sh('PART 1 — EMPLOYER DETAILS');
$row2('Employer Name',  $mysoc->name, 'UEN', getDolGlobalString('SGPAYROLL_COMPANY_UEN'));
$row2('CPF Account No.',getDolGlobalString('SGPAYROLL_CPF_ACCOUNT'), 'Cessation Year', $cessYear);
$pdf->Ln(3);

// ── Part 2: Employee Details ────────────────────────────────────────────────
$sh('PART 2 — EMPLOYEE DETAILS');
$row2('Employee Name',  $empName,            'ID Type',       $emp->id_type ?? 'FIN');
$row2('NRIC/FIN/Passport',$emp->nric_fin??'—','Date of Birth', $emp->dob??'—');
$row2('Nationality',    $emp->citizenship??'','Pass Type',     $emp->pass_type??'—');
$row2('Pass No.',       $emp->pass_number??'—','Pass Expiry',  $emp->pass_expiry??'—');
$row2('Passport No.',   $emp->passport_number??'—','Passport Expiry', $emp->passport_expiry??'—');
$row2('Tax Residency',  ucfirst($emp->tax_residency??'resident'),'Employment Type', ucfirst($emp->employment_type??''));
$pdf->Ln(3);

// ── Part 3: Employment Period ────────────────────────────────────────────────
$sh('PART 3 — EMPLOYMENT PERIOD IN '.$cessYear);
$row2('Start Date', ($emp->work_contract_date ?? 'See HR records'), 'Cessation Date', $emp->cessation_date ?? '—');
$row2('Last Day of Work', $emp->cessation_date ?? '—', 'Notice to IRAS', 'Filed: '.date('d M Y'));
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(180, 0, 0);
$pdf->Cell(0, 5, '⚠  Employer must notify IRAS at least 1 month before last day of employment (Employment Act s. 36).', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// ── Part 4: Income for Year of Cessation ───────────────────────────────────
$sh('PART 4 — INCOME FOR YEAR '.$cessYear.' (SGD)');
$rowAmt('Gross Employment Income (Total Salary)', $inc->gross ?? 0);
$rowAmt('  Bonus / AWS',                          $inc->bonus ?? 0);
$rowAmt('  Commission',                           $inc->commission ?? 0);
$rowAmt('  Overtime Pay',                         $inc->ot ?? 0);
$rowAmt('  Allowances',                           $inc->allowances ?? 0);
$rowAmt('Benefits-in-Kind (BIK)',                 $inc->bik ?? 0);
$rowAmt('Employee CPF Contributions',             $inc->emp_cpf ?? 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(140, 5, 'Total Gross + BIK (Taxable Income Base)', 'T', 0);
$total = ((float)($inc->gross ?? 0)) + ((float)($inc->bik ?? 0));
$pdf->Cell(35, 5, number_format($total, 2), 'T', 1, 'R');
$pdf->Ln(3);

// ── Part 5: Gratuity / Retrenchment Benefits ──────────────────────────────
$sh('PART 5 — GRATUITY / COMPENSATION (if applicable)');
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Enter gratuity, ex-gratia, retrenchment benefit below. Taxable portion must be declared.', 0, 1);
$pdf->SetFont('helvetica', 'B', 8);
foreach (array('Gratuity Paid', 'Retrenchment Benefit', 'Ex-Gratia Payment', 'Notice Pay in Lieu') as $f) {
	$pdf->Cell(110, 6, $f, 1, 0);
	$pdf->Cell(65, 6, 'S$ _______________', 1, 1, 'R');
}
$pdf->Ln(3);

// ── Part 6: Tax Withholding ───────────────────────────────────────────────
$sh('PART 6 — TAX WITHHOLDING STATUS');
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 5,
	"Non-tax-resident employees: employer must withhold 15% of gross employment income (or graduated resident rates if higher).\n"
	."Tax Residency: ".ucfirst($emp->tax_residency ?? 'resident')."\n"
	."For final months salary withheld pending clearance, contact IRAS for directive before releasing.",
0, 'L');
$pdf->Ln(3);

// ── Part 7: Declaration ──────────────────────────────────────────────────
$sh('PART 7 — DECLARATION & SUBMISSION CHECKLIST');
$checkItems = array(
	'Cessation date confirmed and employee notified',
	'All payslips for '.$cessYear.' approved and finalised',
	'Tax clearance submitted on IRAS myTax Portal (mytax.iras.gov.sg → Employers → Tax Clearance)',
	'Final salary withheld until IRAS Clearance Directive received (if non-resident)',
	'Last CPF contribution filed for cessation month',
	'Work pass cancellation filed with MOM within 1 week of last day',
);
$pdf->SetFont('helvetica', '', 8);
foreach ($checkItems as $item) {
	$pdf->Cell(5, 5, '☐', 0, 0);
	$pdf->Cell(0, 5, ' '.$item, 0, 1);
}
$pdf->Ln(4);

$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(80, 5, 'HR Manager Signature: ________________________', 0, 0);
$pdf->Cell(80, 5, 'Date: ________________', 0, 1);
$pdf->Ln(3);

// ── Footer ────────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(0, 4, 'Generated by SG Payroll Module — '.$mysoc->name.' — '.date('d M Y H:i'), 0, 1, 'C');

// ── Output ────────────────────────────────────────────────────────────────
$filename = 'IR21_'.preg_replace('/\s+/','_',$empName).'_'.$cessYear.'.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$filename.'"');
$pdf->Output($filename, 'D');
$db->close();
exit;
