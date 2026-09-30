<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/charge_mois/class/charge_mois.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
$langs->load('grh@grh');
$var 				= true;
$charge_mois 		= new charge_mois($db);
$form 				= new Form($db);
$sortfield 			=  $_GET['sortfield'];
$sortorder 			=  $_GET['sortorder'];
$formother      	= new FormOther($db);
$search_id 	  		= GETPOST('search_id','int');
$search_cat = GETPOST('search_cat');
$search_discription = GETPOST('search_discription');
$search_montant    	= GETPOST('search_montant');
$search_year     	=  GETPOST('search_year');
$search_month     	= GETPOST('search_month');
$action   			= GETPOST('action','alpha');

/*-------------XSL----------------*/
if ( $action == "exl" ) {

$filename="charge_mois_".$search_year."_".$search_month.".xls";
      require_once dol_buildpath('/grh/charge_mois/tpl/charge_mois_xsl.php');
 die();
 
}
/*-------------XSL----------------*/

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
$search_id      		= '';
$search_cat      		= '';
$search_montant    		= '';
$search_discription		= '';
$search_year			= '';
$search_month    		= '';
}
$filter .= (!empty($search_id) && $search_id != -1) ? " AND id = ". $db->escape($search_id)."\n" : "";
$filter .= (!empty($search_montant) && $search_montant != -1) ? " AND cast(montant as decimal(10,1)) = '".str_replace(",", ".", $db->escape($search_montant))."'":"";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) = ".$db->escape($search_year) : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) = ".$db->escape($search_month) : "";
$filter .= (!empty($search_cat) && $search_cat != -1) ? " AND cat = ".$db->escape($search_cat)."" : "";
$filter .= (!empty($search_discription)) ? " AND discription like '%".$db->escape($search_discription)."%'" : "";

$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/charge_mois.js");
llxHeader(array(), $langs->trans('charge_mois'),'','','','',$morejs,0,0);
print_fiche_titre($langs->trans('charge_mois'));	// TITLE

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<div style="float: right;margin-bottom: 8px;">';
print '<a href="./index.php?action=exl" style="float:right; margin-bottom: 9px;" name="action" id="btn_excel" class="butAction" value="exl">'.$langs->trans('Export_Excel').'</a>';
print '<a href="./types/index.php" class="butAction" >'.$langs->trans("Gestion_des_Catégories").'</a>';
print '<a style="" href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
print '</div>';
print '<table id="table-1" class="noborder" style="min-width:100%;width:auto; clear: both;">';
print '<thead>';
print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"], "id", '', '', 'style="padding:1%;" align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("discription"),$_SERVER["PHP_SELF"], "discription", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("date"),$_SERVER["PHP_SELF"], "datec", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("cat"),$_SERVER["PHP_SELF"], "cat", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Amount") ,$_SERVER["PHP_SELF"], "montant", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre(" " ,$_SERVER["PHP_SELF"], "", '', '', 'align="center"', $sortfield, $sortorder);
print '</tr>';
print '<tr class="liste_titre">';
	print '<td class="liste_titre" colspan="1"></td>';
	print '<td align="center"><input type="text" value="'.$search_discription.'" name="search_discription"/></td>';
	print '<td class="liste_titre" align="center">';
	print $form->selectarray('search_year', $charge_mois->get_part_date(), $search_year, 1, 0, 0);
	print $form->selectarray('search_month', $charge_mois->get_part_date('month',' AND year(`datec`) = '.$search_year), $search_month, 1, 0, 0);
	print '</td>';
	print '<td class="liste_titre" align="center">';
	print $form->selectarray('search_cat',$charge_mois->get_cat(), $search_cat, 1, 0, 0);
	print '</td>';
	print '<td class="liste_titre" align="center">';
	print '<input type="number" step="0.1" value="'.$search_montant.'" name="search_montant" style="width:100%;"/>';
	print '</td>';
	print '<td align="center">';
	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	print '</td>';
print '</tr>';
print '</thead><tbody>';
	$charge_mois->fetchAll($sortorder, $sortfield, 20, $offset, $filter);
	$total ;
	for ($i=0; $i < count($charge_mois->rows) ; $i++) {
		$var = !$var;
		$item = $charge_mois->rows[$i];
			print '<tr '.$bc[$var].' >';
		    	print '<td style="padding:1%;" >'.$charge_mois->getNomUrl(1,  $item->id, $item->id).'</td>';
				print '<td>'.$item->discription.'</td>';
				print '<td align="center">'.date("d/m/Y", $item->datec).'</td>';
				print '<td align="center">'. $charge_mois->get_cat_libele($item->cat).'</td>';
				print '<td align="right" style="">'.number_format($item->montant,2).'</td>';
				$total += $item->montant;
				print '<td></td>';
			print '</tr>';
	}
            print '<tr>';
                print '<td align="center" colspan="3"></td>';
                print '<td align="left" ><strong>'.$langs->trans('total').'</strong> </td>';
                print '<td align="right" style="" ><strong>'. number_format($total,2) .'</strong></td>';
                print '<td align="center"></td>';
            print '</tr>';

print '</tbody></table></form>';

llxFooter();