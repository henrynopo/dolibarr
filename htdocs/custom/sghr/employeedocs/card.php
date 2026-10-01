<?php 

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
// require_once DOL_DOCUMENT_ROOT.'/type_document/class/type_document.class.php';


dol_include_once('/sghr/employeedocs/class/EmployeeDocs.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/employeedocs/class/type_document.class.php');

$langs->load('employeedocs@sghr');

$modname = $langs->trans("docsemployes");

// Initial Objects
$employeeDocs  = new EmployeeDocs($db);
$object  = new EmployeeDocs($db);
$form           = new Form($db);
$type_document  = new type_document($db);
$user22 = new User($db);

// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;


if(!empty($id)){
    $object = new EmployeeDocs($db);
    $object->fetch($id);
    if (!($object->rowid > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
} 


include_once(DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php');
$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('employeedocscard'));

$object->fetch($id);
$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action); // See description below

if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');




$error  = false;
if (!$user->rights->sghr->docs->read) {
    accessforbidden();
}

if(in_array($action, ["add","edit"])) {
    if (!$user->rights->sghr->docs->write) {
      accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->sghr->docs->delete) {
      accessforbidden();
    }
}

if($id ){
    $employeeDocs->fetch($id);
    if ($user->id != $employeeDocs->fk_user && empty($user->rights->sghr->docs->write)) {
        accessforbidden();
    }
}
$hookmanager->initHooks(array('employeedocscard'));

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

// If delete of request
if ($action == 'confirm_delete_all' && GETPOST('confirm') == 'yes' ) {
    require_once 'z-actions/show.php';
}

/* ------------------------ View ------------------------------ */

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);




print_fiche_titre($modname);

?>

<style type="text/css">

</style>

<?php

// Call JS.php
// require_once 'script/js.php';

// ------------------------------------------------------------------------- Views


// $hookmanager->initHooks(array('employeedocscard','globalcard'));
// $parameters=array();
// $reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
// if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
// if (empty($reshook)){



    if($action == "add")
        require_once 'z-actions/create.php';

    if($action == "edit")
        require_once 'z-actions/edit.php';

    if($action == "deleteall")
        require_once 'z-actions/edit.php';

    if( ($id && empty($action)) || $action == "delete" )
        require_once 'z-actions/show.php';


?>
<script>
    $(function(){
        $('select#fk_user').select2();

        $('.card_docsemploys .remove_file').click(function() {
            console.log('remove_file');
            var filename = $(this).data("file");
            var file_deleted = $('#file_deleted').val();
            if( file_deleted == '' )
                $('#file_deleted').val(filename);            
            else
                $('#file_deleted').val(file_deleted+','+filename);
            $(this).parent().hide();
        });

        $('.card_docsemploys .remove_picture').click(function() {
            console.log('ghklm');
            var filename = $(this).parent().data("file");
            var photo_deleted = $('#photo_deleted').val();
            if( photo_deleted == '' )
                $('#photo_deleted').val(filename);            
            else
                $('#photo_deleted').val(photo_deleted+','+filename);
            $(this).parent().parents('li').hide();
        });

        $('.bt_valid').click(function(){
            $('.inpt_valid').trigger('click');
        });

        $('#fk_user').change(function(){
            var id = $(this).val();
            $.ajax({
                data:{'id_user':id},
                url:"<?php echo dol_buildpath('/sghr/employeedocs/get_info.php',2) ?>",
                type:'POST',
                success:function($data){
                    $('.destinataire').find('input').val($data);
                }
            })
        });

    });
</script>
<?php
llxFooter();

if (is_object($db)) $db->close();
?>