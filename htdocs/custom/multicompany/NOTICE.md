# SLY-maintained Multi-company — NOTICE

This is a community-maintained fork of the **multicompany** module
(originally `v18.0.1`, Copyright (C) Régis Houssin / Inodbox,
 GPL v3 or later — full licence text in [COPYING](COPYING)).

The upstream Dolistore line (currently 24.0.2) was **not** purchased or
used; every change below was made independently on the v18.0.1 codebase
obtained from a legitimately licensed deployment, which GPL v3 permits.

## Modifications (GPL §5a)

Applied 2026-09-30 / 10-01 by SLY for Dolibarr v24.0.1 + PHP 8.1
(see repository history for per-line attribution):

**Security (audit-driven):**
- `switchEntity()` authz: removed the `MULTICOMPANY_HIDE_LOGIN_COMBOBOX`
  short-circuit that bypassed `verifyRight()` (tenant isolation bypass)
- `confirmsharebyelement` mass action: added superadmin gate + int-cast
  entity arrays (was permission-free)
- GET entity switch: now requires a valid CSRF token on both legacy
  branches (AJAX POST path already carried one)
- `core/ajax/functions.php`: `exit` after the module-disabled 403 gate
- `modifyEntity`: thirdparty write right no longer authorises moving
  contacts/projects across entities
- Plaintext-password compatibility fallback removed from the login
  chain (hash verification only)
- LDAP login filter now RFC 4515-escaped (`ldap_escape`) — LDAP injection
- Entity labels HTML-escaped on output (11 echo sites)
- DAO id/entity values int-cast; identifier whitelist on
  `getIdByForeignKey`; stray `)` parse fix in `getAllUsers`

**Compatibility:**
- `#[\AllowDynamicProperties]` + declared properties (PHP 8.2 readiness)
- `setEntity($id, $type='active', $value=1)` parameter default
- Version self-check retargeted to Dolibarr 24
- Removed a leftover production `error_log`

Redistribution of this fork is permitted under GPL v3+ with this notice
and the original COPYING preserved.
