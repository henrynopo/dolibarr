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
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
dol_include_once('/grh/medical/class/medical.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/lib/medical.lib.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/grh/ref/class/ref.class.php');
dol_include_once('/grh/medical/exmcontrl/class/exmcontrl.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

$langs->load('grh@grh');
$exmcontrl 	= new exmcontrl($db);
$medical 	= new medical($db);
$ref 	  	= new ref($db);
$form 		= new Form($db);
$userm 		= new User($db);

$var = true;
$filter = '';

// $status_array = ['1'=>'Actif','2'=>'Non Actif'];
$status_array = ['1'=>$langs->trans("Enabled"),'2'=>$langs->trans("Disabled")];
// $apte_array = ['1'=>'Apte','2'=>'Inapte'];
$apte_array = ['1'=>$langs->trans("apte"),'2'=>$langs->trans("Inapte")];

$id 	  	= GETPOST('id','int');
$action   	= GETPOST('action','alpha');
$sortfield 	= GETPOST("sortfield",'alpha');
$sortorder 	= GETPOST("sortorder",'alpha');
if (! $sortfield) $sortfield = "rowid";
if (! $sortorder) $sortorder = "DESC";

//__________________Med : 09/11/2016______________________ 
// $month_create    		= GETPOST('month_create');
// $year_create     		= GETPOST('year_create');
// $month_close    		= GETPOST('month_close');
// $year_close    			= GETPOST('year_close');

$search_year_create     = GETPOST('search_year_create');
$search_month_create    = GETPOST('search_month_create');

$search_year_close     	= GETPOST('search_year_close');
$search_month_close     = GETPOST('search_month_close');
//________________________________________________________

//$etat_year      		= GETPOST('etat_year');
$search_dated      		= GETPOST('search_dated');
$search_datef      		= GETPOST('search_datef');
$search_apte      		= GETPOST('search_apte');
$search_user      		= GETPOST('search_user');
$search_status      	= GETPOST('search_status');
$search_mat      		= GETPOST('search_mat');

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
//-----------------Med : 09/11------------------------
// $month_create 		= "";
// $year_create 		= "";
// $month_close			= "";
// $year_close			= "";
$search_year_create		= '';
$search_month_create    = '';
$search_year_close		= '';
$search_month_close    	= '';
//----------------------------------------------------
$search_apte      		= '';
$search_dated      		= '';
$search_datef      		= '';
$search_user      		= '';
$search_status      	= '';
$search_mat      		= '';
}

if (isset($search_dated) && !empty($search_dated)) {
	list($sd, $sm, $sy)  = explode("/", $search_dated);
	if (isset($search_datef) && !empty($search_datef)) {
	list($ed, $em, $ey)  = explode("/", $search_datef);
	$filter .=  " AND datec BETWEEN '". $db->escape($sy.'-'.$sm.'-'.$sd) ."' AND '". $db->escape($ey.'-'.$em.'-'.$ed) ."'";
	}
}


