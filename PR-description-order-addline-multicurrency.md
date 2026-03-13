# Instructions
*This is a template to help you make good pull requests. You may use [Github Markdown](https://help.github.com/articles/getting-started-with-writing-and-formatting-on-github/) syntax to format your issue report.*
*Please:*
- *only keep the "FIX", "CLOSE", "NEW", "UIUX", PERF" or "QUAL" section* (use uppercase to have the PR appears into the ChangeLog, lowercase will not appears)
- *follow the project [contributing guidelines](/.github/CONTRIBUTING.md)*
- ***in particular, in case of a bugfix, please check that you are targetting the branch corresponding to the oldest version in which the bug occurs***
- *replace the bracket enclosed texts with meaningful information*


# FIX|Fix Sales order add line shows 0 when only multicurrency unit price is filled

When adding a product line to a sales order in multicurrency mode, if the user fills only the "UP currency" (multicurrency unit price) field and not the local unit price, the new line was saved with price 0 and the user had to edit the line again.

**Cause:** In `htdocs/commande/card.php`, when a product is selected (`idprod > 0`), the code only overrode `pu_ht`/`pu_ttc` from the local price fields (`price_ht`/`price_ttc`). The form values for `multicurrency_price_ht`/`multicurrency_price_ttc` were never applied to `pu_ht_devise`, and `pu_ht` was not derived from the currency price using the order rate.

**Fix:** When the user enters `multicurrency_price_ht`, set `pu_ht_devise` and, when local price is empty/zero, derive `pu_ht` from `pu_ht_devise / multicurrency_tx` (same convention as `core/lib/price.lib.php` `calcul_price_total`). Same for `multicurrency_price_ttc` → `pu_ttc_devise` and `pu_ttc`.

**Testing:** Create a sales order with a foreign currency, add a product line by filling only the "UP currency" field and qty, click Add — the new line should show the correct unit price without needing to edit again.
