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
 * \file       htdocs/custom/aiconsole/admin/_console.inc.php
 * \ingroup    aiconsole
 * \brief      Renderer of the AI console: the whole console, without the page frame.
 *
 * This file is NOT a page. It contains no bootstrap, no llxHeader() and no llxFooter():
 * it renders the console into whatever page includes it, and the page owns the frame.
 * Its one and only host is custom/aiconsole/admin/setup.php, which sets the two contract
 * variables below, includes this file, prints llxHeader(), then calls
 * aiconsoleRenderConsole(). The module is deliberately self-contained - it publishes no
 * menu entry and is not embedded in any other module's page, so the contract is small.
 *
 * The contract with the host is two variables set BEFORE the include:
 *
 *   $aiconsoleBaseUrl      (string, required) Absolute URL of the host page, without any
 *                                          query string. Every section link is built on
 *                                          top of it.
 *   $aiconsoleExtraParams  (array,  required) Extra query parameters to carry over on
 *                                          every link and form post (backtopage and the
 *                                          like). May be empty.
 *
 * The section bar always goes through dol_get_fiche_head(), which reads $links[$i][0..2]
 * by index - a plain string there gets its characters read as those slots and the bar
 * renders as loose glyphs instead of tabs.
 *
 * The include/call split is deliberate: everything above the call (POST actions,
 * setEventMessages()) must run BEFORE the host prints llxHeader(), because llxHeader() is
 * what flushes the queued event messages into the page.
 *
 * Nothing here writes a constant except the MCP log tool switch, which is the single
 * write action of the whole module. Every other section is read-only and states that
 * plainly; the jump to the core ai/admin editor is an explicit button inside the section,
 * never a navigation target.
 */

$aiconsoleBaseUrl = isset($aiconsoleBaseUrl) ? $aiconsoleBaseUrl : '';
$aiconsoleExtraParams = isset($aiconsoleExtraParams) && is_array($aiconsoleExtraParams) ? $aiconsoleExtraParams : array();
if ($aiconsoleBaseUrl === '') {
	die('aiconsole: the host page must set $aiconsoleBaseUrl before including _console.inc.php');
}

// Backstop: the host already checked both of these before including. An include is not
// a place where an outsider can arrive from, so this only guards against a future host
// page forgetting the check - it is not the gate.
if (empty($GLOBALS['user']->admin) || !isModEnabled('aiconsole')) {
	accessforbidden();
}
$aienabled = isModEnabled('ai');

$langs->loadLangs(array('aiconsole@aiconsole'));

// Core helpers of the ai module are only required when it is enabled, otherwise the
// console still renders (with a warning banner) on a clean install.
if ($aienabled) {
	require_once DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/ai/class/mcp.class.php';
}

// The log tool class is required unconditionally: the preview form reads its MAX_HOURS
// and MAX_LIMIT constants when rendering, on plain GET requests too. The file pulls in
// McpTool itself when the ai module has not loaded it yet, and both are harmless to
// include when the ai module is disabled.
require_once DOL_DOCUMENT_ROOT.'/custom/aiconsole/class/AiConsoleLogTool.class.php';

// The module descriptor owns the section registry, so the section bar, the overview
// links and (until v1.2.0) the left menu were all generated from one list and could not
// describe different sets of pages.
require_once DOL_DOCUMENT_ROOT.'/custom/aiconsole/core/modules/modaiconsole.class.php';
$aiconsoleModule = new modaiconsole($GLOBALS['db']);
$sections = $aiconsoleModule->getSections();

$leftmenu = GETPOST('leftmenu', 'aZ09', 0, 1);
if (!isset($sections[$leftmenu])) {
	$leftmenu = 'aiconsole_overview';
}


/*
 * ACTIONS
 *
 * Two POST actions, both token-protected: main.inc.php already rejects any POST without
 * a valid 'token' (see main.inc.php:420-429), and each host defines CSRFCHECK_WITH_TOKEN
 * before its own bootstrap so GET is covered too. Nothing else writes.
 *
 * Neither action redirects. The form posts back to the URL of the section it was opened
 * from, so the page comes back on the same section with the new state already applied;
 * a redirect would only cost a second round trip.
 */

$action = GETPOST('action', 'aZ09');

if ($action === 'toggle_logtool') {
	$enable = GETPOST('enable', 'int') ? 1 : 0;

	$res = dolibarr_set_const($GLOBALS['db'], 'AICONSOLE_ENABLE_MCP_LOGTOOL', (string) $enable, 'chaine', 1, 'AI console: expose the read-only MCP log tool', (int) $GLOBALS['conf']->entity);
	if ($res > 0) {
		setEventMessages($langs->trans('AiConsoleLogToolSwitchDone', $enable ? $langs->trans('Enabled') : $langs->trans('Disabled')), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('AiConsoleLogToolSwitchFailed'), null, 'errors');
	}
}

// Build the preview payload using the tool's own execute(), so what the administrator
// reads below is byte for byte what an MCP caller would receive. There is no second,
// more permissive code path.
$previewResult = null;
if ($action === 'preview_logtool') {
	$source = GETPOST('preview_source', 'aZ09');
	$args = array('source' => $source);
	if (GETPOSTISSET('preview_hours')) {
		$args['hours'] = (int) GETPOST('preview_hours', 'int');
	}
	if (GETPOSTISSET('preview_limit')) {
		$args['limit'] = (int) GETPOST('preview_limit', 'int');
	}

	$previewTool = new AiConsoleLogTool($GLOBALS['db'], $GLOBALS['user'], $GLOBALS['conf']);
	$previewResult = $previewTool->execute(AiConsoleLogTool::TOOL_NAME, $args);
}


