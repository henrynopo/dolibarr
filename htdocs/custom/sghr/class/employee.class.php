<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        class/employee.class.php
 * \ingroup sghr
 * \brief       Extended Singapore employee profile ORM
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class SghrEmployee
 */
#[AllowDynamicProperties]
class SghrEmployee extends CommonObject
{
	/** @var string Module name */
	public $module = 'sghr';
	/** @var string Table element */
	public $element = 'sgpayroll_employee';
	/** @var string Table name without prefix */
	public $table_element = 'sgpayroll_employee';
	/** @var string Picto */
	public $picto = 'user';

	// ── Fields matching llx_sgpayroll_employee ─────────────────────────────
	public $fk_user;
	public $nric_fin;
	public $id_type          = 'NRIC';
	public $citizenship      = 'SC';
	public $pr_start_date;
	public $race             = '';
	public $is_muslim        = 0;
	public $gender           = '';
	public $dob;
	public $employment_type  = 'fulltime';
	public $pass_type        = '';
	public $pass_number      = '';
	public $pass_expiry;
	public $passport_number  = '';
	public $passport_expiry;
	public $work_contract_date;
	public $probation_end_date;
	public $cessation_date;
	public $tax_residency    = 'resident';
	public $basic_salary     = 0;
	public $hourly_rate      = 0;
	public $payment_mode     = 'bank';
	public $bank_name        = '';
	public $bank_branch_code = '';
	public $bank_account     = '';
	public $shg_opt_out_cdac  = 0;
	public $shg_opt_out_ecf   = 0;
	public $shg_opt_out_mbmf  = 0;
	public $shg_opt_out_sinda = 0;
	public $fk_supervisor;
	// Cost Centre
	public $fk_cost_centre   = 0;
	// Multi-company
	public $entity           = 1;  // scoped to current Dolibarr entity

	public $status           = 1;
	/** @var string ISO 4217 Currency (e.g. SGD, USD) */
	public $contract_currency = 'SGD';
	/** @var float Salary in contract currency */
	public $contract_salary  = 0;
	/** @var float Manual rate: units of contract currency per 1 SGD (e.g. 5 → 1 SGD = 5 CNY) */
	public $exchange_rate    = 1.0;
	/** @var int Custom working days per month (0 = use company default) */
	public $workdays_per_month = 0;
	/** @var string JSON string of pending changes awaiting HR approval */
	public $pending_json     = null;
	/** @var string Structured list of working days (Day:Value;Day:Value) */
	public $weekly_schedule  = 'Mon:1;Tue:1;Wed:1;Thu:1;Fri:1';
	/** @var string JSON string of persistent allowances */
	public $allowances_json   = '';
	/** @var ?string Current job position (sync to llx_user.job, not stored in llx_sgpayroll_employee) */
	public $job_position_current = null;
	/** @var string Last SQL query executed (for debug) */
	public $lastSql;

