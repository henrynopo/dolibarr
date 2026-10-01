<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/ecvexperiences.class.php');
$ecv              = new ecv($db);
$ecvexperiences   = new ecvexperiences($db);
    $id=GETPOST('id');

    // $id_delete=GETPOST('delete_id');
    // if(!empty($id_delete)){
    //     $representant->fetch($id_delete);
    //     $error = $representant->delete();
    // }
    $ecvexperiences->fetch($id);
    $item=$ecvexperiences;
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."representant where fk_intervenant =".$intervenant_id;
    $d=explode('-',$item->debut);
    $debut=$d[2].'/'.$d[1].'/'.$d[0];
    $checked="";
    $f=explode('-',$item->fin);
    $fin=$f[2].'/'.$f[1].'/'.$f[0];
    $projets=json_decode($item->projets);
    // print_r($projets);die();
    $data.='<tr>';
            $data.='<input name="id" type="hidden" value="'.$id.'">';
            $data.='<input name="id_ecv" type="hidden" value="'.$item->fk_ecv.'">';
            $data.='<input name="action"  type="hidden" value="edit">';
            $data.='<input name="entity"  type="hidden" value="'.$item->entity.'">';

        $data.='<td style="width:14px;"><input  type="text" name="experiences['.$id.'][societe]" value="'.$item->societe.'" autocomplete="off"  /></td> ';

        $data.='<td style="width:8%;"><input  type="text" name="experiences['.$id.'][debut]" class="datepickerecvmod debut" id="debut" value="'.$debut.'" autocomplete="off"  /></td>'; 

        if($item->nosjours == 1){
            $checked ="checked";
        }elseif($item->nosjours == 0){
            $checked ="";
        }

        $data.='<td style="width:8%;"><input  type="text" name="experiences['.$id.'][fin]" class="datepickerecvmod fin" id="fin" value="'.$fin.'" autocomplete="off"  /><br><div align="center"><label><input type="checkbox" onchange="nojours(this);" '.$checked.'  name="experiences['.$id.'][no_jours]" id="no_jours"> <b>'.$langs->trans("ecv_no_jours").'</b></label></div></td> ';
        $data.='<td> <textarea  style="width:95%;" name="experiences['.$id.'][description]" autocomplete="off" >'.$item->description.'</textarea></td>';
        $data.='<td><input type="file" accept="image/*" class="file" id="profile" name="profile" style="display:none;" autocomplete="off"/><span>';
        if($item->profile_soc)
            $data.= $item->profile_soc ;
        $data.='</span><a class="butAction" id="upload" onclick="getprofile(this);"  style="background-color:#3c4664; color:white; float: right;" > Upload </a></td>';
        $data.='<td class="projets">';
            $data.='<table width="100%"><tbody class="projet" >';
            if($projets){
                foreach ($projets as $key => $value) {
                    $data.='<tr><td style="border:none !important;"><input style="width:95%" type="text" value="'.$value.'" name="experiences['.$id.'][projets][]"></td><td style="border:none !important;"><img src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/delete.png" class="delete_projet" onclick="delete_tr(this);"></td></tr>';
                }
            }
            $data.='</tbody></table>';
            $data.='</div>';
            $data.='<div align="right">';
                $data.='<img src="'.dol_buildpath('/sghr/ecv/images/add_.png',2).'"height="25px" onclick="add_projet(this);" data-id='.$id.' class="img_add">';
            $data.='</div>';
        $data.='</td>';
        $data.='<td align="center" style="width:8px;"><input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction valider" /> <img src="'.dol_buildpath('/sghr/ecv/images/check.png',2).'" height="25px" class="img_valider"></td>';
    $data.='</tr>';
    $data .= '<tr><td colspan="7" style="border:none !important" align="right"><a href="./index.php?id_ecv='.$item->fk_ecv.'" class="butAction">'.$langs->trans('Cancel').'</a></td><tr>';
    
    $data.='<script>$(document).ready(function(){$( ".datepickerecvmod" ).datepicker({dateFormat: "dd/mm/yy"}); $(".valider").hide(); $(".img_valider").click(function(){$(".valider").trigger("click")}); });</script>';
    	
    echo $data;
?>
<script>
    $(document).ready(function(){

    });
        $( "#debut" ).change( function(){
            $min=$(this).val();
            $("#fin").datepicker( "option", "minDate", $min );
        });
        $( "#fin" ).change( function(){
            $max=$(this).val();
            $("#debut").datepicker( "option", "maxDate", $max );
        });
        function nojours(check){
            $checked_=$(check).prop('checked');
            if($checked_ == true){
                $(check).parent().parent().parent().find('.fin').hide();
            }else
                $(check).parent().parent().parent().find('.fin').show();
        }
        

</script>