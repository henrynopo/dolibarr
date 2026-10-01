<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/cv/class/ecv.class.php');
dol_include_once('/sghr/cv/class/CvSkill.class.php');


$cvProfile              = new Cv($db);
$CvSkill   = new CvSkill($db);
$Skill   = new Skill($db);
    $id=GETPOST('langue_id');
    $data=GETPOST('data');
    $CvSkill->fetch($id);
    $item=$CvSkill;
    $Skill->fetch($item->fk_competance);
    if($data == 'select'){
        $outdata = $CvSkill->select_competances($item->fk_competance,'competances_new['.$id.'][name]');
        echo $outdata;
    }
    elseif($data == 'edit')
    {

        $minifile = getImageFileNameForSize($Skill->icon, '');  
        $dt_files = getAdvancedPreviewUrl('ecv', 'Skill/'.$minifile, 1, '&entity='.$conf->entity);
                
        $outdata = '<tr class="rating_edit">';
            $outdata.='<input name="id" type="hidden" value="'.$id.'">';
            $outdata.='<input name="id_ecv" type="hidden" value="'.$item->fk_ecv.'">';
            $outdata.='<input name="action"  type="hidden" value="edit">';
            $outdata.='<input name="entity"  type="hidden" value="'.$item->entity.'">';
            
            $outdata .='<td>';

            $urlfile = DOL_DATA_ROOT.'/ecv'.'/Skill/'.$minifile;
            
            if(@getimagesize($urlfile))
            $outdata .=' <img alt="Photo" src="'.DOL_URL_ROOT.'/viewimage.php?modulepart=ecv&entity='.$conf->entity.'&file=Skill/'.$minifile.'&perm=download" height="30px" >';
            $outdata .= $Skill->name;
            $outdata .='</td>';
            $outdata .='<td align="center" >';
            $outdata .='<div class="rating" >';
            $rating='<input type="radio" id="st5_'.$id.'" name="value" value="5" /><label for="st5_'.$id.'"></label>';
                $rating.='<input type="radio" id="st4_'.$id.'" name="value" value="4" /><label for="st4_'.$id.'"></label>';
                $rating.='<input type="radio" id="st3_'.$id.'" name="value" value="3" /><label for="st3_'.$id.'"></label>';
                $rating.='<input type="radio" id="st2_'.$id.'" name="value" value="2" /><label for="st2_'.$id.'"></label>';
                $rating.='<input type="radio" id="st1_'.$id.'" name="value" value="1" /><label for="st1_'.$id.'"></label>';
                $rating = str_replace('value="'.$item->value.'"', 'value="'.$item->value.'" checked', $rating);
                $outdata .=$rating;
            $outdata .='</div></td>';
            $outdata.='<td align="center" style="width:8px;"><input type="submit" style="display:none" value="'.$langs->trans('Validate').'" name="bouton" class="butAction valider" /> <img src="'.dol_buildpath('/sghr/cv/images/check.png',2).'" height="25px" class="img_valider"></td>';
        $outdata .= '</tr>'; 
        $outdata .= '<tr><td colspan="3" style="border:none !important" align="right"><a href="./index.php?id_ecv='.$item->fk_ecv.'" class="butAction">'.$langs->trans('Cancel').'</a></td><tr>';
        
        $outdata.='<script>$(document).ready(function(){$( ".datepickerecvmod" ).datepicker({dateFormat: "dd/mm/yy"}); $(".valider").hide(); $(".img_valider").click(function(){$(".valider").trigger("click")}); });</script>';
        echo $outdata; 
    }
?>
<script>
    $(document).ready(function(){
        $('.select_competances').select2();
    });
</script>