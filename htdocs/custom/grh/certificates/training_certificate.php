<?php
/* Copyright (C) 2015 Yassine Belkaid   <y.belkaid@nextconcept.ma>
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
 *	    \file       htdocs/compta/grh/certificates/training_certificate.php
 *      \ingroup    certificates
 *		\brief      Page of training certificates
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
dol_include_once('/grh/certificates/common.inc.php');
dol_include_once('/grh/certificates/class/certificates.class.php');
dol_include_once('/grh/utilisateur_info/class/utilisateur_info.class.php');
$langs->load("users");
$langs->load("salaries");

$id 	= GETPOST("id",'int');
$action = GETPOST('action');

// Security check
$socid = GETPOST("socid","int");
if ($user->societe_id) $socid=$user->societe_id;
// $result = restrictedArea($user, 'certificates', '', '', '');

// order
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
// filter
$search_ref 	 	= GETPOST("search_ref",'int');
$search_user_appro 	= GETPOST("search_user_appro",'int');
$search_user_ben 	= GETPOST("search_user_ben",'int');
$search_status 		= GETPOST("search_status",'int');

// Both test are required to be compatible with all browsers
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$search_ref 	 	= "";
	$search_user_appro 	= "";
	$search_user_ben 	= "";
	$search_status 		= "";
}

if (!$sortfield) $sortfield = "t.rowid";
if (!$sortorder) $sortorder = "DESC";
$page 	   = GETPOST("page",'int');
if ($page == -1 || empty($page)) { $page = 0; }

$limit 		= $conf->liste_limit;
$offset 	= $limit * $page;
$pageprev 	= $page - 1;
$pagenext 	= $page + 1;
$param 		= '';
$filter 	= '';

// Ref
if(!empty($search_ref) && $search_ref > 0) {
    $filter.= " AND t.rowid = ". (int) $db->escape($search_ref)."\n";
}

// Appro
if(!empty($search_user_appro) && $search_user_appro != -1) {
    $filter.= " AND t.fk_user_appro = ". (int) $db->escape($search_user_appro)."\n";
}

// STATUT
if($search_status != '' && $search_status != -1) {
    $filter.= " AND t.status = ". (int) $db->escape($search_status)."\n";
}
// Ben
if(!empty($search_user_ben) && $search_user_ben != -1) {
    $filter.= " AND t.fk_user_ben = ". (int) $db->escape($search_user_ben)."\n";
}

$certificates 	= new Certificates($db);
$userstatic 	= new User($db);
$userben 		= new User($db);
$form 			= new Form($db);
$user_info  = new utilisateur_info($db);

/**
 * Actions
 */

if (isset($_POST["cancel"]) && $_POST["cancel"] == $langs->trans("Cancel")) {
	header("Location: index.php");
	exit;
}

if ($id && $action == "pdf") {
	require_once dol_buildpath('/grh/certificates/pdf/pdf.lib.php');
    $pdf->SetFont('times', '', 15, '', true);
    $pdf->AddPage();
	$certificates->fetch($id);
	$userstatic->fetch($certificates->fk_user_appro);
	$directorName = strtoupper($userstatic->lastname) . ' '. ucfirst($userstatic->firstname);
	$directorCivility= (null !== $userstatic->civility ? strtoupper($userstatic->civility) .'.' : '');
	// $societe = ($certificates->entity == 1 ? ' <strong>NextConcept</strong> '  : ' <strong>NAFET</strong> ');
	$societe = ' « <strong>'.$conf->global->MAIN_INFO_SOCIETE_NOM.'</strong> » '.$conf->global->MAIN_INFO_SOCIETE_ADDRESS.'';
	// $city = ($certificates->entity == 1 ? 'Lâayoune' : 'Agadir');
	$city = $conf->global->MAIN_INFO_SOCIETE_TOWN;

	$userben->fetch($certificates->fk_user_ben);
	$employeeName 		= strtoupper($userben->lastname) . ' '. ucfirst($userben->firstname);
	$employeeJob 		= $userben->job ? ucwords($userben->job) : '';
	$employeeCivility 	= (null !== $userben->civility ? strtoupper($userben->civility) .'.' : '');
	$employeeSalary1 = price2num($userben->salary);
    $employeeSalary = price2num($userben->salary);
	
	$traineeEndDate   	= new DateTime("now");
	$extrafields 		= new ExtraFields($db);
	$extralabels 		= $extrafields->fetch_name_optionals_label($userben->table_element, true);
	$userben->fetch_optionals($userben->id, $extralabels);
	$field 				= $userben->array_options;
	
	$datePeriod = '';
	if (null !== $field['options_nx_date_embauche']) {
		$traineeStartDate = new DateTime($field['options_nx_date_embauche']);

		if ($traineeStartDate->format('Y') == $traineeEndDate->format('Y')) {
			$traineeStartDate = $traineeStartDate->format('d F');
		}
		else {
			$traineeStartDate = $traineeStartDate->format('d F Y');
		}

		$traineeEndDate = $traineeEndDate->format('d F Y');
		$datePeriod 	= $langs->trans('From') .' '. strtolower($traineeStartDate) .' '. $langs->trans('To') .' '. strtolower($traineeEndDate);
	}
	
	// $employeeSalaryLetters = $certificates->convertSalaryToLetters($employeeSalary);
	if ($userben->salary){
		$employeeSalary =  number_format($employeeSalary,2,","," ");
       $employeeSalaryLetters = $certificates->convertSalaryToLetters($employeeSalary1);
	}
    else
	   $employeeSalaryLetters = "";
	$today 			= date('d/m/Y');




	$u = $user_info->fetch($certificates->fk_user_ben);
    $employeeCIN 		= $u->cin;
    $employeeEstab 		= $u->etablissement;
	$employeeEstabOpt 	= $u->etablissement_opt;
	// require template
	require_once dol_buildpath('/grh/certificates/tpl/training_certificat.tpl.php');

	// $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
	$pdf->Output('training_certificate-'.$userben->lastname.'-'.$userben->firstname.'.pdf', 'I');
	die();
}

