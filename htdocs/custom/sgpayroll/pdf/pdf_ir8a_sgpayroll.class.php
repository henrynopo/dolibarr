<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        pdf/pdf_ir8a_sgpayroll.class.php
 * \ingroup     sgpayroll
 * \brief       IR8A Employee Copy PDF generator.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

/**
 * Class pdf_ir8a_sgpayroll
 */
class pdf_ir8a_sgpayroll
{
	/** @var DoliDB */
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Generate IR8A PDF for a single employee (stream to browser).
	 */
	public function generate($userId, $ya, $mode = 'download')
	{
		list($pdf, $filename) = $this->buildIr8aPdf($userId, $ya);
		$dest = ($mode === 'inline') ? 'I' : 'D';
		$pdf->Output($filename, $dest);
	}

	/**
	 * Render the IR8A PDF to a file on disk — used when archiving on AIS confirmation.
	 *
	 * @param  int     $userId     Dolibarr user ID
	 * @param  int     $ya         Year of Assessment
	 * @param  string  $outputPath Absolute file path (directory must already exist)
	 * @param  object  $langs      Unused, kept for call-site compatibility
	 * @return int                 1 on success, -1 on failure
	 */
	public function write_file_for_user($userId, $ya, $outputPath, $langs = null)
	{
		try {
			list($pdf) = $this->buildIr8aPdf($userId, $ya);
			$pdf->Output($outputPath, 'F');
		} catch (Exception $e) {
			dol_syslog('pdf_ir8a_sgpayroll::write_file_for_user: '.$e->getMessage(), LOG_ERR);
			return -1;
		}
		return (is_file($outputPath) && filesize($outputPath) > 0) ? 1 : -1;
	}

	/**
	 * Build the IR8A form content for one employee / Year of Assessment.
	 *
	 * @param  int   $userId  Dolibarr user ID
	 * @param  int   $ya      Year of Assessment
	 * @return array          array(TCPDF pdf object with content drawn, suggested filename)
	 * @throws Exception      When no AIS data exists for this employee/YA
	 */
	protected function buildIr8aPdf($userId, $ya)
	{
		global $conf, $langs, $mysoc;
		$langs->loadLangs(array('sgpayroll@sgpayroll'));
		require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

		// Fetch AIS data
		$sql = "SELECT ar.*, u.lastname, u.firstname, e.nric_fin, e.id_type, e.dob, e.citizenship, u.gender, u.address, u.town, u.zip, u.job AS designation, e.bank_name AS payment_bank, e.work_contract_date, e.cessation_date";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review ar";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ar.fk_user";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."sgpayroll_employee e ON e.fk_user = ar.fk_user";
		$sql .= " WHERE ar.fk_user = ".(int)$userId." AND ar.year_of_assessment = ".(int)$ya;
		$sql .= " AND ar.entity = ".(int)$conf->entity;
		
		$res = $this->db->query($sql);
		if (!$res || !$obj = $this->db->fetch_object($res)) {
			// Throw instead of die(): the AIS confirm loop catches per employee and keeps going
			throw new Exception('No AIS data found for this employee/YA (fk_user='.$userId.', YA='.$ya.')');
		}

		$pdf = pdf_getInstance();
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
			$pdf->SetAutoPageBreak(TRUE, 10);
		}
		$pdf->AddPage();
		
		// Helper functions for drawing
		$drawBox = function($pdf, $w, $h, $txt, $align='R') {
			$currX = $pdf->GetX();
			$currY = $pdf->GetY();
			$pdf->SetFillColor(255);
			$pdf->Cell($w, $h, $txt, 1, 0, $align, true);
			return array($currX+$w, $currY+$h);
		};

		// Top Header
		$pdf->SetFont('helvetica', 'B', 14);
		$pdf->Cell(30, 7, $ya, 0, 0, 'L');
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->Cell(0, 7, 'FORM IR8A', 0, 1, 'C');
		
		$pdf->SetFillColor(0, 0, 0);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('helvetica', 'B', 7.5);
		$pdf->Cell(0, 4.5, "Return of Employee's Remuneration for the Year Ended 31 Dec ".($ya-1), 0, 1, 'C', true);
		$pdf->Cell(0, 4.5, 'Fill in this form and give it to your employee by 1 Mar '.$ya, 0, 1, 'C', true);
		$pdf->Cell(0, 4.5, '(DO NOT SUBMIT THIS FORM TO IRAS UNLESS REQUESTED TO DO SO)', 0, 1, 'C', true);
		
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('helvetica', 'B', 6.5);
		$pdf->MultiCell(0, 4, "This Form will take about 10 minutes to complete. Please get ready the employee's personal particulars and details of his/her employment income. Please read the explanatory notes when completing this form.", 0, 'L');
		$pdf->Ln(1);

