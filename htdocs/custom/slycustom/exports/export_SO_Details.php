<?php
/* Copyright (C) 2025 SLY Custom
 * Legacy endpoint redirected to tabbed export page.
 */

require_once __DIR__.'/../../../main.inc.php';

if (!isModEnabled('slycustom')) {
	accessforbidden();
	exit;
}

// Exports expose the full business dataset: require the module export right
// (admins pass) and never serve external/thirdparty users.
if (empty($user->admin) && empty($user->rights->slycustom->export->read)) {
	accessforbidden();
	exit;
}
if (!empty($user->socid)) {
	accessforbidden();
	exit;
}

$url = DOL_URL_ROOT.'/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_invoices&tab=so_details';
header('Location: '.$url, true, 302);
exit;
