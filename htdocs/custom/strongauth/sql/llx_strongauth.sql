-- ============================================================================
-- StrongAuth: Strong authentication for Dolibarr
-- Compatible with Dolibarr 22.0 / 23.0 / 24.0
-- ============================================================================
-- Storage format conventions:
--   * TOTP secrets: AES-256-GCM encrypted, prefixed with 'v1:' + base64 parts
--     Format: v1:<iv_b64>:<tag_b64>:<ciphertext_b64>
--   * Backup codes: bcrypt-hashed (PASSWORD_BCRYPT)
--   * IdP bindings: subject is the IdP's immutable user identifier (sub / NameID)
-- ============================================================================

-- TOTP secrets (one row per user)
CREATE TABLE IF NOT EXISTS llx_strongauth_totp (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user          INTEGER NOT NULL,
    secret_enc       VARCHAR(512) NOT NULL,           -- AES-256-GCM encrypted
    issuer           VARCHAR(128) NOT NULL,           -- Authenticator app issuer name
    enrolled_at      DATETIME NOT NULL,
    last_used_at     DATETIME,
    algorithm        VARCHAR(16) DEFAULT 'SHA256',   -- 'SHA1' for legacy import
    digits           SMALLINT DEFAULT 6,
    period           SMALLINT DEFAULT 30,
    enabled          TINYINT DEFAULT 1,
    entity           INTEGER DEFAULT 1,
    UNIQUE KEY uk_fk_user (fk_user, entity)
) ENGINE=InnoDB;

-- Backup codes (one row per code; bcrypt-hashed; single-use)
CREATE TABLE IF NOT EXISTS llx_strongauth_backupcode (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user          INTEGER NOT NULL,
    code_hash        VARCHAR(255) NOT NULL,           -- bcrypt hash
    used             TINYINT DEFAULT 0,
    created_at       DATETIME NOT NULL,
    used_at          DATETIME,
    used_ip          VARCHAR(45),
    used_user_agent  VARCHAR(255),
    entity           INTEGER DEFAULT 1,
    INDEX idx_fk_user (fk_user, entity),
    INDEX idx_unused (fk_user, used)
) ENGINE=InnoDB;

-- IdP bindings (one user may have multiple IdPs; populated by Phase 3)
CREATE TABLE IF NOT EXISTS llx_strongauth_idp_binding (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user          INTEGER NOT NULL,
    provider         VARCHAR(32) NOT NULL,            -- 'entra' | 'google' | 'apple' | 'oidc' | 'saml'
    subject          VARCHAR(255) NOT NULL,            -- IdP's immutable sub / NameID
    issuer           VARCHAR(255),
    bound_at         DATETIME NOT NULL,
    last_used_at     DATETIME,
    raw_claims       TEXT,                              -- snapshot of first-login claims (audit)
    entity           INTEGER DEFAULT 1,
    UNIQUE KEY uk_provider_subject (provider, subject, entity),
    INDEX idx_fk_user (fk_user, entity)
) ENGINE=InnoDB;

-- WebAuthn credentials (one row per passkey)
-- source_json holds the full webauthn-framework PublicKeyCredentialSource
-- (lossless round-trip); the scalar columns are kept for queries/forensics.
CREATE TABLE IF NOT EXISTS llx_strongauth_webauthn (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user          INTEGER NOT NULL,
    credential_id    VARCHAR(512) NOT NULL,          -- base64url credential id
    source_json      TEXT NOT NULL,
    aaguid           VARCHAR(36),
    sign_count       BIGINT UNSIGNED DEFAULT 0,
    transports       VARCHAR(255),
    name             VARCHAR(128),
    user_agent       VARCHAR(255),
    created_at       DATETIME,
    last_used_at     DATETIME,
    entity           INTEGER DEFAULT 1,
    UNIQUE KEY uk_credential (credential_id),
    INDEX idx_fk_user (fk_user, entity)
) ENGINE=InnoDB;

-- Audit log — append-only, one row per authentication event
CREATE TABLE IF NOT EXISTS llx_strongauth_audit (
    rowid            BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_time       DATETIME NOT NULL,
    fk_user          INTEGER,                          -- nullable: failed login may not resolve a user
    username_try     VARCHAR(128),                     -- what was typed (for forensics)
    event_type       VARCHAR(32) NOT NULL,             -- see StrongAuth_Audit::EVENT_* constants
    method           VARCHAR(32),                      -- 'local' | 'totp' | 'backupcode' | 'webauthn' | 'entra' | 'google' | ...
    ip_address       VARCHAR(45),
    user_agent       VARCHAR(255),
    detail           TEXT,                             -- JSON or short text with extra context
    entity           INTEGER DEFAULT 1,
    INDEX idx_event_time (event_time),
    INDEX idx_fk_user (fk_user),
    INDEX idx_event_type (event_type, event_time)
) ENGINE=InnoDB;

