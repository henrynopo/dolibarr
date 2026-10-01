<!-- 
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo"
        crossorigin="anonymous">
</script> -->
<?php

    if ($action == 'create' && $request_method === 'POST') {

        $etape = GETPOST('etape');
        $sujet = GETPOST('sujet');
        $nom = GETPOST('nom');
        $etiquettes = 1;
        // $etiquettes=json_encode($etiquettes);
        $contact = GETPOST('contact');
        $email = GETPOST('email');
        $tel = GETPOST('tel');
        $mobil = GETPOST('mobile');
        $niveau = GETPOST('niveau');

        $departement = GETPOST('departement');
        $poste = GETPOST('poste');

        $responsable = GETPOST('responsable');
        $appreciation = GETPOST('appreciation');
        // die($appreciation);
        $apport_par = GETPOST('apport_par');
        $origine = GETPOST('origine');
        $salaire_propose = GETPOST('salaire_propose');
        $salaire_demande = GETPOST('salaire_demande');
        $date_disponible = GETPOST('date_disponible');
        $resume = GETPOST('resume');
        
        $date=explode('/',$date_disponible);
        $date_disponible=$date[2].'-'.$date[1].'-'.$date[0];

        $insert = array(
            'sujet'             =>  $sujet,
            'nom'               =>  $nom,
            'etiquettes'        =>  $etiquettes,
            'contact'           =>  $contact,
            'email'             =>  $email,
            'tel'               =>  $tel,
            'mobile'            =>  $mobil,
            'niveau'            =>  $niveau,
            'poste'             =>  $poste,
            'departement'       =>  $departement,
            'responsable'       =>  $responsable,
            'appreciation'      =>  $appreciation,
            'apport_par'        =>  $apport_par,
            'origine'           =>  $origine,
            'salaire_demande'   =>  $salaire_demande,
            'salaire_propose'   =>  $salaire_propose,
            'date_disponible'   =>  $date_disponible,
            'resume'            =>  $resume,
            'etape'             =>  $etape,
        );
        $avance = $candidature->create(1,$insert);
        $candidature->fetch($avance);
        // If no SQL error we redirect to the request card
        if ($avance > 0 ) {
            header('Location: ./card.php?id='. $avance.'');
            exit;
        } 
        else {
            header('Location: card.php?action=request&error=SQL_Create&msg='.$recrutement->error);
            exit;
        }
    }

    if($action == "add"){

        print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" >';

        $etapes = new etapescandidature($db);

        print '<input type="hidden" name="action" value="create" />';
        print '<input type="hidden" name="page" value="'.$page.'" />';
        print '<br><br>';
        print '<div style="width:100%;padding:5px;margin-bottom:20px;background-color:rgb(60,70,100);"><strong style="font-size:16px;color:white;">'.$langs->trans('etapes').'</strong></div>';
        print '<div>';
            $etapes->fetchAll();
            $nb=count($etapes->rows);
            $etapecandid ='';
            for ($i=0; $i < $nb; $i++) { 
                $etap=$etapes->rows[$i];

                $etapecandid .= '<label class="etapes" >';
                    $etapecandid .= '<input type="radio" id="'.$etap->rowid.'"  style="display:none;" value="'.$etap->rowid.'" name="etape" class="etapes">';
                    $etapecandid .= ' <span class="radio"></span>';
                    $etapecandid .= '<span style="font-size:14px"> '.$langs->trans($etap->label).'</span>';
                $etapecandid .= '</label>';


            }
            // print_r($item->etapes);die();
                $etapecandid = str_replace('<input type="radio" id="'.$item->etape.'"', '<input type="radio" id="'.$item->etape.'" checked ', $etapecandid);
                print $etapecandid;
                // $etapecandid = '<label class="etapes" > <input type="radio" id="" name="etape" class="etapes"> <span style="font-size:14px">ffff</span></label>';
                
        print '</div>';
        print '<br>';
        print '<div style="width:100%; padding:5px; margin:10px 0 10px;background-color:rgb(60,70,100);"><strong style="font-size:16px;color:white;">'.$langs->trans('sujet').'</strong></div>';

        print '<table class="border nc_table_" width="100%">';
            print '<tbody>';

                print '<tr>';
                    print '<td colspan="2"><input type="text" class="" id="sujet"  style="padding:8px 0px 8px 8px; width:80%" name="sujet"  autocomplete="off"/>';
                    print '</td>';
                print '</tr>';

                print '<tr>';
                    print '<th style="width:30%; text-align:left; width:20%">'.$langs->trans('nom_candidat').'</th>';
                    print '<td>'.$langs->trans('etiquettes').'</td>';
                print '</tr>';

                print '<tr>';
                    print '<td  style="width:30%;"><input type="text" class="" id="nom_candidat" style="padding:8px 0px 8px 8px; width:80%" name="nom"  autocomplete="off"/></td>';
                    print '<td>'.$candidature->select_etiquette(0,'etiquettes[]').'</td>';
                print '</tr>';

            print '</tbody>';
        print '</table>';



        print '<div style="width:100% !important;">';
            print '<div style="width:50%; float:left;">';
                print '<table class="border nc_table_" width="100%">';
                    print '<body>';
                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('contact').'</th>';
                            print '<td >'.$candidature->select_contact(0,'contact').'</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('email_contact').'</th>';
                            print '<td ><input type="text" class="" id="email" style="width:72%; padding:8px 0px 8px 8px;" name="email" value="" required="required" autocomplete="off"/>';
                            print '</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('tel').'</th>';
                            print '<td ><input type="text" class="" id="tel" style="width:72%; padding:8px 0px 8px 8px;" name="tel" value="" required="required" autocomplete="off"/>';
                            print '</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('mobile').'</th>';
                            print '<td ><input type="text" class="" id="mobile" style="width:72%; padding:8px 0px 8px 8px;" name="mobile" value="" required="required" autocomplete="off"/>';
                            print '</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('niveau').'</th>';
                            print '<td >'.$candidature->select_niveau('').'</td>';
                        print '</tr>';
                        

                    print '</tbody>';
                print '</table>';
            print '</div>';

            print '<div style="width:48%; float:left; margin-left:20px !important;">';
                print '<table  class="border nc_table_" width="100%" >';
                    print '<tbody>';
                        print '<tr>';
                            print '<th style="padding:8px 0px 8px 8px;text-align:left" width:20%>'.$langs->trans('responsable_candidature').'</th>';
                            print '<td style="padding:8px 0px 8px 8px;">'.$postes->select_user(0,'responsable').'</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th  style="padding:8px 0px 8px 8px; text-align:left" width:20%>'.$langs->trans('appreciation').'</th>';
                            print '<td  style="padding:8px 0px 8px 8px;">';
                                print '<div class="rating">';
                                    print '<input type="radio" id="star3" name="appreciation" value="3" /><label for="star3"></label>';
                                    print '<input type="radio" id="star2" name="appreciation" value="2" /><label for="star2"></label>';
                                    print '<input type="radio" id="star1" name="appreciation" value="1" /><label for="star1"></label>';
                                print '</div>'; 

                            print '</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="padding:8px 0px 8px 8px; text-align:left; width:20%">'.$langs->trans('origine').'</th>';
                            print '<td style="padding:8px 0px 8px 8px;">'.$candidature->select_origine(0,'origine').'</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="padding:8px 0px 8px 8px; text-align:left; width:20%">'.$langs->trans('apport_par').'</th>';
                            print '<td style="padding:8px 0px 8px 8px;"> <input type="text" name="apport_par" style="width:100%;" ></td>';
                        print '</tr>';
                    print '</tbody>';
                print '</table>';
            print '</div>';
        print '</div>';
            

        print '<div style="width:100% !important; float:left">';
            print '<div style="width:50%; float:left;">';

                print '<div style="padding:5px !important; margin:10px 0 10px; background-color:rgb(60,70,100);"><strong style="font-size:16px;color:white;">'.$langs->trans('poste').'</strong></div>';

                print '<table class="border nc_table_" width="100%">';
                    print '<body>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('fonction').'</th>';
                            print '<td >'.$postes->select_postes(0,'poste').'</td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('departement').'</th>';
                            print '<td >'.$postes->select_departement(0,'departement').'</td>';
                        print '</tr>';


                    print '</tbody>';
                print '</table>';
            print '</div>';

            print '<div style="width:48%; float:left; margin-left:20px !important;">';

                print '<div style="padding:5px !important; margin:10px 0 10px; background-color:rgb(60,70,100);"><strong style="font-size:16px;color:white;">'.$langs->trans('contrat').'</strong></div>';

                print '<table class="border nc_table_" width="100%">';
                    print '<body>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%" >'.$langs->trans('salaire_demande').'</th>';
                            print '<td > <input type="number" min="0" max="100000" name="salaire_demande" > </td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('salaire_propose').'</th>';
                            print '<td > <input type="number" min="0" max="100000" name="salaire_propose" > </td>';
                        print '</tr>';

                        print '<tr>';
                            print '<th style="text-align:left; width:20%">'.$langs->trans('date_disponible').'</th>';
                            print '<td > <input type="text" class="datepickerncon" value="'.date('d/m/Y').'" name="date_disponible" autocomplete="off" > </td>';
                        print '</tr>';

                    print '</tbody>';
                print '</table>';
            print '</div>';
        print '</div>';

        print '<table class="border nc_table_" width="100%">';
            print '<body>';
                print '<tr>';
                    print '<th style="width:15%; text-align:left" width:20% >'.$langs->trans('resume').' :</th>';
                    print '<td >';
                        print '<textarea name="resume" style="width:100%;"></textarea>';
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
                    print '<a href="./candidature.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
                print '</tr>';
            print '</table>';

        print '</form>';
    }

