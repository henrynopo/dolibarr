<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        pdf/pdf_summary_sgpayroll.class.php
 * \ingroup sghr
 * \brief       Monthly Payroll Summary PDF generator (HR/Management Report).
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
dol_include_once('sghr/class/payrollrecord.class.php');
dol_include_once('sghr/lib/sghr.lib.php');

/**
 * Class pdf_summary_sgpayroll
 */
class pdf_summary_sgpayroll
{
	/** @var DoliDB */
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Generate and stream summary PDF.
	 * @param  int     $year
	 * @param  int     $month
	 * @param  string  $mode  'download' | 'inline'
	 */
	public function generate($year, $month, $mode = 'download')
	{
		global $conf, $langs, $mysoc;
		$langs->loadLangs(array('sghr@sghr'));

		$rows = SghrRecord::fetchList($this->db, $year, $month);
		$monthLabel = date('F Y', mktime(0, 0, 0, $month, 1, $year));

		$pdf = pdf_getInstance(array(297, 210)); // A4 Landscape
		if (class_exists('TCPDF')) {
			$pdf->SetFont('', '', 8);
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(true);
		}
		$pdf->SetAutoPageBreak(true, 15);
		$pdf->SetMargins(10, 15, 10);
		$pdf->AddPage();

		// Header
		$pdf->SetFont('helvetica', 'B', 12);
		$pdf->Cell(0, 8, dol_string_unaccent($mysoc->name), 0, 1, 'L');
		$pdf->SetFont('helvetica', 'B', 10);
		$pdf->Cell(0, 6, 'PAYROLL SUMMARY REPORT — '.$monthLabel, 0, 1, 'L');
		$pdf->Ln(2);

		// Table Header
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetFont('helvetica', 'B', 8);
		$cols = array(
			array('Employee Name', 55, 'L'),
			array('Gross Salary', 28, 'R'),
			array('EE CPF', 25, 'R'),
			array('ER CPF', 25, 'R'),
			array('SDL', 15, 'R'),
			array('SHG', 15, 'R'),
			array('Claims', 22, 'R'),
			array('Net Pay', 28, 'R'),
			array('Status', 22, 'C'),
		);
		foreach ($cols as $c) $pdf->Cell($c[1], 6, $c[0], 1, 0, $c[2], true);
		$pdf->Ln();

		$pdf->SetFont('helvetica', '', 8);
		$totals = array('gross'=>0, 'ecpf'=>0, 'ercpf'=>0, 'sdl'=>0, 'shg'=>0, 'claims'=>0, 'net'=>0);
		
		foreach ($rows as $r) {
			$name = dol_string_unaccent(sgpayroll_format_employee_name($r->firstname, $r->lastname));
			$shg = ($r->shg_cdac??0) + ($r->shg_ecf??0) + ($r->shg_mbmf??0) + ($r->shg_sinda??0);
			
			$pdf->Cell($cols[0][1], 6, $name, 1, 0, 'L');
			$pdf->Cell($cols[1][1], 6, price($r->gross_salary), 1, 0, 'R');
			$pdf->Cell($cols[2][1], 6, price($r->employee_cpf), 1, 0, 'R');
			$pdf->Cell($cols[3][1], 6, price($r->employer_cpf), 1, 0, 'R');
			$pdf->Cell($cols[4][1], 6, price($r->sdl_amount), 1, 0, 'R');
			$pdf->Cell($cols[5][1], 6, price($shg), 1, 0, 'R');
			$pdf->Cell($cols[6][1], 6, price($r->claims_total), 1, 0, 'R');
			$pdf->Cell($cols[7][1], 6, price($r->net_pay), 1, 0, 'R');
			$pdf->Cell($cols[8][1], 6, $langs->trans('PayrollStatus'.$r->status), 1, 1, 'C');

			$totals['gross'] += $r->gross_salary;
			$totals['ecpf']  += $r->employee_cpf;
			$totals['ercpf'] += $r->employer_cpf;
			$totals['sdl']   += $r->sdl_amount;
			$totals['shg']   += $shg;
			$totals['claims']+= $r->claims_total;
			$totals['net']   += $r->net_pay;
		}

		// Grand Totals
		$pdf->SetFont('helvetica', 'B', 8);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->Cell($cols[0][1], 7, 'GRAND TOTAL', 1, 0, 'R', true);
		$pdf->Cell($cols[1][1], 7, price($totals['gross']), 1, 0, 'R', true);
		$pdf->Cell($cols[2][1], 7, price($totals['ecpf']), 1, 0, 'R', true);
		$pdf->Cell($cols[3][1], 7, price($totals['ercpf']), 1, 0, 'R', true);
		$pdf->Cell($cols[4][1], 7, price($totals['sdl']), 1, 0, 'R', true);
		$pdf->Cell($cols[5][1], 7, price($totals['shg']), 1, 0, 'R', true);
		$pdf->Cell($cols[6][1], 7, price($totals['claims']), 1, 0, 'R', true);
		$pdf->Cell($cols[7][1], 7, price($totals['net']), 1, 0, 'R', true);
		$pdf->Cell($cols[8][1], 7, '', 1, 1, 'C', true);

		$filename = 'Payroll_Summary_'.$year.'_'.str_pad($month, 2, '0', STR_PAD_LEFT).'.pdf';
		$dest = ($mode === 'inline') ? 'I' : 'D';
		$pdf->Output($filename, $dest);
	}
}
