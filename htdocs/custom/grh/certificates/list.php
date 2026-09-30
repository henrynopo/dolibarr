<?php
/* Copyright (C) 2015 Yassine Belkaid   <y.belkaid@nextconcept.ma>
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
 *	    \file       htdocs/compta/grh/certificates/card.php
 *      \ingroup    certificates
 *		\brief      Page of certificates
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/certificates/class/certificates.class.php');
dol_include_once('/grh/certificates/common.inc.php');

$langs->load("users");
$langs->load("salaries");

$id 	= GETPOST("id",'int');
$action = GETPOST('action');
// Security check
$socid = GETPOST("socid","int");
if ($user->societe_id) $socid=$user->societe_id;
// $result = restrictedArea($user, 'certificates', '', '', '');

// order
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
// filter
$search_ref 	 	= GETPOST("search_ref",'int');
$search_user_appro 	= GETPOST("search_user_appro",'int');
$search_user_ben 	= GETPOST("search_user_ben",'int');
$search_status 		= GETPOST("search_status",'int');

// Both test are required to be compatible with all browsers
if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$search_ref 	 	= "";
	$search_user_appro 	= "";
	$search_user_ben 	= "";
	$search_status 		= "";
}

if (!$sortfield) $sortfield = "t.rowid";
if (!$sortorder) $sortorder = "DESC";
$page 	   = GETPOST("page",'int');
if ($page == -1 || empty($page)) { $page = 0; }
$limit 		= $conf->liste_limit;
$offset 	= $limit * $page;
$pageprev 	= $page - 1;
$pagenext 	= $page + 1;
$param 		= '';
$filter 	= '';

// Ref
if(!empty($search_ref) && $search_ref > 0) {
    $filter.= " AND t.rowid = ". (int) $db->escape($search_ref)."\n";
}

// Appro
if(!empty($search_user_appro) && $search_user_appro != -1) {
    $filter.= " AND t.fk_user_appro = ". (int) $db->escape($search_user_appro)."\n";
}

// STATUT
if($search_status != '' && $search_status != -1) {
    $filter.= " AND t.status = ". (int) $db->escape($search_status)."\n";
}
// Ben
if(!empty($search_user_ben) && $search_user_ben != -1) {
    $filter.= " AND t.fk_user_ben = ". (int) $db->escape($search_user_ben)."\n";
}

$certificates 	= new Certificates($db);
$userstatic 	= new User($db);
$userben 		= new User($db);
$form 			= new Form($db);

$order = $db->order($sortfield, $sortorder).$db->plimit($limit + 1, $offset);
$certs = $certificates->fetchByType(1, $filter, $order);
$url   = $_SERVER["PHP_SELF"];
$param = 'action=list&leftmenu=hrm';

// Si Validation de la demande
if ($action == 'confirm_valid' && $_GET['confirm'] == 'yes' && $id) {
	$certificates->fetch((int) $id);
	$certificates->status = 2;
	$validate = $certificates->updateStatus();

	if ($validate > 0) {
    	header('Location: list.php');
        exit;
    }
    else {
        // Otherwise we display the request form with the SQL error message
        header('Location: list.php?error=SQL_Create&msg='.$certificates->error);
        exit;
    }
}

if (GETPOST('error')) {
    switch(GETPOST('error')) {
        case 'user_appro' :
            $errors[] = $langs->trans('FieldRequired', $langs->trans("Approving"));
            break;
        case 'user_ben' :
            $errors[] = $langs->trans('FieldRequired', html_entity_decode($langs->trans("Beneficiary")));
            break;
        case 'company' :
            $errors[] = $langs->trans('FieldRequired', $langs->trans("Company"));
            break;
        case 'alreadyExist' :
            $errors[] = $langs->trans('ErrorAlreadyExist');
            break;
        case 'SQL_Create':
            $errors[] = GETPOST('msg');
            break;
    }

    setEventMessage($errors, 'errors');
}


llxHeader(array(), $langs->trans('ListOfCertificatesOfSalaries'),'','','','','');
// dol_fiche_head(array(), '', $langs->trans("ListOfCertificates"), 0, 'payment');
print_fiche_titre($langs->trans('ListOfCertificatesOfSalaries'));
// print_barre_liste($langs->trans("ListOfCertificates"),$page,$_SERVER["PHP_SELF"],$param,$sortfield,$sortorder,'',0,$totalnboflines, 'title_accountancy.png');

print '<form method="GET" action="'.$url.'">';
print '<input type="hidden" name="action" value="list" />';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
    print_liste_field_titre($langs->trans("Ref"),$url,"t.rowid","",$param,"",$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Approving"),$url,"t.fk_user_appro","",$param,"",$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Beneficiary"),$url,"t.fk_user_ben","",$param,"",$sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Status"),$url,"t.status","",$param,"",$sortfield,$sortorder);
    print_liste_field_titre($langs->trans('Downloading'),$url,"",'','','',$sortfield,$sortorder,'maxwidthsearch ');
print "</tr>\n";

print '<tr class="liste_titre">';
// Ref
print '<td class="liste_titre" align="left">';
print '<input class="flat" type="text" size="3" name="search_ref" value="'. ($search_ref == -1 ? '' : $search_ref).'">';
print '</td>';

// Approving
print '<td class="liste_titre">';
print $form->select_users($search_user_appro, 'search_user_appro',1,'',0);
print '</td>';

// Ben
print '<td class="liste_titre">';
print $form->select_users($search_user_ben, 'search_user_ben',1,'',0);
print '</td>';

// Status
print '<td class="liste_titre">';
$statusList = array(0 => 'Brouillon', 2 => 'Approuvé');
print $form->selectarray('search_status', $statusList, $search_status, 1);
print '</td>';

print '<td class="liste_titre" align="right"><input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
print "</td></tr>\n";

$var = true;

if (count($certs) && $certs != -1)
foreach ($certs as $key => $cert) {
	// approbateur
	$userstatic->fetch($cert->fk_user_appro);

	// beneficiaire
	$userben->fetch($cert->fk_user_ben);

	$var = !$var;
    print "<tr ".$bc[$var].">";
     // Ref
	print "<td>". $cert->rowid ."</td>";
	// Appro
	print "<td>". $userstatic->getNomUrl(1) ."</td>";
	// Ben
	print "<td>". $userben->getNomUrl(1) ."</td>";
	// Status
	print "<td>". $certificates->statusLevel($cert->status) ."</td>";
	
	// PDF
	print "<td align=\"center\">";
        if ($user->id && $user->rights->grh->certificates->write_salaire)
        {
    		if ($cert->status == 2) {
            	print '<a target="_blank" href="card.php?action=pdf&id='. $cert->rowid .'"><img src="'. DOL_URL_ROOT .'/theme/common/mime/pdf.png" title="Télécharger l\'attestation de salaire" /></a>';
    		}

    		if ($user->id == $cert->fk_user_appro && $cert->status == 0) {
            	print '<a href="list.php?id='.$cert->rowid .'&action=valid" class="butAction">'.$langs->trans("Approve").'</a>';
    		}
        }
	print "</td>";
    
    print "</tr>\n";

}

print '</table>';
print '</form>';
if ($user->id && $user->rights->grh->certificates->write_salaire)

print '<a href="card.php?action=create" class="butAction floatright" style="float: right; margin-top: 10px !important;">'.$langs->trans("DemandSalaryCert").'</a>';

// Si validation de la demande
if ($action == 'valid') {
    print $form->formconfirm("list.php?id=".$id, $langs->trans("TitleValidCS"), $langs->trans("ConfirmValidCS"), "confirm_valid", '', 1, 1);
}

dol_fiche_end();

llxFooter();

$db->close();