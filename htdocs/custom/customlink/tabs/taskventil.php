<?php
/* Copyright (C) 2014-2022	Charlene BENKE		<charlene@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
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
 *	\file	   htdocs/customlink/tabs/factureVentil.php
 *	\brief	  liaison de facture fournisseur et calcul de la marge
 *	\ingroup	customlink
 */
$res=@include("../../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) 
	$res=@include("../../../main.inc.php");		// For "custom" directory


require_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';

require_once DOL_DOCUMENT_ROOT.'/margin/lib/margins.lib.php';

dol_include_once('/custom/customlink/class/customlink.class.php');
dol_include_once('/custom/customlink/core/lib/customlink.lib.php');

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

$langs->load('projects');
$langs->load("companies");
$langs->load("customlink@customlink");
$langs->load("bills");
$langs->load('margins');

$mine = $_REQUEST['mode']=='mine' ? 1 : 0;
$withproject=GETPOST('withproject', 'int');
$project_ref = GETPOST('project_ref', 'alpha');

$id = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('facid', 'int'));
$ref = GETPOST('ref', 'alpha');


$object = new Task($db);
$projectstatic = new Project($db);
$object->fetch($id, $ref);
if ($id=="")
	$id=$object->id;
$projectstatic->fetch($object->fk_project);
if (!empty($projectstatic->socid)) 
	$projectstatic->fetch_thirdparty();

$soc = new Societe($db);
$soc->fetch($object->socid);


// Security check
$socid="";
if (! empty($user->socid)) $socid=$user->socid;
restrictedArea($user, 'projet', $object->fk_project, 'projet&project');

$action	= GETPOST('action', 'alpha');

// Retreive First Task ID of Project if withprojet is on to allow project prev next to work
if (! empty($project_ref) && ! empty($withproject)) {
	if ($projectstatic->fetch('', $project_ref) > 0) {
		$tasksarray=$object->getTasksArray(0, 0, $projectstatic->id, $socid, 0);
		if (count($tasksarray) > 0) {
			$id=$tasksarray[0]->id;
			$object->fetch($id);
		} else
			header("Location: ".DOL_URL_ROOT.'/projet/tasks.php?id='.$projectstatic->id.(empty($mode)?'':'&mode='.$mode));
	}
}


/*
 *	View
 */

$help_url='https://wiki.patas-monkey.com/index.php?title=CustomLink';
llxHeader("", $langs->trans("Tasks")." - CustomLink", $help_url);

dol_htmloutput_mesg($mesg);

$form = new Form($db);

if (! empty($withproject)) {
	// Tabs for project
	$tab='tasks';
	$head=project_prepare_head($projectstatic);
	dol_fiche_head($head, $tab, $langs->trans("Project"), -1, ($projectstatic->public?'projectpub':'project'));

	$param=($mode=='mine'?'&mode=mine':'');
	$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php">'.$langs->trans("BackToList").'</a>';
	
	$morehtmlref='<div class="refidno">';
	$morehtmlref.=$projectstatic->title;

	if ($projectstatic->thirdparty->id > 0)
		$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $projectstatic->thirdparty->getNomUrl(1, 'project');
	$morehtmlref.='</div>';

	// Define a complementary filter for search of next/prev ref.
	if (! $user->rights->projet->all->lire) {
		$objectsListId = $object->getProjectsAuthorizedForUser($user, 0, 0);
		$projectstatic->next_prev_filter=" rowid in (".(count($objectsListId)?join(',', array_keys($objectsListId)):'0').")";
	}
	dol_banner_tab($projectstatic, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';

	print '<table class="border tableforfield centpercent">';

	// Usage
	print '<tr><td class="tdtop">';
	print $langs->trans("Usage");
	print '</td>';
	print '<td>';
	if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES))
	{
		print '<input type="checkbox" disabled name="usage_opportunity"'.(GETPOSTISSET('usage_opportunity') ? (GETPOST('usage_opportunity', 'alpha') != '' ? ' checked="checked"' : '') : ($projectstatic->usage_opportunity ? ' checked="checked"' : '')).'"> ';
		$htmltext = $langs->trans("ProjectFollowOpportunity");
		print $form->textwithpicto($langs->trans("ProjectFollowOpportunity"), $htmltext);
		print '<br>';
	}
	if (empty($conf->global->PROJECT_HIDE_TASKS))
	{
		print '<input type="checkbox" disabled name="usage_task"'.(GETPOSTISSET('usage_task') ? (GETPOST('usage_task', 'alpha') != '' ? ' checked="checked"' : '') : ($projectstatic->usage_task ? ' checked="checked"' : '')).'"> ';
		$htmltext = $langs->trans("ProjectFollowTasks");
		print $form->textwithpicto($langs->trans("ProjectFollowTasks"), $htmltext);
		print '<br>';
	}
	if (!empty($conf->global->PROJECT_BILL_TIME_SPENT))
	{
		print '<input type="checkbox" disabled name="usage_bill_time"'.(GETPOSTISSET('usage_bill_time') ? (GETPOST('usage_bill_time', 'alpha') != '' ? ' checked="checked"' : '') : ($projectstatic->usage_bill_time ? ' checked="checked"' : '')).'"> ';
		$htmltext = $langs->trans("ProjectBillTimeDescription");
		print $form->textwithpicto($langs->trans("BillTime"), $htmltext);
		print '<br>';
	}
	print '</td></tr>';


	// Visibility
	print '<tr><td class="titlefield">'.$langs->trans("Visibility").'</td><td>';
	if ($projectstatic->public) print $langs->trans('SharedProject');
	else print $langs->trans('PrivateProject');
	print '</td></tr>';

	// Date start - end
	print '<tr><td>'.$langs->trans("DateStart").' - '.$langs->trans("DateEnd").'</td><td>';
	print dol_print_date($projectstatic->date_start, 'day');
	$end=dol_print_date($projectstatic->date_end, 'day');
	if ($end) print ' - '.$end;
	print '</td></tr>';

	// Budget
	print '<tr><td>'.$langs->trans("Budget").'</td><td>';
	if (strcmp($projectstatic->budget_amount, '')) 
		print price($projectstatic->budget_amount, '', $langs, 1, 0, 0, $conf->currency);
	print '</td></tr>';

	// Other attributes
	$cols = 2;
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';

	print '</div>';
	print '<div class="fichehalfright">';
	print '<div class="ficheaddleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border" width="100%">';

	// Description
	print '<td class="titlefield tdtop">'.$langs->trans("Description").'</td><td>';
	print nl2br($projectstatic->description);
	print '</td></tr>';

	// Categories
	if ($conf->categorie->enabled) {
		print '<tr><td valign="middle">'.$langs->trans("Categories").'</td><td>';
		print $form->showCategories($projectstatic->id, 'project', 1);
		print "</td></tr>";
	}

	print '</table>';

	print '</div>';
	print '</div>';
	print '</div>';
	print '<div class="clearboth"></div>';


	dol_fiche_end();
	print '<br>';
}


