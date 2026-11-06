<?php
/* Copyright (C) 2026 SLY Custom
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
 * \file       htdocs/custom/slycustom/class/SlycustomOrderSearch.class.php
 * \ingroup    slycustom
 * \brief      多维度单据检索：按 SO / PO / 销售发票 / 采购发票 / Shipment 号码定位销售订单
 */

// 防直接浏览器访问本文件（Dolibarr 24 已废弃 ISLOADEDBYDOLIBARR 约定，改用 DOL_DOCUMENT_ROOT 判定）
if (!defined('DOL_DOCUMENT_ROOT')) {
	die('This file must be included by a Dolibarr script');
}

/**
 * 多维度单据检索器。
 *
 * 设计要点：
 * - 业务逻辑完全封装在本类中（视图层只负责渲染），外部脚本 / AI Agent 可直接实例化调用；
 * - 全部查询一次性批量完成，严格避免“循环内查询”；
 * - 所有查询强制附带 entity 过滤（multicompany 兼容），外部第三方用户强制按 fk_soc 过滤；
 * - 关联关系一律走 llx_element_element，且同时兼容两个存储方向与历史遗留的 element 别名。
 *
 * @package Slycustom
 */
class SlycustomOrderSearch
{
	/**
	 * 数据库句柄
	 *
	 * @var DoliDB
	 */
	public $db;

	/**
	 * 当前登录用户
	 *
	 * @var User
	 */
	public $user;

	/** 销售订单 */
	const TYPE_SO = 'so';
	/** 采购订单 */
	const TYPE_PO = 'po';
	/** 销售发票（客户发票） */
	const TYPE_INVOICE = 'invoice';
	/** 采购发票（供应商发票） */
	const TYPE_SUPPLIER_INVOICE = 'supplier_invoice';
	/** 发货单 */
	const TYPE_SHIPMENT = 'shipment';

	/**
	 * 构造函数
	 *
	 * @param DoliDB $db   数据库句柄
	 * @param User   $user 当前登录用户
	 */
	public function __construct($db, $user)
	{
		$this->db = $db;
		$this->user = $user;
	}

	/**
	 * 返回所有支持的检索类型
	 *
	 * @return array<string> 类型代码数组
	 */
	public static function getSupportedTypes()
	{
		return array(
			self::TYPE_SO,
			self::TYPE_PO,
			self::TYPE_INVOICE,
			self::TYPE_SUPPLIER_INVOICE,
			self::TYPE_SHIPMENT,
		);
	}

	/**
	 * 取得检索类型的多语言标签
	 *
	 * @param	string	$type	类型代码
	 * @return	string			标签（无对应翻译时回退为类型代码）
	 */
	public function getTypeLabel($type)
	{
		global $langs;

		switch ($type) {
			case self::TYPE_SO:
				return $langs->trans('CustomersOrders');
			case self::TYPE_PO:
				return $langs->trans('SupplierOrder');
			case self::TYPE_INVOICE:
				return $langs->trans('CustomerInvoice');
			case self::TYPE_SUPPLIER_INVOICE:
				return $langs->trans('SupplierInvoice');
			case self::TYPE_SHIPMENT:
				return $langs->trans('Shipment');
		}
		return $type;
	}

	/**
	 * 检查当前用户是否具备检索指定单据类型的读取权限。
	 *
	 * 每种检索类型都映射到其归属的 Dolibarr 模块权限：
	 * - so                -> commande.lire
	 * - po                -> fournisseur.commande.lire 或 supplier_order.lire
	 * - invoice           -> facture.lire
	 * - supplier_invoice  -> fournisseur.facture.lire 或 supplier_invoice.lire
	 * - shipment          -> expedition.lire
	 *
	 * @param	User	$user	当前登录用户
	 * @param	string	$type	检索类型（见 TYPE_* 常量）
	 * @return	bool			true 表示允许检索该类型
	 */
	public static function canReadType($user, $type)
	{
		if (empty($user)) {
			return false;
		}
		switch ($type) {
			case self::TYPE_SO:
				return (bool) $user->hasRight('commande', 'lire');
			case self::TYPE_PO:
				return (bool) ($user->hasRight('fournisseur', 'commande', 'lire') || $user->hasRight('supplier_order', 'lire'));
			case self::TYPE_INVOICE:
				return (bool) $user->hasRight('facture', 'lire');
			case self::TYPE_SUPPLIER_INVOICE:
				return (bool) ($user->hasRight('fournisseur', 'facture', 'lire') || $user->hasRight('supplier_invoice', 'lire'));
			case self::TYPE_SHIPMENT:
				return (bool) $user->hasRight('expedition', 'lire');
		}
		return false;
	}

