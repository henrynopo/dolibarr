<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/sghr/recrutement/class/etapescandidature.class.php');
dol_include_once('/sghr/recrutement/class/candidatures.class.php');
dol_include_once('/sghr/recrutement/class/departement.class.php');
dol_include_once('/sghr/recrutement/class/postes.class.php');
dol_include_once('/core/class/html.form.class.php');

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';


$langs->load('recrutement@sghr');

$modname = $langs->trans("candidatures");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$candidatures 	= new candidatures($db);
$candidatures2 	= new candidatures($db);
$postes      	= new postes($db);
$etapes      	= new etapescandidature($db);
$form        	= new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}


$srch_sujet 		= GETPOST('srch_sujet');
$srch_nom 		    = GETPOST('srch_nom');
$srch_prenom 		= GETPOST('srch_prenom');
$srch_poste 		= GETPOST('srch_poste');
$srch_contact 		= GETPOST('srch_contact');
$srch_etape         = GETPOST('srch_etape');
$srch_date          = GETPOST('srch_date');
$srch_responsable   = GETPOST('srch_responsable');
$srch_departement   = GETPOST('srch_departement');
$selectyear         = GETPOST('selectyear');
$date = explode('/',$srch_date);
$date = $date[2].'-'.$date[1].'-'.$date[0];

$filter .= (!empty($srch_sujet)) ? " AND sujet like '%".addslashes($srch_sujet)."%'" : "";
// if($srch_nom){
// 	$arr_nom = explode(' ', $srch_nom);
// 	if($arr_nom[0] && $arr_nom[1]){
// 	}else
// 		$filter .= " AND ( nom LIKE '%".$srch_nom."%' OR prenom LIKE '%".$srch_nom."%')";
// }
$filter .= (!empty($srch_nom)) ? " AND rowid = ".$srch_nom : "";
// $filter .= (!empty($srch_prenom)) ? " AND prenom LIKE '%".$srch_prenom."%' " : "";

$filter .= (!empty($srch_poste)) ? " AND poste =".$srch_poste."" : "";

$filter .= (!empty($srch_etape)) ? " AND etape =".$srch_etape."" : "";

$filter .= (!empty($srch_niveau)) ? " AND niveau like '%".addslashes($srch_niveau)."%'" : "";

$filter .= (!empty($srch_contact)) ? " AND contact = ".$srch_contact."" : "";

$filter .= (!empty($srch_date)) ? " AND CAST(date_depot as date) =  '".$date."'" : "";

$filter .= (!empty($srch_responsable)) ? " AND responsable = ".$srch_responsable."" : "";

$filter .= (!empty($srch_departement)) ? " AND departement = ".$srch_departement."" : "";

// die($filter);

// echo $filter;

$gridorlist = '';
if(isset($_GET['gridorlist'])){
  if ($_GET['gridorlist'] == "GRID") {
    $res = dolibarr_set_const($db, 'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST', 'GRID', 'chaine', 0, '', $conf->entity);
    $gridorlist = "GRID";
  } else {
    $res = dolibarr_set_const($db, 'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST', 'LIST', 'chaine', 0, '', $conf->entity);
    $gridorlist = "LIST";
  } 
}else{
  if(dolibarr_get_const($db,'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST',$conf->entity))
    $gridorlist = dolibarr_get_const($db,'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST',$conf->entity);
}

if ($gridorlist == "GRID"){
    header('Location: '.dol_buildpath('/sghr/recrutement/candidatures/kanban.php',2));
}




$limit 	= $conf->liste_limit+1;
$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
$yearnow = date('Y');


if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	$filter = "";
	$offset = 0;
	$filter = "";
	$srch_rowid = "";
	$srch_nom = "";
	$srch_poste = "";
	$srch_sujet = "";
	$srch_etape = 0;
	$srch_niveau = 0;
	$srch_contact = 0;
	$srch_date = "";
	$srch_responsable = 0;
	$srch_departement = 0;
	$selectyear = -1;
}
if (!empty($selectyear) && $selectyear != -1 ) {
	$filter .= " AND YEAR(date_depot) = '".$selectyear."'";
}elseif($selectyear == -1){
	$filter.='';
}


$nbrtotal = $candidatures->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbrtotalnofiltr = $candidatures2->fetchAll();

// echo $conf->liste_limit;
$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

