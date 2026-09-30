<?php
/* Copyright (C) 2016	Yassine Belkaid	<y.belkaid@nextconcept.ma>
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
 * \file       appeloffres/index.php
 * \ingroup    list
 * \brief      List of markets
 */
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 
dol_include_once('/grh/ref/class/ref.class.php');

$ref 	  = new ref($db);
$form 		= new Form($db);
$var = false;
$filter = '';
$types_refs = array(1 => $langs->trans('track'), 2 => $langs->trans('other'));
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
if (! $sortfield) $sortfield = "rowid";
if (! $sortorder) $sortorder = "DESC";
$id 	  = GETPOST('id','int');
$search_name      		= GETPOST('search_name');
$search_datec      		= GETPOST('search_datec');
$search_type=GETPOST('search_type','int');

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
$search_name      		= '';
$search_datec      		= '';
$search_type    		= '';
$search_transp    		= '';
}
$filter .= (!empty($search_name) && $search_name != -1) ? " AND name = '". $db->escape($search_name)."'\n" : "";
$filter .= (!empty($search_type) && $search_type != -1) ? " AND type = '". $db->escape($search_type)."'\n" : "";

$getAppelOffreList = $ref->fetchAll($sortorder, $sortfield, $conf->liste_limit + 1, $offset, $filter);

// Si erreur SQL
if ($getAppelOffreList == '-1') {
    print_fiche_titre($langs->trans('ListOfAO'), '', 'title_project.png');

    dol_print_error($db, $langs->trans('Error').' '.$ref->error);
    exit();
}
if ($id > 0) {
	$ref->fetch($id);
	llxHeader('', $langs->trans('ref'));
	print_fiche_titre($langs->trans("Ref"),'','object_icon@grh');


	print '<table class="border" width="100%">';
	// Ref
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("name").'</td>';
	print '<td colspan="2">'. $ref->name .'</td></tr>';
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("type").'</td>';

	if($ref->type==1){
			print '<td colspan="2">Production </td></tr>';

	}
	if($ref->type==2){
			print '<td colspan="2">Achat </td></tr>';

	}
if($ref->type==3){
			print '<td colspan="2">RH </td></tr>';

	}
if($ref->type==4){
			print '<td colspan="2">Personnel </td></tr>';

	}


	// Classification
	
	print '</table>';
	print '<br>';
	print '<div style="float: right; margin-top: 8px;">';
		print '<a href="./card.php?action=edit&id='. $id .'" class="butAction">'.$langs->trans('Modify').'</a>';
	print '<a href="./index.php?mid='. $mid .'" class="butAction">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	$action   = GETPOST('action','alpha');

	if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id.'&daId='.$daId, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
    }
}
else {

 // Both test are required to be compatible with all browsers

llxHeader('', $langs->trans('Listref'));
print_fiche_titre($langs->trans("Listref"),'','object_icon@grh');
//$listrefts = $ref->findAll();

/*if(!$listrefts){
	echo 'aucun resulta';
	die();
}*/

	dol_fiche_head('', 'refs', $langs->trans("Listref"), 1, '');
	print '<div style="float: right; margin: 8px;">';
		print '<a href="./card.php?action=add" class="butAction">'.$langs->trans('Addref').'</a>';
		print '</div>';
	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<input name="id" value="'. $id .'" type="hidden" />';
	print '<table class="noborder" width="100%;">';
	print "<tr class=\"liste_titre\">";
	print_liste_field_titre($langs->trans("name"),$_SERVER["PHP_SELF"], "name", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("datec"), $_SERVER["PHP_SELF"], 'datec', '', '', 'align="center"', $sortfield, $sortorder);
		print_liste_field_titre($langs->trans("type"), $_SERVER["PHP_SELF"], 'type', '', '', 'align="center"', $sortfield, $sortorder);

	print_liste_field_titre('',$_SERVER["PHP_SELF"],"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
	print "</tr>\n";

	// FILTRES
	print '<tr class="liste_titre">';
	// namericule
	print '<td class="liste_titre" colspan="1" align="center">';
	print '<input class="flat" type="text" size="14" name="search_name" value="'.$search_name.'">';
	print '</td>';

	print '<td class="liste_titre" colspan="1" align="center">';
	print '</td>';
	print '<td class="liste_titre" colspan="1" align="center">';
    print $form->selectarray('search_type', array('-1'=>'','1'=>$langs->trans('Production'),'2'=>$langs->trans('Achat'),'3'=>$langs->trans('RH'),'4'=>$langs->trans('Personnel')),$search_stag);
    print '</td>';
	// ACTION
	print '<td align="right">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print '</td>';

	print "</tr>\n";

	// Lines
	if (count($ref->rows)) {
		//$createdBy = new User($db);
		$i=0;
		foreach($ref->rows as $line) {
			$var = !$var;
			$id 				= $line->id;
			$name 		        = $line->name;
			$datec 				= $line->datec ? dol_print_date($line->datec,'day') : '';
			if($i==0){
						print '<tr style="background-color: antiquewhite;" >';}
			else{
						print '<tr '.$bc[$var].'>';
			}
			print '<td align="left" style ="white-space: nowrap;">';
				print $ref->getNomUrl(1,  $line->id, $name);
			print '</td>';
			 
			print '<td align="center">'. $datec .'</td>';
			if ($line->type == 1)         print '<td align="center">'.$langs->trans('Production').'</td>';
 
            if ($line->type== 2)         print '<td align="center">'. $langs->trans('Achat').'</td>';
			if ($line->type== 3)         print '<td align="center">'. $langs->trans('RH').'</td>';
			if ($line->type== 4)         print '<td align="center">'. $langs->trans('Personnel').'</td>';


			print '<td><div style="float: right; margin-top: 8px;">';
			print '<a href="./index.php?action=delete&id='. $id .'" >'.$langs->trans('Deletref').'</a>';
			print '</div></td>';
			print '</tr>'."\n";
			$i++;
		}
	} else {
		print '<tr>';
	    print '<td colspan="9" '.$bc[false].'">'.$langs->trans('NoneResult').'</td>';
	    print '</tr>';
	}

	print '</table>';
	print '</form>';


		print '<br>';
		


}

llxFooter();

$db->close();


?>