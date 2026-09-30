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
dol_include_once('/grh/cnss_utilisateur/class/cnss_utilisateur.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action  = GETPOST('action', 'alpha');
$id      = (int) $_GET['id'];

$now 	= new DateTime('now');
$form   = new Form($db);
$error 	= false;
$object = new cnss_utilisateur($db);


if ($action == 'create' && $request_method === 'POST') {
	$cnss_utilisateur = new cnss_utilisateur($db);

    if (!$error) {

        $cnss_utilisateur->idutilisateur      = trim(GETPOST('idutilisateur'));
        $date_ = explode('/', trim(GETPOST('datec')));
        $date = $date_[2]."-".$date_[1]."-".$date_[0];
        $cnss_utilisateur->datec        = trim($date);
        $cnss_utilisateur->montant_cnss      = trim(GETPOST('montant_cnss'));
        $cnss_utilisateur->entity  = $conf->entity;
		$cnss_utilisateurID = $cnss_utilisateur->create();

	    // If no SQL error we redirect to the request card
	    if ($cnss_utilisateurID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php');
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&error=SQL_Create&msg='.$cnss_utilisateur->error);
	        exit;
	    }
    }
}

if ($action == 'update' && $request_method === 'POST') {
 
    $cnss_utilisateur_id = (int) GETPOST('id', 'int');

    if (!$cnss_utilisateur_id || $cnss_utilisateur_id <= 0) {
        header('Location: ./card.php?action=request&error=update_failed');
        exit;
    }

    $cnss_utilisateur = new cnss_utilisateur($db);
    $isfound = $cnss_utilisateur->fetch($cnss_utilisateur_id);
    $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
    // If this is the requestor or has read/write rights
    if ($isfound == 1) {
        
        $date_ = explode('/', trim(GETPOST('datec')));
        $date = $date_[2]."-".$date_[1]."-".$date_[0];
        $data = array(
            'idutilisateur'  => GETPOST('idutilisateur'),
            'montant_cnss'   => GETPOST('montant_cnss'),
            'datec'          => $date,
            'entity'         => $entity
        );

		// Update
		$getutilisateurID = $cnss_utilisateur->update($cnss_utilisateur_id, $data);

        if ($getutilisateurID > 0) {
            header('Location: ./card.php?id='.$cnss_utilisateur_id);
            exit;
        } else {
            header('Location: ./card.php?id='. $cnss_utilisateur_id .'&update=0');
            exit;
        }
    }
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    $cnss_utilisateur_id = (int) GETPOST('id', 'int');

    if (!$cnss_utilisateur_id || $cnss_utilisateur_id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed');
        exit;
    }

	$cnss_utilisateur  = new cnss_utilisateur($db);
    $isfound = $cnss_utilisateur->fetch($cnss_utilisateur_id);

    if ($isfound == 1) {
        $cnss_utilisateur->delete();
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
$cnss_utilisateur      = new cnss_utilisateur($db);
$htmlother      = new FormOther($db);

//add files ( js , css , ..... ) to header
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", 
                 "grh/js/cnss_utilisateur.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");
llxHeader(array(), $langs->trans('utilisateur'),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans('gere'));   // TITLE
// methode ajouter un element
if($action == "add"){    
    ?>
    <script type="text/javascript">
        $('#idutilisateur').change(function(){
        check_user();
        });
    </script>
        <div class="row">
                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >
                    <input type="hidden" name="action" value="create" />
                    <table class="table" width="100%">                         
                        <!-- <tr class="liste_titre">
                            <th align="center" colspan="2" > <?php echo $langs->trans('adduser'); ?> </th>
                        </tr> -->
                        <tbody>                     
                        <tr>
                            <td width="20%"><?php echo $langs->trans('User'); ?></td>
                            <td><?php print $form->select_users(null,'idutilisateur',0);?></td>
                        </tr>                   
                        <tr>
                            <td><?php echo $langs->trans('montant_cnss'); ?></td><td><input type="number" step="0.1" value="" name="montant_cnss" required="required" /></td>
                        </tr>                  
                        <tr>
                            <td><?php echo $langs->trans('Date'); ?> </td><td><input type="text" autocomplete="off" class="datepicker22 checkd" name="datec" required="required" /></td>
                        </tr>
                        <tr>
                            <td colspan="2"align="center">
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
                    <?php
                    $cnss_utilisateur = new cnss_utilisateur($db);
                    $cnss_utilisateur->fetch( $userid ); ?>
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="id" value="<?php echo $userid; ?>" />
                    <input type="hidden" id="id_utilisateur" value="<?php echo $cnss_utilisateur->idutilisateur; ?>" />
                    <input type="hidden" id="entity" value="<?php echo $cnss_utilisateur->entity; ?>" />
                    <table class="border" width="100%">                         
                        <!-- <tr class="liste_titre">
                            <th align="center" colspan="2" ><?php echo $langs->trans('edituser'); ?></th>
                        </tr> -->
                        <tbody>

                        
                        <tr>
                            <td width="20%"><?php echo $langs->trans('User'); ?></td>
                            <td><?php print $form->select_users($cnss_utilisateur->idutilisateur,'idutilisateur',0);?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('montant_cnss'); ?>  </td>
                            <td><input type="number" step="0.1" value="<?php echo $cnss_utilisateur->montant_cnss; ?>" name="montant_cnss" required="required" /></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('Date'); ?>  </td>
                            <td><input type="text" autocomplete="off" class="datepicker22 checkd" value="<?php echo date("d/m/Y", $cnss_utilisateur->datec); ?>" name="datec" required="required" /></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center">
                                <br><br>
                                <button class="button valider" type="submit" ><?php echo $langs->trans('Sauvg')?></button>
                                <button class="button" type="button" onclick="javascript:history.go(-1)"><?php echo $langs->trans('cancel')?></button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </form>
            </div>
    <?php
}
?>
<script type="text/javascript">
    $('.checkd').change(function(){
       check_user();
    });
    // $('#idutilisateur').change(function(){
    //   check_user();
    // });
    function check_user(){
        date = $('.checkd').val();
        // console.log(date);
        user = $('#idutilisateur').val();
        action1 = "<?php echo $action; ?>";
        if(date !="" && user!=-1){
            <?php if ($action == "edit") { ?>
                var data = {
                  'role' : 'check_user',
                  'date': date,
                  'user': user,
                  'action': "edit",
                  'rowid': <?php echo $cnss_utilisateur->id; ?>,
                };
            <?php } else if($action == "add"){ ?>
                var data = {
                  'role' : 'check_user',
                  'date': date,
                  'user': user,
                  'action': "add"
                };
            <?php } ?>
            // console.log(data);
            $.ajax({
              type: "POST",
              url: "../cnss_utilisateur/check.php",
              data: data,
              dataType: 'json',
              success: function(found){
                // console.log(found);
                if(found == 1){
                        $('.valider').attr("disabled","disabled");
                        alert('L\'utilisateur sélectionné est déjà pointé sur ce mois !');
                    $('.checkd').val('');
                }else{
                    $('.valider').removeAttr("disabled");
                }
              }
            });
            // console.log(data);
        }
    }
</script>
<?php
// methode delete

if($action == "delete"){
 $userid = trim(GETPOST('id'));   
 print $form->formconfirm("card.php?id=$userid",$langs->trans('confirm') ,$langs->trans('msg_confirm'),"confirm_delete", 'index.php', 0, 1);
 ?>

        <div class="row" >
            <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >

                <input type="hidden" name="confirm" value="no" id="confirm" />
                <input type="hidden" name="id" value="<?php echo $id; ?>" />
                <table id="table-1" class="border" width="100%" >

                    <!-- <tr class="liste_titre">
                        <td colspan="2"><?php echo $langs->trans('gere'); ?></td>
                    </tr> -->
                    <tbody>
                    <?php
                            $cnss_utilisateur = new cnss_utilisateur($db);
                            $userp = new User($db);
                            $cnss_utilisateur->fetch($userid);
                            $userp->fetch($cnss_utilisateur->idutilisateur); ?>
                        <tr>
                            <td width="20%"><?php echo $langs->trans('User'); ?></td>
                            <td><?php echo $userp->getNomUrl(1); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('montant_cnss'); ?> </td>
                            <td><?php echo $cnss_utilisateur->montant_cnss; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('Date'); ?> </td>
                            <td><?php echo date("Y-m-d", $cnss_utilisateur->datec); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="float: right; margin-top: 8px;">
                    <br><br>
                    <button name="action" class="butAction" style="border:none !important" value="edit"><?php echo $langs->trans('Update'); ?></button>
                    <button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" ><?php echo $langs->trans('Delete'); ?></button>
                    <button class="butAction" type="button" style="border:none !important" onclick="javascript:history.go(-1)" ><?php echo $langs->trans('cancel')?></button>
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
                <table id="table-1" class="border" width="100%">

                    <!-- <tr class="liste_titre">
                        <td colspan="2"><?php echo $langs->trans('gere'); ?></td>
                    </tr> -->
                    <tbody>
                    <?php
                            $cnss_utilisateur = new cnss_utilisateur($db);
                            $userp = new User($db);
                            $cnss_utilisateur->fetch($id );
                            $userp->fetch($cnss_utilisateur->idutilisateur); ?>
                        <tr>
                            <td width="20%"><?php echo $langs->trans('User'); ?> </td>
                            <td><?php echo $userp->getNomUrl(1); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('montant_cnss'); ?> </td>
                            <td><?php echo number_format($cnss_utilisateur->montant_cnss,2,","," "); ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('Date'); ?> </td>
                            <td><?php echo date("Y-m-d", $cnss_utilisateur->datec); ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="float: right; margin-top: 8px;">
                    <br><br>
                    <button name="action" class="butAction" style="border:none !important" value="edit"><?php echo $langs->trans('Update'); ?></button>
                    <button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" ><?php echo $langs->trans('Delete'); ?></button>
                    <button class="butAction" type="button" style="border:none !important" onclick="javascript:history.go(-1)" ><?php echo $langs->trans('cancel')?></button>
                </div>
            </form>
        </div>
    <?php
}


llxFooter();

if (is_object($db)) $db->close();

?>