/**
 * URL of one section of the console.
 *
 * Built on the host page's URL ($aiconsoleBaseUrl) plus the query parameters the host
 * wants carried over, rather than on a hardcoded path. Keeping the base URL in the host's
 * hands is what lets this renderer stay free of any assumption about where it is drawn.
 *
 * DOL_URL_ROOT is the host's responsibility - dolBuildUrl() only appends the query
 * string, it never adds the install path.
 *
 * @param string $key Section key
 * @return string
 */
function aiconsoleSectionUrl($key)
{
	global $aiconsoleBaseUrl, $aiconsoleExtraParams;

	return dolBuildUrl($aiconsoleBaseUrl, array_merge($aiconsoleExtraParams, array('leftmenu' => $key)));
}

/**
 * URL of a core ai/admin page, used only by the explicit hand-off button.
 *
 * @param string $path Path of the core page, e.g. '/ai/admin/setup.php'
 * @return string
 */
function aiconsoleCoreUrl($path)
{
	return dolBuildUrl(DOL_URL_ROOT.$path);
}

/**
 * Render a status pill.
 *
 * Delegates to the core dolGetBadge() rather than assembling the markup here, so the
 * console keeps whatever styling the running theme defines. Status types follow the same
 * convention ai/admin/log_viewer.php uses: 0 grey, 3 yellow, 4 green, 8 red.
 *
 * @param int    $status 1 = on/configured, 0 = off/missing, 2 = warning, 3 = error
 * @param string $text   Label to display inside the pill
 * @return string         HTML
 */
function aiconsoleBadge($status, $text)
{
	$type = 'status4';
	if ($status == 0) {
		$type = 'status0';
	} elseif ($status == 2) {
		$type = 'status3';
	} elseif ($status == 3) {
		$type = 'status8';
	}
	return dolGetBadge($text, '', $type);
}

/**
 * Print a two-column "setting / current value" table.
 *
 * @param array<int,array<string,mixed>> $rows Rows of label / value / badge
 * @return void
 */
function aiconsoleSettingTable($rows)
{
	global $langs;

	print '<table class="noborder centpercent">';
	print '<thead>';
	print '<tr class="liste_titre">';
	print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleSetting')).'</td>';
	print '<td class="center">'.dol_escape_htmltag($langs->trans('AiConsoleStatus')).'</td>';
	print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleDetail')).'</td>';
	print '</tr>';
	print '</thead>';
	print '<tbody>';
	foreach ($rows as $row) {
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax200">'.dol_escape_htmltag($row['label']).'</td>';
		print '<td class="center">'.($row['badge'] === '' ? '' : $row['badge']).'</td>';
		print '<td class="tdoverflowmax200">'.dol_escape_htmltag($row['value']).'</td>';
		print '</tr>';
	}
	print '</tbody>';
	print '</table>';
}

/**
 * Print the section bar.
 *
 * Delegates to the core dol_get_fiche_head(). Note that $head[$i] MUST be
 * array(url, label, active): that function reads $links[$i][0..2] by index, so a plain
 * string there gets its characters read as those slots and the bar renders as loose
 * glyphs instead of tabs.
 *
 * @param array<string,array<string,string>> $sections Section registry
 * @param string                            $leftmenu Active section key
 * @return void
 */
function aiconsoleSectionBar($sections, $leftmenu)
{
	global $langs;

	$head = array();
	$h = 0;
	foreach ($sections as $key => $def) {
		$head[$h][0] = aiconsoleSectionUrl($key);
		$head[$h][1] = $langs->trans($def['label']);
		$head[$h][2] = $key;
		$h++;
	}
	print dol_get_fiche_head($head, $leftmenu, '', -1, 'aiconsole');
}

/**
 * Print the section heading plus, when the topic has one, the hand-off button.
 *
 * The button is the only way out of the console on purpose: an administrator always sees
 * where the actual editor lives, but nothing navigates away implicitly.
 *
 * @param string $key Section key
 * @return void
 */
function aiconsoleSectionHead($key)
{
	global $sections, $aienabled, $langs;

	$def = $sections[$key];
	print load_fiche_titre($langs->trans($def['titlelang']), '', '');

	if (!empty($def['coreurl']) && $aienabled) {
		$url = aiconsoleCoreUrl($def['coreurl']);
		print '<div class="right"><a class="button" href="'.dol_escape_htmltag($url).'">'
			.dol_escape_htmltag($langs->trans('AiConsoleConfigure')).'</a></div>';
		print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleReadOnlyHint')).'</div>';
	}
	print '<br>';
}


/**
 * Render the console: collect the state once, then print the bar and the active section.
 *
 * Everything below runs on every request, whatever the active section: eight small
 * sections would otherwise mean eight round trips through PHP for one page. All of it
 * reads constants and one in-memory tool list, plus two aggregate queries that are
 * issued once each and never inside a loop. The switch below only renders.
 *
 * @return void
 */
