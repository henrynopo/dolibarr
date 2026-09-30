<?php

    if ($action == 'create' && $request_method === 'POST') {


        $label = GETPOST('label');
       

        $insert = array(
            'label'         =>  addslashes($label),
        );
        $avance = $etiquette->create(1,$insert);
        $etiquette->fetch($avance);
        // If no SQL error we redirect to the request card
        if ($avance > 0 ) {
           
            header('Location: ./index.php');
            exit;
        } 
        else {
            header('Location: ./index.php?action=request&error=SQL_Create&msg='.$recrutement->error);
            exit;
        }
    }

    if($action == "add"){
        $id_poste=GETPOST('poste');
        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" >';

        print '<input type="hidden" name="action" value="create" />';
        print '<input type="hidden" name="page" value="'.$page.'" />';
        print '<input type="hidden" name="poste" value="'.$id_poste.'" />';
        print '<table class="border nc_table_" width="100%">';
            print '<tbody>';

                print '<tr>';
                    print '<td >'.$langs->trans('etape').'</td>';
                    print '<td ><input type="text" class="" id="label"  style="padding:8px 0px 8px 8px; width:100%" name="label"  autocomplete="off"/>';
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

        print '</form>';
    }

?>

<script>
    $(function(){
        $('#importer').click(function(){
            $('#fichier').trigger('click');
        });
        $('#type').select2();
        $('#type').change(function(){
            if($('#type').val()=="url"){
                $('#url').show();
                $('#importer').hide();
            }
            else{
                $('#url').hide();
                $('#importer').show();
            }
        });

    });
</script>