<?php 

function function_update(){
	
	global $user_info;
    $entity = GETPOST('entity')?GETPOST('entity'):$conf->entity;
    $user_id = GETPOST('user_id');
    $data = array(
     'salary_base'  =>  GETPOST('salary_base'),
     'stag'         =>  GETPOST("stag",'alpha') ? GETPOST("stag",'alpha') : 0,
     'declar'       =>  GETPOST("declar",'alpha') ? GETPOST("declar",'alpha') : 0,
     'mat'          =>  GETPOST('mat'),
     'nb_holiday'  =>  GETPOST('nb_holiday'),
     'date_embauche'  =>  GETPOST('date_embauche' ,'date(Y-m-d)'),
     'cin' =>  GETPOST('cin'),
     'immatriculation' =>  GETPOST('immatriculation'),
     'etablissement' =>  GETPOST('etablissement'),
     'etablissement_opt' =>  GETPOST('etablissement_opt'),
     'nbr_enfants' =>  GETPOST('nbr_enfants'),
     'situation_familiale' =>  GETPOST('situation_familiale'),
     'situation_assure' =>  GETPOST('situation_assure'),
     'entity'   => $entity,
     // 'clas' =>  GETPOST('clas'),
     // 'nbr_enfants' =>  GETPOST('nbr_enfants'),
     // 'situation_assure' =>  GETPOST('situation_assure'),
     // 'is_declared' =>  GETPOST('is_declared'),
     // 'num_compte' =>  GETPOST('num_compte'),
     // 'situation_familiale' =>  GETPOST('situation_familiale'),
     // 'date_embauche' =>  GETPOST('date_embauche'),
     // 'etablissement' =>  GETPOST('etablissement'),
     // 'etablissement_opt' =>  GETPOST('etablissement_opt'),
     // 'dates' =>  GETPOST('dates'),
     // 'datef' =>  GETPOST('datef'),
     );
   	        
    if($user_id){        	
   		$inserted = $user_info->update($user_id, $data,0);    
    }
    //If no SQL error we redirect to the request card
    if ($inserted > 0) {
    	header('Location: show.php?user_id='.$user_id);
        exit;
    } else {
        // Otherwise we display the request form with the SQL error message
        header('Location: index.php?action=edit&user_id='.$user_id);
        exit;
    }
}

