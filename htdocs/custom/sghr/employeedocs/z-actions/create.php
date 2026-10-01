<?php
    // print_r($employeeDocs->get_document_user());
// die();
if ($action == 'create' && $request_method === 'POST') {
    global $dolibarr_main_data_root;

    // debut
    $date = explode('/', GETPOST('issuedate'));
    $issuedate = $date[2]."-".$date[1]."-".$date[0];

    // fin
    $datef = explode('/', GETPOST('expirydate'));
    $expirydate = $datef[2]."-".$datef[1]."-".$datef[0];

    $sendmail = GETPOST('sendmail','int') ? GETPOST('sendmail','int') : 0;
    $num = GETPOST('number');
    $description = addslashes(GETPOST('description'));
    $user22 = GETPOST('fk_user','int') ? GETPOST('fk_user','int') : 0;
    $issuedate = $issuedate;
    $expirydate = $expirydate;
    $document = GETPOST('fk_type_document');
    $destinataire = GETPOST('destinataire');

    
   
    
    $insert = array(
        'file'  =>  $file,
        'number'  =>  $num,
        'fk_user'  =>  $user22,
        'issuedate'  =>  $issuedate,
        'sendmail'   =>  $sendmail,
        'expirydate'  =>  $expirydate,
        'description'  =>  $description,
        'destinataire'  =>  $destinataire,
        'fk_type_document' =>  $document,
        'entity'           => $conf->entity,
    );
    $avance = $employeeDocs->create(1,$insert);

    if($avance > 0){

        $nb=count($_FILES['file']['name']);
        for ($i=0; $i < $nb ; $i++) { 
            $TFile = $_FILES['file'];

            $allowed = array ('image/pjpeg', 'image/jpeg', 'image/JPG', 'image/X-PNG', 'image/PNG', 'image/png', 'image/x-png');
            if (in_array($_FILES['file']['type'][$i], $allowed)) {
                $upload_dir = $conf->docsemployes->multidir_output[$conf->entity].'/'.$avance.'/pictures/';
            }else{
                $upload_dir = $conf->docsemployes->multidir_output[$conf->entity].'/'.$avance.'/files/';
            }
                
            if (dol_mkdir($upload_dir) >= 0)
            {
                $destfull = $upload_dir.$TFile['name'][$i];
                $info = pathinfo($destfull);
                
                $filname = dol_sanitizeFileName($TFile['name'][$i],'');
                $destfull   = $info['dirname'].'/'.$filname;
                $destfull   = dol_string_nohtmltag($destfull);
                $resupload  = dol_move_uploaded_file($TFile['tmp_name'][$i], $destfull, 0, 0, $TFile['error'][$i], 0);
            }
        }
    }


    //If no SQL error we redirect to the request card
    if ($avance > 0) {
        // header('Location: ./card.php?id='. $avance);
        header('Location: ./card.php?id='. $avance);
        exit;
    } else {
        // Otherwise we display the request form with the SQL error message
        header('Location: card.php?action=add&error=SQL_Create&msg='.$employeeDocs->error);
        exit;
    }
}

if($action == "add"){

    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/sghr/employeedocs/card.php?action=add", 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@docsemployes");

    

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="card_docsemploys">';

    print '<input type="hidden" name="action" value="create" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<table class="noborder nc_table_" width="100%">';
        print '<tbody>';
            print '<tr>';

                print '<td style="width:50%">';
                    if(!empty($conf->global->DOCSEMPLOYES_SEND_EMAIL)){
                        print '<div class="test_sendmail"><label for="cb1"><input id="cb1" class="flat checkforselect" type="checkbox" name="sendmail" value="1"> <span class="title_sendmail">'.$langs->trans("test_sendmail_docs").'</span></label> </div>';
                    }
                print '</td>';

               
            print '</tr>';
        print '</tbody>';
    print '</table>';


    print '<table class="noborder nc_table_" width="100%">';
    print '<tbody>';

    print '<tr>';
        print '<td>'.$langs->trans('Employe').' </td>';
        print '<td colspan="3">'.$employeeDocs->select_user(0,'fk_user',1,"rowid","login").'</td>';
    print '</tr>';

    print '<tr>';
        print '<td>'.$langs->trans('Email').' </td>';
        print '<td colspan="3" class="destinataire" > <input type="text" value="" class="minwidth300" name="destinataire"></td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('numberdoc').'</td>';
        print '<td ><input type="text" id="number" name="number" value="" class="minwidth300" required="required" autocomplete="off"/>';
        print '</td>';
        print '<td >'.$langs->trans('issuedate').'</td>';
        print '<td ><input type="text" class="datepickerdatefrformat" id="issuedate" name="issuedate" value="'.date('d/m/Y').'" required="required" autocomplete="off"/>';
        print '</td>';
    print '</tr>';
// '.docsemployes->select_documents_type(0,'document_type_id',1,"rowid","name").'
    
    print '<tr>';
        print '<td >'.$langs->trans('fk_type_document').'</td>';
        print '<td >'.$employeeDocs->select_documents_type(0,'fk_type_document',1,"rowid","name").'</td>';
        print '<td >'.$langs->trans('expirydate').'</td>';
        print '<td ><input type="text" class="datepickerdatefrformat" id="expirydate" name="expirydate" value="'.date('d/m/Y', strtotime(date('Y-m-d'). ' + 1 days')).'"  autocomplete="off"/>';
        print '</td>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('fichier').'</td>';
        print '<td colspan="3"><input type="file" class="" id="file" name="file[]" multiple  required="required" autocomplete="off"/>';
    print '</tr>';

    print '<tr>';
        print '<td >'.$langs->trans('description').'</td>';
        print '<td  colspan="3"> <textarea name="description" style="width:100%"> </textarea> </td>';
    print '</tr>';

    print '</tbody>';
    print '</table>';

    // Actions
    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
        print '<br>';
        print '<input type="submit" style="display:none" value="'.$langs->trans('Valider').'" name="bouton" class="butAction inpt_valid" />';
        print '<a class="butAction bt_valid">'.$langs->trans('Validate').'</a>';
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