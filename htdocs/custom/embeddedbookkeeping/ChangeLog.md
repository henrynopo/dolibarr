# ChangeLog — EmbeddedBookkeeping

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- **The knowledge base was written from model memory and contained real errors.**
  Every tax and standards figure in `knowledge/` has now been checked against IRAS
  and ACRA (2026-10-09). What actually changed:
  - `04-iras-income-tax.md` — **corporate tax was described with the individual income
    tax bracket table** ("first S$10,000 at 8%, capped at S$22,000"). That is the
    personal income tax schedule and has nothing to do with companies. Companies are
    taxed at a **flat 17%** of chargeable income; the file now says exactly that and
    carries an explicit do-not-cite warning on the old table.
  - `04-iras-income-tax.md` — **both partial-exemption schemes were wrong.** The file
    said "first S$100,000 exempt, next S$200,000 at 10%, S$400,000 cap". Verified:
    *New Start-up* exemption is first S$100,000 **75% exempt** + next S$100,000
    **50% exempt**, cap **S$125,000/yr**; the *Partial* exemption for all companies is
    first S$10,000 75% exempt + next S$190,000 50% exempt, cap **S$102,500/yr**.
  - `04-iras-income-tax.md` — **the "file within 3 months" deadline was assigned to the
    wrong filing.** 3 months from financial year end is the **ECI** (estimated
    chargeable income) deadline; the corporate tax return (Form C-S / C-S (Lite) / C)
    is due **30 November** every year, even for loss-making companies.
  - `04-iras-income-tax.md` — **Pillar Two start date was "自 2026 税务年度起"**; IRAS
    states IIR and DTT apply to **financial years starting on or after 1 Jan 2025**,
    gated on the UPE's consolidated revenue reaching **€750m** in at least 2 of the
    preceding 4 years.
  - `04-iras-income-tax.md` — stamp duty 0.2% on the higher of price or NAV was right,
    but the file omitted who pays (the transferee), the 14-day e-Stamping deadline,
    the fact that **new share issues are not chargeable**, and the S$200,000/yr
    M&A remission cap. All added.
  - `05-sg-frs-standards.md` — **the entire small-entity section was about the wrong
    jurisdiction.** "FRS 105 Section 1A / Section 2, S$500,000 turnover" is the **UK**
    FRS 102 Section 1A / FRS 105 regime. **Singapore has no FRS 105 and no S$500,000
    threshold.** The correct instrument is **SFRS for Small Entities** (based on IFRS
    for SMEs): not publicly accountable **plus** at least 2 of 3 of revenue ≤ S$10m,
    gross assets ≤ S$10m, employees ≤ 50. Section rewritten, with a warning not to
    quote the UK standard.
  - `05-sg-frs-standards.md` — the current edition of SFRS for SE applies to periods
    beginning on/after **1 Jan 2024** (and before 1 Jan 2027); the **Third Edition**
    applies from **1 Jan 2027**. Added, plus the distinction between SFRS for SE
    eligibility and the separate Companies Act small-company audit exemption.
  - `05-sg-frs-standards.md` — **every standard number was written as "SFRS nn", but
    Singapore uses the FRS series for local reporting.** Each heading now carries both
    designations: FRS 15 / SFRS(I) 15 (revenue), FRS 109 / SFRS(I) 9 (financial
    instruments), FRS 2 (inventory), FRS 37 (provisions), **FRS 16** (property, plant
    and equipment) and **FRS 116** (leases). A new numbering table was added with an
    explicit trap warning, because **"16" means two different things in the two series**:
    `FRS 16` is *property, plant and equipment* while `SFRS(I) 16` is *leases*. The
    previous text said leases were governed by "SFRS 16", i.e. it pointed at the PPE
    standard. FRS 116 replaced the old FRS 17 with effect from 1 Jan 2019.
  - `02-sg-account-mapping.md` — `kb:sources` previously carried **no URL at all**, so
    a depreciation or fixed-asset question produced an answer with nothing to verify
    against. Now points at the ACRA FRS catalogue and the IRAS e-Tax guide on FRS 116,
    and the fixed-asset section states the FRS 16 depreciation rules (cost less residual
    value over useful life; method must reflect consumption, not merely usage; residual
    value and useful life reassessed at least each year-end with changes applied
    prospectively; land not depreciated; fully-depreciated assets still carried at cost
    less accumulated depreciation with no further charge).
  - `05-sg-frs-standards.md` — the SFRS 37 contingent-liability rule was stated as
    "不计提，除非流出可能性大于 50%" which inverts the disclosure logic. Corrected:
    a provision needs "more probable than not"; a contingent liability is **disclosed**
    in the notes unless its possibility is **remote**.
  - `03-iras-gst.md` — **the ITC documentation threshold was stated as "发票 ≥ S$100
    必须保留税发票原件"**, which is not an IRAS rule. Replaced with what the IRAS page
    actually says: invoices **over S$1,000** require a full tax invoice,
    **S$1,000 and below** may use a simplified tax invoice, plus the entertainment
    concession. The widely-quoted S$75 claim could not be confirmed on the IRAS site
    and is now flagged as unverified rather than asserted.
  - `03-iras-gst.md` — **"零税率…不计入应税营业额" was wrong.** **Zero-rated supplies DO
    count** toward the S$1m registration threshold; only **exempt** supplies are
    excluded. Getting this backwards can make a company wrongly conclude it is below
    the threshold. The two categories are now separated and the distinction called out.
  - `03-iras-gst.md` — the "overseas customers, low-value goods ≤ S$100" figure was a
    conflation. The **LVG threshold is S$400 per item**; **S$100,000** is the
    *overseas vendor's* supply threshold, paired with **> S$1m global turnover** as a
    dual test; and **reverse charge** (not a value threshold) is what applies when a
    local GST-registered business buys remote services from an overseas vendor.
  - `03-iras-gst.md` — record retention is confirmed as **5 years**, but the page does
    **not** state the date the 5-year rule took effect, so the previously asserted
    "自 2025 年 1 月 1 日起由 7 年缩短为 5 年" was dropped. Also added: GST records run
    5 years from **end of accounting period**, CIT records 5 years from the **YA**.
  - `03-iras-gst.md` — added the GST registration retrospective/prospective bases,
    the 30-day application window, and the prospective-basis grace period
    (liability arising on/after **1 Jul 2025** → registered 2 months after the
    forecast). Added the warning that **voluntary** registration locks you in for
    **2 years** while compulsory registration does not.
  - `03-iras-gst.md` — international zero-rating now cites the actual statutory test
    (GST Act **s.21(3)**: contractually supplied to an overseas person and directly
    benefiting an overseas person and/or a Singapore GST-registered person) instead of
    saying "绝大多数国际服务".
  - `01-dolibarr-accounting.md` — the journal-code list presented `VT`/`AC`/`BK`/`CA`/
    `SC`/`EX` as if they were core defaults. They are **not**: journal codes are rows
    in `llx_accounting_journals` and the journal pages receive `code_journal` as a URL
    parameter, so no default is hardcoded anywhere except `bookkeeping.class.php:1985`
    (`'VT'`). The file now says to look the real codes up in 会计 > 设置 > 日记账, and
    flags that `EX` is EBK's suggestion, not a fact about the user's database.
  - `01-dolibarr-accounting.md` — added a "本模块配置页该怎么设置" section answering
    the single most-asked question on that page (which the assistant previously could
    not answer well), ordered so the user checks their real journal codes first.
  - Every file now carries a **已核对日期** line and a **来源** block listing the exact
    IRAS/ACRA URL behind each figure, so the next reviewer can re-verify instead of
    trusting the prose.
