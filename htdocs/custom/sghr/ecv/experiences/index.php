<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/ecvexperiences.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/ecv/lib/ecv.lib.php');

$langs->load('ecv@sghr');
$modname = $langs->trans("ecv_experiences");

// Initial Objects
$ecv  = new ecv($db);
$ecvexperiences = new ecvexperiences($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->cv->read) {
	accessforbidden();
}


$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
$id_ecv = GETPOST('id_ecv');
$id = GETPOST('id');
$action = GETPOST('action');
$request_method = $_SERVER['REQUEST_METHOD'];

if ($action == 'edit' && $request_method === 'POST') {
    $experiences = GETPOST('experiences')[$id];
    $date_d = explode('/',$experiences['debut']);
    $debut = $date_d[2]."-".$date_d[1]."-".$date_d[0];

    $date_f = explode('/',$experiences['fin']);
    $fin = $date_f[2]."-".$date_f[1]."-".$date_f[0];
    $entity  = (GETPOSTISSET('entity') ? GETPOST('entity', 'int') : $conf->entity);

    $ar='';
    if(!empty($experiences['projets']))
        $ar=json_encode($experiences['projets']);
    	$ar=addslashes($ar);
    $ecv->fetch($id_ecv);
    // print_r($experiences['no_jours']);die();
    if(!empty($experiences['no_jours'])){
    	$nosjours=1;
    }else
    	$nosjours=0;
    
    $data =  array( 
        'societe'  	   =>  addslashes($experiences['societe']),
        'debut'    	   =>  $debut,
        'fin'      	   =>  $fin,
        'projets'  	   =>  $ar,
        'description'  =>  addslashes($experiences['description']),	
        'fk_ecv'   	   =>  $id_ecv,
	    'nosjours'     =>  $nosjours,
        'fk_user'  	   =>  $ecv->fk_user,
        'entity'  	   =>  $entity,
    );
    $isvalid = $ecvexperiences->update($id, $data);
    if ($isvalid > 0) {
        $ecvexperiences->fetch($id);
        $upload_dir = $conf->ecv->dir_output.'/'.$id_ecv.'/experiences/'.$id.'/';
    	if(!empty($_FILES['profile']['name'])){
	        if($ecvexperiences->profile_soc){
	            $file=$upload_dir."/".$item->profile_soc;
	            unlink($file);
	        }
	        $TFile = $_FILES['profile'];
			$profile_soc = array('profile_soc' => dol_sanitizeFileName($TFile['name'],''));
            if (dol_mkdir($upload_dir) >= 0)
            {
                $destfull = $upload_dir.$TFile['name'];
                $info     = pathinfo($destfull); 
                
                $filname    = dol_sanitizeFileName($TFile['name'],'');
                $destfull   = $info['dirname'].'/'.$filname;
                $destfull   = dol_string_nohtmltag($destfull);
                $resupload  = dol_move_uploaded_file($TFile['tmp_name'], $destfull, 0, 0, $TFile['error'], 0);
                $ecvexperiences->update($id,$profile_soc);
            }
	    }
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    } 
}

