<?php

    if ($action == 'create' && $request_method === 'POST') {


        $label = GETPOST('label');
        $email = GETPOST('email');
        $lieu = GETPOST('lieu');
        $departement = GETPOST('departement');
        $nb_nouveauemploye = GETPOST('nb_nouveauemploye');
        $responsable_recrutement = GETPOST('responsable_recrutement');
        $description = addslashes(GETPOST('description'));
        $responsable_RH = GETPOST('responsable_RH');
        $date = GETPOST('date');
            
        $date=explode('/',$date);
        $date=$date[2].'-'.$date[1].'-'.$date[0];
        $insert = array(
            'label'                    =>  addslashes($label),
            'lieu'                     =>  $lieu,
            'email'                    =>  $email,
            'date'                     =>  $date,
            'status'                   =>  'Recrutementencours',
            'departement'              =>  $departement,
            'nb_nouveauemploye'        =>  $nb_nouveauemploye,
            'responsable_recrutement'  =>  $responsable_recrutement,
            'responsable_RH'           =>  $responsable_RH,
            'description'              =>  $description,
            'entity'                   =>  $conf->entity
        );
        $avance = $poste->create(1,$insert);
        $poste->fetch($avance);
        // If no SQL error we redirect to the request card
        if ($avance > 0 ) {
           
            header('Location: ./card.php?id='. $avance.'&action=edit');
            exit;
        } 
        else {
            header('Location: card.php?action=request&error=SQL_Create&msg='.$recrutement->error);
            exit;
        }
    }

    if($action == "add"){

        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="recrutmodule">';

        print '<input type="hidden" name="action" value="create" />';
        print '<input type="hidden" name="page" value="'.$page.'" />';
        print '<div class="fichecenter">';
        print '<div class="fichehalfleft">';
            // print '<div class="titesformsrecru" style=""><b>'.$langs->trans('recrutement').'</b></div>';
            print '<table class="border nc_table_" width="100%">';
                print '<thead>';
                    print '<tr class="liste_titre">';
                    print '<th colspan="2">'.$langs->trans('recrutement').'</th>';
                    print '</tr>';
                print '</thead>';
                print '<tbody>';

                print '<tr>';
                    print '<td class="fieldrequired firsttd200px" >'.$langs->trans('PosteOccupe').'</td>';
                    print '<td ><input type="text" class="" id="label" required style="padding:8px 0px 8px 8px; width:97%" name="label"  autocomplete="off"/>';
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td >'.$langs->trans('departement').'</td>';
                    print '<td>'.$poste->select_departement(0,'departement').'</td>';
                print '</tr>';

                print '<tr>';
                    print '<td>'.$langs->trans('lieu').'</td>';
                    print '<td>'.$form->select_company(0,'lieu','','SelectThirdParty').'</td>';
                print '</tr>';

                print '<tr>';
                    print '<td >'.$langs->trans('email').'</td>';
                    print '<td ><input type="text" class="" style="width:97%; padding:8px 0px 8px 8px;" name="email" value="" required="required" autocomplete="off"/>';
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<td >'.$langs->trans('responsable_recrutement').'</td>';
                    print '<td >'.$poste->select_user(0,'responsable_recrutement',1,"rowid","login").'</td>';
                print '</tr>';

                print '<tr>';
                    print '<td >'.$langs->trans('nb_nouveauemploye').'</td>';
                    print '<td ><input type="number" id="nb_nouveauemploye" name="nb_nouveauemploye" value="1" min="0"  autocomplete="off"/>';
                    print '</td>';
                print '</tr>';

                // print '<tr>';
                //     print '<td >'.$langs->trans('description_p').'</td>';
                //     print '<td >';
                //         print '<textarea name="description" style="width:100%;"></textarea>';
                //     print '</td>';
                // print '</tr>';

                print '</tbody>';
            print '</table>';
        print '</div>';
        print '<div class="fichehalfright">';
        print '<div class="ficheaddleft">';
            // print '<div class="titesformsrecru" style=""><b>'.$langs->trans('offre').'</b></div>';
            print '<table  class="border nc_table_" width="100%" >';
                print '<thead>';
                    print '<tr class="liste_titre">';
                    print '<th colspan="2">'.$langs->trans('offre').'</th>';
                    print '</tr>';
                print '</thead>';
                print '<tbody>';
                    print '<tr>';
                        print '<td class=" firsttd200px" >'.$langs->trans('responsable_RH').'</td>';
                        print '<td>'.$poste->select_user(0,'responsable_RH',1,"rowid","login").'</td>';
                    print '</tr>';
                    print '<tr>';
                        print '<td >'.$langs->trans('date_pr_empbouche').'</td>';
                        print '<td ><input type="text" name="date" value="'.date('d/m/Y').'" name="date" class="datepickerncon" id="date" ></td>';
                    print '</tr>';
                print '</tbody>';
            print '</table>';
        print '</div>';
        print '</div>';
        
        print '</div>';

        print '<div class="clear"></div>';

        print '<br>';

        print '<table class="border" width="100%">';
        print '<tr>';
            print '<td class="firsttd200px"" >'.$langs->trans('description_p').'</td>';
            print '<td >';
                print '<textarea name="description" style="width:calc(100% - 13px);"></textarea>';
            print '</td>';
        print '</tr>';
        print '</table>';

        // Actions
            print '<table class="" width="100%">';
                print '<tr>';
                    print '<td colspan="2" >';
                    print '<br>';
                    print '<a class="butAction" id="validatesumitbutton">'.$langs->trans('Validate').'</a>';
                    print '<a href="./postes.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
                    print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction"  style="display: none;"/>';
                print '</tr>';
            print '</table>';

        print '</form>';
    }

?>

<script>
    $(function(){
        $("#date").datepicker({
            dateFormat: "dd/mm/yy",
        });
    });
</script>