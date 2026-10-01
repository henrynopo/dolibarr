<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
dol_include_once('/sghr/recruitment/class/Candidate.class.php');
dol_include_once('/sghr/recruitment/class/RecruitCv.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/recruitment/lib/recrutement.lib.php');


$langs->load('recruitment@sghr');

$modname = $langs->trans("cv");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$JobPosition  = new JobPosition($db);
$Candidate  = new Candidate($db);
$cv        = new cv_recrutement($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= (int) $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}


$srch_poste       = GETPOST('srch_poste');
$srch_type 		  = GETPOST('srch_type');
$srch_date 		  = GETPOST('srch_date');
$srch_nom 		  = GETPOST('srch_nom');
$srch_date 		  = GETPOST('srch_date');
$srch_poste       = GETPOST('srch_poste');
$srch_candidature = GETPOST('srch_candidature');



// die($id_poste);
$date=explode('/', $srch_date);
$date=$date[2].'-'.$date[1].'-'.$date[0];

$filter .= (!empty($srch_nom)) ? " AND nom LIKE '%".$srch_nom."%'" : "";

$filter .= (!empty($srch_type)) ? " AND type LIKE '%".$srch_type."%'" : "";

$filter .= (!empty($srch_candidature)) ? " AND candidature = ".$srch_candidature."" : "";

$filter .= (!empty($srch_poste)) ? " AND candidature IN (SELECT rowid FROM ".MAIN_DB_PREFIX."Candidate WHERE poste = ".$srch_poste.") " : "";

$filter .= (!empty($srch_date)) ? " AND CAST(date as date) = '".$date."' " : "";


// echo $filter;die();

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
	$srch_nom = "";
	$srch_poste = "";
	$srch_candidature = "";
	$srch_type = "";
	$srch_date = "";
}

// echo $filter; die();

$nbrtotal = $cv->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
// print_r($cv->rows);die();
$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);
print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
if($poste){
	$id_general=$poste;
}elseif($candidature){
	$id_general=$candidature;
}
if(!empty($id_general)){

	$head = menu_candidature($id_general);
	if($action != 'add'){
	    dol_fiche_head(
	        $head,
	        'cv',
	        '', 
	        0,
	        "recruitment@sghr"
	    );
	}
}


print '<div style="float: left; margin-bottom: 8px; width:100%">';
	print '<div  style="">';
		print '<a class="icon_list" data-type="list"> <img  src="'.dol_buildpath('/sghr/recruitment/img/list.png',2).'" style="height:30px" id="list" ></a>';
		print '<a class="icon_list" data-type="grid"> <img src="'.dol_buildpath('/sghr/recruitment/img/grip.png',2).'" style="height:30px" id="grid" ></a> ';
		if($candidature){
			print '<a href="card.php?action=add&candidature='.$candidature.'" class="butAction" id="add">'.$langs->trans("Add").'</a>';
		}
	print '</div>';
print '</div>';
print '<div style="float: right; margin-bottom: 8px;">';
print '</div>';


