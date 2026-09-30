# SFRS Financial Reports — Dolibarr Module

A Dolibarr custom module that generates **Singapore Financial Reporting Standards (SFRS)** compliant financial statements:

- **Balance Sheet** (Statement of Financial Position)
- **Profit & Loss Statement** (Statement of Comprehensive Income)
- **Cash Flow Statement** (both direct and indirect methods)

Supports three SFRS frameworks (Advanced toggle in Setup):
- **FRS** — default for non-listed Singapore-incorporated companies
- **SFRS(I)** — IFRS-aligned, mandatory for SGX-listed entities
- **SFRS for Small Entities** — simplified for qualifying small businesses (≤ S$10M revenue / assets, ≤ 50 employees)

Compatible with Singapore GST (9% effective 2024-01-01), PayNow, FRS 116 (Leases), and CPF/SDF contributions.

---

## Installation

1. Copy the `sfrs_reports` folder to your Dolibarr `htdocs/custom/` directory.
2. Log in as administrator: **Home → Setup → Modules → Applications**.
3. Find **SFRS Financial Reports** and click **Enable**. The dependency on the **Double-entry accounting** module will be enforced automatically.
4. Go to **Accounting → SFRS Reports → Setup** and:
   - Configure the active framework (FRS / SFRS(I) / SFRS for SE)
   - Choose default Cash Flow method (direct / indirect)
   - Set presentation currency (default SGD) and company UEN
   - Set GST standard rate (default 9%)
   - Click **Import now** for *Singapore Chart of Accounts* (~200 accounts)
   - Click **Import now** for *SFRS Report Categories* (BS / P&L / CF row definitions)
5. Bind default accounts in **Setup → Accounting → Default accounts**:
   - Customer receivable → **1600 Trade receivables**
   - Supplier payable → **2000 Trade payables**
   - Bank → **1810 Bank account - SGD operating**
   - Customer default VAT → SG GST 9% (create one in **Setup → Accounting → Taxes**)

## Generating Reports

Navigate to **Accounting → SFRS Reports**:

- **Balance Sheet** → pick balance date → *Generate*
- **Profit & Loss** → pick period start / end → *Generate*
- **Cash Flow** → pick period + method (direct/indirect) → *Generate*

Each report includes:
- Company header (name, UEN, framework, period, presentation currency)
- Balance check on BS (Total Assets == Total Equity + Liabilities; warning if not)
- Exportable table (HTML; PDF export is a future enhancement)

---

## Xero Singapore ↔ SFRS-Dolibarr Account Mapping

If you are migrating from **Xero SG** (template "Company – 2024 – 9% GST Rates") to this Dolibarr SFRS module, use this table to remap accounts:

