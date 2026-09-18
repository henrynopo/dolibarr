-- ============================================================
-- SG Payroll Module: Employee Job Position History
-- Table: llx_sgpayroll_employee_jobpos
-- Purpose:
--   Store multiple historical job position + salary records per employee.
--   Latest record is the employee's current Job Position for sync to llx_user.job.
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_employee_jobpos (
    rowid              INT            NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user            INT            NOT NULL COMMENT 'FK to llx_user.rowid',
    date_start         DATE           NOT NULL COMMENT 'Effective start date',
    date_end           DATE           DEFAULT NULL COMMENT 'Effective end date (optional)',
    job_position       VARCHAR(128)   NOT NULL DEFAULT '' COMMENT 'Job position / title',
    salary_sgd         DOUBLE(24,8)   NOT NULL DEFAULT 0.00000000 COMMENT 'Monthly salary in SGD (basic salary)',
    contract_currency  VARCHAR(3)     DEFAULT 'SGD' COMMENT 'ISO 4217 contract currency',
    contract_salary    DOUBLE(24,8)   DEFAULT 0.00000000 COMMENT 'Monthly salary in contract currency',
    -- Meta
    date_creation      DATETIME       NOT NULL,
    tms                TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat      INT            DEFAULT NULL,
    fk_user_modif      INT            DEFAULT NULL,
    entity             INT            NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity',
    INDEX idx_sgpayroll_jobpos_user (fk_user, entity),
    INDEX idx_sgpayroll_jobpos_start (fk_user, entity, date_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

