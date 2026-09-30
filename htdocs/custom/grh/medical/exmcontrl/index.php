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
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
dol_include_once('/grh/medical/exmcontrl/class/exmcontrl.class.php');
dol_include_once('/grh/lib/medical.lib.php');
dol_include_once('/grh/medical/class/medical.class.php');
$medical 	  = new medical($db);

$exmcontrl 	  = new exmcontrl($db);
$form 		= new Form($db);
$action   = GETPOST('action','alpha');
$type_array = ['1'=>$langs->trans('Normale'),'2'=>$langs->trans('Urgence')];
$apte_array = ['1'=>$langs->trans('Yes'),'2'=>$langs->trans('No')];
$var = false;
$filter = '';
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
if (! $sortfield) $sortfield = "rowid";
if (! $sortorder) $sortorder = "DESC";
$id 	  = GETPOST('id','int');
$daId 	  = GETPOST('daId','int');
$search_dated      		= GETPOST('search_dated');
$search_datef      		= GETPOST('search_datef');
$search_type      		= GETPOST('search_type');
$search_apte      		= GETPOST('search_apte');
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
$search_dated      		= '';
$search_datef      		= '';
$search_type      		= '';
$search_apte      		= '';
}
$val_dated ='';
$val_datef ='';
if (isset($search_dated) && !empty($search_dated)) {
	list($sd, $sm, $sy)  = explode("/", $search_dated);
	$val_dated =$db->escape($sy.'-'.$sm.'-'.$sd);
}
if (isset($search_datef) && !empty($search_datef)) {
	list($ed, $em, $ey)  = explode("/", $search_datef);
	$val_datef =  $db->escape($ey.'-'.$em.'-'.$ed) ;
}