- **"我这里应该怎么设置" retrieved no reference material at all** — caught by the
  post-rewrite dry-run. `knowledge/01-dolibarr-accounting.md` had no keyword for
  设置 / 配置 / 怎么设置, so the single most-asked question on the setup page (the
  question the assistant was failing on in production) scored **zero** across the
  whole library and got no context at all. The keyword list now covers
  设置 / 配置 / 怎么设置 / 如何设置 / 初始化 / 启用 / setup / configure.
  The question now resolves to file 01, which gained a dedicated
  "本模块配置页该怎么设置" section answering it step by step.
- **`EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS` was not a real cap.** The selection
  loop read `if ($used > 0 && ($used + strlen($body)) > $budget) continue;`, which
  unconditionally waved the **first** file through, and one file alone can be up to
  `MAX_PER_FILE` (2600) chars. Measured: a question matching all six files injected
  **5415 characters against a 1500-character budget — 3.6× over**, and would keep
  growing as the knowledge files get longer. The first file is now admitted only if
  it fits, and when the budget is smaller than the single most-relevant file that
  file is truncated at a section boundary instead of being dropped entirely (dropping
  it would leave a matched question with no reference at all).
- **The budget constant was read two different ways.** `buildBlock()` reads the
  enable/disable switch through `getDolGlobalInt()` but `maxChars()` read
  `$conf->global->…` directly. Both work in production, but the direct read bypasses
  Dolibarr's `$_SESSION` "override constant" mechanism, so an admin testing a
  different budget in Setup got the old value at runtime. Both now go through
  `getDolGlobalInt()`.