?>

<script>
    $(function(){
        $( ".datepicker" ).datepicker({
            dateFormat: 'dd/mm/yy'
        });
    });
    function get_etiquette(opt) {
        $color=$(opt).data('color');
        $name=$(opt).data('name');
        console.log($('li').attr('title')=='Manager');
        console.log($color);
        console.log($name);
    }
</script>

<style>
    .etapes {
      /*display: block;*/
      position: relative;
      padding-left: 28px;
      margin-left: 13px;
      margin-bottom: 12px;
      cursor: pointer;
      /*font-size: 22px;*/
      -webkit-user-select: none;
      -moz-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }

    /* Hide the browser's default radio button */
    .etapes input {
      position: absolute;
      opacity: 0;
      cursor: pointer;
    }

    /* Create a custom radio button */
    .radio {
      position: absolute;
      top: -4px;
      left: 0;
      height: 25px;
      width: 25px;
      background-color: #eee;
      border-radius: 50%;
    }

    /* On mouse-over, add a grey background color */
    .etapes:hover input ~ .radio {
      background-color: #ccc;
    }

    /* When the radio button is checked, add a blue background */
    .etapes input:checked ~ .radio {
      background-color: #2196F3;
    }

    /* Create the indicator (the dot/circle - hidden when not checked) */
    .radio:after {
      content: "";
      position: absolute;
      display: none;
    }

    /* Show the indicator (dot/circle) when checked */
    .etapes input:checked ~ .radio:after {
      display: block;
    }

    /* Style the indicator (dot/circle) */
    .etapes .radio:after {
        top: 9px;
        left: 9px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: white;
    }

   
</style>