if ($action == 'create' && $request_method === 'POST') {
	$experiences=GETPOST('experiences');
	$id_ecv=GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
	// print_r($experiences);die();
	foreach ($experiences as $key => $value) {
	    if($value['fin']){
			$date_d = explode('/',$value['debut']);
		    $debut = $date_d[2]."-".$date_d[1]."-".$date_d[0];
		}else
	    	$fin=NULL;

	    if($value['fin']){
		    $date_f = explode('/',$value['fin']);
		    $fin = $date_f[2]."-".$date_f[1]."-".$date_f[0];
	    }else
	    	$fin=NULL;
	    if(!empty($value['no_jours'])){
	    	$nosjours=1;
	    }else
	    	$nosjours=0;

	    $ar='';
	    if(!empty($value['projets']) && count($value['projets']) >= 1)
	        $ar=json_encode($value['projets']);
    		$ar=addslashes($ar);
		$data =  array( 
	        'societe'  	   =>  addslashes($value['societe']),
	        'debut'    	   =>  $debut,
	        'fin'      	   =>  $fin,
	        'nosjours'     =>  $nosjours,
	        'projets'  	   =>  $ar,
	        'description'  =>  addslashes($value['description']),
	        'fk_ecv'   	   =>  $id_ecv,
	        'fk_user'  	   =>  $ecv->fk_user,
	        'entity'  	   =>  $conf->entity,
	    );
		$id = $ecvexperiences->create($data, 1);
		if ($id > 0) {
			
	        if ($_FILES['experiences']) { 
	            $TFile = $_FILES['experiences'];
				$profile_soc = array('profile_soc' => dol_sanitizeFileName($TFile['name'][$key],''));
	            $upload_dir = $conf->ecv->dir_output.'/'.$id_ecv.'/experiences/'.$id.'/';
	            if (dol_mkdir($upload_dir) >= 0)
	            {
	                $destfull = $upload_dir.$TFile['name'][$key];
	                $info     = pathinfo($destfull); 
	                
	                $filname    = dol_sanitizeFileName($TFile['name'][$key],'');
	                $destfull   = $info['dirname'].'/'.$filname;
	                $destfull   = dol_string_nohtmltag($destfull);
	                $resupload  = dol_move_uploaded_file($TFile['tmp_name'][$key], $destfull, 0, 0, $TFile['error'][$key], 0);
	                $ecvexperiences->fetch($id);
                    $ecvexperiences->update($id,$profile_soc);

	            }
	        }

	    } 
	}
    header('Location: index.php?id_ecv='.$id_ecv);
    exit;
}

$ecv->fetch($id_ecv);
$filter .= (!empty($id_ecv)) ? " AND fk_ecv = '".$id_ecv."'  AND fk_user=".$ecv->fk_user : "";

$nbrtotal = $ecvexperiences->fetchAll($sortorder, $sortfield, $limit, $offset,$filter);

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./index.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $id_ecv  = GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
    $ecvexperiences->fetch($id);

    $error = 1;
    $ecvexperiences->delete();
    if ($error == 1) {
    	$dir = $conf->ecv->dir_output.'/'.$id_ecv.'/experiences/'.$id.'/';
        $files=scandir($dir);
        foreach ($files as $file) {
            if($file != '.' && $file!='..'){
                $dir = $conf->ecv->dir_output.'/'.$id_ecv.'/experiences/'.$id.'/'.$file;
                if(file_exists($dir)){
                    unlink($dir);
                }
            }
        }
       
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
    dol_fiche_head($head,'experiences','',	0,"ecv@sghr");
}

print '<link rel="stylesheet" href= "'.dol_buildpath('/sghr/ecv/js/ecv.js',2).'">';

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="form_ecv">'."\n";
print '<input style="width:95%" name="pagem" type="hidden" value="'.$page.'">';
print '<input style="width:95%" name="offsetm" type="hidden" value="'.$offset.'">';
print '<input style="width:95%" name="limitm" type="hidden" value="'.$limit.'">';
print '<input style="width:95%" name="filterm" type="hidden" value="'.$filter.'">';
print '<input style="width:95%" name="id_ecv" type="hidden" value="'.$id_ecv.'">';

// print '<div style="float: right; margin: 8px;">';

// 	print '<a href="card.php?action=add&id_ecv='.$id_ecv.'" class="butAction" >'.$langs->trans("Add").'</a>';
// print '</div>';

print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';
	field($langs->trans("ecv_societe_experience"),'societe');
	field($langs->trans("ecv_debut_experience"),'debut');
	field($langs->trans("ecv_fin_experience"),'fin');
	field($langs->trans("ecv_description_experience"),'description');
	field($langs->trans("ecv_projets"),'projets');
	print '<th align="center">'.$langs->trans("Action").'</th>';
print '</tr>';


