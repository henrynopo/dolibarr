# ChangeLog — EmbeddedBookkeeping

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] — 2026-09-21

### Added
- **"Accounting entries" tab** (Odoo-style) on customer invoices, supplier
  invoices and expense reports, registered via the descriptor (`$this->tabs`,
  requires module disable → re-enable to take effect):
  - Section A — per-line account table: bound (fk_code_ventilation), suggested
    (core `AccountingAccount::getAccountingCodeToBind()`) or missing, one row
    per document line.
  - Section B — N-row entry form: counter row (thirdparty/employee account) +
    per-line rows (fk_docdet = line rowid) + VAT rows grouped by tax account;
    live debit/credit totals; posts to the tab itself (own token), never to
    card.php hooks.
  - Section C — posted entries grouped by piece_num, linked to core
    `accountancy/bookkeeping/card.php?piece_num=N`.
- `EBKEntryDraft` value object (N signed rows, balance-validated) and
  `EBKBookkeepingWriter::writeEntry()`: one outer transaction, piece_num left
  to core `BookKeeping::create()` with a same-piece assertion + rollback
  (guards `ACCOUNTANCY_ENABLE_FKDOCDET` fragmentation), defensive
  `price2num(...,'MT')` balance check.
- `EBKTabData` read-side helper: one JOIN per render (line accounts + chart
  rowid joins), counter account, VAT grouping via core `getTaxesFromId()`,
  constructively balanced default draft (stored per-line totals, NPR lines
  carry their taxes, residual folded onto the last line with a warning).
- AI prefill button inside the tab (permission + provider-gated); AI is now a
  pure optional accelerator.
