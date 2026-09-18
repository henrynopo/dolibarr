-- ============================================================
-- SG Payroll Module: Schema Upgrade 7a — Employee Portal Requests Table
-- File: sql/llx_sgpayroll_upgrade_7a.sql
-- Purpose: Creates table to store employee information change requests
--          submitted through the Employee Self-Service Portal.
-- Safe to re-run (uses IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS llx_sgpayroll_portal_requests (
    rowid           INT(11) NOT NULL AUTO_INCREMENT,
    fk_user         INT(11) NOT NULL COMMENT 'Employee who submitted the request',
    request_type    VARCHAR(50) NOT NULL COMMENT 'bank_info | address | emergency | tax_residency | other',
    note            TEXT COMMENT 'Details of the change request',
    status          VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | reviewed | done | rejected',
    date_request    DATETIME DEFAULT NULL,
    date_reviewed   DATETIME DEFAULT NULL,
    fk_user_reviewer INT(11) DEFAULT NULL COMMENT 'HR/Admin who reviewed',
    reviewer_note   TEXT COMMENT 'HR response / action taken',
    entity          INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (rowid),
    INDEX idx_portal_req_user (fk_user),
    INDEX idx_portal_req_status (status),
    INDEX idx_portal_req_entity (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='SG Payroll: Employee self-service information change requests';
