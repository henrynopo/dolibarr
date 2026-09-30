<?php

if ($action == 'create' && $request_method === 'POST') {

    // // debut
    // $date = explode('/', GETPOST('debut'));
    // $debut = $date[2]."-".$date[1]."-".$date[0];

    // // fin
    // $date = explode('/', GETPOST('fin'));
    // $fin = $date[2]."-".$date[1]."-".$date[0];

    $value = GETPOST('name');

    $insert = array(
        'name'  =>  $value,
        'entity'  =>  $conf->entity,
    );
    $avance = $type_document->create(1,$insert);

    //If no SQL error we redirect to the request card
    if ($avance > 0) {
        //header('Location: index.php?id='.$getMarcheID);
        header('Location: ./card.php?id='. $avance);
        exit;
    } else {
        // Otherwise we display the request form with the SQL error message
        header('Location: card.php?action=request&error=SQL_Create&msg='.$type_document->error);
        exit;
    }
}

if($action == "add"){

    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/type_document/card.php?action=add", 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@type_document");


    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" >';

    print '<input type="hidden" name="action" value="create" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<table class="border" width="100%">';
    print '<tbody>';
    print '<tr>';
        print '<td >'.$langs->trans('Name').'</td>';
        print '<td ><input type="text" class="minwidth300" id="name" name="name" value="" required="required" autocomplete="off"/>';
    print '</td>';
    print '</tr>';

    // print '<tr>';
    //     print '<td >'.$langs->trans('Début').'</td>';
    //     print '<td ><input type="text" class="datepicker" id="debut" name="debut" value="'.date('d/m/Y').'" required="required" autocomplete="off"/>';
    // print '</td>';
    // print '</tr>';
    // print '<tr>';
    //     print '<td >'.$langs->trans('Fin').'</td>';
    //     print '<td ><input type="text" class="datepicker" id="fin" name="fin" value="'.date('d/m/Y', strtotime(date('Y-m-d'). ' + 1 days')).'" required="required" autocomplete="off"/>';
    // print '</td>';
    // print '</tr>';

    print '</tbody>';
    print '</table>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
        print '<br>';
        print '<input type="submit" value="'.$langs->trans('Valider').'" name="bouton" class="butAction" />';
        print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Annuler').'</a>';
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