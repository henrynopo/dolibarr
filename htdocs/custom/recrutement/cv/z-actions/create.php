<?php

    if ($action == 'create' && $request_method === 'POST') {


        $nom = GETPOST('nom');
        $type = GETPOST('type');
        if(GETPOST('type_')){
            $type=GETPOST('type_');
        }
        $date =date('Y-m-d');
        $poste = GETPOST('poste');
        $id_candidature = GETPOST('candidature');
        $url='';
        if(GETPOST('url')){
            $url=GETPOST('url');
        }
        $candidature->fetch($id_candidature);
        $insert = array(
            'nom'         =>  addslashes($nom),
            'type'        =>  $type,
            'date'        =>  $date,
            'fichier'     =>  trim($url),
            'poste'       =>  $candidature->poste,
            'candidature' =>  $id_candidature,
            'entity' =>  $conf->entity,
        );
        // print_r($insert);die();
        $avance = $cv->create(1,$insert);
        $cv->fetch($avance);
        // If no SQL error we redirect to the request card
        if ($avance > 0 ) {

            $TFile = $_FILES['document'];
            if($TFile['name']){
                
                $upload_dir = $conf->recrutement->dir_output.'/candidatures/'.$id_candidature.'/cv/'.$avance.'/';
                if (dol_mkdir($upload_dir) >= 0)
                {
                    $destfull = $upload_dir.$TFile['name'];
                    $info = pathinfo($destfull);
                    $filname = dol_sanitizeFileName($TFile['name']);
                    $fichier = ['fichier' => $filname];
                    $destfull   = $info['dirname'].'/'.$filname;

                    $destfull   = dol_string_nohtmltag($destfull);

                    $resupload  = dol_move_uploaded_file($TFile['tmp_name'], $destfull, 0, 0, $TFile['error'], 0);
                    $cv->update($avance,$fichier);
                }
            }

            header('Location: ./card.php?id='. $avance.'&action=edit&candidature='.$id_candidature);
            exit;
        } 
        else {
            header('Location: card.php?action=request&error=SQL_Create&msg='.$recrutement->error);
            exit;
        }
    }

    if($action == "add"){
        $poste=GETPOST('poste');
        $candidature=GETPOST('candidature');
        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" >';

        print '<input type="hidden" name="action" value="create" />';
        print '<input type="hidden" name="page" value="'.$page.'" />';
        print '<input type="hidden" name="poste" value="'.$poste.'" />';
        print '<input type="hidden" name="candidature" value="'.$candidature.'" />';
        print '<table class="border nc_table_" width="100%">';
            print '<tbody>';

            print '<tr>';
                print '<td class="fieldrequired firsttd200px">'.$langs->trans('nom').'</td>';
                print '<td ><input type="text" class="" required id="nom"  style="padding:8px 0px 8px 8px; width:100%" name="nom"  autocomplete="off"/>';
                print '</td>';
            print '</tr>';

            print '<tr>';
                print '<td >'.$langs->trans('type').'</td>';
                print '<td>';
                    print '<select name="type" id="type" class="width150">';
                        print '<option value="fichier">'.$langs->trans('fiche').'</option>';
                        print '<option value="url">'.$langs->trans('url').'</option>';
                    print '</select>';
                print '</td>';
            print '</tr>';

            print '<tr>';
                print '<td>'.$langs->trans('fichiers').'</td>';
                print '<td>';
                    print '<input type="file" name="document" id="fichier" style="display:none;">';
                    print '<input type="text" id="name" style="display:none;" readonly>';
                    print '<a class="butAction" id="importer" >'.$langs->trans('importer').'</a>';
                    print '<input type="text" name="url" id="url" style="display:none">';
                print '</td>';
            print '</tr>';

            print '</tbody>';
        print '</table>';
       
       
        // Actions
            print '<table class="" width="100%">';
            print '<tr>';
                print '<td colspan="2" >';
                print '<br>';
                print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction inpt_submit" style="display:none;" />';
                print '<a class="butAction" id="validatesumitbutton">'.$langs->trans('Validate').'</a>';
                print '<a href="./index.php?page='.$page.'&candidature='.$candidature.'" class="butAction">'.$langs->trans('Cancel').'</a>';
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
                $('#name').hide();
            }
            else{
                $('#url').hide();
                $('#importer').show();
            }
        });
        $('#fichier').change(function(){
            $val  = $('#fichier').val().split('\\');
            $name = $val[$val.length-1];
            $('#name').show();
            $('#name').val($name);
        });


    });
</script>