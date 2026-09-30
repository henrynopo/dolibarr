<?php 
/* Copyright (C) 2015		Yassine Belkaid	<y.belkaid@nextconcept.ma>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       salariescontracts/list.php
 *		\ingroup    list
 *		\brief      List of salaries contracts
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
dol_include_once('/grh/salariescontracts/common.inc.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

$langs->load('scontracts@salariescontracts');
$langs->load("certification@compta");
$langs->load('users');

// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;

if (! $sortfield) $sortfield = "t.rowid";
if (! $sortorder) $sortorder = "DESC";
$offset 	= $conf->liste_limit * $page ;
$pageprev 	= $page - 1;
$pagenext 	= $page + 1;

$id = GETPOST('id','int');

$search_ref      = GETPOST('search_ref');
$month_create    = GETPOST('month_create');
$year_create     = GETPOST('year_create');
$month_start     = GETPOST('month_start');
$year_start      = GETPOST('year_start');
$month_end       = GETPOST('month_end');
$year_end        = GETPOST('year_end');
$search_employe  = GETPOST('search_employe');
$search_type  	 = GETPOST('search_type');

 // Both test are required to be compatible with all browsers
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$search_ref   = "";
	$month_create = "";
	$year_create  = "";
    $month_start  = "";
	$year_start   = "";
	$month_end 	  = "";
	$year_end 	  = "";
	$search_employe= "";
	$search_type  = "";
}

/**
 * Actions
 */

// None

/**
 * View
 */

$salariescontracts 		= new Salariescontracts($db);
$salariescontractstatic = new Salariescontracts($db);
$fuser 					= new User($db);

$max_year 	= 5;
$min_year 	= 10;
$filter 	= '';

llxHeader(array(), $langs->trans('ListOfSalaries'), '', '', '', '', array('/grh/salariescontracts/js/salariescontracts.js'));

// WHERE
if(!empty($search_ref)) {
    $filter .= " AND t.rowid = ". $db->escape($search_ref) ."\n";
}

// DATE START
if($year_start > 0) {
    if($month_start > 0) {
    	$filter .= " AND (t.start_date BETWEEN '".$db->idate(dol_get_first_day($year_start,$month_start,1))."' AND '".$db->idate(dol_get_last_day($year_start,$month_start,1))."')";
    	//$filter.= " AND date_format(t.date_debut, '%Y-%m') = '$year_start-$month_start'";
    } else {
    	$filter .= " AND (t.start_date BETWEEN '".$db->idate(dol_get_first_day($year_start,1,1))."' AND '".$db->idate(dol_get_last_day($year_start,12,1))."')";
    	//$filter.= " AND date_format(t.date_debut, '%Y') = '$year_start'";
    }
} else {
    if($month_start > 0) {
        $filter.= " AND date_format(t.start_date, '%m') = '$month_start'";
    }
}

// DATE FIN
if($year_end > 0) {
    if($month_end > 0) {
    	$filter .= " AND (t.end_date BETWEEN '".$db->idate(dol_get_first_day($year_end,$month_end,1))."' AND '".$db->idate(dol_get_last_day($year_end,$month_end,1))."')";
    	//$filter.= " AND date_format(t.date_fin, '%Y-%m') = '$year_end-$month_end'";
    } else {
    	$filter .= " AND (t.end_date BETWEEN '".$db->idate(dol_get_first_day($year_end,1,1))."' AND '".$db->idate(dol_get_last_day($year_end,12,1))."')";
    	//$filter.= " AND date_format(t.date_fin, '%Y') = '$year_end'";
    }
} else {
    if($month_end > 0) {
        $filter.= " AND date_format(t.end_date, '%m') = '$month_end'";
    }
}

