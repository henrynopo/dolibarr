<?php
/* Copyright (C) 2016		Yassine Belkaid	<y.belkaid@nextconcept.ma>
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
 *   	\file       salariescontracts/document.php
 *		\ingroup    fichier
 *		\brief      Page des documents joints sur les contrats
 */

$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/grh/salariescontracts/class/salariescontracts.class.php');
dol_include_once('/grh/salariescontracts/lib/salariescontracts.lib.php');
dol_include_once('/grh/salariescontracts/common.inc.php');

$langs->load("other");
$langs->load("companies");

$id = GETPOST('id','int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action','alpha');
$confirm = GETPOST('confirm','alpha');

// Security check
if ($user->societe_id) $socid = $user->societe_id;
// $result = restrictedArea($user, 'salariescontracts', $id, 'salariescontracts');

// Get parameters
$files_deleted  = GETPOST('files_deleted');
$objet_id  = GETPOST('objet_id');
$sortfield = GETPOST('sortfield','alpha');
$sortorder = GETPOST('sortorder','alpha');
$page = GETPOST('page','int');
if ($page == -1 || empty($page)) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="name";

$object = new Salariescontracts($db);
$object->fetch($id, null);
$upload_dir = $conf->grh->dir_output;
$modulepart = 'grh';
/*
 * Actions
 */

/*
 * View
 */
$form = new Form($db);

