<?php
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 


dol_include_once('/recrutement/class/etapescandidature.class.php');
dol_include_once('/recrutement/class/candidatures.class.php');
dol_include_once('/recrutement/class/departement.class.php');
dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/core/class/html.form.class.php');
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';


$langs->load('recrutement@recrutement');

$modname = $langs->trans("recru_recherche_avancee_condid");

$etapes         = new etapescandidature($db);
$candidature   = new candidatures($db);
$candidatures   = new candidatures($db);
$candidatures2  = new candidatures($db);
$employe        = new User($db);
$etiquette      = new etiquettes($db);
$postes         = new postes($db);

$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->recrutement->lire) {
	accessforbidden();
}


$filter = "";

$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	header('Location: ./search.php?mainmenu=recrutement');
}



// Filtrage par postes
$srchpost_label 		= GETPOST('srchpost_label');
$srchpost_departement 	= GETPOST('srchpost_departement');
$srchpost_lieu 			= GETPOST('srchpost_lieu');
$srchpost_email 		= GETPOST('srchpost_email');
$srchpost_date 			= GETPOST('srchpost_date');
$srchpost_status 		= GETPOST('srchpost_status');
$srchpost_nb_nouveauemploye = GETPOST('srchpost_nb_nouveauemploye');
$srchpost_responsable_RH 	= GETPOST('srchpost_responsable_RH');
$srchpost_responsable_recrutement = GETPOST('srchpost_responsable_recrutement');
if(!empty($srchpost_date)){
	$date=explode('/', $srchpost_date);
    $srchpost_date=$date[2].'-'.$date[1].'-'.$date[0];
}


$filter .= ($srchpost_label > 0) ? " AND poste = ".$srchpost_label."\n" : "";
$filter .= ($srchpost_departement > 0) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where departement = ".$srchpost_departement.")\n" : "";
$filter .= ($srchpost_lieu > 0) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where lieu = ".$srchpost_lieu.")\n" : "";
$filter .= (!empty($srchpost_email)) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where email like '%".$srchpost_email."%')\n" : "";
$filter .= ($srchpost_responsable_recrutement > 0) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where responsable_recrutement = ".$srchpost_responsable_recrutement.")\n" : "";
$filter .= (!empty($srchpost_nb_nouveauemploye)) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where nb_nouveauemploye = ".$srchpost_nb_nouveauemploye.")\n" : "";
$filter .= ($srchpost_responsable_RH > 0) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where responsable_RH = ".$srchpost_responsable_RH.")\n" : "";
$filter .= (!empty($srchpost_date)) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where CAST(date as date) >= ".$srchpost_date.")\n" : "";
$filter .= (!empty($srchpost_status)) ? " AND poste in (select rowid from `".MAIN_DB_PREFIX."postes` where status = '".$srchpost_status."')\n" : "";





// Filtrage par candidatures
$srchcandi_sujet	= 	GETPOST('srchcandi_sujet');
$srchcandi_etape	= 	GETPOST('srchcandi_etape');
$srchcandi_nom		= 	GETPOST('srchcandi_nom');
$srchcandi_niveau	= 	GETPOST('srchcandi_niveau');
$srchcandi_contact	= 	GETPOST('srchcandi_contact');
$srchcandi_email	= 	GETPOST('srchcandi_email');
$srchcandi_tel		= 	GETPOST('srchcandi_tel');
$srchcandi_mobile	= 	GETPOST('srchcandi_mobile');
$srchcandi_origine	= 	GETPOST('srchcandi_origine');
$srchcandi_poste	= 	GETPOST('srchcandi_poste');
$srchcandi_resume	= 	GETPOST('srchcandi_resume');
$srchcandi_date_depot	= 	GETPOST('srchcandi_date_depot');
$srchcandi_responsable	= 	GETPOST('srchcandi_responsable');
$srchcandi_departement	= 	GETPOST('srchcandi_departement');
$srchcandi_appreciation	= 	GETPOST('srchcandi_appreciation');
$srchcandi_apport_par	= 	GETPOST('srchcandi_apport_par');
$srchcandi_salaire_demande	= 	GETPOST('srchcandi_salaire_demande');
$srchcandi_salaire_propose	= 	GETPOST('srchcandi_salaire_propose');
$srchcandi_date_disponible	= 	GETPOST('srchcandi_date_disponible');
$etiq						= 	GETPOST('srchcandi_etiquettes');

