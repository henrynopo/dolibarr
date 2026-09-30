<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/grh/cnss_utilisateur/class/cnss_utilisateur.class.php');
dol_include_once('/grh/avance_utilisateur/class/avance_utilisateur.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
dol_include_once('/grh/pointage/class/pointage.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
dol_include_once('/grh/salaire_user/class/salaire_user.class.php');
// aaprint '<link rel="stylesheet" href= "'.DOL_MAIN_URL_ROOT.'/ggrh/css/theme.css">';

$langs->load('grh@grh');
$langs->load('salaries');
$langs->load('hrm');
$langs->load('companies');
global $db;
$pointage= new pointage($db);
$salaire_user= new salaire_user($db);
$usergroup=new UserGroup($db);
$var = true;
$form 			  = new Form($db);
$userp 			  = new User($db);
$formother        = new FormOther($db);
$action=GETPOST('action');
$periodyear=GETPOST('periodyear','int');
$periodmonth=GETPOST('periodmonth','int');
$groupid=GETPOST('groupid','int');
$radio      		= GETPOST('radio');
$radio2      		= GETPOST('radio2');

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$users     = "";
	$radio      = '';
	$periodyear    = "";
	$periodmonth     = "";
	$groupid     = "";
}
if (!$radio)
	$radio='both';
if (!$radio2)
	$radio2='both';

if (!$periodyear)
	$periodyear=date('Y');

if (!$periodmonth)
	$periodmonth=date('m');

if ( $action == "exl" ) {
	$filename="racap_".$periodyear."_".$periodmonth.".xls";
    require_once dol_buildpath('/grh/pointage/tpl/racap_xsl.php');
 	die();
}

if($action == "update_salbas"){
	$salaire_user->updateSalarybase($periodmonth, $periodyear);
	header('Location: ./racapsp.php');
}

llxHeader('', $langs->trans('Gestion_de_Paie'));
print_fiche_titre($langs->trans('recapsp').' '.$periodmonth.'/'.$periodyear);

// -------------------------------------------------------------------------------------------

print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">'."\n";
	print '<input  type="hidden" name="action" value="exl">';
	print '<input type="hidden" name="periodyear" value="'.$periodyear.'">';
	print '<input type="hidden" name="periodmonth" value="'.$periodmonth.'">';
	print '<input type="hidden" name="radio" value="'.$radio.'">';
	print '<input type="hidden" name="radio2" value="'.$radio2.'">';
	print '<input type="hidden" name="groupid" value="'.$groupid.'">';	
	print '<input type="submit" name="submit" class="butAction" value="'.$langs->trans('Export_Excel').'" style="margin-bottom: 8px;">';
print '</form>'."\n";

// -------------------------------------------------------------------------------------------

print '<form name="selectperiod" method="POST" style="" action="'.$_SERVER["PHP_SELF"].'">';

print '<table style="float: left;margin-bottom: 5px;"  width="100%">';
print '<tr >';
// print '<td>Choisir l\'année et le mois:</td>';
print '<td></td>';
print '<td>'.$formother->selectyear($periodyear,'periodyear').$formother->select_month($periodmonth,'periodmonth',0,0,'maxwidth70imp').'</td>';
print '<td> '.$langs->trans('Service').':'. $form->select_dolgroups($groupid, 'groupid', 1, '', 0, '', '', 1).'</td>';
print '<td>';

$checkedb ='';
$checkedd ='';
$checkedn ='';

if (isset($radio2) ) {
	if($radio2=='declar' )
		$checkedd ='checked="checked"';
	elseif($radio2=='nodeclar')
		$checkedn ='checked="checked"';
	else
		$checkedb ='checked="checked"';
}

print '<label><input type="radio" '.$checkedb.' name="radio2" value="both">'.$langs->trans("All").'</label><label><input type="radio" '.$checkedd.' name="radio2" value="declar">'.$langs->trans("Declarés").'</label>
 <label><input type="radio" '.$checkedn.' name="radio2" value="nodeclar">'.$langs->trans("Non_Declarés").'</label>';

if($radio2=='declar' ){
	if (isset($radio) ) {
		$checkedb ='';
		$checkedd ='';
		$checkedn ='';
		if($radio=='prime')
			$checkedd ='checked="checked"';
		elseif($radio=='noprime')
			$checkedn ='checked="checked"';
		else
			$checkedb ='checked="checked"';
	}
	print '<br/><label><input type="radio" '.$checkedb.' name="radio" value="both">'.$langs->trans("All").'</label><label><input type="radio" '.$checkedd.' name="radio" value="prime">'.$langs->trans("Avec_prime").'</label>
	<label><input type="radio" '.$checkedn.' name="radio" value="noprime">'.$langs->trans("Sans_prime").'</label>';

}else{
	$radio ='both';
}

print '</td>';
print '<td ><input class="butAction" style="margin-right: 8px;" type=submit name="select" value="'.$langs->trans("Search").'"><div style="float:right;margin: 2px;"><input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"></div>';
print '</td>';

print'</tr></table></form>';

// Table Totals --------------------------------------------------------

print '<div class="row">';
print '<table id="table-2" class="noborder" style="min-width:100%;width:auto; clear: both;">';
	print '<thead>';
		print '<tr class="liste_titre">';
			if($radio!='prime'){
				print '<td align="center">'.$langs->trans("Total_Nbres_H_").'</td>';
				print '<td align="center">'.$langs->trans("Total_Sal_Base").'</td>';
			}

			if($radio!='noprime')
			print '<td align="center">'.$langs->trans("Total_P__et_Ind").'</td>';

			if($radio!='prime'){
				print '<td align="center">'.$langs->trans("Total_Sal_Brut").'</td>';
				print '<td align="center">'.$langs->trans("Total_CNSS").'</td>';
				print '<td align="center">'.$langs->trans("Total_Avance").'</td>';
				print '<td align="center">'.$langs->trans("Somme_de_Total").'</td>';
				print '<td align="center">'.$langs->trans("Total_Net_à_Payer").'</td>';
			}
		print '</tr>';
	print '</thead>';


	$all_users['salary'] = $pointage->getUsersWithS();
	$all_users['nosalary'] = $pointage->getUsersWithS(false);

	if($salaire_user->Check_exist_SU($periodmonth,$periodyear)){
	 	$all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
		$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);
	}

	$avance_utilisateur = new avance_utilisateur($db);
	$cnss_utilisateur = new cnss_utilisateur($db);
	$tnbrh =0;
	$tsalary = 0;
	$tind = 0;
	$tsalary_base = 0;
	$tcnss = 0;
	$tavance = 0;
	$ttotal = 0;
	$tnetap = 0;

	$all_users['salary'] = $pointage->getUsersWithS();