print '<div  class="list" style="width:100% !important;">';
	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'?candidature='.$id_candidature.'">'."\n";
		print '<input name="pagem" type="hidden" value="'.$page.'">';
		print '<input name="offsetm" type="hidden" value="'.$offset.'">';
		print '<input name="limitm" type="hidden" value="'.$limit.'">';
		print '<input name="filterm" type="hidden" value="'.$filter.'">';
		print '<input name="poste" type="hidden" value="'.$poste.'">';
		print '<input name="candidature" type="hidden" value="'.$id_candidature.'">';
		print '<input name="type" type="hidden" value="list">';

		print '<table id="table-1" class="noborder tablerecrutement" style="width: 100%;" >';
			print '<thead>';

			print '<tr class="liste_titre">';
				
				print_liste_field_titre($langs->trans("nom"),$_SERVER["PHP_SELF"], "nom", '', '', 'align="left"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("poste"),$_SERVER["PHP_SELF"], "", '', '', 'align="left"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("candidature"),$_SERVER["PHP_SELF"], "candidature", '', '', 'align="center"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("type"),$_SERVER["PHP_SELF"], "type", '', '', 'align="center"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("date"),$_SERVER["PHP_SELF"], "date", '', '', 'align="center"', $sortfield, $sortorder);
				print '<th align="center">'.$langs->trans("Action").'</th>';

			print '</tr>';

			print '<tr class="liste_titre nc_filtrage_tr">';

				// print '<td align="center"><input style="max-width: 129px;" class="" type="text" class="" id="srch_rowid" name="srch_rowid" value="'.$srch_rowid.'"/></td>';

				print '<td align="left"><input class="" type="text" class="" id="srch_nom" name="srch_nom" value="'.$srch_nom.'"  /></td>';
				print '<td align="left">'.$JobPosition->select_postes($srch_poste,'srch_poste').'</td>';

				print '<td align="center">'.$Candidate->select_candidatures($srch_candidature,'srch_candidature').'</td>';


				print '<td align="center">';
					print '<select id="srch_type" name="srch_type" >';
						print '<option value="" ></option>';
						print '<option value="url" >Url</option>';
						print '<option value="fichier" >Fichier</option>';
					print '</select>';
				print '</td>';

				print '<td align="center"><input style="max-width: 129px;" class="datepickerncon" type="text"  id="srch_date" name="srch_date" value="'.$srch_date.'"  /></td>';

				print '<td align="center">';
					print '<input type="image" name="button_search"  src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
					print '&nbsp;<input type="image" name="button_removefilter"  src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
				print '</td>';
			print '</tr>';

			print '</thead>';
			print '<tbody>';
				$colspn = 7;
				if (count($cv->rows) > 0) {
					for ($i=0; $i < count($cv->rows) ; $i++) {
						$var = !$var;
						$item = $cv->rows[$i];
						$date=explode('-', $item->date);
						$date=$date[2].'/'.$date[1].'/'.$date[0];

				    	// print_r($Candidate);
				    	// print_r('<br>');
						print '<tr '.$bc[$var].' >';
				    		print '<td align="left" style="">'; 
				    		print '<a href="'.dol_buildpath('/sghr/recruitment/cv/card.php?id='.$item->rowid.'&candidature='.$id_candidature,2).'" >';
					    		print $item->nom;
				    		print '</a>';
				    		print '</td>';

				    		print '<td align="left" style="">';
				    			$candidature  = new Candidate($db);
				    			if($item->candidature){
							    	$candidature->fetch($item->candidature);
							    	if($candidature->poste){
							    		$poste = new JobPosition($db);
								    	$poste->fetch($candidature->poste);
						    			print '<a href="'.dol_buildpath('/sghr/recruitment/card.php?id='.$candidature->poste,2).'"> '.$poste->label.' </a>';
							    	}
				    			}
				    		print '</td>';

				    		print '<td align="center" style="">';
				    			if($item->candidature){
							    	$candidature->fetch($item->candidature);
							    	print $candidature->getNomUrl();
			    				}
				    		print '</td>';
							print '<td align="center">'.$langs->trans($item->type).'</td>';
							print '<td align="center">'.$date.'</td>';
							print '<td align="center">';
								if($item->type == 'fichier'){

								 	$minifile = getImageFileNameForSize($item->fichier, '');  
                                    print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=recrutement&file=Candidate/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'"  title="'.$minifile.'">'.img_mime('test.pdf').'</a>' ;

								}elseif($item->type == 'url'){
									print '<a  href="'.$item->fichier.'" target="_blank"  name="action"  ><img alt="Photo" style="height:20px; max-width: 20px;" src="'.dol_buildpath('/sghr/recruitment/img/url.png',2).'" ></a>';
								}
							print '</td>';
						print '</tr>';
					}
					// die();
				}else{
					print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
				}

				print '<br>';
			print '</tbody>';
		print '</table>';
	print '</form>';
print '</div>';
print '<br><br>';

print '<div class="board" style="display: inline-block;width:100% !important; display:none;" >';

	$cv->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
	$nb_=count($cv->rows);
	for ($i=0; $i < $nb_; $i++) { 
		$item=$cv->rows[$i];
	   	print ' <div class="board-column todo" style="width:100% !important;>';
		    print ' <div class="board-column-content-wrapper"  style="width:100% !important">';
		       	print ' <div class="board-column-content" style="width:100% !important">';
			       	print ' <div class="board-item" >';
			       		print ' <div class="board-item-content" style="margin:0 20px 20px">';
			       			print '<div class="item-content">';

			       				print '<div class="imagecontainer">';
				       				if($item->type =='fichier'){
										$upload_dir = $conf->recrutement->dir_output.'/Candidate/'.$item->candidature.'/cv/'.$id.'/';
						                $array_img=[
			                                'pdf'   => dol_buildpath('/sghr/recruitment/images/pdf.png',2),
			                                'doc'   => dol_buildpath('/sghr/recruitment/images/doc.png',2),
			                                'docx'  => dol_buildpath('/sghr/recruitment/images/doc.png',2),
			                                'ppt'   => dol_buildpath('/sghr/recruitment/images/ppt.png',2),
			                                'pptx'  => dol_buildpath('/sghr/recruitment/images/ppt.png',2),
			                                'xls'   => dol_buildpath('/sghr/recruitment/images/xls.png',2),
			                                'xlsx'  => dol_buildpath('/sghr/recruitment/images/xls.png',2),
			                                'txt'   => dol_buildpath('/sghr/recruitment/images/txt.png',2),
			                                'sans'  => dol_buildpath('/sghr/recruitment/images/sans.png',2),
			                            ];
			                            
			                            $ext = explode(".",$item->fichier);
			                            $ext = $ext[count($ext) - 1];

		                                $minifile = getImageFileNameForSize($item->fichier, '');  

		                                if(in_array($ext, ['png','jpeg','jpg','gif','tif'])){
		                                	// print '<img alt="Photo" style="height:80px; max-width: 90px;" src="'.$upload_dir.$minifile.'" >';
		                                        print '<img class="photo" style="height:80px; max-width: 90px;" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=recrutement&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file=Candidate/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'&perm=download" border="0" name="image" >';
		                                }
		                                else{
		                                    if(array_key_exists($ext, $array_img)){
		                                        $src = $array_img[$ext];
		                                    }else{
		                                        $src = $array_img['sans'];
		                                    }

		                                        print '<img class="photo" style="height:80px; max-width: 90px;" src="'.$src.'" border="0" name="image" >';
		                                }


				       				}
				       				else
					                    print '<img alt="Photo" style="height:80px; max-width: 90px;" src="'.dol_buildpath('/sghr/recruitment/img/url.png',2).'" >';


			       				print '</div>';

			       				print '<div class="detailcontainer">';
			       					print '<div ><a href="'.dol_buildpath('recruitment/cv/card.php?id='.$item->rowid,2).'"><strong>'.$item->nom.'</strong></a></div>';
			       					if($item->poste){
				       					$JobPosition->fetch($item->poste);
				       					print '<div class="txt"> <strong>'.$langs->trans('PosteOccupe').': </strong>'.$JobPosition->label.'</div>';
			       					}
			       					if($item->candidature){
			       						$Candidate->fetch($item->candidature);
				       					print '<div class="txt"> <strong>'.$langs->trans('candidat').': </strong>'.$Candidate->sujet.'</div>';
			       					}
			       					$date=explode('-', $item->date);
			       					$date=$date[2].'/'.$date[1].'/'.$date[0];
			       					print '<div class="txt">'.$date.'</div>';
			       				print '</div>';
			       				
			       			print '</div>';
			       		print '</div>';
			       	print'</div>';
		   		print ' </div>';
		    print ' </div>';
	  	// print '</div>';
	}