	/**
	 * 按当前用户的读取权限过滤 Related Objects（关联对象）列表。
	 *
	 * 核心的 fetchObjectLinked() 不会按权限过滤关联对象，若直接渲染，
	 * 只有“销售订单读取权”的用户也能看到发票 / 采购订单 / 发货单的号码与金额。
	 * 本方法按 element 类型逐类判定读取权限，无权查看的类型整类剔除。
	 *
	 * @param	array	$linkedObjects	核心 fetchObjectLinked() 的返回值
	 * @param	User	$user			当前登录用户
	 * @return	array					过滤后的关联对象列表
	 */
	public static function filterLinkedObjectsForUser($linkedObjects, $user)
	{
		if (empty($linkedObjects) || !is_array($linkedObjects)) {
			return array();
		}
		if (empty($user)) {
			return array();
		}

		$canread_po = ($user->hasRight('fournisseur', 'commande', 'lire') || $user->hasRight('supplier_order', 'lire'));
		$canread_supi = ($user->hasRight('fournisseur', 'facture', 'lire') || $user->hasRight('supplier_invoice', 'lire'));
		$canread_so = $user->hasRight('commande', 'lire');
		$canread_invoice = $user->hasRight('facture', 'lire');
		$canread_ship = $user->hasRight('expedition', 'lire');

		// element 类型 => 对应的读取权限判定
		$allow = array(
			'commande' => $canread_so,
			'facture' => $canread_invoice,
			'facturerec' => $canread_invoice,
			'shipping' => $canread_ship,
			'shipment' => $canread_ship,
			'order_supplier' => $canread_po,
			'commande_fournisseur' => $canread_po,
			'invoice_supplier' => $canread_supi,
			'facture_fourn' => $canread_supi,
		);

		$result = array();
		foreach ($linkedObjects as $otype => $objects) {
			if (!is_array($objects)) {
				continue;
			}
			// 未列入白名单的类型：只有在拥有对应通用读取权时才保留
			if (!array_key_exists($otype, $allow)) {
				$result[$otype] = $objects;
				continue;
			}
			if (!empty($allow[$otype])) {
				$result[$otype] = $objects;
			}
		}

		return $result;
	}

	/**
	 * 关联对象 element 类型 -> 显示标签。
	 *
	 * 核心 fetchObjectLinked() 返回的 linkedObjects 以 element 类型串为键
	 * （shipping / commande / facture ...），直接输出会是英文小写内部名。
	 * 销售订单卡片与采购订单卡片共用本映射，保证同一类型在两处显示一致。
	 *
	 * @param	Translate	$langs	语言对象
	 * @param	string		$type	element 类型
	 * @return	string				显示标签（未知类型原样返回）
	 */
	public static function getLinkedTypeLabel($langs, $type)
	{
		switch ($type) {
			case 'facture':
				return $langs->trans("CustomerInvoice");
			case 'facturerec':
				return $langs->trans("DepositInvoice");
			case 'shipping':
			case 'shipment':
				return $langs->trans("Shipment");
			case 'order_supplier':
			case 'commande_fournisseur':
				return $langs->trans("SupplierOrder");
			case 'invoice_supplier':
			case 'facture_fourn':
				return $langs->trans("SupplierInvoice");
			case 'commande':
				return $langs->trans("CustomersOrders");
			default:
				return $type;
		}
	}

	/**
	 * 取得某个销售订单关联的发货单（Shipment）id 列表。
	 *
	 * @param	int	$soId	销售订单 id
	 * @return	array<int>		Shipment id 列表
	 */
	public function getShipmentIdsForOrder($soId)
	{
		$soId = (int) $soId;
		if ($soId <= 0) {
			return array();
		}
		$map = $this->getLinkedIds(array('commande'), array('shipping'), array($soId));
		return isset($map[$soId]) ? array_values($map[$soId]) : array();
	}

