<?php
require_once '../../main.inc.php';
dol_include_once('sghr/core/modules/modSghr.class.php');

if (!$user->admin) accessforbidden();

$mod = new modSghr($db);
$mod->init();

echo "SUCCESS: Module initialized and menus regenerated.\n";
