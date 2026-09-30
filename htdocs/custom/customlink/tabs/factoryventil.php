<?php
/* Copyright (C) 2014-2022 Charlene BENKE 	 	 <charlene@patas-monkey.com>
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
 *	\file	   htdocs/customlink/tabs/factoryVentil.php
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';

require_once DOL_DOCUMENT_ROOT.'/margin/lib/margins.lib.php';

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');

dol_include_once('/custom/customlink/class/customlink.class.php');
dol_include_once('/custom/customlink/core/lib/customlink.lib.php');

if (!empty($conf->projet->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	$langs->load('projects');
}

$langs->load("companies");
$langs->load("customlink@customlink");
$langs->load("factory@factory");

$langs->load("bills");

$id = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('facid', 'int'));
$ref = GETPOST('ref', 'alpha');

$product = new Product($db);
$entrepot = new Entrepot($db);
$object = new Factory($db);
$object->fetch($id, $ref);
$result = $product->fetch($object->fk_product);
$result = $entrepot->fetch($object->fk_entrepot);

if ($id=="")
	$id=$object->id;

// Security check
$result = restrictedArea($user, 'factory');
$action	= GETPOST('action', 'alpha');

/*
 *	View
 */

llxHeader();

$form = new Form($db);

$object->fetch_thirdparty();

$head = factory_prepare_head($object);
dol_fiche_head($head, 'customlink', $langs->trans('Factory'), -1, 'factory@factory');


$linkback = '<a href="'.dol_buildpath('/factory/', 1).'list.php">'.$langs->trans("BackToList").'</a>';
$urllink=dol_buildpath('/factory/', 1).'list.php';
$morehtmlref='<div class="refidno">';

// Ref product
$morehtmlref.='<br>'.$langs->trans('Product') . ' : ' . $product->getNomUrl(1)." - ".$product->label;
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.$urllink.'?productid='.$object->fk_product.'">'.$langs->trans("OtherFactory").'</a>)';

// ref storage
// rendre modifiable
$morehtmlref.='<br><table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("Warehouse").' :</td>';
$morehtmlref.='<td>';
	if ($object->fk_entrepot >0)
		$morehtmlref.=$entrepot->getNomUrl(1)." - ".$entrepot->lieu." (".$entrepot->zip.")" ;
if (empty($conf->global->MAIN_DISABLE_OTHER_LINK)) 
	$morehtmlref.=' (<a href="'.$urllink.'?entrepotid='.$object->fk_entrepot.'">'.$langs->trans("OtherFactory").'</a>)';

$morehtmlref.='</td></tr>';
$morehtmlref.='</table>';
$morehtmlref.='</div>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
//print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield" width="100%">';
print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
print $langs->trans("OFAdditionalInfos");
print '</td></tr>';

// Date start planned
print '<tr><td width=20%>'.$langs->trans("FactoryDateStartPlanned");
print '</td ><td width=20%>';
print dol_print_date($object->date_start_planned, 'day');
print ' - ';
print dol_print_date($object->date_start_made, 'day');
print '</td>';

// Date end planned
print '<tr><td >'.$langs->trans("FactoryDateEndPlanned").'</td>';
print '<td >';
print dol_print_date($object->date_end_planned, 'day');
print ' - ';
print dol_print_date($object->date_end_made, 'day');
print '</td>';


// Qty planned
print '<tr><td>'.$langs->trans("QuantityPlanned").'</td>';
print '<td >';
print $object->qty_planned;
print ' - ';
print $object->qty_made;
print '</td>';

// send the good value in equipment create form
$qtyequipement = ($object->qty_made ? $object->qty_made : $object->qty_planned);

print '</table>';
print '</div>';
print '<div class="fichehalfright"><div class="ficheaddleft">';

print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield" width="100%">';

print '<tr class="liste_titre nodrag nodrop"><th align=left colspan=2>';
print $langs->trans("ProductsAdditionalInfos");
print '</td></tr>';

// TVA
print '<tr><td>'.$langs->trans("VATRate").'</td><td>'.vatrate($product->tva_tx.($product->tva_npr?'*':''),true).'</td></tr>';

// Status (to sell)
print '<tr><td>'.$langs->trans("Status").'</td><td colspan="2">';
print $product->getLibStatut(2, 0);
print "  ";
print $product->getLibStatut(2, 1);
print '</td></tr>';

print '<tr><td>'.$langs->trans("PhysicalStock").'</td>';
$product->load_stock();
print '<td>'.$product->stock_reel.'</td></tr>';

print '</table>';
print '</div>';

print '</div></div>';
print '<div style="clear:both"></div>';

$sql = "SELECT * ";
$sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
$sql.= " WHERE ffv.entity = ".$conf->entity;
$sql .= " AND ffv.fk_facture_link =".$id;
$sql .= " AND ffv.fk_facture_typelink =2"; // 2 = Factory
$sql.= " ORDER BY ffv.rowid";

$result=$db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	$urlparam="";
	$sortfield="";
	$sortorder="";
	print_barre_liste(
					$langs->trans("ListOfVentiledBillsInput"), 0, 
					"facturefournventil.php", $urlparam, $sortfield, 
					$sortorder, '', $num, 0, 'ventilinput@customlink'
	);

	//print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	//print '<input type="hidden" class="flat" name="id" value="'.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
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
		print "<tr >";
		print "<td>".$linkedobject->getNomUrl()."</td>";
		$soc = new Societe($db);
		$soc->fetch($linkedobject->socid);
		print "<td>".$soc->getNomUrl()."</td>";
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
} else
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