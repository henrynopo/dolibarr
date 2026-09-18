-- ============================================================
-- CPF rate tables (versioned)
-- Table: llx_sgpayroll_cpfrates
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_cpfrates (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    effective_date    DATE          NOT NULL COMMENT 'Rate effective from this date',
    citizenship_tier  VARCHAR(10)   NOT NULL COMMENT 'SC|PR1Y|PR2Y|PR3Y',
    age_from          TINYINT       NOT NULL COMMENT 'Age lower bound (inclusive)',
    age_to            TINYINT       NOT NULL COMMENT 'Age upper bound (inclusive, 0=unbounded)',
    wage_band         VARCHAR(20)   NOT NULL DEFAULT 'above750' COMMENT 'above750|501to750|500andbelow',
    employer_rate     DECIMAL(6,4)  NOT NULL DEFAULT 0.0000 COMMENT 'e.g. 0.1700 = 17%',
    employee_rate     DECIMAL(6,4)  NOT NULL DEFAULT 0.0000,
    ow_ceiling        DOUBLE(12,2)  NOT NULL DEFAULT 7400.00 COMMENT 'Ordinary Wage ceiling SGD/month',
    aw_annual_ceiling DOUBLE(12,2)  NOT NULL DEFAULT 102000.00 COMMENT 'Annual wage ceiling SGD',
    oa_pct            DECIMAL(6,4)  DEFAULT 0.0000 COMMENT 'OA allocation from total cpf',
    sa_pct            DECIMAL(6,4)  DEFAULT 0.0000,
    ma_pct            DECIMAL(6,4)  DEFAULT 0.0000,
    note              VARCHAR(255)  DEFAULT '',
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cpfrates (effective_date, citizenship_tier, age_from, age_to, wage_band)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: 2024 rates (OW ceiling $6,800; effective 1 Jan 2024)
INSERT IGNORE INTO llx_sgpayroll_cpfrates
(effective_date, citizenship_tier, age_from, age_to, wage_band, employer_rate, employee_rate, ow_ceiling, aw_annual_ceiling, oa_pct, sa_pct, ma_pct, note) VALUES
('2024-01-01','SC', 0, 55, 'above750',   0.1700, 0.2000, 6800, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 2024'),
('2024-01-01','SC',55, 60, 'above750',   0.1500, 0.1600, 6800, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 2024'),
('2024-01-01','SC',60, 65, 'above750',   0.1200, 0.1050, 6800, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 2024'),
('2024-01-01','SC',65, 70, 'above750',   0.0900, 0.0750, 6800, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 2024'),
('2024-01-01','SC',70, 99, 'above750',   0.0750, 0.0500, 6800, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 2024'),
('2024-01-01','PR1Y',0, 55, 'above750',  0.0400, 0.0500, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR1Y <=55 2024'),
('2024-01-01','PR1Y',55, 60, 'above750', 0.0400, 0.0500, 6800, 102000, 0.3694, 0.4072, 0.2234, 'PR1Y 55-60 2024'),
('2024-01-01','PR1Y',60, 65, 'above750', 0.0400, 0.0500, 6800, 102000, 0.1891, 0.4054, 0.4054, 'PR1Y 60-65 2024'),
('2024-01-01','PR1Y',65, 99, 'above750', 0.0400, 0.0500, 6800, 102000, 0.0000, 0.2500, 0.7500, 'PR1Y 65+ 2024'),
('2024-01-01','PR2Y',0, 55, 'above750',  0.0900, 0.1500, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR2Y <=55 2024'),
('2024-01-01','PR2Y',55, 60, 'above750', 0.0900, 0.1500, 6800, 102000, 0.3694, 0.4072, 0.2234, 'PR2Y 55-60 2024'),
('2024-01-01','PR2Y',60, 65, 'above750', 0.0900, 0.1500, 6800, 102000, 0.1891, 0.4054, 0.4054, 'PR2Y 60-65 2024'),
('2024-01-01','PR2Y',65, 99, 'above750', 0.0900, 0.1500, 6800, 102000, 0.0000, 0.2500, 0.7500, 'PR2Y 65+ 2024'),
('2024-01-01','PR3Y',0, 55, 'above750',  0.1700, 0.2000, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 2024'),
('2024-01-01','PR3Y',55, 60, 'above750', 0.1500, 0.1600, 6800, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 2024'),
('2024-01-01','PR3Y',60, 65, 'above750', 0.1200, 0.1050, 6800, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 2024'),
('2024-01-01','PR3Y',65, 70, 'above750', 0.0900, 0.0750, 6800, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 2024'),
('2024-01-01','PR3Y',70, 99, 'above750', 0.0750, 0.0500, 6800, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 2024');

-- Seed: 2025 rates (OW ceiling $7,400; effective 1 Jan 2025)
INSERT IGNORE INTO llx_sgpayroll_cpfrates
(effective_date, citizenship_tier, age_from, age_to, wage_band, employer_rate, employee_rate, ow_ceiling, aw_annual_ceiling, oa_pct, sa_pct, ma_pct, note) VALUES
('2025-01-01','SC', 0, 55, 'above750',   0.1700, 0.2000, 7400, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 2025'),
('2025-01-01','SC',55, 60, 'above750',   0.1550, 0.1700, 7400, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 2025'),
('2025-01-01','SC',60, 65, 'above750',   0.1200, 0.1150, 7400, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 2025'),
('2025-01-01','SC',65, 70, 'above750',   0.0900, 0.0750, 7400, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 2025'),
('2025-01-01','SC',70, 99, 'above750',   0.0750, 0.0500, 7400, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 2025'),
-- PR 1st year (graduated contribution — reduced rates)
('2025-01-01','PR1Y',0, 55, 'above750',  0.0400, 0.0500, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR1Y <=55 2025'),
('2025-01-01','PR1Y',55, 60, 'above750', 0.0400, 0.0500, 7400, 102000, 0.3694, 0.4072, 0.2234, 'PR1Y 55-60 2025'),
('2025-01-01','PR1Y',60, 65, 'above750', 0.0400, 0.0500, 7400, 102000, 0.1891, 0.4054, 0.4054, 'PR1Y 60-65 2025'),
('2025-01-01','PR1Y',65, 99, 'above750', 0.0400, 0.0500, 7400, 102000, 0.0000, 0.2500, 0.7500, 'PR1Y 65+ 2025'),
-- PR 2nd year
('2025-01-01','PR2Y',0, 55, 'above750',  0.0900, 0.1500, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR2Y <=55 2025'),
('2025-01-01','PR2Y',55, 60, 'above750', 0.0900, 0.1500, 7400, 102000, 0.3694, 0.4072, 0.2234, 'PR2Y 55-60 2025'),
('2025-01-01','PR2Y',60, 65, 'above750', 0.0900, 0.1500, 7400, 102000, 0.1891, 0.4054, 0.4054, 'PR2Y 60-65 2025'),
('2025-01-01','PR2Y',65, 99, 'above750', 0.0900, 0.1500, 7400, 102000, 0.0000, 0.2500, 0.7500, 'PR2Y 65+ 2025'),
-- PR 3rd year and above (full rates same as SC, all age bands)
('2025-01-01','PR3Y',0, 55, 'above750',  0.1700, 0.2000, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 2025'),
('2025-01-01','PR3Y',55, 60, 'above750', 0.1550, 0.1700, 7400, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 2025'),
('2025-01-01','PR3Y',60, 65, 'above750', 0.1200, 0.1150, 7400, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 2025'),
('2025-01-01','PR3Y',65, 70, 'above750', 0.0900, 0.0750, 7400, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 2025'),
('2025-01-01','PR3Y',70, 99, 'above750', 0.0750, 0.0500, 7400, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 2025');

-- 2026 rates (OW ceiling $8,000; senior worker rates stepped up per CPF Advisory Panel)
INSERT IGNORE INTO llx_sgpayroll_cpfrates
(effective_date, citizenship_tier, age_from, age_to, wage_band, employer_rate, employee_rate, ow_ceiling, aw_annual_ceiling, oa_pct, sa_pct, ma_pct, note) VALUES
-- SC full age bands
('2026-01-01','SC', 0, 55, 'above750',   0.1700, 0.2000, 8000, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 2026'),
('2026-01-01','SC',55, 60, 'above750',   0.1600, 0.1800, 8000, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 2026'),
('2026-01-01','SC',60, 65, 'above750',   0.1250, 0.1250, 8000, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 2026'),
('2026-01-01','SC',65, 70, 'above750',   0.0900, 0.0750, 8000, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 2026'),
('2026-01-01','SC',70, 99, 'above750',   0.0750, 0.0500, 8000, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 2026'),
-- PR1Y 2026
('2026-01-01','PR1Y',0, 55, 'above750',  0.0400, 0.0500, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR1Y <=55 2026'),
('2026-01-01','PR1Y',55, 60, 'above750', 0.0400, 0.0500, 8000, 102000, 0.3694, 0.4072, 0.2234, 'PR1Y 55-60 2026'),
('2026-01-01','PR1Y',60, 65, 'above750', 0.0400, 0.0500, 8000, 102000, 0.1891, 0.4054, 0.4054, 'PR1Y 60-65 2026'),
('2026-01-01','PR1Y',65, 99, 'above750', 0.0400, 0.0500, 8000, 102000, 0.0000, 0.2500, 0.7500, 'PR1Y 65+ 2026'),
-- PR2Y 2026
('2026-01-01','PR2Y',0, 55, 'above750',  0.0900, 0.1500, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR2Y <=55 2026'),
('2026-01-01','PR2Y',55, 60, 'above750', 0.0900, 0.1500, 8000, 102000, 0.3694, 0.4072, 0.2234, 'PR2Y 55-60 2026'),
('2026-01-01','PR2Y',60, 65, 'above750', 0.0900, 0.1500, 8000, 102000, 0.1891, 0.4054, 0.4054, 'PR2Y 60-65 2026'),
('2026-01-01','PR2Y',65, 99, 'above750', 0.0900, 0.1500, 8000, 102000, 0.0000, 0.2500, 0.7500, 'PR2Y 65+ 2026'),
-- PR3Y 2026 (full SC rates)
('2026-01-01','PR3Y',0, 55, 'above750',  0.1700, 0.2000, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 2026'),
('2026-01-01','PR3Y',55, 60, 'above750', 0.1600, 0.1800, 8000, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 2026'),
('2026-01-01','PR3Y',60, 65, 'above750', 0.1250, 0.1250, 8000, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 2026'),
('2026-01-01','PR3Y',65, 70, 'above750', 0.0900, 0.0750, 8000, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 2026'),
('2026-01-01','PR3Y',70, 99, 'above750', 0.0750, 0.0500, 8000, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 2026');

-- ============================================================
-- Wage band 500andbelow (OW $50–$500): employer only, employee 0.
-- Used for payslip when monthly OW ≤ $500 (CPF Board threshold).
-- ============================================================
INSERT IGNORE INTO llx_sgpayroll_cpfrates
(effective_date, citizenship_tier, age_from, age_to, wage_band, employer_rate, employee_rate, ow_ceiling, aw_annual_ceiling, oa_pct, sa_pct, ma_pct, note) VALUES
-- 2024
('2024-01-01','SC', 0, 55, '500andbelow', 0.1700, 0.0000, 6800, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 OW$50-500'),
('2024-01-01','SC',55, 60, '500andbelow', 0.1500, 0.0000, 6800, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 OW$50-500'),
('2024-01-01','SC',60, 65, '500andbelow', 0.1200, 0.0000, 6800, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 OW$50-500'),
('2024-01-01','SC',65, 70, '500andbelow', 0.0900, 0.0000, 6800, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 OW$50-500'),
('2024-01-01','SC',70, 99, '500andbelow', 0.0750, 0.0000, 6800, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 OW$50-500'),
('2024-01-01','PR1Y',0, 55, '500andbelow', 0.0400, 0.0000, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR1Y OW$50-500'),
('2024-01-01','PR1Y',55, 99, '500andbelow', 0.0400, 0.0000, 6800, 102000, 0.3694, 0.4072, 0.2234, 'PR1Y OW$50-500'),
('2024-01-01','PR2Y',0, 99, '500andbelow', 0.0900, 0.0000, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR2Y OW$50-500'),
('2024-01-01','PR3Y', 0, 55, '500andbelow', 0.1700, 0.0000, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 OW$50-500'),
('2024-01-01','PR3Y',55, 60, '500andbelow', 0.1500, 0.0000, 6800, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 OW$50-500'),
('2024-01-01','PR3Y',60, 65, '500andbelow', 0.1200, 0.0000, 6800, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 OW$50-500'),
('2024-01-01','PR3Y',65, 70, '500andbelow', 0.0900, 0.0000, 6800, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 OW$50-500'),
('2024-01-01','PR3Y',70, 99, '500andbelow', 0.0750, 0.0000, 6800, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 OW$50-500'),
-- 2025
('2025-01-01','SC', 0, 55, '500andbelow', 0.1700, 0.0000, 7400, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 OW$50-500'),
('2025-01-01','SC',55, 60, '500andbelow', 0.1550, 0.0000, 7400, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 OW$50-500'),
('2025-01-01','SC',60, 65, '500andbelow', 0.1200, 0.0000, 7400, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 OW$50-500'),
('2025-01-01','SC',65, 70, '500andbelow', 0.0900, 0.0000, 7400, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 OW$50-500'),
('2025-01-01','SC',70, 99, '500andbelow', 0.0750, 0.0000, 7400, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 OW$50-500'),
('2025-01-01','PR1Y',0, 99, '500andbelow', 0.0400, 0.0000, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR1Y OW$50-500'),
('2025-01-01','PR2Y',0, 99, '500andbelow', 0.0900, 0.0000, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR2Y OW$50-500'),
('2025-01-01','PR3Y', 0, 55, '500andbelow', 0.1700, 0.0000, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 OW$50-500'),
('2025-01-01','PR3Y',55, 60, '500andbelow', 0.1550, 0.0000, 7400, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 OW$50-500'),
('2025-01-01','PR3Y',60, 65, '500andbelow', 0.1200, 0.0000, 7400, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 OW$50-500'),
('2025-01-01','PR3Y',65, 70, '500andbelow', 0.0900, 0.0000, 7400, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 OW$50-500'),
('2025-01-01','PR3Y',70, 99, '500andbelow', 0.0750, 0.0000, 7400, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 OW$50-500'),
-- 2026
('2026-01-01','SC', 0, 55, '500andbelow', 0.1700, 0.0000, 8000, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 OW$50-500'),
('2026-01-01','SC',55, 60, '500andbelow', 0.1600, 0.0000, 8000, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 OW$50-500'),
('2026-01-01','SC',60, 65, '500andbelow', 0.1250, 0.0000, 8000, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 OW$50-500'),
('2026-01-01','SC',65, 70, '500andbelow', 0.0900, 0.0000, 8000, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 OW$50-500'),
('2026-01-01','SC',70, 99, '500andbelow', 0.0750, 0.0000, 8000, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 OW$50-500'),
('2026-01-01','PR1Y',0, 99, '500andbelow', 0.0400, 0.0000, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR1Y OW$50-500'),
('2026-01-01','PR2Y',0, 99, '500andbelow', 0.0900, 0.0000, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR2Y OW$50-500'),
('2026-01-01','PR3Y', 0, 55, '500andbelow', 0.1700, 0.0000, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 OW$50-500'),
('2026-01-01','PR3Y',55, 60, '500andbelow', 0.1600, 0.0000, 8000, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 OW$50-500'),
('2026-01-01','PR3Y',60, 65, '500andbelow', 0.1250, 0.0000, 8000, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 OW$50-500'),
('2026-01-01','PR3Y',65, 70, '500andbelow', 0.0900, 0.0000, 8000, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 OW$50-500'),
('2026-01-01','PR3Y',70, 99, '500andbelow', 0.0750, 0.0000, 8000, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 OW$50-500');

-- ============================================================
-- Wage band 501to750: rate on OW *in excess of* $500 (graduated band).
-- employer_rate / employee_rate = % on (OW − 500) for $500 < OW ≤ $750.
-- ============================================================
INSERT IGNORE INTO llx_sgpayroll_cpfrates
(effective_date, citizenship_tier, age_from, age_to, wage_band, employer_rate, employee_rate, ow_ceiling, aw_annual_ceiling, oa_pct, sa_pct, ma_pct, note) VALUES
-- 2024–2026: excess rates by age (CPF Board graduated band)
('2024-01-01','SC', 0, 55, '501to750', 0.0060, 0.0060, 6800, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 excess>$500'),
('2024-01-01','SC',55, 60, '501to750', 0.0054, 0.0054, 6800, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 excess'),
('2024-01-01','SC',60, 65, '501to750', 0.00375, 0.00375, 6800, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 excess'),
('2024-01-01','SC',65, 70, '501to750', 0.00225, 0.00225, 6800, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 excess'),
('2024-01-01','SC',70, 99, '501to750', 0.0015, 0.0015, 6800, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 excess'),
('2024-01-01','PR3Y', 0, 55, '501to750', 0.0060, 0.0060, 6800, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 excess'),
('2024-01-01','PR3Y',55, 60, '501to750', 0.0054, 0.0054, 6800, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 excess'),
('2024-01-01','PR3Y',60, 65, '501to750', 0.00375, 0.00375, 6800, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 excess'),
('2024-01-01','PR3Y',65, 70, '501to750', 0.00225, 0.00225, 6800, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 excess'),
('2024-01-01','PR3Y',70, 99, '501to750', 0.0015, 0.0015, 6800, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 excess'),
('2025-01-01','SC', 0, 55, '501to750', 0.0060, 0.0060, 7400, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 excess>$500'),
('2025-01-01','SC',55, 60, '501to750', 0.0054, 0.0054, 7400, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 excess'),
('2025-01-01','SC',60, 65, '501to750', 0.00375, 0.00375, 7400, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 excess'),
('2025-01-01','SC',65, 70, '501to750', 0.00225, 0.00225, 7400, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 excess'),
('2025-01-01','SC',70, 99, '501to750', 0.0015, 0.0015, 7400, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 excess'),
('2025-01-01','PR3Y', 0, 55, '501to750', 0.0060, 0.0060, 7400, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 excess'),
('2025-01-01','PR3Y',55, 60, '501to750', 0.0054, 0.0054, 7400, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 excess'),
('2025-01-01','PR3Y',60, 65, '501to750', 0.00375, 0.00375, 7400, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 excess'),
('2025-01-01','PR3Y',65, 70, '501to750', 0.00225, 0.00225, 7400, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 excess'),
('2025-01-01','PR3Y',70, 99, '501to750', 0.0015, 0.0015, 7400, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 excess'),
('2026-01-01','SC', 0, 55, '501to750', 0.0060, 0.0060, 8000, 102000, 0.6217, 0.2162, 0.1621, 'SC <=55 excess>$500'),
('2026-01-01','SC',55, 60, '501to750', 0.0054, 0.0054, 8000, 102000, 0.3694, 0.4072, 0.2234, 'SC 55-60 excess'),
('2026-01-01','SC',60, 65, '501to750', 0.00375, 0.00375, 8000, 102000, 0.1891, 0.4054, 0.4054, 'SC 60-65 excess'),
('2026-01-01','SC',65, 70, '501to750', 0.00225, 0.00225, 8000, 102000, 0.0000, 0.3333, 0.6667, 'SC 65-70 excess'),
('2026-01-01','SC',70, 99, '501to750', 0.0015, 0.0015, 8000, 102000, 0.0000, 0.0000, 1.0000, 'SC >70 excess'),
('2026-01-01','PR3Y', 0, 55, '501to750', 0.0060, 0.0060, 8000, 102000, 0.6217, 0.2162, 0.1621, 'PR3Y <=55 excess'),
('2026-01-01','PR3Y',55, 60, '501to750', 0.0054, 0.0054, 8000, 102000, 0.3694, 0.4072, 0.2234, 'PR3Y 55-60 excess'),
('2026-01-01','PR3Y',60, 65, '501to750', 0.00375, 0.00375, 8000, 102000, 0.1891, 0.4054, 0.4054, 'PR3Y 60-65 excess'),
('2026-01-01','PR3Y',65, 70, '501to750', 0.00225, 0.00225, 8000, 102000, 0.0000, 0.3333, 0.6667, 'PR3Y 65-70 excess'),
('2026-01-01','PR3Y',70, 99, '501to750', 0.0015, 0.0015, 8000, 102000, 0.0000, 0.0000, 1.0000, 'PR3Y >70 excess');

-- ============================================================
-- Leave accrual ledger
-- Table: llx_sgpayroll_leave_accrual
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_leave_accrual (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL,
    leave_type        VARCHAR(30)   NOT NULL COMMENT 'AL|SL|HL|ML|PL|SPL|CL|NS|NPL|OTHER',
    leave_year        SMALLINT      NOT NULL COMMENT 'Calendar year',
    entitlement_days  DOUBLE(8,2)   DEFAULT 0,
    accrued_days      DOUBLE(8,2)   DEFAULT 0,
    used_days         DOUBLE(8,2)   DEFAULT 0,
    carried_fwd       DOUBLE(8,2)   DEFAULT 0,
    encashed_days     DOUBLE(8,2)   DEFAULT 0,
    balance_days      DOUBLE(8,2)   DEFAULT 0 COMMENT 'entitlement+carried_fwd-used-encashed',
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_leave_accrual (fk_user, leave_type, leave_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Appendix 8A — Benefits in Kind (BIK) detail lines
-- Required for IRAS AIS when employer provides taxable BIK
-- Table: llx_sgpayroll_appendix8a
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_appendix8a (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL COMMENT 'Employee (FK to llx_user.rowid)',
    income_year       SMALLINT      NOT NULL COMMENT 'Calendar year of income',
    -- BIK categories per IRAS Appendix 8A
    car_benefit           DOUBLE(24,8) DEFAULT 0 COMMENT 'Company car: computed annual value',
    car_petrol            DOUBLE(24,8) DEFAULT 0,
    driver_benefit        DOUBLE(24,8) DEFAULT 0,
    accommodation_value   DOUBLE(24,8) DEFAULT 0 COMMENT 'Annual value of accommodation provided',
    accommodation_type    VARCHAR(50)  DEFAULT '' COMMENT 'Furnished|Unfurnished|Hotel',
    home_leave_passage    DOUBLE(24,8) DEFAULT 0,
    education_benefit     DOUBLE(24,8) DEFAULT 0,
    club_membership       DOUBLE(24,8) DEFAULT 0,
    other_bik_desc        TEXT COMMENT 'Description of other BIK items',
    other_bik_value       DOUBLE(24,8) DEFAULT 0,
    total_bik             DOUBLE(24,8) DEFAULT 0 COMMENT 'Sum of all BIK — auto-computed for IR8A export',
    -- Reconciliation
    reviewed_by           INT          DEFAULT NULL,
    reviewed_date         DATETIME     DEFAULT NULL,
    notes                 TEXT,
    date_creation         DATETIME     NOT NULL,
    tms                   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat         INT          DEFAULT NULL,
    UNIQUE KEY uq_8a (fk_user, income_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Appendix 8B — Employee Stock Options/Awards
-- Required for IRAS AIS when employer grants options/RSU/ESOP
-- Table: llx_sgpayroll_appendix8b
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_appendix8b (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL,
    income_year       SMALLINT      NOT NULL,
    -- Grant details
    scheme_name       VARCHAR(100)  DEFAULT '' COMMENT 'Name of ESOP/ESOW scheme',
    scheme_type       VARCHAR(20)   DEFAULT '' COMMENT 'ESOP|RSU|PSU|SAR|ESOW',
    grant_date        DATE          DEFAULT NULL,
    vesting_date      DATE          DEFAULT NULL,
    exercise_date     DATE          DEFAULT NULL,
    shares_exercised  INT           DEFAULT 0,
    price_at_grant    DOUBLE(18,6)  DEFAULT 0 COMMENT 'Market price SGD at grant',
    price_at_exercise DOUBLE(18,6)  DEFAULT 0 COMMENT 'Market price SGD at exercise',
    exercise_price    DOUBLE(18,6)  DEFAULT 0 COMMENT 'Strike price',
    -- Gain
    gross_gain        DOUBLE(24,8)  DEFAULT 0 COMMENT 'Taxable gain = (exercise price - grant price) x shares',
    discount_gain     DOUBLE(24,8)  DEFAULT 0 COMMENT 'Discount component if any',
    total_taxable_gain DOUBLE(24,8) DEFAULT 0,
    -- Tracking
    notes             TEXT,
    date_creation     DATETIME      NOT NULL,
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat     INT           DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Leave accrual ledger
-- Table: llx_sgpayroll_leave_accrual
-- ============================================================
CREATE TABLE IF NOT EXISTS llx_sgpayroll_leave_accrual (
    rowid             INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fk_user           INT           NOT NULL,
    leave_type        VARCHAR(30)   NOT NULL COMMENT 'AL|SL|HL|ML|PL|SPL|CL|NS|NPL|OTHER',
    leave_year        SMALLINT      NOT NULL COMMENT 'Calendar year',
    entitlement_days  DOUBLE(8,2)   DEFAULT 0,
    accrued_days      DOUBLE(8,2)   DEFAULT 0,
    used_days         DOUBLE(8,2)   DEFAULT 0,
    carried_fwd       DOUBLE(8,2)   DEFAULT 0,
    encashed_days     DOUBLE(8,2)   DEFAULT 0,
    balance_days      DOUBLE(8,2)   DEFAULT 0 COMMENT 'entitlement+carried_fwd-used-encashed',
    tms               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_leave_accrual (fk_user, leave_type, leave_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
