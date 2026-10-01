-- Upgrade 14a: Seed Singapore public holidays into Dolibarr dictionary llx_c_hrm_public_holiday.
-- Payslip calculation (SGPayrollCalc::getSingaporePublicHolidays) reads from this table when
-- country = Singapore (SG). Maintain via: Setup → Dictionary → Public holidays (filter by Singapore).
-- year=0 means every year; year>0 means that specific year only (for variable dates like CNY, Deepavali).
-- ============================================================

-- Fixed holidays (year=0: apply every year). fk_country from llx_c_country where code='SG'.
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-NY', c.rowid, '', 0,  1,  1, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-LABOUR', c.rowid, '', 0,  5,  1, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-ND', c.rowid, '', 0,  8,  9, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-XMAS', c.rowid, '', 0, 12, 25, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;

-- Good Friday (dayrule: computed from Easter)
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-GF', c.rowid, 'goodfriday', 0, 0, 0, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;

-- 2024 variable (exact dates)
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-CNY1-2024', c.rowid, '', 2024,  2, 10, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-CNY2-2024', c.rowid, '', 2024,  2, 11, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-HRP-2024', c.rowid, '', 2024,  4, 10, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-VESAK-2024', c.rowid, '', 2024,  5, 22, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-HRH-2024', c.rowid, '', 2024,  6, 17, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-DEEP-2024', c.rowid, '', 2024, 10, 31, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;

-- 2025 variable
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-CNY1-2025', c.rowid, '', 2025,  1, 29, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-CNY2-2025', c.rowid, '', 2025,  1, 30, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-HRP-2025', c.rowid, '', 2025,  3, 31, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-VESAK-2025', c.rowid, '', 2025,  5, 12, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-HRH-2025', c.rowid, '', 2025,  6,  7, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-DEEP-2025', c.rowid, '', 2025, 10, 20, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;

-- 2026 variable
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-CNY1-2026', c.rowid, '', 2026,  2, 17, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-CNY2-2026', c.rowid, '', 2026,  2, 18, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-HRP-2026', c.rowid, '', 2026,  3, 20, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-VESAK-2026', c.rowid, '', 2026,  5, 28, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-HRH-2026', c.rowid, '', 2026,  5, 31, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
INSERT IGNORE INTO llx_c_hrm_public_holiday (entity, code, fk_country, dayrule, year, month, day, active)
SELECT 1, 'SG-DEEP-2026', c.rowid, '', 2026, 11,  8, 1 FROM llx_c_country c WHERE c.code = 'SG' LIMIT 1;