$srchcandi_etiquettes = "";
if(!empty($etiq))
    $srchcandi_etiquettes = implode(",", $etiq);

if(!empty($srchcandi_date_depot)){
	$date_depot=explode('/', $srchcandi_date_depot);
    $srchcandi_date_depot=$date_depot[2].'-'.$date_depot[1].'-'.$date_depot[0];
}

if(!empty($srchcandi_date_disponible)){
	$date_disponible=explode('/', $srchcandi_date_disponible);
    $srchcandi_date_disponible=$date_disponible[2].'-'.$date_disponible[1].'-'.$date_disponible[0];
}

if(!empty($srchcandi_etape)){
	$a = 0;
	$filter .= " AND (";
	foreach ($srchcandi_etape as $key => $value) {
		if($a > 0)
			$filter .= " OR";
		$filter .= " etape = ".$value."";
		$a++;
	}
	$filter .= " )\n";
}
// print_r($srchcandi_etape);
$filter .= (!empty($srchcandi_sujet)) ? " AND sujet like '%".$srchcandi_sujet."%'\n" : "";
$filter .= (!empty($srchcandi_nom)) ? " AND nom like '%".$srchcandi_nom."%'\n" : "";
$filter .= (!empty($srchcandi_niveau)) ? " AND niveau like '%".$srchcandi_niveau."%'\n" : "";
$filter .= (!empty($srchcandi_contact)) ? " AND contact = ".$srchcandi_contact."\n" : "";
$filter .= (!empty($srchcandi_date_depot)) ? " AND CAST(date_depot as date) =  '".$srchcandi_date_depot."'\n" : "";
$filter .= (!empty($srchcandi_responsable)) ? " AND responsable = ".$srchcandi_responsable."\n" : "";
$filter .= (!empty($srchcandi_departement)) ? " AND departement = ".$srchcandi_departement."\n" : "";
$filter .= (!empty($srchcandi_email)) ? " AND email like '%".$srchcandi_email."%'\n" : "";
$filter .= (!empty($srchcandi_tel)) ? " AND tel like '%".$srchcandi_tel."%'\n" : "";
$filter .= (!empty($srchcandi_mobile)) ? " AND mobile like '%".$srchcandi_mobile."%'\n" : "";
$filter .= (!empty($srchcandi_appreciation)) ? " AND appreciation = ".$srchcandi_appreciation."\n" : "";
$filter .= (!empty($srchcandi_origine)) ? " AND origine = ".$srchcandi_origine."\n" : "";
$filter .= (!empty($srchcandi_apport_par)) ? " AND apport_par like '%".$srchcandi_apport_par."%'\n" : "";
$filter .= (!empty($srchcandi_poste)) ? " AND poste = ".$srchcandi_poste."\n" : "";
$filter .= (!empty($srchcandi_salaire_demande)) ? " AND salaire_demande = ".$srchcandi_salaire_demande."\n" : "";
$filter .= (!empty($srchcandi_salaire_propose)) ? " AND salaire_propose = ".$srchcandi_salaire_propose."\n" : "";
$filter .= (!empty($srchcandi_resume)) ? " AND resume like '%".$srchcandi_resume."%'\n" : "";
$filter .= (!empty($srchcandi_date_disponible)) ? " AND CAST(date_disponible as date) =  '".$srchcandi_date_disponible."'\n" : "";
$filter .= (!empty($srchcandi_etiquettes)) ? " AND etiquettes IN (".$srchcandi_etiquettes.")" : "";


