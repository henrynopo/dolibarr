-- ============================================================================
-- Singapore SFRS Chart of Accounts (FRS / SFRS(I) / SFRS for Small Entities)
-- ============================================================================
-- Compatible with Dolibarr's accountancy module (llx_accounting_account)
-- Reference: Xero SG "Company - 2024 - 9% GST Rates" template + FRS 1 framework
-- Code range strategy:
--   1000-1099 : PPE
--   1100-1199 : Intangibles
--   1200-1299 : Right-of-use assets (FRS 116)
--   1300-1399 : Investments
--   1400-1499 : Deferred tax / biological assets
--   1500-1599 : Inventory
--   1600-1699 : Trade receivables
--   1700-1799 : Other receivables / loans
--   1800-1899 : Cash & bank
--   1900-1999 : Prepayments
--   2000-2099 : Trade payables
--   2100-2199 : Accruals
--   2200-2299 : GST liabilities
--   2300-2399 : Short-term borrowings
--   2400-2499 : Other current liabilities
--   2500-2599 : Long-term loans
--   2600-2699 : Other non-current liabilities
--   2700-2799 : Lease liabilities (FRS 116)
--   2800-2899 : Provisions
--   2900-2999 : Deferred tax liabilities
--   3000-3099 : Share capital
--   3100-3199 : Retained earnings
--   3200-3299 : Reserves
--   3300-3399 : Dividends declared
--   4000-4099 : Sales of goods
--   4100-4199 : Service revenue
--   4200-4299 : Other operating revenue
--   4900-4999 : Other income
--   5000-5099 : Cost of goods sold
--   5100-5199 : Direct labour / materials
--   6000-6099 : Salaries & benefits
--   6100-6199 : Rent & utilities
--   6200-6299 : Depreciation & amortisation
--   6300-6399 : Selling expenses
--   6400-6499 : Administrative expenses
--   6500-6599 : Finance costs
--   7000-7099 : Interest income
--   7100-7199 : Interest expense
--   7500-7599 : Income tax expense
--   7600-7699 : Foreign exchange gain/loss
-- ============================================================================

-- Parent rowid allocations (used as account_parent for detail rows).
-- Base rowid range: 500000-500804 (so entity 1 gets rowids 100500000..100500804,
-- entity 2 gets 200500000..200500804, etc.). The __ROWID_OFFSET__ placeholder
-- below is replaced at install time by $conf->entity * 100000000.
-- Multicompany marker: ADD 500000 to rowid (discoverable by the multicompany module).

-- 1000-1999 ASSETS root
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500000, 'SFRS-BASE', 'ASSET',    '1', 0, 'ASSETS', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500001, 'SFRS-BASE', 'ASSET',    '10', __ROWID_OFFSET__+500000, 'Non-current Assets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500002, 'SFRS-BASE', 'ASSET',    '15', __ROWID_OFFSET__+500000, 'Current Assets', 1);

-- 1000-1099 Property, Plant and Equipment
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500010, 'SFRS-BASE', 'ASSET', '1000', __ROWID_OFFSET__+500001, 'Land', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500011, 'SFRS-BASE', 'ASSET', '1001', __ROWID_OFFSET__+500001, 'Buildings at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500012, 'SFRS-BASE', 'ASSET', '1002', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - Buildings', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500013, 'SFRS-BASE', 'ASSET', '1010', __ROWID_OFFSET__+500001, 'Plant and machinery at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500014, 'SFRS-BASE', 'ASSET', '1011', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - Plant & machinery', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500015, 'SFRS-BASE', 'ASSET', '1020', __ROWID_OFFSET__+500001, 'Office equipment at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500016, 'SFRS-BASE', 'ASSET', '1021', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - Office equipment', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500017, 'SFRS-BASE', 'ASSET', '1030', __ROWID_OFFSET__+500001, 'Computer equipment at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500018, 'SFRS-BASE', 'ASSET', '1031', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - Computer equipment', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500019, 'SFRS-BASE', 'ASSET', '1040', __ROWID_OFFSET__+500001, 'Motor vehicles at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500020, 'SFRS-BASE', 'ASSET', '1041', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - Motor vehicles', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500021, 'SFRS-BASE', 'ASSET', '1050', __ROWID_OFFSET__+500001, 'Furniture and fittings at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500022, 'SFRS-BASE', 'ASSET', '1051', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - Furniture & fittings', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500023, 'SFRS-BASE', 'ASSET', '1060', __ROWID_OFFSET__+500001, 'Leasehold improvements at cost', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500024, 'SFRS-BASE', 'ASSET', '1061', __ROWID_OFFSET__+500001, 'Less: Accumulated amortisation - Leasehold improvements', 1);

