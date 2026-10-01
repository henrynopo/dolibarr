<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/ecvqualifications.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/ecv/lib/ecv.lib.php');


$langs->load('ecv@sghr');

$modname = $langs->trans("ecv_qualifications");

// Initial Objects
$ecv  = new ecv($db);
$ecvqualifications = new ecvqualifications($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= (int) $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->cv->read) {
	accessforbidden();
}



$id_ecv = GETPOST('id_ecv');
$id = GETPOST('id');
$action = GETPOST('action');
$request_method = $_SERVER['REQUEST_METHOD'];

if ($action == 'create' && $request_method === 'POST') {
	$qualifications=GETPOST('qualifications');
	$id_ecv=GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
	foreach ($qualifications as $key => $value) {
		
		$data =  array( 
	        'name' =>  addslashes($value['name']),
	        'fk_ecv'   	    =>  $id_ecv,
	        'fk_user'  	    =>  $ecv->fk_user,
	        'entity'  	    =>  $conf->entity,
	    );
		$isvalid = $ecvqualifications->create($data, 1);
	}
    header('Location: index.php?id_ecv='.$id_ecv);
    exit;
}

if ($action == 'edit' && $request_method === 'POST') {
    $name = addslashes(GETPOST('name'));

    $entity  = (GETPOSTISSET('entity') ? GETPOST('entity', 'int') : $conf->entity);
    
    $ecv->fetch($id_ecv);
    $data =  array( 
        'name' =>  $name,
        'entity' =>  $entity,
    );


    $isvalid = $ecvqualifications->update($id, $data);
    if ($isvalid > 0) {
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    } 
}



$filter .= (!empty($srch_name)) ? " AND name like '%".$srch_name."%'" : "";

$ecv->fetch($id_ecv);
$filter .= (!empty($id_ecv)) ? " AND fk_ecv = '".$id_ecv."'  AND fk_user=".$ecv->fk_user : "";


$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$nbrtotal = $ecvqualifications->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./index.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $id_ecv  = GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
    $ecvqualifications->fetch($id);
    $error = 1;
    $ecvqualifications->delete();
    if ($error == 1) {
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    }
    else {      
        header('Location: index.php');
        exit;
    }
}
$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
if( ($id && empty($action)) || $action == "delete" ){

    if($action == "delete"){
        print $form->formconfirm("index.php?id=".$id."&id_ecv=".$id_ecv,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?id_ecv='.$id_ecv.'&page='.$page, 0, 1);
    }
}

if(!empty($id_ecv)){
	$head = ecvAdminPrepareHead($id_ecv);
    dol_fiche_head($head,'qualifications','',	0,"ecvqualifications@ecvqualifications");
}

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="form_ecv index_qualificecv">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
print '<input name="id_ecv" type="hidden" value="'.$id_ecv.'">';



print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';


field($langs->trans("ecv_name_qualification"),'name_qualification');
print '<th align="center">'.$langs->trans("Action").'</th>';


print '</tr>';


print '</thead><tbody>';
	$colspn = 2;

	if(count($ecvqualifications->rows) > 0){
		for($i=0; $i<count($ecvqualifications->rows); $i++){
			$var=!$var;
			$item = $ecvqualifications->rows[$i];
			print '<tr '.$bc[$var].' >';
	    		print '<td align="left" style="">'.$item->name.'</td>';
				print '<td align="center" style="width:10%; ">';
	    			print '<img src="'.dol_buildpath('/sghr/ecv/images/edit.png',2).'" class="img_edit" data-id="'.$item->id.'">  ';
	    			print '<a href="./index.php?id='.$item->id.'&action=delete&id_ecv='.$id_ecv.'" ><img src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png" class="img_delete"></a>';
	    		print'</td>';
			print '</tr>';
		}
	}else{
		print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
	}
	
	

print '</tbody></table></form>';

	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_ecv index_qualificecv">';
		print '<div class="nouveau" style="display:none">';
			print '<div style="background-color:#3c4664;padding:8px; color:white" width="100%" class="titre_ecv">';
			print '</div>';
			print '<div>';
				print '<table id="exp" width="100%" cellpadding="5px"; cellspadding="5px">';
					print '<thead>';
						print '<tr>';
							print '<th align="center">'.$langs->trans('ecv_name_qualification').'</th>';
							
							print '<th align="center" id="action">'.$langs->trans('Action').'</th>';
						print '</tr>';
					print '</thead>';
					print '<tbody id="tr_qualifications"><input name="id_ecv" type="hidden" value="'.$id_ecv.'">';
					print '</tbody>';
				print '</table>';
			print '</div>';
		print '</div>';
		print '<div style="float: right; margin: 8px;">';
			print '<a  class="butAction" id="nouveau" style="background-color:#3c4664; color:white" > '.$langs->trans('New').' </a>';
		print '</div>';
		print '<div style="text-align: center; margin: 8px;">';
			print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" style="display:none;" class="butAction" id="valider" />';
		print '</div>';
	print '</form>';


function field($titre,$champ){
	global $langs;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		
	print '</th>';
}





?>
<script>
	$(document).ready(function(){
	$( ".datepickerecvmod" ).datepicker({
    	dateFormat: 'dd/mm/yy'
	});
    $('#select_projet').select2();
    $('#select_tache').select2();

	$('#select_onechambre>select').select2();
	$('.fiche').find('.tabBar').removeClass('tabBarWithBottom');
	$('#nouveau').click(function(){
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Delete")); ?>');
        	$('.titre_ecv').html('<?php echo dol_escape_js($langs->trans("ecv_nouveau_qualification")); ?>');
			$('.nouveau').show();
			$('#valider').show();
			$id=$('#tr_qualifications tr').length+1; 
			$('#tr_qualifications').append('<tr><td><input name="action" type="hidden" value="create"><input type="text" name="qualifications['+$id+'][name]" autocomplete="off" style="width:99%" /></td><td align="center"><img src="<?php echo dol_escape_js(DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png') ?>" class="img_delete" onclick="delete_tr(this);"></td></tr>');
			$( ".datepickerecvmod" ).datepicker({
		    	dateFormat: 'dd/mm/yy'
			});
			$(".niveau").select2()
		});

		$('.img_edit').click(function(){
        	$('.titre_ecv').html('<?php echo dol_escape_js($langs->trans("ecv_detail_qualification")); ?>');
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Modify")); ?>');
			$('.nouveau').show();
			$('#nouveau').hide();
			$('#valider').hide();
			$id=$(this).data('id');
			$.ajax({
				data:{'id':$id},
				url:"<?php echo dol_escape_js(dol_buildpath('/sghr/ecv/qualifications/data_edit.php',2)); ?>",
				type:'POST',
				success:function(data){
					$('#tr_qualifications').html(data);
				}
			});
		});

	});
	function delete_tr(tr){
		$(tr).parent().parent().remove();
	}

</script>
<style type="text/css">
	
</style>
<?php

llxFooter();