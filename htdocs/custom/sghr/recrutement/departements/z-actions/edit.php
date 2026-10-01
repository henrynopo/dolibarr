<?php

if ($action == 'update' && $request_method === 'POST') {

    $page  = GETPOST('page');

    // $d1 = GETPOST('debut');
    // $f1 = GETPOST('fin');
    $id=GETPOST('id');
  
    $label = GETPOST('label');
    $gestionnaire = GETPOST('gestionnaire');
    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;
   

    $data = array(
        'label'         =>  addslashes($label),
        'gestionnaire'  =>  $gestionnaire,
        'entity'  =>  $entity,
    );

    $isvalid = $departement->update($id, $data);
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


if($action == "edit"){

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" entity="multipart/form-data" class="card_recdepart">';

    $departement->fetch($id);
    $item = $departement;
    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<input type="hidden" name="entity" value="'.$item->entity.'" />';
    print '<table class="border nc_table_" width="100%">';
        print '<tbody>';
            print '<tr>';
                print '<td >'.$langs->trans('label_departement').'</td>';
                print '<td ><input type="text" class="" id="label" value="'.$item->label.'" style="padding:8px 0px 8px 8px; width:100%" name="label"  autocomplete="off"/>';
                print '</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('gestionnaire').'</td>';
                print '<td>'.$poste->select_user($item->gestionnaire,'gestionnaire',1,"rowid","login").'</td>';
            print '</tr>';

        print '</tbody>';
    print '</table>';


    // Actions

    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<input type="submit" value="'.$langs->trans('Validate').'" style="" name="bouton" class="butAction" />';
            print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
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