-- 1100-1199 Intangible Assets
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500030, 'SFRS-BASE', 'ASSET', '1100', __ROWID_OFFSET__+500001, 'Goodwill', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500031, 'SFRS-BASE', 'ASSET', '1110', __ROWID_OFFSET__+500001, 'Patents and trademarks', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500032, 'SFRS-BASE', 'ASSET', '1111', __ROWID_OFFSET__+500001, 'Less: Accumulated amortisation - Patents & trademarks', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500033, 'SFRS-BASE', 'ASSET', '1120', __ROWID_OFFSET__+500001, 'Software and licences', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500034, 'SFRS-BASE', 'ASSET', '1121', __ROWID_OFFSET__+500001, 'Less: Accumulated amortisation - Software & licences', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500035, 'SFRS-BASE', 'ASSET', '1130', __ROWID_OFFSET__+500001, 'Development costs (capitalised)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500036, 'SFRS-BASE', 'ASSET', '1190', __ROWID_OFFSET__+500001, 'Other intangible assets', 1);

-- 1200-1299 Right-of-use assets (FRS 116)
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500040, 'SFRS-BASE', 'ASSET', '1200', __ROWID_OFFSET__+500001, 'Right-of-use asset - Buildings', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500041, 'SFRS-BASE', 'ASSET', '1201', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - ROU Buildings', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500042, 'SFRS-BASE', 'ASSET', '1210', __ROWID_OFFSET__+500001, 'Right-of-use asset - Motor vehicles', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500043, 'SFRS-BASE', 'ASSET', '1211', __ROWID_OFFSET__+500001, 'Less: Accumulated depreciation - ROU Motor vehicles', 1);

-- 1300-1399 Long-term Investments
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500050, 'SFRS-BASE', 'ASSET', '1300', __ROWID_OFFSET__+500001, 'Investment in subsidiaries', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500051, 'SFRS-BASE', 'ASSET', '1310', __ROWID_OFFSET__+500001, 'Investment in associates', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500052, 'SFRS-BASE', 'ASSET', '1320', __ROWID_OFFSET__+500001, 'Investment in joint ventures', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500053, 'SFRS-BASE', 'ASSET', '1330', __ROWID_OFFSET__+500001, 'Available-for-sale financial assets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500054, 'SFRS-BASE', 'ASSET', '1340', __ROWID_OFFSET__+500001, 'Held-to-maturity financial assets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500055, 'SFRS-BASE', 'ASSET', '1350', __ROWID_OFFSET__+500001, 'Loan receivable (long-term)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500056, 'SFRS-BASE', 'ASSET', '1360', __ROWID_OFFSET__+500001, 'Deferred tax asset', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500057, 'SFRS-BASE', 'ASSET', '1370', __ROWID_OFFSET__+500001, 'Biological assets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500058, 'SFRS-BASE', 'ASSET', '1390', __ROWID_OFFSET__+500001, 'Other non-current assets', 1);

-- 1500-1599 Inventory
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500060, 'SFRS-BASE', 'ASSET', '1500', __ROWID_OFFSET__+500002, 'Raw materials', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500061, 'SFRS-BASE', 'ASSET', '1510', __ROWID_OFFSET__+500002, 'Work-in-progress', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500062, 'SFRS-BASE', 'ASSET', '1520', __ROWID_OFFSET__+500002, 'Finished goods', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500063, 'SFRS-BASE', 'ASSET', '1530', __ROWID_OFFSET__+500002, 'Trading inventory', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500064, 'SFRS-BASE', 'ASSET', '1540', __ROWID_OFFSET__+500002, 'Goods in transit', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500065, 'SFRS-BASE', 'ASSET', '1590', __ROWID_OFFSET__+500002, 'Less: Provision for inventory obsolescence', 1);

-- 1600-1699 Trade Receivables
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500070, 'SFRS-BASE', 'ASSET', '1600', __ROWID_OFFSET__+500002, 'Trade receivables', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500071, 'SFRS-BASE', 'ASSET', '1601', __ROWID_OFFSET__+500002, 'Less: Provision for doubtful debts', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500072, 'SFRS-BASE', 'ASSET', '1610', __ROWID_OFFSET__+500002, 'Trade receivables (related parties)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500073, 'SFRS-BASE', 'ASSET', '1620', __ROWID_OFFSET__+500002, 'Unbilled receivables (contract assets)', 1);

-- 1700-1799 Other Receivables
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500080, 'SFRS-BASE', 'ASSET', '1700', __ROWID_OFFSET__+500002, 'Other receivables', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500081, 'SFRS-BASE', 'ASSET', '1710', __ROWID_OFFSET__+500002, 'Staff loans (current)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500082, 'SFRS-BASE', 'ASSET', '1720', __ROWID_OFFSET__+500002, 'Deposits paid', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500083, 'SFRS-BASE', 'ASSET', '1730', __ROWID_OFFSET__+500002, 'GST input tax recoverable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500084, 'SFRS-BASE', 'ASSET', '1740', __ROWID_OFFSET__+500002, 'Income tax recoverable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500085, 'SFRS-BASE', 'ASSET', '1750', __ROWID_OFFSET__+500002, 'Insurance claims receivable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500086, 'SFRS-BASE', 'ASSET', '1790', __ROWID_OFFSET__+500002, 'Other receivables (sundry)', 1);

