<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/cv/class/Cv.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/cv/class/CvEducation.class.php');
dol_include_once('/sghr/cv/class/CvLanguage.class.php');
dol_include_once('/sghr/cv/class/CvLicense.class.php');
dol_include_once('/sghr/cv/class/CvSkill.class.php');

require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';

$langs->load('cv@sghr');

$modname = $langs->trans("ecv_recherche_avancee");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$cvProfile  = new Cv($db);
$ecv2  = new Cv($db);
$CvEducation   = new CvEducation($db);
$CvSkill   = new CvSkill($db);
$CvLanguage   = new CvLanguage($db);
$CvLicense   = new CvLicense($db);

$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= (int) $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->cv->read) {
	accessforbidden();
}


$srch_date_emb 		= GETPOST('srch_date_emb');
$srch_poste_occup 		= GETPOST('srch_poste_occup');
$srch_societe_exper 		= GETPOST('srch_societe_exper');
$srch_projet_exper 		= GETPOST('srch_projet_exper');
$srch_etab_forma 		= GETPOST('srch_etab_forma');
$srch_niveau 		= GETPOST('srch_niveau');
$srch_etab_certif 		= GETPOST('srch_etab_certif');
$srch_initu_certif 		= GETPOST('srch_initu_certif');
$srch_qualif 		= GETPOST('srch_qualif');
$srch_competance 		= GETPOST('srch_competance');

$srch_langue = "";
if(GETPOST('srch_langue')){
	foreach (GETPOST('srch_langue') as $selectedOption)
	    $srch_langue .= ",'".$selectedOption."'";
	$srch_langue = trim($srch_langue,",");
}
$srch_type_permis = "";
if(GETPOST('srch_type_permis')){
	foreach (GETPOST('srch_type_permis') as $selectedOption)
	    $srch_type_permis .= ",'".$selectedOption."'";
	$srch_type_permis = trim($srch_type_permis,",");
}

// echo $srch_langue;
$srch_exist_permis = GETPOST('srch_exist_permis');
$srch_year_permis = GETPOST('srch_year_permis');


$filter = "";
// echo $srch_date_emb;
$date = explode('/', $srch_date_emb);
$date = $date[2]."-".$date[1]."-".$date[0];
$filter .= (!empty($srch_date_emb)) ? " AND CAST(date as date) >= '".$date."'\n" : "";


$filter .= (!empty($srch_poste_occup)) ? " AND poste like '%".$srch_poste_occup."%'\n" : "";

$filter .= (!empty($srch_societe_exper)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvExperience` where societe like '%".$srch_societe_exper."%')\n" : "";
$filter .= (!empty($srch_projet_exper)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvExperience` where projets like '%".$srch_projet_exper."%')\n" : "";

$filter .= (!empty($srch_etab_forma)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvEducation` where etablissement like '%".$srch_etab_forma."%')\n" : "";
$filter .= (!empty($srch_niveau)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvEducation` where niveau = '".$srch_niveau."')\n" : "";

$filter .= (!empty($srch_etab_certif)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvCertificate` where etablissement like '%".$srch_etab_certif."%')\n" : "";
$filter .= (!empty($srch_initu_certif)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvCertificate` where intitule like '%".$srch_initu_certif."%')\n" : "";


$filter .= (!empty($srch_qualif)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvQualification` where name like '%".$srch_qualif."%')\n" : "";

$filter .= (!empty($srch_competance)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvSkill` where fk_competance = '".$srch_competance."')\n" : "";

// $filter .= (!empty($srch_langue)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvLanguage` where name IN (".$srch_langue."))\n" : "";
if(!empty($srch_langue)){
	$ecvids = $CvLanguage->getEcvWithSelectdLangues($srch_langue);
	$ecvids = implode(",",$ecvids);
	$filter .= " AND rowid in (".$ecvids.")\n";
}
if(!empty($srch_type_permis)){
	$ecvids = $CvLicense->getEcvWithSelectdPermis($srch_type_permis);
	$ecvids = implode(",",$ecvids);
	$filter .= " AND rowid in (".$ecvids.")\n";
}



