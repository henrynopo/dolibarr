<?php

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' ) {
    
    if (!$id || $id <= 0) {
        header('Location: ./card.php?action=request&error=dalete_failed&id='.$id);
        exit;
    }

    $page  = GETPOST('page');

    $poste->fetch($id);

    $error = $poste->delete();

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
    // $head[$h][0] = dol_buildpath("/avancementtravaux/card.php?id=".$id, 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@avancementtravaux");


    if($action == "delete"){
        print $form->formconfirm("card.php?id=".$id."&page=".$page,$langs->trans('Confirmation') , $langs->trans('msgconfirmdelet'),"confirm_delete", 'index.php?page='.$page, 0, 1);
    }

    // if (!$user->rights->avancementtravaux->lire) {
    //     accessforbidden();
    // }
    // $avancementtravaux->fetchAll('','',0,0,' and rowid = '.$id);
   
    // $extrafields = new ExtraFields($db);
    // $extralabels=$extrafields->fetch_name_optionals_label($item->table_element);
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" class="posterecrutement recrutmodule">';

    print '<input type="hidden" name="confirm" itemue="no" id="confirm" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    $poste->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $poste->rows[0];

    // print '<div style="display: inline-block;width:100%" >';
    //     if($item->status == "Recrutementencours"){
    //         print '<a class="butActionBTNC butActionDelete" style=" float: right;" data-id="'.$item->rowid.'" id="arret" >'.$langs->trans('arrete').'</a>';
    //     }
    //     else
    //         print '<a class="butAction" style="background-color:#00A09D !important;color:white !important; float: right; " data-id="'.$item->rowid.'" id="lancer" >'.$langs->trans('lancer').'</a>';
    // print '</div>';
    // print '<div class="clear"></div>';
    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
        print '<div class="titesformsrecru" style=""><b>'.$langs->trans('recrutement').'</b></div>';
        print '<table class="noborder" width="100%">';
            // print '<thead>';
            //     print '<tr class="liste_titre">';
            //     print '<th colspan="2">'.$langs->trans('recrutement').'</th>';
            //     print '</tr>';
            // print '</thead>';
            print '<tbody>';
            $departement->fetch($item->departement);
            print '<tr>';
                print '<td class="firsttd200px">'.$langs->trans('PosteOccupe').'</td>';
                print '<td >'.$item->label.'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('departement').'</td>';
                print '<td>'.$departement->label.'</td>';
            print '</tr>';

            print '<tr>';
                print '<td>'.$langs->trans('lieu').'</td>';
                $societe->fetch($item->lieu);
                // print '<td>'.$form->select_company($item->lieu,'lieu','','SelectThirdParty').'</td>';
                print '<td>'.$societe->getNomUrl(1).'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('email').'</td>';
                print '<td >'.$item->email.'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('responsable_recrutement').'</td>';
                $responsable_recrutement = new user($db);
                $responsable_recrutement->fetch($item->responsable_recrutement);
                // print '<td >'.$responsable_recrutement->firstname.' '.$responsable_recrutement->lastname.'</td>';
                print '<td >'.$responsable_recrutement->getNomUrl(1).'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('nb_nouveauemploye').'</td>';
                print '<td >'.$item->nb_nouveauemploye.'</td>';
            print '</tr>';

            // print '<tr>';
            //     print '<td >'.$langs->trans('description_p').'</td>';
            //     print '<td >'.$item->description.'</td>';
            // print '</tr>';

        print '</tbody>';
        print '</table>';
    print '</div>';

    print '<div class="fichehalfright">';
    print '<div class="ficheaddleft">';
        print '<div class="titesformsrecru" style=""><b>'.$langs->trans('offre').'</b></div>';
            print '<table  class="noborder nc_table_" width="100%" >';
                // print '<thead>';
                //     print '<tr class="liste_titre">';
                //     print '<th colspan="2">'.$langs->trans('offre').'</th>';
                //     print '</tr>';
                // print '</thead>';
                print '<tbody>';
                    print '<tr>';
                        print '<td class=" firsttd200px" >'.$langs->trans('responsable_RH').'</td>';
                        $responsable_RH = new user($db);
                        $responsable_RH->fetch($item->responsable_RH);
                        // print '<td >'.$responsable_RH->firstname.' '.$responsable_recrutement->lastname.'</td>';
                        print '<td >'.$responsable_RH->getNomUrl(1).'</td>';
                    print '</tr>';
                    print '<tr>';
                        $data='';
                        if($item->date){
                            $date=explode('-', $item->date);
                            $date=$date[2].'/'.$date[1].'/'.$date[0];
                        }
                        print '<td >'.$langs->trans('date_pr_empbouche').'</td>';
                        print '<td >'.$date.'</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td >'.$langs->trans('Status').'</td>';
                        print '<td>';
                        if($item->status == 'Recrutementencours'){
                            $cl='en-cours';
                            $arr_count[0]++;
                        }
                        else if($item->status=='Recrutementfinalise'){
                            $cl='finalis';
                            $arr_count[1]++;
                        }
                        else if($item->status=='Recrutementarrete'){
                            $cl='arret';
                            $arr_count[2]++;
                        }
                            print'<span class="color_etat '.$cl.'" ><b>';
                            print '</span>&nbsp;&nbsp;';
                            print '<b>'.$langs->trans($item->status).'</b>';
                        print '</td>';
                    print '</tr>';
                print '</tbody>';
            print '</table>';
            print '<div class="clear"></div>';
            print '<div class="divrectrubuttonaction">';
                if($item->status == "Recrutementencours"){
                    print '<a class="butActionBTNC butActionDelete" style="background: #eae4e1;color:#633 !important;font-weight:bold;" data-id="'.$item->rowid.'" id="arret2" href="./card.php?id='.$id.'&etat=arreter">'.$langs->trans('arrete').'</a>';
                    print '<a class="butActionBTNC butActionDelete" href="./card.php?id='.$id.'&etat=finaliser" style="background: #eae4e1;color:#633 !important;font-weight:bold;" data-id="'.$item->rowid.'" id="finaliser" >'.$langs->trans('finaliserrecru').'</a>';

                }
                else
                    print '<a class="butAction" style="background-color:#00A09D !important;color:white !important;" data-id="'.$item->rowid.'" id="lancer2" href="./card.php?id='.$id.'&etat=lancer">'.$langs->trans('lancer').'</a>';
            print '</div>';
            
    print '</div>';
    print '</div>';

    print '<div class="clear"></div>';
        
    print '<br>';

    print '<table class="noborder" width="100%">';
    print '<tr>';
        print '<td class="firsttd200px" >'.$langs->trans('description_p').'</td>';
        print '<td >'.nl2br($item->description).'</td>';
    print '</tr>';
    print '</table>';

    print '</div>';
    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a href="./card.php?id='.$id.'&action=edit" class="butAction">'.$langs->trans('Modify').'</a>';
            print '<a href="./card.php?id='.$id.'&action=delete" class="butActionBTNC butActionDelete">'.$langs->trans('Delete').'</a>';
            print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
            print '<a style="float:right;"  href="./card.php?id='.$id.'&action=pdf" target="_blank" class="butAction">'.$langs->trans('Export_pdf').'</a>';
            
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
    
    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';
    
}

?>

<script>
    $(function(){
        $('#arret').click(function(){
            $id=$('#arret').data('id');
            console.log($id);
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=arreter',2) ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#arret').css('display','none');
                        $('#lancer').css('display','block');
                    }
                        location.reload();
                }
            });
        });

        // $('#finaliser').click(function(){
        //     $id=$('#finaliser').data('id');
        //     console.log($id);
        //     $.ajax({
        //         data:{'poste':$id,},
        //         url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=finaliser',2) ?>",
        //         type:'POST',
        //         success:function($data){
        //             if($data == 'Ok'){
        //                 $('#finaliser').css('display','none');
        //                 // $('#lancer').css('display','block');
        //             }
        //                 // location.reload();
        //         }
        //     });
        // });


        $('#lancer').click(function(){
            $id=$('#lancer').data('id');
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=lancer',2) ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#lancer').css('display','none');
                        $('#arret').css('display','block');
                    }
                        location.reload();
                }
            });
        });
        
    });
</script>