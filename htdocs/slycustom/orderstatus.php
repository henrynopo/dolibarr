<?php
/* Copyright (C) SLY 14.0 / Custom
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/slycustom/orderstatus.php
 *	\ingroup    slycustom
 *	\brief      Search Order — 输入销售订单号（支持模糊），显示 SO/PO 基本信息、关联文件、关联对象、Shipment 信息（无添加标签/添加链接）
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
if (isModEnabled('shipping')) {
	require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
}

// Need commercial langs for SalesRepresentatives label
$langs->loadLangs(array('orders', 'sendings', 'companies', 'bills', 'propal', 'deliveries', 'products', 'commercial', 'other', 'slycustom@slycustom'));

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$so_id_selected = GETPOSTINT('so_id'); // 选择的具体订单 id（模糊搜索多结果时）
$socid = GETPOSTINT('socid');
if (!empty($user->socid)) {
	$socid = $user->socid;
}

// Security: 仍按供应商订单模块做限制
$result = restrictedArea($user, 'fournisseur', 0, '', 'commande');

$usercanread_so   = $user->hasRight('commande', 'lire');
$usercancreate_so = $user->hasRight('commande', 'creer');
$usercanread_po  = ($user->hasRight('fournisseur', 'commande', 'lire') || $user->hasRight('supplier_order', 'lire'));
$usercancreate_po = ($user->hasRight('fournisseur', 'commande', 'creer') || $user->hasRight('supplier_order', 'creer'));

$title = $langs->trans('Order').' - '.$langs->trans('Card');
$help_url = 'EN:Customers_Orders|FR:Commandes_Clients|ES:Pedidos de clientes|DE:Modul_Kundenaufträge';
llxHeader('', $title, $help_url);

// Search form
print '<form method="GET" id="searchorder" action="'.$_SERVER["PHP_SELF"].'">';
print '<h2 style="color:Crimson;">'.$langs->trans("SearchOrder").'</h2>';
print '<input type="hidden" name="mainmenu" value="'.dol_escape_htmltag(GETPOST('mainmenu', 'aZ09')).'">';
print '<input type="hidden" name="leftmenu" value="'.dol_escape_htmltag(GETPOST('leftmenu', 'aZ09')).'">';
print 'SO No : <input class="flat" size="12" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans("Ref").'">';
print ' <input type="submit" class="button" name="search" value="'.$langs->trans("Search").'">';
print '<br><br>';

if ($search_ref === '') {
	print '</form>';
	llxFooter();
	$db->close();
	exit(0);
}

$form = new Form($db);
$formfile = new FormFile($db);
$object_so = new Commande($db);
$object_po = new CommandeFournisseur($db);
$soc = new Societe($db);

// 模糊搜索：先按 c.ref LIKE 查所有匹配的 SO，不连 PO
$sql_search = "SELECT c.rowid, c.ref, c.fk_soc FROM ".MAIN_DB_PREFIX."commande c WHERE c.entity IN (".getEntity('commande').") AND c.ref LIKE '%".$db->escape($search_ref)."%' ORDER BY c.ref DESC";
$res_search = $db->query($sql_search);
$candidates = array();
if ($res_search) {
	while ($row = $db->fetch_object($res_search)) {
		$candidates[] = array('rowid' => (int) $row->rowid, 'ref' => $row->ref, 'fk_soc' => (int) $row->fk_soc);
	}
	$db->free($res_search);
}

// 多结果：让用户选择
if (count($candidates) > 1 && !$so_id_selected) {
	print '<p class="info">'.$langs->trans("MultipleOrdersFound").' '.count($candidates).'</p>';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Ref").'</td><td>'.$langs->trans("ThirdParty").'</td><td></td></tr>';
	foreach ($candidates as $c) {
		$soc->fetch($c['fk_soc']);
		$href = $_SERVER["PHP_SELF"].'?search_ref='.urlencode($search_ref).'&so_id='.$c['rowid'].'&mainmenu='.dol_escape_htmltag(GETPOST('mainmenu', 'aZ09')).'&leftmenu='.dol_escape_htmltag(GETPOST('leftmenu', 'aZ09'));
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($c['ref']).'</td><td>'.$soc->getNomUrl(1).'</td><td><a class="button" href="'.$href.'">'.$langs->trans("Select").'</a></td></tr>';
	}
	print '</table>';
	print '</form>';
	llxFooter();
	$db->close();
	exit(0);
}

// 无结果
if (empty($candidates)) {
	print '<p class="warning">'.$langs->trans("NoRecordFound").'</p>';
	print '</form>';
	llxFooter();
	$db->close();
	exit(0);
}

// 确定唯一 SO：优先选 so_id，否则第一个
$so_rowid = $so_id_selected ? $so_id_selected : (int) $candidates[0]['rowid'];
$object_so->fetch($so_rowid);
if (!$object_so->id) {
	print '<p class="error">'.$langs->trans("NoRecordFound").'</p>';
	print '</form>';
	llxFooter();
	$db->close();
	exit(0);
}

// 查该 SO 关联的 PO（element_element）
$sql_po = 'SELECT cf.rowid FROM '.MAIN_DB_PREFIX.'commande c';
$sql_po .= ' LEFT JOIN (';
$sql_po .= " SELECT ee1.fk_source as fk_source, ee1.fk_target as fk_target FROM ".MAIN_DB_PREFIX."element_element ee1 WHERE ee1.sourcetype = 'order_supplier' AND ee1.targettype = 'commande'";
$sql_po .= " UNION SELECT ee2.fk_target AS fk_source, ee2.fk_source AS fk_target FROM ".MAIN_DB_PREFIX."element_element ee2 WHERE ee2.targettype = 'order_supplier' AND ee2.sourcetype = 'commande'";
$sql_po .= ' ) ee ON c.rowid = ee.fk_target';
$sql_po .= ' LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseur cf ON cf.rowid = ee.fk_source';
$sql_po .= ' WHERE c.rowid = '.(int) $so_rowid;
$res_po = $db->query($sql_po);
$po_rowid = null;
if ($res_po && $row = $db->fetch_object($res_po)) {
	$po_rowid = !empty($row->rowid) ? (int) $row->rowid : null;
	$db->free($res_po);
}
if ($po_rowid) {
	$object_po->fetch($po_rowid);
}

// 客户
if ($object_so->socid) {
	$soc->fetch($object_so->socid);
}
if ($socid > 0 && !$soc->id) {
	$soc->fetch($socid);
}

// SO 联系人：订单/发票/Shipment（按类型取 libelle）
$so_contacts_order = $so_contacts_invoice = $so_contacts_ship = array();
if (method_exists($object_so, 'liste_contact')) {
	$all_contacts = $object_so->liste_contact(-1, 'external');
	if (is_array($all_contacts)) {
		foreach ($all_contacts as $c) {
			$name = trim(($c['firstname'] ?? '').' '.($c['lastname'] ?? ''));
			$code = $c['code'] ?? '';
			$lib = $c['libelle'] ?? $code;
			if (stripos($lib, 'order') !== false || stripos($code, 'COMMANDE') !== false || stripos($code, 'CUSTOMER') !== false) {
				$so_contacts_order[] = $name;
			} elseif (stripos($lib, 'invoice') !== false || stripos($lib, 'facture') !== false || stripos($code, 'BILL') !== false) {
				$so_contacts_invoice[] = $name;
			} elseif (stripos($lib, 'ship') !== false || stripos($lib, 'expedition') !== false || stripos($code, 'SHIP') !== false) {
				$so_contacts_ship[] = $name;
			}
		}
	}
}

// Shipment 及 ATA/ETD/ETA/ATD（expedition + expedition_extrafields）
// 使用与 shipment list 相同的双向 element_element 逻辑，保证结果一致
$ship_ids = array();
$sql_ship = "SELECT eecommande.ship_id";
$sql_ship .= " FROM (";
$sql_ship .= "SELECT ee.fk_target AS ship_id, ee.fk_source AS cmd_id FROM ".MAIN_DB_PREFIX."element_element ee WHERE ee.targettype = 'shipping' AND ee.sourcetype = 'commande'";
$sql_ship .= " UNION ALL";
$sql_ship .= " SELECT ee.fk_source AS ship_id, ee.fk_target AS cmd_id FROM ".MAIN_DB_PREFIX."element_element ee WHERE ee.sourcetype = 'shipping' AND ee.targettype = 'commande'";
$sql_ship .= ") eecommande";
$sql_ship .= " WHERE eecommande.cmd_id = ".(int) $so_rowid;
$res_ship = $db->query($sql_ship);
if ($res_ship) {
	while ($row = $db->fetch_object($res_ship)) {
		$ship_ids[] = (int) $row->ship_id;
	}
	$db->free($res_ship);
}
$shipments_data = array();
if (!empty($ship_ids) && isModEnabled('shipping')) {
	foreach ($ship_ids as $eid) {
		$exp = new Expedition($db);
		if ($exp->fetch($eid) > 0) {
			$row_ef = null;
			$sql_ef = "SELECT pol, atd, pod, ata, etd, eta FROM ".MAIN_DB_PREFIX."expedition_extrafields WHERE fk_object = ".(int) $eid;
			$res_ef = @$db->query($sql_ef);
			if ($res_ef && $db->num_rows($res_ef)) {
				$row_ef = $db->fetch_object($res_ef);
				$db->free($res_ef);
			}
			$shipments_data[] = array(
				'ref' => $exp->ref,
				'date_expedition' => $exp->date_expedition,
				'date_delivery' => $exp->date_delivery,
				// ShipsGo / ETA/ETD/ATD/ATA from extrafields
				'pol' => $row_ef ? ($row_ef->pol ?? '') : '',
				'atd' => $row_ef ? ($row_ef->atd ?? '') : '',
				'pod' => $row_ef ? ($row_ef->pod ?? '') : '',
				'ata' => $row_ef ? ($row_ef->ata ?? '') : '',
				'etd' => $row_ef ? ($row_ef->etd ?? '') : '',
				'eta' => $row_ef ? ($row_ef->eta ?? '') : '',
				'status_html' => $exp->getLibStatut(3),
			);
		}
	}
}

// 输出：仅展示内容，不调用 showLinkToObjectBlock，故无“添加标签/添加链接”
print '<div class="fichecenter"><div class="fichehalfleft">';

// 公司销售代表（基于客户第三方）
$so_salesreps = '-';
if (!empty($soc->id)) {
	$reps = $soc->getSalesRepresentatives($user);
	if (is_array($reps) && count($reps) > 0) {
		$tmpnames = array();
		foreach ($reps as $rep) {
			$user_rep = new User($db);
			if ($user_rep->fetch($rep['id']) > 0) {
				$tmpnames[] = $user_rep->getNomUrl(1);
			}
		}
		if (!empty($tmpnames)) {
			$so_salesreps = implode(', ', $tmpnames);
		}
	}
}

// ----- 1. 销售订单基本信息 -----
$soTitle = '<strong>SALES ORDERS ('.$object_so->ref.')</strong>';
print load_fiche_titre($soTitle, '', '', 0, '', '');
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="fieldrequired">'.$langs->trans("ThirdParty").'</td><td>'.($soc->id ? $soc->getNomUrl(1) : '-').'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("Status").'</td><td>'.$object_so->getLibStatut(3).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("SalesRepresentatives").'</td><td>'.$so_salesreps.'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("InvoiceContact").'</td><td>'.(empty($so_contacts_invoice) ? '-' : implode(', ', array_unique($so_contacts_invoice))).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("ShipmentContact").'</td><td>'.(empty($so_contacts_ship) ? '-' : implode(', ', array_unique($so_contacts_ship))).'</td></tr>';
print '</table>';

// ----- 2. 销售订单关联文件（不显示“Linked files / Documents”标题，仅列表） -----
print '<br>';
$objref_so = dol_sanitizeFileName($object_so->ref);
$filedir_so = !empty($conf->commande->multidir_output[$object_so->entity]) ? $conf->commande->multidir_output[$object_so->entity].'/'.$objref_so : $conf->commande->dir_output.'/'.$objref_so;
$urlsource_so = DOL_URL_ROOT.'/commande/card.php?id='.$object_so->id;
print $formfile->showdocuments('commande', $objref_so, $filedir_so, $urlsource_so, $usercanread_so, 0, '', 1, 0, 0, 0, 0, '', 'none', '', $soc->default_lang ?? '');

// ----- 3. 销售订单 Related Objects（仅表格，金额与订单/发票页面一致：多币种时显示 外币+本币） -----
print '<br>';
$object_so->fetchObjectLinked();
print load_fiche_titre($langs->trans("RelatedObjects"), '', '', 0, '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder allwidth">';
print '<tr class="liste_titre"><td>'.$langs->trans("Type").'</td><td>'.$langs->trans("Ref").'</td><td class="center">'.$langs->trans("Date").'</td><td class="right nowraponall">'.$langs->trans("AmountHTShort").'</td><td class="right">'.$langs->trans("Status").'</td></tr>';
$nbo = 0;
$total_so = 0;
if (!empty($object_so->linkedObjects)) {
	foreach ($object_so->linkedObjects as $otype => $objects) {
		foreach ($objects as $objlink) {
			$nbo++;
			$total_so += (float) ($objlink->total_ht ?? 0);
			$type_label = $otype;
			if ($otype == 'facture') {
				$type_label = $langs->trans("CustomerInvoice");
			} elseif ($otype == 'facturerec') {
				$type_label = $langs->trans("DepositInvoice");
			} elseif ($otype == 'shipping' || $otype == 'shipment') {
				$type_label = $langs->trans("Shipment");
			} elseif ($otype == 'order_supplier') {
				$type_label = $langs->trans("SupplierOrder");
			} elseif ($otype == 'invoice_supplier') {
				$type_label = $langs->trans("SupplierInvoice");
			}
			$amount_cell = '';
			if (isModEnabled('multicurrency')
				&& !empty($objlink->multicurrency_code)
				&& $conf->currency != $objlink->multicurrency_code
				&& (float) ($objlink->multicurrency_total_ht ?? 0) != 0
			) {
				$amount_cell .= $objlink->multicurrency_code.' '.price($objlink->multicurrency_total_ht ?? 0).'<br>';
			}
			$amount_cell .= $conf->currency.' '.price($objlink->total_ht ?? 0);
			print '<tr class="oddeven">';
			print '<td>'.$type_label.'</td>';
			print '<td>'.$objlink->getNomUrl(1).'</td>';
			print '<td class="center">'.dol_print_date($objlink->date ?? 0, 'day').'</td>';
			print '<td class="right nowraponall">'.$amount_cell.'</td>';
			print '<td class="right">'.$objlink->getLibStatut(3).'</td>';
			print '</tr>';
		}
	}
}
if ($nbo == 0) {
	print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table></div>';

print '</div>'; // fichehalfleft

// ----- 右侧：先 Shipment（在采购订单上方），再采购订单 -----
print '<div class="fichehalfright"><div class="ficheaddleft">';

// ----- Shipment 主要信息：Ref、状态、ETD/ETA/ATD/ATA -----
if (!empty($shipments_data)) {
	$shipmentTitle = '<strong>SHIPMENTS ('.$object_so->ref.')</strong>';
	print load_fiche_titre($shipmentTitle, '', '', 0, '', '');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Ref").'</td><td>'.$langs->trans("Status").'</td><td>ETD</td><td>ETA</td><td>ATD</td><td>ATA</td></tr>';
	foreach ($shipments_data as $s) {
		// ATA / ATD：ShipsGo 回写到 expedition_extrafields.ata / atd（DATE）
		$ata_printed = !empty($s['ata']) ? dol_print_date($db->jdate($s['ata']), 'day') : '-';
		$atd_printed = !empty($s['atd']) ? dol_print_date($db->jdate($s['atd']), 'day') : '-';

		// ETD / ETA：优先使用 extrafield etd / eta（UNIX 时间戳或日期），否则退回到发运单计划日期
		if (!empty($s['etd'])) {
			$etd_ts = is_numeric($s['etd']) ? (int) $s['etd'] : $db->jdate($s['etd']);
			$etd_printed = $etd_ts > 0 ? dol_print_date($etd_ts, 'day') : '-';
		} else {
			$etd_printed = !empty($s['date_expedition']) ? dol_print_date($db->jdate($s['date_expedition']), 'day') : '-';
		}
		if (!empty($s['eta'])) {
			$eta_ts = is_numeric($s['eta']) ? (int) $s['eta'] : $db->jdate($s['eta']);
			$eta_printed = $eta_ts > 0 ? dol_print_date($eta_ts, 'day') : '-';
		} else {
			$eta_printed = !empty($s['date_delivery']) ? dol_print_date($db->jdate($s['date_delivery']), 'day') : '-';
		}
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($s['ref']).'</td><td>'.($s['status_html'] ?? '-').'</td><td>'.$etd_printed.'</td><td>'.$eta_printed.'</td><td>'.$atd_printed.'</td><td>'.$ata_printed.'</td></tr>';
	}
	print '</table>';
	print '<br>';
}

// ----- 采购订单 -----
// 标题显示：系统 PO 号 + （可选）供应商单号，例如：PURCHASE ORDERS (PO260027 / XXX123)
$poHeaderRef = $object_po->ref;
if (!empty($object_po->ref_supplier)) {
	$poHeaderRef .= ' / '.$object_po->ref_supplier;
}
$poTitle = '<strong>PURCHASE ORDERS ('.$poHeaderRef.')</strong>';
print load_fiche_titre($poTitle, '', '', 0, '', '');

if (!empty($object_po->id)) {
	// 采购订单基本信息（供应商、状态、跟进采购人员 = 该 PO 的关联联系人，非录入人）
	$sup = new Societe($db);
	if ($object_po->socid) {
		$sup->fetch($object_po->socid);
	}
	// 采购订单关联联系人（element_contact，如“跟进采购”等），即实际跟进该 PO 的人员
	$po_followup_contacts = array();
	if (method_exists($object_po, 'liste_contact')) {
		$po_internal = $object_po->liste_contact(-1, 'internal');
		$po_external = $object_po->liste_contact(-1, 'external');
		foreach (array_merge(is_array($po_internal) ? $po_internal : array(), is_array($po_external) ? $po_external : array()) as $c) {
			$source = $c['source'] ?? '';
			$cid = (int) ($c['id'] ?? 0);
			$name = trim(($c['firstname'] ?? '').' '.($c['lastname'] ?? ''));
			if ($name === '') {
				$name = $c['login'] ?? '';
			}
			if ($name === '') {
				continue;
			}
			if ($source === 'internal' && $cid > 0) {
				$u = new User($db);
				if ($u->fetch($cid) > 0) {
					$po_followup_contacts[] = $u->getNomUrl(1);
				} else {
					$po_followup_contacts[] = dol_escape_htmltag($name);
				}
			} else {
				$url = DOL_URL_ROOT.'/societe/contact/card.php?id='.$cid.($sup->id ? '&socid='.$sup->id : '');
				$po_followup_contacts[] = '<a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($name).'</a>';
			}
		}
	}
	$po_followup_display = empty($po_followup_contacts) ? '-' : implode(', ', array_unique($po_followup_contacts));
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Supplier").'</td><td>'.($sup->id ? $sup->getNomUrl(1) : '-').'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("Status").'</td><td>'.$object_po->getLibStatut(3).'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("FollowUpPurchaser").'</td><td>'.$po_followup_display.'</td></tr>';
	print '</table>';

	// 采购订单关联文件（不显示“Linked files / Documents”标题，仅列表）
	print '<br>';
	$objref_po = dol_sanitizeFileName($object_po->ref);
	$filedir_po = $conf->fournisseur->commande->dir_output.'/'.$objref_po;
	$urlsource_po = DOL_URL_ROOT.'/fourn/commande/card.php?id='.$object_po->id;
	print $formfile->showdocuments('commande_fournisseur', $objref_po, $filedir_po, $urlsource_po, $usercanread_po, 0, '', 1, 0, 0, 0, 0, '', 'none', '', $soc->default_lang ?? '');

	// 采购订单 Related Objects（仅表格，金额与采购订单页面一致：多币种时显示 外币+本币）
	print '<br>';
	$object_po->fetchObjectLinked();
	// 调整 PO 的 Related Objects 显示顺序：先供应商发票，再销售订单，其余类型保持原顺序
	if (!empty($object_po->linkedObjects)) {
		$wantedOrder = array('invoice_supplier', 'commande');
		$reordered = array();
		foreach ($wantedOrder as $otypeWanted) {
			if (!empty($object_po->linkedObjects[$otypeWanted])) {
				$reordered[$otypeWanted] = $object_po->linkedObjects[$otypeWanted];
			}
		}
		foreach ($object_po->linkedObjects as $otypeExisting => $objectsExisting) {
			if (!isset($reordered[$otypeExisting])) {
				$reordered[$otypeExisting] = $objectsExisting;
			}
		}
		$object_po->linkedObjects = $reordered;
	}
	print load_fiche_titre($langs->trans("RelatedObjects"), '', '', 0, '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder allwidth">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Type").'</td><td>'.$langs->trans("Ref").'</td><td class="center">'.$langs->trans("Date").'</td><td class="right nowraponall">'.$langs->trans("AmountHTShort").'</td><td class="right">'.$langs->trans("Status").'</td></tr>';
	$nbo_po = 0;
	$total_po = 0;
	if (!empty($object_po->linkedObjects)) {
		foreach ($object_po->linkedObjects as $otype => $objects) {
			foreach ($objects as $objlink) {
				$nbo_po++;
				$total_po += (float) ($objlink->total_ht ?? 0);
				$type_label = $otype;
				if ($otype == 'commande') {
					$type_label = $langs->trans("CustomersOrders");
				} elseif ($otype == 'order_supplier') {
					$type_label = $langs->trans("SupplierOrder");
				} elseif ($otype == 'invoice_supplier') {
					$type_label = $langs->trans("SupplierInvoice");
				}
				$amount_cell = '';
				if (isModEnabled('multicurrency')
					&& !empty($objlink->multicurrency_code)
					&& $conf->currency != $objlink->multicurrency_code
					&& (float) ($objlink->multicurrency_total_ht ?? 0) != 0
				) {
					$amount_cell .= $objlink->multicurrency_code.' '.price($objlink->multicurrency_total_ht ?? 0).'<br>';
				}
				$amount_cell .= $conf->currency.' '.price($objlink->total_ht ?? 0);
				print '<tr class="oddeven">';
				print '<td>'.$type_label.'</td>';
				print '<td>'.$objlink->getNomUrl(1).'</td>';
				print '<td class="center">'.dol_print_date($objlink->date ?? 0, 'day').'</td>';
				print '<td class="right nowraponall">'.$amount_cell.'</td>';
				print '<td class="right">'.$objlink->getLibStatut(3).'</td>';
				print '</tr>';
			}
		}
	}
	if ($nbo_po == 0) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table></div>';
} else {
	print '<p class="opacitymedium">'.$langs->trans("NoLinkFound").'</p>';
}

print '</div></div></div>';
print '</form>';

llxFooter();
$db->close();

