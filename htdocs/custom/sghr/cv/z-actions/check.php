<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/cv/class/ecv.class.php');

$cvProfile              = new Cv($db);

$element = GETPOST('element');

if($element == 'ADHERENT'){
    $html = $cvProfile->select_user(0,'fk_user',1,"rowid","login");
}else{
    $html = $cvProfile->select_adherent(0,'fk_adherent',1,"rowid","login");
}
    	
echo json_encode($html);

?>
