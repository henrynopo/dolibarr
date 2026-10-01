<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';



dol_include_once('/sghr/employeedocs/class/docsemployes.class.php');
dol_include_once('/core/class/html.form.class.php');

$langs->load('employeedocs@sghr');

$modname = $langs->trans("Liste_des_docsemployes");

// Initial Objects

$employeeDocs  = new EmployeeDocs($db);
$employeeDocs2  = new EmployeeDocs($db);

$object  = new EmployeeDocs($db);
$form           = new Form($db);
$user22           = new User($db);
$objectuser           = new User($db);



$hookmanager=new HookManager($db);
$hookmanager->initHooks(array('employeedocsindex'));

// $object->fetch($id);
$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action); // See description below
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');







$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "number";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= (int) $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->docs->read) {
	accessforbidden();
}





$objdocs  = new EmployeeDocs($db);
$modtxt = 'docsemployes';
global $dolibarr_main_data_root;
if (!dolibarr_get_const($db, strtoupper($modtxt).'_CHANGEPATHDOCS',0)){
	$source = dol_buildpath('/uploads/'.$modtxt);
	if(@is_dir($source)){
		$docdir = $dolibarr_main_data_root.'/'.$modtxt;
		$dmkdir = dol_mkdir($docdir, '', 0755);
		if($dmkdir >= 0){
			@chmod($docdir, 0775);
			$dcopy = dolCopyDir($source, $docdir, 0775, 1);
			// if($dcopy >= 0){
				dolibarr_set_const($db, strtoupper($modtxt).'_CHANGEPATHDOCS',1,'chaine',0,'',0);
				$objdocs->docsemployespermissionto($docdir);
			// }
		}
	}
}


$param = '';

$id = GETPOST('id');

$srch_number = GETPOST('srch_number');
$srch_fk_user = GETPOST('srch_fk_user');
$srch_issuedate  = GETPOST('srch_issuedate');
$srch_expirydate  = GETPOST('srch_expirydate');
$srch_fk_type_document  = GETPOST('srch_fk_type_document');

if($id)
	$objectuser->fetch($id);

$date = explode('/', $srch_issuedate);
$issuedate = $date[2]."-".$date[1]."-".$date[0];

$filter .= (!empty($srch_number)) ? " AND number like '%".$srch_number."%'" : "";
$filter .= (!empty($srch_issuedate)) ? " AND CAST(issuedate as date) = '".$issuedate."'" : "";

$date = explode('/', $srch_expirydate);
$expirydate = $date[2]."-".$date[1]."-".$date[0];

$filter .= (!empty($srch_expirydate)) ? " AND CAST(expirydate as date) = '".$expirydate."'" : "";
if($id != 0 && $id != ""){
	$filter .= (!empty($id)) ? " AND fk_user = ".$id."" : "";
}
if($srch_fk_type_document != 0 && $srch_fk_type_document != ""){
	$filter .= (!empty($srch_fk_type_document)) ? " AND fk_type_document =".$srch_fk_type_document."" : "";
}
// debut

// $limit 	= $conf->liste_limit+1;
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if ($limit > 0 && $limit != $my_limit) $param.='&limit='.$limit;
if($id) $param .= '&id='.$id;

$param .= '&srch_number='.$srch_number;
$param .= '&srch_expirydate='.$srch_expirydate;
$param .= '&srch_issuedate='.$srch_expirydate;
$param .= '&srch_fk_type_document='.$srch_fk_type_document;


if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	$filter = "";
	$offset = 0;
	$filter = "";
	$srch_name = "";
	$srch_debut = "";

	$srch_number = "";
	$srch_fk_user = "";
	$srch_fk_type_document = "";
	$srch_expirydate = "";
	$srch_fk_type_document = "";
}

// echo $filter;

$nbrtotal = $employeeDocs->fetchAll($sortorder, $sortfield, $limit+1, $offset, $filter);

$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
	$nbtotalofrecords = $employeeDocs2->fetchAll($sortorder, $sortfield, "", "", $filter);
	if (($page * $limit) > $nbtotalofrecords)	// if total resultset is smaller then paging size (filtering), goto and load page 0
	{
		$page = 0;
		$offset = 0;
	}
}

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