- **The verified source URLs never reached the model at all.** The fact-check above
  grew the knowledge files from ~2.5KB to 4-7KB, but `MAX_PER_FILE` was still 2600
  and `truncate()` cuts at a section boundary, so **50-70% of every long file was
  silently discarded** — `03-iras-gst.md` kept only **31%**. Because the 来源 block sat
  at the very end, **all 17 verified IRAS/ACRA URLs were thrown away**, which defeats
  the whole point of citing them: the assistant had no page to send the user to.
  Fixed in four parts:
  - `MAX_PER_FILE` 2600 → **4000**, so most files survive whole.
  - New optional `<!-- kb:sources=… -->` meta line, parsed separately by `parse()`
    and rendered by `buildBlock()` as a **人工复核入口** footer *after* selection —
    so it is never truncated away. Appending it inside the body was the first attempt
    and still lost every URL, because `truncate()` cuts at the last `## ` heading and
    the footer sits after it.
  - Sources are **excluded from the character budget**, since the admin cannot tune
    them. **Trade-off, measured:** a two-file answer now runs ~6.9k-8.3k characters
    against the 6000 default. That is the price of the footer; admins who care more
    about prompt size than about handing the user a link can lower
    `EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS` further.
  - The trailing `## 来源` body section is now stripped by `stripSourceSection()`
    instead of being duplicated by the footer.
- **A second matching file was being dropped entirely (regression from the above).**
  With bodies grown, `select()` skipped any file that did not fit the remaining
  budget. "供应商发票怎么记账？" and "凭证借贷不平怎么办？" both tie at score 3, and the
  general Dolibarr manual won the tie and filled the budget — so the assistant was
  answering a **科目映射** question, and a **凭证自查** question, out of the *manual*.
  A partial file now beats no file: it is truncated to the room instead of skipped,
  provided at least `MIN_USEFUL_CHARS` (1200) remains. Below that it stops, because
  a 200-char fragment is worse than nothing — the model may still treat it as the
  whole document and answer confidently from it.
- **`sources` was dropped when building the scored list**, so the footer rendered
  empty even with the wiring in place. Caught by the same dry-run.
- **THE production "AI 无法回答" root cause: PHP was killing the request mid-flight.**
  The LLM call is allowed to run for **90 seconds** (`EBKAiChatHelper`), but neither
  Dolibarr core nor this module ever raises PHP's `max_execution_time`, so the host
  default applies (commonly 30-60s on shared/cPanel hosting). On any such host **every
  single question was killed before the response was written**. `widget.js` then saw
  HTTP 200 with a truncated body, `JSON.parse()` threw, and it displayed its fallback
  string — "⚠ 无法回答，请检查 AI 配置" — which blames a configuration that was in fact
  fine and sent the user hunting through the setup page. That fallback string is itself
  the tell: the module's own failure text is returned as valid JSON and renders
  normally, so seeing the generic message meant PHP died, not that the AI was
  misconfigured. `chat.php` now requests a 300s budget.
