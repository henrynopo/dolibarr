<?php
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);

	$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
	if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
	if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 


	dol_include_once('/sghr/recruitment/class/CandidateStage.class.php');
	dol_include_once('/sghr/recruitment/class/Candidate.class.php');
	dol_include_once('/sghr/recruitment/lib/recrutement.lib.php');
	dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
	dol_include_once('/core/class/html.form.class.php');


	$candidature = new Candidate($db);

	$fk_degree=GETPOST('fk_degree');
	$data = $candidature->select_majors('',' AND fk_degree='.$fk_degree,0);
 	echo $data;

?>