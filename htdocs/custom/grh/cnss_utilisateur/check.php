<?php
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
dol_include_once('/grh/cnss_utilisateur/class/cnss_utilisateur.class.php');


$role = $_POST['role'];
$action = $_POST['action'];
$rowid = $_POST['rowid'];

switch ($role) {
	case 'check_user':
		$datec = $_POST['date'];
		$date_ = explode('/', trim($datec));
        $datec = $date_[2]."-".$date_[1]."-".$date_[0];

		// $year = date("Y", strtotime($_POST['date']));
		// $month = date("m", strtotime($_POST['date']));
		$month = $date_[1];
		$year = $date_[2];

		// echo $datec."<br>";
		// echo $month."<br>";
		// echo $year."<br>";
		// die();
		$iduser = $_POST['user'];
		$cnssu = new cnss_utilisateur($db);
		if ($action == "edit") {
			$is_found = $cnssu->fetchAll('','',0,0, ' AND idutilisateur='.$iduser.' AND MONTH(`datec`) = '.$month.' AND YEAR(`datec`) = '.$year .' AND id != '.$rowid );
		} else{
			$is_found = $cnssu->fetchAll('','',0,0, ' AND idutilisateur='.$iduser.' AND MONTH(`datec`) = '.$month.' AND YEAR(`datec`) = '.$year );
		}
		echo json_encode($is_found);
		break;
	
	case 'get_month':
		$year = $_POST['year'];
		$cnssu = new cnss_utilisateur($db);
		$month = $cnssu->get_part_date('month','AND year(`datec`) = '.$year);
		echo json_encode($month);
		break;
}