$all_users['nosalary'] = $pointage->getUsersWithS(false);

// if($salaire_user->Check_exist_SU($periodmonth,$periodyear)){ 
// 	$all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
// 	$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);
// }

$avance_utilisateur = new avance_utilisateur($db);
$cnss_utilisateur 	= new cnss_utilisateur($db);

$inc_tr = 0;

$tblhtml = '';

foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {
		$group_array = array();
		$userp->fetch($key);
		$user_arr = $pointage->nc_getUserInfo($key);
		if (isset($radio2) && !empty($radio2)) {
			if($radio2=='declar' && $userp->array_options['options_nx_is_declared']==0 )
				continue;
			elseif($radio2=='nodeclar' && $userp->array_options['options_nx_is_declared']==1)
				continue;
		}

		$getthm = $salaire_user->getThm($periodmonth,$periodyear,$key);
		$slbase = $salaire_user->getSalarybase($periodmonth,$periodyear,$key);
		$slaryuse = $salaire_user->getSalary($periodmonth,$periodyear,$key);

	 	$groupslist = $usergroup->listGroupsForUser($key);
	 	foreach ($groupslist as $group) {
			$group_array[]=$group->id;
		}
		if(!empty($groupid) && $groupid!='-1' && !in_array($groupid, $group_array))
			continue;

		$var = !$var;
		$cnss = $cnss_utilisateur->get_cnss_month($periodyear,$periodmonth,$key);
		$avance = $avance_utilisateur->get_avance_month($periodyear,$periodmonth,$key);

		if ($inc_tr == 20) {
			// $tblhtml .= '<tr class="liste_titre">';
			// 	$tblhtml .= '<td align="center">Noms</td>';

			// 	if($radio!='prime'){
			// 		$tblhtml .= '<td align="center">Nbres H.</td>';
			// 		$tblhtml .= '<td align="center">Taux.H</td>';
			// 		$tblhtml .= '<td align="center">Sal.Base</td>';
			// 	}

			// 	if($radio!='noprime')
			// 		$tblhtml .= '<td align="center">P. et Ind</td>';

			// 	if($radio!='prime'){
			// 		$tblhtml .= '<td align="center">Sal.Brut</td>';
			// 		$tblhtml .= '<td align="center">CNSS</td>';
			// 		$tblhtml .= '<td align="center">Avance</td>';
			// 		$tblhtml .= '<td align="center">Total</td>';
			// 		$tblhtml .= '<td align="center">Net à Payer</td>';
			// 	}
			// 	$tblhtml .= '<td align="center">'.$langs->trans('Service').'</td>';

			// $tblhtml .= '</tr>';
			$tblhtml .= '<tr class="liste_titre">';
				$tblhtml .= '<td align="center">'.$langs->trans('Noms').'</td>';


				if($radio!='prime'){
					$tblhtml .= '<td align="center">'.$langs->trans('Nbres_H_').'</td>';
					$tblhtml .= '<td align="center">'.$langs->trans('Taux_H').'</td>';
					$tblhtml .= '<td align="center">'.$langs->trans('Sal_Base').'</td>';
				}

				if($radio!='noprime')
					$tblhtml .= '<td align="center">'.$langs->trans('P_et_Ind').'</td>';

				if($radio!='prime'){
					$tblhtml .= '<td align="center">'.$langs->trans('Sal_Brut').'</td>';
					$tblhtml .= '<td align="center">'.$langs->trans('CNSS').'</td>';
					$tblhtml .= '<td align="center">'.$langs->trans('Avance').'</td>';
					$tblhtml .= '<td align="center">'.$langs->trans('Total').'</td>';
					$tblhtml .= '<td align="center">'.$langs->trans('Net_à_Payer').'</td>';
				}
				$tblhtml .= '<td align="center">'.$langs->trans('Service').'</td>';

			$tblhtml .= '</tr>';
			$inc_tr = 1;
		}
		if($index == 'salary'){
			$tblhtml .= '<tr '.$bc[$var].'>';
				$tblhtml .= '<td align="left" class="salary_user">'.$userp->getNomUrl(1).'</td>';

				$ind 	= 0;
				$salary = 0;

				if($slaryuse)
					$salary = $slaryuse;

				if($slbase){
					$ind = $salary - $slbase;
					$tsalary_base += $slbase;
				}

				if($radio!='prime'){



					$nbr = $pointage->getValByMonth($periodyear,$periodmonth,$key);
					$thm = 0;
					if($getthm > 0){
						$thm = $getthm;
						$tblhtml .= '<td align="center">'.$nbr.'</td>';
						$tblhtml .= '<td align="center">'.number_format($thm,2,',',' ').'</td>';
						$salary = $salary + ($thm * $nbr);
						$ind = $salary - $slbase;
					}else{
						$tblhtml .= '<td class="empty"></td>';
						$tblhtml .= '<td class="empty"></td>';
					}


					// $tblhtml .= '<td></td>';
					// $tblhtml .= '<td></td>';

					// Sal.Base
					$tblhtml .= '<td align="center">'.number_format($slbase,2,',',' ').'</td>';
				}

				// $ind 	= 0;
				// $salary = 0;

				// if($slaryuse)
				// 	$salary = $slaryuse;

				// if($slbase)
				// 	$ind = $salary - $slbase;

				if($radio!='noprime'){
					// P. et Ind
					$tblhtml .= '<td align="center">'.number_format($ind,2,',',' ').'</td>';
				}else{
					$salary = $slbase;
				}

				$tind += $ind;
				
				if($radio!='prime'){
					// Sal.Brut
					$tblhtml .= '<td align="center">'.number_format($salary,2,',',' ').'</td>';
					// CNSS depuis CNSS des salariés
					$tblhtml .= '<td align="center"> '.number_format($cnss,2,',',' ').'</td>';
					// Avance depuis Les avances salariés
					$tblhtml .= '<td align="center">'.number_format($avance,2,',',' ').'</td>';
					$total = $cnss+$avance;
					$tblhtml .= '<td align="center">'.$total.'</td>';
					$netap = $salary - $total;
					$tblhtml .= '<td align="center">'.number_format($netap,2,',',' ').'</td>';
				}

				$tblhtml .= '<td align="center">';  
				$numItems_ = count($groupslist);
				$t = 0;
	            foreach ($groupslist as $group) {
	            	$virgule = ",";
	            	if(++$t === $numItems_) {
	            		$virgule = "";
				  	}
	            	$tblhtml .= $group->name.$virgule." ";
	            }
	         	$tblhtml .= '</td>';
			$tblhtml .= '</tr>';
		}else{

			$nbr = $pointage->getValByMonth($periodyear,$periodmonth,$key);

			if($nbr==0)
				continue;


			$tblhtml .= '<tr '.$bc[$var].'>';
				$tblhtml .= '<td align="left" class="non_salary_user">'.$userp->getNomUrl(1).'</td>';
				if($radio!='prime'){
					// Nbres H.
					$tblhtml .= '<td align="center">'.$nbr.'</td>';
					// Taux.H
					$tblhtml .= '<td align="center">'.number_format($getthm,2,',',' ').'</td>';
					// Sal.Base
					$tblhtml .= '<td align="center">'.number_format($slbase,2,',',' ').'</td>';
				}

				$thm = 0;
				if($getthm)
					$thm = $getthm;

				$salary = $nbr * $thm;
				$ind = $salary;

				// P. et Ind = (Nbres H. * Taux.H) - Sal.Base
				
				if($slbase){
					$ind = $salary - $slbase;
					$tsalary_base += $slbase;
				}

				$tind += $ind;

				if($radio!='noprime'){
					// P. et Ind
					// P. et Ind = Sal.Brut - Sal.Base
					$tblhtml .= '<td align="center" >'.number_format($ind,2,',',' ').'</td>';
				}else{
					$salary = $slbase;
				}

				if($radio!='prime'){
					// Sal.Brut
					$tblhtml .= '<td align="center">'.number_format($salary,2,',',' ').'</td>';
					$tblhtml .= '<td align="center">'.number_format($cnss,2,',',' ').'</td>';
					$tblhtml .= '<td align="center">'.number_format($avance,2,',',' ').'</td>';

					$total = $cnss + $avance;
					$tblhtml .= '<td align="center">'.$total.'</td>';

					$netap = $salary - $total;
					$tblhtml .= '<td align="center">'.number_format($netap,2,',',' ').'</td>';
				}
				$tblhtml .= '<td align="center">';
				$numItems_ = count($groupslist);
				$t = 0;
	            foreach ($groupslist as $group) {
	            	$virgule = ",";
	            	if(++$t === $numItems_) {
	            		$virgule = "";
				  	}
	            	$tblhtml .= $group->name.$virgule." ";
	            }
	         	$tblhtml .= '</td>';
			$tblhtml .= '</tr>';
		}

		$tnbrh += $nbr; 


		$tsalary += $salary;
		$tcnss += $cnss;
		$tavance += $avance;
		$total = $cnss +$avance;
		$ttotal += $total;
		$netap = $salary - $total;
		$tnetap+= $netap;

		$inc_tr++;
	}
}
	

