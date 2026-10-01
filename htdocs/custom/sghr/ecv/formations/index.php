<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/ecvformations.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/ecv/lib/ecv.lib.php');


$langs->load('ecv@sghr');

$modname = $langs->trans("ecv_formations");

// Initial Objects
$ecv  = new ecv($db);
$ecvformations = new ecvformations($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->cv->read) {
	accessforbidden();
}


$id_ecv = GETPOST('id_ecv');
$id = GETPOST('id');
$action = GETPOST('action');
$request_method = $_SERVER['REQUEST_METHOD'];

if ($action == 'create' && $request_method === 'POST') {
	$formations=GETPOST('formations');
	$id_ecv=GETPOST('id_ecv');
	$ecv->fetch($id_ecv);
	// print_r($formations);die;
	foreach ($formations as $key => $value) {
		if(!empty($value['debut'])){
			$date_d = explode('/',$value['debut']);
		    $debut = $date_d[2]."-".$date_d[1]."-".$date_d[0];
		}

		if(!empty($value['fin'])){
	    	$date_f = explode('/',$value['fin']);
	    	$fin = $date_f[2]."-".$date_f[1]."-".$date_f[0];
		}
     	if(!empty($value['no_jours'])){
	    	$nosjours=1;
	    }else
	    	$nosjours=0;

		$data =  array( 
	        'etablissement' =>  addslashes($value['etablissement']),
	        'debut'    	   	=>  $debut,
	        'fin'      	   	=>  $fin,
	        'niveau'  	   	=>  $value['niveau'],
	        'filiere' 		=>  addslashes($value['filiere']),
	        'intitule'      =>  addslashes($value['intitule']),
	        'fk_ecv'   	    =>  $id_ecv,
	        'nosjours'      =>  $nosjours,
	        'fk_user'  	    =>  $ecv->fk_user,
	        'entity'  	    =>  $conf->entity,
	    );
		$isvalid = $ecvformations->create($data, 1);
		
	}
    header('Location: index.php?id_ecv='.$id_ecv);
    exit;
}

if ($action == 'edit' && $request_method === 'POST') {
    $niveau = GETPOST('niveau');
    $filiere = addslashes(GETPOST('filiere'));
    $intitule = addslashes(GETPOST('intitule'));
    $etablissement = addslashes(GETPOST('etablissement'));
    $niveau = GETPOST('niveau');
    $no_jours = GETPOST('no_jours');
    $date_d = explode('/',GETPOST('debut'));
    $debut = $date_d[2]."-".$date_d[1]."-".$date_d[0];

    $date_f = explode('/',GETPOST('fin'));
    $fin = $date_f[2]."-".$date_f[1]."-".$date_f[0];

    $entity  = (GETPOSTISSET('entity') ? GETPOST('entity', 'int') : $conf->entity);

    	
    $ecv->fetch($id_ecv);
    if(!empty($no_jours)){
    	$nojours=1;
    }else
    	$nojours=0;
    $data =  array( 
        'etablissement'  =>  $etablissement,
        'debut'    	     =>  $debut,
        'fin'      	     =>  $fin,
        'filiere'  	     =>  $filiere,	
        'niveau'         =>  $niveau,	
        'intitule'       =>  $intitule,	
        'fk_ecv'   	     =>  $id_ecv,
	    'nosjours'       =>  $nojours,
        'fk_user'  	     =>  $ecv->fk_user,
        'entity'  	     =>  $entity,
    );


    $isvalid = $ecvformations->update($id, $data);
    if ($isvalid > 0) {
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    } 
}

$id_ecv         = GETPOST('id_ecv');$ecv->fetch($id_ecv);
$filter .= (!empty($id_ecv)) ? " AND fk_ecv = '".$id_ecv."'  AND fk_user=".$ecv->fk_user : "";

// print_r($filter);

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
    $ecvformations->fetch($id);
    $error =  $ecvformations->delete();
    if ($error == 1) {
        header('Location: index.php?id_ecv='.$id_ecv);
        exit;
    }
    else {      
        header('Location: index.php');
        exit;
    }
}

