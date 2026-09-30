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
    $nbr_charge      = (int) GETPOST('nbr_charge');
    $id_charge      = (int) GETPOST('id');
    for($i=0 ; $i <$nbr_charge ; $i++ ){
    	$charge_mois = new charge_mois($db);
        $charge_mois->id_charge    = trim(GETPOST('id'));
        $charge_mois->datec        = trim(GETPOST('datec')[$i]);
        $charge_mois->bon          = trim(GETPOST('bon')[$i]);
        $charge_mois->prix         = trim(GETPOST('prix')[$i]);
        $charge_mois->quantity     = trim(GETPOST('quantity')[$i]);

		$charge_moisID = $charge_mois->create_charge();
    }
    // If no SQL error we redirect to the request card
    if ($charge_moisID > 0) {
    	//header('Location: index.php?id='.$getMarcheID);
    	header('Location: card.php?id='.$charge_mois->id_charge);
        exit;
    } else {
        // Otherwise we display the request form with the SQL error message
        header('Location: charge.php?action=request&error=SQL_Create&msg='.$charge_mois->error);
        exit;
    }
        
}

if ($action == 'update' && $request_method === 'POST') {
 
    $charge_mois_id = (int) GETPOST('id', 'int');
    $id_charge = GETPOST('id_charge');

    if (!$charge_mois_id || $charge_mois_id <= 0) {
        header('Location: ./charge.php?action=request&error=update_failed');
        exit;
    }
    $charge_mois = new charge_mois($db);

    $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
    $data = array(
        'date_entre' => GETPOST('datec'),
        'bon'        => GETPOST('bon'),
        'prix'       => GETPOST('prix'),
        'quantity'   => GETPOST('quantity'),
        'entity'     => $entity,
    );

	// Update
	$getcharge_moisID = $charge_mois->update_charge($charge_mois_id, $data);

    if ($getcharge_moisID > 0) {
        header('Location: ./card.php?id='.$id_charge);
        exit;
    } else {
        header('Location: ./charge.php?id='. $id_charge .'&update=0');
        exit;
    }

}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    $id = (int) $_GET['id'];
    $id_charge = $_GET['id_charge'];

    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

	$charge_mois  = new charge_mois($db);
    $charge_mois->delete_charge($id);

	if (!$error) {
		 header('Location: ./card.php?id='.$id_charge);
		exit;
	}
	else {		
        header('Location: ./charge.php?id='. $id_charge .'&update=0');
        exit;
	}
    
}

/* ------------------------ View ------------------------------ */

$form           = new Form($db);
$charge_mois      = new charge_mois($db);
$htmlother      = new FormOther($db);

//add files ( js , css , ..... ) to header
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js","/grh/js/charge_mois_ajouter.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");
llxHeader(array(), $langs->trans('charge_mois'),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans('charge_mois'));    // TITLE

// methode ajouter un element
if($action == "add"){

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
        print '<input type="hidden" name="id" value="'.$id.'" />';
        print '<input type="hidden" name="action" value="create" />';
        print '<input type="hidden" name="nbr_charge" id="nbr_charge" value="1" />';

        print '<table class="noborder" id="tblajo"  style="width:100%;">';
            print '<tr class="liste_titre">';
                print '<th align="center" colspan="6" >'.$langs->trans('charge_mois').'</th>';
            print '</tr>';
            print '<tr class="liste_titre">';
                print '<th align="center" >'.$langs->trans('date').'</th>';
                print '<th align="center" >'.$langs->trans('bon').'</th>';
                print '<th align="center" >'.$langs->trans('prix').'</th>';
                print '<th align="center" >'.$langs->trans('Quantity').'</th>';
            print '</tr>';
            print '<tbody id="tbody">';
                print '<tr >';
                    print '<td align="center"><input type="text" class="dtpick" name="datec[]" required="required" /></td>';
                    print '<td align="center"><input type="number" value="" name="bon[]" /></td>';
                    print '<td align="center"><input type="number" step="any" name="prix[]" class="prix" /></td>';
                    print '<td align="center"><input type="number" value="" name="quantity[]" class="quantity" /></td>';
                print '</tr> ';
            print '</tbody>';
        print '</table>';
        print '<input type="button" value="+" id="addtr" style="margin-top: -30px !important;   margin-right: -0.5px !important;    float: right;padding: 0 4px !important; border-radius: 8px 0 0 0; border: 0;    background: #555;   color: #fff;font-size: 1.5em;   font-weight: bold;" />';
        print '<div style="margin-top: 8px;"><br><br>';
            print '<input type="submit" value="'.$langs->trans('ok').'" name="bouton" class="butAction"/>';
            print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
        print '</div>';
    print '</form>';
    }

// methode modifier un element