| Xero SG Code | Xero SG Account Name | SFRS Dolibarr Code | SFRS Dolibarr Account Name |
|---|---|---|---|
| 200 | Sales | 4000 | Sales - goods (standard-rated 9% GST) |
| 260 | Other Revenue | 4900 | Other operating income (sundry) |
| 270 | Interest Income | 7000 | Interest income - bank deposits |
| 310 | Cost of Goods Sold | 5000 | Cost of goods sold - purchases |
| 320 | Direct Wages | 5100 | Direct labour |
| 325 | Direct Expenses | 5120 | Subcontractor costs |
| 400 | Advertising & Marketing | 6300 | Advertising and marketing |
| 401 | Audit & Accountancy fees | 6400 | Audit and accountancy fees |
| 404 | Bank Fees | 6440 | Bank charges |
| 408 | Cleaning | 6580 | Cleaning and security |
| 412 | Consulting | 6430 | Consultancy fees |
| 416 | Depreciation Expense | 6200-6299 | Depreciation & Amortisation (split by asset class) |
| 420 | Entertainment (100% business) | 6530 | Entertainment (50% deductible) |
| 425 | Postage, Freight & Courier | 6570 | Postage and courier |
| 429 | General Expenses | 6490 | Other administrative expenses |
| 433 | Insurance | 6460 | Insurance |
| 437 | Interest Paid | 7100 | Interest expense - bank loans |
| 441 | Legal Expenses | 6410 | Legal fees |
| 445 | Light, Power, Heating | 6140-6160 | Utilities - electricity/water/gas |
| 449 | Motor Vehicle Expenses | 6500 | Motor vehicle expenses |
| 457 | Operating Lease Payments | 6180 | Short-term lease payments (FRS 16 exemption) |
| 461 | Printing & Stationery | 6470 | Office supplies and stationery |
| 463 | IT Software and Consumables | 6480 | Software subscriptions and IT |
| 469 | Rent | 6100 | Rent expense - office |
| 473 | Repairs & Maintenance | 6560 | Repairs and maintenance |
| 477 | Salaries | 6000 | Salaries and wages |
| 478 | Directors' Remuneration | 6010 | Directors fees |
| 480 | Staff Training | 6060 | Staff training and welfare |
| 482 | Pensions Costs | 6030 | CPF contribution (employer) |
| 485 | Subscriptions | 6550 | Subscriptions and professional fees |
| 489 | Telephone & Internet | 6170 | Telecommunications and internet |
| 493 | Travel - National | 6510 | Travel - domestic |
| 494 | Travel - International | 6520 | Travel - international |
| 500 | Corporation Tax | 7500 | Current income tax expense (CIT 17%) |
| 610 | Accounts Receivable | 1600 | Trade receivables |
| 611 | Less Provision for Doubtful Debts | 1601 | Less: Provision for doubtful debts |
| 620 | Prepayments | 1900-1940 | Prepayments - rent/insurance/subscriptions/other |
| 630 | Inventory | 1500-1540 | Raw materials / WIP / Finished goods / Trading inventory |
| 710 | Office Equipment | 1020 | Office equipment at cost |
| 711 | Less Acc Dep - Office Equipment | 1021 | Less: Accumulated depreciation - Office equipment |
| 720 | Computer Equipment | 1030 | Computer equipment at cost |
| 721 | Less Acc Dep - Computer Equipment | 1031 | Less: Accumulated depreciation - Computer equipment |
| 740 | Buildings | 1001 | Buildings at cost |
| 741 | Less Acc Dep - Buildings | 1002 | Less: Accumulated depreciation - Buildings |
| 750 | Leasehold Improvements | 1060 | Leasehold improvements at cost |
| 751 | Less Acc Dep - Leasehold | 1061 | Less: Accumulated amortisation - Leasehold improvements |
| 760 | Motor Vehicles | 1040 | Motor vehicles at cost |
| 761 | Less Acc Dep - Motor Vehicles | 1041 | Less: Accumulated depreciation - Motor vehicles |
| 764 | Plant & Machinery | 1010 | Plant and machinery at cost |
| 765 | Less Acc Dep - Plant & Machinery | 1011 | Less: Accumulated depreciation - Plant & machinery |
| 770 | Intangibles | 1100-1130 | Goodwill / Patents / Software / Development costs |
| 771 | Less Acc Amort - Intangibles | 1111/1121 | Less: Accumulated amortisation |
| 800 | Accounts Payable | 2000 | Trade payables |
| 805 | Accruals | 2100-2190 | Accruals (operating, audit, bonuses, interest) |
| 810 | Income in Advance | 2440 | Unearned revenue (deferred income) |
| 811 | Credit Card Control Account | 2320 | Credit card liability |
| 820 | VAT | 2200-2240 | GST output / GST input / GST payable to IRAS |
| 825 | PAYE Payable | 2412 | CPF contribution payable (employer) |
| 830 | Provision for Corporation Tax | 2400 | Income tax payable |
| 835 | Directors' Loan Account | 2430 | Director loan account |
| 840 | Historical Adjustment | 8510 | Historical opening balance adjustment |
| 850 | Suspense | 8500 | Suspense account (unallocated entries) |
| 900 | Loan | 2500 | Long-term bank loan |
| 910 | Hire Purchase Loan | 2510 | Hire purchase (non-current) |
| 920 | Deferred Tax | 2900 | Deferred tax liability |
| 950 | Capital | 3000 | Issued share capital (ordinary shares) |
| 960 | Retained Earnings | 3100 | Retained earnings (opening) |
| 970 | Owner A Funds Introduced | 3000 | Issued share capital (ordinary shares) |
| 980 | Owner A Drawings | 3300 | Dividends declared (interim) |

