<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Admin: configure SSO providers — wizard style.
 *
 * Flow: pick a provider card → guided app registration at the vendor portal
 * (deep link + copy-ready redirect URI) → paste the 3 issued credentials →
 * save → one-click discovery test. Apple sign-in is intentionally not
 * supported (SSO restricted to corporate directories).
 *
 * Operations (unchanged semantics):
 *   - save  : upsert a provider row (secrets encrypted at rest)
 *   - delete: remove a provider row
 *   - test  : hit the discovery endpoint
 */

// Locate main.inc.php the standard way (admin/ is 3 levels below htdocs root).
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include(__DIR__.'/../../../main.inc.php');
if (!$res) die('Include of main fails');
require_once __DIR__.'/../lib/strongauth.lib.php';

if (empty($user->rights->strongauth->reset)) {
    accessforbidden();
    exit;
}

$langs->load('strongauth@strongauth');
$langs->load('admin');

$action = GETPOST('action', 'aZ09');
$edit   = GETPOST('edit', 'alphanohtml');
$add    = GETPOST('add', 'alphanohtml');

// All state-changing actions require the CSRF token (defense-in-depth on top
// of main.inc.php's automatic check).
if (in_array($action, array('save', 'delete', 'test'), true)) {
    if (!isset($_SESSION['token']) || GETPOST('token', 'alpha') !== $_SESSION['token']) {
        accessforbidden('CSRF check failed');
    }
}

