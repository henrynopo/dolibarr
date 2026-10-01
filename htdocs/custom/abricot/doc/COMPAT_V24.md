# Compat report — Abricot vs Dolibarr v24

- Module: Abricot
- Current version: 3.10.1
- Target core version: 24.0.0
- Date: 2026-07-01
- Branch: FIX/COMPATV24 (base: 3.10)
- ChangeLog source: `/home/client/develop/dolibarr/ChangeLog`, section
  `***** ChangeLog for 24.0.0 compared to 23.0 *****`

## Summary

All 12 checks (10 from the ChangeLog WARNING block + 2 complementary) are **N/A**.
No code changes required for v24 compatibility.

| Source | Affected | N/A |
|---|---|---|
| ChangeLog v24 | 0 | 10 |
| Extra (code-compta.md) | 0 | 1 |
| Extra (csrf-token.md) | 0 | 1 |

## ChangeLog v24 — WARNING block

| # | Compat point | Source | Status | Severity | Evidence | Fix |
|---|---|---|---|---|---|---|
| 1 | Universal Search Syntax required on all filter params; raw-SQL filters rejected | ChangeLog v24 | N/A | — | `fetchAll(` hits: `includes/class/class.pdo.db.php:431,433` = native `PDOStatement::fetchAll()` (unrelated PHP PDO API); `includes/class/class.seedobject.php:396` = module's own `SeedObject::fetchAll()/fetchByArray()`, which builds its own raw SQL from an array `$TFilter` (key=value equality) and never calls Dolibarr core's `CommonObject::fetchAll()`. Not subject to the core USF requirement. | — |
| 2 | Login API off by default (`API_ENABLE_LOGIN_API`) | ChangeLog v24 | N/A | — | no match for `api/index.php/login` or `API_ENABLE_LOGIN_API` | — |
| 3 | Online-signature securekey now salted by default | ChangeLog v24 | N/A | — | no match for `getOnlineSignatureUrl` / `*_ONLINE_SIGNATURE_SECURITY_TOKEN` | — |
| 4 | `PAYMENT_SECURITY_TOKEN_UNIQUE` removed | ChangeLog v24 | N/A | — | no match | — |
| 5 | `DEPOSIT_AS_CREDIT_AVAILABLE_EVEN_UNPAID` renamed | ChangeLog v24 | N/A | — | no match | — |
| 6 | Substitution `__MYCOUNTRY_ID__` removed | ChangeLog v24 | N/A | — | no match | — |
| 7 | Hook context `info_admin` renamed to `messageOfTheDay` | ChangeLog v24 | N/A | — | no match for quoted `'info_admin'`/`"info_admin"` | — |
| 8 | Library `jeditable` removed from core | ChangeLog v24 | N/A | — | no `jeditable` string anywhere; `.editable(` hits are all inside bundled `includes/js/ckeditor/**` and refer to CKEditor's own `editor.editable()` API, unrelated to the jQuery jeditable plugin | — |
| 9 | Module Paybox removed | ChangeLog v24 | N/A | — | no match | — |
| 10 | Module Deplacement removed | ChangeLog v24 | N/A | — | no match | — |

## Complementary checks

### code-compta.md — deprecated `Societe::$code_compta` bare read

| # | Compat point | Status | Severity | Evidence |
|---|---|---|---|---|
| 1 | Live read of `->code_compta` (customer context) after fetch | N/A | — | Only 2 matches, both **commented-out** lines: `core/modules/modAbricot.class.php:356`, `:392` |

### csrf-token.md — `MAIN_SECURITY_CSRF_WITH_TOKEN` level 3

| # | Compat point | Status | Severity | Evidence |
|---|---|---|---|---|
| 1 | POST form missing token field | N/A | — | 6 `<form method="POST">` across `admin/abricot_setup.php` (5) and `script/migrate_ndf.php` (1); each file's form count matches its `name="token"` field count |
| 2 | State-changing GET action link without token | N/A | — | Whitelist-exclusion enumerator found 0 tokenless non-whitelisted `action=` triggers |
| 3 | Mass-action form without token | N/A | — | No `massaction` surface in the module |
| 4 | Module emits no token at all | N/A | — | Token fields present in both files with forms |

## Baseline

- `php -l` on all module PHP files: **0 syntax errors**.
- Descriptor sanity (`core/modules/modAbricot.class.php`): coherent `numero`/`rights_class`/`const_name` (unchanged by this audit).

## Conclusion

No fixes required (Phase 5 skipped). Proceeding to Phase 6 (compat release bump).