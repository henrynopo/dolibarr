<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/cv/class/ecv.class.php');
dol_include_once('/sghr/cv/class/CvQualification.class.php');
$cvProfile              = new Cv($db);
$CvQualification   = new CvQualification($db);
    $id=GETPOST('id');

    // $id_delete=GETPOST('delete_id');
    // if(!empty($id_delete)){
    //     $representant->fetch($id_delete);
    //     $error = $representant->delete();
    // }
    $CvQualification->fetch($id);
    $item=$CvQualification;
 
    $data.='<tr>';
            $data.='<input name="id" type="hidden" value="'.$id.'">';
            $data.='<input name="id_ecv" type="hidden" value="'.$item->fk_ecv.'">';
            $data.='<input name="action"  type="hidden" value="edit">';
            $data.='<input name="entity"  type="hidden" value="'.$item->entity.'">';
        $data.='<td ><input  type="text" name="name" value="'.$item->name.'" autocomplete="off" style="width:99%;" /></td> ';

        $data.='<td align="center" ><input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction valider" /> <img src="'.dol_buildpath('/sghr/cv/images/check.png',2).'" height="25px" class="img_valider"></td>';
    $data.='</tr>';
    $data.= '<tr><td colspan="3" style="border:none !important" align="right"><a href="./index.php?id_ecv='.$item->fk_ecv.'" class="butAction">'.$langs->trans('Cancel').'</a></td><tr>';
    
    $data.='<script>$(document).ready(function(){ $(".valider").hide(); $(".img_valider").click(function(){$(".valider").trigger("click")}); });</script>';
    	
    echo $data;
?>
<script>
    $(document).ready(function(){
        $( "#debut" ).change( function(){
            $min=$(this).val();
            $("#fin").datepicker( "option", "minDate", $min );
        });
        $( "#fin" ).change( function(){
            $max=$(this).val();
            $("#debut").datepicker( "option", "maxDate", $max );
        });
        

    });
</script>