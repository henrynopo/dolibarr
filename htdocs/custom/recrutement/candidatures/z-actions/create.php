<?php

if ($action == 'create' && $request_method === 'POST') {

    $etape = GETPOST('etape');
    // $sujet = addslashes(GETPOST('sujet'));
    $nom = GETPOST('nom');
    $prenom = GETPOST('prenom');
    // $etiquettes='';
    // if(GETPOST('etiquettes')){
    //     $etiquettes = GETPOST('etiquettes');
    //     $etiquettes=json_encode($etiquettes);
    // }
    $etiquettes = GETPOST('etiquettes');
    $etiq = "";
    if($etiquettes)
        $etiq = implode(",", $etiquettes);

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
    $apport_par = addslashes(GETPOST('apport_par'));
    $origine = GETPOST('origine');
    $salaire_propose = GETPOST('salaire_propose') ? GETPOST('salaire_propose') : 0;
    $salaire_demande = GETPOST('salaire_demande') ? GETPOST('salaire_demande') : 0;
    $date_disponible = GETPOST('date_disponible');
    $date_depot = GETPOST('date_depot');
    $resume = $db->escape(trim(dol_htmlcleanlastbr(GETPOST('resume', 'none'))));
    
    $date=explode('/',$date_disponible);
    $date_disponible=$date[2].'-'.$date[1].'-'.$date[0]; 

    $date=explode('/',$date_depot);
    $date_depot=$date[2].'-'.$date[1].'-'.$date[0];

    $levels=GETPOST('leveled');
    $insert = array(
        // 'sujet'             =>  $sujet,
        'nom'               =>  $nom,
        'prenom'            =>  $prenom,
        'etiquettes'        =>  $etiq,
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
        'date_depot'        =>  $date_depot,
        'resume'            =>  $resume,
        'etape'             =>  $etape,
        'entity'            =>  $conf->entity,
    );
    $avance = $candidature->create(1,$insert);
    // $candidature->fetch($avance);
    // If no SQL error we redirect to the request card
    if ($avance > 0 ) {
        if($levels){
            foreach ($levels as $key => $value) {
                $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'levels_candid (fk_candidat,fk_major,fk_degree,year) VALUES ('.$avance.','.$value["major"].','.$value["degree"].','.$value["year"].')';
                $resql= $db->query($sql);
            }
        }
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'candid_events (`event`,`fk_candidat`,`date`) VALUES ("pass_'.$etape.'",'.$avance.',"'.date('Y-m-d H:i:s').'")';
        $resql = $db->query($sql);
        header('Location: ./card.php?id='. $avance.'');
        exit;
    } 
    else {
        header('Location: card.php?action=request&error=SQL_Create&msg='.$recrutement->error);
        exit;
    }
}