- **A PHP fatal on this endpoint produced no usable output at all.** A
  `register_shutdown_function` responder now emits valid JSON carrying the real
  `error_get_last()` message and file:line, so any future fatal is diagnosable from
  the browser instead of degrading to the generic message. All six early-return
  branches were routed through a single `ebkChatJsonExit()` helper that flips the
  response flag — without it the shutdown handler would have appended a **second** JSON
  document to an already-written response. Verified by simulation: the normal path and
  each early exit emit exactly one document, and a deliberately triggered fatal
  produces parseable JSON. **Caveat:** this only helps when the host has
  `display_errors=Off` (Dolibarr's production requirement). With `display_errors=On`
  PHP prints the raw error *before* the response body and the JSON stops parsing.
- **The assistant was never audited.** The guard read
  `if (!empty($user->id) && function_exists('ai_log_request'))` and only reached
  `require_once ai/lib/ai.lib.php` *inside* that block — but nothing on the EBK path
  loads `ai.lib.php`, so `ai_log_request` did not exist yet, the condition was always
  false, and **every EBK question was silently missing from `llx_ai_request_log`**. The
  admin audit page looked healthy and simply had no EBK rows, which is what lets a gap
  like this survive. The require now happens before the test.
- **The assistant never appeared outside three card pages — root cause found and
  fixed.** `printCommonFooter` is the right hook and `llxFooter()` calls it
  unconditionally, but `HookManager::initHooks()` only *instantiates* a module's
  Actions class for page contexts present in that module's declared hook list
  (or the literal `'all'`). The descriptor declared bare context names
  (`invoicecard`, `invoicesuppliercard`, `expensereportcard`), and
  `printCommonFooter` is a *hook name*, not a context — so on every other page,
  including the whole core Accountancy module, the class was never constructed,
  `executeHooks('printCommonFooter')` found no handler, and the widget was simply
  absent. The widget the user had been testing on came from the direct `<script>`
  include inside `tabs/bookkeeping.php`, which is why it looked like it worked.
  `'all'` is now declared in `modEmbeddedBookkeeping`.
  **Deployment note:** module constants are written by `insert_module_parts()`
  from `_init()` at *enable* time, so the module must be disabled and
  re-enabled once — uploading the file is not enough.
- **The panel came back on every navigation but always empty and collapsed.**
  The open/closed flag is now remembered in `localStorage` under
  `ebk_ai_panel_open` and the panel re-opens automatically on the next page.
  The conversation is deliberately NOT restored — closing the panel wipes it,
  as agreed.
- **CSS root selector was emitted as `#ebk-ai-{…}`.** The stylesheet built the
  root selector from a prefix constant that already ended in `-`, so the rule
  matched nothing: valid CSS, zero effect. `position:fixed` and `z-index` were
  therefore never applied and the whole widget fell into normal document flow,
  off-screen. The selector is now spelled out in full.
- **`widget.js` was served stale from the browser cache**, so after an upload the
  old code kept running and updates appeared not to take effect. The hook now
  appends `?v=<filemtime>` to the script URL.
- **Query-string secrets could leak into the LLM prompt.** The accounting context
  builder dropped a fixed three-key deny list (`token` / `saction` / `page_y`),
  so `token2`, `csrf_token` or `sid` went straight into the prompt. Filtering is
  now shape-based: any key that looks like a secret or a session is dropped
  whatever it is called.
- **AI assistant never saw the current document** (root cause of answers such as
  "我看不到 ER26109 的费用明细"). The widget sent `doc_id` / `doc_type` **nested
  inside** the `context` object, but `ajax/chat.php` reads them from the **top
  level** of the JSON payload — so the server always resolved an empty document
  and the model answered in the abstract. The widget now emits them at top level.
- **`printCommonFooter` receives `$object === null`**, so the hook could not know
  which document was on screen. `ActionsEmbeddedBookkeeping::printCommonFooter()`
  now recovers it from `SCRIPT_NAME` + `GETPOST('id')` for
  `compta/facture/card.php`, `fourn/facture/card.php` and `expensereport/card.php`,
  and reads the reference through a new private `fetchDocRef()` (one tiny query).
- **`GETPOST('id')` inside an AJAX endpoint reads the AJAX request's query
  string, not the page the user is looking at.** On a card page the user sees
  `/accountancy/bookkeeping/card.php?id=42`, but the POST body carries no such
  query, so the voucher resolved as "未指定凭证号". Both the voucher and the
  accounting-account readers now parse the id out of the **page URL** via the new
  `paramFromPageUrl()`.
- **`Bookkeeping::fetch()` leaves `linesmvt` empty.** The core card page loads
  movements with a second call (`accountancy/bookkeeping/card.php` does exactly
  `fetchAllPerMvt($piece_num, $mode)`); without it every voucher would have been
  reported as having no lines at all.
- **Wrong permission path in `ajax/chat.php` and `ajax/suggest_entries.php`**:
  `$user->rights->embeddedbookkeeping->ai->suggest` never resolves — Dolibarr joins
  `rights[4]` and `rights[5]` with `_`. Corrected to `ai_suggest`; non-admin users
  were being rejected with HTTP 403.
- **`Candidate::select_degrees()` XSS / JS-breakage**: the `rect_degrees` label was
  interpolated unescaped into both an `<option>` and (via `recruitment.js.php`) a
  JavaScript single-quoted string literal — an apostrophe in a label produced
  `Uncaught SyntaxError: missing ) after argument list`. Labels are now escaped with
  `dol_escape_htmltag()` and rowids cast to int; `recruitment.js.php` wraps its
  injected output in `dol_escape_js()`.

- **`selText` wiped before it was sent.** When sending, the widget cleared the
  stored selection *before* reading it into the payload, so selected-text context
  always arrived empty. It is now snapshotted first, then reset.
- **Chat messages rendered as unstyled bare text** (no bubbles, no avatars,
  "我"/"AI" duplicated above the text). The stylesheet was written with **ID
  selectors** (`#<chatid>-msg`, `#<chatid>-bubble`, …) while the message nodes
  are built with **class names**, so not one message rule ever matched. Only the
  uniquely-identifiable elements (FAB, panel, header, composer — which do carry a
  matching `id`) were styled, which is exactly why the header looked right and the
  conversation did not. Everything inside the message list is now styled through
  stable `ebk-ai-*` classes; the convention is documented at the top of
  `js/widget.js` so it does not regress.
- **Header title and page label ran together on one line** ("AI 助手pg_expense ·
  ER26109") because both are inline `<span>`s. They now sit in a flex column.
- **Removed the duplicated sender name** ("我" / "AI" appeared twice per message,
  once in the avatar and once in a name label). Alignment, colour and avatar now
  identify the speaker on their own.

### Added
- **No web-search capability, by decision.** The chosen workflow is "offline knowledge
  base + human verifies on iras.gov.sg". Recording here why nothing was built, since
  it is a limitation a future maintainer will otherwise try to "fix":
  - `UniversalLLMAdapter::generate()` sends only a system and a user message to all
    three providers. Core has **no `tools` array and no function-calling**, and its 8
    MCP tools in `ai/tools/` are CRUD/reporting — none is web search.
  - Core's MCP support is **server-only** (`McpHandler` loads tools *contributed by
    modules* via the `addMcpTools` hook). There is **no MCP client**, so no external
    MCP server could be reached either.
  - A search path would have to be hand-built on `getURLContent()`, which does ship
    SSRF guards, and would then face four problems of its own: SSRF reach, prompt
    injection from fetched pages, exfiltration of company data through search
    queries, and no audit trail. Those are not solvable by a thin wrapper.
  - In its place, every knowledge file carries structured `kb:sources` meta, and the
    assistant is required to hand the user the exact official page to open when it
    quotes a rate, threshold or deadline.
- **Offline knowledge base for the assistant** — Dolibarr accounting usage,
  Singapore accounting standards (SFRS / FRS), IRAS GST, IRAS corporate income
  tax and stamp duty, common account-mapping conventions, and a voucher
  self-check list ship as markdown in `knowledge/`. `EBKKnowledgeBase::select()`
  scores each file against the user's question (keyword in the question = 3
  points, keyword in the on-screen page context = 1) and injects only the
  matching files, up to 4 files and a character budget
  (`EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS`, default 6000). A question that
  matches nothing injects nothing, so unrelated questions cost no tokens.
  Company-specific rules go in the new
  `EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA` textarea on the setup page and are
  always injected, unscored. The system prompt now requires the assistant to
  cite the source title, to warn that rates and deadlines must be checked
  against the latest IRAS/ACRA notice, and never to invent an account number,
  a rate, a deadline or a standard clause.
