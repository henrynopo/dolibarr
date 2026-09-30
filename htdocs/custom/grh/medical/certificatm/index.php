<?php
/* Copyright (C) 2016	Yassine Belkaid	<y.belkaid@nextconcept.ma>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file       appeloffres//certificatm/index.php
 * \ingroup    list
 * \brief      List of certificatm related to given daId
 */
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/grh/core/class/html.form.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
dol_include_once('/grh/medical/certificatm/class/certificatm.class.php');
dol_include_once('/grh/lib/medical.lib.php');
dol_include_once('/grh/medical/class/medical.class.php');
$medical 	  = new medical($db);


$form 		= new FormChild($db);
$langs->load('users');
$langs->load('grh');
// $langs->load('grh@grh');

// Protection if external user
if ($user->societe_id > 0) accessforbidden();

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page 	   = GETPOST("page",'int');
$page 	   = is_numeric($page) ? $page : 0;
$page 	   = $page == -1 ? 0 : $page;
$filter    = '';
if (! $sortfield) $sortfield = "rowid";
if (! $sortorder) $sortorder = "DESC";

$offset  = $conf->liste_limit * $page;
$pageprev= $page - 1;
$pagenext= $page + 1;
$action	 = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$daId 	 = GETPOST('daId', 'int');
$id 	 = GETPOST('id', 'int');
$files_deleted  = GETPOST('files_deleted');
$objet_id  = GETPOST('objet_id');
if($objet_id)
    $daId = $objet_id;

if ($daId) {
    $object = new medical($db);
    $object->fetch($daId);
    if (!($object->id > 0))
    {
        $langs->load("errors");
        print($langs->trans('ErrorRecordNotFound'));
        exit;
    }
}

if (!$daId)
	die('ID dossier médical n\'existe pas!');

if($objet_id){
    $dire_file = $conf->grh->dir_output.'/certificatm/'.$objet_id.'/';
    if($files_deleted){
        $files_deleted = explode(',', $files_deleted);
        foreach ($files_deleted as $d) {
            unlink($dire_file.$d);
        }
    }
    if(!is_dir($dire_file))
        mkdir($dire_file, 0777, true);
    // echo $dire_file;
    // die();
    $names = array();
    foreach ($_FILES["files"]["error"] as $key => $error) {
        if ($error == UPLOAD_ERR_OK) {
            $tmp_name = $_FILES["files"]["tmp_name"][$key];
            $name = $_FILES["files"]["name"][$key];
            if(in_array($name,$names))
                $name = $key.'-'.$name;
            $names[$name] = $name;
            // $newfile=$dire_file.'/'.uniqid().'_nc_'.dol_sanitizeFileName($name);
            $newfile=$dire_file.'/nc_'.dol_sanitizeFileName($name);
            dol_move_uploaded_file($tmp_name, $newfile, 1);
        }
    }
    header('Location: ./index.php?daId='. $objet_id);
}
/**
 * Actions
 */

// None

/**
 * View
 */

$certificatm 	= new certificatm($db);
$filter 	= '';
if ($id > 0 || $daId > 0) {
	$certificatm->fetch($id, $daId);
		
	if (!$certificatm->id)
		$certificatm->fetch(null, $daId);
	$object 	= $certificatm;
    $param 		= '&daId='. $daId;

	$upload_dir = $conf->grh->dir_output.'/certificatm/'.$daId;
	$modulepart = 'grh';

	$filearray = array();

	// Construit liste des fichiers
	$filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview\.png)$', $sortfield, (strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC),1);
	$totalsize=0;
	foreach($filearray as $key => $file) {
		$totalsize+=$file['size'];
	}

	require_once dol_buildpath('/grh/core/tpl/document_actions_pre_headers.tpl.php');
}

llxHeader(array(), $langs->trans('certificatm'));
// Récupération de l'ID de l'utilisateur
$user_id 	 = $user->id;
$getcertificatm = $certificatm->fetchAll($daId);

// Si erreur SQL

// Show table of vacations
$var 		= true;
$num 		= count($certificatm->lines);
//$form 		= new FormChild($db);