- Accounting date is editable in the entry form with semantic related dates:
  expense reports offer document / validation / approval / payment date
  (payment from llx_payment_expensereport); customer invoices offer document /
  due date / Incoterm-derived revenue-recognition date (D-group terms →
  shipment's planned delivery date, E/F/C-group → real shipment date). A free
  date input allows any override. The DEFAULT source per document type is an
  admin preset in setup (`EMBEDDEDBOOKKEEPING_DEFAULT_DATE_{CUSTOMER|SUPPLIER|
  EXPENSE}`, seeded to `document`; unavailable presets fall back to the
  document date).
- Invoice-type-specific recognition dates: deposit invoices additionally offer
  the payment (cash receipt) date; credit notes and replacement invoices offer
  the source invoice date (restatement semantics). Per-type admin presets
  (`EMBEDDEDBOOKKEEPING_DEFAULT_DATE_{DEPOSIT|CREDIT_NOTE|REPLACEMENT}`) take
  precedence over the generic customer/supplier preset; deposit seeds to
  `payment`, the others to `document`.

### Fixed
- **Manual bookkeeping was impossible without a working AI provider** (the
  reported "no AI → no bookkeeping" bug): the confirmation modal never reached
  the DOM (written to `$parameters['formConfirm']` which is passed by value)
  and its only opener JS was emitted for `ai->suggest` users alone. The whole
  modal path was replaced by the tab form, which never depends on AI.
- Expense-report eligibility used non-existent `ExpenseReport::STATUS_PAID`;
  replaced with `STATUS_CLOSED` (v22 constant for "paid/settled").
- Paid expense reports were rejected as "not eligible": ExpenseReport exposes
  its status as `$fk_statut` (no `$statut` property exists), so the eligibility
  check read -1 for every expense report. Both the tab page and the card
  button trait now read `fk_statut` for expense reports.
- Fatal `Undefined constant stdClass::TYPE_DEPOSIT` when the
  `ACCOUNTING_ACCOUNT_{CUSTOMER|SUPPLIER}_DEPOSIT` constant is set: core
  `getAccountingCodeToBind()` reads `$facture::TYPE_DEPOSIT` and may do
  `new $facture($db)`, and its v22 signature type-hints the line argument —
  the tab's lightweight objects now use real `Facture`/`FactureFournisseur`
  and `FactureLigne`/`SupplierInvoiceLine` instances (unfetched) instead of
  stdClass.
- **Multicurrency metadata was wrong on foreign-currency invoices**: the
  writer stored the entity-currency (SGD) amount into
  `multicurrency_amount` while labelling it with the foreign code — sfrs
  reports' functional-currency filter therefore dropped EBK rows. Both
  `writeEntry()` and `writePair()` now write `entity amount / invoice
  multicurrency_tx` (rate 1 fallback), matching the FEC / sfrs
  `bookkeepingCreateBefore` convention. Existing mislabelled rows are NOT
  auto-migrated — see docs if a data fix is needed.

### Changed
- Card buttons collapsed to ONE deep-link button ("Bookkeeping" / "View
  bookkeeping" once posted); the entry form, AI prefill and posting all live
  in the tab.
- `EBKWriteResult` extended with `$rowids[]` / `$nb_lines` (old fields kept;
  `writePair()` fills them too).
- Journal-code resolution extracted into `EBKBookkeepingWriter::journalCodeForDocType()`
  shared by `writePair()` (unchanged behaviour) and `writeEntry()`.
- `EBKBookkeepingCreated` message no longer says "(debit + credit rows)" —
  entries can be N rows now.

### Removed
- `class/ActionsEmbeddedBookkeepingConfirmTrait.php` (modal never reached the DOM),
  `class/ActionsEmbeddedBookkeepingDoActionsTrait.php` (action producers removed),
  `class/ActionsEmbeddedBookkeepingAiJsTrait.php` (AI fetch rewritten inside the
  tab targeting real form fields). Hook aggregator shrinks to
  `addMoreActionsButtons` only.

### Migration
- Disable → re-enable the module so `insert_tabs()` writes the
  `MAIN_MODULE_EMBEDDEDBOOKKEEPING_TABS_0..2` constants. No data migration;
  existing 2-row writePair entries display unchanged in the tab's posted list.

## [Unreleased] (1.0.x line, merged into 1.1.0 above)

### Added
- REST API class `api_embeddedbookkeeping.class.php` for AI agent / external
  system access (check / suggest / create / listAccounts).
- `EMBEDDEDBOOKKEEPING_JOURNAL_EXPENSE` constant (default `EX`) for the
  new expense-report entry point.
- Permission descriptor `[2]` (type char, deprecated-but-parity) and `[3]`
  (`default_perms = 0`) per project CLAUDE.md §2 — admin auto-grant still
  flows through `DolibarrModules::insert_permissions(1, …)`.
- `expensereportcard` hook context: button + modal + AI flow on
  `expensereport/card.php`.

### Changed
- `EBKEntryProposal::validate()` accepts `expense_report` in addition to
  `customer_invoice` / `supplier_invoice`.
- `EBKBookkeepingWriter::resolveJournalCode()` picks the journal code by
  `doc_type` (sales / purchases / expense).
- `ActionsEmbeddedBookkeepingUiTrait` / `ConfirmTrait` / `DoActionsTrait` /
  `AiJsTrait` all guard `currentcontext` against the three card contexts.
- AJAX `suggest_entries.php` accepts `side=expense` and loads
  `ExpenseReport`.
- `admin/setup.php` exposes the expense-journal field on the General tab.

### Security
- All `$_POST` reads go through `GETPOST('param', 'type')` — never raw
  array access. SQL strings pass through `$db->escape()` for user data
  and `(int)` casts for numeric ids.
- `token` validation runs in both `doActions` (form submit) and the AJAX
  endpoint (AI suggestion), with `newToken()` regenerated per render.
- IDOR defense: `fk_doc` POSTed by the client must equal the loaded
  `$object->id`; otherwise the request is rejected with
  `ErrorBadFkDocForBookkeeping`.
- Already-posted guard (`EBKBookkeepingAlreadyDone::existsFor`) blocks
  duplicate `piece_num` rows in the core `llx_accounting_bookkeeping` table.

## [1.0.0] — 2026-09-19

### Added
- Initial module: hooks on customer + supplier invoice cards.
- Modal-based bookkeeping entry with manual + AI-assisted paths.
- AI provider abstraction: `ai_module` (Dolibarr core `Ai::generateContent`)
  and `claude` (direct Anthropic Messages API). `NullProvider` when
  disabled.
- Permission model: `read` / `write` / `ai->suggest` / `admin->setup`
  (auto-granted to admin on enable).
- Multicompany safe: every native-table SELECT includes `entity IN (0, X)`.
- Per-row `extraparams` audit blob (`ebk_source`, `ebk_form_version`,
  `ebk_entity`, `ebk_module`, `ebk_confidence?`, `ebk_rationale?`).
- zh_CN + en_US translations.
