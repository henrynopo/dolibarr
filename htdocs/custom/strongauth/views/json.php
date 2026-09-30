<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * WebAuthn JSON endpoint — used by the browser via fetch() to drive the
 * registration and authentication ceremonies.
 *
 * POST /custom/strongauth/views/json.php
 *   body: { "action": "register_begin",   "factor": "webauthn" }
 *       | { "action": "register_finish",  "factor": "webauthn", "attestation": { ... } }
 *       | { "action": "assert_begin",     "factor": "webauthn" }
 *       | { "action": "assert_finish",    "factor": "webauthn", "assertion": { ... } }
 *
 * This page must be reachable BOTH pre-login (assert_* runs while the user is
 * in the 2FA-pending state) and post-login (register_* runs from the user
 * card). Dolibarr's main.inc.php would bounce anonymous requests to the login
 * form, so we define NOLOGIN and manually resume the user from the session
 * when one exists (mirroring main.inc.php's own session-resume logic).
 */

if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');   // JSON fetch() posts cannot carry Dolibarr's form token
}

require_once __DIR__.'/../../../main.inc.php';
require_once __DIR__.'/../lib/strongauth.lib.php';

// Self-diagnosing fatals: some hosts swallow PHP errors, leaving the browser
// with an empty body ("Unexpected end of JSON input"). Emit the fatal as JSON
// so the failure surfaces in the page's error line and the Network tab.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(array(
            'ok' => false,
            'error' => 'PHP fatal: '.$e['message'].' @ '.basename($e['file']).':'.$e['line'],
        ));
    }
});

// Manual session resume: with NOLOGIN, main.inc.php skips authentication
// entirely, so $user is a bare object. If a Dolibarr session exists, load it.
$sessionUser = null;
if (!empty($_SESSION['dol_login'])) {
    $sessionUser = new User($db);
    if ($sessionUser->fetch(0, $_SESSION['dol_login'], '', 1, -1) <= 0) {
        $sessionUser = null;
    }
}

$langs->load('strongauth@strongauth');

header('Content-Type: application/json');

$rawBody = file_get_contents('php://input');
$payload = is_string($rawBody) ? json_decode($rawBody, true) : null;
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(array('error' => 'malformed JSON body'));
    exit;
}

$action = isset($payload['action']) ? (string) $payload['action'] : '';
$factor = isset($payload['factor']) ? (string) $payload['factor'] : '';

// Request-trace log: HTTP-layer oddities on this host swallow responses, so
// persist the execution trail to a file we control. Remove when stable.
if (in_array($action, array('register_begin', 'register_finish', 'assert_begin'), true)) {
    @file_put_contents(
        __DIR__.'/../webauthn_trace.log',
        date('c')." action={$action} actor=".(int) ($actorId ?? 0)
        ." body=".(isset($payload['attestation']) ? substr((string) json_encode($payload['attestation']), 0, 120) : '-')
        ."\n",
        FILE_APPEND
    );
}
if ($factor !== 'webauthn') {
    http_response_code(400);
    echo json_encode(array('error' => 'unsupported factor'));
    exit;
}

