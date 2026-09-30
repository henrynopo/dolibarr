<?php 
/* Copyright (C) 2015		Yassine Belkaid	<y.belkaid@nextconcept.ma>
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
 *   	\file       salariescontracts/list.php
 *		\ingroup    list
 *		\brief      List of salaries contracts
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/grh/salariescontracts/lib/salariescontracts.lib.php');
dol_include_once('/grh/salariescontracts/common.inc.php');

$langs->load('scontracts@salariescontracts');
// Get parameters
$myparam = GETPOST("myparam");
$action  = GETPOST('action', 'alpha');
$id 	 = GETPOST('id', 'int');
$userid  = GETPOST('userid') ? GETPOST('userid') : $user->id;

// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$now 	= dol_now();
$error 	= false;

if ($action == 'create') {
	$sc = new Salariescontracts($db);

    // If no right to create a request
    if (($userid == $user->id && empty($user->rights->grh->salariescontracts->write)) || ($userid != $user->id && empty($user->rights->grh->salariescontracts->write_all))) {
    	$error++;
    	setEventMessage($langs->trans('CantCreateSC'));
    	$action='request';
    }

    if (!$error) {
    	$db->begin();

	    $start_date 	= dol_mktime(0, 0, 0, GETPOST('start_date_month'), GETPOST('start_date_day'), GETPOST('start_date_year'));
	    $end_date   	= (isset($_POST['end_date_']) && !empty($_POST['end_date_']) ? dol_mktime(0, 0, 0, GETPOST('end_date_month'), GETPOST('end_date_day'), GETPOST('end_date_year')) : null);
	    $salarie_sig_date = (isset($_POST['salarie_sig_date_']) && !empty($_POST['salarie_sig_date_']) ? dol_mktime(0, 0, 0, GETPOST('salarie_sig_date_month'), GETPOST('salarie_sig_date_day'), GETPOST('salarie_sig_date_year'), 1) : null);
	    $direction_sig_date = (isset($_POST['direction_sig_date_']) && !empty($_POST['direction_sig_date_']) ? dol_mktime(0, 0, 0, GETPOST('direction_sig_date_month'), GETPOST('direction_sig_date_day'), GETPOST('direction_sig_date_year'), 1) : null);
	    $dpae_date = (isset($_POST['dpae_date_']) && !empty($_POST['dpae_date_']) ? dol_mktime(0, 0, 0, GETPOST('dpae_date_month'), GETPOST('dpae_date_day'), GETPOST('dpae_date_year'), 1) : null);
	    $medical_visit_date = (isset($_POST['medical_visit_date_']) && !empty($_POST['medical_visit_date_']) ? dol_mktime(0, 0, 0, GETPOST('medical_visit_date_month'), GETPOST('medical_visit_date_day'), GETPOST('medical_visit_date_year'), 1) : null);

	    $type 		 = GETPOST('type');
	    $description = trim(GETPOST('description'));
	    $userID 	 = GETPOST('userID');

	    // If no start date
	    if (empty($start_date)) {
	        header('Location: card.php?action=request&error=nodatedebut');
	        exit;
	    }

	    // If start date after end date
	    if (!empty($end_date) && $start_date > $end_date) {
	        header('Location: card.php?action=request&error=datefin');
	        exit;
	    }

	    // Check if there is already holiday for this period
	    // $verifCP = $sc->verifContractByUser($userID, $date_debut, $date_fin, $halfday);
	    /*if (! $verifCP)
	    {
	        header('Location: card.php?action=request&error=alreadyCP');
	        exit;
	    }*/

	    $dateTime = new DateTime('now');
	    $sc->fk_user 	 		= $userid;
	    $sc->fk_user_create		= $user->id;
	    $sc->date_create		= $dateTime->format('Y-m-d');
	    $sc->description 		= $description;
	    $sc->start_date  		= $start_date;
	    $sc->end_date 	 		= $end_date;
		$sc->salarie_sig_date 	= $salarie_sig_date;
		$sc->direction_sig_date = $direction_sig_date;
		$sc->dpae_date 			= $dpae_date;
		$sc->medical_visit_date = $medical_visit_date;
	    $sc->type 				= $type;

		$verif = $sc->create($user);

	    // If no SQL error we redirect to the request card
	    if ($verif > 0) {
			$db->commit();

	    	header('Location: card.php?id='.$verif);
	        exit;
	    }
	    else {
	    	$db->rollback();

	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&error=SQL_Create&msg='.$sc->error);
	        exit;
	    }
    }
}

