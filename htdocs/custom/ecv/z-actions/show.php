<?php

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');
    $filter .= (!empty($id)) ? " AND fk_ecv = '".$id."'" : "";
    $ecv->fetch($id);
    $ecvcertificats->fetchAll('', '', '', '', $filter);
    if(count($ecvcertificats->rows)>0){ 
        for($i=0; $i < count($ecvcertificats->rows); $i++) { 
            $ecvcertificats->fetch($ecvcertificats->rows[$i]->rowid);
            $ecvcertificats->delete();
        }
    }

    $ecvexperiences->fetchAll('', '', '', '', $filter);
    if(count($ecvexperiences->rows)>0){
       for($i=0; $i < count($ecvexperiences->rows); $i++) { 
            $ecvexperiences->fetch($ecvexperiences->rows[$i]->rowid);
            $ecvexperiences->delete();
        }
    }

    $ecvcompetances->fetchAll('', '', '', '', $filter);
    if(count($ecvcompetances->rows)>0){
       for($i=0; $i < count($ecvcompetances->rows); $i++) { 
            $ecvcompetances->fetch($ecvcompetances->rows[$i]->rowid);
            $ecvcompetances->delete();
        }
    }

    $ecvformations->fetchAll('', '', '', '', $filter);
    if(count($ecvformations->rows)>0){
       for($i=0; $i < count($ecvformations->rows); $i++) { 
            $ecvformations->fetch($ecvformations->rows[$i]->rowid);
            $ecvformations->delete();
        }
    }


    $ecvlangues->fetchAll('', '', '', '', $filter);
    if(count($ecvlangues->rows)>0){
       for($i=0; $i < count($ecvlangues->rows); $i++) { 
            $ecvlangues->fetch($ecvlangues->rows[$i]->rowid);
            $ecvlangues->delete();
        }
    }

    $ecvqualifications->fetchAll('', '', '', '', $filter);
    if(count($ecvqualifications->rows)>0){
       for($i=0; $i < count($ecvqualifications->rows); $i++) { 
            $ecvqualifications->fetch($ecvqualifications->rows[$i]->rowid);
            $ecvqualifications->delete();
        }
    }

    $error = $ecv->delete();
    // $error = 1;
    if ($error == 1) {
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
    // $head[$h][0] = dol_buildpath("/ecv/card.php?id=".$id, 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@ecv");


    if($action == "delete"){
        print $form->formconfirm("card.php?id=".$id."&page=".$page,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?page='.$page, 0, 1);
    }

    if (!$user->rights->ecv->lire) {
        accessforbidden();
    }
    // $ecv->fetchAll('','',0,0,' and rowid = '.$id);
    $ecv->fetch($id);
    $item = $ecv;
    // $extrafields = new ExtraFields($db);
    // $extralabels=$extrafields->fetch_name_optionals_label($item->table_element);
    $d=explode(' ', $item->datehire);
    $date_d = explode('-', $d[0]);
    $date = $date_d[2]."/".$date_d[1]."/".$date_d[0];

        
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" class="card_ecv">';

    print '<input type="hidden" name="confirm" value="no" id="confirm" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<table class="border nc_table_" width="100%">';
    print '<tbody>';

    if($item->useroradherent == 'ADHERENT'){
        $adherent_cv->fetch($item->fk_user);
        $nameusr = $adherent_cv->getNomUrl(1);
    }else{
        $user_cv->fetch($item->fk_user);
        $nameusr = $user_cv->getNomUrl(1);
    }
    print '<tr>';
        print '<td >'.$langs->trans('ecv_ref').'</td>';
        print '<td >'.$item->getNomUrl(0).'</td>';
    print '</tr>';

    print '<tr>';
        if($item->useroradherent == 'ADHERENT')
            print '<td >'.$langs->trans('ecv_Member').'</td>';
        else
            print '<td >'.$langs->trans('ecv_employe').'</td>';
        if(!empty($item->fk_user)){
            
        print '<td >'.$nameusr.'</td>';
        }
    print '</tr>';
    
    print '<tr>';
        print '<td >'.$langs->trans('ecv_date').'</td>';
        print '<td>'.$date.'</td>';
    print '</tr>';
  
    //$name_cv=['cv_1'=>'Modéle 1','cv_2'=>'Modéle 2','cv_3'=>'Modéle 3'];
    $name_cv=['cv_1'=>$langs->trans('Modéle').' 1','cv_2'=>$langs->trans('Modéle').' 2','cv_3'=>$langs->trans('Modéle').' 3'];

    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('ecv_modele').'</td>';
       
        print '<td colspan="3" id="files">';
        print '<div id="wrapper"><ul>';
        {
            if($item->module){
                $cv=dol_buildpath("/ecv/images/".$item->module.".png",2);
                print '<li> <a href="'.$cv.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv.'" ><br><b align="center">'.$name_cv[$item->module].'</b></a> </li>';
            }
        }
        print '</ul></div></td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_poste').'</td>';
        print '<td>'.$item->poste.'</td>';
    print '</tr>';
  
    print '<tr>';
        print '<td >'.$langs->trans('ecv_objectifs').'</td>';
        print '<td >'.$item->objectifs.'</td>';
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
            print '<a style="float:right;"  href="./card.php?id='.$id.'&action_export=pdf&id_cv='.$item->module.'" target="_blank" class="butAction">'.$langs->trans('Export_pdf').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';
    print '</form>';
     require_once dol_buildpath('/ecv/export/export_cv_3.php');
    // print '<div id="html">'.$html.'</div>';
}

?>
