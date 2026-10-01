<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/cv/class/Cv.class.php');
dol_include_once('/sghr/cv/class/CvCertificate.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/cv/lib/cv.lib.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$langs->load('cv@sghr');

$modname = $langs->trans("ecv_certificats");

// Initial Objects
$cvProfile  = new Cv($db);
$CvCertificate = new CvCertificate($db);
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
	$certificats=GETPOST('certificats');
	$id_ecv=GETPOST('id_ecv');
	$cvProfile->fetch($id_ecv);
	foreach ($certificats as $key => $value) {
		$date_d = explode('/',$value['debut']);
	    $debut = $date_d[2]."-".$date_d[1]."-".$date_d[0];

	    $date_f = explode('/',$value['fin']);
	    $fin = $date_f[2]."-".$date_f[1]."-".$date_f[0];

		$data =  array( 
	        'etablissement' =>  addslashes($value['etablissement']),
	        'debut'    	   	=>  $debut,
	        'fin'      	   	=>  $fin,
	        'description'  	=>  addslashes($value['description']),
	        'intitule'      =>  addslashes($value['intitule']),
	        'fk_ecv'   	    =>  $id_ecv,
	        'fk_user'  	    =>  $cvProfile->fk_user,
	        'entity'  	    =>  $conf->entity,
	    );
		$isvalid =  $CvCertificate->create($data, 1);
		if ($isvalid > 0) {
			if ($_FILES['certificats']) { 
	            $TFile = $_FILES['certificats'];
				$copie = array('copie' => dol_sanitizeFileName($TFile['name'][$i],''));
	            $upload_dir = DOL_DATA_ROOT.'/ecv'.'/'.$id_ecv.'/certificates/'.$isvalid.'/';
	            if (dol_mkdir($upload_dir) >= 0)
	            {
	                $destfull = $upload_dir.$TFile['name'][$key];
	                $info     = pathinfo($destfull); 
	                
	                $filname    = dol_sanitizeFileName($TFile['name'][$key],'');
	                $destfull   = $info['dirname'].'/'.$filname;
	                $destfull   = dol_string_nohtmltag($destfull);
	                $resupload  = dol_move_uploaded_file($TFile['tmp_name'][$key], $destfull, 0, 0, $TFile['error'][$key], 0);
	                $CvCertificate->fetch($isvalid);
                    $CvCertificate->update($isvalid,$copie);
	            }
	        }
	    }
	}
    header('Location: index.php?id_ecv='.$id_ecv);
    exit;
}

if ($action == 'edit' && $request_method === 'POST') {
    $intitule = addslashes(GETPOST('intitule'));
    $description = addslashes(GETPOST('description'));
    $etablissement = addslashes(GETPOST('etablissement'));

    $entity      = (GETPOSTISSET('entity') ? GETPOST('entity', 'int') : $conf->entity);

    $date_d = explode('/',GETPOST('debut'));
    $debut  = $date_d[2]."-".$date_d[1]."-".$date_d[0];

    $date_f = explode('/',GETPOST('fin'));
    $fin = $date_f[2]."-".$date_f[1]."-".$date_f[0];
    
    $cvProfile->fetch($id_ecv);
    $data =  array( 
        'etablissement'  =>  $etablissement,
        'description'  	 =>  $description,	
        'intitule'       =>  $intitule,	
        'debut'    	     =>  $debut,
        'fin'      	     =>  $fin,	
        'fk_ecv'   	     =>  $id_ecv,
        'fk_user'  	     =>  $cvProfile->fk_user,
        'entity'  	     =>  $entity,
    );
    $isvalid = $CvCertificate->update($id, $data);
    if ($isvalid > 0) {
    	$CvCertificate->fetch($id);
    	if($_FILES['copie']['name']){
		    $dir = DOL_DATA_ROOT.'/ecv'.'/'.$id_ecv.'/certificates/'.$id.'/';
			$copie = array('copie' => dol_sanitizeFileName($_FILES['copie']['name'],''));
	    	if($CvCertificate->copie && $_FILES['copie']['name']){
	            $file=$dir.$CvCertificate->copie;
	            unlink($file);
	        }
		    $TFile = $_FILES['copie'];
	       if (dol_mkdir($dir) >= 0)
	        {
	            $destfull = $dir.$TFile['name'];
	            $info     = pathinfo($destfull); 
	            
	            $filname    = dol_sanitizeFileName($TFile['name'],'');
	            $destfull   = $info['dirname'].'/'.$filname;
	            $destfull   = dol_string_nohtmltag($destfull);
	            $resupload  = dol_move_uploaded_file($TFile['tmp_name'], $destfull, 0, 0, $TFile['error'], 0);
	            $CvCertificate->fetch($id);
	            $CvCertificate->update($id,$copie);
	        }
    	}
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    } 
}

