<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * User-managed authentication settings.
 *
 * Shows:
 *   - TOTP enrollment status (with re-enroll / disable buttons)
 *   - Backup codes remaining (with regenerate button)
 *   - Passkey enrollment (if admin enabled + user is internal)
 *
 * Actions:
 *   - op=re_enroll_totp : clear current TOTP, redirect to factor_enroll.php
 *   - op=disable_totp   : require password re-entry, then disable
 *   - op=regen_backup   : require password re-entry, regenerate backup codes
 *
 * Permission: user can manage their own; admin with right 'strongauth->reset'
 * can manage anyone.
 */

$res = 0;
if (!$res && file_exists('../main.inc.php'))     $res = @include('../main.inc.php');
if (!$res && file_exists('../../main.inc.php'))  $res = @include('../../main.inc.php');
if (!$res && file_exists('../../../main.inc.php')) $res = @include('../../../main.inc.php');
if (!$res) die('Include of main fails');

require_once __DIR__.'/../lib/strongauth.lib.php';

if (!defined('NOTOKENRENEWAL')) {
    // fine
}

$langs->load('strongauth@strongauth');
$langs->load('errors');

$id     = (int) GETPOST('id', 'int');
$op     = GETPOST('op', 'aZ09');
$pw     = GETPOST('confirm_password', 'alphanohtml');
$token  = GETPOST('token', 'alphanohtml');

if (empty($user->id)) {
    header('Location: '.DOL_URL_ROOT.'/index.php');
    exit;
}

// Whose card is being managed?
$targetId = $id ?: (int) $user->id;
// A user can always manage their own 2FA settings; admin needs the strongauth->reset
// right to manage anyone else's. Dolibarr does not have a canonical self-rights
// check for this — fall back to requiring a logged-in user.
$canManage = ($user->id == $targetId)
          || ($user->id != $targetId && !empty($user->rights->strongauth->reset));
if (!$canManage) {
    accessforbidden();
}

// Load target user (only fields we need).
$target = new User($db);
if ($target->fetch($targetId) <= 0) {
    accessforbidden('Invalid target user');
}
// Cross-entity guard: the target must be VISIBLE from the current entity.
// Dolibarr semantics: entity=0 users (super-admins, shared accounts) are
// visible from every entity — only a user pinned to a DIFFERENT entity
// (>0, != current) is out of scope.
if ($targetId !== (int) $user->id
    && (int) $target->entity !== 0
    && (int) $target->entity !== (int) $conf->entity) {
    accessforbidden('Cross-entity access denied');
}
if ($targetId !== $user->id) {
    // Admin must enter their own password to act on another user.
    $requireAdminPassword = true;
} else {
    $requireAdminPassword = false;
}

$orchestrator = new StrongAuth_Orchestrator($db);
$isInternal = $orchestrator->isInternalDomain($target->email ?? '');
$hasTotp    = $orchestrator->hasTotp($targetId);
$hasPasskey = $orchestrator->hasPasskey($targetId);
$allowPass  = !empty($conf->global->STRONGAUTH_ALLOW_WEBAUTHN) && $isInternal;
$unused     = (new Factor_BackupCode())->countUnused($targetId);

// ============== Operations ==============
if ($op) {
    // CSRF: main.inc.php already validates the request-level token against
    // $_SESSION['token'] for POST requests (when MAIN_SECURITY_CSRF_WITH_TOKEN is on).
    // We don't reimplement it here.

    // Sensitive ops require the actor's password (defense-in-depth: prevents
    // CSRF-driven disable/regenerate even if the session is hijacked).
    $needsPassword = in_array($op, array('disable_totp', 're_enroll_totp', 'regen_backup'), true)
                     && ($requireAdminPassword || $targetId === (int) $user->id);
    if ($needsPassword) {
        if (empty($pw) || !password_verify($pw, $user->pass_crypted)) {
            setEventMessage($langs->trans('StrongAuthPasswordRequired'), 'errors');
            header('Location: ?id='.$targetId);
            exit;
        }
    }

    if ($op === 'disable_totp') {
        (new Factor_TOTP())->disable($targetId, (int) $user->id);
        setEventMessage($langs->trans('StrongAuthTotpDisabled'));
        header('Location: ?id='.$targetId);
        exit;
    }

    if ($op === 're_enroll_totp') {
        (new Factor_TOTP())->disable($targetId, (int) $user->id);
        $_SESSION['strongauth_enroll_secret'] = null;
        header('Location: '.STRONGAUTH_MODULE_URL_ROOT.'/views/factor_enroll.php?step=1'
             .'&for_user='.$targetId);
        exit;
    }

    if ($op === 'del_passkey') {
        $rowid = (int) GETPOST('rowid', 'int');
        if ($rowid > 0) {
            (new StrongAuth_WebauthnRepo())->deleteCredential((int) $targetId, $rowid);
            setEventMessage($langs->trans('StrongAuthPasskeyDeleted'));
        }
        header('Location: ?id='.$targetId);
        exit;
    }

    if ($op === 'regen_backup') {
        $codes = (new Factor_BackupCode())->generate($targetId);
        $_SESSION['strongauth_regen_backupcodes'] = $codes;
        $_SESSION['strongauth_regen_backupcodes_for'] = $targetId;
        header('Location: ?id='.$targetId.'&showregen=1');
        exit;
    }
}

