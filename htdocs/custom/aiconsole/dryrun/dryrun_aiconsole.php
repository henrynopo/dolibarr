<?php
/**
 * Dry-run harness for AiConsoleLogTool. Loads the real class with a stubbed McpTool
 * base so the security-critical methods can be exercised without a Dolibarr bootstrap.
 *
 * Run: php dryrun_aiconsole.php
 */

define('DOL_DOCUMENT_ROOT', __DIR__);
define('DOL_DATA_ROOT', sys_get_temp_dir());

// Minimal stand-in for ai/class/mcptool.class.php. The real one is standalone, but
// stubbing keeps this harness independent of the Dolibarr tree.
abstract class McpTool
{
	protected $db;
	protected $user;
	protected $conf;

	public function __construct($db, $user, $conf)
	{
		$this->db = $db;
		$this->user = $user;
		$this->conf = $conf;
	}
	abstract public function getDefinitions(): array;
	abstract public function execute(string $toolName, array $args);
}

/**
 * Core helpers the class touches, reduced to what a dry-run needs. Overridable so the
 * gate tests can force the "switch on" branch without a database.
 */
$GLOBALS['DRYRUN_CONSTANTS'] = array();

if (!function_exists('dol_trunc')) {
	/**
	 * Copy of core dol_trunc() semantics: keep $size characters plus an ellipsis.
	 *
	 * @param string $text Source text
	 * @param int    $size Maximum length
	 * @return string
	 */
	function dol_trunc($text, $size = 50)
	{
		$text = (string) $text;
		// mbstring is absent from some CLI builds; byte length is close enough for
		// ASCII fixtures and keeps the harness runnable everywhere.
		$len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
		if ($len > $size) {
			return substr($text, 0, $size).'...';
		}
		return $text;
	}
}

if (!function_exists('getDolGlobalString')) {
	/**
	 * @param string $key    Constant name
	 * @param string $default Fallback
	 * @return string
	 */
	function getDolGlobalString($key, $default = '')
	{
		return isset($GLOBALS['DRYRUN_CONSTANTS'][$key]) ? $GLOBALS['DRYRUN_CONSTANTS'][$key] : $default;
	}
}

if (!function_exists('isModEnabled')) {
	/**
	 * @param string $name Module name
	 * @return bool
	 */
	function isModEnabled($name)
	{
		return true;
	}
}

/**
 * Stub account, carrying only what the gates look at.
 */
class DryrunUser
{
	/** @var bool */
	public $granted;

	/** @var int Entity the account sits in, 0 meaning "all entities" */
	public $entity;

	/**
	 * @param bool $granted  Whether aiconsole->mcp_logtool->read is held
	 * @param int  $entity   Entity of the account
	 */
	public function __construct($granted, $entity)
	{
		$this->granted = $granted;
		$this->entity = $entity;
	}

	/**
	 * @param string $module Module name
	 * @param string $right1 First right part
	 * @param string $right2 Second right part
	 * @return bool
	 */
	public function hasRight($module, $right1, $right2 = '')
	{
		return $this->granted;
	}
}

/**
 * Stub database. query() returns false so fetchRows() takes its empty-result path;
 * that is the case worth testing, since a real failure there must degrade to zero rows
 * rather than to a fatal or a partial result.
 */
class DryrunDb
{
	/** @var string[] Every SQL string the tool built, in order */
	public $queries = array();

	/**
	 * @return string Table prefix
	 */
	public function prefix()
	{
		return 'llxsf_';
	}

	/**
	 * @param string $sql Query
	 * @return false Always: stands for a failed query
	 */
	public function query($sql)
	{
		$this->queries[] = $sql;
		return false;
	}

	/**
	 * @param false $res Result set
	 * @return void
	 */
	public function free($res)
	{
	}
}

require __DIR__.'/../class/AiConsoleLogTool.class.php';

$fail = 0;
$pass = 0;

/**
 * @param string $label  What is being checked
 * @param mixed  $got    Actual
 * @param mixed  $expect Expected
 */
function check($label, $got, $expect)
{
	global $fail, $pass;
	if ($got === $expect) {
		$pass++;
		echo "  PASS  ".$label."\n";
	} else {
		$fail++;
		echo "  FAIL  ".$label."\n";
		echo "        expected: ".var_export($expect, true)."\n";
		echo "        got:      ".var_export($got, true)."\n";
	}
}

// Reflection to reach the private methods. setAccessible() is a no-op from PHP 8.1
// on and deprecated in 8.5, so it is only called on the versions that need it.
$tool = new AiConsoleLogTool(null, null, null);
$r = new ReflectionClass($tool);

$scrub = $r->getMethod('scrub');
$clamp = $r->getMethod('clamp');
if (PHP_VERSION_ID < 80100) {
	$scrub->setAccessible(true);
	$clamp->setAccessible(true);
}

