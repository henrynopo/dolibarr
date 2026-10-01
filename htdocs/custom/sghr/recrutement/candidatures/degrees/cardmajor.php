<?php

if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 

global $conf;
if (!$conf->recrutement->enabled) {
    accessforbidden();
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

dol_include_once('/sghr/recrutement/class/candidatures.class.php');
dol_include_once('/sghr/recrutement/lib/recrutement.lib.php');

$langs->load('recrutement@sghr');
$modname = $langs->trans("degrees");


$form           = new Form($db);

$var                = true;
$sortfield          = ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder          = ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id                 = (int) $_GET['id'];
$action             = $_GET['action'];
$action             = GETPOST('action');
$id                 = GETPOST('id');
$id_degree          = GETPOST('id_degree');


if(!empty($id)){
    $object = new rect_majors($db);
    $object->fetch($id);
    if (!($object->rowid > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 



$error  = false;
if (!$user->rights->sghr->rec->read) {
    accessforbidden();
}

if($action == "add") {
    if (!$user->rights->sghr->rec->write) {
        accessforbidden();
    }
}
if($action == "edit") {
    if (!$user->rights->sghr->rec->write) {
        accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->sghr->rec->delete) {
        accessforbidden();
    }
}


if ($action == 'create') {
    $backtopage = GETPOST('backtopage');
    
    $object = new rect_majors($db);
    $data = array(
        'label'       =>  GETPOST('label'),
        'fk_degree'   =>  $id_degree,
        'entity'      =>  $conf->entity,

    );
    $insertid = $object->create($data);

    
    // If no SQL error we redirect to the request card
    if ($insertid > 0 ) {
        if($backtopage){
            header('Location:'. $backtopage);
        }else
            header('Location: ./indexmajor.php?id_degree='.$id_degree.'&page='. $page);
        exit;
    } 
    else {
        header('Location: cardmajor.php?id_degree='.$id_degree.'&action=request&error=SQL_Create&msg='.$pointctrl->error);
        exit;
    }
}

if ($action == 'update') {
    
    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;

    $object = new rect_majors($db);
    $object->fetch($id);

    $backtopage = GETPOST('backtopage');
    $data = array(
        'fk_degree'   =>  $id_degree,
        'label'       =>  GETPOST('label'),
        'entity'      =>  $entity,
    );
    $avanc = $object->update($id,$data);

    // If no SQL error we redirect to the request card
    if ($avanc > 0 ) {
        if($backtopage){
            header('Location:'. $backtopage);
        }else
            header('Location: ./indexmajor.php?id_degree='.$id_degree.'&page='. $page);
        exit;
    } 
    else {
        header('Location: cardmajor.php?id_degree='.$id_degree.'&action=request&error=SQL_Create&msg='.$object->error);
        exit;
    }
}
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./cardmajor.php?id_degree='.$id_degree.'&action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $objet = new rect_majors($db);
    $objet->fetch($id);

    $error = $objet->delete();

    if ($error == 1) {
        header('Location: indexmajor.php?id_degree='.$id_degree.'&delete='.$id.'&page='.$page);
        exit;
    }
    else {      
        header('Location: card.php?id_degree='.$id_degree.'&delete=1&page='.$page);
        exit;
    }
}



$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);
print_fiche_titre($modname);

$head = menu_degrees($id_degree);
dol_fiche_head($head, 'majors', $langs->trans('major'), -1, 'generic');

$degree = new rect_degrees($db);
$degree->fetch($id_degree);
// die("Traitement en cour ...");


$object   = new rect_degrees($db);
$object->fetch($id_degree);

if($id){
    $objmajor = new rect_majors($db);
    $objmajor->fetch($id);

    $linkback = '<a href="./indexmajor.php?id_degree='.$id_degree.'&page='.$page.'">'.$langs->trans("BackToList").'</a>';
    print $object->showNavigations($objmajor, $linkback);
}



print '<div id="carddegree">';
    if($action == "add"){
        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_degrees">';
            print '<input type="hidden" name="action" value="create" />';
            print '<input type="hidden" name="page" value="'.$page.'" />';
            print '<input type="hidden" name="id_degree" value="'.$id_degree.'" />';
            print '<input type="hidden" name="backtopage" value="'.$backtopage.'" />';

            print '<table class="border nc_table_" width="100%">';

                print '<tr>';
                    print '<td class="titlefieldcreate" >'.$langs->trans('degree').'</td>';
                    print '<td>';
                        print '<input type="text" style="width:100%" disabled name="fk_degree" value="'.$degree->label.'">';
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td class="titlefieldcreate" >'.$langs->trans('Label').'</td>';
                    print '<td>';
                        print '<input type="text" style="width:100%" name="label">';
                    print '</td>';
                print '</tr>';

            print '</table>';

            print '<div style="clear:both"></div>';

            // Actions
            print '<br>';
            print '<div class="center">';
                    print '<input type="submit" id="submitform" value="'.$langs->trans('Validate').'" name="bouton" class="button" />';
                    print '<input type="button" value="'.$langs->trans('Cancel').'" class="button" onclick="history.go(-1)">';
            print '</div>';

        print '</form>';
    }



    if($action == "edit"){

        $object = new rect_degrees($db);
        $object->fetch($id_degree);

        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_degrees">';
            print '<input type="hidden" name="action" value="update" />';
            print '<input type="hidden" name="id" value="'.$id.'" />';
            print '<input type="hidden" name="id_degree" value="'.$id_degree.'" />';
            print '<input type="hidden" name="page" value="'.$page.'" />';
            print '<input type="hidden" name="entity" value="'.$object->entity.'" />';
            print '<input type="hidden" name="backtopage" value="'.$backtopage.'" />';

            print '<table class="border nc_table_" width="100%">';

                print '<tr>';
                    print '<td class="titlefieldcreate" >'.$langs->trans('degree').'</td>';
                    print '<td>';
                        print '<input type="text" style="width:100%" disabled name="fk_degree" value="'.$degree->label.'">';
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td class="titlefieldcreate" >'.$langs->trans('Label').'</td>';
                    print '<td>';
                        print '<input type="text" style="width:100%" value="'.$objmajor->label.'" name="label">';
                    print '</td>';
                print '</tr>';

            print '</table>';

            print '<div style="clear:both"></div>';

            // Actions
            print '<br>';

            print '<div class="center">';
                print '<input type="submit" id="submitform" value="'.$langs->trans('Validate').'" name="bouton" class="button" />';
                print '<input type="button" value="'.$langs->trans('Cancel').'" class="button" onclick="history.go(-1)">';
            print '</div>';

        print '</form>';
    }

    if( ($id && empty($action)) || $action == "delete"){

        if($action == "delete")
            print $form->formconfirm("cardmajor.php?id=".$id."&page=".$page.'&id_degree='.$id_degree,$langs->trans('Confirmation') , $langs->trans('ConfirmDeleteObject'),"confirm_delete",    'indexmajor.php?id_degree='.$id_degree.'&page='.$page, 0, 1);


        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_reservsall">';

            $linkback = '<a href="./index.php?page='.$page.'">'.$langs->trans("BackToList").'</a>';
            // print $pointcontrol->showNavigations($object, $linkback);

            print '<input type="hidden" name="action" value="update" />';
            print '<input type="hidden" name="id" value="'.$id.'" />';
            print '<input type="hidden" name="page" value="'.$page.'" />';
            print '<input type="hidden" name="confirm" itemue="no" id="confirm" />';

            print '<input type="hidden" name="backtopage" value="'.$backtopage.'" />';

            print '<table class="border tableforfield" width="100%">';
                
                print '<tr>';
                    print '<td class="titlefieldcreate" >'.$langs->trans('degree').'</td>';
                    print '<td>'.$object->label.'</td>';
                print '</tr>';

                print '<tr>';
                    print '<td class="titlefieldcreate" >'.$langs->trans('Label').'</td>';
                    print '<td>'.$objmajor->label.'</td>';
                print '</tr>';

            print '</table>';

            print '<div style="clear:both"></div>';
            print '<br>';
            // Actions
            print '<table class="" width="100%">';
                print '<tr>';
                    print '<td colspan="2" align="right">';
                    print '<a href="./cardmajor.php?id='.$id.'&id_degree='.$id_degree.'&action=edit" class="butAction">'.$langs->trans('Modify').'</a>';
                    print '<a href="./indexmajor.php?id_degree='.$id_degree.'&page='.$page.'" class="butAction">'.$langs->trans('annuler').'</a>';
                    print '<a href="./cardmajor.php?id='.$id.'&id_degree='.$id_degree.'&action=delete" class="butActionBTNC butActionDelete">'.$langs->trans('Delete').'</a></td>';
                print '</tr>';
            print '</table>';

        print '</form>';
    }

print '</div>';

// End of page
llxFooter();
$db->close();