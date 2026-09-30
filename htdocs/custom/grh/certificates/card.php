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
 *	    \file       htdocs/grh/certificates/card.php
 *      \ingroup    certificates
 *		\brief      Page of certificates
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

$certificates 	= new Certificates($db);
$userstatic 	= new User($db);
$userben 		= new User($db);
$form 			= new Form($db);
$user_info  = new utilisateur_info($db);
/**
 * Actions
 */

if (isset($_POST["cancel"]) && $_POST["cancel"] == $langs->trans("Cancel")) {
	header("Location: ./list.php?leftmenu=hrm");
	exit;
}

if ($id && $action == "pdf") {
	require_once dol_buildpath('/grh/certificates/pdf/pdf.lib.php');
    $pdf->SetFont('times', '', 15, '', true);
    $pdf->AddPage();

	$certificates->fetch($id);
	$userstatic->fetch($certificates->fk_user_appro);
	$directorName = strtoupper($userstatic->lastname) . ' '. ucfirst($userstatic->firstname);
    // $societe = ($certificates->entity == 1 ? ' &laquo; <strong>Nextconcept</strong> &raquo;, '  : ' &laquo; <strong>NAFET</strong> &raquo;, ');
	$societe = ' &laquo; <strong>'.$conf->global->MAIN_INFO_SOCIETE_NOM.'</strong> &raquo; '.$conf->global->MAIN_INFO_SOCIETE_ADDRESS.', ';
    // $city = ($certificates->entity == 1 ? 'Lâayoune' : 'Agadir');
	$city = $conf->global->MAIN_INFO_SOCIETE_TOWN;
	
	$userben->fetch($certificates->fk_user_ben);
	$employeeName 	= strtoupper($userben->lastname) . ' '. ucfirst($userben->firstname);
	$employeeJob 	= $userben->job ? ucwords($userben->job) : '';
    $employeeSalary1 = price2num($userben->salary);
    $employeeSalary = price2num($userben->salary);
    if ($userben->salary) {
        $employeeSalary =  number_format($employeeSalary,2,","," ");
    }
    $exp = explode(".",$employeeSalary1);
    if ($userben->salary) {
        if(substr($exp[1], 1, 1) == "0"){
            $employeeSalary1 = number_format($employeeSalary1,1,".","");
        }else{
            $employeeSalary1 = number_format($employeeSalary1,2,".","");
        }
    }

    $NF = new NumberFormatter($local, NumberFormatter::SPELLOUT);

    $explode = explode(".",$employeeSalary1);
    $tot_character1 = ucfirst($NF->format($explode[0]));
    $had =$explode[1]; 
    if (strlen((string)$explode[1]) == 1 && $explode[1] < 9) {
        $had = $explode[1]*10;
    }
    $tot_character2 = $NF->format($had);

    $string = $tot_character1." <b>".$conf->currency."</b>";

    if ($explode[1] > 0){
        $zero2 = "";
        $and = "et";
        $zero = "zéro-";
        $zero = (substr($explode[1], 0, 1) == "0") ? $zero : "";
        if (!empty($three)) {
            $zero2 = (substr($explode[1], 1, 1) == "0") ? $zero : "";
        }
        $tot_character2 = $zero.$zero2."".$tot_character2;
        $sub_unit = ".";
        $string .= " ".$and." ".$tot_character2."<b>".$sub_unit."</b>";
    }

    if ($userben->salary)
        $employeeSalaryLetters = $string;
    else
       $employeeSalaryLetters = "";




	$today 			= date('d/m/Y');
    $u = $user_info->fetch($certificates->fk_user_ben);
    $employeeCIN        = $u->cin;
    $employeeEstab      = $u->etablissement;
    $employeeEstabOpt   = $u->etablissement_opt;
	// require template
	require_once dol_buildpath('/grh/certificates/tpl/salary_certificat.tpl.php');

	$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
	$pdf->Output('salary_certificat-'.$userben->lastname.'-'.$userben->firstname.'.pdf', 'I');
	die();
}

if ($action == "add") {
	if (empty($_POST['fk_user_appro']) || $_POST['fk_user_appro'] == -1) {
		header("Location: card.php?action=create&error=user_appro");
	    exit;
	}

	if (!isset($_POST['fk_user_ben']) || empty($_POST['fk_user_ben']) || $_POST['fk_user_ben'] == -1) {
		header('Location: card.php?action=create&error=user_ben');
	    exit;
	}

	// if (!isset($_POST['entity']) || empty($_POST['entity'])) {
	// 	header('Location: card.php?action=create&error=company');
	//     exit;
	// }

	$fk_user_appro = (int) trim($_POST['fk_user_appro']);
	$fk_user_ben   = (int) trim($_POST['fk_user_ben']);
	$entity 	   = (int) trim($_POST['entity']);

	$checkDuplication = $certificates->checkCertificateDuplication($fk_user_appro, $fk_user_ben, 1, $entity);

	if ($checkDuplication == 1 || $checkDuplication == -1) {
		header("Location: card.php?action=create&error=alreadyExist");
	    exit;
	}

	$certificates->fk_user_appro 	= $fk_user_appro;
	$certificates->fk_user_ben 	= $fk_user_ben;
	$certificates->entity 		= $entity;
	$certificates->cert_type 		= 1;

	$insert = $certificates->create($user);

	if ($insert > 0) {
		$db->commit();

    	header('Location: list.php');
        exit;
    }
    else {
    	$db->rollback();

        // Otherwise we display the request form with the SQL error message
        header('Location: card.php?action=create&error=SQL_Create&msg='.$certificates->error);
        exit;
    }
    $action = 'create';
}

if ($action == "update") {}

llxHeader(array(), $langs->trans('ListOfcertificatesOfSalaries'),'','','','','');
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

	// print_fiche_titre($langs->trans("DemandSalaryCert"),'', 'title_accountancy.png');
    print_fiche_titre($langs->trans('DemandSalaryCert'));
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

llxFooter();

$db->close();