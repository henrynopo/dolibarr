<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/docsemployes/class/type_document.class.php');
dol_include_once('/core/class/html.form.class.php');

$langs->load('docsemployes@sghr');

$modname = $langs->trans("Liste_des_type_document");

// Initial Objects
$type_document  = new type_document($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->docs->read) {
	accessforbidden();
}


$srch_name 			= GETPOST('srch_name');

$filter .= (!empty($srch_name)) ? " AND name like '%".addslashes($srch_name)."%'" : "";

// debut
$date = explode('/', $srch_debut);
$debut = $date[2]."-".$date[1]."-".$date[0];

$filter .= (!empty($srch_debut)) ? " AND CAST(debut as date) = '".$debut."' " : "";

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
	$srch_name = "";
	$srch_debut = "";
}

// echo $filter;

$nbrtotal = $type_document->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<input name="pagem" type="hidden" value="'.$page.'">';
	print '<input name="offsetm" type="hidden" value="'.$offset.'">';
	print '<input name="limitm" type="hidden" value="'.$limit.'">';
	print '<input name="filterm" type="hidden" value="'.$filter.'">';

	print '<div style="float: right; margin: 8px;">';
		print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
	print '</div>';

	print '<table id="table-1" class="noborder" style="width: 100%;" >';
		print '<thead>';
		
			print '<tr class="liste_titre">';
				print_liste_field_titre($langs->trans("Name"),$_SERVER["PHP_SELF"], "rowid", '', '', 'align="center"', $sortfield, $sortorder);
				print '<th align="center">'.$langs->trans("Action").'</th>';
			print '</tr>';

			print '<tr class="liste_titre nc_filtrage_tr">';
				print '<td align="center"><input style="max-width: 129px;" type="text" class="" id="srch_name" name="srch_name" value="'.$srch_name.'"/></td>';
				print '<td align="center">';
					print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
					print '&nbsp;<input type="image" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
				print '</td>';
			print '</tr>';

		print '</thead>';

		print '<tbody>';
			$colspn = 2;
			if (count($type_document->rows) > 0) {
				for ($i=0; $i < count($type_document->rows) ; $i++) {
					$var = !$var;
					$item = $type_document->rows[$i];
					print '<tr '.$bc[$var].' >';
			    		print '<td align="center" style="">'; 
			    			print '<a href="'.dol_buildpath('/sghr/docsemployes/type_document/card.php?id='.$item->rowid,2).'" >'.$item->name.'</a>';
			    		print '</td>';
			    		print '<td></td>';
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
	} );
</script>
<style type="text/css">
	
</style>
<?php

llxFooter();