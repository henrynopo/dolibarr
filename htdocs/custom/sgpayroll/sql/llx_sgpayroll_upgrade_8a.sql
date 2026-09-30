-- ============================================================
-- SG Payroll Module: Schema Upgrade 8a — Email Notification Log
-- File:    sql/llx_sgpayroll_upgrade_8a.sql
-- Purpose: Create llx_sgpayroll_email_log table to record
--          all payslip email notifications sent to employees.
-- Run via: Admin → SG Payroll → Settings → Install/Upgrade Tables
-- ============================================================

-- ── Create email_log table ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS llx_sgpayroll_email_log (
	rowid        INT          NOT NULL AUTO_INCREMENT,
	fk_user      INT          NOT NULL          COMMENT 'Employee user ID (llx_user.rowid)',
	pay_year     SMALLINT     NOT NULL          COMMENT 'Payroll year',
	pay_month    TINYINT      NOT NULL          COMMENT 'Payroll month (1-12)',
	email_to     VARCHAR(255) NOT NULL          COMMENT 'Recipient email address',
	date_sent    DATETIME     NOT NULL          COMMENT 'UTC timestamp of send attempt',
	pdf_attached TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 if PDF was attached, 0 otherwise',
	send_status  VARCHAR(20)  NOT NULL DEFAULT 'sent' COMMENT 'sent | failed | skipped',
	error_msg    VARCHAR(500)     NULL          COMMENT 'Error details if send_status=failed',
	fk_user_sender INT            NULL          COMMENT 'HR user who triggered the send',
	entity       INT          NOT NULL DEFAULT 1,
	PRIMARY KEY (rowid),
	KEY idx_sgp_emaillog_user  (fk_user),
	KEY idx_sgp_emaillog_period(pay_year, pay_month),
	KEY idx_sgp_emaillog_entity(entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Portal requests table (upgrade_7a safety re-run) ─────────────────────────
-- Already created by upgrade_7a.sql — included here as IF NOT EXISTS guard only
CREATE TABLE IF NOT EXISTS llx_sgpayroll_portal_requests (
	rowid              INT          NOT NULL AUTO_INCREMENT,
	fk_user            INT          NOT NULL,
	request_type       VARCHAR(50)  NOT NULL DEFAULT 'other',
	note               TEXT             NULL,
	status             VARCHAR(20)  NOT NULL DEFAULT 'pending',
	date_request       DATETIME     NOT NULL,
	fk_user_reviewer   INT              NULL,
	date_reviewed      DATETIME         NULL,
	reviewer_note      TEXT             NULL,
	entity             INT          NOT NULL DEFAULT 1,
	PRIMARY KEY (rowid),
	KEY idx_sgp_portal_req_user  (fk_user),
	KEY idx_sgp_portal_req_status(status),
	KEY idx_sgp_portal_req_entity(entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
