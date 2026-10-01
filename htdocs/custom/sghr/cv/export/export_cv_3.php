<?php

$html='';
$cvProfile->fetch($id);
$user_cv = new User($db);
// $user_cv->fetch($cvProfile->fk_user);

$adherent_cv = new Adherent($db);
if($cvProfile->useroradherent == 'ADHERENT'){
    $adherent_cv->fetch($cvProfile->fk_user);
    $objuserecv = $adherent_cv;
}else{
    $user_cv->fetch($cvProfile->fk_user);
    $objuserecv = $user_cv;
}

 global $dolibarr_main_data_root;
// print_r($user_cv->getFullAddress(1,', ',$conf->global->MAIN_SHOW_REGION_IN_STATE_SELECT));die();
$filter='AND fk_ecv='.$id.' AND fk_user='.$cvProfile->fk_user;
$html.='<table style="width:100%; border:none !important;" cellpadding="5px"; cellspadding="5px" >'; 
    $html.='<tr>';
        $html.='<td style="background-color:#282e38; color:white; text-align:center;"><br><br><br><div><strong style="font-size:30px;"><span style="color:#dc172e;">'.$objuserecv->firstname.'</span><span> '.$objuserecv->lastname.'</span></strong></div> <div style="font-size:12px;">'.$cvProfile->poste.'</div>';
        $html.='<br><br></td>';
    $html.='</tr>';
    $html.='<tr>';
        $html.='<td style="width:35%;" align="center"><br><br>';
            if(!empty($objuserecv->photo) ){ 

                if($cvProfile->useroradherent == 'ADHERENT'){
                    $dir=$dolibarr_main_data_root.'/adherent/'.$cvProfile->fk_user.'/photos/';
                    if( file_exists($dir) && is_dir($dir) ){
                        $html.= '<img id="photo_user" src="'.DOL_DATA_ROOT.'/adherent/'.$cvProfile->fk_user.'/photos/'.$objuserecv->photo.'"  height="35mm" ><br>';
                    }
                }else{
                    $dir=$dolibarr_main_data_root.'/users/'.$cvProfile->fk_user.'/0/';
                    if( file_exists($dir) && is_dir($dir) ){
                        $html.= '<img id="photo_user" src="'.DOL_DATA_ROOT.'/users/'.$cvProfile->fk_user.'/0/'.$objuserecv->photo.'"  height="35mm" ><br>';
                    }
                    else{
                        $html.= '<img id="photo_user" src="'.DOL_DATA_ROOT.'/users/'.$cvProfile->fk_user.'/'.$objuserecv->photo.'"  height="35mm" ><br>';
                    }
                }
            }
            else{
                $html.= '<img id="photo_user" src="'.dol_buildpath('/sghr/cv/img/user_man.png',1).'" height="35mm" style="border-radius: 50%;border: 1px solid red;"><br>';
            }
            
        $html.='</td>';
        $html.='<td style="width:60%; "><br><br>';
            $html.='<div style="line-height:20px; font-size:15px; width:20px !important;"><b>'.$langs->trans("ecv_objectifs").',</b></div><br><span>'.$cvProfile->objectifs.'</span><br>';
        $html.='</td>';
        $html.='<td style="width:5%;"> </td>';
    $html.='</tr>';
