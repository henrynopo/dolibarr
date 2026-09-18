-- ============================================================
-- SG Payroll Module: Schema Upgrade 6a — is_cpf_liable ExtraField Migration
-- File: sql/llx_sgpayroll_upgrade_6a.sql
-- Purpose:
--   Registers `is_cpf_liable` as a Dolibarr ExtraField on the
--   `expensereport_det` element (expense line level), so that
--   individual expense items can be flagged as CPF-liable or not.
--   This replaces the old field in llx_sgpayroll_claims.
--
-- Run via Admin → SG Payroll → Settings → Install/Upgrade Tables
--   OR execute manually in phpMyAdmin / mysql CLI.
-- Safe to re-run: uses INSERT IGNORE.
-- ============================================================

-- ── 1. Register is_cpf_liable ExtraField on expensereport_det ─────────────
-- Dolibarr stores custom fields in llx_extrafields. The actual data column
-- is added to llx_expensereport_det automatically on module activation.
-- We insert the metadata row here for modules that do not use PHP-based init.

INSERT IGNORE INTO llx_extrafields
    (name, label, type, size, elementtype, fieldunique, fieldrequired, param, alwayseditable, perms, langs, list, printable,
     totalizable, fieldpos, enabled, help, entity)
VALUES
    ('is_cpf_liable',
     'CPF Liable',
     'boolean',
     '',
     'expensereport_det',
     0,
     0,
     NULL,
     1,
     '',
     'sgpayroll@sgpayroll',
     '1',
     '1',
     0,
     100,
     '1',
     'Tick if this expense item should be included in CPF-liable income calculation (for payroll integration)',
     0);

-- ── 2. Add actual data column to expensereport_det table ──────────────────
-- Dolibarr extrafields saves data in the main table as a column named
-- options_<fieldname>.
ALTER TABLE llx_expensereport_det
    ADD COLUMN options_is_cpf_liable TINYINT(1) DEFAULT 0
    COMMENT 'SG Payroll: CPF liable flag (1=yes, 0=no). Registered via ExtraFields.';

-- ── 3. Also register at expensereport (header) level for summary display ──
INSERT IGNORE INTO llx_extrafields
    (name, label, type, size, elementtype, fieldunique, fieldrequired, param, alwayseditable, perms, langs, list, printable,
     totalizable, fieldpos, enabled, help, entity)
VALUES
    ('sgp_notes',
     'SG Payroll Notes',
     'varchar',
     '255',
     'expensereport',
     0,
     0,
     NULL,
     1,
     '',
     'sgpayroll@sgpayroll',
     '0',
     '0',
     0,
     101,
     '1',
     'Internal payroll notes (e.g. claim month, payslip reference)',
     0);

ALTER TABLE llx_expensereport
    ADD COLUMN options_sgp_notes VARCHAR(255) DEFAULT NULL
    COMMENT 'SG Payroll: internal payroll processing notes';