- **The assistant now works inside the core Accountancy module.** With `'all'` in
  the hook list the widget is injected on every page, and `buildAccountingBlock()`
  feeds the model the object actually on screen: a voucher
  (`accountancy/bookkeeping/card.php`) with its journal, date, source document,
  third party, every movement line (account / label / 摘要 / debit / credit /
  subledger), the debit-credit totals and an explicit imbalance warning; a
  chart-of-accounts entry (`accountancy/admin/account.php`); or, on any other
  accounting screen, the path plus the active filters. Movements are loaded
  through the core `Bookkeeping` / `AccountingAccount` classes so the entity and
  rights filtering core already applies is reused.
- **Chat turns are written to the core AI audit log.** `ajax/chat.php` now calls
  the core `ai_log_request()` helper (`ai/lib/ai.lib.php`) with provider `ebk`,
  so assistant questions land in `llx_ai_request_log` next to the core AI
  Assistant and the MCP server, and are reviewed in one place
  (`ai/admin/log_viewer.php`). No second log table.
- **The AI panel can now be dragged anywhere and resized.** Drag it by the
  header bar (the close button stays clickable), resize from the new grip in the
  bottom-right corner. Minimum 320×340; position and size are clamped to the
  viewport on every move so the header can never be dragged out of reach, and
  re-clamped when the browser window is resized. The geometry is persisted in
  `localStorage` under `ebk_ai_panel_geom` so it follows the user across pages
  and reloads; **double-clicking the header** clears it and snaps the panel back
  to the default bottom-right anchor. Drag state suppresses text selection, and
  the `mousemove`/`mouseup` listeners are attached on drag start and removed on
  release, so a drag that ends outside the window still terminates.
  Storage being unavailable (private mode) degrades to "applies for this page
  only" rather than throwing.
- **Messenger-style colour separation between speakers**, per request. AI messages
  are white bubbles with a `#dbe2ec` border on the left; user messages are solid
  `#1565c0` bubbles with white text on the right. Both carry a CSS-only tail
  (`::before` triangle) and the bubble corner nearest the avatar is squared off.
  The message area uses a `#eaeef4` wallpaper so the white AI bubbles stand out.
  Speaker name labels were dropped; `code` / `strong` inside user bubbles are
  recoloured for contrast on blue. `ajax/chat.php` gained
  `buildChartBlock()`, which dumps the active chart of accounts
  (`llx_accounting_account`, scoped by `CHARTOFACCOUNTS` and `entity IN (0, current)`
  for multicompany) as `number  label` lines and the system prompt now forbids
  inventing account numbers.
- **Full document context for the assistant.** `buildDocBlock()` renders header
  (type / ref / status / dates / third party / HT-VAT-TTC totals), the bound
  counterparty account, one block per line (description, expense type, qty, HT,
  VAT rate, VAT, TTC, currently bound account, NPR flag) and the grouped VAT
  breakdown. Line data is reused from `EBKTabData::lineRows()` so the numbers the
  model sees are exactly the ones the EBK tab renders.
- `EBKTabData::lineRows()` now also returns `fk_c_type_fees` (expense type), which
  is the strongest signal for picking an account on an expense report.
- LLM timeout raised 30s → 90s: the prompt is now much larger (document + chart).

### Changed
- **Knowledge files now carry their verification links as structured `kb:sources`
  meta** instead of a trailing prose `## 来源` section. `parse()` extracts them and
  `buildBlock()` re-appends them after selection, so the URLs survive truncation and
  reach the assistant on every prompt.