if ($objectuser->id)
{
	/*
	 * Affichage onglets
	 */
	if (!empty($conf->notification->enabled)) $langs->load("mails");
	$head = user_prepare_head($objectuser);

	$form = new Form($db);

	dol_fiche_head($head, 'tab_docsemploye', $langs->trans("User"), -1, 'user');

	$linkback = '';
	if ($user->rights->user->user->lire || $user->admin) {
		$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
	}

    dol_banner_tab($objectuser, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

		print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
			print_barre_liste('', $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $nbrtotal, $nbtotalofrecords, '', 0, '', '', $limit);

			print '<input name="pagem" type="hidden" value="'.$page.'">';
			print '<input name="offsetm" type="hidden" value="'.$offset.'">';
			print '<input name="limitm" type="hidden" value="'.$limit.'">';
			print '<input name="filterm" type="hidden" value="'.$filter.'">';
			print '<input name="id" type="hidden" value="'.$id.'">';

			print '<table id="table-1" class="noborder" style="width: 100%;" >';
				print '<thead>';

					print '<tr class="liste_titre">';

						print_liste_field_titre($langs->trans("numberdoc"),$_SERVER["PHP_SELF"], "number", '', '', 'align="center"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("issuedate"),$_SERVER["PHP_SELF"], "issuedate", '', '', 'align="center"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("expirydate"),$_SERVER["PHP_SELF"], "expirydate", '', '', 'align="center"', $sortfield, $sortorder);
						print_liste_field_titre($langs->trans("fk_type_document"),$_SERVER["PHP_SELF"], "fk_type_document", '', '', 'align="center"', $sortfield, $sortorder);
						print '<th align="center">'.$langs->trans("Action").'</th>';

					print '</tr>';

					print '<tr class="liste_titre nc_filtrage_tr">';

						print '<td align="center"><input style="max-width: 129px;" type="text" class="" id="srch_number" name="srch_number" value="'.$srch_number.'"/></td>';
						print '<td align="center"><input style="max-width: 129px;" type="text" class="datepicker" id="srch_issuedate" name="srch_issuedate" value="'.$srch_issuedate.'"/></td>';
						print '<td align="center"><input style="max-width: 129px;" type="text" class="datepicker" id="srch_expirydate" name="srch_expirydate" value="'.$srch_expirydate.'"/></td>';
						print '<td align="center">'.$employeeDocs->select_documents_type($srch_fk_type_document,'srch_fk_type_document',1,"rowid","name").'</td>';

						print '<td align="center">';
							print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
							print '&nbsp;<input type="image" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
						print '</td>';
					print '</tr>';


				print '</thead>';
				print '<tbody>';
					$colspn = 6;
					if (count($employeeDocs->rows) > 0) {
						for ($i=0; $i < count($employeeDocs->rows) ; $i++) {
							if ($i < min($nbrtotal, $limit)){
								$var = !$var;
								$item = $employeeDocs->rows[$i];
								$employeeDocs->get_type_document($item->fk_type_document);
								$d=explode(' ', $item->issuedate);
								$date_d = explode('-', $d[0]);
						    	$issue = $date_d[2]."/".$date_d[1]."/".$date_d[0];

						    	$f=explode(' ', $item->expirydate);
								$date_f = explode('-', $f[0]);
						    	$expiry = $date_f[2]."/".$date_f[1]."/".$date_f[0];
								print '<tr '.$bc[$var].' >';
						    		print '<td align="center" style="">'; 
						    		print '<a href="'.dol_buildpath('/sghr/employeedocs/card.php?id='.$item->rowid,2).'" >';
						    		print $item->number.' </a>';
						    		print '</td>';
						    		
						    		print '<td align="center"> '.$issue.' </td>';
						    		print '<td align="center"> '.$expiry.' </td>';
						    		print '<td align="center">';
						    		print $employeeDocs->get_type_document($item->fk_type_document);
						    		print '</td>';
						    		

						    		print '<td></td>';
								print '</tr>';
							}
						}
					}else{
						print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
					}

				print '</tbody>';
			print '</table>';
		print '</form>';
	print '</div>';

}

else
{
	accessforbidden('', 0, 1);
}



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