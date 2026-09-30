<?php
require_once '../../main.inc.php';
dol_include_once('sgpayroll/core/modules/modSGPayroll.class.php');

if (!$user->admin) accessforbidden();

$mod = new modSGPayroll($db);
$mod->init();

echo "SUCCESS: Module initialized and menus regenerated.\n";