if(!empty($id)){
    $object = new exmcontrl($db);
    $object->fetch($id);
    if (!($object->id > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 

$filter .= (!empty($val_dated) ) ? " AND dated ='". $db->escape($val_dated)."'"  : "";
$filter .= (!empty($val_datef) ) ? " AND datef ='". $db->escape($val_datef)."'"  : "";
$filter .= (!empty($search_apte) && $search_apte != -1) ? " AND apte =". $db->escape($search_apte)  : "";
$filter .= (!empty($search_type) && $search_type != -1) ? " AND type =". $db->escape($search_type)  : "";

$filter .= ' AND da = '. $daId;

$getAppelOffreList = $exmcontrl->fetchAll($sortorder, $sortfield, $conf->liste_limit + 1, $offset, $filter);

// Si erreur SQL
if ($getAppelOffreList == '-1') {
    print_fiche_titre($langs->trans('listDem'), '', 'title_project.png');

    dol_print_error($db, $langs->trans('Error').' '.$exmcontrl->error);
    exit();
}
if ($id > 0) {
	$exmcontrl->fetch($id);
	llxHeader('', $langs->trans('exmcontrl'));
	$head = medical_prepare_head($daId);
	print_fiche_titre($langs->trans('exmcontrl'));

	dol_fiche_head($head, 'exmcontrl', $langs->trans("exmcontrl"), 0, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();

	print '<table class="border" width="100%">';
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("DateStart").'</td>';
	print '<td align="left">'. dol_print_date($exmcontrl->dated,'day') .'</td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("DateEnd").'</td>';
	print '<td align="left">'. dol_print_date($exmcontrl->datef,'day') .'</td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("observation").'</td>';
	print '<td align="left">'. $exmcontrl->observation .' </td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("Type").'</td>';
	print '<td align="left">'. $type_array[$exmcontrl->type] .' </td></tr>';
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("apte").'</td>';
	print '<td align="left">'. $apte_array[$exmcontrl->apte] .' </td></tr>';
	

	print '</table>';

	print '<br>';
	print '<div style="text-align: right; margin-top: 8px;">';
	print '<a href="./card.php?action=edit&id='. $id .'&daId='.$daId.'" class="butAction">'.$langs->trans('Modify').'</a>';
	print '<a href="./index.php?action=delete&id='. $id .'&daId='.$daId.'" class="butActionBTNC butActionDelete">'.$langs->trans('DeletTransp').'</a>';
	print '<a href="./index.php?daId='. $daId .'" class="butAction">'.$langs->trans('Cancel').'</a>';
	print '</div>';

	if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id.'&daId='.$daId, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
    }
}
else {

$morejs  = array("/grh/js/exmcontrl.js");
llxHeader('', $langs->trans('exmcontrl'),'','','','',$morejs);
	$head = medical_prepare_head($daId);
		print_fiche_titre($langs->trans('exmcontrl'));

	dol_fiche_head($head, 'exmcontrl', $langs->trans("exmcontrl"), 1, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<input name="id" value="'. $id .'" type="hidden" />';
	print '<input name="daId" value="'. $daId .'" type="hidden" />';
	print '<table class="noborder" style="min-width:100%;width:auto;overflow-x: scroll;">';
	print "<tr class=\"liste_titre\">";
	print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"], "rowid", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("DateStart"),$_SERVER["PHP_SELF"], "dated", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("DateEnd"),$_SERVER["PHP_SELF"], "datef", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("observation"),$_SERVER["PHP_SELF"], "observation", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Type"),$_SERVER["PHP_SELF"], "type", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("apte"),$_SERVER["PHP_SELF"], "apte", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre('',$_SERVER["PHP_SELF"],"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
	print "</tr>\n";

// FILTRES

	print '<tr class="liste_titre">';
	print '<td class="liste_titre" colspan="1" align="center">';
	print '</td>';
	print '<td align="center" >';
	print '<input autocomplete="off" class="flat datepicker" type="text" size="14" name="search_dated" value="'.$search_dated.'">';
	 print '</td>';
	print '<td align="center" >';
	// End Date
	print '<input autocomplete="off" class="flat datepicker" type="text" size="14" name="search_datef" value="'.$search_datef.'">';
	 print '</td>';
	print '<td align="center" >';
    print '</td>';

    print '<td class="liste_titre" colspan="1" align="center">';
    print $form->selectarray('search_type', $type_array, (($search_type !=-1) ? $search_type : null), 1, 0, 0);
	print '</td>';

	print '<td class="liste_titre" colspan="1" align="center">';
    print $form->selectarray('search_apte', $apte_array, (($search_apte !=-1) ? $search_apte : null), 1, 0, 0);
	print '</td>';



    print '<td align="right" style="width: 67px;">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print '</td>';

	print "</tr>\n";

	// Lines
	if (count($exmcontrl->rows)) {
		//$createdBy = new User($db);
		foreach($exmcontrl->rows as $line) {
			$var = !$var;
			$id 			= $line->id;
			$datec          = dol_print_date($line->datec,'day');
			$dated         = dol_print_date($line->dated,'day');
		  	$datef        = dol_print_date($line->datef,'day');
		  	$observation         = $line->observation;
		  	$type         = $line->type;
		  	$apte          = $line->apte;
			
			print '<td align="center" style ="white-space: nowrap;">';
				print $exmcontrl->getNomUrl(1,  $line->id, $id,$daId);
			print '</td>';

			print '<td align="center">'. $dated .'</td>';

			print '<td align="center">'. $datef.'</td>';

			print '<td align="center">'. $observation .' </td>';

			print '<td align="center">'. $type_array[$type] .' </td>';
			
			print '<td align="center">'. $apte_array[$apte] .' </td>';
			
			print '<td><div style="text-align: right; margin-top: 8px;">';
			print '<a href="./index.php?action=delete&id='. $id .'&daId='.$daId.'" >'.img_delete().'</a>';
			print '</div></td>';
			
			print '</tr>';

		}
	} else {

		print '<tr>';
	    print '<td colspan="9" '.$bc[false].'">'.$langs->trans('NoneResult').'</td>';
	    print '</tr>';
	}

	print '</table>';
	print '</form>';

		print '<div style="text-align: right; margin-top: 8px;">';
		print '<a href="./card.php?action=add&daId='.$daId.'" class="butAction">'.$langs->trans('Addexmcontrl').'</a>';
		print '</div>';
	
	


		


}

llxFooter();

$db->close();


?>