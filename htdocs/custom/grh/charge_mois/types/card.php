<?php 

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/charge_mois/class/charge_mois.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
$langs->load('grh@grh');
// Get parameters
$charge_mois_cat  = new charge_mois_cat($db);
$form           = new Form($db);
$request_method     = $_SERVER['REQUEST_METHOD'];
$action             = GETPOST('action', 'alpha');
$id                 = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;
$error              = false;

if ($action == 'create' && $request_method === 'POST') {

    $charge_mois_cat->libele = GETPOST('name');

    $avance = $charge_mois_cat->create();

    if ($avance > 0) {
        header('Location: index.php');
        exit;
    } else {
        // Otherwise we display the request form with the SQL error message
        header('Location: card.php?action=request&error=SQL_Create&msg='.$gestion_carriere->error);
        exit;
    }
}

if ($action == 'update' && $request_method === 'POST') {
  
    $data_carriere = array(
        'libele'    => GETPOST('name')
    );
    $isvalid = $charge_mois_cat->update($id, $data_carriere);

    if ($isvalid > 0) {
        header('Location: index.php');
        exit;
    } else {
        header('Location: ./card.php?id='. $id .'&action=edit');
        exit;
    }
}

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=delete_failed&id='.$id);
        exit;
    }

    $charge_mois_cat->id_cat = $id;
    $charge_mois_cat->delete();

    if (!$error) {
        header('Location: index.php');
        exit;
    }else{      
        header('Location: card.php?delete=1');
        exit;
    }
}

$js = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js","/includes/jquery/plugins/timepicker/datetimer.js", "gestion_carriere/js/gestion_carriere.js");

switch ($action) {
    case 'add':
        $the_title = $langs->trans('Gestion_des_Catégories');
        break;
    case 'edit':
        $the_title = $langs->trans('Gestion_des_Catégories');
        break;
    case 'delete':
        $the_title = $langs->trans('Gestion_des_Catégories');
        break;
    default:
        $the_title = $langs->trans('Gestion_des_Catégories');
        break;
}
llxHeader(array(), $langs->trans($the_title),'','','','',$js,0,0);
// print_fiche_titre($langs->trans('add_cheques'));


// methode ajouter un element
if($action == "add"){
    // print_barre_liste($langs->trans('AddChantier'), "","", '', "", "", "", "", "", 'object_.png');
    // print_barre_liste($langs->trans($the_title));
    print_fiche_titre($langs->trans($the_title));
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
    print '<input type="hidden" name="action" value="create" />';
    print '<input type="hidden" id="sortie_count" name="sortie_count" value="1" />';
    print '<input type="hidden" id="depens_count" name="depens_count" value="1" />';

    print '<table class="border" width="100%">';
    // print '<tr class="liste_titre">';
    //     print '<td align="center" colspan="2">'.$langs->trans('add').'</td>';
    // print '</tr>';
    print '<tr>';
        print '<td width="20%">'.$langs->trans('Libellé').'</td>';
        print '<td><input required="required" type="text" name="name" style="font-weight: 700;width:90%;" /></td>';
    print '</tr>';
    print '</table><div class="clear"></div>';
    print '<div>';
        print '<input style="font-weight: 700;" type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction" />&nbsp;&nbsp;';
        // print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
         print '<a style="font-weight: 700;" href="./index.php" class="butAction">'.$langs->trans("Cancel").'</a>';
    print '</div>';
    print '</form>';
}

