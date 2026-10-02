<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        core/triggers/interface_99_modSghr_AccountingHook.class.php
 * \ingroup sghr
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
dol_include_once('sghr/lib/sghr.lib.php');
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
		$this->family      = 'sghr';
		$this->description = 'On payslip approval: post accounting entries (if accounting enabled), sync to Salaries module for finance, save payslip PDF.';
		$this->version     = '1.0.0';
		$this->picto       = 'sghr@sghr';
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
		if (!isModEnabled('sghr')) return 0;

		// Only act on our custom events
		if ($action !== 'SGHR_PAYROLLLINE_APPROVED' && $action !== 'SGHR_PAYROLLLINE_UNAPPROVED') return 0;

		// ── Payroll line values (used by accounting, Salary sync and PDF) ───────
		$gross        = (float)($object->gross_salary    ?? 0);
		$empCpf       = (float)($object->employee_cpf    ?? 0);
		$erCpf        = (float)($object->employer_cpf    ?? 0);
		$sdl          = (float)($object->sdl_amount      ?? 0);
		$netPay       = (float)($object->net_pay         ?? 0);
		$payRef       = 'PAY-'.$object->pay_year.'-'.str_pad($object->pay_month, 2, '0', STR_PAD_LEFT);
		$docDateTs    = mktime(0, 0, 0, (int)$object->pay_month, 28, (int)$object->pay_year);
		$docDate      = date('Y-m-d', $docDateTs);

		// Unapprove: refresh only the monthly CPF & Levies social contribution.
		// Journal entries / salary sync / PDF created at approval time are left untouched.
		if ($action === 'SGHR_PAYROLLLINE_UNAPPROVED') {
			return $this->syncCpfLeviesSociales($object, $user);
		}

		$error = 0;

		// ── 1. Accounting: post journal entries (only if accounting module enabled) ─
		if (!empty($conf->accounting->enabled)) {
			require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingjournal.class.php';
			require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';

			$glSalary    = getDolGlobalString('SGHR_GL_SALARY');
			$glCpfEmplyee= getDolGlobalString('SGHR_GL_CPF_PAYABLE') ?: getDolGlobalString('SGHR_GL_CPF_PAYABLE');
			$glCpfEr     = getDolGlobalString('SGHR_GL_CPF_PAYABLE');
			$glSdl       = getDolGlobalString('SGHR_GL_SDL_PAYABLE');
			$glBank      = getDolGlobalString('SGHR_GL_BANK');

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
					$bk->doc_date            = $docDateTs;
					$bk->doc_ref             = $payRef;
					$bk->doc_type            = 'sghr';
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
			$obj_chk = $res_chk ? $this->db->fetch_object($res_chk) : null;
			if ($obj_chk) {
				// Record already exists for this employee+period: keep amount in sync while unpaid
				$salaryRowid = (int) $obj_chk->rowid;
				$sql_paye = "SELECT paye FROM ".MAIN_DB_PREFIX."salary WHERE rowid = ".$salaryRowid;
				$res_paye = $this->db->query($sql_paye);
				$obj_paye = $res_paye ? $this->db->fetch_object($res_paye) : null;
				if ($obj_paye && (int) $obj_paye->paye == 1) {
					dol_syslog('SGPayroll: Salary '.$salaryRowid.' already paid - amount not refreshed', LOG_WARNING);
				} else {
					$sql_sal  = "UPDATE ".MAIN_DB_PREFIX."salary SET";
					$sql_sal .= " amount = ".price2num($netPay);
					$sql_sal .= ", label = '".$this->db->escape('SG Payroll: '.$payRef.' (Net Pay)')."'";
					$sql_sal .= ", datesp = '".$this->db->idate(dol_get_first_day($object->pay_year, $object->pay_month))."'";
					$sql_sal .= ", dateep = '".$this->db->idate(dol_get_last_day($object->pay_year, $object->pay_month))."'";
					$sql_sal .= ", fk_user_modif = ".(int) $user->id;
					$sql_sal .= " WHERE rowid = ".$salaryRowid;
					if (!$this->db->query($sql_sal)) {
						dol_syslog('SGPayroll: Failed to update core Salary record: '.$this->db->lasterror(), LOG_ERR);
					}
				}
			} else {
				$salary->fk_user = $object->fk_user;
				$salary->amount  = $netPay;
				$salary->label   = 'SG Payroll: '.$payRef.' (Net Pay)';
				$salary->datesp  = dol_get_first_day($object->pay_year, $object->pay_month);
				$salary->dateep  = dol_get_last_day($object->pay_year, $object->pay_month);
				$salary->datep   = $docDateTs;
				$salary->fk_user_author = $user->id;
				$salary->entity  = $conf->entity;
				$salary->paye    = 0; // Unpaid

				if ($salary->create($user) < 0) {
					dol_syslog('SGPayroll: Failed to sync core Salary record: '.$salary->error, LOG_ERR);
				}
			}
		}

		// ── 3. Save Payslip PDF to Employee Document Vault ─────────────────────
		dol_include_once('sghr/core/modules/sghr/pdf/pdf_payslip_sgpayroll.class.php');
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$pdfGen = new pdf_payslip_sgpayroll($this->db);
		
		// Define relative path and absolute path
		$relDir  = 'sghr/documents/'.$object->fk_user;
		$absDir  = DOL_DATA_ROOT.'/'.$relDir;
		if (!is_dir($absDir)) {
			dol_mkdir($absDir);
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

		// ── 4. Sync monthly Social Contribution (CPF & Levies) ────────────────
		$this->syncCpfLeviesSociales($object, $user);

		// $this->db->commit(); // Removed - handled by caller
		return 1;
	}

	/**
	 * Upsert the monthly "CPF & Levies" social contribution (compta/sociales).
	 * Total = employee CPF + employer CPF + SDL + SHG over all approved/paid
	 * payslip lines of the period — same basis as cpf_review.php "Grand Total".
	 * One record per month, identified by dictionary type SGCPFLEVY + periode
	 * + entity. Recomputed from scratch on every approve/unapprove so the
	 * record follows later payroll edits. Records already paid are never touched.
	 *
	 * @param  CommonObject $object  Payroll line (uses pay_year, pay_month)
	 * @param  User         $user    User
	 * @return int          1=updated/created, 0=skipped, -1=error
	 */
	private function syncCpfLeviesSociales($object, User $user)
	{
		global $conf;

		// Dictionary type (see sql/llx_sgpayroll_upgrade_7a_cpflevies_sociales.sql)
		$sqlT = "SELECT id FROM ".MAIN_DB_PREFIX."c_chargesociales WHERE code = 'SGCPFLEVY' AND active = 1";
		$resT = $this->db->query($sqlT);
		if (!$resT || $this->db->num_rows($resT) == 0) {
			dol_syslog('SGPayroll: dictionary type SGCPFLEVY missing (run sghr SQL upgrade) - social contribution not synced', LOG_WARNING);
			return 0;
		}
		$fkType = (int) $this->db->fetch_object($resT)->id;

		// Recompute the monthly total from scratch — idempotent on any approve/unapprove
		$sqlSum  = "SELECT COALESCE(SUM(employee_cpf + employer_cpf + sdl_amount + shg_cdac + shg_ecf + shg_mbmf + shg_sinda), 0) AS total";
		$sqlSum .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line";
		$sqlSum .= " WHERE pay_year = ".(int) $object->pay_year." AND pay_month = ".(int) $object->pay_month;
		$sqlSum .= " AND status IN ('approved','paid') AND entity = ".(int) $conf->entity;
		$resSum  = $this->db->query($sqlSum);
		if (!$resSum) {
			dol_syslog('SGPayroll: CPF & Levies sum failed: '.$this->db->lasterror(), LOG_ERR);
			return -1;
		}
		$total = round((float) $this->db->fetch_object($resSum)->total, 2);

		$periodeTs = dol_get_first_day($object->pay_year, $object->pay_month);
		$nextYear  = ((int) $object->pay_month == 12) ? ((int) $object->pay_year + 1) : (int) $object->pay_year;
		$nextMonth = ((int) $object->pay_month == 12) ? 1 : ((int) $object->pay_month + 1);
		$dateEchTs = dol_mktime(12, 0, 0, $nextMonth, 14, $nextYear); // CPF/SDL statutory due date: 14th of following month
		$libelle   = 'SG CPF&Levies '.$object->pay_year.'-'.str_pad((string) $object->pay_month, 2, '0', STR_PAD_LEFT);

		$sqlF  = "SELECT rowid, paye, amount FROM ".MAIN_DB_PREFIX."chargesociales";
		$sqlF .= " WHERE fk_type = ".$fkType." AND periode = '".$this->db->idate($periodeTs)."'";
		$sqlF .= " AND entity = ".(int) $conf->entity;
		$resF  = $this->db->query($sqlF);
		if (!$resF) {
			dol_syslog('SGPayroll: social contribution lookup failed: '.$this->db->lasterror(), LOG_ERR);
			return -1;
		}
		$objF = $this->db->fetch_object($resF);

		if ($objF) {
			if ((int) $objF->paye == 1) {
				dol_syslog('SGPayroll: social contribution '.$objF->rowid.' already paid - amount not refreshed', LOG_WARNING);
				return 0;
			}
			if (abs((float) $objF->amount - $total) < 0.005) {
				return 0; // unchanged
			}
			$sqlU  = "UPDATE ".MAIN_DB_PREFIX."chargesociales SET";
			$sqlU .= " amount = ".price2num($total).", date_ech = '".$this->db->idate($dateEchTs)."'";
			$sqlU .= ", libelle = '".$this->db->escape($libelle)."', fk_user_modif = ".(int) $user->id;
			$sqlU .= " WHERE rowid = ".(int) $objF->rowid;
			if (!$this->db->query($sqlU)) {
				dol_syslog('SGPayroll: social contribution update failed: '.$this->db->lasterror(), LOG_ERR);
				return -1;
			}
		} elseif ($total > 0) {
			require_once DOL_DOCUMENT_ROOT.'/compta/sociales/class/chargesociales.class.php';
			$charge = new ChargeSociales($this->db);
			$charge->type     = $fkType;
			$charge->label    = $libelle;
			$charge->amount   = $total;
			$charge->date_ech = $dateEchTs;
			$charge->period   = $periodeTs; // create() reads ->period
			if ($charge->create($user) < 0) {
				dol_syslog('SGPayroll: social contribution create failed: '.$charge->error, LOG_ERR);
				return -1;
			}
		}

		return 1;
	}
}
