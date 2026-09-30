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
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';

require_once DOL_DOCUMENT_ROOT.'/margin/lib/margins.lib.php';

dol_include_once('/custom/customlink/class/customlink.class.php');
dol_include_once('/custom/customlink/core/lib/customlink.lib.php');

if (!empty($conf->projet->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	$langs->load('projects');
}


$langs->load("companies");
$langs->load("customlink@customlink");
$langs->load("bills");

$id = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('facid', 'int'));
$ref = GETPOST('ref', 'alpha');

$object = new Facture($db);
$object->fetch($id, $ref);
if ($id=="")
	$id=$object->id;

$soc = new Societe($db);
$soc->fetch($object->socid);


// Security check
if (! empty($user->socid)) $socid=$user->socid;
$result = restrictedArea($user, 'fournisseur', $id, 'facture_fourn', 'facture');

$action	= GETPOST('action', 'alpha');

/*
 *	View
 */

$help_url='https://wiki.patas-monkey.com/index.php?title=CustomLink';
llxHeader('', $langs->trans("Bill"), $help_url);

$form = new Form($db);

$object->fetch_thirdparty();

$head = facture_prepare_head($object);
dol_fiche_head($head, 'customlink', $langs->trans("InvoiceCustomer"), -1, 'bill');

$linkback = '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php'.(! empty($socid)?'?socid='.$socid:'').'">';
$linkback.= $langs->trans("BackToList").'</a>';

$morehtmlref='<div class="refidno">';
// Ref customer
$morehtmlref.=$form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
$morehtmlref.=$form->editfieldval(
				"RefCustomer", 'ref_client', $object->ref_client, 
				$object, 0, 'string', '', null, null, '', 1
);
// Thirdparty
$morehtmlref.='<br>'.$langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1);

