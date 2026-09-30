-- ============================================================================
-- SFRS Report Categories — defines BS / P&L / Cash Flow rows for Singapore
-- ============================================================================
-- Uses fk_report = 2 (Balance Sheet), 3 (Profit & Loss), 4 (Cash Flow).
-- Country = 29 (Singapore). Entity = __ENTITY__ (resolved at install time).
-- sens: 0 = credit - debit (revenue / liability), 1 = debit - credit (asset / expense)
-- category_type: 0 = single account range, 1 = formula (sums other category codes)
-- position: sort order within the report
-- ============================================================================

-- ============================================================================
-- BALANCE SHEET (fk_report = 2)
-- ============================================================================

-- ASSETS — section header
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_HEADER_ASSETS', '═══ ASSETS ═══', '', 0, 2, '', 100, 29, 1);

-- Non-current Assets
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCA_PPE',         'Property, plant and equipment',  '1000-1099', 1, 0, '', 110, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCA_INTANG',      'Intangible assets',              '1100-1199', 1, 0, '', 120, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCA_ROU',         'Right-of-use assets (FRS 116)',  '1200-1299', 1, 0, '', 130, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCA_INVEST',      'Long-term investments',          '1300-1399', 1, 0, '', 140, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCA_OTHER',       'Other non-current assets',        '1390-1499', 1, 0, '', 150, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCA_TOTAL',       'Total non-current assets',        '',          1, 1, 'NCA_PPE+NCA_INTANG+NCA_ROU+NCA_INVEST+NCA_OTHER', 199, 29, 1);

-- Current Assets
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CA_INVENTORY',    'Inventories',                     '1500-1599', 1, 0, '', 210, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CA_TRADE_RECV',   'Trade receivables',               '1600-1699', 1, 0, '', 220, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CA_OTHER_RECV',   'Other receivables',               '1700-1799', 1, 0, '', 230, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CA_CASH',         'Cash and cash equivalents',       '1800-1899', 1, 0, '', 240, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CA_PREPAY',       'Prepayments and accrued income',  '1900-1999', 1, 0, '', 250, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CA_TOTAL',        'Total current assets',            '',          1, 1, 'CA_INVENTORY+CA_TRADE_RECV+CA_OTHER_RECV+CA_CASH+CA_PREPAY', 299, 29, 1);

INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_TOTAL_ASSETS', 'TOTAL ASSETS',                    '',          1, 1, 'NCA_TOTAL+CA_TOTAL', 399, 29, 1);

-- LIABILITIES — section header
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_HEADER_LIAB',  '═══ LIABILITIES ═══', '', 0, 2, '', 400, 29, 1);

-- Current Liabilities
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_TRADE_PAY',    'Trade payables',                  '2000-2099', 0, 0, '', 410, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_ACCRUALS',     'Accruals',                        '2100-2199', 0, 0, '', 420, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_GST',          'GST liabilities',                 '2200-2299', 0, 0, '', 430, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_BORROWINGS',   'Short-term borrowings',           '2300-2399', 0, 0, '', 440, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_OTHER',        'Other current liabilities',       '2400-2499', 0, 0, '', 450, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_LEASE_CUR',    'Lease liabilities (current portion)', '2701,2711', 0, 0, '', 460, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'CL_TOTAL',        'Total current liabilities',       '',          0, 1, 'CL_TRADE_PAY+CL_ACCRUALS+CL_GST+CL_BORROWINGS+CL_OTHER+CL_LEASE_CUR', 499, 29, 1);

-- Non-current Liabilities
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCL_LT_LOANS',    'Long-term loans',                 '2500-2599', 0, 0, '', 510, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCL_OTHER',       'Other non-current liabilities',    '2600-2699', 0, 0, '', 520, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCL_LEASE',       'Lease liabilities (non-current)', '2700-2799', 0, 0, '', 530, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCL_PROVISIONS',  'Provisions',                      '2800-2899', 0, 0, '', 540, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCL_DEFERRED_TAX','Deferred tax liabilities',        '2900-2999', 0, 0, '', 550, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'NCL_TOTAL',       'Total non-current liabilities',   '',          0, 1, 'NCL_LT_LOANS+NCL_OTHER+NCL_LEASE+NCL_PROVISIONS+NCL_DEFERRED_TAX', 599, 29, 1);

INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_TOTAL_LIAB',   'TOTAL LIABILITIES',               '',          0, 1, 'CL_TOTAL+NCL_TOTAL', 699, 29, 1);

-- EQUITY
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_HEADER_EQ',    '═══ EQUITY ═══', '', 0, 2, '', 700, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'EQ_SHARE_CAP',    'Share capital',                   '3000-3099', 0, 0, '', 710, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'EQ_RETAINED',     'Retained earnings',               '3100-3199', 0, 0, '', 720, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'EQ_RESERVES',     'Reserves',                        '3200-3299', 0, 0, '', 730, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'EQ_DIVIDENDS',    'Dividends (declaration/payable)', '3300-3399', 0, 0, '', 740, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_TOTAL_EQ',     'TOTAL EQUITY',                    '',          0, 1, 'EQ_SHARE_CAP+EQ_RETAINED+EQ_RESERVES+EQ_DIVIDENDS', 799, 29, 1);

