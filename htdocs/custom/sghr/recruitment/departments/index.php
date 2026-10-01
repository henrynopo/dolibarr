<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
dol_include_once('/sghr/recruitment/class/Department.class.php');
dol_include_once('/core/class/html.form.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$langs->load('recruitment@sghr');

$modname = $langs->trans("departements");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$poste  = new JobPosition($db);
$Department        = new departements($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= (int) $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}


$srch_label 		= GETPOST('srch_label');
$srch_gestionnaire    = GETPOST('srch_gestionnaire');

$date = explode('/', $srch_date);
$date = $date[2]."-".$date[1]."-".$date[0];

$filter .= (!empty($srch_label)) ? " AND label like '%".addslashes($srch_label)."%'" : "";

$filter .= (!empty($srch_gestionnaire)) ? " AND gestionnaire = ".$srch_gestionnaire."" : "";


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
	$srch_label = "";
	$srch_gestionnaire = "";
	$srch_module = "";
	$srch_date = "";
}

// echo $filter;

$nbrtotal = $Department->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);


print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<input name="pagem" type="hidden" value="'.$page.'">';
	print '<input name="offsetm" type="hidden" value="'.$offset.'">';
	print '<input name="limitm" type="hidden" value="'.$limit.'">';
	print '<input name="filterm" type="hidden" value="'.$filter.'">';
	print '<input name="id_cv" type="hidden" value="'.$id_recrutement.'">';

	print '<div style="float: right; margin: 8px;">';
		print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
	print '</div>';

	print '<table id="table-1" class="noborder tablerecrutement" style="width: 100%;" >';
		print '<thead>';
		
			print '<tr class="liste_titre">';
				print_liste_field_titre($langs->trans("label_departement"),$_SERVER["PHP_SELF"], "label", '', '', 'align="center"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("gestionnaire"),$_SERVER["PHP_SELF"], "gestionnaire", '', '', 'align="center"', $sortfield, $sortorder);
				print '<th align="center"></th>';
			print '</tr>';

			print '<tr class="liste_titre nc_filtrage_tr">';
				print '<td align="center"><input style="max-width: 129px;" class="" type="text" class="" id="srch_label" name="srch_label" value="'.$srch_label.'"/></td>';
				print '<td align="center">'.$poste->select_user($srch_gestionnaire,'srch_gestionnaire',1,"rowid","login").'</td>';
				print '<td align="center">';
					print '<input type="image" name="button_search"  src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
					print '&nbsp;<input type="image" name="button_removefilter"  src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
				print '</td>';
			print '</tr>';	
		print '</thead>';

		print '<tbody>';
			$colspn = 7;
			if (count($Department->rows) > 0) {
				for ($i=0; $i < count($Department->rows) ; $i++) {
					$var = !$var;
					$item = $Department->rows[$i];

			    	$user_=new User($db);
			    	$user_->fetch($item->gestionnaire);
					print '<tr '.$bc[$var].' >';
			    		print '<td align="center" style="">'; 
				    		print '<a href="'.dol_buildpath('/sghr/recruitment/departements/card.php?id='.$item->rowid,2).'" >';
				    			print $item->label;
				    		print '</a>';
			    		print '</td>';
			    		print '<td align="center" style="">'.$user_->getNomUrl(1).'</td>';
						print '<td align="center"></td>';
					print '</tr>';
				}
			}else{
				print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
			}
		print '</tbody>';
	print '</table>';
print '</form>';


?>
<script>
	$( function() {
	$( ".datepicker" ).datepicker({
    	dateFormat: 'dd/mm/yy'
	});
	$('#srch_gestionnaire').select2();
	$('#srch_fk_product').select2();
	} );
</script>

<?php

llxFooter();