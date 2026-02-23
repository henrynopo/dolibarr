<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

$res = 0;
if (!empty($res) && !empty($_GET["dol_ajax"])) {
	echo $res;
	exit;
}

require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (!$user->admin) {
	accessforbidden();
	exit;
}

$langs->loadLangs(array("admin", "slycustom@slycustom"));

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */

/*
 * View
 */
$page_name = "SLYCustomSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';
print '<tr><td>'.$langs->trans("SLYCustomDescription").'</td><td>';
print $langs->trans("SLYCustomDescriptionLong");
print '</td></tr>';
print '</table>';
print '</div>';

print "<br>\n";

llxFooter();
$db->close();
