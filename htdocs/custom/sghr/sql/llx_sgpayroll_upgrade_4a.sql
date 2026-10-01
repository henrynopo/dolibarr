-- ============================================================
-- SG Payroll Module: Schema Upgrade — Multi-Currency + FWL + Dolibarr Compat
-- File: sql/llx_sgpayroll_upgrade_4a.sql
-- Run via Admin → SG Payroll → Settings → Install/Upgrade
-- ============================================================

-- ── 1. Multi-currency support on Employee profile ─────────────────────────
ALTER TABLE llx_sgpayroll_employee ADD COLUMN contract_currency VARCHAR(3) DEFAULT 'SGD';
ALTER TABLE llx_sgpayroll_employee ADD COLUMN contract_salary DOUBLE(24,8) DEFAULT 0;
ALTER TABLE llx_sgpayroll_employee ADD COLUMN exchange_rate DOUBLE(18,8) DEFAULT 1;
ALTER TABLE llx_sgpayroll_employee ADD COLUMN exchange_rate_date DATE DEFAULT NULL;

-- ── 2. Multi-currency on Payroll Line ─────────────────────────────────────
ALTER TABLE llx_sgpayroll_payroll_line ADD COLUMN contract_currency VARCHAR(3) DEFAULT 'SGD';
ALTER TABLE llx_sgpayroll_payroll_line ADD COLUMN exchange_rate DOUBLE(18,8) DEFAULT 1;
ALTER TABLE llx_sgpayroll_payroll_line ADD COLUMN basic_salary_fc DOUBLE(24,8) DEFAULT 0;
ALTER TABLE llx_sgpayroll_payroll_line ADD COLUMN net_pay_fc DOUBLE(24,8) DEFAULT 0;

-- ── 3. Multi-currency on Payroll Run header ───────────────────────────────
ALTER TABLE llx_sgpayroll_payroll ADD COLUMN multicurrency_used TINYINT(1) DEFAULT 0;

-- ── 5. Add reminder columns if not exist ─────────────────────────────────
ALTER TABLE llx_sgpayroll_documents ADD COLUMN reminder_sent_60 TINYINT(1) DEFAULT 0;
ALTER TABLE llx_sgpayroll_documents ADD COLUMN reminder_sent_30 TINYINT(1) DEFAULT 0;
ALTER TABLE llx_sgpayroll_documents ADD COLUMN reminder_sent_7 TINYINT(1) DEFAULT 0;