// methode modifier un element
if($action == "edit"){
    $charge_mois_cat->fetchAll('','',0,0,' and id_cat = '.$id);
        $item = $charge_mois_cat->rows[0];
    print_barre_liste($langs->trans($the_title), "","", '', "", "", "", "", "", 'object_Ch_.png');
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';

        
  
    print '<table class="border" width="100%">';
    // print '<tr class="liste_titre">';
    //     print '<td align="center" colspan="2">'.$langs->trans('Modifier').'</td>';
    // print '</tr>';
    print '<tr>';
        print '<td>'.$langs->trans('Label').'</td>';
        print '<td><input  required="required" type="text" name="name" style="width:90%" value="'.$item->libele.'" /></td>';
    print '</tr>';
    print '</table>';
    print '<div>';
    print '<button style="font-weight: 700;" type="submit" class="butAction" value="edit">'.$langs->trans('Update').'</button>&nbsp;&nbsp;';
        // print '<input style="font-weight: 700;" type="submit" value="'.$langs->trans('edit').'" class="butAction" />&nbsp;&nbsp;';
        // print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
         print '<a style="font-weight: 700;" href="./index.php" class="butAction">'.$langs->trans("Cancel").'</a>';
    print '</div>';
    print '</form>';
}

// methode delete
if($action == "delete"){
  
    print_barre_liste($langs->trans($the_title), "","", '', "", "", "", "", "", 'object_.png');
    print $form->formconfirm("card.php?id=".$id,$langs->trans('confirm') , $langs->trans('msg_confirm'),"confirm_delete", 'index.php', 0, 1);

    $charge_mois_cat->fetchAll('','',0,0,' and id_cat = '.$id);
    $item = $charge_mois_cat->rows[0];

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
    print '<input type="hidden" name="confirm" value="no" id="confirm" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';

       
    print '<table class="border" width="100%">';
    // print '<tr class="liste_titre">';
    //     print '<td align="center" colspan="2">'.$langs->trans('Affichage').'</td>';
    // print '</tr>';
    print '<tr>';
        print '<td>'.$langs->trans('Label').'</td>';
        print '<td>'.$item->libele.'</td>';
    print '</tr>';
    print '</table>';

    print '<div>';
        print '<td colspan="2">';
            print '<button style="font-weight: 700;" name="action" class="butAction" value="edit">'.$langs->trans('edit').'</button>&nbsp;&nbsp;';
            print '<button style="font-weight: 700;" name="action" class="butActionBTNC butActionDelete" value="delete" >'.$langs->trans('Delete').'</button>&nbsp;&nbsp;';
            // print '<a href="./index.php" class="butAction">'.$langs->trans('Cancel').'</a>';
             print '<a style="font-weight: 700;" href="./index.php" class="butAction">'.$langs->trans("Cancel").'</a>';
        print '</td>';
    print '</div>';
    print '</table>';
    print '</form>';
}

// methode afficher
if($id && empty($action) ){

    $charge_mois_cat->fetchAll('','',0,0,' and id_cat = '.$id);
    $item = $charge_mois_cat->rows[0];
     print_barre_liste($langs->trans($the_title), "","", '', "", "", "", "", "", 'object_.png');
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" >';
    print '<input type="hidden" name="confirm" value="no" id="confirm" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    
  
    print '<table class="border" width="100%">';
    // print '<tr class="liste_titre">';
    //     print '<td align="center" colspan="2">'.$langs->trans('Affichage').'</td>';
    // print '</tr>';
    print '<tr>';
        print '<td>'.$langs->trans('Label').'</td>';
        print '<td>'.$item->libele.'</td>';
    print '</tr>';
    print '</table>';

    print '<div>';
        print '<td colspan="2">';
            print '<button style="font-weight: 700;" name="action" class="butAction" value="edit">'.$langs->trans('edit').'</button>&nbsp;&nbsp;';
            print '<button style="font-weight: 700;" name="action" class="butActionBTNC butActionDelete" value="delete" >'.$langs->trans('Delete').'</button>&nbsp;&nbsp;';
            // print '<a href="./index.php" class="butAction">'.$langs->trans('Cancel').'</a>';
             print '<a style="font-weight: 700;" href="./index.php" class="butAction">'.$langs->trans("Cancel").'</a>';
        print '</td>';
    print '</div>';
    print '</table>';
    print '</form>';
}

llxFooter();

if (is_object($db)) $db->close();

?>