$title = $langs->trans('StrongAuthManageTitle', $target->getFullName($langs));
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-user page-strongauth');

$linkback = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.(int) $targetId.'">'.$langs->trans("StrongAuthBackToUserCard").'</a>';
print load_fiche_titre($title, $linkback, 'user');

// One-time display of regenerated backup codes.
if (!empty($_SESSION['strongauth_regen_backupcodes'])
    && ($_SESSION['strongauth_regen_backupcodes_for'] ?? null) == $targetId) {
    print '<div class="warning" style="margin:1em 0;padding:12px;background:#fffbe6;border:1px solid #f5c66b;">';
    print '<p><b>'.$langs->trans('StrongAuthNewBackupCodes').'</b> &mdash; '
        .$langs->trans('StrongAuthShownOnce').'</p>';
    print '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;'
        .'padding:12px;background:#f8f8f8;border:1px dashed #999;font-family:monospace;">';
    foreach ($_SESSION['strongauth_regen_backupcodes'] as $c) {
        print '<div>'.$c.'</div>';
    }
    print '</div></div>';
    unset($_SESSION['strongauth_regen_backupcodes']);
    unset($_SESSION['strongauth_regen_backupcodes_for']);
}

print '<table class="centpercent">';

// ----- TOTP -----
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('StrongAuthFactorTOTP').'</th></tr>';
print '<tr><td>'.$langs->trans('StrongAuthStatus').'</td><td>'
    .($hasTotp
        ? '<span class="fas fa-check-circle" style="color:green;"></span> '.$langs->trans('StrongAuthEnrolled')
        : '<span class="fas fa-times-circle" style="color:#999;"></span> '.$langs->trans('StrongAuthNotEnrolled'))
    .'</td></tr>';

print '<tr><td>'.$langs->trans('StrongAuthActions').'</td><td>';
if ($hasTotp) {
    // Re-enroll / disable buttons (token as hidden POST field, never in the URL).
    $t = newToken();
    $actionUrl = '?id='.$targetId;
    print '<form method="POST" style="display:inline" action="'.$actionUrl.'">'
        .'<input type="hidden" name="token" value="'.$t.'">'
        .'<input type="hidden" name="op" value="re_enroll_totp">'
        .'<input type="password" name="confirm_password" placeholder="'.$langs->trans('StrongAuthConfirmYourPassword').'" required>'
        .' <input type="submit" class="button" value="'.$langs->trans('StrongAuthReEnroll').'"></form> ';
    print '<form method="POST" style="display:inline" action="'.$actionUrl.'"'
        .' onsubmit="return confirm(\''.dol_escape_js($langs->trans('StrongAuthDisableConfirm')).'\');">'
        .'<input type="hidden" name="token" value="'.$t.'">'
        .'<input type="hidden" name="op" value="disable_totp">'
        .'<input type="password" name="confirm_password" placeholder="'.$langs->trans('StrongAuthConfirmYourPassword').'" required>'
        .' <input type="submit" class="button" value="'.$langs->trans('StrongAuthDisable').'"></form>';
} else {
    // External user: force enroll. Internal user: not applicable.
    if (!$isInternal) {
        print '<a class="button" href="'.STRONGAUTH_MODULE_URL_ROOT.'/views/factor_enroll.php?step=1">'
            .$langs->trans('StrongAuthEnroll').'</a>';
    } else {
        print '<span class="opacitymedium">'
            .$langs->trans('StrongAuthUseEntraIdInstead').'</span>';
    }
}
print '</td></tr>';

