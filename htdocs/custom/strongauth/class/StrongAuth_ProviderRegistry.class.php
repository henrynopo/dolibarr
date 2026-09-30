<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * StrongAuth_ProviderRegistry
 *
 * Discovers every ProviderInterface implementation registered with the module,
 * keyed by id() (e.g. 'local', 'entra', 'google', 'apple', 'oidc').
 *
 * Registry composition:
 *   - 'local'        : Provider_LocalPassword  (always available)
 *   - 'entra'        : Provider_EntraID        (if configured in llx_strongauth_idp_config)
 *   - 'google'       : Provider_Google         (if configured)
 *   - 'apple'        : Provider_Apple          (if configured)
 *   - 'oidc'         : Provider_GenericOIDC    (if configured — can host multiple generic IdPs
 *                                                 by overriding the issuer URL per config row)
 *
 * The orchestrator asks the registry for the provider matching a given
 * path.primary; the controller scripts ask for a provider by id() to begin
 * the SSO flow.
 *
 * Note: providers are constructed lazily and held for the lifetime of the
 * request. They are stateless beyond a per-request JWKS cache in OIDCClient.
 */

class StrongAuth_ProviderRegistry
{
    /** @var ProviderInterface[]|null */
    private static $instances = null;

    /**
     * Return every registered provider (constructed lazily).
     *
     * @return ProviderInterface[]
     */
    public static function all(): array
    {
        if (self::$instances !== null) {
            return self::$instances;
        }
        $registry = array();
        $registry['local']  = new Provider_LocalPassword();

        // IdP providers are loaded only if their config row is enabled. This
        // avoids loading OAuth secrets for providers that the admin has not set up.
        $configs = self::loadEnabledConfigs();
        foreach ($configs as $cfg) {
            $provider = self::buildFromConfig($cfg);
            if ($provider !== null) {
                $registry[$cfg['provider']] = $provider;
            }
        }
        self::$instances = $registry;
        return $registry;
    }

    /**
     * Look up a provider by its id().
     */
    public static function get(string $id): ?ProviderInterface
    {
        $all = self::all();
        return $all[$id] ?? null;
    }

    /**
     * Return only the providers that should be advertised on the login page
     * (i.e. configured + applicable to at least one user — typically all of them).
     */
    public static function loginPageChoices(): array
    {
        $out = array();
        foreach (self::all() as $id => $provider) {
            if (!$provider->isConfigured()) {
                continue;
            }
            $out[$id] = $provider;
        }
        return $out;
    }

    // -----------------------------------------------------------------------
    // Config loading
    // -----------------------------------------------------------------------

    /**
     * Load every enabled llx_strongauth_idp_config row.
     * @return array<int,array<string,mixed>>
     */
    private static function loadEnabledConfigs(): array
    {
        global $conf, $db;
        $rows = array();
        $sql = "SELECT rowid, provider, label, client_id, client_secret_enc, issuer, tenant_id,"
             . " team_id, key_id, private_key_enc, discovery_url, authorization_url, token_url,"
             . " jwks_url, scopes, enabled, auto_provision"
             . " FROM ".MAIN_DB_PREFIX."strongauth_idp_config"
             . " WHERE entity = ".(int) $conf->entity
             . " AND enabled = 1";
        $res = $db->query($sql);
        if (!$res) {
            return array();
        }
        while ($obj = $db->fetch_object($res)) {
            $rows[] = (array) $obj;
        }
        return $rows;
    }

    /**
     * Build a ProviderInterface for a given config row.
     */
    private static function buildFromConfig(array $cfg): ?ProviderInterface
    {
        $providerId = $cfg['provider'];
        $secret = !empty($cfg['client_secret_enc'])
            ? StrongAuth_Crypto::decrypt($cfg['client_secret_enc'])
            : null;

        switch ($providerId) {
            case 'entra':
                return new Provider_EntraID($cfg, $secret);
            case 'google':
                return new Provider_Google($cfg, $secret);
            case 'oidc':
                return new Provider_GenericOIDC($cfg, $secret);
            // 'apple' rows are ignored: Apple sign-in was removed by policy —
            // SSO only accepts corporate directories (Entra ID, Workspace).
        }
        return null;
    }
}
