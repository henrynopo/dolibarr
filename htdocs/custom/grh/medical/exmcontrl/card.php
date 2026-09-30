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
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/medical/exmcontrl/class/exmcontrl.class.php');
dol_include_once('/grh/lib/medical.lib.php');

// Get parameters
$action  = GETPOST('action', 'alpha');
$id 	 = GETPOST('id', 'int');
$daId    = GETPOST('daId', 'int');
$request_method = $_SERVER['REQUEST_METHOD'];

$type_array = ['1'=>$langs->trans('Normale'),'2'=>$langs->trans('Urgence')];
$apte_array = ['1'=>$langs->trans('Yes'),'2'=>$langs->trans('No')];

if ($user->societe_id > 0) accessforbidden();

if(!empty($id) && !empty($daId)){

    $object = new exmcontrl($db);
    $object->fetch($id);
    if (!($object->id > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 

$now 	= new DateTime('now');
$error 	= false;

if ($action == 'create' && $request_method === 'POST') {
	$exmcontrl = new exmcontrl($db);

    if (!$error) {
        if (isset($_POST['dated_']) && !empty($_POST['dated_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['dated_']);
           $exmcontrl->dated        = $ety.'-'.$etm.'-'.$etd ;
        }
        if (isset($_POST['datef_']) && !empty($_POST['datef_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['datef_']);
           $exmcontrl->datef        = $ety.'-'.$etm.'-'.$etd ;
        }
        
        $exmcontrl->observation             = trim(GETPOST('observation_'));
        $exmcontrl->type            = trim(GETPOST('type_'));
        $exmcontrl->apte             = trim(GETPOST('apte_'));
        $exmcontrl->da                = Intval(trim(GETPOST('daId')));
        $exmcontrl->entity          = $conf->entity;
		$exmcontrlID = $exmcontrl->create();

	    // If no SQL error we redirect to the request card
	    if ($exmcontrlID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php'.'?daId='.trim(GETPOST('daId')));
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&daId='.trim(GETPOST('daId')).'&error=SQL_Create&msg='.$exmcontrl->error);
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

    $exmcontrl_id = (int) GETPOST('exmcontrl_id', 'int');

    if (!$exmcontrl_id || $exmcontrl_id <= 0) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }

    $exmcontrl = new exmcontrl($db);
    $exmcontrl->fetch($exmcontrl_id);

   // $canedit = ($user->rights->marches->write || $user->rights->marches->write_all);
    $canedit = true ; 


    // If this is the requestor or has read/write rights
    if ($canedit) {
        $dated ='';
        $datef ='';
        if (isset($_POST['dated_']) && !empty($_POST['dated_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['dated_']);
           $dated        = $ety.'-'.$etm.'-'.$etd ;
        }
        if (isset($_POST['datef_']) && !empty($_POST['datef_'])) {
            list($etd, $etm, $ety) = explode("/", $_POST['datef_']);
           $datef        = $ety.'-'.$etm.'-'.$etd ;
        }
        
        $observation             = trim(GETPOST('observation_'));
        $type            = trim(GETPOST('type_'));
        $apte             = trim(GETPOST('apte_'));
        $da                = Intval(trim(GETPOST('daId')));

        $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
        $data = array(
            'dated'        => $dated,
            'datef'        => $datef,
            'observation'  => $observation,
            'type'         => $type,
            'apte'         => $apte,
            'da'           => $da,
            'entity'       => $entity

        );

		// Update
		$getexmcontrlID = $exmcontrl->update($exmcontrl_id, $data);

        if ($getexmcontrlID > 0) {
            header('Location: ./index.php?id='.$exmcontrl_id.'&daId='.$da);
            exit;
        } else {
            // Otherwise we display the request form with the SQL error message
            header('Location: ./card.php?id='. $exmcontrl_id .'&action=edit&error=SQL_Create&msg='.$exmcontrl->error);
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
	$error=0;

	//$db->begin();

	$exmcontrl  = new exmcontrl($db);
	//$canedit = ($user->rights->marches->write_all || $user->rights->marches->delete);
    $canedit = true;
	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $exmcontrl->fetch($id);
		$exmcontrl->delete();
	}
	else {
		$error = $langs->trans('ErrorCantDelete');
	}

	if (!$error) {
		//$db->commit();
		header('Location: index.php?daId='.$daId);
		exit;
	}
	else {
		
        header('Location: index.php?leftmenu=exmcontrl');
        exit;
	}
}

/*
 * View
 */

$form           = new Form($db);
$exmcontrl      = new exmcontrl($db);

$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/exmcontrl.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");

llxHeader(array(), $langs->trans('exmcontrl'),'','','','',$morejs,$morecss,0,0);

if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create' ) {
	// Si l'utilisateur n'a pas le droit de créer un marché
    /*if (empty($user->rights->marches->write) || empty($user->rights->marches->write_all)) {
        $errors[]=$langs->trans('CantCreate');
    }
    else {*/
        // Formulaire appel d'offre
        print_fiche_titre($langs->trans('Addexmcontrl'));

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
        $head = medical_prepare_head($daId);
        dol_fiche_head($head, 'exmcontrl', $langs->trans("exmcontrl"), 0, '');

        print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";
         print '<input type="hidden" name="daId" value="'.$daId.'" />'."\n";

        print '<table class="border" width="100%">';
        print '<tbody>';

        print '<tr><td class="fieldrequired" width="20%">'.$langs->trans("DateStart").'</td><td>';
           print '<input type="text" autocomplete="off" class="datepicker22" name="dated_" required="required"/> ';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("DateEnd").'</td><td>';
        print '<input type="text" autocomplete="off" class="datepicker22"  name="datef_" required="required"/> ';
        print '</td></tr>';
        print '<tr><td class="fieldrequired">'.$langs->trans("observation").'</td><td>';
        print '<textarea  name="observation_" id="observation_txt"  ></textarea>';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("type").'</td><td>';
        print $form->selectarray('type_', $type_array, null, 0, 0, 0);
    
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("apte").'</td><td>';
        print $form->selectarray('apte_', $apte_array, null, 0, 0, 0);
        print '</td></tr>';



        print '</tbody>';
        print '</table>';

        print '<div class="center">';
        print '<input type="submit" value="'. $langs->trans("Createexmcontrl") .'" name="bouton" class="butAction">';
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
        // Affichage de la fiche d'une exmcontrl de congés payés

        if ($id > 0) {
            $exmcontrl->fetch($id);

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

            // On vérifie si l'utilisateur à le droit de lire cette exmcontrl
           // if ($canedit) {
                if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id.'&daId='.$daId, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
                }

                // Si annulation de la exmcontrl
                if ($action == 'cancel') {
                    print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleCancelSC"), $langs->trans("ConfirmCancelSC"),"confirm_cancel", '', 1, 1);
                }
                  print_fiche_titre($langs->trans('exmcontrl'));
  
                // dol_fiche_head('', 'exmcontrls', $langs->trans("exmcontrl"), 0, '');

                if ($action == 'edit') {
                    $edit = true;
                    $head = medical_prepare_head($daId);
                    dol_fiche_head($head, 'exmcontrl', $langs->trans("exmcontrl"), 0, '');
                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="exmcontrl_id" value="'.$id.'" />'."\n";
                    print '<input type="hidden" name="daId" value="'.$daId.'" />'."\n";
                    print '<input type="hidden" name="entity" value="'.$exmcontrl->entity.'" />'."\n";

                    print '<table class="border" width="100%">';
                    print '<tbody>';


                     list($dated, $datec_time) = explode(" ", dol_print_date($exmcontrl->dated,'dayhoursec'));
                     list($datef, $datec_time) = explode(" ", dol_print_date($exmcontrl->datef,'dayhoursec'));
        print '<tr><td class="fieldrequired" width="20%">'.$langs->trans("DateStart").'</td><td>';
           print '<input type="text" autocomplete="off" class="datepicker22" value="'.$dated.'"  name="dated_" required="required"/> ';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("DateEnd").'</td><td>';
        print '<input type="text" autocomplete="off" class="datepicker22" value="'.$datef.'"  name="datef_" required="required"/> ';
        print '</td></tr>';
        print '<tr><td class="fieldrequired">'.$langs->trans("observation").'</td><td>';
        print '<textarea  name="observation_" id="observation_txt"  >'.$exmcontrl->observation.'</textarea>';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("type").'</td><td>';
         print $form->selectarray('type_', $type_array, $exmcontrl->type, 0, 0, 0);
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("apte").'</td><td>';
         print $form->selectarray('apte_', $apte_array, $exmcontrl->apte, 0, 0, 0);
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