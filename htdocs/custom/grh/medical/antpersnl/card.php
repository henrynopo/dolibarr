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
dol_include_once('/grh/medical/antpersnl/class/antpersnl.class.php');
dol_include_once('/grh/lib/medical.lib.php');

// Get parameters
$action  = GETPOST('action', 'alpha');
$id 	 = GETPOST('id', 'int');
$daId    = GETPOST('daId', 'int');
$request_method = $_SERVER['REQUEST_METHOD'];


if ($user->societe_id > 0) accessforbidden();

$now 	= new DateTime('now');
$error 	= false;

if ($action == 'create' && $request_method === 'POST') {
	$antpersnl = new antpersnl($db);

    if (!$error) {

        $antpersnl->malades          = trim(GETPOST('malades_'));
        $antpersnl->habitud               = trim(GETPOST('habitud_'));
        $antpersnl->interviews             = trim(GETPOST('interviews_'));
        $antpersnl->accidents            = trim(GETPOST('accidents_'));
        $antpersnl->malad_pro             = trim(GETPOST('malad_pro_'));
        $antpersnl->da                = Intval(trim(GETPOST('daId')));
        $antpersnl->entity = $conf->entity
		$antpersnlID = $antpersnl->create();

	    // If no SQL error we redirect to the request card
	    if ($antpersnlID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php'.'?daId='.trim(GETPOST('daId')));
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&daId='.trim(GETPOST('daId')).'&error=SQL_Create&msg='.$antpersnl->error);
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

    $antpersnl_id = (int) GETPOST('antpersnl_id', 'int');

    if (!$antpersnl_id || $antpersnl_id <= 0) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }

    $antpersnl = new antpersnl($db);
    $antpersnl->fetch($antpersnl_id);

   // $canedit = ($user->rights->marches->write || $user->rights->marches->write_all);
    $canedit = true ; 


    // If this is the requestor or has read/write rights
    if ($canedit) {
        $datec ='';
        $malades          = trim(GETPOST('malades_'));
        $habitud               = trim(GETPOST('habitud_'));
        $interviews             = trim(GETPOST('interviews_'));
        $accidents            = trim(GETPOST('accidents_'));
        $malad_pro             = trim(GETPOST('malad_pro_'));
        $da                = Intval(trim(GETPOST('daId')));
        $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
        $data = array(
            'malades'        => $malades,
            'habitud'             => $habitud,
            'interviews'           => $interviews,
            'accidents'          => $accidents,
            'malad_pro'           => $malad_pro,
            'da'              => $da,
            'entity'              => $entity

        );

		// Update
		$getantpersnlID = $antpersnl->update($antpersnl_id, $data);

        if ($getantpersnlID > 0) {
            header('Location: ./index.php?id='.$antpersnl_id.'&daId='.$da);
            exit;
        } else {
            // Otherwise we display the request form with the SQL error message
            header('Location: ./card.php?id='. $antpersnl_id .'&action=edit&error=SQL_Create&msg='.$antpersnl->error);
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
	$error=0;

	//$db->begin();

	$antpersnl  = new antpersnl($db);
	//$canedit = ($user->rights->marches->write_all || $user->rights->marches->delete);
    $canedit = true;
	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $antpersnl->fetch($id);
		$antpersnl->delete();
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
		
        header('Location: index.php?leftmenu=antpersnl');
        exit;
	}
}

/*
 * View
 */

$form           = new Form($db);
$antpersnl      = new antpersnl($db);

$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/antpersnl.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");

llxHeader(array(), $langs->trans('antpersnl'),'','','','',$morejs,$morecss,0,0);

if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create' ) {
	// Si l'utilisateur n'a pas le droit de créer un marché
    /*if (empty($user->rights->marches->write) || empty($user->rights->marches->write_all)) {
        $errors[]=$langs->trans('CantCreate');
    }
    else {*/
        // Formulaire appel d'offre
        print_fiche_titre($langs->trans('AddAntpersnl'));

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
        dol_fiche_head($head, 'antpersnl', $langs->trans("antpersnl"), 0, '');
        print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";
         print '<input type="hidden" name="daId" value="'.$daId.'" />'."\n";

        print '<table class="border" width="100%">';
        print '<tbody>';

         print '<tr><td class="fieldrequired">'.$langs->trans("malades").'</td><td>';
           print '<textarea name="malades_"  ></textarea>';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("interviews").'</td><td>';
        print '<textarea name="interviews_" ></textarea>';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("accidents").'</td><td>';
          print '<textarea name="accidents_" ></textarea>';
        print '</td></tr>';

        print '<tr><td class="fieldrequired">'.$langs->trans("malad_pro").'</td><td>';
        print '<textarea name="malad_pro_" ></textarea>';
        print '</td></tr>';

         print '<tr><td class="fieldrequired">'.$langs->trans("habitud").'</td><td>';
        print '<textarea name="habitud_" /></textarea>';
        print '</td></tr>';



        print '</tbody>';
        print '</table>';

        print '<div class="center">';
        print '<input type="submit" value="'. $langs->trans("CreateAntpersnl") .'" name="bouton" class="butAction">';
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
        // Affichage de la fiche d'une antpersnl de congés payés

        if ($id > 0) {
            $antpersnl->fetch($id);

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

            // On vérifie si l'utilisateur à le droit de lire cette antpersnl
           // if ($canedit) {
                if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id.'&daId='.$daId, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
                }

                // Si annulation de la antpersnl
                if ($action == 'cancel') {
                    print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleCancelSC"), $langs->trans("ConfirmCancelSC"),"confirm_cancel", '', 1, 1);
                }
                  print_fiche_titre($langs->trans('antpersnl'));
  
                // dol_fiche_head('', 'antpersnls', $langs->trans("antpersnl"), 0, '');

                if ($action == 'edit') {
                    $edit = true;
                    $head = medical_prepare_head($daId);
                    dol_fiche_head($head, 'antpersnl', $langs->trans("antpersnl"), 0, '');
                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="antpersnl_id" value="'.$id.'" />'."\n";
                    print '<input type="hidden" name="daId" value="'.$daId.'" />'."\n";
                    print '<input type="hidden" name="entity" value="'.$antpersnl->entity.'" />'."\n";

                    print '<table class="border" width="100%">';
                    print '<tbody>';


                        list($datec, $datec_time) = explode(" ", dol_print_date($antpersnl->datec,'dayhoursec'));

                        print '<tr><td class="fieldrequired">'.$langs->trans("malades").'</td><td>';
                           print '<textarea name="malades_"  >'.$antpersnl->malades.'</textarea>';
                        print '</td></tr>';

                        print '<tr><td class="fieldrequired">'.$langs->trans("interviews").'</td><td>';
                        print '<textarea name="interviews_" >'.$antpersnl->interviews.'</textarea>';
                        print '</td></tr>';

                        print '<tr><td class="fieldrequired">'.$langs->trans("accidents").'</td><td>';
                          print '<textarea name="accidents_" >'.$antpersnl->accidents.'</textarea>';
                        print '</td></tr>';

                        print '<tr><td class="fieldrequired">'.$langs->trans("malad_pro").'</td><td>';
                        print '<textarea name="malad_pro_" >'.$antpersnl->malad_pro.'</textarea>';
                        print '</td></tr>';

                         print '<tr><td class="fieldrequired">'.$langs->trans("habitud").'</td><td>';
                        print '<textarea name="habitud_" />'.$antpersnl->habitud.'</textarea>';
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