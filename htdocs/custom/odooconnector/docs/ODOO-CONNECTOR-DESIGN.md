# Odoo Connector – Design & Sync Strategy

This document describes how to achieve synchronised accounting records between **Dolibarr** and **Odoo Online** (customer invoices, vendor bills, expenses).

## Goals

- **Customer invoices**: Dolibarr `Facture` (customer) ↔ Odoo `account.move` (type = out_invoice)
- **Vendor bills**: Dolibarr `FactureFournisseur` ↔ Odoo `account.move` (type = in_invoice)
- **Expenses**: Dolibarr `ExpenseReport` ↔ Odoo `account.move` (type = in_invoice, draft; no third party in Dolibarr, a dedicated partner `DOL-EXPENSES` is used)

Sync can be **Dolibarr → Odoo** (push), **Odoo → Dolibarr** (pull), or **bidirectional** with conflict rules (e.g. last-write-wins or Dolibarr as master).

---

## Architecture Overview

```
┌─────────────────┐                    ┌─────────────────┐
│   Dolibarr      │  ←── Sync ───────→  │  Odoo Online    │
│                 │   (cron / manual)   │                 │
│ • Facture       │  JSON-RPC / REST    │ • account.move  │
│ • FactureFourn  │                     │ • res.partner   │
│ • ExpenseReport │                     │ • res.partner   │
└─────────────────┘                     └─────────────────┘
```

- **Dolibarr** exposes data via internal PHP classes (`Facture`, `FactureFournisseur`, `ExpenseReport`) and optionally REST API.
- **Odoo Online** is accessed via **JSON-RPC** (recommended) or XML-RPC from PHP. REST is available from Odoo 17+.
- A **custom module** (`odoo_connector`) runs inside Dolibarr: it uses a cron job to run sync logic that reads from Dolibarr DB/API and calls Odoo’s JSON-RPC API.

---

## 1. Odoo API Access (Odoo Online)

- **Protocol**: Use **JSON-RPC** from PHP (works with Odoo 8+ and Odoo Online).
- **Endpoints** (replace `yourcompany` with your Odoo subdomain):
  - Login: `https://yourcompany.odoo.com/jsonrpc` or `https://www.odoo.com/jsonrpc` with database name.
  - For **Odoo Online**, the “database” is often your company subdomain; confirm in Odoo’s “Database manager” or support.
- **Authentication**: User + password (or API key if you use a custom auth module). Store credentials in Dolibarr conf (setup) and never in code.
- **Models**:
  - Invoices / bills / expenses: `account.move` (filter `move_type` in `['out_invoice','out_refund','in_invoice','in_refund']`).
  - Third parties: `res.partner` (map to Dolibarr `societe`).

---

## 2. Sync Strategy

### 2.1 Link records: external reference

- **Dolibarr → Odoo**: When we create or update a record in Odoo from Dolibarr, store Odoo’s `id` in Dolibarr (e.g. `facture.ref_ext`, or a dedicated table `llx_odoo_connector_sync` with `element_type`, `fk_source_id`, `odoo_model`, `odoo_id`, `last_sync`).
- **Odoo → Dolibarr**: When we create a record in Dolibarr from Odoo, set `ref_ext` to Odoo’s external id (e.g. `odoo_account.move_123`) or store in the same mapping table.
- Use **one mapping table** for all entity types to avoid schema changes on core tables and to support both directions.

### 2.2 Direction and conflict

- **Push only** (Dolibarr → Odoo): Dolibarr is the master where all documents are recorded; Odoo is used for accounting only.
- **Trigger**: a record is pushed when validated AND re-pushed on later modification (Dolibarr `tms` > mapping `last_sync`).
- Odoo records stay **draft** (not posted) so Dolibarr edits can be re-pushed; write to a posted move is not attempted.

### 2.3 What we push