if ( $daId > 0 && $num>0) {

	if (GETPOST('notification')) {
        $notif = '';
        switch(GETPOST('notification')) {
            case 'marketCreated' :
                $notif = $langs->trans('marketCreatedAuto');
                break;
            case 'marketDeleted' :
                $notif = $langs->trans('marketDeleted');
                break;
            case 'marketArchived' :
                $notif = $langs->trans('marketArchived');
                break;
            case 'marketActivated' :
                $notif = $langs->trans('marketActivated');
                break;
        }

        setEventMessage($notif);
    }

	
	$title = $langs->trans("certificatm");
	print_fiche_titre($title);

	$title = $langs->trans("certificatm");
	$head = medical_prepare_head($daId);
	dol_fiche_head($head, 'certificatm', $title, 1, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	// $modulepart = 'grh';
 //    $permission = true;
 //    $param 		            = '&daId='. $daId;
 //    $relativepathwithnofile = '/certificatm/'.$daId.'/';






    // fichier joint
    ?>
    <script type="text/javascript">
    $( function() {
        $('#add_files').click(function(){
            $('#files').append('<input type="file" name="files[]" style="width: 300px;" />');
        });
        $('.delete_file').click(function(e) {
            e.preventDefault();
            var files_href = $(this).attr("datafile");
            var files_deleted = $('#files_deleted').val();
            if(files_deleted == '')
                $('#files_deleted').val(files_href);            
            else
                $('#files_deleted').val(files_deleted+','+files_href);
            $(this).parent('li').remove();
            $("#savechangedfiles").show();
        });
        $('.lightbox_trigger').click(function(e) {
            e.preventDefault();
            var image_href = $(this).attr("href");
            $('#lightbox #content').html('<img src="' + image_href + '" />');
            $('#lightbox').show();
        });
        $('#lightbox,#lightbox p').click(function() {
            $('#lightbox').hide();
        });
    });
    </script>
    <?php
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="forms_grh">';
    print '<table class="border" width="100%">';
    print '<tr>';
        print '<td style="">'.$langs->trans('AttachedFiles').'</td>';
        print '<td id="files">';
            print '<input type="file" name="files[]" style="width: 300px;" /><input type="submit" value="'.$langs->trans('Upload').'" class="button" /><br><br>';
            print '<div id="wrapper" class="certificatmedic"><ul style="padding-left: 8px;margin: 0;">';
            $dire = $conf->grh->dir_output.'/certificatm/'.$daId;

            if (file_exists($dire)) {
                $images = scandir($dire);
                foreach ($images as $img) {
                    if (!in_array($img,array(".",".."))) 
                    { 
                        $ext = explode(".", $img);
                        $ext = $ext[count($ext) - 1];
                        $title = explode('_nc_', $img);
                        $title = $title[1];
                        $picto = dol_buildpath('/grh/images/'.$ext.'.png',2);
                        $nopicto = dol_buildpath('/grh/images/file.png',2);
                        $minifile = getImageFileNameForSize($img, '');  
                        $dt_files = getAdvancedPreviewUrl('grh', 'certificatm/'.$daId.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));


                        if ($ext == "png" || $ext == "jpeg" || $ext == "jpg") {
                            print '<li>';
                                print '<a href="'.$dt_files['url'].'" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a><a href="'.$dt_files['url'].'&attachment=0" class="pictopreview documentpreview" mime="image/png" title="'.$title.'"><img class="photo" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=grh&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file=certificatm/'.$daId.'/'.$minifile.'&perm=download" /></a>';
                            print '</li>';
                        }
                        elseif ($ext == "pdf") {
                            print '<li>';
                                print '
                                    <a href="" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a>

                                    <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&attachment=0&file=certificatm/'.$daId.'/'.$minifile.'" target="_blank" class="pictopreview documentpreview" mime="application/pdf" title="'.$title.'" ><img src="'.$picto.'" /></a>';
                            print '</li>';
                        }else{
                            print '<li>';
                            if (file_exists(dol_buildpath('/grh/images/'.$ext.'.png'))) {
                                print '<a href="" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a>
                                <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=certificatm/'.$daId.'/'.$minifile.'" download title="'.$title.'"><img src="'.$picto.'" /></a>';
                            }else{
                                print '<a href="" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a>
                                <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=certificatm/'.$daId.'/'.$minifile.'" download title="'.$title.'"><img src="'.$nopicto.'" /></a>';
                            }
                            print '</li>';
                        }
                    }
                }
            }
            print '<input type="hidden" name="files_deleted" id="files_deleted" />';
            print '<input type="hidden" name="objet_id" value="'.$daId.'" />';
            print '</ul></div>';

            // print '<input id="add_files" class="add_ button" style="float: right;padding: 5px 10px !important;" value="Nouveau" type="button">';
        print '</td>';
    print '</tr>';
    
    print '</table><br>';
 	print '<div style="display:none;text-align:center;" id="savechangedfiles"><input class="butAction" type="submit" value="'.$langs->trans('Enregistrer').'" class="button" /></div>';
    print '</form>';
    // End fichier joint








}

if ($user->id && $num <= 0) {
	$title = $langs->trans("Addcertificatm");
	print_fiche_titre($title);
	$head = medical_prepare_head($daId);
	dol_fiche_head($head, 'certificatm', $title, 0, '');
	$medical->fetch($daId);
	echo $medical->getMedicalHeader();
	print '<br>';
	print '<div style="text-align: center; margin-top: 8px;">';
	print '<a href="./card.php?action=create&daId='. $daId .'" class="butAction">'.$langs->trans('Addcertificatm').'</a>';
	print '</div>';
}
print '<div id="lightbox" style="display:none;"><div id="content"><img src="" /></div></div>';
llxFooter();

$db->close();

?>