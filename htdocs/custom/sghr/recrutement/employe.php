<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
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

$modname = $langs->trans("candidatures");
// print_r(picto_from_langcode($langs->defaultlang));die();
// Initial Objects
$candidature  = new candidatures($db);
$candidature_2  = new candidatures($db);

$etape  = new etapescandidature($db);
$poste      = new postes($db);
$employe      = new User($db);
$form         = new Form($db);


$var                = true;
$sortfield          = ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder          = ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id                 = (int) $_GET['id'];
$action             = $_GET['action'];


if (!$user->rights->sghr->rec->read) {
	accessforbidden();
}


$id = GETPOST('poste');
// echo $filter;

$limit  = $conf->liste_limit+1;
$page   = GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
// $filter.='AND poste = '.$id;
$nbrtotal = $candidature->fetchAll($sortorder, $sortfield, $limit, $offset, 'AND poste ='.$id);
$nbrtotalnofiltr = $candidature_2->fetchAll();

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "&poste=$id", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

// print_fiche_titre($modname);


$head = menu_poste($id);
if($action != 'add'){
    dol_fiche_head(
        $head,
        'employes',
        '', 
        0,
        "recrutement@sghr"
    );
}
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);

// $employe->fetch($candidature->employe);
// print_r($employe);die();

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input name="pagem" type="hidden" value="'.$page.'">';
print '<input name="offsetm" type="hidden" value="'.$offset.'">';
print '<input name="limitm" type="hidden" value="'.$limit.'">';
print '<input name="filterm" type="hidden" value="'.$filter.'">';
print '<input name="id_cv" type="hidden" value="'.$id_recrutement.'">';

print '<table id="table-1" class="noborder" style="width: 100%;" >';
print '<thead>';

print '<tr class="liste_titre">';


// field($langs->trans("ref"),'ref');
field($langs->trans("nom_employe"),'nom_employe');
field($langs->trans("email"),'email');
field($langs->trans("tel"),'tel');
field($langs->trans("mobile"),'mobile');
field($langs->trans("fonction"),'fonction');
field($langs->trans("responsable_RH"),'responsable_RH');
field($langs->trans("etape"),'situation');


print '</thead><tbody>';

    // $candidature->fetchAll('','',0,0,' );
    // $candidature->fetchAll('','',0,0,' AND (employe IS NOT NULL OR employe != 0) AND poste = '.$id);
    $colspn = 7;
    if (count($candidature->rows) > 0) {
        for ($i=0; $i < count($candidature->rows) ; $i++) {
            $var = !$var;
            $item = $candidature->rows[$i];

            print '<tr '.$bc[$var].' >';
                print '<td align="left" style="">';
                print '<a href="'.dol_buildpath('/sghr/recrutement/candidatures/card.php?id='.$item->rowid,2).'" >';
                print $item->prenom.' '.$item->nom;
                print '</a>';
                print '</td>';
                // print '<td align="left" style="">'.$item->nom.'</td>';
                // $user->fetch($item->color);
                print '<td align="left" style="">'.$item->email.'</td>';
                print '<td align="left" style="">'.$item->tel.'</td>';
                print '<td align="left" style="">'.$item->mobile.'</td>';
                $poste->fetch($item->poste);
                print '<td align="left" style="">'.$poste->label.'</td>';
                $employe->fetch($item->employe);
                // print '<td align="left">'.$employe->lastname.' '.$employe->firstname.'</td>';
                print '<td align="left">'.$employe->getNomUrl(1).'</td>';
                $etape->fetch($item->etape);
                print '<td align="left">';
                    if($item->refuse == 1){
                        print 'Refusé';
                    }else
                        print $langs->trans($etape->label);
                print '</td>';

            print '</tr>';
        }
    }else{
        print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
    }

print '</tbody></table></form>';

function field($titre,$champ){
    global $langs;
    print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
        print '<a href="?sortfield='.$champ.'&amp;sortorder=desc">';
        print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/recrutement/img/1uparrow.png',2).'" alt="" title="Z-A" class="imgup" border="0"></span>';
        print '</a>';
        print '<a href="?sortfield='.$champ.'&amp;sortorder=asc">';
        print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/recrutement/img/1downarrow.png',2).'" alt="" title="A-Z" class="imgup" border="0"></span>';
        print '</a>';
    print '</th>';
}

?>
<script>
	$(function(){
        $('.fiche').find('.tabBar').removeClass('tabBarWithBottom');
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
                url:"<?php echo dol_buildpath('/sghr/recrutement/candidatures/info_contact.php?action_=delete_user',2) ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        window.location.href="<?php echo dol_buildpath('/sghr/recrutement/candidatures/card.php?id='.$id,2) ;?>";
                    }

                }
            });
        });


	});
</script>

<?php

llxFooter();