/** Effective actor id: logged-in user for registration, pending-2FA user for assertion. */
$actorId = 0;
if (in_array($action, array('register_begin', 'register_finish'), true)) {
    $actorId = ($sessionUser !== null) ? (int) $sessionUser->id : 0;
    if ($actorId <= 0) {
        http_response_code(401);
        echo json_encode(array('error' => 'authentication required'));
        exit;
    }
    // Passkey registration is for internal-domain users only and only when the
    // admin master switch is on. webauthn_enroll.php enforces this for the UI;
    // repeat it here so a direct fetch() cannot bypass the policy.
    if (empty($conf->global->STRONGAUTH_ALLOW_WEBAUTHN)) {
        http_response_code(403);
        echo json_encode(array('error' => 'passkey disabled'));
        exit;
    }
    $orch = new StrongAuth_Orchestrator($db);
    if (!$orch->isInternalDomain((string) ($sessionUser->email ?? ''))) {
        StrongAuth_Audit::log($db, $actorId, $sessionUser->login,
            StrongAuth_Audit::EVENT_LOGIN_FAIL_2FA, 'webauthn',
            'external user attempted passkey registration');
        http_response_code(403);
        echo json_encode(array('error' => 'passkey not available for this account'));
        exit;
    }
} else {
    $actorId = isset($_SESSION['strongauth_pending_2fa_userid'])
        ? (int) $_SESSION['strongauth_pending_2fa_userid']
        : 0;
    if ($actorId <= 0) {
        http_response_code(400);
        echo json_encode(array('error' => 'no pending 2FA session'));
        exit;
    }
    // Only users whose resolved auth path offers webauthn may start an
    // assertion (external users must never authenticate with a Passkey).
    $pendingFactors = $_SESSION['strongauth_pending_path']['available_factors'] ?? array();
    if (!in_array('webauthn', $pendingFactors, true)) {
        http_response_code(403);
        echo json_encode(array('error' => 'passkey not available for this account'));
        exit;
    }
}

$factorObj = new Factor_WebAuthn();

try {
    switch ($action) {
        case 'register_begin':
            $resp = $factorObj->beginRegistration(
                $actorId,
                $sessionUser->login ?? 'user',
                $sessionUser->getFullName($langs) ?: ($sessionUser->login ?? 'User')
            );
            echo json_encode(array('ok' => true, 'options' => $resp));
            exit;

        case 'register_finish':
            @file_put_contents(__DIR__.'/../webauthn_trace.log', date('c')." finish: entering try\n", FILE_APPEND);
            $attestation = isset($payload['attestation']) ? json_encode($payload['attestation'], JSON_UNESCAPED_SLASHES) : '';
            if ($attestation === '') {
                http_response_code(400);
                echo json_encode(array('error' => 'missing attestation'));
                exit;
            }
            try {
                $resp = $factorObj->finishRegistration($actorId, $attestation);
                @file_put_contents(__DIR__.'/../webauthn_trace.log', date('c')." finish: OK cred=".substr($resp['credential_id'] ?? '?', 0, 16)."\n", FILE_APPEND);
                echo json_encode(array('ok' => true, 'credential' => $resp));
            } catch (Throwable $fin) {
                @file_put_contents(__DIR__.'/../webauthn_trace.log', date('c')." finish: THROWN ".get_class($fin).": ".$fin->getMessage()."\n", FILE_APPEND);
                throw $fin;
            }
            exit;

        case 'assert_begin':
            // Lockout applies to the WebAuthn assertion path as well — refuse
            // to even hand out a challenge for a locked account.
            if (StrongAuth_Lockout::isLocked($db, $actorId)) {
                StrongAuth_Audit::log($db, $actorId, null,
                    StrongAuth_Audit::EVENT_LOGIN_LOCKED, 'webauthn');
                http_response_code(429);
                echo json_encode(array('error' => 'account locked'));
                exit;
            }
            $resp = $factorObj->beginAssertion($actorId);
            echo json_encode(array('ok' => true, 'options' => $resp));
            exit;
            // NOTE: there is deliberately NO assert_finish here. The browser
            // puts the assertion JSON into the login form's hidden
            // strongauth_webauthn input; afterLogin -> Factor_WebAuthn::verify()
            // consumes the challenge and verifies the signature in one place.
            // Verifying here instead would burn the challenge and make the
            // subsequent form submit always fail.

        default:
            http_response_code(400);
            echo json_encode(array('error' => 'unknown action'));
            exit;
    }
} catch (Throwable $e) {
    dol_syslog('json.php: '.$e->getMessage(), LOG_WARNING);
    http_response_code(400);
    echo json_encode(array('error' => get_class($e).': '.$e->getMessage()));
    exit;
}
