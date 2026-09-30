<?php

if($id ){
    $docsemployes->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $docsemployes->rows[0];
    if ($user->id != $docsemployes->fk_user && empty($user->admin)) {
        accessforbidden();
    }
}

if ($action == 'update' && $request_method === 'POST') {

    $sendmail=GETPOST('sendmail');
    $page  = GETPOST('page');
    global $dolibarr_main_data_root;
    $d_issue = GETPOST('issuedate');
    $d_expiry = GETPOST('expirydate');
    $destinataire = GETPOST('destinataire');
    $description = GETPOST('description');

    $docsemployes->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $docsemployes->rows[0];
    // print_r(json_encode($_FILES['file']['name']));die();
    
    $object = new docsemployes($db);
    $object->fetch($item->rowid);

    // issuedate
    $date1 = explode('/', $d_issue);
    $issuedate = $date1[2]."-".$date1[1]."-".$date1[0];
    $expirydate = '';
    // expirydate
    $expirydate = '';
    if($d_expiry){

        $date2 = explode('/', $d_expiry);
        $expirydate = $date2[2]."-".$date2[1]."-".$date2[0];
    }

    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;

    $file='';
    $number = GETPOST('number');
    $emp = GETPOST('fk_user'); 
    $document = GETPOST('fk_type_document');
   
    // print_r($file_deleted);die();
    $data =  array( 
        'fk_user'      =>  $emp,
        'file'         =>  '',
        'number'       =>  $number,
        'issuedate'    =>  $issuedate,
        'expirydate'   =>  $expirydate,
        'description'  =>  $description,
        'destinataire' =>  $destinataire,
        'fk_type_document'     =>  $document,
        'sendmail'   =>  $sendmail,
        'entity'     =>  $entity,

    );
    $isvalid = $docsemployes->update($id, $data);
    
    $photo_deleted=GETPOST('photo_deleted');
    if(!empty($photo_deleted)){
        $upload_dir = $conf->docsemployes->multidir_output[$object->entity].'/'.$id.'/pictures/';
        $photo_deleted=explode(',',$photo_deleted);
        foreach ($photo_deleted as $value) {
           unlink($upload_dir.$value);
        }
    }
    $file_deleted=GETPOST('file_deleted');
    if(!empty($file_deleted)){
        $upload_dir = $conf->docsemployes->multidir_output[$object->entity].'/'.$id.'/files/';
        $file_deleted=explode(',',$file_deleted);
        foreach ($file_deleted as $value) {
           unlink($upload_dir.$value);
        }
    }


    $nb=count($_FILES['file']['name']);
    for ($i=0; $i < $nb ; $i++) { 
        $TFile = $_FILES['file'];

        $allowed = array ('image/pjpeg', 'image/jpeg', 'image/JPG', 'image/X-PNG', 'image/PNG', 'image/png', 'image/x-png');
        if (in_array($_FILES['file']['type'][$i], $allowed)) {
            $upload_dir = $conf->docsemployes->multidir_output[$object->entity].'/'.$id.'/pictures/';
        }else{
            $upload_dir = $conf->docsemployes->multidir_output[$object->entity].'/'.$id.'/files/';
        }
            
        if (dol_mkdir($upload_dir) >= 0)
        {
            $destfull = $upload_dir.$TFile['name'][$i];
            $info = pathinfo($destfull);

            $filname = dol_sanitizeFileName($TFile['name'][$i]);
            $destfull   = $info['dirname'].'/'.$filname;
            $destfull   = dol_string_nohtmltag($destfull);
            $resupload  = dol_move_uploaded_file($TFile['tmp_name'][$i], $destfull, 0, 0, $TFile['error'][$i], 0);
        }
    }


  
    if ($isvalid > 0) {
        header('Location: ./card.php?id='.$id);
        exit;
    } else {
        header('Location: ./card.php?id='. $id .'&update=0');
        exit;
    }
}
?>


<script type="text/javascript">
    $(document).ready(function() {
        $("input.datepicker").datepicker({
            dateFormat: "dd/mm/yy"
        });
        $('.delete_file').click(function(e) {
            e.preventDefault();
            var filename = $(this).data("file");
            var file_deleted = $('#file_deleted').val();
            if( file_deleted == '' )
                $('#file_deleted').val(filename);            
            else
                $('#file_deleted').val(file_deleted+','+filename);
            $(this).parent('li').remove();
        });
         $('.delete_photo').click(function(e) {
            e.preventDefault();
            var image_href = $(this).data("photo");
            var photo_deleted = $('#photo_deleted').val();
            if(photo_deleted == '')
                $('#photo_deleted').val(image_href);            
            else
                $('#photo_deleted').val(photo_deleted+','+image_href);
            $(this).parent('li').remove();
        });
    });
