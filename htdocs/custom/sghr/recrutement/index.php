<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

dol_include_once('/sghr/recrutement/class/postes.class.php');
dol_include_once('/sghr/recrutement/class/candidatures.class.php');
dol_include_once('/sghr/recrutement/class/departement.class.php');
dol_include_once('/core/class/html.form.class.php');


$langs->load('recrutement@sghr');

$modname = $langs->trans("postes");
$candidatures  = new candidatures($db);
$departement   = new departements($db);
$postes  	   = new postes($db);
$postes2  	   = new postes($db);
$form          = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= (int) $_GET['id'];
$action   			= $_GET['action'];

$objdocs  = new postes($db);
$modtxt = 'recrutement';
global $dolibarr_main_data_root;
if (!dolibarr_get_const($db, strtoupper($modtxt).'_CHANGEPATHDOCS',0)){
	$source = dol_buildpath('/uploads/'.$modtxt);
	if(@is_dir($source)){
		$docdir = $dolibarr_main_data_root.'/'.$modtxt;
		$dmkdir = dol_mkdir($docdir, '', 0755);
		if($dmkdir >= 0){
			@chmod($docdir, 0775);
			$dcopy = dolCopyDir($source, $docdir, 0775, 1);
			// if($dcopy >= 0){
				dolibarr_set_const($db, strtoupper($modtxt).'_CHANGEPATHDOCS',1,'chaine',0,'',0);
				$objdocs->recrutementpermissionto($docdir);
			// }
		}
	}
}

if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}

$postes->upgradeModuleRecrut();

// $srch_rowid 		= GETPOST('srch_rowid');
$srch_label 		= GETPOST('srch_label');
$srch_status    = GETPOST('srch_status');
$srch_departement = GETPOST('srch_departement');
$srch_nb_nouveauemploye     = GETPOST('srch_nb_nouveauemploye');
$srch_date = GETPOST('srch_date');
$date = explode('/',$srch_date);
$date = $date[2].'-'.$date[1].'-'.$date[0];


$filter .= (!empty($srch_label)) ? " AND label like '%".addslashes($srch_label)."%'" : "";

$filter .= (!empty($srch_departement)) ? " AND departement = ".$srch_departement."" : "";

$filter .= (!empty($srch_nb_nouveauemploye)) ? " AND nb_nouveauemploye =".$srch_nb_nouveauemploye."" : "";

$filter .= (!empty($srch_status)) ? " AND status like '%".$srch_status."%'" : "";

$filter .= (!empty($srch_date)) ? " AND CAST(date as date) =  '".$date."'" : "";

// die($filter);
$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	$filter = "";
	$offset = 0;
	$filter = "";
	$srch_rowid = "";
	$srch_label = "";
	$srch_nb_nouveauemploye = '';
	$srch_departement = 0;
	$srch_status = "";
	$srch_date = "";
}



$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

	$nbrtotalnofiltr = $postes2->fetchAll();
	$nbrtotal = $postes->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
	print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
	$arr_count=[0=>0,1=>0,2=>0];
	for ($i=0; $i <count($postes->rows) ; $i++) { 
		$item=$postes->rows[$i];
		if($item->status == 'Recrutementencours'){
			$arr_count[0]++;
		}
		else if($item->status=='Recrutementfinalise'){
			$arr_count[1]++;
		}
		else if($item->status=='Recrutementarrete'){
			$arr_count[2]++;
		}
	}

print '<div class="div_h posterecrutement">';
	print '<div class="statusdetailcolorsback" style="display: block;">';

		print '<span class="statusname STATUSPROPAL_0">';
			print '<span class="colorstatus en-cours" ></span>';
			print '<span class="labelstatus "><span class="counteleme"> '.$arr_count[0].'</span></span>&nbsp;';
			print $langs->trans('Recrutementencours');
		print '</span>';

		print '<span class="statusname STATUSPROPAL_1">';
			print '<span class="colorstatus finalis" ></span>';
			print '<span class="labelstatus"><span class="counteleme"> '.$arr_count[1].'</span></span>&nbsp;';
			print $langs->trans('Recrutementfinalise');
		print '</span>';

		print '<span class="statusname STATUSPROPAL_2">';
			print '<span class="colorstatus arret" ></span>';
			print '<span class="labelstatus"><span class="counteleme"> '.$arr_count[2].'</span></span>&nbsp;';
			print $langs->trans('Recrutementarrete');
		print '</span>';

	print '</div>';

	print '<div style="float: right; margin-bottom: 8px; width:20%">';
		print '<a href="card.php?action=add" class="butAction" style="float:right" >'.$langs->trans("Add").'</a><br>';
	print '</div>';
