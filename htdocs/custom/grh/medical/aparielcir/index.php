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
dol_include_once('/grh/medical/aparielcir/class/aparielcir.class.php');
dol_include_once('/grh/lib/medical.lib.php');
dol_include_once('/grh/medical/class/medical.class.php');
$medical 	  = new medical($db);

$aparielcir 	  = new aparielcir($db);
$form 		= new Form($db);
$action   = GETPOST('action','alpha');

$var = false;
$filter = '';
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
if (! $sortfield) $sortfield = "rowid";
if (! $sortorder) $sortorder = "DESC";
$id 	  = GETPOST('id','int');
$daId 	  = GETPOST('daId','int');
$search_lot      		= GETPOST('search_lot');
$search_year      		= GETPOST('search_year');
$search_month      		= GETPOST('search_month');
$search_mat      		= GETPOST('search_mat');
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
$search_lot      		= '';
$search_year      		= '';
$search_month      		= '';
$search_mat      		= '';
}
$filter .= (!empty($search_lot) && $search_lot != -1) ? " AND lot =". $db->escape($search_lot)  : "";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) =". $db->escape($search_year)  : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) =". $db->escape($search_month)  : "";
$filter .= (!empty($search_mat) && $search_mat != -1) ? " AND vaisc =". $db->escape($search_mat)  : "";
$filter .= ' AND da = '. $daId;

$getAppelOffreList = $aparielcir->fetchAll($sortorder, $sortfield, $conf->liste_limit + 1, $offset, $filter);

// Si erreur SQL
if ($getAppelOffreList == '-1') {
    print_fiche_titre($langs->trans('listDem'), '', 'title_project.png');

    dol_print_error($db, $langs->trans('Error').' '.$aparielcir->error);
    exit();
}
if ($id > 0) {
	$aparielcir->fetch($id);
	llxHeader('', $langs->trans('aparielcir'));
	$head = medical_prepare_head($daId);
	print_fiche_titre($langs->trans('aparielcir'));

	dol_fiche_head($head, 'aparielres', $langs->trans(""), 0, '');
	$head2 = medical_apariel_prepare_head($daId);
		//print_fiche_titre($langs->trans('aparielgu'));

	dol_fiche_head($head2, 'aparielcir', $langs->trans("aparielcir"), 0, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	print '<table class="border" width="100%">';
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("coeur").'</td>';
	print '<td colspan="2">'. $aparielcir->coeur .'</td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("vaisc").'</td>';
	print '<td colspan="2">'. $aparielcir->vaisc .'</td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("varicls").'</td>';
	print '<td colspan="2">'. $aparielcir->varic .' </td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("ta").'</td>';
	print '<td colspan="2">'. $aparielcir->ta .' </td></tr>';
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("cong").'</td>';
	print '<td colspan="2">'. $aparielcir->cong .' </td></tr>';
	
print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("egg").'</td>';
	print '<td colspan="2">'. $aparielcir->egg .' </td></tr>';
	
print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("otherex").'</td>';
	print '<td colspan="2">'. $aparielcir->otherex .' </td></tr>';
	


	print '</table>';

	print '<br>';
	print '<div style="float: right; margin-top: 8px;">';
	print '<a href="./index.php?action=delete&id='. $id .'&daId='.$daId.'" class="butAction">'.$langs->trans('DeletTransp').'</a>';
	print '<a href="./card.php?action=edit&id='. $id .'&daId='.$daId.'" class="butAction">'.$langs->trans('Modify').'</a>';
	print '</div>';

	if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id.'&daId='.$daId, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
    }
}
else {

$morejs  = array("/grh/js/aparielcir.js");
llxHeader('', $langs->trans('aparielcir'),'','','','',$morejs);
//$listaparielcirts = $aparielcir->findAll();

/*if(!$listaparielcirts){
	echo 'aucun resulta';
	die();
}*/

	$head = medical_prepare_head($daId);
		print_fiche_titre($langs->trans('aparielcir'));

	dol_fiche_head($head, 'aparielres', $langs->trans(""), 0, '');
		$head2 = medical_apariel_prepare_head($daId);
		//print_fiche_titre($langs->trans('aparielgu'));

	dol_fiche_head($head2, 'aparielcir', $langs->trans("aparielcir"), 0, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	// Lines

	if (count($aparielcir->rows)) {
		//$createdBy = new User($db);
		foreach($aparielcir->rows as $line) {
			$var = !$var;
			$id 			= $line->id;
			$datec          = dol_print_date($line->datec,'day');
			$coeur         = $line->coeur;
		  	$vaisc        = $line->vaisc;
		  	$varic         = $line->varic;
		  	$ta         = $line->ta;
		  	$cong          = $line->cong;
			$egg          = $line->egg;
			$otherex          = $line->otherex;

			print '<table class="border" width="100%">';

			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("coeur").'</td>';
			print '<td colspan="2">'. $coeur .'</td></tr>';

			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("vaisc").'</td>';
			print '<td colspan="2">'. $vaisc.'</td></tr>';

			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("varicls").'</td>';
			print '<td colspan="2">'. $varic .' </td></tr>';

			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("ta").'</td>';
			print '<td colspan="2">'. $ta .' </td></tr>';
			
			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("cong").'</td>';
			print '<td colspan="2">'. $cong .' </td></tr>';
			
			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("egg").'</td>';
			print '<td colspan="2">'. $egg .' </td></tr>';	
				print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("otherex").'</td>';
				print '<td colspan="2">'. $otherex .' </td></tr>';	
		
			
			print '</table>';

			 print '<br>';
			print '<div style="float: right; margin-top: 8px;">';
			print '<a href="./index.php?action=delete&id='. $id .'&daId='.$daId.'" class="butAction">'.$langs->trans('DeletTransp').'</a>';
			print '<a href="./card.php?action=edit&id='. $id .'&daId='.$daId.'" class="butAction">'.$langs->trans('Modify').'</a>';
			print '</div>';
		}
	} else {
		print '<br>';
		print '<div style="float: right; margin-top: 8px;">';
		print '<a href="./card.php?action=add&daId='.$daId.'" class="butAction">'.$langs->trans('Addaparielcir').'</a>';
		print '</div>';

	   
	}

	
	


		


}

llxFooter();

$db->close();


?>