<?php
/* Copyright (C) 2025  Odoo Connector (Dolibarr)
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
 * \defgroup   odoo_connector  Module Odoo Connector
 * \brief      Sync customer invoices, vendor bills and expenses between Dolibarr and Odoo Online.
 * \file       htdocs/custom/odooconnector/core/modules/modOdooConnector.class.php
 * \ingroup    odoo_connector
 * \brief      Description and activation for module Odoo Connector
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Odoo Connector
 */
class modOdooConnector extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		// Module unique id. 500200=EmbeddedBookkeeping; 500300=OdooConnector (SLY range 500100-500999).
		$this->numero = 500300;
		$this->rights_class = 'odoo_connector';
		$this->family = 'other';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Sync customer invoices, vendor bills and expenses with Odoo Online';
		$this->descriptionlong = 'Synchronises accounting records between Dolibarr and Odoo Online: customer invoices (Facture), vendor bills (FactureFournisseur), and expense reports (ExpenseReport). Uses Odoo JSON-RPC API; configurable via Setup.';
		$this->editor_name = 'Custom';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'generic';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array(); // no directory to create on activation (web process often cannot write into module dir)
		$this->config_page_url = array('setup.php@odooconnector');
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 3); // same as Dolibarr 22 core
		$this->need_dolibarr_version = array(22, 0);
		$this->langfiles = array('odoo_connector@odooconnector');
		$this->warnings_activation = array();
		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array(
			0 => array(
				'entity' => 1, // Must match the entity where ODOO_CONNECTOR_* consts are saved: cron with entity 0 loads only entity 0 consts and would find no config
				'label' => 'Odoo Connector sync (invoices, bills, expenses)',
				'jobtype' => 'method',
				'class' => 'custom/odooconnector/class/OdooSync.class.php',
				'objectname' => 'OdooSync',
				'method' => 'runSync',
				'parameters' => '',
				'comment' => 'Sync Dolibarr invoices, vendor bills and expenses to Odoo Online',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => '$conf->odooconnector->enabled',
				'priority' => 50,
			),
			1 => array(
				'entity' => 1, // Same entity constraint as the sync job above
				'label' => 'Odoo Connector shipment date refresh (ATA/ETA updates)',
				'jobtype' => 'method',
				'class' => 'custom/odooconnector/class/OdooSync.class.php',
				'objectname' => 'OdooSync',
				'method' => 'runShipmentDateSync',
				'parameters' => '',
				'comment' => 'Refresh the accounting date of Odoo draft invoices/bills linked to shipments updated recently',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => '$conf->odooconnector->enabled',
				'priority' => 50,
			),
		);

		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf('%02d', $r + 1);
		$this->rights[$r][1] = 'Configure Odoo Connector and run sync';
		$this->rights[$r][4] = 'setup';
		$r++;

		$this->menu = array();
		$r = 0;
		$this->menu[$r] = array(
			'fk_menu' => 'fk_mainmenu=home,fk_leftmenu=setup',
			'type' => 'left',
			'titre' => 'OdooConnector',
			'prefix' => img_picto('', 'generic', 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu' => 'home',
			'leftmenu' => 'odoo_connector',
			'url' => '/custom/odooconnector/admin/setup.php',
			'langs' => 'odoo_connector@odooconnector',
			'position' => 100,
			'enabled' => '$conf->odooconnector->enabled',
			'perms' => '$user->admin',
			'target' => '',
			'user' => 2,
		);
		$r++;
	}

	/**
	 * Function called when module is enabled.
	 * Creates mapping table for Dolibarr <-> Odoo sync, then registers
	 * the module (activation const, cron job, permissions, menu) via _init().
	 *
	 * @param string $options Options when enabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		// Create mapping table (run_sql replaces llx_ with the real DB prefix)
		$result = $this->_load_tables('/custom/odooconnector/sql/', 'odoo_connector');
		if ($result <= 0) {
			return $result; // table creation failed: do not register the module
		}
		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param string $options Options when disabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
