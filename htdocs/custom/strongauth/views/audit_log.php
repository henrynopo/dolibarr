<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Admin-only audit log viewer.
 *
 * Filters:
 *   - user id, event type, method, date range, IP substring
 *
 * Permissions: $user->rights->strongauth->audit
 */

$res = 0;
if (!$res && file_exists('../main.inc.php'))     $res = @include('../main.inc.php');
if (!$res && file_exists('../../main.inc.php'))  $res = @include('../../main.inc.php');
if (!$res && file_exists('../../../main.inc.php')) $res = @include('../../../main.inc.php');
if (!$res) die('Include of main fails');

require_once __DIR__.'/../lib/strongauth.lib.php';

if (empty($user->id)) {
    header('Location: '.DOL_URL_ROOT.'/index.php');
    exit;
}
if (empty($user->rights->strongauth->audit)) {
    accessforbidden();
}

$langs->load('strongauth@strongauth');
$langs->load('admin');

$filterUserId    = (int) GETPOST('filter_userid', 'int');
$filterEventType = GETPOST('filter_event', 'aZ09');
$filterMethod    = GETPOST('filter_method', 'aZ09');
$filterIp        = GETPOST('filter_ip', 'alphanohtml');
$filterFrom      = GETPOST('filter_from', 'alphanohtml');
$filterTo        = GETPOST('filter_to', 'alphanohtml');
$page            = max(0, GETPOST('page', 'int'));
$pageSize        = 50;

$where = array("entity = ".(int) $conf->entity);
if ($filterUserId) {
    $where[] = "fk_user = ".(int) $filterUserId;
}
if (!empty($filterEventType)) {
    $where[] = "event_type = '".$db->escape($filterEventType)."'";
}
if (!empty($filterMethod)) {
    $where[] = "method = '".$db->escape($filterMethod)."'";
}
if (!empty($filterIp)) {
    $where[] = "ip_address LIKE '%".$db->escape($filterIp)."%'";
}
if (!empty($filterFrom)) {
    $where[] = "event_time >= '".$db->escape($filterFrom)." 00:00:00'";
}
if (!empty($filterTo)) {
    $where[] = "event_time <= '".$db->escape($filterTo)." 23:59:59'";
}
$whereSql = "WHERE ".implode(' AND ', $where);

$offset = $page * $pageSize;
// Limit the user-table LEFT JOIN to users visible to the current entity to
// avoid leaking logins/real names of users that belong to other entities
// (in multicompany mode the user table is shared at entity=0 and would
// otherwise expose cross-entity names to anyone who can read the log).
$sql = "SELECT a.rowid, a.event_time, a.fk_user, a.username_try, a.event_type, a.method,"
     . " a.ip_address, a.user_agent, a.detail,"
     . " u.login AS user_login, u.firstname, u.lastname"
     . " FROM ".MAIN_DB_PREFIX."strongauth_audit AS a"
     . " LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = a.fk_user"
     . "      AND u.entity IN (0, ".(int) $conf->entity.")"
     . " ".$whereSql
     . " ORDER BY a.event_time DESC LIMIT ".$pageSize." OFFSET ".$offset;
$res = $db->query($sql);

$totalRes = $db->query("SELECT COUNT(*) AS c FROM ".MAIN_DB_PREFIX."strongauth_audit ".$whereSql);
$totalRow = $db->fetch_object($totalRes);
$total = (int) ($totalRow->c ?? 0);

llxHeader('', $langs->trans('StrongAuthAuditTitle'), '', '', 0, 0, '', '', '', 'mod-admin page-strongauth');

$linkback = '<a href="'.STRONGAUTH_MODULE_URL_ROOT.'/admin/setup.php">'.$langs->trans("StrongAuthSetup").'</a>';
print load_fiche_titre($langs->trans('StrongAuthAuditTitle'), $linkback, 'title_setup');

print '<p class="opacitymedium">'.$langs->trans('StrongAuthAuditIntro').'</p>';

