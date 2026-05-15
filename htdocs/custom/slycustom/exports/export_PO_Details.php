<?php
/* Copyright (C) 2025 SLY Custom
 * Legacy endpoint redirected to tabbed export page.
 */

require_once __DIR__.'/../../../main.inc.php';

if (!isModEnabled('slycustom')) {
	accessforbidden();
	exit;
}

$url = DOL_URL_ROOT.'/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_invoices&tab=po_details';
header('Location: '.$url, true, 302);
exit;
