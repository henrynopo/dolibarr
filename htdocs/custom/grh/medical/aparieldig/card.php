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
dol_include_once('/grh/medical/aparieldig/class/aparieldig.class.php');
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
	$aparieldig = new aparieldig($db);

    if (!$error) {

        $aparieldig->bouch          = trim(GETPOST('bouch_'));
        $aparieldig->amyg               = trim(GETPOST('amyg_'));
        $aparieldig->foie             = trim(GETPOST('foie_'));
        $aparieldig->rate             = trim(GETPOST('rate_'));
        $aparieldig->hern             = trim(GETPOST('hern_'));
        $aparieldig->da                = Intval(trim(GETPOST('daId')));
        $aparieldig->entity = $conf->entity;
		$aparieldigID = $aparieldig->create();

	    // If no SQL error we redirect to the request card
	    if ($aparieldigID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php'.'?daId='.trim(GETPOST('daId')));
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&daId='.trim(GETPOST('daId')).'&error=SQL_Create&msg='.$aparieldig->error);
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

    $aparieldig_id = (int) GETPOST('aparieldig_id', 'int');

    if (!$aparieldig_id || $aparieldig_id <= 0) {
        header('Location: ./card.php?action=request&error=CantUpdate');
        exit;
    }

    $aparieldig = new aparieldig($db);
    $aparieldig->fetch($aparieldig_id);

   // $canedit = ($user->rights->marches->write || $user->rights->marches->write_all);
    $canedit = true ; 


    // If this is the requestor or has read/write rights
    if ($canedit) {
        $datec ='';
        $bouch          = trim(GETPOST('bouch_'));
        $amyg               = trim(GETPOST('amyg_'));
        $foie             = trim(GETPOST('foie_'));
        $rate             = trim(GETPOST('rate_'));
        $hern             = trim(GETPOST('hern_'));
        $da                = Intval(trim(GETPOST('daId')));

        $data = array(
            'bouch'        => $bouch,
            'amyg'             => $amyg,
            'foie'           => $foie,
            'rate'           => $rate,
            'hern'           => $hern,
            'da'              => $da
            'entity'              => $entity

        );

		// Update
		$getaparieldigID = $aparieldig->update($aparieldig_id, $data);

        if ($getaparieldigID > 0) {
            header('Location: ./index.php?id='.$aparieldig_id.'&daId='.$da);
            exit;
        } else {
            // Otherwise we display the request form with the SQL error message
            header('Location: ./card.php?id='. $aparieldig_id .'&action=edit&error=SQL_Create&msg='.$aparieldig->error);
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
	$error=0;

	//$db->begin();

	$aparieldig  = new aparieldig($db);
	//$canedit = ($user->rights->marches->write_all || $user->rights->marches->delete);
    $canedit = true;
	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $aparieldig->fetch($id);
		$aparieldig->delete();
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
		
        header('Location: index.php?leftmenu=aparieldig');
        exit;
	}
}

/*
 * View
 */

$form           = new Form($db);
$aparieldig      = new aparieldig($db);

$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/aparieldig.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");

llxHeader(array(), $langs->trans('aparieldig'),'','','','',$morejs,$morecss,0,0);

if (empty($id) || $action == 'add' || $action == 'request' || $action == 'create' ) {
	// Si l'utilisateur n'a pas le droit de créer un marché
    /*if (empty($user->rights->marches->write) || empty($user->rights->marches->write_all)) {
        $errors[]=$langs->trans('CantCreate');
    }
    else {*/
        // Formulaire appel d'offre
        print_fiche_titre($langs->trans('Addaparieldig'));

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
        dol_fiche_head($head, 'aparielres', $langs->trans(""), 0, '');
        $head2 = medical_apariel_prepare_head($daId);
        dol_fiche_head($head2, 'aparieldig', $langs->trans("aparieldig"), 0, '');

        print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";
         print '<input type="hidden" name="daId" value="'.$daId.'" />'."\n";

        print '<table class="border" width="100%">';
        print '<tbody>';

        print '<tr><td >'.$langs->trans("bouch").'</td><td>';
           print '<input type="text" name="bouch_" /> ';
        print '</td></tr>';

        print '<tr><td  >'.$langs->trans("amyg").'</td><td>';
        print '<input type="text"  name="amyg_" /> ';
        print '</td></tr>';
        print '<tr><td >'.$langs->trans("foie").'</td><td>';
        print '<input type="text" name="foie_" />';
        print '</td></tr>';

        // print '<tr><td >'.$langs->trans("audition").'</td><td>';
        // print '<input type="text" name="audition_" />';
        // print '</td></tr>';

        print '<tr><td  >'.$langs->trans("rate").'</td><td>';
        print '<input type="text" name="rate_" />';
        print '</td></tr>';

        print '<tr><td  >'.$langs->trans("hern").'</td><td>';
        print '<input type="text" name="hern_" />';
        print '</td></tr>';



        print '</tbody>';
        print '</table>';

        print '<div class="center">';
        print '<input type="submit" value="'. $langs->trans("Createaparieldig") .'" name="bouton" class="butAction">';
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
        // Affichage de la fiche d'une aparieldig de congés payés

        if ($id > 0) {
            $aparieldig->fetch($id);

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

            // On vérifie si l'utilisateur à le droit de lire cette aparieldig
           // if ($canedit) {
                if ($action == 'delete') {
                        print $form->formconfirm("card.php?id=".$id.'&daId='.$daId, $langs->trans("TitleDelete"),$langs->trans("ConfirmDelete"),"confirm_delete", '', 0, 1);
                }

                // Si annulation de la aparieldig
                if ($action == 'cancel') {
                    print $form->formconfirm("card.php?id=".$id, $langs->trans("TitleCancelSC"), $langs->trans("ConfirmCancelSC"),"confirm_cancel", '', 1, 1);
                }
                  print_fiche_titre($langs->trans('aparieldig'));
  
                // dol_fiche_head('', 'aparieldigs', $langs->trans("aparieldig"), 0, '');

                if ($action == 'edit') {
                    $edit = true;
                    $head = medical_prepare_head($daId);
                    dol_fiche_head($head, 'aparielres', $langs->trans(""), 0, '');
                    $head2 = medical_apariel_prepare_head($daId);
                    dol_fiche_head($head2, 'aparieldig', $langs->trans("aparieldig"), 0, '');

                    print '<form method="post" action="'.$_SERVER['PHP_SELF'].'?id='.$id.'">'."\n";
                    print '<input type="hidden" name="action" value="update" />'."\n";
                    print '<input type="hidden" name="aparieldig_id" value="'.$id.'" />'."\n";
                    print '<input type="hidden" name="daId" value="'.$daId.'" />'."\n";
                    print '<input type="hidden" name="entity" value="'.$aparieldig->entity.'" />'."\n";

                    print '<table class="border" width="100%">';
                    print '<tbody>';


                     list($datec, $datec_time) = explode(" ", dol_print_date($aparieldig->datec,'dayhoursec'));
        print '<tr><td  >'.$langs->trans("bouch").'</td><td>';
           print '<input type="text" value="'.$aparieldig->bouch.'"  name="bouch_"  /> ';
        print '</td></tr>';

        print '<tr><td  >'.$langs->trans("amyg").'</td><td>';
        print '<input type="text"  value="'.$aparieldig->amyg.'"  name="amyg_"  /> ';
        print '</td></tr>';
        print '<tr><td  >'.$langs->trans("foie").'</td><td>';
        print '<input type="text" name="foie_" value="'.$aparieldig->foie.'"  />';
        print '</td></tr>';

        // print '<tr><td  >'.$langs->trans("audition").'</td><td>';
        // print '<input type="text" name="audition_" value="'.$aparieldig->audition.'"  />';
        // print '</td></tr>';

        print '<tr><td  >'.$langs->trans("rate").'</td><td>';
        print '<input type="text" name="rate_" value="'.$aparieldig->rate.'"  />';
        print '</td></tr>';

        print '<tr><td  >'.$langs->trans("hern").'</td><td>';
        print '<input type="text" name="hern_" value="'.$aparieldig->hern.'"  />';
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