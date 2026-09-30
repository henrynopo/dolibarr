<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/ecv/class/ecv.class.php');
dol_include_once('/ecv/class/ecvpermis.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/ecv/lib/ecv.lib.php');


$langs->load('ecv@ecv');

$modname = $langs->trans("ecv_permis");

// Initial Objects
$ecv  = new ecv($db);
$ecvpermis = new ecvpermis($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->ecv->lire) {
	accessforbidden();
}


$id_ecv         = GETPOST('id_ecv');
$action         = GETPOST('action');
$request_method         = $_SERVER['REQUEST_METHOD'];
$ecv->fetch($id_ecv);
$filter .= (!empty($id_ecv)) ? " AND fk_ecv = '".$id_ecv."'  AND fk_user=".$ecv->fk_user : "";

if ($action == 'create' && $request_method === 'POST') {
	$permis=GETPOST('permis_new');
	$id_ecv=GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
	foreach ($permis as $key => $value) {
		
		$data =  array( 
	        'type'       => strtoupper(addslashes(trim($value['type']))),
	        'year'      =>  $value['year'],
	        'fk_ecv'   	 =>  $id_ecv,
	        'fk_user'  	 =>  $ecv->fk_user,
	        'entity'  	 =>  $conf->entity,
	    );
		$isvalid = $ecvpermis->create(1, $data);
		
	}
    header('Location: index.php?id_ecv='.$id_ecv);
    exit;
}
// print_r($action.'/'.$request_method);die();
if ($action == 'edit' && $request_method === 'POST') {
    $id = GETPOST('id');
    $type = GETPOST('type');
    $year = GETPOST('year');
    $ecv->fetch($id_ecv);
    $entity  = (GETPOSTISSET('entity') ? GETPOST('entity', 'int') : $conf->entity);

    $data =  array( 
        'type'       =>  strtoupper(addslashes(trim($type))), 
        'year'       =>  $year,	
        'fk_ecv'   	     =>  $id_ecv,
        'fk_user'  	     =>  $ecv->fk_user,
        'entity'  	     =>  $entity,
    );
    $isvalid = $ecvpermis->update($id, $data);
    if ($isvalid > 0) {
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    } 
}


$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;


if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    if (!$id || $id <= 0) {
        header('Location: ./index.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $id_ecv  = GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
    $ecvpermis->fetch($id);
    $error =  $ecvpermis->delete();
    if ($error == 1) {
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    }
    else {      
        header('Location: index.php');
        exit;
    }
}

$nbrtotal = $ecvpermis->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

if( ($id && empty($action)) || $action == "delete" ){

    if($action == "delete"){
        print $form->formconfirm("index.php?id=".$id."&id_ecv=".$id_ecv,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?id_ecv='.$id_ecv.'&page='.$page, 0, 1);
    }
}

$id_ecv=GETPOST('id_ecv');
if(!empty($id_ecv)){
	$head = ecvAdminPrepareHead($id_ecv);
    dol_fiche_head($head,'permis','',	0,"ecv@ecv");
}
print '<link rel="stylesheet" href= "'.dol_buildpath('/ecv/permis/css/rating.css',2).'">';

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="form_ecv index_permisecv">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';

// print '<div style="float: right; margin: 8px;">';
// if(!empty($id_ecv))
// 	print '<a href="card.php?action=add&id_ecv='.$id_ecv.'" class="butAction" >'.$langs->trans("Add").'</a>';
// else
// 	print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
// print '</div>';

print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';


field($langs->trans("ecv_permis_type"),'ecv_permis_type');
field($langs->trans("ecv_annee_acquisition"),'ecv_annee_acquisition');
print '<th align="center">'.$langs->trans("Action").'</th>';


print '</tr>';


print '</thead><tbody>';
	$colspn = 5;
	$ecv->fetch($id_ecv);

		if (count($ecvpermis->rows) > 0) {
			for ($i=0; $i < count($ecvpermis->rows) ; $i++) {


				$var = !$var;
				$item = $ecvpermis->rows[$i];
				// $langs->loadLangs(array('admin', 'languages', 'other', 'companies', 'products', 'members', 'projects', 'hrm', 'agenda'));
				print '<tr '.$bc[$var].' >';
                    print '<td align="left" style=""> '.$item->type.'</td>';
		    		print '<td align="center" style=""> '.$item->year.'</td>';
		            
					print '<td align="center" style="width:10%; ">';
						print '<img src="'.dol_buildpath('/ecv/images/edit.png',2).'" class="img_edit" data-id="'.$item->rowid.'">  <a href="./index.php?id='.$item->id.'&action=delete&id_ecv='.$id_ecv.'" ><img src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png" class="img_delete"></a>';
					print '</td>';
				print '</tr>';
			}
		}else{
			print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
		}

print '</tbody></table></form><br><br>';

	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_ecv index_permisecv">';
		print '<div class="nouveau" style="display:none">';
			print '<div style="background-color:#3c4664;padding:8px; color:white" width="100%" class="titre_ecv">Les détails de la permis';
			print '</div>';
			print '<div>';
				print '<table id="exp" width="100%" cellpadding="5px"; cellspadding="5px">';
					print '<thead>';
						print '<tr>';
							print '<th align="center">'.$langs->trans('ecv_permis_type').'</th>';
							print '<th align="center">'.$langs->trans('ecv_annee_acquisition').'</th>';
							print '<th align="center" id="action">'.$langs->trans('Action').'</th>';
						print '</tr>';
					print '</thead>';
					print '<tbody id="new_permis"><input name="id_ecv" type="hidden" value="'.$id_ecv.'">';
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
		$('.fiche').find('.tabBar').removeClass('tabBarWithBottom');
		$('#select_onechambre>select').select2();

		$('#nouveau').click(function(){
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Delete")); ?>');
        	$('.titre_ecv').html('<?php echo dol_escape_js($langs->trans("Nouveau_permis")); ?>');
			$('.nouveau').show();
			$('#valider').show();
            $id=$('#new_permis tr').length+1;
			// console.log($id);
           		$.ajax({
           			url:"<?php echo dol_escape_js(dol_buildpath('/ecv/permis/data_permis.php?data=select',2)); ?>",
		            type:"POST",
		            data:{'permis_id':$id},
		            success:function(data){
		                $('#select_permis_'+$id).html(data);
		            }
           		})
            	$('#new_permis').append('<tr > <input name="action"  type="hidden" value="create"><td align="center"><input type="text" style="width:95%;" name="permis_new['+$id+'][type]" value="" autocomplete="off"/></td> <td align="center"><input type="number" style="width:60px;" name="permis_new['+$id+'][year]" value="'+<?php echo date('Y'); ?>+'" autocomplete="off"/></td><td style="width:10%" align="center"><img src="<?php echo dol_escape_js(DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png'); ?>" class="img_delete" onclick="delete_tr(this);"></td></tr>');
            $('.select_permis').select2();
        });

        $('.img_edit').click(function(){
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Modify")); ?>');
        	$('.titre_ecv').html('Détails permis');
			$('.nouveau').show();
			$('#nouveau').hide();
			$('#valider').hide();
			$id=$(this).data('id');
			$.ajax({
				data:{'permis_id':$id},
				url:"<?php echo dol_escape_js(dol_buildpath('/ecv/permis/data_permis.php?data=edit',2)); ?>",
				type:'POST',
				success:function(data){
					$('#new_permis').html(data);
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