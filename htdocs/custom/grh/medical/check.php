<?php

if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
dol_include_once('/grh/medical/class/medical.class.php');

$role = $_POST['role'];
$year = $_POST['year'];
$medicalu = new medical($db);
$month = $medicalu->get_part_datec('month','AND year(`datec`) = '.$year);
echo json_encode($month);