$head = task_prepare_head($object);

dol_fiche_head($head, 'customlink', $langs->trans("Task"), -1, 'projecttask');


$linkback = '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';

$morehtmlref.='</div>';

// To give a chance to dol_banner_tab to use already paid amount to show correct status		
$object->totalpaye = $totalpaye;

dol_banner_tab($object, 'ref', $linkback, 1, ((int) DOL_VERSION >= 10?'ref':'facnumber'), 'ref', $morehtmlref, '', 0);
//dol_fiche_end();

print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

// Task parent
print '<tr><td>'.$langs->trans("ChildOfTask").'</td><td>';
if ($object->fk_task_parent > 0) {
	$tasktmp = new Task($db);
	$tasktmp->fetch($object->fk_task_parent);
	print $tasktmp->getNomUrl(1);
}
// Date start - Date end
print '<tr><td class="titlefield">'.$langs->trans("DateStart").' - '.$langs->trans("DateEnd").'</td><td>';
$start = dol_print_date($object->date_start, 'dayhour');
print ($start ? $start : '?');
$end = dol_print_date($object->date_end, 'dayhour');
print ' - ';
print ($end ? $end : '?');
if ($object->hasDelay()) {
	print img_warning("Late");
}
print '</td></tr>';

// Planned workload
print '<tr><td>'.$langs->trans("PlannedWorkload").'</td><td>';
if ($object->planned_workload) {
	print convertSecondToTime($object->planned_workload, 'allhourmin');
}
print '</td></tr>';
print '</table>';

print '</div>';

print '<div class="fichehalfright"><div class="ficheaddleft">';

print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
// Progress declared
print '<tr><td class="titlefield">'.$langs->trans("ProgressDeclared").'</td><td>';
print $object->progress != '' ? $object->progress.' %' : '';
print '</td></tr>';

// Progress calculated
print '<tr><td>'.$langs->trans("ProgressCalculated").'</td><td>';
if ($object->planned_workload) {
	$tmparray = $object->getSummaryOfTimeSpent();
	if ($tmparray['total_duration'] > 0) {
		print round($tmparray['total_duration'] / $object->planned_workload * 100, 2).' %';
	} else {
		print '0 %';
	}
} else {
	print '<span class="opacitymedium">'.$langs->trans("WorkloadNotDefined").'</span>';
}
print '</td>';
print '</tr>';


//if (! empty($conf->margin->enabled)) {
if (false) {
	print '<tr>';
	print '<td rowspan="7" colspan="2" valign="top">';
	// Margin Infos
		displayMarginInfos($object->statut > 0);
	
	print '</td>';
	print '</tr>';
}
print '</table>';

print '</div>';

print '</div></div>';
print '<div style="clear:both"></div>';