-- 1800-1899 Cash & Bank
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500090, 'SFRS-BASE', 'ASSET', '1800', __ROWID_OFFSET__+500002, 'Cash on hand', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500091, 'SFRS-BASE', 'ASSET', '1810', __ROWID_OFFSET__+500002, 'Bank account - SGD operating', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500092, 'SFRS-BASE', 'ASSET', '1811', __ROWID_OFFSET__+500002, 'Bank account - SGD payroll', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500093, 'SFRS-BASE', 'ASSET', '1812', __ROWID_OFFSET__+500002, 'Bank account - SGD tax reserve', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500094, 'SFRS-BASE', 'ASSET', '1820', __ROWID_OFFSET__+500002, 'Bank account - USD', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500095, 'SFRS-BASE', 'ASSET', '1830', __ROWID_OFFSET__+500002, 'Bank account - other currencies', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500096, 'SFRS-BASE', 'ASSET', '1840', __ROWID_OFFSET__+500002, 'Bank deposits (short-term, 3-12 months)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500097, 'SFRS-BASE', 'ASSET', '1850', __ROWID_OFFSET__+500002, 'PayNow / GrabPay / e-Wallets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500098, 'SFRS-BASE', 'ASSET', '1890', __ROWID_OFFSET__+500002, 'Petty cash', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500099, 'SFRS-BASE', 'ASSET', '1899', __ROWID_OFFSET__+500002, 'Cash and cash equivalents (clearing account)', 1);

-- 1900-1999 Prepayments
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500100, 'SFRS-BASE', 'ASSET', '1900', __ROWID_OFFSET__+500002, 'Prepayments - rent', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500101, 'SFRS-BASE', 'ASSET', '1910', __ROWID_OFFSET__+500002, 'Prepayments - insurance', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500102, 'SFRS-BASE', 'ASSET', '1920', __ROWID_OFFSET__+500002, 'Prepayments - subscriptions', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500103, 'SFRS-BASE', 'ASSET', '1930', __ROWID_OFFSET__+500002, 'Prepayments - other', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500104, 'SFRS-BASE', 'ASSET', '1940', __ROWID_OFFSET__+500002, 'Accrued income', 1);

-- 2000-2999 LIABILITIES root
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500200, 'SFRS-BASE', 'LIABILITY', '2', 0, 'LIABILITIES', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500201, 'SFRS-BASE', 'LIABILITY', '20', __ROWID_OFFSET__+500200, 'Current Liabilities', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500202, 'SFRS-BASE', 'LIABILITY', '25', __ROWID_OFFSET__+500200, 'Non-current Liabilities', 1);

-- 2000-2099 Trade Payables
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500210, 'SFRS-BASE', 'LIABILITY', '2000', __ROWID_OFFSET__+500201, 'Trade payables', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500211, 'SFRS-BASE', 'LIABILITY', '2010', __ROWID_OFFSET__+500201, 'Trade payables (related parties)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500212, 'SFRS-BASE', 'LIABILITY', '2020', __ROWID_OFFSET__+500201, 'Bills payable', 1);

-- 2100-2199 Accruals
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500220, 'SFRS-BASE', 'LIABILITY', '2100', __ROWID_OFFSET__+500201, 'Accruals - operating expenses', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500221, 'SFRS-BASE', 'LIABILITY', '2110', __ROWID_OFFSET__+500201, 'Accruals - audit & accountancy fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500222, 'SFRS-BASE', 'LIABILITY', '2120', __ROWID_OFFSET__+500201, 'Accruals - bonuses', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500223, 'SFRS-BASE', 'LIABILITY', '2130', __ROWID_OFFSET__+500201, 'Accruals - interest', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500224, 'SFRS-BASE', 'LIABILITY', '2190', __ROWID_OFFSET__+500201, 'Other accruals', 1);

-- 2200-2299 GST Liabilities (SG GST 9% effective 2024-01-01)
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500230, 'SFRS-BASE', 'LIABILITY', '2200', __ROWID_OFFSET__+500201, 'GST output tax (9% standard-rated supplies)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500231, 'SFRS-BASE', 'LIABILITY', '2210', __ROWID_OFFSET__+500201, 'GST output tax (zero-rated supplies)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500232, 'SFRS-BASE', 'LIABILITY', '2220', __ROWID_OFFSET__+500201, 'GST output tax (exempt supplies)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500233, 'SFRS-BASE', 'LIABILITY', '2230', __ROWID_OFFSET__+500201, 'GST suspense / control account', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500234, 'SFRS-BASE', 'LIABILITY', '2240', __ROWID_OFFSET__+500201, 'GST payable to IRAS (net)', 1);

-- 2300-2399 Short-term Borrowings
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500240, 'SFRS-BASE', 'LIABILITY', '2300', __ROWID_OFFSET__+500201, 'Short-term bank loan (current portion)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500241, 'SFRS-BASE', 'LIABILITY', '2310', __ROWID_OFFSET__+500201, 'Hire purchase (current portion)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500242, 'SFRS-BASE', 'LIABILITY', '2320', __ROWID_OFFSET__+500201, 'Credit card liability', 1);