if($objet_id){
    $dire_file = $conf->grh->dir_output.'/salariescontracts/'.$objet_id;
    if($files_deleted){
        $files_deleted = explode(',', $files_deleted);
        foreach ($files_deleted as $d) {
            unlink($dire_file.'/'.$d);
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
    header('Location: ./document.php?id='. $objet_id);
}


llxHeader("", "", $langs->trans("InterventionCard"));

if ($object->id) {
	$userRequest = new User($db);
	$userRequest->fetch($object->fk_user);

	$head = salariescontracts_prepare_head($object);
	dol_fiche_head($head, 'documents', $langs->trans("Document"), 0, 'object_salariescontracts@salariescontracts');

    $filearray = array();

	// Construit liste des fichiers
	$filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview\.png)$', $sortfield, (strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC),1);
	$totalsize=0;
	foreach($filearray as $key => $file) {
		$totalsize+=$file['size'];
	}

    print '<table class="border" width="100%">';

    $linkback='';

    print '<tr>';
    print '<td width="20%">'.$langs->trans("Ref").'</td>';
    print '<td>';
    print $object->id;
    print '</td>';
    print '</tr>';

    print '<td>'.$langs->trans("User").'</td>';
    print '<td>';
    print $userRequest->getNomUrl(1);
    print '</td></tr>';

    if (!empty($object->fk_user_create)) {
    	$userCreate = new User($db);
    	$userCreate->fetch($object->fk_user_create);
        print '<tr>';
        print '<td>'.$langs->trans('CreatedByCP').'</td>';
        print '<td>'.$userCreate->getNomUrl(1).'</td>';
        print '</tr>';
    }

    print '<tr>';
    print '<td>'.$langs->trans('DateCreate').'</td>';
    print '<td>'.dol_print_date($object->date_create, 'dayhour').'</td>';
    print '</tr>';

    if(!$edit) {
    	// Type
    	print '<tr>';
	    print '<td>'.$langs->trans("Type").'</td>';
	    print '<td>';
	    print ucfirst($object->getContractTypeById($object->type)).'</td>';
	    print '</tr>';
    	
    	print '<tr>';
        print '<td>'.$langs->trans('HiringDate').'</td>';
        print '<td>'.dol_print_date($object->start_date,'day').'</td>';
        print '</tr>';

        $endDate = dol_print_date($object->end_date, 'day');
        print '<tr>';
        print '<td>'.$langs->trans('EndDate').'</td>';
        print '<td>'.($endDate ?: 'Vide').'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('SalarySignatureDateSC').'</td>';
        print '<td>'. dol_print_date($object->salarie_sig_date, 'day') .'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('ManagerSignatureDateSC').'</td>';
        print '<td>'. dol_print_date($object->direction_sig_date, 'day') .'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('DeclarationPriorToHiringSC').'</td>';
        print '<td>'. dol_print_date($object->dpae_date, 'day') .'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('MedicalVisitSC').'</td>';
        print '<td>'. dol_print_date($object->medical_visit_date, 'day') .'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('DescSC').'</td>';
        print '<td>'.nl2br($object->description).'</td>';
        print '</tr>';
    }
    else {
    	// Type
        print '<tr>';
        print '<td>'.$langs->trans("Type").'</td>';
        print '<td>';
				print $form->selectarray('type', $object->getContractsTypes(), (GETPOST('type')?GETPOST('type'):$object->type), 1);
        print '</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('HiringDate').'</td>';
        print '<td>';
      	  $form->select_date($object->start_date,'start_date_').'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('EndDate').'</td>';
        print '<td>';
  	      $form->select_date($object->end_date, 'end_date_').'</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('SalarySignatureDateSC').'</td>';
        print '<td>';
  	      $form->select_date($object->salarie_sig_date, 'salarie_sig_date_');
        print '</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('ManagerSignatureDateSC').'</td>';
        print '<td>';
  	      $form->select_date($object->direction_sig_date, 'direction_sig_date_');
        print '</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('DeclarationPriorToHiringSC').'</td>';
        print '<td>';
  	      $form->select_date($object->dpae_date, 'dpae_date_');
        print '</td>';
        print '</tr>';

        print '<tr>';
        print '<td>'.$langs->trans('MedicalVisitSC').'</td>';
        print '<td>';
  	      $form->select_date($object->medical_visit_date, 'medical_visit_date_');
        print '</td>';
        print '</tr>';

        print '<tr>';
	    print '<td>'.$langs->trans('DescSC').'</td>';
	    print '<td><textarea name="description" class="flat" rows="'.ROWS_3.'" cols="70">'.$object->description.'</textarea></td>';
	    print '</tr>';
    }

    // print '<tr><td>'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
    // print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.$totalsize.' '.$langs->trans("bytes").'</td></tr>';



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
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="forms_grh" >';
    print '<table class="border" width="100%">';
    print '<tr>';
        print '<td style="">'.$langs->trans('Fichier Joints').'</td>';
        print '<td id="files">';
            print '<input type="file" name="files[]" style="width: 300px;" /><input type="submit" value="'.$langs->trans('Envoyer fichier').'" class="button" /><br><br>';
            print '<div id="wrapper"><ul style="padding-left: 8px;margin: 0;">';
            $dire = $conf->grh->dir_output.'/salariescontracts/'.$objet_id;

            if (file_exists($dire)) {
                $images = scandir($dire);
                foreach ($images as $img) {
                    if (!in_array($img,array(".",".."))) 
                    { 
                        $ext = explode(".", $img);
                        $ext = $ext[count($ext) - 1];
                        $title = explode('_nc_', $img);
                        $title = $title[1];
                        $picto =dol_buildpath('/grh/images/'.$ext.'.png',2);
                        $nopicto =dol_buildpath('/grh/images/file.png',2);

                        $minifile = getImageFileNameForSize($img,'');
                        $dt_files = getAdvancedPreviewUrl('grh', 'salariescontracts/'.$objet_id.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));


                        if ($ext == "png" || $ext == "jpeg" || $ext == "jpg") {
                            print '<li>';
                                print '<a href="'.$dt_files[url].'" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a><a href="'.$dire.'/'.$img.'" class="lightbox_trigger" title="'.$title.'"><img src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=grh&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file=salariescontracts/'.$objet_id.'/'.$minifile.'&perm=download" /></a>';
                            print '</li>';
                        }
                        elseif ($ext == "pdf") {
                            print '<li>';
                                print '
                                    <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=salariescontracts/'.$objet_id.'/'.$minifile.'" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a>
                                    <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=salariescontracts/'.$objet_id.'/'.$minifile.'" target="_blank" class="" title="'.$title.'"><img src="'.$picto.'" /></a>';
                            print '</li>';
                        }
                        else{
                            print '<li>';
                            if (file_exists(dol_buildpath('/grh/images/'.$ext.'.png'))) {
                                print '<a href="" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a>
                                <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=salariescontracts/'.$objet_id.'/'.$minifile.'" download title="'.$title.'"><img src="'.$picto.'" /></a>';
                            }else{
                                print '<a href="" datafile="'.$img.'" class="delete_file"><span><i class="fa fa-times"></i></span></a>
                                <a href="'.DOL_URL_ROOT.'/document.php?modulepart=grh&file=salariescontracts/'.$objet_id.'/'.$minifile.'" download title="'.$title.'"><img src="'.$nopicto.'" /></a>';
                            }
                            print '</li>';
                        }
                    }
                }
            }
            print '<input type="hidden" name="files_deleted" id="files_deleted" />';
            print '<input type="hidden" name="objet_id" value="'.$object->id.'" />';
            print '</ul></div>';

            // print '<input id="add_files" class="add_ button" style="float: right;padding: 5px 10px !important;" value="Nouveau" type="button">';
        print '</td>';
    print '</tr>';
    
    print '</table>';
    print '<div style="display:none;text-align:center;" id="savechangedfiles"><input class="butAction" type="submit" value="'.$langs->trans('Enregistrer').'" class="button" /></div>';
    print '</form>';
    // End fichier joint


    dol_fiche_end();



    // $modulepart = 'grh';
    // $permission = $user->rights->grh->salariescontracts->write;
    // $param 		= '&id=' . $object->id;
    // $relativepathwithnofile = '/'.$object->id.'/';
    // include_once DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_post_headers.tpl.php';
}
else {
	print $langs->trans("ErrorUnknown");
}

print '<div id="lightbox" style="display:none;"><div id="content"><img src="" /></div></div>';
llxFooter();

$db->close();

?>