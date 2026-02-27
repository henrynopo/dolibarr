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
 *	\brief      SLY 定制功能模块：PDF 模板、ShipsGo、列表列、语言选择器等（支持官方 14.0 / 22.0）
 *	\file       htdocs/slycustom/core/modules/modSlyCustom.class.php
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
		$this->description = "SLY 定制：PDF 模板、ShipsGo、来源订单列、语言选择器、导出等";
		$this->descriptionlong = "将 SLY 独有功能以模块形式提供，支持官方 Dolibarr 14.0 与 22.0。启用后即提供 SLY 发票/订单/发货单/采购单 PDF、ShipsGo 物流、列表来源订单列、语言选择器、订单附加条款等；22.0 上配合少量 core 补丁可恢复多币种与 linkedobject 等完整能力，见 patches/APPLY-ON-22.md 与 patches/PATCHES-BY-MODULE.md。";
		$this->editor_name = 'SLY';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';

		// Module parts: models (PDF) so that SLY templates are found in core/modules/*/doc under slycustom
		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 1,
			'css' => array('/slycustom/css/langpicker.css.php'),
			'js' => array('/slycustom/js/langpicker.js'),
			'hooks' => array(
				'data' => array(
					'invoicecard', 'invoicelist',
					'ordercard', 'orderlist',
					'expeditioncard', 'shipmentlist', 'ordershipmentcard',
					'paymentlist', 'paymentcard',
					'supplierinvoicelist', 'supplierorderlist',
					'paymentsupplierlist', 'propallist',
					'ordersuppliercard', 'invoicesuppliercard',
				'remx',  // 折扣拆分页：afterSplitDiscount（需应用 sly22.0-remx-hooks.patch）
					// 语言选择器（来自 custom/langpicker）
					'toprightmenu', 'mainloginpage', 'login',
					'menuLeftMenuItems',  // 左侧菜单：将 Shipment 从产品目录移到商业目录
					'formfile',  // 销售订单生成文档表单：增加「附加销售条款」选项
				),
				'entity' => '0',
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array("/slycustom/temp");
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

		// Cron: ShipsGo 状态更新（若已放置 ShipsGo_Update 类则取消注释并填写正确 class 路径）
		$this->cronjobs = array(
			// 0 => array(
			// 	'label' => 'ShipsGo update shipment status',
			// 	'jobtype' => 'method',
			// 	'class' => '/slycustom/class/ShipsGo_Update.class.php',
			// 	'objectname' => 'ShipsGo_Update',
			// 	'method' => 'runScheduledUpdate',
			// 	'parameters' => '',
			// 	'comment' => 'SLY ShipsGo 物流状态同步',
			// 	'frequency' => 1,
			// 	'unitfrequency' => 3600,
			// 	'status' => 0,
			// 	'test' => '$conf->slycustom->enabled',
			// 	'priority' => 50,
			// ),
		);

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = '使用 SLY 导出与报表';
		$this->rights[$r][4] = 'export';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = '使用语言选择器';
		$this->rights[$r][4] = 'langpicker';
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
			'url' => '/slycustom/exports/index.php',
			'langs' => 'slycustom@slycustom',
			'position' => 100,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		// SLY Export (Tools): all 7 entries as direct children of Tools so they display in parallel (same level)
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'SLYExportMenu',
			'prefix' => img_picto('', 'list', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export',
			'url' => '/slycustom/exports/tools.php?mainmenu=tools&leftmenu=sly_export',
			'langs' => 'slycustom@slycustom',
			'position' => 400,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		// SLY ALL-in-One: level 2 under SLY Export; the 5 detail exports are level 3 under it
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export',
			'type' => 'left',
			'titre' => 'SLYExportAllInOne',
			'prefix' => img_picto('', 'list', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_all',
			'url' => '/slycustom/exports/export_all.php?mainmenu=tools&leftmenu=sly_export_all',
			'langs' => 'slycustom@slycustom',
			'position' => 401,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_all',
			'type' => 'left',
			'titre' => 'SLYExportSODetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_all',
			'url' => '/slycustom/exports/export_SO_Details.php?mainmenu=tools&leftmenu=sly_export_all',
			'langs' => 'slycustom@slycustom',
			'position' => 402,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_all',
			'type' => 'left',
			'titre' => 'SLYExportSOInvoiceDetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_all',
			'url' => '/slycustom/exports/export_SO_Inv_Details.php?mainmenu=tools&leftmenu=sly_export_all',
			'langs' => 'slycustom@slycustom',
			'position' => 403,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_all',
			'type' => 'left',
			'titre' => 'SLYExportShipmentDetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_all',
			'url' => '/slycustom/exports/export_Shipment_Details.php?mainmenu=tools&leftmenu=sly_export_all',
			'langs' => 'slycustom@slycustom',
			'position' => 404,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_all',
			'type' => 'left',
			'titre' => 'SLYExportPODetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_all',
			'url' => '/slycustom/exports/export_PO_Details.php?mainmenu=tools&leftmenu=sly_export_all',
			'langs' => 'slycustom@slycustom',
			'position' => 405,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools,fk_leftmenu=sly_export_all',
			'type' => 'left',
			'titre' => 'SLYExportPOInvoiceDetails',
			'prefix' => '',
			'mainmenu' => 'tools',
			'leftmenu' => 'sly_export_all',
			'url' => '/slycustom/exports/export_PO_Inv_Details.php?mainmenu=tools&leftmenu=sly_export_all',
			'langs' => 'slycustom@slycustom',
			'position' => 406,
			'enabled' => '$conf->slycustom->enabled',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);
		$r++;
		// Search Order：放在「Tools」目录下，对应 slycustom/orderstatus.php（SLY Search Order 页，完全在模块内）
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=tools',
			'type' => 'left',
			'titre' => 'SearchOrder',
			'prefix' => img_picto('', 'search', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'tools',
			'leftmenu' => 'orderstatus',
			'url' => '/slycustom/orderstatus.php?mainmenu=tools&leftmenu=orderstatus',
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

		// Language picker table (from custom/langpicker)
		$result = $this->db->query("SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX."lang_picker")."'");
		if ($result && $this->db->num_rows($result) == 0) {
			$this->db->query(
				"CREATE TABLE ".MAIN_DB_PREFIX."lang_picker (".
				"rowid INTEGER AUTO_INCREMENT PRIMARY KEY,".
				"lang_code VARCHAR(100) NOT NULL,".
				"position INTEGER DEFAULT 0".
				")"
			);
			$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."lang_picker ADD UNIQUE INDEX uk_lang_code (lang_code)");
			foreach (array(array(1, 'en_US', 1), array(2, 'fr_FR', 2), array(3, 'es_ES', 3), array(4, 'it_IT', 4), array(5, 'de_DE', 5), array(6, 'zh_CN', 6)) as $row) {
				$this->db->query("INSERT INTO ".MAIN_DB_PREFIX."lang_picker (rowid, lang_code, position) VALUES (".(int)$row[0].", '".$this->db->escape($row[1])."', ".(int)$row[2].")");
			}
		}
		// Default constants for language picker (can be overridden in setup)
		if (dolibarr_get_const($this->db, 'LANG_PICKER_POSITION', $conf->entity) === false) {
			dolibarr_set_const($this->db, 'LANG_PICKER_POSITION', '130', 'chaine', 0, '', $conf->entity);
		}
		if (dolibarr_get_const($this->db, 'LANG_PICKER_ADD_TO_LOGIN_PAGE', $conf->entity) === false) {
			dolibarr_set_const($this->db, 'LANG_PICKER_ADD_TO_LOGIN_PAGE', '0', 'chaine', 0, '', $conf->entity);
		}
		if (dolibarr_get_const($this->db, 'LANG_PICKER_HIDDEN', $conf->entity) === false) {
			dolibarr_set_const($this->db, 'LANG_PICKER_HIDDEN', '0', 'chaine', 0, '', $conf->entity);
		}

		// Ensure language picker hook contexts are registered (for installs that enabled module before we added toprightmenu/mainloginpage/login)
		$hooksConst = dolibarr_get_const($this->db, 'MAIN_MODULE_SLYCUSTOM_HOOKS', 0);
		$langPickerContexts = array('toprightmenu', 'mainloginpage', 'login');
		if ($hooksConst !== false && $hooksConst !== null && $hooksConst !== '') {
			$arr = json_decode($hooksConst, true);
			if (is_array($arr)) {
				$merged = array_unique(array_merge($arr, $langPickerContexts));
				if (count($merged) > count($arr)) {
					dolibarr_set_const($this->db, 'MAIN_MODULE_SLYCUSTOM_HOOKS', json_encode(array_values($merged)), 'chaine', 0, '', 0);
				}
			}
		}

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
			$sql .= " WHERE module = '".$module."' AND mainmenu = 'tools' AND leftmenu = 'sly_export_all' AND position = 401 AND entity = ".(int) $entity;
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
