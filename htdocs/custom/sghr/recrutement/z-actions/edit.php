<?php

if ($action == 'update' && $request_method === 'POST') {

    $page  = GETPOST('page');

    // $d1 = GETPOST('debut');
    // $f1 = GETPOST('fin');
    $id=GETPOST('id');
   
    $label = GETPOST('label');
    $email = GETPOST('email');
    $lieu = GETPOST('lieu');
    $departement = GETPOST('departement');
    $nb_nouveauemploye = GETPOST('nb_nouveauemploye');
    $responsable_recrutement = GETPOST('responsable_recrutement');
    $description = addslashes(GETPOST('description'));
    $responsable_RH = GETPOST('responsable_RH');
    $date=explode('/',GETPOST('date'));
    $date=$date[2].'-'.$date[1].'-'.$date[0];
    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;

    $data = array(
        'label'         =>  addslashes($label),
        'lieu'          =>  $lieu,
        'email'         =>  $email,
        'date'          =>  $date,
        'departement'   =>  $departement,
        'nb_nouveauemploye'        => $nb_nouveauemploye,
        'responsable_recrutement'  =>  $responsable_recrutement,
        'responsable_RH' =>  $responsable_RH,
        'description'    =>  $description,
        'entity' =>  $conf->entity,
    );
    $isvalid = $poste->update($id, $data);
    // $composantes_new = (GETPOST('composantes_new'));
    // $composantes = (GETPOST('composantes'));
    // $composants_deleted = explode(',', GETPOST('composants_deleted'));
   
    if ($isvalid > 0) {
        header('Location: ./card.php?id='.$id);
        exit;
    } 
    else {
        header('Location: ./card.php?id='. $id .'&update=0');
        exit;
    }
}
$etat =GETPOST('etat');