if ($action == 'update') {

	$start_date = dol_mktime(0, 0, 0, GETPOST('start_date_month'), GETPOST('start_date_day'), GETPOST('start_date_year'));
    $end_date       = (isset($_POST['end_date_']) && !empty($_POST['end_date_']) ? dol_mktime(0, 0, 0, GETPOST('end_date_month'), GETPOST('end_date_day'), GETPOST('end_date_year')) : null);
    $salarie_sig_date = (isset($_POST['salarie_sig_date_']) && !empty($_POST['salarie_sig_date_']) ? dol_mktime(0, 0, 0, GETPOST('salarie_sig_date_month'), GETPOST('salarie_sig_date_day'), GETPOST('salarie_sig_date_year'), 1) : null);
    $direction_sig_date = (isset($_POST['direction_sig_date_']) && !empty($_POST['direction_sig_date_']) ? dol_mktime(0, 0, 0, GETPOST('direction_sig_date_month'), GETPOST('direction_sig_date_day'), GETPOST('direction_sig_date_year'), 1) : null);
    $dpae_date = (isset($_POST['dpae_date_']) && !empty($_POST['dpae_date_']) ? dol_mktime(0, 0, 0, GETPOST('dpae_date_month'), GETPOST('dpae_date_day'), GETPOST('dpae_date_year'), 1) : null);
    $medical_visit_date = (isset($_POST['medical_visit_date_']) && !empty($_POST['medical_visit_date_']) ? dol_mktime(0, 0, 0, GETPOST('medical_visit_date_month'), GETPOST('medical_visit_date_day'), GETPOST('medical_visit_date_year'), 1) : null);

	$description = GETPOST('description');
	$type 		 = GETPOST('type');

    // If no right to modify a request 
    if (!$user->rights->grh->salariescontracts->write) {
        header('Location: card.php?action=request&error=CantUpdate');
        exit;
    }

    $sc 	= new Salariescontracts($db);
    $sc_id 	= (int) $_POST['salariecontract_id'];
    $sc->fetch($sc_id);

	$canedit = (($user->id == $sc->fk_user && $user->rights->grh->salariescontracts->write) || ($user->id != $sc->fk_user && $user->rights->grh->salariescontracts->write_all));

    // If this is the requestor or has read/write rights
    if ($canedit) {
        $description = trim($_POST['description']);

        // If no start date
        if (empty($_POST['start_date_'])) {
            header('Location: card.php?id='. $sc_id .'&action=edit&error=nodatedebut');
            exit;
        }

        // If no end date
        if (empty($_POST['type'])) {
            header('Location: card.php?id='. $sc_id .'&action=edit&error=notype');
            exit;
        }

        // If start date after end date
        if (!empty($_POST['end_date_']) && $end_date < $start_date) {
            header('Location: card.php?id='. $sc_id .'&action=edit&error=datefin');
            exit;
        }

        $sc->description 		= $description;
        $sc->start_date  		= $start_date;
        $sc->end_date    		= $end_date;
		$sc->type  	 			= $type;
		$sc->salarie_sig_date  	= $salarie_sig_date;
		$sc->direction_sig_date = $direction_sig_date;
		$sc->dpae_date  	 	= $dpae_date;
		$sc->medical_visit_date = $medical_visit_date;

		// Update
		$verif = $sc->update($user);

        if ($verif > 0) {
            header('Location: card.php?id='.$sc_id);
            exit;
        }
        else {
            // Otherwise we display the request form with the SQL error message
            header('Location: card.php?id='. $sc_id .'&action=edit&error=SQL_Create&msg='.$sc->error);
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' && $user->rights->grh->salariescontracts->delete) {
	$error=0;

	$db->begin();

	$sc = new Salariescontracts($db);
	$sc->fetch($id);

	$canedit = (($user->id == $sc->fk_user && $user->rights->grh->salariescontracts->write) || ($user->id != $sc->fk_user && $user->rights->grh->salariescontracts->write_all));

	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
		$result = $sc->delete($user);
	}
	else {
		$error = $langs->trans('ErrorCantDeleteSC');
	}

	if (!$error) {
		$db->commit();
		header('Location: list.php');
		exit;
	}
	else {
		$db->rollback();
	}
}

/*
 * View
 */

$form = new Form($db);
$sc   = new Salariescontracts($db);

llxHeader(array(), $langs->trans('ListOfSalaries'));

if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create') {
	// Si l'utilisateur n'a pas le droit de faire une demande
    if (($userid == $user->id && empty($user->rights->grh->salariescontracts->write)) || ($userid != $user->id && empty($user->rights->grh->salariescontracts->write_all))) {
        $errors[]=$langs->trans('CantCreateSC');
    }
    else {
        // Formulaire de demande de congés payés
        // dol_fiche_head('', '', $langs->trans("MenuAddSC"), 0, 'user');
        print_fiche_titre($langs->trans('MenuAddSC'));
        // Si il y a une erreur
        if (GETPOST('error')) {

            switch(GETPOST('error')) {
                case 'datefin' :
                    $errors[] = $langs->trans('ErrorEndDateCP');
                    break;
                case 'SQL_Create' :
                    $errors[] = $langs->trans('ErrorSQLCreateCP').' <b>'.htmlentities($_GET['msg']).'</b>';
                    break;
                case 'CantCreate' :
                    $errors[] = $langs->trans('CantCreateCP');
                    break;
                case 'Type' :
                    $errors[] = $langs->trans('ErrorTypeSc');
                    break;
                case 'nodatedebut' :
                    $errors[] = $langs->trans('NoHiringDate');
                    break;
                case 'nodatefin' :
                    $errors[] = $langs->trans('NoDateFin');
                    break;
                case 'alreadyCP' :
                    $errors[] = $langs->trans('alreadyCPexist');
                    break;
            }

	        setEventMessage($errors, 'errors');
        }

		print '<script type="text/javascript">
	    function valider() {
    	    if(document.contratSC.start_date.value == "") {
	           alert("'.dol_escape_js($langs->transnoentities('NoDateDebut')).'");
	           return false;
	        }

	        if(document.contratSC.type.value == "" || document.contratSC.type.value == "-1") {
              alert("'.dol_escape_js($langs->trans('ErrorTypeSc')).'");
              return false;
            }
       	}
       </script>'."\n";

        // Formulaire de demande
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" onsubmit="return valider()" name="contratSC">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";
        print '<input type="hidden" name="userID" value="'.$userid.'" />'."\n";

        print '<table class="border" width="100%">';
        print '<tbody>';

        // User
        print '<tr>';
        print '<td class="fieldrequired">'.$langs->trans("User").'</td>';
        print '<td>';
        if (empty($user->rights->grh->salariescontracts->write_all)) {
        	print $form->select_users($userid,'useridbis',0,'',1);
        	print '<input type="hidden" name="userid" value="'.$userid.'">';
        }
        else print $form->select_users(GETPOST('userid')?GETPOST('userid'):$user->id,'userid',0,'',0);
        print '</td>';
        print '</tr>';

        // Type
        print '<tr>';
        print '<td class="fieldrequired">'.$langs->trans("Type").'</td>';
        print '<td>';
	        print $form->selectarray('type', $sc->getContractsTypes(), (GETPOST('type')?GETPOST('type'):''), 1);
        print '</td>';
        print '</tr>';

        // Date start
        print '<tr>';
        print '<td class="fieldrequired">'.$langs->trans("HiringDate").'</td>';
        print '<td>';
            $form->select_date(-1,'start_date_');
        print '</td>';
        print '</tr>';

        // Date end
        print '<tr>';
        print '<td class="">'.$langs->trans("EndDate").'</td>';
        print '<td>';
            $form->select_date(-1,'end_date_');
        print '</td>';
        print '</tr>';

         // Salarie signature date
        print '<tr>';
        print '<td class="">'.$langs->trans("SalarySignatureDateSC").'</td>';
        print '<td>';
            $form->select_date(-1,'salarie_sig_date_');
        print '</td>';
        print '</tr>';

        // Direction signature date
        print '<tr>';
        print '<td class="">'.$langs->trans("ManagerSignatureDateSC").'</td>';
        print '<td>';
            $form->select_date(-1,'direction_sig_date_');
        print '</td>';
        print '</tr>';

        // Declaration preable à l'embauche
        print '<tr>';
        print '<td class="">'.$langs->trans("DeclarationPriorToHiringSC").'</td>';
        print '<td>';
            $form->select_date(-1,'dpae_date_');
        print '</td>';
        print '</tr>';

        // Visite medicale
        print '<tr>';
        print '<td class="">'.$langs->trans("MedicalVisitSC").'</td>';
        print '<td>';
            $form->select_date(-1,'medical_visit_date_');
        print '</td>';
        print '</tr>';

        // Description
        print '<tr>';
        print '<td>'.$langs->trans("DescSC").'</td>';
        print '<td>';
        print '<textarea name="description" class="flat" rows="'.ROWS_3.'" cols="70"></textarea>';
        print '</td>';
        print '</tr>';

        print '</tbody>';
        print '</table>';

        dol_fiche_end();

        print '<div class="center">';
        print '<input type="submit" value="'.$langs->trans("SendRequestSC").'" name="bouton" class="button">';
        print '&nbsp; &nbsp; ';
        print '<input type="button" value="'.$langs->trans("TitleCancelSC").'" class="button" onclick="history.go(-1)">';
        print '</div>';

        print '</from>'."\n";
    }
}
else {
    if ($error) {
        print '<div class="tabBar">';
        print $error;
        print '<br /><br /><input type="button" value="'.$langs->trans("ReturnSC").'" class="button" onclick="history.go(-1)" />';
        print '</div>';
    }
    else {
        // Affichage de la fiche d'une demande de congés payés
        if ($id > 0) {
            $sc->fetch($id);

			$canedit = (($user->id == $sc->fk_user && $user->rights->grh->salariescontracts->write) || ($user->id != $sc->fk_user && $user->rights->grh->salariescontracts->write_all));

            $userRequest = new User($db);
            $userRequest->fetch($sc->fk_user);

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

            $head = salariescontracts_prepare_head($sc);

            // On vérifie si l'utilisateur à le droit de lire cette demande
            if ($canedit) {
                if ($action == 'delete') {
                    if($user->rights->grh->salariescontracts->delete) {
                        print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleDeleteSC"),$langs->trans("ConfirmDeleteSC"),"confirm_delete", '', 0, 1);
                    }
                }

                // Si annulation de la demande
                if ($action == 'cancel') {
                    print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleCancelSC"), $langs->trans("ConfirmCancelSC"),"confirm_cancel", '', 1, 1);
                }

                if ($action == 'edit') {
                    dol_fiche_head($head, 'card', $langs->trans("ModifyContract"), 0, 'object_salariescontracts@salariescontracts');
                } else {
                    dol_fiche_head($head, 'card', $langs->trans("ContractDetail"), 0, 'object_salariescontracts@salariescontracts');
                }

                if ($action == 'edit') {
                    $edit = true;
                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="salariecontract_id" value="'.$id.'" />'."\n";
                }

                print '<table class="border" width="100%">';
                print '<tbody>';

                $linkback = '';

                print '<tr>';
                print '<td width="20%">'.$langs->trans("Ref").'</td>';
                print '<td>';
                	print $sc->id;
                // print $form->showrefnav($sc, 'id', $linkback, 1, 'rowid', 'ref');
                print '</td>';
                print '</tr>';

                print '<td>'.$langs->trans("User").'</td>';
        		print '<td>';
        		print $userRequest->getNomUrl(1);
        		print '</td></tr>';

                if(!$edit) {
                	// Type
			        print '<tr>';
			        print '<td>'.$langs->trans("Type").'</td>';
			        print '<td>';
			        print $sc->getContractTypeById($sc->type);
			        print '</td>';
			        print '</tr>';

                    print '<tr>';
                    print '<td>'.$langs->trans('HiringDate').'</td>';
                    print '<td>'.dol_print_date($sc->start_date,'day');
                    print '</td>';
                    print '</tr>';
                }
                else {
                	// Type
			        print '<tr>';
			        print '<td>'.$langs->trans("Type").'</td>';
			        print '<td>';
	       				print $form->selectarray('type', $sc->getContractsTypes(), (GETPOST('type')?GETPOST('type'):$sc->type), 1);
			        print '</td>';
			        print '</tr>';

                    print '<tr>';
                    print '<td>'.$langs->trans('HiringDate').'</td>';
                    print '<td>';
                  	  $form->select_date($sc->start_date,'start_date_');
                    print '</td>';
                    print '</tr>';
                }

                if (!$edit) {
                	$endDate = dol_print_date($sc->end_date, 'day');
                    print '<tr>';
                    print '<td>'.$langs->trans('EndDate').'</td>';
                    print '<td>'.($endDate ?: 'Vide');
                    print '</td>';
                    print '</tr>';
                }
                else {
                    print '<tr>';
                    print '<td>'.$langs->trans('EndDate').'</td>';
                    print '<td>';
              	      $form->select_date($sc->end_date, 'end_date_');
                    print '</td>';
                    print '</tr>';
                }

                // Description
                if (!$edit) {
                    print '<tr>';
                    print '<td>'.$langs->trans('DescSC').'</td>';
                    print '<td>'.nl2br($sc->description).'</td>';
                    print '</tr>';
                }
                else {
                    print '<tr>';
                    print '<td>'.$langs->trans('DescSC').'</td>';
                    print '<td><textarea name="description" class="flat" rows="'.ROWS_3.'" cols="70">'.$sc->description.'</textarea></td>';
                    print '</tr>';
                }

                print '</tbody>';
                print '</table>'."\n";

                print '<br><br>';

				// Other Info
                print '<table class="border" width="50%">'."\n";
                print '<tbody>';
                print '<tr class="liste_titre">';
                print '<td colspan="2">'.$langs->trans("OtherInfoSC").'</td>';
                print '</tr>';

                if (!empty($sc->fk_user_create)) {
                	$userCreate = new User($db);
                	$userCreate->fetch($sc->fk_user_create);
	                print '<tr>';
	                print '<td>'.$langs->trans('CreatedByCP').'</td>';
	                print '<td>'.$userCreate->getNomUrl(1).'</td>';
	                print '</tr>';
                }

                print '<tr>';
                print '<td>'.$langs->trans('DateCreate').'</td>';
                print '<td>'.dol_print_date($sc->date_create,'dayhour').'</td>';
                print '</tr>';

                if (!$edit) {
                    print '<tr>';
                    print '<td width="50%">'.$langs->trans('SalarySignatureDateSC').'</td>';
                    print '<td>'. dol_print_date($sc->salarie_sig_date, 'day') .'</td>';
                    print '</tr>';

                    print '<tr>';
                    print '<td width="50%">'.$langs->trans('ManagerSignatureDateSC').'</td>';
                    print '<td>'. dol_print_date($sc->direction_sig_date, 'day') .'</td>';
                    print '</tr>';

                    print '<tr>';
                    print '<td width="50%">'.$langs->trans('DeclarationPriorToHiringSC').'</td>';
                    print '<td>'. dol_print_date($sc->dpae_date, 'day') .'</td>';
                    print '</tr>';

                    print '<tr>';
                    print '<td width="50%">'.$langs->trans('MedicalVisitSC').'</td>';
                    print '<td>'. dol_print_date($sc->medical_visit_date, 'day') .'</td>';
                    print '</tr>';
                } else {
                    print '<tr>';
                    print '<td>'.$langs->trans('SalarySignatureDateSC').'</td>';
                    print '<td>';
              	      $form->select_date($sc->salarie_sig_date, 'salarie_sig_date_');
                    print '</td>';
                    print '</tr>';

                    print '<tr>';
                    print '<td>'.$langs->trans('ManagerSignatureDateSC').'</td>';
                    print '<td>';
              	      $form->select_date($sc->direction_sig_date, 'direction_sig_date_');
                    print '</td>';
                    print '</tr>';

                    print '<tr>';
                    print '<td>'.$langs->trans('DeclarationPriorToHiringSC').'</td>';
                    print '<td>';
              	      $form->select_date($sc->dpae_date, 'dpae_date_');
                    print '</td>';
                    print '</tr>';

                    print '<tr>';
                    print '<td>'.$langs->trans('MedicalVisitSC').'</td>';
                    print '<td>';
              	      $form->select_date($sc->medical_visit_date, 'medical_visit_date_');
                    print '</td>';
                    print '</tr>';
                }
                
                print '</tbody>';
                print '</table>';

                if ($action == 'edit') {
                    print '<br><div align="center">';
                    if ($canedit) {
                        print '<input type="submit" value="'.$langs->trans("UpdateButtonSC").'" class="button">';
                    }
                    print '</div>';

                    print '</form>';
                }

                dol_fiche_end();

                if (!$edit) {
		            print '<div class="tabsAction">';

                    // Boutons d'actions
                    if ($canedit) {
                        print '<a href="card.php?id='.$_GET['id'].'&action=edit" class="butAction">'.$langs->trans("EditSC").'</a>';
                    }

                    // If draft
                    if ($user->rights->grh->salariescontracts->delete)	{
                    	print '<a href="card.php?id='.$_GET['id'].'&action=delete" class="butActionDelete">'.$langs->trans("DeleteSC").'</a>';
                    }

                    print '</div>';
                }

            } else {
                print '<div class="tabBar">';
                print $langs->trans('ErrorUserViewSC');
                print '<br /><br /><input type="button" value="'.$langs->trans("ReturnSC").'" class="button" onclick="history.go(-1)" />';
                print '</div>';
            }

        } else {
            print '<div class="tabBar">';
            print $langs->trans('ErrorIDFicheSC');
            print '<br /><br /><input type="button" value="'.$langs->trans("ReturnSC").'" class="button" onclick="history.go(-1)" />';
            print '</div>';
        }

    }

}

// End of page
llxFooter();

if (is_object($db)) $db->close();

?>