echo "== redaction ==\n";
$cases = array(
	'Authorization header' => array('X-Api-Key Bearer sk-abc123def456ghi', 'X-Api-Key Bearer ***'),
	'bare bearer' => array('Authorization: Bearer sk-proj-AAAABBBBCCCCDDDD', 'Authorization: Bearer ***'),
	'apiKey equals' => array('Call API for apiKey=sk-live-XYZ987654, model=x', 'Call API for apiKey=***, model=x'),
	'api_key json' => array('{"api_key": "sk-live-QQQQWWWW11112222"}', '{"api_key": "***"}'),
	'snake secret' => array("secret='topsecretvalue123'", "secret='***'"),
	'password colon' => array('password: hunter2hunter2', 'password: ***'),
	'plain text kept' => array('Call API for apiEndpoint=https://x/v1, model=gpt', 'Call API for apiEndpoint=https://x/v1, model=gpt'),
	'long base64' => array('hash abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJ end', 'hash *** end'),
);
foreach ($cases as $label => $case) {
	check($label, $scrub->invoke($tool, $case[0]), $case[1]);
}

echo "\n== truncation ==\n";
// Spaces every few characters, so the long-blob rule does not fire and the value
// really reaches dol_trunc() at full length.
$long = str_repeat('alpha bravo ', 100);
check('300 char cap', strlen($scrub->invoke($tool, $long)), 303);
check('cap ends with ellipsis', substr($scrub->invoke($tool, $long), -3), '...');

echo "\n== clamps ==\n";
check('limit 9999 -> 50', $clamp->invoke($tool, array('limit' => 9999), 'limit', 50), 50);
check('limit -5 -> 1', $clamp->invoke($tool, array('limit' => -5), 'limit', 50), 1);
check('limit absent -> 50', $clamp->invoke($tool, array(), 'limit', 50), 50);
check('hours 99999 -> 168', $clamp->invoke($tool, array('hours' => 99999), 'hours', 168), 168);
check('hours 24 -> 24', $clamp->invoke($tool, array('hours' => 24), 'hours', 168), 24);

echo "\n== isSystem must be false (true would bypass the allow-list) ==\n";
check('isSystem', $tool->isSystem(), false);

echo "\n== wrong tool name refused, gate or not ==\n";
check('wrong tool name refused', $tool->execute('other_tool', array('source' => 'events')), array('error' => "Tool 'other_tool' is not provided by this class."));

echo "\n== gate 2: constant off refuses, whatever else is true ==\n";
// Default state: no constant at all. A fully privileged account is still refused.
$privileged = new DryrunUser(true, 0);
$tool2 = new AiConsoleLogTool(null, $privileged, (object) array('entity' => 1));
$res = $tool2->execute('aiconsole_get_logs', array('source' => 'ai_requests'));
check('off => refused', isset($res['error']), true);
check('off => names the constant', strpos($res['error'], 'AICONSOLE_ENABLE_MCP_LOGTOOL') !== false, true);
echo "        message: ".$res['error']."\n";

echo "\n== gate 1/3: constant on, right not held => refused ==\n";
$GLOBALS['DRYRUN_CONSTANTS']['AICONSOLE_ENABLE_MCP_LOGTOOL'] = '1';
$noRight = new DryrunUser(false, 0);
$tool3 = new AiConsoleLogTool(null, $noRight, (object) array('entity' => 1));
$res = $tool3->execute('aiconsole_get_logs', array('source' => 'ai_requests'));
check('no right => refused', isset($res['error']), true);
echo "        message: ".$res['error']."\n";

echo "\n== multicompany: account not covering the entity => refused ==\n";
// Right held, switch on, but the account only sits in entity 7 while the request
// runs in entity 1. This must be refused before any query is built.
$wrongEntity = new DryrunUser(true, 7);
$tool4 = new AiConsoleLogTool(null, $wrongEntity, (object) array('entity' => 1));
$res = $tool4->execute('aiconsole_get_logs', array('source' => 'ai_requests'));
check('entity mismatch => refused', isset($res['error']), true);
echo "        message: ".$res['error']."\n";

echo "\n== both gates open: passes the gates, then reaches the data layer ==\n";
$db = new DryrunDb();
$granted = new DryrunUser(true, 0);
$conf = (object) array('entity' => 1);
$tool5 = new AiConsoleLogTool($db, $granted, $conf);
$res = $tool5->execute('aiconsole_get_logs', array('source' => 'ai_requests'));
check('granted => reaches query', isset($res['source']) && $res['source'] === 'ai_requests', true);
check('granted => empty rows, no fatal', isset($res['rows']) && $res['rows'] === array(), true);

