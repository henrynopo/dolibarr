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
dol_include_once('/grh/avance_utilisateur/class/avance_utilisateur.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
$langs->load('grh@grh');
// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action  = GETPOST('action', 'alpha');
$id      = (int) $_GET['id'];
$userp = new User($db);

$now 	= new DateTime('now');
$form   = new Form($db);
$error 	= false;
$object = new avance_utilisateur($db);


if ($id) {
    $object = new avance_utilisateur($db);
    $object->fetch($id);
    if (!($object->id > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
}

if ($action == 'create' && $request_method === 'POST') {
	$avance_utilisateur = new avance_utilisateur($db);

    if (!$error) {

        $avance_utilisateur->idavance_utilisateur      = trim(GETPOST('idavance_utilisateur'));
        $date_ = explode('/', trim(GETPOST('datec')));
        $date = $date_[2]."-".$date_[1]."-".$date_[0];

        $avance_utilisateur->datec        = $date;
        $avance_utilisateur->entity       = $conf->entity;
        $avance_utilisateur->montant      = trim(GETPOST('montant'));
        // print_r($avance_utilisateur->datec);
        // die();
		$avance_utilisateurID = $avance_utilisateur->create();

	    // If no SQL error we redirect to the request card
	    if ($avance_utilisateurID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php');
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&error=SQL_Create&msg='.$avance_utilisateur->error);
	        exit;
	    }
    }
}

if ($action == 'update' && $request_method === 'POST') {
 
    $avance_utilisateur_id = (int) GETPOST('id', 'int');

    if (!$avance_utilisateur_id || $avance_utilisateur_id <= 0) {
        header('Location: ./card.php?action=request&error=update_failed');
        exit;
    }

    $avance_utilisateur = new avance_utilisateur($db);
    $isfound = $avance_utilisateur->fetch($avance_utilisateur_id);

    // If this is the requestor or has read/write rights
    if ($isfound == 1) {
        
        $date_ = explode('/', trim(GETPOST('datec')));
        $date = $date_[2]."-".$date_[1]."-".$date_[0];
        $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
        $data = array(
            'idavance_utilisateur'   => GETPOST('idavance_utilisateur'),
            'montant'         => GETPOST('montant'),
            'datec'           => $date,
            'entity'          => $entity
        );

		// Update
		$getavance_utilisateurID = $avance_utilisateur->update($avance_utilisateur_id, $data);

        if ($getavance_utilisateurID > 0) {
            header('Location: ./index.php?update='.$avance_utilisateur_id);
            exit;
        } else {
            header('Location: ./card.php?id='. $avance_utilisateur_id .'&update=0');
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    $avance_utilisateur_id = (int) GETPOST('id', 'int');

    if (!$avance_utilisateur_id || $avance_utilisateur_id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed');
        exit;
    }

	$avance_utilisateur  = new avance_utilisateur($db);
    $isfound = $avance_utilisateur->fetch($avance_utilisateur_id);

    if ($isfound == 1) {
        $avance_utilisateur->delete();
	}
	if (!$error) {
		header('Location: index.php');
		exit;
	}
	else {		
        header('Location: index.php?delete=1');
        exit;
	}
}

/* ------------------------ View ------------------------------ */

$form           = new Form($db);
$avance_utilisateur      = new avance_utilisateur($db);
$htmlother      = new FormOther($db);

//add files ( js , css , ..... ) to header
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/avance_utilisateur.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");
llxHeader(array(), $langs->trans('avance_utilisateur'),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans('Les_avances_salariés'));   // TITLE
?>
  <script>
  $( function() {
    $(".datepicker22").datepicker({
        dateFormat: 'yy-mm-dd'
    });
  } );
  </script>
<?php

// methode ajouter un element
if($action == "add"){
    // print_fiche_titre($langs->trans('Ajouter avance salarié'));
  
    ?>
        <div class="row">
                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >
                    <input type="hidden" name="action" value="create" />
                    <table class="border" width="100%">                         
                        <!-- <tr class="liste_titre">
                            <th align="center" colspan="2" ><?php echo $langs->trans('adduser'); ?> </th>
                        </tr> -->
                        <tbody>                     
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('User'); ?>   </td>
                            <td><?php print $form->select_users(null,'idavance_utilisateur',0);?></td>
                        </tr>                   
                        <tr>
                            <td><?php echo $langs->trans('Amount'); ?>   </td><td><input type="number" step="0.1" value="" name="montant" required="required" /></td>
                        </tr>                  
                        <tr>
                            <td><?php echo $langs->trans('date'); ?>   </td><td><input autocomplete="off" class="datepicker22" name="datec" required="required" /></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center">
                                <br><br>
                                <button class="button" type="submit" ><?php echo $langs->trans('Sauvg')?></button>
                                <button class="button" type="button" onclick="javascript:history.go(-1)"><?php echo $langs->trans('cancel')?></button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>
            </div>
    <?php
}

// methode modifier un element

if($action == "edit"){

 $userid = trim(GETPOST('id'));  


    ?>
        <div class="row">
                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="id" value="<?php echo $userid; ?>" />
                    <input type="hidden" name="entity" value="<?php echo $conf->entity; ?>" />
                    <table class="border" width="100%">                         
                        <!-- <tr class="liste_titre">
                            <th align="center" colspan="2" > <?php echo $langs->trans('edituser'); ?> </th>
                        </tr> -->
                        <tbody>

                        <?php
                            $avance_utilisateur = new avance_utilisateur($db);
                            $avance_utilisateur->fetch( $userid ); 
                             ?>
                        <tr>
                            <td><?php echo $langs->trans('User'); ?> </td>
                            <td><?php print $form->select_users($avance_utilisateur->idavance_utilisateur,'idavance_utilisateur',0);?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('Amount'); ?> </td>
                            <td><input type="number" step="0.1" value="<?php echo $avance_utilisateur->montant; ?>" name="montant" required="required" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('date'); ?> </td>
                            <td><input type="text" autocomplete="off" class="datepicker22" value="<?php echo date("d/m/Y", $avance_utilisateur->datec); ?>" name="datec" required="required" /></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center">
                                <br><br>
                                <button class="button" type="submit" ><?php echo $langs->trans('ok')?></button>
                                <button class="button" type="button" onclick="javascript:history.go(-1)"><?php echo $langs->trans('cancel')?></button>

                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>
            </div>
    <?php
}


// methode delete

if($action == "delete"){
 $userid = trim(GETPOST('id'));   
 print $form->formconfirm("card.php?id=$userid",$langs->trans('confirm') , $langs->trans('msg_confirm'),"confirm_delete", 'index.php', 0, 1);
 ?>

        <div class="row" >
            <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >

                <input type="hidden" name="confirm" value="no" id="confirm" />
                <input type="hidden" name="id" value="<?php echo $id; ?>" />
                <table id="table-1" class="noborder" style="min-width:100%;width:auto;">

                    <!-- <tr class="liste_titre">
                        <td colspan="2">Gestion</td>
                    </tr> -->
                    <tbody>
                    <?php
                            $avance_utilisateur = new avance_utilisateur($db);
                            $userp = new User($db);
                            $avance_utilisateur->fetch($userid);
                            $userp->fetch($avance_utilisateur->idavance_utilisateur); ?>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('User'); ?> </td>
                            <td><?php echo $userp->getNomUrl(1); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('Amount'); ?>  </td>
                            <td><?php echo $avance_utilisateur->montant; ?></td>
                        </tr>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('date'); ?>  </td>
                            <td><?php echo dol_print_date($avance_utilisateur->datec, 'day'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="float: right; margin-top: 8px;">
                    <br><br>
                    <button name="action" class="butAction" style="border:none !important" value="edit"><?php echo $langs->trans('edit'); ?> </button>
                    <button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" ><?php echo $langs->trans('Delete'); ?> </button>
                    <a href="./index.php" class="butAction"><?php echo $langs->trans('Cancel'); ?> </a>
                </div>
            </form>
        </div>
 <?php
}


// methode afficher

if($id && empty($action) ){ ?>

        <div class="row" >
            <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >

                <input type="hidden" name="confirm" value="no" id="confirm" />
                <input type="hidden" name="id" value="<?php echo $id; ?>" />
                <table id="table-1" class="noborder"  style="min-width:100%;width:auto;">

                    <!-- <tr class="liste_titre">
                        <td colspan="2">Gestion</td>
                    </tr> -->
                    <tbody>
                    <?php
                            $avance_utilisateur = new avance_utilisateur($db);
                            $userp = new User($db);
                            $avance_utilisateur->fetch($id );
                            $userp->fetch($avance_utilisateur->idavance_utilisateur); ?>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('User'); ?>  </td>
                            <td><?php echo $userp->getNomUrl(1); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('Amount'); ?>  </td>
                            <td><?php echo $avance_utilisateur->montant; ?></td>
                        </tr>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('date'); ?>  </td>
                            <td><?php echo dol_print_date($avance_utilisateur->datec, 'day'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="float: right; margin-top: 8px;">
                    <br><br>
                    <button name="action" class="butAction" style="border:none !important" value="edit"><?php echo $langs->trans('edit'); ?> </button>
                    <button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" ><?php echo $langs->trans('Delete'); ?> </button>
                    <button class="butAction" style="border:none !important" type="button" onclick="javascript:history.go(-1)"><?php echo $langs->trans('Cancel'); ?></button>
                </div>
            </form>
        </div>
    <?php
}


llxFooter();

if (is_object($db)) $db->close();

?>