// echo $filter;


$noneorblock = "hidecollapse";
if(empty($filter)){
	$noneorblock = "";
	$filter = " AND 1<0 ";
}

$nbrtotal = $candidatures->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbrtotalnofiltr = $candidatures2->fetchAll("", "", "", "", $filter);
// $nbrtotalnofiltr = $nbrtotal;

// print_r($candidatures->rows);
$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


print_fiche_titre($modname);
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">'."\n";
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num, $nbrtotal);

print '<input type="hidden" name="sortfield" value="'.$sortfield.'" id="sortfield_">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'" id="sortorder_">';
print '<input type="hidden" name="limit" value="'.$limit.'" id="limit_">';
print '<input type="hidden" name="offset" value="'.$offset.'" id="offset_">';
print '<input type="hidden" name="filter" value="'.$filter.'" id="filter_">';



print '<div class="search_filter_avancer modulerecrutement">';


print '<span class="choosefilterwith">';
    print '<a class="greenbutton" id="srch_with_postes" >';
    print '<img src="'.dol_buildpath('/recrutement/img/collapse.png',2).'" >';
    print '<span class="text">'.$langs->trans('recru_postes').'</span>';
    print '</a>';
print '</span>';

print '<div class="srch_with_postes '.$noneorblock.'">';

print '<div class="div-table-responsive">';
print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	    // print '<div class="titesformsrecru" style=""><b>'.$langs->trans('recrutement').'</b></div>';
	    print '<table class="border nc_table_" width="100%">';
	        // print '<thead>';
	        //     print '<tr class="liste_titre">';
	        //     print '<th colspan="2">'.$langs->trans('recrutement').'</th>';
	        //     print '</tr>';
	        // print '</thead>';
	        print '<tbody>';
	        print '<tr>';
	            print '<td class=" firsttd200px" >'.$langs->trans('PosteOccupe').'</td>';
	            print '<td>'.$postes->select_postes($srchpost_label,'srchpost_label').'</td>';
	            // print '<td ><input type="text" class="" id="label" value="'.$srchpost_label.'"  style="padding:8px 0px 8px 8px; width:97%" name="srchpost_label" autocomplete="off"/>';
	            print '</td>';
	        print '</tr>';

	        print '<tr>';
	            print '<td >'.$langs->trans('departement').'</td>';
	            print '<td>'.$postes->select_departement($srchpost_departement,'srchpost_departement').'</td>';
	        print '</tr>';

	        print '<tr>';
	            print '<td>'.$langs->trans('lieu').'</td>';
	            print '<td>'.$form->select_company($srchpost_lieu,'srchpost_lieu','','SelectThirdParty').'</td>';
	        print '</tr>';

	        print '<tr>';
	            print '<td >'.$langs->trans('email').'</td>';
	            print '<td ><input type="text" class="" value="'.$srchpost_email.'" style="width:97%; padding:8px 0px 8px 8px;" name="srchpost_email" autocomplete="off"/>';
	            print '</td>';
	        print '</tr>';

	        print '<tr>';
	            print '<td >'.$langs->trans('responsable_recrutement').'</td>';
	            print '<td >'.$postes->select_user($srchpost_responsable_recrutement,'srchpost_responsable_recrutement',1,"rowid","login").'</td>';
	        print '</tr>';

	        print '<tr>';
	            print '<td >'.$langs->trans('nb_nouveauemploye').'</td>';
	            print '<td ><input type="number" id="nb_nouveauemploye" name="srchpost_nb_nouveauemploye" value="'.$srchpost_nb_nouveauemploye.'" min="0"  autocomplete="off"/>';
	            print '</td>';
	        print '</tr>';

	        // print '<tr>';
	        //     print '<td >'.$langs->trans('description_p').'</td>';
	        //     print '<td >';
	        //         print '<textarea name="description" style="width:97%;">'.$srchpost_description.'</textarea>';
	        //     print '</td>';
	        // print '</tr>';

	        print '</tbody>';
	    print '</table>';
	print '</div>';
	print '<div class="fichehalfright">';
	print '<div class="ficheaddleft">';
	    // print '<div class="titesformsrecru" style=""><b>'.$langs->trans('offre').'</b></div>';
	    print '<table  class="border nc_table_" width="100%" >';
	        // print '<thead>';
	        //     print '<tr class="liste_titre">';
	        //     print '<th colspan="2">'.$langs->trans('offre').'</th>';
	        //     print '</tr>';
	        // print '</thead>';
	        print '<tbody>';
	            print '<tr>';
	                print '<td class=" firsttd200px" >'.$langs->trans('responsable_RH').'</td>';
	                print '<td >'.$postes->select_user($srchpost_responsable_RH,'srchpost_responsable_RH',1,"rowid","login").'</td>';
	            print '</tr>';
	            $date="";
	            if($srchpost_date){
	                $date=explode('-', $srchpost_date);
	                $date=$date[2].'/'.$date[1].'/'.$date[0];
	            }
	            print '<tr>';
	                print '<td >'.$langs->trans('date_pr_empbouche').'</td>';
	            	print '<td ><input type="text" name="srchpost_date" value="'.$date.'" class="datepickerncon" id="date" ></td>';
	            print '</tr>';
	            print '<tr>';
	                print '<td >'.$langs->trans('Status').'</td>';
	            	$select = '<select id="srchpost_status" name="srchpost_status">';
					$select .= '<option value=""></option>';
					$select .= '<option value="Recrutementencours">'.$langs->trans('recrutement_cours').'</option>';
					$select .= '<option value="Recrutementfinalise">'.$langs->trans('recrutement_finalise').'</option>';
					$select .= '<option value="Recrutementarrete">'.$langs->trans('recrutement_arrete').'</option>';
					$select .= '</select>';
					$select=str_replace('value="'.$srchpost_status.'"', 'value="'.$srchpost_status.'" selected', $select);
					print '<td >';
					print $select;
					print '</td >';
	            print '</tr>';
	        print '</tbody>';
	    print '</table>';
	print '</div>';
	print '</div>';