$cvProfile->fetch($id_ecv);
$filter .= (!empty($id_ecv)) ? " AND fk_ecv = '".$id_ecv."'  AND fk_user=".$cvProfile->fk_user : "";

$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;



$nbrtotal = $CvCertificate->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./index.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $id_ecv  = GETPOST('id_ecv');
	$cvProfile->fetch($id_ecv);
    $CvCertificate->fetch($id);
    $error = $CvCertificate->delete();
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
    dol_fiche_head($head,'certificats','',	0,"CvCertificate@CvCertificate");
}

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="certifcecv form_ecv">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
print '<input name="id_ecv" type="hidden" value="'.$id_ecv.'">';


print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';


field($langs->trans("ecv_etablissement_certificat"),'etablissement_certificat');
field($langs->trans("ecv_intitule_certificat"),'intitule_certificat');
field($langs->trans("ecv_debut_certificat"),'debut');
field($langs->trans("ecv_fin_certificat"),'fin');
field($langs->trans("ecv_description_certificat"),'description_certificat');
field($langs->trans("ecv_copie"),'copie');
print '<th align="center">'.$langs->trans("Action").'</th>';


print '</tr>';

print '</thead><tbody>';
	$colspn = 7;

	
		if (count($CvCertificate->rows) > 0) {
			for ($i=0; $i < count($CvCertificate->rows) ; $i++) {


				$var = !$var;
				$item = $CvCertificate->rows[$i];
    			$cvProfile->fetch($item->fk_ecv);

				$d=explode(' ', $item->debut);
				$date_d = explode('-', $d[0]);
		    	$debut = $date_d[2]."/".$date_d[1]."/".$date_d[0];

		    	$f=explode(' ', $item->fin);
				$date_f = explode('-', $f[0]);
		    	$fin = $date_f[2]."/".$date_f[1]."/".$date_f[0];

				print '<tr '.$bc[$var].' >';
		    		print '<td align="left" style="width:20%"> '.$item->etablissement.'</td>';
		    		print '<td align="left" style="width:10%"> '.$item->intitule.'</td>';
		    		print '<td align="center" style="width:10%"> '.$debut.'</td>';
		    		print '<td align="center" style=""> '.$fin.'</td>';
		    		print '<td align="left" style="width:30%; text-align:justify;"> '.nl2br($item->description).'</td>';
					print '<td align="center" style="width:20%">';
					print '<div id="wrapper"> <ul>';
				        if($item->copie){
				        	print '<li>';
                            	$minifile = getImageFileNameForSize($item->copie, '');  
	                            $dt_files = getAdvancedPreviewUrl('ecv', $item->fk_ecv.'/certificates/'.$item->rowid.'/'.$minifile, 1, '&entity='.(!empty($item->entity)?$item->entity:$conf->entity));

	                            print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'">' ;
	                                print '<img class="photo" title="'.$minifile.'" alt="Fichier binaire" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=ecv&entity='.(!empty($item->entity)?$item->entity:$conf->entity).'&file='.$item->fk_ecv.'/certificates/'.$item->rowid.'/'.$minifile.'&perm=download" border="0" name="image" >';
	                            print '</a> ';
	                        print '</li>';
				        }
				        print '</ul></div>';
				        print '</td>';
		    		print '<td align="center" style="width:10%; ">';
		    			print '<img src="'.dol_buildpath('/sghr/cv/images/edit.png',2).'" class="img_edit" data-id="'.$item->id.'">  ';
		    			print '<a href="./index.php?id='.$item->id.'&action=delete&id_ecv='.$id_ecv.'" ><img src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png" class="img_delete"></a>';
		    		print'</td>';
				print '</tr>';
			}
		}else{
			print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
		}

