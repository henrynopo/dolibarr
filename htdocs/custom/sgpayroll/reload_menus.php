<?php
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/core/modules/modSGPayroll.class.php';

if (!$user->admin) accessforbidden();

$mod = new modSGPayroll($db);
$mod->init();

echo "SUCCESS: Module initialized and menus regenerated.\n";