// Project
if (! empty($conf->projet->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
	$langs->load("projects");
	$morehtmlref.='<br>'.$langs->trans('Project') . ' ';
	if (! empty($object->fk_project)) {
		$proj = new Project($db);
		$proj->fetch($object->fk_project);
		$morehtmlref.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$object->fk_project.'"';
		$morehtmlref.=' title="'.$langs->trans('ShowProject').'">';
		$morehtmlref.=$proj->ref;
		$morehtmlref.='</a>';
	} else {
		$morehtmlref.='';
	}
}
$morehtmlref.='</div>';

dol_banner_tab($object, 'ref', $linkback, 1, ((int) DOL_VERSION >= 10?'ref':'facnumber'), 'ref', $morehtmlref, '', 0);
//dol_fiche_end();

print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
	
// Date invoice
print '<tr><td width="30%">';
print $langs->trans('Date');
print '</td><td >';
print dol_print_date($object->date, 'daytext');
print '</td>';
print '</tr>';

// Date payment term
print '<tr><td>';
print $langs->trans('DateMaxPayment');
print '</td><td>';
if ($object->type != 2) {
	$now = dol_now();
	print dol_print_date($object->date_lim_reglement, 'daytext');
	if ($object->date_lim_reglement < ($now - $conf->facture->client->warning_delay) 
			&& ! $object->paye && $object->statut == 1 && ! isset($object->am)) 
		print img_warning($langs->trans('Late'));
} else
	print '&nbsp;';
print '</td></tr>';

// Conditions de reglement
print '<tr><td>';
print $langs->trans('PaymentConditionsShort');
print '</td><td>';
if ($object->type != 2) {
		$form->form_conditions_reglement(
						$_SERVER['PHP_SELF'].'?id='.$object->id, 
						$object->cond_reglement_id, 'none'
		);
} else
	print '&nbsp;';
print '</td>';
print '</tr>';


// Mode de reglement
print '<tr><td>';
print $langs->trans('PaymentMode');
print '</td><td >';
	$form->form_modes_reglement(
					$_SERVER['PHP_SELF'].'?id='.$object->id, 
					$object->mode_reglement_id, 'none'
	);
print '</td>';
print '</tr>';
print '</table>';

print '</div>';
print '<div class="fichehalfright"><div class="ficheaddleft">';

print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

// Montants
print '<tr><td>'.$langs->trans('AmountHT').'</td>';
print '<td align="right" colspan="2" nowrap>'.price($object->total_ht).'</td>';
print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';
print '<tr><td>'.$langs->trans('AmountVAT').'</td>';
print '<td align="right" colspan="2" nowrap>'.price($object->total_tva).'</td>';
print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';
print '<tr><td>'.$langs->trans('AmountTTC').'</td>';
print '<td align="right" colspan="2" nowrap>'.price($object->total_ttc).'</td>';
print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';

// We can also use bcadd to avoid pb with floating points
// For example print 239.2 - 229.3 - 9.9; does not return 0.
//$resteapayer=bcadd($object->total_ttc, $totalpaye, $conf->global->MAIN_MAX_DECIMALS_TOT);
//$resteapayer=bcadd($resteapayer, $totalavoir, $conf->global->MAIN_MAX_DECIMALS_TOT);
				// Total payments
				$sql = 'SELECT SUM(pf.amount) as total_paiements';
				$sql .= ' FROM '.MAIN_DB_PREFIX.'paiement_facture as pf, '.MAIN_DB_PREFIX.'paiement as p';
				$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_paiement as c ON p.fk_paiement = c.id';
				$sql .= ' WHERE pf.fk_facture = '.((int) $object->id);
				$sql .= ' AND pf.fk_paiement = p.rowid';
				$sql .= ' AND p.entity IN ('.getEntity('invoice').')';
				$resql = $db->query($sql);
				if (!$resql) {
					dol_print_error($db);
				}

				$res = $db->fetch_object($resql);
				$total_paiements = $res->total_paiements;

				// Total credit note and deposit
				$total_creditnote_and_deposit = 0;
				$sql = "SELECT re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc,";
				$sql .= " re.description, re.fk_facture_source";
				$sql .= " FROM ".MAIN_DB_PREFIX."societe_remise_except as re";
				$sql .= " WHERE fk_facture = ".((int) $object->id);
				$resql = $db->query($sql);
				if (!empty($resql)) {
					while ($obj = $db->fetch_object($resql)) {
						$total_creditnote_and_deposit += $obj->amount_ttc;
					}
				} else {
					dol_print_error($db);
				}
$resteapayer = price2num($object->total_ttc - $total_paiements - $total_creditnote_and_deposit, 'MT');

print '<tr><td>'.$langs->trans('RemainderToPay').'</td>';
print '<td align="right" colspan="2" nowrap>'.price($resteapayer).'</td>';
print '<td>'.$langs->trans('Currency'.$conf->currency).'</td></tr>';
print '</table>';

print '</div>';

print '</div></div>';
print '<div style="clear:both"></div>';

$sql = "SELECT *  FROM ".MAIN_DB_PREFIX."facture_fourn_ventil as ffv";
$sql.= " WHERE ffv.entity = ".$conf->entity;
$sql.= " AND ffv.fk_facture_link =".$id;
$sql.= " AND ffv.fk_facture_typelink =0";
$sql.= " ORDER BY ffv.rowid";

$result=$db->query($sql);
if ($result) {
	$num = $db->num_rows($result);

	// c'est forcément une facture fournisseur qui est ventilé
	//$Facture=new FactureFournisseur($db);
	print_barre_liste(
					$langs->trans("ListOfVentiledBillsInput"), 0, "facturefournventil.php", 
					'', '', '', '', $num, $num, 'ventilinput@customlink'
	);

	//print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	//print '<input type="hidden" class="flat" name="id" value="'.$id.'">';
	print '<table class="noborder" width="100%">';

	print "<tr class='liste_titre'>";
	print_liste_field_titre($langs->trans("Ref"));
	print_liste_field_titre($langs->trans("Company"));
	print_liste_field_titre($langs->trans("DateInvoice"));
	print_liste_field_titre($langs->trans("DateVentilation"));
	print_liste_field_titre($langs->trans("label"));
	print_liste_field_titre($langs->trans("PriceUHT"));
	print_liste_field_titre($langs->trans("VAT"));
	print_liste_field_titre($langs->trans("Qty"));
	print_liste_field_titre($langs->trans("TotalTTC"));
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