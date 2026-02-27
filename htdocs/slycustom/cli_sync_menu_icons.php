#!/usr/bin/env php
<?php
/**
 * CLI script to sync SLY Custom menu icons (Search Order, SLY Exports) in llx_menu.
 * Run from repo root: php htdocs/slycustom/cli_sync_menu_icons.php
 * Or from htdocs: php slycustom/cli_sync_menu_icons.php
 * Requires main.inc.php (Dolibarr env + DB).
 */
$res = 0;
if (!isset($argv) || !is_array($argv)) {
	$argv = array();
}
if (php_sapi_name() !== 'cli') {
	die('Run this script from command line only.');
}
$path = dirname(dirname(__FILE__));
if (file_exists($path.'/main.inc.php')) {
	require_once $path.'/main.inc.php';
} elseif (file_exists($path.'/../main.inc.php')) {
	require_once $path.'/../main.inc.php';
} else {
	echo "Error: main.inc.php not found. Run from Dolibarr htdocs or repo root.\n";
	exit(1);
}
if (!isModEnabled('slycustom')) {
	echo "SLY Custom module is not enabled.\n";
	exit(1);
}
require_once DOL_DOCUMENT_ROOT.'/slycustom/core/modules/modSlyCustom.class.php';
$module = new modSlyCustom($db);
$err = $module->syncMenuPrefixes();
if ($err == 0) {
	echo "OK: Menu icons synced. Refresh the Tools or Commerce page to see them.\n";
	exit(0);
} else {
	echo "Error: syncMenuPrefixes failed (".$err.").\n";
	exit(1);
}