</script>
<?php
$dir_icon=dol_buildpath("/docsemployes/img",2);


if($action == "edit" || $action == "deleteall"){


    if($action == "deleteall"){
        print $form->formconfirm("card.php?id=".$id."&page=".$page,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete_all", 'index.php?page='.$page, 0, 1);
    }
    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/docsemployes/card.php?id=".$id."&action=edit", 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@docsemployes");

     // print '<div align="right"> <a href="./notifications/card.php?action=add"><input id="add_notification" align="right" class="add_ button" style="float: right;padding: 5px 10px !important;" value="Ajouter Notification" type="button"></a> </div> <br>';

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="card_docsemploys">';
    $docsemployes->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $docsemployes->rows[0];

    $object = new docsemployes($db);
    $object->fetch($item->rowid);

    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<input type="hidden" name="entity" value="'.$item->entity.'" />';


    $d=explode(' ', $item->issuedate);
    $date_d = explode('-', $d[0]);
    $issuedate = $date_d[2]."/".$date_d[1]."/".$date_d[0];

    // expirydate
    $expirydate = null;
    if($item->expirydate){
        $f=explode(' ', $item->expirydate);
        $date_f = explode('-', $f[0]);
        $expirydate = $date_f[2]."/".$date_f[1]."/".$date_f[0];
    }

    if(!empty($conf->global->DOCSEMPLOYES_SEND_EMAIL)){
        if($item->sendmail) $checked = 'checked';
        print '<div class="test_sendmail"><input id="cb1" class="flat checkforselect" type="checkbox" '.$checked.' name="sendmail" value="1"> <span class="title_sendmail">'.$langs->trans("test_sendmail_docs").'</span></div>';
    }
    print '<table class="border nc_table_" width="100%">';
    print '<tbody>';

    print '<tr>';
        print '<td >'.$langs->trans('Employe').'</td>';
        print '<td colspan="3"> '.$docsemployes->select_user($item->fk_user,'fk_user',1,'rowid','login').'</td>';
    print '</tr>';

    print '<tr>';
        print '<td>'.$langs->trans('Email').' </td>';
        print '<td class="destinataire" > <input value="'.$item->destinataire.'" class="minwidth300" name="destinataire"> </td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('numberdoc').'</td>';
        print '<td ><input type="text" class="minwidth300" id="number" name="number" value="'.$item->number.'" required="required" autocomplete="off"/>';

        print '<td >'.$langs->trans('issuedate').'</td>';
        print '<td ><input type="text" class="datepickerdatefrformat" id="issuedate" name="issuedate" value="'.$issuedate.'" required="required" autocomplete="off"/>';
    print '</td>';

    print '<tr>';
        print '<td >'.$langs->trans('fk_type_document').'</td>';
        print '<td > '.$docsemployes->select_documents_type($item->fk_type_document,'fk_type_document',1,'rowid','name').'</td>';

        print '<td >'.$langs->trans('expirydate').'</td>';
        print '<td ><input type="text" class="datepickerdatefrformat" id="expirydate" name="expirydate" value="'.$expirydate.'"  autocomplete="off"/>';
    print '</td>';
    print '</tr>';

    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('photo').'</td>';
        
        print '<td colspan="3" id="documents">';
            print '<div id="wrapper_doc">';
                print '<ul>';
                    $dir = $conf->docsemployes->multidir_output[$object->entity].'/'.$id.'/pictures/';
                    if(file_exists($dir)){
                        if(is_dir($dir)){
                            $documents=scandir($dir);
                        }
                        foreach ($documents as  $doc) {
                            if (!in_array($doc,array(".","..","files"))) 
                            { 
                                print '<li>';
                                    $minifile = getImageFileNameForSize($doc, '');  
                                    $dt_files = getAdvancedPreviewUrl('docsemployes', $item->rowid.'/pictures/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));

                                    print ' <a data-file="'.$minifile.'">' ;
                                    print '<span class="remove_picture">Supprimer</span>';

                                        print '<img class="photo" title="'.$minifile.'" alt="Fichier binaire" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=docsemployes&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file='.$item->rowid.'/pictures/'.$minifile.'&perm=download" border="0" name="image" >';
                                    print '</a> ';
                                    print '<br>';
                                    // print '<div class="files" align="center" data-file="'.$minifile.'">'.img_delete('default','class="remove_picture"').' <span class="name_file">'.dol_trunc($minifile, 10).'</span></div>';
                                print '</li>';
                            }
                        }
                    }
                print '</ul>';
            print '</div>';
        print '</td>';
    print '</tr>';

    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('file').'</td>';
       
        print '<td colspan="2" id="documents">';
            print '<div id="wrapper_doc">';
                print '<ul>';
                    $array_img=[
                        'pdf'   => dol_buildpath('/docsemployes/images/pdf.png',2),
                        'doc'   => dol_buildpath('/docsemployes/images/doc.png',2),
                        'RTF'   => dol_buildpath('/docsemployes/images/doc.png',2),
                        'docx'  => dol_buildpath('/docsemployes/images/doc.png',2),
                        'ppt'   => dol_buildpath('/docsemployes/images/ppt.png',2),
                        'pptx'  => dol_buildpath('/docsemployes/images/ppt.png',2),
                        'xls'   => dol_buildpath('/docsemployes/images/xls.png',2),
                        'xlsx'  => dol_buildpath('/docsemployes/images/xls.png',2),
                        'txt'   => dol_buildpath('/docsemployes/images/text.png',2),
                        'sans'  => dol_buildpath('/docsemployes/images/sans.png',2),
                    ];

                    $dir = $conf->docsemployes->multidir_output[$object->entity].'/'.$id.'/files/';
                    if(file_exists($dir)){
                        if(is_dir($dir)){
                            $documents=scandir($dir);
                        }
                        foreach ($documents as  $doc) {
                            if (!in_array($doc,array(".","..","files"))) 
                            { 
                                print '<li>';
                                    $minifile=getImageFileNameForSize($doc, '');  
                                    $fileinfo = pathinfo($minifile);
                                    $ext = $fileinfo['extension'];
                                    if(array_key_exists($ext, $array_img)){
                                        $src = $array_img[$ext];
                                    }else{
                                        $src = $array_img['sans'];
                                    }
                                    print '<span data-file="'.$minifile.'" class="remove_file">Supprimer</span>';
                                    print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                                    print '<br>';
                                    // print '<div class="files" align="center" data-file="'.$minifile.'">'.img_delete('default','class="remove_file"').' <span class="name_file">'.dol_trunc($minifile, 10).'</span></div>';
                                print '</li>';
                            }
                        }
                    }
                   
                    // $caneditfield=1;
                    // if ($caneditfield)
                    // {
                    //     if ($item->file) print "<br>\n";
                    //     print '<table class="nobordernopadding">';
                    //         print '<tr><td><input type="hidden" name="file_deleted" id="file_deleted" ></td></tr>';
                    //     print '<tr><td><input type="file" class="flat" multiple name="file[]" multiple id="photoinput"></td></tr>';
                    //     print '</table>';
                    // }
                
                print '</ul>';
            print '</div>';
            print '<input type="file" class="flat" multiple name="file[]" multiple id="photoinput" >';
        print '</td>';
        print '<td style="vertical-align:bottom;">';
            
            if($documents){
                print '  <a href="./card.php?action=deleteall&id='.$id.'" class="deleteallfils" align="right">'.$langs->trans("deleteAll").'</a>';
            }
            print '<input type="hidden" name="file_deleted" id="file_deleted" >';
            print '<input type="hidden" name="photo_deleted" id="photo_deleted" >';
        print '</td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('description').'</td>';
        print '<td  colspan="3"> <textarea name="description" style="width:100%"> '.$item->description.' </textarea> </td>';
    print '</tr>';

    print '</tbody>';
    print '</table>';
    print '<br> <br>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" align="center">';
        print '<br>';
        print '<input type="submit" class="inpt_valid" style="display:none;" value="'.$langs->trans('Valider').'" name="bouton" class="butAction" />';
        print '<a class="butAction bt_valid">'.$langs->trans('Validate').'</a>';
        print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Annuler').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
       
}
?>