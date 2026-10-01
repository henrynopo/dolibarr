<?php


// $elaboration_pv->fetch($id);
// $item = $elaboration_pv;
// $projet = $projet->fetch($item->fk_projet);
// $tache = $tache->fetch($item->fk_tache);#55acee

$html='<style>';
    $html .= 'td.td-1{width:30%;  border-bottom:2px solid #55acee; border-right:2px solid #55acee; text-align:center"}';
    $html .= 'td.td-2{width:70%;  border-bottom:2px solid #55acee; border-left:2px solid #55acee; "}';
    $html .= 'td.td-3{width:30%;  border-top:2px solid #55acee; border-right:2px solid #55acee; text-align:center"}';
    $html .= 'td.td-4{width:70%;  border-top:2px solid #55acee; border-left:2px solid #55acee; "}';
    $html .= 'table{width:100%; height:100%"}';
$html .= '</style>';
$ecv->fetch($id);
$user_cv = new User($db);
// $user_cv->fetch($ecv->fk_user);

$adherent_cv = new Adherent($db);
if($ecv->useroradherent == 'ADHERENT'){
    $adherent_cv->fetch($ecv->fk_user);
    $objuserecv = $adherent_cv;
    $modulepart = 'memberphoto';
}else{
    $user_cv->fetch($ecv->fk_user);
    $objuserecv = $user_cv;
    $modulepart = 'userphoto';
}

