<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * First-time TOTP enrollment wizard.
 *
 * Flow:
 *   step=1 → show QR + secret + require user to enter a 6-digit code to confirm
 *   step=2 → on confirmation, generate backup codes and show them once
 *   step=3 → user checks "I have saved my codes" → finalize enrollment
 *
 * The wizard is reached in three ways:
 *   - Forced     : external user logs in without an enrolled TOTP — afterLogin
 *                  bounces them here PRE-LOGIN (session marker
 *                  strongauth_must_enroll_totp identifies the target user)
 *   - Self       : logged-in user clicks "Set up TOTP" from factor_manage.php
 *   - Admin      : admin with strongauth->reset re-enrolls on behalf of another
 *                  user via ?for_user=<id>
 *
 * Because the forced path runs before any Dolibarr session exists, this page
 * is NOLOGIN and resumes the actor manually from $_SESSION['dol_login'] when
 * present. The three modes resolve the enrollment target as follows:
 *   forced  → marker userid (must match pending 2FA state)
 *   admin   → for_user param (requires resumed admin + strongauth->reset)
 *   self    → resumed user id
 */

if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}

$res = 0;
if (!$res && file_exists('../main.inc.php'))     $res = @include('../main.inc.php');
if (!$res && file_exists('../../main.inc.php'))  $res = @include('../../main.inc.php');
if (!$res && file_exists('../../../main.inc.php')) $res = @include('../../../main.inc.php');
if (!$res) die('Include of main fails');

require_once __DIR__.'/../lib/strongauth.lib.php';

// Manually resume the acting user (NOLOGIN skips main.inc.php's own resume).
$actor = null;
if (!empty($_SESSION['dol_login'])) {
    $actor = new User($db);
    if ($actor->fetch(0, $_SESSION['dol_login'], '', 1, -1) <= 0) {
        $actor = null;
    }
}

// ---- Resolve the enrollment target ----
$forUserId = (int) GETPOST('for_user', 'int');
$mustEnrollId = (int) ($_SESSION['strongauth_must_enroll_totp'] ?? 0);
$forcedMode = false;

if ($mustEnrollId > 0
    && (int) ($_SESSION['strongauth_pending_2fa_userid'] ?? 0) === $mustEnrollId) {
    // Forced first-time enrollment (pre-login).
    $targetId   = $mustEnrollId;
    $forcedMode = true;
} elseif ($forUserId > 0) {
    // Admin re-enrollment on behalf of another user.
    if ($actor === null || empty($actor->rights->strongauth->reset)) {
        accessforbidden('Admin only');
    }
    $targetId = $forUserId;
} elseif ($actor !== null && $actor->id > 0) {
    // Self-service enrollment.
    $targetId = (int) $actor->id;
} else {
    header('Location: '.DOL_URL_ROOT.'/index.php');
    exit;
}

// Load the target (entity guard: entity=0 users are visible from every
// entity per Dolibarr semantics; only users pinned to a different entity
// are out of scope).
$targetUser = new User($db);
if ($targetUser->fetch($targetId) <= 0
    || ((int) $targetUser->entity !== 0 && (int) $targetUser->entity !== (int) $conf->entity)) {
    accessforbidden('Invalid target user');
}

$langs->load('strongauth@strongauth');
$langs->load('errors');

// Must not be an internal SSO-only user (those use Entra ID, not TOTP).
$orchestrator = new StrongAuth_Orchestrator($db);
if ($orchestrator->isInternalDomain($targetUser->email ?? '')) {
    header('Location: '.DOL_URL_ROOT.'/user/card.php?id='.(int) $targetUser->id);
    exit;
}

// GETPOST('...','int') only VALIDATES numerics — it returns a string.
// Cast explicitly: the routing below uses === comparisons against ints.
$step         = (int) GETPOST('step', 'int');
$confirmCode   = GETPOST('confirm_code', 'alphanohtml');
$ackSaved      = (int) GETPOST('ack_saved', 'int');
$sessionSecret = $_SESSION['strongauth_enroll_secret'] ?? null;
$sessionBackup = $_SESSION['strongauth_enroll_backupcodes'] ?? null;

// Keep the wizard bound to one target: if the stored secret belongs to a
// different user than the current target, restart from step 1.
if ($step === 2 || $step === 3) {
    $secretFor = (int) ($_SESSION['strongauth_enroll_for'] ?? 0);
    if ($secretFor !== (int) $targetUser->id) {
        $step = 1;
        $sessionSecret = null;
        $sessionBackup = null;
    }
}

// ---- Pre-render routing: ALL redirects happen before any output ----

// STEP 3 finalize: acknowledge + finish → redirect (must precede llxHeader).
if ($step === 3 && $ackSaved && !empty($sessionBackup)) {
    unset($_SESSION['strongauth_enroll_secret']);
    unset($_SESSION['strongauth_enroll_backupcodes']);
    unset($_SESSION['strongauth_enroll_for']);
    unset($_SESSION['strongauth_must_enroll_totp']);

    setEventMessage($langs->trans('StrongAuthEnrollDone'));

    if ($forcedMode) {
        // Pre-login flow: the user proved a live code during this session, so
        // afterLogin honors the short-lived marker for the final password POST.
        $_SESSION['strongauth_2fa_passed'][(int) $targetUser->id] = time();
        header('Location: '.DOL_URL_ROOT.'/index.php');
    } elseif ($forUserId > 0 && $actor !== null && (int) $forUserId !== (int) $actor->id) {
        // Admin enrolled for someone else — back to the target's card.
        header('Location: '.DOL_URL_ROOT.'/user/card.php?id='.(int) $targetUser->id);
    } else {
        $_SESSION['strongauth_2fa_passed'][(int) $targetUser->id] = time();
        header('Location: '.DOL_URL_ROOT.'/');
    }
    exit;
}