// DATE CREATE
if($year_create > 0) {
    if($month_create > 0) {
    	$filter .= " AND (t.date_create BETWEEN '".$db->idate(dol_get_first_day($year_create,$month_create,1))."' AND '".$db->idate(dol_get_last_day($year_create,$month_create,1))."')";
    	//$filter.= " AND date_format(t.date_create, '%Y-%m') = '$year_create-$month_create'";
    } else {
    	$filter .= " AND (t.date_create BETWEEN '".$db->idate(dol_get_first_day($year_create,1,1))."' AND '".$db->idate(dol_get_last_day($year_create,12,1))."')";
    	//$filter.= " AND date_format(t.date_create, '%Y') = '$year_create'";
    }
} else {
    if($month_create > 0) {
        $filter.= " AND date_format(t.date_create, '%m') = '$month_create'";
    }
}

// EMPLOYE
if(!empty($search_employe) && $search_employe != -1) {
    $filter.= " AND t.fk_user = '".$db->escape($search_employe)."'\n";
}

// type des contrats
if(!empty($search_type) && $search_type != -1) {
    $filter.= " AND t.type = '".$db->escape($search_type)."'\n";
}

/*************************************
 * Fin des filtres de recherche
*************************************/

// Récupération de l'ID de l'utilisateur
$user_id = $user->id;

if ($id > 0) {
	// Charge utilisateur edite
	$fuser->fetch($id);
	$fuser->getrights();
	$user_id = $fuser->id;
}

// Récupération des congés payés de l'utilisateur ou de tous les users
if (empty($user->rights->grh->salariescontracts->write_all) || $id > 0) {
	// $order = $db->order($sortfield, $sortorder);
	$getContractsList = $salariescontracts->fetchByUser($user_id, $order, $filter);	// Load array $holiday->holiday
} else {
    $getContractsList = $salariescontracts->fetchAll($sortorder, $sortfield, $conf->liste_limit + 1, $offset, $filter);	// Load array $salariescontracts->salariescontracts
}

// Si erreur SQL
if ($getContractsList == '-1') {
    // print_fiche_titre($langs->trans('ListOfSalaries'), '', 'title_hrm.png');
    print_fiche_titre($langs->trans('ListOfSalaries'));
    dol_print_error($db, $langs->trans('Error').' '.$salariescontracts->error);
    exit();
}

// Show table of vacations
$var 		= true;
$num 		= count($salariescontracts->lines_sc);
$form 		= new Form($db);
$formother 	= new FormOther($db);

if ($id > 0) {
	$head = user_prepare_head($fuser);

	$title = $langs->trans("User");
	dol_fiche_head($head, 'tabusersc', $title, 0, 'user');

	print '<table class="border" width="100%">';

	// Ref
	print '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
	print '<td colspan="2">';
	print $form->showrefnav($fuser,'id','',$user->rights->user->user->lire || $user->admin);
	print '</td>';
	print '</tr>';

	// LastName
	print '<tr><td width="20%">'.$langs->trans("LastName").'</td>';
	print '<td colspan="2">'.$fuser->lastname.'</td>';
	print "</tr>\n";

	// FirstName
	print '<tr><td width="20%">'.$langs->trans("FirstName").'</td>';
	print '<td colspan="2">'.$fuser->firstname.'</td>';
	print "</tr>\n";

	print '</table><br>';
}
else {
	// dol_fiche_head('', '', $langs->trans("ListOfSalaries"), 0, 'user');
	print_fiche_titre($langs->trans('ListOfSalaries'));
}
// echo $filter;
print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<table class="noborder" width="100%;">';
print "<tr class=\"liste_titre\">";
print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"],"t.rowid","",'','',$sortfield,$sortorder);
print_liste_field_titre($langs->trans("DateCreate"),$_SERVER["PHP_SELF"],"t.date_create","",'','align="center"',$sortfield,$sortorder);
print_liste_field_titre($langs->trans("Salary"),$_SERVER["PHP_SELF"],"t.fk_user","",'','align="center"',$sortfield,$sortorder);
print_liste_field_titre($langs->trans("Type"),$_SERVER["PHP_SELF"],'t.type','','','align="center"',$sortfield,$sortorder);