$sql = "SELECT *  FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
$sql.= " WHERE ffv.entity = ".$conf->entity;
$sql.= " AND ffv.fk_facture_link =".$id;
$sql.= " AND ffv.fk_facture_typelink =4";
$sql.= " ORDER BY ffv.rowid";

$result=$db->query($sql);
if ($result) {
	$num = $db->num_rows($result);

	// c'est forcément une facture fournisseur qui est ventilé
	//$Facture=new FactureFournisseur($db);
	print_barre_liste(
					$langs->trans("ListOfVentiledBillsInput"), $page, "facturefournventil.php", 
					$urlparam, $sortfield, $sortorder, '', $num, $num, 'ventilinput@customlink'
	);

	//print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	//print '<input type="hidden" class="flat" name="id" value="'.$id.'">';
	print '<table class="noborder" width="100%">';

	print "<tr class='liste_titre'>";
	print_liste_field_titre($langs->trans("Ref"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Company"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("DateInvoice"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("DateVentilation"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("label"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("PriceUHT"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("VAT"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Qty"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("TotalTTC"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print "</tr>\n";


	$total = 0;
	$i = 0;
	while ($i < $num) {
		$objp = $db->fetch_object($result);

		$linkedobject = new FactureFournisseur($db);
		$linkedobject->fetch($objp->fk_facture_fourn);
		print "<tr>";
		print "<td>".$linkedobject->getNomUrl(1)."</td>";
		$soc = new Societe($db);
		$soc->fetch($linkedobject->socid);
		print "<td>".$soc->getNomUrl(1)."</td>";
		print "<td>".dol_print_date($linkedobject->date, "%d/%m/%Y")."</td>";
		print "<td>".dol_print_date($objp->datev, "%d/%m/%Y")."</td>";
		print "<td>".$objp->label."</td>";
		print "<td>".price($objp->subprice)."</td>";
		print "<td>".price($objp->tva_tx)."</td>";
		print "<td>".$objp->qty."</td>";
		print "<td>".price($objp->total_ttc)."</td>";
		print "</tr>\n";
		$i++;
	}
	print '</table>';
}
else
	dol_print_error($db);


dol_fiche_end();
llxFooter();
$db->close();

function displayMarginInfos($force_price=false) 
{
	global $object, $db, $langs, $conf, $user;

	if (! empty($user->socid)) return;

	if (! $user->rights->margins->liretous) return;

	$rounding = min($conf->global->MAIN_MAX_DECIMALS_UNIT, $conf->global->MAIN_MAX_DECIMALS_TOT);

	$marginInfo = getMarginInfos($force_price, 0, 0, 0, 0, 0, 0);

	print '<table class="noborder margininfos" width="100%">';
	print '<tr class="liste_titre">';
	print '<td width="30%" >'.$langs->trans('Margins').'</td>';
	print '<td width="20%" align="right">'.$langs->trans('SellingPrice').'</td>';
	if ($conf->global->MARGIN_TYPE == "1")
		print '<td width="20%" align="right">'.$langs->trans('BuyingPrice').'</td>';
	else
		print '<td width="20%" align="right">'.$langs->trans('CostPrice').'</td>';
	print '<td width="20%" align="right">'.$langs->trans('Margin').'</td>';
	print '</tr>';

	print '<tr class="impair">';
	print '<td>'.$langs->trans('MarginOnProducts').'</td>';
	print '<td align="right">'.price($marginInfo['pv_products'], null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($marginInfo['pa_products'], null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($marginInfo['margin_on_products'], null, null, null, null, $rounding).'</td>';
	print '</tr>';

	print '<tr class="pair">';
	print '<td>'.$langs->trans('MarginOnServices').'</td>';
	print '<td align="right">'.price($marginInfo['pv_services'], null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($marginInfo['pa_services'], null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($marginInfo['margin_on_services'], null, null, null, null, $rounding).'</td>';
	print '</tr>';

	$sql = "SELECT sum(total_ht) as totalventil";
	$sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
	$sql.= " WHERE ffv.entity = ".$conf->entity;
	$sql .= " AND ffv.fk_facture_link =".$object->id;
	$sql .= " AND ffv.fk_facture_typelink =0";
	$sql.= " ORDER BY ffv.rowid";

	$result=$db->query($sql);

	if ($result) {
		$obj = $db->fetch_object($result);
		$totalventil = $obj->totalventil;
	}

	print '<tr class="impair">';
	print '<td>'.$langs->trans('MarginOnVentilation').'</td>';
	print '<td align="right">'.price(0, null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($totalventil, null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price(-$totalventil, null, null, null, null, $rounding).'</td>';
	print '</tr>';

	print '<tr class="pair">';
	print '<td>'.$langs->trans('TotalMargin').'</td>';
	print '<td align="right">'.price($marginInfo['pv_total'], null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($marginInfo['pa_total']+$totalventil, null, null, null, null, $rounding).'</td>';
	print '<td align="right">'.price($marginInfo['total_margin']-$totalventil, null, null, null, null, $rounding).'</td>';
	print '</tr>';
	print '</table>';
}