if ($action === 'save') {
    $provider = GETPOST('provider', 'alphanohtml');
    if (!in_array($provider, array('entra', 'google', 'oidc'), true)) {
        setEventMessage('Invalid provider id', 'errors');
    } else {
        $rowid        = (int) GETPOST('rowid', 'int');
        $label        = GETPOST('label', 'nohtml');
        $clientId     = GETPOST('client_id', 'alphanohtml');
        $clientSecret = GETPOST('client_secret', 'alphanohtml');      // plaintext from form
        $issuer       = GETPOST('issuer', 'alphanohtml');
        $tenantId     = GETPOST('tenant_id', 'alphanohtml');
        $scopes       = GETPOST('scopes', 'alphanohtml') ?: 'openid email profile';
        $enabled      = GETPOSTINT('enabled') ? 1 : 0;
        $autoProvision= GETPOSTINT('auto_provision') ? 1 : 0;

        $secretEnc = '';
        if ($clientSecret !== '') {
            $secretEnc = StrongAuth_Crypto::encrypt($clientSecret);
        }

        $now = $db->idate(gmdate('Y-m-d H:i:s'));
        if ($rowid > 0) {
            // Update. Only re-encrypt if a new value was provided.
            $sets = array(
                "label = '".$db->escape($label)."'",
                "client_id = '".$db->escape($clientId)."'",
                "issuer = '".$db->escape($issuer)."'",
                "tenant_id = '".$db->escape($tenantId)."'",
                "scopes = '".$db->escape($scopes)."'",
                "enabled = ".(int) $enabled,
                "auto_provision = ".(int) $autoProvision,
                "updated_at = '".$now."'",
            );
            if ($secretEnc !== '')     { $sets[] = "client_secret_enc = '".$db->escape($secretEnc)."'"; }
            $sql = "UPDATE ".MAIN_DB_PREFIX."strongauth_idp_config SET ".implode(', ', $sets)
                 . " WHERE rowid = ".(int) $rowid
                 . " AND entity = ".(int) $conf->entity;
            $db->query($sql);
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_idp_config"
                 . " (provider, label, client_id, client_secret_enc, issuer, tenant_id,"
                 . "  scopes, enabled, auto_provision, created_at, updated_at, entity)"
                 . " VALUES ("
                 ."'".$db->escape($provider)."',"
                 ."'".$db->escape($label)."',"
                 ."'".$db->escape($clientId)."',"
                 ."'".$db->escape($secretEnc)."',"
                 ."'".$db->escape($issuer)."',"
                 ."'".$db->escape($tenantId)."',"
                 ."'".$db->escape($scopes)."',"
                 .(int) $enabled.","
                 .(int) $autoProvision.","
                 ."'".$now."',"
                 ."'".$now."',"
                 .(int) $conf->entity.")";
            $db->query($sql);
        }
        setEventMessage($langs->trans('StrongAuthIdpSaved'));

        // Corporate deployments must pin a dedicated tenant: with 'common' or
        // 'consumers' any personal Microsoft account can reach the app and
        // email linking trusts whatever the IdP asserts.
        if ($provider === 'entra') {
            $tenantWarn = trim($tenantId);
            if ($tenantWarn === '' || in_array(strtolower($tenantWarn), array('common', 'consumers', 'organizations'), true)) {
                setEventMessage($langs->trans('StrongAuthIdpWarnTenant'), 'warnings');
            }
        }
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'delete') {
    $rowid = (int) GETPOST('rowid', 'int');
    if ($rowid > 0) {
        $db->query(
            "DELETE FROM ".MAIN_DB_PREFIX."strongauth_idp_config"
            ." WHERE rowid = ".(int) $rowid
            ." AND entity = ".(int) $conf->entity
        );
        setEventMessage($langs->trans('StrongAuthIdpDeleted'));
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if ($action === 'test') {
    $rowid = (int) GETPOST('rowid', 'int');
    $cfg = null;
    if ($rowid > 0) {
        $res = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."strongauth_idp_config WHERE rowid = ".(int) $rowid." AND entity = ".(int) $conf->entity);
        if ($res) $cfg = $db->fetch_array($res);
    }
    if ($cfg) {
        try {
            $client = new StrongAuth_OIDCClient();
            $issuer = !empty($cfg['issuer']) ? $cfg['issuer']
                     : ($cfg['provider'] === 'entra' ? 'https://login.microsoftonline.com/'.($cfg['tenant_id'] ?: 'common').'/v2.0'
                     : ($cfg['provider'] === 'google' ? 'https://accounts.google.com' : null));
            if (!$issuer) {
                throw new RuntimeException('No issuer URL to test');
            }
            $doc = $client->discovery($issuer);
            setEventMessage($langs->trans('StrongAuthIdpTestOk', $doc['issuer'] ?? $issuer, $doc['authorization_endpoint'] ?? '?'), 'mesgs');
        } catch (Throwable $e) {
            setEventMessage($langs->trans('StrongAuthIdpTestFailed', $e->getMessage()), 'errors');
        }
    }
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

llxHeader('', $langs->trans('StrongAuthIdpSetupTitle'), '', '', 0, 0, '', '', '', 'mod-admin page-strongauth');

dol_include_once('/core/class/html.form.class.php');
$form = new Form($db);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans('StrongAuthIdpSetupTitle'), $linkback, 'title_setup');

$head = array();
$h = 0;
$head[$h][0] = STRONGAUTH_MODULE_URL_ROOT.'/admin/setup.php';
$head[$h][1] = $langs->trans('StrongAuthSetup');
$head[$h][2] = 'settings';
$h++;
$head[$h][0] = $_SERVER['PHP_SELF'];
$head[$h][1] = $langs->trans('StrongAuthIdpSetupTitle');
$head[$h][2] = 'providers';
$h++;

print dol_get_fiche_head($head, 'providers', $langs->trans('StrongAuthIdpSetupTitle'), -1, 'fa-shield-halved@strongauth');

// ---------------------------------------------------------------------------
// Current site origin (for the copy-ready redirect URI)
// ---------------------------------------------------------------------------
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteOrigin = $scheme.'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');

// ---------------------------------------------------------------------------
// Provider cards / edit context
// ---------------------------------------------------------------------------
$wizardProvider = '';
$row = null;
if ($edit !== '') {
    $rowid = (int) GETPOST('rowid', 'int');
    if ($rowid > 0) {
        $res2 = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."strongauth_idp_config WHERE rowid = ".(int) $rowid." AND entity = ".(int) $conf->entity);
        if ($res2) $row = $db->fetch_array($res2);
    }
    $wizardProvider = $row['provider'] ?? $edit;
} elseif ($add !== '' && in_array($add, array('entra', 'google', 'oidc'), true)) {
    $wizardProvider = $add;
}

/** Per-provider metadata: title, portal URL, ordered guide-step lang keys. */
$providerMeta = array(
    'entra' => array(
        'title'  => 'Microsoft 365',
        'portal' => 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
        'steps'  => array('StrongAuthIdpEntraS1', 'StrongAuthIdpEntraS2', 'StrongAuthIdpEntraS3', 'StrongAuthIdpEntraS4', 'StrongAuthIdpEntraS5'),
    ),
    'google' => array(
        'title'  => 'Google',
        'portal' => 'https://console.cloud.google.com/apis/credentials',
        'steps'  => array('StrongAuthIdpGoogleS1', 'StrongAuthIdpGoogleS2', 'StrongAuthIdpGoogleS3', 'StrongAuthIdpGoogleS4'),
    ),
    'oidc' => array(
        'title'  => 'Self-hosted OIDC (Keycloak / Authentik / Okta ...)',
        'portal' => '',
        'steps'  => array('StrongAuthIdpOidcS1', 'StrongAuthIdpOidcS2'),
    ),
);

// ---------------------------------------------------------------------------
// Configured providers list (with test / edit / delete)
// ---------------------------------------------------------------------------
$sql = "SELECT rowid, provider, label, client_id, issuer, tenant_id, enabled, auto_provision"
     . " FROM ".MAIN_DB_PREFIX."strongauth_idp_config"
     . " WHERE entity = ".(int) $conf->entity
     . " ORDER BY provider";
