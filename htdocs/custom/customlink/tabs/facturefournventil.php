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
 *	\file	   htdocs/customlink/tabs/facturefournventil.php
 *	\brief	  liaison de facture fournisseur
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
require_once(DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php');

dol_include_once('/custom/customlink/class/customlink.class.php');
dol_include_once('/custom/customlink/core/lib/customlink.lib.php');

if ($conf->global->MAIN_MODULE_FACTORY)
	dol_include_once('/factory/class/factory.class.php');

if (!empty($conf->projet->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
	$langs->load('projects');
}
if (!empty($conf->fichinter->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
	$langs->load('interventions');
}


$langs->load("companies");
$langs->load("customlink@customlink");
$langs->load("bills");

$id = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('facid', 'int'));
$ref = GETPOST('ref', 'alpha');
$action	= GETPOST('action', 'alpha');


$subprice = GETPOST('subprice','int');
$tva_tx = GETPOST('tva_tx','int');
$qty = GETPOST('qty','int');
$label = GETPOST('label','alpha');

// Security check
if (! empty($user->socid)) $socid=$user->socid;
$result = restrictedArea($user, 'fournisseur', $id, 'facture_fourn', 'facture');

$object = new FactureFournisseur($db);
$object->fetch($id, $ref);
if (empty($id))
	$id = $object->id;

$customlinkstatic = new Customlink($db);
// on alimente les clés
$customlinkstatic->fk_source=$id;


// suppression d'une ventilation
if ($action=="deletebillink") {
	$customlinkstatic->rowid = GETPOST("facture_link");
	$customlinkstatic->deleteventilation($user);
	$action="";
}


// Ajout d'une ventilation
if ($action=="addbillink") {
	$dateventil=dol_mktime(
					0, 0, 0, 
					GETPOST('datevmonth', 'int'), GETPOST('datevday', 'int'), GETPOST('datevyear', 'int')
	);	
	$customlinkstatic->type_source="invoice_supplier";
	$typeobjectlinked=GETPOST("typeobjectlinked", "int");
	// on active la bonne liaison selon le type de target
	switch($typeobjectlinked) {
		case 0 :
			$customlinkstatic->type_target="facture";
			break;
		case 1 :
			$customlinkstatic->type_target="invoice_supplier";
			break;
		case 2 :
			$customlinkstatic->type_target="factory";
			break;
		case 3 :
			$customlinkstatic->type_target="fichinter";
			break;
		case 4 :
			$customlinkstatic->type_target="task";
			break;
	}
	
	// on récupère l'id de la facture à lier
	$customlinkstatic->fk_target = $customlinkstatic->get_idlink($customlinkstatic->type_target, trim(GETPOST("reffact", 'alpha')));

	if ($customlinkstatic->fk_target > 0) // on crée le lien
		$customlinkstatic->addventil($subprice, $tva_tx, $qty, $label, $dateventil);
	else {
		setEventMessage($langs->trans("ErrorRefNotFound", $langs->transnoentities("RefTarget")), 'errors');
		$error++;
	}
}


/*
 *	View
*/

$form = new Form($db);

llxHeader();

$object->fetch_thirdparty();

$head = facturefourn_prepare_head($object);
$titre=$langs->trans('SupplierInvoice');
dol_fiche_head($head, 'customlink', $titre, -1, 'bill');

$linkback = '<a href="'.DOL_URL_ROOT.'/fourn/facture/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';
// Ref supplier
$morehtmlref.=$form->editfieldkey(
				"RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 
				0, 'string', '', 0, 1
);
$morehtmlref.=$form->editfieldval(
				"RefSupplier", 'ref_supplier', $object->ref_supplier, $object, 
				0, 'string', '', null, null, '', 1
);
// Thirdparty
$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);
// Project
if (! empty($conf->projet->enabled)) {
	$langs->load("projects");
	$morehtmlref.='<br>'.$langs->trans('Project') . ' ';
	if (! empty($object->fk_project)) {
		$proj = new Project($db);
		$proj->fetch($object->fk_project);
		$morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$object->fk_project.'"';
		$morehtmlref.=' title="'.$langs->trans('ShowProject').'">'.$proj->ref.'</a>';
	} else {
		$morehtmlref.='';
	}
}
$morehtmlref.='</div>';
// To give a chance to dol_banner_tab to use already paid amount to show correct status
$object->totalpaye = $object->getSommePaiement();
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';


print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('Type').'</td><td>';
print $object->getLibType();
if ($object->type == FactureFournisseur::TYPE_REPLACEMENT)
{
	$facreplaced = new FactureFournisseur($db);
	$facreplaced->fetch($object->fk_facture_source);
	print ' ('.$langs->transnoentities("ReplaceInvoice", $facreplaced->getNomUrl(1)).')';
}
if ($object->type == FactureFournisseur::TYPE_CREDIT_NOTE)
{
	$facusing = new FactureFournisseur($db);
	$facusing->fetch($object->fk_facture_source);
	print ' ('.$langs->transnoentities("CorrectInvoice", $facusing->getNomUrl(1)).')';
}

$facidavoir = $object->getListIdAvoirFromInvoice();
if (count($facidavoir) > 0) {
	print ' ('.$langs->transnoentities("InvoiceHasAvoir");
	$i = 0;
	foreach ($facidavoir as $fid) 	{
		if ($i == 0) print ' ';
		else print ',';
		$facavoir = new FactureFournisseur($db);
		$facavoir->fetch($fid);
		print $facavoir->getNomUrl(1);
	}
	print ')';
}
if (isset($facidnext) && $facidnext > 0) {
	$facthatreplace = new FactureFournisseur($db);
	$facthatreplace->fetch($facidnext);
	print ' ('.$langs->transnoentities("ReplacedByInvoice", $facthatreplace->getNomUrl(1)).')';
}
print '</td></tr>';


// Label
print '<tr><td>'.$langs->trans('Label').'</td>';
print '<td colspan="3">'.$object->label.'</td>';

// Date invoice
print '<tr><td>';
print $langs->trans('Date');
print '</td><td colspan="3">';
print dol_print_date($object->date, 'daytext');
print '</td></tr>';
print '</table>';

print '</div>';
print '<div class="fichehalfright"><div class="ficheaddleft">';

print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

print '<tr><td width="20%" class="titlefield">'.$langs->trans('AmountHT').'</td><td width=150px align="right">';
print price($object->total_ht, 1, $langs, 0, -1, -1, $conf->currency);
print '</td><td colspan="2" align="left">&nbsp;</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('AmountVAT').'</td><td align="right">';
print price($object->total_tva, 1, $langs, 0, -1, -1, $conf->currency);
print '</td><td colspan="2" align="left">&nbsp;</td></tr>';
print '<tr><td class="titlefield">'.$langs->trans('AmountTTC').'</td><td  align="right">';
print price($object->total_ttc, 1, $langs, 0, -1, -1, $conf->currency);
print '</td><td colspan="2" align="left">&nbsp;</td></tr>';

print '</table>';
print '</div>';

print '</div></div>';
print '<div style="clear:both"></div>';

// liste des factures ventilé sur cette facture
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
$sql.= " WHERE ffv.entity = ".$conf->entity;
$sql.= " AND ffv.fk_facture_link =".$id;
$sql.= " AND ffv.fk_facture_typelink =1";
$sql.= " ORDER BY ffv.rowid";

//print $sql;
$result=$db->query($sql);
if ($result) {
	$num = $db->num_rows($result);
	if ($num > 0 ) {

		print_barre_liste(
						$langs->trans("ListOfVentiledBillsInput"), $page, "facturefournventil.php",
						$urlparam, $sortfield, $sortorder, '', $num, 0, 'ventilinput@customlink'
		);

		//print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">'."\n";
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
		print_liste_field_titre($langs->trans("PriceUTTC"), "", "", "", $urlparam, '', $sortfield, $sortorder);
		print "</tr>\n";
	

		$total = 0;
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);
			print "<tr >";
			$linkedobject = new FactureFournisseur($db);
			$linkedobject->fetch($objp->fk_facture_link);
			print "<td>".$linkedobject->getNomUrl()."</td>";
			$soc = new Societe($db);
			$soc->fetch($linkedobject->socid);
			print "<td>".$soc->getNomUrl()."</td>";
			print "<td>".dol_print_date($linkedobject->date, "daytext")."</td>";
			print "<td>".dol_print_date($objp->datev, "daytext")."</td>";
			print "<td>".$objp->label."</td>";
			print "<td>".price($objp->subprice)."</td>";
			print "<td>".price($objp->tva_tx)."</td>";
			print "<td>".$objp->qty."</td>";
			print "<td>".price($objp->total_ttc)."</td>";
			print "</tr>\n";

			$i++;
		}

		print '</table>';
		print '<br>';
	}
}

