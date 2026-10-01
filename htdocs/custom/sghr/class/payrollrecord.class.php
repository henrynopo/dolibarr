<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */
/** \file class/payrollrecord.class.php — PayrollRecord ORM (run header + line) */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('sghr/class/payrollcalc.class.php');

/**
 * Class SghrRecord — wraps llx_sgpayroll_payroll_line rows
 */
#[AllowDynamicProperties]
class SghrRecord extends CommonObject
{
	public $element = 'sgpayroll_payroll_line';
	public $table_element = 'sgpayroll_payroll_line';

	// Header
	public $fk_payroll; public $ref; public $pay_year; public $pay_month; public $status = 'draft';
	// Per-employee fields (payroll line)
	public $fk_user;
	public $basic_salary = 0;       public $work_days = 26;
	public $prorated_salary = 0;    public $allowances_total = 0;
	public $allowances_json = '';
	public $bonus = 0;              public $commission = 0;
	public $overtime_pay = 0;      public $overtime_hours = 0;
	public $al_encashment = 0;     public $other_aw = 0;   public $aw_total = 0;
	public $upl_days = 0;          public $upl_deduction = 0;
	public $salary_advance_recovery = 0;  public $other_deductions = 0;
	public $claims_total = 0;
	public $ordinary_wages = 0;    public $additional_wages = 0;
	public $employee_cpf = 0;      public $employer_cpf = 0;
	public $employee_cpf_oa = 0;   public $employee_cpf_sa = 0;   public $employee_cpf_ma = 0;
	public $sdl_amount = 0;        public $fwl_amount = 0;
	public $shg_cdac = 0;          public $shg_ecf = 0;
	public $shg_mbmf = 0;          public $shg_sinda = 0;
	public $withholding_tax = 0;   public $bik_value = 0;
	public $gross_salary = 0;      public $total_deductions = 0;   public $net_pay = 0;
	public $payment_date;
	public $note = '';

	/** @var string ISO 4217 Currency (e.g. SGD, USD) */
	public $contract_currency = 'SGD';
	/** @var float Snapshot rate: FCY per 1 SGD when manual; Dolibarr table rate when auto */
	public $exchange_rate    = 1.0;
	/** @var float Basic salary in contract currency */
	public $basic_salary_fc  = 0;
	/** @var float Net pay in contract currency */
	public $net_pay_fc       = 0;

	// From join
	public $lastname; public $firstname;

	// Multi-company
	public $entity = 1;

	public function __construct($db)
	{
		global $conf;
		$this->db     = $db;
		$this->entity = (int)($conf->entity ?? 1);
		if ($this->entity < 1 && !isModEnabled('multicompany')) {
			$this->entity = 1;
		}
	}

	/** Fetch a single payroll line by rowid */
	public function fetch($rowid)
	{
		$sql  = "SELECT pl.*, p.pay_year, p.pay_month, u.lastname, u.firstname";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
		$sql .= " WHERE pl.rowid = ".(int)$rowid." AND pl.entity = ".(int)$this->entity;
		$res  = $this->db->query($sql);
		if (!$res || $this->db->num_rows($res) === 0) return 0;
		$obj  = $this->db->fetch_object($res);
		foreach (get_object_vars($obj) as $k => $v) {
			$this->$k = $v;
		}
		$this->id = $obj->rowid;
		return 1;
	}

