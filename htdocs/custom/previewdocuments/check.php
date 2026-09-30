<?php

if (!defined('NOREQUIRESOC'))    define('NOREQUIRESOC', '1');
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);
if (!defined('NOREQUIREHTML'))   define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))   define('NOREQUIREAJAX', '1');

$res=0;

$l = "";

if (! $res && file_exists("../main.inc.php")){

	$res=@include("../main.inc.php");// For root directory

}



if (! $res && file_exists("../../main.inc.php")){

	$res=@include("../../main.inc.php"); // For "custom" directory

	$l = "/custom";

}





$action = $_POST['action'];

// $urlfile = $_POST['urlfile'];

$extention = $_POST['extention'];

$filename = $_POST['filename'];

if (strpos($filename, 'file=') !== false) {
	$filename2 = explode('file=', $filename);
	$filename2 = explode('&', $filename2[1]);
	$filename  = $filename2[0];
	$filename = urldecode($filename);
	$filename2 = explode('/', $filename2[0]);

	$filename2 = $filename2[count($filename2)-1];
	$filename2 = urldecode($filename2);
	$filename2=dol_string_nospecial($filename2, '',' ');
}else{
	$filename2 = urldecode($filename2);
	$filename2=dol_string_nospecial($filename, '',' ');
}


$urldoc = $_POST['urldoc'];
// echo $filename2;
// echo $urldoc;


if($action == "readThisFile"){



	$newfile = dol_buildpath('/previewdocuments');
	$dir = $newfile.'/toview/';
	if(!file_exists($dir))
	{
        $result = dol_mkdir($dir);
	}



	$newfile = dol_buildpath('/previewdocuments/toview/');

	$newfilelink = dol_buildpath('/previewdocuments/toview/', 2);

	// $file2 = $dolibarr_main_data_root.'/facture/FA1807-0004/FA1807-0004-excel_.xlsx';





	$files = glob($newfile.'*'); //get all file names

	foreach($files as $f){

	    if(is_file($f))

	    unlink($f); //delete file

	}


	$file3 = $urldoc.'/'.$filename;

	// $new = $newfile."newfile4.".$extention;

	$new = $newfile.$filename2;

	// $myfile = "";

	// $myfile = fopen($new, "w");

	// fwrite($myfile, file_get_contents($file3) );

	// fclose($myfile);

	@chmod(dol_buildpath('/previewdocuments/toview'),0777);

	// echo json_encode($new);

	$file = 'example.txt';
	$newfile = 'example.txt.bak';


	if (!copy($file3, $new)) {

	    echo "";

	}else{

		echo $newfilelink.$filename2;

	}



	// echo json_encode($newfilelink."newfile.".$extention);

}