print_liste_field_titre($langs->trans("HiringDate"),$_SERVER["PHP_SELF"],"t.start_date","",'','align="center"',$sortfield,$sortorder);
print_liste_field_titre($langs->trans("EndDate"),$_SERVER["PHP_SELF"],"t.end_date","",'','align="center"',$sortfield,$sortorder);

print_liste_field_titre('',$_SERVER["PHP_SELF"],"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
print "</tr>\n";

// FILTRES
print '<tr class="liste_titre">';
print '<td class="liste_titre" align="left" width="50">';
print '<input class="flat" size="4" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
print '</td>';

// DATE CREATE
print '<td class="liste_titre" colspan="1" align="center">';
print '<input class="flat" type="text" size="1" maxlength="2" name="month_create" value="'.$month_create.'">';
$formother->select_year($year_create,'year_create',1, $min_year, 0);
print '</td>';

// UTILISATEUR
if ($user->rights->grh->salariescontracts->write_all) {
    print '<td class="liste_titre" align="center">';
    print $form->select_dolusers($search_employe,"search_employe",1,"",0,'','',0,32);
    print '</td>';
}
else {
    //print '<td class="liste_titre">&nbsp;</td>';
    print '<td class="liste_titre" align="center">';
    print $form->select_dolusers($user->id,"search_employe",1,"",1,'','',0,32);
    print '</td>';
}

// Type
print '<td class="liste_titre" colspan="1" align="center">';
print $form->selectarray('search_type', $salariescontracts->getContractsTypes(), (GETPOST('type')?GETPOST('type'):''), 1);
print '</td>';

// DATE DEBUT
print '<td class="liste_titre" colspan="1" align="center">';
print '<input autocomplete="off" class="flat datepicker" type="text" size="1" maxlength="2" name="month_start" value="'.$month_start.'">';
$formother->select_year($year_start,'year_start',1, $min_year, $max_year);
print '</td>';

// DATE FIN
print '<td class="liste_titre" colspan="1" align="center">';
print '<input autocomplete="off" class="flat datepicker" type="text" size="1" maxlength="2" name="month_end" value="'.$month_end.'">';
$formother->select_year($year_end,'year_end',1, $min_year, $max_year);
print '</td>';

// ACTION
print '<td align="right">';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
print '</td>';

print "</tr>\n";


// Lines
if (!empty($salariescontracts->lines_sc)) {
	$approbatorstatic 	= new User($db);

	foreach($salariescontracts->lines_sc as $infos_CP) {
		$var = !$var;

		$approbatorstatic->fetch($infos_CP['fk_user']);
		$createdAt = dol_print_date($infos_CP['date_create'], 'day');
		$endDate   = dol_print_date($infos_CP['end_date'], 'day');
		$salaryId  = $infos_CP['rowid'];

		print '<tr '.$bc[$var].'>';
		print '<td>';
			print $salariescontracts->getNomUrl(1, $salaryId, $salaryId);
		print '</td>';
		print '<td align="center">'. $createdAt .'</td>';
		print '<td align="center">'.$approbatorstatic->getNomUrl('1').'</td>';
		print '<td align="center">'. $salariescontracts->getContractTypeById($infos_CP['type']) .'</td>';
		print '<td align="center">'.dol_print_date($infos_CP['start_date'],'day').'</td>';
		print '<td align="center">'. ($endDate ?: 'Vide') .'</td>';
		print '<td></td>';
		print '</tr>'."\n";

	}
}

// Si il n'y a pas d'enregistrement suite à une recherche
if($getCPforUser == '2') {
    print '<tr>';
    print '<td colspan="9" '.$bc[false].'">'.$langs->trans('None').'</td>';
    print '</tr>';
}

print '</table>';
print '</form>';

if ($user_id == $user->id) {
	print '<br>';
	print '<div style="float: right; margin-top: 8px;">';
	print '<a href="./card.php?action=request" class="butAction">'.$langs->trans('AddSalarieContract').'</a>';
	print '</div>';
}

llxFooter();

$db->close();

?>