print '</div>';
print '</div>';

print '<div style="clear:both;"></div>';
print '</div>'; // end srch_with_postes



















print '<span class="choosefilterwith">';
    print '<a class="greenbutton" id="srch_with_candidatures" >';
    print '<img src="'.dol_buildpath('/recrutement/img/collapse.png',2).'" >';
    print '<span class="text">'.$langs->trans('recru_candidatures').'</span>';
    print '</a>';
print '</span>';

print '<div class="srch_with_candidatures '.$noneorblock.'" style="display:block;">';



// Nom & etiquettes
print '<div class="div-table-responsive">';
print '<table class="border nc_table_" width="100%">';
        print '<tr>';
            print '<td colspan="2" class="alletapesrecru">';
                $etapes->fetchAll();
                $nb=count($etapes->rows);
                $etapecandid ='';
                for ($i=0; $i < $nb; $i++) { 
                    $etap=$etapes->rows[$i];

                    $etapecandid .= '<label class="etapes" >';
                		$chkd = "";
                		if($srchcandi_etape){
	                    	if(in_array($etap->rowid, $srchcandi_etape))
	                    		$chkd = "checked";
                		}
                        $etapecandid .= '<input type="checkbox" id="'.$etap->rowid.'" '.$chkd.' style="display:none;" value="'.$etap->rowid.'" name="srchcandi_etape[]" class="etapes">';
                        $etapecandid .= ' <span class="radio"></span>';
                        $etapecandid .= '<span style="font-size:14px"> '.$langs->trans($etap->label).'</span>';
                    $etapecandid .= '</label>';


                }
                // print_r($item->etapes);die();
                // $etapecandid = str_replace('<input type="checkbox" id="'.$srchcandi_etape.'"', '<input type="radio" id="'.$srchcandi_etape.'" checked ', $etapecandid);
                print $etapecandid;
            print '</td>';
        print '</tr>';
