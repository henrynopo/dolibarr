
<?php

if ($action == 'create' && $request_method === 'POST') {


    $name = GETPOST('name');
    $ecv->fetch($id__ecv);
    
    $insert = array(
        'name'      =>  $name,
        'icon'      =>  $_FILES['icon']['name'],
        'entity'    =>  $conf->entity,
        
    );
    $avance = $competances->create(1,$insert);
        
   
    //If no SQL error we redirect to the request card
    if ($avance > 0) {
       
        $competances->fetch($avance);
        if ($_FILES['icon']) { 
            $TFile = $_FILES['icon'];
            $copie = array('icon' => dol_sanitizeFileName($TFile['name'],''));
            $upload_dir = $conf->ecv->dir_output.'/competances/';
            if (dol_mkdir($upload_dir) >= 0)
            {
                $destfull = $upload_dir.$TFile['name'];
                $info     = pathinfo($destfull); 
                
                $filname    = dol_sanitizeFileName($TFile['name'],'');
                $destfull   = $info['dirname'].'/'.$filname;
                $destfull   = dol_string_nohtmltag($destfull);
                $resupload  = dol_move_uploaded_file($TFile['tmp_name'], $destfull, 0, 0, $TFile['error'], 0);
                $competances->update($avance,$copie);
            }
        }
        header('Location: ./card.php?id='. $avance);
        exit;
    } else {
        // Otherwise we display the request form with the SQL error message
        header('Location: card.php?action=request&error=SQL_Create&msg='.$ecvcompetances->error);
        exit;
    }
}

if($action == "add"){

    // $h = 0;
    // $head = array();
    // $head[$h][0] = dol_buildpath("/avancementtravaux/card.php?action=add", 1);
    // $head[$h][1] = $langs->trans($modname);
    // $head[$h][2] = 'affichage';
    // $h++;
    // dol_fiche_head($head,'affichage',"",0,"logo@avancementtravaux");


    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="competcecv">';

    print '<input type="hidden" name="action" value="create" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<table class="border nc_table_" width="100%">';
    print '<tr>';
        print '<td class="titlefieldcreate">'.$langs->trans('ecv_competence').'</td>';
        print '<td><input type="text" name="name" required /></td>';
    print '</tr>';
    print '<tbody>';
    print '<tr>';
        print '<td>'.$langs->trans('ecv_icon_competence').'</td>';
        print '<td>';
           print '<input type="file" accept="image/*" class="icon" id="icon" name="icon"  autocomplete="off"/>';
        print '</td>';
       
    print '</tr>';

    
    print '</tbody>';
    print '</table>';
    print '<br>';

    print '<br>';

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
    ?>
    

    <?php
}