print '</div>';


print '<div class="list recrutmodule">';
	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="posterecrutement">'."\n";
		print '<input name="pagem" type="hidden" value="'.$page.'">';
		print '<input name="offsetm" type="hidden" value="'.$offset.'">';
		print '<input name="limitm" type="hidden" value="'.$limit.'">';
		print '<input name="filterm" type="hidden" value="'.$filter.'">';
		print '<input name="id_cv" type="hidden" value="'.$id_recrutement.'">';

		print '<table id="table-1" class="noborder tablerecrutement" style="width: 100%;" >';
			print '<thead>';
				print '<tr class="liste_titre">';
					print_liste_field_titre($langs->trans("PosteOccupe"),$_SERVER["PHP_SELF"], "label", '', '', 'align="left"', $sortfield, $sortorder);
					print_liste_field_titre($langs->trans("departement"),$_SERVER["PHP_SELF"], "departement", '', '', 'align="left"', $sortfield, $sortorder);
					print_liste_field_titre($langs->trans("nb_nouveauemploye"),$_SERVER["PHP_SELF"], "nb_nouveauemploye", '', '', 'align="left"', $sortfield, $sortorder);
					print_liste_field_titre($langs->trans("date_pr_empbouche"),$_SERVER["PHP_SELF"], "date", '', '', 'align="left"', $sortfield, $sortorder);
					// print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"], "status", '', '', 'align="left"', $sortfield, $sortorder);
					print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"], "status", '', '', 'align="left"', $sortfield, $sortorder);
					print '<th align="left"></th>';
				print '</tr>';

				print '<tr class="liste_titre nc_filtrage_tr">';

					print '<td align="left"><input style="width: 96%;" class="" type="text" class="" id="srch_label" name="srch_label" value="'.$srch_label.'"/></td>';

					print '<td align="left">'.$postes->select_departement($srch_departement,'srch_departement',1,"rowid","login").'</td>';

					print '<td align="left"><input style="max-width: 129px;" class="" type="number" class="" id="srch_nb_nouveauemploye" name="srch_nb_nouveauemploye" value="'.$srch_nb_nouveauemploye.'" min="0" /></td>';

					print '<td align="left">';
						print '<input style="max-width: 129px;" type="text" class="datepickerncon" id="srch_date" name="srch_date" value="'.$srch_date.'" autocomplete="off" />';
					print '</td>';

					print '<td align="left">';
						$select = '<select id="srch_status" name="srch_status">';
							$select .= '<option value=""></option>';
							$select .= '<option value="Recrutementencours">'.$langs->trans("Recrutementencours").'</option>';
							$select .= '<option value="Recrutementfinalise">'.$langs->trans("Recrutementfinalise").'</option>';
							$select .= '<option value="Recrutementarrete">'.$langs->trans("Recrutementarrete").'</option>';
						$select .= '</select>';
						$select=str_replace('value="'.$srch_status.'"', 'value="'.$srch_status.'" selected', $select);
						print $select;
					print '</td>';

					print '<td align="left">';
						print '<input type="image" name="button_search"  src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
						print '&nbsp;<input type="image" name="button_removefilter"  src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
					print '</td>';
					
				print '</tr>';
			print '</thead>';

			print '<tbody>';
				$colspn = 7;
				if (count($postes->rows) > 0) {
					for ($i=0; $i < count($postes->rows) ; $i++) {
						$var = !$var;
						$item = $postes->rows[$i];

				    	$departement = new departements($db);
				    	$departement->fetch($item->departement);

						print '<tr '.$bc[$var].' >';
				    		print '<td align="left" >'; 
								$postes->fetch($item->rowid);
								print $postes->getNomUrl(1);
				    		print '</td>';
				    		print '<td align="left" style="">';
				    			print '<a href="'.dol_buildpath('/sghr/recrutement/departements/card.php?id='.$departement->rowid,2).'" >';
					    			print $departement->label;
					    		print '</a>';
				    		print '</td>';
				    		print '<td align="left" style="">'.$item->nb_nouveauemploye.'</td>';
				    		print '<td align="left">';
								if($item->date){
									$date=explode('-', $item->date);
									print $date[2].'/'.$date[1].'/'.$date[0];
								}
							print '</td>';
				    		print '<td class="status">';
				    		if($item->status == 'Recrutementencours'){
								$cl='en-cours';
								$arr_count[0]++;
							}
							else if($item->status=='Recrutementfinalise'){
								$cl='finalis';
								$arr_count[1]++;
							}
							else if($item->status=='Recrutementarrete'){
								$cl='arret';
								$arr_count[2]++;
							}
							print'<span class="color_etat '.$cl.'" ><b>';
							print '</span>&nbsp;&nbsp;';
				    		print '<b>'.$langs->trans($item->status).'</b>';
				    		print '</td>';

							print '<td align="left"></td>';
						print '</tr>';
					}
				}else{
					print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
				}
			print '</tbody>';

		print '</table>';
		
	print '</form>';
