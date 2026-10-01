<?php

if ($action == 'update' && $request_method === 'POST') {

    $page  = GETPOST('page');

    // $d1 = GETPOST('debut');
    // $f1 = GETPOST('fin');
   
   
    $fk_user    = GETPOST('fk_user');
    $date       = GETPOST('datehire');
    $poste      = addslashes(GETPOST('poste'));
    $objectifs  = addslashes(GETPOST('objectifs'));
    $ref = $id;
    if(!empty(GETPOST('ref'))){
        $ref = GETPOST('ref');
    }
    $cv     = GETPOST('cv');
    // $extrafields = new ExtraFields($db);

    //date
    $date_ = explode('/', $date);
    $date = $date_[2]."-".$date_[1]."-".$date_[0];
    $entity  = (GETPOSTISSET('entity') ? GETPOST('entity', 'int') : $conf->entity);
    
        // 'fk_user'  =>  $fk_user,
    $data =  array( 
        'ref'  =>  $ref,
        'datehire'  =>  $date,
        'poste'  =>  $poste,
        'module'  =>  $cv,
        'objectifs'  =>  $objectifs,
        'entity'  =>  $entity,
    );
    // print_r($data);die();
    $isvalid = $ecv->update($id, $data);
    
    if ($isvalid > 0) {
        header('Location: ./card.php?id='.$id);
        exit;
    } 
    else {
        header('Location: ./card.php?id='. $id .'&update=0');
        exit;
    }
}
if($action == "edit"){

    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/sghr/ecv/card.php?id=".$id."&action=edit", 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_file_head($head,'affichage',"",0,"logo@ecv");


    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="competcecv card_ecv">';

    $ecv->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $ecv->rows[0];

    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<input type="hidden" name="entity" value="'.$item->entity.'" />';

    $d=explode(' ', $item->datehire);
    $date_d = explode('-', $d[0]);
    $date = $date_d[2]."/".$date_d[1]."/".$date_d[0];

    
    // $user_cv->fetch($item->fk_user);
    if($item->useroradherent == 'ADHERENT'){
        $adherent_cv->fetch($item->fk_user);
        $nameusr = $adherent_cv->getNomUrl(1);
    }else{
        $user_cv->fetch($item->fk_user);
        $nameusr = $user_cv->getNomUrl(1);
    }

    print '<table class="border nc_table_" width="100%">';
    print '<tbody>';
    print '<tr>';
        print '<td >'.$langs->trans('ecv_ref').'</td>';
        print '<td ><input type="text" class="" id="ref" style=" padding:8px 0px 8px 8px;" name="ref" value="'.$item->ref.'"  autocomplete="off"/>';
        print '</td>';
    print '</tr>';
    
    print '<tr>';
        if($item->useroradherent == 'ADHERENT')
            print '<td >'.$langs->trans('ecv_Member').'</td>';
        else
            print '<td >'.$langs->trans('ecv_employe').'</td>';
        print '<td >'.$nameusr.'</td>';
        // print '<td>'.$ecv->select_user($item->fk_user,'fk_user',1,"rowid","login").'</td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_date').'</td>';
        print '<td ><input type="text" class="datepickerecvmod" id="date" style="width:15%; padding:8px 0px 8px 8px;" name="datehire" value="'.$date.'" required="required" autocomplete="off"/>';
        print '</td>';
    print '</tr>';

   
    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('ecv_modele').'</td>';
       
        print '<td colspan="3" id="files">';
        print '<div id="wrapper"><ul>';
        {
                $cv_1 = dol_buildpath("/sghr/ecv/images/cv_1.png",2);
                $cv_2 = dol_buildpath("/sghr/ecv/images/cv_2.png",2);
                $cv_3 = dol_buildpath("/sghr/ecv/images/cv_3.png",2);
    $name_cv=['cv_1'=>$langs->trans('Modéle').' 1','cv_2'=>$langs->trans('Modéle').' 2','cv_3'=>$langs->trans('Modéle').' 3'];

                $cvs= '<li> <a href="'.$cv_1.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv_1.'" ></a> <br> <div align="center" ><label class="modele_cv"><input type="radio" name="cv" id="cv_1" class="modele_cv" value="cv_1" ><br><b align="center">'.$name_cv['cv_1'].'</b></label></div></li>';

                $cvs.= '<li> <a href="'.$cv_2.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv_2.'" ></a> <br> <div align="center" ><label class="modele_cv"><input type="radio" name="cv" id="cv_2" class="modele_cv" value="cv_2"><br><b align="center">'.$name_cv['cv_2'].'</b></label></div></li>';

                $cvs.= '<li> <a href="'.$cv_3.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv_3.'" ></a> <br> <div align="center" ><label class="modele_cv"><input type="radio" name="cv" id="cv_3" class="modele_cv" value="cv_3" ><br><b align="center">'.$name_cv['cv_3'].'</b></label></div></li>';
            if($item->module){
                $cvs = str_replace('value="'.$item->module.'"', 'value="'.$item->module.'" checked', $cvs);
            }
                print $cvs;
        }
        print '</ul></div></td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_poste').'</td>';
        print '<td ><input type="text" style="width:100%; padding:8px 0px 8px 8px;" class="" id="poste" name="poste" value="'.$item->poste.'"  autocomplete="off"/>';
    print '</td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_objectifs').'</td>';
        print '<td ><textarea style="width:100%; padding:8px 0px 8px 8px;" class="" id="objectifs" name="objectifs" >'.$item->objectifs.'</textarea>';
    print '</td>';
    print '</tr>';


    print '</tbody>';
    print '</table>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
        print '<br>';
        print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction" />';
        print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';
    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';

    print '</form>';
        

    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            $('#fk_user').select2();
        });
    </script>
    <?php
}

?>