	/** Create a payroll run header and return its rowid */
	public function create(User $user)
	{
		$sql  = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_payroll";
		$sql .= " (ref, pay_year, pay_month, status, entity, date_creation, fk_user_creat)";
		$sql .= " VALUES ('".$this->db->escape($this->ref)."',".(int)$this->pay_year.",".(int)$this->pay_month;
		$sql .= ",'".$this->db->escape($this->status)."',".(int)$this->entity.",NOW(),".(int)$user->id.")";
		if (!$this->db->query($sql)) { $this->error = $this->db->lasterror(); return -1; }
		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'sgpayroll_payroll');
		$this->fk_payroll = $this->id;
		return $this->id;
	}

	/** Save or update a payslip line using computed result array */
	public function savePayslipLine(User $user)
	{
		// Find or create parent payroll run for this month
		if (empty($this->fk_payroll)) {
			$ref = 'PAY-'.$this->pay_year.'-'.str_pad($this->pay_month, 2, '0', STR_PAD_LEFT);
			$chk = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."sgpayroll_payroll WHERE ref='".$this->db->escape($ref)."' AND entity=".(int)$this->entity);
			if ($chk && $this->db->num_rows($chk) > 0) {
				$this->fk_payroll = $this->db->fetch_object($chk)->rowid;
			} else {
				$this->ref = $ref;
				if ($this->create($user) < 0) return -1;
			}
		}

		// Check existing line
		$chk = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=".(int)$this->fk_payroll." AND fk_user=".(int)$this->fk_user);
		$existId = ($chk && $this->db->num_rows($chk) > 0) ? $this->db->fetch_object($chk)->rowid : 0;

		$cols = array('fk_payroll','fk_user','entity','basic_salary','work_days','prorated_salary',
			'allowances_total','allowances_json','bonus','commission','overtime_pay','overtime_hours',
			'al_encashment','other_aw','aw_total','upl_days','upl_deduction','salary_advance_recovery',
			'other_deductions','claims_total','ordinary_wages','additional_wages',
			'employee_cpf','employer_cpf','employee_cpf_oa','employee_cpf_sa','employee_cpf_ma',
			'sdl_amount','fwl_amount','shg_cdac','shg_ecf','shg_mbmf','shg_sinda',
			'withholding_tax','bik_value','gross_salary','total_deductions','net_pay','status',
			'payment_date',
			'contract_currency','exchange_rate','basic_salary_fc','net_pay_fc','note');
		// Make sure entity is set
		$this->entity = $this->entity ?: 1;

		// Format value for SQL (payment_date: use NULL if empty to avoid invalid DATE)
		$valForCol = function ($c) {
			$v = $this->$c ?? 0;
			if ($c === 'note') return "'".$this->db->escape($this->note)."'";
			if ($c === 'payment_date' && (empty($v) || $v === '0' || $v === 0)) return 'NULL';
			if ($c === 'payment_date' && !empty($v)) return "'".$this->db->escape($v)."'";
			return "'".$this->db->escape($v)."'";
		};

		if ($existId) {
			$set = array();
			foreach ($cols as $c) {
				$set[] = $c.'='.$valForCol($c);
			}
			$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_payroll_line SET ".implode(',',$set).",tms=NOW() WHERE rowid=".(int)$existId;
			$res = $this->db->query($sql);
			if (!$res) { $this->error = $this->db->lasterror(); return -1; }
			$this->id = $existId;
		} else {
			$vals = array();
			foreach ($cols as $c) $vals[] = $valForCol($c);
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_payroll_line (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
			$res = $this->db->query($sql);
			if (!$res) { $this->error = $this->db->lasterror(); return -1; }
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'sgpayroll_payroll_line');
		}

		// Update run header totals
		$this->db->query(
			"UPDATE ".MAIN_DB_PREFIX."sgpayroll_payroll p SET"
			." total_gross=(SELECT SUM(gross_salary) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid),"
			." total_net=(SELECT SUM(net_pay) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid),"
			." total_employer_cpf=(SELECT SUM(employer_cpf) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid),"
			." total_employee_cpf=(SELECT SUM(employee_cpf) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid),"
			." total_sdl=(SELECT SUM(sdl_amount) FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=p.rowid)"
			." WHERE p.rowid=".(int)$this->fk_payroll
		);
		return $this->id;
	}

	/** Update just the status of a line */
	public function saveStatus(User $user)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_payroll_line SET status='".$this->db->escape($this->status)."'";
		$sql .= ", tms=NOW() WHERE rowid=".(int)$this->id;
		$res = $this->db->query($sql);
		if ($res && $this->status === 'approved') {
			$res_trigger = $this->call_trigger('SGHR_PAYROLLLINE_APPROVED', $user);
			if ($res_trigger < 0) {
				$this->error = $this->db->lasterror();
				$res = -1;
			}
		}

		// Auto-update parent batch status
		if ($res >= 0 && !empty($this->fk_payroll)) {
			// Check if all lines are approved or paid
			$sqlC = "SELECT COUNT(rowid) as total, SUM(IF(status IN ('approved','paid'), 1, 0)) as completed FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line WHERE fk_payroll=".(int)$this->fk_payroll;
			$resC = $this->db->query($sqlC);
			if ($resC && $objC = $this->db->fetch_object($resC)) {
				if ($objC->total > 0 && $objC->total == $objC->completed) {
					$this->db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_payroll SET status='approved' WHERE rowid=".(int)$this->fk_payroll." AND status != 'approved'");
				} else {
					$this->db->query("UPDATE ".MAIN_DB_PREFIX."sgpayroll_payroll SET status='draft' WHERE rowid=".(int)$this->fk_payroll." AND status != 'draft'");
				}
			}
		}

		return $res;
	}

	/**
	 * Fetch list of payroll lines for a given year/month.
	 * If ownUserId > 0, filter to that employee only.
	 */
	public static function fetchList($db, $year, $month, $ownUserId = 0)
	{
		global $conf;
		$entity = (int)($conf->entity ?? 1);
		$sql  = "SELECT pl.rowid, pl.fk_user, pl.gross_salary, pl.employee_cpf, pl.net_pay, pl.status,";
		$sql .= " p.ref, p.pay_year, p.pay_month, u.lastname, u.firstname";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_payroll_line pl";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."sgpayroll_payroll p ON p.rowid = pl.fk_payroll";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = pl.fk_user";
		$sql .= " WHERE p.pay_year=".(int)$year." AND p.pay_month=".(int)$month;
		$sql .= " AND p.entity = ".$entity;
		if ($ownUserId > 0) $sql .= " AND pl.fk_user=".(int)$ownUserId;
		$sql .= " ORDER BY u.lastname, u.firstname";

		$res  = $db->query($sql);
		$rows = array();
		while ($res && $obj = $db->fetch_object($res)) {
			$r = new self($db);
			foreach (get_object_vars($obj) as $k => $v) $r->$k = $v;
			$r->id = $obj->rowid;
			$rows[] = $r;
		}
		return $rows;
	}
}
