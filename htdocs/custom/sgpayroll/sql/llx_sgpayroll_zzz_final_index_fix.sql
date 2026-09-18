-- Final robust fix for multicompany index
-- This ensures the unique constraint covers both fk_user and entity

-- First ensure columns exist (Dolibarr run_sql handles errors if already exist)
ALTER TABLE llx_sgpayroll_employee ADD COLUMN entity integer DEFAULT 1 NOT NULL;
ALTER TABLE llx_sgpayroll_employee ADD COLUMN pending_json text DEFAULT NULL;

-- Fix any NULL entities before applying index
UPDATE llx_sgpayroll_employee SET entity = 1 WHERE entity IS NULL;

-- Drop old index (Dolibarr handles error if not exists)
ALTER TABLE llx_sgpayroll_employee DROP INDEX uq_sgpayroll_employee_user;

-- Add new unique multicompany index
-- Use ALTER TABLE syntax which is more reliable across MySQL flavors
ALTER TABLE llx_sgpayroll_employee ADD UNIQUE INDEX uq_sgpayroll_employee_user (fk_user, entity);
