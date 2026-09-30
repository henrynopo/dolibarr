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
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/ref/class/ref.class.php');

// Get parameters
$action  = GETPOST('action', 'alpha');
$id 	 = GETPOST('id', 'int');
$request_method = $_SERVER['REQUEST_METHOD'];

// $langs->load('margins');
// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$now 	= new DateTime('now');
$form       = new Form($db);
$error 	= false;

if ($action == 'create' && $request_method === 'POST') {
	$ref = new ref($db);

    if (!$error) {

        $ref->name                = trim(GETPOST('name_'));
        $ref->type                = GETPOST('type');

            if(!$ref->check_name($ref->name)){
                    $refID = $ref->create();
            
                    // If no SQL error we redirect to the request card
                    if ($refID > 0) {
                        //header('Location: index.php?id='.$getMarcheID);
                        header('Location: index.php');
                        exit;
                    } else {
                        // Otherwise we display the request form with the SQL error message
                        header('Location: card.php?action=request&error=SQL_Create&msg='.$ref->error);
                        exit;
                    }
                }
            else{
                $msg ='Cet Référence '.$ref->name.' est deja saisée !';
                 header('Location: card.php?action=request&error=SQL_Create&msg='.$msg);
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

    $ref_id = (int) GETPOST('ref_id', 'int');

    if (!$ref_id || $ref_id <= 0) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }

    $ref = new ref($db);
    $ref->fetch($ref_id);

   // $canedit = ($user->rights->marches->write || $user->rights->marches->write_all);
    $canedit = true ; 


    // If this is the requestor or has read/write rights
    if ($canedit) {
        $name   = trim(GETPOST('name_'));
        $type   = trim(GETPOST('type'));


        $data = array(
            'name'             => $name, 
            'type'             => $type
        );

		$getrefID = $ref->update($ref_id, $data);
        
                if ($getrefID > 0) {
                    header('Location: ./index.php?id='.$ref_id);
                    exit;
                } else {
                    // Otherwise we display the request form with the SQL error message
                    header('Location: ./card.php?id='. $ref_id .'&action=edit&error=SQL_Create&msg='.$ref->error);
                    exit;
                } 
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
	$error=0;

	//$db->begin();

	$event  = new ref($db);
	//$canedit = ($user->rights->marches->write_all || $user->rights->marches->delete);
    $canedit = true;
	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $event->fetch($id);
		$event->delete();
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
		
        header('Location: index.php?leftmenu=marches');
        exit;
	}
}

/*
 * View
 */

$form      = new Form($db);
$ref     = new ref($db);

$morejs  = array("/grh/js/ref.js");
$morecss = array("");

llxHeader(array(), $langs->trans('ref'),'','','','',$morejs,$morecss,0,0);


if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create') {
	// Si l'utilisateur n'a pas le droit de créer un marché
    /*if (empty($user->rights->marches->write) || empty($user->rights->marches->write_all)) {
        $errors[]=$langs->trans('CantCreate');
    }
    else {*/
        // Formulaire appel d'offre
        print_fiche_titre($langs->trans('Addref'), '', 'object_');

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


        // name
        print '<tr><td class="fieldrequired">'.$langs->trans("name").'</td><td>';
        print '<input type="text" value="" name="name_" required="required" />';
        print '</td></tr>';
        
        print '<tr><td class="fieldrequired">'.$langs->trans("type").'</td><td>';
        print '<select  name="type" required="required" >';
        print '<option  value="1" selected >Production</option>';
        print '<option  value="2">Achat </option>';
        print '<option  value="3">RH </option>';
        print '<option  value="4">Personnel </option>';
        print '<select   />';
        print '</td></tr>';
        print '</tbody>';
        print '</table>';

        print '<div class="center">';
        print '<input type="submit" value="'. $langs->trans("Createref") .'" name="bouton" class="butAction">';
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
        // Affichage de la fiche d'une demande de congés payés

        if ($id > 0) {
            $ref->fetch($id);

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

            // On vérifie si l'utilisateur à le droit de lire cette demande
           // if ($canedit) {
                if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
                }

                // Si annulation de la demande
                if ($action == 'cancel') {
                    print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleCancelSC"), $langs->trans("ConfirmCancelSC"),"confirm_cancel", '', 1, 1);
                }

                    print_fiche_titre($langs->trans('Modifier'), '', 'object_');
                dol_fiche_head('', 'refs', $langs->trans("Ref"), 0, '');

                if ($action == 'edit') {
                    $edit = true;
                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="ref_id" value="'.$id.'" />'."\n";

                    print '<table class="border" width="100%">';
                    print '<tbody>';

                    // Name
                    print '<tr><td>'.$langs->trans("name").'</td><td>';
                    print '<input type="text" value="'. $ref->name .'" name="name_" required="required" />';
                    print '</td></tr>';
                    print '<tr><td class="fieldrequired">'.$langs->trans("type").'</td><td>';
                    $select1 ='';
                    $select2 ='';
                    $select3 ='';
                    $select4 ='';
                    if ($ref->type==1)
                    $select1=' selected="selected"';

                    if ($ref->type==2) 
                    $select2=' selected="selected"';
                  
                   if ($ref->type==3) 
                    $select3=' selected="selected"';
                   
                    if ($ref->type==4) 
                    $select4=' selected="selected"';
                    
                     print '<select  name="type" required="required" >';
                print '<option '.$select1.'  value="1" selected >Production</option>';
                print '<option '.$select2.'  value="2">Achat </option>';
                print '<option '.$select3.' value="3">RH </option>';
                print '<option '.$select4.' value="4">Personnel </option>';
                print '<select   />';
                    
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