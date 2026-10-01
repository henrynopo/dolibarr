<?php
/**
 * SG Payroll - Menu Repair Tool
 * Visit this script in your browser to force-refresh all icons in the current entity.
 */
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Access control
if (!$user->admin) accessforbidden();

$entity = $conf->entity;
echo "<h1>SG Payroll - Menu Repair Tool</h1>";
echo "<p>Current Entity: <strong>$entity</strong></p>";

// 1. Load the module class to get the latest definitions
include_once __DIR__ . '/core/modules/modSghr.class.php';
$module = new modSghr($db);

if (empty($module->menu)) {
    echo "<p style='color:red;'>Error: Could not load menu definitions from modSghr.class.php</p>";
    exit;
}

echo "<h3>Step 1: Cleaning old menu records for entity $entity...</h3>";
$sqlClean = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE mainmenu = 'sghr' AND entity = ".$entity;
if ($db->query($sqlClean)) {
    echo "<p style='color:green;'>Success: Old records removed.</p>";
} else {
    echo "<p style='color:red;'>Error: Failed to clean records. " . $db->error() . "</p>";
}

echo "<h3>Step 2: Re-inserting menus from code...</h3>";
$res = $module->_init(array(), '');
if ($res > 0) {
    echo "<p style='color:green;'>Success: Menus re-inserted ($res records).</p>";
} else {
    echo "<p style='color:red;'>Error: Failed to re-insert menus.</p>";
}

echo "<h3>Step 3: Checking for icon presence in database...</h3>";
$sqlCheck = "SELECT rowid, titre, prefix, leftmenu FROM ".MAIN_DB_PREFIX."menu WHERE mainmenu = 'sghr' AND entity = ".$entity;
$resCheck = $db->query($sqlCheck);
if ($resCheck) {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Prefix (Icon HTML)</th><th>LeftMenu Key</th></tr>";
    while ($obj = $db->fetch_object($resCheck)) {
        echo "<tr>";
        echo "<td>".$obj->rowid."</td>";
        echo "<td>".htmlspecialchars($obj->titre)."</td>";
        echo "<td>".htmlspecialchars($obj->prefix)."</td>";
        echo "<td>".$obj->leftmenu."</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h3>Step 4: Clearing Menu Cache...</h3>";
if (isset($_SESSION['dol_menu'])) unset($_SESSION['dol_menu']);
if (function_exists('dol_syslog')) dol_syslog("Menu cache cleared by SG Payroll Repair Tool");
echo "<p style='color:green;'>Menu session cache cleared.</p>";

echo "<br><hr>";
echo "<h2>Done! Please refresh your browser page (Cmd+Shift+R) and check the menu.</h2>";
echo "<p><a href='../../index.php'>Go back to Home</a></p>";