$nbrtotal = $ecvformations->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

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
    dol_fiche_head($head,'formations','',	0,"ecvformations@ecvformations");
}

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="form_ecv">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
print '<input name="id_ecv" type="hidden" value="'.$id_ecv.'">';



print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';


field($langs->trans("ecv_etablissement_formation"),'etablissement_formation');
field($langs->trans("ecv_niveau"),'ecv_niveau');
field($langs->trans("ecv_intitule_formation"),'ecv_intitule_formation');
field($langs->trans("ecv_filiere"),'ecv_filiere');
field($langs->trans("ecv_debut_formation"),'ecv_debut_formation');
field($langs->trans("ecv_fin_formation"),'ecv_fin_formation');
print '<th align="center">'.$langs->trans("Action").'</th>';


print '</tr>';

print '</thead><tbody>';
	$colspn = 7;
	
		if (count($ecvformations->rows) > 0) {
			for ($i=0; $i < count($ecvformations->rows) ; $i++) {
				$var = !$var;
				$item = $ecvformations->rows[$i];

				// $d=explode(' ', $item->debut);
				$date_d = explode('-', $item->debut);
		    	$debut = $date_d[2]."/".$date_d[1]."/".$date_d[0];

		    	// $f=explode(' ', $item->fin);
				$date_f = explode('-', $item->fin);
		    	$fin = $date_f[2]."/".$date_f[1]."/".$date_f[0];

				print '<tr '.$bc[$var].' >';
		    		print '<td align="left" style=""> '.$item->etablissement.'</td>';
		    		print '<td align="center" style="">'.$langs->trans($item->niveau).'</td>';
		    		print '<td align="left" style="">'.$item->intitule.'</td>';
		    		print '<td align="left" style="">'.$item->filiere.'</td>';
		    		print '<td align="center" style="">'.$debut.'</td>';
		    		print '<td align="center" style="">';
		    		if($item->nosjours == 1){
		    			print 'Nos jours';
		    		}elseif($item->nosjours == 0)
		    			print $fin;
		    		print '</td>';
					print '<td align="center" style="width:10%; "><img src="'.dol_buildpath('/sghr/ecv/images/edit.png',2).'" class="img_edit" data-id="'.$item->id.'">  <a href="./index.php?id='.$item->id.'&action=delete&id_ecv='.$id_ecv.'" ><img src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png" class="img_delete"></a></td>';
				print '</tr>';
			}
		}
		else{
			print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
		}

print '</tbody></table></form><br><br>';

	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="form_ecv">';
		print '<div class="nouveau" style="display:none">';
			print '<div style="background-color:#3c4664;padding:8px; color:white" width="100%" class="titre_ecv">Détails d\'une expérience';
			print '</div>';
			print '<div>';
				print '<table id="exp" width="100%" cellpadding="5px"; cellspadding="5px">';
					print '<thead>';
						print '<tr>';
							print '<th align="center">'.$langs->trans('ecv_etablissement_formation').'</th>';
							print '<th align="center">'.$langs->trans('ecv_niveau').'</th>';
							print '<th align="center">'.$langs->trans('ecv_intitule_formation').'</th>';
							print '<th align="center">'.$langs->trans('ecv_filiere').'</th>';
							print '<th align="center">'.$langs->trans('ecv_debut_formation').'</th>';
							print '<th align="center">'.$langs->trans('ecv_fin_formation').'</th>';
							print '<th align="center" id="action">'.$langs->trans('Action').'</th>';
						print '</tr>';
					print '</thead>';
					print '<tbody id="tr_formations"><input name="id_ecv" type="hidden" value="'.$id_ecv.'">';
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


$Qualifiant = preg_replace('/\s\s+/', '', $langs->trans("Qualifiant")." ");
$Master_recherche = preg_replace('/\s\s+/', '', $langs->trans("Master_recherche")." ");
$Master_recherche = preg_replace('/\s\s+/', '', $langs->trans("Master_recherche")." ");


