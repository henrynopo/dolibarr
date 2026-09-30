<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Admin setup page for StrongAuth.
 *
 * Sections:
 *   1. General (issuer name, internal domains, require-2FA toggles, lockout)
 *   2. Backup codes (count)
 *   3. Passkey (admin master switch — additional gating happens per-user)
 *   4. Maintenance (rotate AES key, view audit log)
 *
 * Access: $user->admin OR $user->rights->strongauth->reset
 */

$res = 0;
if (!$res && file_exists('../main.inc.php'))     $res = @include('../main.inc.php');
if (!$res && file_exists('../../main.inc.php'))  $res = @include('../../main.inc.php');
if (!$res && file_exists('../../../main.inc.php')) $res = @include('../../../main.inc.php');
if (!$res) die('Include of main fails');

require_once __DIR__.'/../lib/strongauth.lib.php';

if (empty($user->admin) && empty($user->rights->strongauth->reset)) {
    accessforbidden();
}

dol_include_once('/core/lib/admin.lib.php');

$langs->load('strongauth@strongauth');
$langs->load('admin');

$action = GETPOST('action', 'aZ09');

// ============== Save form ==============
if ($action === 'update') {
    // Defense-in-depth CSRF (Dolibarr's main.inc.php already checks this for
    // POSTs when MAIN_SECURITY_CSRF_WITH_TOKEN is enabled; we repeat it so a
    // misconfigured deployment can't bypass).
    if (!isset($_SESSION['token']) || GETPOST('token', 'alpha') !== $_SESSION['token']) {
        accessforbidden('CSRF check failed');
    }

    $fields = array(
        'STRONGAUTH_ISSUER_NAME',
        'STRONGAUTH_INTERNAL_DOMAINS',
        'STRONGAUTH_REQUIRE_2FA_LOCAL',
        'STRONGAUTH_REQUIRE_2FA_IDP',
        'STRONGAUTH_LOCKOUT_MAX_FAILED',
        'STRONGAUTH_LOCKOUT_DURATION',
        'STRONGAUTH_BACKUPCODE_COUNT',
        'STRONGAUTH_DEVICE_TRUST_LOCAL',
        'STRONGAUTH_ADMIN_BREAKGLASS',
        'STRONGAUTH_ALLOW_WEBAUTHN',
        'STRONGAUTH_WEBAUTHN_RPID',
    );
    foreach ($fields as $name) {
        $val = GETPOST($name, 'alphanohtml');
        if ($name === 'STRONGAUTH_INTERNAL_DOMAINS') {
            // dol_strtolower is UTF-8 safe — strtolower() mangles multi-byte chars.
            $val = dol_strtolower(trim($val));
        }
        if ($name === 'STRONGAUTH_BACKUPCODE_COUNT') {
            $val = max(1, min(50, (int) $val));
        }
        if ($name === 'STRONGAUTH_LOCKOUT_MAX_FAILED') {
            $val = max(1, min(50, (int) $val));
        }
        if ($name === 'STRONGAUTH_LOCKOUT_DURATION') {
            $val = max(60, min(86400, (int) $val));
        }
        dolibarr_set_const($db, $name, (string) $val, 'chaine', 0, '', $conf->entity);
    }
    setEventMessage($langs->trans('StrongAuthSetupSaved'));
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============== One-click: create missing module tables ==============
// Runs the schema files directly (prefix-aware, IF NOT EXISTS) and reports
// per-table results in the event messages — any real SQL error becomes
// visible instead of being swallowed by the enable-time loader.
if ($action === 'create_tables' && !empty($user->rights->strongauth->reset)) {
    if (!isset($_SESSION['token']) || GETPOST('token', 'alpha') !== $_SESSION['token']) {
        accessforbidden('CSRF check failed');
    }
    $sqlDir = __DIR__.'/../sql/';
    if (!is_dir($sqlDir)) {
        setEventMessage($langs->trans('StrongAuthTablesNoSqlDir'), 'errors');
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
    $done = 0; $errors = array();
    foreach (glob($sqlDir.'llx_*.sql') as $sqlFile) {
        $lines = array();
        foreach (preg_split('/\R/', (string) file_get_contents($sqlFile)) as $line) {
            if (preg_match('/^\s*--/', $line)) { continue; }
            $lines[] = $line;
        }
        $body = str_replace('llx_', MAIN_DB_PREFIX, implode("\n", $lines));
        foreach (array_filter(array_map('trim', explode(';', $body))) as $stmt) {
            if ($stmt === '') { continue; }
            if ($db->query($stmt)) {
                if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?(\S+)/i', $stmt, $m)) { $done++; }
            } else {
                $errors[] = substr($stmt, 0, 60).'... → '.$db->lasterror;
            }
        }
    }
    if (empty($errors)) {
        setEventMessage($langs->trans('StrongAuthTablesCreated', $done));
    } else {
        setEventMessage($langs->trans('StrongAuthTablesCreated', $done), 'warnings');
        setEventMessage($langs->trans('StrongAuthTablesErrors').'<br>'.implode('<br>', array_map('dol_escape_htmltag', $errors)), 'errors');
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============== Rotate key ==============
if ($action === 'rotate_key' && !empty($user->rights->strongauth->reset)) {
    if (!isset($_SESSION['token']) || GETPOST('token', 'alpha') !== $_SESSION['token']) {
        accessforbidden('CSRF check failed');
    }
    try {
        $result = StrongAuth_Crypto::rotateKey($db, $conf->entity);
        $msg = $langs->trans('StrongAuthKeyRotated', $result['rows'], $result['new_key']);
        setEventMessage($msg, 'warnings');
        StrongAuth_Audit::log($db, $user->id, null,
            StrongAuth_Audit::EVENT_KEY_ROTATE, 'totp', 'rows='.$result['rows']);
    } catch (Throwable $e) {
        setEventMessage($langs->trans('StrongAuthKeyRotateFailed').': '.$e->getMessage(), 'errors');
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============== One-click: add strongauth_sso to the auth method ==============
// The auth method lives in conf/conf.php ($dolibarr_main_authentication),
// not in llx_const — Dolibarr offers no admin UI for it. We edit the file
// carefully: backup first, then a narrow regex replace of that one line.
if ($action === 'enable_sso_authmode' && !empty($user->rights->strongauth->reset)) {
    if (!isset($_SESSION['token']) || GETPOST('token', 'alpha') !== $_SESSION['token']) {
        accessforbidden('CSRF check failed');
    }
    $confFile = DOL_DOCUMENT_ROOT.'/../conf/conf.php';
    if (!is_writable($confFile)) {
        setEventMessage($langs->trans('StrongAuthAuthModeNotWritable'), 'errors');
    } else {
        $content = file_get_contents($confFile);
        $newLine = "\$dolibarr_main_authentication='strongauth_sso,dolibarr';";
        if (preg_match('/^\s*\$dolibarr_main_authentication\s*=\s*[\'"][^\'"]*[\'"]\s*;/m', $content, $m)) {
            if (strpos($m[0], 'strongauth_sso') === false) {
                // preserve any non-default modes already configured
                $modes = array('strongauth_sso');
                if (preg_match('/=[\'"]([^\'"]*)[\'"]/', $m[0], $mm)) {
                    foreach (explode(',', $mm[1]) as $mode) {
                        $mode = trim($mode);
                        if ($mode !== '' && $mode !== 'strongauth_sso') { $modes[] = $mode; }
                    }
                }
                $newLine = "\$dolibarr_main_authentication='".implode(',', $modes)."';";
                $backup = $confFile.'.backup-'.date('YmdHis');
                if (!copy($confFile, $backup)) {
                    setEventMessage($langs->trans('StrongAuthAuthModeBackupFailed'), 'errors');
                } else {
                    $updated = preg_replace(
                        '/^\s*\$dolibarr_main_authentication\s*=\s*[\'"][^\'"]*[\'"]\s*;/m',
                        $newLine,
                        $content, 1
                    );
                    if ($updated !== null && file_put_contents($confFile, $updated) !== false) {
                        setEventMessage($langs->trans('StrongAuthAuthModeEnabled', basename($backup)));
                        StrongAuth_Audit::log($db, $user->id, null,
                            StrongAuth_Audit::EVENT_KEY_ROTATE, 'sso',
                            'auth method updated: strongauth_sso prepended');
                    } else {
                        copy($backup, $confFile);   // restore on write failure
                        setEventMessage($langs->trans('StrongAuthAuthModeWriteFailed'), 'errors');
                    }
                }
            } else {
                setEventMessage($langs->trans('StrongAuthAuthModeAlreadySet'));
            }
        } else {
            // No declaration line found — append one.
            if (copy($confFile, $confFile.'.backup-'.date('YmdHis'))) {
                file_put_contents($confFile, $content."\n".$newLine."\n");
                setEventMessage($langs->trans('StrongAuthAuthModeEnabled'));
            } else {
                setEventMessage($langs->trans('StrongAuthAuthModeBackupFailed'), 'errors');
            }
        }
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

// ============== Render setup form ==============
$title = $langs->trans('StrongAuthSetupTitle');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-admin page-strongauth');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

// ---- Deployment self-checks ----
$warnings = array();
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    $warnings[] = $langs->trans('StrongAuthWarnComposerMissing');
}
if (strpos((string) $conf->file->main_authentication, 'strongauth_sso') === false) {
    $warnings[] = $langs->trans('StrongAuthWarnAuthMethod');
}

// Schema completeness: every module table must exist (the enable-time fallback
// creates them, but verify on every visit — catches half-created schemas).
dol_include_once('/strongauth/core/modules/modStrongauth.class.php');
$modDesc = new modStrongauth($db);
$missingTables = $modDesc->saMissingTables();
if (!empty($missingTables)) {
    $warnings[] = $langs->trans('StrongAuthWarnTablesMissing', implode(', ', $missingTables));
}

// Multicompany coverage: hooks and the SSO login function only load for
// entities where the module is ENABLED. An entity without StrongAuth has NO
// 2FA at all — enumerate llx_entity and flag any that are missing.
$entityRes = $db->query("SELECT rowid, label FROM ".MAIN_DB_PREFIX."entity ORDER BY rowid");
if ($entityRes) {
    $missingEntities = array();
    while ($eobj = $db->fetch_object($entityRes)) {
        $enabledRes = $db->query(
            "SELECT COUNT(*) AS c FROM ".MAIN_DB_PREFIX."const"
            . " WHERE name = 'MAIN_MODULE_STRONGAUTH'"
            . " AND entity IN (0, ".(int) $eobj->rowid.")"
            . " AND value = '1'"
        );
        $erow = $db->fetch_object($enabledRes);
        if (!$erow || (int) $erow->c === 0) {
            $missingEntities[] = ($eobj->label !== '' ? $eobj->label : 'entity '.$eobj->rowid);
        }
    }
    if (!empty($missingEntities)) {
        $warnings[] = $langs->trans('StrongAuthWarnEntitiesMissing', implode(', ', $missingEntities));
    }
}

// Internal domains without a working IdP: password logins for internal users
// are rejected by policy, so without an enabled IdP those users are locked
// out completely.
if (trim((string) getDolGlobalString('STRONGAUTH_INTERNAL_DOMAINS')) !== '') {
    $idpRes = $db->query(
        "SELECT COUNT(*) AS c FROM ".MAIN_DB_PREFIX."strongauth_idp_config"
        . " WHERE enabled = 1 AND entity = ".(int) $conf->entity
    );
    $idpRow = $db->fetch_object($idpRes);
    if (!$idpRow || (int) $idpRow->c === 0) {
        $warnings[] = $langs->trans('StrongAuthWarnInternalNoIdp');
    }
}

if (!empty($warnings)) {
    foreach ($warnings as $w) {
        print '<div class="warning" style="padding:8px 12px;margin-bottom:6px;">'.$w.'</div>';
    }
}

// One-click schema repair shown only while tables are missing.
if (!empty($missingTables) && !empty($user->rights->strongauth->reset)) {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:10px;">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="create_tables">';
    print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('StrongAuthCreateTablesButton')).'">';
    print '</form>';
}

// One-click fix for the auth-method warning (conf/conf.php is editable only
// from the filesystem; the button rewrites it with a timestamped backup).
if (strpos((string) $conf->file->main_authentication, 'strongauth_sso') === false
    && !empty($user->rights->strongauth->reset)) {
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="margin-bottom:10px;">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="enable_sso_authmode">';
    print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('StrongAuthAuthModeButton')).'"';
    print ' onclick="return confirm(\''.dol_escape_js($langs->trans('StrongAuthAuthModeConfirm')).'\');">';
    print '</form>';
}

$head = array();
$h = 0;
$head[$h][0] = STRONGAUTH_MODULE_URL_ROOT.'/admin/setup.php';
$head[$h][1] = $langs->trans('StrongAuthSetup');
$head[$h][2] = 'settings';
$h++;

print dol_get_fiche_head($head, 'settings', $title, -1, 'fa-shield-halved@strongauth');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent">';

$yesNoSelect = static function (string $name, bool $current, string $help, $langs): string {
    $y = $current ? ' selected' : '';
    $n = $current ? '' : ' selected';
    $helpHtml = $help !== '' ? '<br><span class="opacitymedium">'.$help.'</span>' : '';
    return '<tr class="oddeven">'
        .'<td>'.$name.'</td>'
        .'<td><select class="flat" name="'.$name.'">'
        .'<option value="1"'.$y.'>'.$langs->trans('Yes').'</option>'
        .'<option value="0"'.$n.'>'.$langs->trans('No').'</option>'
        .'</select></td>'
        .'<td>'.$helpHtml.'</td>'
        .'</tr>';
};

$textRow = static function (string $name, string $value, string $help, string $sizeClass = 'minwidth300'): string {
    $helpHtml = $help !== '' ? '<br><span class="opacitymedium">'.$help.'</span>' : '';
    return '<tr class="oddeven">'
        .'<td>'.$name.'</td>'
        .'<td><input type="text" class="flat '.$sizeClass.'" name="'.$name.'" value="'.dol_escape_htmltag($value).'"></td>'
        .'<td>'.$helpHtml.'</td>'
        .'</tr>';
};

// --- Section: General ---
print '<tr class="liste_titre"><th colspan="3">'.$langs->trans('StrongAuthSectionGeneral').'</th></tr>';
print $textRow('STRONGAUTH_ISSUER_NAME',
    getDolGlobalString('STRONGAUTH_ISSUER_NAME', 'Dolibarr'),
    $langs->trans('StrongAuthIssuerNameHelp'));
print $textRow('STRONGAUTH_INTERNAL_DOMAINS',
    getDolGlobalString('STRONGAUTH_INTERNAL_DOMAINS', ''),
    $langs->trans('StrongAuthInternalDomainsHelp'));
print $yesNoSelect('STRONGAUTH_REQUIRE_2FA_LOCAL',
    (bool) getDolGlobalInt('STRONGAUTH_REQUIRE_2FA_LOCAL'),
    $langs->trans('StrongAuthRequire2FALocalHelp'), $langs);
print $yesNoSelect('STRONGAUTH_REQUIRE_2FA_IDP',
    (bool) getDolGlobalInt('STRONGAUTH_REQUIRE_2FA_IDP'),
    $langs->trans('StrongAuthRequire2FAIdpHelp'), $langs);
print $yesNoSelect('STRONGAUTH_DEVICE_TRUST_LOCAL',
    (bool) getDolGlobalInt('STRONGAUTH_DEVICE_TRUST_LOCAL'),
    $langs->trans('StrongAuthDeviceTrustLocalHelp'), $langs);
print $yesNoSelect('STRONGAUTH_ADMIN_BREAKGLASS',
    (bool) getDolGlobalInt('STRONGAUTH_ADMIN_BREAKGLASS', 1),
    $langs->trans('StrongAuthAdminBreakglassHelp'), $langs);

// --- Section: Lockout ---
print '<tr class="liste_titre"><th colspan="3">'.$langs->trans('StrongAuthSectionLockout').'</th></tr>';
print $textRow('STRONGAUTH_LOCKOUT_MAX_FAILED',
    (string) getDolGlobalInt('STRONGAUTH_LOCKOUT_MAX_FAILED', 5),
    $langs->trans('StrongAuthMaxFailedHelp'), 'width75');
print $textRow('STRONGAUTH_LOCKOUT_DURATION',
    (string) getDolGlobalInt('STRONGAUTH_LOCKOUT_DURATION', 900),
    $langs->trans('StrongAuthLockoutDurationHelp'), 'width75');

// --- Section: Backup codes ---
print '<tr class="liste_titre"><th colspan="3">'.$langs->trans('StrongAuthSectionBackupCodes').'</th></tr>';
print $textRow('STRONGAUTH_BACKUPCODE_COUNT',
    (string) getDolGlobalInt('STRONGAUTH_BACKUPCODE_COUNT', 10),
    $langs->trans('StrongAuthBackupCountHelp'), 'width75');

// --- Section: Passkey ---
print '<tr class="liste_titre"><th colspan="3">'.$langs->trans('StrongAuthSectionPasskey').'</th></tr>';
print $yesNoSelect('STRONGAUTH_ALLOW_WEBAUTHN',
    (bool) getDolGlobalInt('STRONGAUTH_ALLOW_WEBAUTHN'),
    $langs->trans('StrongAuthAllowWebAuthnHelp'), $langs);
print $textRow('STRONGAUTH_WEBAUTHN_RPID',
    getDolGlobalString('STRONGAUTH_WEBAUTHN_RPID', ''),
    $langs->trans('StrongAuthWebAuthnRpIdHelp'));

// --- Section: SSO ---
print '<tr class="liste_titre"><th colspan="3">'.$langs->trans('StrongAuthSectionSso').'</th></tr>';
print '<tr class="oddeven"><td colspan="3"><a class="button" href="'.STRONGAUTH_MODULE_URL_ROOT.'/admin/idp_setup.php">'
    .$langs->trans('StrongAuthSsoConfigureProviders').'</a></td></tr>';

print '</table>';
print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

// --- Section: Maintenance ---
print '<br>';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">'.$langs->trans('StrongAuthSectionMaintenance').'</th></tr>';

print '<tr class="oddeven"><td>'.$langs->trans('StrongAuthAuditLog').'</td>';
print '<td><a class="button" href="'.STRONGAUTH_MODULE_URL_ROOT.'/views/audit_log.php">'
    .$langs->trans('StrongAuthViewAudit').'</a></td></tr>';

if (!empty($user->rights->strongauth->reset)) {
    print '<tr class="oddeven"><td>'.$langs->trans('StrongAuthRotateKey').'</td>';
    print '<td><form method="POST" action="'.$_SERVER['PHP_SELF'].'" style="display:inline">';
    print '<input type="hidden" name="action" value="rotate_key">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="submit" class="button" value="'.$langs->trans('StrongAuthRotateKeyButton').'"';
    print ' onclick="return confirm(\''.dol_escape_js($langs->trans('StrongAuthRotateKeyConfirm')).'\');">';
    print '</form></td></tr>';
}

print '<tr class="oddeven"><td>'.$langs->trans('StrongAuthCodeRev').'</td>';
print '<td class="opacitymedium">'.(defined('STRONGAUTH_CODE_REV') ? STRONGAUTH_CODE_REV : 'lib/strongauth.lib.php is stale').'</td></tr>';

print '</table>';
$db->close();
