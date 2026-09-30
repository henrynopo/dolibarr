-- ============================================================
-- SG Payroll Module: Employee extended profile
-- Table: llx_sgpayroll_employee
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_employee (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL COMMENT 'FK to llx_user.rowid',
    -- Identity
    nric_fin          VARCHAR(20)   DEFAULT '' COMMENT 'NRIC/FIN/Passport (encrypted at application layer)',
    id_type           VARCHAR(10)   DEFAULT 'NRIC' COMMENT 'NRIC|FIN|WP|SP|EP|MIC|PASSPORT',
    citizenship       VARCHAR(10)   DEFAULT 'SC' COMMENT 'SC|PR1Y|PR2Y|PR3Y|FIN',
    pr_start_date     DATE          DEFAULT NULL COMMENT 'Date PR status granted',
    race              VARCHAR(20)   DEFAULT '' COMMENT 'Chinese|Malay|Indian|Eurasian|Others',
    is_muslim         TINYINT(1)    DEFAULT 0 COMMENT '1=Muslim (MBMF applies)',
    dob               DATE          DEFAULT NULL COMMENT 'Date of birth',
    -- Employment
    employment_type   VARCHAR(20)   DEFAULT 'fulltime' COMMENT 'fulltime|parttime|contract|freelancer|intern',
    pass_type         VARCHAR(10)   DEFAULT '' COMMENT 'EP|SP|S-Pass|WP|LTVP|None',
    pass_number       VARCHAR(30)   DEFAULT '',
    pass_expiry       DATE          DEFAULT NULL,
    passport_number   VARCHAR(30)   DEFAULT '',
    passport_expiry   DATE          DEFAULT NULL,
    work_contract_date DATE         DEFAULT NULL,
    probation_end_date DATE         DEFAULT NULL,
    cessation_date    DATE          DEFAULT NULL COMMENT 'Date of resignation/termination',
    tax_residency     VARCHAR(20)   DEFAULT 'resident' COMMENT 'resident|non_resident',
    -- Salary
    basic_salary      DOUBLE(24,8)  DEFAULT 0.00000000,
    hourly_rate       DOUBLE(24,8)  DEFAULT 0.00000000,
    payment_mode      VARCHAR(20)   DEFAULT 'bank' COMMENT 'bank|cash|cheque',
    bank_name         VARCHAR(100)  DEFAULT '',
    bank_branch_code  VARCHAR(10)   DEFAULT '',
    bank_account      VARCHAR(50)   DEFAULT '' COMMENT 'Encrypted at application layer',
    -- SHG opt-out flags
    shg_opt_out_cdac  TINYINT(1)    DEFAULT 0,
    shg_opt_out_ecf   TINYINT(1)    DEFAULT 0,
    shg_opt_out_mbmf  TINYINT(1)    DEFAULT 0,
    shg_opt_out_sinda TINYINT(1)    DEFAULT 0,
    -- Meta
    date_creation     DATETIME      NOT NULL,
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat     INT           DEFAULT NULL,
    fk_user_modif     INT           DEFAULT NULL,
    import_key        VARCHAR(14)   DEFAULT NULL,
    status            INT           DEFAULT 1 COMMENT '1=active, 0=inactive',
    -- Multi-currency support
    contract_currency  VARCHAR(3)    DEFAULT 'SGD' COMMENT 'ISO 4217 currency code',
    contract_salary    DOUBLE(24,8)  DEFAULT 0     COMMENT 'Salary in contract currency',
    exchange_rate      DOUBLE(18,8)  DEFAULT 1     COMMENT 'Exchange rate to SGD',
    exchange_rate_date DATE          DEFAULT NULL  COMMENT 'Date exchange rate was last set',
    allowances_json   TEXT          DEFAULT NULL  COMMENT 'Queued persistent allowances as JSON',
    -- Multi-company support
    entity            INT           NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity',
    pending_json      TEXT          DEFAULT NULL COMMENT 'Queued changes for HR approval',
    fk_supervisor     INT           DEFAULT NULL COMMENT 'Manager/Supervisor (User ID)',
    workdays_per_month DOUBLE(8,2)  DEFAULT 0 COMMENT 'Override for default working days per month (0 = use global default)',
    weekly_schedule   VARCHAR(255)  DEFAULT 'Mon,Tue,Wed,Thu,Fri' COMMENT 'JSON or comma-separated list of working days',
    fk_cost_centre    INT           DEFAULT 0,
    UNIQUE KEY uq_sgpayroll_employee_user (fk_user, entity),
    INDEX idx_sgpayroll_emp_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
