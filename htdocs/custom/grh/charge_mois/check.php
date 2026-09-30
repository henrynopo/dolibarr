<?php
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
dol_include_once('/grh/charge_mois/class/charge_mois.class.php');

$year = $_POST['year'];
$chmois = new charge_mois($db);
$month = $chmois->get_part_date('month',' AND year(`datec`) = '.$year);
echo json_encode($month);