print '</table>';
print '</div>';
print '<div class="div-table-responsive">';
print '<table class="border nc_table_" width="100%">';
        print '<tr>';
            print '<td colspan="" class="" style="width: 125px;">';
            print $langs->trans('sujet')."<br>";
            print '</td >';
            print '<td colspan="3" class="">';
            print '<input type="text" class="" id="sujet" value="'.$srchcandi_sujet.'" style="padding:8px 0px 8px 8px; width:calc(100% - 15px)" name="srchcandi_sujet"  autocomplete="off"/>';
            print '</td >';
        print '</tr>';
        print '<tr>';
            print '<td colspan="" class="" style="width: 125px;">';
            print $langs->trans('nom_candidat')."<br>";
            print '</td >';
            print '<td colspan="" class="">';
            print '<input type="text" class="" id="nom_candidat" value="'.$srchcandi_nom.'" style="padding:8px 0px 8px 8px; width:80%" name="srchcandi_nom"  autocomplete="off"/>';
            print '</td>';
            print '<td colspan="" class="" style="width: 125px;">';
            print $langs->trans('etiquettes');
            print '</td >';
            print '<td >';
            print '<span class="etiquettesrecru">';
            print $candidature->select_etiquette($srchcandi_etiquettes,'srchcandi_etiquettes[]');
            print '</span>';
            print '</td>';
        print '</tr>';
print '</table>';
print '</div>';

print '<div class="clear" style="margin-top: 4px;"></div>';

// info
print '<div class="fichecenter" >';
    print '<div class="fichehalfleft">';
        print '<table class="border nc_table_" width="100%">';
            print '<body>';
                print '<tr>';
                    print '<td class=" firsttd200px" style="text-align:left;">'.$langs->trans('contact').'</td>';
                    print '<td>';
                    print $candidature->select_contact($srchcandi_contact,'srchcandi_contact');
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('email_contact').'</td>';
                    print '<td ><input type="text" class="" id="email" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="srchcandi_email" value="'.$srchcandi_email.'" autocomplete="off"/></td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('tel').'</td>';
                    print '<td ><input type="text" class="" id="tel" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="srchcandi_tel" value="'.$srchcandi_tel.'" autocomplete="off"/></td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('mobile').'</td>';
                    print '<td ><input type="text" class="" id="mobile" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="srchcandi_mobile" value="'.$srchcandi_mobile.'" autocomplete="off"/></td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('niveau').'</td>';
                    print '<td >'.$candidature->select_niveau($srchcandi_niveau,"srchcandi_niveau").'</td>';
                print '</tr>';

            print '</tbody>';
        print '</table>';
        print '<br>';
    print '</div>';

    print '<div class="fichehalfright">';
    print '<div class="ficheaddleft">';
        print '<table class="border nc_table_" width="100%" >';
            print '<tbody>';
                print '<tr>';
                    print '<td style="text-align:left;" class=" firsttd200px">'.$langs->trans('responsable_candidature').'</td>';
                    print '<td style="">';
                    print $postes->select_user($srchcandi_departement,'srchcandi_responsable');
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td  style="text-align:left;">'.$langs->trans('appreciation').'</td>';
                    print '<td  style="">';
                        print '<div>';
                            print '<div class="rating">';
                                $rating  ='<input type="radio" id="star5" name="srchcandi_appreciation" value="5" data-status="'.$langs->trans('Excellent').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Excellent').'" for="star5"></label>';
                                $rating .='<input type="radio" id="star4" name="srchcandi_appreciation" value="4" data-status="'.$langs->trans('Très_bien').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Très_bien').'" for="star4"></label>';
                                $rating .='<input type="radio" id="star3" name="srchcandi_appreciation" value="3" data-status="'.$langs->trans('Bien').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Bien').'" for="star3"></label>';
                                $rating .='<input type="radio" id="star2" name="srchcandi_appreciation" value="2" data-status="'.$langs->trans('Satisfaisant').'" onchange="get_status_appreciation(this)" /><label title="'.$langs->trans('Satisfaisant').'" for="star2"></label>';
                                $rating .='<input type="radio" id="star1" name="srchcandi_appreciation" value="1" data-status="'.$langs->trans('Insuffisant').'" onchange="get_status_appreciation(this)" /><label title="'.$langs->trans('Insuffisant').'" for="star1"></label>';

                                $rating = str_replace('value="'.$srchcandi_appreciation.'"', 'value="'.$srchcandi_appreciation.'" checked', $rating);
                                print $rating;
                            print '</div>';
                            $status=[1=>$langs->trans('Insuffisant'),2=>$langs->trans('Satisfaisant'),3=>$langs->trans('Bien'),4=>$langs->trans('Très_bien'),5=>$langs->trans('Excellent')];
                            $clscolor = "";
                            if(!empty($srchcandi_appreciation)){
                                if($srchcandi_appreciation > 2)
                                    $clscolor = "greenbg";
                                else
                                    $clscolor = "redbg";
                            }
                            print '<div class="txt appreciationdetail '.$clscolor.'">';
                                print $status[$srchcandi_appreciation];
                            print '</div>';
                        print '</div>';
                    print '</td>';
                print '</tr>';

        
                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('origine').'</td>';
                    print '<td style="">';
                    print $candidature->select_origine($srchcandi_origine,'srchcandi_origine');
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('apport_par').'</td>';
                    print '<td style=""><input type="text" name="srchcandi_apport_par" value="'.$srchcandi_apport_par.'" style="width:100%;" ></td>';
                print '</tr>';
            print '</tbody>';
        print '</table>';
    print '</div>';
    print '</div>';
    print '<div class="clear"></div>';
