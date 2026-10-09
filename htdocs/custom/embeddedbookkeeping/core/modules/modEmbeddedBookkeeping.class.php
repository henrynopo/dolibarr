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
		$this->family = 'HaoSG';
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
						// 'all' is what actually makes printCommonFooter fire everywhere.
						// initHooks() instantiates the actions class once per PAGE context, and
						// it only matches a module whose declared list contains that context (or
						// 'all'). Declaring bare context names like 'invoicecard' therefore only
						// worked on those three cards - on every other page (the whole core
						// Accountancy module included) the class was never instantiated, so
						// executeHooks('printCommonFooter') found nothing and the assistant
						// vanished. The specific contexts below are kept as documentation and
						// to narrow the Actions class instantiation where it matters; the
						// methods themselves re-check $parameters['currentcontext'] before
						// doing anything.
						'all',                    // global floating assistant (printCommonFooter)
						'invoicecard',            // compta/facture/card.php - customer invoice card
						'invoicesuppliercard',    // fourn/facture/card.php - supplier invoice card
						'expensereportcard',      // expensereport/card.php - expense report card
						'printCommonFooter',      // llxFooter() - floating AI assistant on every page
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

		// Constants to materialise in `llx_const` at enable time. Names
		// prefixed with `EMBEDDEDBOOKKEEPING_` so they are clearly owned by
		// this module. The bookkeeping-suggest prompts (PRE / POST) live
		// here on purpose — see init() migration block below for the
		// historical "this used to be parked in the system AI module's
		// AI_CONFIGURATIONS_PROMPT JSON" note.
		//
		// The CUSTOM_* quartet backs the 'ebk_custom' provider — a fully
		// independent LLM config the admin can enable WITHOUT touching the
		// system AI module's own settings. service/url/model default to
		// empty so the seed is harmless until the admin actually fills
		// them in (resolveAdapter() returns null for an incomplete config
		// and the bookkeeping tab falls back to manual entry).
		$this->conf = array(
			'EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE'  => array('type'=>'chaine', 'value'=>''),
			'EMBEDDEDBOOKKEEPING_AI_PROMPT_POST' => array('type'=>'chaine', 'value'=>''),
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_SERVICE' => array('type'=>'chaine', 'value'=>'chatgpt'),
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_KEY'     => array('type'=>'chaine', 'value'=>''),
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_URL'     => array('type'=>'chaine', 'value'=>''),
			'EMBEDDEDBOOKKEEPING_AI_CUSTOM_MODEL'   => array('type'=>'chaine', 'value'=>''),
			// Offline knowledge base (custom/embeddedbookkeeping/knowledge/*.md):
			// enabled by default, 6000-char budget, no company notes yet. Like all
			// module constants these are materialised on enable(), so the module
			// has to be disabled/re-enabled once for them to exist.
			'EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_ENABLED'  => array('type'=>'chaine', 'value'=>'1'),
			'EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_MAXCHARS' => array('type'=>'chaine', 'value'=>'6000'),
			'EMBEDDEDBOOKKEEPING_AI_KNOWLEDGE_EXTRA'    => array('type'=>'chaine', 'value'=>''),
		);

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
		$this->rights[$r][3] = 1; // auto-grant to admins via _init() — see CLAUDE.md §2
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

		// One-shot migration: the bookkeeping-suggest prompt used to live
		// inside the system AI module's `AI_CONFIGURATIONS_PROMPT` JSON
		// under the function key `bookkeepingsuggest` — a design that
		// violated CLAUDE.md §1 "独立与完整性 / 卸载不影响核心" by polluting
		// another module's namespace. The 2026-10 refactor moved it into
		// EBK's own `EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE/_POST` constants.
		//
		// This block runs every enable() call but is idempotent:
		//   1. If the system AI module's JSON still has `bookkeepingsuggest`
		//      and our new constants are still empty, COPY the values across
		//      so the admin's edit is preserved across the upgrade.
		//   2. UNSET the `bookkeepingsuggest` key from the JSON so the
		//      system AI module's UI no longer shows the migrated entry.
		//   3. If our new constants are non-empty (admin already saved
		//      since the upgrade), just UNSET the JSON key without
		//      overwriting the admin's work.
		//
		// Each dolibarr_set_const() call is scoped to the current entity
		// to honor multicompany.
		$aiConfigsJson = (string) dolibarr_get_const($this->db, 'AI_CONFIGURATIONS_PROMPT', (int) $conf->entity);
		if ($aiConfigsJson !== '') {
			$aiConfigs = json_decode($aiConfigsJson, true);
			if (is_array($aiConfigs) && isset($aiConfigs['bookkeepingsuggest']) && is_array($aiConfigs['bookkeepingsuggest'])) {
				$migrated = $aiConfigs['bookkeepingsuggest'];
				unset($aiConfigs['bookkeepingsuggest']);

				// Copy across only if our new constant is still empty
				// (admin hasn't already saved since the upgrade). This
				// prevents the migration from clobbering a newer edit.
				if (empty(dolibarr_get_const($this->db, 'EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE', (int) $conf->entity))
					&& !empty($migrated['prePrompt'])) {
					dolibarr_set_const($this->db, 'EMBEDDEDBOOKKEEPING_AI_PROMPT_PRE', (string) $migrated['prePrompt'], 'chaine', 0, '', (int) $conf->entity);
				}
				if (empty(dolibarr_get_const($this->db, 'EMBEDDEDBOOKKEEPING_AI_PROMPT_POST', (int) $conf->entity))
					&& !empty($migrated['postPrompt'])) {
					dolibarr_set_const($this->db, 'EMBEDDEDBOOKKEEPING_AI_PROMPT_POST', (string) $migrated['postPrompt'], 'chaine', 0, '', (int) $conf->entity);
				}

				// Persist the JSON without our key (other function keys
				// the admin configured elsewhere in the system AI module
				// are preserved untouched).
				$newJson = json_encode($aiConfigs, JSON_UNESCAPED_UNICODE);
				dolibarr_set_const($this->db, 'AI_CONFIGURATIONS_PROMPT', $newJson, 'chaine', 0, '', (int) $conf->entity);
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