// $nbtotalofrecords = '';
// // die($conf->global->MAIN_DISABLE_FULL_SCANLIST);
// if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
// {
// 	$result = $db->query($sql);
// 	$nbtotalofrecords = $db->num_rows($result);
// 	if (($page * $limit) > $nbtotalofrecords)	// if total resultset is smaller then paging size (filtering), goto and load page 0
// 	{
// 		$page = 0;
// 		$offset = 0;
// 	}
// }
print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr,'',0,'','',$limit);
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr,'',0,'','',$limit);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="kanban_recrut">'."\n";
	print '<div class="div_h">';
		print '<div style="float: left; width:10%; margin-bottom: 8px;">';
			print '<div>';
				print '<a class="icon_list" data-type="list" href="'.dol_buildpath('/sghr/recrutement/candidatures/index.php?gridorlist=LIST',2).'"> <img  src="'.dol_buildpath('/sghr/recrutement/img/list.png',2).'" style="height:30px" id="list" ></a>';
				print '<a class="icon_list" data-type="grid" href="'.dol_buildpath('/sghr/recrutement/candidatures/kanban.php?gridorlist=GRID',2).'"> <img src="'.dol_buildpath('/sghr/recrutement/img/grip.png',2).'" style="height:30px" ></a> ';
			print '</div>';
		print '</div>';
		
		print '<div class="statusdetailcolorsback" style="display: block;">';
			$etapes->fetchAll();
			$arr_etapes=[];
			for ($i=0; $i <count($etapes->rows); $i++) { 
				$etape=$etapes->rows[$i];
				$arr_etapes[$etape->rowid]=0;
				for ($j=0; $j < count($candidatures2->rows) ; $j++) { 
					$candidat=$candidatures2->rows[$j];
					if($candidat->etape == $etape->rowid){ $arr_etapes[$etape->rowid]++; };
				}
					print '<span class="statusname STATUSPROPAL_0">';
						print '<span class="colorstatus" style="background:'.$etape->color.';"></span>';
						print '<span class="labelstatus"><span class="counteleme">'.$arr_etapes[$etape->rowid].'</span></span>&nbsp';
						print $langs->trans($etape->label);
					print '</span>';
			}
			// print_r($arr_etapes);die();
		print '</div>';

		print '<div style="float: left; margin-bottom: 8px; width:20%">';
			print '<a href="card.php?action=add" class="butAction" style="float:right">'.$langs->trans("Add").'</a>';
		print '</div>';
	print '</div>';

	print '<div style="width:100%; float:left" >';
		print'<select style="float:left;" id="selectyear" name="selectyear">';
			$years = $candidatures->getYears("date_depot");
			// die($selectyear);
			print'<option value="-1" >'.$langs->trans("Toutes").'</option>';
			krsort($years);
			foreach ($years as $key => $value) {
				$slctd2="";
				if($key == $selectyear){
					$slctd2="selected";
				}
				print'<option value="'.$key.'" '.$slctd2.'>'.$key.'</option>';
			}
		print'</select>';
		print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</div>';
	
	print '<div class="list">';
			print '<input name="pagem" type="hidden" value="'.$page.'">';
			print '<input name="offsetm" type="hidden" value="'.$offset.'">';
			print '<input name="limitm" type="hidden" value="'.$limit.'">';
			print '<input name="filterm" type="hidden" value="'.$filter.'">';
			print '<input name="id_cv" type="hidden" value="'.$id_recrutement.'">';


			print '<table id="table-1" class="noborder tablerecrutement" style="width: 100%;" >';
				print '<thead>';
					print '<tr class="liste_titre">';

						// print_liste_field_titre($langs->trans("sujet"),$_SERVER["PHP_SELF"], "sujet", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("nom_candidat"),$_SERVER["PHP_SELF"], "nom", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("poste"),$_SERVER["PHP_SELF"], "poste", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("contact"),$_SERVER["PHP_SELF"], "contact", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("situation"),$_SERVER["PHP_SELF"], "etape", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("date_depot"),$_SERVER["PHP_SELF"], "date_depot", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("responsable"),$_SERVER["PHP_SELF"], "responsable", '', '', 'align="left"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("departement"),$_SERVER["PHP_SELF"], "departement", '', '', 'align="left"', $sortfield, $sortorder);
						print '<th align="left"></th>';


					print '</tr>';

					print '<tr class="liste_titre nc_filtrage_tr">';
						print '<td align="left">'.$candidatures->select_with_filter($srch_nom,'srch_nom').'</td>';
						// print '<td align="left"><input placeholder="'.$langs->trans("Lastname").'" class="maxwidth100" type="text" id="srch_nom" name="srch_nom" value="'.$srch_nom.'"/> <input placeholder="'.$langs->trans("Firstname").'" class="maxwidth100" type="text" id="srch_prenom" name="srch_prenom" value="'.$srch_prenom.'"/></td>';
						print '<td align="left">'.$postes->select_with_filter($srch_poste,"srch_poste").'</td>';
						print '<td align="left">'.$candidatures->select_contact($srch_contact,'srch_contact').'</td>';
						print '<td align="left">'.$candidatures->select_etapes($srch_etape,'srch_etape').'</td>';
						print '<td align="left">';
							print '<input style="max-width: 129px;" type="text" class="datepickerncon" id="srch_date" name="srch_date" value="'.$srch_date.'" autocomplete="off" />';
						print '</td>';
						print '<td align="left">'.$postes->select_user($srch_responsable,'srch_responsable').'</td>';
						print '<td align="left">'.$postes->select_departement($srch_departement,'srch_departement').'</td>';
						print '<td align="left">';
							print '<input type="image" name="button_search"  src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
							print '&nbsp;<input type="image" name="button_removefilter"  src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
						print '</td>';
					print '</tr>';
				print '</thead>';

				print '<tbody>';
			$colspn = 9;
			if (count($candidatures->rows) > 0) {
				for ($i=0; $i < count($candidatures->rows) ; $i++) {
					$var = !$var;
					$item = $candidatures->rows[$i];

			    	$responsable = new User($db);
		   			$etapes = new etapescandidature($db);

					print '<tr '.$bc[$var].' >';
			    		// print '<td align="center" style=""><a href="'.dol_buildpath('/sghr/recrutement/candidatures/card.php?id='.$item->rowid,2).'" >';
			    		// 	print $item->sujet;
			    		// print '</a></td>';
			    		
			    		print '<td align="left" style=""><a href="'.dol_buildpath('/sghr/recrutement/candidatures/card.php?id='.$item->rowid,2).'" >';
			    			print $item->nom.' '.$item->prenom;
			    		print '</a></td>';
			    		
			    		// print '<td align="left">'.$item->prenom.' '.$item->nom.'</td>';
			    		print '<td align="left" style="">';
			    			if($item->poste){
			    				$poste = new postes($db);
				    			$poste->fetch($item->poste);
				    			print $poste->getNomUrl(0);
			    			}else{ 
			    				print '<b>__</b>';
			    			}
		    			PRINT '</td>';
			    		// print '<td align="left">'.$langs->trans($item->niveau).'</td>';

			    		print '<td align="left">';
			    			if($item->contact){
		   						$contact = new Contact($db);
			    				$contact->fetch($item->contact);
			    				// print $contact->firstname.' '.$contact->lastname;
			    				print $contact->getNomUrl(1);
			    			}else{
			    				print '<b>__</b>';
			    			}
			    		print '</td>';

			    		print '<td align="left" class="etat">';
			    				$etapes->fetch($item->etape);
			    				print'<span class="colorstatus" style="background-color:'.$etapes->color.';"></span>&nbsp;&nbsp;';
			    				print $langs->trans($etapes->label);
				    			if($item->refuse ==1){
				    				print '&nbsp;<span class="refuse"><b>(Refuser)</b></span>';
				    				// print'<span style="background-color:white;color:white;padding:0 15px;"></span>&nbsp;&nbsp;';
				    			}
			    		print '</td>';

			    		print '<td align="left">';
							if($item->date_depot){
								$date=explode('-', $item->date_depot);
								print $date[2].'/'.$date[1].'/'.$date[0];
							}
						print '</td>';

			    		print '<td align="left" style="">';
			    			if($item->responsable){
				    			$responsable->fetch($item->responsable);
				    			print $responsable->getNomUrl(0);
			    			}else{ 
			    				print '<b>__</b>';
			    			}
		    			PRINT '</td>';

			    		print '<td align="left" style="">';
				    		if($item->departement){
		   						$departement = new departements($db);
				    			$departement->fetch($item->departement);
				    			print '<a href="'.dol_buildpath('/sghr/recrutement/departements/card.php?id='.$item->departement,2).'" >'.$departement->label.'</a>';
				    		}
			    		print '</td>';
			    		
						print '<td align="left"></td>';
					print '</tr>';
				}
			}else{
				print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
			}
				print '</tbody>';
			print '</table>';
	print '</div>';
