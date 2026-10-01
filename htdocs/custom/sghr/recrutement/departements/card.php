<?php 

if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

dol_include_once('/sghr/recrutement/class/postes.class.php');
dol_include_once('/sghr/recrutement/class/departement.class.php');
dol_include_once('/core/class/html.form.class.php');



$langs->load('recrutement@sghr');

$modname = $langs->trans("departement");
// Initial Objects
$departement = new departements($db);
$poste       = new postes($db);
$form        = new Form($db);
// Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action         = GETPOST('action', 'alpha');
$page           = GETPOST('page');
$id             = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;

if(!empty($id)){
    $object = new departements($db);
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
llxFooter();
$db->close();
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
                url:"<?php echo dol_buildpath('/sghr/recrutement/verifierdisponibilite.php?action=valider',2) ?>",
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
                url:"<?php echo dol_buildpath('/sghr/recrutement/verifierdisponibilite.php?action=produire',2) ?>",
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

         $('.delete_copie').click(function(e) {
            e.preventDefault();
            var filename = $(this).data("file");
            var file_deleted = $('#copie_deleted').val();
            if( file_deleted == '' )
                $('#copie_deleted').val(filename);            
            else
                $('#copie_deleted').val(file_deleted+','+filename);
            $(this).parent('li').remove();
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
    function show_composante(x){
        // if($(x).val()=='Valider'){
        //     $('#listcomposante').show();
        // }
        // else{
        //     $('#listcomposante').hide();
        // }
    }
    
</script>
<?php

// llxFooter();

// if (is_object($db)) $db->close();
?>