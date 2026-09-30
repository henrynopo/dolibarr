<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/ecv/class/ecv.class.php');
dol_include_once('/ecv/class/ecvcertificats.class.php');
$ecv              = new ecv($db);
$ecvcertificats   = new ecvcertificats($db);
    $id=GETPOST('id');
    // $id_delete=GETPOST('delete_id');
    // if(!empty($id_delete)){
    //     $representant->fetch($id_delete);
    //     $error = $representant->delete();
    // }
    $ecvcertificats->fetch($id);
    $item=$ecvcertificats;
    $d=explode('-',$item->debut);
    $debut=$d[2].'/'.$d[1].'/'.$d[0];

    $f=explode('-',$item->fin);
    $fin=$f[2].'/'.$f[1].'/'.$f[0];
    $data.='<tr>';
            $data.='<input name="id" type="hidden" value="'.$id.'">';
            $data.='<input name="id_ecv" type="hidden" value="'.$item->fk_ecv.'">';
            $data.='<input name="action"  type="hidden" value="edit">';
            $data.='<input name="entity"  type="hidden" value="'.$item->entity.'">';
        $data.='<td style="width:14px;"><input  type="text" name="etablissement" value="'.$item->etablissement.'" autocomplete="off"  /></td> ';


        $data.='<td> <input style="width:95%;" name="intitule" autocomplete="off" value="'.$item->intitule.'" /></td>';


        $data.='<td style="width:8px;"><input type="text" name="debut" class="datepickerecvmod debut" id="debut" value="'.$debut.'" autocomplete="off" /></td>'; 

        $data.='<td style="width:8px;"><input  type="text" name="fin" class="datepickerecvmod fin" id="fin" value="'.$fin.'" autocomplete="off"  /></td> ';
        $data.='<td> <textarea style="width:95%;" name="description" autocomplete="off"> '.$item->description.' </textarea></td>';

        $data.='<td><input type="file" accept="image/*" class="copie" id="copie" name="copie" style="display:none;" autocomplete="off"/>';
        $data.='<span>';
            if($item->copie){
                $data.= $item->copie ;
            }
        $data.='</span>';
        $data.='<a class="butAction" id="upload" onclick="getcopie(this);" style="background-color:#3c4664; color:white; float: right;" > Upload </a></td>';

        $data.='<td align="center" style="width:8px;"><input type="submit" value="'.$langs->trans('Validate').'" name="bouton" class="butAction valider" /> <img src="'.dol_buildpath('/ecv/images/check.png',2).'" height="25px" class="img_valider"></td>';
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
        

    });
</script>