-- 2400-2499 Other Current Liabilities
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500250, 'SFRS-BASE', 'LIABILITY', '2400', __ROWID_OFFSET__+500201, 'Income tax payable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500251, 'SFRS-BASE', 'LIABILITY', '2410', __ROWID_OFFSET__+500201, 'Wages and salaries payable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500252, 'SFRS-BASE', 'LIABILITY', '2411', __ROWID_OFFSET__+500201, 'CPF contribution payable (employee)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500253, 'SFRS-BASE', 'LIABILITY', '2412', __ROWID_OFFSET__+500201, 'CPF contribution payable (employer)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500254, 'SFRS-BASE', 'LIABILITY', '2413', __ROWID_OFFSET__+500201, 'SDF / SDL / CDAC / MBMF payable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500255, 'SFRS-BASE', 'LIABILITY', '2420', __ROWID_OFFSET__+500201, 'Director fees payable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500256, 'SFRS-BASE', 'LIABILITY', '2430', __ROWID_OFFSET__+500201, 'Director loan account', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500257, 'SFRS-BASE', 'LIABILITY', '2440', __ROWID_OFFSET__+500201, 'Unearned revenue (deferred income)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500258, 'SFRS-BASE', 'LIABILITY', '2450', __ROWID_OFFSET__+500201, 'Deposits received', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500259, 'SFRS-BASE', 'LIABILITY', '2490', __ROWID_OFFSET__+500201, 'Other current liabilities (sundry)', 1);

-- 2500-2599 Long-term Loans
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500260, 'SFRS-BASE', 'LIABILITY', '2500', __ROWID_OFFSET__+500202, 'Long-term bank loan', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500261, 'SFRS-BASE', 'LIABILITY', '2510', __ROWID_OFFSET__+500202, 'Hire purchase (non-current)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500262, 'SFRS-BASE', 'LIABILITY', '2520', __ROWID_OFFSET__+500202, 'Bonds payable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500263, 'SFRS-BASE', 'LIABILITY', '2530', __ROWID_OFFSET__+500202, 'Convertible loans', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500264, 'SFRS-BASE', 'LIABILITY', '2540', __ROWID_OFFSET__+500202, 'Loan from shareholder / director', 1);

-- 2600-2699 Other Non-current Liabilities
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500270, 'SFRS-BASE', 'LIABILITY', '2600', __ROWID_OFFSET__+500202, 'Other non-current liabilities', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500271, 'SFRS-BASE', 'LIABILITY', '2610', __ROWID_OFFSET__+500202, 'Retention sum payable (long-term)', 1);

-- 2700-2799 Lease Liabilities (FRS 116)
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500280, 'SFRS-BASE', 'LIABILITY', '2700', __ROWID_OFFSET__+500202, 'Lease liability - Buildings (non-current)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500281, 'SFRS-BASE', 'LIABILITY', '2701', __ROWID_OFFSET__+500201, 'Lease liability - Buildings (current portion)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500282, 'SFRS-BASE', 'LIABILITY', '2710', __ROWID_OFFSET__+500202, 'Lease liability - Motor vehicles (non-current)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500283, 'SFRS-BASE', 'LIABILITY', '2711', __ROWID_OFFSET__+500201, 'Lease liability - Motor vehicles (current portion)', 1);

-- 2800-2899 Provisions
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500290, 'SFRS-BASE', 'LIABILITY', '2800', __ROWID_OFFSET__+500202, 'Provision for warranties', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500291, 'SFRS-BASE', 'LIABILITY', '2810', __ROWID_OFFSET__+500202, 'Provision for restoration costs', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500292, 'SFRS-BASE', 'LIABILITY', '2820', __ROWID_OFFSET__+500202, 'Provision for legal claims', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500293, 'SFRS-BASE', 'LIABILITY', '2830', __ROWID_OFFSET__+500202, 'Provision for onerous contracts', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500294, 'SFRS-BASE', 'LIABILITY', '2890', __ROWID_OFFSET__+500202, 'Other provisions', 1);

-- 2900-2999 Deferred Tax Liabilities
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500295, 'SFRS-BASE', 'LIABILITY', '2900', __ROWID_OFFSET__+500202, 'Deferred tax liability', 1);

-- 3000-3999 EQUITY root
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500300, 'SFRS-BASE', 'EQUITY', '3', 0, 'EQUITY', 1);

-- 3000-3099 Share Capital
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500310, 'SFRS-BASE', 'EQUITY', '3000', __ROWID_OFFSET__+500300, 'Issued share capital (ordinary shares)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500311, 'SFRS-BASE', 'EQUITY', '3010', __ROWID_OFFSET__+500300, 'Issued share capital (preference shares)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500312, 'SFRS-BASE', 'EQUITY', '3020', __ROWID_OFFSET__+500300, 'Share premium', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500313, 'SFRS-BASE', 'EQUITY', '3030', __ROWID_OFFSET__+500300, 'Treasury shares', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500314, 'SFRS-BASE', 'EQUITY', '3040', __ROWID_OFFSET__+500300, 'Unissued share capital (control account)', 1);

