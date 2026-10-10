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
 * \file       htdocs/custom/aiconsole/class/AiConsoleLogTool.class.php
 * \ingroup    aiconsole
 * \brief      Read-only MCP tool letting an AI agent read Dolibarr diagnostics.
 *
 * Four independent gates must all be open before a single byte is returned:
 *
 *   1. Right 'aiconsole->mcp_logtool->read'  - declared with [3] = 0, so granted to
 *      NO group by default. An administrator must tick it explicitly.
 *   2. Constant AICONSOLE_ENABLE_MCP_LOGTOOL  - absent by default. If it is absent
 *      the tool is never even constructed (see ActionsAiconsole::addMcpTools).
 *   3. Both re-checked inside execute()          - so bypassing the admin UI or the
 *      configuration screen changes nothing.
 *   4. Bounded output: entity filter, fixed column lists, clamped row and time
 *      windows, truncated fields and a mandatory scrub pass.
 *
 * Explicit non-goals, because each would defeat the point of the above:
 *   - PHP error_log is NOT read. Its path is chosen by php.ini, outside any Dolibarr
 *     permission model; reading it would bypass gates 1-3 entirely.
 *   - Prompt and response bodies are NOT returned. llx_ai_request_log stores them in
 *     query_text / raw_request_payload / raw_response_payload; those three columns are
 *     never selected. dolibarr_ai.log is metadata plus its 'Error:' lines only,
 *     because ai/class/ai.class.php writes the full Authorization header into it.
 *   - isSystem() stays false. Returning true would exempt this tool from the
 *     allow-list check in McpHandler::executeTool().
 */

// McpTool is loaded unconditionally by ai/class/mcp.class.php. Required for the
// class declaration below, so this file is not standalone-loadable.
if (!class_exists('McpTool')) {
	require_once DOL_DOCUMENT_ROOT.'/ai/class/mcptool.class.php';
}

/**
 * Class AiConsoleLogTool
 *
 * A single read-only MCP tool over four Dolibarr log sources.
 *
 * One tool rather than four: the admin allow-list gains one entry instead of four,
 * and the model cannot wander between log readers.
 */
class AiConsoleLogTool extends McpTool
{
	/** @var string Name advertised to the MCP client and used in the allow-list */
	const TOOL_NAME = 'aiconsole_get_logs';

	/** @var int Hard ceiling on returned rows, whatever the caller asks for */
	const MAX_LIMIT = 50;

	/** @var int Hard ceiling on the lookback window, in hours (7 days) */
	const MAX_HOURS = 168;

	/** @var int Hard ceiling on characters returned for any single string field */
	const MAX_FIELD_LEN = 300;

	/** @var int Bytes read from the tail of the syslog file (whole file is never loaded) */
	const SYSLOG_TAIL_BYTES = 131072;

	/**
	 * @var string[] Log lines are returned only if they carry one of these markers
	 *
	 * Verified against the whole tree: the core ai module emits exactly two markers,
	 * '[McpHandler] ' (9 distinct messages) and '[MCP] Internal error: '.
	 * The second one matters most - it is the generic failure path - so both are here.
	 * Matching is case sensitive, hence the two spellings of the MCP prefix.
	 * '[AiConsole]' covers this module's own diagnostics once it starts logging.
	 */
	const SYSLOG_MARKERS = array('[McpHandler]', '[MCP]', '[AiConsole]');

