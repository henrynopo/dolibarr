<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/recrutement/class/etapescandidature.class.php');
dol_include_once('/recrutement/class/candidatures.class.php');
dol_include_once('/recrutement/lib/recrutement.lib.php');
dol_include_once('/recrutement/class/departement.class.php');
dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/core/class/html.form.class.php');



$langs->load('recrutement@recrutement');

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

if (!$user->rights->recrutement->lire) {
	accessforbidden();
}


$id_candid = GETPOST('id_candid');
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;


$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."candid_events WHERE fk_candidat=".$id_candid;

$result = $db->query($sql);
$nbtotalofrecords = $db->num_rows($result);
if (($page * $limit) > $nbtotalofrecords)	// if total resultset is smaller then paging size (filtering), goto and load page 0
{
	$page = 0;
	$offset = 0;
}


$sql .= $db->order('date', 'DESC');
$sql .= $db->plimit($limit + 1, $offset);
$resql = $db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
}



$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], $param, '', '', '', $num, $nbtotalofrecords, 'generic', 0, '', '', $limit, 0, 0, 1);

$head = menu_candidature($id_candid);
dol_fiche_head(
    $head,
    'events',
    '', 
    0,
    "recrutement@recrutement"
);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="Event_cnadid">'."\n";
	
	print '<input name="pagem" type="hidden" value="'.$page.'">';
	print '<input name="offsetm" type="hidden" value="'.$offset.'">';
	print '<input name="limitm" type="hidden" value="'.$limit.'">';
	print '<input name="filterm" type="hidden" value="'.$filter.'">';
	print '<input name="id_cv" type="hidden" value="'.$id_recrutement.'">';


	print '<table id="table-1" class="border tableforfield" style="width: 100%;" >';

		print '<tbody>';
			$colspn = 9;

			$i = 0;
			$totalarray = array();
			while ($i < min($num, $limit))
			{
				$obj = $db->fetch_object($resql);
				$event = explode('_', $obj->event);

				$typ_event = $event[0];
				$id_event = $event[1];
    			$etape = new etapescandidature($db);
    			$etape->fetch($id_event);

				print '<tr>';
					print '<td class="width150">'.dol_print_date($db->jdate($obj->date),'dayhoursec').'</td>';
					print '<td>'.$langs->trans('event_'.$typ_event).' '.$langs->trans($etape->label).'</td>';
				print '</tr>';
				$i++;
			}
			// if (count($candidatures->rows) > 0) {
			// 	for ($i=0; $i < count($candidatures->rows) ; $i++) {
			// 		$var = !$var;
			// 		$item = $candidatures->rows[$i];

			//     	$responsable = new User($db);
		 //   			$etapes = new etapescandidature($db);

			// 		print '<tr '.$bc[$var].' >';
			//     		// print '<td align="center" style=""><a href="'.dol_buildpath('/recrutement/candidatures/card.php?id='.$item->rowid,2).'" >';
			//     		// 	print $item->sujet;
			//     		// print '</a></td>';
			    		
			//     		print '<td align="center" style=""><a href="'.dol_buildpath('/recrutement/candidatures/card.php?id='.$item->rowid,2).'" >';
			//     			print $item->nom.' '.$item->prenom;
			//     		print '</a></td>';
			    		
			//     		// print '<td align="center">'.$item->prenom.' '.$item->nom.'</td>';
			//     		print '<td align="center">'.$langs->trans($item->niveau).'</td>';

			//     		print '<td align="center">';
			//     			if($item->contact){
		 //   						$contact = new Contact($db);
			//     				$contact->fetch($item->contact);
			//     				// print $contact->firstname.' '.$contact->lastname;
			//     				print $contact->getNomUrl(1);
			//     			}else{
			//     				print '<b>__</b>';
			//     			}
			//     		print '</td>';

			//     		print '<td align="left" class="etat">';
			//     				$etapes->fetch($item->etape);
			//     				print'<span class="colorstatus" style="background-color:'.$etapes->color.';"></span>&nbsp;&nbsp;';
			//     				print $langs->trans($etapes->label);
			// 	    			if($item->refuse ==1){
			// 	    				print '&nbsp;<span class="refuse"><b>(Refuser)</b></span>';
			// 	    				// print'<span style="background-color:white;color:white;padding:0 15px;"></span>&nbsp;&nbsp;';
			// 	    			}
			//     		print '</td>';

			//     		print '<td align="center">';
			// 				if($item->date_depot){
			// 					$date=explode('-', $item->date_depot);
			// 					print $date[2].'/'.$date[1].'/'.$date[0];
			// 				}
			// 			print '</td>';

			//     		print '<td align="center" style="">';
			//     			if($item->responsable){
			// 	    			$responsable->fetch($item->responsable);
			// 	    			print $responsable->getNomUrl(0);
			//     			}else{ 
			//     				print '<b>__</b>';
			//     			}
		 //    			PRINT '</td>';

			//     		print '<td align="center" style="">';
			// 	    		if($item->departement){
		 //   						$departement = new departements($db);
			// 	    			$departement->fetch($item->departement);
			// 	    			print '<a href="'.dol_buildpath('/recrutement/departements/card.php?id='.$item->departement,2).'" >'.$departement->label.'</a>';
			// 	    		}
			//     		print '</td>';
			    		
			// 			print '<td align="center"></td>';
			// 		print '</tr>';
			// 	}
			// }else{
			// 	print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
			// }
		print '</tbody>';
	print '</table>';
print '</form>';

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
  //               window.location.href="<?php echo dol_buildpath('/recrutement/candidatures/candidature-v2.php',2);?>";
  //       		$('.board').show();
  //       		$('.list').hide();
  //       	}
  //       });

	});
</script>


<style type="text/css">
	
<?php

