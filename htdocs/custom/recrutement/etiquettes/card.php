<?php 

if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/recrutement/class/etiquettes.class.php');
dol_include_once('/core/class/html.form.class.php');



$langs->load('recrutement@recrutement');

$modname = $langs->trans("etiquette");
// Initial Objects
$etiquette = new etiquettes($db);
$poste       = new postes($db);
$form        = new Form($db);
// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;


if(!empty($id)){
    $object = new etiquettes($db);
    $object->fetch($id);
    if (!($object->rowid > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 


$error  = false;
if (!$user->rights->recrutement->lire) {
    accessforbidden();
}

if(in_array($action, ["add","edit"])) {
    if (!$user->rights->recrutement->creer) {
      accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->recrutement->supprimer) {
      accessforbidden();
    }

}

// ------------------------------------------------------------------------- Actions "Create/Update/Delete"
if ($action == 'create' && $request_method === 'POST') {
    require_once 'z-actions/create.php';
}

if ($action == 'update' && $request_method === 'POST') {
    require_once 'z-actions/edit.php';
}

// If delete of request
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/show.php';
}

if ($action == 'confirm_deconstruction' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/edit.php';
}

if ($action == 'confirm_rebut' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/edit.php';
}


$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);
print_fiche_titre($modname);


    // die($action);
    // ------------------------------------------------------------------------- Views
    if($action == "add")
        require_once 'z-actions/create.php';

    if($action == "edit")
        require_once 'z-actions/edit.php';

    if( ($id && empty($action)) || $action == "delete" )
        require_once 'z-actions/show.php';

    ?>


<script>
    
    $(document).ready(function(){
        $("#date").datepicker({
            dateFormat: "dd/mm/yy"
        });
        $("#date_d").datepicker({
            dateFormat: "dd/mm/yy"
        });
    });
        
</script>
<?php

llxFooter();

if (is_object($db)) $db->close();
?>