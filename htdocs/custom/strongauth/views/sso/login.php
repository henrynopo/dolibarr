<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * SSO login controller — entry point.
 *
 * URL: /custom/strongauth/views/sso/login.php?provider=entra
 *
 * Steps:
 *   1. Look up the requested provider (must be configured)
 *   2. Generate state + nonce + PKCE, persist challenge row
 *   3. Redirect browser to the IdP's authorization endpoint
 *
 * This endpoint is reached from the login page's SSO buttons, i.e. by
 * anonymous visitors — NOLOGIN keeps main.inc.php from bouncing them back
 * to the login form before the IdP redirect can happen.
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

// Optional login_hint (e.g. the email the user typed on the login page).
$loginHint = GETPOST('login_hint', 'email');

try {
    $url = $provider->beginAuthorization(null, array(
        'login_hint'    => $loginHint,
        'redirect_after'=> DOL_URL_ROOT.'/',
    ));
    header('Location: '.$url);
    exit;
} catch (Throwable $e) {
    dol_syslog('sso/login.php: '.$e->getMessage(), LOG_ERR);
    http_response_code(500);
    exit('SSO initiation failed. Please contact your administrator.');
}
