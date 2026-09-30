<?php
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
dol_include_once('/grh/avance_utilisateur/class/avance_utilisateur.class.php');

$role = $_POST['role'];

switch ($role) {
	case 'check_user':
		$year = date("Y", strtotime($_POST['date']));
		$month = date("m", strtotime($_POST['date']));
		$iduser = $_POST['user'];
		$cnssu = new avance_utilisateur($db);
		$is_found = $cnssu->fetchAll('','',0,0, ' AND idutilisateur='.$iduser.' AND MONTH(`datec`) = '.$month.' AND YEAR(`datec`) = '.$year );
		echo json_encode($is_found);
		break;
	
	case 'get_month':
		$year = $_POST['year'];
		$cnssu = new avance_utilisateur($db);
		$month = $cnssu->get_part_date('month','AND year(`datec`) = '.$year);
		echo json_encode($month);
		break;
}
