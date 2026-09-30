**2.1.6**
* FIX Compatibility v24: select_company()/select_thirdparty_list() no longer accept legacy raw-SQL
  filters ('client>0', 'fournisseur=1') — Dolibarr 24 removed the fallback and embeds the literal
  error string "Filter error - Bad syntax of the search string" into the SQL, killing invoice/propal/
  order cards with DB_ERROR_SYNTAX. Filters now use the Universal Filter Syntax
  ('(s.client:>:0)', '(s.fournisseur:=:1)') on Dolibarr >= 18, legacy strings kept for older versions.

**2.1.5**
* FIX Compatibility v19
* ADD class (fa-pencil-alt) to display "Modifier" icon
* ADD sql query to update order_supplier v<14 
* ADD sql query to update invoice_supplier

**2.1.4**
* FIX Compatibility v18

**2.1.3**
* ADD V17 Compatibility
* ADD option to change thirdparty on all other invoices status (only Draft before)

**2.1.2**
* FIX name
**2.1.1**
* FIX can no longer change third parties after sending an email
**2.1.0**
* ADD Event bind of changetiers
**2.0.1**
* FIX Create supplier invoice from supplier order. We have an id instead of a facid.
* FIX Edit ref_client or project on invoice. We have an id instead of a facid/ref.

**2.0.0**
* ADD Right on each action
* ADD lock on third parties modification on invoice card. Only possible on draft invoice
* ADD change third parties on reception card
* FIX SQL error when navigating with the arrows in the customer's invoice and making a third party change
* Compatibility Easya (fa-icon & module classification)

**1.6.0**
* ADD change third parties on contract

* **1.5.1**
* ADD Compatibility V15
* FIX change third parties on shipping card
* FIX change third parties on supplier card

**1.5**
* FIX Compatibility v10

**1.4**
* FIX Compatibility v8

**1.3**
* ADD change third parties in expedition card

**1.2**
* FIX Supplier invoice bug correction

**1.1**
* ADD Delete external contact linked

**1.0**
* The end of the beginning