// $filter .= (!empty($srch_exist_permis)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvLicense` where exist = '".$srch_exist_permis."')\n" : "";
$filter .= (!empty($srch_year_permis)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvLicense` where year = ".$srch_year_permis.")\n" : "";
// $filter .= (!empty($srch_type_permis)) ? " AND rowid in (select fk_ecv from `".MAIN_DB_PREFIX."CvLicense` where type like '%".$srch_type_permis."%')\n" : "";

// echo "<br>".$filter;


$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	header('Location: ./search.php?mainmenu=ecv');
}

if(empty($filter))
	$filter = " AND 1<0 ";

// echo $filter;

$nbrtotal = $cvProfile->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbrtotalnofiltr = $ecv2->fetchAll("", "", "", "", $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


print_fiche_titre($modname);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">'."\n";
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num, $nbrtotal);

print '<input type="hidden" value="'.$sortfield.'" id="sortfield_">';
print '<input type="hidden" value="'.$sortorder.'" id="sortorder_">';
print '<input type="hidden" value="'.$limit.'" id="limit_">';
print '<input type="hidden" value="'.$offset.'" id="offset_">';
print '<input type="hidden" value="'.$filter.'" id="filter_">';



print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("e_cv").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("e_cv").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print '<b class="tofiltre">'.$langs->trans("ecv_date")." ".$langs->trans("ecv_supérieur_a").'</b>';
print '</td>';
print '<td class="fouth_td filter_debut_fin" style="width: 30%;">';
print '<input type="text" class="datepickerecvmod" id="date" style="" name="srch_date_emb" value="'.$srch_date_emb.'" autocomplete="off"/>';
print '</td>';

print '<td class="fouth_td" style="width: 170px;">';
	print '<b class="tofiltre">'.$langs->trans("ecv_poste").'</b>';
print '</td>';
print '<td class="fouth_td">';
print '<input type="text" style="width:98%; padding:8px 0px 8px 8px;" class="" name="srch_poste_occup" value="'.$srch_poste_occup.'" autocomplete="off"/>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';



print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_experiences").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_experiences").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_societe_experience").'</b>';
print '</td>';

print '<td class="fouth_td filter_debut_fin" style="width: 30%;">';
print '<input type="text" style="width:96%; padding:8px 0px 8px 8px;" class="" name="srch_societe_exper" value="'.$srch_societe_exper.'" autocomplete="off"/>';
print '</td>';

print '<td class="fouth_td" style="width: 170px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_projets").'</b>';
print '</td>';
print '<td class="fouth_td" style="">';
print '<input type="text" style="width:96%; padding:8px 0px 8px 8px;" class="" name="srch_projet_exper" value="'.$srch_projet_exper.'" autocomplete="off"/>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';



print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_formations").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_formations").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_etablissement_formation").'</b>';
print '</td>';

print '<td class="fouth_td filter_debut_fin" style="width: 30%;">';
print '<input type="text" style="width:96%; padding:8px 0px 8px 8px;" class="" name="srch_etab_forma" value="'.$srch_etab_forma.'" autocomplete="off"/>';
print '</td>';

print '<td class="fouth_td" style="width: 170px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_niveau").'</b>';
print '</td>';
print '<td class="fouth_td" style="">';
print $CvEducation->select_niveau($srch_niveau,"srch_niveau");
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';



print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_certificats").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_certificats").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_etablissement_certificat").'</b>';
print '</td>';

print '<td class="fouth_td filter_debut_fin" style="width: 30%;">';
print '<input type="text" style="width:96%; padding:8px 0px 8px 8px;" class="" name="srch_etab_certif" value="'.$srch_etab_certif.'" autocomplete="off"/>';
print '</td>';

print '<td class="fouth_td" style="width: 170px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_intitule_certificat").'</b>';
print '</td>';
print '<td class="fouth_td" style="">';
print '<input type="text" style="width:96%; padding:8px 0px 8px 8px;" class="" name="srch_initu_certif" value="'.$srch_initu_certif.'" autocomplete="off"/>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';



print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_qualifications").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_qualifications").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_name_qualification").'</b>';
print '</td>';

print '<td class="fouth_td filter_debut_fin" style="" colspan="3">';
print '<input type="text" style="width:98%; padding:8px 0px 8px 8px;" class="" name="srch_qualif" value="'.$srch_qualif.'" autocomplete="off"/>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';


print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_competences").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_competences").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_titre_competence").'</b>';
print '</td>';

print '<td class="fouth_td srch_competance filter_debut_fin" style="" colspan="3">';
print '<select name="srch_competance" id="srch_competance">';
print $CvSkill->select_competances($srch_competance,'srch_competance');
print '<select>';
print '</td>';

print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';



print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_langues").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_langues").'</td>';
print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_titre_langue").'</b>';
print '</td>';

$srchlang = str_replace("'", "", $srch_langue);
$srchlang = explode(',', $srchlang);
// echo $srchlang;
$srchlangsarr = [];
foreach($srchlang as $k => $v)
	$srchlangsarr[$v] = $v;
// print_r($srchlangsarr); 
print '<td class="fouth_td filter_debut_fin selectlangu" style="" colspan="3">';
print $CvLanguage->select_language($srchlangsarr,'srch_langue',0,null,1,"multiple");
print '</td>';

print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';

$chekdyes = $chekdno = "";
if($srch_exist_permis == "yes"){
	$chekdyes = "checked";
}
elseif($srch_exist_permis == "no"){
	$chekdno = "checked";
}

print '<div class="containeroffilter">';
// print '<div class="titleoffilter">'.$langs->trans("ecv_permis_circulations").'</div>';
print '<div class="ecv_srch_filtrage_div">';
print '<table class="border" style="width: 100%;">';
print '<tr>';
print '<td class="titleoffilter">'.$langs->trans("ecv_permis_circulations").'</td>';
// print '<td class="fouth_td" style="width:200px;">';
// print '<label style="padding-right:14px;"><input style="min-width: initial;" type="radio" name="srch_exist_permis" id="no" value="no" '.$chekdno.'>'.$langs->trans('No').'</label>';
// print '<label><input style="min-width: initial;" type="radio" name="srch_exist_permis" id="yes" value="yes" '.$chekdyes.'>'.$langs->trans('Yes').'</label>';
// print '</td>';


print '<td class="fouth_td" style="width:200px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_permis_type").'</b>';
print '</td>';

print '<td class="fouth_td selectperms" style="width: 30%;">';
$srchperm = str_replace("'", "", $srch_type_permis);
$srchperm = explode(',', $srchperm);
// echo $srchperm;
$srchpermsarr = [];
foreach($srchperm as $k => $v)
	$srchpermsarr[$v] = $v;
print $CvLicense->select_permis($srchpermsarr,'srch_type_permis',0,null,1,"multiple");
// print '<input type="text" style="" name="srch_type_permis" value="'.$srch_type_permis.'" autocomplete="off"/>';

print '</td>';

print '<td class="fouth_td" style="width: 170px;">';
	print  '<b class="tofiltre">'.$langs->trans("ecv_annee_acquisition").'</b>';
print '</td>';
print '<td class="fouth_td" style="">';
print '<input type="number" style="min-width: 53px;width: 70px;" name="srch_year_permis" value="'.$srch_year_permis.'" autocomplete="off"/>';
// print '<input type="text" style="width:96%; padding:8px 0px 8px 8px;" class="" name="srch_initu_certif" value="'.$srch_initu_certif.'" autocomplete="off"/>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '</div>';






print '<div style="text-align:center;margin: 27px 0 6px;">';
print '<input type="submit" value="'.$langs->trans('Search').'" name="bouton" class="butAction" />';
print '<input type="submit" value="'.$langs->trans('Reset').'" name="button_removefilter" class="butAction" />';
print '</div>';


print '</form>';


$options = "&srch_date_emb=".$srch_date_emb."&srch_poste_occup=".$srch_poste_occup."&srch_societe_exper=".$srch_societe_exper."&srch_projet_exper=".$srch_projet_exper."&srch_etab_forma=".$srch_etab_forma."&srch_niveau=".$srch_niveau."&srch_etab_certif=".$srch_etab_certif."&srch_initu_certif=".$srch_initu_certif."&srch_qualif=".$srch_qualif."&srch_competance=".$srch_competance."&srch_langue=".$srch_langue."&srch_type_permis=".$srch_type_permis."&srch_exist_permis=".$srch_exist_permis."&srch_year_permis=".$srch_year_permis;

$modname = $langs->trans("ecv_result_of_search");
print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], $options, $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
print '<input name="id_cv" type="hidden" value="'.$id_ecv.'">';

// print '<div style="float: right; margin: 8px;">';
// print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
// print '</div>';

print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';


field($langs->trans("ecv_ref"),'ref');
field($langs->trans("ecv_fk_user"),'fk_user');
field($langs->trans("ecv_date"),'datehire');
field($langs->trans("ecv_modele"),'module');
field($langs->trans("Export_pdf"),'');
// print '<th align="center">'.$langs->trans("Export_pdf").'</th>';

print '</tr>';



print '</thead><tbody>';
	$colspn = 5;
    //$name_cv=['cv_1'=>'Modéle 1','cv_2'=>'Modéle 2','cv_3'=>'Modéle 3'];
    $name_cv=['cv_1'=>$langs->trans('Modéle').' 1','cv_2'=>$langs->trans('Modéle').' 2','cv_3'=>$langs->trans('Modéle').' 3'];
	if (count($cvProfile->rows) > 0) {
	for ($i=0; $i < count($cvProfile->rows) ; $i++) {


		$var = !$var;
		$item = $cvProfile->rows[$i];

    	$f=explode(' ', $item->datehire);
		$date_f = explode('-', $f[0]);
    	$date = $date_f[2]."/".$date_f[1]."/".$date_f[0];
    	$user_cv=new User($db);
    	// $user_cv->fetch($item->fk_user);

    	$adherent_cv = new Adherent($db);
    	if($item->useroradherent == 'ADHERENT'){
	        $adherent_cv->fetch($item->fk_user);
	        $nameusr = $adherent_cv->getNomUrl(1);
	    }else{
	        $user_cv->fetch($item->fk_user);
	        $nameusr = $user_cv->getNomUrl(1);
	    }

		print '<tr '.$bc[$var].' >';
    		print '<td align="center" style="">'; 
    		print '<a href="'.dol_buildpath('/sghr/cv/card.php?id='.$item->rowid,2).'" >';
    		print $item->ref;
    		print '</a>';
    		print '</td>';
    		// $user->fetch($item->fk_user);
    		print '<td align="center" style="">'.$nameusr.'</td>';
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
	global $langs,$filter;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		// print '<a href="?sortfield='.$champ.'&amp;sortorder=desc&filter=".'.$filter.'>';
		// print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/cv/img/1uparrow.png',2).'" alt="" title="Z-A" class="imgup" border="0"></span>';
		// print '</a>';
		// print '<a href="?sortfield='.$champ.'&amp;sortorder=asc&filter=".'.$filter.'>';
		// print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/cv/img/1downarrow.png',2).'" alt="" title="A-Z" class="imgup" border="0"></span>';
		// print '</a>';
	print '</th>';
}

?>
<script>
	$( function() {
	$("#datepicker1").datepicker({ dateFormat: 'yy' });
	$( ".datepickerecvmod" ).datepicker({
    	dateFormat: 'dd/mm/yy'
	});
	$('#srch_fk_user').select2();
	$('#srch_competance').select2();
	} );
</script>


<style type="text/css">
	.select2{
		width: 71% !important;
	}
</style>
<?php

llxFooter();