?>
<style>
	form[name="selectperiod"] td label {
	    margin: 0 7px;
	}
	.BgTrColg {background: #ffff84 none repeat scroll 0px 0px;}
	.BgTrColg .BgtdColg {border-right: 1px solid #e6e6e6;}
	.guide_salariegrh{
		/*font-family: roboto,Open Sans !important;*/
		font-size: 12px;
	}
	.guide_salariegrh>div{
		float: left;
		padding: 10px;
	}
	.guide_salariegrh span{
		width: 11px;
	    height: 11px;
	    border-radius: 50%;
	    display: inline-block;
	}
	.guide_salariegrh .non_salarie_ span{
		background: #3598DC;
	}
	.guide_salariegrh .salarie_ span{
		background: #E7505A;
	}
	.guide_salariegrh .salarie_ {
		margin-right: 20px;
	}
	td.salary_user * {
    	color: #E7505A;
	}
	td.non_salary_user * {
	    color: #3598DC;
	}
</style>
<?php
print '<tr class="BgTrColg">';
	if($radio!='prime'){
		print '<td class="BgtdColg" align="center">'.$tnbrh.'</td>';
		print '<td class="BgtdColg" align="center">'.number_format($tsalary_base,2,',',' ').'</td>';
	}
	if($radio!='noprime'){
		print '<td class="BgtdColg" align="center">'.number_format($tind,2,',',' ').'</td>';
	}else{
		$tsalary = $tsalary_base;
		$tnetap= $tsalary - $ttotal;
	}

	if($radio!='prime'){
		print '<td class="BgtdColg" align="center">'.number_format($tsalary,2,',',' ').'</td>';
		print '<td class="BgtdColg" align="center"> '.number_format($tcnss,2,',',' ').'</td>';
		print '<td class="BgtdColg" align="center">'.number_format($tavance,2,',',' ').'</td>';
		print '<td class="BgtdColg" align="center">'.number_format($ttotal,2,',',' ').'</td>';
		print '<td align="center">'.number_format($tnetap,2,',',' ').'</td>';
	}
print '</tr>';
print '</tbody></table>';
print '</div>';

// END Table Totals --------------------------------------------------------------------------

// table contenu --------------------------------------------------------------------------
print '<div class="guide_salariegrh">
	<div class="salarie_">
		<span class="bg_red"></span> 
		'.$langs->trans("Employees").' ('.$langs->trans("Salary").' > '.price(0,0,$langs,1,-1,-1,$conf->currency).')
	</div>
	<div class="non_salarie_">
		<span class="bg_blue"></span> 
		'.$langs->trans("No").' '.$langs->trans("Employees").'
	</div>
	<div class="update_salbas"><a href="racapsp.php?action=update_salbas&periodyear='.$periodyear.'&periodmonth='.$periodmonth.'" class="butAction">'.$langs->trans("update_salbas").'</a></div>
</div>';
print '<table id="table-1" class="noborder" style="min-width:100%;width:auto; clear: both;">';
print '<thead>';
print '<tr class="liste_titre">';
	print '<td align="center">'.$langs->trans('Noms').'</td>';


	if($radio!='prime'){
		print '<td align="center">'.$langs->trans('Nbres_H_').'</td>';
		print '<td align="center">'.$langs->trans('Taux_H').'</td>';
		print '<td align="center">'.$langs->trans('Sal_Base').'</td>';
	}

	if($radio!='noprime')
		print '<td align="center">'.$langs->trans('P_et_Ind').'</td>';

	if($radio!='prime'){
		print '<td align="center">'.$langs->trans('Sal_Brut').'</td>';
		print '<td align="center">'.$langs->trans('CNSS').'</td>';
		print '<td align="center">'.$langs->trans('Avance').'</td>';
		print '<td align="center">'.$langs->trans('Total').'</td>';
		print '<td align="center">'.$langs->trans('Net_à_Payer').'</td>';
	}
	print '<td align="center">'.$langs->trans('Service').'</td>';

print '</tr>';
print '</thead>';


print $tblhtml;


print '</tbody></table>';

// END table contenu----------------------------------------------------------------------------

print '</div>';
llxFooter(); 

?>