// Filters
print '<form method="GET" class="filter">';
print '<input type="hidden" name="id" value="">';   // keep query clean
print '<table class="noborder"><tr>';
print '<td>'.$langs->trans('User').'</td><td><input type="number" name="filter_userid" value="'.$filterUserId.'" size="6"></td>';
print '<td>'.$langs->trans('StrongAuthEvent').'</td><td><select name="filter_event">';
print '<option value=""></option>';
foreach (array(
    'login_ok', 'login_fail_password', 'login_fail_2fa', 'login_locked',
    'factor_enroll', 'factor_reset', 'factor_use',
    'backupcode_use', 'backupcode_regen', 'admin_reset', 'key_rotate'
) as $ev) {
    $sel = ($filterEventType === $ev) ? ' selected' : '';
    print '<option value="'.$ev.'"'.$sel.'>'.$ev.'</option>';
}
print '</select></td>';
print '<td>'.$langs->trans('StrongAuthMethod').'</td><td><input type="text" name="filter_method" value="'.dol_escape_htmltag($filterMethod).'" size="10"></td>';
print '<td>'.$langs->trans('IP').'</td><td><input type="text" name="filter_ip" value="'.dol_escape_htmltag($filterIp).'" size="12"></td>';
print '</tr><tr>';
print '<td>'.$langs->trans('From').'</td><td><input type="date" name="filter_from" value="'.dol_escape_htmltag($filterFrom).'"></td>';
print '<td>'.$langs->trans('To').'</td><td><input type="date" name="filter_to" value="'.dol_escape_htmltag($filterTo).'"></td>';
print '<td colspan="2"><input type="submit" class="button" value="'.$langs->trans('Search').'"></td>';
print '</tr></table>';
print '</form>';

// Table
print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('User').'</th>';
print '<th>'.$langs->trans('StrongAuthEvent').'</th>';
print '<th>'.$langs->trans('StrongAuthMethod').'</th>';
print '<th>IP</th>';
print '<th>UA</th>';
print '<th>'.$langs->trans('Detail').'</th>';
print '</tr>';

if ($res) {
    while ($row = $db->fetch_object($res)) {
        $userLabel = $row->fk_user
            ? dol_escape_htmltag(trim(($row->firstname ?? '').' '.($row->lastname ?? '')).' ('.$row->user_login.')')
            : '<span class="opacitymedium">'.dol_escape_htmltag($row->username_try ?? '').'</span>';
        print '<tr>'
            .'<td>'.dol_escape_htmltag($row->event_time).'</td>'
            .'<td>'.$userLabel.'</td>'
            .'<td>'.dol_escape_htmltag($row->event_type).'</td>'
            .'<td>'.dol_escape_htmltag($row->method ?? '').'</td>'
            .'<td>'.dol_escape_htmltag($row->ip_address ?? '').'</td>'
            .'<td>'.dol_escape_htmltag(substr($row->user_agent ?? '', 0, 40)).'</td>'
            .'<td>'.dol_escape_htmltag($row->detail ?? '').'</td>'
            .'</tr>';
    }
}
print '</table>';

// Pagination
$totalPages = max(1, (int) ceil($total / $pageSize));
$base = '?'.http_build_query(array_filter(array(
    'filter_userid' => $filterUserId, 'filter_event' => $filterEventType,
    'filter_method' => $filterMethod, 'filter_ip' => $filterIp,
    'filter_from' => $filterFrom, 'filter_to' => $filterTo,
)));
print '<p>'.$langs->trans('Page').' '.($page + 1).' / '.$totalPages.' ('.$total.' '.$langs->trans('Records').')</p>';
if ($page > 0) {
    print '<a class="button" href="'.$base.'&page='.($page - 1).'">&laquo; '.$langs->trans('Previous').'</a> ';
}
if ($page + 1 < $totalPages) {
    print '<a class="button" href="'.$base.'&page='.($page + 1).'">'.$langs->trans('Next').' &raquo;</a>';
}

llxFooter();
$db->close();
