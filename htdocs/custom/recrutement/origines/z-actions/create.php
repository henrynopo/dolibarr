<?php

    if ($action == 'create' && $request_method === 'POST') {


        $source = GETPOST('source');
        $backtopage = GETPOST('backtopage');

        $insert = array(
            'source'         =>  addslashes($source),
            'entity'         =>  $conf->entity,
        );
        $avance = $origine->create(1,$insert);
        $origine->fetch($avance);
        // If no SQL error we redirect to the request card
        if ($avance > 0 ) {
           if($backtopage){
            header('Location:'. $backtopage.'&id_origine='.$avance);
            }else
                header('Location: ./card.php?id='. $avance.'&action=');
            // header('Location: ./card.php?id='. $avance.'&action=edit');
            exit;
        } 
        else {
            header('Location: card.php?action=request&error=SQL_Create&msg='.$recrutement->error);
            exit;
        }
    }

    if($action == "add"){
        $backtopage = GETPOST('backtopage');
        // die($backtopage);
        $id_poste=GETPOST('poste');
        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" >';

        print '<input type="hidden" name="action" value="create" />';
        print '<input type="hidden" name="page" value="'.$page.'" />';
        print '<input type="hidden" name="poste" value="'.$id_poste.'" />';
        print '<input type="hidden" name="backtopage" value="'.$backtopage.'" />';
        print '<table class="border nc_table_" width="100%">';
            print '<tbody>';

                print '<tr>';
                    print '<td class="fieldrequired firsttd200px" >'.$langs->trans('source').'</td>';
                    print '<td ><input type="text" class="" required id="source"  style="padding:8px 0px 8px 8px; width:100%" name="source"  autocomplete="off"/>';
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
                print '<a href="./index.php?page='.$page.'" class="butAction" onClick="javascript:history.go(-1)" >'.$langs->trans('Cancel').'</a>';
            print '</tr>';
            print '</table>';

        print '</form>';
    }

?>