	/**
	 * {@inheritDoc}
	 *
	 * Must remain false. McpHandler::executeTool() only enforces the allow-list for
	 * non-system tools, so a true here would make the admin allow-list irrelevant.
	 *
	 * @return bool
	 */
	public function isSystem()
	{
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int,array<string,mixed>> List of tool definitions
	 */
	public function getDefinitions(): array
	{
		return array(
			array(
				'name' => self::TOOL_NAME,
				'description' => 'Read recent Dolibarr diagnostics for troubleshooting. '
					.'Read-only. Requires the calling account to hold the "aiconsole->mcp_logtool->read" right. '
					."source: 'ai_requests' (AI request log, metadata and error messages only), "
					."'events' (security/audit events), "
					."'syslog' (Dolibarr file log, AI-related lines only), "
					."'ai_debug_file' (metadata of the AI debug file plus its error lines). "
					.'Prompt and response bodies are never returned.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => array(
						'source' => array(
							'type' => 'string',
							'enum' => array('ai_requests', 'events', 'syslog', 'ai_debug_file'),
							'description' => 'Which log to read.',
						),
						'hours' => array(
							'type' => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_HOURS,
							'description' => 'How far back to look, in hours. Capped at '.self::MAX_HOURS.'.',
						),
						'limit' => array(
							'type' => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_LIMIT,
							'description' => 'Maximum rows to return. Capped at '.self::MAX_LIMIT.'.',
						),
					),
					'required' => array('source'),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string> Categories this tool belongs to
	 */
	public function getCategories(): array
	{
		return array('diagnostics');
	}

	/**
	 * Execute the tool.
	 *
	 * @param string               $toolName Name of the tool being executed
	 * @param array<string,mixed>  $args     Caller-supplied arguments
	 * @return array<string,mixed>  Structured result, or array('error' => ...) on refusal
	 */
	public function execute(string $toolName, array $args)
	{
		if ($toolName !== self::TOOL_NAME) {
			return array('error' => "Tool '".$toolName."' is not provided by this class.");
		}

		$denied = $this->checkAccess();
		if ($denied !== null) {
			return array('error' => $denied);
		}

		// From here on every argument is clamped before use. Nothing below trusts the
		// caller: $source is matched against a fixed enum, and the two numbers are
		// cast with an explicit min/max so no oversized value can reach SQL or a loop.
		$source = isset($args['source']) && is_string($args['source']) ? $args['source'] : '';
		if (!in_array($source, array('ai_requests', 'events', 'syslog', 'ai_debug_file'), true)) {
			return array('error' => "Invalid 'source'. Expected one of: ai_requests, events, syslog, ai_debug_file.");
		}

		$limit = $this->clamp($args, 'limit', self::MAX_LIMIT);
		$hours = $this->clamp($args, 'hours', self::MAX_HOURS);

		switch ($source) {
			case 'ai_requests':
				return $this->readAiRequests($hours, $limit);
			case 'events':
				return $this->readEvents($hours, $limit);
			case 'syslog':
				return $this->readSyslog($limit);
			case 'ai_debug_file':
			default:
				return $this->readAiDebugFile($limit);
		}
	}

	/**
	 * Gates 1 and 3, plus the multicompany check.
	 *
	 * Returns null when access is granted, or a refusal message. Note that gate 2 is
	 * checked here too: a caller can hold the right while the constant has since been
	 * switched off, and the switch must take effect immediately.
	 *
	 * @return string|null Refusal message, or null when allowed
	 */
	private function checkAccess()
	{
		if (!getDolGlobalString('AICONSOLE_ENABLE_MCP_LOGTOOL')) {
			return 'Permission Denied: the AI console MCP log tool is disabled (constant AICONSOLE_ENABLE_MCP_LOGTOOL is not set).';
		}

		if (!is_object($this->user) || !$this->user->hasRight('aiconsole', 'mcp_logtool->read')) {
			return 'Permission Denied: this account is not granted the right aiconsole->mcp_logtool->read.';
		}

		// Multicompany: the tool must never return rows belonging to another entity.
		// An entity of 0 on the account means "all entities" (Dolibarr convention for
		// administrators), which is accepted; otherwise the current entity must be listed.
		$userentities = (array) $this->user->entity;
		if (!in_array((int) $this->conf->entity, $userentities, true) && !in_array(0, $userentities, true)) {
			return 'Permission Denied: this account does not cover entity '.(int) $this->conf->entity.'.';
		}

		return null;
	}

	/**
	 * Clamp a caller-supplied integer into [1, $max].
	 *
	 * @param array<string,mixed> $args Caller arguments
	 * @param string              $key  Key to read
	 * @param int                 $max  Inclusive upper bound
	 * @return int
	 */
	private function clamp(array $args, $key, $max)
	{
		$value = isset($args[$key]) ? (int) $args[$key] : $max;
		if ($value < 1) {
			$value = 1;
		}
		if ($value > $max) {
			$value = $max;
		}
		return $value;
	}

	/**
	 * Build a lower date bound. Computed from a clamped integer, so the result is a
	 * value this function produced, never a caller-supplied string.
	 *
	 * @param int $hours Lookback in hours
	 * @return string 'Y-m-d H:i:s'
	 */
	private function sinceDate($hours)
	{
		return date('Y-m-d H:i:s', time() - ($hours * 3600));
	}

	/**
	 * Source 'ai_requests': recent rows of the AI request log.
	 *
	 * The column list is fixed and deliberately excludes query_text,
	 * raw_request_payload and raw_response_payload, which hold the full prompt and
	 * the full model response. Selecting '*' here would leak the business content of
	 * every AI interaction.
	 *
	 * @param int $hours Lookback in hours
	 * @param int $limit Maximum rows
	 * @return array<string,mixed>
	 */
	private function readAiRequests($hours, $limit)
	{
		$tablename = $this->db->prefix().'ai_request_log';
		$sql = 'SELECT t.rowid, t.date_request, t.tool_name, t.provider, t.status,';
		$sql .= ' t.execution_time, t.error_msg';
		$sql .= ' FROM '.$tablename.' t';
		$sql .= ' WHERE t.entity = '.(int) $this->conf->entity;
		$sql .= ' AND t.date_request >= \''.$this->sinceDate($hours).'\'';
		$sql .= ' ORDER BY t.rowid DESC';
		$sql .= ' LIMIT '.(int) $limit;

		return array('source' => 'ai_requests', 'rows' => $this->fetchRows($sql, array('error_msg')));
	}

	/**
	 * Source 'events': recent security and audit events.
	 *
	 * The ip column is not selected: it is personal data and adds nothing to
	 * diagnosing an AI or MCP problem.
	 *
	 * @param int $hours Lookback in hours
	 * @param int $limit Maximum rows
	 * @return array<string,mixed>
	 */
	private function readEvents($hours, $limit)
	{
		$tablename = $this->db->prefix().'events';
		$sql = 'SELECT e.rowid, e.type, e.dateevent, e.fk_user, e.description';
		$sql .= ' FROM '.$tablename.' e';
		$sql .= ' WHERE e.entity = '.(int) $this->conf->entity;
		$sql .= ' AND e.dateevent >= \''.$this->sinceDate($hours).'\'';
		$sql .= ' ORDER BY e.rowid DESC';
		$sql .= ' LIMIT '.(int) $limit;

		return array('source' => 'events', 'rows' => $this->fetchRows($sql, array('description')));
	}

	/**
	 * Source 'syslog': AI-related lines from the Dolibarr file log.
	 *
	 * This is where McpHandler's own dol_syslog() diagnostics land - llx_events is
	 * NOT the destination of dol_syslog, it holds security events only. Only the tail
	 * of the file is read, and only lines carrying an AI marker are returned.
	 *
	 * @param int $limit Maximum lines returned
	 * @return array<string,mixed>
	 */
	private function readSyslog($limit)
	{
		$logfile = $this->resolveSyslogFile();
		if ($logfile === null) {
			return array(
				'source' => 'syslog',
				'available' => false,
				'reason' => 'Syslog file handler not enabled (SYSLOG_HANDLERS does not include mod_syslog_file).',
				'lines' => array(),
			);
		}
		if (!is_file($logfile) || !is_readable($logfile)) {
			return array('source' => 'syslog', 'available' => false, 'reason' => 'Log file not readable.', 'lines' => array());
		}

		$handle = @fopen($logfile, 'r');
		if (!$handle) {
			return array('source' => 'syslog', 'available' => false, 'reason' => 'Log file could not be opened.', 'lines' => array());
		}

		// Seek to the tail instead of reading the whole file: a busy instance can
		// produce a multi-megabyte log and loading it whole would be both a memory
		// problem and an uncontrolled amount of data for the model to chew on.
		$filesize = filesize($logfile);
		$offset = ($filesize > self::SYSLOG_TAIL_BYTES) ? ($filesize - self::SYSLOG_TAIL_BYTES) : 0;
		if ($offset > 0) {
			fseek($handle, $offset);
		}
		$chunk = fread($handle, self::SYSLOG_TAIL_BYTES);
		fclose($handle);

		if ($chunk === false || $chunk === '') {
			return array('source' => 'syslog', 'available' => true, 'lines' => array());
		}

		$lines = array();
		foreach (preg_split('/\r\n|\r|\n/', $chunk) as $line) {
			if ($line === '' || !$this->hasAiMarker($line)) {
				continue;
			}
			$lines[] = $this->scrub($line);
			if (count($lines) >= $limit) {
				break;
			}
		}

		return array(
			'source' => 'syslog',
			'available' => true,
			'truncated_at_head' => ($offset > 0),
			'lines' => $lines,
		);
	}

	/**
	 * Source 'ai_debug_file': metadata of the AI debug file plus its error lines.
	 *
	 * ai/class/ai.class.php writes the whole Authorization header into this file with
	 * var_export() when AI_DEBUG is on, with no masking (the substr($apiKey, 0, 5)
	 * mask applies to the dol_syslog line only). So the body is never read: only the
	 * file's size and mtime, and the lines that start with 'Error: '.
	 *
	 * @param int $limit Maximum error lines returned
	 * @return array<string,mixed>
	 */
	private function readAiDebugFile($limit)
	{
		$file = DOL_DATA_ROOT.'/dolibarr_ai.log';
		if (!is_file($file)) {
			return array(
				'source' => 'ai_debug_file',
				'enabled' => false,
				'hint' => 'File absent. Either AI_DEBUG is off, or no AI call has run since it was enabled.',
				'errors' => array(),
			);
		}

		$out = array(
			'source' => 'ai_debug_file',
			'enabled' => true,
			'size_bytes' => (int) filesize($file),
			'modified' => date('c', (int) filemtime($file)),
			'errors' => array(),
		);

		$handle = @fopen($file, 'r');
		if (!$handle) {
			return $out;
		}

		$filesize = filesize($file);
		$offset = ($filesize > self::SYSLOG_TAIL_BYTES) ? ($filesize - self::SYSLOG_TAIL_BYTES) : 0;
		if ($offset > 0) {
			fseek($handle, $offset);
		}
		$chunk = fread($handle, self::SYSLOG_TAIL_BYTES);
		fclose($handle);

		if ($chunk === false || $chunk === '') {
			return $out;
		}

		foreach (preg_split('/\r\n|\r|\n/', $chunk) as $line) {
			// Only the error section. The prompt, payload and response sections of
			// this file are deliberately never touched.
			if (strpos(ltrim($line), 'Error: ') !== 0) {
				continue;
			}
			$out['errors'][] = $this->scrub($line);
			if (count($out['errors']) >= $limit) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Resolve the configured syslog file path.
	 *
	 * @return string|null Absolute path, or null when the file handler is not active
	 */
	private function resolveSyslogFile()
	{
		$handlers = getDolGlobalString('SYSLOG_HANDLERS');
		if (empty($handlers)) {
			return null;
		}
		$decoded = json_decode($handlers, true);
		if (!is_array($decoded) || !in_array('mod_syslog_file', $decoded, true)) {
			return null;
		}

		$path = getDolGlobalString('SYSLOG_FILE', 'DOL_DATA_ROOT/dolibarr.log');
		return str_replace('DOL_DATA_ROOT', DOL_DATA_ROOT, $path);
	}

	/**
	 * Whether a syslog line looks like it came from the AI or MCP stack.
	 *
	 * @param string $line Raw log line
	 * @return bool
	 */
	private function hasAiMarker($line)
	{
		foreach (self::SYSLOG_MARKERS as $marker) {
			if (strpos($line, $marker) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run a SELECT and return scrubbed rows.
	 *
	 * @param string   $sql          Fully built query, no caller input reaches it
	 * @param string[] $textcolumns  Columns whose values must be scrubbed and truncated
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchRows($sql, $textcolumns)
	{
		$out = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $out;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$row = array();
			foreach ((array) $obj as $key => $value) {
				if (in_array($key, $textcolumns, true)) {
					$row[$key] = ($value === null) ? null : $this->scrub($value);
				} else {
					$row[$key] = $value;
				}
			}
			$out[] = $row;
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Redact anything that looks like a credential, then truncate.
	 *
	 * Applied to every string this tool returns, regardless of source. Defence in
	 * depth: even if a future column or a new log line carries a secret, it does not
	 * reach the model intact.
	 *
	 * @param string $text Raw text
	 * @return string
	 */
	private function scrub($text)
	{
		$text = (string) $text;

		// Authorization headers, in headers arrays or in free text.
		$text = preg_replace('/(Bearer\s+)\S+/i', '$1***', $text);
		// key=value / key: value pairs for the usual credential names. The credential
		// charset stops at quotes, braces, brackets and commas: \S+ would eat the
		// closing delimiter along with the secret and leave the surrounding JSON or
		// header array syntactically broken.
		$text = preg_replace('/((?:api[_-]?key|apikey|secret|token|password|passwd|pwd)["\']?\s*[=:]\s*["\']?)[^\s"\';,)\]}]+/i', '$1***', $text);
		// Vendor-prefixed keys such as sk-... and pk-...
		$text = preg_replace('/\b(?:sk|pk|ghp|xox[abps])-[A-Za-z0-9_\-]{8,}/', 'sk-***', $text);
		// Long opaque blobs: base64, hex digests, JWT bodies.
		$text = preg_replace('/\b[A-Za-z0-9+\/]{40,}={0,2}\b/', '***', $text);

		return dol_trunc($text, self::MAX_FIELD_LEN);
	}
}