- Original-currency amounts only: `multicurrency_total_ht`, `multicurrency_total_tva`, `multicurrency_total_ttc` with `multicurrency_code` (falls back to main-currency totals when the invoice is in the company currency).
- **No exchange rate is transferred** (neither Dolibarr `multicurrency_tx` nor an inverted rate): Odoo resolves its own rate by invoice date.
- No invoice line detail: one income/expense line (`total_ht`) plus one tax line (`total_tva` on the configured tax account).
- `ref` = Dolibarr invoice number (vendor bills: supplier ref) for reconciliation.

### 2.4 Field mapping (high level)

| Dolibarr                    | Odoo model       | Notes |
|----------------------------|------------------|--------|
| Customer invoice (Facture) | account.move     | move_type = out_invoice / out_refund, draft |
| Supplier invoice (FactureFournisseur) | account.move | move_type = in_invoice / in_refund, draft |
| Expense report (ExpenseReport)        | account.move | move_type = in_invoice, draft, journal = expense (general), partner `DOL-EXPENSES` |

Line account selection (untaxed line): by Dolibarr document type, configurable in Setup
with Odoo-side suggestion (dropdown filtered by `account_type`), falling back to the journal
default account when empty:

| Document | Account config | Odoo account_type suggested |
|---|---|---|
| Customer invoice / credit note | ODOO_CONNECTOR_INVOICE_REVENUE_ACCOUNT_ID | income |
| Customer **deposit invoice** | ODOO_CONNECTOR_DEPOSIT_ACCOUNT_ID | liability (prepayment, not revenue) |
| Vendor bill / refund | ODOO_CONNECTOR_BILL_EXPENSE_ACCOUNT_ID | expense |
| Vendor **deposit invoice** | ODOO_CONNECTOR_SUPPLIER_DEPOSIT_ACCOUNT_ID | asset (prepayment to vendor, not expense) |
| Expense report | ODOO_CONNECTOR_EXPENSE_ACCOUNT_ID | expense |

Lifecycle rules:

- Synced set: invoices with `fk_statut IN (1, 2)` (validated or paid).
- Invoices deleted / reopened to draft / abandoned after being synced (e.g. refunded then
  cancelled without a credit note): the Odoo **draft** move is deleted and the mapping row
  removed. Posted moves cannot be deleted automatically: a warning asks for a manual reversal
  in Odoo.
- Deposit handling: Dolibarr deposit invoices are pushed as customer prepayments (liability
  account) / vendor prepayments (asset account); standard invoices and vendor bills that deduct
  deposits via lines/discount carry the net revenue/expense.

| Dolibarr field | Odoo field |
|---|---|
| multicurrency_code | account.move.currency_id (res.currency ensured) |
| multicurrency_total_ht | invoice_line_ids[0].price_unit |
| multicurrency_total_tva | invoice_line_ids[1].price_unit (account = ODOO_CONNECTOR_TAX_ACCOUNT_ID) |
| facnumber / ref_supplier | ref |
| (fixed const) | company_id = ODOO_CONNECTOR_COMPANY_ID |
| (auto or const) | journal_id = first sale/purchase journal, or ODOO_CONNECTOR_*_JOURNAL_ID |

Partner sync: `res.partner` matched by Dolibarr `code_client` (stored in Odoo `ref`), created minimal (name/ref/email) when missing. Never matched by name.

Entity mapping: Dolibarr entity 1 ↔ Odoo company ID 1 (SLY Food Pte. Ltd.), fixed by config `ODOO_CONNECTOR_COMPANY_ID` because the Odoo database holds 7 companies.

---

## 3. Implementation in Dolibarr

### 3.1 Module: `odoo_connector`

- **Location**: `htdocs/custom/odoo_connector/`
- **Components**:
  - **modOdooConnector.class.php**: Module descriptor, permissions, **cron job** for sync.
  - **class/OdooConnector.class.php**: Odoo **JSON-RPC client** (login, `call_kw` for search_read, create, write).
  - **class/OdooSync.class.php**: Sync logic:
    - Load config (Odoo URL, db, user, password).
    - For each entity (invoices, supplier invoices, expenses):
      - Select records to sync (e.g. modified since last run, or with `ref_ext` empty for push).
      - Map to Odoo fields; create or update in Odoo; store Odoo id in mapping table or `ref_ext`.
    - Optional: pull from Odoo and create/update Dolibarr (with mapping table).
  - **admin/setup.php**: Configuration (Odoo URL, database, user, password, sync direction, which entities to sync).
  - **Cron**: Runs `OdooSync::runSync()` every X minutes (e.g. 15–60).