print '</div>';

// post & contrat
print '<div class="fichecenter postandcontrat">';
    print '<div class="fichehalfleft">';
        print '<div class="topheaderrecrutmenus"><span>'.$langs->trans('poste').'</span></div>';
        print '<div class="divcontaintable">';
        print '<table class="border nc_table_" width="100%">';
            print '<body>';
                print '<tr>';
                    print '<td style="text-align:left;" class=" firsttd200px">'.$langs->trans('fonction').'</td>';
                    print '<td >';
                        print $postes->select_postes($srchcandi_poste,'srchcandi_poste');
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('departement').'</td>';
                    print '<td style="">';
                        print $postes->select_departement($srchcandi_departement,'srchcandi_departement');
                    print '</td>';
                print '</tr>';
                $date_depot = "";
                if($srchcandi_date_depot){
                    $date=explode('-', $srchcandi_date_depot);
                    $date_depot=$date[2].'/'.$date[1].'/'.$date[0];
                }
                print '<tr>';
                    print '<td >'.$langs->trans('date_depot').'</td>';
                    print '<td ><input type="text" name="srchcandi_date_depot" value="'.$date_depot.'" class="datepickerncon" ></td>';
                print '</tr>';


            print '</tbody>';
        print '</table>';
        print '</div>';
    print '</div>';

    print '<div class="fichehalfright">';
    print '<div class="ficheaddleft">';    
        print '<div class="bordercontainr">';    
        print '<div class="topheaderrecrutmenus"><span>'.$langs->trans('contrat').'</span></div>';
        print '<div class="divcontaintable">';
        print '<table class="border nc_table_" width="100%">';
            print '<body>';

                print '<tr>';
                    print '<td style="text-align:left;" class=" firsttd200px">'.$langs->trans('salaire_demande').'</td>';
                    print '<td ><input type="number" min="0" value="'.$srchcandi_salaire_demande.'" name="srchcandi_salaire_demande" > </td>';
                print '</tr>';

                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('salaire_propose').'</td>';
                    print '<td ><input type="number" min="0" value="'.$srchcandi_salaire_propose.'" name="srchcandi_salaire_propose" > </td>';
                print '</tr>';

                $date_disponible = "";
                if(!empty($srchcandi_date_disponible)){
	                $date=explode('-', $srchcandi_date_disponible);
	                $date_disponible=$date[2].'/'.$date[1].'/'.$date[0];
                }
                print '<tr>';
                    print '<td style="text-align:left;">'.$langs->trans('date_disponible').'</td>';
                    print '<td>';
                        print '<input type="text" class="datepickerncon" value="'.$date_disponible.'" name="srchcandi_date_disponible" autocomplete="off" >';
                    print '</td>';
                print '</tr>';


            print '</tbody>';
        print '</table>';
        print '</div>';
        print '</div>';
    print '</div>';
    print '</div>';

    print '<div class="clear"></div>';
