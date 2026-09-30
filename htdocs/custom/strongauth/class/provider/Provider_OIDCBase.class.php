<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Provider_OIDCBase
 *
 * Common implementation for all OIDC providers (Entra, Google, Apple, generic).
 *
 * Subclasses customize:
 *   - issuer URL & discovery override (e.g. Entra tenant routing)
 *   - client_secret derivation (Apple signs a JWT instead)
 *   - subject claim name (Apple uses 'sub' too, but some IdPs use email)
 *
 * The base class handles:
 *   - State / nonce / PKCE generation and validation
 *   - Authorization URL building
 *   - Code exchange and ID token verification
 *   - UserInfo fallback
 *   - IdP binding persistence (lookup or create on first login)
 *   - Session token issuance (the orchestrator looks at this token on the next request)
 */

abstract class Provider_OIDCBase implements ProviderInterface
{
    /** @var array<string,mixed> Raw config row from llx_strongauth_idp_config */
    protected $config;

    /** @var string|null Decrypted client_secret (or null for public-client PKCE-only flows) */
    protected $clientSecret;

    /** @var StrongAuth_OIDCClient */
    protected $client;

    public function __construct(array $config, ?string $clientSecret)
    {
        $this->config       = $config;
        $this->clientSecret = $clientSecret;
        $this->client       = new StrongAuth_OIDCClient();
    }

    /**
     * Subclasses return their provider id ('entra', 'google', ...).
     */
    abstract public function id(): string;

    /**
     * Subclasses return a human label.
     */
    abstract public function label(): string;

    /**
     * Subclasses may override the issuer URL (e.g. Entra uses a per-tenant URL).
     */
    protected function issuer(): string
    {
        return (string) ($this->config['issuer'] ?? '');
    }

    /** Font Awesome brand class for login-button icons ('' = none). */
    public function iconClass(): string
    {
        return '';
    }

    /**
     * Subclasses may provide a custom client_secret (Apple signs an ES256 JWT
     * each time). Default: the secret stored in the config row.
     */
    protected function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * Default scope is openid + profile + email. Subclasses may extend.
     */
    protected function defaultScopes(): string
    {
        return $this->config['scopes'] ?: 'openid email profile';
    }

