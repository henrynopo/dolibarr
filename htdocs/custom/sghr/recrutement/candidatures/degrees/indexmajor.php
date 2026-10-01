<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 


dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/recrutement/class/candidatures.class.php');
dol_include_once('/sghr/recrutement/lib/recrutement.lib.php');


$langs->load('recrutement@sghr');

$modname = $langs->trans("majors");

$majors   = new rect_majors($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}
$id_degree = GETPOST('id_degree');

$srch_label 		= GETPOST('srch_label');

$date = explode('/', $srch_date);
$date = $date[2]."-".$date[1]."-".$date[0];

$filter .= (!empty($srch_label)) ? " AND label like '%".addslashes($srch_label)."%'" : "";


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
	$srch_color = "";
	$srch_module = "";
	$srch_date = "";
}

if($id_degree){
	$filter .= ' AND fk_degree='.$id_degree;
}

$nbrtotal = $majors->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbtotalofrecords = $majors->fetchAll('', '', 0, 0, $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

$newcardbutton = dolGetButtonTitle($langs->trans("Add"), '', 'fa fa-plus-circle', 'cardmajor.php?id_degree='.$id_degree.'&action=add', '', 1, $params);
print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $nbrtotal, $nbtotalofrecords, '', 0, $newcardbutton, '', $limit, 1, 0, 1);


$head = menu_degrees($id_degree);
dol_fiche_head($head, 'majors', $langs->trans('major'), -1, 'generic');

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="list_majors">'."\n";
	print '<input name="pagem" type="hidden" value="'.$page.'">';
	print '<input name="offsetm" type="hidden" value="'.$offset.'">';
	print '<input name="limitm" type="hidden" value="'.$limit.'">';
	print '<input name="filterm" type="hidden" value="'.$filter.'">';
	print '<input name="id_degree" type="hidden" value="'.$id_degree.'">';

	print '<table id="table-1" class="noborder" style="width: 100%;" >';
		
		print '<thead>';
			print '<tr class="liste_titre">';
				print_liste_field_titre($langs->trans("Label"),$_SERVER["PHP_SELF"], "label", '', '', 'align="center"', $sortfield, $sortorder);
				print '<th align="center"></th>';
			print '</tr>';

			print '<tr class="liste_titre nc_filtrage_tr">';
				print '<td align="center"><input style="max-width: 129px;" class="" type="text" class="" id="srch_label" name="srch_label" value="'.$srch_label.'"/></td>';
				print '<td align="center">';
					$searchpicto = $form->showFilterButtons();
					print $searchpicto;
				print '</td>';
			print '</tr>';
		print '</thead>';

		print '<tbody>';
			$colspn = 7;
			if (count($majors->rows) > 0) {
				for ($i=0; $i < count($majors->rows) ; $i++) {
					$var = !$var;
					$item = $majors->rows[$i];

					print '<tr '.$bc[$var].' >';
			    		print '<td align="center" style="">'; 
				    		print '<a href="'.dol_buildpath('/sghr/recrutement/candidatures/degrees/cardmajor.php?id='.$item->rowid.'&id_degree='.$id_degree,2).'" >';
				    			print $item->label;
				    		print '</a>';
			    		print '</td>';
			    		
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

<?php

llxFooter();