<?php

if ($action == 'create' && $request_method === 'POST') {

    global $conf;

    $fk_user = GETPOST('fk_user');
    $date = GETPOST('datehire');
    $module = GETPOST('cv');
    $poste = addslashes(GETPOST('poste'));
    $objectifs = addslashes(GETPOST('objectifs'));
    $ref='';
    if(!empty(GETPOST('ref'))){
        $ref = GETPOST('ref');
    }
    //date
    $date_ = explode('/', $date);
    $date = $date_[2]."-".$date_[1]."-".$date_[0];
    $insert = array(
        'ref'  =>  $ref,
        'datehire'  =>  $date,
        'poste'  =>  $poste,
        'module'  =>  $module,
        'entity'  =>  $conf->entity,
        'objectifs'  =>  $objectifs,
    );


    $MenuMembers=$conf->global->ECV_GENERATE_CV_FOR_ADHERENTS;
    if(isset($MenuMembers) && $MenuMembers > 0){
        if(GETPOST('useroradherent') == 'ADHERENT'){
            $insert['useroradherent'] = 'ADHERENT';
            $fk_user = GETPOST('fk_adherent');
        }
        else
            $insert['useroradherent'] = 'USER';
    }

    $insert['fk_user'] = $fk_user;
    
    $avance = $ecv->create(1,$insert);
    $ecv->fetch($avance);


   
  

    //If no SQL error we redirect to the request card
    if ($avance > 0) {
        if($ecv->ref == ''){
            $data = ['ref' => $avance];
            $avance = $ecv->update($avance,$data);
        }
        // $dataprs =  array( 
        //     'exist'      => "no",
        //     'year'      => date('Y'),
        //     'fk_ecv'     =>  $avance,
        //     'fk_user'    =>  $fk_user,
        // );
        // $isvalid = $ecvpermis->create(1, $dataprs);

        //header('Location: index.php?id='.$getMarcheID);
        header('Location: ./card.php?id='. $avance);
        exit;
    } 
    else {
        // Otherwise we display the request form with the SQL error message
        header('Location: card.php?action=request&error=SQL_Create&msg='.$ecv->error);
        exit;
    }
}

if($action == "add"){

    global $conf;
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="competcecv card_ecv">';

    print '<input type="hidden" name="action" value="create" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<table class="border nc_table_" width="100%">';
    print '<tbody>';

    $MenuMembers=$conf->global->ECV_GENERATE_CV_FOR_ADHERENTS;
    if(isset($MenuMembers) && $MenuMembers > 0){
        print '<tr>';
            print '<td colspan="2" class="generatecvforcheck" align="center">';
            print '<label>';
            print '<input type="radio" name="useroradherent" checked value="USER"> '.$langs->trans("Users");
            print '</label>';
            print '<label>';
            print '<input type="radio" name="useroradherent" value="ADHERENT"> '.$langs->trans("MenuMembers");
            print '</label>';
            print '</td>';
        print '</tr>';
    }

    print '<tr>';
        print '<td >'.$langs->trans('ecv_ref').'</td>';
        print '<td ><input type="text" class="" id="ref" style="padding:8px 0px 8px 8px;" name="ref" autocomplete="off" required/>';
        print '</td>';
    print '</tr>';

    print '<tr>';
        print '<td id="titletdecv">'.$langs->trans('ecv_employe').'</td>';
        print '<td id="useroradherent">';
        print '<span id="users">';
        print $ecv->select_user(0,'fk_user',1,"rowid","login");
        print '</span>';
        print '<span id="adherents" style="display:none;">';
        print $ecv->select_adherent(0,'fk_adherent',1,"rowid","login");
        print '</span>';
        print '</td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_date').'</td>';
        print '<td ><input type="text" class="datepickerecvmod" id="date" style="width:15%; padding:8px 0px 8px 8px;" name="datehire" value="'.date('d/m/Y').'" required="required" autocomplete="off"/>';
        print '</td>';
    print '</tr>';

    print '<tr class="hideonsmartphone">';
        print '<td >'.$langs->trans('ecv_modele').'</td>';
       
        print '<td colspan="3" id="files">';
        print '<div id="wrapper"><ul>';
        {
            $cv_1 = dol_buildpath("/ecv/images/cv_1.png",2);
            $cv_2 = dol_buildpath("/ecv/images/cv_2.png",2);
            $cv_3 = dol_buildpath("/ecv/images/cv_3.png",2);
            $name_cv=['cv_1'=>$langs->trans('Modéle').' 1','cv_2'=>$langs->trans('Modéle').' 2','cv_3'=>$langs->trans('Modéle').' 3'];
            $cvs= '<li> <a href="'.$cv_1.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv_1.'" ></a> <br> <div align="center" ><label class="modele_cv"><input type="radio" name="cv" id="cv_1" class="modele_cv" value="cv_1" ><br><b align="center">'.$name_cv['cv_1'].'</b></label></div></li>';

            $cvs.= '<li> <a href="'.$cv_2.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv_2.'" ></a> <br> <div align="center" ><label class="modele_cv"><input type="radio" name="cv" checked id="cv_2" class="modele_cv" value="cv_2"><br><b align="center">'.$name_cv['cv_2'].'</b></label></div></li>';

            $cvs.= '<li> <a href="'.$cv_3.'" class="lightbox_trigger"> <img   alt="Photo" src="'.$cv_3.'" ></a> <br> <div align="center" ><label class="modele_cv"><input type="radio" name="cv" id="cv_3" class="modele_cv" value="cv_3" ><br><b align="center">'.$name_cv['cv_3'].'</b></label></div></li>';
            print $cvs;
        }
        print '</ul></div></td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_poste').'</td>';
        print '<td ><input type="text" style="width:100%; padding:8px 0px 8px 8px;" class="" id="poste" name="poste" value=""  autocomplete="off"/>';
        print '</td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('ecv_objectifs').'</td>';
        print '<td ><textarea style="width:100%; padding:8px 0px 8px 8px;" class="" id="objectifs" name="objectifs" ></textarea>';
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
        print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
    print '</tr>';
    print '</table>';

    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';
    print '</form>';

    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            // $('#fk_user').select2();
            $('#useroradherent>span select').select2();

            $('input[type=radio][name=useroradherent]').change(function() {
                $('#useroradherent>span').hide();
                if (this.value == 'ADHERENT') {
                    // $element = 'ADHERENT';
                    $('#useroradherent #adherents').show();
                    $('#titletdecv').html("<?php echo preg_replace('/\s\s+/', '', html_entity_decode($langs->trans('ecv_Member')) ); ?>");
                }
                else {
                    $('#useroradherent #users').show();
                    $('#titletdecv').html("<?php echo preg_replace('/\s\s+/', '', html_entity_decode($langs->trans('ecv_employe')) ); ?>");
                    // $element = 'USER';
                }
                // $.ajax({
                //     data:{'element':$element},
                //     url:"<?php echo dol_buildpath('/ecv/z-actions/check.php',2)?>",
                //     type:'POST',
                //     success:function(data){
                //         console.log(data);
                //         $('#useroradherent').html(data);
                //     }
                // });
            });

        });
    </script>
    <?php
}