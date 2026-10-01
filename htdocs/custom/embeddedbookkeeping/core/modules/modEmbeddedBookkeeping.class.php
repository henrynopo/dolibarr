<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
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
 *	\defgroup   embeddedbookkeeping     Module EmbeddedBookkeeping
 *	\brief      Record bookkeeping entries directly from invoice cards (Xero/Odoo style). AI-assisted account suggestion via Dolibarr ai module or Anthropic Claude fallback.
 *	\file       htdocs/custom/embeddedbookkeeping/core/modules/modEmbeddedBookkeeping.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Description and activation file for module EmbeddedBookkeeping
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *	Description and activation class for module EmbeddedBookkeeping
 */
class modEmbeddedBookkeeping extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		// Module unique id. 500200 leaves room for future SLY siblings (500100=slycustom, 500200=embeddedbookkeeping).
		$this->numero = 500200;
		$this->rights_class = 'embeddedbookkeeping';
		$this->family = 'HaoPie';
		$this->module_position = '92';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleEmbeddedBookkeepingDesc';
		$this->descriptionlong = 'EmbeddedBookkeepingDescriptionLong';
		$this->editor_name = 'SLY';
		$this->editor_url = '';
		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'accountancy';

			// Hooks. invoicecard / invoicesuppliercard / expensereportcard all expose
			// the full quartet (addMoreActionsButtons / formConfirm / doActions / formObjectOptions),
			// making them the natural targets for "embedded bookkeeping". bankcard / paymentcard
			// only expose formObjectOptions; they are documented as future work in docs/README.md.
			$this->module_parts = array(
				'triggers' => 0,
				'login' => 0,
				'substitutions' => 0,
				'menus' => 0,
				'tpl' => 0,
				'barcode' => 0,
				'models' => 0,
				'css' => array(),
				'js' => array(),
				'hooks' => array(
					'data' => array(
						'invoicecard',         // compta/facture/card.php — customer invoice card
						'invoicesuppliercard', // fourn/facture/card.php — supplier invoice card
						'expensereportcard',   // expensereport/card.php — expense report card
						'globalcard',          // any card.php that includes the globalcard context
					),
					'entity' => '0',
				),
				'moduleforexternal' => 0,
			);

		$this->dirs = array("/custom/embeddedbookkeeping/temp");
		$this->config_page_url = array("setup.php@embeddedbookkeeping");
		$this->hidden = false;
		$this->depends = array(); // soft-dep on 'ai' (graceful fallback when ai is missing)
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(14, 0); // 14.0+ through 22.0.x
		$this->langfiles = array("embeddedbookkeeping@embeddedbookkeeping");
		$this->warnings_activation = array();
		$this->const = array();

		// Tabs on the three supported cards. Format (6 fields, colon-separated):
		//   objecttype:+tabcode:Title:langfile@module:permission-condition:url
		// Constants are written by insert_tabs() AT ENABLE TIME — after changing
		// this list the module must be disabled and re-enabled.
		$tabUrlBase = '/custom/embeddedbookkeeping/tabs/bookkeeping.php?id=__ID__&objecttype=';
		$tabPerm = '$user->hasRight("embeddedbookkeeping","bookkeeping","read")';
		$this->tabs = array(
			array('data' => 'invoice:+ebkbookkeeping:EBKTabTitle:embeddedbookkeeping@embeddedbookkeeping:'.$tabPerm.':'.$tabUrlBase.'customer_invoice', 'entity' => '0'),
			array('data' => 'supplier_invoice:+ebkbookkeeping:EBKTabTitle:embeddedbookkeeping@embeddedbookkeeping:'.$tabPerm.':'.$tabUrlBase.'supplier_invoice', 'entity' => '0'),
			array('data' => 'expensereport:+ebkbookkeeping:EBKTabTitle:embeddedbookkeeping@embeddedbookkeeping:'.$tabPerm.':'.$tabUrlBase.'expense_report', 'entity' => '0'),
		);
		$this->dictionaries = array();
		$this->boxes = array();

		$this->cronjobs = array();

		// Permissions. IDs: 50020001..50020004
		// Array layout: [0]=id, [1]=label lang key, [2]=type char (deprecated, kept for parity with core descriptors),
		// [3]=default_perms (0=not auto-granted; admin auto-gets via insert_permissions(1,...) in _init),
		// [4]=perms group, [5]=subperms.
		$this->rights = array();
		$r = 0;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'EBKRightRead';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'bookkeeping';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'EBKRightWrite';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'bookkeeping';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'EBKRightAiSuggest';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'ai';
		$this->rights[$r][5] = 'suggest';
		$r++;
		$this->rights[$r][0] = $this->numero.sprintf("%02d", $r + 1);
		$this->rights[$r][1] = 'EBKRightAdmin';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'admin';
		$this->rights[$r][5] = 'setup';
		$r++;

		// No menu (the buttons live inside invoice cards, not in the side menu).
		$this->menu = array();

		if (!isset($conf->embeddedbookkeeping) || !isset($conf->embeddedbookkeeping->enabled)) {
			$conf->embeddedbookkeeping = new stdClass();
			$conf->embeddedbookkeeping->enabled = 0;
		}
	}

	/**
	 * Function called when module is enabled.
	 * Seeds default constants (journal codes) so the UI has sensible defaults
	 * the first time an accountant opens the setup page. No DDL — bookkeeping
	 * table is core.
	 *
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf;

		$sql = array();

		// Seed journal code defaults (idempotent — dolibarr_set_const would otherwise be the canonical writer,
		// but we only seed here so the admin can override without losing the value).
		$defaults = array(
			'EMBEDDEDBOOKKEEPING_JOURNAL_SALES'     => 'VT',
			'EMBEDDEDBOOKKEEPING_JOURNAL_PURCHASES' => 'AC',
			'EMBEDDEDBOOKKEEPING_JOURNAL_EXPENSE'   => 'EX',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CUSTOMER' => 'document',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_SUPPLIER' => 'document',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_EXPENSE'  => 'document',
			// Invoice-type presets override the generic ones (recognition
			// semantics differ): deposit = cash receipt, credit note/replacement
			// = source invoice period.
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_DEPOSIT' => 'payment',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_CREDIT_NOTE' => 'document',
			'EMBEDDEDBOOKKEEPING_DEFAULT_DATE_REPLACEMENT' => 'document',
			'EMBEDDEDBOOKKEEPING_AI_PROVIDER'       => 'ai_module',
			'EMBEDDEDBOOKKEEPING_AI_CLAUDE_MODEL'   => 'claude-sonnet-4-5',
			'EMBEDDEDBOOKKEEPING_AI_DEBUG'          => '0',
			'EMBEDDEDBOOKKEEPING_AI_MAX_LINES'      => '1',
			'EMBEDDEDBOOKKEEPING_AI_CONFIDENCE_THRESHOLD' => '0.6',
		);
		foreach ($defaults as $name => $val) {
			$current = dolibarr_get_const($this->db, $name, (int) $conf->entity);
			if ($current === null || $current === '') {
				if (dolibarr_set_const($this->db, $name, $val, 'chaine', 0, '', (int) $conf->entity) < 0) {
					$this->error = $this->db->lasterror;
					return 0;
				}
			}
		}

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Keep constants in place so re-enabling is seamless; remove only if explicit purge requested.
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