if($action == "add"){

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data"  class="formrecrutements">';

    $etapes = new etapescandidature($db);

    print '<input type="hidden" name="action" value="create" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';

        


    // Nom & etiquettes
    print '<table class="border nc_table_" width="100%">';
        print '<tbody>';

            print '<tr>';
                print '<td colspan="2" class="alletapesrecru">';
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
                        $etapecandid = str_replace('<input type="radio" id="1"', '<input type="radio" id="1" checked ', $etapecandid);
                        print $etapecandid;
                print '</td>';
            print '</tr>';

    print '</table>';
    print '<table class="border nc_table_" width="100%">';
            // print '<tr>';
            //     print '<td colspan="" class="fieldrequired" style="width: 125px;">';
            //     print $langs->trans('sujet')."<br>";
            //     print '</td >';
            //     print '<td colspan="3" class="">';
            //     print '<input type="text" class="" required id="sujet" value="" style="padding:8px 0px 8px 8px; width:calc(100% - 15px)" name="sujet"  autocomplete="off"/>';
            //     print '</td >';
            // print '</tr>';

            print '<tr>';
                print '<td colspan="" class="fieldrequired" style="width: 125px;">';
                print $langs->trans('nom_candidat')."<br>";
                print '</td >';
                print '<td colspan="" class="" style="max-width: 600px;width: 40%;">';
                     print '<span>';
                        print '<span class="span_placeholder">'.$langs->trans("LastName").':</span>';
                        print ' <input type="text" required id="nom_candidat" style="padding:8px 0px 8px 8px; width:35%" name="nom"  autocomplete="off"/>';
                    print ' </span>';

                    print '<b class="border_b"></b>';

                    print '<span>';
                        print '<span class="span_placeholder">'.$langs->trans("FirstName").':</span>';
                        print '<input type="text" required id="prenom_candidat" style="padding:8px 0px 8px 8px; width:35%" name="prenom"  autocomplete="off"/>';
                    print '</span>';
                // print '<input type="text" class="" required id="nom_candidat" value="" style="padding:8px 0px 8px 8px; width:80%" name="nom"  autocomplete="off"/>';
                print '</td>';
                print '<td colspan="" class="" style="width: 125px;">';
                print $langs->trans('etiquettes');
                print '</td >';
                print '<td >';
                print '<span class="etiquettesrecru">';
                print $candidature->select_etiquette(0,'etiquettes[]');
                print '</span>';
                print '</td>';
            print '</tr>';
    print '</table>';
    
    print '<div class="clear" style="margin-top: 4px;"></div>';

    // info
    print '<div class="fichecenter" >';
        print '<div class="fichehalfleft">';
            print '<table class="border nc_table_" width="100%">';
                print '<body>';
                    print '<tr>';
                        print '<td class="fieldrequired firsttd200px" style="text-align:left;">'.$langs->trans('contact').'</td>';
                        print '<td>';
                        print $candidature->select_contact(0,'contact');
                        print '  <a href="'.DOL_MAIN_URL_ROOT.'/contact/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=add').'">'.$langs->trans('cree_contact').'</a>';
                        print '</td>';
                    print '</tr>';

                    $contact->fetch($item->contact);
                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('email_contact').'</td>';
                        print '<td ><input type="text" class="" value="'.$candidature->email.'" id="email" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="email"  autocomplete="off"/></td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('tel').'</td>';
                        print '<td ><input type="text" class="" id="tel" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="tel"  autocomplete="off"/></td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('mobile').'</td>';
                        print '<td ><input type="text" class="" id="mobile" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="mobile" value="'.$item->mobile.'"  autocomplete="off"/></td>';
                    print '</tr>';

                    // print '<tr>';
                    //     print '<td style="text-align:left;">'.$langs->trans('niveau').'</td>';
                    //     print '<td >'.$candidature->select_niveau($item->niveau).'</td>';
                    // print '</tr>';

                print '</tbody>';
            print '</table>';
            print '<br>';
        print '</div>';

       

        print '<div class="fichehalfright">';
        print '<div class="ficheaddleft">';
            print '<table class="border nc_table_" width="100%" >';
                print '<tbody>';
                    print '<tr>';
                        print '<td style="text-align:left;" class=" firsttd200px">'.$langs->trans('responsable_candidature').'</td>';
                        print '<td style="">'.$postes->select_user(0,'responsable').'</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td  style="text-align:left;">'.$langs->trans('appreciation').'</td>';
                        print '<td  style="">';
                            print '<div>';
                                print '<div class="rating">';
                                    print '<input type="radio" id="star5" name="appreciation" value="5" data-status="'.$langs->trans('Excellent').'" onchange="get_status_appreciation(this)" /><label  title="Excellent" for="star5"></label>';

                                    print '<input type="radio" id="star4" name="appreciation" value="4" data-status="'.$langs->trans('Très_bien').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Très_bien').'" for="star4"></label>';

                                    print '<input type="radio" id="star3" name="appreciation" value="3" data-status="'.$langs->trans('Bien').'" checked onchange="get_status_appreciation(this)" /><label for="star3" title="'.$langs->trans('Bien').'"></label>';

                                    print '<input type="radio" id="star2" name="appreciation" value="2" data-status="'.$langs->trans('Satisfaisant').'"  onchange="get_status_appreciation(this)" /><label for="star2" title="'.$langs->trans('Satisfaisant').'"></label>';

                                    print '<input type="radio" id="star1" name="appreciation" value="1" data-status="'.$langs->trans('Insuffisant').'" onchange="get_status_appreciation(this)" /><label title="'.$langs->trans('Insuffisant').'" for="star1"></label>';
                                print '</div>';
                                $status=[1=>$langs->trans('Insuffisant'),2=>$langs->trans('Satisfaisant'),3=>$langs->trans('Bien'),4=>$langs->trans('Très_bien'),5=>$langs->trans('Excellent')];
                                print '<div class="txt appreciationdetail greenbg">';
                                    print $status[3];
                                print '</div>';

                            print '</div>';
                        print '</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('origine').'</td>';
                        print '<td style="">';
                        $id_org = 0;
                         if(GETPOST('id_origine')){
                            $id_org=GETPOST('id_origine');
                        }
                        print $candidature->select_origine($id_org,'origine');
                        print '  <a href="'.dol_buildpath('/recrutement/origines/card.php?action=add&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=add'),2).'">'.$langs->trans('cree_origine').'</a>';
                        print '</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('apport_par').'</td>';
                        print '<td style=""><input type="text" name="apport_par" style="width:100%;" ></td>';
                    print '</tr>';
                print '</tbody>';
            print '</table>';
        print '</div>';
        print '</div>';
        print '<div class="clear"></div>';
    print '</div>';

    print '<br>';
    print '<div width="100%">';
        print '<div class="topheaderrecrutmenus" style="text-align:left"><span>'.$langs->trans('niveau').'</span></div>';
        print '<br>';
        print '<table class="border nc_table_" style="border:1px solid #e9eaed; width:100%">';
            print '<thead>';
                print '<tr class="liste_titre" style="background-color:#e9eaed!important">';
                    print '<td style="text-align:center;" class="">'.$langs->trans('degrees').'</td>';
                    print '<td style="text-align:center;" class="">'.$langs->trans('majors').'</td>';
                    print '<td style="text-align:center;" class="">'.$langs->trans('Year').'</td>';
                    print '<td style="text-align:center;" class="width100"><a  id="add_major"><span class="fa fa-plus-circle valignmiddle btnTitle-icon"></span> '.$langs->trans('New').' </a></td>';
                print '</tr>';
            print '</thead>';
            print '<tbody id="tab_level">';
                print '<tr>';
                    print '<td style="text-align:center;" class=""><select class="maxwidth75imp select_degrees" onchange="GetMajors(this)" name="leveled[0][degree]">'.$candidature->select_degrees(0).'</select></td>';
                    print '<td style="text-align:center;" class=""><select class="maxwidth75imp select_majors" name="leveled[0][major]"></select></td>';
                    print '<td style="text-align:center;" ><input type="number" name="leveled[0][year]" min="0" class="select_years maxwidth75imp" value="'.date('Y').'"></td>';
                    print '<td style="text-align:center;" class="width50"><a class="deletelevel" onclick="DeleteLevel(this)">'.img_delete().'</a></td>';
                print '</tr>';
            print '</tbody>';
        print '</table>';
    print '</div>';
    print '<br>';

    // post & contrat
    print '<div class="fichecenter postandcontrat">';
        print '<div class="fichehalfleft">';
            print '<div class="topheaderrecrutmenus"><span>'.$langs->trans('poste').'</span></div>';
            print '<div class="divcontaintable">';
            print '<table class="border nc_table_" width="100%">';
                print '<body>';
                    print '<tr>';
                        print '<td style="text-align:left;" class="fieldrequired firsttd200px">'.$langs->trans('fonction').'</td>';
                        print '<td >';
                            print $postes->select_postes(0,'poste');
                        print '</td>';
                    print '</tr>';
                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('departement').'</td>';
                        print '<td style="">';
                        $id_dep=0;
                        if(GETPOST('id_departement')){
                            $id_dep = GETPOST('id_departement');
                        }
                        // die(GETPOST('id_departement'));
                            print $postes->select_departement($id_dep,'departement');
                            print '<a href="'.dol_buildpath('/recrutement/departements/card.php?action=add&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=add'),2).'">'.$langs->trans('cree_departement').'</a>';
                        print '</td>';
                    print '</tr>';
                    print '<tr>';
                        print '<td >'.$langs->trans('date_depot').'</td>';
                        print '<td ><input type="text" name="date_depot" value="'.date('d/m/Y').'" class="datepickerncon" ></td>';
                    print '</tr>';


                print '</tbody>';
            print '</table>';
            print '</div>';
        print '</div>';

        print '<div class="fichehalfright">';
        print '<div class="ficheaddleft">';    
            print '<div class="bordercontainr">';    
            print '<div class="topheaderrecrutmenus"><span>'.$langs->trans('contrat').'</span></div>';
            print '<div class="divcontaintable">';
            print '<table class="border nc_table_" width="100%">';
                print '<body>';

                    print '<tr>';
                        print '<td style="text-align:left;" class=" firsttd200px">'.$langs->trans('salaire_demande').'</td>';
                        print '<td ><input type="number" min="0" value="0" name="salaire_demande" > </td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('salaire_propose').'</td>';
                        print '<td ><input type="number" min="0" name="salaire_propose" > </td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('date_disponible').'</td>';
                        print '<td>';
                            print '<input type="text" class="datepickerncon" value="'.date('d/m/Y').'" name="date_disponible" autocomplete="off" >';
                        print '</td>';
                    print '</tr>';


                print '</tbody>';
            print '</table>';
            print '</div>';
            print '</div>';
        print '</div>';
        print '</div>';

        print '<div class="clear"></div>';
    print '</div>';

    print '<br>';
    // Description
    print '<table class="border nc_table_" width="100%">';
        print '<body>';
            print '<tr>';
                print '<td style="width:180px;text-align:left;vertical-align: top;" >'.$langs->trans('resume').'</td>';
                print '<td >';
                    // print '<textarea name="resume" style="width:calc(100% - 8px);"></textarea>';
                    $doleditor = new DolEditor('resume', '', '', 140, 'dolibarr_notes', '', false, true, $conf->global->FCKEDITOR_ENABLE_PRODUCTDESC, ROWS_4, '90%');
                    print $doleditor->Create();
                print '</td>';
            print '</tr>';
        print '</tbody>';
    print '</table>';





    // Actions
        print '<table class="" width="100%">';
            print '<tr>';
                print '<td colspan="2" align="center">';
                print '<br>';
                print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction"/>';
                // print '<a class="butAction" id="validatesumitbutton">'.$langs->trans('Validate').'</a>';
                print '<a href="./index.php?page='.$page.'" class="butAction">'.$langs->trans('Cancel').'</a>';
                print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction" style="display: none;;"/>';
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
    function get_status(input) {
        $status=$(input).data('status');
        $id=$(input).val();
        console.log($id);
        $('.txt').css('display','block');
        $('.txt').css('background-color','red');
        if($id > 2){
            $('.txt').css('background-color','green');
        }
        $('.txt').html($status);
    }
</script>

