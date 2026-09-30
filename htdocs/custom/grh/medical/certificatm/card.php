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
 *   	\file       grh/competitors/card.php
 *		\ingroup    card
 *		\brief      Gestion des concurrents
 */

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/grh/medical/certificatm/class/certificatm.class.php');
dol_include_once('/grh/lib/medical.lib.php');

// Get parameters
$myparam = GETPOST("myparam");
$action  = GETPOST('action', 'alpha');
$daId 	 = GETPOST('daId', 'int');
$id 	 = GETPOST('id', 'int');
$request_method = $_SERVER['REQUEST_METHOD'];


$langs->load('grh');

// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$error 	    = false;
$certificatm   = new certificatm($db);


if ($action == 'create') {

    
    $certificatm->da= $daId;
	$certificatm->entity = $conf->entity;
    
	$getcertificatmId = $certificatm->create();

    // If no SQL error we redirect to the request card
    header('Location: ./index.php?daId='.$daId.'&id='.$getcertificatmId. $notification);
    die();
}

if ($_POST['action'] == 'update' && $_POST['id'] > 0 && $_POST['daId'] > 0 && $request_method === 'POST') {
	// If no right to create a request
    	$entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
	    $data = array(
			'da' 	 => $daId,
			'entity' => $entity
		);

		$certificatm->update($id, $data);

    	header('Location: ./index.php?daId='.$daId.'&id='.$id);
        die();
	}


if ($action == 'confirm_delete' && $_GET['confirm'] == 'yes' && $user->rights->grh->delete) {
	$error = 0;
	$daId = GETPOST('daId', 'int');
	$id = GETPOST('id', 'int');
	
	if (!$daId || !$id)
		return;

	$db->begin();

	$canedit = true;

	// Si l'utilisateur à le droit de lire ce contrat, il peut le supprimer
	if ($canedit) {
        $certificatm->id = (int) $id;
        $certificatm->fk_appel_offre = (int) $daId;
		$certificatm->delete();
	} else {
		$error = $langs->trans('ErrorCantDelete');
	}

	if (!$error) {
		$db->commit();
		header('Location: ./index.php?leftmenu=grh&daId='.$daId);
		exit;
	} else {
		$db->rollback();
        header('Location: ./index.php?leftmenu=grh&daId='.$daId);
        exit;
	}
}

$form 	 	= new Form($db);
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js", "/grh/js/certificatm.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css", "/grh/css/grh.css");

llxHeader(array(), $langs->trans('ListOfCompetitors'),'','','','',$morejs,$morecss,0,0);