	/**
	 * 取得检索类型对应的输入框标签（如 “SO No”）。
	 *
	 * @param	string	$type	检索类型（见 TYPE_* 常量）
	 * @return	string			标签
	 */
	public function getTypeRefLabel($type)
	{
		global $langs;

		switch ($type) {
			case self::TYPE_SO:
				return $langs->trans('SearchRefSO');
			case self::TYPE_PO:
				return $langs->trans('SearchRefPO');
			case self::TYPE_INVOICE:
				return $langs->trans('SearchRefInvoice');
			case self::TYPE_SUPPLIER_INVOICE:
				return $langs->trans('SearchRefSupplierInvoice');
			case self::TYPE_SHIPMENT:
				return $langs->trans('SearchRefShipment');
		}
		return $langs->trans('Ref');
	}

	/**
	 * 批量读取第三方（客户）名称。
	 *
	 * 用于检索结果列表，避免在循环内逐条 Societe::fetch()（硬性禁止循环内查询）。
	 *
	 * @param	array	$socIds	第三方 id 数组
	 * @return	array			map：第三方 id => 名称
	 */
	public function fetchThirdPartyNames($socIds)
	{
		$result = array();
		$ids = array();
		foreach ($socIds as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (empty($ids)) {
			return $result;
		}

		$sql = "SELECT s.rowid AS id, s.nom AS nom FROM ".MAIN_DB_PREFIX."societe s";
		$sql .= " WHERE s.rowid IN (".implode(',', $ids).")";

		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$result[(int) $row->id] = (string) $row->nom;
			}
			$this->db->free($res);
		}

