# SG HR & Payroll

Singapore HR & payroll module for Dolibarr ERP/CRM.

## What's new in 2.1.0 (2026-10-01)

Renamed from **SGPayroll** to **SG HR & Payroll** and absorbed three modules:

- **docsemployes** (employee documents + expiry reminders) → `sghr/docsemployes/`,
  user-card tab + "Employee Documents" left menu, permission tree `sghr->docs`
- **ecv** (CV / skills / experience management) → `sghr/ecv/`,
  "CV Management" left menu, permission tree `sghr->cv`
- **recrutement** (jobs, candidatures, interview kanban, dictionaries, exports) →
  `sghr/recrutement/`, left-menu group "Recruitment", permission tree `sghr->rec`
- New **Candidate to Employee** conversion (`sghr/candidate_convert.php`,
  left menu) turning a recrutement candidacy into a Dolibarr user +
  SG HR employee profile in three steps

**Zero data migration**: tables `llx_sgpayroll_*`, `llx_docsemployes`,
`llx_ecv*`, `llx_recrutement`, `llx_candidatures` keep their original names. User-right bindings survive the
rename — permission ids are unchanged and `init()` relabels their module.

### Upgrade from sgpayroll/docsemployes/ecv/recrutement (deploy sequence)

1. Disable modules **SGPayroll**, **docsemployes**, **ecv** and
   **recrutement** in the module list (cleans their consts/menus),
   then delete their directories.
2. On the server: `mv documents/sgpayroll documents/sghr` (payslip file
   modulepart changed).
3. Enable **SG HR & Payroll** — `init()` runs the rename migration
   (rights/cronjobs labels, SGPAYROLL_* consts, menu cleanup) and
   rebuilds menus/tabs.

## Compatibility

- **Dolibarr:** 11.0+
- **PHP:** 7.4+

## Installation

1. Ensure `htdocs/custom/` is enabled in `conf/conf.php`:
   ```php
   $dolibarr_main_url_root_alt = '/custom';
   $dolibarr_main_document_root_alt = '/path/to/dolibarr/htdocs/custom';
   ```
2. Go to **Home → Setup → Modules** and enable **Singapore Payroll (Sgpayroll)**.
3. Activating the module runs `sql/` automatically (standard Dolibarr `_load_tables` behaviour, scripts are idempotent). For later schema upgrades use **Singapore Payroll → Setup → SQL Upgrade Runner** (`admin/upgrade_sql.php`).
4. Optional: the **Salaries** module is used when enabled (net-pay sync to Salary payments for finance); it is not a hard dependency.

## Singapore public holidays

Payslip working-day count and public-holiday OT (2.0×) use **Dolibarr’s Public holidays dictionary** for country **Singapore (SG)**:

- **Where to maintain:** **Setup → Dictionary → Public holidays** (filter by country Singapore).
- **Initial data:** Run **Singapore Payroll → Setup → Install/Upgrade tables** to apply `llx_sgpayroll_upgrade_14a_public_holidays.sql`, which seeds fixed holidays (New Year, Labour Day, National Day, Christmas, Good Friday) and variable dates for 2024–2026. For later years, add rows in the dictionary (year = specific year, month/day = date).
- **Behaviour:** If the dictionary has no Singapore entries, the module falls back to a built-in list. Once dictionary data exists, it is used for all payroll calculations.

## Version

- Module version: 1.6
- Declared minimum Dolibarr version via `need_dolibarr_version` in `core/modules/modSGPayroll.class.php`.

## Fixes (2026-10-28)

- **Payslip approval no longer fails with an empty "Approval failed" message.**
  Root cause: the payroll -> accounting trigger posted its journal lines with
  `doc_type='sghr'`, `fk_doc=0` and an empty `label_operation`. Those three fields
  form the duplicate-detection key of `BookKeeping::create()`, so any two lines
  sharing a GL account (gross salary + employer CPF on the salary account,
  employee + employer CPF on the CPF account) were refused as duplicates and the
  whole approval was rolled back. The trigger now posts with `fk_doc` = payslip
  line id and `label_operation` = entry label, so every line of every payslip is
  accepted (re-approval of an already posted payslip is detected and skipped).
- **Accounting failures no longer block a payroll approval.** A closed / missing
  fiscal period or a chart-of-accounts problem is a finance issue: it is now
  logged (`LOG_ERR`) and shown as a warning, while the payslip still gets approved.
  Entry date falls back to today when the payroll month is not on an active
  fiscal period.
- **Error messages are no longer empty.** `SghrRecord::saveStatus()` now reports
  the trigger/SQL error instead of the (usually empty) DB error, and the page uses
  the `PayslipApproveFailed` / `PayslipUnapproveFailed` language keys.
- New dry-run: `php htdocs/custom/sghr/test/test_accounting_keys.php` replays the
  journal key computation and shows the old/new collision behaviour.
- **Deployment:** the fix only touches files of this module (no core file), so it is
  deployed by uploading the changed files:
  `class/payrollrecord.class.php`, `core/triggers/interface_99_modSghr_AccountingHook.class.php`,
  `payslip_card.php`, `langs/en_US/sghr.lang`, `langs/zh_CN/sghr.lang`
  (plus the new `test/test_accounting_keys.php` dry-run). The module is
  self-contained: it does not require the `slycustom` module to be installed.
