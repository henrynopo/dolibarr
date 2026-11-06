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
 *	\file       htdocs/custom/slycustom/orderstatus.php
 *	\ingroup    slycustom
 *	\brief      Search Order — 支持按 SO / PO / 销售发票 / 采购发票 / Shipment 号码（模糊）检索，
 *	            统一解析到销售订单后输出 SO、PO 基本信息、关联文件、关联对象与 Shipment 信息
 *	            （无添加标签/添加链接）
 *
 *	业务逻辑全部封装在 SlycustomOrderSearch 类中，本页面只负责渲染。
 */

// Try to locate main.inc.php for both repo layout (htdocs/...) and deployed layout (/custom under webroot)
$res = 0;
if (!$res && file_exists(__DIR__.'/../main.inc.php')) {
	$res = @include __DIR__.'/../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
	$res = @include __DIR__.'/../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
	$res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
	die('Include of main.inc.php failed for SLY Custom orderstatus.php');
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/class/SlycustomOrderSearch.class.php';

// Need commercial langs for SalesRepresentatives label
$langs->loadLangs(array('orders', 'sendings', 'companies', 'bills', 'propal', 'deliveries', 'products', 'commercial', 'other', 'slycustom@slycustom'));

$search_ref = trim(GETPOST('search_ref', 'alphanohtml'));
$search_type = GETPOST('search_type', 'alphanohtml');
$so_id_selected = GETPOSTINT('so_id');		// 选择的具体销售订单 id（模糊搜索多结果时）
$sel_id_selected = GETPOSTINT('sel_id');	// 选择的具体主单据 id（PO/发票/Shipment 检索时）
$socid = GETPOSTINT('socid');
if (!empty($user->socid)) {
	$socid = $user->socid;
}

$searcher = new SlycustomOrderSearch($db, $user);

// 检索类型非法时回退为销售订单
if (!in_array($search_type, SlycustomOrderSearch::getSupportedTypes())) {
	$search_type = SlycustomOrderSearch::TYPE_SO;
}

// Security: 页面入口拦截。
// 本页支持 5 种检索类型，入口只要求“至少具备其中一种的读取权限”；
// 具体检索类型的越权由下方 canReadType() 逐类型校验（禁止只靠前端隐藏）。
$has_any_read = false;
foreach (SlycustomOrderSearch::getSupportedTypes() as $tmp_type) {
	if (SlycustomOrderSearch::canReadType($user, $tmp_type)) {
		$has_any_read = true;
		break;
	}
}
if (!$has_any_read) {
	accessforbidden();
}

// Security: 检索类型对应模块的读取权限校验（越权拦截，禁止只靠前端隐藏）
if ($search_ref !== '' && !SlycustomOrderSearch::canReadType($user, $search_type)) {
	accessforbidden();
}

$usercanread_so   = $user->hasRight('commande', 'lire');
$usercancreate_so = $user->hasRight('commande', 'creer');
$usercanread_po  = ($user->hasRight('fournisseur', 'commande', 'lire') || $user->hasRight('supplier_order', 'lire'));
$usercancreate_po = ($user->hasRight('fournisseur', 'commande', 'creer') || $user->hasRight('supplier_order', 'creer'));
$usercanread_ship = $user->hasRight('expedition', 'lire');

$title = $langs->trans('Order').' - '.$langs->trans('Card');
$help_url = 'EN:Customers_Orders|FR:Commandes_Clients|ES:Pedidos de clientes|DE:Modul_Kundenaufträge';
llxHeader('', $title, $help_url);

// 当前用户可用的检索类型（权限不足的类型不出现在下拉框中）
$available_types = array();
foreach (SlycustomOrderSearch::getSupportedTypes() as $tmp_type) {
	if (SlycustomOrderSearch::canReadType($user, $tmp_type)) {
		$available_types[] = $tmp_type;
	}
}
if (empty($available_types)) {
	$available_types = array(SlycustomOrderSearch::TYPE_SO);
}

// Search form
print '<form method="GET" id="searchorder" action="'.$_SERVER["PHP_SELF"].'">';
print '<h2 style="color:Crimson;">'.$langs->trans("SearchOrder").'</h2>';
print '<input type="hidden" name="mainmenu" value="'.dol_escape_htmltag(GETPOST('mainmenu', 'aZ09')).'">';
print '<input type="hidden" name="leftmenu" value="'.dol_escape_htmltag(GETPOST('leftmenu', 'aZ09')).'">';
print $langs->trans("SearchType").' : <select name="search_type" id="search_type" class="flat">';
foreach ($available_types as $tmp_type) {
	print '<option value="'.dol_escape_htmltag($tmp_type).'"'.($tmp_type == $search_type ? ' selected="selected"' : '').'>'.dol_escape_htmltag($searcher->getTypeLabel($tmp_type)).'</option>';
}
print '</select> ';
print dol_escape_htmltag($searcher->getTypeRefLabel($search_type)).' : <input class="flat" size="16" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'" placeholder="'.$langs->trans("Ref").'">';
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

// 模糊搜索主单据，并解析到关联的销售订单
$primaries = $searcher->searchPrimaries($search_type, $search_ref);
$resolved = $searcher->resolveOrders($search_type, $primaries);

// 多结果：让用户选择
if (count($resolved) > 1 && !$so_id_selected) {
	print '<p class="info">'.($search_type == SlycustomOrderSearch::TYPE_SO ? $langs->trans("MultipleOrdersFound") : $langs->trans("MultipleDocumentsFound")).' '.count($resolved).'</p>';

	// 批量读取第三方名称，避免在循环内逐条 fetch
	$socids = array();
	foreach ($resolved as $tmp_row) {
		$socids[(int) $tmp_row['fk_soc']] = (int) $tmp_row['fk_soc'];
	}
	$socnames = $searcher->fetchThirdPartyNames($socids);

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("Type").'</td><td>'.$langs->trans("Ref").'</td>';
	if ($search_type != SlycustomOrderSearch::TYPE_SO && $usercanread_so) {
		print '<td>'.$langs->trans("RelatedSalesOrder").'</td>';
	}
	print '<td>'.$langs->trans("ThirdParty").'</td><td></td></tr>';
	foreach ($resolved as $tmp_row) {
		$href = $_SERVER["PHP_SELF"]
			.'?search_type='.urlencode($search_type)
			.'&search_ref='.urlencode($search_ref)
			.'&so_id='.(int) $tmp_row['so_id']
			.'&sel_id='.(int) $tmp_row['primary_id']
			.'&mainmenu='.urlencode(GETPOST('mainmenu', 'aZ09'))
			.'&leftmenu='.urlencode(GETPOST('leftmenu', 'aZ09'));
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($searcher->getTypeLabel($search_type)).'</td>';
		print '<td>'.dol_escape_htmltag($tmp_row['primary_ref']).'</td>';
		if ($search_type != SlycustomOrderSearch::TYPE_SO && $usercanread_so) {
			print '<td>'.dol_escape_htmltag($tmp_row['so_ref']).'</td>';
		}
		print '<td>'.dol_escape_htmltag(isset($socnames[(int) $tmp_row['fk_soc']]) ? $socnames[(int) $tmp_row['fk_soc']] : '-').'</td>';
		print '<td><a class="button" href="'.dol_escape_htmltag($href).'">'.$langs->trans("Select").'</a></td>';
		print '</tr>';
	}
	print '</table>';
	print '</form>';
	llxFooter();
	$db->close();
	exit(0);
}

// 无结果
if (empty($resolved)) {
	print '<p class="warning">'.$langs->trans("NoRecordFound").'</p>';
	print '</form>';
	llxFooter();
	$db->close();
	exit(0);
}

// 确定唯一结果：优先选 so_id（只在已按 entity/socid 过滤的候选集内取值，
// Commande::fetch(rowid) 不带 entity 条件，直接信任 so_id 会造成跨实体越权读取）。
$selected = null;
if ($so_id_selected) {
	foreach ($resolved as $tmp_row) {
		if ((int) $tmp_row['so_id'] === (int) $so_id_selected
			&& (!$sel_id_selected || (int) $tmp_row['primary_id'] === (int) $sel_id_selected)) {
			$selected = $tmp_row;
			break;
		}
	}
}
if (empty($selected)) {
	$selected = $resolved[0];
}
$so_rowid = (int) $selected['so_id'];

// 只有具备销售订单读取权时才加载并展示销售订单卡片
if ($usercanread_so) {
	$object_so->fetch($so_rowid);
	if (!$object_so->id) {
		print '<p class="error">'.$langs->trans("NoRecordFound").'</p>';
		print '</form>';
		llxFooter();
		$db->close();
		exit(0);
	}
}

// 命中的主单据与销售订单对应关系提示
print '<p class="opacitymedium">'.$langs->trans("MatchedDocument").' : '.dol_escape_htmltag($searcher->getTypeLabel($search_type)).' '.dol_escape_htmltag($selected['primary_ref']);
if (!empty($selected['primary_ref2'])) {
	print ' ('.dol_escape_htmltag($selected['primary_ref2']).')';
}
if ($usercanread_so) {
	print ' &rarr; '.$langs->trans("RelatedSalesOrder").' '.dol_escape_htmltag($selected['so_ref']);
}
print '</p>';

// 查该 SO 关联的 PO（element_element）。
// 按 PO / 采购发票检索时，聚焦被检索到的那张 PO；其余情况沿用 SO 关联的第一张 PO。
if ($usercanread_po) {
	$po_ids = $searcher->getLinkedSupplierOrderIds($search_type, (int) $selected['primary_id']);
	if (empty($po_ids)) {
		$po_ids = $searcher->getLinkedSupplierOrderIds(SlycustomOrderSearch::TYPE_SO, $so_rowid);
	}
	$po_rowid = !empty($po_ids) ? (int) $po_ids[0] : 0;
	if ($po_rowid) {
		$object_po->fetch($po_rowid);
	}
} else {
	$po_rowid = 0;
}

// 客户
if ($usercanread_so && $object_so->socid) {
	$soc->fetch($object_so->socid);
}
if ($socid > 0 && !$soc->id) {
	$soc->fetch($socid);
}

// SO 联系人：订单/发票/Shipment（按类型取 libelle）
$so_contacts_order = $so_contacts_invoice = $so_contacts_ship = array();
if ($usercanread_so && method_exists($object_so, 'liste_contact')) {
	$all_contacts = $object_so->liste_contact(-1, 'external');
	if (is_array($all_contacts)) {
		foreach ($all_contacts as $c) {
			$name = trim(($c['firstname'] ?? '').' '.($c['lastname'] ?? ''));
			$code = $c['code'] ?? '';
			$lib = $c['libelle'] ?? $code;
			if (stripos($lib, 'order') !== false || stripos($code, 'COMMANDE') !== false || stripos($code, 'CUSTOMER') !== false) {
				$so_contacts_order[] = dol_escape_htmltag($name);
			} elseif (stripos($lib, 'invoice') !== false || stripos($lib, 'facture') !== false || stripos($code, 'BILL') !== false) {
				$so_contacts_invoice[] = dol_escape_htmltag($name);
			} elseif (stripos($lib, 'ship') !== false || stripos($lib, 'expedition') !== false || stripos($code, 'SHIP') !== false) {
				$so_contacts_ship[] = dol_escape_htmltag($name);
			}
		}
	}
}

// Shipment 及 ATA/ETD/ETA/ATD（expedition + expedition_extrafields）
// 批量读取，避免在循环内逐条 fetch / 查询
$shipments_data = array();
if ($usercanread_ship) {
	$ship_ids = $searcher->getShipmentIdsForOrder($so_rowid);
	if ($search_type == SlycustomOrderSearch::TYPE_SHIPMENT) {
		// 按 Shipment 检索时，被检索的发货单必须出现在列表中
		$tmp_shipid = (int) $selected['primary_id'];
		if ($tmp_shipid > 0 && !in_array($tmp_shipid, $ship_ids)) {
			$ship_ids[] = $tmp_shipid;
		}
	}
	$shipments_data = $searcher->fetchShipments($ship_ids);
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
if ($usercanread_so) {
	$soTitle = '<strong>SALES ORDERS ('.dol_escape_htmltag($object_so->ref).')</strong>';
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
	// 核心 fetchObjectLinked() 不按权限过滤，此处按用户读取权限剔除无权查看的类型
	$object_so->linkedObjects = SlycustomOrderSearch::filterLinkedObjectsForUser($object_so->linkedObjects, $user);
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
				$type_label = SlycustomOrderSearch::getLinkedTypeLabel($langs, $otype);
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
}

print '</div>'; // fichehalfleft

// ----- 右侧：先 Shipment（在采购订单上方），再采购订单 -----
print '<div class="fichehalfright"><div class="ficheaddleft">';

// ----- Shipment 主要信息：Ref、状态、ETD/ETA/ATD/ATA -----
if (!empty($shipments_data)) {
	$shipmentTitle = '<strong>SHIPMENTS ('.dol_escape_htmltag($selected['so_ref']).')</strong>';
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
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($s['ref']).'</td><td>'.($s['status_html'] !== '' ? $s['status_html'] : '-').'</td><td>'.$etd_printed.'</td><td>'.$eta_printed.'</td><td>'.$atd_printed.'</td><td>'.$ata_printed.'</td></tr>';
	}
	print '</table>';
	print '<br>';
}

// ----- 采购订单 -----
// 标题显示：系统 PO 号 + （可选）供应商单号，例如：PURCHASE ORDERS (PO260027 / XXX123)
if ($usercanread_po) {
	$poHeaderRef = dol_escape_htmltag($object_po->ref);
	if (!empty($object_po->ref_supplier)) {
		// ref_supplier 是表单可编辑的外部字段，必须转义（存储型 XSS）
		$poHeaderRef .= ' / '.dol_escape_htmltag($object_po->ref_supplier);
	}
	$poTitle = '<strong>PURCHASE ORDERS ('.$poHeaderRef.')</strong>';
	print load_fiche_titre($poTitle, '', '', 0, '', '');
}

if ($usercanread_po && !empty($object_po->id)) {
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
	// 核心 fetchObjectLinked() 不按权限过滤，此处按用户读取权限剔除无权查看的类型
	$object_po->linkedObjects = SlycustomOrderSearch::filterLinkedObjectsForUser($object_po->linkedObjects, $user);
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
				$type_label = SlycustomOrderSearch::getLinkedTypeLabel($langs, $otype);
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
} elseif ($usercanread_po) {
	print '<p class="opacitymedium">'.$langs->trans("NoLinkFound").'</p>';
}

print '</div></div></div>';
print '</form>';

llxFooter();
$db->close();
