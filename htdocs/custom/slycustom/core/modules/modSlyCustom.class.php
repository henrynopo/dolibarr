<?php
/* Copyright (C) 2025  SLY Custom
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
 *	\defgroup   slycustom     Module SLY Custom
 *	\brief      SLY customisations: PDFs, ShipsGo, exports, Search Order, terms/hooks for Dolibarr 14.0 / 22.0
 *	\file       htdocs/custom/slycustom/core/modules/modSlyCustom.class.php
 *	\ingroup    slycustom
 *	\brief      Description and activation file for the module SLY Custom
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *	Description and activation class for module SLY Custom
 */
class modSlyCustom extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		// Module unique id (see https://wiki.dolibarr.org/index.php/List_of_modules_id)
		$this->numero = 500100;
		$this->rights_class = 'slycustom';
		$this->family = "other";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleSlyCustomDesc';
		$this->descriptionlong = 'SLYCustomDescriptionLong';
		$this->editor_name = 'SLY';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';

		// Module parts: models (PDF) so that SLY templates are found in core/modules/*/doc under custom/slycustom
		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 1,
			'css' => array(),
			'js' => array(),
			'hooks' => array(
				'data' => array(
					'invoicecard', 'invoicelist',
					'invoiceindex',  // 财务首页：多币种 invoiceIndexSelectSuffix / invoiceIndexAmountDisplay
					'ordercard', 'orderlist',
					'productcard',  // 产品卡片：加载 slycustom 以覆盖 CustomsCode/CustomCode → Plant No./厂号
					'ordersindex',  // 订单首页：多币种列 ordersIndexSelectSuffix / ordersIndexRowAmount
					// 以下页面会 new HookManager 且只 init 单一 context；若不声明则 ActionsSlycustom 不会加载，menuLeftMenuItems 从不执行
					'commercialindex',  // comm/index.php — Commerce 顶栏首页须立刻显示 Shipment 左侧菜单
					'productindex',  // product/index.php — Products 首页须立刻从产品侧栏移除 Shipment
					'productservicelist',  // product/list.php — 产品/服务列表页同样须移除 Shipment
					'expeditioncard', 'shipmentlist', 'ordershipmentcard',
					'paymentlist', 'paymentcard',
					'supplierinvoicelist', 'supplierorderlist',
					'paymentsupplierlist', 'propallist',
					'ordersuppliercard', 'invoicesuppliercard',
					'remx',  // 折扣拆分页：afterSplitDiscount（需应用 sly22.0-remx-hooks.patch）
					'menuLeftMenuItems',  // 左侧菜单：将 Shipment 从产品目录移到商业目录
					'formfile',  // 销售订单生成文档表单：增加「附加销售条款」选项
				),
				'entity' => '0',
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array("/custom/slycustom/temp");
		$this->config_page_url = array("setup.php@slycustom");
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(5, 6);
		$this->need_dolibarr_version = array(14, 0); // 14.0+ (含 22.0)
		$this->langfiles = array("slycustom@slycustom");
		$this->warnings_activation = array();
		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		// SLY Phase 3: replacement boxes (zero core patch) - multicurrency boxes removed; core boxes now have multicurrency
		$this->boxes = array(
			0 => array('file' => 'box_sly_lastactions.php@slycustom', 'note' => 'SLY', 'enabledbydefaulton' => ''),
			1 => array('file' => 'box_sly_birthdays.php@slycustom', 'note' => 'SLY', 'enabledbydefaulton' => ''),
		);

		// Cron: ShipsGo status update (kept as-is from the original 1-hour schedule).
		// Webhook is the primary path; this cron still runs hourly as a safety net.
		$this->cronjobs = array(
			0 => array(
				'entity' => 0,
				'label' => 'ShipsGo update shipment status',
				'jobtype' => 'method',
				'class' => 'custom/slycustom/class/ShipsGo_Update.class.php',
				'objectname' => 'ShipmentStatus',
				'method' => 'updateships',
				'parameters' => '20',
				'comment' => 'SLY ShipsGo shipment status sync',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => '$conf->slycustom->enabled',
				'priority' => 50,
			),
		);

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = '使用 SLY 导出与报表';
		$this->rights[$r][4] = 'export';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->menu = array();
		$r = 0;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=home,fk_leftmenu=commercial',
			'type' => 'left',
			'titre' => 'SLY Exports',
			'prefix' => img_picto('', 'list', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'home',
			'leftmenu' => 'slycustom_exports',
			'url' => '/custom/slycustom/exports/index.php',
			'langs' => 'slycustom@slycustom',
			'position' => 100,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		// Tools → SLY Export → (SLY Invoices | SLY Payments planning)
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'SLYExportMenu',
			'prefix' => img_picto('', 'list', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export',
			'url' => '/custom/slycustom/exports/export_all.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 400,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export',
			'type' => 'left',
			'titre' => 'SLYExportMenuInvoices',
			'prefix' => img_picto('', 'list', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_all.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 401,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_invoices',
			'type' => 'left',
			'titre' => 'SLYExportAllInOne',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_all.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 402,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_invoices',
			'type' => 'left',
			'titre' => 'SLYExportSODetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_SO_Details.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 403,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_invoices',
			'type' => 'left',
			'titre' => 'SLYExportSOInvoiceDetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_SO_Inv_Details.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 404,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_invoices',
			'type' => 'left',
			'titre' => 'SLYExportShipmentDetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_Shipment_Details.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 405,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_invoices',
			'type' => 'left',
			'titre' => 'SLYExportPODetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_PO_Details.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 406,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_invoices',
			'type' => 'left',
			'titre' => 'SLYExportPOInvoiceDetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_invoices',
			'url' => '/custom/slycustom/exports/export_PO_Inv_Details.php?mainmenu=tools&leftmenu=sly_export_invoices',
			'langs' => 'slycustom@slycustom',
			'position' => 407,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export',
			'type' => 'left',
			'titre' => 'SLYExportMenuPaymentsPlanning',
			'prefix' => img_picto('', 'bill', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_cashflow',
			'url' => '/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_cashflow&tab=so_inv_receivable',
			'langs' => 'slycustom@slycustom',
			'position' => 410,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_cashflow',
			'type' => 'left',
			'titre' => 'SLYExportSOInvoiceReceivable',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_cashflow',
			'url' => '/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_cashflow&tab=so_inv_receivable',
			'langs' => 'slycustom@slycustom',
			'position' => 411,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_cashflow',
			'type' => 'left',
			'titre' => 'SLYExportPOInvoicePayable',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_cashflow',
			'url' => '/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_cashflow&tab=po_inv_payable',
			'langs' => 'slycustom@slycustom',
			'position' => 412,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_cashflow',
			'type' => 'left',
			'titre' => 'SLYExportSODepositInvoiceMissing',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_cashflow',
			'url' => '/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_cashflow&tab=so_deposit_invoice_missing',
			'langs' => 'slycustom@slycustom',
			'position' => 413,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_cashflow',
			'type' => 'left',
			'titre' => 'SLYExportPODepositInvoiceMissing',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_cashflow',
			'url' => '/custom/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export_cashflow&tab=po_deposit_invoice_missing',
			'langs' => 'slycustom@slycustom',
			'position' => 414,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		// Search Order：放在「Tools」目录下，对应 custom/slycustom/orderstatus.php（SLY Search Order 页，完全在模块内）
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'SearchOrder',
			'prefix' => img_picto('', 'search', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'orderstatus',
			'url' => '/custom/slycustom/orderstatus.php?mainmenu=tools&leftmenu=orderstatus',
			'langs' => 'slycustom@slycustom',
			'position' => 500,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '($user->rights->fournisseur->commande->lire || $user->rights->supplier_order->lire)',
			'target' => '',
			'user' => 2,
		);
		$r++;

		if (!isset($conf->slycustom) || !isset($conf->slycustom->enabled)) {
			$conf->slycustom = new stdClass();
			$conf->slycustom->enabled = 0;
		}
	}

	/**
	 * Ensure SLY PDF templates (sly_invoice, sly_packinglist, sly_debitnote) exist in document_model
	 * so they appear in "默认 PDF 模板" dropdowns. Call from init() or from setup when module is enabled.
	 *
	 * @return int 0 on success, <0 on error
	 */
	public function syncDocumentModels()
	{
		global $conf;

		$entities = array(0, (int) $conf->entity);
		$entities = array_unique($entities);

		foreach ($entities as $entity) {
			$sql = "SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_invoice' AND type = 'invoice' AND entity = ".((int) $entity);
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) == 0) {
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('sly_invoice', 'invoice', ".((int) $entity).")";
				if ($this->db->query($sql) === false) {
					return -1;
				}
			}
			$sql = "SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_packinglist' AND type = 'shipping' AND entity = ".((int) $entity);
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) == 0) {
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('sly_packinglist', 'shipping', ".((int) $entity).")";
				if ($this->db->query($sql) === false) {
					return -1;
				}
			}
			$sql = "SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_debitnote' AND type = 'invoice_supplier' AND entity = ".((int) $entity);
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) == 0) {
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('sly_debitnote', 'invoice_supplier', ".((int) $entity).")";
				if ($this->db->query($sql) === false) {
					return -1;
				}
			}
			$sql = "SELECT 1 FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'cornas_SLY' AND type = 'order_supplier' AND entity = ".((int) $entity);
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) == 0) {
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('cornas_SLY', 'order_supplier', ".((int) $entity).")";
				if ($this->db->query($sql) === false) {
					return -1;
				}
			}
		}
		return 0;
	}

	/**
	 * Ensure MAIN_MODULE_SLYCUSTOM_MODELS is set so that Vendor/Supplier Invoice (and other)
	 * setup pages scan slycustom core module doc dirs and list SLY templates (e.g. sly_debitnote).
	 * Call from setup when module is enabled; run once if the template list is empty in settings.
	 *
	 * @return int 0 on success, <0 on error
	 */
	public function syncModulePartsModels()
	{
		global $conf;

		$constname = $this->const_name.'_MODELS';
		$entities = array(0, (int) $conf->entity);
		$entities = array_unique($entities);

		foreach ($entities as $entity) {
			if (dolibarr_set_const($this->db, $constname, '1', 'chaine', 0, '', $entity) < 0) {
				return -1;
			}
		}
		return 0;
	}

	/**
	 * Function called when module is enabled.
	 * Registers SLY PDF templates in document_model so they appear in Setup and in dropdowns.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf;

		$this->syncModulePartsModels();

		$sql = array(
			// Migrate old template names so existing config keeps working
			"UPDATE ".MAIN_DB_PREFIX."const SET value = 'sly_invoice' WHERE name = 'FACTURE_ADDON_PDF' AND value = 'sponge_SLY_consignee'",
			"UPDATE ".MAIN_DB_PREFIX."const SET value = 'sly_invoice' WHERE name = 'FACTURE_ADDON_PDF' AND value = 'sponge SLY consignee'",
			"UPDATE ".MAIN_DB_PREFIX."const SET value = 'sly_packinglist' WHERE name = 'EXPEDITION_ADDON_PDF' AND value = 'espadon_SLY_PL'",
			// Migrate invoice model_pdf: old SLY consignee template was removed, use sly_invoice
			"UPDATE ".MAIN_DB_PREFIX."facture SET model_pdf = 'sly_invoice' WHERE model_pdf = 'sponge_SLY_consignee'",
			"UPDATE ".MAIN_DB_PREFIX."facture SET model_pdf = 'sly_invoice' WHERE model_pdf = 'sponge SLY consignee'",
			// Register SLY templates in document_model
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_invoice' AND type = 'invoice' AND entity = ".((int) $conf->entity),
			"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('sly_invoice', 'invoice', ".((int) $conf->entity).")",
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_packinglist' AND type = 'shipping' AND entity = ".((int) $conf->entity),
			"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('sly_packinglist', 'shipping', ".((int) $conf->entity).")",
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_debitnote' AND type = 'invoice_supplier' AND entity = ".((int) $conf->entity),
			"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('sly_debitnote', 'invoice_supplier', ".((int) $conf->entity).")",
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'cornas_SLY' AND type = 'order_supplier' AND entity = ".((int) $conf->entity),
			"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('cornas_SLY', 'order_supplier', ".((int) $conf->entity).")",
		);

		$result = $this->_init($sql, $options);
		if ($result) {
			$this->syncMenuPrefixes();
		}
		return $result;
	}

	/**
	 * Update menu entry prefixes (icons) in llx_menu for SLY Custom left menu items.
	 * Menus are loaded from DB, so existing installs have old/empty prefix until we run this.
	 *
	 * @return int 0 on success, <0 on error
	 */
	public function syncMenuPrefixes()
	{
		global $conf;

		$prefixSearch = $this->db->escape(img_picto('', 'search', 'class="paddingright pictofixedwidth valignmiddle"'));
		$prefixList = $this->db->escape(img_picto('', 'list', 'class="paddingright pictofixedwidth valignmiddle"'));

		$entities = array(0, (int) $conf->entity);
		$entities = array_unique($entities);
		$module = $this->db->escape('slycustom');

		foreach ($entities as $entity) {
			$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET prefix = '".$prefixSearch."'";
			$sql .= " WHERE module = '".$module."' AND mainmenu = 'tools' AND leftmenu = 'orderstatus' AND entity = ".(int) $entity;
			if ($this->db->query($sql) === false) {
				return -1;
			}
			$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET prefix = '".$prefixList."'";
			$sql .= " WHERE module = '".$module."' AND mainmenu = 'home' AND leftmenu = 'slycustom_exports' AND entity = ".(int) $entity;
			if ($this->db->query($sql) === false) {
				return -1;
			}
			$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET prefix = '".$prefixList."'";
			$sql .= " WHERE module = '".$module."' AND mainmenu = 'tools' AND leftmenu = 'sly_export' AND position = 400 AND entity = ".(int) $entity;
			if ($this->db->query($sql) === false) {
				return -1;
			}
			$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET prefix = '".$prefixList."'";
			$sql .= " WHERE module = '".$module."' AND mainmenu = 'tools' AND leftmenu = 'sly_export_invoices' AND position = 401 AND entity = ".(int) $entity;
			if ($this->db->query($sql) === false) {
				return -1;
			}
			$prefixBill = $this->db->escape(img_picto('', 'bill', 'class="paddingright pictofixedwidth valignmiddle"'));
			$sql = "UPDATE ".MAIN_DB_PREFIX."menu SET prefix = '".$prefixBill."'";
			$sql .= " WHERE module = '".$module."' AND mainmenu = 'tools' AND leftmenu = 'sly_export_cashflow' AND position = 410 AND entity = ".(int) $entity;
			if ($this->db->query($sql) === false) {
				return -1;
			}
		}
		return 0;
	}

	/**
	 * Function called when module is disabled.
	 * Removes SLY PDF template entries from document_model.
	 *
	 * @param string $options Options when disabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		global $conf;

		$sql = array(
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_invoice' AND type = 'invoice' AND entity = ".((int) $conf->entity),
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_packinglist' AND type = 'shipping' AND entity = ".((int) $conf->entity),
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'sly_debitnote' AND type = 'invoice_supplier' AND entity = ".((int) $conf->entity),
			"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'cornas_SLY' AND type = 'order_supplier' AND entity = ".((int) $conf->entity),
		);

		return $this->_remove($sql, $options);
	}
}
