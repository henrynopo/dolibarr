<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/ecv/class/ecv.class.php');

$ecv              = new ecv($db);

$element = GETPOST('element');

if($element == 'ADHERENT'){
    $html = $ecv->select_user(0,'fk_user',1,"rowid","login");
}else{
    $html = $ecv->select_adherent(0,'fk_adherent',1,"rowid","login");
}
    	
echo json_encode($html);

?>
