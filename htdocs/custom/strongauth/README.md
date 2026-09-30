# StrongAuth

**Strong authentication for Dolibarr 22.0+ — SSO + TOTP 2FA + optional Passkey**

A self-contained replacement for legacy `totp2fa` (which is simply disabled before enabling StrongAuth). StrongAuth is **completely independent** of the totp2fa module — it does not load any totp2fa PHP code, does not register totp2fa hooks, and does not read any totp2fa table. There is no data-migration path by design: users enroll their authenticator fresh through the forced first-login wizard.

| Layer | Component |
|---|---|
| Primary login | Local password, Entra ID (M365), Google Workspace, self-hosted OIDC (Apple sign-in removed by policy) |
| Second factor (default) | **TOTP** — RFC 6238, AES-256-GCM encrypted, any standard Authenticator App |
| Second factor (optional) | **Passkey (WebAuthn/FIDO2)** — internal employees only; platform-agnostic: Windows Hello, iCloud Keychain (Touch ID/Face ID), Google Password Manager |
| Recovery | Backup codes (10 one-time codes, bcrypt-hashed) |

## Key design decisions

- **TOTP-only**: No email OTP, no SMS OTP, no voice OTP — these are vulnerable to phishing / SIM swap / mailbox takeover
- **Passkey restricted to internal users**: enforced in three layers (enrollment UI, JSON endpoint, login verification) — external users can neither register nor authenticate with a Passkey
- **Internal users are IdP-only**: any password-based login for an internal-domain user is rejected regardless of password validity; only the IdP-asserted SSO cookie path is accepted
- **TOTP as universal fallback**: Anyone with a TOTP registered can use it, including as Passkey's recovery path
- **Independent of totp2fa**: StrongAuth runs without totp2fa being installed, enabled, or present. Disable totp2fa before enabling StrongAuth; users re-enroll through the forced wizard.

## Permissions

| Right (id) | Grants |
|---|---|
| `strongauth->reset` (5700151) | Manage/re-enroll 2FA for any user, configure IdP providers, rotate the AES key, open setup page |
| `strongauth->audit` (5700152) | View the authentication audit log |
| (no right needed) | Self-service: enroll own TOTP, regenerate own backup codes, register own Passkey, manage own IdP bindings |

Dolibarr `admin` implicitly reaches the setup page; the key-rotation action
additionally requires `strongauth->reset`.

## Configuration reference

| Constant | Default | Effect |
|---|---|---|
| `STRONGAUTH_ISSUER_NAME` | company name | Issuer label in TOTP QR codes and WebAuthn RP name |
| `STRONGAUTH_INTERNAL_DOMAINS` | (empty) | Comma list of domains whose users are IdP-only (password login refused) and Passkey-eligible |
| `STRONGAUTH_REQUIRE_2FA_LOCAL` | 1 | External users without TOTP are forced into enrollment on next login. Off: 2FA optional (already-enrolled users are still verified) |
| `STRONGAUTH_REQUIRE_2FA_IDP` | 0 | Step-up: SSO users who have TOTP/Passkey enrolled must also present it (on top of IdP MFA) |
| `STRONGAUTH_LOCKOUT_MAX_FAILED` | 5 | Failed 2FA attempts before lockout (global per user, cross-entity) |
| `STRONGAUTH_LOCKOUT_DURATION` | 900 | Lockout duration in seconds |
| `STRONGAUTH_BACKUPCODE_COUNT` | 10 | Backup codes per enrollment (1–50) |
| `STRONGAUTH_DEVICE_TRUST_LOCAL` | 0 | Allow the "trust this device 30 days" checkbox (local-password path only; never for SSO) |
| `STRONGAUTH_ALLOW_WEBAUTHN` | 0 | Master switch for Passkey (internal users only) |
| `STRONGAUTH_WEBAUTHN_RPID` | (auto) | WebAuthn relying-party ID override (behind host-rewriting proxies) |

TOTP parameters (6 digits / 30 s / SHA-256 / ±1 step) are hard-coded and not
admin-configurable by design.

## Choosing IdP providers (email-trust comparison)