-- 3100-3199 Retained Earnings
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500320, 'SFRS-BASE', 'EQUITY', '3100', __ROWID_OFFSET__+500300, 'Retained earnings (opening)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500321, 'SFRS-BASE', 'EQUITY', '3110', __ROWID_OFFSET__+500300, 'Current year profit/loss', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500322, 'SFRS-BASE', 'EQUITY', '3120', __ROWID_OFFSET__+500300, 'Prior year adjustment', 1);

-- 3200-3299 Reserves
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500330, 'SFRS-BASE', 'EQUITY', '3200', __ROWID_OFFSET__+500300, 'Revaluation reserve (PPE)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500331, 'SFRS-BASE', 'EQUITY', '3210', __ROWID_OFFSET__+500300, 'Fair value reserve (financial assets)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500332, 'SFRS-BASE', 'EQUITY', '3220', __ROWID_OFFSET__+500300, 'Foreign currency translation reserve', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500333, 'SFRS-BASE', 'EQUITY', '3230', __ROWID_OFFSET__+500300, 'General reserve', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500334, 'SFRS-BASE', 'EQUITY', '3290', __ROWID_OFFSET__+500300, 'Other reserves', 1);

-- 3300-3399 Dividends
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500340, 'SFRS-BASE', 'EQUITY', '3300', __ROWID_OFFSET__+500300, 'Dividends declared (interim)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500341, 'SFRS-BASE', 'EQUITY', '3310', __ROWID_OFFSET__+500300, 'Dividends declared (final)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500342, 'SFRS-BASE', 'EQUITY', '3320', __ROWID_OFFSET__+500300, 'Dividend payable', 1);

-- 4000-4999 REVENUE root
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500400, 'SFRS-BASE', 'INCOME', '4', 0, 'REVENUE', 1);

-- 4000-4099 Sales of Goods
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500410, 'SFRS-BASE', 'INCOME', '4000', __ROWID_OFFSET__+500400, 'Sales - goods (standard-rated 9% GST)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500411, 'SFRS-BASE', 'INCOME', '4001', __ROWID_OFFSET__+500400, 'Sales - goods (zero-rated, e.g. exports)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500412, 'SFRS-BASE', 'INCOME', '4002', __ROWID_OFFSET__+500400, 'Sales - goods (exempt supplies)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500413, 'SFRS-BASE', 'INCOME', '4010', __ROWID_OFFSET__+500400, 'Sales returns and allowances', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500414, 'SFRS-BASE', 'INCOME', '4020', __ROWID_OFFSET__+500400, 'Sales discounts', 1);

-- 4100-4199 Service Revenue
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500420, 'SFRS-BASE', 'INCOME', '4100', __ROWID_OFFSET__+500400, 'Service revenue (standard-rated 9% GST)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500421, 'SFRS-BASE', 'INCOME', '4101', __ROWID_OFFSET__+500400, 'Service revenue (zero-rated, e.g. international services)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500422, 'SFRS-BASE', 'INCOME', '4102', __ROWID_OFFSET__+500400, 'Service revenue (exempt, e.g. financial services)', 1);

-- 4200-4299 Other Operating Revenue
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500430, 'SFRS-BASE', 'INCOME', '4200', __ROWID_OFFSET__+500400, 'Commission income', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500431, 'SFRS-BASE', 'INCOME', '4210', __ROWID_OFFSET__+500400, 'Royalty income', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500432, 'SFRS-BASE', 'INCOME', '4220', __ROWID_OFFSET__+500400, 'Rental income', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500433, 'SFRS-BASE', 'INCOME', '4230', __ROWID_OFFSET__+500400, 'Management fee income', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500434, 'SFRS-BASE', 'INCOME', '4240', __ROWID_OFFSET__+500400, 'Government grant income', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500435, 'SFRS-BASE', 'INCOME', '4250', __ROWID_OFFSET__+500400, 'Job credit / wage subsidy (e.g. WCS, JSS)', 1);

-- 4900-4999 Other Income
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500490, 'SFRS-BASE', 'INCOME', '4900', __ROWID_OFFSET__+500400, 'Other operating income (sundry)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500491, 'SFRS-BASE', 'INCOME', '4910', __ROWID_OFFSET__+500400, 'Gain on disposal of fixed assets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500492, 'SFRS-BASE', 'INCOME', '4920', __ROWID_OFFSET__+500400, 'Bad debt recovered', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500493, 'SFRS-BASE', 'INCOME', '4930', __ROWID_OFFSET__+500400, 'Foreign exchange gain (operating)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500494, 'SFRS-BASE', 'INCOME', '4990', __ROWID_OFFSET__+500400, 'Other non-operating income', 1);

