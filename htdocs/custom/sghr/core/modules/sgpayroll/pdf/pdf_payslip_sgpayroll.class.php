<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        pdf/pdf_payslip_sgpayroll.class.php
 * \ingroup sghr
 * \brief       MOM-compliant payslip PDF generator.
 *              Uses Dolibarr bundled TCPDF library.
 *              Uses Talenox-style 2 column dual layout design.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
dol_include_once('sghr/class/payrollrecord.class.php');
dol_include_once('sghr/class/employee.class.php');
dol_include_once('sghr/class/payrollcalc.class.php');
dol_include_once('sghr/lib/sghr.lib.php');

/**
 * Class pdf_payslip_sgpayroll
 */
class pdf_payslip_sgpayroll
{
	/** @var DoliDB */
	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function generate($lineId, $mode = 'download', $outputPath = '')
	{
		global $conf, $langs, $mysoc;
		$langs->loadLangs(array('sghr@sghr'));

		if (empty($mysoc)) {
			require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
			$mysoc = new Societe($this->db);
			$mysoc->setMysoc($conf);
		}

		// ── Load payroll line ─────────────────────────────────────────────────
		$line = new SghrRecord($this->db);
		if (!$line->fetch($lineId)) {
			if ($mode === 'save') return false;
			accessforbidden('Payroll record not found');
		}

		// ── Load employee profile ─────────────────────────────────────────────
		$emp = new SghrEmployee($this->db);
		$emp->fetchByUser($line->fk_user);

		// ── Load Dolibarr user ────────────────────────────────────────────────
		$empUser = new User($this->db);
		$empUser->fetch($line->fk_user);

        // Dates
        $dtFirst = mktime(0, 0, 0, (int)$line->pay_month, 1, (int)$line->pay_year);
        $dtLast  = mktime(0, 0, 0, (int)$line->pay_month + 1, 0, (int)$line->pay_year);
        $periodFrom = date('d M Y', $dtFirst);
        $periodTo   = date('d M Y', $dtLast);
        
        // Payment date
        if (!empty($line->payment_date)) {
			$payDateDisplay = dol_print_date($this->db->jdate($line->payment_date), 'day');
		} else {
			$payDateDisplay = $periodTo;
		}

        // Calculate actual worked period for the label (truncate by employment dates)
        $empStartTS = is_numeric($emp->work_contract_date) ? (int)$emp->work_contract_date : strtotime((string)$emp->work_contract_date);
        if (empty($emp->work_contract_date) || $empStartTS <= 0) $empStartTS = $dtFirst;
        
        $empEndTS = is_numeric($emp->cessation_date) ? (int)$emp->cessation_date : strtotime((string)$emp->cessation_date);
        if (empty($emp->cessation_date) || $empEndTS <= 0) $empEndTS = $dtLast;
        
        $actualStart = max($dtFirst, $empStartTS);
        $actualEnd = min($dtLast, $empEndTS);
        
        $periodLabel = date('d M y', $actualStart).' - '.date('d M y', $actualEnd);

		// ── Initialise TCPDF ──────────────────────────────────────────────────
		$pdf = pdf_getInstance(array(210, 297)); // A4
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetAutoPageBreak(true, 15);
		$pdf->SetMargins(15, 15, 15);
		$pdf->AddPage();

        // Build Data
        
        $empName = htmlspecialchars(sgpayroll_format_employee_name($empUser->firstname, $empUser->lastname), ENT_QUOTES);
        
        $empNric = htmlspecialchars($emp->nric_fin ?: '', ENT_QUOTES);
        if (empty($empNric)) {
            $empUser->fetch_optionals();
            if (!empty($empUser->array_options['options_national_id'])) {
                $empNric = htmlspecialchars($empUser->array_options['options_national_id'], ENT_QUOTES);
            }
        }
        
        $jobTitle = htmlspecialchars($empUser->job ?: 'Employee', ENT_QUOTES);
        
        $currency = $line->contract_currency ?: 'SGD';
        $pfx = ($currency === 'SGD') ? 'S$' : $currency.' ';
        
        $formatAmt = function($amt) {
            return number_format((float)abs($amt), 2);
        };

        // YTD Calculation
        $ytdGross = 0; $ytdBonus = 0; $ytdEeCpf = 0; $ytdErCpf = 0; $ytdNetPay = 0;
        $sqlYtd = "SELECT SUM(pl.gross_salary) as ytd_gross, SUM(pl.bonus) as ytd_bonus, SUM(pl.employee_cpf) as ytd_ee_cpf, SUM(pl.employer_cpf) as ytd_er_cpf, SUM(pl.net_pay) as ytd_net_pay";
        $sqlYtd .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line as pl";
        $sqlYtd .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll as p ON p.rowid = pl.fk_payroll";
        $sqlYtd .= " WHERE pl.fk_user = ".(int)$line->fk_user;
        $sqlYtd .= " AND p.pay_year = ".(int)$line->pay_year;
        $sqlYtd .= " AND p.pay_month <= ".(int)$line->pay_month;
        $sqlYtd .= " AND (pl.status != 'draft' OR pl.rowid = ".(int)$line->id.")";
        $resYtd = $this->db->query($sqlYtd);
        if ($resYtd && $objYtd = $this->db->fetch_object($resYtd)) {
            $ytdGross = $objYtd->ytd_gross;
            $ytdBonus = $objYtd->ytd_bonus;
            $ytdEeCpf = $objYtd->ytd_ee_cpf;
            $ytdErCpf = $objYtd->ytd_er_cpf;
            $ytdNetPay = $objYtd->ytd_net_pay;
        }

        // Leave Balance — AL balance from HRM holiday counters via shared lib helper
        if (!function_exists('sghr_get_al_balance_from_hrm')) {
            dol_include_once('sghr/lib/sghr.lib.php');
        }
        $leaveDays = sghr_get_al_balance_from_hrm($this->db, (int)$line->fk_user);

        // Logo - Use base64 encoding to bypass TCPDF path resolution issues
        $logoHtml = '';
        if (!empty($mysoc->logo)) {
            $logoFile = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
            if (!is_file($logoFile) || !is_readable($logoFile)) {
                $logoFile = DOL_DATA_ROOT.'/mycompany/logos/'.$mysoc->logo;
            }
            
            if (is_file($logoFile) && is_readable($logoFile)) {
                $imgData = file_get_contents($logoFile);
                if ($imgData !== false) {
                    $base64 = base64_encode($imgData);
                    $mime = 'image/png'; // Default
                    $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
                    if ($ext == 'jpg' || $ext == 'jpeg') $mime = 'image/jpeg';
                    elseif ($ext == 'gif') $mime = 'image/gif';
                    
                    // Height 48: common header logo size for payslips
                    $logoHtml = '<img src="data:'.$mime.';base64,'.$base64.'" height="48">';
                }
            }
        }

        $allowances = !empty($line->allowances_json) ? json_decode($line->allowances_json, true) : array();
        
        // Sums
        $allowTotal = 0;
        foreach ($allowances as $a) $allowTotal += (float)($a['amount'] ?? 0);
        
        $addTotal = (float)$line->bonus + (float)$line->other_aw + (float)$line->al_encashment + (float)$line->commission;
        $otTotal = (float)$line->overtime_pay;
        $claimTotal = (float)$line->claims_total;
        
        $totalInc = $line->gross_salary ?: 0;
        
        $dedEeCpf = (float)$line->employee_cpf;
        $dedUpl = (float)$line->upl_deduction;
        $dedLoan = (float)($line->salary_advance_recovery ?? 0);
        $dedOther = (float)$line->withholding_tax + (float)$line->other_deductions + (float)($line->shg_cdac??0) + (float)($line->shg_ecf??0) + (float)($line->shg_mbmf??0) + (float)($line->shg_sinda??0);
        $totalDed = $line->total_deductions ?: 0;
        // The benchmark shows Total Deductions (E) separate from CPF/UPL, but practically E+G+H+... = TOTAL DEDUCTIONS.
        // We will map "Total Deductions (E)" to other manual deductions.
        $dedE = $dedOther; 
        
        $employerCpf = (float)$line->employer_cpf;
        $sdlAmt = (float)$line->sdl_amount;
        $netPay = (float)$line->net_pay;
        
        $daysOff = (float)$line->upl_days;

        // Days Off: list all approved leave for the pay month (by type); count only working days (excl. PH) for Days Worked
        $daysOffLines = array();
        $totalLeaveDaysInMonth = 0; // working days only (excl. public holidays)
        $startDate = sprintf('%04d-%02d-01', (int)$line->pay_year, (int)$line->pay_month);
        $endDate   = date('Y-m-t', strtotime($startDate));
        $phList = SghrCalc::getSingaporePublicHolidays((int)$line->pay_year);
        $schedule = ($emp->weekly_schedule !== '' && $emp->weekly_schedule !== null) ? $emp->weekly_schedule : 'Mon,Tue,Wed,Thu,Fri';

        $sqlLeave  = "SELECT h.date_debut, h.date_fin, h.halfday, t.code, t.label";
        $sqlLeave .= " FROM ".MAIN_DB_PREFIX."holiday h";
        $sqlLeave .= " INNER JOIN ".MAIN_DB_PREFIX."c_holiday_types t ON t.rowid = h.fk_type";
        $sqlLeave .= " WHERE h.fk_user = ".(int)$line->fk_user;
        $sqlLeave .= " AND h.statut = 3"; // Approved
        $sqlLeave .= " AND (";
        $sqlLeave .= "      (h.date_debut >= '".$this->db->idate(strtotime($startDate))."' AND h.date_debut <= '".$this->db->idate(strtotime($endDate))."')";
        $sqlLeave .= "   OR (h.date_fin >= '".$this->db->idate(strtotime($startDate))."' AND h.date_fin <= '".$this->db->idate(strtotime($endDate))."')";
        $sqlLeave .= "   OR (h.date_debut < '".$this->db->idate(strtotime($startDate))."' AND h.date_fin > '".$this->db->idate(strtotime($endDate))."')";
        $sqlLeave .= " )";
        $sqlLeave .= " ORDER BY t.code, h.date_debut";
        $resLeave = $this->db->query($sqlLeave);
        if ($resLeave) {
            $byType = array();
            while ($obj = $this->db->fetch_object($resLeave)) {
                $dStart = is_numeric($obj->date_debut) ? (int)$obj->date_debut : strtotime($obj->date_debut);
                $dEnd   = is_numeric($obj->date_fin)   ? (int)$obj->date_fin   : strtotime($obj->date_fin);
                $monthStart = strtotime($startDate);
                $monthEnd   = strtotime($endDate.' 23:59:59');
                $sliceStart = max($dStart, $monthStart);
                $sliceEnd   = min($dEnd,   $monthEnd);
                if ($sliceStart <= $sliceEnd) {
                    $sliceStartStr = date('Y-m-d', $sliceStart);
                    $sliceEndStr   = date('Y-m-d', $sliceEnd);
                    if (!empty($obj->halfday) && $dStart == $dEnd) {
                        $dayStr = date('Y-m-d', $dStart);
                        $days = (in_array($dayStr, $phList, true)) ? 0 : 0.5;
                    } else {
                        $days = SghrCalc::countWorkingDaysInRange($sliceStartStr, $sliceEndStr, $schedule, $phList);
                    }
                    $label = ($obj->code && $langs->trans($obj->code) != $obj->code) ? $langs->trans($obj->code) : $obj->label;
                    if (!isset($byType[$label])) $byType[$label] = 0;
                    $byType[$label] += (float)$days;
                    $totalLeaveDaysInMonth += (float)$days;
                }
            }
            foreach ($byType as $typeLabel => $typeDays) {
                $daysOffLines[] = array('label' => $typeLabel, 'days' => $typeDays);
            }
        }

        // Public holidays in month (for Days Worked note only; not listed under Days Off)
        $workDaysExclPh = (float) SghrCalc::countWorkingDays((int)$line->pay_year, (int)$line->pay_month, $schedule, true);
        $workDaysInclPh = (float) SghrCalc::countWorkingDays((int)$line->pay_year, (int)$line->pay_month, $schedule, false);
        $phDaysInMonth = max(0, $workDaysInclPh - $workDaysExclPh);

        $daysOffDisplay = count($daysOffLines) > 0
            ? implode('; ', array_map(function ($x) {
                return $x['label'] . ': ' . (float)$x['days'];
            }, $daysOffLines))
            : (string)(float)$daysOff;
        $daysOffDisplay = htmlspecialchars($daysOffDisplay, ENT_QUOTES);

        // Days Worked = working days in month minus approved leave on working days only (PH already excluded from leave count)
        $daysWorked = ($line->work_days > 0)
            ? max(0, (float)$line->work_days - $totalLeaveDaysInMonth)
            : 0;
        $daysWorkedNote = '';
        if ($phDaysInMonth > 0) {
            $phLabel = $langs->transnoentities('PublicHolidays');
            if ($phLabel === 'PublicHolidays') $phLabel = 'Public holidays';
            $daysWorkedNote = $langs->transnoentities('Inc').' '.(float)$phDaysInMonth.' '.$phLabel;
        }
        $daysWorkedNote = htmlspecialchars($daysWorkedNote, ENT_QUOTES);
        
        $companyName = strtoupper(htmlspecialchars($mysoc->name, ENT_QUOTES));
        $companyAddress = htmlspecialchars($mysoc->address.', '.$mysoc->zip.' '.$mysoc->town, ENT_QUOTES);
        $companyTel = htmlspecialchars($mysoc->phone ?: '', ENT_QUOTES);
        
        $payIdStr = str_pad($line->id, 4, '0', STR_PAD_LEFT);
        $monthStr = date('M Y', $dtFirst);
        $statusStr = ucfirst($line->status ?: 'paid');
        $pm = isset($emp->payment_mode) ? (string) $emp->payment_mode : '';
        $payModeLabels = array('bank' => 'Bank Transfer', 'cash' => 'Cash', 'cheque' => 'Cheque');
        $payModeStr = isset($payModeLabels[$pm]) ? $payModeLabels[$pm] : (!empty($pm) ? ucfirst($pm) : 'Bank Transfer');
        $payTypeStr = htmlspecialchars($payModeStr, ENT_QUOTES).' / '.$payDateDisplay;
        
        $dobTs = !empty($empUser->birth) ? (is_numeric($empUser->birth) ? (int)$empUser->birth : strtotime((string)$empUser->birth)) : 0;
        $dobStr = $dobTs > 0 ? dol_print_date($dobTs, 'day') : '';

        // Determine if left logo or name
        $logoDisplay = $logoHtml ? $logoHtml : '<span style="font-size:16pt; font-weight:bold; color:#1f135b;">'.$companyName.'</span>';

        $html = '
        <style>
            td { font-size: 8.5pt; color: #222; }
            .bold { font-weight: bold; }
            .title { font-size: 14pt; font-weight: bold; text-align: center; }
            .box { border: 1px solid #000; padding: 5px; }
            .sub-title { font-size: 9.5pt; font-weight: bold; text-align: center; }
            hr.cell-line { border-top: 1px solid #000; }
        </style>

        <table width="100%" cellpadding="3" cellspacing="0" border="0">
            <tr>
                <td width="30%">'.$logoDisplay.'</td>
                <td width="70%" align="right">
                    <span style="font-size: 11pt; font-weight: bold;">'.$companyName.'</span><br>
                    '.($companyTel ? 'Tel: '.$companyTel.'<br>' : '').'
                    <span style="font-size: 8.5pt;">'.$companyAddress.'</span>
                </td>
            </tr>
        </table>
        
        <br>
        <div class="title">PAYSLIP</div>
        <br>

        <table width="100%" cellpadding="3" cellspacing="0" border="0">
            <tr>
                <td width="20%" class="bold">Employee Name</td><td width="30%">: '.$empName.'</td>
                <td width="20%" class="bold">Payslip ID</td><td width="30%">: '.$payIdStr.'</td>
            </tr>
            <tr>
                <td width="20%" class="bold">Employee NRIC/FIN</td><td width="30%">: '.$empNric.'</td>
                <td width="20%" class="bold">Payroll Month</td><td width="30%">: '.$monthStr.'</td>
            </tr>
            <tr>
                <td width="20%" class="bold">Employee DOB</td><td width="30%">: '.$dobStr.'</td>
                <td width="20%" class="bold">Salary Period</td><td width="30%">: '.$periodFrom.' - '.$periodTo.'</td>
            </tr>
            <tr>
                <td width="20%" class="bold">Designation</td><td width="30%">: '.$jobTitle.'</td>
                <td width="20%" class="bold">Payment Status</td><td width="30%">: '.$statusStr.'</td>
            </tr>
            <tr>
                <td width="20%" class="bold">Reference</td><td width="30%">: </td>
                <td width="20%" class="bold">Payment Type / Date</td><td width="30%">: '.$payTypeStr.'</td>
            </tr>
        </table>
        
        <br><br>

        <table width="100%" cellpadding="5" cellspacing="0" border="1">
            <tr>
                <td width="50%" class="sub-title">INCOME</td>
                <td width="50%" class="sub-title">DEDUCTIONS</td>
            </tr>
            <tr>
                <!-- INCOME DETAILS -->
                <td width="50%">
                    <table width="100%" cellpadding="2" border="0">
                        <tr><td width="60%">Basic Rate</td><td width="40%" align="right">'.$pfx.$formatAmt($line->basic_salary ?: $emp->basic_salary).' (Monthly)</td></tr>
                        <tr><td width="60%">Basic Salary (A)</td><td width="40%" align="right">'.$pfx.$formatAmt($line->prorated_salary).'</td></tr>
                        <tr><td width="60%">Total Allowances (B)</td><td width="40%" align="right">'.$pfx.$formatAmt($allowTotal).'</td></tr>
                        <tr><td width="60%">Total Additional (C)</td><td width="40%" align="right">'.$pfx.$formatAmt($addTotal).'</td></tr>
                        <tr><td width="60%">Total Overtime Pay (D)</td><td width="40%" align="right">'.$pfx.$formatAmt($otTotal).'</td></tr>
                        <tr><td width="60%">Total Claim (I)</td><td width="40%" align="right">'.$pfx.$formatAmt($claimTotal).'</td></tr>
                        <tr><td colspan="2"><br><br></td></tr>
                    </table>
                </td>
                
                <!-- DEDUCTION DETAILS -->
                <td width="50%">
                    <table width="100%" cellpadding="2" border="0">
                        <tr><td width="60%">Total Deductions (E)</td><td width="40%" align="right">'.$pfx.$formatAmt($dedE).'</td></tr>
                        <tr><td width="60%">Employee CPF (G)</td><td width="40%" align="right">'.$pfx.$formatAmt($dedEeCpf).'</td></tr>
                        <tr><td width="60%">Unpaid Leave</td><td width="40%" align="right">'.$pfx.$formatAmt($dedUpl).'</td></tr>
                        <tr><td width="60%">Loan (H)</td><td width="40%" align="right">'.$pfx.$formatAmt($dedLoan).'</td></tr>
                        <tr><td colspan="2"><br><br><br></td></tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td width="50%">
                    <table width="100%"><tr><td width="60%" class="bold">TOTAL INCOME</td><td width="40%" align="right" class="bold">'.$pfx.$formatAmt($totalInc).'</td></tr></table>
                </td>
                <td width="50%">
                    <table width="100%"><tr><td width="60%" class="bold">TOTAL DEDUCTIONS</td><td width="40%" align="right" class="bold">'.$pfx.$formatAmt($totalDed).'</td></tr></table>
                </td>
            </tr>
        </table>

        <br>

        <table width="100%" cellpadding="5" cellspacing="0" border="1">
            <tr>
                <!-- LEFT BOTTOM DETAILS -->
                <td width="50%">
                    <table width="100%" cellpadding="2" border="0">
                        <tr><td width="60%">Employer CPF</td><td width="40%" align="right">'.$pfx.$formatAmt($employerCpf).'</td></tr>
                        <tr><td width="60%">SDL</td><td width="40%" align="right">'.$pfx.$formatAmt($sdlAmt).'</td></tr>
                        <tr><td width="60%">Gross Pay (A+B+C+D)</td><td width="40%" align="right">'.$pfx.$formatAmt($totalInc).'</td></tr>
                        <tr><td width="60%">NET Pay (A+B+C+D-E-G-H+I)</td><td width="40%" align="right">'.$pfx.$formatAmt($netPay).'</td></tr>
                        <tr><td width="60%">Days Worked'.($daysWorkedNote !== '' ? '<br><span style="font-size:7pt; color:#666">('.$daysWorkedNote.')</span>' : '').'</td><td width="40%" align="right">'.(float)$daysWorked.'</td></tr>
                        <tr><td width="60%">Days Off</td><td width="40%" align="right">'.$daysOffDisplay.'</td></tr>
                        <tr><td width="30%">Remark</td><td width="70%" align="left">'.htmlspecialchars($line->note?:'', ENT_QUOTES).'</td></tr>
                    </table>
                </td>
                
                <!-- RIGHT YTD DETAILS -->
                <td width="50%" valign="top">
                    <table width="100%" cellpadding="2" border="0">
                        <tr><td width="50%">Year to Date Earnings</td><td width="50%" align="right">Jan '.date('Y', $dtFirst).' - '.$monthStr.'</td></tr>
                        <tr><td width="50%">Net Pay</td><td width="50%" align="right">'.$pfx.$formatAmt($ytdNetPay).'</td></tr>
                        <tr><td width="50%">Gross Pay</td><td width="50%" align="right">'.$pfx.$formatAmt($ytdGross).'</td></tr>
                        <tr><td width="50%">Employee CPF</td><td width="50%" align="right">'.$pfx.$formatAmt($ytdEeCpf).'</td></tr>
                        <tr><td width="50%">Employer CPF</td><td width="50%" align="right">'.$pfx.$formatAmt($ytdErCpf).'</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <br><br><br>
        <div style="font-size: 8pt; text-align:right;">
            <i>Generated on '.dol_print_date(dol_now(), '%Y-%m-%d %H:%M:%S').'</i>
        </div>
        ';

		$pdf->writeHTML($html, true, false, true, false, '');

		// ── Output ────────────────────────────────────────────────────────────
		$payMonthStr = str_pad((string)$line->pay_month, 2, '0', STR_PAD_LEFT);
		// Trim name to avoid trailing underscores
		$empNameClean = preg_replace('/\s+/', '_', trim((string)$empName));
		$filename = 'payslip_'.$empNameClean.'_'.$line->pay_year.$payMonthStr.'.pdf';
		
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
		$filename = dol_sanitizeFileName($filename);

		if ($mode === 'save') {
			$pdf->Output($outputPath, 'F');
			return $filename;
		}
		
		while (ob_get_level()) { ob_end_clean(); }
		$pdf->Output($filename, 'I');
		exit;
	}
}