print '</form>';

function field($titre,$champ){
	global $langs;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=desc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/recrutement/img/1uparrow.png',2).'" alt="" title="Z-A" class="imgup" border="0"></span>';
		print '</a>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=asc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/recrutement/img/1downarrow.png',2).'" alt="" title="A-Z" class="imgup" border="0"></span>';
		print '</a>';
	print '</th>';
}
llxFooter();
$db->close();
?>
<script>
	$(function(){
        $('#list').css('background-color','rgba(0, 0, 0, 0.15)');
		$( ".datepicker" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});
		$('select#niveau').select2();

		// $('.icon_list').click(function(){
  //       	$type=$(this).data('type');
  //       	if($type == 'list'){
  //       		$('#grid').css('background-color','white');
  //       		$('#list').css('background-color','rgba(0, 0, 0, 0.15)');
  //       		$('.board').hide();
  //       		$('.list').show();
  //       	}
  //       	if($type == 'grid'){
  //       		$('#list').css('background-color','white');
  //       		$('#grid').css('background-color','rgba(0, 0, 0, 0.15)');
  //               window.location.href="<?php echo dol_buildpath('/sghr/recrutement/candidatures/candidature-v2.php',2);?>";
  //       		$('.board').show();
  //       		$('.list').hide();
  //       	}
  //       });

	});
</script>


<style type="text/css">
	
<?php