- **The conversation is deliberately NOT persisted, and is wiped on close.**
  Opening and closing the panel now go through `openPanel()` / `closePanel()`;
  `closePanel()` empties the message list, so reopening always starts from the
  empty state. Only the open/closed flag survives navigation — no message text is
  written to storage. Closing with the ✕ or with a second FAB click both clear it.
- **AI chat UI rebuilt for legibility.** Messages now have colour-coded circular
  avatars (grey "AI" left, blue "我" right), mirrored left/right layout, white/blue
  bubbles, a timestamp line, a light message background, a wider panel (400px) and
  an auto-growing input. AI answers get a minimal safe renderer (`**bold**`,
  `` `code` ``, `-` bullets, paragraph spacing) — HTML is escaped first, so only
  our own tags are injected.
- The text-selection context feature is retained, with a more visible amber banner.

### Changed (earlier in this release)
- **ARCHITECTURE: read-only display layer.** The "Accounting entries" TAB is now
  purely a display layer — it reads from `llx_accounting_bookkeeping` (the table
  populated by the core accounting journals: sellsjournal / purchasesjournal /
  expensereportsjournal). It does NOT implement or replicate accounting logic in
  the UI. Users must run the appropriate core journal first; entries appear here
  afterward.
  - Section B entry form (write path) removed from the TAB page.
  - Section A now reads per-line bindings from the bookkeeping rows when available,
    or shows "missing" when the core journal has not run yet.
  - Added "Not yet booked" banner + "Run X Journal" button linking to the correct
    core journal page when no bookkeeping rows exist for the document.

  **Known leftover — NOT yet removed:** the write-side classes
  (`EBKBookkeepingWriter`, `EBKBookkeepingAlreadyDone`, `EBKEntryProposal`,
  `EBKEntryDraft`, `EBKTabData::defaultDraft()`, `relatedDates()`,
  `resolveDateByPreference()`), `ajax/suggest_entries.php` and
  `class/api_embeddedbookkeeping.class.php` are still present and still able to
  post entries. The TAB no longer calls them, but the REST API does. Removing
  them is a breaking change for any existing agent, so it is tracked separately.

### Fixed (earlier in this release)
- **`lineRows()` silent empty results** (removed — logic now lives in the tab page
  directly, which reads bookkeeping bindings when available).

### Added (earlier in this release)
- **Widget i18n — ZH/EN bilingual support.** `widget.js` now detects the
  page language from `document.documentElement.lang` and renders all UI strings
  (FAB tooltip, header, placeholder, empty-state bullets, typing indicator,
  error messages, page-section labels) in the matching language. English users
  see the full English interface; Chinese users see Chinese.
- **Universal floating AI assistant widget** (bottom-right FAB, every Dolibarr page).
  Registered via the EBK module's `$this->module_parts['hooks']` → `printCommonFooter`
  hook — the only hook that fires inside `llxFooter()` on every page. Appears
  for users with AI suggest permission. Captures mouse-selected text as context,
  detects page section automatically, and passes document context (doc_id / doc_type)
  when available. Backed by `ajax/chat.php` → `EBKAiChatHelper` →
  `EBKAiProviderFactory` (works with both `ai_module` and `ebk_custom` providers).
  System prompt limits knowledge to Dolibarr accounting scope and cites official
  wiki links at the end of answers:
  - Module Double Entry Accounting:
    https://wiki.dolibarr.org/index.php/Module_Double_Entry_Accounting
  - Module Accounting Simplified:
    https://wiki.dolibarr.org/index.php/Module_Accounting_Simplified
  - Double Entry (developer):
    https://wiki.dolibarr.org/index.php/Module_Double_Entry_Accounting_(developer)
  New files: `ajax/chat.php`, `class/ai/EBKAiChatHelper.class.php`,
  `js/widget.js` (registered in `$this->module_parts['js']`).

### Removed
- **"AI Prefill" button and its inline JS from the Accounting entries tab.**
  The floating AI assistant widget (bottom-right FAB) now serves the same
  purpose with a better UX — users can ask accounting questions directly from
  any page. The redundant prefill click-handler block (`aiBtn.addEventListener`
  → `suggest_entries.php` fetch) has been removed from `tabs/bookkeeping.php`,
  and the AI Prefill submit button markup was removed in a prior session.

### Fixed
- **Prompt textarea showed literal `\n` (two-char backslash+n) instead of
  real newlines.** `dol_escape_htmltag()` converts `\n` → `\n` (literal)
  by default (`$keepn=0`). Both `EBK_BOOKKEEPING_PROMPT` and
  `EBK_BOOKKEEPING_POST_PROMPT` textareas now pass `$keepn=1` so real
  newlines are preserved inside the textarea, matching what gets saved and
  what the LLM receives at runtime.