		// Grid: Particulars
		$pdf->SetFont('helvetica', '', 6.5);
		$startX = $pdf->GetX();
		$startY = $pdf->GetY();
		$col1W = 95; $col2W = 50; $col3W = 0; $h = 8;
		
		// Row 1
		$pdf->MultiCell($col1W, $h, "Employer's Tax Ref. No. / UEN\n   ".getDolGlobalString('SGPAYROLL_COMPANY_UEN'), 1, 'L', 0, 0);
		$pdf->MultiCell(0, $h, "Employee's Tax Reference. No.: *NRIC / FIN (Foreign Identification No.) /Passport\n   ".$obj->nric_fin, 1, 'L', 0, 1);
		
		// Row 2
		$pdf->MultiCell($col1W, $h, "Full Name of Employee as per NRIC / FIN / Passport\n   ".sgpayroll_format_employee_name($obj->firstname, $obj->lastname), 1, 'L', 0, 0);
		$pdf->MultiCell($col2W, $h, "Date of Birth\n   ".($obj->dob ? dol_print_date($obj->dob, 'day') : ''), 1, 'L', 0, 0);
		$gender = (strtoupper($obj->gender)=='M'?'Male':(strtoupper($obj->gender)=='F'?'Female':''));
		$pdf->MultiCell(0, $h, "Sex\n   ".$gender, 1, 'L', 0, 1);
		
