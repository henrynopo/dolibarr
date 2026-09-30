-- SG Payroll Module: Schema Upgrade 11a — Add Cost Centres
-- File: sql/llx_sgpayroll_upgrade_11a.sql

CREATE TABLE IF NOT EXISTS llx_sgpayroll_cost_centre (
    rowid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entity INT NOT NULL DEFAULT 1,
    code VARCHAR(32) NOT NULL,
    label VARCHAR(255) NOT NULL,
    active TINYINT DEFAULT 1,
    tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_creation DATETIME DEFAULT NULL,
    UNIQUE KEY uk_sgpayroll_cost_centre (entity, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE llx_sgpayroll_employee ADD COLUMN fk_cost_centre INT DEFAULT 0 AFTER tax_residency;
