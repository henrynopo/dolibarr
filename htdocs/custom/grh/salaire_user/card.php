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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/salaire_user/class/salaire_user.class.php');
dol_include_once('/grh/pointage/class/pointage.class.php');
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$pointage =  new pointage($db);
$userp= new User($db);
$form           = new Form($db);
$salaire_user     = new salaire_user($db);
// Get parameters
$myparam = GETPOST("myparam");
$action  = GETPOST('action', 'alpha');
$month      = GETPOST('mid','int');
$year =GETPOST('periodyear','int');
$id 	 = GETPOST('id', 'int');
$request_method = $_SERVER['REQUEST_METHOD'];

$langs->load('salaire_user');
// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$now 	= new DateTime('now');
$error 	= false;



if ($action == 'update' && $request_method === 'POST') {
    // If no right to modify a request
    /*if (!$user->rights->marches->write) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }*/

    $salaire_user_id = (int) GETPOST('salaire_user_id', 'int');

    if (!$salaire_user_id || $salaire_user_id <= 0) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }

    $salaire_user->fetch($salaire_user_id);

   // $canedit = ($user->rights->marches->write || $user->rights->marches->write_all);
    $canedit = true ; 
   
    // If this is the requestor or has read/write rights
   
    if ($canedit) {
        $salary     = trim(GETPOST('salary_'));
        $salary_base     = trim(GETPOST('salary_base_'));
        $thm     = trim(GETPOST('thm_'));
        $entity=GETPOST('entity')?GETPOST('entity'):$conf->entity;
		// Update
        $data = array(
            'salary'            => $salary,
            'salary_base'       => $salary_base,
            'thm'            => $thm,
            'entity'            => $entity,
        );
       
        $getMarcheID = $salaire_user->update($salaire_user_id, $data);
 
        if ($getMarcheID > 0) {
        header('Location: ./index.php?mid='.$salaire_user->month_point.'&periodyear='.$salaire_user->year_point);
            exit;
        } else {
            // Otherwise we display the request form with the SQL error message
            header('Location: ./card.php?id='. $salaire_user_id .'&action=edit&error=SQL_Create&msg='.$salaire_user->error);
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
	$error=0;

	//$db->begin();

	//$canedit = ($user->rights->marches->write_all || $user->rights->marches->delete);
    $canedit = true;
	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $check = $salaire_user->fetch($id);
        $m = $salaire_user->month_point;
        $y = $salaire_user->year_point;
        if($check)
		$salaire_user->delete($id);
	}
	else {
		$error = $langs->trans('ErrorCantDelete');
	}

	if (!$error) {
		$db->commit();
		 header('Location: ./index.php?mid='.$m.'&periodyear='.$y);
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




///////////////////////////insertion d'un salaire_user
if ($action == 'create' && $request_method === 'POST') {
        $salaire_user->year_point         =  GETPOST('year') ;
        $salaire_user->month_point        = trim(GETPOST('month'));
        $salaire_user->fk_user        = trim(GETPOST('fk_user'));
        $salaire_user->entity        = $conf->entity;
        $salaire_user->created_by        = $user->id;
        $userp->fetch(GETPOST('fk_user'));
        $salary = 0;
        $salary_base = 0;
        $thm = 0;
        if($userp->salary_base)
            $salary_base= $userp->salary_base;
        if($userp->salary)
            $salary= $userp->salary;
        // elseif($userp->thm)
        //     $thm = $userp->thm;
        if($userp->thm)
            $thm = $userp->thm;
       $salaire_user->salary         =  $salary ;
       $salaire_user->salary_base         =  $salary_base ;
       $salaire_user->thm        = $thm;
        
           $getMarcheID = $salaire_user->create();
         
            if ($getMarcheID > 0) {
                     header('Location: ./index.php?mid='.$salaire_user->month_point.'&periodyear='.$salaire_user->year_point);
                    exit;
                } else {
                    // Otherwise we display the request form with the SQL error message
                    header('Location: card.php?action=request&error=SQL_Create&msg='.$salaire_user->error.'&mid='.$salaire_user->month_point.'&periodyear='.$salaire_user->year_point);
                    exit;
                }
            
}










$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/salaire_user.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");

llxHeader(array(), $langs->trans('listsalaire_users'),'','','','',$morejs,$morecss,0,0);

if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create') {
	// Si l'utilisateur n'a pas le droit de créer un marché
    /*if (empty($user->rights->marches->write) || empty($user->rights->marches->write_all)) {
        $errors[]=$langs->trans('CantCreate');
    }
    else {*/
        // Formulaire appel d'offre
        print_fiche_titre($langs->trans('Addsalaire_user'), '', 'object_');

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

	        seteventMessage($errors, 'errors');
        }

        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";
        print '<input type="hidden" name="year" value="'.$year.'" />'."\n";
        print '<input type="hidden" name="month" value="'.$month.'" />'."\n";

        print '<table class="border" width="100%">';
        print '<tbody>';


        // Name
        print '<tr><td class="fieldrequired">'.$langs->trans("User").'</td><td>';
        $exclus_users = $salaire_user->getUsersFromUS($year,$month);
       
           print $form->select_users('',"fk_user",0,$exclus_users);
        print '</td></tr>';


        print '</tbody>';
        print '</table>';


        print '<div class="center">';
        print '<input type="submit" value="'. $langs->trans("Createsalaire_user") .'" name="bouton" class="butAction">';
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
            $salaire_user->fetch($id);
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

	            setsalaire_userMessage($errors, 'errors');
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
                 print_fiche_titre($langs->trans('editsalaire_user'), '', 'object_');
                dol_fiche_head('', 'salaire_users', $langs->trans("salaire_user"), 0, '');

                if ($action == 'edit') {
                    $edit = true;
                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="salaire_user_id" value="'.$id.'" />'."\n";
                    print '<input type="hidden" name="entity" value="'.$salaire_user->entity.'" />'."\n";

                    print '<table class="border" width="100%">';
                    print '<tbody>';
                  
                    $userp->fetch($salaire_user->fk_user);

                    // Name
                    print '<tr><td>'.$langs->trans("User").'</td><td>';
          
                     print '<input type="text" value="'. $userp->getFullName($salaire_user->fk_user) .'"  disabled="disabled" />';
                    // date de pointage
                      print '<tr><td>'.$langs->trans("salaire").'</td><td>';
                    print '<input type="number" step="0.01" min="0" value="'. $salaire_user->salary .'" name="salary_"  />';
                  print '<tr><td>'.$langs->trans("salary_base").'</td><td>';
                    print '<input type="number" step="0.01" min="0" value="'. $salaire_user->salary_base .'" name="salary_base_"  />';
                  
                  
                      print '<tr><td>'.$langs->trans("thm").'</td><td>';
                    print '<input type="number" step="0.01" min="0" value="'. $salaire_user->thm .'" name="thm_"  />';
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



llxFooter();
if (is_object($db)) $db->close();


?>