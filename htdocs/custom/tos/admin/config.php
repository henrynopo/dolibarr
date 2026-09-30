<?php
/* Copyright (C) 2012      Mikael Carlavan        <contact@mika-carl.fr>
 * Copyright (C) 2018      Inovea Conseil - Nicolas ZABOURI        <info@inovea-conseil.com>  
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


/**
 *      \file       htdocs/tos/admin/config.php
 *		\ingroup    tos
 *		\brief      Page to setup tos module
 */

$res=@include("../../main.inc.php");				// For root directory
if (! $res) $res=@include("../../../main.inc.php");	// For "custom" directory


require_once(DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.form.class.php");

$langs->load("admin");
$langs->load("companies");
$langs->load("tos@tos");
$langs->load("other");
$langs->load("errors");

if (!$user->admin)
{
   accessforbidden();
}

//Init error
$error = false;
$message = false;

$action = GETPOST('action');
$value = GETPOST('value');

$html = new Form($db);

if ($action == 'update')
{
	if(DOL_VERSION < 15){
		$varfiles = 'file';
	} else {
		$varfiles = 'userfile';
	}

	// Upload dir
	$upload_dir = $conf->tos->dir_output;
	$result = 0;

	if (! empty($_FILES[$varfiles])) // For view $_FILES[$varfiles]['error']
	{

		if(DOL_VERSION < 15){
			$finame = $_FILES[$varfiles]['name'];
		} else {
			$finame = $_FILES[$varfiles]['name'][0];
		}

		$ext = substr($finame, (strrpos($finame, '.') + 1));
		$ext = strtolower($ext);
		
		if ($ext != 'pdf')
		{
			dol_syslog("ToS::config wrong extension", LOG_DEBUG);
			$error = true;
			Header("Location: " . $_SERVER['PHP_SELF'] . "?action=error");
		}
		
		if (!$error)
		{					
			$res = dol_delete_dir_recursive($upload_dir); // Empty dir
			$res = dol_mkdir($upload_dir);
			if ($res >= 0)
			{
				if(DOL_VERSION < 15){
					$resupload = dol_move_uploaded_file($_FILES[$varfiles]['tmp_name'], $upload_dir . "/" . $finame, 1, 0, $_FILES[$varfiles]['error'], 0, $varfiles);
				} else {
					$resupload = dol_move_uploaded_file($_FILES[$varfiles]['tmp_name'][0], $upload_dir . "/" . $finame, 1, 0, $_FILES[$varfiles]['error'], 0, $varfiles);
				}
				if((int)DOL_VERSION <=10){
					Header("Location: " . $_SERVER['PHP_SELF'] . "?action=updated&token=".$_SESSION['newtoken']);
				}
				else{
					Header("Location: " . $_SERVER['PHP_SELF'] . "?action=updated&token=".newToken());
				}

			}
			else
			{
				dol_syslog("ToS::config create directory=".$res, LOG_DEBUG);
			}
		}
	}

}
else
{
	if ($action == 'updated')
	{
		$message = $langs->trans('CGVUploaded');
	}

	if ($action == 'error')
	{
		$message = $langs->trans('CGVError');
	}

	if ($action == 'setautotos')
	{
		dolibarr_set_const($db, 'AUTO_ADD_TOS', 1, 'chaine', 0, '', $conf->entity);
	}

	if ($action == 'unsetautotos')
	{
		dolibarr_set_const($db, 'AUTO_ADD_TOS', '', 'chaine', 0, '', $conf->entity);
	}

	if ($action == 'settosoneachpage')
	{
		dolibarr_set_const($db, 'ADD_TOS_ON_EACH_PAGE', 1, 'chaine', 0, '', $conf->entity);
	}

	if ($action == 'unsettosoneachpage')
	{
		dolibarr_set_const($db, 'ADD_TOS_ON_EACH_PAGE', '', 'chaine', 0, '', $conf->entity);
	}
        
        if ($action == 'setpropal')
	{
		dolibarr_set_const($db, 'ADD_TOS_PROPAL', '1', 'chaine', 0, '', $conf->entity);
	}
        if ($action == 'unsetpropal')
	{
		dolibarr_set_const($db, 'ADD_TOS_PROPAL', '', 'chaine', 0, '', $conf->entity);
	}
        
        if ($action == 'setorder')
	{
		dolibarr_set_const($db, 'ADD_TOS_ORDER', '1', 'chaine', 0, '', $conf->entity);
	}
        if ($action == 'unsetorder')
	{
		dolibarr_set_const($db, 'ADD_TOS_ORDER', '', 'chaine', 0, '', $conf->entity);
	}
        
        if ($action == 'setinvoice')
	{
		dolibarr_set_const($db, 'ADD_TOS_INVOICE', '1', 'chaine', 0, '', $conf->entity);
	}
        if ($action == 'unsetinvoice')
	{
		dolibarr_set_const($db, 'ADD_TOS_INVOICE', '', 'chaine', 0, '', $conf->entity);
	}

	$upload_dir = $conf->tos->dir_output;
	$filearray = dol_dir_list($upload_dir, 'files', 0, '', '\.meta$', '', SORT_ASC,1);

	$file = array_pop($filearray);
	
	if (is_array($file))
	{
		$filename = $file['name'];
		$link = DOL_URL_ROOT."/document.php?modulepart=tos&file=".$file['name'];
		$size = (intval($file['size'])/(1024*1024));
	}
	else
	{
		$filename = '';
		$link = '';
		$size = 0;
	}
				
	/*
	 * View
	 */

	$actionAddToS = ($conf->global->AUTO_ADD_TOS ?  'unsetautotos' : 'setautotos');
	$imgAddToS = ($conf->global->AUTO_ADD_TOS ?  img_picto($langs->trans("Activated"),'switch_on') : img_picto($langs->trans("Disabled"),'switch_off'));
	if((int)DOL_VERSION <= 10){
		$linkAddToS	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToS."&token=".$_SESSION["newtoken"].'" >'.$imgAddToS.'</a>';
	}
	else{
		$linkAddToS	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToS."&token=".newToken().'" >'.$imgAddToS.'</a>';
	}


	$actionAddToSPage = ($conf->global->ADD_TOS_ON_EACH_PAGE ?  'unsettosoneachpage' : 'settosoneachpage');
	$imgAddToSPage = ($conf->global->ADD_TOS_ON_EACH_PAGE ?  img_picto($langs->trans("Activated"),'switch_on') : img_picto($langs->trans("Disabled"),'switch_off'));
	if((int)DOL_VERSION <= 10){
		$linkAddToSPage	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".$_SESSION["newtoken"].'" >'.$imgAddToSPage.'</a>';
	}else{
		$linkAddToSPage	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".newToken().'" >'.$imgAddToSPage.'</a>';
	}

        
	$actionAddToSPage = ($conf->global->ADD_TOS_PROPAL ?  'unsetpropal' : 'setpropal');
	$imgAddToSPage = ($conf->global->ADD_TOS_PROPAL ?  img_picto($langs->trans("Activated"),'switch_on') : img_picto($langs->trans("Disabled"),'switch_off'));
	if((int)DOL_VERSION <= 10){
		$linkAddP	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".$_SESSION["newtoken"].'" >'.$imgAddToSPage.'</a>';
	}else{
		$linkAddP	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".newToken().'" >'.$imgAddToSPage.'</a>';
	}


	$actionAddToSPage = ($conf->global->ADD_TOS_ORDER ?  'unsetorder' : 'setorder');
	$imgAddToSPage = ($conf->global->ADD_TOS_ORDER ?  img_picto($langs->trans("Activated"),'switch_on') : img_picto($langs->trans("Disabled"),'switch_off'));
	if((int)DOL_VERSION <= 10){
		$linkAddO	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".$_SESSION["newtoken"].'" >'.$imgAddToSPage.'</a>';
	}else{
		$linkAddO	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".newToken().'" >'.$imgAddToSPage.'</a>';
	}

	$actionAddToSPage = ($conf->global->ADD_TOS_INVOICE ?  'unsetinvoice' : 'setinvoice');
	$imgAddToSPage = ($conf->global->ADD_TOS_INVOICE ?  img_picto($langs->trans("Activated"),'switch_on') : img_picto($langs->trans("Disabled"),'switch_off'));
	if((int)DOL_VERSION <= 10){
		$linkAddI	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".$_SESSION["newtoken"].'" >'.$imgAddToSPage.'</a>';
	}else{
		$linkAddI	= '<a href="'.$_SERVER['PHP_SELF'].'?action='.$actionAddToSPage."&token=".newToken().'" >'.$imgAddToSPage.'</a>';
	}

        
	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/tos/admin/config.php', 1);
	$head[$h][1] = $langs->trans("Setup");
	$head[$h][2] = 'config';
	$h++;
	
	$current_head = 'config';

	$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';

	require_once("../tpl/admin.config.tpl.php");
}

$db->close();

?>
