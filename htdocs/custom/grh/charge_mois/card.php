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
dol_include_once('/grh/charge_mois/class/charge_mois.class.php');
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
$object = new charge_mois($db);


if ($action == 'create' && $request_method === 'POST') {
	$charge_mois = new charge_mois($db);
    $montant_autres = trim(GETPOST('montant'));
    $montant_autres = str_replace(',','.',$montant_autres);
    if (!$error) {

        //$charge_mois->id      = trim(GETPOST('id'));
        $charge_mois->discription      = trim(GETPOST('discription'));
        $date_ = explode('/', trim(GETPOST('datec')));
        $date = $date_[2]."-".$date_[1]."-".$date_[0];
        $charge_mois->datec        = trim($date);
        $charge_mois->cat          = trim(GETPOST('cat'));
        $charge_mois->entity       = $conf->entity;
        if (!empty($montant_autres))
            $charge_mois->prix          = $montant_autres;

		      $charge_moisID = $charge_mois->create();

	    // If no SQL error we redirect to the request card
	    if ($charge_moisID > 0) {
	    	//header('Location: index.php?id='.$getMarcheID);
	    	header('Location: index.php');
	        exit;
	    } else {
	        // Otherwise we display the request form with the SQL error message
	        header('Location: card.php?action=request&error=SQL_Create&msg='.$charge_mois->error);
	        exit;
	    }
    }
}

if ($action == 'update' && $request_method === 'POST') {
 
    $charge_mois_id = (int) GETPOST('id', 'int');

    if (!$charge_mois_id || $charge_mois_id <= 0) {
        header('Location: ./card.php?action=request&error=update_failed');
        exit;
    }
    $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;

    $charge_mois = new charge_mois($db);
        $date_ = explode('/', trim(GETPOST('datec')));
        $date = $date_[2]."-".$date_[1]."-".$date_[0];
        $data = array(
            'datec'        => $date,
            'discription'  => GETPOST('discription'),
            'entity'       => $entity,
        );

    if(!empty(GETPOST('montant'))){
        $montant = str_replace(',','.',GETPOST('montant'));
        $set_montant = $charge_mois->set_montant($charge_mois_id,$montant);
    }		// Update
        $getcharge_moisID = $charge_mois->update($charge_mois_id, $data);

       if ($getcharge_moisID > 0 && $set_montant > 0 ) {
            header('Location: ./index.php?update='.$charge_mois_id);
            exit;
        } else {
            header('Location: ./card.php?id='. $charge_mois_id .'&update=0');
            exit;
        }

}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    $charge_mois_id = (int) GETPOST('id', 'int');

    if (!$charge_mois_id || $charge_mois_id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$charge_mois_id);
        exit;
    }

	$charge_mois  = new charge_mois($db);
    $charge_mois->id = $charge_mois_id;
    $charge_mois->delete();

	if (!$error) {
		header('Location: index.php?delete='.$charge_mois_id);
		exit;
	}
	else {		
        header('Location: index.php?delete=1');
        exit;
	}
}

/* ------------------------ View ------------------------------ */

$form           = new Form($db);
$charge_mois      = new charge_mois($db);
$htmlother      = new FormOther($db);

