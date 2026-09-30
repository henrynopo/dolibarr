<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * StrongAuth_OIDCClient
 *
 * Lightweight OpenID Connect client. We deliberately do NOT use a third-party
 * OAuth client library — the attack surface (token handling, state management,
 * PKCE) is small enough that a focused, auditable implementation is safer than
 * pulling in a generic client.
 *
 * What this class provides:
 *   - OIDC discovery (.well-known/openid-configuration)
 *   - Authorization URL builder (with PKCE + state + nonce)
 *   - Authorization-code → token exchange
 *   - ID token verification (RS256/ES256, JWKS rotation, aud/iss/exp/nonce)
 *   - UserInfo fetching as a fallback when claims are missing in the ID token
 *
 * What this class does NOT do:
 *   - Account linking — handled by StrongAuth_IdpBindingStore
 *   - Session/cookie management — handled by the controller scripts
 *   - Provider-specific quirks (Apple's ES256 client_secret, Entra tenant routing)
 *     — handled by subclasses of Provider_OIDCBase
 */

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class StrongAuth_OIDCClient
{
    /** @var array<string,mixed>|null Cached discovery document for the current request */
    private static $discoveryCache = array();

    /** @var array<string,string>|null Cached JWKS for the current request */
    private static $jwksCache = null;

    /**
     * Fetch and cache the OIDC discovery document.
     *
     * @param  string $issuerUrl  The OIDC issuer URL (used to derive .well-known/openid-configuration)
     * @return array<string,mixed>
     */
    public function discovery(string $issuerUrl): array
    {
        if (isset(self::$discoveryCache[$issuerUrl])) {
            return self::$discoveryCache[$issuerUrl];
        }

        $wellKnown = rtrim($issuerUrl, '/').'/.well-known/openid-configuration';
        $doc = $this->httpGetJson($wellKnown);
        if (!is_array($doc) || empty($doc['authorization_endpoint'])) {
            throw new RuntimeException('OIDC discovery failed for '.$issuerUrl);
        }
        self::$discoveryCache[$issuerUrl] = $doc;
        return $doc;
    }

    /**
     * Build an authorization URL with PKCE + state + nonce.
     *
     * @param  array<string,mixed> $discovery  Result of discovery()
     * @param  array{
     *   client_id: string,
     *   redirect_uri: string,
     *   scope: string,
     *   state: string,
     *   nonce: string,
     *   code_challenge: string,
     *   code_challenge_method: string,
     *   login_hint?: string,
     *   prompt?: string,
     *   acr_values?: string,
     * } $params
     * @return string
     */
    public function buildAuthorizationUrl(array $discovery, array $params): string
    {
        $query = array(
            'response_type'         => 'code',
            'client_id'             => $params['client_id'],
            'redirect_uri'          => $params['redirect_uri'],
            'scope'                 => $params['scope'],
            'state'                 => $params['state'],
            'nonce'                 => $params['nonce'],
            'code_challenge'        => $params['code_challenge'],
            'code_challenge_method' => $params['code_challenge_method'],
        );
        if (!empty($params['login_hint'])) {
            $query['login_hint'] = $params['login_hint'];
        }
        if (!empty($params['prompt'])) {
            $query['prompt'] = $params['prompt'];
        }
        if (!empty($params['acr_values'])) {
            $query['acr_values'] = $params['acr_values'];
        }
        return $discovery['authorization_endpoint']
            .(strpos($discovery['authorization_endpoint'], '?') === false ? '?' : '&')
            .http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for tokens.
     *
     * @param  array<string,mixed> $discovery
     * @param  array{
     *   client_id: string,
     *   client_secret?: string,           // absent for PKCE-only public clients
     *   code: string,
     *   redirect_uri: string,
     *   code_verifier: string,
     * } $params
     * @return array{id_token:?string, access_token:?string, refresh_token:?string, token_type:?string, expires_in:?int, raw:array}
     */
    public function exchangeCode(array $discovery, array $params): array
    {
        $body = array(
            'grant_type'    => 'authorization_code',
            'client_id'     => $params['client_id'],
            'code'          => $params['code'],
            'redirect_uri'  => $params['redirect_uri'],
            'code_verifier' => $params['code_verifier'],
        );
        if (!empty($params['client_secret'])) {
            $body['client_secret'] = $params['client_secret'];
        }

        $raw = $this->httpPostForm($discovery['token_endpoint'], $body);
        return array(
            'id_token'     => $raw['id_token']     ?? null,
            'access_token' => $raw['access_token'] ?? null,
            'refresh_token'=> $raw['refresh_token']?? null,
            'token_type'   => $raw['token_type']   ?? null,
            'expires_in'   => isset($raw['expires_in']) ? (int) $raw['expires_in'] : null,
            'raw'          => $raw,
        );
    }

    /**
     * Verify an ID token's signature, issuer, audience and nonce.
     *
     * Returns the decoded claims. Throws on any failure.
     *
     * @param  string $idToken     Raw JWT string
     * @param  string $expectedIss The OIDC issuer we expect
     * @param  string $expectedAud client_id (which is the audience)
     * @param  string $expectedNonce
     * @return array<string,mixed>
     */
    public function verifyIdToken(string $idToken, string $expectedIss, string $expectedAud, string $expectedNonce): array
    {
        $jwksUrl = $this->discovery($expectedIss)['jwks_uri'] ?? null;
        if (!$jwksUrl) {
            throw new RuntimeException('OIDC discovery did not include jwks_uri');
        }
        $jwks = $this->fetchJwks($jwksUrl);
        $keys = JWK::parseKeySet(self::normalizeJwks($jwks));

        // JWT::$leeway = 60 covers minor clock skew between us and the IdP.
        JWT::$leeway = 60;
        $decoded = JWT::decode($idToken, $keys);

        $claims = (array) $decoded;
        if (($claims['iss'] ?? '') !== $expectedIss) {
            throw new RuntimeException('ID token issuer mismatch');
        }
        // aud may be a string or an array — both are valid per spec.
        $aud = $claims['aud'] ?? null;
        $audOk = is_string($aud) ? ($aud === $expectedAud) : (is_array($aud) && in_array($expectedAud, $aud, true));
        if (!$audOk) {
            throw new RuntimeException('ID token audience mismatch');
        }
        if (($claims['nonce'] ?? '') !== $expectedNonce) {
            throw new RuntimeException('ID token nonce mismatch');
        }
        if (!isset($claims['sub']) || $claims['sub'] === '') {
            throw new RuntimeException('ID token missing sub claim');
        }
        return $claims;
    }

    /**
     * Fetch the UserInfo endpoint using the access token. Some IdPs (Apple) put
     * email only in the userinfo response, not in the ID token.
     *
     * @return array<string,mixed>
     */
    public function fetchUserInfo(array $discovery, string $accessToken): array
    {
        $url = $discovery['userinfo_endpoint'] ?? null;
        if (!$url) {
            return array();
        }
        return $this->httpGetJson($url, array('Authorization: Bearer '.$accessToken));
    }

    // -----------------------------------------------------------------------
    // HTTP helpers (no Guzzle dependency — Dolibarr ships its own curl wrapper)
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    public function httpGetJson(string $url, array $headers = array()): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => array_merge(array('Accept: application/json'), $headers),
        ));
        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('HTTP GET failed: '.curl_error($ch).' (URL: '.$url.')');
        }
        curl_close($ch);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('HTTP GET returned non-JSON (URL: '.$url.')');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    public function httpPostForm(string $url, array $form): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($form),
            CURLOPT_HTTPHEADER     => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ),
        ));
        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('HTTP POST failed: '.curl_error($ch).' (URL: '.$url.')');
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('HTTP POST returned non-JSON (URL: '.$url.', status: '.$status.')');
        }
        if ($status >= 400) {
            throw new RuntimeException('HTTP POST error '.$status.': '.($decoded['error_description'] ?? $decoded['error'] ?? 'unknown'));
        }
        return $decoded;
    }

    /**
     * Fetch and cache the JWKS for the current request.
     * @return array<string,mixed>
     */
    private function fetchJwks(string $url): array
    {
        if (self::$jwksCache !== null) {
            return self::$jwksCache;
        }
        $jwks = $this->httpGetJson($url);
        self::$jwksCache = $jwks;
        return $jwks;
    }

    /**
     * Ensure every JWK carries an "alg" — Entra/Google JWKS omit it, but
     * firebase/php-jwt v7 requires the parameter. Inferred from kty
     * (RSA→RS256, EC→ES256). Public static so tests exercise the SAME code
     * the login flow uses.
     */
    public static function normalizeJwks(array $jwks): array
    {
        if (!isset($jwks['keys']) || !is_array($jwks['keys'])) {
            throw new RuntimeException('JWKS did not include a keys array');
        }
        foreach ($jwks['keys'] as $i => $jwk) {
            if (empty($jwk['alg'])) {
                $jwks['keys'][$i]['alg'] = ($jwk['kty'] ?? '') === 'EC' ? 'ES256' : 'RS256';
            }
        }
        return $jwks;
    }
}
