<?php



$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/avance_utilisateur/class/avance_utilisateur.class.php');

$var = true;
$avance_utilisateur = new avance_utilisateur($db);
$form 		= new Form($db);
$userp = new User($db);

$search_id 	  = GETPOST('search_id','int');
$search_idu 	  = GETPOST('search_idu','int');
$search_montant    = GETPOST('search_montant');
$search_year     = GETPOST('search_year');
$search_month     = GETPOST('search_month');

$action   = GETPOST('action','alpha');


/*-------------XSL----------------*/
if ( $action == "exl" ) {

$filename="avance_".$search_year."_".$search_month.".xls";
      require_once dol_buildpath('/grh/avance_utilisateur/tpl/avance_xsl.php');
 die();
 
}
/*-------------XSL----------------*/


if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
$search_id      		= '';
$search_idu      		= '';
$search_montant      	= '';
$search_year			= '';
$search_month    		= '';
}

// $search_year = (empty($search_year) || $search_year == -1) ? date("Y",strtotime("now"))  : $search_year ;
// $search_month = (empty($search_month) || $search_month == -1) ? date("m",strtotime("now"))  : $search_month ;

					
$filter .= (!empty($search_id) && $search_id != -1) ? " AND id = ". $db->escape($search_id)."\n" : "";
$filter .= (!empty($search_idu) && $search_idu != -1) ? " AND idavance_utilisateur = ". $db->escape($search_idu)."\n" : "";
$filter .= (!empty($search_montant) && $search_montant != -1) ? " AND cast(montant as decimal(5,1)) = '".str_replace(",", ".", $db->escape($search_montant))."'":"";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) = ".$db->escape($search_year) : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) = ".$db->escape($search_month) : "";


/*

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
if (! $sortfield) $sortfield = "id";
if (! $sortorder) $sortorder = "DESC";

*/

//llxHeader('', $langs->trans('avance_utilisateurs'));			// Header
//add files ( js , css , ..... ) to header
$morejs  = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.js", "/grh/js/jquery/timepicker/timepicker-fr.js","/grh/js/avance_utilisateur.js");
$morecss = array("/includes/jquery/plugins/timepicker/jquery-ui-timepicker-addon.css");
llxHeader(array(), $langs->trans('avance_utilisateur'),'','','','',$morejs,$morecss,0,0);
print_fiche_titre($langs->trans('Les_avances_salariés'));	// TITLE
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
		print '<a style="float:right;margin-bottom: 8px !important;" href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a>';
		// print '<button style="float:right; margin-bottom: 9px;" name="action" class="butAction" value="exl">'.$langs->trans('Export Excel').'</button>';
		print '<a href="./index.php?action=exl" style="float:right; margin-bottom: 9px;" name="action" id="btn_excel" class="butAction" value="exl">'.$langs->trans('Export Excel').'</a>';

		
		?>

		<table id="table-1" class="noborder" style="min-width:100%;width:auto; clear: both;">
			<thead>
				<tr class="liste_titre">
				<?php 
				print_liste_field_titre($langs->trans("Ref") ,$_SERVER["PHP_SELF"], "id", '', '', 'style="    text-align: center;padding:1%;width: 125px;"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("User") ,$_SERVER["PHP_SELF"], "idavance_utilisateur", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("date") ,$_SERVER["PHP_SELF"], "datec", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
				print_liste_field_titre($langs->trans("Amount") ,$_SERVER["PHP_SELF"], "montant", '', '', 'style="    text-align: center;"', $sortfield, $sortorder);
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
					<td style="text-align: center;" class="liste_titre" colspan="1">
					<?php 
					print $form->selectarray('search_year', $avance_utilisateur->get_part_date(), $search_year, 1, 0, 0);
					print $form->selectarray('search_month', $avance_utilisateur->get_part_date('month','AND year(`datec`) = '.$search_year), $search_month, 1, 0, 0);
					?> <!--<input type="date" value="<?php echo $search_date; ?>" id="datepicker" name="search_date"/>-->
					</td>
					<td style="text-align: center;" class="liste_titre" colspan="1">
						<input type="number" step="0.1" value="<?php echo $search_montant; ?>" name="search_montant"/>
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
					$tmontant = 0;
					
					$avance_utilisateur->fetchAll($sortorder, $sortfield, 20, $offset, $filter);
					
					 for ($i=0; $i < count($avance_utilisateur->rows) ; $i++) {
					 	$var = !$var;
					 	$item = $avance_utilisateur->rows[$i];
					 	
					 	$userp->fetch($item->idavance_utilisateur);
					?>
	    				<tr <?php print($bc[$var]) ?> >
	    				<td style="text-align: center;" ><?php echo $avance_utilisateur->getNomUrl(1,  $item->id, $item->id); ?>
							<td style="text-align: center;"><?php echo $userp->getNomUrl(1); ?></td>
							<td style="text-align: center;" align="center"><?php echo dol_print_date($item->datec,'day'); ?></td>
							<td style="text-align: center;" align="right"><?php echo number_format($item->montant,2); ?></td>
							<td></td>
						<?php $tmontant += $item->montant; ?>

						</tr>
			<?php } 
	
					print '<tr class="liste_titre">';
					print '<td align="center"></td>';
					print '<td align="center"></td>';
					print '<td align="center"  style="font-weight: bold;" ><strong>TOTAL</strong></td>';
					print '<td align="center" style="font-weight: bold;">'.number_format($tmontant,2,',',' ').'</td>';
					print '<td align="center"></td>';

					print '</tr>';
			?>

			</tbody>
		</table>
	</form>
</div>


<?php llxFooter(); ?>