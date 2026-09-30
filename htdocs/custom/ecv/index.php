<?php
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';


dol_include_once('/ecv/class/ecv.class.php');
dol_include_once('/core/class/html.form.class.php');
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';

$langs->load('ecv@ecv');

$modname = $langs->trans("ecv");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$ecv  = new ecv($db);
$ecv2  = new ecv($db);

$projet        = new Project($db);
$tache          = new Task($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

$ecv->upgradeModuleEcv();

$objdocs  = new ecv($db);
global $dolibarr_main_data_root;
if (!dolibarr_get_const($db,'ECV_CHANGEPATHDOCS',0)){
	$source = dol_buildpath('/uploads/ecv');
	if(@is_dir($source)){
		$docdir = $dolibarr_main_data_root.'/ecv';
		$dmkdir = dol_mkdir($docdir, '', 0755);
		if($dmkdir >= 0){
			@chmod($docdir, 0775);
			$dcopy = dolCopyDir($source, $docdir, 0775, 1);
			// if($dcopy >= 0){
				dolibarr_set_const($db,'ECV_CHANGEPATHDOCS',1,'chaine',0,'',0);
				$objdocs->ecvpermissionto($docdir);
			// }
		}
	}
}


if (!$user->rights->ecv->lire) {
	accessforbidden();
}


$srch_rowid 		= GETPOST('srch_rowid');
$srch_fk_user 		= GETPOST('srch_fk_user');
$srch_date 		= GETPOST('srch_date');
$srch_module 		= GETPOST('srch_module');

$date = explode('/', $srch_date);
$date = $date[2]."-".$date[1]."-".$date[0];

$filter .= (!empty($srch_rowid)) ? " AND ref like '%".$srch_rowid."%'" : "";

$filter .= (!empty($srch_fk_user)) ? " AND fk_user like '%".$srch_fk_user."%'" : "";


$filter .= (!empty($srch_date)) ? " AND CAST(datehire as date) = '".$date."' " : "";

$filter .= (!empty($srch_module)) ? " AND module like '%".$srch_module."%'" : "";




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
	$srch_fk_user = "";
	$srch_module = "";
	$srch_date = "";
}


// echo $filter;

$nbrtotal = $ecv->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbrtotalnofiltr = $ecv2->fetchAll("", "", "", "", $filter);
$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
print '<input name="id_cv" type="hidden" value="'.$id_ecv.'">';

print '<div style="float: right; margin: 8px;">';
print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
print '</div>';

print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';

	$files_fk_user = (isset($MenuMembers) && $MenuMembers >0) ? $langs->trans('ecv_employe').' | '.$langs->trans('ecv_Member') : $langs->trans("ecv_employe");
	print_liste_field_titre($langs->trans("ecv_ref"),$_SERVER["PHP_SELF"], "rowid", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($files_fk_user,$_SERVER["PHP_SELF"], "fk_user", '', '', 'align="left"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("ecv_date"),$_SERVER["PHP_SELF"], "date", '', '', 'align="center"', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("ecv_modele"),$_SERVER["PHP_SELF"], "module", '', '', 'align="center"', $sortfield, $sortorder);

print '<th align="center">'.$langs->trans("Action").'</th>';


print '</tr>';

print '<tr class="liste_titre nc_filtrage_tr">';

print '<td align="center"><input style="max-width: 129px;" class="" type="text" class="" id="srch_rowid" name="srch_rowid" value="'.$srch_rowid.'"/></td>';

print '<td align="left">'.$ecv->select_user($srch_fk_user,'srch_fk_user',1,"rowid","login").'</td>';

print '<td align="center"><input style="max-width: 129px;" class="datepickerecvmod" type="text" class="" id="srch_date" name="srch_date" value="'.$srch_date.'"/></td>';

print '<td align="center"><input style="max-width: 129px;" class="" type="number" min="1" max="3" step="1" class="" id="srch_module" name="srch_module" value="'.$srch_module.'"/></td>';

print '<td align="center">';
	print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '&nbsp;<input type="image" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"></td>';
print '</tr>';


print '</thead><tbody>';
	$colspn = 5;
    //$name_cv=['cv_1'=>'Modéle 1','cv_2'=>'Modéle 2','cv_3'=>'Modéle 3'];
    $name_cv=['cv_1'=>$langs->trans('Modéle').' 1','cv_2'=>$langs->trans('Modéle').' 2','cv_3'=>$langs->trans('Modéle').' 3'];
	if (count($ecv->rows) > 0) {
	for ($i=0; $i < count($ecv->rows) ; $i++) {


		$var = !$var;
		$item = $ecv->rows[$i];

    	$f=explode(' ', $item->datehire);
		$date_f = explode('-', $f[0]);
    	$date = $date_f[2]."/".$date_f[1]."/".$date_f[0];
    	$user_cv=new User($db);
    	// $user_cv->fetch($item->fk_user);

    	$nameusr = '';

    	if($item->useroradherent == 'ADHERENT'){
    		$adherent_cv = new Adherent($db);
	        $resfetch = $adherent_cv->fetch($item->fk_user);
	        if($resfetch) $nameusr = $adherent_cv->getNomUrl(1);
	    }else{
	        $resfetch = $user_cv->fetch($item->fk_user);
	        if($resfetch) $nameusr = $user_cv->getNomUrl(1);
	    }

		print '<tr '.$bc[$var].' >';
    		print '<td align="center" style="">'; 
    		print '<a href="'.dol_buildpath('/ecv/card.php?id='.$item->rowid,2).'" >';
    		print $item->ref;
    		print '</a>';
    		print '</td>';
    		// $user->fetch($item->fk_user);
    		print '<td align="left" style="">'.$nameusr.'</td>';
    		print '<td align="center" style="">'.$date.'</td>';
    		print '<td align="center" style="">'.$name_cv[$item->module].'</td>';
			print '<td align="center"><a  href="./card.php?id='.$item->rowid.'&action_export=pdf&id_cv='.$item->module.'" target="_blank" >'.img_mime('test.pdf').'</a></td>';
		print '</tr>';
	}
	}else{
		print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
	}

print '</tbody></table></form>';


function field($titre,$champ){
	global $langs;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=desc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/ecv/img/1uparrow.png',2).'" alt="" title="Z-A" class="imgup" border="0"></span>';
		print '</a>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=asc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/ecv/img/1downarrow.png',2).'" alt="" title="A-Z" class="imgup" border="0"></span>';
		print '</a>';
	print '</th>';
}

?>
<script>
	$( function() {
	$( ".datepickerecvmod" ).datepicker({
    	dateFormat: 'dd/mm/yy'
	});
	$('#srch_fk_user').select2();
	$('#select_onechambre>select').select2();
	} );
</script>

<?php

llxFooter();