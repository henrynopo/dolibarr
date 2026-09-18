-- SG Payroll Module: Schema Upgrade 12a — Job Position history: add contract_currency, contract_salary
-- File: sql/llx_sgpayroll_upgrade_12a.sql
-- Run via Setup → Install/Upgrade Tables. Re-run safe (Duplicate column → ignored by setup).

ALTER TABLE llx_sgpayroll_employee_jobpos
    ADD COLUMN contract_currency VARCHAR(3) DEFAULT 'SGD' COMMENT 'ISO 4217 contract currency' AFTER salary_sgd,
    ADD COLUMN contract_salary DOUBLE(24,8) DEFAULT 0.00000000 COMMENT 'Monthly salary in contract currency' AFTER contract_currency;
