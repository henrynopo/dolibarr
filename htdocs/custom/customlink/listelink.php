<?php
/* Copyright (C) 2014-2021	Charlene BENKE	 <charlene@patas-monkey.com>
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
 *	  \file	   htdocs/customlink/index.php
 *	  \ingroup	tools
 *	  \brief	  liste liens présent dans 
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

dol_include_once('/custom/customlink/class/customlink.class.php');
dol_include_once('/custom/customlink/core/lib/customlink.lib.php');


$langs->load("customlink@customlink");
$langs->load("sendings");

if (!getDolGlobalString('CUSTOMLINK_ENABLE_LINK')) {
	setEventMessage($langs->trans("CUSTOMLINK_FeatureDisabledInSetup"), 'warnings');
	header('Location: '.dol_buildpath('/custom/customlink/index.php', 1));
	exit;
}

// Security check
$result=restrictedArea($user, 'customlink');
$sortfield = GETPOST("sortfield");
$sortorder = GETPOST("sortorder");
if (! $sortfield) $sortfield="el.sourcetype";
if (! $sortorder) $sortorder="ASC";

$page = (GETPOST("page", "int")?GETPOST("page", "int"):0);
$limit = $conf->liste_limit;
$offset = $limit * $page;

$objectlink=new Customlink($db);

$refid="";

$action = GETPOST("action");
if ($action=="delete") {
	$ret=$objectlink->fetch(GETPOST("rowid"));
	$ret=$objectlink->delete($user);
}


$typeelement=GETPOST("typeelement");
if (!$typeelement)
	$typeelement=-1;
$refelement=GETPOST("refelement");
if ($refelement) {
	// on détermine l'id de l'élément selon le type
	$refid=$objectlink->get_idlink($typeelement, $refelement);
}


$sql = "SELECT *";
$sql .= " FROM ".MAIN_DB_PREFIX."element_element as el";
// on applique les filtres
if ($typeelement !=-1) {
	if ($refid > 0) {
		$sql .= " WHERE concat(sourcetype,'-',fk_source)='".$typeelement."-".$refid."'";
		$sql .= " OR concat(targettype,'-',fk_target)='".$typeelement."-".$refid."'";
	} else {
		$sql .= " WHERE sourcetype='".$typeelement."'";
		$sql .= " OR targettype='".$typeelement."'";
	}
}	

$sql.= " ORDER BY $sortfield $sortorder";
$sql.= $db->plimit($limit+1, $offset);
$result = $db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
} else
	dol_print_error($db);

$help_url='https://wiki.patas-monkey.com/index.php?title=CustomLink';
llxHeader("", $langs->trans("ListOfLinks"), $help_url);

print '<br>';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print_barre_liste($langs->trans("ListOfLinks"), $page, "index.php", "", $sortfield, $sortorder, '', $num);
print '<input type="hidden" name="action" value="filter">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<table class=border width=30%><tr>';
print '<td>';
select_element_type($typeelement, 'typeelement', 1);
print '</td>';
print '<td>'.$langs->trans("Ref").'</td><td>';
print '<input type=text size=10 name=refelement id=refelement value="'.GETPOST("refelement").'"></td>';
print '<td><input type=submit class="butAction" name=search ></td>';
print '</tr></table>';
print '</form>';
print '<br>';
print '<table class="noborder" width="100%">';

print '<tr class="liste_titre">';

print_liste_field_titre(
				"", "", "", '', '', 
				' width=20px align="left"', "", ""
);
print_liste_field_titre(
				$langs->trans("SourceRef"), "index.php", "", 
				'', '', 'align="left"', $sortfield, $sortorder
);
print_liste_field_titre(
				$langs->trans("TargetRef"), "index.php", "",
				'', '', 'align="left"', $sortfield, $sortorder
);
print_liste_field_titre(
				"", "", "", '', '', 
				'width=50px align="right"', "", ""
);

print "</tr>\n";

if ($num) {

	$i = 0;

	while ($i < min($num, $limit)) {
		$objp = $db->fetch_object($result);

		print "<tr>";
		
		print '<td><a href="fichelink.php?rowid='.$objp->rowid.'">'.img_edit().'</a></td>';
		print '<td>'.$objectlink->getUrlofLink($objp->sourcetype, $objp->fk_source).'&nbsp;'.img_info().'</td>';
		print '<td>'.$objectlink->getUrlofLink($objp->targettype, $objp->fk_target).'&nbsp;'.img_info().'</td>';
		print '<td align=right>';
		print '<a href="listelink.php?rowid='.$objp->rowid.'&action=delete">'.img_delete().'</a></td>';
		print "</tr>\n";
		$i++;
	}

} 

$db->free($result);
print "</table>";

llxFooter();
$db->close();