	/** @var array<string,bool> */
	private $tableExistsCache = array();

	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db     = $db;
		// When multicompany is disabled, Dolibarr uses entity 1; ensure we never use 0
		$this->entity = (int)($conf->entity ?? 1);
		if ($this->entity < 1 && !isModEnabled('multicompany')) {
			$this->entity = 1;
		}
	}

	/**
	 * Check if a DB table exists (by table element name, without prefix).
	 *
	 * @param  string $tableNoPrefix
	 * @return bool
	 */
	private function tableExists($tableNoPrefix)
	{
		$tableNoPrefix = (string) $tableNoPrefix;
		if (isset($this->tableExistsCache[$tableNoPrefix])) return $this->tableExistsCache[$tableNoPrefix];
		$sql = "SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX.$tableNoPrefix)."'";
		$res = $this->db->query($sql);
		$ok = ($res && $this->db->num_rows($res) > 0);
		$this->tableExistsCache[$tableNoPrefix] = $ok;
		return $ok;
	}

	/**
	 * Check if sgpayroll_employee_jobpos has contract_currency and contract_salary columns (upgrade 12a).
	 *
	 * @return bool
	 */
	private function jobposHasContractColumns()
	{
		$key = 'sgpayroll_employee_jobpos_contract';
		if (isset($this->tableExistsCache[$key])) return $this->tableExistsCache[$key];
		if (!$this->tableExists('sgpayroll_employee_jobpos')) {
			$this->tableExistsCache[$key] = false;
			return false;
		}
		$sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos LIKE 'contract_currency'";
		$res = $this->db->query($sql);
		$ok = ($res && $this->db->num_rows($res) > 0);
		$this->tableExistsCache[$key] = $ok;
		return $ok;
	}

	/** Public check for contract columns (for UI upgrade notice). */
	public function hasJobposContractColumns()
	{
		return $this->jobposHasContractColumns();
	}

	/**
	 * Load employee record by fk_user.
	 * @param  int  $userId  Dolibarr user ID
	 * @return int  1 if found, 0 if not found, -1 on error
	 */
	public function fetchByUser($userId)
	{
		$sql  = "SELECT e.*, u.gender as core_gender, u.job as core_job, ";
		$sql .= " u.salary as core_salary, u.thm as core_thm, u.birth as core_dob, ";
		$sql .= " u.dateemployment as core_dateemployment, u.dateemploymentend as core_cessation_date, u.fk_user as core_supervisor, u.statut as core_status, ";
		$sql .= " rib.bank as core_bank_name, rib.number as core_bank_account, rib.bic as core_bank_bic ";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user_rib rib ON rib.fk_user = e.fk_user";
		$sql .= " WHERE e.fk_user = ".(int)$userId;
		$sql .= " AND e.entity = ".(int)$this->entity;

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->num_rows($res) === 0 && !isModEnabled('multicompany')) {
			// Without multicompany, accept entity 0 or 1 (legacy or default)
			$sql2  = "SELECT e.*, u.gender as core_gender, u.job as core_job, ";
			$sql2 .= " u.salary as core_salary, u.thm as core_thm, u.birth as core_dob, ";
			$sql2 .= " u.dateemployment as core_dateemployment, u.dateemploymentend as core_cessation_date, u.fk_user as core_supervisor, u.statut as core_status, ";
			$sql2 .= " rib.bank as core_bank_name, rib.number as core_bank_account, rib.bic as core_bank_bic ";
			$sql2 .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
			$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
			$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."user_rib rib ON rib.fk_user = e.fk_user";
			$sql2 .= " WHERE e.fk_user = ".(int)$userId;
			$sql2 .= " AND e.entity IN (0, 1)";
			$res = $this->db->query($sql2);
		}
		if (!$res || $this->db->num_rows($res) === 0) {
			return 0;
		}
		$obj = $this->db->fetch_object($res);

		// Prioritize core data if available
		if (isset($obj->core_salary)) $obj->basic_salary = $obj->core_salary;
		if (isset($obj->core_thm))    $obj->hourly_rate  = $obj->core_thm;
		if (isset($obj->core_dob))    $obj->dob          = $obj->core_dob;
		if (isset($obj->core_gender)) $obj->gender       = $obj->core_gender; // Mapping core gender directly
		if (isset($obj->core_dateemployment)) $obj->work_contract_date = $obj->core_dateemployment;
		if (isset($obj->core_cessation_date)) $obj->cessation_date     = $obj->core_cessation_date;
		if (isset($obj->core_supervisor))     $obj->fk_supervisor      = $obj->core_supervisor;
		if (isset($obj->core_status))         $obj->status             = (int)$obj->core_status;
		
		if (isset($obj->core_bank_name))      $obj->bank_name          = $obj->core_bank_name;
		if (isset($obj->core_bank_account))   $obj->bank_account       = $obj->core_bank_account;
		if (isset($obj->core_bank_bic))       $obj->bank_branch_code   = $obj->core_bank_bic; // Mapping BIC to branch code for SG context
		if (isset($obj->core_job))            $obj->job_position_current = $obj->core_job;

		$this->setVarsFromDbObject($obj);
		return 1;
	}

	/**
	 * Create or update employee record.
	 * @param  User  $user  Current Dolibarr user
	 * @return int   rowid on success, -1 on error
	 */
	public function save($user)
	{
		// Normalize date fields to timestamps if they are strings (from fetch)
		$dateFields = array('pr_start_date', 'dob', 'pass_expiry', 'passport_expiry', 'work_contract_date', 'probation_end_date', 'cessation_date');
		foreach ($dateFields as $df) {
			if (!empty($this->$df) && !is_numeric($this->$df)) {
				$this->$df = $this->db->jdate($this->$df);
			}
		}

		// Determine insert or update
		$existing = 0;
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."sgpayroll_employee WHERE fk_user = ".(int)$this->fk_user." AND entity = ".(int)$this->entity;
		$res = $this->db->query($sql);
		if ($res && $this->db->num_rows($res) > 0) {
			$existing = $this->db->fetch_object($res)->rowid;
			$this->id = $existing; // Ensure ID is set for update
		}

		$fields = array(
			'fk_user'           => (int)$this->fk_user,
			'nric_fin'          => $this->nric_fin,
			'id_type'           => $this->id_type,
			'citizenship'       => $this->citizenship,
			'pr_start_date'     => $this->pr_start_date ? ("'".$this->db->idate($this->pr_start_date)."'") : 'NULL',
			'race'              => $this->race,
			'is_muslim'         => (int)$this->is_muslim,
			'dob'               => $this->dob ? ("'".$this->db->idate($this->dob)."'") : 'NULL',
			'employment_type'   => $this->employment_type,
			'pass_type'         => $this->pass_type,
			'pass_number'       => $this->pass_number,
			'pass_expiry'       => $this->pass_expiry ? ("'".$this->db->idate($this->pass_expiry)."'") : 'NULL',
			'passport_number'   => $this->passport_number,
			'passport_expiry'   => $this->passport_expiry ? ("'".$this->db->idate($this->passport_expiry)."'") : 'NULL',
			'work_contract_date'=> $this->work_contract_date ? ("'".$this->db->idate($this->work_contract_date)."'") : 'NULL',
			'probation_end_date'=> $this->probation_end_date ? ("'".$this->db->idate($this->probation_end_date)."'") : 'NULL',
			'weekly_schedule'   => $this->weekly_schedule,
			'cessation_date'    => $this->cessation_date ? ("'".$this->db->idate($this->cessation_date)."'") : 'NULL',
			'tax_residency'     => $this->tax_residency,
			'basic_salary'      => (float)$this->basic_salary,
			'hourly_rate'       => (float)$this->hourly_rate,
			'payment_mode'      => $this->payment_mode,
			'bank_name'         => $this->bank_name,
			'bank_branch_code'  => $this->bank_branch_code,
			'bank_account'      => $this->bank_account,
			'shg_opt_out_cdac'  => (int)$this->shg_opt_out_cdac,
			'shg_opt_out_ecf'   => (int)$this->shg_opt_out_ecf,
			'shg_opt_out_mbmf'  => (int)$this->shg_opt_out_mbmf,
			'shg_opt_out_sinda' => (int)$this->shg_opt_out_sinda,
			'status'            => (int)$this->status,
			'entity'            => (int)$this->entity,
			'pending_json'      => $this->pending_json,
			'fk_supervisor'     => (int)$this->fk_supervisor ?: 'NULL',
			'workdays_per_month'=> (float)$this->workdays_per_month,
			'contract_currency' => $this->contract_currency,
			'contract_salary'   => (float)$this->contract_salary,
			'exchange_rate'     => (float)$this->exchange_rate,
			'allowances_json'   => $this->allowances_json,
			'fk_cost_centre'    => (int)$this->fk_cost_centre,
		);
		dol_syslog('sgpayroll SghrEmployee::save() fields: '.json_encode($fields), LOG_DEBUG);

		$this->db->begin();

		// 1. Sync to Core Dolibarr User table
		// Require proper date formatting for Dolibarr DB
		// Properties are now guaranteed to be timestamps (or null)
		$db_dob = $this->dob ? "'".$this->db->idate($this->dob)."'" : 'NULL';
		$db_employment = $this->work_contract_date ? "'".$this->db->idate($this->work_contract_date)."'" : 'NULL';
		$db_cessation  = $this->cessation_date ? "'".$this->db->idate($this->cessation_date)."'" : 'NULL';

		$sql_user = "UPDATE ".MAIN_DB_PREFIX."user SET ";
		$sql_user .= " salary = ".(float)$this->basic_salary;
		$sql_user .= ", thm = ".(float)$this->hourly_rate;
		if ($this->job_position_current !== null) {
			$sql_user .= ", job = '".$this->db->escape($this->job_position_current)."'";
		}
		$sql_user .= ", birth = ".$db_dob;
		$sql_user .= ", gender = ".($this->gender ? "'".$this->db->escape($this->gender)."'" : 'NULL');
		$sql_user .= ", dateemployment = ".$db_employment;
		$sql_user .= ", dateemploymentend = ".$db_cessation;
		$sql_user .= ", fk_user = ".((int)$this->fk_supervisor ?: 'NULL');
		$sql_user .= ", statut = ".(int)$this->status;
		$sql_user .= " WHERE rowid = ".(int)$this->fk_user;
		if (!$this->db->query($sql_user)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		// 2. Sync to Core Dolibarr User RIB table
		$sql_rib_check = "SELECT rowid FROM ".MAIN_DB_PREFIX."user_rib WHERE fk_user = ".(int)$this->fk_user;
		$res_rib = $this->db->query($sql_rib_check);
		if ($res_rib && $this->db->num_rows($res_rib) > 0) {
			$sql_rib = "UPDATE ".MAIN_DB_PREFIX."user_rib SET ";
			$sql_rib .= " bank = '".$this->db->escape($this->bank_name)."'";
			$sql_rib .= ", number = '".$this->db->escape($this->bank_account)."'";
			$sql_rib .= ", bic = '".$this->db->escape($this->bank_branch_code)."'";
			$sql_rib .= " WHERE fk_user = ".(int)$this->fk_user;
		} else {
			$sql_rib = "INSERT INTO ".MAIN_DB_PREFIX."user_rib (fk_user, bank, number, bic, entity) VALUES (";
			$sql_rib .= (int)$this->fk_user.", '".$this->db->escape($this->bank_name)."', '".$this->db->escape($this->bank_account)."', '".$this->db->escape($this->bank_branch_code)."', ".(int)$this->entity.")";
		}
		if (!$this->db->query($sql_rib)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		// 3. Save to SGPayroll specific table
		if ($existing) {
			$setSql = array();
			foreach ($fields as $col => $val) {
				if (in_array($col, array('pass_expiry','passport_expiry','pr_start_date','dob','work_contract_date','probation_end_date','cessation_date','fk_supervisor'))) {
					$setSql[] = $col.' = '.$val;
				} else {
					$setSql[] = $col." = '".$this->db->escape($val)."'";
				}
			}
			$setSql[] = "fk_user_modif = ".(int)$user->id;
			$setSql[] = "tms = '".$this->db->idate(dol_now())."'";
			$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee SET ".implode(', ', $setSql)." WHERE rowid = ".(int)$existing;
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		} else {
			$cols = array_keys($fields);
			$vals = array();
			foreach ($fields as $col => $val) {
				if (in_array($col, array('pass_expiry','passport_expiry','pr_start_date','dob','work_contract_date','probation_end_date','cessation_date','fk_supervisor'))) {
					$vals[] = $val;
				} else {
					$vals[] = "'".$this->db->escape($val)."'";
				}
			}
			$cols[] = 'fk_user_creat';
			$vals[] = (int)$user->id;
			$cols[] = 'date_creation';
			$vals[] = "'".$this->db->idate(dol_now())."'";
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_employee (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'sgpayroll_employee');
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Map DB object properties to class properties.
	 * @param  stdClass  $obj
	 */
	protected function setVarsFromDbObject($obj)
	{
		$this->id               = $obj->rowid;
		$this->fk_user          = $obj->fk_user;
		$this->nric_fin         = $obj->nric_fin;
		$this->id_type          = $obj->id_type;
		$this->citizenship      = $obj->citizenship;
		$this->pr_start_date    = $obj->pr_start_date;
		$this->race             = $obj->race;
		$this->is_muslim        = $obj->is_muslim;
		$this->gender           = $obj->gender ?? $obj->core_gender;
		$this->dob              = $obj->dob;
		$this->employment_type  = $obj->employment_type;
		$this->pass_type        = $obj->pass_type;
		$this->pass_number      = $obj->pass_number;
		$this->pass_expiry      = $obj->pass_expiry;
		$this->passport_number  = $obj->passport_number;
		$this->passport_expiry  = $obj->passport_expiry;
		$this->work_contract_date  = $obj->work_contract_date;
		$this->probation_end_date  = $obj->probation_end_date;
		$this->cessation_date   = $obj->cessation_date;
		$this->tax_residency    = $obj->tax_residency;
		$this->fk_cost_centre   = (int) ($obj->fk_cost_centre ?? 0);
		$this->basic_salary     = $obj->basic_salary;
		$this->hourly_rate      = $obj->hourly_rate;
		$this->payment_mode     = $obj->payment_mode;
		$this->bank_name        = $obj->bank_name;
		$this->bank_branch_code = $obj->bank_branch_code;
		$this->bank_account     = $obj->bank_account;
		$this->shg_opt_out_cdac  = $obj->shg_opt_out_cdac;
		$this->shg_opt_out_ecf   = $obj->shg_opt_out_ecf;
		$this->shg_opt_out_mbmf  = $obj->shg_opt_out_mbmf;
		$this->shg_opt_out_sinda = $obj->shg_opt_out_sinda;
		$this->status           = $obj->status;
		$this->pending_json     = $obj->pending_json;
		$this->fk_supervisor    = $obj->fk_supervisor;
		$this->workdays_per_month = $obj->workdays_per_month ?? 0;
		$this->contract_currency = $obj->contract_currency ?? 'SGD';
		$this->contract_salary  = $obj->contract_salary ?? 0;
		$this->exchange_rate    = $obj->exchange_rate ?? 1.0;
		$this->weekly_schedule  = $obj->weekly_schedule ?? 'Mon,Tue,Wed,Thu,Fri';
		$this->allowances_json  = $obj->allowances_json ?? '';
		$this->job_position_current = $obj->job_position_current ?? ($obj->core_job ?? null);
	}

	/**
	 * Get all active employees as array.
	 * @param  DoliDB  $db
	 * @param  array   $filters  Optional ['employment_type'=>'...']
	 * @return array   Array of SghrEmployee objects
	 */
	public static function fetchAll($db, $filters = array())
	{
		global $conf;
		$entity = (int)($conf->entity ?? 1);
		$sql  = "SELECT e.*, u.lastname, u.firstname, u.email, u.login, u.gender as core_gender, ";
		$sql .= " u.salary as core_salary, u.thm as core_thm, u.birth as core_dob, ";
		$sql .= " u.dateemployment as core_dateemployment, u.dateemploymentend as core_cessation_date, u.fk_user as core_supervisor, u.statut as core_status, ";
		$sql .= " rib.bank as core_bank_name, rib.number as core_bank_account, rib.bic as core_bank_bic ";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user_rib rib ON rib.fk_user = e.fk_user";
		$sql .= " WHERE e.entity = ".$entity;
		
		if (!empty($filters['status'])) {
			$status_val = (int)$filters['status'];
			if ($status_val >= 0) $sql .= " AND u.statut = ".$status_val;
		} else {
			// Default to active only if not specified
			$sql .= " AND u.statut = 1";
		}

		if (!empty($filters['employment_type'])) {
			$sql .= " AND e.employment_type = '".$db->escape($filters['employment_type'])."'";
		}
		$sql .= " ORDER BY u.lastname, u.firstname";

		$res  = $db->query($sql);
		$objs = array();
		while ($res && $obj = $db->fetch_object($res)) {
			// Prioritize core data if available
			if (isset($obj->core_salary)) $obj->basic_salary = $obj->core_salary;
			if (isset($obj->core_thm))    $obj->hourly_rate  = $obj->core_thm;
			if (isset($obj->core_dob))    $obj->dob          = $obj->core_dob;
			if (isset($obj->core_dateemployment)) $obj->work_contract_date = $obj->core_dateemployment;
			if (isset($obj->core_cessation_date)) $obj->cessation_date     = $obj->core_cessation_date;
			if (isset($obj->core_supervisor))     $obj->fk_supervisor      = $obj->core_supervisor;
			if (isset($obj->core_status))         $obj->status             = (int)$obj->core_status;
			
			if (isset($obj->core_bank_name))      $obj->bank_name          = $obj->core_bank_name;
			if (isset($obj->core_bank_account))   $obj->bank_account       = $obj->core_bank_account;
			if (isset($obj->core_bank_bic))       $obj->bank_branch_code   = $obj->core_bank_bic;

			$emp = new self($db);
			$emp->setVarsFromDbObject($obj);
			$emp->lastname  = $obj->lastname;
			$emp->firstname = $obj->firstname;
			$emp->email     = $obj->email;
			$emp->login     = $obj->login;
			$objs[]         = $emp;
		}
		return $objs;
	}

	/**
	 * Fetch latest job position record for a user.
	 *
	 * @param  int $userId
	 * @return array|null  ['rowid'=>int,'date_start'=>string,'date_end'=>?string,'job_position'=>string,'salary_sgd'=>float] or null
	 */
	public function fetchLatestJobPosition($userId)
	{
		if (!$this->tableExists('sgpayroll_employee_jobpos')) {
			return null;
		}
		$hasContract = $this->jobposHasContractColumns();
		$sql  = "SELECT rowid, date_start, date_end, job_position, salary_sgd";
		if ($hasContract) $sql .= ", contract_currency, contract_salary";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
		$sql .= " WHERE fk_user = ".(int)$userId." AND entity = ".(int)$this->entity;
		$sql .= " ORDER BY date_start DESC, rowid DESC";
		$sql .= " LIMIT 1";

		$res = $this->db->query($sql);
		if (!$res) return null;
		if ($this->db->num_rows($res) === 0) return null;
		$obj = $this->db->fetch_object($res);
		return array(
			'rowid'            => (int) $obj->rowid,
			'date_start'       => $obj->date_start,
			'date_end'         => $obj->date_end,
			'job_position'     => (string) $obj->job_position,
			'salary_sgd'       => (float) $obj->salary_sgd,
			'contract_currency'=> ($hasContract && isset($obj->contract_currency)) ? (string) $obj->contract_currency : 'SGD',
			'contract_salary'  => ($hasContract && isset($obj->contract_salary)) ? (float) $obj->contract_salary : (float) $obj->salary_sgd,
		);
	}

	/**
	 * Fetch job position history records for a user.
	 *
	 * @param  int $userId
	 * @param  int $limit
	 * @return array<int,array>
	 */
	public function fetchJobPositionHistory($userId, $limit = 50)
	{
		if (!$this->tableExists('sgpayroll_employee_jobpos')) {
			return array();
		}
		$hasContract = $this->jobposHasContractColumns();
		$limit = max(1, (int)$limit);
		$sql  = "SELECT rowid, date_start, date_end, job_position, salary_sgd";
		if ($hasContract) $sql .= ", contract_currency, contract_salary";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
		$sql .= " WHERE fk_user = ".(int)$userId." AND entity = ".(int)$this->entity;
		$sql .= " ORDER BY date_start DESC, rowid DESC";
		$sql .= " LIMIT ".$limit;

		$res = $this->db->query($sql);
		if (!$res) return array();
		$out = array();
		while ($obj = $this->db->fetch_object($res)) {
			$out[] = array(
				'rowid'            => (int) $obj->rowid,
				'date_start'       => $obj->date_start,
				'date_end'         => $obj->date_end,
				'job_position'     => (string) $obj->job_position,
				'salary_sgd'       => (float) $obj->salary_sgd,
				'contract_currency'=> ($hasContract && isset($obj->contract_currency)) ? (string) $obj->contract_currency : 'SGD',
				'contract_salary'  => ($hasContract && isset($obj->contract_salary)) ? (float) $obj->contract_salary : (float) $obj->salary_sgd,
			);
		}
		return $out;
	}

	/**
	 * Insert a job position history record.
	 *
	 * @param int  $userId
	 * @param string $jobPosition
	 * @param int|null $dateStartTs
	 * @param int|null $dateEndTs
	 * @param float $salarySgd
	 * @param string $contractCurrency
	 * @param float $contractSalary
	 * @param User $actor
	 * @return int  rowid on success, -1 on error
	 */
	public function addJobPositionHistory($userId, $jobPosition, $dateStartTs, $dateEndTs, $salarySgd, $actor, $contractCurrency = 'SGD', $contractSalary = 0.0)
	{
		if (!$this->tableExists('sgpayroll_employee_jobpos')) {
			$this->error = 'Missing table: '.MAIN_DB_PREFIX.'sgpayroll_employee_jobpos (run SG Payroll → Setup → Install/Upgrade Tables)';
			return -1;
		}
		$jobPosition = trim((string)$jobPosition);
		if ($jobPosition === '') return -1;

		$dateStartTs = $dateStartTs ?: dol_now();
		$dbStart = "'".$this->db->idate($dateStartTs)."'";
		$dbEnd   = $dateEndTs ? ("'".$this->db->idate($dateEndTs)."'") : 'NULL';
		$hasContract = $this->jobposHasContractColumns();
		$ccy = preg_replace('/[^A-Z]/', '', strtoupper((string)$contractCurrency)) ?: 'SGD';
		$csal = (float)$contractSalary;

		$sql  = "INSERT INTO ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
		$sql .= " (fk_user, date_start, date_end, job_position, salary_sgd";
		if ($hasContract) $sql .= ", contract_currency, contract_salary";
		$sql .= ", date_creation, fk_user_creat, entity) VALUES (";
		$sql .= (int)$userId.", ".$dbStart.", ".$dbEnd.", '".$this->db->escape($jobPosition)."', ".((float)$salarySgd);
		if ($hasContract) $sql .= ", '".$this->db->escape($ccy)."', ".$csal;
		$sql .= ", '".$this->db->idate(dol_now())."', ".(int)$actor->id.", ".(int)$this->entity;
		$sql .= ")";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'sgpayroll_employee_jobpos');
	}

	/**
	 * Fetch one job position history row (scoped to user+entity).
	 *
	 * @param int $userId
	 * @param int $rowid
	 * @return array|null
	 */
	public function fetchJobPositionHistoryRow($userId, $rowid)
	{
		if (!$this->tableExists('sgpayroll_employee_jobpos')) return null;
		$hasContract = $this->jobposHasContractColumns();
		$sql  = "SELECT rowid, date_start, date_end, job_position, salary_sgd";
		if ($hasContract) $sql .= ", contract_currency, contract_salary";
		$sql .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
		$sql .= " WHERE rowid = ".(int)$rowid;
		$sql .= " AND fk_user = ".(int)$userId;
		$sql .= " AND entity = ".(int)$this->entity;
		$sql .= " LIMIT 1";
		$res = $this->db->query($sql);
		if ($res && $this->db->num_rows($res) > 0) {
			$obj = $this->db->fetch_object($res);
		} else {
			// Fallback: try without entity
			$sql2  = "SELECT rowid, date_start, date_end, job_position, salary_sgd";
			if ($hasContract) $sql2 .= ", contract_currency, contract_salary";
			$sql2 .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
			$sql2 .= " WHERE rowid = ".(int)$rowid;
			$sql2 .= " AND fk_user = ".(int)$userId;
			$sql2 .= " LIMIT 1";
			$res2 = $this->db->query($sql2);
			if (!$res2 || $this->db->num_rows($res2) === 0) return null;
			$obj = $this->db->fetch_object($res2);
		}

		return array(
			'rowid'            => (int) $obj->rowid,
			'date_start'       => $obj->date_start,
			'date_end'         => $obj->date_end,
			'job_position'     => (string) $obj->job_position,
			'salary_sgd'       => (float) $obj->salary_sgd,
			'contract_currency'=> ($hasContract && isset($obj->contract_currency)) ? (string) $obj->contract_currency : 'SGD',
			'contract_salary'  => ($hasContract && isset($obj->contract_salary)) ? (float) $obj->contract_salary : (float) $obj->salary_sgd,
		);
	}

	/**
	 * Update one job position history row.
	 *
	 * @param int $userId
	 * @param int $rowid
	 * @param string $jobPosition
	 * @param int|null $dateStartTs
	 * @param int|null $dateEndTs
	 * @param float $salarySgd
	 * @param string $contractCurrency
	 * @param float $contractSalary
	 * @param User $actor
	 * @return int 1 on success, -1 on error
	 */
	public function updateJobPositionHistory($userId, $rowid, $jobPosition, $dateStartTs, $dateEndTs, $salarySgd, $actor, $contractCurrency = 'SGD', $contractSalary = 0.0)
	{
		if (!$this->tableExists('sgpayroll_employee_jobpos')) {
			$this->error = 'Missing table: '.MAIN_DB_PREFIX.'sgpayroll_employee_jobpos';
			return -1;
		}
		$jobPosition = trim((string)$jobPosition);
		if ($jobPosition === '') return -1;
		if (empty($dateStartTs)) return -1;

		$dbStart = "'".$this->db->idate($dateStartTs)."'";
		$dbEnd   = $dateEndTs ? ("'".$this->db->idate($dateEndTs)."'") : 'NULL';
		$hasContract = $this->jobposHasContractColumns();
		$ccy = preg_replace('/[^A-Z]/', '', strtoupper((string)$contractCurrency)) ?: 'SGD';

		$sql  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos SET";
		$sql .= " date_start = ".$dbStart;
		$sql .= ", date_end = ".$dbEnd;
		$sql .= ", job_position = '".$this->db->escape($jobPosition)."'";
		$sql .= ", salary_sgd = ".((float)$salarySgd);
		if ($hasContract) {
			$sql .= ", contract_currency = '".$this->db->escape($ccy)."'";
			$sql .= ", contract_salary = ".((float)$contractSalary);
		}
		$sql .= ", fk_user_modif = ".(int)$actor->id;
		$sql .= " WHERE rowid = ".(int)$rowid;
		$sql .= " AND fk_user = ".(int)$userId;
		$sql .= " AND entity = ".(int)$this->entity;

		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		
		// Consider success if query ran, even if 0 rows affected (e.g. updating same data)
		// Try fallbacks if entity or user mismatch
		if ($this->db->affected_rows($res) < 1) {
			// Fallback 1: try without entity
			$sql2  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos SET";
			$sql2 .= " date_start = ".$dbStart;
			$sql2 .= ", date_end = ".$dbEnd;
			$sql2 .= ", job_position = '".$this->db->escape($jobPosition)."'";
			$sql2 .= ", salary_sgd = ".((float)$salarySgd);
			if ($hasContract) {
				$sql2 .= ", contract_currency = '".$this->db->escape($ccy)."'";
				$sql2 .= ", contract_salary = ".((float)$contractSalary);
			}
			$sql2 .= ", fk_user_modif = ".(int)$actor->id;
			$sql2 .= " WHERE rowid = ".(int)$rowid." AND fk_user = ".(int)$userId;
			$res2 = $this->db->query($sql2);
			if (!$res2 || $this->db->affected_rows($res2) < 1) {
				// Fallback 2: rowid only (last resort)
				$sql3  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos SET";
				$sql3 .= " date_start = ".$dbStart;
				$sql3 .= ", date_end = ".$dbEnd;
				$sql3 .= ", job_position = '".$this->db->escape($jobPosition)."'";
				$sql3 .= ", salary_sgd = ".((float)$salarySgd);
				if ($hasContract) {
					$sql3 .= ", contract_currency = '".$this->db->escape($ccy)."'";
					$sql3 .= ", contract_salary = ".((float)$contractSalary);
				}
				$sql3 .= ", fk_user_modif = ".(int)$actor->id;
				$sql3 .= " WHERE rowid = ".(int)$rowid;
				$res3 = $this->db->query($sql3);
				if (!$res3 || $this->db->affected_rows($res3) < 1) {
					// Final check: if query was successful but 0 rows, it might just be identical data
					// We return success if the record exists but nothing changed.
					if ($this->fetchJobPositionHistoryRow($userId, $rowid)) return 1;
					
					$this->error = 'No row updated';
					return -1;
				}
			}
		}
		return 1;
	}

	/**
	 * Delete one job position history row.
	 *
	 * @param int $userId
	 * @param int $rowid
	 * @return int 1 on success, -1 on error
	 */
	public function deleteJobPositionHistory($userId, $rowid)
	{
		if (!$this->tableExists('sgpayroll_employee_jobpos')) {
			$this->error = 'Missing table: '.MAIN_DB_PREFIX.'sgpayroll_employee_jobpos';
			return -1;
		}
		$sql  = "DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
		$sql .= " WHERE rowid = ".(int)$rowid;
		$sql .= " AND fk_user = ".(int)$userId;
		$sql .= " AND entity = ".(int)$this->entity;
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if ($this->db->affected_rows($res) < 1) {
			// Fallback 1: try without entity
			$sql2 = "DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
			$sql2 .= " WHERE rowid = ".(int)$rowid." AND fk_user = ".(int)$userId;
			$res2 = $this->db->query($sql2);
			if ($res2 && $this->db->affected_rows($res2) >= 1) return 1;

			// Fallback 2: rowid only
			$sql3 = "DELETE FROM ".MAIN_DB_PREFIX."sgpayroll_employee_jobpos";
			$sql3 .= " WHERE rowid = ".(int)$rowid;
			$res3 = $this->db->query($sql3);
			if ($res3 && $this->db->affected_rows($res3) >= 1) return 1;

			$this->error = 'No row deleted';
			return -1;
		}
		return 1;
	}

	/**
	 * Sync core user.job and salary from latest history record.
	 * Also sync sgpayroll_employee.basic_salary for payroll calculations.
	 *
	 * @param int $userId
	 * @param User $actor
	 * @return int 1 if synced, 0 if no history, -1 on error
	 */
	public function syncCurrentJobPositionFromHistory($userId, $actor)
	{
		$latest = $this->fetchLatestJobPosition($userId);
		if (empty($latest)) return 0;

		$job = trim((string)$latest['job_position']);
		$salary = (float)$latest['salary_sgd'];
		$ccy = isset($latest['contract_currency']) ? $this->db->escape((string)$latest['contract_currency']) : 'SGD';
		$csal = isset($latest['contract_salary']) ? (float)$latest['contract_salary'] : $salary;

		$this->db->begin();

		$sqlU  = "UPDATE ".MAIN_DB_PREFIX."user SET";
		$sqlU .= " job = '".$this->db->escape($job)."'";
		$sqlU .= ", salary = ".$salary;
		$sqlU .= " WHERE rowid = ".(int)$userId;
		if (!$this->db->query($sqlU)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$sqlE  = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee SET";
		$sqlE .= " basic_salary = ".$salary;
		$sqlE .= ", contract_currency = '".$ccy."'";
		$sqlE .= ", contract_salary = ".$csal;
		$sqlE .= ", fk_user_modif = ".(int)$actor->id;
		$sqlE .= ", tms = '".$this->db->idate(dol_now())."'";
		$sqlE .= " WHERE fk_user = ".(int)$userId;
		$sqlE .= " AND entity = ".(int)$this->entity;
		if (!$this->db->query($sqlE)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Queue a change request for sensitive fields.
	 * @param  array  $changes  Key-value pairs of proposed changes
	 * @return int              1 on success, -1 on error
	 */
	public function submitChangeRequest($changes)
	{
		$currentPending = array();
		if (!empty($this->pending_json)) {
			$currentPending = json_decode($this->pending_json, true) ?: array();
		}
		// Merge new changes into existing queue
		foreach ($changes as $k => $v) {
			$currentPending[$k] = $v;
		}
		$this->pending_json = json_encode($currentPending);
		
		$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee SET pending_json = '".$this->db->escape($this->pending_json)."' WHERE rowid = ".(int)$this->id;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Apply pending changes to the main record.
	 * @param  User  $user  Approving user
	 * @return int          1 on success, -1 on error
	 */
	public function approveChangeRequest($user)
	{
		if (empty($this->pending_json)) return 0;
		$changes = json_decode($this->pending_json, true);
		if (empty($changes)) return 0;

		foreach ($changes as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		$this->pending_json = null;
		return ($this->save($user) > 0) ? 1 : -1;
	}

	/**
	 * Clear the pending queue without applying changes.
	 * @return int  1 on success, -1 on error
	 */
	public function rejectChangeRequest()
	{
		$this->pending_json = null;
		$sql = "UPDATE ".MAIN_DB_PREFIX."sgpayroll_employee SET pending_json = NULL WHERE rowid = ".(int)$this->id;
		return $this->db->query($sql) ? 1 : -1;
	}
}
