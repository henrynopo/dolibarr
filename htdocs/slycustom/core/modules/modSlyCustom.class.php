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
 *	\brief      SLY 定制功能模块：PDF 模板、ShipsGo、导出等（基于官方 Dolibarr 14.0）
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
		$this->description = "SLY 定制：发票/订单/发货单 PDF 模板、ShipsGo 物流、报表导出等";
		$this->descriptionlong = "将 SLY14.0 独有功能以模块形式提供，便于在官方 Dolibarr 14.0 上仅启用本模块即可使用 SLY 定制。";
		$this->editor_name = 'SLY';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';

		// Module parts: models (PDF) so that SLY templates are found in core/modules/*/doc under slycustom
		$this->module_parts = array(
			'triggers' => 0,
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
					'ordercard', 'orderlist',
					'expeditioncard', 'shipmentlist', 'ordershipmentcard',
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
		$this->boxes = array();

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

		$this->menu = array();
		$r = 0;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=home,fk_leftmenu=commercial',
			'type' => 'left',
			'titre' => 'SLY Exports',
			'prefix' => img_picto('', 'generic', 'class="paddingright pictofixedwidth valignmiddle"'),
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

		if (!isset($conf->slycustom) || !isset($conf->slycustom->enabled)) {
			$conf->slycustom = new stdClass();
			$conf->slycustom->enabled = 0;
		}
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_init(array(), $options);
		return $result;
	}
}
