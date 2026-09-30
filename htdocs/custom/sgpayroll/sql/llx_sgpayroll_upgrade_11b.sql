-- SG Payroll Module: Schema Upgrade 11b — Add allowances_json to employee
-- File: sql/llx_sgpayroll_upgrade_11b.sql

ALTER TABLE llx_sgpayroll_employee ADD COLUMN allowances_json TEXT DEFAULT NULL AFTER exchange_rate;