### 3.2 Config (stored in Dolibarr const or conf)

- `ODOO_CONNECTOR_URL` (e.g. `https://yourcompany.odoo.com`)
- `ODOO_CONNECTOR_DB`
- `ODOO_CONNECTOR_USER`
- `ODOO_CONNECTOR_PASSWORD` (or API key if used)
- `ODOO_CONNECTOR_COMPANY_ID` (Odoo res.company id, default 1)
- `ODOO_CONNECTOR_TAX_ACCOUNT_ID` (Odoo account.account id for the tax line, optional)
- `ODOO_CONNECTOR_SALES_JOURNAL_ID` / `ODOO_CONNECTOR_PURCHASE_JOURNAL_ID` (optional, auto first sale/purchase journal)
- `ODOO_CONNECTOR_SYNC_INVOICES` (yes/no)
- `ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS` (yes/no)
- `ODOO_CONNECTOR_SYNC_EXPENSES` (yes/no)

### 3.3 Mapping table (optional but recommended)

```sql
CREATE TABLE llx_odoo_connector_sync (
  rowid           int AUTO_INCREMENT PRIMARY KEY,
  entity          int DEFAULT 1,
  element_type    varchar(32) NOT NULL,  -- 'facture', 'facture_fourn', 'expensereport'
  fk_source_id     int NOT NULL,          -- Dolibarr ID
  odoo_model      varchar(64) NOT NULL,  -- 'account.move'
  odoo_id         int NOT NULL,
  odoo_write_date datetime,
  last_sync       datetime,
  UNIQUE KEY (entity, element_type, fk_source_id)
);
```

Use this to know which Dolibarr record corresponds to which Odoo id and to avoid duplicates.

---

## 4. Flow Summary

1. **Setup**: Admin configures Odoo URL, DB, user, password and which entities to sync in **Setup → Odoo Connector**.
2. **Cron**: Periodically, `OdooSync::runSync()`:
   - Authenticates with Odoo (JSON-RPC).
   - For **customer invoices**: gets list of Facture (e.g. validated, not yet synced or updated since last sync); for each, creates or updates `account.move` (out_invoice); saves Odoo id in mapping.
   - Same for **vendor bills** (FactureFournisseur → account.move in_invoice).
   - For **expenses**: gets ExpenseReport; creates/updates `account.move` (in_invoice, draft, expense journal, partner `DOL-EXPENSES`); saves mapping.
3. **Optional pull**: If direction is pull or both, read from Odoo (e.g. new payments), create or update Dolibarr records and mapping.
4. **Logs**: Log sync results (created/updated/failed) in Dolibarr syslog or a dedicated log table for debugging.

---

## 5. How to Achieve Synced Accounting – Checklist

- [ ] Install and enable **odoo_connector** module in Dolibarr.
- [ ] Configure Odoo Online URL, database, user and password in module setup.
- [ ] Enable sync for **Customer invoices**, **Vendor bills**, **Expenses** as needed.
- [ ] Run cron (or manual “Sync now” in setup) so that:
  - New/updated Dolibarr invoices and bills are pushed to Odoo.
  - New/updated Dolibarr expense reports are pushed to Odoo.
- [ ] Optionally map **third parties** (societe ↔ res.partner) first to avoid duplicate partners in Odoo.
- [ ] Monitor logs and mapping table to fix duplicates or failed records.

This design allows you to have **synchronised accounting records** between both systems while keeping Dolibarr as the primary source for invoicing and expenses if you use push-only, or to extend later to bidirectional sync with clear rules.
