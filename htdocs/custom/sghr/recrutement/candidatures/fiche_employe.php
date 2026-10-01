<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 



dol_include_once('/sghr/recrutement/class/etapescandidature.class.php');
dol_include_once('/sghr/recrutement/class/candidatures.class.php');
dol_include_once('/sghr/recrutement/class/departement.class.php');
dol_include_once('/sghr/recrutement/lib/recrutement.lib.php');
dol_include_once('/sghr/recrutement/class/postes.class.php');
dol_include_once('/core/class/html.form.class.php');

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';


$langs->load('recrutement@sghr');

$modname = $langs->trans("fiche_employe");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$candidature = new candidatures($db);
$postes       = new postes($db);
$employe      = new User($db);
$respon      = new User($db);
$form         = new Form($db);

if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}
$id = GETPOST('candidature');

// echo $filter;

// $nbrtotal = $candidatures->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);
print_fiche_titre($modname);


$head = menu_candidature($id);
if($action != 'add'){
    dol_fiche_head(
        $head,
        'fiche_employe',
        '', 
        0,
        "recrutement@sghr"
    );
}
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

$candidature->fetch($id);
if($candidature->employe){
    $employe->fetch($candidature->employe);
}
// print_r($employe);die();
// print '<span class="createemployrecrutbutt">';
//  print '<a class="butAction" style="background-color:green !important;color:white !important; float: right; " data-id="'.$id.'" id="delete" >'.$langs->trans('delete').'</a>';
// print '</div>';
 print '<table class="border" width="100%">';

    print '<tbody>';
        print '<tr>';
            print '<td class="firsttd200px">'.$langs->trans('nom_employe').'</td>';
            // print '<td style="width:80% !important">'.$employe->lastname.' '.$employe->firstname.'</td>';
            print '<td>'.$employe->getNomUrl(1).'</td>';
        print '</tr>';
            print '<td class="firsttd200px">'.$langs->trans('email').'</td>';
            print '<td>'.$employe->email.'</td>';
        print '</tr>';
            print '<td class="firsttd200px">'.$langs->trans('tel').'</td>';
            print '<td>'.$employe->office_phone.'</td>';
        print '</tr>';
            print '<td class="firsttd200px">'.$langs->trans('mobile').'</td>';
            print '<td>'.$employe->user_mobile.'</td>';
        print '</tr>';
            print '<td class="firsttd200px">'.$langs->trans('poste').'</td>';
            print '<td>'.$employe->job.'</td>';
        print '</tr>';
            $respon->fetch($employe->fk_user);
            print '<td class="firsttd200px">'.$langs->trans('responsable').'</td>';
            print '<td>'.$respon->getNomUrl(1).'</td>';
        print '</tr>';
  
    print '</tbody>';
print '</table>';

// print '<span class="createemployrecrutbutt" style="display: inline-block;">';
    // print '<a class="butActionBTNC butActionDelete" style="float: right; " data-id="'.$id.'" id="delete" >'.$langs->trans('Delete').'</a>';
// print '</div>';

?>
<script>
	$(function(){
        $('#list').css('background-color','rgba(0, 0, 0, 0.15)');
		$( ".datepicker" ).datepicker({
	    	dateFormat: 'dd/mm/yy'
		});
		$('#srch_fk_user').select2();
		$('#srch_fk_product').select2();

		$('.icon_list').click(function(){
        	$type=$(this).data('type');
        	if($type == 'list'){
        		$('#grid').css('background-color','white');
        		$('#list').css('background-color','rgba(0, 0, 0, 0.15)');
        		$('.board').hide();
        		$('.list').show();
        	}
        	if($type == 'grid'){
        		$('#list').css('background-color','white');
        		$('#grid').css('background-color','rgba(0, 0, 0, 0.15)');
        		$('.board').show();
        		$('.list').hide();
        	}
        });
         $('#delete').click(function(){
            $id=$('#delete').data('id');
            // console.log($id);
            $.ajax({
                data:{'id_candidature':$id,},
                url:"<?php echo dol_bildpath('/sghr/recrutement/candidatures/info_contact.php?action_=delete_user',2) ?>",
                type:'POST',
                success:function($data){
                    console.log($data);
                    if($data == 'Ok'){
                        window.location.href="<?php echo dol_bildpath('/sghr/recrutement/candidatures/card.php?id='.$id,2) ;?>";
                    }else
                    location.reload();

                }
            });
        });


	});
</script>


<?php

llxFooter();