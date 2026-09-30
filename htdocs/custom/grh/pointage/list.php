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
$radio      		= GETPOST('radio');

if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter")) {
	$users     = "";
	$radio      = '';
	$periodyear    = "";
	$periodmonth     = "";
}

if (!$periodyear)
	$periodyear=date('Y');

if (!$radio)
	$radio='both';

if (!$periodmonth)
	$periodmonth=date('m');

if ( $action == "exl" ) {


$filename="racap_".$periodyear."_".$periodmonth.".xls";
      require_once dol_buildpath('/grh/pointage/tpl/racap_service_xsl.php');
 die();
 
}

llxHeader('', $langs->trans('recaps'));
print_fiche_titre($langs->trans('recaps').' '.$periodmonth.'/'.$periodyear);

print '<form style="float: left;" name="selectperiod" method="POST" action="'.$_SERVER["PHP_SELF"].'">';

print '<table style="float: left;"  width="100%">';
print '<tr >';
// print '<td>Choisir l\'année et le mois:</td>';
print '<td></td>';
print '<td>'.$formother->selectyear($periodyear,'periodyear').$formother->select_month($periodmonth,'periodmonth').'</td>';
print '<td>';
$checkedb ='';
$checkedd ='';
$checkedn ='';
if (isset($radio) ) {
    			if($radio=='declar' )
    				$checkedd ='checked="checked"';
    			elseif($radio=='nodeclar')
    				$checkedn ='checked="checked"';
    			else
					$checkedb ='checked="checked"';
    	}
print '<label><input type="radio" '.$checkedb.' name="radio" value="both"> '.$langs->trans("All").' </label><label><input type="radio" '.$checkedd.' name="radio" value="declar"> '.$langs->trans("Déclarés").' </label><label><input type="radio" '.$checkedn.' name="radio" value="nodeclar"> '.$langs->trans("Non_Declarés").' </label>';
print '</td>';
print '<td><input class="butAction" style="margin-right:6px;" type=submit name="select" value="'.$langs->trans("Show").'"><div style="float: right;"><input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans("Search"),'searchclear.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'"></div>';
print '</td>';

print'</tr></table></form>';

 print '<div style="float: right;">';
 print '<form method="post" action="'.$_SERVER["PHP_SELF"].'" style="    background: transparent">'."\n";
 print '<input  type="hidden" name="action" value="exl">';
 print '<input type="hidden" name="periodyear" value="'.$periodyear.'">';
 print '<input type="hidden" name="periodmonth" value="'.$periodmonth.'">';
 print '<input type="hidden" name="radio" value="'.$radio.'">';
 print '<input type="submit" name="submit" class="butAction" value="'.$langs->trans("Export_Excel").'">';
	  
 print '</form>'."\n";
 print '</div>';


// TABLE
if (!empty($periodyear) && !empty($periodmonth) ){


$service_salary = array();
$service_prime = array();
$service_salary_base = array();
$groups = array();
 $all_users['salary'] = $pointage->getUsersWithS();
 $all_users['nosalary'] = $pointage->getUsersWithS(false);
 if($salaire_user->Check_exist_SU($periodmonth,$periodyear))
 	{ $all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
	$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);}
$avance_utilisateur = new avance_utilisateur($db);
$cnss_utilisateur = new cnss_utilisateur($db);

// print_r($all_users);
foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {


	$userp->fetch($key);
	$user_arr = $pointage->nc_getUserInfo($key);
 	$groupslist = $usergroup->listGroupsForUser($key);
	 
	$nbr = $pointage->getValByMonth($periodyear,$periodmonth,$key);
	if(!$nbr && $index=='nosalary')
		continue;

	$var = !$var;
	$cnss = $cnss_utilisateur->get_cnss_month($periodyear,$periodmonth,$key);
	$avance = $avance_utilisateur->get_avance_month($periodyear,$periodmonth,$key);
	$salary_base = $salaire_user->getSalarybase($periodmonth,$periodyear,$key);
	$salary = 0;
	if($salaire_user->getSalary($periodmonth,$periodyear,$key))
	$salary = $salaire_user->getSalary($periodmonth,$periodyear,$key);
	elseif($salaire_user->getThm($periodmonth,$periodyear,$key)){
		$salary_base = $nbr*$salaire_user->getThm($periodmonth,$periodyear,$key);
		$salary = $salary_base + ($salary_base-$userp->array_options['options_nx_salaire_base']);
	}
		
	$prime = $salary-$salary_base;
	if($userp->array_options['options_nx_is_declared'])
	{	foreach ($groupslist as $group){
		if(!array_key_exists($group->id, $groups))
			$groups[$group->id] =$group->id;
		if(array_key_exists('declar_'.$group->id, $service_salary_base)){
			$service_salary_base['declar_'.$group->id] +=$salary_base;
			$service_salary['declar_'.$group->id] +=$salary;
			$service_prime['declar_'.$group->id] +=$prime;
		}
		else{
			$service_salary_base['declar_'.$group->id] =$salary_base;
			$service_salary['declar_'.$group->id] =$salary;
			$service_prime['declar_'.$group->id] =$prime;
		}
		}
	}
	else
	{	
		foreach ($groupslist as $group){
			if(!array_key_exists($group->id, $groups))
			$groups[$group->id] =$group->id;
		if(array_key_exists('nodeclar_'.$group->id, $service_salary_base)){
			$service_salary_base['nodeclar_'.$group->id] +=$salary_base;
			$service_salary['nodeclar_'.$group->id] +=$salary;
			$service_prime['nodeclar_'.$group->id] +=$prime;
		}
		else{
			$service_salary_base['nodeclar_'.$group->id] =$salary_base;
			$service_salary['nodeclar_'.$group->id] =$salary;
			$service_prime['nodeclar_'.$group->id] =$prime;
		}
		}
	}	
	
	
    	}
}
		
