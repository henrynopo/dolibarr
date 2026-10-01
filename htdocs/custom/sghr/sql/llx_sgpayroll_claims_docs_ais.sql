-- ============================================================
-- Expense Claims
-- Table: llx_sgpayroll_claims
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_claims (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL COMMENT 'Claimant',
    claim_date        DATE          NOT NULL,
    category          VARCHAR(50)   DEFAULT '' COMMENT 'Transport|Meal|Medical|Entertainment|Other',
    description       TEXT,
    amount            DOUBLE(24,8)  DEFAULT 0,
    currency          VARCHAR(3)    DEFAULT 'SGD',
    is_cpf_liable     TINYINT(1)    DEFAULT 0,
    receipt_file      VARCHAR(255)  DEFAULT '' COMMENT 'Path to attached receipt',
    status            VARCHAR(20)   DEFAULT 'draft' COMMENT 'draft|submitted|approved|rejected|paid',
    fk_payroll_line   INT           DEFAULT NULL COMMENT 'Linked payroll line when paid',
    fk_user_approve   INT           DEFAULT NULL,
    date_approve      DATETIME      DEFAULT NULL,
    note              TEXT,
    date_creation     DATETIME      NOT NULL,
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat     INT           DEFAULT NULL,
    entity            INT           NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity',
    INDEX idx_sgpayroll_claims_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Employee Document Vault
-- Table: llx_sgpayroll_documents
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_documents (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL COMMENT 'Document owner (employee)',
    doc_type          VARCHAR(50)   NOT NULL COMMENT 'NRIC|FIN|PASSPORT|WORKPASS|CONTRACT|QUALIFICATION|INSURANCE|REVIEW|OTHER',
    doc_label         VARCHAR(200)  DEFAULT '',
    file_path         VARCHAR(500)  DEFAULT '' COMMENT 'Relative path under DOL_DATA_ROOT',
    issue_date        DATE          DEFAULT NULL,
    expiry_date       DATE          DEFAULT NULL,
    reminder_sent_60  TINYINT(1)    DEFAULT 0,
    reminder_sent_30  TINYINT(1)    DEFAULT 0,
    reminder_sent_7   TINYINT(1)    DEFAULT 0,
    notes             TEXT,
    date_creation     DATETIME      NOT NULL,
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat     INT           DEFAULT NULL,
    entity            INT           NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity',
    INDEX idx_sgpayroll_docs_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- IRAS AIS Employment Income Review
-- Table: llx_sgpayroll_ais_review
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_ais_review (
    rowid              INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user            INT          NOT NULL COMMENT 'Employee',
    year_of_assessment SMALLINT     NOT NULL COMMENT 'e.g. 2026 for income year 2025',
    -- Auto-computed from payroll lines
    gross_salary       DOUBLE(24,8) DEFAULT 0,
    bonus              DOUBLE(24,8) DEFAULT 0,
    commission         DOUBLE(24,8) DEFAULT 0,
    transport_allowance DOUBLE(24,8) DEFAULT 0,
    other_allowances   DOUBLE(24,8) DEFAULT 0,
    overtime_pay       DOUBLE(24,8) DEFAULT 0,
    employee_cpf       DOUBLE(24,8) DEFAULT 0,
    mbmf               DOUBLE(24,8) DEFAULT 0 COMMENT 'SHG Donation',
    sinda              DOUBLE(24,8) DEFAULT 0 COMMENT 'SHG Donation',
    cdac               DOUBLE(24,8) DEFAULT 0 COMMENT 'SHG Donation',
    ecf                DOUBLE(24,8) DEFAULT 0 COMMENT 'SHG Donation',
    -- Manual entry
    bik_value          DOUBLE(24,8) DEFAULT 0 COMMENT 'Benefits in kind (manual)',
    director_fees      DOUBLE(24,8) DEFAULT 0,
    gratuity           DOUBLE(24,8) DEFAULT 0,
    -- Calculated
    taxable_income     DOUBLE(24,8) DEFAULT 0,
    -- Workflow status
    status             VARCHAR(30)  DEFAULT 'draft' COMMENT 'draft|manager_reviewed|approver_confirmed',
    reviewed_by        INT          DEFAULT NULL COMMENT 'fk_user of HR Manager who reviewed',
    reviewed_date      DATETIME     DEFAULT NULL,
    confirmed_by       INT          DEFAULT NULL COMMENT 'fk_user of HR Approver who confirmed',
    confirmed_date     DATETIME     DEFAULT NULL,
    discrepancy_flag   TINYINT(1)   DEFAULT 0 COMMENT '1=discrepancy noted by manager',
    notes              TEXT,
    tms                TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    entity             INT          NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity',
    UNIQUE KEY uq_ais_review (fk_user, year_of_assessment, entity),
    INDEX idx_sgpayroll_ais_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
