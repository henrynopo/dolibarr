<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/charge_mois/class/charge_mois.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
$langs->load('grh@grh');
$var 				= true;
$sortfield 			= $_GET['sortfield'];
$sortorder 			= $_GET['sortorder'];
$charge_mois_cat    = new charge_mois_cat($db);
$id 				= $_GET['id'];
$action   			= $_GET['action'];
$srch_libele     		= GETPOST('srch_libele');

$filter .= (!empty($srch_libele)) ? " AND libele like '%".$srch_libele."%' " : "";

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$srch_libele = "";
}

/*-------------excel-----------------*/
if ( $action == "excel" ) {
	$charge_mois_cat->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
	$filename="Liste_Categories.xls";
	require_once dol_buildpath('/grh/charge_mois/types/liste_types_xsl.php');
	die();
}
/*-------------excel-----------------*/

$charge_mois_cat->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

llxHeader(array(), $langs->trans('Liste_des_Catégories'),'','','','','',0,0);
print_fiche_titre($langs->trans('Liste_des_Catégories'));


print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";

print "<style>\n.pagination button{ padding: 3px 10px; display: block; margin: 0; }\n";
print ".pagination button.active{ background: #5999A7; color: #fff; } </style>";
	
print '<div style="float: right;">';
print '<button name="action" id="btn_excel" class="butAction" value="excel" style="font-weight: 700;">'.$langs->trans('Export Excel ').'</button>&nbsp;&nbsp;&nbsp;<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
print '<div style="clear:both; width: 100% !important;"></div><br></div>';

print '<table id="table-1" class="noborder" width="100%" >';
print '<thead>';

function field($titre,$champ){
	print '<th class="liste_titre" id="'.$champ.'" style="padding:5px; 0 5px 5px; text-align:center;">'.$titre.'<br>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=desc">';
		print '<span class="nowrap"><img src="' . DOL_URL_ROOT . '/theme/allscreens/img/1uparrow.png" alt="" title="Z-A" class="imgup" border="0"></span>';
		print '</a>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=asc">';
		print '<span class="nowrap"><img src="' . DOL_URL_ROOT . '/theme/allscreens/img/1downarrow.png" alt="" title="A-Z" class="imgup" border="0"></span>';
		print '</a>';
	print '</th>';
}

print '<tr class="liste_titre">';

field($langs->trans("Ref"),'id_cat');
field($langs->trans("Label"),'name');
print '<th></th>';

print '</tr>';

print '<tr class="liste_titre">';
print '<td style="color: transparent;"></td>';
print '<td><input type="text" name="srch_libele" value="'.$srch_libele.'"/></td>';
print "<td>";
	print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '&nbsp;<input type="image" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"></td>';
print '</tr>';
print '</thead><tbody>';


	for ($i=0; $i < count($charge_mois_cat->rows) ; $i++) {
		$var = !$var;
		$item = $charge_mois_cat->rows[$i];

		print '<tr '.$bc[$var].' >';
    		print '<td style="padding:1%;" align="center">'.$charge_mois_cat->getNomUrl(1,$item->id_cat, $item->id_cat).'</td>';
			print '<td align="left">'.$item->libele.'</td>';
			print '<td align="center" class="action">';

				print '<a title="Modifier" href="card.php?action=edit&id='.$item->id_cat.'" ><img src="'.img_picto($langs->trans("edit"),'edit.png','','',1).'" /></a>';
				print '<a title="Supprimer" href="card.php?action=delete&id='.$item->id_cat.'" ><img src="'.img_picto($langs->trans("delete"),'delete.png','','',1).'" /></a>';

			print '</td>';
		print '</tr>';
	}


print '</tbody></table></form>';

llxFooter();