-- 5000-5999 COST OF SALES
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500500, 'SFRS-BASE', 'EXPENSE', '5', 0, 'COST OF SALES', 1);

-- 5000-5099 Cost of Goods Sold
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500510, 'SFRS-BASE', 'EXPENSE', '5000', __ROWID_OFFSET__+500500, 'Cost of goods sold - purchases', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500511, 'SFRS-BASE', 'EXPENSE', '5010', __ROWID_OFFSET__+500500, 'Purchases returns and allowances', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500512, 'SFRS-BASE', 'EXPENSE', '5020', __ROWID_OFFSET__+500500, 'Purchase discounts', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500513, 'SFRS-BASE', 'EXPENSE', '5030', __ROWID_OFFSET__+500500, 'Freight and carriage inwards', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500514, 'SFRS-BASE', 'EXPENSE', '5040', __ROWID_OFFSET__+500500, 'Customs duty and excise', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500515, 'SFRS-BASE', 'EXPENSE', '5050', __ROWID_OFFSET__+500500, 'Inventory write-down / obsolescence', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500516, 'SFRS-BASE', 'EXPENSE', '5090', __ROWID_OFFSET__+500500, 'Other cost of sales', 1);

-- 5100-5199 Direct labour / materials
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500520, 'SFRS-BASE', 'EXPENSE', '5100', __ROWID_OFFSET__+500500, 'Direct labour', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500521, 'SFRS-BASE', 'EXPENSE', '5110', __ROWID_OFFSET__+500500, 'Direct materials', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500522, 'SFRS-BASE', 'EXPENSE', '5120', __ROWID_OFFSET__+500500, 'Subcontractor costs', 1);

-- 6000-7999 OPERATING EXPENSES
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500600, 'SFRS-BASE', 'EXPENSE', '6', 0, 'OPERATING EXPENSES', 1);

-- 6000-6099 Salaries & Benefits
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500610, 'SFRS-BASE', 'EXPENSE', '6000', __ROWID_OFFSET__+500600, 'Salaries and wages', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500611, 'SFRS-BASE', 'EXPENSE', '6010', __ROWID_OFFSET__+500600, 'Directors fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500612, 'SFRS-BASE', 'EXPENSE', '6020', __ROWID_OFFSET__+500600, 'Bonus', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500613, 'SFRS-BASE', 'EXPENSE', '6030', __ROWID_OFFSET__+500600, 'CPF contribution (employer)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500614, 'SFRS-BASE', 'EXPENSE', '6040', __ROWID_OFFSET__+500600, 'SDF / SDL / CDAC / MBMF (employer)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500615, 'SFRS-BASE', 'EXPENSE', '6050', __ROWID_OFFSET__+500600, 'Foreign worker levy', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500616, 'SFRS-BASE', 'EXPENSE', '6060', __ROWID_OFFSET__+500600, 'Staff training and welfare', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500617, 'SFRS-BASE', 'EXPENSE', '6070', __ROWID_OFFSET__+500600, 'Staff recruitment', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500618, 'SFRS-BASE', 'EXPENSE', '6080', __ROWID_OFFSET__+500600, 'Staff uniforms and PPE', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500619, 'SFRS-BASE', 'EXPENSE', '6090', __ROWID_OFFSET__+500600, 'Other staff costs', 1);

-- 6100-6199 Rent & Utilities
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500620, 'SFRS-BASE', 'EXPENSE', '6100', __ROWID_OFFSET__+500600, 'Rent expense - office', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500621, 'SFRS-BASE', 'EXPENSE', '6110', __ROWID_OFFSET__+500600, 'Rent expense - warehouse', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500622, 'SFRS-BASE', 'EXPENSE', '6120', __ROWID_OFFSET__+500600, 'Condominium / MCST management fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500623, 'SFRS-BASE', 'EXPENSE', '6130', __ROWID_OFFSET__+500600, 'Property tax', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500624, 'SFRS-BASE', 'EXPENSE', '6140', __ROWID_OFFSET__+500600, 'Utilities - electricity', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500625, 'SFRS-BASE', 'EXPENSE', '6150', __ROWID_OFFSET__+500600, 'Utilities - water', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500626, 'SFRS-BASE', 'EXPENSE', '6160', __ROWID_OFFSET__+500600, 'Utilities - gas', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500627, 'SFRS-BASE', 'EXPENSE', '6170', __ROWID_OFFSET__+500600, 'Telecommunications and internet', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500628, 'SFRS-BASE', 'EXPENSE', '6180', __ROWID_OFFSET__+500600, 'Short-term lease payments (FRS 16 exemption)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500629, 'SFRS-BASE', 'EXPENSE', '6190', __ROWID_OFFSET__+500600, 'Low-value lease payments (FRS 16 exemption)', 1);

