<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Audit log: append-only, one row per authentication event.
 * Designed to be replicated off-server to SIEM for tamper resistance.
 */

class StrongAuth_Audit
{
    // Event type constants — keep stable; downstream SIEM rules depend on these.
    const EVENT_LOGIN_OK            = 'login_ok';
    const EVENT_LOGIN_FAIL_PASSWORD = 'login_fail_password';
    const EVENT_LOGIN_FAIL_2FA      = 'login_fail_2fa';
    const EVENT_LOGIN_LOCKED        = 'login_locked';
    const EVENT_FACTOR_ENROLL       = 'factor_enroll';
    const EVENT_FACTOR_RESET        = 'factor_reset';
    const EVENT_FACTOR_USE          = 'factor_use';
    const EVENT_BACKUPCODE_USE      = 'backupcode_use';
    const EVENT_BACKUPCODE_REGEN    = 'backupcode_regen';
    const EVENT_ADMIN_RESET         = 'admin_reset';
    const EVENT_KEY_ROTATE          = 'key_rotate';

    /**
     * Append an event to the audit log. Never throws — failures are silently
     * swallowed and dol_syslog'd so that audit problems do not block login.
     */
    public static function log(
        DoliDB $db,
        ?int $fkUser,
        ?string $usernameTry,
        string $eventType,
        ?string $method,
        ?string $detail = null
    ): void {
        global $conf;
        try {
            $ip  = self::clientIp();
            $ua  = self::clientUserAgent();
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_audit ("
                 . "event_time, fk_user, username_try, event_type, method, ip_address, user_agent, detail, entity"
                 . ") VALUES ("
                 . "'".$db->idate(gmdate('Y-m-d H:i:s'))."', "
                 .($fkUser !== null ? (int) $fkUser : 'NULL').", "
                 ."'".$db->escape($usernameTry ?? '')."', "
                 ."'".$db->escape($eventType)."', "
                 .(!empty($method) ? "'".$db->escape($method)."'" : 'NULL').", "
                 ."'".$db->escape($ip)."', "
                 ."'".$db->escape($ua)."', "
                 .(!empty($detail) ? "'".$db->escape($detail)."'" : 'NULL').", "
                 .(int) $conf->entity
                 .")";
            $db->query($sql);
        } catch (Throwable $e) {
            dol_syslog('StrongAuth_Audit::log failed: '.$e->getMessage(), LOG_ERR);
        }
    }

    private static function clientIp(): string
    {
        // Honor X-Forwarded-For when Dolibarr's reverse-proxy config is on
        // (MAIN_PROXY_URL or default behavior); otherwise trust REMOTE_ADDR.
        // We deliberately only walk ONE hop — `X-Forwarded-For: a, b, c` means
        //   a = original client, b = first proxy, c = last proxy = REMOTE_ADDR.
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (getDolGlobalString('MAIN_PROXY_URL') !== '' && $forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            if (!empty($parts[0]) && filter_var($parts[0], FILTER_VALIDATE_IP)) {
                return $parts[0];
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    private static function clientUserAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    }
}
