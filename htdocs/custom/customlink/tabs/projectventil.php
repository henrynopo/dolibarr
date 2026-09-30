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


//$object = new Task($db);
$object = new Project($db);
$object->fetch($id, $ref);
if ($id=="")
	$id=$object->id;

if (!empty($object->socid)) 
	$object->fetch_thirdparty();

$soc = new Societe($db);
$soc->fetch($object->socid);


// Security check
$socid="";
if (! empty($user->socid)) $socid=$user->socid;
$result = restrictedArea($user, 'projet', $id);

$action	= GETPOST('action', 'alpha');


/*
 *	View
 */

$help_url='https://wiki.patas-monkey.com/index.php?title=CustomLink';
llxHeader("", $langs->trans("Project")." - CustomLink", $help_url);

dol_htmloutput_mesg($mesg);

$form = new Form($db);

// Tabs for project
$tab='customlink';
$head=project_prepare_head($object);
dol_fiche_head($head, $tab, $langs->trans("Project"), -1, ($object->public?'projectpub':'project'));

$param=($mode=='mine'?'&mode=mine':'');
$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php">'.$langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';
$morehtmlref.=$object->title;

if ($object->thirdparty->id > 0)
	$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'project');
$morehtmlref.='</div>';

// Define a complementary filter for search of next/prev ref.
if (! $user->rights->projet->all->lire) {
	$objectsListId = $object->getProjectsAuthorizedForUser($user, 0, 0);
	$projectstatic->next_prev_filter=" rowid in (".(count($objectsListId)?join(',', array_keys($objectsListId)):'0').")";
}
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

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
	print '<input type="checkbox" disabled name="usage_task"'.(GETPOSTISSET('usage_task') ? (GETPOST('usage_task', 'alpha') != '' ? ' checked="checked"' : '') : ($object->usage_task ? ' checked="checked"' : '')).'"> ';
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
if ($object->public) print $langs->trans('SharedProject');
else print $langs->trans('PrivateProject');
print '</td></tr>';

// Date start - end
print '<tr><td>'.$langs->trans("DateStart").' - '.$langs->trans("DateEnd").'</td><td>';
print dol_print_date($object->date_start, 'day');
$end=dol_print_date($object->date_end, 'day');
if ($end) print ' - '.$end;
print '</td></tr>';

// Budget
print '<tr><td>'.$langs->trans("Budget").'</td><td>';
if (strcmp($object->budget_amount, '')) 
	print price($object->budget_amount, '', $langs, 1, 0, 0, $conf->currency);
print '</td></tr>';

// Description
print '<td class="titlefield tdtop">'.$langs->trans("Description").'</td><td>';
print nl2br($object->description);
print '</td></tr>';

// Categories
if ($conf->categorie->enabled) {
	print '<tr><td valign="middle">'.$langs->trans("Categories").'</td><td>';
	print $form->showCategories($object->id, 'project', 1);
	print "</td></tr>";
}

print '</table>';

print '</div>';
print '<div class="fichehalfright">';
print '<div class="ficheaddleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border" width="100%">';
// on affiche le récapitulatif des entrées et sortie du projet
print "<tr class='liste_titre'>";
print '<td align="left">'.$langs->trans("Elements").'</td>';
print '<td align="right">'.$langs->trans("Nb").'</td>';
print '<td align="right">'.$langs->trans("TotalHT").'</td>';
print '<td align="right">'.$langs->trans("TotalTTC")."</td></tr>";
// on boucle sur la liste des éléments à récuperer
$totalht =0;
$totalttc =0;

// facture clients
$sql = "SELECT count(*) as nb, sum(total_ht) as totht, sum(total_ttc) as totttc";
$sql.= " FROM ".MAIN_DB_PREFIX."facture";
$sql.= " WHERE fk_projet=".$object->id;
$resql = $db->query($sql);
if($resql) {
	$objp = $db->fetch_object($result);
	$totalht = $objp->totht;
	$totalttc = $objp->totttc;
	print '<tr><td align="left">'.$langs->trans("Bill").'</td>';
	print '<td align="right">'.$objp->nb.'</td>';
	print '<td align="right">'.price( $objp->totht).'</td>';
	print '<td align="right">'.price($objp->totttc)."</td></tr>";
}
// note de frais
$sql = "SELECT count(*) as nb, sum(total_ht) as totht, sum(total_ttc) as totttc";
$sql.= " FROM ".MAIN_DB_PREFIX."expensereport_det";
$sql.= " WHERE fk_projet=".$object->id;
$resql = $db->query($sql);
if($resql) {
	$objp = $db->fetch_object($result);
	$totalht+= $objp->totht;
	$totalttc+= $objp->totttc;
	print '<tr><td align="left">'.$langs->trans("ExpenseReport").'</td>';
	print '<td align="right">'.$objp->nb.'</td>';
	print '<td align="right">'.price( $objp->totht).'</td>';
	print '<td align="right">'.price($objp->totttc)."</td></tr>";
}

// payment divers payment_various
$sql = "SELECT count(*) as nb, sum(amount) as totht, sum(amount) as totttc";
$sql.= " FROM ".MAIN_DB_PREFIX."payment_various";
$sql.= " WHERE fk_projet=".$object->id;
$resql = $db->query($sql);
if($resql) {
	$objp = $db->fetch_object($result);
	$totalht+= $objp->totht;
	$totalttc+= $objp->totttc;
	print '<tr><td align="left">'.$langs->trans("PayementVarious").'</td>';
	print '<td align="right">'.$objp->nb.'</td>';
	print '<td align="right">'.price( $objp->totht).'</td>';
	print '<td align="right">'.price($objp->totttc)."</td></tr>";
}



print '</table>';

print '</div>';
print '</div>';
print '</div>';
print '<div class="clearboth"></div>';


dol_fiche_end();
print '<br>';


print '<div style="clear:both"></div>';

$sql = "SELECT ffv.fk_facture_fourn, ffv.fk_facture_link, ffv.datev, ffv.subprice, ffv.tva_tx, ffv.qty, ffv.total_ttc";
$sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
$sql.= "	 INNER JOIN ".MAIN_DB_PREFIX."projet_task as pt ON ffv.fk_facture_link = pt.rowid";
$sql.= " WHERE ffv.entity = ".$conf->entity;
$sql.= " 	AND pt.fk_projet =".$id;
$sql.= " 	AND ffv.fk_facture_typelink =4";
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
	print_liste_field_titre($langs->trans("Task"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("DateVentilation"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("label"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("PriceUHT"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("VAT"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Qty"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("TotalTTC"), "", "", "", $urlparam, '', $sortfield, $sortorder);
	print "</tr>\n";


	$total = 0;
	$i = 0;
	$soc = new Societe($db);
	$task = new Task($db);
	while ($i < $num) {
		$objp = $db->fetch_object($result);

		$linkedobject = new FactureFournisseur($db);
		$linkedobject->fetch($objp->fk_facture_fourn);
		print "<tr>";
		print "<td>".$linkedobject->getNomUrl(1)."</td>";
		
		$soc->fetch($linkedobject->socid);
		print "<td>".$soc->getNomUrl(1)."</td>";
		$task->fetch($objp->fk_facture_link);
		print "<td>".dol_print_date($linkedobject->date, "%d/%m/%Y")."</td>";
		print "<td>".$task->getNomUrl(1, 'withproject')."</td>";
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