$total_general = 0;
if (isset($radio) && $radio=='declar' || isset($radio) && $radio=='both'){
print '<table id="declar" class="noborder" style="min-width:100%;width:auto; clear: both;">';
print '<thead>';
print '<tr class="liste_titre">';
print '<td align="center">'.$langs->trans('Services_déclarés_à_la_CNSS').'</td>';
print '<td align="center">'.$langs->trans('Salaires_déclaries').'</td>';
print '<td align="center">'.$langs->trans('Primes').'</td>';
print '<td align="center">Total</td>';


print '</tr></thead>';
$total_s_declar = 0;
$total_b_declar = 0;
$total_p_declar = 0;
$var = true;
foreach ($groups as $group) {
	if(array_key_exists('declar_'.$group, $service_salary_base)){
		$var = !$var;
		$usergroup->fetch($group);
		print '<tr '.$bc[$var].'>';
		print '<td align="left">'.$usergroup->name.'</td>';
		print '<td align="center">'.number_format($service_salary_base['declar_'.$group],2,',',' ').'</td>';
		print '<td align="center">'.number_format($service_prime['declar_'.$group],2,',',' ').'</td>';
		print '<td align="center">'.number_format($service_salary['declar_'.$group],2,',',' ').'</td>';
		$total_s_declar += $service_salary['declar_'.$group];
		$total_b_declar += $service_salary_base['declar_'.$group];
		$total_p_declar += $service_prime['declar_'.$group];
		print '</tr>';
	}
}
print '<tr class="liste_titre">';
print '<td align="center">Totaux</td>';
print '<td align="center">'.number_format($total_b_declar,2,',',' ').'</td>';
print '<td align="center">'.number_format($total_p_declar,2,',',' ').'</td>';
print '<td align="center">'.number_format($total_s_declar,2,',',' ').'</td>';
$total_general += $total_s_declar;

print '</tr>';

print '</tbody></table>';
}
if (isset($radio) && $radio=='nodeclar' || isset($radio) && $radio=='both'){
{
print '<table id="nodeclar" class="noborder" style="min-width:100%;width:auto; clear: both;">';
print '<thead>';
print '<tr class="liste_titre">';
print '<td align="center">'.$langs->trans('Services_non_déclarés_à_la_CNSS').'</td>';
print '<td align="center">'.$langs->trans('Salaires_non_déclaries').'</td>';
print '<td align="center">'.$langs->trans('Primes').'</td>';
print '<td align="center">Total</td>';


print '</tr></thead>';
$total_s_declar = 0;
$total_b_declar = 0;
$total_p_declar = 0;
$var = true;
foreach ($groups as $group) {
	if(array_key_exists('nodeclar_'.$group, $service_salary_base)){
		$var = !$var;
		$usergroup->fetch($group);
		print '<tr '.$bc[$var].'>';
		print '<td align="left">'.$usergroup->name.'</td>';
		print '<td align="center">'.number_format($service_salary_base['nodeclar_'.$group],2,',',' ').'</td>';
		print '<td align="center">'.number_format($service_prime['nodeclar_'.$group],2,',',' ').'</td>';
		print '<td align="center">'.number_format($service_salary['nodeclar_'.$group],2,',',' ').'</td>';
		$total_s_declar += $service_salary['nodeclar_'.$group];
		$total_b_declar += $service_salary_base['nodeclar_'.$group];
		$total_p_declar += $service_prime['nodeclar_'.$group];
		print '</tr>';
	}
}

print '<tr class="liste_titre">';
print '<td align="center">Totaux</td>';
print '<td align="center">'.number_format($total_b_declar,2,',',' ').'</td>';
print '<td align="center">'.number_format($total_p_declar,2,',',' ').'</td>';
print '<td align="center">'.number_format($total_s_declar,2,',',' ').'</td>';
$total_general += $total_s_declar;

print '</tr>';
print '</tbody></table>';

}
print '<table id="total" class="noborder" style="min-width:100%;width:auto; clear: both;">';

print '<tr class="liste_titre">';
print '<td align="center" colspan="3"><strong>TOTAL</strong></td>';

print '<td align="center"><strong>'.number_format($total_general,2,',',' ').'</strong></td>';

print '</tr>';

print '</tbody></table></div>';
}
}


 llxFooter(); 

?>