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
- A **custom module** (`odooconnector`) runs inside Dolibarr. Sync is **event-driven**: a
  trigger pushes on `BILL_VALIDATE` / `BILL_SUPPLIER_VALIDATE` (validate) and resets the
  Odoo entry on `BILL_UNVALIDATE` / `BILL_SUPPLIER_UNVALIDATE` (reopen/"Modify"). An
  optional hourly cron job is a retry/fallback channel, not the primary path.

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

- **Dolibarr → Odoo**: When we create or update a record in Odoo from Dolibarr, store Odoo’s `id` in the dedicated mapping table `llx_odoo_connector_sync` (`element_type`, `fk_source_id`, `odoo_model`, `odoo_id`, `last_sync`). Core tables are never altered.
- **Odoo → Dolibarr**: When we create a record in Dolibarr from Odoo, set `ref_ext` to Odoo’s external id (e.g. `odoo_account.move_123`) or store in the same mapping table.
- Use **one mapping table** for all entity types to avoid schema changes on core tables and to support both directions.

### 2.2 Direction and conflict

- **Push only** (Dolibarr → Odoo): Dolibarr is the master where all documents are recorded; Odoo is used for accounting only.
- **Event-driven push**: a document is pushed when validated (trigger, immediate) and re-pushed on later modification (Dolibarr `tms` > mapping `last_sync`, picked up by the next sync run / manual "Sync now").
- Odoo records stay **draft** (not posted) so Dolibarr edits can be re-pushed.
- **Posted entries**: reopening the document in Dolibarr ("Modify") resets the linked Odoo
  entry to draft immediately (trigger). If that is missed, the sync auto-resets when it
  detects a change on a posted entry (draft + To Review), then pushes. Odoo refusals
  (locked period, reconciled lines) fall back to a manual workflow: chatter warning +
  failure log entry.

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
- Invoices **deleted or abandoned** (fk_statut = 3) after being synced (e.g. refunded then
  cancelled without a credit note): the Odoo **draft** move is deleted and the mapping row
  removed. Posted moves cannot be deleted automatically: a warning asks for a manual reversal
  in Odoo.
- Invoices **reopened** (fk_statut = 0, "Modify") are NOT cleaned: their Odoo entry is reset
  to draft by the unvalidate trigger and kept in place, and the next validation pushes the
  update into the same entry (same Odoo id).
- Deposit handling: Dolibarr deposit invoices are pushed as customer prepayments (liability
  account) / vendor prepayments (asset account); standard invoices and vendor bills that deduct
  deposits via lines/discount carry the net revenue/expense.

| Dolibarr field | Odoo field |
|---|---|
| multicurrency_code | account.move.currency_id (res.currency ensured) |
| multicurrency_total_ht | invoice_line_ids[0].price_unit |
| multicurrency_total_tva | invoice_line_ids[1].price_unit (account = ODOO_CONNECTOR_TAX_ACCOUNT_ID) |
| ref (Dolibarr 21+; formerly facnumber) / ref_supplier | ref |
| (fixed const) | company_id = ODOO_CONNECTOR_COMPANY_ID |
| (auto or const) | journal_id = first sale/purchase journal, or ODOO_CONNECTOR_*_JOURNAL_ID |

Partner sync: `res.partner` is matched by a chain, first hit wins —
1. Odoo `ref` = Dolibarr `code_client` or `code_fournisseur` (role-preferred order);
2. VAT number (both spellings, with/without country prefix);
3. a unique company email match.
A partner matched by VAT/email gets the Dolibarr code written back into its empty `ref`,
so later runs take the fast path. Nothing matches → created with name/ref/email/vat/
address/phone and the customer/supplier rank (when the Odoo version still has those
fields). Name is **never** a match key (a homonym would silently merge two companies);
when a same-name partner already exists, the sync output asks for a manual merge in Odoo
(Contacts → Merge) instead.

Entity mapping: Dolibarr entity 1 ↔ Odoo company ID 1 (SLY Food Pte. Ltd.), fixed by config `ODOO_CONNECTOR_COMPANY_ID` because the Odoo database holds 7 companies.

---

## 3. Implementation in Dolibarr

### 3.1 Module: `odooconnector`

- **Location**: `htdocs/custom/odooconnector/`
- **Components**:
  - **modOdooConnector.class.php**: Module descriptor, permissions, optional cron jobs.
  - **core/triggers/interface_99_modOdooConnector_OdooConnector.class.php**: event entry
    points — validate pushes (`BILL_VALIDATE` / `BILL_SUPPLIER_VALIDATE` →
    `OdooSync::syncDocumentNow`), reopen resets (`BILL_UNVALIDATE` /
    `BILL_SUPPLIER_UNVALIDATE` → `OdooSync::syncDocumentUnvalidate`).
  - **class/OdooConnector.class.php**: Odoo **JSON-RPC client** (authenticate, execute_kw,
    searchRead, create, write, unlink).
  - **class/OdooSync.class.php**: Sync logic (event handlers above + `runSync` for cron /
    manual runs: invoices, vendor bills, expenses, revoked-move cleanup).
  - **admin/setup.php**: Configuration and the sync failure log.
  - **Cron (optional)**: `OdooSync::runSync()` every hour (retry/fallback) and
    `OdooSync::runShipmentDateSync()` (required when the accounting date follows shipment
    ATA/ETA, since those updates fire no invoice event).

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
2. **Validate** (trigger, immediate): the invoice/vendor bill is pushed as an Odoo draft
   (`account.move`), or the existing draft is updated, and the mapping row is saved. A
   duplicate guard adopts an existing Odoo move pushed earlier for the same invoice number
   (crash recovery) instead of creating a second copy.
3. **Reopen** ("Modify", trigger, immediate): a posted Odoo entry is reset to draft and
   flagged To Review, so step 2 can push the changes into it on the next validation.
4. **Cron / manual "Sync now"** (retry & fallback): re-pushes everything changed since its
   last run (`tms` > `last_sync`), cleans moves of deleted/abandoned documents, flags
   paid-in-Dolibarr posted entries To Review. The shipment date cron refreshes the
   accounting date of drafts linked to shipments whose ATA/ETA changed.
5. **Logs**: failures are recorded per document in `llx_odoo_connector_synclog` (listed in
   the module setup page) and cleared automatically when a retry succeeds.

---

## 5. How to Achieve Synced Accounting – Checklist

- [ ] Install and enable **odooconnector** module in Dolibarr.
- [ ] Configure Odoo Online URL, database, user and password in module setup.
- [ ] Enable sync for **Customer invoices**, **Vendor bills**, **Expenses** as needed.
- [ ] Day-to-day: just **Validate** in Dolibarr — the push is immediate (event-driven);
  reopening with **Modify** resets the linked Odoo entry so the next validation updates it.
- [ ] Optionally enable the hourly sync cron as an automatic retry channel, and the
  shipment date cron when the accounting date follows shipment ATA/ETA.
- [ ] Partner duplicates from the pre-connector Odoo base: run one sync (VAT/email matches
  get their `ref` backfilled automatically), then merge the leftovers in Odoo
  (Contacts → Merge) — the sync output lists the same-name candidates.
- [ ] Monitor the sync failure log in the module setup page; entries clear themselves when
  a retry succeeds.

This design allows you to have **synchronised accounting records** between both systems while keeping Dolibarr as the primary source for invoicing and expenses if you use push-only, or to extend later to bidirectional sync with clear rules.