if (empty($daId) || $action == 'add' || $action == 'request' ) {
	// Si l'utilisateur n'a pas le droit de créer un marché
   
        // Formulaire de concurrents
		$title = $langs->trans('Addcertificatm');
		print_fiche_titre($title,'','object_grh@grh');
		
		$head = medical_prepare_head($daId);
		dol_fiche_head($head, 'certificatm', $langs->trans('Addcertificatm'), 0, '');


        print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">'."\n";
        print '<input type="hidden" name="action" value="create" />'."\n";
        print '<input type="hidden" name="daId" value="'. $daId .'" />'."\n";

        print '<table class="border" width="100%" data-row-id="0">';
        print '<tbody>';

        print '<tr><td class="fieldrequired">'.$langs->trans("certificatmAmountHT").'</td><td>';
        print '<input type="text" class="amount" value="" name="amount_ht" required="required" /></td></tr>';
        
        print '<tr><td class="fieldrequired">'.$langs->trans("certificatmAmountTva").'</td><td>';
        print '<input type="text" class="tva" value="" name="amount_tva" required="required" /></td></tr>';
        
        print '<tr><td class="fieldrequired">'.$langs->trans("certificatmAmountTTC").'</td><td>';
        print '<input type="text" class="total_amout" value="" name="amount_ttc" required="required" /></td></tr>';

        print '<tr><td class="" width="400px">'.$langs->trans("certificatmDateSigMarket").'</td><td>';
        print '<input type="text" class="" value="" name="signature_num" />&nbsp;&nbsp;'. $langs->trans("certificatmDateAt").' &nbsp;&nbsp;<input type="text" autocomplete="off" class="datepicker22" value="" name="signature_date" readonly="readonly" />';
        print '</td></tr>';

        print '<tr><td>'.$langs->trans("certificatmDateApprov").'</td><td>';
        print '<input type="text" class="" value="" name="certificatm_num" />&nbsp;&nbsp;'. $langs->trans("certificatmDateAt").' &nbsp;&nbsp;<input type="text" autocomplete="off" class="datepicker22" value="" name="certificatm_date" readonly="readonly" />';
        print '</td></tr>';

        print '<tr><td>'.$langs->trans("certificatmDateNumVisa").'</td><td>';
        print '<input type="text" class="" value="" name="visa_num" />&nbsp;&nbsp;'. $langs->trans("certificatmDateAt").' &nbsp;&nbsp;<input type="text" autocomplete="off" class="datepicker22" value="" name="visa_date" readonly="readonly" />';
        print '</td></tr>';

        print '<tr><td>'.$langs->trans("Approved").'</td><td>';
        print '<input type="radio" class="approved" value="1" name="approved" checked /> Oui &nbsp;&nbsp;&nbsp; <input type="radio" class="approved" value="0" name="approved" /> Non';
        print '</td></tr>';

        print '<tr class="hidden bail_info"><td>'.$langs->trans("certificatmBailNum").'</td><td><input type="text" class="" value="" name="bail_num" /></td></tr>';
        print '<tr class="hidden bail_info"><td>'.$langs->trans("certificatmBailDate").'</td><td><input type="text" autocomplete="off" class="datepicker22" value="" name="bail_date" readonly="readonly" /></td></tr>';
        print '<tr class="hidden bail_info"><td>'.$langs->trans("certificatmBailAmount").'</td><td><input type="text" class="" value="" name="bail_amount" /></td></tr>';

        print '</tbody>';
        print '</table>';

		dol_fiche_end();

        print '<div class="center"  style="margin-top: 28px;">';
        print '<input type="submit" value="'. $langs->trans("Createcertificatm") .'" name="bouton" class="butAction">';
        print '&nbsp; &nbsp; ';
        print '<input type="button" value="'. $langs->trans("Cancel") .'" class="butAction" onclick="history.go(-1)">';
        print '</div>';

        print '</form>';
    
} else {
	if ($id > 0 && $daId > 0) {
		$title = $langs->trans('Modifycertificatm');
		print_fiche_titre($title,'','object_grh@grh');
		$certificatm->fetch($id, $daId);
		$appeloffre->fetch($daId);
		$head  = appeloffre_prepare_head($appeloffre);
		$canedit = ($user->rights->grh->delete || $user->rights->grh->write_all);

		if ($canedit) {
            if ($action == 'delete') {
                if($user->rights->grh->delete) {
                    print $form->formconfirm("./card.php?daId=".$daId."&id=".$id, $langs->trans("TitleDeleteMonitoring"),$langs->trans("ConfirmDeleteMonitoring"),"confirm_delete", '', 0, 1);
                }
            }

			if ($action == 'edit' || $action == 'delete') {
				dol_fiche_head($head, 'certificatm', $langs->trans('Addcertificatm'), 0, '');

				echo $appeloffre->getAppelOffreHeader();

		        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="sub_form_prt">'."\n";
		        print '<input type="hidden" name="action" value="update" />'."\n";
		        print '<input type="hidden" name="daId" value="'. $daId .'" />'."\n";
		        print '<input type="hidden" name="entity" value="'. $certificatm->entity .'" />'."\n";
		        print '<input type="hidden" name="id" value="'. $id .'" />'."\n";

				$amount_ht 		  = $certificatm->amount_ht ? number_format($certificatm->amount_ht, 0, '', '') : '';
				$amount_tva		  = $certificatm->amount_tva ? number_format($certificatm->amount_tva, 0, '', '') : '';
				$amount_ttc	  	  = $certificatm->amount_ttc ? number_format($certificatm->amount_ttc, 0, '', '') : '';
				$signature_num	  = $certificatm->signature_num ? $certificatm->signature_num : '';
				$signature_date	  = $certificatm->signature_date ? dol_print_date($certificatm->signature_date, 'day') : '';
				$certificatm_num	  = $certificatm->certificatm_num ? $certificatm->certificatm_num : '';
				$certificatm_date	  = $certificatm->certificatm_date ? dol_print_date($certificatm->certificatm_date, 'day') : '';
				$visa_num	  	  = $certificatm->visa_num ? $certificatm->visa_num : '';
				$visa_date	  	  = $certificatm->visa_date ? dol_print_date($certificatm->visa_date, 'day') : '';
				$approved	  	  = $certificatm->approved ? $langs->trans('Yes') : $langs->trans('Non');
				$addMoreBtns 	  = ($certificatm->approved ? ' check_confirm_btn confirmActionToGo' : ' check_confirm_btn');

		        print '<table class="border" width="100%" data-row-id="0">';
		        print '<tbody>';

		        print '<tr><td class="fieldrequired">'.$langs->trans("certificatmAmountHT").'</td><td>';
		        print '<input type="text" class="amount" value="'. $amount_ht .'" name="amount_ht" required="required" /></td></tr>';
		        
		        print '<tr><td class="fieldrequired">'.$langs->trans("certificatmAmountTva").'</td><td>';
		        print '<input type="text" class="tva" value="'. $amount_tva .'" name="amount_tva" required="required" /></td></tr>';
		        
		        print '<tr><td class="fieldrequired">'.$langs->trans("certificatmAmountTTC").'</td><td>';
		        print '<input type="text" class="total_amout" value="'. $amount_ttc .'" name="amount_ttc" required="required" /></td></tr>';

		        print '<tr><td class="" width="400px">'.$langs->trans("certificatmDateSigMarket").'</td><td>';
		        print '<input type="text" class="" value="'. $signature_num .'" name="signature_num" />&nbsp;&nbsp;'. $langs->trans("certificatmDateAt").' &nbsp;&nbsp;<input type="text" autocomplete="off" class="datepicker22" value="'. $signature_date .'" name="signature_date" readonly="readonly" />';
		        print '</td></tr>';

		        print '<tr><td>'.$langs->trans("certificatmDateApprov").'</td><td>';
		        print '<input type="text" class="" value="'. $certificatm_num .'" name="certificatm_num" />&nbsp;&nbsp;'. $langs->trans("certificatmDateAt").' &nbsp;&nbsp;<input type="text" autocomplete="off" class="datepicker22" value="'. $certificatm_date .'" name="certificatm_date" readonly="readonly" />';
		        print '</td></tr>';

		        print '<tr><td>'.$langs->trans("certificatmDateNumVisa").'</td><td>';
		        print '<input type="text" class="" value="'. $visa_num .'" name="visa_num" />&nbsp;&nbsp;'. $langs->trans("certificatmDateAt").' &nbsp;&nbsp;<input type="text" autocomplete="off" class="datepicker22" value="'. $visa_date .'" name="visa_date" readonly="readonly" />';
		        print '</td></tr>';

		        print '<tr><td>'.$langs->trans("Approved").'</td><td>';
		        print '<input type="radio" class="approved" value="1" name="approved" '. ($certificatm->approved == 1 ? ' checked="checked"' : '') .' /> Oui &nbsp;&nbsp;&nbsp; <input type="radio" class="approved" value="0" name="approved"'. ($certificatm->approved == 0 ? ' checked="checked"' : '') .' /> Non';
		        print '</td></tr>';

		        print '<tr class="hidden bail_info"><td>'.$langs->trans("certificatmBailNum").'</td><td><input type="text" class="" value="'. $bail_num .'" name="bail_num" /></td></tr>';
		        print '<tr class="hidden bail_info"><td>'.$langs->trans("certificatmBailDate").'</td><td><input type="text" autocomplete="off" class="datepicker22" value="'. $bail_date .'" name="bail_date" readonly="readonly" /></td></tr>';
		        print '<tr class="hidden bail_info"><td>'.$langs->trans("certificatmBailAmount").'</td><td><input type="text" class="" value="'. $bail_amount .'" name="bail_amount" /></td></tr>';

		        print '</tbody>';
		        print '</table>';

				dol_fiche_end();

		        print '<div class="tabsAction">';
                    // Boutons d'actions
		        	print '<input type="submit" value="'. $langs->trans("Modify") .'" name="bouton" class="butAction '. $addMoreBtns .'">';
                    print '<a onclick="history.go(-1)" class="butAction">'.$langs->trans("Cancel").'</a>';
                print '</div>';

		        print '</form>';
			}
        }
	}
}



llxFooter();

$db->close();

?>