-- Grand total check
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 2, 'BS_GRAND_TOTAL',  'TOTAL EQUITY AND LIABILITIES',    '',          0, 1, 'BS_TOTAL_LIAB+BS_TOTAL_EQ', 899, 29, 1);


-- ============================================================================
-- PROFIT & LOSS (fk_report = 3)
-- ============================================================================

INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_HEADER_REV',   '═══ REVENUE ═══', '', 0, 2, '', 100, 29, 1);

-- Revenue
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'REV_SALES',       'Sales of goods',                  '4000-4099', 0, 0, '', 110, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'REV_SERVICES',    'Service revenue',                 '4100-4199', 0, 0, '', 120, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'REV_OTHER_OP',    'Other operating revenue',         '4200-4299', 0, 0, '', 130, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'REV_TOTAL',       'Total revenue',                   '',          0, 1, 'REV_SALES+REV_SERVICES+REV_OTHER_OP', 199, 29, 1);

-- Cost of sales
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_HEADER_COS',   '═══ COST OF SALES ═══', '', 1, 2, '', 200, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'COS_GOODS',       'Cost of goods sold',              '5000-5199', 1, 0, '', 210, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'COS_TOTAL',       'Total cost of sales',             '',          1, 1, 'COS_GOODS', 299, 29, 1);

-- Gross profit
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_GROSS',        'GROSS PROFIT',                    '',          0, 1, 'REV_TOTAL-COS_TOTAL', 399, 29, 1);

-- Other income
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'REV_OTHER_NONOP', 'Other income',                    '4900-4999', 0, 0, '', 410, 29, 1);

-- Operating expenses
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_HEADER_OPEX',  '═══ OPERATING EXPENSES ═══', '', 1, 2, '', 500, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_SALARIES',   'Salaries and benefits',           '6000-6099', 1, 0, '', 510, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_RENT_UTIL',  'Rent and utilities',              '6100-6199', 1, 0, '', 520, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_DA',         'Depreciation and amortisation',   '6200-6299', 1, 0, '', 530, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_SELLING',    'Selling expenses',                '6300-6399', 1, 0, '', 540, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_ADMIN',      'Administrative expenses',         '6400-6499', 1, 0, '', 550, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_OTHER',      'Other operating expenses',        '6500-6599', 1, 0, '', 560, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'OPEX_TOTAL',      'Total operating expenses',        '',          1, 1, 'OPEX_SALARIES+OPEX_RENT_UTIL+OPEX_DA+OPEX_SELLING+OPEX_ADMIN+OPEX_OTHER', 599, 29, 1);

-- Operating profit
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_OP_PROFIT',    'OPERATING PROFIT',                '',          0, 1, 'PL_GROSS+REV_OTHER_NONOP-OPEX_TOTAL', 699, 29, 1);

-- Finance
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_HEADER_FIN',   '═══ FINANCE ═══', '', 0, 2, '', 700, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'FIN_INCOME',      'Finance income (interest, etc.)', '7000-7099', 0, 0, '', 710, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'FIN_COSTS',       'Finance costs (interest, etc.)',  '7100-7199', 1, 0, '', 720, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_FX_OP',        'FX gain/(loss) - operating',      '7600-7699', 0, 0, '', 730, 29, 1);

-- Pre-tax profit
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_PRETAX',       'PROFIT BEFORE TAX',               '',          0, 1, 'PL_OP_PROFIT+FIN_INCOME-FIN_COSTS+PL_FX_OP', 799, 29, 1);

-- Tax
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_TAX',          'Income tax expense',              '7500-7599', 1, 0, '', 810, 29, 1);

-- Net profit
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_NET',          'NET PROFIT / (LOSS) FOR THE YEAR','',          0, 1, 'PL_PRETAX-PL_TAX', 899, 29, 1);

-- Non-recurring
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 3, 'PL_NONRECUR',     'Non-recurring gain/(loss)',       '7800-7899', 0, 0, '', 910, 29, 1);


-- ============================================================================
-- CASH FLOW STATEMENT (fk_report = 4)
-- For direct method: each line is a category of cash receipts/payments
-- For indirect method: the report builder will use P&L net profit and add
-- back non-cash items, ignoring the category_type=0 lines here.
-- ============================================================================

INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_HEADER_OP',    '═══ OPERATING ACTIVITIES ═══', '', 0, 2, '', 100, 29, 1);

-- Direct method - cash receipts
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'OP_RECEIPTS',     'Cash receipts from customers',    '1600-1600', 0, 0, '', 110, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'OP_PAYMENTS_SUPP','Cash paid to suppliers',         '2000-2000', 1, 0, '', 120, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'OP_PAYMENTS_EMP', 'Cash paid to/for employees',      '2410-2413,6000-6199,500-5099', 1, 0, '', 130, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'OP_TAX_PAID',     'Income tax / GST paid',          '2200-2299,7500-7599,2400-2499', 1, 0, '', 140, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'OP_OTHER',        'Other operating cash flows',      '1700-1799,2100-2199,6500-6599', 0, 0, '', 150, 29, 1);

INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_NET_OP',       'NET CASH FROM OPERATING',         '',          0, 1, 'OP_RECEIPTS-OP_PAYMENTS_SUPP-OP_PAYMENTS_EMP-OP_TAX_PAID+OP_OTHER', 199, 29, 1);

-- Investing activities
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_HEADER_INV',   '═══ INVESTING ACTIVITIES ═══', '', 0, 2, '', 200, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'INV_PPE',         'Purchase of PPE / intangibles',   '1000-1499', 1, 0, '', 210, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'INV_SALE_PPE',    'Proceeds from disposal of PPE',   '4910-4910', 0, 0, '', 220, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'INV_INVESTMENTS', 'Investments (acquired / disposed)','1300-1399', 0, 0, '', 230, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'INV_INTEREST',    'Interest received',               '7000-7099', 0, 0, '', 240, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_NET_INV',      'NET CASH FROM INVESTING',         '',          0, 1, 'INV_SALE_PPE+INV_INVESTMENTS+INV_INTEREST-INV_PPE', 299, 29, 1);

-- Financing activities
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_HEADER_FIN',   '═══ FINANCING ACTIVITIES ═══', '', 0, 2, '', 300, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'FIN_LOANS',       'Proceeds from borrowings',        '2300-2599,2540-2549', 0, 0, '', 310, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'FIN_REPAY',       'Repayment of borrowings',         '2300-2599,2540-2549', 1, 0, '', 320, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'FIN_INTEREST',    'Interest paid',                   '7100-7199', 1, 0, '', 330, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'FIN_LEASE',       'Lease payments (FRS 116)',        '2700-2799,1200-1299', 1, 0, '', 340, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'FIN_EQUITY',      'Proceeds from issue of shares',   '3000-3099', 0, 0, '', 350, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'FIN_DIVIDENDS',   'Dividends paid',                  '3300-3399,2540-2549', 1, 0, '', 360, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_NET_FIN',      'NET CASH FROM FINANCING',         '',          0, 1, 'FIN_LOANS-FIN_REPAY-FIN_INTEREST-FIN_LEASE+FIN_EQUITY-FIN_DIVIDENDS', 399, 29, 1);

-- Net change
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_NET_CHANGE',   'NET INCREASE/(DECREASE) IN CASH', '',          0, 1, 'CF_NET_OP+CF_NET_INV+CF_NET_FIN', 499, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_OPENING',      'Cash at beginning of period',     '',          0, 0, '', 510, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'CF_CLOSING',      'Cash at end of period',           '',          0, 0, '', 520, 29, 1);

-- ============================================================================
-- CASH FLOW — INDIRECT METHOD INPUT CATEGORIES (data-driven ranges)
-- These rows replace the hard-coded account ranges previously baked into
-- SfrsCashFlowBuilder::buildIndirect(). Editing range_account here is enough
-- to customise a company chart without touching PHP. sens mirrors the same
-- convention as the BS/PL: 0 = credit-debit (liability/equity/income);
-- 1 = debit-credit (asset/expense). Position 600-799 to leave room above.
-- ============================================================================

INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_DA',         'Depreciation and amortisation (indirect)',  '6200-6299', 1, 0, '', 600, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_WC_AR',      'Working capital - Trade receivables',       '1600-1699', 1, 0, '', 610, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_WC_INV',     'Working capital - Inventories',             '1500-1599', 1, 0, '', 620, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_WC_PREP',    'Working capital - Prepayments',             '1900-1999', 1, 0, '', 630, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_WC_AP',      'Working capital - Trade payables',          '2000-2099', 0, 0, '', 640, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_WC_ACCR',    'Working capital - Accruals',                '2100-2199', 0, 0, '', 650, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_WC_OCL',     'Working capital - Other current liab',      '2400-2499', 0, 0, '', 660, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_INV_PPE',    'Investing - Non-current assets (net)',      '1000-1499', 1, 0, '', 700, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_FIN_LOAN',   'Financing - Long-term loans (net)',         '2500-2599', 0, 0, '', 720, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_FIN_LEASE',  'Financing - Lease liab non-current (net)',  '2700-2799', 0, 0, '', 730, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_FIN_EQ',     'Financing - Share capital (net)',           '3000-3099', 0, 0, '', 740, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_FIN_DIV',    'Financing - Dividends paid (movement)',     '3300-3399', 1, 0, '', 750, 29, 1);
INSERT INTO llx_c_accounting_category (entity, fk_report, code, label, range_account, sens, category_type, formula, position, fk_country, active) VALUES (__ENTITY__, 4, 'IND_FIN_RET',    'Financing - Retained earnings (movement)',  '3100-3199', 0, 0, '', 760, 29, 1);