print '</div>';


function field($titre,$champ){
	global $langs,$id_candidature;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		print '<a href="?candidature='.$id_candidature.'&sortfield='.$champ.'&amp;sortorder=desc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/recruitment/img/1uparrow.png',2).'" alt="" title="Z-A" class="imgup" border="0"></span>';
		print '</a>';
		print '<a href="?candidature='.$id_candidature.'&sortfield='.$champ.'&amp;sortorder=asc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/recruitment/img/1downarrow.png',2).'" alt="" title="A-Z" class="imgup" border="0"></span>';
		print '</a>';
	print '</th>';
}

?>
<script>
	$(function() {
		$('.fiche').find('.tabBar').removeClass('tabBarWithBottom');
        $('#list').css('background-color','rgba(0, 0, 0, 0.15)');
		$( ".datepicker" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});

		$('#srch_type').select2();

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
        		$('.board').show();
        		$('.list').hide();
        	}
        });
    });
</script>


<style>

	div.tabBarWithBottom {
		 border-bottom: 0px; 
	}
	#add{
		float: right;
	}
	.candidature{
		color: rgb(255, 255, 255);
	    background-color: rgb(0, 109, 107);
	    border-color: rgb(0, 96, 94);
	}
	.board {
	  position: relative;
	  margin-left: 1%;
	}
	.board-column {
	  width: 30%;
	  border-radius: 3px;
	}
	
	.board-column.todo .board-column-header {
	  background: #4A9FF9;
	}
	.board-column.working .board-column-header {
	  background: #f9944a;
	}
	.board-column.done .board-column-header {
	  background: #2ac06d;
	}
	
	.board-item-content {
	  background: #fff;
	  border-radius: 4px;
	  font-size: 15px;
	  -webkit-box-shadow: 0px 1px 3px 0 rgba(0,0,0,0.2);
	  box-shadow: 0px 1px 3px 0 rgba(0,0,0,0.2);
	}
	.board-item{
		width:33.33%; 
		float:left;
	}

	.item-content{
	  padding:0px;
	}
	.button{
		/*background: rgb(60,70,100); */
	    text-decoration: none;
	    /*font-weight: bold;*/
	    margin: 0em 0.9em !important;
	    padding: 0.4em 0.5em;
	    font-family: roboto,arial,tahoma,verdana,helvetica;
	    display: inline-block;
	    text-align: center;
	    cursor: pointer;
	     color: #fff; 
	    color: #444;
	     border: 1px solid #aaa; 
	     border-color: rgba(0, 0, 0, 0.15) rgba(0, 0, 0, 0.15) rgba(0, 0, 0, 0.25); 
	}
	.bottom{
		background: rgb(60,70,100);
	  	padding: 5px;
		color: white;
	}
	.txt{
		/*font-family: "Roboto";*/
	    font-size: 14px;
	    font-weight: 400;
	    line-height: 1.5;
	    color: #666666;
	    text-align: left;
	}
	@media (max-width: 600px) {
	  .board-item-content {
	    text-align: center;
	  }
	  .board-item-content span {
	    display: none;
	  }
	  
	}
	.icon_list{
		cursor: pointer;
		/*text-decoration: none;*/
		display: inline-block;
	}
	.icon_list:hover{
		text-decoration: none;
		/*padding: 5px;*/

	}

	.icon_list:hover img{
		background-color: rgba(0, 0, 0, 0.15);
	}
	.select2{
		/*width: 50% !important;*/
	}
</style>

<?php

llxFooter();