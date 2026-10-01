<?php
	$res=0;
	if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
	if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 


	$user_   = new User($db);

	$id_user = GETPOST('id_user');
	$user_->fetch($id_user);
	
 	echo $user_->email;

?>