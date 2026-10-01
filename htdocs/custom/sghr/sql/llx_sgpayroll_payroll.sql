-- ============================================================
-- Monthly payroll run header
-- Table: llx_sgpayroll_payroll
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_payroll (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ref               VARCHAR(30)   NOT NULL COMMENT 'e.g. PAY-2025-06',
    pay_year          SMALLINT      NOT NULL,
    pay_month         TINYINT       NOT NULL COMMENT '1-12',
    status            VARCHAR(20)   DEFAULT 'draft' COMMENT 'draft|submitted|approved|paid',
    total_gross       DOUBLE(24,8)  DEFAULT 0,
    total_net         DOUBLE(24,8)  DEFAULT 0,
    total_employer_cpf DOUBLE(24,8) DEFAULT 0,
    total_employee_cpf DOUBLE(24,8) DEFAULT 0,
    total_sdl         DOUBLE(24,8)  DEFAULT 0,
    total_fwl         DOUBLE(24,8)  DEFAULT 0,
    note_private      TEXT,
    date_creation     DATETIME      NOT NULL,
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat     INT           DEFAULT NULL,
    fk_user_modif     INT           DEFAULT NULL,
    fk_user_approve   INT           DEFAULT NULL,
    date_approve      DATETIME      DEFAULT NULL,
    ya_locked         TINYINT(1)    DEFAULT 0 COMMENT '1=Year locked after AIS confirm',
    -- Multi-company and Multi-currency
    entity            INT           NOT NULL DEFAULT 1,
    multicurrency_used TINYINT(1)    DEFAULT 0,
    INDEX idx_sgpayroll_pay_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Monthly payroll per-employee line
-- Table: llx_sgpayroll_payroll_line
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_payroll_line (
    rowid              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_payroll         INT          NOT NULL COMMENT 'FK to llx_sgpayroll_payroll.rowid',
    fk_user            INT          NOT NULL COMMENT 'FK to llx_user.rowid',
    -- Basic components
    basic_salary       DOUBLE(24,8) DEFAULT 0,
    work_days          DOUBLE(8,2)  DEFAULT 0 COMMENT 'Actual working days this month',
    prorated_factor    DOUBLE(8,6)  DEFAULT 1.000000,
    prorated_salary    DOUBLE(24,8) DEFAULT 0,
    -- Allowances (JSON for flexibility)
    allowances_json    TEXT         COMMENT 'JSON: [{label, amount, cpf_liable}]',
    allowances_total   DOUBLE(24,8) DEFAULT 0,
    -- Additional wages
    bonus              DOUBLE(24,8) DEFAULT 0,
    commission         DOUBLE(24,8) DEFAULT 0,
    overtime_pay       DOUBLE(24,8) DEFAULT 0,
    overtime_hours     DOUBLE(8,2)  DEFAULT 0,
    al_encashment      DOUBLE(24,8) DEFAULT 0,
    other_aw           DOUBLE(24,8) DEFAULT 0,
    aw_total           DOUBLE(24,8) DEFAULT 0,
    -- Deductions
    upl_days           DOUBLE(8,2)  DEFAULT 0,
    upl_deduction      DOUBLE(24,8) DEFAULT 0,
    salary_advance_recovery DOUBLE(24,8) DEFAULT 0,
    other_deductions   DOUBLE(24,8) DEFAULT 0,
    -- Claims reimbursement
    claims_total       DOUBLE(24,8) DEFAULT 0,
    -- CPF
    ordinary_wages     DOUBLE(24,8) DEFAULT 0,
    additional_wages   DOUBLE(24,8) DEFAULT 0,
    employee_cpf       DOUBLE(24,8) DEFAULT 0,
    employer_cpf       DOUBLE(24,8) DEFAULT 0,
    employee_cpf_oa    DOUBLE(24,8) DEFAULT 0,
    employee_cpf_sa    DOUBLE(24,8) DEFAULT 0,
    employee_cpf_ma    DOUBLE(24,8) DEFAULT 0,
    -- Levies (employer cost)
    sdl_amount         DOUBLE(24,8) DEFAULT 0,
    fwl_amount         DOUBLE(24,8) DEFAULT 0,
    -- SHG donations (employee deductions)
    shg_cdac           DOUBLE(24,8) DEFAULT 0,
    shg_ecf            DOUBLE(24,8) DEFAULT 0,
    shg_mbmf           DOUBLE(24,8) DEFAULT 0,
    shg_sinda          DOUBLE(24,8) DEFAULT 0,
    -- Withholding tax (non-residents)
    withholding_tax    DOUBLE(24,8) DEFAULT 0,
    -- Totals
    gross_salary       DOUBLE(24,8) DEFAULT 0,
    total_deductions   DOUBLE(24,8) DEFAULT 0,
    net_pay            DOUBLE(24,8) DEFAULT 0,
    -- Benefits in kind (for IRAS IR8A)
    -- benefits in kind (for IRAS IR8A)
    bik_value          DOUBLE(24,8) DEFAULT 0,
    -- Multi-currency support
    contract_currency  VARCHAR(3)    DEFAULT 'SGD',
    exchange_rate      DOUBLE(18,8)  DEFAULT 1,
    basic_salary_fc    DOUBLE(24,8)  DEFAULT 0 COMMENT 'Basic salary in foreign currency',
    net_pay_fc         DOUBLE(24,8)  DEFAULT 0 COMMENT 'Net pay in foreign currency',
    -- Multi-company and status
    entity             INT           NOT NULL DEFAULT 1,
    status             VARCHAR(20)  DEFAULT 'draft' COMMENT 'draft|finalised',
    note               TEXT,
    tms                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_line (fk_payroll, fk_user),
    INDEX idx_sgpayroll_line_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