    /**
     * Subclasses may override to allow auto-provisioning users. Default: never.
     */
    protected function autoProvisionEnabled(): bool
    {
        return !empty($this->config['auto_provision']);
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['enabled'])
            && !empty($this->config['client_id'])
            && $this->issuer() !== '';
    }

    /**
     * Whether this provider applies to a given user. By default the SSO path
     * is opt-in: the user explicitly clicks "Sign in with X". We never force
     * SSO on a user — the login page lists every configured provider.
     */
    public function appliesTo(User $user): bool
    {
        // SSO is always opt-in from the user's side; the orchestrator handles
        // routing once the IdP callback returns. Returning true here means
        // "this provider is offered on the login page if the user picks it".
        return $this->isConfigured();
    }

    /**
     * Begin the SSO flow:
     *   1. Generate state, nonce, PKCE verifier
     *   2. Persist them in llx_strongauth_idp_challenge
     *   3. Return the authorization URL to redirect to
     *
     * @param User   $user     Optional — for login_hint (e.g. Entra's email hint)
     * @param array  $context  Optional — may contain 'login_hint' or 'redirect_after'
     * @return string          Authorization URL (caller redirects the browser)
     */
    public function beginAuthorization(?User $user, array $context = array()): string
    {
        $state    = self::randomToken(32);
        $nonce    = self::randomToken(24);
        $verifier = self::randomToken(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        global $conf, $db;
        $redirectAfter = !empty($context['redirect_after'])
            ? substr($context['redirect_after'], 0, 512)
            : DOL_URL_ROOT.'/';

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_idp_challenge"
             . " (state, nonce, pkce_verifier, provider, redirect_after, created_at)"
             . " VALUES ("
             ."'".$db->escape($state)."',"
             ."'".$db->escape($nonce)."',"
             ."'".$db->escape($verifier)."',"
             ."'".$db->escape($this->id())."',"
             ."'".$db->escape($redirectAfter)."',"
             ."'".$db->idate(gmdate('Y-m-d H:i:s'))."')";
        $db->query($sql);

        $params = array(
            'client_id'             => (string) $this->config['client_id'],
            'redirect_uri'          => $this->redirectUri(),
            'scope'                 => $this->defaultScopes(),
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'prompt'                => 'select_account',
        );
        if (!empty($context['login_hint'])) {
            $params['login_hint'] = (string) $context['login_hint'];
        } elseif ($user && !empty($user->email)) {
            $params['login_hint'] = (string) $user->email;
        }

        $discovery = $this->client->discovery($this->issuer());
        return $this->client->buildAuthorizationUrl($discovery, $params);
    }

    /**
     * Handle the IdP callback. Validates state/nonce/PKCE, exchanges the code,
     * verifies the ID token, and either:
     *   - returns a verified session token + claims array
     *   - throws on any failure (controller renders an error page)
     *
     * @return array{
     *   fk_user: int,                // resolved Dolibarr user id
     *   subject: string,             // IdP 'sub'
     *   issuer: string,
     *   amr: string,                 // space-separated authentication methods
     *   acr: string,
     *   email: string,
     *   display_name: string,
     *   session_token: string,       // to be stored in cookie + session
     *   expires_at: int,
     *   claims: array,               // raw claims (for audit log)
     * }
     */
    public function handleCallback(array $params): array
    {
        global $conf, $db;

        $state = (string) ($params['state'] ?? '');
        $code  = (string) ($params['code']  ?? '');
        if ($state === '' || $code === '') {
            throw new RuntimeException($this->id().': missing state or code');
        }

        // 1. Validate state and consume the challenge row.
        $row = $this->consumeChallenge($state);
        if ($row === null) {
            throw new RuntimeException($this->id().': invalid or expired state');
        }

        // 2. Exchange code for tokens.
        $discovery = $this->client->discovery($this->issuer());
        $token = $this->client->exchangeCode($discovery, array(
            'client_id'     => (string) $this->config['client_id'],
            'client_secret' => $this->getClientSecret(),
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
            'code_verifier' => $row['pkce_verifier'],
        ));

        if (empty($token['id_token'])) {
            throw new RuntimeException($this->id().': token endpoint returned no id_token');
        }

        // 3. Verify the ID token.
        $claims = $this->client->verifyIdToken(
            $token['id_token'],
            $this->issuer(),
            (string) $this->config['client_id'],
            $row['nonce']
        );

        // 4. Merge UserInfo claims if available (e.g. Apple).
        $email = (string) ($claims['email'] ?? '');
        $name  = (string) ($claims['name'] ?? ($claims['preferred_username'] ?? ''));
        if ($email === '' && !empty($token['access_token'])) {
            $info = $this->client->fetchUserInfo($discovery, $token['access_token']);
            if (!empty($info['email']))   { $email = (string) $info['email']; $claims = $claims + $info; }
            if (!empty($info['name']))    { $name  = (string) $info['name']; }
        }
        if ($email === '') {
            throw new RuntimeException($this->id().': no email claim in ID token or userinfo');
        }
        $email = dol_strtolower($email);

        // 4b. Refuse unverified emails. Identity linking is BY EMAIL — if the
        // IdP itself says it never verified the address, anyone could have
        // registered that address on the IdP and would inherit the Dolibarr
        // account. Entra tenant emails are admin-assigned (no claim emitted);
        // Google/others emit email_verified and are enforced here.
        if (isset($claims['email_verified']) && !$claims['email_verified']) {
            throw new RuntimeException($this->id().': IdP returned email_verified=false; refusing to link');
        }

        // 5. Resolve the Dolibarr user.
        $userId = $this->resolveDolibarrUser($email, $claims);
        if ($userId <= 0) {
            throw new RuntimeException($this->id().': no Dolibarr user matches email '.$email);
        }

        // 6. Persist the binding (first time) and audit.
        $subject = (string) $claims['sub'];
        $issuer  = $this->issuer();
        $amr     = is_array($claims['amr'] ?? null) ? implode(' ', $claims['amr']) : (string) ($claims['amr'] ?? '');
        $acr     = (string) ($claims['acr'] ?? '');
        $this->upsertBinding($userId, $subject, $issuer, $claims);

        // 7. Issue a session token + persist it. The next request will read this
        //    row to identify the SSO user without re-running the IdP flow.
        $sessionToken = self::randomToken(32);
        $expiresAt    = time() + 600;        // 10 minutes is enough to land on /index.php
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_sso_session"
             . " (session_token, fk_user, provider, subject, issuer, amr, acr, issued_at, expires_at, entity)"
             . " VALUES ("
             ."'".$db->escape($sessionToken)."',"
             .(int) $userId.","
             ."'".$db->escape($this->id())."',"
             ."'".$db->escape($subject)."',"
             ."'".$db->escape($issuer)."',"
             ."'".$db->escape($amr)."',"
             ."'".$db->escape($acr)."',"
             ."'".$db->idate(gmdate('Y-m-d H:i:s'))."',"
             ."'".$db->idate(gmdate('Y-m-d H:i:s', $expiresAt))."',"
             .(int) $conf->entity.")";
        $db->query($sql);

        StrongAuth_Audit::log(
            $db,
            $userId,
            $email,
            StrongAuth_Audit::EVENT_LOGIN_OK,
            $this->id(),
            'amr='.$amr.',acr='.$acr
        );

        return array(
            'fk_user'       => $userId,
            'subject'       => $subject,
            'issuer'        => $issuer,
            'amr'           => $amr,
            'acr'           => $acr,
            'email'         => $email,
            'display_name'  => $name,
            'session_token' => $sessionToken,
            'expires_at'    => $expiresAt,
            'claims'        => $claims,
        );
    }

    /**
     * authenticate() is not used by SSO providers (the IdP performs the actual
     * authentication). We return 'redirect' to signal that the controller
     * should redirect to the IdP. Returning 'redirect' is documented in
     * ProviderInterface.
     */
    public function authenticate(User $user, array $context): bool|string
    {
        return 'redirect';
    }

    // -----------------------------------------------------------------------
    // Hooks for subclasses
    // -----------------------------------------------------------------------

    /**
     * Subclasses may override to enforce provider-specific redirects
     * (Entra: include tenant ID in the issuer).
     *
     * OAuth requires an ABSOLUTE URI — STRONGAUTH_MODULE_URL_ROOT alone is a
     * relative path, so the site origin is prepended from the request.
     */
    protected function redirectUri(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme.'://'.$host.STRONGAUTH_MODULE_URL_ROOT.'/views/sso/callback.php?provider='.$this->id();
    }

    /**
     * Look up a Dolibarr user by email. Override in Provider_GenericOIDC if
     * the IdP uses a different linking claim.
     */
    protected function resolveDolibarrUser(string $email, array $claims): int
    {
        global $conf, $db;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user"
             . " WHERE email = '".$db->escape($email)."'"
             . " AND entity IN (0, ".(int) $conf->entity.")";
        $res = $db->query($sql);
        if ($res && ($obj = $db->fetch_object($res))) {
            return (int) $obj->rowid;
        }
        if ($this->autoProvisionEnabled()) {
            return $this->autoProvisionUser($email, $claims);
        }
        return 0;
    }

    /**
     * Auto-provision a Dolibarr user the first time they SSO in. Returns the
     * new user rowid. Off by default; admins must opt in per IdP config.
     */
    protected function autoProvisionUser(string $email, array $claims): int
    {
        global $db, $conf;
        $login = preg_replace('/[^a-z0-9._-]/i', '', explode('@', $email)[0]);
        $login = substr($login, 0, 32);
        // Avoid clashing with existing logins.
        $base = $login;
        $n = 0;
        while ($this->loginExists($login)) {
            $n++;
            $login = substr($base, 0, 30).$n;
        }
        $placeholder = 'disabled-'.$this->id().'-'.bin2hex(random_bytes(8));
        $now = $db->idate(gmdate('Y-m-d H:i:s'));
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."user"
             . " (login, email, pass_crypted, admin, statut, entity, datec)"
             . " VALUES ("
             ."'".$db->escape($login)."',"
             ."'".$db->escape($email)."',"
             ."'".$db->escape($placeholder)."',"
             ."0,"
             ."1,"
             .(int) $conf->entity.","
             ."'".$now."')";
        if (!$db->query($sql)) {
            return 0;
        }
        return (int) $db->last_insert_id(MAIN_DB_PREFIX."user");
    }

    private function loginExists(string $login): bool
    {
        global $conf, $db;
        $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE login = '".$db->escape($login)."' LIMIT 1");
        return $res && $db->num_rows($res) > 0;
    }

    /**
     * Persist (or update) the IdP binding for this user.
     */
    protected function upsertBinding(int $userId, string $subject, string $issuer, array $claims): void
    {
        global $conf, $db;
        $now = $db->idate(gmdate('Y-m-d H:i:s'));
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."strongauth_idp_binding"
             . " WHERE provider = '".$db->escape($this->id())."'"
             . " AND subject = '".$db->escape($subject)."'"
             . " AND entity = ".(int) $conf->entity;
        $res = $db->query($sql);
        if ($res && $db->num_rows($res) > 0) {
            $obj = $db->fetch_object($res);
            $upd = "UPDATE ".MAIN_DB_PREFIX."strongauth_idp_binding SET"
                 . " fk_user = ".(int) $userId.","
                 . " issuer = '".$db->escape($issuer)."',"
                 . " last_used_at = '".$now."',"
                 . " raw_claims = '".$db->escape(json_encode($claims))."'"
                 . " WHERE rowid = ".(int) $obj->rowid;
            $db->query($upd);
            return;
        }
        $ins = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_idp_binding"
             . " (fk_user, provider, subject, issuer, bound_at, last_used_at, raw_claims, entity)"
             . " VALUES ("
             .(int) $userId.","
             ."'".$db->escape($this->id())."',"
             ."'".$db->escape($subject)."',"
             ."'".$db->escape($issuer)."',"
             ."'".$now."',"
             ."'".$now."',"
             ."'".$db->escape(json_encode($claims))."',"
             .(int) $conf->entity.")";
        $db->query($ins);
    }

    /**
     * Look up + delete (consume) the challenge row for a given state.
     * Also enforces a 10-minute TTL — challenges older than that are rejected.
     *
     * @return array{nonce:string, pkce_verifier:string, redirect_after:string, provider:string}|null
     */
    private function consumeChallenge(string $state): ?array
    {
        global $conf, $db;
        $sql = "SELECT rowid, nonce, pkce_verifier, redirect_after, provider, created_at, consumed_at"
             . " FROM ".MAIN_DB_PREFIX."strongauth_idp_challenge"
             . " WHERE state = '".$db->escape($state)."' LIMIT 1";
        $res = $db->query($sql);
        if (!$res || $db->num_rows($res) === 0) {
            return null;
        }
        $row = $db->fetch_array($res);
        if (!empty($row['consumed_at'])) {
            return null;        // already used → replay attack
        }
        $createdTs = strtotime($row['created_at']);
        if ($createdTs === false || (time() - $createdTs) > 600) {
            return null;        // expired (10 min)
        }
        if ($row['provider'] !== $this->id()) {
            return null;        // wrong provider for this state
        }
        $db->query("UPDATE ".MAIN_DB_PREFIX."strongauth_idp_challenge SET consumed_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."' WHERE rowid = ".(int) $row['rowid']);
        return array(
            'nonce'          => $row['nonce'],
            'pkce_verifier'  => $row['pkce_verifier'],
            'redirect_after' => $row['redirect_after'],
            'provider'       => $row['provider'],
        );
    }

    /**
     * Cryptographically random token, URL-safe base64.
     */
    public static function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
