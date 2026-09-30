<?php
$output.= '<meta charset="utf-8" />';
$output.= '<h1 align="center"> Racap Globale '.$periodmonth.'/'.$periodyear.'</h1>';


// TABLE

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


foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {

$var = !$var;

	$userp->fetch($key);
	$user_arr = $pointage->nc_getUserInfo($key);
 	$groupslist = $usergroup->listGroupsForUser($key);
	 
	$nbr = $pointage->getValByMonth($periodyear,$periodmonth,$key);
	if(!$nbr && $index=='nosalary')
		continue;
	$cnss = $cnss_utilisateur->get_cnss_month($periodyear,$periodmonth,$key);
	$avance = $avance_utilisateur->get_avance_month($periodyear,$periodmonth,$key);
	$salary_base = $salaire_user->getSalarybase($periodmonth,$periodyear,$key);
	$salary = 0;
	if($salaire_user->getSalary($periodmonth,$periodyear,$key))
	$salary = $salaire_user->getSalary($periodmonth,$periodyear,$key);
	elseif($salaire_user->getThm($periodmonth,$periodyear,$key)){
		$salary_base = $nbr*$salaire_user->getThm($periodmonth,$periodyear,$key);
		$salary = $salary_base + ($salary_base-$user_arr->salary_base);
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
$output.= '<table border="1" width="100%">';
$output.= '<thead>';
$output.= '<tr class="liste_titre">';
$output.= '<td align="center"><strong>Services '.$langs->trans('declares').' à la CNSS</strong></td>';
$output.= '<td align="center"><strong>Salaires '.$langs->trans('declares').'</strong></td>';
$output.= '<td align="center"><strong>Primes</strong></td>';
$output.= '<td align="center"><strong>Total</strong></td>';


$output.= '</tr></thead>';
$total_s_declar = 0;
$total_b_declar = 0;
$total_p_declar = 0;
$var = false;
foreach ($groups as $group) {
	$var = !$var;
	if(array_key_exists('declar_'.$group, $service_salary_base)){
		$usergroup->fetch($group);
		$output.= '<tr '.$bc[$var].'>';
		$output.= '<td align="left">'.$usergroup->name.'</td>';
		$output.= '<td align="center">'.number_format($service_salary_base['declar_'.$group],2,',',' ').'</td>';
		$output.= '<td align="center">'.number_format($service_prime['declar_'.$group],2,',',' ').'</td>';
		$output.= '<td align="center">'.number_format($service_salary['declar_'.$group],2,',',' ').'</td>';
		$total_s_declar += $service_salary['declar_'.$group];
		$total_b_declar += $service_salary_base['declar_'.$group];
		$total_p_declar += $service_prime['declar_'.$group];
		$output.= '</tr>';
	}
}

$output.= '<tr class="liste_titre">';
$output.= '<td align="center"><strong>Totaux</strong></td>';
$output.= '<td align="center">'.number_format($total_b_declar,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($total_p_declar,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($total_s_declar,2,',',' ').'</td>';
$total_general += $total_s_declar;
$output.= '</tr>';

$output.= '</tbody></table><br/>';
}
if (isset($radio) && $radio=='nodeclar' || isset($radio) && $radio=='both'){
{
$output.= '<table border="1" width="100%">';
$output.= '<thead>';
$output.= '<tr class="liste_titre">';
$output.= '<td align="center"><strong>Services non '.$langs->trans('declares').' à la CNSS</strong></td>';
$output.= '<td align="center"><strong>Salaires non '.$langs->trans('declares').'</strong></td>';
$output.= '<td align="center"><strong>Primes</strong></td>';
$output.= '<td align="center"><strong>Total</strong></td>';


$output.= '</tr></thead>';
$total_s_declar = 0;
$total_b_declar = 0;
$total_p_declar = 0;
$var = false;
foreach ($groups as $group) {
	$var = !$var;
	if(array_key_exists('nodeclar_'.$group, $service_salary_base)){
		$usergroup->fetch($group);
		$output.= '<tr '.$bc[$var].'>';
		$output.= '<td align="left">'.htmlentities($usergroup->name).'</td>';
		$output.= '<td align="center">'.number_format($service_salary_base['nodeclar_'.$group],2,',',' ').'</td>';
		$output.= '<td align="center">'.number_format($service_prime['nodeclar_'.$group],2,',',' ').'</td>';
		$output.= '<td align="center">'.number_format($service_salary['nodeclar_'.$group],2,',',' ').'</td>';
		$total_s_declar += $service_salary['nodeclar_'.$group];
		$total_b_declar += $service_salary_base['nodeclar_'.$group];
		$total_p_declar += $service_prime['nodeclar_'.$group];
		$output.= '</tr>';
	}
}

$output.= '<tr class="liste_titre">';
$output.= '<td align="center"><strong>Totaux</strong></td>';
$output.= '<td align="center">'.number_format($total_b_declar,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($total_p_declar,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($total_s_declar,2,',',' ').'</td>';
$output.= '</tr>';
$total_general += $total_s_declar;
$output.= '</tbody></table>';
}

}
$output.= '<table border="1" width="100%">';
$output.= '<tr class="liste_titre">';
$output.= '<td align="center" colspan="3"><strong>TOTAL GENERAL</strong></td>';

$output.= '<td align="center"><strong>'.number_format($total_general,2,',',' ').'</strong></td>';

$output.= '</tr>';

$output.= '</tbody></table></div>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>