?>
<script>
	$(document).ready(function() {
		$('.fiche').find('.tabBar').removeClass('tabBarWithBottom');
		$( ".datepickerecvmod" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});
	    $('#select_projet').select2();
	    $('#select_tache').select2();
		$('#select_onechambre>select').select2();

		var $txtform = '';
		$('#nouveau').click(function(){
			$txtform = '';
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Delete")); ?>');
        	$('.titre_ecv').html('<?php echo dol_escape_js($langs->trans("ecv_nouveau_formation")); ?>');
			$('.nouveau').show();
			$('#valider').show();
			$id=$('#tr_formations tr').length+1; 

			$txtform += '<tr><td><input name="action" type="hidden" value="create"><input type="text" name="formations['+$id+'][etablissement]" autocomplete="off" style="width:95%" /></td> <td style="width:10%;"> <select class="niveau flat" id="niveau" name="formations['+$id+'][niveau]" ><option value="0" ></option><option value="Qualifiant"  ><?php echo dol_escape_js($Qualifiant); ?></option><option value="Bac" >Bac</option> <option value="Bac+1" >Bac+1</option><option value="Bac+2" >Bac+2</option><option value="Bac+3" >Bac+3</option><option value="Bac+4" >Bac+4</option><option value="Bac+5" >Bac+5</option><option value="Master_specialisé" ><?php echo dol_escape_js($Master_recherche); ?></option><option value="Master_recherche" ><?php echo dol_escape_js($Master_recherche); ?></option> <option value="Doctorat" >Doctorat</option></select> </td> <td ><input type="text"  name="formations['+$id+'][intitule]" autocomplete="off" style="width:95%" /></td> <td><input type="text"  name="formations['+$id+'][filiere]" autocomplete="off" style="width:95%" /></td><td style="width:8%;"><input type="text" name="formations['+$id+'][debut]" class="datepickerecvmod debut" value="<?php echo date('d/m/Y'); ?>" onchange="MinDate(this);" autocomplete="off" /></td> <td style="width:8%;"><input type="text" name="formations['+$id+'][fin]" value="<?php echo date('d/m/Y'); ?>" class="datepickerecvmod fin" onchange="MaxDate(this);" autocomplete="off" /><div align="center"><label><input type="checkbox" name="formations['+$id+'][no_jours]" onchange="no_jourss(this);"> <b class="nos_jour"><?php echo dol_escape_js($langs->trans("ecv_no_jours")); ?></b></label></div> </td><td align="center"><img src="<?php echo dol_escape_js(DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png'); ?>" class="img_delete" onclick="delete_tr(this);"></td></tr>';

			$txtform = $txtform.replace( /[\r\n]+/gm, "" );
			$('#tr_formations').append($txtform);

			$( ".datepickerecvmod" ).datepicker({
		    	dateFormat: 'dd/mm/yy'
			});
			// $(".niveau").select2()
		});

		$('.img_edit').click(function(){
        	$('#action').html('<?php echo dol_escape_js($langs->trans("Modify")); ?>');
        	$('.titre_ecv').html('<?php echo dol_escape_js($langs->trans("ecv_detail_formation")); ?>');
			$('.nouveau').show();
			$('#valider').hide();
			$('#nouveau').hide();
			$id=$(this).data('id');
			$.ajax({
				data:{'id':$id},
				url:"<?php echo dol_escape_js(dol_buildpath('/sghr/ecv/formations/data_edit.php',2)) ;?>",
				type:'POST',
				success:function(data){
					$('#tr_formations').html(data);
            		no_jourss('#no_jours');
					textarea_autosize();
				}
			});
		});

		$( ".datepickerecvmod" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
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
	function textarea_autosize(){
	  	$(".form_ecv textarea").each(function(textarea) {
		    $(this).height($(this)[0].scrollHeight);
		    $(this).css('resize', 'none');
		});
	}
	
	function no_jourss(check){
		$checked_=$(check).prop('checked');
		if($checked_ == true){
			$(check).parent().parent().parent().find('.fin').hide();
		}else
			$(check).parent().parent().parent().find('.fin').show();
		
	}

</script>

<?php

llxFooter();