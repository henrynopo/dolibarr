<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Provider_Google — Google Workspace (or personal Google accounts, scoped to a Google Cloud project)
 *
 * Configuration knobs in llx_strongauth_idp_config:
 *   - issuer     : default https://accounts.google.com
 *   - client_id  : OAuth 2.0 client ID (Web application)
 *   - client_secret : OAuth client secret (AES-256-GCM encrypted)
 *   - scopes     : default 'openid email profile'
 *
 * Google's discovery document is canonical:
 *   https://accounts.google.com/.well-known/openid-configuration
 *
 * Google-specific notes:
 *   - email_verified claim is honored — we reject bindings where the email
 *     is not verified by Google.
 *   - hd claim (hosted domain) can be checked for Workspace accounts. We
 *     leave this to the orchestrator: internal domain matching happens after
 *     user resolution.
 */

class Provider_Google extends Provider_OIDCBase
{
    public function id(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google';
    }

    /** Font Awesome brand class for the login button icon. */
    public function iconClass(): string
    {
        return 'fab fa-google';
    }

    protected function issuer(): string
    {
        return $this->config['issuer'] ?: 'https://accounts.google.com';
    }

    /**
     * Restrict logins to a Google Workspace hosted domain.
     *
     * The OAuth app itself accepts ANY Google account (personal gmail or other
     * Workspace domains) unless we check the hd claim. Without this, anyone
     * who verified ownership of a matching address on a personal Google
     * account could inherit the Dolibarr identity linked by email. Set the
     * Workspace domain in the provider's tenant_id field to enforce it.
     */
    protected function resolveDolibarrUser(string $email, array $claims): int
    {
        $allowedHd = trim((string) ($this->config['tenant_id'] ?? ''));
        if ($allowedHd !== '') {
            $hd = (string) ($claims['hd'] ?? '');
            if ($hd === '' || dol_strtolower($hd) !== dol_strtolower($allowedHd)) {
                throw new RuntimeException('google: token hd claim does not match the configured Workspace domain');
            }
        }
        return parent::resolveDolibarrUser($email, $claims);
    }

    // email_verified enforcement lives in Provider_OIDCBase::handleCallback()
    // and applies to every provider that emits the claim.
}