print '</div>';



print ' <div class="board" style="width:100% !important; display:none">';
	$postes->fetchAll($sortorder, $sortfield, $limit, $offset);
	$nb=count($postes->rows);
	for ($i=0; $i < $nb; $i++) { 
		$item=$postes->rows[$i];
	   	print ' <div class="board-column todo" style="width:100% !important">';
		    // print ' <div class="board-column-header">;To do</div>';
		    print ' <div class="board-column-content-wrapper"  style="width:100% !important">';
		       	print ' <div class="board-column-content" style="width:100% !important">';
			       	print ' <div class="board-item" >';
			       		print ' <div class="board-item-content" style="margin:0 20px 20px">';
			       			print '<div class="item-content">';
				       			print '<div > <span>'.$item->label.'</span> <a href="./card.php?id='.$item->id.'&action=edit"><img align="right" src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/edit.png"></a> <br><br></div>';
				       			if($item->status == "Recrutementencours"){
				       				print '<div > <a href="'.dol_buildpath('/sghr/recrutement/candidatures/candidature.php?id_poste='.$item->id,2).'" class="button" ><b>'.$langs->trans("candidatures").'</b></a> </div>';
				       			}
				       			if($item->status == "Recrutementarrete"){
				       				print '<a class="button" style="background-color:#00A09D !important;color:white !important;  " data-id="'.$item->rowid.'" id="lancer" >'.$langs->trans('lancer').'</a>';
				       			}

			       			print '</div>';
			       			print '<div class="bottom">';
			       				print '<div style="float:left"> <a href="./cv/index.php?poste='.$item->id.'"> <img src="'.DOL_MAIN_URL_ROOT.'/theme/eldy/img/object_dir.png"></a></div>';

			       				$candidatures->fetchAll('','',0,0,' AND poste ='.$item->rowid.' AND refuse = 0');
								$nb_candidature=count($candidatures->rows);
			       				print '<div style="text-align:right;"><span style="font-size:14px;">'.$nb_candidature.' '.$langs->trans("employe_recrute").'</span></div>';
			       			print '</div>';
			       		print '</div>';
			       	print'</div>';
		   		print ' </div>';
		    print ' </div>';
	  	print '</div>';
	}
print '</div>';

?>


<script>
	$(function(){
		// $('.list').hide();
        $('#list').css('background-color','rgba(0, 0, 0, 0.15)');

		$( ".datepicker" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});

		$('select#srch_status').select2();

		$('#lancer').click(function(){
            $id=$('#lancer').data('id');
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=lancer',2) ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#lancer').css('display','none');
                        $('#arret').css('display','block');
                    }
                        location.reload();
                }
            });
        });
        
        $('.icon_list').click(function(){
        	$type=$(this).data('type');
        	if($type == 'list'){
        		$('#grid').css('background-color','white');
        		$('#list').css('background-color','rgba(0, 0, 0, 0.15)');
        		$('.board').hide();
        		$('.list').show();
        	}
        	if($type == 'grid'){
        		$('#list').css('background-color','white');
        		$('#grid').css('background-color','rgba(0, 0, 0, 0.15)');
        		$('.board').css('display','inline-table');
        		$('.list').hide();
        	}
        });
	});
</script>
<?php
llxFooter();