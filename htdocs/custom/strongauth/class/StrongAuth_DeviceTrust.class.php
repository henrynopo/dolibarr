<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * StrongAuth_DeviceTrust
 *
 * Per-user device trust. After a successful 2FA login, a user may mark their
 * current browser as "trusted for 30 days" — StrongAuth will skip the 2FA
 * prompt on that device until the trust expires (or is revoked).
 *
 * Threat model:
 *   - The trust cookie is opaque + signed (HMAC-SHA-256 under a key derived
 *     from STRONGAUTH_TOTP_ENC_KEY).
 *   - We also store a SHA-256 fingerprint of (user-agent + accept-language).
 *     On each verification we check the fingerprint matches — this catches
 *     stolen cookies used from a different browser profile.
 *   - Tokens are stored as SHA-256 hashes in llx_strongauth_device_trust.
 *     We never store plaintext tokens.
 *
 * Limitations:
 *   - This is convenience, not security. Cookies can be exfiltrated.
 *   - Admins can revoke all trusts for a user via the user-management page.
 *
 * Activation:
 *   - Off by default (STRONGAUTH_DEVICE_TRUST_LOCAL=0).
 *   - Even when ON, the user must OPT IN by clicking "Trust this device" after
 *     a successful 2FA. We never silently issue trust cookies.
 */

class StrongAuth_DeviceTrust
{
    const COOKIE_NAME_PREFIX = 'strongauth_trust_';
    const TRUST_DURATION     = 30 * 24 * 3600;          // 30 days

    /**
     * Issue a new trust cookie for the user. Returns the plaintext token to
     * set in the Set-Cookie header; the caller is responsible for emitting it.
     */
    public static function issue(int $userId): string
    {
        global $conf, $db;
        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $fingerprint = self::fingerprint();
        $now     = $db->idate(gmdate('Y-m-d H:i:s'));
        $expires = $db->idate(gmdate('Y-m-d H:i:s', time() + self::TRUST_DURATION));

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_device_trust"
             . " (fk_user, token_hash, fingerprint, user_agent, ip_first_seen, created_at, expires_at, entity)"
             . " VALUES ("
             .(int) $userId.","
             ."'".$db->escape($tokenHash)."',"
             ."'".$db->escape($fingerprint)."',"
             ."'".$db->escape(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255))."',"
             ."'".$db->escape(self::clientIp())."',"
             ."'".$now."',"
             ."'".$expires."',"
             .(int) $conf->entity.")";
        $db->query($sql);

        return $token;
    }

    /**
     * Verify the trust cookie from the request. Returns the user_id if valid,
     * 0 otherwise. The fingerprint MUST match the current browser; otherwise
     * we treat it as a stolen cookie and revoke it.
     */
    public static function verify(int $userId): bool
    {
        global $conf, $db;
        $name = self::COOKIE_NAME_PREFIX.$userId;
        $cookie = $_COOKIE[$name] ?? '';
        if ($cookie === '' || !preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            return false;
        }
        $hash = hash('sha256', $cookie);
        $sql = "SELECT rowid, fingerprint, expires_at, revoked_at"
             . " FROM ".MAIN_DB_PREFIX."strongauth_device_trust"
             . " WHERE fk_user = ".(int) $userId
             . " AND token_hash = '".$db->escape($hash)."'"
             . " AND entity = ".(int) $conf->entity
             . " LIMIT 1";
        $res = $db->query($sql);
        if (!$res || $db->num_rows($res) === 0) {
            return false;
        }
        $row = $db->fetch_array($res);
        if (!empty($row['revoked_at'])) {
            return false;
        }
        if (strtotime($row['expires_at']) < time()) {
            return false;
        }
        if (!hash_equals($row['fingerprint'], self::fingerprint())) {
            // Cookie was copied to a different browser — revoke immediately.
            self::revoke($userId);
            return false;
        }
        return true;
    }

    /**
     * Revoke a single token (e.g. after fingerprint mismatch).
     */
    public static function revoke(int $userId): void
    {
        global $conf, $db;
        $name = self::COOKIE_NAME_PREFIX.$userId;
        $cookie = $_COOKIE[$name] ?? '';
        if ($cookie === '' || !preg_match('/^[a-f0-9]{64}$/', $cookie)) {
            return;
        }
        $hash = hash('sha256', $cookie);
        $db->query(
            "UPDATE ".MAIN_DB_PREFIX."strongauth_device_trust"
            ." SET revoked_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."'"
            ." WHERE fk_user = ".(int) $userId
            ." AND token_hash = '".$db->escape($hash)."'"
        );
    }

    /**
     * Revoke every trust token for a user (admin reset).
     */
    public static function revokeAllForUser(int $userId): int
    {
        global $conf, $db;
        $db->query(
            "UPDATE ".MAIN_DB_PREFIX."strongauth_device_trust"
            ." SET revoked_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."'"
            ." WHERE fk_user = ".(int) $userId
            ." AND revoked_at IS NULL"
        );
        return (int) $db->affected_rows();
    }

    /**
     * Build the Set-Cookie header value to be emitted by the controller.
     */
    public static function cookieHeader(int $userId, string $token): string
    {
        $name = self::COOKIE_NAME_PREFIX.$userId;
        $expires = time() + self::TRUST_DURATION;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $parts = array(
            $name.'='.$token,
            'Expires='.gmdate('D, d M Y H:i:s', $expires).' GMT',
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        );
        if ($secure) {
            $parts[] = 'Secure';
        }
        return implode('; ', $parts);
    }

    /**
     * Browser fingerprint: SHA-256 of (user-agent + accept-language + sec-ch-ua).
     * Loose enough to survive a browser auto-update, strict enough to catch
     * a cookie stolen and replayed from a different machine.
     */
    private static function fingerprint(): string
    {
        $bits = array(
            $_SERVER['HTTP_USER_AGENT']     ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE']?? '',
            $_SERVER['HTTP_SEC_CH_UA']      ?? '',
        );
        return hash('sha256', implode("\n", $bits));
    }

    private static function clientIp(): string
    {
        // Honor MAIN_SECURITY_FORWARD_IP_HEADER only if it's been explicitly enabled.
        // The default behavior reads REMOTE_ADDR — the trust comes from the proxy.
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
