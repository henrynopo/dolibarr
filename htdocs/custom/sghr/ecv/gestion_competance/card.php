<?php 

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/competances.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/ecv/lib/ecv.lib.php');

$langs->load('ecv@sghr');

$modname = $langs->trans("ecv_competences");


// Initial Objects
$ecv  = new ecv($db);
$competances  = new competances($db);
$form        = new Form($db);


// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;

$error  = false;
if (!$user->rights->sghr->cv->read) {
    accessforbidden();
}

if(in_array($action, ["add","edit"])) {
    if (!$user->rights->sghr->cv->write) {
      accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->sghr->cv->delete) {
      accessforbidden();
    }
}



// ------------------------------------------------------------------------- Actions "Create/Update/Delete/Export"


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

/* ------------------------ View ------------------------------ */

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_fiche_titre($modname);
$id_ecv=GETPOST('id_ecv');
// print_r($id_ecv);die();
if(!empty($id_ecv)){
    $head = ecvAdminPrepareHead($id_ecv);
    dol_fiche_head(
        $head,
        'competances',
        '', 
        0,
        "ecv@sghr"
    );
}

// Call JS.php
// require_once 'script/js.php';

print '<link rel="stylesheet" href= "'.dol_buildpath('/sghr/ecv/competances/css/rating.css',2).'">';

// ------------------------------------------------------------------------- Views
if($action == "add")
    require_once 'z-actions/create.php';

if($action == "edit")
    require_once 'z-actions/edit.php';

if( empty($action) || $action == "delete" )
    require_once 'z-actions/show.php';

?>



<script type="text/javascript">

    
    $(document).ready(function(){
        $('.lightbox_trigger').click(function(e) {
            e.preventDefault();
            var image_href = $(this).attr("href");
            $('#lightbox #content').html('<img src="' + image_href + '" />');
            $('#lightbox').show();
        });

        $('#lightbox,#lightbox p').click(function() {
            $('#lightbox').hide();
        });
    });

</script>

<?php

llxFooter();

if (is_object($db)) $db->close();
?>