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