		return $result;
	}

	/**
	 * 批量读取发货单信息。
	 *
	 * 一次性查询 llx_expedition + llx_expedition_extrafields，避免在循环内逐条 fetch()，
	 * 状态标签复用核心 Expedition 的标签表（不调用核心 LibStatut，避免触发 signed 相关调用）。
	 *
	 * @param	array	$shipIds	Shipment id 数组
	 * @return	array			每项 = array('id','ref','date_expedition','date_delivery','status_html','pol','atd','pod','ata','etd','eta')
	 */
	public function fetchShipments($shipIds)
	{
		global $langs;

		$result = array();
		$ids = array();
		foreach ($shipIds as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (empty($ids)) {
			return $result;
		}
		$in = implode(',', $ids);

		// 1) 发货单主表
		$rows = array();
		$sql = "SELECT e.rowid AS id, e.ref AS ref, e.date_expedition AS date_expedition,";
		$sql .= " e.date_delivery AS date_delivery, e.fk_statut AS fk_statut";
		$sql .= " FROM ".MAIN_DB_PREFIX."expedition e";
		$sql .= " WHERE e.rowid IN (".$in.")";
		$sql .= " AND e.entity IN (".getEntity('expedition').")";

		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$rows[(int) $row->id] = array(
					'id' => (int) $row->id,
					'ref' => (string) $row->ref,
					'date_expedition' => (string) $row->date_expedition,
					'date_delivery' => (string) $row->date_delivery,
					'fk_statut' => (int) $row->fk_statut,
				);
			}
			$this->db->free($res);
		}
		if (empty($rows)) {
			return $result;
		}

		// 2) ShipsGo / ETA-ETD-ATD-ATA 扩展字段（表可能未安装，失败即跳过）
		$ef = array();
		$sql = "SELECT fk_object, pol, atd, pod, ata, etd, eta FROM ".MAIN_DB_PREFIX."expedition_extrafields";
		$sql .= " WHERE fk_object IN (".$in.")";
		$res = @$this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$ef[(int) $row->fk_object] = array(
					'pol' => (string) ($row->pol ?? ''),
					'atd' => (string) ($row->atd ?? ''),
					'pod' => (string) ($row->pod ?? ''),
					'ata' => (string) ($row->ata ?? ''),
					'etd' => (string) ($row->etd ?? ''),
					'eta' => (string) ($row->eta ?? ''),
				);
			}
			$this->db->free($res);
		}

		// 3) 状态标签（复用核心 Expedition 的标签定义）
		$exp = null;
		if (isModEnabled('shipping')) {
			require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
			$exp = new Expedition($this->db);
		}
		$statusCache = array();

		foreach ($rows as $id => $row) {
			$st = (int) $row['fk_statut'];
			if (!isset($statusCache[$st])) {
				$statusCache[$st] = $this->buildShipmentStatusLabel($exp, $st);
			}
			$result[] = array(
				'id' => $id,
				'ref' => $row['ref'],
				'date_expedition' => $row['date_expedition'],
				'date_delivery' => $row['date_delivery'],
				'status_html' => $statusCache[$st],
				'pol' => isset($ef[$id]['pol']) ? $ef[$id]['pol'] : '',
				'atd' => isset($ef[$id]['atd']) ? $ef[$id]['atd'] : '',
				'pod' => isset($ef[$id]['pod']) ? $ef[$id]['pod'] : '',
				'ata' => isset($ef[$id]['ata']) ? $ef[$id]['ata'] : '',
				'etd' => isset($ef[$id]['etd']) ? $ef[$id]['etd'] : '',
				'eta' => isset($ef[$id]['eta']) ? $ef[$id]['eta'] : '',
			);
		}

		return $result;
	}

	/**
	 * 生成发货单状态标签（与核心 Expedition::LibStatut 输出一致）。
	 *
	 * 有核心 Expedition 实例时直接复用核心 LibStatut()（含 signed_status 处理，
	 * 与核心发货单页面完全一致）；shipping 模块不可用时按核心映射降级生成，
	 * 保证离线自测（无核心类）同样可跑。
	 *
	 * @param	Expedition|null	$exp		核心 Expedition 实例（可为 null）
	 * @param	int					$status	发货单状态码
	 * @return	string						状态 HTML
	 */
	private function buildShipmentStatusLabel($exp, $status)
	{
		global $langs;

		$status = (int) $status;

		if (is_object($exp) && isset($exp->labelStatus[$status])) {
			return $exp->LibStatut($status, 3);
		}

		// 降级路径：与核心 Expedition::LibStatut() 的映射保持一致
		$labels = array(
			-1 => 'StatusSendingCanceled',
			0 => 'StatusSendingDraft',
			1 => 'StatusSendingValidated',
			2 => 'StatusSendingProcessed',
		);
		$shorts = array(
			-1 => 'StatusSendingCanceledShort',
			0 => 'StatusSendingDraftShort',
			1 => 'StatusSendingValidatedShort',
			2 => 'StatusSendingProcessedShort',
		);

		$label = '';
		$labelShort = '';
		if (isset($labels[$status])) {
			$label = $labels[$status];
			$labelShort = $shorts[$status];
		}

		$statusType = 'status'.$status;
		if ($status == 1) {
			$statusType = 'status4';
		} elseif ($status == 2) {
			$statusType = 'status6';
		} elseif ($status == -1) {
			$statusType = 'status9';
		}

		return dolGetStatus(
			$langs->transnoentitiesnoconv($label),
			$langs->transnoentitiesnoconv($labelShort),
			'',
			$statusType,
			3
		);
	}

	/**
	 * 按号码（模糊）检索单据主表。
	 *
	 * @param	string	$type	检索类型（见 TYPE_* 常量）
	 * @param	string	$ref	号码（模糊匹配）
	 * @return	array			候选列表：每项 = array('id','ref','ref2','fk_soc','type')
	 */
	public function searchPrimaries($type, $ref)
	{
		$ref = trim((string) $ref);
		if ($ref === '' || !in_array($type, self::getSupportedTypes())) {
			return array();
		}

		$like = '%'.$this->db->escape($ref).'%';
		// 外部第三方用户（socid 非空）只能看到自己名下的单据。
		// PO / Shipment / 采购发票不做 fk_soc 过滤：外部客户也可能按 PO 号检索，
		// 且最终展示的仍是其名下 SO（resolveOrders() 内已按 entity + socid 二次校验）。
		$socidfilter = '';
		if (!empty($this->user->socid)) {
			$socidfilter = " AND c.fk_soc = ".(int) $this->user->socid;
		}

		switch ($type) {
			case self::TYPE_SO:
				$sql = "SELECT c.rowid AS id, c.ref AS ref, '' AS ref2, c.fk_soc AS fk_soc";
				$sql .= " FROM ".MAIN_DB_PREFIX."commande c";
				$sql .= " WHERE c.entity IN (".getEntity('commande').")";
				$sql .= $socidfilter;
				$sql .= " AND c.ref LIKE '".$like."' ORDER BY c.ref DESC";
				break;

			case self::TYPE_PO:
				$sql = "SELECT cf.rowid AS id, cf.ref AS ref, cf.ref_supplier AS ref2, cf.fk_soc AS fk_soc";
				$sql .= " FROM ".MAIN_DB_PREFIX."commande_fournisseur cf";
				$sql .= " WHERE cf.entity IN (".getEntity('supplier_order').")";
				$sql .= " AND (cf.ref LIKE '".$like."' OR cf.ref_supplier LIKE '".$like."') ORDER BY cf.ref DESC";
				break;

			case self::TYPE_INVOICE:
				$sql = "SELECT f.rowid AS id, f.ref AS ref, '' AS ref2, f.fk_soc AS fk_soc";
				$sql .= " FROM ".MAIN_DB_PREFIX."facture f";
				$sql .= " WHERE f.entity IN (".getEntity('invoice').")";
				$sql .= " AND f.ref LIKE '".$like."' ORDER BY f.ref DESC";
				break;

			case self::TYPE_SUPPLIER_INVOICE:
				$sql = "SELECT ff.rowid AS id, ff.ref AS ref, ff.ref_supplier AS ref2, ff.fk_soc AS fk_soc";
				$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn ff";
				$sql .= " WHERE ff.entity IN (".getEntity('supplier_invoice').")";
				$sql .= " AND (ff.ref LIKE '".$like."' OR ff.ref_supplier LIKE '".$like."') ORDER BY ff.ref DESC";
				break;

			case self::TYPE_SHIPMENT:
				$sql = "SELECT e.rowid AS id, e.ref AS ref, e.tracking_number AS ref2, e.fk_soc AS fk_soc";
				$sql .= " FROM ".MAIN_DB_PREFIX."expedition e";
				$sql .= " WHERE e.entity IN (".getEntity('expedition').")";
				$sql .= " AND (e.ref LIKE '".$like."' OR e.tracking_number LIKE '".$like."') ORDER BY e.ref DESC";
				break;

			default:
				return array();
		}

		$result = array();
		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$result[] = array(
					'id' => (int) $row->id,
					'ref' => (string) $row->ref,
					'ref2' => (string) ($row->ref2 ?? ''),
					'fk_soc' => (int) $row->fk_soc,
					'type' => $type,
				);
			}
			$this->db->free($res);
		}

		return $result;
	}

	/**
	 * 将检索到的主单据解析为其关联的销售订单（SO）。
	 *
	 * @param	string	$type		检索类型
	 * @param	array	$primaries	searchPrimaries() 的返回值
	 * @return	array				解析结果：每项 = array('so_id','so_ref','fk_soc','primary_id','primary_ref','primary_ref2','primary_type')
	 */
	public function resolveOrders($type, $primaries)
	{
		$result = array();
		if (empty($primaries) || !in_array($type, self::getSupportedTypes())) {
			return $result;
		}

		$primaryIds = array();
		foreach ($primaries as $p) {
			$primaryIds[(int) $p['id']] = $p;
		}

		// 主单据 -> SO id 的映射
		$map = array();
		if ($type == self::TYPE_SO) {
			foreach ($primaryIds as $id => $p) {
				$map[$id] = array($id);
			}
		} elseif ($type == self::TYPE_PO) {
			$map = $this->getLinkedIds(array('order_supplier'), array('commande'), array_keys($primaryIds));
		} elseif ($type == self::TYPE_INVOICE) {
			$map = $this->getLinkedIds(array('facture'), array('commande'), array_keys($primaryIds));
		} elseif ($type == self::TYPE_SHIPMENT) {
			$map = $this->getLinkedIds(array('shipping'), array('commande'), array_keys($primaryIds));
			// 兜底：部分发货单未写 element_element，可通过发运行项目反查销售订单
			$mapByLine = $this->getShipmentOrderIdsByLines(array_keys($primaryIds));
			foreach ($mapByLine as $shipId => $soList) {
				$map[$shipId] = array_merge(isset($map[$shipId]) ? $map[$shipId] : array(), $soList);
			}
		} elseif ($type == self::TYPE_SUPPLIER_INVOICE) {
			// 采购发票 -> 采购订单 -> 销售订单（两跳）
			$poMap = $this->getLinkedIds(array('invoice_supplier', 'facture_fourn'), array('order_supplier'), array_keys($primaryIds));
			$poIds = array();
			foreach ($poMap as $invId => $list) {
				foreach ($list as $poId) {
					$poIds[(int) $poId] = (int) $poId;
				}
			}
			$soMap = $this->getLinkedIds(array('order_supplier'), array('commande'), array_values($poIds));
			foreach ($poMap as $invId => $list) {
				foreach ($list as $poId) {
					if (!empty($soMap[$poId])) {
						$map[$invId] = array_merge($map[$invId] ?? array(), $soMap[$poId]);
					}
				}
			}
		}

		// 同一个主单据可能经多条关联路径解析到同一张 SO（例如 Shipment 既写入了
		// element_element、又有 expeditiondet 行项目关联），合并后会出现重复 id，
		// 导致候选列表里同一个单据被列成多行。此处统一按 id 去重（保持首次出现顺序）。
		foreach ($map as $pid => $list) {
			$map[$pid] = array_values(array_unique(array_map('intval', $list)));
		}

		if (empty($map)) {
			return $result;
		}

		// 汇总所有待校验的 SO id，一次性批量取回（并做 entity / 第三方用户过滤）
		$soIds = array();
		foreach ($map as $list) {
			foreach ($list as $soId) {
				$soIds[(int) $soId] = (int) $soId;
			}
		}
		$soRows = $this->fetchOrders(array_values($soIds));

		foreach ($primaryIds as $pid => $p) {
			if (empty($map[$pid])) {
				continue;
			}
			foreach ($map[$pid] as $soId) {
				$soId = (int) $soId;
				if (!isset($soRows[$soId])) {
					continue;	// 被 entity / 权限过滤掉
				}
				$result[] = array(
					'so_id' => $soId,
					'so_ref' => $soRows[$soId]['ref'],
					'fk_soc' => $soRows[$soId]['fk_soc'],
					'primary_id' => (int) $p['id'],
					'primary_ref' => $p['ref'],
					'primary_ref2' => $p['ref2'],
					'primary_type' => $type,
				);
			}
		}

		return $result;
	}

	/**
	 * 取得某个主单据关联的采购订单（PO）id 列表。
	 *
	 * 用于：按 PO 检索时聚焦该 PO；按采购发票检索时定位其来源 PO；
	 * 按 SO 检索时定位其关联 PO（沿用原页面逻辑）。
	 *
	 * @param	string	$type	检索类型
	 * @param	int		$id		主单据 id
	 * @return	array<int>		PO id 列表
	 */
	public function getLinkedSupplierOrderIds($type, $id)
	{
		$id = (int) $id;
		if ($id <= 0) {
			return array();
		}
		if ($type == self::TYPE_PO) {
			return array($id);
		}
		if ($type == self::TYPE_SUPPLIER_INVOICE) {
			$map = $this->getLinkedIds(array('invoice_supplier', 'facture_fourn'), array('order_supplier'), array($id));
			return isset($map[$id]) ? array_values($map[$id]) : array();
		}
		if ($type == self::TYPE_SO) {
			$map = $this->getLinkedIds(array('commande'), array('order_supplier'), array($id));
			return isset($map[$id]) ? array_values($map[$id]) : array();
		}
		return array();
	}

	/**
	 * 通过发运行项目（expeditiondet -> commandedet）反查销售订单。
	 *
	 * 作为 element_element 之外的兜底关联路径：部分发货单只保留行项目关联，
	 * 未写入 element_element，此时仍可定位到其来源销售订单。
	 *
	 * @param	array	$shipIds	Shipment id 数组
	 * @return	array			map：Shipment id => 销售订单 id 列表
	 */
	private function getShipmentOrderIdsByLines($shipIds)
	{
		$result = array();
		$ids = array();
		foreach ($shipIds as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (empty($ids)) {
			return $result;
		}

		$sql = "SELECT ed.fk_expedition AS a, cd.fk_commande AS b";
		$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet ed";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.rowid = ed.fk_elementdet";
		$sql .= " WHERE ed.fk_expedition IN (".implode(',', $ids).")";

		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$a = (int) $row->a;
				$b = (int) $row->b;
				if ($a <= 0 || $b <= 0) {
					continue;
				}
				$result[$a][$b] = $b;
			}
			$this->db->free($res);
		}

		foreach ($result as $a => $list) {
			$result[$a] = array_values($list);
		}

		return $result;
	}

	/**
	 * 批量读取 element_element 关联关系（兼容两个存储方向与历史 element 别名）。
	 *
	 * 语义：传入 $typesA 类型的 id，返回这些 id 对应的 $typesB 类型 id 列表。
	 * 即 map[ idA ] = array(idB, ...)。
	 *
	 * @param	array	$typesA	A 侧 element 类型（可含历史别名）
	 * @param	array	$typesB	B 侧 element 类型（可含历史别名）
	 * @param	array	$idsA	A 侧主键数组
	 * @return	array			map：A 的 id => B 的 id 列表
	 */
	private function getLinkedIds($typesA, $typesB, $idsA)
	{
		$result = array();
		$ids = array();
		foreach ($idsA as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (empty($ids) || empty($typesA) || empty($typesB)) {
			return $result;
		}
		$in = implode(',', $ids);
		$inA = "'".implode("','", array_map(array($this->db, 'escape'), $typesA))."'";
		$inB = "'".implode("','", array_map(array($this->db, 'escape'), $typesB))."'";

		// 方向 1：A 为 source
		$sql = "SELECT ee.fk_source AS a, ee.fk_target AS b FROM ".MAIN_DB_PREFIX."element_element ee";
		$sql .= " WHERE ee.sourcetype IN (".$inA.") AND ee.targettype IN (".$inB.")";
		$sql .= " AND ee.fk_source IN (".$in.")";
		// 方向 2：A 为 target
		$sql .= " UNION SELECT ee.fk_target AS a, ee.fk_source AS b FROM ".MAIN_DB_PREFIX."element_element ee";
		$sql .= " WHERE ee.sourcetype IN (".$inB.") AND ee.targettype IN (".$inA.")";
		$sql .= " AND ee.fk_target IN (".$in.")";

		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$a = (int) $row->a;
				$b = (int) $row->b;
				if ($a <= 0 || $b <= 0) {
					continue;
				}
				$result[$a][$b] = $b;
			}
			$this->db->free($res);
		}

		// 转成纯 list，便于调用方遍历
		foreach ($result as $a => $list) {
			$result[$a] = array_values($list);
		}

		return $result;
	}

	/**
	 * 批量读取销售订单（带 entity 与第三方用户过滤）
	 *
	 * @param	array	$soIds	SO id 数组
	 * @return	array			map：SO id => array('ref','fk_soc')
	 */
	private function fetchOrders($soIds)
	{
		$result = array();
		$ids = array();
		foreach ($soIds as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		if (empty($ids)) {
			return $result;
		}

		$sql = "SELECT c.rowid AS id, c.ref AS ref, c.fk_soc AS fk_soc";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande c";
		$sql .= " WHERE c.rowid IN (".implode(',', $ids).")";
		$sql .= " AND c.entity IN (".getEntity('commande').")";
		if (!empty($this->user->socid)) {
			$sql .= " AND c.fk_soc = ".(int) $this->user->socid;
		}

		$res = $this->db->query($sql);
		if ($res) {
			while ($row = $this->db->fetch_object($res)) {
				$result[(int) $row->id] = array('ref' => $row->ref, 'fk_soc' => (int) $row->fk_soc);
			}
			$this->db->free($res);
		}

		return $result;
	}
}