Account identity is linked **by email**, so the effective security of an account
is that of the *weakest* enabled IdP. Corporate suitability:

| Provider | Email authority | Notes |
|---|---|---|
| Entra ID (M365) | Highest — tenant directory, admin-assigned | MUST use the dedicated tenant ID; `common`/`consumers` admit personal Microsoft accounts (setup warns) |
| Google Workspace | High — domain admin-assigned | Set the Workspace domain in `tenant_id` to pin the `hd` claim; otherwise any personal Google account with a verified matching address is accepted |
| ~~Apple~~ | Removed by policy | Personal-identity IdP (no corporate directory); also "Hide My Email" relay addresses break email linking. Apple users still get Passkey (iCloud Keychain) as a second factor |
| Generic OIDC | Varies | Self-hosted Keycloak/Authentik ≈ Entra; social-login-federated IdPs inherit the weakest social provider. `email_verified=false` is always rejected |

For internal-domain users, enabling more than one IdP widens the attack
surface to all of them — enable only what users actually need.

## Requirements

- Dolibarr 22.0 / 23.0 / 24.0
- PHP 8.1+
- `openssl` extension (AES-256-GCM), `gd` (QR rendering)
- Composer (ONLY to refresh the bundled `vendor/`; not needed for deployment)

## Installation

1. Copy `strongauth/` to `htdocs/custom/strongauth/` — **dependencies are
   bundled**: the module ships with `vendor/` (37 MB, pure-PHP packages, no
   platform-specific builds), so `composer install` is NOT required for a
   standard deployment. Only run it (on the server) to refresh dependencies.
2. Setup → Modules/Applications → enable **StrongAuth**
3. **For SSO cookie login**: the setup page offers a one-click button that
   prepends `strongauth_sso` to the authentication method in `conf/conf.php`
   (timestamped backup kept). Manual alternative: edit the file and set
   `$dolibarr_main_authentication='strongauth_sso,dolibarr';`
4. Setup → Modules/Applications → StrongAuth → **Configure**
5. The first time it enables, it auto-generates an AES-256 encryption key into
   `llx_const` at **entity 0** (shared across multicompany entities — back it up!)

The setup page self-checks all of the above (bundled vendor present, auth
method active, per-entity module coverage, internal domains have an IdP) and
shows actionable warnings for anything missing.

## multicompany deployment (IMPORTANT)

Dolibarr enables modules **per entity**. Hooks and the SSO login function only
load for entities where StrongAuth is enabled — an entity without the module
has **no 2FA at all**, and users shared across entities can bypass the second
factor by logging in through it. Therefore:

- Enable StrongAuth in **every entity that has users** (switch entity via the
  multicompany selector, then enable the module). The setup page lists any
  entities where the module is missing.
- The AES master key lives at **entity 0** (shared), so TOTP secrets enrolled
  in one entity decrypt in all others.
- Account **lockout is global per user** (not per entity): failure counters
  and lockouts follow the user across entities, so counter reset by entity
  hopping is not possible.
- TOTP enrollment itself is per entity (users must enroll once per entity
  they use).

## Module structure

```
custom/strongauth/
├── README.md
├── ChangeLog
├── composer.json
├── core/
│   ├── modules/modStrongauth.class.php
│   └── login/functions_strongauth_sso.php      # SSO-cookie auth fn (module_parts['login'])
├── sql/llx_strongauth.sql
├── lib/strongauth.lib.php                      # bootstrap: constants + class loading
├── class/                                      # loaded via lib/strongauth.lib.php
│   ├── actions_strongauth.class.php
│   ├── StrongAuth_Crypto.class.php
│   ├── StrongAuth_Orchestrator.class.php
│   ├── StrongAuth_Audit.class.php
│   ├── StrongAuth_Lockout.class.php
│   ├── StrongAuth_DeviceTrust.class.php
│   ├── StrongAuth_OIDCClient.class.php
│   ├── StrongAuth_ProviderRegistry.class.php
│   ├── factor/FactorInterface.php
│   ├── factor/Factor_TOTP.class.php
│   ├── factor/Factor_BackupCode.class.php
│   ├── factor/Factor_WebAuthn.class.php
│   ├── provider/ProviderInterface.php
│   ├── provider/Provider_LocalPassword.class.php
│   ├── provider/Provider_EntraID.class.php
│   ├── provider/Provider_Google.class.php
│   ├── provider/Provider_GenericOIDC.class.php
│   └── provider/Provider_Apple.class.php
├── views/
│   ├── factor_enroll.php             # TOTP wizard (works pre-login in forced mode)
│   ├── factor_manage.php
│   ├── json.php                      # WebAuthn endpoint (NOLOGIN-capable)
│   ├── webauthn_enroll.php
│   ├── idp_link.php
│   ├── audit_log.php
│   └── sso/{login,callback}.php      # NOLOGIN endpoints
├── admin/
│   ├── setup.php
│   ├── idp_setup.php
└── langs/{en_US,zh_CN}/strongauth.lang
```