if(!empty($id)){
    $object = new medical($db);
    $object->fetch($id);
    if (!($object->id > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 


$filter .= (!empty($search_user) && $search_user != -1) ? " AND fk_user =". $db->escape($search_user)  : "";

/*$filter .= (!empty($etat_year) && $etat_year != -1) ? " AND YEAR(datec) =". $db->escape($etat_year)  : " AND YEAR(datec) =". date("Y");

if (empty($etat_year))
  $etat_year = date("Y");*/

$filter .= (!empty($search_status) && $search_status != -1) ? " AND status =". $db->escape($search_status)  : "";

if(empty($search_status))
$filter .= " AND status = 1";

//----------------- Med : 09/11 ------------------------

// // DATE CREATE
$filter .= (!empty($search_year_create) && $search_year_create != -1) ? " AND YEAR(datec) =". $db->escape($search_year_create)  : "";

$filter .= (!empty($search_month_create) && $search_month_create != -1) ? " AND MONTH(datec) =". $db->escape($search_month_create)  : "";


// // DATE CLOSE
$filter .= (!empty($search_year_close) && $search_year_close != -1) ? " AND YEAR(datef) =". $db->escape($search_year_close)  : "";

$filter .= (!empty($search_month_close) && $search_month_close != -1) ? " AND MONTH(datef) =". $db->escape($search_month_close)  : "";

//-------------------------------------------------------

// echo "<br><br><br>".$filter;
$getAppelOffreList = $medical->fetchAll($sortorder, $sortfield, $conf->liste_limit + 1, $offset, $filter);

if (!empty($getAppelOffreList)  && $action == "xsl" ) {
$filename="etat_medical.xls";
      require_once dol_buildpath('/grh/medical/tpl/medical_xsl.php');
 die();
 
}

if (!empty($id) && $action == "pdf") {
		$medical->fetch($id);
		$userm->fetch($medical->fk_user);

		//ob_clean();
	require_once dol_buildpath('/grh/medical/pdf/pdf.lib.php');
    $pdf->SetFont('times', '', 12, '', true);
    $pdf->AddPage();

	
	$today 			= date('d/m/Y');
	// require template
	require_once dol_buildpath('/grh/medical/tpl/medical.tpl.php');
    /*echo($html);
    die();*/
	//$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
    $pdf->writeHTML($html, true, false, true, false, '');

   //
   ob_end_clean();
	 $pdf->Output('medical.pdf', 'I');
	
	die();
}

// Si erreur SQL
if ($getAppelOffreList == '-1') {
    print_fiche_titre($langs->trans('listMedc'));

    dol_print_error($db, $langs->trans('Error').' '.$medical->error);
    exit();
}
//-----------------Med : 09/11------------------------
// $formother 	= new FormOther($db);
//----------------------------------------------------
if ($id > 0) {
	$medical->fetch($id);
	llxHeader('', $langs->trans('medical'));
	print_fiche_titre($langs->trans("medical"));
	$head = medical_prepare_head($id);
	dol_fiche_head($head, 'medical', $langs->trans("medical"), 1, '');

	print '<table class="border" width="100%">';
	// Ref
	print '<tr><td width="20%" class="">'.$langs->trans("Ref").'</td>';
	print '<td colspan="2">'. $medical->id .'</td></tr>';
	// Classification
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("User").'</td>';
	$userm->fetch($medical->fk_user);
	print '<td colspan="2">'.$userm->getNomUrl(1) .' </td></tr>';
	$extrafields = new ExtraFields($db);
	$extrafields->fetch_name_optionals_label($userm->table_element);

	$situation_fa = $userm->situation_familiale;
	$situation_fa = $extrafields->showOutputField('situation_familiale',$situation_fa);

	//-----------------Med : 09/11------------------------
	print '<tr><td width="20%" class="">'.$langs->trans("Date_création_du_dossier").'</td>';
	print '<td colspan="2">'. dol_print_date($medical->datec,'day') .' </td></tr>';
	//----------------------------------------------------
	print '<tr><td width="20%" class="">'.$langs->trans("poste").'</td>';
	print '<td colspan="2">'.$userm->job .' </td></tr>';
	
	// print '<tr><td width="20%" class="">'.$langs->trans("situationfam").'</td>';
	// print '<td colspan="2">'.$situation_fa .' </td></tr>';
	print '<tr><td width="20%" class="">'.$langs->trans("daten").'</td>';
	print '<td colspan="2">'. dol_print_date($medical->daten,'day') .' </td></tr>';
	
	print '<tr><td width="20%" class="">'.$langs->trans("adress").'</td>';
	print '<td colspan="2">'. $medical->adress .' </td></tr>';
	
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("Status").'</td>';
	print '<td colspan="2">'. $status_array[$medical->status] .' </td></tr>';
	//-----------------Med : 09/11------------------------
	if ($medical->status == 2) {
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("Date fin du dossier").'</td>';
	print '<td colspan="2">'. dol_print_date($medical->datef,'day') .' </td></tr>';
	}
	//----------------------------------------------------
	
	print '<tr><td width="20%" class="fieldrequired">'.$langs->trans("risques").'</td>';
	print '<td colspan="2">'. $medical->risques .' </td></tr>';

	print '</table>';

	print '<br>';
	print '<div style="text-align: right; margin-top: 8px;">';
							//if ($userm->id && $userm->rights->grh->write_service)	
		print '<a href="./card.php?action=edit&id='. $id .'" class="butAction">'.$langs->trans('Modify').'</a>';
		print '<a class="butActionBTNC butActionDelete" href="./index.php?action=delete&id='. $id .'" >'.$langs->trans('Supprimer').'</a>';
	print '<a href="./index.php?mid='. $mid .'" class="butAction">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
                }
}
else {

$morejs  = array("/grh/js/medical.js");
llxHeader('', $langs->trans('medical'),'','','','',$morejs);
print_fiche_titre($langs->trans("medical"));
//$listmedicalts = $medical->findAll();

/*if(!$listmedicalts){
	echo 'aucun resulta';
	die();
}*/

	// dol_fiche_head('', 'medical', $langs->trans("listMedc"), 1, '');
	// print '<table align="right" class="noborder" style="width:30%;">';
	// print "<tr class=\"liste_titre\">";
	// print "<th align=\"center\" class=\"liste_titre\"> Référence Actuelle </th>";
	// print "<th align=\"center\" class=\"liste_titre\"> </th>";
	// print "</tr>";
	// print "<tr>";
	// print '<td align="center">'.$ref->get_last_ref(3).'</td>'; 
	// print '<td align="center">';
	// print '<a href="../ref/index.php" >'.$langs->trans('Modify').'</a></td>';
	// print "</tr>";
	// print '</table><br/><br/><br/><br/><br/><br/>';
	print '<div style="float: left; margin: 8px;">';
	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	/*print 'Liste dossiers médicaux de l\'année :'.$form->selectarray('etat_year', $medical->getYears(), (($etat_year) ? $etat_year : date("Y")), 0, 0, 0);*/
	print $langs->trans("Liste_dossiers_médicaux").': '.$langs->trans("from").'<input autocomplete="off" class="flat datepicker" type="text" size="14" name="search_dated" value="'.$search_dated.'">';
	// End Date
	print $langs->trans("to").'<input autocomplete="off" class="flat datepicker" type="text" size="14" name="search_datef" value="'.$search_datef.'">';
	print '<input type="submit" name="submit" class="butAction" value="'.$langs->trans("Validate").'">';
	print '</form>'."\n";
	print '</div>';
	print '<div style="float: right; margin: 8px;">';
	print '<a href="./index.php?action=xsl&search_dated='.$search_dated.'&search_datef='.$search_datef.'&search_user='.$search_user.'&search_status='.$search_status.'&search_year_create='.$search_year_create.'&search_month_create='.$search_month_create.'&search_year_close='.$search_year_close.'&search_month_close='.$search_month_close.'" class="butAction">'.$langs->trans('Generate_EXCEL').'</a>';
	print '<a href="./card.php?action=add" class="butAction">'.$langs->trans('Addmedical').'</a>';
	print '</div>';

	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<input name="id" value="'. $id .'" type="hidden" />';
	print '<table class="noborder" style="min-width:100%;width:auto;overflow-x: scroll;">';
	print "<tr class=\"liste_titre\">";
	print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"], "rowid", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("User"),$_SERVER["PHP_SELF"], "fk_user", '', '', 'align="center"', $sortfield, $sortorder);
	//-----------------Med : 09/11------------------------
	print_liste_field_titre($langs->trans("Date"),$_SERVER["PHP_SELF"], "datec", '', '', 'align="center"', $sortfield, $sortorder);
	if ($search_status == 2) {
	print_liste_field_titre($langs->trans("DateEnd"),$_SERVER["PHP_SELF"], "datef", '', '', 'align="center"', $sortfield, $sortorder);
	}
	//----------------------------------------------------
	print_liste_field_titre($langs->trans("poste"),$_SERVER["PHP_SELF"], "rowid", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"], "status", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("apte"),$_SERVER["PHP_SELF"], "rowid", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Dernier_Certificat_Médical"),$_SERVER["PHP_SELF"],"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
	print "</tr>\n";

// FILTRES
	print '<tr class="liste_titre">';

	print '<td class="liste_titre"  align="center">';
	print '</td>';

	print '<td align="center" >';
	print $form->select_users((($search_user) ? $search_user : null),"search_user",1);
    print '</td>';
    //-----------------Med : 09/11------------------------
    // DATE CREATE
	print '<td class="liste_titre" colspan="1" align="center">';
	print $form->selectarray('search_year_create', $medical->getYears(), (($search_year_create) ? $search_year_create : null), 1, 0, 0);
    print $form->selectarray('search_month_create', (($search_year_create) ? $medical->getMonths($search_year_create) : null), (($search_month_create) ? $search_month_create : null), 1, 0, 0);
    print '</td>';

	// DATE CLOSE
	if ($search_status == 2) {
	print '<td class="liste_titre" colspan="1" align="center">';
	print $form->selectarray('search_year_close', $medical->getYearsf(), (($search_year_close) ? $search_year_close : null), 1, 0, 0);
    print $form->selectarray('search_month_close', (($search_year_close) ? $medical->getMonthsf($search_year_close) : null), (($search_month_close) ? $search_month_close : null), 1, 1, 0);
    print '</td>';
    print '<td class="liste_titre" colspan="1" align="center">';
	print '</td>';
	}else{
	print '<td class="liste_titre" colspan="1" align="center">';
	print '</td>';
	}
	//----------------------------------------------------

	print '<td class="liste_titre" colspan="1" align="center">';
    print $form->selectarray('search_status', $status_array, (($search_status) ? $search_status : 1), 1, 0, 0);
	print '</td>';

	print '<td class="liste_titre" colspan="1" align="center">';
    print $form->selectarray('search_apte', $apte_array, (($search_apte!=-1) ? $search_apte : null), 1, 0, 0);
	print '</td>';



    print '<td align="right" style="white-space:nowrap;">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print '</td>';

	print "</tr>\n";

	// Lines
	if (count($medical->rows)) {
		//$createdBy = new userm($db);
		foreach($medical->rows as $line) {
			$var = !$var;
			$id 			= $line->id;
			$datec          = dol_print_date($line->datec,'day');
			$datef          = dol_print_date($line->datef,'day');
			$fk_user         = $line->fk_user;
		  	$status         = $line->status;
		  	$risques          = $line->risques;
		  	$apte = $exmcontrl->getAptebyDI($line->id);
		  	if($search_apte!=='' && $search_apte!=-1 && $search_apte!==$apte)
		  		continue;
		  	$userm->fetch($fk_user);
			print '<tr '.$bc[$var].'>';
			print '<td align="center" style ="white-space: nowrap;">';
				print $medical->getNomUrl(1,  $line->id, $id);
			print '</td>';
			 
			print '<td align="center">'.$userm->getNomUrl(1).'</td>';
			//----------------- Med : 08/11 ----------------------
			print '<td align="center">'.$datec.'</td>';
			if ($search_status == 2) {
			print '<td align="center">'.$datef.'</td>';
			}
			//----------------------------------------------------
			print '<td align="center">'.$userm->job.'</td>';
			print '<td align="center">'.$status_array[$status].'</td>';
			if($apte != -1)
				print '<td align="center">'.$apte_array[$apte].'</td>';
			else
				print '<td align="center"></td>';
			
			print '<td align="center"><div style="margin-top: 8px;">';
									//if ($userm->id && $userm->rights->grh->delete_service)	
			$last = "";
			$dire = $conf->grh->dir_output.'/certificatm/'.$id;

			if (file_exists($dire)) {
                $images = scandir($dire);
                foreach ($images as $img) {
                    if (!in_array($img,array(".",".."))) 
                    {
                    	$last = $img;
                		$title = explode('_nc_', $img);
                		$title = $title[1];
                	}
                }
            }
            if ($last){
            	
            	$ext = explode(".", $img);
                $ext = $ext[count($ext) - 1];
				$minifile = getImageFileNameForSize($last, '');  
	            $dt_files = getAdvancedPreviewUrl('grh', 'certificatm/'.$id.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));
				$url = ($dt_files["url"] ? $dt_files["url"] : DOL_URL_ROOT.'/document.php?modulepart=grh&file=certificatm/'.$id.'/'.$minifile);
				if($ext == 'pdf'){
					print '<a href="'.$dt_files['url'].'" target="'.$dt_files['target'].'" class="'.$dt_files['css'].'" mime="'.$dt_files['mime'].'" title="'.$title.'"><i class="fa fa-paperclip"></i></a>';
				}else{
					print '<a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=certificatm/'.$id.'/'.$minifile.'" target="'.$dt_files['target'].'" class="'.$dt_files['css'].'" mime="'.$dt_files['mime'].'" title="'.$title.'"><i class="fa fa-paperclip"></i></a>';
				}
            }


			print '</tr>'."\n";
			
		}
	} else {
		print '<tr>';
	    print '<td align="center" colspan="9" '.$bc[false].'">'.$langs->trans('NoneResult').'</td>';
	    print '</tr>';
	}

	print '</table>';
	print '</form>';


		print '<br>';
		print '<div style="float: right; margin-top: 8px;">';
						//if ($userm->id && $userm->rights->grh->write_service)	
		// print '<a href="./card.php?action=add" class="butAction">'.$langs->trans('Addmedical').'</a>';
		print '</div>';


}

llxFooter();

$db->close();


?>