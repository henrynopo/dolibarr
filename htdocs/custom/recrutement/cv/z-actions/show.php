<?php

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $id_candidature  = GETPOST('id_candidature');
    $parms='';
    // die($candidature);
    $cv->fetch($id);
    if($cv){
        // $parms='&candidature='.$candidature;
        $parms='&candidature='.$cv->candidature;
    }

    $error = $cv->delete();

    if ($error == 1) {

        $upload_dir = $conf->recrutement->dir_output.'/candidatures/'.$id_candidature.'/cv/'.$id.'/'.$cv->fichier;
        if(file_exists($upload_dir)){
            unlink($upload_dir);
        }

        header('Location: index.php?delete='.$id.''.$parms.'&page='.$page);
        exit;
    }
    else {      
        header('Location: card.php?delete=1'.$parms.'&page='.$page);
        exit;
    }
}


if( ($id && empty($action)) || $action == "delete" ){
    
    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/avancementtravaux/card.php?id=".$id, 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@avancementtravaux");

    $id_candidature=GETPOST('candidature');

    $cv->fetch($id);
    $item = $cv;

    if($action == "delete"){
        print $form->formconfirm("card.php?id=".$id."&page=".$page.'candidature='.$item->candidature,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?page='.$page.'candidature='.$item->candidature, 0, 1);
    }

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'"  class="card_cvcandid">';

        print '<input type="hidden" name="confirm" itemue="no" id="confirm" />';
        print '<input type="hidden" name="id" value="'.$id.'" />';
        print '<input type="hidden" name="id_candidature" value="10" />';
        print '<input type="hidden" name="page" value="'.$page.'" />';
        print '<table class="border" width="100%">';

        print '<tbody>';
            print '<tr>';
                print '<td style="width:20% !important">'.$langs->trans('nom');
                print '<td style="width:80% !important">'.$item->nom.'</td>';
            print '</tr>';

            print '<tr>';

                    $candidature->fetch($item->candidature);
                    print '<td >'.$langs->trans('candidature').'</td>';
                    print '<td >'.$candidature->getNomUrl().'</td>';

            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('fichiers');
                print '<td>';
                    print '<div id="d_wrapper"><ul>';
                        print '<li>';            
                            if($item->fichier){

                                if($item->type == 'fichier' && $item->fichier){

                                    $array_img=[
                                        'doc'   => dol_buildpath('/recrutement/images/doc.png',2),
                                        'docx'  => dol_buildpath('/recrutement/images/doc.png',2),
                                        'ppt'   => dol_buildpath('/recrutement/images/ppt.png',2),
                                        'pptx'  => dol_buildpath('/recrutement/images/ppt.png',2),
                                        'xls'   => dol_buildpath('/recrutement/images/xls.png',2),
                                        'xlsx'  => dol_buildpath('/recrutement/images/xls.png',2),
                                        'txt'   => dol_buildpath('/recrutement/images/txt.png',2),
                                        'sans'  => dol_buildpath('/recrutement/images/sans.png',2),
                                    ];

                                    $ext = explode(".",$item->fichier);
                                    $ext = $ext[count($ext) - 1];
                                    $minifile = getImageFileNameForSize($item->fichier, '');  
                                    $dt_files = getAdvancedPreviewUrl('recrutement', '/candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));
                                    if(in_array($ext, ['png','jpeg','jpg','gif','tif'])){
                                        print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'" title="'.$minifile.'" >' ;
                                            print '<img class="photo" title="'.$minifile.'" alt="Fichier binaire" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=recrutement&entity='.(!empty($object->entity)?$object->entity:$conf->entity).'&file=candidatures/'.$id_candidature.'/cv/'.$item->rowid.'/'.$minifile.'&perm=download" border="0" name="image" >';
                                        print '</a> ';
                                    }

                                    elseif($ext == 'pdf'){
                                        print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" mime="'.$dt_files['mime'].'" target="'.$dt_files['target'].'" title="'.$minifile.'" >';
                                            $src = dol_buildpath('/recrutement/images/pdf.png',2);
                                            print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                                        print '</a>' ;
                                    }elseif(array_key_exists($ext,$array_img)){
                                        print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=recrutement&file=candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'" class="'.$dt_files['css'].'" mime="'.$dt_files['mime'].'" target="'.$dt_files['target'].'" title="'.$minifile.'" >';
                                            $src = $array_img[$ext];
                                            print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                                        print '</a>' ;
                                    }else{
                                        $src = $array_img['sans'];
                                        print ' <a href="'.DOL_URL_ROOT.'/document.php?modulepart=recrutement&file=candidatures/'.$item->candidature.'/cv/'.$item->rowid.'/'.$minifile.'"  title="'.$minifile.'">';
                                            print '<img class="photo" alt="Fichier binaire" src="'.$src.'" border="0" name="image" >';
                                        print '</a>' ;
                                    }

                                }elseif($item->type == 'url'){
                                    print '<a  href="'.$item->fichier.'" target="_blank"  name="action"  ><img alt="Photo" style="height:20px; max-width: 20px;" src="'.dol_buildpath('/recrutement/img/url.png',2).'" ></a>';
                                }

                            }
                        print '</li>';            
                    print '</ul></div>';
                print '</td>';
            print '</tr>';
        print '</tbody>';

    print '</table>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a href="./card.php?id='.$id.'&action=edit&candidature='.$item->candidature.'" class="butAction">'.$langs->trans('Modify').'</a>';
            print '<a href="./card.php?id='.$id.'&action=delete&candidature='.$item->candidature.'" class="butActionBTNC butActionDelete">'.$langs->trans('Delete').'</a>';
           
            print '<a href="./index.php?page='.$page.'&candidature='.$item->candidature.'" class="butAction">'.$langs->trans('Cancel').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
    
    
}

?>

<script>
    $(document).ready(function(){

         $('.delete_copie').click(function(e) {
            e.preventDefault();
            var filename = $(this).data("file");
            var file_deleted = $('#copie_deleted').val();
            if( file_deleted == '' )
                $('#copie_deleted').val(filename);            
            else
                $('#copie_deleted').val(file_deleted+','+filename);
            $(this).parent('li').remove();
        });

    });
</script>