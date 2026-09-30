<?php
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

	$res=0;
	if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
	if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 


	dol_include_once('/recrutement/class/etapescandidature.class.php');
	dol_include_once('/recrutement/class/candidatures.class.php');
	dol_include_once('/recrutement/lib/recrutement.lib.php');
	dol_include_once('/recrutement/class/postes.class.php');
	dol_include_once('/core/class/html.form.class.php');


	$candidature = new candidatures($db);

	$fk_degree=GETPOST('fk_degree');
	$data = $candidature->select_majors('',' AND fk_degree='.$fk_degree,0);
 	echo $data;

?>