<?php 
/* Copyright (C) 2016		Yassine Belkaid	<y.belkaid@nextconcept.ma>
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
 *   	\file       marches/list.php
 *		\ingroup    list
 *		\brief      Gestion des marches
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/medical/class/medical.class.php');
dol_include_once('/grh/lib/medical.lib.php');
// Get parameters
$action  = GETPOST('action', 'alpha');
$id 	 = GETPOST('id', 'int');
$request_method = $_SERVER['REQUEST_METHOD'];
$status_array = ['1'=>$langs->trans('Actif'),'2'=>$langs->trans('Non_Actif')];
$langs->load('grh@grh');


if ($user->societe_id > 0) accessforbidden();

$now 	= new DateTime('now');
//-----------------Med : 08/11------------------------
$now       = $now->format('d/m/Y');
//----------------------------------------------------
$error 	= false;


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

if ($action == 'create' && $request_method === 'POST') {
	$medical = new medical($db);
    if (!$error) {
        if (isset($_POST['daten_']) && !empty($_POST['daten_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['daten_']);
           $medical->daten        = $ety.'-'.$etm.'-'.$etd ;
        }
     
        //________________Med : 09/11/2016________________________
        if (isset($_POST['datec_']) && !empty($_POST['datec_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['datec_']);
           $medical->datec        = $ety.'-'.$etm.'-'.$etd ;
        }

        if (isset($_POST['datef_']) && !empty($_POST['datef_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['datef_']);
           $medical->datef        = $ety.'-'.$etm.'-'.$etd ;
        }

        //________________________________________________________
        $medical->fk_user = trim(GETPOST('fk_user_'));
        $medical->status  = trim(GETPOST('status_'));
        $medical->risques = trim(GETPOST('risques_'));
        $medical->adress  = trim(GETPOST('adress_'));
        $medical->entity  = $conf->entity;
		$medicalID = $medical->create();

	    // If no SQL error we redirect to the request card
	    if ($medicalID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php');
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&error=SQL_Create&msg='.$medical->error);
	        exit;
	    }
    }
}

if ($action == 'update' && $request_method === 'POST') {
    // If no right to modify a request
    /*if (!$user->rights->marches->write) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }*/

    $medical_id = (int) GETPOST('medical_id', 'int');

    if (!$medical_id || $medical_id <= 0) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }

    $medical = new medical($db);
    $medical->fetch($medical_id);

   // $canedit = ($user->rights->marches->write || $user->rights->marches->write_all);
    $canedit = true ; 


    // If this is the requestor or has read/write rights
    if ($canedit) {
        $daten ='';
        if (isset($_POST['daten_']) && !empty($_POST['daten_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['daten_']);
           $daten        = $ety.'-'.$etm.'-'.$etd ;
        }
        //-----------------Med : 09/11------------------------
        $datec ='';
        if (isset($_POST['datec_']) && !empty($_POST['datec_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['datec_']);
           $datec        = $ety.'-'.$etm.'-'.$etd ;
        }
        $datef ='';
        if (isset($_POST['datef_']) && !empty($_POST['datef_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['datef_']);
           $datef        = $ety.'-'.$etm.'-'.$etd ;
        }
        //----------------------------------------------------
        $fk_user             = trim(GETPOST('fk_user_'));
        $status             = trim(GETPOST('status_'));
        $adress             = trim(GETPOST('adress_'));
        $risques              = trim(GETPOST('risques_'));
        $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
        $data = array(
            //-----------------Med : 09/11------------------------    
            'datec'           => $datec,
            'datef'           => $datef,
            //----------------------------------------------------
            'daten'           => $daten,
            'fk_user'          => $fk_user,
            'status'          => $status,
            'adress'          => $adress,
            'risques'           => $risques,
            'entity'           => $entity

        );

		// Update
		$getmedicalID = $medical->update($medical_id, $data);

        if ($getmedicalID > 0) {
            header('Location: ./index.php?id='.$medical_id);
            exit;
        } else {
            // Otherwise we display the request form with the SQL error message
            header('Location: ./card.php?id='. $medical_id .'&action=edit&error=SQL_Create&msg='.$medical->error);
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
	$error=0;

	//$db->begin();

	$medical  = new medical($db);
	//$canedit = ($user->rights->marches->write_all || $user->rights->marches->delete);
    $canedit = true;
	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $medical->fetch($id);
		$medical->delete();
	}
	else {
		$error = $langs->trans('ErrorCantDelete');
	}

	if (!$error) {
		//$db->commit();
		header('Location: index.php');
		exit;
	}
	else {
		
        header('Location: index.php?leftmenu=medical');
        exit;
	}
}

/*
 * View
 */

$form         = new Form($db);
$medical      = new medical($db);
$exclus_users = $medical->getUsers();

$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/medical.js","/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/new-medical.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");

llxHeader(array(), $langs->trans('medical'),'','','','',$morejs,$morecss,0,0);

if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create') {
	// Si l'utilisateur n'a pas le droit de créer un marché
    /*if (empty($user->rights->marches->write) || empty($user->rights->marches->write_all)) {
        $errors[]=$langs->trans('CantCreate');
    }
    else {*/
        // Formulaire appel d'offre
        print_fiche_titre($langs->trans('Addmedical'));

        // Si il y a une erreur
        if (GETPOST('error')) {
            switch(GETPOST('error')) {
                case 'nobudget' :
                    $errors[] = $langs->trans('errorNoBudget');
                    break;
                case 'SQL_Create' :
                    $errors[] = $langs->trans('ErrorSQLCreateCP').' <b>'.htmlentities($_GET['msg']).'</b>';
                    break;
                case 'CantCreate' :
                    $errors[] = $langs->trans('CantCreateCP');
                    break;
                case 'nouser' :
                    $errors[] = $langs->trans('errorNoUser');
                    break;
                case 'nodatedebut' :
                    $errors[] = $langs->trans('NoDateDebut');
                    break;
                case 'nobank' :
                    $errors[] = $langs->trans('errorNoBank');
                    break;
                case 'noqc' :
                    $errors[] = $langs->trans('errorNoQc');
                    break;
                case 'alreadyExists' :
                    $errors[] = $langs->trans('alreadyExistAO');
                    break;
                case 'noref' :
                    $errors[] = $langs->trans('errorNoRef');
                    break;
            }

	        setEventMessage($errors, 'errors');
        }

        print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";

        print '<table class="border" width="100%">';
        print '<tbody>';

        print '<tr><td class="fieldrequired" width="20%">'.$langs->trans("fk_user").'</td><td>';
        print $form->select_users('',"fk_user_",0,$exclus_users);
        print '</td></tr>';
        //__________________Med : 08/11/2016______________________

        print '<tr><td >'.$langs->trans("Date_création_du_dossier").'</td><td>';
        print '<input id="datecnow" type="text" autocomplete="off" class="datepicker22" name="datec_" /> ';
        // $form->select_date('', 'datec_', '', '', '1', "datec", 1, 1,0);
        print '</td></tr>';     
        //________________________________________________________

        print '<tr><td >'.$langs->trans("daten").'</td><td>';
           print '<input type="text" autocomplete="off" class="datepicker22" name="daten_" /> ';
        print '</td></tr>';


        print '<tr><td >'.$langs->trans("adress").'</td><td>';
           print '<input type="text" name="adress_" /> ';
        print '</td></tr>';


        print '<tr><td >'.$langs->trans("Status").'</td><td>';
        print $form->selectarray('status_', $status_array, (GETPOST('status_') ? GETPOST('status_') : '1'), 0, 0, 0);
        print '</td></tr>';

        //__________________Med : 09/11/2016______________________

        print '<tr id="datefdm" style="display:none;"><td >'.$langs->trans("Date fin du dossier").'</td><td>';
        print '<input id="datefnow" type="text" autocomplete="off" class="datepicker22" name="datef_" value="" /> ';
        // print '<input type="hidden" class="datenowhidden" value="" name="datec_" /> ';
        // // $form->select_date('', 'datef_', '', '', '1', "datef", 1, 1,0);
        // $form->select_date('', 'datef_', '', '', '55', "datef", 1, 1);
        print '</td></tr>';     
        //________________________________________________________

        print '<tr><td >'.$langs->trans("risques").'</td><td>';
        print '<textarea  name="risques_" id="risques_txt" ></textarea>';
        print '</td></tr>';



        print '</tbody>';
        print '</table>';

        print '<div class="center">';
        print '<input type="submit" value="'. $langs->trans("CreateMedc") .'" name="bouton" class="butAction">';
        print '&nbsp; &nbsp; ';
        print '<input type="button" value="'. $langs->trans("Cancel") .'" class="butAction" onclick="history.go(-1)">';
        print '</div>';
        print '</form>';
    //}
}
else {
    if ($error) {
        print '<div class="tabBar">';
        print $error;
        print '<br /><br /><input type="button" value="'.$langs->trans("ReturnSC").'" class="butAction" onclick="history.go(-1)" />';
        print '</div>';
    }
    else {
        // Affichage de la fiche d'une medical de congés payés

        if ($id > 0) {
            $medical->fetch($id);

			//$canedit = ($user->rights->marches->delete || $user->rights->marches->write_all);

            // Si il y a une erreur
            if (GETPOST('error')) {
                switch(GETPOST('error')) {
                    case 'datefin' :
                        $errors[] = $langs->transnoentitiesnoconv('ErrorEndDateCP');
                        break;
                    case 'SQL_Create' :
                        $errors[] = $langs->transnoentitiesnoconv('ErrorSQLCreateCP').' '.$_GET['msg'];
                        break;
                    case 'CantCreate' :
                        $errors[] = $langs->transnoentitiesnoconv('CantCreateCP');
                        break;
                    case 'Valideur' :
                        $errors[] = $langs->transnoentitiesnoconv('InvalidValidatorCP');
                        break;
                    case 'nodatedebut' :
                        $errors[] = $langs->transnoentitiesnoconv('NoDateDebut');
                        break;
                    case 'nodatefin' :
                        $errors[] = $langs->transnoentitiesnoconv('NoDateFin');
                        break;
                    case 'DureeHoliday' :
                        $errors[] = $langs->transnoentitiesnoconv('ErrorDureeCP');
                        break;
                    case 'NoMotifRefuse' :
                        $errors[] = $langs->transnoentitiesnoconv('NoMotifRefuseCP');
                        break;
                    case 'mail' :
                        $errors[] = $langs->transnoentitiesnoconv('ErrorMailNotSend')."\n".$_GET['error_content'];
                        break;
                }

	            setEventMessage($errors, 'errors');
            }

            // On vérifie si l'utilisateur à le droit de lire cette medical
           // if ($canedit) {
                if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
                }

                // Si annulation de la medical
                if ($action == 'cancel') {
                    print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleCancelSC"), $langs->trans("ConfirmCancelSC"),"confirm_cancel", '', 1, 1);
                }
                 print_fiche_titre($langs->trans('Modifymedical'));

                // dol_fiche_head('', 'medicals', $langs->trans("medical"), 0, '');

                if ($action == 'edit') {
                    $edit = true;
                    
                    $head = medical_prepare_head($id);
                    dol_fiche_head($head, 'medical', $langs->trans("medical"), 0, '');

                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="medical_id" value="'.$id.'" />'."\n";

                    print '<table class="border" width="100%">';
                    print '<tbody>';


                    list($daten, $datec_time) = explode(" ", dol_print_date($medical->daten,'dayhoursec'));
                    //-----------------Med : 09/11------------------------
    
                    list($datec, $datec_time) = explode(" ", dol_print_date($medical->datec,'dayhoursec'));
                    list($datef, $datec_time) = explode(" ", dol_print_date($medical->datef,'dayhoursec'));

                    //----------------------------------------------------

                    
                    print '<tr><td class="fieldrequired" width="20%">'.$langs->trans("User").'</td><td>';
                    unset($exclus_users[$medical->fk_user]);
                    print $form->select_users($medical->fk_user,"fk_user_",0,$exclus_users);
                    print '</td></tr>';
                    //-----------------Med : 08/11------------------------
                    print '<tr><td >'.$langs->trans("Date_création_du_dossier").'</td><td>';
                    print '<input type="text" autocomplete="off" class="datepicker22" name="datec_" value="'.$datec.'" required /> ';
                    // $form->select_date('', 'datec_', '', '', '', "datec", 1, 1);
                    print '</td></tr>';
                    //----------------------------------------------------

                    print '<tr><td >'.$langs->trans("daten").'</td><td>';
                   print '<input type="text" autocomplete="off" class="datepicker22" name="daten_" value="'.$daten.'" /> ';
                    print '</td></tr>';


                    print '<tr><td >'.$langs->trans("adress").'</td><td>';
                       print '<input type="text" value="'.$medical->adress.'" name="adress_" /> ';
                    print '</td></tr>';

                    print '<tr><td class="fieldrequired">'.$langs->trans("Status").'</td><td class="m_status">';
                     print $form->selectarray('status_', $status_array, $medical->status, 0, 0, 0);
                    print '</td></tr>';

                    //__________________Med : 09/11/2016______________________

                    $displayofdatef = ($medical->status == 2 ? "table-row" : "none");

                    print '<tr id="datefdm" style="display:'.$displayofdatef.';"><td >'.$langs->trans("Date fin du dossier").'</td><td>';
                    print '<input id="m-Date fin du dossier" type="text" autocomplete="off" class="datepicker22" name="datef_" value="'.$datef.'" /> ';

                    // $form->select_date('', 'datef_', '', '', $showdatenow, "datef", 1, 1,0);
                    print '</td></tr>';     
                    //________________________________________________________

                    print '<tr><td class="fieldrequired">'.$langs->trans("risques").'</td><td>';
                    print '<textarea  name="risques_" id="risques_txt" >'.$medical->risques.'</textarea>';
                    print '</td></tr>';


                    print '</tbody>';
                    print '</table>';

                    print '<div class="center">';
                    print '<input type="submit" value="'. $langs->trans("Modify") .'" name="bouton" class="butAction">';
                    print '&nbsp; &nbsp; ';
                    print '<input type="button" value="'. $langs->trans("Cancel") .'" class="butAction" onclick="history.go(-1)">';
                    print '</div>';
                    print '</table>';
                }

                dol_fiche_end();

                if (!$edit) {
		            print '<div class="tabsAction">';

                    // Boutons d'actions
                    //if ($canedit) {
                        print '<a href="card.php?id='.$_GET['id'].'&action=edit" class="butAction">'.$langs->trans("Modify").'</a>';
                    //}

                    // If draft
                   // if ($user->rights->marches->delete)	{
                    	print '<a href="card.php?id='.$_GET['id'].'&action=delete" class="butActionDelete">'.$langs->trans("Delete").'</a>';
                   // }

                    print '</div>';
                }

        } else {
                
                print '<div class="tabBar">';
                print $langs->trans('ErrorUserViewSC');
                print '<br /><br /><input type="button" value="'.$langs->trans("ReturnSC").'" class="butAction" onclick="history.go(-1)" />';
                print '</div>';
            }

        /*} else {
            print '<div class="tabBar">';
            print $langs->trans('ErrorIDFicheSC');
            print '<br /><br /><input type="button" value="'.$langs->trans("ReturnSC").'" class="butAction" onclick="history.go(-1)" />';
            print '</div>';
        }*/

    }
}

// End of page
llxFooter();

if (is_object($db)) $db->close();

?>