function aiconsoleRenderConsole()
{
	global $db, $conf, $user, $langs, $aienabled, $sections, $leftmenu, $previewResult;

	// --- Tool allow-lists ---------------------------------------------------------
	// Resolved with the same core helpers ai/admin/configure_tools.php uses, so the
	// numbers and the per-tool states shown here match what that page shows.
	// getToolsSchemaUnfiltered() loads every tool once into memory; every count below is
	// a count over that array, never a query inside a loop.
	$allDiscoveredTools = array();
	$toolsByCategory = array();
	$nbsystemtools = 0;
	$astAllowedList = array();
	$mcpAllowedList = array();

	if ($aienabled && class_exists('McpHandler')) {
		$mcpHandler = new McpHandler($db, $user, $conf, McpHandler::CTX_ASSISTANT);
		$unfilteredSchema = $mcpHandler->getToolsSchemaUnfiltered();
		if (!empty($unfilteredSchema) && is_array($unfilteredSchema)) {
			foreach ($unfilteredSchema as $toolDef) {
				if (!empty($toolDef['is_system'])) {
					$nbsystemtools++;
				} else {
					$allDiscoveredTools[] = $toolDef['name'];
				}

				// Same grouping key configure_tools.php uses: the first declared category.
				$categories = isset($toolDef['categories']) && is_array($toolDef['categories']) ? $toolDef['categories'] : array();
				$categoryName = empty($categories) ? $langs->trans('AiConsoleToolOtherGroup') : (string) reset($categories);
				$toolsByCategory[$categoryName][] = $toolDef;
			}
		}
		$astAllowedList = McpHandler::resolveAllowList(getDolGlobalString('AI_ASSISTANT_ALLOWED_TOOLS'), $allDiscoveredTools);
		$mcpAllowedList = McpHandler::resolveAllowList(getDolGlobalString('AI_MCP_SERVER_ALLOWED_TOOLS'), $allDiscoveredTools);
	}
	$totalToolCount = count($allDiscoveredTools);
	$astAllowedCount = count($astAllowedList);
	$mcpAllowedCount = count($mcpAllowedList);

	// --- Provider ------------------------------------------------------------------
	$providerLabel = '';
	$providerKeyset = 0;
	$serviceKey = $aienabled ? getDolGlobalString('AI_API_SERVICE') : '';
	$providerUrl = '';
	if (!empty($serviceKey) && $serviceKey != '-1' && function_exists('getListOfAIServices')) {
		$services = getListOfAIServices();
		if (isset($services[$serviceKey]['label'])) {
			$providerLabel = (string) $services[$serviceKey]['label'];
		}
		// Presence check only: the constant is stored encrypted and is never echoed back.
		$providerKeyset = empty(getDolGlobalString('AI_API_'.strtoupper($serviceKey).'_KEY')) ? 0 : 1;
		// AI_API_<SVC>_URL does not end in _KEY, so dol_is_secured_key() leaves it in clear:
		// it is an endpoint, not a credential, and showing it is the point of this section.
		$providerUrl = (string) getDolGlobalString('AI_API_'.strtoupper($serviceKey).'_URL');
	}
	if (empty($providerLabel)) {
		$providerLabel = $langs->trans('AiConsoleNoProvider');
	}

	// Model overrides, one row per AI function. Built from getListOfAIFeatures() so the
	// list cannot fall out of step with what the core page offers. Constants are grouped
	// by "function" because that is the granularity the core stores them at.
	$modelOverrides = array();
	if ($aienabled && !empty($serviceKey) && $serviceKey != '-1' && function_exists('getListOfAIFeatures')) {
		$seenFunctions = array();
		foreach (getListOfAIFeatures() as $feature) {
			if (empty($feature['function'])) {
				continue;
			}
			$fn = strtoupper($feature['function']);
			if (isset($seenFunctions[$fn])) {
				continue;
			}
			$seenFunctions[$fn] = 1;
			$modelOverrides[$fn] = (string) getDolGlobalString('AI_API_'.strtoupper($serviceKey).'_MODEL_'.$fn);
		}
	}

	// --- Assistant -----------------------------------------------------------------
	$assistantEnabled = $aienabled ? (int) getDolGlobalInt('AI_ASSISTANT_ENABLED') : 0;
	$inputMode = $aienabled ? (string) getDolGlobalString('AI_DEFAULT_INPUT_MODE') : '';
	$inputModeLabel = $inputMode;
	if ($inputMode === 'native') {
		$inputModeLabel = $langs->trans('AiConsoleModeNative');
	} elseif ($inputMode === 'whisper') {
		$inputModeLabel = $langs->trans('AiConsoleModeWhisper');
	} elseif ($inputMode === 'text' || $inputMode === '') {
		$inputModeLabel = $langs->trans('AiConsoleModeText');
	}
	$privacyRedaction = $aienabled ? (int) getDolGlobalInt('AI_PRIVACY_REDACTION') : 0;
	$askConfirmation = $aienabled ? (int) getDolGlobalInt('AI_ASK_FOR_CONFIRMATION', 1) : 1;
	$confirmLabel = $langs->trans('AiConsoleConfirmAlways');
	if ($askConfirmation == 0) {
		$confirmLabel = $langs->trans('AiConsoleConfirmNever');
	} elseif ($askConfirmation == 1) {
		$confirmLabel = $langs->trans('AiConsoleConfirmWriteOnly');
	}
	$intentPrompt = $aienabled ? (string) getDolGlobalString('AI_INTENT_PROMPT') : '';
	$intentPreview = $intentPrompt === '' ? '' : dol_trunc($intentPrompt, 200);

	// --- MCP server ----------------------------------------------------------------
	$mcpEnabled = $aienabled ? (int) getDolGlobalInt('AI_MCP_ENABLED') : 0;
	$mcpKeySet = ($aienabled && !empty(getDolGlobalString('AI_MCP_API_KEY'))) ? 1 : 0;
	$mcpUserId = $aienabled ? (int) getDolGlobalInt('AI_MCP_USER_ID') : 0;
	$mcpUserLabel = $langs->trans('AiConsoleNoServiceUser');
	if ($mcpUserId > 0) {
		$mcpServiceUser = new User($db);
		if ($mcpServiceUser->fetch($mcpUserId) > 0) {
			$mcpUserLabel = $mcpServiceUser->login;
		} else {
			$mcpUserLabel = $langs->trans('AiConsoleServiceUserMissing', $mcpUserId);
		}
	}
	$astAllowRaw = $aienabled ? (string) getDolGlobalString('AI_ASSISTANT_ALLOWED_TOOLS') : '';
	$mcpAllowRaw = $aienabled ? (string) getDolGlobalString('AI_MCP_SERVER_ALLOWED_TOOLS') : '';
	$mcpEndpoint = DOL_URL_ROOT.'/ai/server/mcp_server.php';

	// --- Custom prompts ------------------------------------------------------------
	$promptSet = ($aienabled && !empty(getDolGlobalString('AI_CONFIGURATIONS_PROMPT'))) ? 1 : 0;
	$promptConfigs = array();
	if ($promptSet) {
		$decoded = json_decode((string) getDolGlobalString('AI_CONFIGURATIONS_PROMPT'), true);
		if (is_array($decoded)) {
			$promptConfigs = $decoded;
		}
	}

	// --- Request log ---------------------------------------------------------------
	// Two queries, both entity-scoped for multicompany, both issued once outside any
	// loop. These are core tables, so the real prefix is fetched at runtime: $db->prefix()
	// returns llx_ on a default install and llxsf_ on the slyfood production database.
	$logEnabled = $aienabled ? (int) getDolGlobalInt('AI_LOG_REQUESTS') : 0;
	$logRetention = $aienabled ? (int) getDolGlobalInt('AI_LOG_RETENTION', 30) : 30;
	$nblogs = 0;
	$lastrequest = 0;
	$recentlogs = array();
	if ($logEnabled) {
		$tablename = $db->prefix().'ai_request_log';

		$sql = 'SELECT COUNT(*) AS nb, MAX(t.date_request) AS lastreq';
		$sql .= ' FROM '.$tablename.' t';
		$sql .= ' WHERE t.entity = '.(int) $conf->entity;
		$resql = $db->query($sql);
		if ($resql) {
			$objlog = $db->fetch_object($resql);
			if ($objlog) {
				$nblogs = (int) $objlog->nb;
				$lastrequest = (int) $objlog->lastreq;
			}
			$db->free($resql);
		}

		// Recent rows, metadata only. query_text / raw_request_payload /
		// raw_response_payload hold the full prompt and the full model response and are
		// never selected, exactly as in AiConsoleLogTool. This view is admin-gated, but
		// keeping the column list narrow here means the same screen can be reused
		// elsewhere without re-auditing it.
		$sqlrecent = 'SELECT t.rowid, t.date_request, t.tool_name, t.provider, t.status';
		$sqlrecent .= ', t.execution_time, t.error_msg';
		$sqlrecent .= ' FROM '.$tablename.' t';
		$sqlrecent .= ' WHERE t.entity = '.(int) $conf->entity;
		$sqlrecent .= ' ORDER BY t.rowid DESC LIMIT 20';
		$resqlrecent = $db->query($sqlrecent);
		if ($resqlrecent) {
			while ($objrow = $db->fetch_object($resqlrecent)) {
				$recentlogs[] = (array) $objrow;
			}
			$db->free($resqlrecent);
		}
	}

	// --- Overview rows -------------------------------------------------------------
	// One row per topic, linking INSIDE the console. The jump to the core editor lives
	// in the section itself, so the overview never throws the administrator out.
	$rows = array(
		array(
			'label' => $langs->trans('AiConsoleProvider'),
			'value' => $providerLabel,
			'badge' => aiconsoleBadge($providerKeyset ? 1 : 0, $providerKeyset ? $langs->trans('AiConsoleKeySet') : $langs->trans('AiConsoleKeyMissing')),
			'leftmenu' => 'aiconsole_provider',
		),
		array(
			'label' => $langs->trans('AiConsoleAssistant'),
			'value' => $langs->trans('AiConsoleChatInterface'),
			'badge' => aiconsoleBadge($assistantEnabled ? 1 : 0, $assistantEnabled ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled')),
			'leftmenu' => 'aiconsole_assistant',
		),
		array(
			'label' => $langs->trans('AiConsoleMcp'),
			'value' => $mcpUserLabel,
			'badge' => aiconsoleBadge($mcpEnabled ? 1 : 0, $mcpEnabled ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled')),
			'extra' => aiconsoleBadge($mcpKeySet ? 1 : 0, $mcpKeySet ? $langs->trans('AiConsoleKeyGenerated') : $langs->trans('AiConsoleKeyNotGenerated'))
				.' '.aiconsoleBadge($mcpAllowedCount > 0 ? 1 : 2, $langs->trans('AiConsoleToolsAllowed', $mcpAllowedCount, $totalToolCount))
				.' '.aiconsoleBadge(2, $langs->trans('AiConsoleSystemTools', $nbsystemtools)),
			'leftmenu' => 'aiconsole_mcp',
		),
		array(
			'label' => $langs->trans('AiConsoleToolAccess'),
			'value' => $langs->trans('AiConsoleAllowedSplit'),
			'badge' => aiconsoleBadge(2, $langs->trans('AiConsoleAssistantTools', $astAllowedCount, $totalToolCount))
				.' '.aiconsoleBadge(2, $langs->trans('AiConsoleMcpTools', $mcpAllowedCount, $totalToolCount)),
			'leftmenu' => 'aiconsole_tools',
		),
		array(
			'label' => $langs->trans('AiConsoleCustomPrompt'),
			'value' => $langs->trans('AiConsolePromptDesc'),
			'badge' => aiconsoleBadge($promptSet ? 1 : 0, $promptSet ? $langs->trans('AiConsoleConfigured') : $langs->trans('AiConsoleNotConfigured')),
			'leftmenu' => 'aiconsole_prompt',
		),
		array(
			'label' => $langs->trans('AiConsoleRequestLog'),
			'value' => $nblogs > 0 ? $langs->trans('AiConsoleLogCount', $nblogs) : $langs->trans('AiConsoleLogEmpty'),
			'badge' => aiconsoleBadge($logEnabled ? 1 : 0, $logEnabled ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled'))
				.($lastrequest > 0 ? ' '.aiconsoleBadge(1, $langs->trans('AiConsoleLastRequest', dol_print_date($lastrequest, 'short'))) : ''),
			'leftmenu' => 'aiconsole_log',
		),
	);


	/*
	 * View
	 */

	aiconsoleSectionBar($sections, $leftmenu);

	if (!$aienabled) {
		print '<div class="warning">'.dol_escape_htmltag($langs->trans('AiConsoleModuleDisabled')).'</div>';
	}

	switch ($leftmenu) {

	case 'aiconsole_provider':
		aiconsoleSectionHead('aiconsole_provider');

		$providerRows = array(
			array(
				'label' => 'AI_API_SERVICE',
				'value' => $providerLabel,
				'badge' => aiconsoleBadge($providerLabel == $langs->trans('AiConsoleNoProvider') ? 0 : 1, $providerLabel == $langs->trans('AiConsoleNoProvider') ? $langs->trans('AiConsoleNotConfigured') : $langs->trans('AiConsoleConfigured')),
			),
			array(
				'label' => 'AI_API_'.strtoupper($serviceKey).'_KEY',
				'value' => $langs->trans('AiConsoleKeyNeverShown'),
				// 3 = red when missing, because without a key nothing works at all.
				'badge' => aiconsoleBadge($providerKeyset ? 1 : 3, $providerKeyset ? $langs->trans('AiConsoleKeySet') : $langs->trans('AiConsoleKeyMissing')),
			),
			array(
				'label' => 'AI_API_'.strtoupper($serviceKey).'_URL',
				'value' => $providerUrl !== '' ? $providerUrl : $langs->trans('AiConsoleNotConfigured'),
				'badge' => aiconsoleBadge($providerUrl !== '' ? 1 : 0, $providerUrl !== '' ? $langs->trans('AiConsoleConfigured') : $langs->trans('AiConsoleNotConfigured')),
			),
		);
		aiconsoleSettingTable($providerRows);

		if (!empty($modelOverrides)) {
			print load_fiche_titre($langs->trans('AiConsoleModelOverride'), '', '');
			$modelRows = array();
			foreach ($modelOverrides as $fn => $model) {
				$modelRows[] = array(
					'label' => 'AI_API_'.strtoupper($serviceKey).'_MODEL_'.$fn,
					'value' => $model !== '' ? $model : $langs->trans('AiConsoleNoOverride'),
					'badge' => aiconsoleBadge($model !== '' ? 1 : 0, $model !== '' ? $langs->trans('AiConsoleOverrideSet') : $langs->trans('AiConsoleUseDefault')),
				);
			}
			aiconsoleSettingTable($modelRows);
		}
		break;

	case 'aiconsole_assistant':
		aiconsoleSectionHead('aiconsole_assistant');

		$assistantRows = array(
			array(
				'label' => 'AI_ASSISTANT_ENABLED',
				'value' => $langs->trans('AiConsoleChatInterface'),
				'badge' => aiconsoleBadge($assistantEnabled ? 1 : 0, $assistantEnabled ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled')),
			),
			array(
				'label' => 'AI_DEFAULT_INPUT_MODE',
				'value' => $inputModeLabel,
				'badge' => aiconsoleBadge($inputMode === '' ? 2 : 1, $inputModeLabel),
			),
			array(
				'label' => 'AI_PRIVACY_REDACTION',
				// Flagged red when off: without it, personal data reaches the provider.
				'value' => $privacyRedaction ? $langs->trans('AiConsolePrivacyOn') : $langs->trans('AiConsolePrivacyOff'),
				'badge' => aiconsoleBadge($privacyRedaction ? 1 : 3, $privacyRedaction ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled')),
			),
			array(
				'label' => 'AI_ASK_FOR_CONFIRMATION',
				// Yellow at 0: the AI would then be able to write without ever asking.
				'value' => $confirmLabel,
				'badge' => aiconsoleBadge($askConfirmation == 0 ? 2 : 1, $confirmLabel),
			),
			array(
				'label' => 'AI_INTENT_PROMPT',
				'value' => $intentPreview !== '' ? $intentPreview : $langs->trans('AiConsoleNotConfigured'),
				'badge' => aiconsoleBadge($intentPrompt !== '' ? 1 : 0, $intentPrompt !== '' ? $langs->trans('AiConsoleConfigured') : $langs->trans('AiConsoleNotConfigured')),
			),
		);
		aiconsoleSettingTable($assistantRows);
		break;

	case 'aiconsole_mcp':
		aiconsoleSectionHead('aiconsole_mcp');

		$mcpRows = array(
			array(
				'label' => 'AI_MCP_ENABLED',
				'value' => $mcpEndpoint,
				'badge' => aiconsoleBadge($mcpEnabled ? 1 : 0, $mcpEnabled ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled')),
			),
			array(
				'label' => 'AI_MCP_API_KEY',
				'value' => $langs->trans('AiConsoleKeyNeverShown'),
				'badge' => aiconsoleBadge($mcpKeySet ? 1 : 0, $mcpKeySet ? $langs->trans('AiConsoleKeyGenerated') : $langs->trans('AiConsoleKeyNotGenerated')),
			),
			array(
				'label' => 'AI_MCP_USER_ID',
				'value' => $mcpUserLabel,
				'badge' => aiconsoleBadge($mcpUserId > 0 ? 1 : 2, $mcpUserId > 0 ? $langs->trans('AiConsoleConfigured') : $langs->trans('AiConsoleNotConfigured')),
			),
			array(
				'label' => 'AI_ASSISTANT_ALLOWED_TOOLS',
				'value' => $astAllowRaw === '' ? $langs->trans('AiConsoleAllowListOpen') : $astAllowRaw,
				// Red when the list is empty, because empty means EVERY tool is allowed.
				'badge' => aiconsoleBadge($astAllowRaw === '' ? 3 : 1, $langs->trans('AiConsoleAssistantTools', $astAllowedCount, $totalToolCount)),
			),
			array(
				'label' => 'AI_MCP_SERVER_ALLOWED_TOOLS',
				'value' => $mcpAllowRaw === '' ? $langs->trans('AiConsoleAllowListOpen') : $mcpAllowRaw,
				'badge' => aiconsoleBadge($mcpAllowRaw === '' ? 3 : 1, $langs->trans('AiConsoleMcpTools', $mcpAllowedCount, $totalToolCount)),
			),
			array(
				'label' => $langs->trans('AiConsoleSystemTools'),
				'value' => $langs->trans('AiConsoleSystemToolsHelp'),
				'badge' => aiconsoleBadge(2, (string) $nbsystemtools),
			),
		);
		aiconsoleSettingTable($mcpRows);
		break;

	case 'aiconsole_tools':
		aiconsoleSectionHead('aiconsole_tools');

		print '<div class="warning">'.dol_escape_htmltag($langs->trans('AiConsoleAllowListWarning')).'</div>';

		print '<table class="noborder centpercent">';
		print '<thead>';
		print '<tr class="liste_titre">';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleToolName')).'</td>';
		print '<td class="center">'.dol_escape_htmltag($langs->trans('AiConsoleToolType')).'</td>';
		print '<td class="hideonsmartphone">'.dol_escape_htmltag($langs->trans('AiConsoleToolDescription')).'</td>';
		print '<td class="center nowraponall">'.dol_escape_htmltag($langs->trans('AiConsoleColAssistant')).'</td>';
		print '<td class="center nowraponall">'.dol_escape_htmltag($langs->trans('AiConsoleColMcp')).'</td>';
		print '</tr>';
		print '</thead>';
		print '<tbody>';

		if (empty($toolsByCategory)) {
			print '<tr class="oddeven"><td colspan="5" class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleNoTools')).'</td></tr>';
		} else {
			foreach ($toolsByCategory as $categoryName => $definitions) {
				print '<tr class="liste_titre"><td colspan="5">'.dol_escape_htmltag($categoryName).'</td></tr>';
				foreach ($definitions as $def) {
					$name = $def['name'];
					$isSystem = !empty($def['is_system']);

					// Same read/write heuristic the core page uses, so the badge means here
					// exactly what it means there.
					if (preg_match('/^(create|update|delete|add|remove|change|write|edit|validate|pay|send)/i', $name)) {
						$isWrite = true;
					} else {
						$isWrite = false;
					}

					// System tools bypass the allow-list in McpHandler::executeTool(), so
					// they read as on for both contexts and are not counted in the
					// "allowed" badges.
					$isAstOn = $isSystem ? 1 : (in_array($name, $astAllowedList, true) ? 1 : 0);
					$isMcpOn = $isSystem ? 1 : (in_array($name, $mcpAllowedList, true) ? 1 : 0);

					print '<tr class="oddeven">';
					print '<td class="tdoverflowmax200"><strong>'.dol_escape_htmltag($name).'</strong>';
					if ($isSystem) {
						print ' <span class="badgeneutral">'.dol_escape_htmltag($langs->trans('System')).'</span>';
					}
					print '</td>';
					print '<td class="center">'.($isWrite
						? dolGetBadge($langs->trans('Modify'), '', 'status2')
						: dolGetBadge($langs->trans('ViewOnly'), '', 'status4')).'</td>';
					print '<td class="small opacitymedium hideonsmartphone">'.dol_escape_htmltag(!empty($def['description']) ? $def['description'] : '-').'</td>';
					print '<td class="center nowraponall">'.($isAstOn
						? dolGetBadge($langs->trans('Active'), '', 'status4')
						: dolGetBadge($langs->trans('Disabled'), '', 'status0')).'</td>';
					print '<td class="center nowraponall">'.($isMcpOn
						? dolGetBadge($langs->trans('Active'), '', 'status4')
						: dolGetBadge($langs->trans('Disabled'), '', 'status0')).'</td>';
					print '</tr>';
				}
			}
		}
		print '</tbody>';
		print '</table>';
		break;

	case 'aiconsole_prompt':
		aiconsoleSectionHead('aiconsole_prompt');

		if (empty($promptConfigs)) {
			print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleNoPrompt')).'</div>';
		} else {
			print '<table class="noborder centpercent">';
			print '<thead>';
			print '<tr class="liste_titre">';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsolePromptFunction')).'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsolePromptPre')).'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsolePromptPost')).'</td>';
			print '<td class="right">'.dol_escape_htmltag($langs->trans('AiConsolePromptBlacklist')).'</td>';
			print '</tr>';
			print '</thead>';
			print '<tbody>';
			foreach ($promptConfigs as $functioncode => $promptConf) {
				$pre = isset($promptConf['prePrompt']) ? (string) $promptConf['prePrompt'] : '';
				$post = isset($promptConf['postPrompt']) ? (string) $promptConf['postPrompt'] : '';
				$blacklist = isset($promptConf['blacklists']) && is_array($promptConf['blacklists']) ? count($promptConf['blacklists']) : 0;

				print '<tr class="oddeven">';
				print '<td class="tdoverflowmax200"><strong>'.dol_escape_htmltag((string) $functioncode).'</strong></td>';
				print '<td class="tdoverflowmax200">'.($pre === '' ? '<span class="opacitymedium">-</span>' : dol_escape_htmltag(dol_trunc($pre, 150))).'</td>';
				print '<td class="tdoverflowmax200">'.($post === '' ? '<span class="opacitymedium">-</span>' : dol_escape_htmltag(dol_trunc($post, 150))).'</td>';
				print '<td class="right">'.($blacklist > 0 ? (string) $blacklist : '<span class="opacitymedium">0</span>').'</td>';
				print '</tr>';
			}
			print '</tbody>';
			print '</table>';
		}
		break;

	case 'aiconsole_log':
		aiconsoleSectionHead('aiconsole_log');

		$logRows = array(
			array(
				'label' => 'AI_LOG_REQUESTS',
				'value' => $nblogs > 0 ? $langs->trans('AiConsoleLogCount', $nblogs) : $langs->trans('AiConsoleLogEmpty'),
				'badge' => aiconsoleBadge($logEnabled ? 1 : 0, $logEnabled ? $langs->trans('AiConsoleEnabled') : $langs->trans('AiConsoleDisabled')),
			),
			array(
				'label' => 'AI_LOG_RETENTION',
				'value' => $logRetention > 0 ? $langs->trans('AiConsoleRetentionDays', $logRetention) : $langs->trans('AiConsoleRetentionForever'),
				'badge' => aiconsoleBadge(1, (string) $logRetention),
			),
			array(
				'label' => $langs->trans('AiConsoleLastRequest'),
				'value' => $lastrequest > 0 ? dol_print_date($lastrequest, 'short') : $langs->trans('AiConsoleLogEmpty'),
				'badge' => aiconsoleBadge($lastrequest > 0 ? 1 : 0, $lastrequest > 0 ? $langs->trans('AiConsoleConfigured') : $langs->trans('AiConsoleNotConfigured')),
			),
		);
		aiconsoleSettingTable($logRows);

		print load_fiche_titre($langs->trans('AiConsoleRecentLog'), '', '');

		if (empty($recentlogs)) {
			print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleLogEmpty')).'</div>';
		} else {
			print '<table class="noborder centpercent">';
			print '<thead>';
			print '<tr class="liste_titre">';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleLogDate')).'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleToolName')).'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleLogProvider')).'</td>';
			print '<td class="center">'.dol_escape_htmltag($langs->trans('AiConsoleStatus')).'</td>';
			print '<td class="right">'.dol_escape_htmltag($langs->trans('AiConsoleLogDuration')).'</td>';
			print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleLogError')).'</td>';
			print '</tr>';
			print '</thead>';
			print '<tbody>';
			foreach ($recentlogs as $recent) {
				// status is varchar(50) holding a translated label, not a numeric code.
				// Compared the same way ai/admin/log_viewer.php compares it.
				$status = isset($recent['status']) ? (string) $recent['status'] : '';
				$statusLevel = 0;
				if ($status !== '' && $status == $langs->transnoentitiesnoconv('Success')) {
					$statusLevel = 1;
				} elseif ($status !== '' && $status == $langs->transnoentitiesnoconv('Confirm')) {
					$statusLevel = 2;
				} elseif ($status !== '' && $status == $langs->transnoentitiesnoconv('Error')) {
					$statusLevel = 3;
				}
				$err = isset($recent['error_msg']) ? (string) $recent['error_msg'] : '';

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.dol_escape_htmltag(!empty($recent['date_request']) ? dol_print_date($recent['date_request'], 'short') : '-').'</td>';
				print '<td class="tdoverflowmax200">'.dol_escape_htmltag(!empty($recent['tool_name']) ? $recent['tool_name'] : '-').'</td>';
				print '<td>'.dol_escape_htmltag(!empty($recent['provider']) ? $recent['provider'] : '-').'</td>';
				print '<td class="center">'.aiconsoleBadge($statusLevel, $status !== '' ? $status : '-').'</td>';
				print '<td class="right nowrap">'.(isset($recent['execution_time']) ? dol_escape_htmltag((string) $recent['execution_time']) : '-').'</td>';
				print '<td class="tdoverflowmax200">'.($err === '' ? '<span class="opacitymedium">-</span>' : dol_escape_htmltag(dol_trunc($err, 150))).'</td>';
				print '</tr>';
			}
			print '</tbody>';
			print '</table>';
		}
		break;

	case 'aiconsole_logtool':
		/*
		 * MCP log tool - the only writable part of this module
		 */

		print load_fiche_titre($langs->trans('AiConsoleLogToolSection'), '', '');

		print '<div class="warning">'.dol_escape_htmltag($langs->trans('AiConsoleLogToolWarning')).'</div>';

		// --- Gate state readout -----------------------------------------------------
		print '<table class="noborder centpercent">';
		print '<thead>';
		print '<tr class="liste_titre">';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleGate')).'</td>';
		print '<td class="center">'.dol_escape_htmltag($langs->trans('AiConsoleStatus')).'</td>';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleDetail')).'</td>';
		print '</tr>';
		print '</thead>';
		print '<tbody>';

		$logtoolEnabled = (int) getDolGlobalInt('AICONSOLE_ENABLE_MCP_LOGTOOL');
		$hasRight = $user->hasRight('aiconsole', 'mcp_logtool->read');

		$gates = array(
			array(
				'label' => $langs->trans('AiConsoleGateConfig'),
				'on' => $logtoolEnabled ? 1 : 0,
				'ok' => $logtoolEnabled ? 1 : 0,
				'detail' => 'AICONSOLE_ENABLE_MCP_LOGTOOL',
			),
			array(
				'label' => $langs->trans('AiConsoleGateRight'),
				'on' => $hasRight ? 1 : 0,
				// Flagged as a warning when the switch is on but no right is held: the
				// tool would be advertised by tools/list yet refuse every call.
				'ok' => ($hasRight || !$logtoolEnabled) ? 1 : 2,
				'detail' => $hasRight ? $user->login : $langs->trans('AiConsoleGateRightMissing'),
			),
			array(
				'label' => $langs->trans('AiConsoleGateMcp'),
				'on' => $mcpEnabled ? 1 : 0,
				'ok' => $mcpEnabled ? 1 : 0,
				'detail' => $langs->trans('AiConsoleGateMcpDetail'),
			),
		);
		foreach ($gates as $gate) {
			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($gate['label']).'</td>';
			print '<td class="center">'.aiconsoleBadge($gate['ok'], $gate['on'] ? $langs->trans('AiConsoleOpen') : $langs->trans('AiConsoleClosed')).'</td>';
			print '<td class="tdoverflowmax200">'.dol_escape_htmltag($gate['detail']).'</td>';
			print '</tr>';
		}
		print '</tbody>';
		print '</table>';
		print '<br>';

		// The form posts back to this same section of this same page, so the section
		// survives the round trip without any redirect.
		$formurl = dol_escape_htmltag(aiconsoleSectionUrl('aiconsole_logtool'));

		// --- Switch -----------------------------------------------------------------
		// A form on its own row, matching how core renders a lone save button rather
		// than letting it float in the table flow.
		print '<form method="post" action="'.$formurl.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="toggle_logtool">';
		print '<input type="hidden" name="enable" value="'.($logtoolEnabled ? 0 : 1).'">';
		print '<input class="button '.($logtoolEnabled ? 'button-cancel' : 'button-edit').'" type="submit" value="'.dol_escape_htmltag($logtoolEnabled ? $langs->trans('AiConsoleLogToolDisable') : $langs->trans('AiConsoleLogToolEnable')).'">';
		print '</form>';
		print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleLogToolSwitchHint')).'</div>';
		print '<br><br>';

		// --- Preview ----------------------------------------------------------------
		// POST rather than a GET link on purpose: a token in the query string ends up in
		// the webserver access log and in the Referer header.
		print load_fiche_titre($langs->trans('AiConsolePreviewResult'), '', '');
		print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsolePreviewIntro')).'</div>';
		print '<form method="post" action="'.$formurl.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="preview_logtool">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsolePreviewSource')).'</td>';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsolePreviewHours')).'</td>';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsolePreviewLimit')).'</td>';
		print '<td></td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td><select name="preview_source" class="flat minwidth200">';
		foreach (array('ai_requests', 'events', 'syslog', 'ai_debug_file') as $src) {
			print '<option value="'.$src.'">'.dol_escape_htmltag($src).'</option>';
		}
		print '</select></td>';
		print '<td><input type="number" name="preview_hours" value="24" min="1" max="'.AiConsoleLogTool::MAX_HOURS.'" class="width50"></td>';
		print '<td><input type="number" name="preview_limit" value="10" min="1" max="'.AiConsoleLogTool::MAX_LIMIT.'" class="width50"></td>';
		print '<td class="right"><input class="button button-edit" type="submit" value="'.dol_escape_htmltag($langs->trans('AiConsolePreviewRun')).'"></td>';
		print '</tr>';
		print '</table>';
		print '</form>';
		print '<br>';

		if ($previewResult !== null) {
			print '<pre class="'.(isset($previewResult['error']) ? 'error' : 'flat').'">';
			print dol_escape_htmltag(json_encode($previewResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			print '</pre>';
		}
		break;

	case 'aiconsole_overview':
	default:
		print '<div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleHelp')).'</div>';
		print '<br>';

		print '<table class="noborder centpercent">';
		print '<thead>';
		print '<tr class="liste_titre">';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleFeature')).'</td>';
		print '<td class="center">'.dol_escape_htmltag($langs->trans('AiConsoleStatus')).'</td>';
		print '<td>'.dol_escape_htmltag($langs->trans('AiConsoleDetail')).'</td>';
		print '<td class="right">'.dol_escape_htmltag($langs->trans('AiConsoleAction')).'</td>';
		print '</tr>';
		print '</thead>';
		print '<tbody>';

		foreach ($rows as $row) {
			// The overview links INSIDE the console. Jumping to the core editor is the
			// job of the "go configure" button that sits inside each section.
			$url = aiconsoleSectionUrl($row['leftmenu']);
			print '<tr class="oddeven">';
			print '<td>'.dol_escape_htmltag($row['label']).'</td>';
			print '<td class="center">'.$row['badge'].(empty($row['extra']) ? '' : '<br>'.$row['extra']).'</td>';
			print '<td class="tdoverflowmax200">'.dol_escape_htmltag($row['value']).'</td>';
			print '<td class="right nowrap">'.($aienabled ? '<a class="button" href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($langs->trans('AiConsoleDetailView')).'</a>' : '').'</td>';
			print '</tr>';
		}

		print '</tbody>';
		print '</table>';

		print '<br><div class="opacitymedium">'.dol_escape_htmltag($langs->trans('AiConsoleFooterNote')).'</div>';
		break;
	}
}