print '</thead><tbody>';
	$colspn = 6;
	
		if (count($ecvexperiences->rows) > 0) {
			for ($i=0; $i < count($ecvexperiences->rows) ; $i++) {
				$var = !$var;
				$item = $ecvexperiences->rows[$i];

				$d=explode(' ', $item->debut);
				$date_d = explode('-', $d[0]);
		    	$debut = $date_d[2]."/".$date_d[1]."/".$date_d[0];

		    	$f=explode(' ', $item->fin);
				$date_f = explode('-', $f[0]);
		    	$fin = $date_f[2]."/".$date_f[1]."/".$date_f[0];
		    	$ecv->fetch($item->fk_ecv);
				print '<tr '.$bc[$var].' >';
		    		print '<td  style="width:20%"> ';
		    		if(!empty($item->profile_soc)){
                    	$minifile = getImageFileNameForSize($item->profile_soc, '');  
                        $dt_files = getAdvancedPreviewUrl('ecv', $item->fk_ecv.'/experiences/'.$item->rowid.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));

                        print ' <div style="width:10%; float:left">';
	                        print '<a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'">' ;
	                            print '<img class="photo" height="20" title="'.$minifile.'" alt="Fichier binaire" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=ecv&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file='.$item->fk_ecv.'/experiences/'.$item->rowid.'/'.$minifile.'&perm=download" border="0" name="image" >';
	                        print '</a>';
                        print '</div>';
		    		}
		    		print  '<div style="width:90%;margin-left:32px;" align="left">'.$item->societe.'</div>';
		    		print '</td>';
		    		print '<td align="center" style="width:10%">'.$debut.'</td>';
		    		print '<td align="center" style="width:10%">';
		    		if($item->nosjours == 1){
		    			print 'Nos jours';
		    		}elseif($item->nosjours == 0)
		    			print $fin;
		    		print '</td>';
		    		print '<td align="center" style="width:30%;  text-align: justify; ">'.nl2br($item->description).'</td>';
		    		print '<td style="width:20%;">';
		            $projets=json_decode($item->projets);
		            if(!empty($projets) && count($projets)>0){
		                print '<ul>';
		                foreach ($projets as $key => $value) {
		                    print '<li>'.$value.'</li>';
		                }
		                print '</ul>';
		            }
		            print '</td>';
					print '<td align="center" style="width:10%; "><img src="'.dol_buildpath('/sghr/ecv/images/edit.png',2).'" class="img_edit" data-id="'.$item->id.'">  <a href="./index.php?id='.$item->id.'&action=delete&id_ecv='.$id_ecv.'" ><img src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png" class="img_delete"></a></td>';
				print '</tr>';
			}
		}else{
			print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
		}

print '</tbody></table><br><br>';
print '</form>';

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_ecv">';
		print '<div class="nouveau" style="display:none">';
			print '<div style="background-color:#3c4664;padding:8px; color:white" width="100%" class="titre_ecv">'.$langs->trans('ecv_nouveau_experience');
			print '</div>';
			print '<div>';
				print '<table id="exp" width="100%" cellpadding="5px"; cellspadding="5px">';
					print '<thead>';
						print '<tr>';
							print '<th align="center">'.$langs->trans('ecv_societe_experience').'</th>';
							print '<th align="center">'.$langs->trans('ecv_debut_experience').'</th>';
							print '<th align="center">'.$langs->trans('ecv_fin_experience').'</th>';
							print '<th align="center">'.$langs->trans('ecv_description_experience').'</th>';
							print '<th align="center">'.$langs->trans('ecv_profile_soc').'</th>';
							print '<th align="center">'.$langs->trans('ecv_projets').'</th>';
							print '<th align="center" id="action">'.$langs->trans('Action').'</th>';
						print '</tr>';
					print '</thead>';
					print '<tbody id="tr_exp"><input name="id_ecv" type="hidden" value="'.$id_ecv.'">';
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
	global $langs, $id_ecv;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		
	print '</th>';
}