- **Provider & AI tab was unreachable from the setup page.** The `$head`
  array passed to `dol_get_fiche_head()` only registered the General tab,
  so the "Provider & AI" tab was never rendered in the tab bar — even
  though its content (provider choice, debug, bookkeeping-suggest prompt
  textareas) was already coded below. Added the missing entry so the AI
  settings are now reachable.

### Removed
- **`ClaudeProvider` and the standalone Anthropic-Claude path.** The independent
  `ClaudeProvider` class (which called the Anthropic Messages API directly,
  bypassing the system AI module) was removed along with its constants
  `EMBEDDEDBOOKKEEPING_ANTHROPIC_KEY`, `EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL`,
  `EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_EN`, `EMBEDDEDBOOKKEEPING_AI_SYSTEM_PROMPT_ZH`,
  the `claude` option in the setup-page provider selector, and the two
  per-language Claude fallback prompt textareas. All LLM traffic now goes
  through the system AI module — Anthropic users should configure their
  OpenAI-compatible endpoint under `ai/admin/setup.php` ("custom" service).
  Existing rows in `llx_const` for the removed keys are NOT auto-purged
  (the module's `remove()` keeps constants on purpose to make re-enable
  seamless); admins may drop them manually if desired.

### Changed
- **AI provider now reuses the system AI module's prompt dispatch.** The
  `ai_module` provider's call to `Ai::generateContent()` was passing arguments
  in the wrong order (`$systemPrompt` ended up in the `$function` slot),
  which meant `AI_CONFIGURATIONS_PROMPT` was effectively never consulted and
  every call went through with an empty prePrompt. The provider now passes
  the canonical function key `'bookkeepingsuggest'` so the core AI module
  dispatches the configured prePrompt / postPrompt. The dead-code
  `resolveSystemPrompt()` private method was removed.
- **`EBKRightAiSuggest` is now auto-granted to admins** (default=1), so the
  AI prefill button works out-of-the-box once an admin enables the module
  and the system AI module is configured.

