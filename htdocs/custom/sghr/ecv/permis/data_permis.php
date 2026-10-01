<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/ecvpermis.class.php');
$ecv              = new ecv($db);
$ecvpermis   = new ecvpermis($db);
    $id=GETPOST('permis_id');
    $data=GETPOST('data');
    
    $ecvpermis->fetch($id);
    $item=$ecvpermis;
    if($data == 'select'){
        $outdata = $ecvpermis->select_language($item->name,'permis_new['.$id.'][name]');
        echo $outdata;
    }
    elseif($data == 'edit')
    {
        $langs->loadLangs(array('admin', 'languages', 'other', 'companies', 'products', 'members', 'projects', 'hrm', 'agenda'));

        $outdata = '<tr class="rating_edit">';
            $outdata.='<input name="id" type="hidden" value="'.$id.'">';
            $outdata.='<input name="id_ecv" type="hidden" value="'.$item->fk_ecv.'">';
            $outdata.='<input name="action"  type="hidden" value="edit">';
            $outdata.='<input name="entity"  type="hidden" value="'.$item->entity.'">';
            $outdata .='<td align="center" >';
            $outdata .='<input type="text" style="width:100%;" name="type" value="'.$item->type.'" autocomplete="off"/>';
            $outdata .='</td>';
            $outdata .='<td align="center" >';
            $outdata .='<input type="number" style="width:60px;" name="year" value="'.$item->year.'" autocomplete="off"/>';
            $outdata .='</td>';
            $outdata.='<td align="center" style="width:8px;"><input type="submit" style="display:none" value="'.$langs->trans('Validate').'" name="bouton" class="butAction valider" /> <img src="'.dol_buildpath('/sghr/ecv/images/check.png',2).'" height="25px" class="img_valider"></td>';
        $outdata .= '</tr>'; 
        $outdata .= '<tr><td colspan="3" style="border:none !important" align="right"><a href="./index.php?id_ecv='.$item->fk_ecv.'" class="butAction">'.$langs->trans('Cancel').'</a></td><tr>';
        $outdata.='<script>$(document).ready(function(){$( ".datepickerecvmod" ).datepicker({dateFormat: "dd/mm/yy"}); $(".valider").hide(); $(".img_valider").click(function(){$(".valider").trigger("click")}); });</script>';
        echo $outdata; 
    }
?>
<script>
    $(document).ready(function(){
        $('.lg').select2();
    });
</script>