print '</div>';

// Description
print '<table class="border nc_table_" width="100%">';
    print '<body>';
        print '<tr>';
            print '<td style="width:180px;text-align:left;" >'.$langs->trans('resume').'</td>';
            print '<td >';
                print '<textarea name="srchcandi_resume" style="width:calc(100% - 8px);">'.$srchcandi_resume.'</textarea>';
            print '</td>';
        print '</tr>';
    print '</tbody>';
print '</table>';



print '<div style="clear:both;"></div>';

print '</div>'; // end srch_with_candidatures

print '</div>';





















print '<div style="text-align:center;margin: 27px 0 6px;">';
print '<input type="submit" value="'.$langs->trans('Search').'" name="bouton" class="butAction" />';
print '<input type="submit" value="'.$langs->trans('Reset').'" name="button_removefilter" class="butAction" />';
print '</div>';


print '</form>';

$options = "&srchpost_label=".$srchpost_label."&srchpost_departement=".$srchpost_departement."&srchpost_lieu=".$srchpost_lieu."&srchpost_email=".$srchpost_email."&srchpost_date=".$srchpost_date."&srchpost_status=".$srchpost_status."&srchpost_nb_nouveauemploye=".$srchpost_nb_nouveauemploye."&srchpost_responsable_RH=".$srchpost_responsable_RH."&srchpost_responsable_recrutement=".$srchpost_responsable_recrutement;

$options .= "&srchcandi_sujet=".$srchcandi_sujet."&srchcandi_etape=".$srchcandi_etape."&srchcandi_nom=".$srchcandi_nom."&srchcandi_niveau=".$srchcandi_niveau."&srchcandi_contact=".$srchcandi_contact."&srchcandi_email=".$srchcandi_email."&srchcandi_tel=".$srchcandi_tel."&srchcandi_mobile=".$srchcandi_mobile."&srchcandi_origine=".$srchcandi_origine."&srchcandi_poste=".$srchcandi_poste."&srchcandi_resume=".$srchcandi_resume."&srchcandi_date_depot=".$srchcandi_date_depot."&srchcandi_responsable=".$srchcandi_responsable."&srchcandi_departement=".$srchcandi_departement."&srchcandi_appreciation=".$srchcandi_appreciation."&srchcandi_apport_par=".$srchcandi_apport_par."&srchcandi_salaire_demande=".$srchcandi_salaire_demande."&srchcandi_salaire_propose=".$srchcandi_salaire_propose."&srchcandi_date_disponible=".$srchcandi_date_disponible."&etiq=".$etiq;


$modname = $langs->trans("recru_result_of_search");
print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], $options, $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
// print '<input name="id_cv" type="hidden" value="'.$id_ecv.'">';

// print '<div style="float: right; margin: 8px;">';
// print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
// print '</div>';

