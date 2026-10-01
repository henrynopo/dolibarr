<?php

if ($action == 'update' && $request_method === 'POST') {

    $page  = GETPOST('page');

    // $d1 = GETPOST('debut');
    // $f1 = GETPOST('fin');
    $candidature_id=GETPOST('candidature');
    $id=GETPOST('id');
    $cv->fetch($id);
    $nom = GETPOST('nom');
    $type = GETPOST('type');
    $date =date('Y-m-d');
    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;
    $url='';
    if($type == 'url' && GETPOST('url')){
        $url=GETPOST('url');
    }
    $data = array(
        'nom'         =>  $nom,
        'type'        =>  $type,
        'date'        =>  $date,
        'fichier'     =>  $url,
        'candidature' =>  $candidature_id,
        'entity' =>  $entity,
    );
    $isvalid = $cv->update($id, $data);
    $cv->fetch($id);
    // $composantes_new = (GETPOST('composantes_new'));
    // $composantes = (GETPOST('composantes'));
    // $composants_deleted = explode(',', GETPOST('composants_deleted'));
    $cv_deleted = GETPOST('cv_deleted');

    if ($isvalid > 0) {

         if(!empty($cv_deleted)){

        $upload_dir = $conf->recrutement->dir_output.'/candidatures/'.$candidature_id.'/cv/'.$id.'/'.$cv_deleted;
      
        if(file_exists($upload_dir)){
            unlink($upload_dir);
            $cv->update($id,['fichier'=>'']);
        }
       
    }

        if($_FILES['document']['name']){

            $TFile = $_FILES['document'];
            $upload_dir = $conf->recrutement->dir_output.'/candidatures/'.$candidature_id.'/cv/'.$id.'/';
            if (dol_mkdir($upload_dir) >= 0)
            {
                if($item->type == 'fichier'){
                    $upload_dir = $conf->recrutement->dir_output.'/candidatures/'.$candidature_id.'/cv/'.$id.'/'.$cv->fichier;
                    if(file_exists($upload_dir)){
                        unlink($upload_dir);
                        $cv->update($id,['fichier'=>'']);
                    }
                }
                $destfull = $upload_dir.$TFile['name'];
                $info = pathinfo($destfull);
                $filname = dol_sanitizeFileName($TFile['name']);
                $destfull   = $info['dirname'].'/'.$filname;

                $destfull   = dol_string_nohtmltag($destfull);

                $resupload  = dol_move_uploaded_file($TFile['tmp_name'], $destfull, 0, 0, $TFile['error'], 0);
                $fichiers = ['fichier' => $filname];
                $result = $cv->update($id,$fichiers);
            }



                
        }
        header('Location: ./card.php?id='.$id.'&candidature='.$candidature_id);
        exit;
    } 
    else {
        header('Location: ./card.php?id='. $id .'&update=0');
        exit;
    }
}
$etat =GETPOST('etat');