### Removed
- **Writing the bookkeeping-suggest prompt into the system AI module's
  `AI_CONFIGURATIONS_PROMPT` JSON.** The previous design (and the matching
  "Bookkeeping-suggest" textareas in admin/setup.php) merged our prompt
  into `AI_CONFIGURATIONS_PROMPT.bookkeepingsuggest`. That polluted a
  different module's namespace — CLAUDE.md §1 forbids cross-module
  namespace pollution ("禁用或卸载模块时，绝对不能影响核心系统的运行，
  严禁遗留脏数据") — and made it look in the system AI module's
  custom_prompt.php page as if our bookkeeping prompt were a system
  feature. EBK now owns its own prompt constants
  (`EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE` / `_POST`); the system AI module's
  `AI_CONFIGURATIONS_PROMPT` JSON is NEVER read or written by EBK, and
  the `Ai::generateContent()` call has been replaced with a direct call
  to `UniversalLLMAdapter::generate()` so the dispatcher is no longer in
  the loop either. The runtime helper
  `AiModuleProvider::injectDefaultBookkeepingPromptIfEmpty()` (which used
  to mutate `$conf->global->AI_CONFIGURATIONS_PROMPT` request-locally) is
  also gone — its role is now played by `resolvePrompt()`, a pure
  read-only in-process fallback that doesn't touch any cross-module
  global.

### Added
- **"Bookkeeping-suggest" prompt editor in module setup** (Provider & AI
  tab). Two textareas are written into this module's own
  `EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE` / `_POST` constants. The
  pre/post-prompt fields are the EBK side of a clean split: CREDENTIALS
  (service, key, URL, model) are BORROWED from the system AI module's
  existing setup, while PROMPT is fully owned by EBK. Saving blank
  textareas is allowed — `resolvePrompt()` then falls back to the EBK
  built-in EN/ZH default at request time, so the LLM never receives a
  bare prompt out of the box.
- **`ebk_custom` provider** — a third option in the Provider dropdown
  that uses a fully independent LLM configuration stored entirely in
  this module's own `EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE` / `_KEY` /
  `_URL` / `_MODEL` constants. `ai_module` (the previous default) still
  borrows service / key / URL / model from the system AI module's
  `AI_API_*` setup; `ebk_custom` lets the admin point the bookkeeping
  flow at a completely different upstream (e.g. an internal Claude
  proxy, a self-hosted model behind an OpenAI-compatible gateway) without
  touching the system AI module's own configuration. The KEY is written
  with the `chaine:KEY` type suffix so it is encrypted on disk (same
  pattern as `ai/admin/setup.php`). At runtime `resolveAdapter()` picks
  the right constant namespace based on `EMBEDDEDBOOKKEEPING_AI_PROVIDER`
  — no cross-module reads or writes.
- **The four `EMBEDDEDBOOKKEEPING_AI_CUSTOM_*` input fields are rendered
  unconditionally** in the Provider tab (not gated on the currently
  selected provider), so the admin can always reach them to type
  service / API key / URL / model. The provider dropdown auto-submits
  on change (`onchange="this.form.submit()"`) so switching to
  `ebk_custom` is immediate and unambiguous. Both flows work:
  pre-fill the parameters here and then switch, or switch first and
  then fill. The save handler writes the four CUSTOM_* keys on every
  submit regardless of which provider is currently active, so an
  admin using `ai_module` can stage a future switch to `ebk_custom`
  without losing typed values.

### Changed
- **Provider dropdown no longer auto-saves the form on change.** The
  previous `onchange="this.form.submit()"` (added in the same change
  that made the CUSTOM_* fields always-visible) caused switching the
  provider to commit the entire form — discarding any unsaved prompt,
  key, or URL the admin had just typed. The dropdown is now wired to
  a new GET-only `action=switch_provider` handler at the top of
  `admin/setup.php` that writes ONLY `EMBEDDEDBOOKKEEPING_AI_PROVIDER`
  and redirects back to `setup.php?tab=provider`. CSRF is enforced via
  `newToken()`. The main `action=save` handler no longer touches the
  provider constant at all — the two write paths are now strictly
  disjoint.

### Added
- **"Currently borrowing from system AI module" read-only panel** in
  the Provider tab. When `EMBEDDEDBOOKKEEPING_AI_PROVIDER='ai_module'`
  and the system AI module is enabled, the panel shows the upstream
  service / endpoint / model that EBK will hit (with the API key
  masked as bullets). The panel is purely informational: it never
  auto-populates the four `EMBEDDEDBOOKKEEPING_AI_CUSTOM_*` input
  fields and is unaffected by Save. The four fields stay independent
  and can be pre-filled for a future switch to `ebk_custom`. Lang
  keys: `EBKAiBorrowedPanelTitle`, `EBKAiBorrowed{Service,Key,Url,Model}`,
  `EBKAiBorrowedNotSet`, `EBKAiBorrowedPanelTooltip`.
- **Built-in EN/ZH default prompt for `bookkeepingsuggest`.** Lives in
  `AiModuleProvider::getDefaultBookkeepingPrompt()` and selected by
  `$langs->defaultlang`. Used as the textarea prefill in setup.php (same
  source of truth as the runtime fallback) and as the in-process default
  when both admin-saved constants are empty. The prompt itself is
  GAAP-agnostic — it tells the LLM to infer the company's convention
  from the supplied CHART_OF_ACCOUNTS and LAST_N_BOOKKEEPINGS, not from
  any specific country framework.
- **`AiModuleProvider::resolveAdapter($conf, $provider)`** instantiates
  a `UniversalLLMAdapter` from the active provider's configuration
  (`ai_module` borrows `AI_API_*`; `ebk_custom` uses EBK's own CUSTOM_*
  constants). Returns null when the admin hasn't configured the
  required fields, so the bookkeeping tab cleanly falls back to manual
  entry. Two private helpers `resolveAdapterAiModule()` /
  `resolveAdapterEbkCustom()` factor out the per-provider logic, and
  `buildAdapter()` is the shared constructor wrapper.

### Migration
- `modEmbeddedBookkeeping::init()` runs a one-shot migration on every
  enable: if `AI_CONFIGURATIONS_PROMPT.bookkeepingsuggest` still exists
  in the DB, its pre/post values are copied into the new
  `EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE` / `_POST` constants (only when the
  new constants are still empty, so we never clobber a newer admin edit)
  and the `bookkeepingsuggest` key is then unset from the system AI
  module's JSON. Other function keys in the JSON (textgenerationemail,
  etc.) are left untouched. After the first re-enable following the
  upgrade, the system AI module's custom_prompt.php page no longer shows
  a stray `bookkeepingsuggest` row.
- **AI's `CHART_OF_ACCOUNTS` block now includes `pcg_type='TAX'` accounts.**
  The previous filter (`INCOME / EXPENSE / ASSET / LIABILITY`) silently
  excluded VAT/GST accounts whose `pcg_type` is `TAX` (the Dolibarr enum
  value), so the LLM could never suggest a tax leg. The chart text now
  includes `TAX` so VAT/GST accounts reach the prompt. Companies that
  classify VAT under `LIABILITY` / `ASSET` instead see no change.
- **AI's `LAST_N_BOOKKEEPINGS` lookup is now supplier-aware.** The previous
  read used `$thirdparty->code_client ?: code_compta`, which is empty for
  suppliers and therefore never matched bookkeeping rows for them. The
  read now collects every non-empty code candidate (`code_client`,
  `code_compta`, `code_fournisseur`, `code_compta_fournisseur`) and ORs
  them in a single `IN (...)` clause, so we match whichever field the
  writer used — Dolibarr core's `sellsjournal.php` writes `code_client`,
  `purchasesjournal.php` writes `code_fournisseur`, our own EBK writer
  writes `code_client` (currently — supplier-side EBK bookkeeping
  generation tracks an empty `thirdparty_code` and is a separate
  concern).

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
