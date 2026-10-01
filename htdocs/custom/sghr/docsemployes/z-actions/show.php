<?php


if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');

    $docsemployes->fetch($id);

    $error = $docsemployes->delete();

    if ($error == 1) {
        $dire = $conf->docsemployes->multidir_output[$object->entity].'/';
        $dire_file = $dire.$id.'/files/';
        $dire_photo = $dire.$id.'/pictures/';
        
        if(is_dir($dire_file)){
            $files = scandir($dire_file);
            foreach ($files as $key => $file) {
                if($file != '.' && $file!='..'){
                    if(file_exists($dire_file.$file)){
                        unlink($dire_file.$file);
                    }
                }
            }
            rmdir($dire_file);
        }

        if(is_dir($dire_photo)){
            $photos = scandir($dire_photo);
            foreach ($photos as $key => $photo) {
                if($photo != '.' && $photo!='..'){
                    if(file_exists($dire_photo.$photo)){
                        unlink($dire_photo.$photo);
                    }
                }
            }
            rmdir($dire_photo);
        }
        // rmdir($dire);

        header('Location: index.php?page='.$page);
        exit;
    }
    else {      
        header('Location: card.php?delete=1&page='.$page);
        exit;
    }
}


if ($action == 'confirm_delete_all' && GETPOST('confirm') == 'yes' ) {
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');


        $dire = $conf->docsemployes->multidir_output[$object->entity].'/';
        $dire_file = $dire.$id.'/files/';
        $dire_photo = $dire.$id.'/pictures/';
        
        $files = scandir($dire_file);
        foreach ($files as $key => $file) {
            if($file != '.' && $file!='..'){
                if(file_exists($dire_file.$file)){
                    unlink($dire_file.$file);
                }
            }
        }
        rmdir($dire_file);

        $photos = scandir($dire_photo);
        foreach ($photos as $key => $photo) {
            if($photo != '.' && $photo!='..'){
                if(file_exists($dire_photo.$photo)){
                    unlink($dire_photo.$photo);
                }
            }
        }
        rmdir($dire_photo);
        // rmdir($dire);

        header('Location: card.php?id='.$id.'&page='.$page);
        exit;
   
}



// print_r($conf->global->MAIN_INFO_SOCIETE_NOM);die();

