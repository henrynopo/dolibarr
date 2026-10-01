<?php 

if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

dol_include_once('/sghr/recruitment/class/cv.class.php');
dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
dol_include_once('/sghr/recruitment/class/Candidate.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/recruitment/lib/recrutement.lib.php');



$langs->load('recruitment@sghr');

$modname = $langs->trans("cv");
// Initial Objects
$candidature  = new Candidate($db);
$JobPosition  = new JobPosition($db);
$cv = new cv_recrutement($db);
$form        = new Form($db);

// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;


if(!empty($id)){
    $object = new cv_recrutement($db);
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

if(in_array($action, ["add","edit"])) {
    if (!$user->rights->sghr->rec->write) {
      accessforbidden();
    }
}
if($action == "delete") {
    if (!$user->rights->sghr->rec->delete) {
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

$action_export=GETPOST('action_export');
$id_cv=GETPOST('id_cv');
$candidature_id=GETPOST('candidature');
// echo $candidature_id;

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);
print_fiche_titre($modname);

$cv->fetch($id);
if($cv->poste){
    $id_general=$cv->poste;
}elseif($cv->candidature){
    $id_general=$cv->candidature;
}
$head = menu_candidature($candidature_id);
// if($action != 'add'){
    dol_fiche_head(
        $head,
        'cv',
        '', 
        0,
        "recruitment@sghr"
    );
// }

?>
<style type="text/css">
    #wrapper ul{
        padding: 0;
        list-style: none;
    }
    #wrapper ul li{
        display: inline-block;
        padding: 6px;
        position: relative;
    }
    #wrapper ul li img{
        width: auto !important;
        height: 80px;
        border: solid 1px #ccc;
        background: #fff;
    }
    #wrapper ul li span{
        position: absolute;
        width: 100%;
        height: 83px;
        top: 0;
        left: 0;
        text-align: center;
        line-height: 75px;
        border: solid 1px #ccc;
        background: #fff;
        display: none;
        font-size: 13px;
    }
    #wrapper ul li:hover span{
        display: block;
    }

    #lightbox {
        position:fixed; /* keeps the lightbox window in the current viewport */
        top:0; 
        left:0; 
        width:100%; 
        height:100%; 
        background-color: rgba(0,0,0,0.5);
        text-align:center;
        //padding-top: 3%;
    z-index: 5000;
            overflow: auto;
    }
    #lightbox p {
        text-align:right; 
        color:#fff; 
        margin-right:20px; 
        font-size: 2em;
        font-weight: bold;
        cursor: pointer;
        font-family: cursive;
    }
    #lightbox img {
        box-shadow:0 0 25px #111;
        -webkit-box-shadow:0 0 25px #111;
        -moz-box-shadow:0 0 25px #111;
        max-width:800px;
        background: #fff;
    }
    table.nc_table_ tr td:first-child {
        width: 20%;
    }
    .modele_cv:hover{
        cursor: pointer;
    }
    .valider{
        background-color:green;
        color:white !important;
        padding:5px;
        border-radius:3px;
        font-size:16px;
    }
    .valider:hover{
        cursor: pointer;
        color:white;
        text-decoration: none;
        padding: 8px;
    }

    #deconstruction{
        background-color:#b30000;
        color:white;
        padding:5px;
        border-radius:3px;
        font-size:16px;
    }
    #deconstruction:hover{
        cursor: pointer;
        text-decoration: none;
        padding: 8px;
    }
    .table_ td{
        border: 1px solid rgb(169, 169, 169); 
        padding:5px;"
    }
    .select2{
        /*width: 20% !important;*/
     }
    #importer{
        background-color: rgb(60,70,100);
        color: white;
    }
    #url{
            width: 265px;
    }
    #name{
        width: 85%;
    }
</style>
<?php
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
        $('#fk_user').select2();
        $('#add_composant').click(function(){
            $id=$('#composantes tr').length+1;
            $('#composantes').append('<tr> <td><select name="composantes_new['+$id+'][product]" class="select_product"><?php echo $recrutement->select_product() ?></select></td> <td align="center"><input type="number" name="composantes_new['+$id+'][quantite]" class="quantite" value="" min="0"  autocomplete="off"/></td> <td align="center"><img src="<?php echo DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png' ?>" class="delete_projet" onclick="delete_tr(this);"></td> </tr>');
            $('.select_product').select2();
            $('.select2').css('width','100%');
            $('.quantite').css('width','30%');
        });
        $('.select_product').select2();
        $('.select2').css('width','100%');
        // $('.quantite').css('width','30%');
        $('#valider').click(function(){
            $id=$('#valider').data('id');
            $.ajax({
                data:{'id_':$id,},
                url:"<?php echo dol_buildpath('/sghr/recruitment/verifierdisponibilite.php?action=valider',2) ?>",
                type:'POST',
                dataType: 'json',
                success:function(data){
                    console.log(data);
                    if(data['msg'] == ''){
                        $('#valider').hide();
                        $('.verification').html('<a id="produire" data-id="'+$id+'" > <?php echo $langs->trans('produire') ?></a> ');
                        location.reload();
                    }else{
                        location.reload();
                    }
                }
                

            });
        });
        $('#produire').click(function(){
            $id=$('#produire').data('id');
            $id_entrepot=$('#fk_entrepot').val();
            $.ajax({
                data:{'id_':$id,'id_entrepot':$id_entrepot},
                url:"<?php echo dol_buildpath('/sghr/recruitment/verifierdisponibilite.php?action=produire',2) ?>",
                type:'POST',
                success:function($data){
                    if($data==''){
                        $('#valider').hide();
                        $('.verification').html('<a id="produire" data-id="'+$id+'" > <?php echo $langs->trans('produire') ?></a> ');
                        location.reload();
                    }
                }

            });
        });


        $('.lightbox_trigger').click(function(e) {
            e.preventDefault();
            var image_href = $(this).attr("href");
            $('#lightbox #content').html('<img src="' + image_href + '" />');
            $('#lightbox').show();
        });

        $('#lightbox,#lightbox p').click(function() {
            $('#lightbox').hide();
        });

        $('#validatesumitbutton').click(function(){
            $('.inpt_submit').trigger('click');            
        });
        
    });
    
    function delete_tr(tr){
        $(tr).parent().parent().remove();
        if($(tr).data('id')){
            $id=$(tr).data('id')
            $data=$('#composants_deleted').val();
            if ($data =='') {
                $('#composants_deleted').val($id);
            }else
                $('#composants_deleted').val($data+","+$id);
        }
    }

    function change_qt(x) {
        $('#qtt').val($(x).val());
    }
    function change_qt_rebut(x) {
        $('#qtt_rebut').val($(x).val());
    }
    
    
</script>
<?php

llxFooter();

if (is_object($db)) $db->close();
?>