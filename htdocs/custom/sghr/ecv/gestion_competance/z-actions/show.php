<?php

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');

    $competances->fetch($id);
    // print_r($competances->icon);
    $error = $competances->delete();

    if ($error == 1) {
        $dir = $conf->ecv->dir_output.'/competances/';
            $file = $dir.$competances->icon;
        if($competances->icon){
            unlink($file);
        }
        header('Location: index.php?delete='.$id.'&page='.$page);
        exit;
    }
    else {      
        header('Location: card.php?delete=1&page='.$page);
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


    if($action == "delete"){
        print $form->formconfirm("card.php?id=".$id."&page=".$page,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?page='.$page, 0, 1);
    }

    if (!$user->rights->sghr->cv->read) {
        accessforbidden();
    }
    // $avancementtravaux->fetchAll('','',0,0,' and rowid = '.$id);
    $competances->fetch($id);
    $item = $competances;
   
        
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'"  class="competcecv">';

    print '<input type="hidden" name="confirm" value="no" id="confirm" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<table class="border" width="100%">';
    print '<tbody>';

    print '<tr>';
        print '<td class="titlefieldcreate">'.$langs->trans('ecv_competence');
        print '<td>'.$item->name.'</td>';
        // print_r($item->icon);die();
    print '</tr>';

    print '<tr>';
        print '<td  >'.$langs->trans('ecv_icon_competence');
        print '<td >';
            print '<div id="wrapper"><ul>';
            {   
                if(!empty($item->icon)){

                    print '<li>';
                        $minifile = getImageFileNameForSize($item->icon, '');  
                        $dt_files = getAdvancedPreviewUrl('ecv', 'competances/'.$minifile, 1, '&entity='.$conf->entity);

                        print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'">' ;
                            print '<img class="photo" title="'.$minifile.'" alt="Fichier binaire" src="'.$dt_files['url'].'" border="0" name="image" >';
                        print '</a> ';
                    print '</li>';
                }
            }
            print '</ul></div></td>';
    print '</tr>';
    print '</tbody>';
    print '</table>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a href="./card.php?id='.$id.'&action=edit" class="butAction">'.$langs->trans('Modify').'</a>';
            print '<a href="./card.php?id='.$id.'&action=delete" class="butActionBTNC butActionDelete">'.$langs->trans('Delete').'</a>';
            print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
            
            // print '<a style="float:right;" href="./card.php?id='.$id.'&action_export=pdf" target="_blank" class="butAction">'.$langs->trans('Export').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';
    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';

    print '</form>';
    
    
}

?>