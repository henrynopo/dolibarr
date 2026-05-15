<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__.'/../../../main.inc.php';
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

// Commerce index: Tools → SLY Export → Invoices & Shipment | Payments planning
print '<tr><td><strong>'.$langs->trans("Tools").' → '.$langs->trans("SLYExportMenu").'</strong><br>';
print '<a href="'.DOL_URL_ROOT.'/custom/slycustom/exports/export_all.php?mainmenu=tools&leftmenu=sly_export_invoices">'.$langs->trans("SLYExportMenuInvoices").'</a> · ';
print '<a href="'.DOL_URL_ROOT.'/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_cashflow&tab=so_inv_receivable">'.$langs->trans("SLYExportMenuPaymentsPlanning").'</a>';
print '</td><td>'.$langs->trans("SLYExportTabGroupDetails").' / '.$langs->trans("SLYExportTabGroupCashflow").'</td></tr>';
print '<tr><td colspan="2">'.$langs->trans("SLYCustomDescriptionLong").'</td></tr>';
print '</table>';
print '</div>';

llxFooter();
$db->close();