// ----- Backup codes -----
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('StrongAuthFactorBackupCode').'</th></tr>';
print '<tr><td>'.$langs->trans('StrongAuthUnused').'</td><td>'.$unused.'</td></tr>';
if ($hasTotp) {
    print '<tr><td>'.$langs->trans('StrongAuthActions').'</td><td>';
    $t = newToken();
    print '<form method="POST" style="display:inline" action="?id='.$targetId.'"'
        .' onsubmit="return confirm(\''.dol_escape_js($langs->trans('StrongAuthRegenConfirm')).'\');">'
        .'<input type="hidden" name="token" value="'.$t.'">'
        .'<input type="hidden" name="op" value="regen_backup">'
        .'<input type="password" name="confirm_password" placeholder="'.$langs->trans('StrongAuthConfirmYourPassword').'" required>'
        .' <input type="submit" class="button" value="'.$langs->trans('StrongAuthRegenerate').'"></form>';
    print '</td></tr>';
}

// ----- Passkey -----
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('StrongAuthFactorPasskey').'</th></tr>';
print '<tr><td>'.$langs->trans('StrongAuthStatus').'</td><td>';
if (!$allowPass) {
    print '<span class="opacitymedium">'.$langs->trans('StrongAuthPasskeyNotAvailable').'</span>';
} elseif ($hasPasskey) {
    print '<span class="fas fa-check-circle" style="color:green;"></span> '.$langs->trans('StrongAuthEnrolled');
} else {
    print '<span class="opacitymedium">'.$langs->trans('StrongAuthNotEnrolled').'</span>';
}
print '</td></tr>';
if ($allowPass) {
    $passkeyUrl = STRONGAUTH_MODULE_URL_ROOT.'/views/webauthn_enroll.php?id='.(int) $targetId;
    print '<tr><td>'.$langs->trans('StrongAuthActions').'</td><td>'
        .'<a class="button" href="'.dol_escape_htmltag($passkeyUrl).'">'
        .$langs->trans('StrongAuthEnrollPasskeyButton').'</a></td></tr>';
}

// Registered credentials with per-item delete — listed whenever rows exist,
// even with enrollment disabled (STRONGAUTH_ALLOW_WEBAUTHN off), so turning
// the feature off never hides already-enrolled credentials from cleanup.
// A browser that already holds a passkey is excluded from re-registration
// until its row is removed here.
$creds = (new StrongAuth_WebauthnRepo())->rowsForUser((int) $targetId);
if (!empty($creds)) {
    print '<tr><td>'.$langs->trans('StrongAuthPasskeyDevices').'</td><td>';
    print '<table class="noborder">';
    print '<tr class="liste_titre"><td>'.$langs->trans('Name').'</td><td>'.$langs->trans('DateCreation').'</td><td>'.$langs->trans('StrongAuthLastUsed').'</td><td></td></tr>';
    foreach ($creds as $c) {
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($c->name ?: substr($c->credential_id, 0, 12).'…').'</td>';
        print '<td>'.dol_escape_htmltag($c->created_at).'</td>';
        print '<td>'.dol_escape_htmltag($c->last_used_at ?: '—').'</td>';
        print '<td><form method="POST" action="?id='.(int) $targetId.'" style="display:inline">'
            .'<input type="hidden" name="token" value="'.newToken().'">'
            .'<input type="hidden" name="op" value="del_passkey">'
            .'<input type="hidden" name="rowid" value="'.(int) $c->rowid.'">'
            .'<button type="submit" class="button" onclick="return confirm(\''.dol_escape_js($langs->trans('StrongAuthPasskeyDeleteConfirm')).'\');">'
            .$langs->trans('Delete').'</button></form></td>';
        print '</tr>';
    }
    print '</table>';
    print '</td></tr>';
}

// ----- SSO bindings -----
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('StrongAuthIdpLinkTitle').'</th></tr>';
$idpUrl = STRONGAUTH_MODULE_URL_ROOT.'/views/idp_link.php';
print '<tr><td>'.$langs->trans('StrongAuthActions').'</td><td>'
    .'<a class="button" href="'.dol_escape_htmltag($idpUrl).'">'
    .$langs->trans('StrongAuthIdpLinkTitle').'</a></td></tr>';

print '</table>';

llxFooter();
$db->close();