//add files ( js , css , ..... ) to header
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/charge_mois_2.js","/grh/js/charge_mois_ajouter.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");
llxHeader(array(), $langs->trans('charge_mois'),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans('charge_mois'));    // TITLE
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
    ?>
        <div class="row">
                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >
                    <input type="hidden" name="action" value="create" />
                    <table class="border" width="100%">                         
                        <tr class="liste_titre">
                            <th align="center" colspan="2" ><?php echo $langs->trans('add'); ?> </th>
                        </tr>
                        <tbody>                  
                        <tr>
                            <td><?php echo $langs->trans('cat'); ?>  : </td>
                            <td>
                            <?php 
                            print $form->selectarray('cat',$charge_mois->get_cat(), -1, 1, 0, 0);
                            ?>
                            <a href="./types/card.php?action=add" class="butAction" >Ajouter une Catégorie</a>
                            </td>
                        </tr>        
                        <tr>
                            <td><?php echo $langs->trans('date'); ?>   </td><td><input autocomplete="off" class="datepicker22" name="datec" required="required" /></td>
                        </tr>                      
                        <tr class="montant_autres">
                            <td><?php echo $langs->trans('montant'); ?>  </td><td><input type="number" step="any" value="" name="montant" id="mont" class="montant" /></td>
                        </tr>                  
                        <tr>
                            <td><?php echo $langs->trans('discription'); ?>   </td><td><textarea name="discription" style="width:80%;"></textarea></td>
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
 $userid = trim(GETPOST('id'));  ?>
        <div class="row">
                <?php
                    $charge_mois = new charge_mois($db);
                    $item = $charge_mois->fetch($userid);
                ?>                        
                <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="id" value="<?php echo $userid; ?>" />
                    <input type="hidden" name="entity" value="<?php echo $item->entity; ?>" />
                    <table class="border" width="100%">                         
                        <tr class="liste_titre">
                            <th align="center" colspan="2" > <?php echo $langs->trans('edit'); ?> </th>
                        </tr>
                        <tbody>
                        <tr>
                            <td><?php echo $langs->trans('cat'); ?>   </td>
                            <td><?php echo $item['libele']; ?></td>
                        </tr>
                        <tr>
                            <td><?php echo $langs->trans('date'); ?>  </td>
                            <td><input type="text" value="<?php echo date("d/m/Y", strtotime($item['datec'])); ?>" autocomplete="off" class="datepicker22" name="datec" required="required" /></td>
                        </tr> 
                        <?php if (strtolower($item['libele']) == "autres") { ?>
                        <tr class="montant_modif">
                            <td style="width: 10%;"><?php echo $langs->trans('montant'); ?>  </td>
                            <td><input type="number" step="any" value="<?php echo $charge_mois->get_montant($userid); ?>" name="montant" class="montant" /></td>
                        </tr>
                        <?php } ?>                 
                        <tr>
                            <td><?php echo $langs->trans('discription'); ?>   </td>
                            <td><textarea name="discription" style="width:80%;"><?php echo $item['discription']; ?></textarea></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center">
                                <br><br>
                                <button class="button" type="submit" ><?php echo $langs->trans('edit')?></button>
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
 $id = trim(GETPOST('id'));   
 print $form->formconfirm("card.php?id=$id",$langs->trans('confirm') , $langs->trans('msg_confirm'),"confirm_delete", 'index.php', 0, 1);
 ?>

        <div class="row" >
            <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>" >

                <input type="hidden" name="confirm" value="no" id="confirm" />
                <input type="hidden" name="id" value="<?php echo $id; ?>" />
                <table class="noborder"  style="min-width:100%;width:auto;">                        
                        <tr class="liste_titre">
                            <th align="center" colspan="2" > <?php echo $langs->trans('charge_mois'); ?> </th>
                        </tr>
                   <tbody>

                    <?php
                            $charge_mois = new charge_mois($db);
                            $item = $charge_mois->fetch($id);
                    ?>
                        <tr>
                            <td><?php echo $langs->trans('cat'); ?>   </td>
                            <td><?php echo $item['libele']; ?></td>
                        </tr>                 
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('date'); ?>  </td>
                            <td><?php echo date("d/m/Y", strtotime($item['datec'])); ?></td>
                        </tr>

                        <?php if (strtolower($item['libele']) == "autres") { ?>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('montant'); ?>  </td>
                            <td><?php echo $charge_mois->get_montant($id); ?></td>
                        </tr>
                        <?php } ?>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('discription'); ?>  </td>
                            <td><?php echo $item['discription']; ?></td>
                        </tr>
                    </tbody>
                </table>
                <div style="float: right; margin-top: 8px;">
                    <button name="action" class="butAction" style="border:none !important" value="edit"><?php echo $langs->trans('edit'); ?> </button>
                    <button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" ><?php echo $langs->trans('Delete'); ?> </button>
                    <button class="button" type="button" onclick="javascript:history.go(-1)"><?php echo $langs->trans('cancel')?></button>
                    
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

                <div style="float: right; margin-top: 8px;">
                    <button name="action" class="butAction" style="border:none !important" value="edit"><?php echo $langs->trans('edit'); ?> </button>
                    <button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" ><?php echo $langs->trans('Delete'); ?> </button>
                    <button class="butAction" type="button" style="border:none !important" onclick="javascript:history.go(-1)"><?php echo $langs->trans('cancel')?></button>

                </div>
            <br>
            <br>
            <br>

                <table class="border"  style="min-width:100%;width:auto;">
                        
                        <tr class="liste_titre">
                            <th align="center" colspan="2" > <?php echo $langs->trans('charge_mois'); ?> </th>
                        </tr>
                    <tbody>
                    <?php
                            $charge_mois = new charge_mois($db);
                            $item = $charge_mois->fetch($id);
                    ?>
                        <tr>
                            <td><?php echo $langs->trans('cat'); ?>   </td>
                            <td><?php echo $item['libele']; ?></td>
                        </tr>                 
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('date'); ?>  </td>
                            <td><?php echo date("d/m/Y", strtotime($item['datec'])); ?></td>
                        </tr>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('discription'); ?>  </td>
                            <td><?php echo $item['discription']; ?></td>
                        </tr>
                        <?php if (strtolower($item['libele']) == "autres") { ?>
                        <tr>
                            <td style="width: 10%;"><?php echo $langs->trans('montant'); ?>  </td>
                            <td><?php echo $charge_mois->get_montant($id); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </form>
            <br>
<?php 
    if (strtolower($item['libele']) != "autres") {
        print '<form method="post" action="charge.php" >';
        print '<a href="charge.php?action=add&id='.$id.'" style="float:left" class="butAction" >'.$langs->trans("Add").'</a><br><br><br>';
        print '<table class="noborder"  style="width:100%;">';
        print '<tr class="liste_titre">';
            print '<th align="center" colspan="6" ><b>'.$langs->trans('charge_mois').'</b></th>';
        print '<tr class="liste_titre">';
            print '<th align="center" >'.$langs->trans('Ref').'</th>';
            print '<th align="center" >'.$langs->trans('date').'</th>';
            print '<th align="center" >'.$langs->trans('bon').'</th>';
            print '<th align="center" >'.$langs->trans('prix').'</th>';
            print '<th align="center" >'.$langs->trans('Quantity').'</th>';
            print '<th align="center" >'.$langs->trans('total').'</th>';
        print '</tr>';
        print '<tbody>';
            $charge_mois = new charge_mois($db);
            $item = $charge_mois->get_charge($id);
            $ttotal;
        for($i=0 ; $i<count($item) ; $i++){
            print '<tr>';
                print '<td align="center" >'.$charge_mois->getNomUrl2(1,  $item[$i]['id_entre'], $i+1).'</td>';
                print '<td align="center" >'.date("d-m-Y", strtotime($item[$i]['date_entre'])).'</td>';
                print '<td align="center" >'.$item[$i]['bon'].'</td>';
                print '<td align="center" >'.number_format($item[$i]['prix'],2).'</td>';
                print '<td align="center" >'.$item[$i]['quantity'].'</td>';
                $ttotal += ($item[$i]['prix'] * $item[$i]['quantity'] );

                print '<td align="center" >'. number_format($item[$i]['prix'] * $item[$i]['quantity'] ,2) .'</td>';
            print '</tr>';
        }
            print '<tr>';
                print '<td align="center" colspan="4"></td>';
                print '<td align="center" >'.$langs->trans('total').'</td>';
                print '<td align="center" >'.number_format($ttotal,2) .'</td>';
            print '</tr>';
        print '</tbody>';
        print '</form>';
    }
?>
        </div>
    <?php
}


llxFooter();

if (is_object($db)) $db->close();

?>