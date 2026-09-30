<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/cnss_utilisateur/class/cnss_utilisateur.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');

$var = true;
$cnss_utilisateur = new cnss_utilisateur($db);
$form 		= new Form($db);
$userp = new User($db);
$formother      = new FormOther($db);
$search_id 	  = GETPOST('search_id','int');
$search_idu 	  = GETPOST('search_idu','int');
$search_montant_cnss    = GETPOST('search_montant_cnss');

$search_year     = GETPOST('search_year');
$search_month     = GETPOST('search_month');

$action   = GETPOST('action','alpha');

/*-------------XSL----------------*/
if ( $action == "exl" ) {

$filename="racap_".$search_year."_".$search_month.".xls";
      require_once dol_buildpath('/grh/cnss_utilisateur/tpl/cnss_xsl.php');
 die();
 
}
/*-------------XSL----------------*/
// $search_year = (empty($search_year) || $search_year == -1) ? date("Y",strtotime("now"))  : $search_year ;
// $search_month = (empty($search_month) || $search_month == -1) ? date("m",strtotime("now"))  : $search_month ;

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
$search_id      		= '';
$search_idu      		= '';
$search_montant_cnss    = '';
$search_year			= '';
$search_month    		= '';
}
$filter .= (!empty($search_id) && $search_id != -1) ? " AND id = ". $db->escape($search_id)."\n" : "";
$filter .= (!empty($search_idu) && $search_idu != -1) ? " AND idutilisateur = ". $db->escape($search_idu)."\n" : "";
$filter .= (!empty($search_montant_cnss) && $search_montant_cnss != -1) ? " AND cast(montant_cnss as decimal(5,1)) = '".str_replace(",", ".", $db->escape($search_montant_cnss))."'":"";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) = ".$db->escape($search_year) : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) = ".$db->escape($search_month) : "";


//llxHeader('', $langs->trans('utilisateurs'));			// Header
//add files ( js , css , ..... ) to header
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/cnss_utilisateur.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");
llxHeader(array(), $langs->trans('gere'),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans('gere'));	// TITLE

// echo "<br><br><br>".$search_month;
// echo "<br><br><br>".$filter;
?>
  <script>
  $( function() {
    $( "#datepicker" ).datepicker({
        dateFormat: 'yy-mm-dd'
    });
  } );
  </script>
<?php

	
?>
<div class="row" >
<?php 

    print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">'."\n";
		print '<a style="float:right; margin-bottom: 8px !important;" href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
		print '<a href="./index.php?action=exl" style="float:right; margin-bottom: 9px;" name="action" id="btn_excel" class="butAction" value="exl">'.$langs->trans('Export_Excel').'</a>';
		?>

		<table id="table-1" class="noborder" style="min-width:100%;width:auto; clear: both;">
			<thead>
				<tr class="liste_titre">
				<?php 
				print_liste_field_titre($langs->trans("Ref"),$_SERVER["PHP_SELF"], "id", '', '', 'style="    text-align: center;padding:1%;width: 125px;"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("User"),$_SERVER["PHP_SELF"], "idutilisateur", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("Month"),$_SERVER["PHP_SELF"], "datec", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("montant_cnss") ,$_SERVER["PHP_SELF"], "montant_cnss", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
				print_liste_field_titre(" " ,$_SERVER["PHP_SELF"], "", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
				?>
				</tr>
				<tr class="liste_titre">
					<td style="text-align: center;" class="liste_titre" colspan="1">
						<!--<input type="text" name="search_id" value="<?php echo $search_id; ?>" id="search_id" >-->
					</td>
					<td style="text-align: center;" class="liste_titre" colspan="1">

						<?php print $form->select_users($search_idu,'search_idu',2);?>
					</td>
					<td style="text-align: center;" class="liste_titre" olspan="1">	
					<?php 
					print $form->selectarray('search_year', $cnss_utilisateur->get_part_date(), $search_year, 1, 0, 0);
					print $form->selectarray('search_month', $cnss_utilisateur->get_part_date('month',' AND year(`datec`) = '.$search_year), $search_month, 1, 0, 0);
					?>		
						</td>
					<td style="text-align: center;" class="liste_titre" olspan="1">
						<input type="number" step="0.1" value="<?php echo $search_montant_cnss; ?>" name="search_montant_cnss"/>
					</td>
					<td align="right">
<?php

	print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
	
?>
					</td>
				</tr>


			</thead>
			<tbody>
			<?php 	

					$sortfield =  $_GET['sortfield'];
					$sortorder =  $_GET['sortorder'];
					$tmontant_cnss = 0;

					
					$cnss_utilisateur->fetchAll($sortorder, $sortfield, 20, $offset, $filter);
					
					 for ($i=0; $i < count($cnss_utilisateur->rows) ; $i++) {
					 	$var = !$var;
					 	$item = $cnss_utilisateur->rows[$i];
					 	
					 	$userp->fetch($item->idutilisateur);
					?>
	    				<tr <?php print($bc[$var]) ?> >
	    				 	<td style="text-align: center;" ><?php echo $cnss_utilisateur->getNomUrl(1,  $item->id, $item->id); ?>
							<td style="text-align: center;"><?php echo $userp->getNomUrl(1); ?></td>
							<td style="text-align: center;" align="center"><?php echo date("d/m/Y", $item->datec); ?></td>
							<td style="text-align: center;" align="right"><?php echo number_format($item->montant_cnss,2); ?></td>
							<td></td>
						<?php $tmontant_cnss += $item->montant_cnss; ?>

						</tr>
			<?php } 
					print '<tr class="liste_titre" style="">';
					print '<td align="center"></td>';
					print '<td align="center"></td>';
					print '<td align="center"  style="font-weight: bold;" ><strong>TOTAL</strong></td>';
					print '<td align="center" style="font-weight: bold;">'.number_format($tmontant_cnss,2,',',' ').'</td>';
					print '<td align="center"></td>';

					print '</tr>';







			?>
			</tbody>
		</table>
	</form>
</div>


	<!--
<tr>
					<td colspan="3">
						<button name="action" class="butAction" style="border:none !important" value="add">ajouter</button>
						<button name="action" class="butAction" style="border:none !important" value="edit">Modifier</button>
						<button name="action" class="butActionBTNC butActionDelete" style="border:none !important" value="delete" onclick="return fn();"  >Supprimer</button>
					</td>
				</tr>
	-->

<?php llxFooter(); ?>