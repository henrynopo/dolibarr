<?php

if ($action == 'update' && $request_method === 'POST') {

    $page  = GETPOST('page');
    $id=GETPOST('id');
   
    // $sujet = GETPOST('sujet');
    $nom = GETPOST('nom');
    $prenom = GETPOST('prenom');
    $etiquettes = GETPOST('etiquettes');
    $etiq = "";
    if($etiquettes)
        $etiq = implode(",", $etiquettes);

    // if($etiquettes){
    //     $etiquettes=json_encode($etiquettes);
    // }

    $contact = GETPOST('contact');
    $email = GETPOST('email');
    $tel = GETPOST('tel');
    $mobil = GETPOST('mobile');
    $niveau = GETPOST('niveau');

    $departement = GETPOST('departement');
    $poste = GETPOST('poste');

    $responsable = GETPOST('responsable');
    $appreciation = GETPOST('appreciation');
    $apport_par = addslashes(GETPOST('apport_par'));
    $origine = GETPOST('origine');
    $salaire_propose = GETPOST('salaire_propose') ? GETPOST('salaire_propose') : 0;
    $salaire_demande = GETPOST('salaire_demande') ? GETPOST('salaire_demande') : 0;
    $date_disponible = GETPOST('date_disponible');
    $date_depot = GETPOST('date_depot');
    $resume = $db->escape(trim(dol_htmlcleanlastbr(GETPOST('resume', 'none'))));
    $etape = GETPOST('etape');
    if(!empty($etape) && !is_numeric($etape)){
        $etape=1;
    }
    $entity = GETPOST('entity') ? GETPOST('entity') : $conf->entity;
    $date=explode('/',$date_disponible);
    $date_disponible=$date[2].'-'.$date[1].'-'.$date[0];

    $date=explode('/',$date_depot);
    $date_depot=$date[2].'-'.$date[1].'-'.$date[0];
    $candid = new candidatures($db);
    $candid->fetch($id);
    $old_etape = $candid->etape;
    $data = array(
        // 'sujet'             =>  $sujet,
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
        'entity'            =>  $entity,
    );

    $levels=GETPOST('leveled');

    if(!empty($nom)){
        $data['nom'] = $nom;
    }
    if(!empty($prenom)){
        $data['prenom'] = $prenom;
    }
    $isvalid = $candidature->update($id, $data);
    
    if ($isvalid > 0) {
        $levels_edit=GETPOST('levelededit');

        if($levels_edit){
            foreach ($levels_edit as $key => $value) {
                $sql = "UPDATE ".MAIN_DB_PREFIX."levels_candid SET fk_major=".$value['major'].", fk_degree=".$value['degree'].", year=".$value['year'];
                $sql .= " WHERE rowid=".$key;
                $resql = $db->query($sql);
            }
        }

        if($levels){
            foreach ($levels as $key => $value) {
                $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'levels_candid (fk_candidat,fk_major,fk_degree,year,entity) VALUES ('.$id.','.$value["major"].','.$value["degree"].','.$value["year"].','.$conf->entity.')';
                $resql= $db->query($sql);
            }
        }
        $dataremove = GETPOST('dataremove');
        if($dataremove){
            $dataremove = explode(',', $dataremove);
            foreach ($dataremove as $value) {
                $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'levels_candid WHERE rowid='.$value;
                $resq = $db->query($sql);
            }
        }

        if($old_etat != $etape){
            if((int)$etape > (int)$old_etape) $typevent = 'pass';
            else $typevent = 'return';
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'candid_events (`event`,`fk_candidat`,`date`,`entity`) VALUES ("'.$typevent.'_'.$etape.'",'.$id.',"'.date('Y-m-d H:i:s').'",'.$conf->entity.')';
            $resql = $db->query($sql);
        }
        header('Location: ./card.php?id='.$id);
        exit;
    } 
    else {
        header('Location: ./card.php?id='. $id .'&update=0');
        exit;
    }
}
$etat =GETPOST('etat');



