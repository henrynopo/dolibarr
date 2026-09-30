<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Provider_GenericOIDC — any OIDC-compliant IdP
 *
 * Use this for:
 *   - Self-hosted Keycloak / Authentik / Auth0 / Okta tenants
 *   - Internal OIDC providers
 *   - Future / niche IdPs we don't ship a first-class provider for
 *
 * Configuration knobs in llx_strongauth_idp_config:
 *   - issuer         : REQUIRED — OIDC issuer URL
 *   - client_id      : OAuth client ID
 *   - client_secret  : OAuth client secret (AES-256-GCM encrypted)
 *   - scopes         : default 'openid email profile'
 *
 * Note: if multiple GenericOIDC providers are needed, multiple rows in
 * llx_strongauth_idp_config with provider='oidc' are supported as long as
 * their issuers differ. The registry keys them all under the same id 'oidc';
 * the admin should use the label field to disambiguate. The login page will
 * show a single "Other IdP" button unless the admin configures multiple rows
 * with distinct provider ids (e.g. 'oidc-keycloak', 'oidc-auth0'). For now
 * we keep the registry simple — first 'oidc' row wins; further rows are
 * ignored at registration time.
 */

class Provider_GenericOIDC extends Provider_OIDCBase
{
    public function id(): string
    {
        return 'oidc';
    }

    public function label(): string
    {
        $label = $this->config['label'] ?? null;
        return is_string($label) && $label !== '' ? $label : 'Generic OIDC';
    }
}
