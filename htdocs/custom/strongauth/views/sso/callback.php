<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * SSO callback controller — IdP redirect target.
 *
 * URL: /custom/strongauth/views/sso/callback.php?provider=entra&state=...&code=...
 *
 * Steps:
 *   1. Verify state matches a row in llx_strongauth_idp_challenge (consumed on use)
 *   2. Exchange code for tokens via the OIDC client
 *   3. Verify ID token (signature, iss, aud, nonce)
 *   4. Resolve Dolibarr user by email, upsert binding
 *   5. Issue the one-time SSO session token (cookie + DB row)
 *   6. Redirect to index.php with actionlogin=login&username=<login> —
 *      Dolibarr's own authentication pipeline then validates the cookie via
 *      core/login/functions_strongauth_sso.php (module_parts['login']) and
 *      establishes the session natively, afterLogin included.
 *
 * The IdP redirects here with NO Dolibarr session, hence NOLOGIN.
 */

if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}

// Locate main.inc.php by probing upward (views/sso/ is 4 levels below htdocs root).
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php'))    $res = @include(__DIR__.'/../../../main.inc.php');
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) $res = @include(__DIR__.'/../../../../main.inc.php');
if (!$res) die('Include of main fails');
require_once __DIR__.'/../../lib/strongauth.lib.php';

global $langs;
$langs->load('strongauth@strongauth');

$providerId = GETPOST('provider', 'alphanohtml');
if ($providerId === '') {
    http_response_code(400);
    exit('Missing provider parameter');
}

$provider = StrongAuth_ProviderRegistry::get($providerId);
if ($provider === null) {
    http_response_code(404);
    exit('Provider not configured');
}

try {
    $result = $provider->handleCallback(array(
        'state' => GETPOST('state', 'alphanohtml'),
        'code'  => GETPOST('code',  'alphanohtml'),
    ));

    // Fetch the login so the redirect can prefill index.php's login loop.
    $ssoUser = new User($db);
    if ($ssoUser->fetch((int) $result['fk_user']) <= 0) {
        throw new RuntimeException('SSO user no longer exists');
    }

    // One-time SSO cookie. functions_strongauth_sso() validates the DB row,
    // consumes it, and deletes this cookie during the login pass.
    $cookieName  = 'strongauth_sso';
    $cookieValue = $result['session_token'].':'.(int) $result['fk_user'];
    setcookie(
        $cookieName,
        $cookieValue,
        array(
            'expires'  => $result['expires_at'],
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );

    // actionlogin=login + username make main.inc.php enter its login loop
    // without a form POST; the authmode chain (must include strongauth_sso,
    // see admin/setup.php warning) picks up the cookie and logs the user in.
    // The token is required when CSRFCHECK_WITH_TOKEN is defined in conf.php —
    // the callback shares the session with the login page, so newToken() here
    // validates on arrival.
    header('Location: '.DOL_URL_ROOT.'/index.php?actionlogin=login&username='.urlencode($ssoUser->login).'&token='.newToken());
    exit;
} catch (Throwable $e) {
    dol_syslog('sso/callback.php: '.$e->getMessage(), LOG_ERR);
    dol_syslog('sso/callback.php trace: '.str_replace("\n", ' | ', $e->getTraceAsString()), LOG_ERR);
    $langs->load('errors');
    http_response_code(401);
    ?>
    <!doctype html>
    <html><head><title>SSO login failed</title></head><body>
    <h1><?php echo htmlspecialchars($langs->trans('StrongAuthSsoFailedTitle', $provider->label())); ?></h1>
    <p><?php echo htmlspecialchars($langs->trans('StrongAuthSsoFailedBody')); ?></p>
    <p style="color:#666;font-size:small"><?php echo htmlspecialchars($e->getMessage()); ?></p>
    <pre style="color:#999;font-size:x-small;text-align:left;background:#f6f6f6;padding:8px;border:1px solid #ddd;white-space:pre-wrap;"><?php echo htmlspecialchars($e->getTraceAsString()); ?></pre>
    <p><a href="<?php echo htmlspecialchars(DOL_URL_ROOT.'/index.php'); ?>"><?php echo htmlspecialchars($langs->trans('StrongAuthBackToLogin')); ?></a></p>
    </body></html>
    <?php
    exit;
}
