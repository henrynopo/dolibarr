-- ============================================================
-- SG Payroll Module: Upgrade 7a — CPF & Levies social contribution type
-- File: sql/llx_sgpayroll_upgrade_7a_cpflevies_sociales.sql
-- Purpose:
--   Registers the dictionary type used by the accounting trigger
--   (interface_99_modSGPayroll_AccountingHook) to upsert the monthly
--   "CPF & Levies" social contribution (compta/sociales) from the
--   payslip Grand Total (employee CPF + employer CPF + SDL + SHG).
--
-- Run via Admin → SG Payroll → Settings → Install/Upgrade Tables
--   OR execute manually in phpMyAdmin / mysql CLI.
-- Safe to re-run: guarded by NOT EXISTS.
-- ============================================================

INSERT INTO llx_c_chargesociales (libelle, deductible, active, code, accountancy_code, fk_pays, module)
SELECT 'CPF & Levies (SG Payroll)', 1, 1, 'SGCPFLEVY', NULL, 29, 'sghr'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM llx_c_chargesociales WHERE code = 'SGCPFLEVY');
