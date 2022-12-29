<?php
/* Copyright (C) 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file 	htdocs/commande/orderstatus.php
 */

 require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Load translation files required by the page
$langs->loadLangs(array('orders', 'sendings', 'companies', 'bills', 'propal', 'deliveries', 'products', 'other'));

$search_ref = '';
$search_ref = GETPOST('search_ref', 'alpha');

// Security check
if (!empty($user->socid)) {
	$socid = $user->socid;
}
$result = restrictedArea($user, 'commande', $id);

$usercanread_so			= $user->rights->commande->lire;
$usercancreate_so		= $user->rights->commande->creer;
$usercandelete_so		= $user->rights->commande->supprimer;

$permissionnote_so		= $usercancreate; // Used by the include of actions_setnotes.inc.php
$permissiondellink_so	= $usercancreate; // Used by the include of actions_dellink.inc.php
$permissiontoadd_so		= $usercancreate; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php

// Common permissions
$result = restrictedArea($user, 'fournisseur', $id, 'commande_fournisseur', 'commande', 'fk_soc', 'rowid', $isdraft);

$usercanread_po			= ($user->rights->fournisseur->commande->lire || $user->rights->supplier_order->lire);
$usercancreate_po		= ($user->rights->fournisseur->commande->creer || $user->rights->supplier_order->creer);
$usercandelete_po		= (($user->rights->fournisseur->commande->supprimer || $user->rights->supplier_order->supprimer) || ($usercancreate && isset($object->statut) && $object->statut == $object::STATUS_DRAFT));

$permissionnote_po		= $usercancreate; // Used by the include of actions_setnotes.inc.php
$permissiondellink_po	= $usercancreate; // Used by the include of actions_dellink.inc.php
$permissiontoedit_po	= $usercancreate; // Used by the include of actions_lineupdown.inc.php
$permissiontoadd_po		= $usercancreate; // Used by the include of actions_addupdatedelete.inc.php

$title = $langs->trans('Order')." - ".$langs->trans('Card');
$help_url = 'EN:Customers_Orders|FR:Commandes_Clients|ES:Pedidos de clientes|DE:Modul_Kundenaufträge';
llxHeader('', $title, $help_url);

$sql = 'SELECT';
$sql .= ' c.rowid as salesorder, c.ref as salesorderref, SO_ATA(c.rowid) as ATA, ';
$sql .= " cf.rowid as supplierorder, cf.ref as supplierorderref ";
$sql .= ' FROM '.MAIN_DB_PREFIX.'commande as c';
$sql .= ' LEFT JOIN (';
$sql .= " SELECT ee1.fk_source as fk_source, ee1.fk_target as fk_target FROM ".MAIN_DB_PREFIX."element_element as ee1 WHERE (ee1.sourcetype = 'order_supplier' AND ee1.targettype = 'commande')";
$sql .= ' UNION ';
$sql .= " SELECT ee2.fk_target as fk_source, ee2.fk_source as fk_target FROM ".MAIN_DB_PREFIX."element_element as ee2 WHERE (ee2.targettype = 'order_supplier' AND ee2.sourcetype = 'commande')";
$sql .= ' ) as eecommande ON (c.rowid = eecommande.fk_target)';
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande_fournisseur as cf on (cf.rowid = eecommande.fk_source)";
$sql .= " WHERE c.ref = '". $search_ref." ' ";

$resql = $db->query($sql);
$obj = $db->fetch_object($resql);

print '<form method="POST" id="searchorder" action="'.$_SERVER["PHP_SELF"].'">';
print '<h2 style="color:Crimson;">Search Order</h2>';
print 'SO No : <input class="flat" size="6" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
print '<input type="submit" class="button" name="search" value="'.$langs->trans("Search").'">';
print '<br><br><br>';

$form = new Form($db);
$formfile = new FormFile($db);
$object_so = new Commande($db);
$object_po = new CommandeFournisseur($db);

$soc = new Societe($db);
if ($socid > 0) {
	$res = $soc->fetch($socid);
}

$object_so->fetch($obj->salesorder);
$object_po->fetch($obj->supplierorder);

// Sales Order

print '<div class="fichecenter"><div class="fichehalfleft">';
print '<h3 style="color:DarkBlue;">Sales Order</h3>';
print '<h4 style="color:DarkGreen;">ATA :  '.dol_print_date($obj->ATA, 0).'</h4>';
$objref_so = dol_sanitizeFileName($object_so->ref);
$relativepath_so = $objref_so.'/'.$objref_so.'.pdf';
$filedir_so = $conf->commande->multidir_output[$object_so->entity].'/'.$objref_so;
$urlsource_so = $_SERVER["PHP_SELF"]."?id=".$object_so->id;
$genallowed_so = $usercanread_so;
$delallowed_so = $usercancreate_so;

print $formfile->showdocuments('commande', $objref_so, $filedir_so, $urlsource_so, $genallowed_so, 0, '', 1, 0, 0, 0, 0, '', '', '', $soc->default_lang);
$form->showLinkedObjectBlock($object_so, '', false);
print '</div>';

// Purchase Order

print '<div class="fichehalfright"><div class="ficheaddleft">';
print '<h3 style="color:DarkBlue;">Purchase Order</h3>';

$objref_po = dol_sanitizeFileName($object_po->ref);
$file_po = $conf->fournisseur->dir_output.'/commande/'.$objref_po.'/'.$objref_po.'.pdf';
$relativepath_po = $objref_po.'/'.$objref_po.'.pdf';
$filedir_po = $conf->fournisseur->dir_output.'/commande/'.$objref_po;
$urlsource_po = $_SERVER["PHP_SELF"]."?id=".$objref_po->id;
$genallowed_po = $usercanread_po;
$delallowed_po = $usercancreate_po;

print $formfile->showdocuments('commande_fournisseur', $objref_po, $filedir_po, $urlsource_po, $genallowed_po, 0, '', 1, 0, 0, 0, 0, '', '', '', $soc->default_lang);
$form->showLinkedObjectBlock($object_po, '', false);

print '</div></div></div>';

// End of page
llxFooter();
$db->close();
