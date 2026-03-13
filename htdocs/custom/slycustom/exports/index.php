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

$title = $langs->trans("SLYExports");
llxHeader('', $title);

print load_fiche_titre($title, '', 'title_export');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans("Export").'</th><th>'.$langs->trans("Description").'</th></tr>';

// SLY report exports (Tools → SLY Export) are in custom/slycustom/exports/
print '<tr><td><a href="'.DOL_URL_ROOT.'/custom/slycustom/exports/tools.php">'.$langs->trans("SLYExportMenu").'</a></td><td>'.$langs->trans("SLYExportMenu").' ('.dol_escape_htmltag($langs->trans("SLYExportAllInOne")).', '.$langs->trans("SLYExportSODetails").', '.$langs->trans("SLYExportShipmentDetails").', '.$langs->trans("SLYExportPODetails").', …)</td></tr>';
print '<tr><td colspan="2">'.$langs->trans("SLYCustomDescriptionLong").'</td></tr>';
print '</table>';
print '</div>';

llxFooter();
$db->close();