$res = $db->query($sql);
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('StrongAuthProvider').'</td>';
print '<td>'.$langs->trans('StrongAuthClientId').'</td>';
print '<td>'.$langs->trans('StrongAuthEnabled').'</td>';
print '<td>'.$langs->trans('StrongAuthActions').'</td>';
print '</tr>';
$anyRow = false;
while ($obj = $db->fetch_object($res)) {
    $anyRow = true;
    print '<tr class="oddeven">';
    print '<td>'.dol_escape_htmltag(($providerMeta[$obj->provider]['title'] ?? $obj->provider).($obj->label ? ' — '.$obj->label : '')).'</td>';
    print '<td><code>'.dol_escape_htmltag(substr($obj->client_id, 0, 24)).'…</code></td>';
    print '<td>'.($obj->enabled ? $langs->trans('Enabled') : '<span class="opacitymedium">'.$langs->trans('Disabled').'</span>').'</td>';
    print '<td>';
    $self = $_SERVER['PHP_SELF'];
    print '<a class="button" href="'.dol_escape_htmltag($self.'?edit='.urlencode($obj->provider).'&rowid='.(int) $obj->rowid).'">'
        .$langs->trans('StrongAuthEdit').'</a> ';
    print '<form method="POST" action="'.dol_escape_htmltag($self).'" style="display:inline">';
    print '<input type="hidden" name="action" value="test">';
    print '<input type="hidden" name="rowid" value="'.(int) $obj->rowid.'">';
    print '<input type="hidden" name="token" value="'.dol_escape_htmltag($_SESSION['token']).'">';
    print '<button type="submit" class="button">'.$langs->trans('StrongAuthTest').'</button>';
    print '</form> ';
    print '<form method="POST" action="'.dol_escape_htmltag($self).'" style="display:inline">';
    print '<input type="hidden" name="action" value="delete">';
    print '<input type="hidden" name="rowid" value="'.(int) $obj->rowid.'">';
    print '<input type="hidden" name="token" value="'.dol_escape_htmltag($_SESSION['token']).'">';
    print '<button type="submit" class="button" onclick="return confirm(\''.$langs->trans('StrongAuthIdpDeleteConfirm').'\')">'
        .$langs->trans('Delete').'</button>';
    print '</form>';
    print '</td>';
    print '</tr>';
}
print '</table>';
if (!$anyRow) {
    print '<p class="opacitymedium">'.$langs->trans('StrongAuthIdpNoneYet').'</p>';
}