if( ($id && empty($action)) || $action == "delete" ){
    
     // print '<div align="right"> <a href="./notifications/index.php">Liste des Notifications</a> </div> <br>';

    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/sghr/docsemployes/card.php?id=".$id, 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@docsemployes");


    if($action == "delete"){
        print $form->formconfirm("card.php?id=".$id."&page=".$page,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?page='.$page, 0, 1);
    }

    if (!$user->rights->sghr->docs->read) {
        accessforbidden();
    }

    $docsemployes->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $docsemployes->rows[0];
    
    $object = new docsemployes($db);
    $object->fetch($item->rowid);


    $d=explode(' ', $item->issuedate);
    $date_d = explode('-', $d[0]);
    $issue = $date_d[2]."/".$date_d[1]."/".$date_d[0];
    $expiry = '';
    if($item->expirydate){

        $f=explode(' ', $item->expirydate);
        $date_f = explode('-', $f[0]);
        $expiry = $date_f[2]."/".$date_f[1]."/".$date_f[0];
   
    }else{
        $expiry = $langs->trans("nodertermin");
        
    }
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" class="card_docsemploys">';

    print '<input type="hidden" name="confirm" value="no" id="confirm" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';

    if(!empty($conf->global->PARCAUTOMOBILE_INTERVENTION_SEND_EMAIL)){
        if($item->sendmail) $checked = 'checked';
        print '<div class="test_sendmail"><input id="cb1" disabled class="flat checkforselect" type="checkbox" '.$checked.' name="sendmail" value="1"> <span class="title_sendmail">'.$langs->trans("test_sendmail_docs").'</span></div>';
    }
    print '<table class="noborder nc_table_" width="100%">';
    print '<tbody>';
    
    $user22->fetch($item->fk_user);
    print '<tr>';
        print '<td >'.$langs->trans('Employe');
        print '<td colspan="3">'.$user22->getNomUrl(1).'</td >';
    print '</tr>';


    print '<tr>';
        print '<td>'.$langs->trans('Email').' </td>';
        print '<td class="destinataire" > '.$item->destinataire.' </td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('numberdoc');
        print '<td>'.$item->number.'</td>';

        print '<td >'.$langs->trans('issuedate');
        print '<td>'.$issue.'</td>';
    print '</tr>';

    $type_document->fetch($item->fk_type_document);
    print '<tr>';
        print '<td >'.$langs->trans('fk_type_document').'.';
        // print '<td>'.$docsemployes->get_type_document($item->fk_type_document).'</td>';
        print '<td >'.$type_document->getNomUrl(0).'</td>';

        print '<td >'.$langs->trans('expirydate').'.';
        print '<td>'.$expiry.'</td>';
    print '</tr>';

    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('Photos').'</td>';
       
        print '<td colspan="3" id="files">';
        print '<div id="wrapper_doc"><ul>';
        {
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
                            

                            print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'">' ;
                                print '<img class="photo" title="'.$minifile.'" alt="Fichier binaire" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=docsemployes&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file='.$item->rowid.'/pictures/'.$minifile.'&perm=download" border="0" name="image" >';
                            print '</a> ';
                        print '</li>';
                    }
                }
            }
        }
        print '</ul></div></td>';
    print '</tr>';

    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('files').'</td>';
       
        print '<td colspan="3" id="files">';
        print '<div id="wrapper_doc"><ul>';
        $array_img=[
            'RTF'   => dol_buildpath('/sghr/docsemployes/images/doc.png',2),
            'doc'   => dol_buildpath('/sghr/docsemployes/images/doc.png',2),
            'docx'  => dol_buildpath('/sghr/docsemployes/images/doc.png',2),
            'ppt'   => dol_buildpath('/sghr/docsemployes/images/ppt.png',2),
            'pptx'  => dol_buildpath('/sghr/docsemployes/images/ppt.png',2),
            'xls'   => dol_buildpath('/sghr/docsemployes/images/xls.png',2),
            'xlsx'  => dol_buildpath('/sghr/docsemployes/images/xls.png',2),
            'txt'   => dol_buildpath('/sghr/docsemployes/images/text.png',2),
            'sans'  => dol_buildpath('/sghr/docsemployes/images/sans.png',2),
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
                        $dt_files = getAdvancedPreviewUrl('docsemployes', '/'.$id.'/files/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));
                        $fileinfo = pathinfo($minifile);
                        $ext = $fileinfo['extension'];
                        if($ext == 'pdf'){
                            $src_pdf =  dol_buildpath('/sghr/docsemployes/images/pdf.png',2);
                            print ' <a href="'.$dt_files['url'].'" class="'.$dt_files["css"].'" mime="'.$dt_files['mime'].'" target="'.$dt_files["target"].'"  title="'.$minifile.'">' ;
                                print '<img class="photo" alt="Fichier binaire" src="'.$src_pdf.'" border="0" name="image" >';
                            print '</a> ';
                        }
                        elseif(array_key_exists($ext, $array_img)){
                            $src = $array_img[$ext];
                            print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=docsemployes&file='.urlencode($item->rowid.'/files/'.$minifile).'" class="'.$dt_files["css"].'" mime="'.$dt_files['mime'].'" target="'.$dt_files["target"].'"  title="'.$minifile.'">' ;
                                print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                            print '</a> ';
                        }else{
                            $src = $array_img['sans'];
                            print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=docsemployes&file='.urlencode($item->rowid.'/files/'.$minifile).'" class="'.$dt_files["css"].'" mime="'.$dt_files['mime'].'" target="'.$dt_files["target"].'"  title="'.$minifile.'">' ;
                                print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                            print '</a> ';
                        }
                    print '</li>';
                }
            }
        }
        print '</ul></div></td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('description').'</td>';
        print '<td  colspan="3">  '.nl2br($item->description).'  </td>';
    print '</tr>';

    print '</tbody>';
    print '</table>';
    $date = date('Y-m-d H:i:s');
    // if($item->date_notification){
    //     $d=explode(' ', $item->issuedate);
    //     $date_d = explode('-', $d[0]);
    //     $notification = $date_d[2]."/".$date_d[1]."/".$date_d[0];
    //     print '<br> <br><div >';
    //         // print '<div style="background-color:#efe9e9 !important; padding:8px;"> Document-'.$item->number.' Expirer à '.$expiry.' <br> </div>';
    //         print '<div class="info"> ';
    //         print '<div>Dernière notification par email envoyée à '.$user22->getNomUrl(0).' le '.$notification.'</div>';
    //         print '</div> ';
    //         // print '<div style="padding-left:20px; background-color:white;"> ';
    //         // print '<div>La notification est envoyé à '.$user22->getNomUrl(0).' '.$user22->email.' le  '.$notification.'</div><br>';
    //         // print '</div> ';
    //         print '<br> ';
    //     print '</div>';
    
    // }
    


    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a href="./card.php?id='.$id.'&action=edit" class="butAction">'.$langs->trans('Modify').'</a>';
            print '<a href="./card.php?id='.$id.'&action=delete" class="butActionBTNC butActionDelete">'.$langs->trans('Delete').'</a>';
            print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Annuler').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
    print '<div id="lightbox" style="display:none; "><p>X</p><div id="content"><img src="" /></div></div>';
}

?>

<script>
    $(document).ready(function(){

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