## Operations & Recovery (admin runbook)

Three settings are NOT obvious from the Dolibarr UI — know where they live:

| Setting | Where | Effect |
|---|---|---|
| `$dolibarr_main_authentication='strongauth_sso,dolibarr';` | **conf/conf.php** (file, not DB — no admin UI) | Enables the SSO-cookie login mode. Setup page offers a one-click rewriter with backup; when the file is not web-writable, edit it by hand. |
| `STRONGAUTH_INTERNAL_DOMAINS` | Setup page → General | Comma-separated email domains whose users are MFA-forced through the IdP and refused local-password login. **Configure only after the IdP works end-to-end.** |
| `STRONGAUTH_ADMIN_BREAKGLASS` | Setup page → General (default ON) | Dolibarr admins may bypass the IdP-only rule with the local password. Turn OFF to enforce IdP-only for admins too — an IdP outage then locks them out as well. |

### Emergency keys (keep these handy)

**IdP outage / users locked out** — clears the internal-domain rule; local
passwords work again immediately (no restart needed):

```sql
UPDATE <prefix>const SET value = '' WHERE name = 'STRONGAUTH_INTERNAL_DOMAINS';
```

`<prefix>` is your Dolibarr table prefix — find it with
`SHOW TABLES LIKE '%strongauth%';` or `grep db_prefix conf/conf.php`.

**Admin locked out with break-glass OFF** — same statement as above. (With
break-glass ON admins can always fall back to their local password.)

**Module misbehaving / must get in quickly** — disable StrongAuth in
Setup ▸ Modules. Hooks stop loading, Dolibarr native login works; all tables,
TOTP secrets and bindings are preserved for re-enabling later.

**Master key loss** — `STRONGAUTH_TOTP_ENC_KEY` (llx_const, **entity 0**) is
not recoverable: every TOTP secret and IdP client secret must be re-enrolled.
Back it up (and note the rotation button on the setup page re-encrypts but
does not archive — keep your own copy).

### Where things are

- Audit trail: Setup page → View audit log (right `strongauth->audit`), table `<prefix>strongauth_audit`
- SSO sessions / bindings: tables `<prefix>strongauth_sso_session`, `<prefix>strongauth_idp_binding`
- Lockout state (global per user): `<prefix>strongauth_lockout` — clear a stuck
  user with `DELETE FROM <prefix>strongauth_lockout WHERE fk_user = <id>;`
- Diagnostics: the setup page self-checks (vendor, auth method, per-entity
  coverage, IdP presence for internal domains, table completeness)


## Status

All authentication paths are implemented and audited (2026-09-17):

| Component | Status |
|---|---|
| Module skeleton, AES-256-GCM crypto, TOTP factor, local-password provider, hooks, setup page | ✅ |
| Backup codes, enrollment wizard (incl. pre-login forced mode), management UI, audit log | ✅ |
| SSO: Entra ID, Google, Apple, generic OIDC + native login-pipeline integration | ✅ |
| Passkey (WebAuthn/FIDO2) for internal users — 3-layer restriction | ✅ |
| totp2fa migration (explicit, opt-in) | ✅ |

## License

GPL-3.0-or-later
