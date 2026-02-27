<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

if (!is_object($conf->slycustom) || empty($conf->slycustom->enabled)) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array("slycustom@slycustom", "other"));

$title = $langs->trans("SLYExportMenu");
llxHeader('', $title);

print load_fiche_titre($title, '', 'title_export');

// Export scripts are in slycustom module
$export_base = DOL_URL_ROOT.'/slycustom/exports';

$links = array(
	'SLYExportAllInOne' => 'export_all.php',
	'SLYExportSODetails' => 'export_SO_Details.php',
	'SLYExportSOInvoiceDetails' => 'export_SO_Inv_Details.php',
	'SLYExportShipmentDetails' => 'export_Shipment_Details.php',
	'SLYExportPODetails' => 'export_PO_Details.php',
	'SLYExportPOInvoiceDetails' => 'export_PO_Inv_Details.php',
);

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans("Export").'</th><th>'.$langs->trans("Description").'</th></tr>';
foreach ($links as $langkey => $script) {
	$url = $export_base.'/'.$script.'?mainmenu=tools&leftmenu=sly_export';
	$label = $langs->trans($langkey);
	print '<tr class="oddeven"><td><a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($label).'</a></td>';
	print '<td>'.dol_escape_htmltag($label).'</td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
