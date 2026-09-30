<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * User-facing IdP binding management.
 *
 * Operations:
 *   - link   : redirect to /views/sso/login.php?provider=... for a fresh binding
 *              (the IdP callback will upsert the binding automatically)
 *   - unlink : remove a single llx_strongauth_idp_binding row
 *   - list   : show all of the user's existing bindings
 */

// Locate main.inc.php the standard way (views/ is 3 levels below htdocs root).
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include(__DIR__.'/../../../main.inc.php');
if (!$res) die('Include of main fails');
require_once __DIR__.'/../lib/strongauth.lib.php';

global $langs, $user, $conf;
$langs->load('strongauth@strongauth');

if (empty($user->id)) {
    llxHeader();
    print '<div class="error">'.$langs->trans('StrongAuthLoginRequired').'</div>';
    llxFooter();
    exit;
}

$action = GETPOST('sa', 'alphanohtml');
$target = (int) GETPOST('id', 'int');
if ($target === 0) { $target = (int) $user->id; }

// Permission: user may manage only their own bindings.
if ($target !== (int) $user->id) {
    accessforbidden();
    exit;
}

// CSRF: Dolibarr auto-validates $_POST['token'] when MAIN_SECURITY_CSRF_WITH_TOKEN is on.
// Repeat the check explicitly so a misconfigured deployment cannot skip it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'unlink') {
    if (!isset($_SESSION['token']) || GETPOST('token', 'alpha') !== $_SESSION['token']) {
        accessforbidden('CSRF check failed');
    }
    $rowId = (int) GETPOST('rowid', 'int');
    if ($rowId > 0) {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."strongauth_idp_binding"
             . " WHERE rowid = ".(int) $rowId
             . " AND fk_user = ".(int) $target
             . " AND entity = ".(int) $conf->entity;
        $db->query($sql);
        setEventMessage($langs->trans('StrongAuthIdpUnlinked'), 'mesgs');
        header('Location: '.STRONGAUTH_MODULE_URL_ROOT.'/views/idp_link.php');
        exit;
    }
}

llxHeader();

$linkback = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.(int) $target.'">'.$langs->trans("StrongAuthBackToUserCard").'</a>';
print load_fiche_titre($langs->trans('StrongAuthIdpLinkTitle'), $linkback, 'user');
print '<p>'.$langs->trans('StrongAuthIdpLinkIntro').'</p>';

// List configured providers.
$configured = StrongAuth_ProviderRegistry::loginPageChoices();
if (empty($configured) || count($configured) <= 1) {   // 'local' always present
    print '<p class="opacitymedium">'.$langs->trans('StrongAuthIdpLinkNoneConfigured').'</p>';
} else {
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre"><td>'.$langs->trans('StrongAuthProvider').'</td><td>'.$langs->trans('StrongAuthAction').'</td></tr>';
    foreach ($configured as $id => $provider) {
        if ($id === 'local') { continue; }
        $icon = '';
        if (method_exists($provider, 'iconClass') && $provider->iconClass() !== '') {
            $icon = '<i class="'.dol_escape_htmltag($provider->iconClass()).'" style="margin-right:8px;"></i>';
        }
        print '<tr class="oddeven">';
        print '<td>'.$icon.dol_escape_htmltag($provider->label()).'</td>';
        $url = STRONGAUTH_MODULE_URL_ROOT.'/views/sso/login.php?provider='.urlencode($id);
        print '<td><a class="button" href="'.dol_escape_htmltag($url).'">'
            .dol_escape_htmltag($langs->trans('StrongAuthIdpLinkAction')).'</a></td>';
        print '</tr>';
    }
    print '</table>';
}

print '<h3>'.$langs->trans('StrongAuthIdpExistingBindings').'</h3>';
$sql = "SELECT rowid, provider, subject, issuer, bound_at, last_used_at"
     . " FROM ".MAIN_DB_PREFIX."strongauth_idp_binding"
     . " WHERE fk_user = ".(int) $target
     . " AND entity = ".(int) $conf->entity
     . " ORDER BY bound_at DESC";
$res = $db->query($sql);
$rows = array();
if ($res) {
    while ($obj = $db->fetch_object($res)) { $rows[] = $obj; }
}

if (empty($rows)) {
    print '<p class="opacitymedium">'.$langs->trans('StrongAuthIdpNoBindings').'</p>';
} else {
    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>'.$langs->trans('StrongAuthProvider').'</td>';
    print '<td>'.$langs->trans('StrongAuthSubject').'</td>';
    print '<td>'.$langs->trans('StrongAuthBoundAt').'</td>';
    print '<td>'.$langs->trans('StrongAuthLastUsed').'</td>';
    print '<td>'.$langs->trans('StrongAuthAction').'</td>';
    print '</tr>';
    foreach ($rows as $row) {
        print '<tr class="oddeven">';
        print '<td>'.dol_escape_htmltag($row->provider).'</td>';
        print '<td><code>'.dol_escape_htmltag(substr($row->subject, 0, 16)).'…</code></td>';
        print '<td>'.dol_escape_htmltag($row->bound_at).'</td>';
        print '<td>'.dol_escape_htmltag($row->last_used_at ?? '').'</td>';
        $formUrl = STRONGAUTH_MODULE_URL_ROOT.'/views/idp_link.php';
        print '<td>';
        print '<form method="POST" action="'.dol_escape_htmltag($formUrl).'" style="display:inline">';
        print '<input type="hidden" name="sa" value="unlink">';
        print '<input type="hidden" name="rowid" value="'.(int) $row->rowid.'">';
        print '<input type="hidden" name="token" value="'.dol_escape_htmltag($_SESSION['token']).'">';
        print '<button type="submit" class="button">'.dol_escape_htmltag($langs->trans('StrongAuthIdpUnlink')).'</button>';
        print '</form>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
}

llxFooter();
