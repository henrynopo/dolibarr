<?php 
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/utilisateur_info/class/utilisateur_info.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$moduleName = "Infos des salariés";
// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action  = GETPOST('action', 'alpha');
$form   = new Form($db);
$htmlother      = new FormOther($db);

$user_info  = new utilisateur_info($db);
$user_id = GETPOST('user_id');
$user_id      = (int) ( (!empty($_GET['user_id'])) ? $_GET['user_id'] : GETPOST('user_id') ) ;


/* ------------------------ View ------------------------------ */
$actions = [
    'update'=>'update.php',
];

if(array_key_exists($action, $actions)){
    $abs = true;
    require_once $actions[$action];
}

if ($action == 'update' && $request_method === 'POST') {
    call_user_func('function_'.$action);
}

$morejs = array("/gestion_plan/js/functions.js");
$morecss = array("/gestion_plan/card/plan/assets/css/main.css");
llxHeader(array(), $langs->trans($moduleName),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans($moduleName));    // TITLE

$abs = false;


// methode modifier un element
if($action == "edit"){
    require_once $actions["update"];
}

?>
<script>
    $('#select_spot').select2();
</script>
<style>
    #s2id_select_spot{
        width: 23% !important;
    }
    #num_pv{width: 23% !important;}
</style>
<?php 
llxFooter();

if (is_object($db)) $db->close();
?>
