# SLY Accounting Date Rules

This document describes how **accounting date** is determined for sales invoices, supplier (PO) invoices, and expense reports in SLY. These rules are implemented in `class/SLY_AccountingDate.class.php` and are used by the Odoo Connector when syncing to Odoo.

## Sales invoice (customer invoice)

- **If the invoice is linked to a shipment**: the accounting date is the shipment’s **ATA** (Actual Time of Arrival), or **ETA** (Estimated Time of Arrival) if ATA is not set.
- **Otherwise**: the accounting date is the **invoice date**.

Link to shipment is determined by:
- Invoice `origin` = `shipping` / `expedition` and `origin_id` = expedition id, or
- A link in `element_element` between the invoice and an expedition.

ATA/ETA are read from `llx_expedition_extrafields` (columns `ata`, `eta`). These columns are part of the SLY expedition_extrafields structure. **ShipsGo** (cron and “Update Ships” on shipment card/list) writes to `expedition_extrafields`: `pol`, `atd`, `pod`, `ata`, and `eta` when the API returns them (`ArrivalDate` → ata; `Eta` or `EstimatedArrivalDate` → eta).

---

## Supplier invoice (vendor bill / PO invoice)

### Standard invoice

- **If the PO is linked to an SO (sales order)** and that SO has a linked **shipment**: the accounting date is the **shipment’s ATA or ETA**.
- **Constraint**: only **one** non-cancelled standard invoice per PO may use the shipment ATA/ETA as accounting date. That invoice is the one with the **smallest rowid** (oldest) among all non-cancelled standard invoices linked to that PO. Any other standard invoice on the same PO uses the **invoice date** as accounting date.
- **If the PO is not linked to an SO**, or the SO has no shipment: accounting date = **invoice date**.

### Deposit invoice and credit note

- Accounting date = **invoice date** (always).

Link chain: supplier invoice ↔ PO (`element_element`) ↔ SO (`element_element`) ↔ expedition (`element_element`). ATA/ETA are taken from the first linked expedition of that SO.

---

## Expense report

- Accounting date = **approval date** (date when the expense report was approved).
- In Dolibarr this is `date_approbation` / `date_approve` on the expense report. If not set, fallback to validation date or report start date.

---

## Usage

- **Odoo Connector**: when the module `slycustom` is enabled and `SLY_AccountingDate` is present, the connector uses these rules to set the `date` / `invoice_date` (and expense `date`) sent to Odoo.
- **Other integrations**: call `SLY_AccountingDate::getForCustomerInvoice()`, `getForSupplierInvoice()`, or `getForExpenseReport()` to get the accounting date as a Unix timestamp.

## Database

- Shipment dates: `llx_expedition_extrafields.ata`, `llx_expedition_extrafields.eta`. These are already part of SLY’s expedition_extrafields; ShipsGo updates (cron and manual “Update Ships”) write `ata` from API `ArrivalDate` and `eta` from API `Eta`/`EstimatedArrivalDate` when present.
- Links: `llx_element_element` (invoice ↔ expedition, invoice_supplier ↔ order_supplier, order_supplier ↔ commande, commande ↔ expedition).
