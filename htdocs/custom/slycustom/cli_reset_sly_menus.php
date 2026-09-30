#!/usr/bin/env php
<?php
/**
 * CLI script to remove all SLY Custom menu entries and re-insert them from modSlyCustom.class.php.
 * Use this to clean leftover/duplicate entries (e.g. old "SLY ALL-in-One" or wrong hierarchy).
 *
 * Run from repo root: php htdocs/custom/slycustom/cli_reset_sly_menus.php
 * Or from htdocs: php custom/slycustom/cli_reset_sly_menus.php
 * Requires main.inc.php (Dolibarr env + DB). SLY Custom must be enabled.
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
	echo "SLY Custom module is not enabled. Enable it first, then run this script.\n";
	exit(1);
}
require_once DOL_DOCUMENT_ROOT.'/custom/slycustom/core/modules/modSlyCustom.class.php';
$module = new modSlyCustom($db);
$err = $module->delete_menus();
if ($err) {
	echo "Error: delete_menus failed (".$err."). ".$module->error."\n";
	exit(1);
}
$err = $module->insert_menus();
if ($err) {
	echo "Error: insert_menus failed (".$err."). ".$module->error."\n";
	exit(1);
}
echo "OK: SLY Custom menus reset (old entries removed, current definitions re-inserted). Refresh Tools page to see the menu.\n";
exit(0);