-- 6200-6299 Depreciation & Amortisation
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500630, 'SFRS-BASE', 'EXPENSE', '6200', __ROWID_OFFSET__+500600, 'Depreciation - Buildings', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500631, 'SFRS-BASE', 'EXPENSE', '6201', __ROWID_OFFSET__+500600, 'Depreciation - Plant and machinery', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500632, 'SFRS-BASE', 'EXPENSE', '6202', __ROWID_OFFSET__+500600, 'Depreciation - Office equipment', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500633, 'SFRS-BASE', 'EXPENSE', '6203', __ROWID_OFFSET__+500600, 'Depreciation - Computer equipment', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500634, 'SFRS-BASE', 'EXPENSE', '6204', __ROWID_OFFSET__+500600, 'Depreciation - Motor vehicles', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500635, 'SFRS-BASE', 'EXPENSE', '6205', __ROWID_OFFSET__+500600, 'Depreciation - Furniture and fittings', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500636, 'SFRS-BASE', 'EXPENSE', '6206', __ROWID_OFFSET__+500600, 'Amortisation - Leasehold improvements', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500637, 'SFRS-BASE', 'EXPENSE', '6207', __ROWID_OFFSET__+500600, 'Amortisation - Intangible assets (patents, software)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500638, 'SFRS-BASE', 'EXPENSE', '6208', __ROWID_OFFSET__+500600, 'Depreciation - Right-of-use assets (FRS 116)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500639, 'SFRS-BASE', 'EXPENSE', '6290', __ROWID_OFFSET__+500600, 'Impairment of fixed assets', 1);

-- 6300-6399 Selling Expenses
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500640, 'SFRS-BASE', 'EXPENSE', '6300', __ROWID_OFFSET__+500600, 'Advertising and marketing', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500641, 'SFRS-BASE', 'EXPENSE', '6310', __ROWID_OFFSET__+500600, 'Sales commissions', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500642, 'SFRS-BASE', 'EXPENSE', '6320', __ROWID_OFFSET__+500600, 'Trade shows and exhibitions', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500643, 'SFRS-BASE', 'EXPENSE', '6330', __ROWID_OFFSET__+500600, 'Distribution and freight outwards', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500644, 'SFRS-BASE', 'EXPENSE', '6340', __ROWID_OFFSET__+500600, 'Packaging materials', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500645, 'SFRS-BASE', 'EXPENSE', '6350', __ROWID_OFFSET__+500600, 'Warranty and after-sales service', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500646, 'SFRS-BASE', 'EXPENSE', '6360', __ROWID_OFFSET__+500600, 'Bad debts written off', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500647, 'SFRS-BASE', 'EXPENSE', '6370', __ROWID_OFFSET__+500600, 'Increase in provision for doubtful debts', 1);

-- 6400-6499 Administrative Expenses
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500650, 'SFRS-BASE', 'EXPENSE', '6400', __ROWID_OFFSET__+500600, 'Audit and accountancy fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500651, 'SFRS-BASE', 'EXPENSE', '6410', __ROWID_OFFSET__+500600, 'Legal fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500652, 'SFRS-BASE', 'EXPENSE', '6420', __ROWID_OFFSET__+500600, 'Tax fees (corporate tax, GST filing)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500653, 'SFRS-BASE', 'EXPENSE', '6430', __ROWID_OFFSET__+500600, 'Consultancy fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500654, 'SFRS-BASE', 'EXPENSE', '6440', __ROWID_OFFSET__+500600, 'Bank charges', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500655, 'SFRS-BASE', 'EXPENSE', '6450', __ROWID_OFFSET__+500600, 'Credit card merchant fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500656, 'SFRS-BASE', 'EXPENSE', '6460', __ROWID_OFFSET__+500600, 'Insurance', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500657, 'SFRS-BASE', 'EXPENSE', '6470', __ROWID_OFFSET__+500600, 'Office supplies and stationery', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500658, 'SFRS-BASE', 'EXPENSE', '6480', __ROWID_OFFSET__+500600, 'Software subscriptions and IT', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500659, 'SFRS-BASE', 'EXPENSE', '6490', __ROWID_OFFSET__+500600, 'Other administrative expenses', 1);

-- 6500-6599 Finance Costs
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500660, 'SFRS-BASE', 'EXPENSE', '6500', __ROWID_OFFSET__+500600, 'Motor vehicle expenses', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500661, 'SFRS-BASE', 'EXPENSE', '6510', __ROWID_OFFSET__+500600, 'Travel - domestic', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500662, 'SFRS-BASE', 'EXPENSE', '6520', __ROWID_OFFSET__+500600, 'Travel - international', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500663, 'SFRS-BASE', 'EXPENSE', '6530', __ROWID_OFFSET__+500600, 'Entertainment (50% deductible)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500664, 'SFRS-BASE', 'EXPENSE', '6540', __ROWID_OFFSET__+500600, 'Donations (charitable, 250% tax deduction)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500665, 'SFRS-BASE', 'EXPENSE', '6550', __ROWID_OFFSET__+500600, 'Subscriptions and professional fees', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500666, 'SFRS-BASE', 'EXPENSE', '6560', __ROWID_OFFSET__+500600, 'Repairs and maintenance', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500667, 'SFRS-BASE', 'EXPENSE', '6570', __ROWID_OFFSET__+500600, 'Postage and courier', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500668, 'SFRS-BASE', 'EXPENSE', '6580', __ROWID_OFFSET__+500600, 'Cleaning and security', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500669, 'SFRS-BASE', 'EXPENSE', '6590', __ROWID_OFFSET__+500600, 'Other operating expenses', 1);

