<?php
/* Copyright (C) 2025 SLY Custom
 *
 * Switch user language and redirect back. Used by language picker dropdown.
 */

require '../main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

$lang_code = GETPOST('lang_code', 'alphanohtml');
$backtopage = GETPOST('backtopage', 'none');

if ($lang_code === '' || $lang_code === null) {
	$backtopage = $backtopage ?: (DOL_URL_ROOT.'/');
	header('Location: '.$backtopage);
	exit;
}

if ($user->id > 0) {
	$tab = array('MAIN_LANG_DEFAULT' => $lang_code);
	dol_set_user_param($db, $conf, $user, $tab);
}

$backtopage = $backtopage ?: (DOL_URL_ROOT.'/');
if (strpos($backtopage, 'http') !== 0 && strpos($backtopage, DOL_URL_ROOT) !== 0) {
	$backtopage = DOL_URL_ROOT.'/'.ltrim($backtopage, '/');
}
header('Location: '.$backtopage);
exit;