print '</tbody></table></form><br><br>';

	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="certifcecv form_ecv">';
		print '<div class="nouveau" style="display:none">';
			print '<div style="background-color:#3c4664;padding:8px; color:white" width="100%" class="titre_ecv">Détails d\'un certificat';
			print '</div>';
			print '<div>';
				print '<table id="exp" width="100%" cellpadding="5px"; cellspadding="5px">';
					print '<thead>';
						print '<tr>';
							print '<th align="center">'.$langs->trans('ecv_etablissement_certificat').'</th>';
							print '<th align="center">'.$langs->trans('ecv_intitule_certificat').'</th>';
							print '<th align="center">'.$langs->trans('ecv_debut_certificat').'</th>';
							print '<th align="center">'.$langs->trans('ecv_fin_certificat').'</th>';
							print '<th align="center">'.$langs->trans('ecv_description_certificat').'</th>';
							print '<th align="center">'.$langs->trans('ecv_copie').'</th>';
							print '<th align="center" id="action">'.$langs->trans('Action').'</th>';
						print '</tr>';
					print '</thead>';
					print '<tbody id="tr_certificats"><input name="id_ecv" type="hidden" value="'.$id_ecv.'">';
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
    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';
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
		$( ".datepickerecvmod" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});
	    $('#select_projet').select2();
	    $('#select_tache').select2();

		$('#select_onechambre>select').select2();

		$('#nouveau').click(function(){
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Delete")); ?>');
        	$('.titre_ecv').html('<?php echo dol_escape_js(trim(preg_replace('/\s\s+/', '', $langs->trans("ecv_nouveau_certificat")))); ?>');
			$('.nouveau').show();
			$('#valider').show();
			$id=$('#tr_certificats tr').length+1; 
			$('#tr_certificats').append('<tr><td><input name="action" type="hidden" value="create"><input type="text" name="certificats['+$id+'][etablissement]" autocomplete="off" style="width:95%" /></td>  <td ><input type="text"  name="certificats['+$id+'][intitule]" autocomplete="off" style="width:95%" /></td> <td style="width:8%;"><input type="text" name="certificats['+$id+'][debut]"  value="<?php echo date('d/m/Y') ?>" class="datepickerecvmod debut" onchange="MinDate(this);" autocomplete="off" /></td> <td style="width:8%;"><input type="text" name="certificats['+$id+'][fin]" class="datepickerecvmod fin"  value="<?php echo date('d/m/Y') ?>" onchange="MaxDate(this);" autocomplete="off" /></td><td><textarea type="text" name="certificats['+$id+'][description]" autocomplete="off" style="width:95%" ></textarea></td><td><input type="file" accept="image/*" class="copie" id="copie" name="certificats['+$id+']" style="display:none;" autocomplete="off"/><a class="butAction" id="upload" onclick="getcopie(this);" style="background-color:#3c4664; color:white; float: right;" > Upload </a></td><td align="center"><img src="<?php echo dol_escape_js(DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png'); ?>" class="img_delete" onclick="delete_tr(this);"></td></tr>');
			$( ".datepickerecvmod" ).datepicker({
		    	dateFormat: 'dd/mm/yy'
			});
			$(".niveau").select2()
		});

		$('.img_edit').click(function(){
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Modify")); ?>');
        	$('.titre_ecv').html('<?php echo dol_escape_js(trim(preg_replace('/\s\s+/', '', $langs->trans("ecv_detail_certificat")))); ?>');
			$('.nouveau').show();
			$('#nouveau').hide();
			$('#valider').hide();
			$id=$(this).data('id');
			$.ajax({
				data:{'id':$id},
				url:"<?php echo dol_escape_js(dol_buildpath('/sghr/cv/certificates/data_edit.php',2)) ?>",
				type:'POST',
				success:function(data){
					$('#tr_certificats').html(data);
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
	});
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

	function getcopie(file) {
		$(file).parent().find('.copie').trigger('click');
		$(file).parent().find('span').text($(file).parent().find('.file').val());
	}
</script>
<style type="text/css">
	
	

</style>
<?php

llxFooter();