**Note on GST**: Xero uses account 820 "VAT" for both input and output GST. Dolibarr SFRS splits this into:
- **2200 GST output tax** (9% standard-rated supplies)
- **2210 GST output tax** (zero-rated)
- **2220 GST output tax** (exempt)
- **1730 GST input tax recoverable** (asset, on purchase side)
- **2230 GST suspense / control account**
- **2240 GST payable to IRAS (net)** — used at period end after netting

When migrating from Xero, you may need to manually re-classify opening balances across these accounts.

---

## Module Architecture

```
htdocs/custom/sfrs_reports/
├── core/modules/modSfrsReports.class.php   # Module descriptor (numero=501200, depends on modAccounting)
├── class/
│   ├── sfrsreport.class.php                  # Main facade; imports COA + categories
│   ├── sfrsbalancebuilder.class.php          # BS data aggregator
│   ├── sfrsplbuilder.class.php               # P&L data aggregator
│   └── sfrscashflowbuilder.class.php         # CF data aggregator (direct + indirect)
├── admin/setup.php                           # Module configuration UI
├── pages/
│   ├── index.php                             # Module landing page
│   ├── balance_sheet.php                     # BS renderer
│   ├── profit_loss.php                       # P&L renderer
│   └── cash_flow.php                         # CF renderer (with method toggle)
├── sql/data/
│   ├── llx_sfrs_account_sg.sql               # ~200 SFRS accounts
│   └── llx_sfrs_c_accounting_category.sql    # BS/P&L/CF category rows
├── langs/
│   ├── en_US/sfrs_reports.lang
│   └── zh_CN/sfrs_reports.lang
└── img/sfrs_reports.png                      # Module icon
```

### How reports are computed

All three reports are driven by `llx_c_accounting_category`:

- Each **row** in a report maps to one category (`code` like `NCA_PPE`, `REV_SALES`, etc.).
- `range_account` defines which SFRS chart-of-accounts numbers feed into the row (e.g. `'1000-1099'`).
- `sens` controls the sign (debit-credit vs credit-debit).
- `formula` (when `category_type=1`) lets a row sum other categories — e.g. `BS_TOTAL_ASSETS = NCA_TOTAL + CA_TOTAL`.
- `position` controls display order.

For BS, the data source is `llx_accounting_bookkeeping` summed up to the balance date.
For P&L and CF, the data source is the same table filtered to the report period.

---

## Compliance Notes

### Singapore Statutory Requirements

- **Companies Act §201**: all Singapore-incorporated companies must prepare financial statements that comply with ASC-issued accounting standards and give a true and fair view.
- **FRS 1** (or SFRS(I) 1-1): requires the financial statements to comprise:
  1. Statement of comprehensive income (P&L)
  2. Statement of financial position (BS)
  3. Statement of changes in equity
  4. Statement of cash flows
  5. Notes
  6. Director's declaration (signed by two directors)

This module generates (1), (2), and (4). Items (3), (5), and (6) are out of scope and must be prepared separately.

### Annual Filing Deadlines (measured from financial year-end)

| Entity | AGM | Annual return filing |
|---|---|---|
| Listed | within 4 months after FYE | within 5 months after FYE |
| Non-listed | within 6 months after FYE | within 7 months after FYE |

### XBRL Filing

ACRA requires most Singapore companies to file financial statements in **XBRL format** through BizFinx (using CorpPass). This module does **not** yet export XBRL; that is a Phase 9 enhancement. See the [SFRS Reports plan file](../..) for the implementation roadmap.

---

## Future Roadmap

- [ ] PDF export (TCPDF, similar to `pdf_balance.modules.php`)
- [ ] XBRL export for ACRA BizFinx filing
- [ ] Statement of Changes in Equity (SFRS 1 requirement)
- [ ] Notes auto-generation (accounting policies, related-party disclosures)
- [ ] Multi-currency presentation translation
- [ ] Audit trail / period locking
- [ ] GST F5 return generation (IRAS quarterly format)

---

## License

GNU General Public License v3 or later. See `LICENSE` for the full text (inherited from Dolibarr's licensing).

## Author

Henry Guo `<hbg@hbg.sg>` — Singapore, 2026.