-- 7000-7999 NON-OPERATING ITEMS
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500700, 'SFRS-BASE', 'INCOME', '7', 0, 'NON-OPERATING ITEMS', 1);

-- 7000-7099 Interest Income / Finance Income
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500710, 'SFRS-BASE', 'INCOME', '7000', __ROWID_OFFSET__+500700, 'Interest income - bank deposits', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500711, 'SFRS-BASE', 'INCOME', '7010', __ROWID_OFFSET__+500700, 'Interest income - loans receivable', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500712, 'SFRS-BASE', 'INCOME', '7020', __ROWID_OFFSET__+500700, 'Dividend income', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500713, 'SFRS-BASE', 'INCOME', '7090', __ROWID_OFFSET__+500700, 'Other finance income', 1);

-- 7100-7199 Interest Expense / Finance Costs
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500720, 'SFRS-BASE', 'EXPENSE', '7100', __ROWID_OFFSET__+500700, 'Interest expense - bank loans', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500721, 'SFRS-BASE', 'EXPENSE', '7110', __ROWID_OFFSET__+500700, 'Interest expense - hire purchase', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500722, 'SFRS-BASE', 'EXPENSE', '7120', __ROWID_OFFSET__+500700, 'Interest expense - bonds', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500723, 'SFRS-BASE', 'EXPENSE', '7130', __ROWID_OFFSET__+500700, 'Interest expense - lease liability (FRS 116)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500724, 'SFRS-BASE', 'EXPENSE', '7140', __ROWID_OFFSET__+500700, 'Bank overdraft interest', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500725, 'SFRS-BASE', 'EXPENSE', '7190', __ROWID_OFFSET__+500700, 'Other finance costs', 1);

-- 7500-7599 Income Tax Expense
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500750, 'SFRS-BASE', 'EXPENSE', '7500', __ROWID_OFFSET__+500700, 'Current income tax expense (CIT 17%)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500751, 'SFRS-BASE', 'EXPENSE', '7510', __ROWID_OFFSET__+500700, 'Deferred income tax expense', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500752, 'SFRS-BASE', 'EXPENSE', '7520', __ROWID_OFFSET__+500700, 'Withholding tax (foreign dividends/interest)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500753, 'SFRS-BASE', 'EXPENSE', '7530', __ROWID_OFFSET__+500700, 'Under/(over) provision of tax in prior year', 1);

-- 7600-7699 Foreign Exchange
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500760, 'SFRS-BASE', 'EXPENSE', '7600', __ROWID_OFFSET__+500700, 'Realised foreign exchange loss', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500761, 'SFRS-BASE', 'INCOME', '7610', __ROWID_OFFSET__+500700, 'Realised foreign exchange gain', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500762, 'SFRS-BASE', 'EXPENSE', '7620', __ROWID_OFFSET__+500700, 'Unrealised foreign exchange loss', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500763, 'SFRS-BASE', 'INCOME', '7630', __ROWID_OFFSET__+500700, 'Unrealised foreign exchange gain', 1);

-- 7800-7899 Extraordinary / Non-recurring
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500780, 'SFRS-BASE', 'EXPENSE', '7800', __ROWID_OFFSET__+500700, 'Loss on disposal of fixed assets', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500781, 'SFRS-BASE', 'EXPENSE', '7810', __ROWID_OFFSET__+500700, 'Restructuring costs', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500782, 'SFRS-BASE', 'EXPENSE', '7820', __ROWID_OFFSET__+500700, 'Litigation settlement', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500789, 'SFRS-BASE', 'EXPENSE', '7890', __ROWID_OFFSET__+500700, 'Other non-recurring expenses', 1);

-- 8000-8999 Period closure accounts (clearing/closing)
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500800, 'SFRS-BASE', 'EXPENSE', '8000', 0, 'Period closure - Net profit (clearing)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500801, 'SFRS-BASE', 'INCOME', '8100', 0, 'Period closure - Net loss (clearing)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500802, 'SFRS-BASE', 'EXPENSE', '8200', 0, 'Rounding differences (clearing)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500803, 'SFRS-BASE', 'LIABILITY', '8500', __ROWID_OFFSET__+500200, 'Suspense account (unallocated entries)', 1);
INSERT INTO llx_accounting_account (entity, rowid, fk_pcg_version, pcg_type, account_number, account_parent, label, active) VALUES (__ENTITY__, __ROWID_OFFSET__+500804, 'SFRS-BASE', 'LIABILITY', '8510', __ROWID_OFFSET__+500200, 'Historical opening balance adjustment', 1);