$filter='AND fk_ecv='.$id.' AND fk_user='.$ecv->fk_user;
// $html.='<table style="width:100%;" cellpadding="15px"; cellspadding="5px" id="info_user" >';
//     $html.='<tr>';
//     $html.='<td>rtertert</td>';
//     $html.='<td>rtertert</td>';
//     $html.='</tr>';
// $html.='</table>';
$html.='<table class="info_user" style="width:100%">'; 
    $html.='<tr><td style="border-right:2px solid #55acee;width:30%;"><span style="line-height:5px;"></span></td><td></td></tr>';
    // $html.='<tr><td></td></tr>'; 
    $html.='<tr>'; 
        $html.='<td class="td-1">'; 

         if(!empty($objuserecv->photo) ){ 


            // $phototoshow = $form->showphoto($modulepart, $objuserecv, 0, '35mm', 0, 'photoref', 'small', 0, 0, 1);
            // $html.= $phototoshow.'<br>';

            if($ecv->useroradherent == 'ADHERENT'){
                $dir=$dolibarr_main_data_root.'/adherent/'.$ecv->fk_user.'/photos/';
                if( file_exists($dir) && is_dir($dir) ){
                    $html.= '<img id="photo_user" src="'.DOL_DATA_ROOT.'/adherent/'.$ecv->fk_user.'/photos/'.$objuserecv->photo.'"  height="35mm" ><br>';
                }
            }else{
                $dir=$dolibarr_main_data_root.'/users/'.$ecv->fk_user.'/0/';
                if( file_exists($dir) && is_dir($dir) ){
                    $html.= '<img src="'.DOL_DATA_ROOT.'/users/'.$ecv->fk_user.'/0/'.$objuserecv->photo.'"  height="35mm"><br>';
                }
                else{
                    $html.= '<img src="'.DOL_DATA_ROOT.'/users/'.$ecv->fk_user.'/'.$objuserecv->photo.'"  height="35mm"><br>';
                }
            }

        } 
        else{
            $html.= '<img src="'.DOL_MAIN_URL_ROOT.'/public/theme/common/user_man.png" height="35mm"><br>';
        }
        $html.='</td>'; 
        $html.='<td class="td-2">'; 
            $html.='<table style="width:100%;" cellpadding="5px"; cellspadding="5px" >'; 
                $html.='<tr>'; 
                    $html.='<td style="width:60%;text-align: center; padding-top: 30px;">'; 
                        $html.='<strong style="font-size:25px; "><span style="line-height:60px"></span><i>'.$objuserecv->lastname.' '.$objuserecv->firstname.'</i></strong>';
                        $html.='<div style="text-align:center; font-size:12px;line-height:18px">'.$ecv->poste.'</div>'; 
                    $html.= '</td>';
                    $html.='<td style="width:40%" align="right" >'; 
                        $html.='<table style="width:100%" >'; 
                            if($ecv->useroradherent == 'ADHERENT'){
                                if($objuserecv->phone_mobile){
                                    // $html.='<tr><td colspan="2"><span style="line-height:5px;"></span></td></tr>';
                                    $html.='<tr>';
                                        $html.='<td colspan="2" align="left"><br><table width="100%" ><tr><td width="10%" align="right"></td><td width="90%" align="left"><img  src="'.dol_buildpath('/sghr/ecv/images/tel_.png',1).'" height="12px"> '.$objuserecv->phone_mobile.'<br></td></tr></table></td>';
                                    $html.='</tr>';
                                }
                            }else{
                                if($objuserecv->user_mobile){
                                    // $html.='<tr><td colspan="2"><span style="line-height:5px;"></span></td></tr>';
                                    $html.='<tr>';
                                        $html.='<td colspan="2" align="left"><br><table width="100%" ><tr><td width="10%" align="right"></td><td width="90%" align="left"><img  src="'.dol_buildpath('/sghr/ecv/images/tel_.png',1).'" height="12px"> '.$objuserecv->user_mobile.'<br></td></tr></table></td>';
                                    $html.='</tr>';
                                }
                            }
                            if($objuserecv->email){
                                $html.='<tr>';
                                    $html.='<td colspan="2" align="left"><table width="100%"><tr><td width="10%" align="right"></td><td width="90%" align="left"><img src="'.dol_buildpath('/sghr/ecv/images/email_2.png',1).'" width="12px"> '.$objuserecv->email.'<br></td></tr></table></td>';
                                $html.='</tr>';
                            }
                            if($objuserecv->address || $objuserecv->country_id || $objuserecv->country_code){
                                $html.='<tr>';
                                    $html.='<td colspan="2" align="left"><br><table width="100%" ><tr><td width="10%" align="right"></td><td width="90%" align="left"><img  src="'.dol_buildpath('/sghr/ecv/images/adress.png',1).'" height="12px"> '.$objuserecv->getFullAddress(1,', ',$conf->global->MAIN_SHOW_REGION_IN_STATE_SELECT).'<br></td></tr></table></td>';
                                $html.='</tr>';

                             
                            }

                            
                            $filter='AND fk_ecv='.$id.' AND fk_user='.$ecv->fk_user;
                            $ecvpermis->fetchAll('','',0,0,$filter);
                            if(count($ecvpermis->rows) > 0){
                                $typs = "";
                                foreach ($ecvpermis->rows as $key => $v) {
                                    $typs .= $v->type.", ";
                                }
                                $typs = trim($typs,", ");
                                // $itemp = $ecvpermis->rows[0];
                                // if($itemp->exist == "yes"){
                                    $html.='<tr>';
                                        $html.='<td colspan="2" align="left"><br><table width="100%" ><tr><td width="10%" align="right"></td><td width="90%" align="left"><img  src="'.dol_buildpath('/sghr/ecv/images/permis.png',1).'" height="12px"> '.$langs->trans("ecv_permis").': '.$typs.'<br></td></tr></table></td>';
                                    $html.='</tr>';
                                // }
                            }

                        $html.= '</table>';
                    $html.= '</td>';
                $html.= '</tr>';
            $html.= '</table>';
        $html.='</td>'; 
    $html.='</tr>';

    $html.='<tr >'; 
        $html.='<td class="td-3" >';
            $html.='<table style="width:100%;" cellpadding="5px"; cellspadding="5px" >';

                $html.='<tr>';
                    $html.='<td style="width:5%;"> </td>'; 
                        $html.='<td align="left" style=" width:90%;" ><br><br><strong style="font-size:16px;">'.$langs->trans("ecv_objectifs").'</strong>'; 
                    $html.='</td>'; 
                $html.='</tr>';

                $html.='<tr>';
                    $html.='<td style="width:10%;"></td>'; 
                    $html.='<td align="left"  style="width:90%;">'.$ecv->objectifs.'</td>'; 
                $html.='</tr>';

                $filter='AND fk_ecv='.$id.' AND fk_user='.$ecv->fk_user;
                $ecvcompetances->fetchAll('','',0,0,$filter);
                if(count($ecvcompetances->rows)>0){
                    $html.='<tr>';
                        $html.='<td style="width:5%;"></td><td style="width:90%;" align="left" style="color:#55acee;" ><br><br><strong style="font-size:16px">'.$langs->trans("ecv_competences").':</strong></td>'; 
                    $html.='</tr>';

                    foreach ($ecvcompetances->rows as $val) {
                        $competances->fetch($val->fk_competance);
                        
                        $html.='<tr>';
                            $html.='<td style="width:7%;"></td><td style="width:58%;"align="left">';

                            $minifile = getImageFileNameForSize($competances->icon, '');  
                            // $urlfile = DOL_DATA_ROOT.'/ecv'.'/competances/'.$competances->rowid.'/'.$minifile;
                            $urlfile = DOL_DATA_ROOT.'/ecv'.'/competances/'.$minifile;
                            if(@getimagesize($urlfile))
                            $html.='<img alt="Photo" src="'.$urlfile.'" height="13px" > ';


                            $html .= $competances->name.'</td>';
                            $html.='<td style="width:35%;">';
                               for($i=1; $i <= 5; $i++){
                                    if($i <= $val->value){
                                        $html.='<img src="'.dol_buildpath('/sghr/ecv/images/etoile.png',1).'"  height="13px" >';
                                    }
                                    else
                                        $html.='<img src="'.dol_buildpath('/sghr/ecv/img/null-etoile.png',1).'" height="13px" >';
                                }
                            $html.='</td>';
                        $html.='</tr>';
                    }
                }
                
                $filter='AND fk_ecv='.$id.' AND fk_user='.$ecv->fk_user;
                $ecvlangues->fetchAll('','',0,0,$filter);
                $langs->loadLangs(array('admin', 'languages', 'other', 'companies', 'products', 'members', 'projects', 'hrm', 'agenda'));
                if(count($ecvlangues->rows)>0){
                    
                    $html.='<tr>';
                        $html.='<td style="width:5%;"></td>'; 
                        $html.='<td align="left" style="color:#55acee; width:90%" ><br><br><strong style="font-size:16px">'.$langs->trans("ecv_langues").':</strong></td>';
                    $html.='</tr>';

                    foreach ($ecvlangues->rows as $val) {
                        $html.='<tr>';
                            $name=$langs->trans("Language_".$val->name);
                            $ar=explode('(', $name);
                            if(count($ar)>0){
                                $name=$ar[0];
                            }
                            $srcimg = picto_from_langcode($val->name);
                            $urlc = DOL_MAIN_URL_ROOT;
                            $urln = DOL_URL_ROOT;
                            $srcimg = str_replace($urln, $urlc, $srcimg);
                            $html.='<td style="width:5%;"></td><td style="width:55%;" align="left">'.$srcimg.'&nbsp;&nbsp;'.$name.'</td>';
                            $html.='<td align="right" style="width:40%;">';
                                for($i=1; $i <= 5; $i++){
                                    if($i<=$val->value){
                                        $html.='<img src="'.dol_buildpath('/sghr/ecv/images/langue.png',1).'" height="10px" > ';
                                    }else{
                                        $html.='<img src="'.dol_buildpath('/sghr/ecv/img/langue-null.png',1).'" height="10px" > ';
                                    }
                                }
                            $html.='</td>';
                        $html.='</tr>';
                    }
                }
                
                $filter='AND fk_ecv='.$id.' AND fk_user='.$ecv->fk_user;
                $ecvqualifications->fetchAll('','',0,0,$filter);
                if(count($ecvqualifications->rows)>0){
                    $html.='<tr>';
                        $html.='<td style="width:5%;"></td><td align="left" style="width:90%;" ><br><br><strong style="font-size:16px; color:#55acee;">'.$langs->trans("ecv_qualification").':</strong></td>';
                    $html.='</tr>';
                    $html.='<tr>';
                        $html.='<td style="width:2%;"></td>';
                        $html.='<td align="left" width="98%" >';
                            foreach ($ecvqualifications->rows as $key => $value) {
                                $html.='<table cellpadding="2px"; cellspadding="0px" width="100%"><tr><td width="10%" style="" align="right"><b>-</b></td><td width="90%" align="left">'.$value->name.'</td></tr></table><br>';
                            }
                        $html.='</td>';
                    $html.='</tr>';
                }


                // $filter='AND fk_ecv='.$id.' AND fk_user='.$ecv->fk_user;
                // $ecvpermis->fetchAll('','',0,0,$filter);
                // if(count($ecvpermis->rows)>0){
                //     $itemp = $ecvpermis->rows[0];
                //     if($itemp->exist == "yes"){
                //     $html.='<tr>';
                //         $html.='<td style="width:5%;"></td><td align="left" style="width:90%;" ><br><strong style="font-size:16px; color:#55acee;">'.$langs->trans("ecv_permis_circulations").':</strong></td>';
                //     $html.='</tr>';
                //     $html.='<tr>';
                //         $html.='<td style="width:2%;"></td>';
                //         $html.='<td align="left" width="98%" >';
                //             $html.='<table cellpadding="2px"; cellspadding="0px" width="100%">';

                //             $html.='<tr>';
                //             $html.='<td width="100%" style="" align="left">';
                //             $html.='- '.$langs->trans("ecv_annee_acquisition").'';
                //             $html.=' : <b>'.$itemp->year.'</b></td>';
                //             $html.='</tr>';

                //             $html.='<tr>';
                //             $html.='<td width="100%" style="" align="left">';
                //             $html.='- '.$langs->trans("ecv_permis_type").'';
                //             $html.=' : <b>'.$itemp->type.'</b></td>';
                //             $html.='</tr>';

                //             $html.='</table><br>';
                //         $html.='</td>';
                //     $html.='</tr>';
                //     }
                // }
            $html.='</table>';
        $html.='</td>'; 

        $html.='<td class="td-4">'; 
            $html.='<table style="width:100%; border:none !important;">';
                $ecvexperiences->fetchAll('','',0,0,$filter); 
                if(count($ecvexperiences->rows)>0){
                    $html.='<tr>';
                        $html.='<td colspan="2"><br><br><strong style="font-size:16px; color:#55acee;">'.$langs->trans("ecv_experiences").':</strong><br></td>';
                    $html.='</tr>';
                    foreach ($ecvexperiences->rows as $key => $value) {
                        
                        $html.='<tr>';
                            $html.='<td style="width:18%; color:grey; " align="center">'.$ecv->format_year($value->debut).' - ';
                            if($value->nosjours == 1){
                                $html.=$langs->trans("ecv_no_jours");
                            }
                            elseif($value->nosjours == 0){ 
                                $html.=$ecv->format_year($value->fin);
                            }
                            $html.='</td>';
                            $html.='<td style="width:67%"><b>'.$value->societe.' </b></td>';
                            $html.='<td style="width:10%" align="right">';

                                $minifile = getImageFileNameForSize($value->profile_soc, '');  
                                $urlfile = DOL_DATA_ROOT.'/ecv'.'/'.$ecv->rowid.'/experiences/'.$value->rowid.'/'.$minifile;
                                if(@getimagesize($urlfile))
                                $html.='<img src="'.$urlfile.'" height="15px"  >';


                            $html.='</td>';
                            $html.='<td style="width:5%;"></td>';
                        $html.='</tr>';
                        $html.='<tr>';
                            $html.='<td style="width:18%;" align="center"></td>';
                            $html.='<td style="width:77%;text-align:justify" >'.nl2br($value->description).'</td>';
                            $html.='<td style="width:5%;"></td>';
                        $html.='</tr>';
                        $html.='<tr><td colspan="2"><span style="line-height:5px"></span></td></tr>';
                    }
                }
                $ecvformations->fetchAll('','',0,0,$filter);
                if(count($ecvformations->rows)>0){
                    $html.='<tr>';
                        $html.='<td colspan="2"><br><br><strong style="font-size:16px; color:#55acee;">'.$langs->trans("ecv_formations").':</strong><br></td>';
                    $html.='</tr>';
                    foreach ($ecvformations->rows as $key => $value) {

                        $html.='<tr>';
                            $html.='<td style="width:18%; color:grey; " align="center">'.$ecv->format_year($value->debut).' - ';
                            if($value->nosjours == 1){
                                $html.=$langs->trans("ecv_no_jours");
                            }
                            elseif($value->nosjours == 0){ 
                                $html.=$ecv->format_year($value->fin);
                            }
                            $html.='</td>';
                            $html.='<td style="width:77%"><b>'.$value->etablissement.'</b> <div>'.$value->filiere.'</div>';
                            $html.='<br></td>';
                            $html.='<td style="width:5%;"></td>';
                        $html.='</tr>';
                    }
                }
                
                $ecvcertificats->fetchAll('','',0,0,$filter);
                if(count($ecvcertificats->rows)>0){
                    $html.='<tr>';
                        $html.='<td colspan="2"><br><br><strong style="font-size:16px; color:#55acee;">'.$langs->trans("ecv_certificats").':</strong><br></td>';
                    $html.='</tr>';
                    foreach ($ecvcertificats->rows as $key => $value) {
                        $html.='<tr>';
                            $html.='<td style="width:18%; color:grey; " align="center">'.$ecv->format_year($value->debut).' - '.$ecv->format_year($value->fin).'</td>';
                            $html.='<td style="width:77%"><b>'.$value->intitule.'</b></td>';
                        $html.='<td style="width:5%;"></td>';
                        $html.='</tr>';
                        $html.='<tr>';
                            $html.='<td style="width:18% !important;" align="center">';

                            $minifile = getImageFileNameForSize($value->copie, '');  
                            $urlfile = DOL_DATA_ROOT.'/ecv'.'/'.$ecv->rowid.'/certificats/'.$value->rowid.'/'.$minifile;
                            if(@getimagesize($urlfile))
                            $html .= '<img src="'.$urlfile.'" height="35px" >';

                            $html .= '</td>';
                            $html.='<td style="width:77% !important;text-align:justify;">'.nl2br($value->description).'</td>';
                        $html.='<td style="width:5%;"></td>';
                        $html.='</tr>';
                        $html.='<tr><td colspan="2"><span style="line-height:5px"></span></td></tr>';
                    }
                }
               
            $html.='</table>';
        $html.='</td>'; 
    $html.='</tr>'; 
$html.='</table>'; 
// print_r($html);die();
    
 