?>
<script>
	$(document).ready(function() {
		$('.nouveau').hide();
		$('.file').hide();
		$('#valider').hide();
		$('#select_onechambre>select').select2();

		$('.fiche').find('.tabBar').removeClass('tabBarWithBottom');
		// $(".valider").trigger("click"
		$('#nouveau').click(function(){
        	$('.titre_ecv').html('<?php echo dol_escape_js(trim(preg_replace('/\s\s+/', '', $langs->trans("ecv_nouveau_experience")))); ?>');
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Delete")); ?>');
			$('.nouveau').show();
			$('#valider').show();
			$id=$('#tr_exp tr').length+1; 
			$('#tr_exp').append('<tr><td style="width:14px;"><input name="action"  type="hidden" value="create"><input type="text" name="experiences['+$id+'][societe]" autocomplete="off" /></td> <td style="width:8%;"><input type="text" name="experiences['+$id+'][debut]" class="datepickerecvmod debut" value="<?php echo date('d/m/Y') ?>"  onchange="MinDate(this);" autocomplete="off" /></td> <td style="width:8%;"><input type="text" name="experiences['+$id+'][fin]" class="datepickerecvmod fin" value="<?php echo date('d/m/Y') ?>"  onchange="MaxDate(this);" autocomplete="off" /><div align="center"><label><input type="checkbox" name="experiences['+$id+'][no_jours]" onchange="no_jours(this);"> <b class="nos_jour"><?php echo trim(preg_replace('/\s\s+/', '', $langs->trans("ecv_no_jours") )); ?></b></label></div></td><td  style="width:30%;"><textarea style="width:95%"  name="experiences['+$id+'][description]" autocomplete="off" ></textarea></td><td><a  class="butAction" id="upload" onclick="getprofile(this);"  style="background-color:#3c4664; color:white; float: right;" > Upload </a><input type="file"  accept="image/*" class="file" id="profile" name="experiences['+$id+']"  autocomplete="off"/></td><td class="projets"><table style="width:100%"><tbody class="projet" ></tbody></table><div align="right"><img src="<?php echo dol_escape_js(dol_buildpath('/sghr/ecv/images/add_.png',2))  ?>" height="25px" onclick="add_projet(this);" data-id="'+$id+'"; class="img_add"></div></td><td align="center"><img src="<?php echo dol_escape_js(DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png') ?>" class="img_delete" onclick="delete_tr(this);"></td></tr>');
			textarea_autosize();
			// $('html, body').animate({
		 //      scrollTop: $('.titre_ecv').offset().top
		 //    }, 1000);
			$( ".datepickerecvmod" ).datepicker({
		    	dateFormat: 'dd/mm/yy'
			});
			$('.file').hide();

		});

		$('.img_edit').click(function(){
        	$('.titre_ecv').html('<?php echo dol_escape_js($langs->trans("ecv_detail_experience")); ?>');
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Modify")); ?>');
			$('.nouveau').show();
			$('#nouveau').hide();
			$('#valider').hide();
			$id=$(this).data('id');
			$.ajax({
				data:{'id':$id},
				url:"<?php echo dol_escape_js(dol_buildpath('/sghr/ecv/experiences/data_edit.php',2)); ?>",
				type:'POST',
				success:function(data){
					$('#tr_exp').html(data);
					textarea_autosize();
            		no_jours('#no_jours');
				}
			});

			// $('html, body').animate({
		 //      scrollTop: $('.titre_ecv').offset().top
		 //    }, 1000);
		});
		$( ".datepickerecvmod" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});
		$('.form_ecv textarea').change(function(){
			textarea_autosize();
		});
		
	});

	function add_projet(x){
		$id_projet=$('tbody.projet tr').length+1;
		$id=$(x).data('id');
		$(x).parent().parent().find('tbody.projet').append('<tr><td style="border:none !important"><input style="width:95%" type="text" name="experiences['+$id+'][projets][]"></td><td style="border:none !important"><img src="<?php echo dol_escape_js(DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png'); ?>" class="delete_projet" onclick="delete_tr(this);"></td></tr>');
	}

	function delete_tr(tr){
		$(tr).parent().parent().remove();
	}

	function MaxDate(max) {
		$max=$(max).val();
		$(max).parent().parent().find('.debut').datepicker( "option", "maxDate", $max );
	}

	function MinDate(min) {
		$min=$(min).val();
		$(min).parent().parent().find('.fin').datepicker( "option", "minDate", $min );
	}

	function getprofile(file) {
		console.log('fgvdfgfdg');
		$(file).parent().find('.file').trigger('click');
		
		$(file).parent().find('span').text($(file).parent().find('.file').val());
	}

	function no_jours(check){
		$checked_=$(check).prop('checked');
		if($checked_ == true){
			$(check).parent().parent().parent().find('.fin').hide();
		}else
			$(check).parent().parent().parent().find('.fin').show();
	}

	function textarea_autosize(){
	  	$(".form_ecv textarea").each(function(textarea) {
		    $(this).height($(this)[0].scrollHeight);
		    $(this).css('resize', 'none');
		});
	}

</script>
		
<?php

llxFooter();