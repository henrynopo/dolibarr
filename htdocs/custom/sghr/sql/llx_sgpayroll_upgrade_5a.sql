-- ============================================================
-- SG Payroll Module: Schema Upgrade — Employee Extended Fields
-- File: sql/llx_sgpayroll_upgrade_5a.sql
-- Run via Admin → SG Payroll → Settings → Install/Upgrade Tables
-- Safe to run multiple times (uses IF NOT EXISTS pattern where supported)
-- ============================================================

-- ── 1. Supervisor linkage (added for approval workflow) ────────────────────
ALTER TABLE llx_sgpayroll_employee ADD COLUMN
    fk_supervisor INT DEFAULT NULL COMMENT 'Direct manager (User ID) for subordinate-based access control';

-- ── 2. Per-employee work days override ────────────────────────────────────
ALTER TABLE llx_sgpayroll_employee ADD COLUMN
    workdays_per_month DOUBLE(8,2) DEFAULT 0 COMMENT 'Override for default working days per month (0 = use global default)';
