<?php 
// $res=0;
// if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
// if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom"
// dol_include_once('/sghr/docsemployes/class/interventions_parc.class.php');
// $intervention = new interventions_parc($db);
// $intervention->checkInterventionsMails();
// die();



$res=0;
if (! $res && file_exists("../conf/conf.php")) $res=@include("../conf/conf.php");       // For root directory
if (! $res && file_exists("../../conf/conf.php")) $res=@include("../../conf/conf.php"); // For "custom"


global $dolibarr_main_url_docsemployes_cronjob;
if(!empty($dolibarr_main_url_docsemployes_cronjob)){

	$URL_CRON = $dolibarr_main_url_docsemployes_cronjob; // Get Link From configuration of the "Documents employés" module
	if(!empty($URL_CRON)){
		$callurl = curl_init();
		curl_setopt($callurl , CURLOPT_URL, $URL_CRON);
		curl_setopt($callurl , CURLOPT_HEADER, 0);
		curl_exec($callurl);
		curl_close($callurl);
	}
}