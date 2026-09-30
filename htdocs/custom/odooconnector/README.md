# Odoo Connector

Dolibarr custom module to **synchronise customer invoices, vendor bills and expense reports** with **Odoo Online** (or self-hosted Odoo), so both systems keep aligned accounting records.

## Features

- **Customer invoices** (Dolibarr `Facture`) → Odoo `account.move` (out_invoice / out_refund)
- **Vendor bills** (Dolibarr `FactureFournisseur`) → Odoo `account.move` (in_invoice / in_refund)
- **Expense reports** (Dolibarr `ExpenseReport`) → Odoo `account.move` (in_invoice)

Sync is **push-only** (Dolibarr → Odoo): validated invoices/bills and expense reports are created or updated in Odoo. A mapping table stores the link between Dolibarr IDs and Odoo IDs to avoid duplicates.

## Requirements

- Dolibarr 22.0+ (developed and tested on 22.0.4)
- PHP 7.3+ with `json` extension (for Odoo JSON-RPC)
- Odoo Online or self-hosted Odoo with **external API access** (JSON-RPC). For Odoo Online, ensure your plan allows API access and set a password (or use an API key) for the connector user.

## Installation

1. Place the module in `htdocs/custom/odooconnector/`.
2. Go to **Setup → Modules/Applications**, find **Odoo Connector** and enable it.
3. Go to **Setup → Odoo Connector** (or **Home → Setup → Odoo Connector** in the left menu) and set:
   - **Odoo URL**: e.g. `https://yourcompany.odoo.com`
   - **Database**: Odoo database name (often your company subdomain for Odoo Online)
   - **Username** and **Password** (or API key as password). The password input stays empty
     after saving on purpose: an empty field keeps the stored key.
   - Which entities to sync (invoices, vendor bills, expenses).
4. Optionally enable the cron job **"Odoo Connector sync (invoices, bills, expenses)"** in
   **Setup → Cron jobs** (default: every hour) as a retry/fallback channel — it is **not**
   required for day-to-day operation (see Usage).
5. Optionally enable the cron job **"Odoo Connector shipment date refresh"** (method
   `OdooSync::runShipmentDateSync`): when a shipment's ATA/ETA changes (e.g. ShipsGo), it
   updates the accounting date of the linked Odoo DRAFT invoices/bills, including old
   invoices outside the main sync window. **Required** if the accounting date source is
   "delivery/reception": those updates touch the shipment, not the invoice, so no invoice
   event fires. Lookback window and on/off switch are in the module setup.

## Usage

Sync is **event-driven** (no cron needed for the normal flow):

- **On validate** (`Validate` button): the invoice/vendor bill is pushed to Odoo immediately
  (created as draft, or the existing draft is updated).
- **On reopen** (`Modify` button): if the linked Odoo entry was already posted, it is reset to
  draft right away and flagged To Review, so the next validation pushes the changes into it.
- Failures never block the Dolibarr operation: they are logged in the module setup page
  (Sync failure log) for a later retry — run **Sync now** there, or enable the hourly cron
  job as an automatic retry.
- **Manual**: in **Setup → Odoo Connector**, click **Sync now** to run the sync once.

## Design

See [docs/ODOO-CONNECTOR-DESIGN.md](docs/ODOO-CONNECTOR-DESIGN.md) for architecture, field mapping, and sync strategy.

## License

GPL v3.
