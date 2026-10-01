<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/cv/class/Cv.class.php');
dol_include_once('/sghr/cv/class/CvEducation.class.php');
$cvProfile              = new Cv($db);
$CvEducation   = new CvEducation($db);
    $id=GETPOST('id');
    // $id_delete=GETPOST('delete_id');
    // if(!empty($id_delete)){
    //     $representant->fetch($id_delete);
    //     $error = $representant->delete();
    // }
    $CvEducation->fetch($id);
    $item=$CvEducation;
    $d=explode('-',$item->debut);
    $debut=$d[2].'/'.$d[1].'/'.$d[0];

    $f=explode('-',$item->fin);
    $fin=$f[2].'/'.$f[1].'/'.$f[0];
    $checked ="";
    // print_r($projets);die();
    $data.='<tr>';
            $data.='<input name="id" type="hidden" value="'.$id.'">';
            $data.='<input name="id_ecv" type="hidden" value="'.$item->fk_ecv.'">';
            $data.='<input name="action"  type="hidden" value="edit">';
            $data.='<input name="entity"  type="hidden" value="'.$item->entity.'">';
            
        $data.='<td style="width:14px;"><input  type="text" name="etablissement" value="'.$item->etablissement.'" autocomplete="off"  /></td> ';

        $data.='<td> '.$CvEducation->select_niveau($item->niveau).'</td>';

        $data.='<td> <input style="width:95%;" name="intitule" autocomplete="off" value="'.$item->intitule.'" /></td>';

        $data.='<td> <input style="width:95%;" name="filiere" autocomplete="off" value="'.$item->filiere.'" /></td>';

        $data.='<td style="width:8%;"><input type="text" name="debut" class="datepickerecvmod debut" id="debut" value="'.$debut.'" autocomplete="off" /></td>'; 
        if($item->nosjours == 1){
            $checked ="checked";
        }elseif($item->nosjours == 0){
            $checked ="";
        }
        $data.='<td style="width:8%;"><input  type="text" name="fin" class="datepickerecvmod fin" id="fin" value="'.$fin.'" autocomplete="off"  /><div align="center"><label><input type="checkbox" name="no_jours" id="no_jours" '.$checked.' onchange="no_jourss(this);" > <b class="nos_jour">'.$langs->trans("ecv_no_jours") .'</b></label></div> </td> ';

        $data.='<td align="center" style="width:8px;"><input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction valider" /> <img src="'.dol_buildpath('/sghr/cv/images/check.png',2).'" height="25px" class="img_valider"></td>';
    $data.='</tr>';
    $data .= '<tr><td colspan="7" style="border:none !important" align="right"><a href="./index.php?id_ecv='.$item->fk_ecv.'" class="butAction">'.$langs->trans('Cancel').'</a></td><tr>';
    
    $data.='<script>$(document).ready(function(){$( ".datepickerecvmod" ).datepicker({dateFormat: "dd/mm/yy"}); $(".valider").hide(); $(".img_valider").click(function(){$(".valider").trigger("click")}); });</script>';
    	
    echo $data;
?>
<script>
    $(document).ready(function(){
        $(".niveau").select2();
        $( "#debut" ).change( function(){
            $min=$(this).val();
            $("#fin").datepicker( "option", "minDate", $min );
        });
        $( "#fin" ).change( function(){
            $max=$(this).val();
            $("#debut").datepicker( "option", "maxDate", $max );
        });
        $('#no_jours').change(function(){
            no_jourss( $('#no_jours'));
        });
        

    });

</script>