print_barre_liste(
				$langs->trans("AddNewVentilation"), 0, "facturefournventil.php",
				'', '', '', '', $num, 0, 'ventilation@customlink'
);

$value_qty=1;

// Ajout d'un lien sur une facture cliente
print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="addbillink">';
print '<input type="hidden" name="id" value="'.$id.'">';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width=175px ><a name="addcustom"></a>'; // ancre
print $langs->trans('TypeOfLink').'</td>';
print '<td width=175px >'; // ancre
print $langs->trans('RefOfLink').'</td>';
print_liste_field_titre($langs->trans("DateVentilation"));
print_liste_field_titre($langs->trans("PriceUHT"));
print_liste_field_titre($langs->trans("VAT"));
print_liste_field_titre($langs->trans("Qty"));
print_liste_field_titre($langs->trans("label"));

print "<td></td>";
print "</tr>\n";

// soit 0, soit 1, soit 2

print "<tr >\n";
print '<td>';
$arrayLinked=array();
$arrayLinked[0] = $langs->trans("CustomerBill");
$arrayLinked[1] = $langs->trans("FournishBill");
if (!empty($conf->global->MAIN_MODULE_FACTORY))
	$arrayLinked[2] = $langs->trans("FactoryOF");
if (empty($conf->global->fichinter))
	$arrayLinked[3] = $langs->trans("Interventionnal");