if($action == "edit"){
    $id = trim(GETPOST('id')); 
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
        $charge_mois = new charge_mois($db);
        $item = $charge_mois->get_charge($id , "id_entre");
        print '<input type="hidden" name="id" value="'.$id.'" />';
        print '<input type="hidden" name="action" value="update" />';
        print '<table class="noborder"  style="width:100%;">';
            print '<tr class="liste_titre">';
                print '<th align="center" colspan="6" >'.$langs->trans('charge_mois').'</th>';
            print '</tr>';
            print '<tr class="liste_titre">';
                print '<th align="center" >'.$langs->trans('ref').'</th>';
                print '<th align="center" >'.$langs->trans('date').'</th>';
                print '<th align="center" >'.$langs->trans('bon').'</th>';
                print '<th align="center" >'.$langs->trans('prix').'</th>';
                print '<th align="center" >'.$langs->trans('Quantity').'</th>';
            print '</tr>';
            print '<tbody>';
                
                for($i=0 ; $i<count($item) ; $i++){
                    print '<tr>';
                        print '<input type="hidden" name="id_charge" value="'.$item[$i]['id_charge'].'" />';
                        print '<input type="hidden" name="entity" value="'.$item->entity.'" />';
                        print '<td align="center" >'.$item[$i]['id_entre'].'</td>';
                        print '<td align="center" >';
                        print '<input type="text" class="dtpick" value="'.date("Y-m-d", strtotime($item[$i]['date_entre'])).'" name="datec" required="required" />';
                        print '</td>';
                        print '<td align="center" ><input type="number" value="'.$item[$i]['bon'].'" name="bon" /></td>';
                        print '<td align="center" ><input type="number" step="any" value="'.$item[$i]['prix'].'" name="prix" class="prix" /></td>';
                        print '<td align="center" ><input type="number" value="'.$item[$i]['quantity'].'" name="quantity" class="quantity" /></td>';
                    print '</tr>';
                }
            print '</tbody>';
        print '</table>';
        print '<br><br>';
        print '<input type="submit" value="'.$langs->trans('ok').'" class="butAction" />';
        print '<a href="./charge.php?id='.$id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
    print '</form>';
}


// methode delete

if($action == "delete"){
 $id = trim(GETPOST('id'));   
 $id_charge = trim(GETPOST('id_charge'));   
 print $form->formconfirm("charge.php?id=$id&id_charge=$id_charge",$langs->trans('confirm') , $langs->trans('msg_confirm'),"confirm_delete", 'index.php', 0, 1);

        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
        print '<input type="hidden" name="id" value="'.$id.'" />';
        print '<input type="hidden" name="confirm" value="no" id="confirm" />';
        print '<input type="hidden" name="action" value="confirm_delete" />';

        print '<table class="noborder"  style="width:100%;">';
        print '<tr class="liste_titre">';
            print '<th align="center" colspan="6" >'.$langs->trans('charge_mois').'</th>';
        print '</tr>';
        print '<tr class="liste_titre">';
            print '<th align="center" >'.$langs->trans('ref').'</th>';
            print '<th align="center" >'.$langs->trans('date').'</th>';
            print '<th align="center" >'.$langs->trans('bon').'</th>';
            print '<th align="center" >'.$langs->trans('prix').'</th>';
            print '<th align="center" >'.$langs->trans('Quantity').'</th>';
        print '</tr>';
        print '<tbody>';
            $charge_mois = new charge_mois($db);
            $item = $charge_mois->get_charge($id , "id_entre");
            
            print '<tr>';
                print '<td align="center" >'.$item[0]['id_entre'].'</td>';
                print '<td align="center" >'.date("d-m-Y", strtotime($item[0]['date_entre'])).'</td>';
                print '<td align="center" >'.$item[0]['bon'].'</td>';
                print '<td align="center" >'.$item[0]['prix'].'</td>';
                print '<td align="center" >'.$item[0]['quantity'].'</td>';
            print '</tr>';
        print '</tbody>';
        print '</table>';
        print '<div style="margin-top: 8px;">';
            print '<br><br>';
            print '<a href="./card.php?id='.$item[0]['id_charge'].'" class="butAction">'.$langs->trans('Cancel').'</a>';
        print '</div>';
        print '</form>';
}


// methode afficher

if($id && empty($action) ){

        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
        print '<input type="hidden" name="id" value="'.$id.'" />';
        print '<input type="hidden" name="confirm" value="no" id="confirm" />';

        print '<table class="noborder"  style="width:100%;">';
        print '<tr class="liste_titre">';
            print '<th align="center" colspan="6" >'.$langs->trans('charge_mois').'</th>';
        print '</tr>';
        print '<tr class="liste_titre">';
            print '<th align="center" >'.$langs->trans('ref').'</th>';
            print '<th align="center" >'.$langs->trans('date').'</th>';
            print '<th align="center" >'.$langs->trans('bon').'</th>';
            print '<th align="center" >'.$langs->trans('prix').'</th>';
            print '<th align="center" >'.$langs->trans('Quantity').'</th>';
            print '<th align="center" >'.$langs->trans('total').'</th>';
        print '</tr>';
        print '<tbody>';
            $charge_mois = new charge_mois($db);
            $item = $charge_mois->get_charge($id , "id_entre");
            
            print '<tr>';
                print '<input type="hidden" name="id_charge" value="'.$item[0]['id_charge'].'" />';
                print '<td align="center" >'.$item[0]['id_entre'].'</td>';
                print '<td align="center" >'.date("d-m-Y", strtotime($item[0]['date_entre'])).'</td>';
                print '<td align="center" >'.$item[0]['bon'].'</td>';
                print '<td align="center" >'.$item[0]['prix'].'</td>';
                print '<td align="center" >'.$item[0]['quantity'].'</td>';
                print '<td align="center" >'.($item[0]['quantity'] * $item[0]['prix']).'</td>';
            print '</tr>';
        print '</tbody>';
        print '</table>';
        print '<div style="margin-top: 8px;">';
            print '<br><br>';
            print '<button name="action" class="butAction" style="border:none !important" value="edit">'.$langs->trans('edit').'</button>';
            print '<button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" >'.$langs->trans('Delete').'</button>';
            // print '<a href="./card.php?id='.$item[0]['id_charge'].'" class="butAction">'.$langs->trans('Cancel').'</a>';
            print '<button class="butAction" type="button" style="border:none !important" onclick="javascript:history.go(-1)">'.$langs->trans('cancel').'</button>';
        print '</div>';
        print '</form>';

?>
        </div>
    <?php
}


llxFooter();

if (is_object($db)) $db->close();

?>