if(!$abs){ 		
    $u = $user_info->fetch($user_id);
    print '<form method="POST"  action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
	print '<input type="hidden" name="action" value="update" />';
	print '<input type="hidden" name="user_id" value="'.$user_id.'" />';
    print '<input type="hidden" name="entity" value="'.$user_info->entity.'" />';
    print '<table width="100%" class="border">';
    print '<tr class="liste_titre" >';
    print '<th colspan="2" align="center">Modifier</th>';
    print '</tr>';
    print '<tbody>';
   
        print '<tr>';
            print '<td width="150px">Utilisateur</td>';
            print '<td>';
            print '<img src="'.dol_buildpath('/grh/img/object_user.png',1).'" class="classfortooltip">';
            print '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$u->rowid.'">'.$u->firstname.' '.$u->lastname.'</a>';
            print '</td>';
        print '</tr>';

        // salary_base
        print '<tr>';
        print '<td>'.$langs->trans("salary_base").'</td>';
        print '<td><input type="number" step="0.01" min="0" name="salary_base" value="'.number_format($u->salary_base,2,".","").'"></td>';
        print "</tr>";

        // CIN
        print '<tr><td>CIN</td>';
        print '<td>';
        print '<input size="8" type="text" name="cin" value="'.$u->cin.'" maxlength="8" />';
        print '</td>';
        print "</tr>";

        // immatriculation
        print '<tr><td>Immatriculation de la CNSS</td>';
        print '<td>';
        print '<input size="8" type="text" name="immatriculation" value="'.$u->immatriculation.'" maxlength="8" />';
        print '</td>';
        print "</tr>";

        // Stagaire
        print "<tr id='stag_slct'>";
        print '<td>Stagaire</td>';
        print '<td>';
            if($u->stag == 0){
                $slct0 = "selected";
                $slct1 = "";
            }else{
                $slct0 = "";
                $slct1 = "selected";
            }
            print '<select name="stag" id="stag">
                <option value="0" '.$slct0.'>Non</option>
                <option value="1" '.$slct1.'>Oui</option>
            </select>';
            print '</td></tr>';
        print '</td>';
        print '</tr>';
        // if ($u->stag==1) 
        // {
        //     print '<tr><td >Date ébut</td><td>  '.dol_print_date($u->datec,'day').'</td></tr>';
        //     print '</td></tr><tr><td >Date Fin</td><td>  '.dol_print_date($u->datef,'day').'</td></tr>';
        // }
        // if($u->stag==0){
            print '<tr id="declar_slct"><td>Salarié déclaré</td><td>';
            if($u->declar == 0){
                $slct0 = "selected";
                $slct1 = "";
            }else{
                $slct0 = "";
                $slct1 = "selected";
            }
            print '<select name="declar" id="declar">
                <option value="0" '.$slct0.'>Non</option>
                <option value="1" '.$slct1.'>Oui</option>
            </select>';
            print '</td></tr>';
        // }

        print '<tr><td>Matricule</td><td>';
        print '<input type="text" name="mat" value="'.$u->mat.'" />';
        print '</td></tr>';

        // Nombre des jours de congé
        print '<tr><td>Nombre des jours de congé</td><td>';
        print '<input type="text" name="nb_holiday" value="'.$u->nb_holiday.'" />';
        print '</td></tr>';

        // Date d\'embauche
        print '<tr><td>Date d\'embauche</td><td>';
        if (!empty($u->date_embauche) && $u->date_embauche != 0 ) {
            print '<input autocomplete="off" class="datepicker22" type="text" name="date_embauche" value="'.$u->date_embauche.'" />';
        }else{
            print '<input autocomplete="off" class="datepicker22" type="text" name="date_embauche" value="" />';
        }
        print '</td></tr>';

        // Etablissement
        print '<tr><td>Etablissement</td><td>';
        print '<input type="text" name="etablissement" value="'.$u->etablissement.'" />';
        print '</td></tr>';
        print '<tr><td>Etablissement option</td><td>';
        print '<input type="text" name="etablissement_opt" value="'.$u->etablissement_opt.'" />';
        print '</td></tr>';
        
        print '<tr id="declar_slct"><td>Situation familiale</td><td>';
            $slct00 = "";
            $slct0 = "";
            $slct1 = "";
        if($u->situation_familiale == 0){
            $slct0 = "selected";
        }elseif($u->situation_familiale == 1){
            $slct1 = "selected";
        }else{
            $slct00 = "selected";
        }
        print '<select name="situation_familiale" id="situation_familiale">
            <option value="-1" '.$slct00.'></option>
            <option value="0" '.$slct0.'>Célibataire</option>
            <option value="1" '.$slct1.'>Marié(e)</option>
        </select>';
        print '</td></tr>';

        print '<tr><td>Nombre d\'enfant</td><td>';
        print '<input type="text" name="nbr_enfants" value="'.$u->nbr_enfants.'" />';
        print '</td></tr>';

        // Situation assuré
        print '<tr><td>Situation assuré</td>';
        print '<td>';
        $situatons = array(1 => "Sortant", 2 => "Decédé", 3 => "Maternité", 4 => "Maladie", 5 => "Accident de travail", 6 => "Congé Sans salaire", 7 => "Maintenu Sans Salaire", 8 => "Maladie Professionnelle");
        print $form->selectarray('situation_assure', $situatons, $u->situation_assure, 1);
        print '</td>';
        print "</tr>\n";

        // print '<tr><td>Classement</td><td>';
        // print '<input type="text" name="clas" value="'.$u->clas.'" />';
        // print '</td></tr>';

    print '</tbody>';
    print '</table>';   
     print '<input style="padding: .7em 1em;font-weight: bold;margin-bottom: 1.8px;" type="submit" class="butAction" name="valider" value="Valider">';
     print '<a href="../index.php" class="butAction">Annuler</a>';
    print  '</form>';
	?>
    <script type="text/javascript">
        $( function() {
            $(".datepicker22").datepicker({
                dateFormat: 'yy-mm-dd'
            });
        $("#stag_slct #stag").change(function(){
            if(this.value == 1 ){
                $("#declar_slct #declar").val(0)
            //     $("#declar_slct").hide();
            // }else{
            //     $("#declar_slct").show();
            }
        }).trigger('change');
        } );
    </script>
    <?php

}
?>