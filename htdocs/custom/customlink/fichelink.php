<?php
/* Copyright (C) 2014-2017	Charlene BENKE	<charlie@patas-monkey.Com>
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
 *	\file	   htdocs/customlink/fiche.php
 *	\ingroup	tools
 *	\brief	  customelink card
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) 
	$res=@include("../../main.inc.php");		// For "custom" directory

dol_include_once('/custom/customlink/class/customlink.class.php');
dol_include_once('/custom/customlink/core/lib/customlink.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

$langs->load("customlink@customlink");

if (!getDolGlobalString('CUSTOMLINK_ENABLE_LINK')) {
	setEventMessage($langs->trans("CUSTOMLINK_FeatureDisabledInSetup"), 'warnings');
	header('Location: '.dol_buildpath('/custom/customlink/index.php', 1));
	exit;
}

$rowid=GETPOST('rowid', 'alpha');
$action=GETPOST('action', 'alpha');
//$fk_entrepot=GETPOST('fk_entrepot');
$backtopage=GETPOST('backtopage', 'alpha');
$type_source=GETPOST('type_source', 'alpha');
$ref_source=GETPOST('ref_source', 'alpha');
$type_target=GETPOST('type_target', 'alpha');
$ref_target=GETPOST('ref_target', 'alpha');

if (!$user->rights->customlink->lire) accessforbidden();

$object = new Customlink($db);

/*
 * Actions
 */


if ($action == 'add' && $user->rights->customlink->creer) {
	$error=0;
	// on controle la source
	if (empty($type_source)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentities("TypeSource")), 'errors');
		$error++;
	} else {	// on controle que la ref est bien saisie
		if (empty($ref_source)) {
			setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentities("RefSource")), 'errors');
			$error++;
		} else {	// on controle qu'il y a bien quelquechose de lié
			$object->fk_source = $object->get_idlink($type_source, $ref_source);
			if ($object->fk_source <=0 ) {
				setEventMessage($langs->trans("ErrorRefNotFound", $langs->transnoentities("RefSource")), 'errors');
				$error++;
			}
		}
	}

	// on controle la target
	if (empty($type_target)) {
		setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentities("TypeTarget")), 'errors');
		$error++;
	} else {	// on controle que la ref est bien saisie
		if (empty($ref_target)) {
			setEventMessage($langs->trans("ErrorFieldRequired", $langs->transnoentities("RefTarget")), 'errors');
			$error++;
		} else {	// on controle qu'il y a bien quelquechose de lié
			$object->fk_target = $object->get_idlink($type_target, $ref_target);
			if ($object->fk_target <=0 ) {
				setEventMessage($langs->trans("ErrorRefNotFound", $langs->transnoentities("RefTarget")), 'errors');
				$error++;
			}
		}
	}

	if (! $error) {
		// les fk_ sont déjà renseigné
		$object->type_target = $_POST["type_target"];
		$object->type_source = $_POST["type_source"];
		$object->ref_source	 = $_POST["ref_source"];
		$object->ref_target	 = $_POST["ref_target"];

		$result = $object->create($user);
		if ($result == -1) {
			$langs->load("errors");
			setEventMessage($object->error, 'errors');
			$error++;
		}

		if (! $error) {
			// on se positionne sur les même sources dans la liste
			header("Location:index.php?refelement=".$object->ref_source."&typeelement=".$object->type_source);
			exit;
		} else
			$action = '';
	} else
		$action = '';
} elseif ($action == 'delete' && $user->rights->customlink->supprimer) {
	$ret=$object->fetch($rowid);
	$ret=$object->delete($user);
	// retour à la liste
	header("Location:index.php");
	exit;
}
/*
 *	View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$help_url="EN:Module_customlink|FR:Module_customlink|ES:M&oacute;dulo_customlink";
llxHeader("", $langs->trans("CustomLink"), $help_url);

// accès direct = mode création
if ($action == '' && $user->rights->customlink->creer) {
	/*
	 * Create
	 */
	print load_fiche_titre($langs->trans("CreateCustomLink"));

	print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

	print '<table class="border" width="30%">';

	print '<tr><th colspan=2>'.$langs->trans("Source").'</th>';
	print '<th></th>';
	print '<th colspan=2>'.$langs->trans("Target").'</th></tr>';

	$allowed_link_types = getDolGlobalString('CUSTOMLINK_ALLOWED_LINK_TYPES', '');
	print '<tr><td >'.$langs->trans("Type").'</td><td>';
	select_element_type($type_source, 'type_source', 0, 1);
	print '</td>';
	print '<td></td><td >'.$langs->trans("Type").'</td><td>';
	select_element_type($type_target, 'type_target', 0, 1, $allowed_link_types);
	print '</td></tr>';
	print '<tr><td >'.$langs->trans("Ref").'</td><td>';
	print '<input type="text" name=ref_source value="'.$ref_source.'">';
	print '</td>';
	print '<td></td><td >'.$langs->trans("Ref").'</td><td>';
	print '<input type="text" name="ref_target" value="'.$ref_target.'">';
	print '</td></tr>';
	print '<tr><td colspan=5>';
	print '<div class="tabsAction">';
	print '<input type="submit" class="button" value="'.$langs->trans("CreateCustomLink").'">';
	if (! empty($backtopage)) {
		print ' &nbsp; &nbsp; ';
		print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
	}
	print '</div>';
	print '</td></tr>';
	print '</table>';
	print '</form>';
} else {
	/*
	 * Show
	 */
	$ret = $object->fetch($rowid);

	print load_fiche_titre($langs->trans("ShowCustomlink"));

	print '<table class="border" width="100%">';
	$linkback = '<a href="'.dol_buildpath('/custom/customlink/index.php', 1).'">'.$langs->trans("BackToList").'</a>';
	print '<tr><td>'.$langs->trans("Ref").'</td><td colspan="2">'.$object->rowid.'</td></tr>';
	print '<tr><td>'.$langs->trans("TypeSource").'</td><td colspan="2">'.dol_escape_htmltag($object->type_source).'</td></tr>';
	print '<tr><td>'.$langs->trans("TypeTarget").'</td><td colspan="2">'.dol_escape_htmltag($object->type_target).'</td></tr>';
	print '</table>';

	print '<div class="tabsAction">';
	if ($user->rights->customlink->supprimer) {
		print '<a class="butAction" href="'.dol_buildpath('/custom/customlink/fichelink.php', 1).'?rowid='.((int) $object->rowid).'&action=delete&token='.newToken().'">'.$langs->trans('Delete').'</a>';
	} else {
		print '<a class="butActionRefused" href="#" title="'.$langs->trans("NotAllowed").'">'.$langs->trans('Delete').'</a>';
	}
	print "<br>\n";
	print '</div>';
}
llxFooter();
$db->close();