if ($action == "add") {
	if (empty($_POST['fk_user_appro']) || $_POST['fk_user_appro'] == -1) {
		header("Location: training_certificate.php?action=create&error=user_appro");
	    exit;
	}

	if (!isset($_POST['fk_user_ben']) || empty($_POST['fk_user_ben']) || $_POST['fk_user_ben'] == -1) {
		header('Location: training_certificate.php?action=create&error=user_ben');
	    exit;
	}

	// if (!isset($_POST['entity']) || empty($_POST['entity'])) {
	// 	header('Location: training_certificate.php?action=create&error=company');
	//     exit;
	// }

	$fk_user_appro = (int) trim($_POST['fk_user_appro']);
	$fk_user_ben   = (int) trim($_POST['fk_user_ben']);
	$entity 	   = (int) trim($_POST['entity']);

	$checkDuplication = $certificates->checkCertificateDuplication($fk_user_appro, $fk_user_ben, 3, $entity);

	if ($checkDuplication == 1 || $checkDuplication == -1) {
		header("Location: training_certificate.php?action=create&error=alreadyExist");
	    exit;
	}

	$certificates->fk_user_appro 	= $fk_user_appro;
	$certificates->fk_user_ben 	= $fk_user_ben;
	$certificates->entity 		= $entity;
	$certificates->cert_type 		= 3;

	$insert = $certificates->create($user);

	if ($insert > 0) {
		$db->commit();

    	header('Location: training_certificate.php?action=list');
        exit;
    }
    else {
    	$db->rollback();

        // Otherwise we display the request form with the SQL error message
        header('Location: training_certificate.php?action=create&error=SQL_Create&msg='.$certificates->error);
        exit;
    }
    $action = 'create';
}

if ($action == "update") {}

// Si Validation de la demande
if ($action == 'confirm_valid' && $_GET['confirm'] == 'yes') {
	$certificates->fetch((int) $id);
	$certificates->status = 2;
	$validate = $certificates->updateStatus();

	if ($validate > 0) {
    	header('Location: training_certificate.php?action=list');
        exit;
    }
    else {
        // Otherwise we display the request form with the SQL error message
        header('Location: training_certificate.php?action=list&error=SQL_Create&msg='.$certificates->error);
        exit;
    }
}

llxHeader(array(), $langs->trans('ListOfCertificatesOfTraining'),'','','','','');
$errors = array();