		// Row 3 (Residential Address - Improved wrapping)
		$address = trim($obj->address.' '.$obj->town.' '.$obj->zip);
		$pdf->MultiCell($col1W, $h, "Residential Address\n   ".$address, 1, 'L', 0, 0, '', '', true, 0, false, true, $h, 'T', true);
		$designation = $obj->designation;
		if (empty($designation)) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
			$eu = new User($this->db);
			$eu->fetch($userId);
			$designation = $eu->job;
		}
		$pdf->MultiCell($col2W, $h, "Designation\n   ".$designation, 1, 'L', 0, 0);
		$pdf->MultiCell(0, $h, "Name of Bank to which salary is credited\n   ".$obj->payment_bank, 1, 'L', 0, 1);
		
		// Row 4
		$cst = ($obj->work_contract_date ? dol_print_date($obj->work_contract_date,'day') : '');
		$cend = ($obj->cessation_date ? dol_print_date($obj->cessation_date,'day') : '');
		$pdf->MultiCell($col1W, $h, "If employment commenced and/or ceased during the year, state:\n(See Explanatory Note 5)", 1, 'L', 0, 0);
		$pdf->MultiCell($col2W, $h, "Date of Commencement\n   ".$cst, 1, 'L', 0, 0);
		$pdf->MultiCell(0, $h, "Date of Cessation\n   ".$cend, 1, 'L', 0, 1);
		
		// INCOME Section
		$pdf->SetFillColor(0, 0, 0);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(150, 4.5, "     INCOME (See Explanatory Note 9 unless otherwise specified)", 1, 0, 'L', true);
		$pdf->Cell(0, 4.5, "$", 1, 1, 'C', true);
		$pdf->SetTextColor(0, 0, 0);
		
		$pdf->SetFont('helvetica', '', 6.5);
		$boxW = 25; $boxH = 4.5; $rX = 175;
		
		// a) Gross Salary — IRAS: income round-down to whole dollar
		$pdf->Cell(10, 6, "a)", 0, 0);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(140, 6, "Gross Salary, Fees, Leave Pay, Wages and Overtime Pay", 0, 0);
		$pdf->SetFont('helvetica', '', 6.5);
		$pdf->SetX($rX); $drawBox($pdf, $boxW, $boxH, price(sgpayroll_round_iras_income($obj->gross_salary), 0, 'none', 1, 0)); $pdf->Ln(6);

		// b) Bonus
		$pdf->Cell(10, 6, "b)", 0, 0);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(140, 6, "Bonus (non-contractual bonus paid in ".($ya-1)." and/or contractual bonus)", 0, 0);
		$pdf->SetFont('helvetica', '', 6.5);
		$pdf->SetX($rX); $drawBox($pdf, $boxW, $boxH, $obj->bonus>0 ? price(sgpayroll_round_iras_income($obj->bonus), 0, 'none', 1, 0) : 'N.A'); $pdf->Ln(6);
		
		// c) Director's fees
		$pdf->Cell(10, 6, "c)", 0, 0);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(140, 6, "Director's fees (approved at the company's AGM/EGM on ... /... /...)", 0, 0);
		$pdf->SetFont('helvetica', '', 6.5);
		$pdf->SetX($rX); $drawBox($pdf, $boxW, $boxH, 'N.A'); $pdf->Ln(6);
		
		// d) Others
		$pdf->Cell(10, 5, "d)", 0, 0);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(140, 5, "Others:", 0, 1);
		$pdf->SetFont('helvetica', '', 6.5);
		
		// 1. Allowances (income: round-down)
		$totAllow = sgpayroll_round_iras_income($obj->transport_allowance + $obj->other_allowances);
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "1. Allowances", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, $totAllow>0 ? price($totAllow, 0, 'none', 1, 0) : 'N.A', 'B', 1, 'R');
		// 2. Commission
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "2. Gross Commission", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, $obj->commission>0 ? price(sgpayroll_round_iras_income($obj->commission), 0, 'none', 1, 0) : 'N.A', 'B', 1, 'R');
		// 3. Lump sum
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "3. Lump sum payment: Gratuity/ Notice Pay/ Ex-gratia payment", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, 'N.A', 'B', 1, 'R');
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "Compensation for loss of office $ 0.00", 0, 1);
		$pdf->SetFont('helvetica', 'B', 8);
		$pdf->Cell(10, 4, "", 0, 0); $pdf->Cell(140, 4, "[See Explanatory Notes 9d (3)]", 0, 1);
		$pdf->SetFont('helvetica', '', 8);
		
		// 4-8 Skipped details for brevity, showing N.A
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "4. Pension/Retirement benefits accrued from 1993 (Other than CPF Benefits)", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, 'N.A', 'B', 1, 'R');
		$pdf->Cell(10, 4, "", 0, 0); $pdf->Cell(140, 4, "Name of Designated Pension or Provident Fund for which employee made compulsory contribution:", 0, 1);
		$pdf->Cell(10, 4, "", 0, 0); $pdf->Cell(140, 4, "................................", 0, 1);
		
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "5. Contributions made by employer to any Pension/Provident Fund constituted outside Singapore", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, 'N.A', 'B', 1, 'R');
		
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "6. Excess/Voluntary contribution to CPF by employer (less amount refunded/to be refunded):", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, 'N.A', 'B', 1, 'R');
		
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "7 i Gains or profits under S10(1)(b), including gains and profits from share options:", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, 'N.A', 'B', 1, 'R');
		$pdf->Cell(10, 4, "", 0, 0); $pdf->Cell(140, 4, "  ii.Gains or profits under S10(1)(g), including gains and profits from share options", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, 'N.A', 'B', 1, 'R');
		
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "8. Value of Benefits-in-kind [See Explanatory Note 12 and complete Appendix 8A]", 0, 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, $obj->bik_value>0 ? price(sgpayroll_round_iras_income($obj->bik_value), 0, 'none', 1, 0) : 'N.A', 'B', 1, 'R');

		$pdf->Ln(2);

		// TOTAL (d1+d2+d8, all income round-down)
		$pdf->SetFont('helvetica', 'B', 8);
		$dTot = $totAllow + sgpayroll_round_iras_income($obj->commission) + sgpayroll_round_iras_income($obj->bik_value);
		$pdf->Cell(165, 6, "TOTAL of items d1 to d8 (excluding 7ii)", 0, 0, 'R');
		$pdf->SetX($rX); $drawBox($pdf, $boxW, 6, $dTot>0 ? price($dTot, 0, 'none', 1, 0) : 'N.A'); $pdf->Ln(6);
		$pdf->SetFont('helvetica', '', 8);
		
		// e)
		$pdf->Cell(10, 5, "e)", 0, 0); $pdf->Cell(140, 5, "1. Remission: Amount of Income $...0.00...........", 0, 1);
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "2. Overseas Posting: *Full Year/Part of the Year (See Explanatory Note 8a)", 0, 1);
		$pdf->Cell(10, 5, "", 0, 0); $pdf->Cell(140, 5, "3. Exempt Income: $ ..0.00.......  (See Explanatory Note 8b)", 0, 1);
		
		// f)
		$pdf->Cell(10, 5, "f)", 0, 0);
		$pdf->Cell(25, 5, "Employee's income", 1, 0);
		$pdf->Cell(165, 5, "If tax is fully borne by employer, DO NOT enter any amount in (i) and (ii)", 1, 1);
		
		$pdf->Cell(10, 5, "", 0, 0);
		$pdf->Cell(25, 5, "tax borne by", 'L R', 0);
		$pdf->Cell(140, 5, "(i) If tax is partially borne by employer, state the amount of income for which tax is borne by employer", 1, 0);
		$pdf->SetX($rX); $drawBox($pdf, $boxW, 5, 'N.A'); $pdf->Ln(5);
		
		$pdf->Cell(10, 5, "", 0, 0);
		$pdf->Cell(25, 5, "employer?", 'L R', 0);
		$pdf->Cell(140, 5, "(ii) If a fixed amount of tax is borne by employee, state the amount of tax to be paid by employee", 1, 0);
		$pdf->SetX($rX); $drawBox($pdf, $boxW, 5, 'N.A'); $pdf->Ln(5);
		
		$pdf->Cell(10, 5, "", 0, 0);
		$pdf->Cell(25, 5, "* YES / NO", 'L B R', 1);
		$pdf->Ln(1);

		// DEDUCTIONS Section
		$pdf->SetFillColor(0, 0, 0);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(0, 4.5, "     DEDUCTIONS (See Explanatory Note 10 - Deductions)", 1, 1, 'L', true);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('helvetica', '', 6);
		
		$pdf->Cell(165, 4, "EMPLOYEE'S COMPULSORY contribution to *CPF/Designated Pension or Provident Fund (less amount refunded/to be", 'L T', 0);
		$pdf->Cell(0, 4, "", 'R T', 1);
		
		$pdf->Cell(165, 4, "refunded) Name of Fund : CPF", 'L', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, "", 'R', 1, 'R');
		
		$pdf->Cell(165, 4, "(Apply the appropriate CPF rates published by CPF Board on its website 'www.cpf.gov.sg'. Do not include excess/voluntary", 'L', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, price(sgpayroll_round_iras_deduction($obj->employee_cpf), 0, 'none', 1, 0), 'R', 1, 'C');
		
		$pdf->Cell(165, 4, "contributions to CPF, voluntary contributions to MediSave Account, voluntary contributions to CPF Retirement Sum", 'L', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, "", 'R', 1, 'R');
		
		$pdf->Cell(165, 4, "Topping-up Scheme, SRS contributions and contributions to Overseas Pension or Provident Fund in this item)", 'L B', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, "", 'R B', 1, 'R');
		
		$totDonation = sgpayroll_round_iras_deduction((float)($obj->mbmf ?? 0) + (float)($obj->sinda ?? 0) + (float)($obj->cdac ?? 0) + (float)($obj->ecf ?? 0));
		$pdf->Cell(165, 4, "Donations deducted from salaries for:", 'L T', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, "", 'R T', 1);
		$pdf->Cell(165, 4, "*Yayasan Mendaki Fund/Community Chest of Singapore/SINDA/CDAC/ECF/Other tax exempt donations", 'L B', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 4, $totDonation>0 ? price($totDonation, 0, 'none', 1, 0) : '$0.00', 'R B', 1, 'C');
		
		$pdf->Cell(165, 5, "Contributions deducted from salaries to Mosque Building Fund:", 'L B', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, '$0.00', 'R B', 1, 'C');
		
		$pdf->Cell(165, 5, "Life Insurance premiums deducted from salaries:", 'L B', 0);
		$pdf->SetX($rX); $pdf->Cell($boxW, 5, '$0.00', 'R B', 1, 'C');
		
		// DECLARATION Section
		$pdf->SetFillColor(0, 0, 0);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(0, 4.5, "     DECLARATION (See Explanatory Note 2)", 1, 1, 'L', true);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('helvetica', '', 6.5);
		
		$pdf->Cell(25, 5, "Name of Employer:", 'L', 0);
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(0, 5, "...".$mysoc->name.".........................................................................................", 'R', 1);
		$pdf->SetFont('helvetica', '', 6.5);
		
		$pdf->Cell(25, 6, "Address of Employer:", 'L', 0);
		$pdf->SetFont('helvetica', '', 8);
		$comp_addr = str_replace(array("\r","\n"), ' ', $mysoc->address);
		$pdf->Cell(0, 6, "   ".substr($comp_addr, 0, 100), 'R', 1);
		
		$pdf->Cell(0, 6, "                                           ...................................................................................................................................................", 'L R', 1);
		$pdf->Cell(70, 6, "Name of authorised person making the declaration", 'L B', 0);
		$pdf->Cell(30, 6, "Designation", 'B', 0);
		$pdf->Cell(30, 6, "Tel. No./Email", 'B', 0);
		$pdf->Cell(35, 6, "Signature", 'B', 0);
		$pdf->Cell(0, 6, "Date", 'R B', 1);
		
		$pdf->SetFont('helvetica', 'B', 8);
		$pdf->Cell(0, 5, "There are penalties for failing to give a return or furnishing an incorrect or late return.", 0, 1, 'C');
		$pdf->SetFont('helvetica', 'B', 7);
		$pdf->Cell(100, 4, "IR8A (1/".($ya).")", 0, 0, 'L');
		$pdf->Cell(0, 4, "* Delete where applicable", 0, 1, 'R');

		$filename = 'IR8A_YA'.$ya.'_'.preg_replace('/\s+/', '_', $obj->lastname).'.pdf';
		return array($pdf, $filename);
	}
}