// ---------------------------------------------------------------------------
// Wizard: provider selection OR guided form
// ---------------------------------------------------------------------------
if ($wizardProvider === '') {
    print '<br><div class="titre">'.$langs->trans('StrongAuthIdpChooseProvider').'</div>';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td class="nowrap">'.$langs->trans('StrongAuthProvider').'</td><td>&nbsp;</td></tr>';
    foreach ($providerMeta as $pid => $meta) {
        print '<tr class="oddeven">';
        print '<td class="nowrap"><b>'.dol_escape_htmltag($meta['title']).'</b></td>';
        print '<td class="right">';
        print '<a class="button" href="'.dol_escape_htmltag($_SERVER['PHP_SELF'].'?add='.$pid).'">'
            .dol_escape_htmltag($langs->trans('StrongAuthIdpStartSetup')).'</a>';
        print '</td></tr>';
    }
    print '</table>';
} else {
    $meta = $providerMeta[$wizardProvider] ?? $providerMeta['entra'];
    $redirectUri = $siteOrigin.STRONGAUTH_MODULE_URL_ROOT.'/views/sso/callback.php?provider='.$wizardProvider;

    $rowValue = function (string $key) use ($row) { return $row[$key] ?? ''; };
    $labelVal    = htmlspecialchars((string) $rowValue('label'));
    $clientIdVal = htmlspecialchars((string) $rowValue('client_id'));
    $issuerVal   = htmlspecialchars((string) $rowValue('issuer'));
    $tenantVal   = htmlspecialchars((string) $rowValue('tenant_id'));
    $enabledVal  = !empty($row['enabled']);
    $autoProvVal = !empty($row['auto_provision']);

    print '<br><div class="titre">'.($row ? $langs->trans('StrongAuthIdpEditRow') : dol_escape_htmltag($meta['title'])).'</div>';

    // ---- Step 1: guided registration at the vendor portal ----
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('StrongAuthIdpStep1').'</td></tr>';
    print '<tr class="oddeven"><td colspan="2">';
    print '<ol style="margin:8px 0 8px 24px;padding:0;">';
    foreach ($meta['steps'] as $stepKey) {
        print '<li style="margin:4px 0;">'.$langs->trans($stepKey).'</li>';
    }
    print '</ol>';
    if ($meta['portal'] !== '') {
        print '<a class="button" href="'.dol_escape_htmltag($meta['portal']).'" target="_blank" rel="noopener noreferrer">'
            .dol_escape_htmltag($langs->trans('StrongAuthIdpOpenPortal')).'</a>';
        print '<br><br>';
    }
    print $langs->trans('StrongAuthIdpRedirectUriLabel').':<br>';
    print '<div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">';
    print '<input type="text" id="strongauth_redirect_uri" readonly class="flat" style="flex:1 1 60%;min-width:300px;" value="'.dol_escape_htmltag($redirectUri).'">';
    print '<button type="button" class="button" id="strongauth_copy_btn">'
        .dol_escape_htmltag($langs->trans('StrongAuthCopy')).'</button>';
    print '</div>';
    print '</td></tr>';
    print '</table>';
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('strongauth_copy_btn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var e = document.getElementById('strongauth_redirect_uri');
            e.select();
            try { navigator.clipboard.writeText(e.value); } catch (x) { document.execCommand('copy'); }
            btn.textContent = <?php echo json_encode($langs->trans('StrongAuthCopied')); ?>;
        });
    });
    </script>
    <?php

    // ---- Step 2: paste the issued credentials ----
    print '<br><div class="titre">'.$langs->trans('StrongAuthIdpStep2').'</div>';
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="save">';
    print '<input type="hidden" name="provider" value="'.dol_escape_htmltag($wizardProvider).'">';
    if ($row && !empty($row['rowid'])) {
        print '<input type="hidden" name="rowid" value="'.(int) $row['rowid'].'">';
    }
    print '<table class="noborder centpercent">';

    if ($wizardProvider === 'entra') {
        print '<tr class="oddeven"><td class="fieldrequired nowrap">'.$langs->trans('StrongAuthIdpTenantId').'</td>'
            .'<td><input type="text" name="tenant_id" class="flat minwidth500" required value="'.$tenantVal.'" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">'
            .'<br><span class="opacitymedium">'.$langs->trans('StrongAuthIdpTenantIdHelp').'</span></td></tr>';
    } elseif ($wizardProvider === 'google') {
        print '<tr class="oddeven"><td class="fieldrequired nowrap">'.$langs->trans('StrongAuthIdpWorkspaceDomain').'</td>'
            .'<td><input type="text" name="tenant_id" class="flat minwidth300" required value="'.$tenantVal.'" placeholder="haosg.com">'
            .'<br><span class="opacitymedium">'.$langs->trans('StrongAuthIdpWorkspaceDomainHelp').'</span></td></tr>';
    } else {
        print '<tr class="oddeven"><td class="fieldrequired nowrap">Issuer URL</td>'
            .'<td><input type="text" name="issuer" class="flat minwidth500" required value="'.$issuerVal.'" placeholder="https://sso.example.com/realms/main">'
            .'<br><span class="opacitymedium">'.$langs->trans('StrongAuthIdpIssuerHelp').'</span></td></tr>';
    }

    print '<tr class="oddeven"><td class="fieldrequired nowrap">Client ID</td>'
        .'<td><input type="text" name="client_id" class="flat minwidth500" required value="'.$clientIdVal.'"></td></tr>';
    print '<tr class="oddeven"><td class="fieldrequired nowrap">Client Secret</td>'
        .'<td><input type="password" name="client_secret" class="flat minwidth500" autocomplete="new-password"'.($row ? '' : ' required').'>'
        .'<br><span class="opacitymedium">'.$langs->trans(empty($row) ? 'StrongAuthIdpSecretHelp' : 'StrongAuthLeaveBlankToKeep').'</span></td></tr>';
    print '<tr class="oddeven"><td class="nowrap">'.$langs->trans('StrongAuthIdpLabelField').'</td>'
        .'<td><input type="text" name="label" class="flat minwidth300" value="'.$labelVal.'">'
        .'<br><span class="opacitymedium">'.$langs->trans('StrongAuthIdpLabelHelp').'</span></td></tr>';
    print '<tr class="oddeven"><td class="nowrap">'.$langs->trans('StrongAuthEnabled').'</td>'
        .'<td>'.$form->selectyesno('enabled', $row ? ($enabledVal ? 1 : 0) : 1, 1).'</td></tr>';
    print '<tr class="oddeven"><td class="nowrap">'.$langs->trans('StrongAuthIdpAutoProvision').'</td>'
        .'<td>'.$form->selectyesno('auto_provision', $autoProvVal ? 1 : 0, 1)
        .'<br><span class="opacitymedium">'.$langs->trans('StrongAuthIdpAutoProvisionHelp').'</span></td></tr>';
    print '</table>';
    print '<div class="center"><input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
    print '</form>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
