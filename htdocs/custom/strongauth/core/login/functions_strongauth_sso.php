<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * SSO session pickup via Dolibarr's native authentication pipeline.
 *
 * Registered through module_parts['login'] (modStrongauth descriptor). When
 * "strongauth_sso" is listed in the authentication method (setup:
 * strongauth_sso,dolibarr), checkLoginPassEntity() calls this function on
 * every login attempt. It validates the one-time SSO cookie left by
 * /views/sso/callback.php and, on success, returns the Dolibarr login so
 * Dolibarr itself establishes the session (triggers, last-login-date, and
 * the afterLogin hook for factor policy all run normally).
 *
 * @param string $usertotest      login typed in the form (ignored — identity
 *                                comes from the signed SSO session row)
 * @param string $passwordtotest  password typed in the form (ignored)
 * @param int    $entitytotest    entity the user is logging into
 * @return string                 Dolibarr login on success, '' otherwise
 */
function check_user_password_strongauth_sso($usertotest, $passwordtotest, $entitytotest = 1, $context = '')
{
    global $db, $conf;

    if (!is_object($db)) {
        return '';
    }

    $cookie = $_COOKIE['strongauth_sso'] ?? '';
    if (!is_string($cookie) || $cookie === '' || strpos($cookie, ':') === false) {
        return '';
    }
    list($token, $userId) = explode(':', $cookie, 2);
    $userId = (int) $userId;
    if ($userId <= 0 || !preg_match('/^[A-Za-z0-9_-]{32,}$/', $token)) {
        return '';
    }

    $sql = "SELECT s.rowid, s.fk_user, s.provider, s.expires_at, s.consumed_at, u.login, u.statut"
         . " FROM ".MAIN_DB_PREFIX."strongauth_sso_session AS s"
         . " INNER JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = s.fk_user"
         . " WHERE s.session_token = '".$db->escape($token)."'"
         . " AND s.fk_user = ".$userId
         . " AND s.entity = ".(int) $entitytotest
         . " LIMIT 1";
    $res = $db->query($sql);
    if (!$res || $db->num_rows($res) === 0) {
        return '';
    }
    $row = $db->fetch_object($res);

    // Replay / expiry / disabled-user checks.
    if (!empty($row->consumed_at)) {
        return '';
    }
    if (strtotime($row->expires_at) < time()) {
        return '';
    }
    if ($row->statut != 1) {
        return '';
    }

    // Consume the row so the token is single-use.
    $db->query(
        "UPDATE ".MAIN_DB_PREFIX."strongauth_sso_session"
        ." SET consumed_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."'"
        ." WHERE rowid = ".(int) $row->rowid
        ." AND consumed_at IS NULL"
    );

    // The one-time cookie has served its purpose — drop it from the browser.
    if (PHP_VERSION_ID >= 70300) {
        setcookie('strongauth_sso', '', array('expires' => time() - 3600, 'path' => '/'));
    } else {
        setcookie('strongauth_sso', '', time() - 3600, '/');
    }

    return $row->login;
}
