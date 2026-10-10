<?php
/* Copyright (C) 2026  HaoSG Group
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
 * \defgroup   aiconsole      Module AI Console
 * \brief      Read-only console over the configuration of the core AI module.
 * \file       htdocs/custom/aiconsole/core/modules/modaiconsole.class.php
 * \ingroup    aiconsole
 * \brief      Description and activation for module AI Console
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module AI Console
 *
 * The module owns no business data and writes no constant of the core ai module. Its
 * console renders the state of that configuration in detail, one section per topic.
 *
 * It is a standalone module: it declares NO left menu and is embedded in no other
 * module's page. It is reached from the module list "Configure" button (config_page_url),
 * which is the Dolibarr-native way in for an admin-only settings module, and by URL.
 * From v1.0.0 to v1.2.0 it published a "Tools > AI Console" entry tree; from v1.3.0 the
 * menu is empty and re-enabling the module deletes those nine rows. A console that
 * configures a single core module does not belong in a left menu next to Tools such as
 * the backup or the module manager - it is reached from the module that owns it.
 *
 * Three things the console still adds over the tabs the core module renders:
 *   - The user never leaves the console. Each topic renders here; the jump to the core
 *     editor is an explicit button inside the section, never a navigation target.
 *   - ai/admin/log_viewer.php is declared by no menu and no tab, so it is unreachable
 *     without typing its URL. This module gives it a home.
 *   - It is reachable whatever MAIN_FEATURES_LEVEL is, because that gate applies to the
 *     core ai tabs and not to this console.
 *
 * The section registry in getSections() is the single source of truth for that console:
 * the section bar, the overview links and the eight switch cases are all generated from
 * it, so they cannot describe different sets of pages.
 */
class modaiconsole extends DolibarrModules
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		// Module unique id. 500200=EmbeddedBookkeeping; 500300=OdooConnector (SLY range 500100-500999).
		$this->numero = 500400;
		$this->rights_class = 'aiconsole';
		$this->family = 'HaoSG';
		$this->module_position = '90';
		$this->name = 'aiconsole';
		$this->description = 'Read-only console for the AI module configuration, tab by tab';
		$this->descriptionlong = 'Renders, section by section, how the core AI module is currently configured (provider settings, assistant, MCP server, tool access control, custom prompts, request log) plus this module\'s own read-only MCP log tool. Navigation never leaves the console: the jump to the core editor is an explicit button inside each section. Standalone module, reached from the module list "Configure" button. Writes one constant of its own and stores no data.';
		$this->editor_name = 'Custom';
		$this->editor_url = '';
		$this->version = '1.3.0';
		$this->const_name = 'MAIN_MODULE_AICONSOLE';
		$this->picto = 'setup';

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
			'hooks' => array('aimcp'), // context used by McpHandler::loadExternalTools() to let this module register MCP tools
			'moduleforexternal' => 0,
		);

		$this->dirs = array(); // no directory to create on activation
		$this->config_page_url = array('setup.php@aiconsole');
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->phpmin = array(7, 0); // identical to every v24 core module descriptor (modAi, modApi, modOpenSurvey...)
		$this->need_dolibarr_version = array(24, 0);
		$this->langfiles = array('aiconsole@aiconsole');
		$this->warnings_activation = array();
		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		// Single read-level right, granted by default so the entry appears for
		// administrators right after activation. admin/setup.php still hard-checks
		// $user->admin, mirroring the admin-only gating of every ai/admin page.
		$this->rights[$r][0] = $this->numero.sprintf('%02d', $r + 1);
		$this->rights[$r][1] = 'View the AI console overview';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'overview';
		$this->rights[$r][5] = 'read';
		$r++;

		// Read Dolibarr logs through the MCP log tool.
		//
		// [3] is 0 on purpose: this right is NOT granted to any group, not even the
		// administrator one. The core module grants its rights by default, but doing
		// so here would make the right permanently on and silently defeat the whole
		// point of having it. An administrator must tick it explicitly, per group.
		// AiconsoleLogTool::execute() re-checks this right on every call, so holding
		// it is necessary but not sufficient - the AICONSOLE_ENABLE_MCP_LOGTOOL
		// constant is a second, independent gate.
		$this->rights[$r][0] = $this->numero.sprintf('%02d', $r + 1);
		$this->rights[$r][1] = 'Read Dolibarr logs through the MCP log tool';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'mcp_logtool';
		$this->rights[$r][5] = 'read';
		$r++;

		// No left menu. This is an admin-only settings module, so the Dolibarr-native way
		// in is the module list "Configure" button (config_page_url above) - the same way
		// every other setup-only module is reached. A left menu entry would sit next to
		// Tools such as the backup or the module manager, which is the wrong neighbourhood
		// for a console that only reads the core AI module's configuration. Leaving this
		// empty also makes the removal clean: re-enabling the module deletes the nine rows
		// v1.0.0-1.2.0 wrote.
		$this->menu = array();
	}

	/**
	 * The eight sections of the console, keyed by leftmenu.
	 *
	 * Single source of truth. The section bar and the overview links rendered by
	 * admin/_console.inc.php are generated from it, and its keys are exactly the cases of
	 * that file's switch. Before v1.2.0 the section bar and the left menu each carried
	 * their own hardcoded copy of this list; that is how a section ended up pointing at
	 * /ai/admin/*.php and threw the administrator straight out of the console.
	 *
	 * 'coreurl' is the hand-off target of the single "go configure" button inside the
	 * section. It is the ONLY route out of the console; nothing navigates there implicitly.
	 * Leaving it empty means the section has no counterpart in the core ai module
	 * (the overview, and the MCP log tool this module owns itself).
	 *
	 * @return array<string,array<string,string>>
	 */
	public function getSections()
	{
		return array(
			'aiconsole_overview' => array('label' => 'AiConsoleOverview', 'titlelang' => 'AiConsoleTitle', 'coreurl' => ''),
			'aiconsole_provider' => array('label' => 'AiConsoleProvider', 'titlelang' => 'AiConsoleProviderTitle', 'coreurl' => '/ai/admin/setup.php'),
			'aiconsole_assistant' => array('label' => 'AiConsoleAssistant', 'titlelang' => 'AiConsoleAssistantTitle', 'coreurl' => '/ai/admin/assistant.php'),
			'aiconsole_mcp' => array('label' => 'AiConsoleMcp', 'titlelang' => 'AiConsoleMcpTitle', 'coreurl' => '/ai/admin/server_mcp.php'),
			'aiconsole_tools' => array('label' => 'AiConsoleTools', 'titlelang' => 'AiConsoleToolsTitle', 'coreurl' => '/ai/admin/configure_tools.php'),
			'aiconsole_prompt' => array('label' => 'AiConsolePrompt', 'titlelang' => 'AiConsolePromptTitle', 'coreurl' => '/ai/admin/custom_prompt.php'),
			'aiconsole_log' => array('label' => 'AiConsoleLog', 'titlelang' => 'AiConsoleLogTitle', 'coreurl' => '/ai/admin/log_viewer.php'),
			'aiconsole_logtool' => array('label' => 'AiConsoleLogToolNav', 'titlelang' => 'AiConsoleLogToolSection', 'coreurl' => ''),
		);
	}

	/**
	 * Function called when module is enabled. Registers permissions and menus only.
	 *
	 * @param string $options Options when enabling module
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		// No table to create, no constant to set: this module is navigation only.
		return $this->_init(array(), $options);
	}

	/**
	 * Function called when module is disabled. Removes menus and permissions.
	 *
	 * Nothing else to clean up: the module never created a file or a row.
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