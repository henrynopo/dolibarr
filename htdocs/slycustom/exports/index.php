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

// Link to main exports if SLY export scripts are in main htdocs/exports (when using SLY14.0 fork)
$main_export = DOL_DOCUMENT_ROOT.'/exports/export_all.php';
if (file_exists($main_export)) {
	print '<tr><td><a href="'.DOL_URL_ROOT.'/exports/export_all.php">export_all.php</a></td><td>'.dol_escape_htmltag($langs->trans("SLYCustomDescriptionLong")).'</td></tr>';
}
print '<tr><td colspan="2">'.$langs->trans("SLYCustomDescriptionLong").'</td></tr>';
print '</table>';
print '</div>';

llxFooter();
$db->close();
