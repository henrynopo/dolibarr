<?php
/*
 * Copyright (C)	2016	  Yassine belkaid		  <y.belkaid@nextconcept.ma>
 */

$langs->load("link");
if (empty($relativepathwithnofile)) $relativepathwithnofile='';

$param = (isset($param) && !empty($param) ? $param : '');
/*
 * Confirm form to delete
 */

if ($action == 'delete') {
	$langs->load("companies");	// Need for string DeleteFile+ConfirmDeleteFiles
	$url = $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&urlfile=' . urlencode(GETPOST("urlfile")) . '&linkid=' . GETPOST('linkid', 'int') . $param;
	$ret = $form->form_confirm_files($url, $langs->trans('DeleteFile'), $langs->trans('ConfirmDeleteFile'), 'confirm_deletefile', '', 0, 1);
	if ($ret == 'html') print '<br>';
}

$formfile=new FormFile($db);

// We define var to enable the feature to add prefix of uploaded files
$savingdocmask='';
if (empty($conf->global->MAIN_DISABLE_SUGGEST_REF_AS_PREFIX))
{
	//var_dump($modulepart);
	if (in_array($modulepart,array('facture_fournisseur','commande_fournisseur','facture','commande','propal','askpricesupplier','ficheinter','contract','project','project_task','expensereport')))
	{
		$savingdocmask=dol_sanitizeFileName($object->ref).'-__file__';
	}
	/*if (in_array($modulepart,array('member')))
	{
		$savingdocmask=$object->login.'___file__';
	}*/
}

// Show upload form (document and links)
$formfile->form_attach_new_file(
    $_SERVER["PHP_SELF"].'?id='.$object->id.(empty($withproject)?'':'&withproject=1').$param,
    '',
    0,
    0,
    $permission,
    50,
    $object,
	'',
	1,
	$savingdocmask
);

// List of document
$formfile->list_of_documents(
    $filearray,
    $object,
    $modulepart,
    $param,
    0,
    $relativepathwithnofile,		// relative path with no file. For example "moduledir/0/1"
    $permission
);

print "<br>";
//List of links
$formfile->listOfLinks($object, $permission, $action, GETPOST('linkid', 'int'), $param);
print "<br>";
