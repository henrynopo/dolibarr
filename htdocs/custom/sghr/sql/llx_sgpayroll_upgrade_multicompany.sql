-- =============================================================================
-- SG Payroll Module: Multi-Company (Entity) Migration
-- File: sql/llx_sgpayroll_upgrade_multicompany.sql
-- Run once via Admin → SG Payroll → Settings → Install/Upgrade Tables
-- Safe to run multiple times (uses IF NOT EXISTS / IGNORE patterns).
-- =============================================================================

-- 1. llx_sgpayroll_employee
ALTER TABLE llx_sgpayroll_employee ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';
ALTER TABLE llx_sgpayroll_employee ADD COLUMN pending_json TEXT DEFAULT NULL COMMENT 'Queued changes for HR approval';
ALTER TABLE llx_sgpayroll_employee ADD INDEX idx_sgpayroll_emp_entity (entity);

-- Drop old unique index and create the new multicompany-aware unique index
ALTER TABLE llx_sgpayroll_employee DROP INDEX uq_sgpayroll_employee_user;
ALTER TABLE llx_sgpayroll_employee ADD UNIQUE INDEX uq_sgpayroll_employee_user (fk_user, entity);

-- 2. llx_sgpayroll_payroll (run header)
ALTER TABLE llx_sgpayroll_payroll ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';
ALTER TABLE llx_sgpayroll_payroll ADD INDEX idx_sgpayroll_pay_entity (entity);

-- 3. llx_sgpayroll_payroll_line
ALTER TABLE llx_sgpayroll_payroll_line ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';
ALTER TABLE llx_sgpayroll_payroll_line ADD INDEX idx_sgpayroll_line_entity (entity);

-- 4. llx_sgpayroll_claims (if table exists)
ALTER TABLE llx_sgpayroll_claims ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';
ALTER TABLE llx_sgpayroll_claims ADD INDEX idx_sgpayroll_claims_entity (entity);

-- 5. llx_sgpayroll_documents
ALTER TABLE llx_sgpayroll_documents ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';
ALTER TABLE llx_sgpayroll_documents ADD INDEX idx_sgpayroll_docs_entity (entity);

-- 6. llx_sgpayroll_ais_review
ALTER TABLE llx_sgpayroll_ais_review ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';
ALTER TABLE llx_sgpayroll_ais_review ADD INDEX idx_sgpayroll_ais_entity (entity);

-- 7. llx_sgpayroll_appendix8a
ALTER TABLE llx_sgpayroll_appendix8a ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';

-- 8. llx_sgpayroll_appendix8b
ALTER TABLE llx_sgpayroll_appendix8b ADD COLUMN entity INT NOT NULL DEFAULT 1 COMMENT 'Dolibarr entity (multi-company)';

-- NOTE: llx_sgpayroll_cpfrates intentionally has NO entity column —
-- CPF rates are Singapore-wide and shared by all entities in the installation.
