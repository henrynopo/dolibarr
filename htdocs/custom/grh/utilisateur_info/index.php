<?php 
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/utilisateur_info/class/utilisateur_info.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$moduleName = "Infos des salariés";
$var 		= true;

// // Get parameters
$request_method = $_SERVER['REQUEST_METHOD'];
$action  = GETPOST('action', 'alpha');
$id      = (int) ( (!empty($_GET['id'])) ? $_GET['id'] : GETPOST('id') ) ;
$form   = new Form($db);
$htmlother      = new FormOther($db);



$User = new User($db);
$user_info 	= new utilisateur_info($db);




$sortfield 		= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 		= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$userid 	  	= GETPOST('userid','int');
$declar 	  	= GETPOST('declar','int');
$filter 		= GETPOST('filter');

 
 $filter_user = GETPOST('spot');
 $filter_dossier = trim(GETPOST('filter_num_dossier'));


// $filter .= (!empty($userid) && $userid != -1) ? " AND userid = ". $db->escape($userid)."\n" : "";
// if (!empty($userid) && $userid != -1) {
// 	$userid = GETPOST('userid','int');
// }
// echo "<br><br><br>".$filter;

$limit 	= 10;
$page 	= GETPOST("page",'int');
if ($page == "")
	$page = 0;
$offset = $limit * $page;

if(GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	$userid = "";
    $filter_user = "";
}

if ($page==-1) {
	$limit = 0;
	$offset =0;
}

if (empty($page) || $page == 0){
	$offset = 1;
}

/*----PDF-----*/
if (!empty($id) && $action == "pdf") {
	
	require_once DOL_DOCUMENT_ROOT.'/gestion_plan/pv_livraison/pdf/pdf.lib.php';
    $pdf->SetFont('times', '', 9, '', true);
    $pdf->AddPage();

	require_once DOL_DOCUMENT_ROOT.'/gestion_plan/pv_livraison/tpl/pv_livraison.tpl.php';    
	//$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
    $pdf->writeHTML($html, true, false, true, false, '');
   	$pdf->Output('pv_livraison-'.$id.'.pdf', 'I');
	die();
}


llxHeader(array(), $langs->trans($moduleName),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans($moduleName));    // TITLE
?>
<script>
  $( function() {
	   $('#select_user').select2();
  });
</script>

<?php 
function field($titre,$champ,$style=""){
	print '<th class="liste_titre" style="padding:5px; 0 5px 5px; text-align:center;">'.$titre.'<br>';
	print '<a href="?sortfield='.$champ.'&amp;sortorder=desc">';
	print '<span class="nowrap"><img src="'.dol_buildpath('/grh/img/1uparrow.png',2).'" title="Z-A" class="imgup" border="0"></span>';
	print '</a>';
	print '<a href="?sortfield='.$champ.'&amp;sortorder=asc">';
	print '<span class="nowrap"><img src="'.dol_buildpath('/grh/img/1downarrow.png',2).'" title="A-Z" class="imgup" border="0"></span>';
	print '</a>';
	print '</th>';
}
$all_users = $user_info->getAllUsers($userid);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="index_infousers">';
	print '<div style="clear:both; width: 100% !important;">';
	print '</div>';
	print'<table id="tab_list" width="100%" class="noborder bbc_tab_list">';
		print'<theader>';
			print '<tr class="liste_titre">';
			    print '<th align="center">Action</th>';
				print '<th align="center">Utilisateur</th>';
				print '<th align="center">Salaire de base ('.$langs->getCurrencySymbol($conf->currency).')</th>';
				print '<th align="center">CIN</th>';
				print '<th align="center">Immatriculation de la CNSS</th>';
				print '<th align="center">Salarié déclaré</th>';
			    print '<th align="center"></th>';
			print '</tr>';
			
			print '<tr class="liste_titre">';
				print'<td></td>';
				print'<td>';
				print $form->select_users($userid,'userid',1);
				print'</td>';
				print'<td colspan="3"></td>';
				print'<td>';
				// print '<select name="declar" id="declar">
    //             			<option value="0" '.$slct0.'>Non</option>
    //             			<option value="1" '.$slct1.'>Oui</option>
    //         			</select>';
				print'</td>';
			    print '<td>';
				print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
				print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
				print '</td>';
			print '</tr>';


		print'</theader>';
		print'<tbody>';
		foreach($all_users as $u) {
			$var = !$var;
			print '<tr '.$bc[$var].'>';
			print '<td align="center" style="width:166px;"><a style="color:#000;white-space:nowrap;" href="'.dol_buildpath('/grh/utilisateur_info/card/show.php?user_id='.$u->rowid,1).'"><i class="fa fa-eye" ></i> Afficher</a></td>';
			print '<td align="left">';
				print '<img src="'.dol_buildpath('/grh/img/object_user.png',1).'" class="classfortooltip">';
				print '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$u->rowid.'">'.$u->firstname.' '.$u->lastname.'</a>';
	      	print '</td>';
	      	print '<td align="center">'.number_format($u->salary_base,2,","," ").'</td>';
	      	print '<td align="center">'.$u->cin.'</td>';
	      	print '<td align="center">'.$u->immatriculation.'</td>';
	      	print '<td align="center">';
	      	 if ($u->stag == 0) {
	      	 	if ($u->declar == 1) {
	      	 		print 'Oui';
	      	 	}else{
		      	 	print 'Non';
	      	 	}
      	 	}else{
      	 		print '-';
      	 	}
	      	print '</td>';
	      	print '<td></td>';
			print '</tr>';
		}

			print'</tbody></table></form>';

llxFooter();
if (is_object($db)) $db->close();
?>