if ($action == "create") {

	if (GETPOST('error')) {
        switch(GETPOST('error')) {
            case 'user_appro' :
                $errors[] = $langs->trans('FieldRequired', $langs->trans("Approving"));
                break;
            case 'user_ben' :
                $errors[] = $langs->trans('FieldRequired', html_entity_decode($langs->trans("Beneficiary")));
                break;
            case 'company' :
                $errors[] = $langs->trans('FieldRequired', $langs->trans("Company"));
                break;
            case 'alreadyExist' :
                $errors[] = $langs->trans('ErrorAlreadyExist');
                break;
            case 'SQL_Create':
	            $errors[] = GETPOST('msg');
                break;
        }

        setEventMessage($errors, 'errors');
    }

	// print_fiche_titre($langs->trans("DemandTrainingCert"),'', 'title_accountancy.png');
	print_fiche_titre($langs->trans('DemandTrainingCert'));
	print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'."\n";
    print '<input type="hidden" name="token" value="'. $_SESSION['token'] .'" />'."\n";
    print '<input type="hidden" name="action" value="add" />'."\n";

    dol_fiche_head();

    	print '<table class="border" width="100%">';

        // Approving
        print '<tr>';
        print '<td class="fieldrequired">'.$langs->trans("Approving").'</td>';
        print '<td>';
        print $form->select_users((GETPOST('fk_user_appro') ? GETPOST('fk_user_appro') : ''), 'fk_user_appro',1,'',0);
        print '</td>';
        print '</tr>';

        // Beneficiary
        print '<tr>';
        print '<td class="fieldrequired">'.$langs->trans("Beneficiary").'</td>';
        print '<td>';
        print $form->select_users((GETPOST('fk_user_ben') ? GETPOST('fk_user_ben') : ''), 'fk_user_ben',1,'',0);
        print '</td>';
        print '</tr>';

        // Societe
        // print '<tr>';
        // print '<td class="fieldrequired">'.$langs->trans("Company").'</td>';
        // print '<td>';
        // $companies = array(1 => 'NextConcept', 2 => 'NAFET');
        // print $form->selectarray('entity', $companies, (GETPOST('entity') ? GETPOST('entity') : ''));
        // print '</td>';
        // print '</tr>';

        print '</table>';
	dol_fiche_end();

	print '<div class="right">';
	print '<input type="submit" class="butAction" value="'.$langs->trans("Save").'">';
	print '</div>';

	print '</form>';
}

if ($action == "edit") {}

if (!$action || $action == 'list' || $action == 'valid') {
	$order = $db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
	$certs = $certificates->fetchByType(3, $filter, $order);
	$url   = $_SERVER["PHP_SELF"];
	$param = 'action=list&leftmenu=hrm';

	// dol_fiche_head(array(), '', $langs->trans("ListOfCertificatesOfTraining"), 0, 'payment');
	print_fiche_titre($langs->trans('ListOfCertificatesOfTraining'));
	print '<form method="GET" action="'.$url.'">';
	print '<input type="hidden" name="action" value="list" />';

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("Ref"),$url,"t.rowid","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Approving"),$url,"t.fk_user_appro","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Beneficiary"),$url,"t.fk_user_ben","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans("Status"),$url,"t.status","",$param,"",$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Downloading'),$url,"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
    print "</tr>\n";

    print '<tr class="liste_titre">';
	// Ref
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="3" name="search_ref" value="'.$search_ref.'">';
	print '</td>';
	
	// Approving
	print '<td class="liste_titre">';
    print $form->select_users($search_user_appro, 'search_user_appro',1,'',0);
	print '</td>';

	// Ben
	print '<td class="liste_titre">';
    print $form->select_users($search_user_ben, 'search_user_ben',1,'',0);
	print '</td>';

	// Status
	print '<td class="liste_titre">';
	$statusList = array(0 => 'Brouillon', 2 => 'Approuvé');
	print $form->selectarray('search_status', $statusList, $search_status, 1);
	print '</td>';

	print '<td class="liste_titre" align="right"><input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print "</td></tr>\n";

	$var = true;

	foreach ($certs as $key => $cert) {
		// approbateur
		$userstatic->fetch($cert->fk_user_appro);

		// beneficiaire
		$userben->fetch($cert->fk_user_ben);

		$var = !$var;
        print "<tr ".$bc[$var].">";
         // Ref
		print "<td>". $cert->rowid ."</td>";
		// Appro
		print "<td>". $userstatic->getNomUrl(1) ."</td>";
		// Ben
		print "<td>". $userben->getNomUrl(1) ."</td>";
		// Status
		print "<td>". $certificates->statusLevel($cert->status) ."</td>";
		
		// PDF
		print "<td align=\"center\">";
						    if ($user->id && $user->rights->grh->certificates->write_stage)
{
			if ($cert->status == 2) {
            	print '<a target="_blank" href="training_certificate.php?action=pdf&id='. $cert->rowid .'"><img src="'. DOL_URL_ROOT .'/theme/common/mime/pdf.png" title="Télécharger l\'attestation de travail" /></a>';
			}

			if ($user->id == $cert->fk_user_appro && $cert->status == 0) {
            	print '<a href="training_certificate.php?id='.$cert->rowid .'&action=valid" class="butAction">'.$langs->trans("Approve").'</a>';
			}
}
		print "</td>";
        print "</tr>\n";

	}

	print '</table>';
	print '</form>';
if ($user->id && $user->rights->grh->certificates->write_stage)

    print '<a href="training_certificate.php?action=create" class="butAction floatright" style="float: right; margin-top: 10px !important;">'.$langs->trans("DemandTrainingCert").'</a>';

	dol_fiche_end();
}

// Si validation de la demande
if ($action == 'valid') {
print $form->formconfirm("training_certificate.php?id=".$id, $langs->trans("TitleValidCT"), $langs->trans("ConfirmValidCT"), "confirm_valid", '', 1, 1);
}


llxFooter();

$db->close();