-- Failed-attempt counters for lockout.
-- Deliberately GLOBAL per user (not per entity): an attacker must not be able
-- to reset the failure counter by hopping between multicompany entities.
-- The entity column only records which entity saw the first failure (audit).
CREATE TABLE IF NOT EXISTS llx_strongauth_lockout (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user          INTEGER NOT NULL,
    failed_count     INTEGER DEFAULT 0,
    first_fail_at    DATETIME,
    locked_until     DATETIME,
    entity           INTEGER DEFAULT 1,
    UNIQUE KEY uk_user (fk_user)
) ENGINE=InnoDB;

-- IdP challenge storage (Phase 3 — for OIDC state / nonce / PKCE)
CREATE TABLE IF NOT EXISTS llx_strongauth_idp_challenge (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    state            VARCHAR(64) NOT NULL,
    nonce            VARCHAR(64),
    pkce_verifier    VARCHAR(128),
    provider         VARCHAR(32) NOT NULL,
    redirect_after   VARCHAR(512),
    created_at       DATETIME NOT NULL,
    consumed_at      DATETIME,
    UNIQUE KEY uk_state (state),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- WebAuthn challenge storage (Phase 4)
CREATE TABLE IF NOT EXISTS llx_strongauth_webauthn_challenge (
    rowid            INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user          INTEGER,
    challenge        VARCHAR(255) NOT NULL,
    purpose          VARCHAR(32) NOT NULL,             -- 'register' | 'authenticate'
    created_at       DATETIME NOT NULL,
    consumed_at      DATETIME,
    INDEX idx_challenge (challenge)
) ENGINE=InnoDB;

-- SSO provider configuration (one row per provider per entity)
-- Stores client_id, encrypted client_secret, issuer URL, scopes, tenant, etc.
CREATE TABLE IF NOT EXISTS llx_strongauth_idp_config (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    provider          VARCHAR(32) NOT NULL,           -- 'entra' | 'google' | 'apple' | 'oidc'
    label             VARCHAR(128),
    client_id         VARCHAR(255),
    client_secret_enc VARCHAR(2048),                  -- AES-256-GCM encrypted (same master key as TOTP)
    issuer            VARCHAR(512),                   -- OIDC issuer (e.g. https://login.microsoftonline.com/{tenant}/v2.0)
    tenant_id         VARCHAR(128),                   -- Entra-specific
    team_id           VARCHAR(128),                   -- Apple-specific
    key_id            VARCHAR(128),                   -- Apple-specific (kid of the private key)
    private_key_enc   TEXT,                           -- Apple-specific (PEM, AES-256-GCM encrypted)
    discovery_url     VARCHAR(512),                   -- override discovery (for generic OIDC)
    authorization_url VARCHAR(512),                   -- override (rare)
    token_url         VARCHAR(512),
    jwks_url          VARCHAR(512),
    scopes            VARCHAR(255) DEFAULT 'openid email profile',
    enabled           TINYINT DEFAULT 0,
    auto_provision    TINYINT DEFAULT 0,              -- create user if email not found in llx_user
    created_at        DATETIME,
    updated_at        DATETIME,
    entity            INTEGER DEFAULT 1,
    UNIQUE KEY uk_provider (provider, entity)
) ENGINE=InnoDB;

-- Trusted device cookies (Phase 4 — 30-day TOTP bypass)
-- A row per (user, device token). When the cookie HMAC verifies and the row exists
-- and the device fingerprint matches, TOTP is skipped on that browser.
CREATE TABLE IF NOT EXISTS llx_strongauth_device_trust (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user           INTEGER NOT NULL,
    token_hash        VARCHAR(128) NOT NULL,          -- SHA-256 of the issued cookie value
    fingerprint       VARCHAR(128) NOT NULL,          -- user-agent + accept-language hash
    user_agent        VARCHAR(255),
    ip_first_seen     VARCHAR(45),
    created_at        DATETIME NOT NULL,
    expires_at        DATETIME NOT NULL,
    revoked_at        DATETIME,
    entity            INTEGER DEFAULT 1,
    UNIQUE KEY uk_token (token_hash),
    INDEX idx_user (fk_user, entity),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- OIDC session cache: after a successful SSO callback we cache the user's
-- verified subject+issuer in the session keyed by this token. This avoids
-- re-running token verification on every request before afterLogin runs.
CREATE TABLE IF NOT EXISTS llx_strongauth_sso_session (
    rowid             INTEGER AUTO_INCREMENT PRIMARY KEY,
    session_token     VARCHAR(64) NOT NULL,           -- random; stored in cookie & session
    fk_user           INTEGER NOT NULL,
    provider          VARCHAR(32) NOT NULL,
    subject           VARCHAR(255) NOT NULL,
    issuer            VARCHAR(512),
    amr               VARCHAR(255),                   -- 'pwd' | 'mfa' | 'otp' | ... (space-separated)
    acr               VARCHAR(128),
    issued_at         DATETIME NOT NULL,
    expires_at        DATETIME NOT NULL,
    consumed_at       DATETIME,
    entity            INTEGER DEFAULT 1,
    UNIQUE KEY uk_session (session_token),
    INDEX idx_user (fk_user, entity)
) ENGINE=InnoDB;