print '<div class="div-table-responsive">';
print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';
	print '<tr class="liste_titre">';

	field($langs->trans("sujet"),'sujet');
	field($langs->trans("nom_candidat"),'nom_candidat');
	field($langs->trans("contact"),'contact');
	field($langs->trans("niveau"),'niveau');
	field($langs->trans("situation"),'situation');
	field($langs->trans("responsable"),'responsable');
	field($langs->trans("departement"),'departement');
	// print '<th align="center"></th>';

	print '</tr>';
print '</thead>';

print '<tbody>';
$colspn = 7;
if (count($candidatures->rows) > 0) {
	for ($i=0; $i < count($candidatures->rows) ; $i++) {
		$var = !$var;
		$item = $candidatures->rows[$i];

    	$responsable = new User($db);
		$contact = new Contact($db);
		$etapes = new etapescandidature($db);
		$departement = new departements($db);
    	$responsable->fetch($item->responsable);

		print '<tr '.$bc[$var].' >';
    		print '<td align="center" style=""><a href="'.dol_buildpath('/recrutement/candidatures/card.php?id='.$item->rowid,2).'" >';
    			print $item->sujet;
    		print '</a></td>';
    		print '<td align="center">'.$item->nom.'</td>';
    		print '<td align="center">';
    			if($item->contact){
    				$contact->fetch($item->contact);
    				// print $contact->firstname.' '.$contact->lastname;
    				print $contact->getNomUrl(1);
    			}
    		print '</td>';
    		print '<td align="center">'.$langs->trans($item->niveau).'</td>';
    		print '<td align="left" class="etat">';
    			// if($item->etape && $item->refuse ==0){
    				$etapes->fetch($item->etape);
    				print'<span style="background-color:'.$etapes->color.';color:white;padding:0 15px;"></span>&nbsp;&nbsp;';
    				print $langs->trans($etapes->label);
    			// }
    			// if($item->refuse ==1){
    			// 	print'<span style="background-color:#ff5858;color:white;padding:0 15px;"></span>&nbsp;&nbsp;';
    			// 	print 'Refuser';
    			// }
    			if($item->refuse ==1){
    				print '&nbsp;<span class="refuse"><b>(Refuser)</b></span>';
    				// print'<span style="background-color:white;color:white;padding:0 15px;"></span>&nbsp;&nbsp;';
    			}
    		print '</td>';
    		print '<td align="center" style="">'.$responsable->getNomUrl(0).'</td>';
    		

    		print '<td align="center" style="">';
	    		if($item->departement){
	    			$departement->fetch($item->departement);
	    			print '<a href="'.dol_buildpath('/recrutement/departements/card.php?id='.$item->departement,2).'" >'.$departement->label.'</a>';
	    		}
    		print '</td>';
    		
			// print '<td align="center"></td>';
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
	global $langs,$filter;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
	print '</th>';
}

?>
<script>
	$(function() {
        $('.hidecollapse').stop().slideToggle(100);
	    $('#srch_with_candidatures').click(function(){
	        $('.srch_with_candidatures').stop().slideToggle(100);
	        return false;
	    });
	    $('#srch_with_postes').click(function(){
	        $('.srch_with_postes').stop().slideToggle(100);
	        return false;
	    });
		$("#datepicker1").datepicker({ dateFormat: 'yy' });
		$( ".datepicker" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});
		$('#srch_fk_user').select2();
		$('#srch_competance').select2();
		$('select#srchpost_status').select2();
		$('#niveau').select2();
		$('#srchpost_lieu').select2();
	});
</script>


<style type="text/css">
	table.border th {
	    padding: 7px 8px 7px 8px;
	}
	@media only screen and (max-width: 570px){
		td,th {
		    white-space: nowrap;
		}
	}
	.refuse{
		color:#e01212f2;
		font-size: 11px;
	}
</style>
<?php

llxFooter();