# SLY Custom — Dolibarr upgrade compatibility checklist

Verified against: **22.0.4** (source-level, 2026-09). For each future major
(23.0 / 24.0), walk this list against `install/mysql/tables/` and the class
sources of the target version BEFORE deploying. 30 minutes, and it is the
difference between "strictly compatible" and "probably compatible".

## 1. Raw SQL — core tables and columns used by the module

Any rename/removal here breaks silently (raw SQL does not throw on missing
WHERE columns — it returns wrong data). Check each table definition:

| Table | Columns used | Used by |
|---|---|---|
| llx_facture | rowid, ref, fk_soc, total_ttc, multicurrency_code, multicurrency_tx, paye, fk_statut, type, entity, **datef** (was `date` before 22) | Wise matching/record |
| llx_paiement_facture | fk_facture, amount | remaining calculations |
| llx_commande | rowid, ref, ref_client, fk_soc, entity | Wise SO matching |
| llx_commande_fourn | ref_supplier (flow A, upcoming) | Wise transfer reference |
| llx_element_element | fk_source, sourcetype, fk_target, targettype (relationtype) | SO↔invoice, PO↔supplier invoice links |
| llx_c_paiement | id, entity, code, libelle, active, position | payment mode lookup |
| llx_bank_account | rowid, ref, label, currency_code, clos, entity | Wise bank mapping |
| llx_societe | rowid, nom | candidate labels |
| llx_const | name, value, entity | config consts, WISE_PROFILE_ID routing, COMMANDE_*_MASK |
| llx_expedition | tracking_number, entity | ShipsGo |
| llx_boxes_def / llx_boxes | file, fk_box | before deleting unused box files |

## 2. Core class APIs the module calls

Signature changes throw loudly (good) — smoke-test the reconcile page and one
payment recording after upgrade:

- `Paiement::create($user, $closepaidinvoices = 0, $thirdparty = null)` and the
  `amounts` / `multicurrency_amounts` / `multicurrency_code` / `getWay()` logic
- `Paiement::addPaymentToBank($user, $mode, $label, $accountid, $emetteur_nom, $emetteur_banque, ...)`
- `Facture` auto-close via `$closepaidinvoices = 1`
- ` Expedition / ShipmentStatus` extrafields writes (ShipsGo)

## 3. Module descriptor mechanics

Stable historically, but verify after upgrade:

- `module_parts.hooks.data` contexts registered in llx_const
  (`MAIN_MODULE_SLYCUSTOM_HOOKS`) — new contexts require merge on activation
- `cronjobs` rows inserted on activation; cron method jobs receive
  comma-split parameters and assign `->entity` on the object
- `menu` entries / `rights` rows inserted on activation

## 4. Data-level dependencies (NOT code — live in the database)

- Computed extrafield expressions follow **dol_eval mode '2' grammar**: no
  `??`, no infix grouping parentheses, no nested direct function calls, single
  space-padded ternaries, single leading `isset(...)` guards. The grammar has
  tightened across versions before — re-validate all `llx_extrafields.fieldcomputed`
  entries after an upgrade (see COMPUTED-FIELD-USAGE.md).
- Numbering masks (`COMMANDE_<NAME>_MASK`): placeholder syntax from
  `get_next_value()` — Wise SO-pattern derivation reads it.

## 5. Page-level helpers used

`llxHeader/llxFooter`, `newToken()`, `GETPOST` (int/array/alphanohtml/aZ09
filters — NOT `GETPOSTINT`, absent before 22), `$user->hasRight()`,
`accessforbidden()`, `dol_escape_htmltag()`, `price()/price2num()`,
`img_picto/img_warning`, `selectyesno`, `textwithpicto`.

## 6. External (version-independent, but re-test on upgrade)

- Wise API surface (profiles/balances/statements) and webhook payloads — see
  wise-integration notes in README §5.
- ShipsGo v2 API.
