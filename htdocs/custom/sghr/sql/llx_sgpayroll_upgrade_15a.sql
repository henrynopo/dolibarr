-- SG Payroll Module: Schema Upgrade 15a — Schedule presets (weekly schedule templates)
-- File: sql/llx_sgpayroll_upgrade_15a.sql

CREATE TABLE IF NOT EXISTS llx_sgpayroll_schedule_preset (
    rowid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entity INT NOT NULL DEFAULT 1,
    code VARCHAR(64) NOT NULL,
    label VARCHAR(255) NOT NULL,
    schedule_value VARCHAR(255) NOT NULL COMMENT 'e.g. Mon:1;Tue:1;Wed:1;Thu:1;Fri:1',
    active TINYINT DEFAULT 1,
    tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    date_creation DATETIME DEFAULT NULL,
    UNIQUE KEY uk_sgpayroll_schedule_preset (entity, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
