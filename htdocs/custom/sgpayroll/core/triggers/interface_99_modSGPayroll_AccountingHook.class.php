<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        core/triggers/interface_99_modSGPayroll_AccountingHook.class.php
 * \ingroup     sgpayroll
 * \brief       Dolibarr trigger — posts journal entries when a payroll run is approved.
 *
 * Journal entries posted on PAYROLLLINE_APPROVED:
 *   Dr. Salaries Expense       = Gross Salary
 *       Cr. CPF Payable (Employee)
 *       Cr. CPF Payable (Employer)
 *       Cr. SDL Payable
 *       Cr. Bank / Salary Payable  = Net Pay
 *
 * FWL is posted separately as an operating expense.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';
/**
 * Class InterfaceAccountingHook
 * (class name must match the trigger file name per Dolibarr naming rule)
 */
class InterfaceAccountingHook extends DolibarrTriggers
{
	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family      = 'sgpayroll';
		$this->description = 'On payslip approval: post accounting entries (if accounting enabled), sync to Salaries module for finance, save payslip PDF.';
		$this->version     = '1.0.0';
		$this->picto       = 'sgpayroll@sgpayroll';
	}

	/**
	 * Run trigger
	 * @param  string  $action  Event action code
	 * @param  CommonObject $object  Object triggering event
	 * @param  User    $user    Current user
	 * @param  Translate $langs Translations
	 * @param  Conf    $conf    Configuration
	 * @return int     0=no action taken, 1=OK, -1=error
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('sgpayroll')) return 0;

		// Only act on our custom event
		if ($action !== 'SGPAYROLL_PAYROLLLINE_APPROVED') return 0;

		// ── Payroll line values (used by accounting, Salary sync and PDF) ───────
		$gross        = (float)($object->gross_salary    ?? 0);
		$empCpf       = (float)($object->employee_cpf    ?? 0);
		$erCpf        = (float)($object->employer_cpf    ?? 0);
		$sdl          = (float)($object->sdl_amount      ?? 0);
		$netPay       = (float)($object->net_pay         ?? 0);
		$payRef       = 'PAY-'.$object->pay_year.'-'.str_pad($object->pay_month, 2, '0', STR_PAD_LEFT);
		$docDate      = date('Y-m-d', mktime(0, 0, 0, $object->pay_month, 28, $object->pay_year));

		$error = 0;

		// ── 1. Accounting: post journal entries (only if accounting module enabled) ─
		if (!empty($conf->accounting->enabled)) {
			require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingjournal.class.php';
			require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

			$glSalary    = getDolGlobalString('SGPAYROLL_GL_SALARY');
			$glCpfEmplyee= getDolGlobalString('SGPAYROLL_GL_CPF_PAYABLE') ?: getDolGlobalString('SGPAYROLL_GL_CPF_PAYABLE');
			$glCpfEr     = getDolGlobalString('SGPAYROLL_GL_CPF_PAYABLE');
			$glSdl       = getDolGlobalString('SGPAYROLL_GL_SDL_PAYABLE');
			$glBank      = getDolGlobalString('SGPAYROLL_GL_BANK');

			if ($glSalary && $glBank) {
				$entries = array();
				if ($gross > 0) {
					$entries[] = array($glSalary, 'Salary '.$payRef.' - '.sgpayroll_format_employee_name($object->firstname, $object->lastname), $gross, 0);
				}
				if ($empCpf > 0) {
					$entries[] = array($glCpfEmplyee, 'CPF Employee '.$payRef, 0, $empCpf);
				}
				if ($erCpf > 0) {
					$entries[] = array($glSalary, 'Employer CPF '.$payRef, $erCpf, 0);
					$entries[] = array($glCpfEr,  'CPF Employer '.$payRef, 0, $erCpf);
				}
				if ($sdl > 0 && $glSdl) {
					$entries[] = array($glSalary, 'SDL '.$payRef, $sdl, 0);
					$entries[] = array($glSdl,   'SDL Payable '.$payRef, 0, $sdl);
				}
				if ($netPay > 0) {
					$entries[] = array($glBank, 'Net Salary '.$payRef, 0, $netPay);
				}

				foreach ($entries as $e) {
					list($acct, $label, $debit, $credit) = $e;
					$bk = new BookKeeping($this->db);
					$bk->doc_date            = $docDate;
					$bk->doc_ref             = $payRef;
					$bk->doc_type            = 'sgpayroll';
					$bk->numero_compte       = $acct;
					$bk->label_compte        = $label;
					$bk->debit               = $debit;
					$bk->credit              = $credit;
					$bk->montant             = $debit > 0 ? $debit : -$credit;
					$bk->sens                = $debit > 0 ? 'D' : 'C';
					$bk->fk_user_author      = $user->id;
					$bk->entity              = $conf->entity;

					if ($bk->create($user) < 0) {
						$this->errors = array_merge($this->errors, $bk->errors);
						$error++;
						break;
					}
				}
			} else {
				dol_syslog('SGPayroll: GL accounts not configured – skipping journal entry.', LOG_WARNING);
			}
		}

		if ($error) return -1;

		// ── 2. Sync to Core Salary Module (Finance) ─────────────────────────
		// So finance can pay salaries from Salaries -> Salary Payments (Finance)
		if (isModEnabled('salaries')) {
			require_once DOL_DOCUMENT_ROOT.'/salaries/class/salary.class.php';
			$salary = new Salary($this->db);

			$sql_chk = "SELECT rowid FROM ".MAIN_DB_PREFIX."salary";
			$sql_chk .= " WHERE fk_user = ".(int)$object->fk_user;
			$sql_chk .= " AND datesp = '".$this->db->idate(dol_get_first_day($object->pay_year, $object->pay_month))."'";
			$sql_chk .= " AND entity = ".(int)$conf->entity;

			$res_chk = $this->db->query($sql_chk);
			if ($res_chk && $this->db->num_rows($res_chk) == 0) {
				$salary->fk_user = $object->fk_user;
				$salary->amount  = $netPay;
				$salary->label   = 'SG Payroll: '.$payRef.' (Net Pay)';
				$salary->datesp  = dol_get_first_day($object->pay_year, $object->pay_month);
				$salary->dateep  = dol_get_last_day($object->pay_year, $object->pay_month);
				$salary->datep   = $docDate;
				$salary->fk_user_author = $user->id;
				$salary->entity  = $conf->entity;
				$salary->paye    = 0; // Unpaid

				if ($salary->create($user) < 0) {
					dol_syslog('SGPayroll: Failed to sync core Salary record: '.$salary->error, LOG_ERR);
				}
			}
		}

		// ── 3. Save Payslip PDF to Employee Document Vault ─────────────────────
		require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/pdf/pdf_payslip_sgpayroll.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$pdfGen = new pdf_payslip_sgpayroll($this->db);
		
		// Define relative path and absolute path
		$relDir  = 'sgpayroll/documents/'.$object->fk_user;
		$absDir  = DOL_DATA_ROOT.'/'.$relDir;
		if (!is_dir($absDir)) {
			dol_mkdir($absDir, 1);
		}

		// Generate filename
		$empName = sgpayroll_format_employee_name($object->firstname, $object->lastname);
		$filename = 'Payslip_'.preg_replace('/\s+/', '_', $empName).'_'.$object->pay_year.'-'.str_pad($object->pay_month, 2, '0', STR_PAD_LEFT).'.pdf';
		$absPath = $absDir.'/'.$filename;
		$relPath = $relDir.'/'.$filename;

		// Save PDF
		try {
			$res_pdf = $pdfGen->generate($object->id, 'save', $absPath);
		} catch (Exception $e) {
			dol_syslog('SGPayroll: PDF Generation Exception: ' . $e->getMessage(), LOG_ERR);
			$res_pdf = false;
		}

		if ($res_pdf) {
			// Check if document record already exists to avoid duplicates
			$sql_doc_chk = "SELECT rowid FROM ".MAIN_DB_PREFIX."sgpayroll_documents";
			$sql_doc_chk .= " WHERE fk_user = ".(int)$object->fk_user;
			$sql_doc_chk .= " AND doc_type = 'PAYSLIP'";
			$sql_doc_chk .= " AND doc_label LIKE '%".$this->db->escape($payRef)."%'";
			$sql_doc_chk .= " AND entity = ".(int)$conf->entity;

			$res_doc_chk = $this->db->query($sql_doc_chk);
			if ($res_doc_chk && $this->db->num_rows($res_doc_chk) == 0) {
				$sql_doc  = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_documents";
				$sql_doc .= " (fk_user, doc_type, doc_label, file_path, issue_date, entity, date_creation, fk_user_creat)";
				$sql_doc .= " VALUES (".(int)$object->fk_user.", 'PAYSLIP', 'Payslip ".$this->db->escape($payRef)."', '".$this->db->escape($relPath)."',";
				$sql_doc .= " '".$this->db->escape($docDate)."', ".(int)$conf->entity.", NOW(), ".(int)$user->id.")";

				if (!$this->db->query($sql_doc)) {
					dol_syslog('SGPayroll: Failed to insert document record: '.$this->db->lasterror, LOG_ERR);
				}
			}
		}

		// $this->db->commit(); // Removed - handled by caller
		return 1;
	}
}
