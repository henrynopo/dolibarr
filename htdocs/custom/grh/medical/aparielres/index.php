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
dol_include_once('/grh/medical/aparielres/class/aparielres.class.php');
dol_include_once('/grh/lib/medical.lib.php');
dol_include_once('/grh/medical/class/medical.class.php');
$medical 	  = new medical($db);


$aparielres 	  = new aparielres($db);
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
$filter .= (!empty($search_mat) && $search_mat != -1) ? " AND examenr =". $db->escape($search_mat)  : "";
$filter .= ' AND da = '. $daId;

$getAppelOffreList = $aparielres->fetchAll($sortorder, $sortfield, $conf->liste_limit + 1, $offset, $filter);

// Si erreur SQL
if ($getAppelOffreList == '-1') {
    print_fiche_titre($langs->trans('listDem'), '', 'title_project.png');

    dol_print_error($db, $langs->trans('Error').' '.$aparielres->error);
    exit();
}
if ($id > 0) {
	$aparielres->fetch($id);
	llxHeader('', $langs->trans('aparielres'));
	$head = medical_prepare_head($daId);
	print_fiche_titre($langs->trans('aparielres'));

	dol_fiche_head($head, 'aparielres', $langs->trans(""), 0, '');
	$head2 = medical_apariel_prepare_head($daId);
		//print_fiche_titre($langs->trans('aparielres'));

	dol_fiche_head($head2, 'aparielres', $langs->trans("aparielres"), 0, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	print '<table class="border" width="100%">';
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("examenc").'</td>';
	print '<td colspan="2">'. $aparielres->examenc .'</td></tr>';

	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("examenr").'</td>';
	print '<td colspan="2">'. $aparielres->examenr .'</td></tr>';

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

$morejs  = array("/grh/js/aparielres.js");
llxHeader('', $langs->trans('aparielres'),'','','','',$morejs);
//$listaparielrests = $aparielres->findAll();

/*if(!$listaparielrests){
	echo 'aucun resulta';
	die();
}*/

	$head = medical_prepare_head($daId);
		print_fiche_titre($langs->trans('aparielres'));

	dol_fiche_head($head, 'aparielres', $langs->trans(""), 0, '');

	$head2 = medical_apariel_prepare_head($daId);
		//print_fiche_titre($langs->trans('aparielres'));

	dol_fiche_head($head2, 'aparielres', $langs->trans("aparielres"), 0, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	// Lines
	if (count($aparielres->rows)) {
		//$createdBy = new User($db);
		foreach($aparielres->rows as $line) {
			$var = !$var;
			$id 			= $line->id;
			$datec          = dol_print_date($line->datec,'day');
			$examenc         = $line->examenc;
		  	$examenr        = $line->examenr;
		  	$accidents         = $line->accidents;
		  	$malad_pro         = $line->malad_pro;
		  	$habitud          = $line->habitud;

			print '<table class="border" width="100%">';

			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("examenc").'</td>';
			print '<td colspan="2">'. $examenc .'</td></tr>';

			print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("examenr").'</td>';
			print '<td colspan="2">'. $examenr.'</td></tr>';

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
		print '<a href="./card.php?action=add&daId='.$daId.'" class="butAction">'.$langs->trans('Addaparielres').'</a>';
		print '</div>';

	   
	}

	
	


		


}

llxFooter();

$db->close();


?>