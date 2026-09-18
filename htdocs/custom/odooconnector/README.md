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

1. Place the module in `htdocs/custom/odoo_connector/`.
2. Go to **Setup → Modules/Applications**, find **Odoo Connector** and enable it.
3. Go to **Setup → Odoo Connector** (or **Home → Setup → Odoo Connector** in the left menu) and set:
   - **Odoo URL**: e.g. `https://yourcompany.odoo.com`
   - **Database**: Odoo database name (often your company subdomain for Odoo Online)
   - **Username** and **Password** (or API key as password)
   - Which entities to sync (invoices, vendor bills, expenses).
4. Optionally configure the cron job **"Odoo Connector sync (invoices, bills, expenses)"** in **Setup → Cron jobs** (default: every hour).
5. Optionally configure the cron job **"Odoo Connector shipment date refresh"** in **Setup → Cron jobs** (method `OdooSync::runShipmentDateSync`): when a shipment's ATA/ETA changes (e.g. ShipsGo), it updates the accounting date of the linked Odoo DRAFT invoices/bills, including old invoices outside the main sync window. Lookback window and on/off switch are in the module setup.

## Usage

- **Automatic**: The cron runs periodically and pushes new/updated records to Odoo.
- **Manual**: In **Setup → Odoo Connector**, click **Sync now** to run the sync once.

## Design

See [docs/ODOO-CONNECTOR-DESIGN.md](docs/ODOO-CONNECTOR-DESIGN.md) for architecture, field mapping, and sync strategy.

## License

GPL v3.