if (empty($conf->global->project))
	$arrayLinked[4] = $langs->trans("Tasks");

//var_dump($arrayLinked);
print $form->selectarray("typeobjectlinked", $arrayLinked);
print '</td>';

print '<td><input type=text name=reffact size=10 value=""></td>';
print '<td>';
print $form->selectDate("", 'datev', 0, 0, '', "datev");
print '</td>';
print '<td align="left"><input type="text" size="8" name="subprice" value="'.$subprice.'"></td>';

print '<td align="left">'.$form->load_tva('tva_tx', $tva_tx, $object->thirdparty).'</td>';
print '<td align="left"><input type="text" size="3" name="qty" value="'.$value_qty.'"></td>';
print '<td><input type=text name=label size=30 value="'.$label.'"></td>';
print '<td align="center" valign="middle" >';
print '<input type="submit" class="button" value="'.$langs->trans('Add').'" name="addline">';
print '</td>';
print "</tr>\n";
print '</table >';
print '</form>'."\n";

dol_fiche_end();


// ensuite la liste des montant associés aux factures
$sql = "SELECT * ";
$sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
$sql.= " WHERE ffv.entity = ".$conf->entity;
$sql .= " AND ffv.fk_facture_fourn =".$id;
$sql.= " ORDER BY ffv.rowid";

$result=$db->query($sql);
if ($result) {
	$num = $db->num_rows($result);

	if ($num >0) {
		//dol_fiche_head();
		print_barre_liste(
						$langs->trans("ListOfVentiledBillsOutput"), 0, "facturefournventil.php", 
						'', '', '', '', $num, $num, 'ventiloutput@customlink'
		);

		print '<input type="hidden" class="flat" name="id" value="'.$id.'">';
		print '<table class="noborder" width="100%">';

		print "<tr class='liste_titre'>";
		print_liste_field_titre($langs->trans("Ref"));
		print_liste_field_titre($langs->trans("Company").' / '.$langs->trans("Product"));
		print_liste_field_titre($langs->trans("DateInvoice").' / '.$langs->trans("DateBuildOF"));
		print_liste_field_titre($langs->trans("DateVentilation"));
		print_liste_field_titre($langs->trans("label"));
		print_liste_field_titre($langs->trans("PriceUHT"));
		print_liste_field_titre($langs->trans("VAT"));
		print_liste_field_titre($langs->trans("Qty"));
		print_liste_field_titre($langs->trans("PriceUTTC"));
		print_liste_field_titre("", "", "", "", "", '', "", "");
		print "</tr>\n";

		$total = 0;
		$i = 0;
		while ($i < $num) {
			$objp = $db->fetch_object($result);

			switch ($objp->fk_facture_typelink) {
				case 0:
					$linkedobject = new Facture($db);
					break;
				case 1:
					$linkedobject = new FactureFournisseur($db);
					break;
				case 2:
					$linkedobject = new Factory($db);
					break;
				case 3:
					$linkedobject = new Fichinter($db);
					break;
				case 4:
					$linkedobject = new Task($db);
					break;
			}
			$linkedobject->fetch($objp->fk_facture_link);
			print "<tr >";
			print "<td>";
			if ($objp->fk_facture_typelink == 4)
				print $linkedobject->getNomUrl(1, 'withproject');
			else
				print $linkedobject->getNomUrl(1);
			print "</td>";
			switch($objp->fk_facture_typelink) {
				case 2: 
					$associatedObject = new Product($db);
					$associatedObject->fetch($linkedobject->fk_product);
					$datelinked=($linkedobject->date_start_made ? $linkedobject->date_start_made:$linkedobject->date_start_planned);
					break;
				case 4: 
					$associatedObject = new Project($db);
					$associatedObject->fetch($linkedobject->fk_project);
					$datelinked=$linkedobject->date_start;
					break;
	
				default : 
					$associatedObject = new Societe($db);
					$associatedObject->fetch($linkedobject->socid);
					$datelinked=$linkedobject->date;
					break;
			}
			print "<td>".$associatedObject->getNomUrl(1)."</td>";
			print "<td>".dol_print_date($datelinked, "daytext")."</td>";
			print "<td>".dol_print_date($objp->datev, "daytext")."</td>";
			print "<td>".$objp->label."</td>";
			print "<td>".price($objp->subprice)."</td>";
			print "<td>".price($objp->tva_tx)."</td>";
			print "<td>".$objp->qty."</td>";
			print "<td>".price($objp->total_ttc)."</td>";
			print "<td><a href='facturefournventil.php?action=deletebillink&token=".newToken()."&id=".$id."&facture_link=".$objp->rowid."'>";
			print img_delete()."</a></td>";
			print "</tr>\n";
			$i++;
		}

		// on affiche le total
		
		print '</table>';
	}
	$db->free($result);
} else
	dol_print_error($db);

llxFooter();
$db->close();