echo "\n== input validation, with the gates open ==\n";
// Only reachable once a caller is authorised, which is exactly why these checks come
// after the gates: an unauthorised caller learns nothing about the parameter surface.
$bad = "Invalid 'source'. Expected one of: ai_requests, events, syslog, ai_debug_file.";
check('path traversal refused', $tool5->execute('aiconsole_get_logs', array('source' => '../../etc/passwd')), array('error' => $bad));
check('missing source refused', $tool5->execute('aiconsole_get_logs', array()), array('error' => $bad));
check('array source refused', $tool5->execute('aiconsole_get_logs', array('source' => array('ai_requests'))), array('error' => $bad));
check('null source refused', $tool5->execute('aiconsole_get_logs', array('source' => null)), array('error' => $bad));

echo "\n== built SQL is entity-scoped, clamped and free of payload columns ==\n";
$db->queries = array();
$tool5->execute('aiconsole_get_logs', array('source' => 'ai_requests', 'limit' => 9999, 'hours' => 99999));
$sql = $db->queries[0];
check('limit clamped in SQL', strpos($sql, 'LIMIT 50') !== false, true);
check('no SELECT *', preg_match('/SELECT\s+\*/i', $sql) === 0, true);
check('entity filter present', strpos($sql, 't.entity = 1') !== false, true);
check('lookback bounded to 7 days', strpos($sql, date('Y-m-d H:i:s', time() - (168 * 3600))) !== false, true);
// The three columns that carry the full prompt and the full model response.
foreach (array('raw_request_payload', 'query_text', 'raw_response_payload') as $forbidden) {
	check('no '.$forbidden.' column', stripos($sql, $forbidden) === false, true);
}
echo "        SQL: ".$sql."\n";

$db->queries = array();
$tool5->execute('aiconsole_get_logs', array('source' => 'events', 'limit' => 5));
$sql = $db->queries[0];
check('events: no ip column', stripos($sql, 'e.ip') === false, true);
check('events: entity filter', strpos($sql, 'e.entity = 1') !== false, true);
echo "        SQL: ".$sql."\n";

echo "\n== every source reaches its reader without fatal ==\n";
foreach (array('ai_requests', 'events', 'syslog', 'ai_debug_file') as $src) {
	$res = $tool5->execute('aiconsole_get_logs', array('source' => $src));
	check('source '.$src, isset($res['source']) && $res['source'] === $src, true);
}

echo "\n== syslog marker filter matches what core actually writes ==\n";
// Every one of these strings is copied verbatim from a dol_syslog() call in the core
// ai module. If a marker is misspelled the filter silently returns nothing, which
// looks identical to "no errors happened".
$hasMarker = $r->getMethod('hasAiMarker');
if (PHP_VERSION_ID < 80100) {
	$hasMarker->setAccessible(true);
}
$coreLines = array(
	'[MCP] Internal error: something broke',
	'[McpHandler] Executing tool \DolibarrMcpTool\Navigation\SomeClass',
	'[McpHandler] Error executing tool \X: boom',
	'[McpHandler] Successfully registered MCP tool: Foo',
	'[McpHandler] A module provided a tool that is not an instance of McpTool',
	'[McpHandler] Attempted to load tool outside of allowed directories: X',
	'[McpHandler] MCP tools directory not found: /x',
);
foreach ($coreLines as $line) {
	check('kept: '.substr($line, 0, 46), $hasMarker->invoke($tool, $line), true);
}
// Business logs must stay out even though they are plentiful.
foreach (array(
	'2026-10-09 12:00:00 [DB] INSERT INTO llx_societe',
	'[McpHandler] is the only AI line',
) as $line) {
	$expected = (strpos($line, '[McpHandler]') !== false);
	check('decision for: '.substr($line, 0, 40), $hasMarker->invoke($tool, $line), $expected);
}
check('plain log line dropped', $hasMarker->invoke($tool, '[WARN] something in a normal module'), false);

echo "\n== tool definition shape ==\n";
$defs = $tool->getDefinitions();
check('one tool only', count($defs), 1);
check('tool name', $defs[0]['name'], 'aiconsole_get_logs');
check('required', $defs[0]['inputSchema']['required'], array('source'));
check('source enum', $defs[0]['inputSchema']['properties']['source']['enum'], array('ai_requests', 'events', 'syslog', 'ai_debug_file'));
check('limit max in schema', $defs[0]['inputSchema']['properties']['limit']['maximum'], 50);
check('hours max in schema', $defs[0]['inputSchema']['properties']['hours']['maximum'], 168);

echo "\n----------------------------------------\n";
echo ($fail === 0 ? "ALL ".$pass." CHECKS PASSED\n" : $fail." FAILED, ".$pass." passed\n");
exit($fail === 0 ? 0 : 1);