$html.='</table>';
 $html.='<table style="width:100%; border:none !important;" cellpadding="5px"; cellspadding="5px" >'; 
    $html.='<tr>'; 
        $html.='<td style="width:35%">'; 
            $html.='<table style="width:100%; border:none !important;" cellpadding="3px"; cellspadding="3px" >'; 
                
                

                if($cvProfile->useroradherent == 'ADHERENT'){
                    if($objuserecv->phone_mobile){
                        $html.='<tr>';
                            $html.='';
                            $html.='<td><table width="100%"><tr> <td width="5%"></td><td width="15%" align="center"><img src="'.dol_buildpath('/sghr/cv/images/tel_black.png',1).'" height="12px"></td><td width="80%">'.trim($objuserecv->phone_mobile).'</td></tr></table></td>';
                        $html.='</tr>';
                    }
                }else{
                    if($objuserecv->user_mobile){
                        $html.='<tr>';
                            $html.='';
                            $html.='<td><table width="100%"><tr> <td width="5%"></td><td width="15%" align="center"><img src="'.dol_buildpath('/sghr/cv/images/tel_black.png',1).'" height="12px"></td><td width="80%">'.trim($objuserecv->user_mobile).'</td></tr></table></td>';
                        $html.='</tr>';
                    }
                }
                if($objuserecv->email){
                    $html.='<tr>';
                        $html.='';
                        $html.='<td ><table width="100%"><tr><td width="5%"></td><td width="15%" align="center"><img src="'.dol_buildpath('/sghr/cv/images/email_2black.png',1).'"  width="12px"></td><td width="80%">'.trim($objuserecv->email).'</td></tr></table></td>';
                    $html.='</tr>';
                }
                if($objuserecv->address){
                    $html.='<tr>';
                        $html.='';
                        $html.='<td><table width="100%"><tr><td width="5%"></td><td width="15%" align="center"><img src="'.dol_buildpath('/sghr/cv/images/adressblack.png',1).'"  height="12px"></td><td width="80%">'.$objuserecv->getFullAddress(1,', ',$conf->global->MAIN_SHOW_REGION_IN_STATE_SELECT).'</td></tr></table></td>';
                    $html.='</tr>';
                }
                $filter='AND fk_ecv='.$id.' AND fk_user='.$cvProfile->fk_user;
                $CvLicense->fetchAll('','',0,0,$filter);
                if(count($CvLicense->rows) > 0){
                    $typs = "";
                    foreach ($CvLicense->rows as $key => $v) {
                        $typs .= $v->type.", ";
                    }
                    $typs = trim($typs,", ");
                    // $itemp = $CvLicense->rows[0];
                    // if($itemp->exist == "yes"){
                        $html.='<tr>';
                            $html.='<td><table width="100%"><tr><td width="5%"></td><td width="15%" align="center"><img src="'.dol_buildpath('/sghr/cv/images/permisblack.png',1).'"  height="12px"></td><td width="80%">'.$langs->trans("ecv_permis").': '.$typs.'</td></tr></table></td>';
                        $html.='</tr>';
                    // }
                }

                $html.='<br>';
                $html.='<br>';
                $filter='AND fk_ecv='.$id.' AND fk_user='.$cvProfile->fk_user;
                $CvSkill->fetchAll('','',0,0,$filter);
                if(count($CvSkill->rows) > 0){
                    $html.='<tr>';
                        $html.='<td width="5%"></td><td style="border-bottom-style:dashed; border-bottom-color:#dc172e; width:92%" align="left" >'; 
                            $html.= '<strong style="font-size:16px; color:#dc172e">'.$langs->trans("ecv_competences").': </strong>'; 
                        $html.='</td><td width="3%"></td>'; 
                    $html.='</tr>';
                     $html.='<tr>';
                        $html.='<td colspan="3"  style="line-height:1px;"></td>'; 
                    $html.='</tr>';
                    foreach ($CvSkill->rows as $val) {
                        $html.='<tr>';
                            $competance = new Skill($db);
                            $competance->fetch($val->fk_competance);
                                $html.='<td style="width:7%;"></td><td style="width:58%;"align="left">';

                                    if($competance->icon){
                                        $minifile = getImageFileNameForSize($competance->icon, '');  
                                        $urlfile = DOL_DATA_ROOT.'/ecv'.'/Skill/'.$minifile;
                                        if(@getimagesize($urlfile))
                                        $html.='<img alt="Photo" src="'.$urlfile.'" height="13px" > ';
                                    }

                                $html .=$competance->name.'</td>';

                                $html.='<td style="width:35%;" align="right">';
                                    for($i=1; $i <= 5; $i++){
                                        if($i <= $val->value){
                                            $html.='<img src="'.dol_buildpath('/sghr/cv/img/etoile-red.png',1).'"  height="13px" >';
                                        }
                                        else
                                            $html.='<img src="'.dol_buildpath('/sghr/cv/img/null-etoile.png',1).'" height="13px" >';
                                    }
                                $html.='</td>';
                        $html.='</tr>';
                    }
                }
                    $html.='<tr><td colspan="2"><span style="line-height:10px"></span></td></tr>';

                $CvLanguage->fetchAll('','',0,0,$filter);
                if(count($CvLanguage->rows) > 0){
                    $html.='<tr>';
                        $html.='<td width="5%"></td> <td style="border-bottom-style:dashed; border-bottom-color:#dc172e; width:92%;" align="left" ><strong style="font-size:16px; color:#dc172e; ">'.$langs->trans("ecv_langues").':</strong></td><td width="3%"></td>';
                    $html.='</tr>';
                    $filter='AND fk_ecv='.$id.' AND fk_user='.$cvProfile->fk_user;
                    $langs->loadLangs(array('admin', 'languages', 'other', 'companies', 'products', 'members', 'projects', 'hrm', 'agenda'));
                     $html.='<tr>';
                        $html.='<td colspan="3"  style="line-height:1px;"></td>'; 
                    $html.='</tr>';
                    foreach ($CvLanguage->rows as $val) {
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
                            $html.='<td width="5%"></td> <td width="55%" >'.$srcimg.'&nbsp;&nbsp;'.$name.'</td>';
                            $html.='<td width="40%" align="right">';
                                for($i=1; $i <= 5; $i++){
                                    if($i<=$val->value){
                                        $html.='<img src="'.dol_buildpath('/sghr/cv/img/langue-red.png',1).'" height="10px" > ';
                                    }else{
                                        $html.='<img src="'.dol_buildpath('/sghr/cv/img/langue-null.png',1).'" height="10px" > ';
                                    }
                                }
                            $html.='</td><td width="5%"></td>';
                        $html.='</tr>';
                    }
                }
                $html.='<tr><td colspan="2"><span style="line-height:10px"></span></td></tr>';

                $filter='AND fk_ecv='.$id.' AND fk_user='.$cvProfile->fk_user;
                $CvQualification->fetchAll('','',0,0,$filter);
                if(count($CvQualification->rows) > 0){
                    $html.='<tr>';
                        $html.='<td width="5%"></td> <td style="border-bottom-style:dashed; border-bottom-color:#dc172e; width:85%" align="left" ><br><br><strong style="font-size:16px;color:#dc172e;">'.$langs->trans("ecv_qualification").':</strong></td>';
                    $html.='<td width="10%"></td>';
                    $html.='</tr>';
                    $html.='<tr>';
                        $html.='<td width="5%"></td>';
                        $html.='<td align="left" width="95%" >';
                            foreach ($CvQualification->rows as $key => $value) {
                                $html.='<table cellpadding="0px"; cellspadding="0px" width="100%"><tr><td width="5%" style="" align="center"><b>-</b></td><td width="90%" align="left">'.$value->name.'</td></tr></table><br>';
                            }
                        $html.='</td>';
                    $html.='</tr>';
                }

                
            $html.='</table>'; 
        $html.='</td>'; 

        $html.='<td style="width:65%"><br>'; 
           $html.='<table style="width:100%; border:none !important;"   >';
                
                $CvExperience->fetchAll('','',0,0,$filter);
                if(count($CvExperience->rows)>0){
                    $html.='<tr>';
                        $html.='<td style="border-bottom-style:dashed; border-bottom-color:#dc172e; width:95%"><strong style="font-size:16px; color:#dc172e;" >'.$langs->trans("ecv_experiences").':</strong></td>';

                        $html.='<td style="width:5%"></td>';
                    $html.='</tr>';
                    $html.='<tr><td colspan="2"><span style="line-height:20px"></span></td></tr>';
                    foreach ($CvExperience->rows as $key => $value) {
                        $html.='<tr>';
                            $html.='<td style="width:18%; color:grey;" align="right"><span><b> '.$cvProfile->format_year($value->debut).'-';
                            if($value->nosjours == 1){
                                $html.=$langs->trans("ecv_no_jours");
                            }
                            elseif($value->nosjours == 0){ 
                                $html.=$cvProfile->format_year($value->fin);
                            }
                            $html.=' </b></span> <img src="'.dol_buildpath('/sghr/cv/img/langue-red.png',1).'" height="8px" height="8px" > </td>';

                            $html.='<td style="width:67%"><b>'.$value->societe.'</b>';
                            $html.='</td>';
                            $html.='<td style="width:10%" align="right">';
                                if($value->profile_soc){
                                    $minifile = getImageFileNameForSize($value->profile_soc, '');  
                                    $urlfile = DOL_DATA_ROOT.'/ecv'.'/'.$cvProfile->rowid.'/experiences/'.$value->rowid.'/'.$minifile;
                                    if(@getimagesize($urlfile))
                                    $html.='<img src="'.$urlfile.'" height="15px" >';

                                }
                            $html.='</td>';
                            $html.='<td style="width:5%">';
                            $html.='</td>';
                        $html.='</tr>';
                        $html.='<tr>';
                            $html.='<td style="width:18%;" align="center">';
                            $html.='</td>';
                            $html.='<td style="width:77%"><div><span style="text-align:justify;">'.nl2br($value->description).'</span></div></td>';
                            $html.='<td style="width:5%"></td>';
                        $html.='</tr>';
                        $html.='<tr><td colspan="2"><span style="line-height:20px"></span></td></tr>';
                    }
                }

                $CvEducation->fetchAll('','',0,0,$filter);
                if(count($CvEducation->rows)>0){
                    $html.='<tr>';
                        $html.='<td style="border-bottom-style:dashed; border-bottom-color:#dc172e; width:95%"><strong style="font-size:16px; color:#dc172e;">'.$langs->trans("ecv_formations").':</strong></td>';
                        $html.='<td style="width:5%"></td>';
                    $html.='</tr>';
                    $html.='<tr><td colspan="2"><span style="line-height:20px"></span></td></tr>';
                    foreach ($CvEducation->rows as $key => $value) {
                        
                        $html.='<tr>';
                            $html.='<td style="width:18%; color:grey;" align="center"><b>'.$cvProfile->format_year($value->debut).'-';
                             if($value->nosjours == 1){
                                $html.=$langs->trans("ecv_no_jours");
                            }
                            elseif($value->nosjours == 0){  
                                $html.=$cvProfile->format_year($value->fin);
                            }
                            $html.=' </b> <img src="'.dol_buildpath('/sghr/cv/img/langue-red.png',1).'" height="8px" ></td>';
                            $html.='<td style="width:67%"><b>'.$value->etablissement.'</b><br>'.$value->filiere;
                            $html.='<br></td>';
                            $html.='<td style="width:5%"></td>';
                        $html.='</tr>';
                    }
                }

                $CvCertificate->fetchAll('','',0,0,$filter);
                if(count($CvCertificate->rows)>0){
                    $html.='<tr>';
                        $html.='<td  style="border-bottom-style:dashed; border-bottom-color:#dc172e; width:95%"><strong style="font-size:16px; color:#dc172e;">'.$langs->trans("ecv_certificats").':</strong></td>';
                    $html.='</tr>';
                    $html.='<tr><td colspan="2"><span style="line-height:20px"></span></td></tr>';
                    foreach ($CvCertificate->rows as $key => $value) {
                        
                        $html.='<tr>';
                            $html.='<td style="width:18%; color:grey;" align="center"><b>'.$cvProfile->format_year($value->debut).'-'.$cvProfile->format_year($value->fin).' </b> <img src="'.dol_buildpath('/sghr/cv/img/langue-red.png',1).'" height="8px" ></td>';
                            $html.='<td style="width:82%"><b>'.$value->intitule.'</b>';
                            $html.='</td>';
                        $html.='</tr>';
                        $html.='<tr>';
                        $html.='<td style="width:18%;" align="center">';

                        $minifile = getImageFileNameForSize($value->copie, '');  
                        $urlfile = DOL_DATA_ROOT.'/ecv'.'/'.$cvProfile->rowid.'/certificates/'.$value->rowid.'/'.$minifile;
                        if(@getimagesize($urlfile))
                        $html .= '<img src="'.$urlfile.'" height="35px" >';
                        
                        $html.='</td>';
                        $html.='<td style="width:77%"><span style="text-align:justify;">'.nl2br($value->description).'</span></td>';
                        $html.='<td style="width:5%"></td>';
                        $html.='</tr>';
                        $html.='<tr><td colspan="2"><span style="line-height:5px"></span></td></tr>';
                    }
                }

            $html.='</table>';
        $html.='</td>'; 
    $html.='</tr>'; 
$html.='</table>'; 

    
 