// Anything that is not a renderable step → restart the wizard.
if ($step !== 1 && $step !== 2) {
    header('Location: ?step=1'.($forcedMode || $forUserId <= 0 ? '' : '&for_user='.$forUserId));
    exit;
}

$title = $langs->trans('StrongAuthEnrollTitle');

// Pre-login (forced) mode cannot use llxHeader/llxFooter — Dolibarr's page
// frame depends on a loaded user + menus. Render a minimal HTML shell instead.
if ($forcedMode) {
    print '<!doctype html><html><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>'.dol_escape_htmltag($title).'</title>'
        .'<style>body{font-family:sans-serif;max-width:640px;margin:2em auto;padding:0 1em;}'
        .'code{font-family:monospace;} .error{color:#a00;}</style>'
        .'</head><body>';
} else {
    llxHeader('', $title);
}

print '<div class="loginpage-head">';
print '<h2>'.$title.($forcedMode ? '' : ' — '.dol_escape_htmltag($targetUser->getFullName($langs))).'</h2>';
print '</div>';

$factor = new Factor_TOTP();

// ============== STEP 1: present QR + require confirmation ==============
if ($step === 1 || ($step === 2 && empty($sessionSecret))) {
    $secret = $factor->generateSecret();
    $_SESSION['strongauth_enroll_secret'] = $secret;
    $_SESSION['strongauth_enroll_for']    = (int) $targetUser->id;

    $provisioningUri = $factor->getProvisioningUri($secret, $targetUser->email ?: $targetUser->login);
    $qrDataUri       = $factor->getQrCodeDataUri($provisioningUri);

    print '<div class="loginpage-body">';
    print '<table class="centpercent"><tr><td>';
    print '<p>'.$langs->trans('StrongAuthEnrollStep1Intro').'</p>';

    print '<div style="text-align:center;margin:1em 0;">';
    print '<img src="'.$qrDataUri.'" alt="TOTP QR code" style="max-width:240px;border:1px solid #ccc;padding:8px;background:#fff;">';
    print '</div>';

    print '<p><label>'.$langs->trans('StrongAuthEnrollSecretManual').'</label></p>';
    print '<p><code style="font-family:monospace;font-size:1.2em;background:#f5f5f5;padding:6px 12px;border-radius:4px;">'
        .dol_escape_htmltag($secret).'</code></p>';

    print '<form method="POST">';
    print '<input type="hidden" name="step" value="2">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    if (!$forcedMode) {
        print '<input type="hidden" name="for_user" value="'.(int) $targetUser->id.'">';
    }
    print '<p>'.$langs->trans('StrongAuthEnrollConfirmPrompt').'</p>';
    print '<input type="text" name="confirm_code" maxlength="6" pattern="[0-9]*" inputmode="numeric"'
        .' autocomplete="one-time-code" autofocus class="flat" size="10" placeholder="123456">';
    print ' <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('StrongAuthEnrollVerify')).'">';
    print '</form>';

    print '</td></tr></table>';
    print '</div>';
}

// ============== STEP 2: verify code, then show backup codes ==============
elseif ($step === 2 && !empty($sessionSecret)) {
    // At step 2 the user is not yet enrolled — Factor_TOTP::verify() would always
    // return false because no DB row exists. Verify the in-session secret directly.
    $code = preg_replace('/\s+/', '', (string) $confirmCode);
    if (strlen($code) !== 6 || !ctype_digit($code) || !$factor->verifyEnrollmentCode($sessionSecret, $code)) {
        renderEnrollInvalidCode();
        if ($forcedMode) { print '</body></html>'; } else { llxFooter(); }
        $db->close(); exit;
    }

    // Save TOTP secret encrypted in DB.
    $factor->enroll((int) $targetUser->id, $sessionSecret);

    // Generate backup codes (one-time display).
    $codes = (new Factor_BackupCode())->generate((int) $targetUser->id);
    $_SESSION['strongauth_enroll_backupcodes'] = $codes;

    print '<div class="loginpage-body">';
    print '<p class="ok">'.$langs->trans('StrongAuthEnrollTotpSaved').'</p>';
    print '<p><b>'.$langs->trans('StrongAuthEnrollBackupCodesHeader').'</b></p>';
    print '<p>'.$langs->trans('StrongAuthEnrollBackupCodesWarning').'</p>';

    print '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:1em 0;'
        .'padding:12px;background:#f8f8f8;border:1px dashed #999;font-family:monospace;">';
    foreach ($codes as $c) {
        print '<div>'.dol_escape_htmltag($c).'</div>';
    }
    print '</div>';

    print '<form method="POST">';
    print '<input type="hidden" name="step" value="3">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    if (!$forcedMode) {
        print '<input type="hidden" name="for_user" value="'.(int) $targetUser->id.'">';
    }
    print '<p><label><input type="checkbox" name="ack_saved" value="1" required> '
        .$langs->trans('StrongAuthEnrollAckSave').'</label></p>';
    print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('StrongAuthEnrollFinish')).'">';
    print '</form>';

    print '</div>';
}

if ($forcedMode) {
    print '</body></html>';
} else {
    llxFooter();
}
$db->close();

// ---------------------------------------------------------------------------
// Local helpers
// ---------------------------------------------------------------------------

function renderEnrollInvalidCode(): void
{
    global $langs;
    print '<div class="error">'.$langs->trans('StrongAuthEnrollInvalidCode').'</div>';
    print '<p><a href="?step=1">'.$langs->trans('StrongAuthEnrollBack').'</a></p>';
}
