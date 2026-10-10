<?php
/* Copyright (C) 2026  HaoSG Group
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of any later version.
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
 * \file       htdocs/custom/aiconsole/class/actions_aiconsole.class.php
 * \ingroup    aiconsole
 * \brief      Hook provider for module AI Console.
 *
 * Implements the core 'addMcpTools' hook (context 'aimcp') so this module can
 * register an MCP tool without patching any core file. The core calls it from
 * McpHandler::loadExternalTools().
 *
 * Security note: this hook fires on EVERY McpHandler construction, including
 * every /ai/admin/*.php admin page load. It must therefore stay cheap and must
 * never throw - hence the single constant test below, evaluated before any file
 * is included.
 */

/**
 * Class ActionsAiconsole
 *
 * Hook methods contributed by the AI Console module.
 */
class ActionsAiconsole
{
	/**
	 * @var array<int,array<int,\McpTool>> Collected hook results.
	 *
	 * Shape matters: HookManager merges this into $hookmanager->resArray with
	 * array_merge_recursive(), and McpHandler then walks it as a two-level
	 * foreach ($resArray as $moduleTools) { foreach ($moduleTools as $tool) }.
	 * Each module therefore contributes one inner array of tool instances.
	 */
	public $results = array();

	/**
	 * @var string[] Printable results
	 */
	public $resprints = '';

	/**
	 * Register the AI Console read-only log tool with the MCP server.
	 *
	 * Gate 2 of the four gates described in README.md. Because the constant is
	 * absent on a fresh install, an administrator who merely activates this
	 * module exposes nothing at all: the tool is never constructed, so it never
	 * appears in ai/admin/configure_tools.php and the MCP tools/list call does
	 * not advertise it.
	 *
	 * @param array<string,mixed> $parameters Hook parameters: db, user, conf
	 * @param object              $object     The calling object (McpHandler)
	 * @param string              $action     Action code
	 * @return void
	 */
	public function addMcpTools($parameters, &$object, &$action)
	{
		// Gate 2: the constant does not exist by default => return before doing anything.
		if (!getDolGlobalString('AICONSOLE_ENABLE_MCP_LOGTOOL')) {
			return;
		}

		// Belt and braces: the core ai module must be on (implied by the constant in
		// practice, but this also keeps the tool out of a half-enabled install).
		if (!isModEnabled('ai') || !isModEnabled('aiconsole')) {
			return;
		}

		// McpTool is loaded unconditionally by ai/class/mcp.class.php line 27, so the
		// base class is available whenever this hook runs. Guard anyway: a missing
		// base class must not produce a fatal error inside the MCP request.
		if (!class_exists('McpTool')) {
			dol_syslog('[aiconsole] McpTool base class not available, log tool not registered.', LOG_WARNING);
			return;
		}

		require_once __DIR__.'/AiConsoleLogTool.class.php';

		$this->results[] = array(new AiConsoleLogTool($parameters['db'], $parameters['user'], $parameters['conf']));
	}
}