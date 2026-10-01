<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/ecv/class/ecv.class.php');
dol_include_once('/sghr/ecv/class/competances.class.php');
dol_include_once('/core/class/html.form.class.php');
dol_include_once('/sghr/ecv/lib/ecv.lib.php');


$langs->load('ecv@sghr');

$modname = $langs->trans("ecv_competences");

// Initial Objects
$ecv  = new ecv($db);
$competances = new competances($db);
$form           = new Form($db);

$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

if (!$user->rights->sghr->cv->read) {
	accessforbidden();
}


$srch_name = GETPOST('srch_name');
$srch_value 		= GETPOST('srch_value');


$filter .= (!empty($srch_name)) ? " AND name like '%".$srch_name."%'" : "";




$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter") || $page < 0) {
	$filter = "";
	$offset = 0;
	$filter = "";
	$srch_name = "";
	$srch_title = "";
}

// echo $filter;

$nbrtotal = $competances->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);


print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
$id_ecv=GETPOST('id_ecv');
// if(!empty($id_ecv)){
// 	$head = ecvAdminPrepareHead($id_ecv);
//     dol_fiche_head($head,'competances','',	0,"competances@competances");
// }

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="competcecv">'."\n";
    print '<input name="pagem" type="hidden" value="'.$page.'">';
    print '<input name="offsetm" type="hidden" value="'.$offset.'">';
    print '<input name="limitm" type="hidden" value="'.$limit.'">';
    print '<input name="filterm" type="hidden" value="'.$filter.'">';

    print '<div style="float: right; margin: 8px;">';
    	print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
    print '</div>';

    print '<table id="table-1" class="noborder" style="width: 100%;" >';
    print '<thead>';

        print '<tr class="liste_titre">';
			print_liste_field_titre($langs->trans("ecv_competence"), $_SERVER["PHP_SELF"], "", "", $param, 'align="left"', $sortfield, $sortorder);
			print_liste_field_titre($langs->trans("ecv_icon_competence"), $_SERVER["PHP_SELF"], "", "", $param, 'align="left"', $sortfield, $sortorder);
            print '<th align="center">'.$langs->trans("Action").'</th>';
        print '</tr>';

        print '<tr class="liste_titre nc_filtrage_tr">';
            print '<td align="left"><input style="max-width: 129px;" type="text" class="" id="srch_name" name="srch_name" value="'.$srch_name.'"/></td>';

            print '<td align="left"></td>';
            print '<td align="center">';
    	    print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
    	    print '&nbsp;<input type="image" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"></td>';
        print '</tr>';
    print '</thead>';
    print '<tbody>';
	$colspn = 5;
	$ecv->fetch($id_ecv);

		if (count($competances->rows) > 0) {
			for ($i=0; $i < count($competances->rows) ; $i++) {


				$var = !$var;
				$item = $competances->rows[$i];

				print '<tr '.$bc[$var].' >';
		    		print '<td align="left" style=""> ';
		    		print '<a href="'.dol_buildpath('/sghr/ecv/gestion_competance/card.php?id='.$item->rowid,2).'" >';
		    		print  $item->name;
		    		print '</a></td>';
		    		print '<td align="left" style="">';
		    		print '<div> <ul style="padding: 0;list-style: none;">';
				        if($item->icon){
		                    print '<li>';
		                        $minifile = getImageFileNameForSize($item->icon, '');  
		                        // $dt_files = getAdvancedPreviewUrl('ecv', 'competances/'.$item->rowid.'/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));
		                        $dt_files = getAdvancedPreviewUrl('ecv', 'competances/'.$minifile, 1, '&entity='.(!empty($object->entity)?$object->entity:$conf->entity));

		                        print ' <a href="'.$dt_files['url'].'" class="'.$dt_files['css'].'" target="'.$dt_files['target'].'" mime="'.$dt_files['mime'].'">' ;
		                            print '<img class="photo" title="'.$minifile.'" height="20px" alt="Fichier binaire" src="'.$dt_files['url'].'" border="0" name="image" >';
		                        print '</a> ';
		                    print '</li>';
				        }
				        print '</ul></div>';
		    		print '</td>';
		    		print '<td></td>';
				print '</tr>';
			}
		}else{
			print '<tr><td align="center" colspan="'.$colspn.'">'.$langs->trans("NoResults").'</td></tr>';
		}

print '</tbody></table>';
    print '<div id="lightbox" style="display:none;"><p>X</p><div id="content"><img src="" /></div></div>';
print '</form>';


function field($titre,$champ){
	global $langs;
	print '<th class="" style="padding:5px; 0 5px 5px; text-align:center;">'.$langs->trans($titre).'<br>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=desc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/ecv/img/1uparrow.png',2).'" alt="" title="Z-A" class="imgup" border="0"></span>';
		print '</a>';
		print '<a href="?sortfield='.$champ.'&amp;sortorder=asc">';
		print '<span class="nowrap"><img src="'.dol_buildpath('/sghr/ecv/img/1downarrow.png',2).'" alt="" title="A-Z" class="imgup" border="0"></span>';
		print '</a>';
	print '</th>';
}





?>
<script>
	$( function() {
	$( ".datepickerecvmod" ).datepicker({
    	dateFormat: 'dd/mm/yy'
	});
    $('#select_projet').select2();
    $('#select_tache').select2();

	$('#select_onechambre>select').select2();
	} );

	$('.lightbox_trigger').click(function(e) {
            e.preventDefault();
            var image_href = $(this).attr("href");
            $('#lightbox #content').html('<img src="' + image_href + '" />');
            $('#lightbox').show();
        });

        $('#lightbox,#lightbox p').click(function() {
            $('#lightbox').hide();
        });
</script>
<style type="text/css">
	
</style>
<?php

llxFooter();