if($action == "edit"){

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="card_cvcandid">';

    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<input type="hidden" name="entity" value="'.$conf->entity.'" />';
    print '<input type="hidden" name="candidature" value="'.$candidature_id.'" />';
    print '<table class="border nc_table_" width="100%">';
        print '<tbody>';
            $cv->fetch($id);
            $item = $cv;
            print '<tr>';
                print '<td class="fieldrequired firsttd200px">'.$langs->trans('nom').'</td>';
                print '<td ><input type="text" class="" id="nom" required value="'.$item->nom.'"  style="padding:8px 0px 8px 8px; width:100%" name="nom"  autocomplete="off"/>';
                print '</td>';
            print '</tr>';

            print '<tr>';
                $candidature->fetch($item->candidature);
                print '<td >'.$langs->trans('candidature').'</td>';
                print '<td >'.$candidature->getNomUrl(0).'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('type').'</td>';
                print '<td>';
                    $select = '<select name="type" id="type" class="width150">';
                        $select.= '<option value="fichier" >'.$langs->trans('fiche').'</option>';
                        $select.= '<option value="url">'.$langs->trans('url').'</option>';
                    $select.= '</select>';
                    $select =str_replace('value="'.$item->type.'"','value="'.$item->type.'" selected',$select);
                    print $select;
                print '</td>';
            print '</tr>';

            print '<tr>';
                print '<td>'.$langs->trans('fichiers').'</td>';
                print '<td>';
                    if($item->type == 'fichier'){
                        $stl_url = 'display:none;';
                        $stl_file = '';
                    }else{
                        $stl_file = 'display:none';
                        $stl_url = '';
                    }
                    if($item->fichier){
                        print '<div id="d_wrapper" style="'.$stl_file.'"> <ul>';


                            $array_img=[
                                'doc'   => dol_buildpath('/sghr/recrutement/images/doc.png',2),
                                'docx'  => dol_buildpath('/sghr/recrutement/images/doc.png',2),
                                'ppt'   => dol_buildpath('/sghr/recrutement/images/ppt.png',2),
                                'pptx'  => dol_buildpath('/sghr/recrutement/images/ppt.png',2),
                                'xls'   => dol_buildpath('/sghr/recrutement/images/xls.png',2),
                                'xlsx'  => dol_buildpath('/sghr/recrutement/images/xls.png',2),
                                'txt'   => dol_buildpath('/sghr/recrutement/images/txt.png',2),
                                'sans'  => dol_buildpath('/sghr/recrutement/images/sans.png',2),
                            ];
                            
                            $ext = explode(".",$item->fichier);
                            $ext = $ext[count($ext) - 1];
                            print '<li>';
                                $minifile = getImageFileNameForSize($item->fichier, '');  
                                $dt_files = getAdvancedPreviewUrl('recrutement', 'candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));
                                if(in_array($ext, ['png','jpeg','jpg','gif','tif'])){
                                    print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'">' ;
                                        print '<img class="photo" title="'.$minifile.'" alt="Fichier binaire" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=recrutement&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file=candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'&perm=download" border="0" name="image" >';
                                    print '</a> ';
                                }
                                else{
                                    if(array_key_exists($ext, $array_img)){
                                        $src = $array_img[$ext];
                                        print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=recrutement&file=candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'"  title="'.$minifile.'">' ;
                                            print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                                        print '</a> ';
                                    }elseif($ext == 'pdf'){
                                        $src_pdf = dol_buildpath('/sghr/recrutement/images/pdf.png',2);
                                        print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'"  title="'.$minifile.'">' ;
                                            print '<img class="photo" alt="Fichier binaire" src="'.$src_pdf.'" border="0" name="image" >';
                                        print '</a> ';
                                    }else{
                                        $src = $array_img['sans'];
                                        print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=recrutement&file=candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'"  title="'.$minifile.'">' ;
                                            print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                                        print '</a> ';
                                    }
                                }
                                print '<div class="files" align="left" data-file="'.$minifile.'">'.img_delete('default','class="remove_cv"').' <span class="name_file">'.dol_trunc($minifile, 10).'</span></div>';

                            print '</li>';  

                            print '<input type="hidden" name="cv_deleted" id="cv_deleted" >';
                        print '</ul></div>';
                    }

                    // print '<img  src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/edit.png" id="edit">';
                print '<input type="text" style="'.$stl_url.'" value="'.$item->fichier.'"  name="url" id="url">';
                print '<a class="butAction" style="'.$stl_file.'" id="importer" >'.$langs->trans('importer').'</a>';
                print '<input type="file" name="document" id="fichier" style="display:none;">';
                print '</td>';
            print '</tr>';

        print '</tbody>';
    print '</table>';

    //  if($item->poste){
    //     $id_general=$item->poste;
    //     $source='poste';
    // }elseif($item->candidature){
    //     $id_general=$item->candidature;
    //     $source='candidature';
    // }

    $id_general=$candidature_id;
    $source='candidature';
    // Actions

    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a class="butAction" id="validatesumitbutton">'.$langs->trans('Validate').'</a>';
            print '<input type="submit" value="'.$langs->trans('Validate').'" style="display:none" name="bouton" class="butAction inpt_submit" />';
            print '<a href="./index.php?page='.$page.'&'.$source.'='.$id_general.'&candidature='.$candidature_id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';

    ?>
    <?php
}

?>


<script>
    $(function(){

        $('span.remove_cv').click(function() {
            var filename = $(this).parent().data("file");
            $('#cv_deleted').val(filename);
            $(this).parent().parent().remove();
        });


        $('#importer').click(function(){
            $('#fichier').trigger('click');
        });
        $('#edit').click(function(){
            $('#fichier').trigger('click');
        });
        $('#type').select2();
        $('#type').change(function(){
            if($('#type').val()=="url"){
                $('#url').show();
                $('#url').attr('readonly',false);
                $('#importer').hide();
                $('#edit').hide();
            }
            else{
                // $('#url').val('');
                $('#url').attr('readonly',true);
                $('#importer').show();
            }
        });
        $('#fichier').change(function(){
            $val  = $('#fichier').val().split('\\');
            $name = $val[$val.length-1];
            if($name!=''){
                $('#url').html('');
                $('#url').val($name);
            }
        });

    });
</script>

<style>
    #edit:hover{
        cursor: pointer;
    }
</style>