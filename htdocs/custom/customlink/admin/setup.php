<?php
/* Copyright (C) 2014-2021	 Charlene BENKE <charlene@patas-monkey.com>
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
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file	   htdocs/customlink/admin/setup.php
 *		\ingroup	customlink
 *		\brief	  Page to setup the module customlink (enable/disable Tag and Link features)
 */

// Dolibarr environment
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");		// For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");	// For "custom" directory


require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once("/custom/customlink/core/lib/customlink.lib.php");

$langs->load("admin");
$langs->load("customlink@customlink");

if (! $user->admin) accessforbidden();

$action = GETPOST('action', 'alpha');

/*
 * Actions: save feature toggles and allowed link types
 */
if ($action == 'update') {
	$enable_tag = GETPOST('CUSTOMLINK_ENABLE_TAG', 'alpha') ? '1' : '0';
	$enable_link = GETPOST('CUSTOMLINK_ENABLE_LINK', 'alpha') ? '1' : '0';
	$allowed_arr = GETPOST('CUSTOMLINK_ALLOWED_LINK_TYPES', 'array');
	$allowed_link_types = is_array($allowed_arr) ? implode(',', array_map('trim', $allowed_arr)) : '';

	$r1 = dolibarr_set_const($db, 'CUSTOMLINK_ENABLE_TAG', $enable_tag, 'chaine', 0, '', $conf->entity);
	$r2 = dolibarr_set_const($db, 'CUSTOMLINK_ENABLE_LINK', $enable_link, 'chaine', 0, '', $conf->entity);
	$r3 = dolibarr_set_const($db, 'CUSTOMLINK_ALLOWED_LINK_TYPES', $allowed_link_types, 'chaine', 0, '', $conf->entity);
	if ($r1 !== false && $r2 !== false && $r3 !== false) {
		$conf->global->CUSTOMLINK_ENABLE_TAG = $enable_tag;
		$conf->global->CUSTOMLINK_ENABLE_LINK = $enable_link;
		$conf->global->CUSTOMLINK_ALLOWED_LINK_TYPES = $allowed_link_types;
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

// Sync dictionary display labels to module's authoritative list (button below)
if ($action == 'syncdict') {
	$result = customlink_sync_dictionary_labels($db);
	if (!empty($result['error'])) {
		setEventMessages($result['error'], null, 'errors');
	} else {
		setEventMessages($langs->trans("CustomlinkSyncDictDone", $result['updated']), null, 'mesgs');
	}
	$action = '';
}

// Default to enabled when constant not set (e.g. first install)
$enable_tag = (getDolGlobalString('CUSTOMLINK_ENABLE_TAG') !== '0');
$enable_link = (getDolGlobalString('CUSTOMLINK_ENABLE_LINK') !== '0');
$allowed_link_types = getDolGlobalString('CUSTOMLINK_ALLOWED_LINK_TYPES', '');
$allowed_link_types_arr = !empty($allowed_link_types) ? array_map('trim', explode(',', $allowed_link_types)) : array();

// Load all element types for "allowed link types" selector
$element_types = array();
$sqle = "SELECT rowid, label, type, translatefile FROM ".MAIN_DB_PREFIX."c_element_type ORDER BY incore desc, type";
$resqle = $db->query($sqle);
if ($resqle) {
	while ($obj = $db->fetch_object($resqle)) {
		$element_types[] = $obj;
	}
}

/*
 * View
 */
$page_name = $langs->trans("CustomlinkSetup"). " - " .$langs->trans("CustomlinkGeneralSetting");
$help_url='https://wiki.patas-monkey.com/index.php?title=CustomLink';

llxHeader('', $page_name, $help_url);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($page_name, $linkback, 'title_setup');

$head = customlink_admin_prepare_head();
dol_fiche_head($head, 'admin', $langs->trans("CustomLink"), -1, 'customlink@customlink');

dol_htmloutput_mesg($mesg);

// Feature toggles section (Tag / Link)
print '<div class="div-table-responsive-no-min">';
print '<h3>'.$langs->trans("CUSTOMLINK_FeatureToggles").'</h3>';
print '<form action="'.$_SERVER["PHP_SELF"].'?action=update" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td class="right">'.$langs->trans("Value").'</td></tr>';
$form = new Form($db);
print '<tr class="oddeven"><td>';
print $langs->trans("CUSTOMLINK_EnableTagFeature").'<br>';
print '<span class="opacitymedium">'.$langs->trans("CUSTOMLINK_EnableTagFeatureDesc").'</span>';
print '</td><td class="right">';
print $form->selectyesno('CUSTOMLINK_ENABLE_TAG', $enable_tag, 1);
print '</td></tr>';
print '<tr class="oddeven"><td>';
print $langs->trans("CUSTOMLINK_EnableLinkFeature").'<br>';
print '<span class="opacitymedium">'.$langs->trans("CUSTOMLINK_EnableLinkFeatureDesc").'</span>';
print '</td><td class="right">';
print $form->selectyesno('CUSTOMLINK_ENABLE_LINK', $enable_link, 1);
print '</td></tr>';
print '<tr class="oddeven"><td>';
print $langs->trans("CUSTOMLINK_AllowedLinkTypes").'<br>';
print '<span class="opacitymedium">'.$langs->trans("CUSTOMLINK_AllowedLinkTypesDesc").'</span>';
print '</td><td style="text-align:left; vertical-align:top;">';
if (!empty($element_types)) {
	$cols = 4;
	$total = count($element_types);
	print '<table class="noborder nohover" style="width:100%; text-align:left;"><tr>';
	foreach ($element_types as $i => $et) {
		if ($i > 0 && $i % $cols === 0) {
			print '</tr><tr>';
		}
		$langs->load($et->translatefile);
		$checked = in_array($et->type, $allowed_link_types_arr) ? ' checked="checked"' : '';
		print '<td class="nowraponall" style="padding: 2px 12px 2px 0; text-align:left; vertical-align:middle; width:25%;">';
		print '<label class="valignmiddle">';
		print '<input type="checkbox" class="valignmiddle" name="CUSTOMLINK_ALLOWED_LINK_TYPES[]" value="'.dol_escape_htmltag($et->type).'"'.$checked.'> ';
		print dol_escape_htmltag($langs->trans($et->label));
		print '</label>';
		print '</td>';
	}
	// pad last row if needed
	while ($total % $cols !== 0) {
		print '<td style="width:25%;"></td>';
		$total++;
	}
	print '</tr></table>';
} else {
	print $langs->trans("CUSTOMLINK_NoElementTypes");
}
print '</td></tr>';
print '</table>';
print '<div class="center margin-top-2">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';
print '</form>';
print '</div>';

dol_fiche_end();

// Dictionary label sync section (independent form, POST + CSRF token)
print '<br>';
print load_fiche_titre($langs->trans("CustomlinkSyncDict"));
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="syncdict">';
print '<table class="noborder centpercent">';
print '<tr class="oddeven"><td>'.$langs->trans("CustomlinkSyncDictDesc").'</td>';
print '<td class="right"><input type="submit" class="button" value="'.$langs->trans("CustomlinkSyncDict").'"></td></tr>';
print '</table>';
print '</form>';

/*
 *  Infos pour le support
 */
print '<br>';
libxml_use_internal_errors(true);
$sxe = simplexml_load_string(nl2br(file_get_contents('../changelog.xml')));
if ($sxe === false) {
	echo "Erreur lors du chargement du XML\n";
	foreach (libxml_get_errors() as $error) 
		print $error->message;
	exit;
} else
	$tblversions=$sxe->Version;

$currentversion = $tblversions[count($tblversions)-1];

print '<table class="noborder" width="100%">'."\n";
print '<tr class="liste_titre">'."\n";
print '<td width=20%>'.$langs->trans("SupportModuleInformation").'</td>'."\n";
print '<td>'.$langs->trans("Value").'</td>'."\n";
print "</tr>\n";
print '<tr><td >'.$langs->trans("DolibarrVersion").'</td><td>'.DOL_VERSION.'</td></tr>'."\n";
print '<tr><td >'.$langs->trans("ModuleVersion").'</td>';
print '<td>'.$currentversion->attributes()->Number." (".$currentversion->attributes()->MonthVersion.')</td></tr>'."\n";
print '<tr><td >'.$langs->trans("PHPVersion").'</td><td>'.version_php().'</td></tr>'."\n";
print '<tr><td >'.$langs->trans("DatabaseVersion").'</td>';
print '<td>'.$db::LABEL." ".$db->getVersion().'</td></tr>'."\n";
print '<tr><td >'.$langs->trans("WebServerVersion").'</td>';
print '<td>'.$_SERVER["SERVER_SOFTWARE"].'</td></tr>'."\n";
print '<tr>'."\n";
print '<td colspan="2">'.$langs->trans("SupportModuleInformationDesc").'</td></tr>'."\n";
print "</table>\n";

// Show messages
dol_htmloutput_mesg($mesg);

// Footer
llxFooter();
$db->close();