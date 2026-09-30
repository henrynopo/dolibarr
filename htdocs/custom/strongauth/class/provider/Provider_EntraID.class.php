<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Provider_EntraID — Microsoft Entra ID (formerly Azure AD)
 *
 * Configuration knobs in llx_strongauth_idp_config:
 *   - tenant_id   : Directory (tenant) ID, or 'common' / 'organizations' / 'consumers'
 *   - issuer      : Optional override; default = https://login.microsoftonline.com/{tenant_id}/v2.0
 *   - client_id   : Application (client) ID
 *   - client_secret : App-registered client secret (stored AES-256-GCM encrypted)
 *   - scopes      : default 'openid email profile offline_access'
 *
 * Entra specifics:
 *   - Authorization endpoint: https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize
 *   - Token endpoint:         https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
 *   - Discovery:              /v2.0/.well-known/openid-configuration
 *   - amr claim values:       'pwd' (password), 'mfa', 'otp', 'rsa' (FIDO2), 'ngcmfa' (Microsoft Authenticator)
 *
 * We never expose an Entra tenant URL until the admin has filled tenant_id + client_id.
 */

class Provider_EntraID extends Provider_OIDCBase
{
    public function id(): string
    {
        return 'entra';
    }

    public function label(): string
    {
        return 'Microsoft 365';
    }

    /** Font Awesome brand class for the login button icon. */
    public function iconClass(): string
    {
        return 'fab fa-microsoft';
    }

    protected function issuer(): string
    {
        if (!empty($this->config['issuer'])) {
            return (string) $this->config['issuer'];
        }
        $tenant = (string) ($this->config['tenant_id'] ?? 'common');
        return 'https://login.microsoftonline.com/'.$tenant.'/v2.0';
    }

    protected function defaultScopes(): string
    {
        return $this->config['scopes'] ?: 'openid email profile offline_access';
    }

    public function isConfigured(): bool
    {
        if (!parent::isConfigured()) {
            return false;
        }
        // Either issuer or tenant_id must be set.
        return !empty($this->config['tenant_id']) || !empty($this->config['issuer']);
    }
}