if($action == "edit"){

    print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="formrecrutements" >';

    $candidature->fetch($id);
    $item = $candidature;
    // $departements = new departements($db);
    // $etiquette    = new etiquettes($db);
    // $origine      = new origines($db);
    // $contact      = new Contact($db);
    // $etapes       = new etapescandidature($db);
    // $user_        = new User($db);
    print '<input type="hidden" name="action" value="update" />';
    print '<input type="hidden" name="id" value="'.$id.'" />';
    print '<input type="hidden" name="page" value="'.$page.'" />';
    print '<input type="hidden" name="entity" value="'.$item->entity.'" />';

    // if(empty($candidature->employe)){

    //     print '<a class="butAction" style="background-color:green !important;color:white !important; float: right;" data-id="'.$item->rowid.'" id="cree_employe" >'.$langs->trans('cree_employe').'</a>';
    // }
    // if($candidature->employe && $item->refuse==0){
    //     print '<a class="butAction" style="background-color:green !important;color:white !important; float: right; " data-id="'.$item->rowid.'" id="refuse" >'.$langs->trans('refuse').'</a>';
    // }
    // if($item->refuse){
    //     print '<a class="butAction" style="background-color:green !important;color:white !important; float: right; " data-id="'.$item->rowid.'" id="relance" >'.$langs->trans('relance').'</a>';
    // }
    // print '<br><br>';
        
    $status=[1=>'Insuffisant',2=>'Satisfaisant',3=>'Bien',4=>$langs->trans('Très_bien'),5=>'Excellent'];
    // Nom & etiquettes
    print '<table class="border nc_table_" width="100%">';
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
                    $etapecandid = str_replace('<input type="radio" id="'.$item->etape.'"', '<input type="radio" id="'.$item->etape.'" checked ', $etapecandid);
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
            //     print '<input type="text" class="" required id="sujet" value="'.$item->sujet.'" style="padding:8px 0px 8px 8px; width:calc(100% - 15px)" name="sujet"  autocomplete="off"/>';
            //     print '</td >';
            // print '</tr>';
            print '<tr>';
                print '<td colspan="" class="fieldrequired" style="width: 125px;">';
                print $langs->trans('nom_candidat')."<br>";
                print '</td >';
                print '<td colspan="" class="" style="width:40%;">';
                if(empty($item->employe)){
                    print '<span>';
                        print '<span class="span_placeholder">'.$langs->trans("LastName").':</span>';
                        print ' <input type="text" class="" placeholder="'.$langs->trans("LastName").'" required id="nom_candidat" value="'.$item->nom.'" style="padding:8px 0px 8px 8px; width:35%" name="nom"  autocomplete="off"/>';
                    print ' </span>';

                    print '<b class="border_b"></b>';

                    print '<span>';
                        print '<span class="span_placeholder">'.$langs->trans("FirstName").':</span>';
                        print '<input type="text" class="" placeholder="'.$langs->trans("FirstName").'" required id="prenom_candidat" value="'.$item->prenom.'" style="padding:8px 0px 8px 8px; width:35%" name="prenom"  autocomplete="off"/>';
                    print '</span>';
                }else 
                    print '<span class="nom_complet">'.$item->prenom.' '.$item->nom.'</span>';
                print '</td>';
                print '<td colspan="" class="" style="width: 125px;">';
                print $langs->trans('etiquettes');
                print '</td >';
                print '<td >';
                print '<span class="etiquettesrecru">';
                print $candidature->select_etiquette($item->etiquettes,'etiquettes[]');
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
                        print $candidature->select_contact($item->contact,'contact');
                        print '  <a href="'.DOL_MAIN_URL_ROOT.'/contact/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=edit&id='.$id).'">'.$langs->trans('cree_contact').'</a>';
                        print '</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('email_contact').'</td>';
                        print '<td ><input type="text" class="" id="email" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="email" value="'.$candidature->email.'" autocomplete="off"/></td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('tel').'</td>';
                        print '<td ><input type="text" class="" id="tel" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="tel" value="'.$item->tel.'" autocomplete="off"/></td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('mobile').'</td>';
                        print '<td ><input type="text" class="" id="mobile" style="width:60%;min-width:150px; padding:8px 0px 8px 8px;" name="mobile" value="'.$item->mobile.'" autocomplete="off"/></td>';
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
                        print '<td style="">';
                        print $postes->select_user($item->departement,'responsable');
                        print '</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td  style="text-align:left;">'.$langs->trans('appreciation').'</td>';
                        print '<td  style="">';
                            print '<div>';
                                print '<div class="rating">';

                                    $rating  ='<input type="radio" id="star5" name="appreciation" value="5" data-status="'.$langs->trans('Excellent').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Excellent').'" for="star5"></label>';
                                    $rating .='<input type="radio" id="star4" name="appreciation" value="4" data-status="'.$langs->trans('Très_bien').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Très_bien').'" for="star4"></label>';
                                    $rating .='<input type="radio" id="star3" name="appreciation" value="3" data-status="'.$langs->trans('Bien').'" onchange="get_status_appreciation(this)" /><label  title="'.$langs->trans('Bien').'" for="star3"></label>';
                                    $rating .='<input type="radio" id="star2" name="appreciation" value="2" data-status="'.$langs->trans('Satisfaisant').'" onchange="get_status_appreciation(this)" /><label title="'.$langs->trans('Satisfaisant').'" for="star2"></label>';
                                    $rating .='<input type="radio" id="star1" name="appreciation" value="1" data-status="'.$langs->trans('Insuffisant').'" onchange="get_status_appreciation(this)" /><label title="'.$langs->trans('Insuffisant').'" for="star1"></label>';

                                    $rating = str_replace('value="'.$item->appreciation.'"', 'value="'.$item->appreciation.'" checked', $rating);
                                    print $rating;
                                print '</div>';
                                // $color='red';
                                // if($item->appreciation>2){
                                //     $color='green';
                                // }
                                $status=[1=>$langs->trans('Insuffisant'),2=>$langs->trans('Satisfaisant'),3=>$langs->trans('Bien'),4=>$langs->trans('Très_bien'),5=>$langs->trans('Excellent')];
                                $clscolor = "redbg";
                                if($item->appreciation > 2)
                                    $clscolor = "greenbg";
                                print '<div class="txt appreciationdetail '.$clscolor.'">';
                                    print $status[$item->appreciation];
                                print '</div>';
                            print '</div>';
                        print '</td>';
                    print '</tr>';

                    if(GETPOST('id_origine')){
                        $id_origine=GETPOST('id_origine');
                    }else
                        $id_origine=$item->origine;

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('origine').'</td>';
                        print '<td style="">';
                        print $candidature->select_origine($id_origine,'origine');
                        print '  <a href="'.dol_buildpath('/recrutement/origines/card.php?action=add&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=edit&id='.$id),2).'">'.$langs->trans('cree_origine').'</a>';
                        print '</td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('apport_par').'</td>';
                        print '<td style=""><input type="text" name="apport_par" value="'.$item->apport_par.'" style="width:100%;" ></td>';
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
        print '<input type="hidden" name="dataremove" class="dataremove">';
            print '<thead>';
                print '<tr class="liste_titre" style="background-color:#e9eaed!important">';
                    print '<td style="text-align:center;" class="">'.$langs->trans('degrees').'</td>';
                    print '<td style="text-align:center;" class="">'.$langs->trans('majors').'</td>';
                    print '<td style="text-align:center;" class="">'.$langs->trans('Year').'</td>';
                    print '<td style="text-align:center;" class="width100"><a  id="add_major"><span class="fa fa-plus-circle valignmiddle btnTitle-icon"></span> '.$langs->trans('New').' </a></td>';
                print '</tr>';
            print '</thead>';
            print '<tbody id="tab_level">';
                $sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'levels_candid where fk_candidat='.$id;
                $resql = $db->query($sql);
                $nb_level=$db->num_rows($resql);
                while ($obj=$db->fetch_object($resql)) {
                    print '<tr>';
                        print '<td style="text-align:center;" class=""><select class="select_degrees" onchange="GetMajors(this)" name="levelededit['.$obj->rowid.'][degree]">'.$candidature->select_degrees($obj->fk_degree).'</select></td>';
                        print '<td style="text-align:center;" class=""><select class="select_majors" name="levelededit['.$obj->rowid.'][major]">'.$candidature->select_majors($obj->fk_major,' AND fk_degree='.$obj->fk_degree).'</select></td>';
                        print '<td style="text-align:center;" ><input type="number" name="levelededit['.$obj->rowid.'][year]" min="0" class="select_years maxwidth75imp" value="'.$obj->year.'"></td>';
                        // print '<td style="text-align:center;" ><select class="select_years maxwidth75imp" name="levelededit['.$obj->rowid.'][year]">'.$candidature->selectyear($obj->year).'</select></td>';
                        print '<td style="text-align:center;" class="width50"><a class="deletelevel" data-id="'.$obj->rowid.'" onclick="RemoveLevel(this)">'.img_delete().'</a></td>';
                    print '</tr>';
                }
                if(!$nb_level){
                    print '<tr>';
                        print '<td style="text-align:center;" class=""><select class="select_degrees" onchange="GetMajors(this)" name="leveled[0][degree]">'.$candidature->select_degrees(0).'</select></td>';
                        print '<td style="text-align:center;" class=""><select class="select_majors" name="leveled[0][major]"></select></td>';
                        print '<td style="text-align:center;"><input type="number" name="leveled[0][year]" min="0" class="select_years maxwidth75imp" value="'.date('Y').'"></td>';
                        print '<td style="text-align:center;" class="width50"><a class="deletelevel"  onclick="DeleteLevel(this)">'.img_delete().'</a></td>';
                    print '</tr>';
                }
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
                            print $postes->select_postes($item->poste,'poste');
                        print '</td>';
                    print '</tr>';
                    if(GETPOST('id_departement')){
                        $id_departement=GETPOST('id_departement');
                    }else
                        $id_departement=$item->departement;
                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('departement').'</td>';
                        print '<td style="">';
                            print $postes->select_departement($id_departement,'departement');
                            print '<a href="'.dol_buildpath('/recrutement/departements/card.php?action=add&backtopage='.urlencode($_SERVER["PHP_SELF"].'?action=edit&id='.$id),2).'">'.$langs->trans('cree_departement').'</a>';
                        print '</td>';
                    print '</tr>';
                    $date_depot = date('d/m/Y');
                    if($item->date_depot){
                        $date=explode('-', $item->date_depot);
                        $date_depot=$date[2].'/'.$date[1].'/'.$date[0];
                    }
                    print '<tr>';
                        print '<td width="34%;" >'.$langs->trans('date_depot').'</td>';
                        print '<td ><input type="text" name="date_depot" value="'.$date_depot.'" class="datepickerncon" ></td>';
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
                        print '<td ><input type="number" min="0" value="'.$item->salaire_demande.'" name="salaire_demande" > </td>';
                    print '</tr>';

                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('salaire_propose').'</td>';
                        print '<td ><input type="number" min="0" value="'.$item->salaire_propose.'" name="salaire_propose" > </td>';
                    print '</tr>';

                    $date=explode('-', $item->date_disponible);
                    $date_disponible=$date[2].'/'.$date[1].'/'.$date[0];
                    print '<tr>';
                        print '<td style="text-align:left;">'.$langs->trans('date_disponible').'</td>';
                        print '<td>';
                            print '<input type="text" class="datepickerncon" value="'.$date_disponible.'" name="date_disponible" autocomplete="off" >';
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

    // Description
    print '<table class="border nc_table_" width="100%">';
        print '<body>';
            print '<tr>';
                print '<td style="width:180px;text-align:left;vertical-align: top;" >'.$langs->trans('resume').'</td>';
                print '<td >';
                    // print '<textarea name="resume" style="width:calc(100% - 8px);">'.$item->resume.'</textarea>';
                    $doleditor = new DolEditor('resume', $item->resume, '', 140, 'dolibarr_notes', '', false, true, $conf->global->FCKEDITOR_ENABLE_PRODUCTDESC, ROWS_4, '90%');
                    print $doleditor->Create();
                print '</td>';
            print '</tr>';
        print '</tbody>';
    print '</table>';





    // Actions

    print '<table class="" width="100%">';
    print '<tr>';
        print '<td colspan="2" >';
            print '<br>';
            print '<a class="butAction" id="validatesumitbutton">'.$langs->trans('Validate').'</a>';
            print '<a href="./card.php?id='.$id.'" class="butAction">'.$langs->trans('Cancel').'</a>';
            print '<input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction" style="display:none;"/>';

            print '<span class="createemployrecrutbutt">';
                
                // if(empty($candidature->employe)){
                // print '<a class="butAction" style="" data-id="'.$item->rowid.'" id="cree_employe" >'.$langs->trans('cree_employe').'</a>';
                // }
                if($item->refuse==0){
                print '<a class="butActionBTNC butActionDelete" style="float: right;" data-id="'.$item->rowid.'" id="refuse" >'.$langs->trans('refuse').'</a>';
                }
                if($item->refuse){
                print '<a class="butAction" style="; " data-id="'.$item->rowid.'" id="relance" >'.$langs->trans('relance').'</a>';
                }
            print '</span>';
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
        $( ".datepicker" ).datepicker({
            dateFormat: 'dd/mm/yy'
        });
        
    });
    function get_etiquette(opt) {
        $color=$(opt).data('color');
        $name=$(opt).data('name');
    }
    // function get_status(input) {
    //     $status=$(input).data('status');
    //     $id=$(input).val();
    //     console.log($id);
    //     $('.txt').css('background-color','red');
    //     if($id > 2){
    //         $('.txt').css('background-color','green');
    //     }
    //     $('.txt').html($status);
    // }

</script>

