<?php

if ($action == 'update' && $request_method === 'POST') {

    $page  = GETPOST('page');

    // $d1 = GETPOST('debut');
    // $f1 = GETPOST('fin');

    // // debut
    // $date = explode('/', $d1);
    // $debut = $date[2]."-".$date[1]."-".$date[0];

    // // fin
    // $date = explode('/', $f1);
    // $fin = $date[2]."-".$date[1]."-".$date[0];
    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;

    $value = GETPOST('name');
    $data =  array( 
        'name'   =>  $value,
        'entity' =>  $entity,
    );

    $isvalid = $type_document->update($id, $data);

    if ($isvalid > 0) {
        header('Location: ./card.php?id='.$id);
        exit;
    } else {
        header('Location: ./card.php?id='. $id .'&update=0');
        exit;
    }
}

if($action == "edit"){

    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/type_document/card.php?id=".$id."&action=edit", 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@type_document");


    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" >';
    $type_document->fetchAll('','',0,0,' and rowid = '.$id);
    $item = $type_document->rows[0];

    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<input type="hidden" name="entity" value="'.$item->entity.'" />';


    print '<table class="border" width="100%">';
    print '<tbody>';
    print '<tr>';
        print '<td >'.$langs->trans('Name').'</td>';
        print '<td ><input type="text" class="minwidth300" id="name" name="name" value="'.$item->name.'" required="required" autocomplete="off"/>';
    print '</td>';
    print '</tr>';
    print '</tbody>';
    print '</table>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
        print '<br>';
        print '<input type="submit" value="'.$langs->trans('Valider').'" name="bouton" class="butAction" />';
        print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Annuler').'</a>';
        print '</td>';
    print '</tr>';
    print '</table>';

    print '</form>';
    
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function() {
            $("input.datepicker").datepicker({
                dateFormat: "dd/mm/yy"
            });
        });
    </script>
    <?php
}
?>