if($action == "edit"){

    $poste->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $poste->rows[0];
    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="posterecrutement recrutmodule">';

    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$item->entity.'" />';
    print '<input type="hidden" name="entity" value="'.$page.'" />';
    print '<div style="display: inline-block;width:100%" >';
    //     if($item->status == "Recrutement en cours"){
    //         print '<a class="butActionBTNC butActionDelete" style="background: #eae4e1; float: right;" data-id="'.$item->rowid.'" id="arret" >'.$langs->trans('arrete').'</a>';
    //     }
    //     else
    //         print '<a class="butAction" style="background-color:#00A09D !important;color:white !important; float: right; " data-id="'.$item->rowid.'" id="lancer" >'.$langs->trans('lancer').'</a>';
    // print '</div>';
    // print '<div class="clear"></div>';
    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
        print '<div class="titesformsrecru" style=""><b>'.$langs->trans('recrutement').'</b></div>';
        print '<table class="border nc_table_ ncrecrutementtab" width="100%">';
            // print '<thead>';
            //     print '<tr class="liste_titre">';
            //     print '<th colspan="2">'.$langs->trans('recrutement').'</th>';
            //     print '</tr>';
            // print '</thead>';
            print '<tbody>';
            print '<tr>';
                print '<td class="fieldrequired firsttd200px" >'.$langs->trans('PosteOccupe').'</td>';
                print '<td ><input type="text" class="" id="label" value="'.$item->label.'"  style="padding:8px 0px 8px 8px; width:97%" name="label" required autocomplete="off"/>';
                print '</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('departement').'</td>';
                print '<td>'.$poste->select_departement($item->departement,'departement').'</td>';
            print '</tr>';

            print '<tr>';
                print '<td>'.$langs->trans('lieu').'</td>';
                print '<td>'.$form->select_company($item->lieu,'lieu','','SelectThirdParty').'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('email').'</td>';
                print '<td ><input type="text" class="" value="'.$item->email.'" style="width:97%; padding:8px 0px 8px 8px;" name="email" required="required" autocomplete="off"/>';
                print '</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('responsable_recrutement').'</td>';
                print '<td >'.$poste->select_user($item->responsable_recrutement,'responsable_recrutement',1,"rowid","login").'</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('nb_nouveauemploye').'</td>';
                print '<td ><input type="number" id="nb_nouveauemploye" name="nb_nouveauemploye" value="'.$item->nb_nouveauemploye.'" min="0"  autocomplete="off"/>';
                print '</td>';
            print '</tr>';

            // print '<tr>';
            //     print '<td >'.$langs->trans('description_p').'</td>';
            //     print '<td >';
            //         print '<textarea name="description" style="width:97%;">'.$item->description.'</textarea>';
            //     print '</td>';
            // print '</tr>';

            print '</tbody>';
        print '</table>';
    print '</div>';
    print '<div class="fichehalfright">';
    print '<div class="ficheaddleft">';
        print '<div class="titesformsrecru" style=""><b>'.$langs->trans('offre').'</b></div>';
        print '<table  class="border nc_table_" width="100%" >';
            // print '<thead>';
            //     print '<tr class="liste_titre">';
            //     print '<th colspan="2">'.$langs->trans('offre').'</th>';
            //     print '</tr>';
            // print '</thead>';
            print '<tbody>';
                print '<tr>';
                    print '<td class=" firsttd200px" >'.$langs->trans('responsable_RH').'</td>';
                    print '<td >'.$poste->select_user($item->responsable_RH,'responsable_RH',1,"rowid","login").'</td>';
                print '</tr>';
                $date=date('d/m/Y');
                if($item->date){
                    $date=explode('-', $item->date);
                    $date=$date[2].'/'.$date[1].'/'.$date[0];
                }
                print '<tr>';
                        print '<td >'.$langs->trans('date_pr_empbouche').'</td>';
                    print '<td ><input type="text" name="date" value="'.$date.'" class="datepickerncon" id="date" ></td>';
                print '</tr>';
            print '</tbody>';
        print '</table>';
        print '<div class="clear"></div>';
        print '<div class="divrectrubuttonaction">';
            if($item->status == "Recrutementencours"){
                print '<a class="butActionBTNC butActionDelete" style="background: #eae4e1;color:#633 !important;font-weight:bold;" data-id="'.$item->rowid.'" id="arret2" href="./card.php?id='.$id.'&etat=arreter">'.$langs->trans('arrete').'</a>';
                print '<a class="butActionBTNC butActionDelete" style="background: #eae4e1;color:#633 !important;font-weight:bold;" data-id="'.$item->rowid.'" id="finaliser2" href="./card.php?id='.$id.'&etat=finaliser">'.$langs->trans('finaliserrecru').'</a>';
            }
            else
                print '<a class="butAction" style="background-color:#00A09D !important;color:white !important;" data-id="'.$item->rowid.'" id="lancer2" href="./card.php?id='.$id.'&etat=lancer">'.$langs->trans('lancer').'</a>';
        print '</div>';

       
    print '</div>';
    print '</div>';

    print '<div class="clear"></div>';
        
    print '<br>';

    print '<table class="border" width="100%">';
    print '<tr>';
        print '<td class="firsttd200px" >'.$langs->trans('description_p').'</td>';
        print '<td >';
            print '<textarea name="description" style="width:97%;">'.$item->description.'</textarea>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</div>';

    // Actions

    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a class="butAction" id="validatesumitbutton">'.$langs->trans('Validate').'</a>';
            print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
            print '<input type="submit" value="'.$langs->trans('Validate').'" style="display: none;" name="bouton" class="butAction" />';
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
        $("#date").datepicker({
            dateFormat: "dd/mm/yy"
        });
        $('#arret').click(function(){
            $id=$('#arret').data('id');
            console.log($id);
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=arreter',2); ?>",
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

        $('#finaliser').click(function(){
            $id=$('#finaliser').data('id');
            console.log($id);
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=finaliser',2); ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#finaliser').css('display','none');
                        // $('#lancer').css('display','block');
                    }
                        location.reload();
                }
            });
        });


        $('#lancer').click(function(){
            $id=$('#lancer').data('id');
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=lancer',2); ?>",
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