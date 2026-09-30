<?php

$output.= '<h1 align="center"> Gestion de Paie '.$periodmonth.'/'.$periodyear.'</h1>';


// TABLE

$output.= '<table  border="1" >';
$output.= '<thead>';
$output.= '<tr class="liste_titre">';
if($radio!='prime'){
$output.= '<td align="center"><strong>Total Nbres H.</strong></td>';
$output.= '<td align="center"><strong>Total Sal.Base</strong></td>';}
if($radio!='noprime')
$output.= '<td align="center"><strong>Total P. et Ind</strong></td>';
if($radio!='prime'){
$output.= '<td align="center"><strong>Total Sal.Brut</strong></td>';
$output.= '<td align="center"><strong>Total CNSS</strong></td>';
$output.= '<td align="center"><strong>Total Avance</strong></td>';
$output.= '<td align="center"><strong>Somme de Total</strong></td>';
$output.= '<td align="center"><strong>Total '.$langs->trans('netap').'</strong></td>';}

$output.= '</tr></thead>';



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
if($salaire_user->Check_exist_SU($periodmonth,$periodyear))
{ $all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyear,$periodmonth);
$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyear,$periodmonth);}
$avance_utilisateur = new avance_utilisateur($db);
$cnss_utilisateur = new cnss_utilisateur($db);

$tblhtml = '';

foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {
		$var = !$var;
		$group_array = array();
		$userp->fetch($key);
		$user_arr = $pointage->nc_getUserInfo($key);
		if (isset($radio2) && !empty($radio2)) {
			if($radio2=='declar' && $userp->array_options['options_nx_is_declared']==0 )
				continue;
			elseif($radio2=='nodeclar' && $userp->array_options['options_nx_is_declared']==1)
				continue;
		}
	 	$groupslist = $usergroup->listGroupsForUser($key);
	 	foreach ($groupslist as $group) {
	    	$group_array[]=$group->id;
	   	}
		if(!empty($groupid) && $groupid!='-1' && !in_array($groupid, $group_array))
			continue;

		$getthm = $salaire_user->getThm($periodmonth,$periodyear,$key);
		$slbase = $salaire_user->getSalarybase($periodmonth,$periodyear,$key);
		$slaryuse = $salaire_user->getSalary($periodmonth,$periodyear,$key);
		
		$cnss = $cnss_utilisateur->get_cnss_month($periodyear,$periodmonth,$key);
		$avance = $avance_utilisateur->get_avance_month($periodyear,$periodmonth,$key);

		if($index == 'salary'){
			$tblhtml.= '<tr '.$bc[$var].'>';
				// $tblhtml.= '<td align="center">'.$userp->mat.'</td>';
				$tblhtml.= '<td align="left">'.$userp->getFullName($trans).'</td>';

				$ind=0;
				$salary = 0;
				if($slaryuse)
					$salary = $slaryuse;
				
				if($slbase)
					$ind = $salary - $slbase;

				if($radio!='prime'){

					$nbr = $pointage->getValByMonth($periodyear,$periodmonth,$key);
					$thm = 0;
					if($getthm > 0){
						$thm = $getthm;
						$tblhtml.='<td align="center">'.$nbr.'</td>';
						$tblhtml.='<td align="center">'.number_format($thm,2,',',' ').'</td>';
						$salary = $salary + ($thm * $nbr);
						$ind = $salary - $slbase;
					}else{
						$tblhtml.='<td class="empty"></td>';
						$tblhtml.='<td class="empty"></td>';
					}

					// $tblhtml.= '<td></td>';
					// $tblhtml.= '<td></td>';

					$tblhtml.= '<td align="center">'.number_format($slbase,2,',',' ').'</td>';

				}

				// $ind=0;
				// $salary = 0;
				// if($slaryuse)
				// 	$salary = $slaryuse;

				// if($slbase)
				// $ind = $salary - $slbase;

				if($radio!='noprime')
				$tblhtml.= '<td align="center">'.number_format($ind,2,',',' ').'</td>';
				else
					$salary = $slbase;

				if($radio!='prime'){
				$tblhtml.= '<td align="center">'.number_format($salary,2,',',' ').'</td>';
				$tblhtml.= '<td align="center"> '.number_format($cnss,2,',',' ').'</td>';
				$tblhtml.= '<td align="center">'.number_format($avance,2,',',' ').'</td>';
				$total = $cnss+$avance;
				$tblhtml.= '<td align="center">'.$total.'</td>';
				$netap = $salary - $total;
				$tblhtml.= '<td align="center">'.number_format($netap,2,',',' ').'</td>';}
				$tblhtml.= '<td align="center">';
					$numItems_ = count($groupslist);
					$t = 0;
	                foreach ($groupslist as $group) {
	                	$virgule = ",";
		            	if(++$t === $numItems_) {
		            		$virgule = "";
					  	}
	                	$tblhtml.= $group->name.$virgule." ";
	                }
	             $tblhtml.= '</td>';
			$tblhtml.= '</tr>';
			}
		else{
			$nbr = $pointage->getValByMonth($periodyear,$periodmonth,$key);
			if($nbr==0)
			continue;
			$tblhtml.= '<tr '.$bc[$var].'>';
				// $tblhtml.= '<td align="center">'.$userp->mat.'</td>';
				$tblhtml.= '<td align="left">'.$userp->getFullName($trans).'</td>';
				if($radio!='prime'){
				$tblhtml.= '<td align="center">'.number_format($nbr,2,',',' ').'</td>';
				$tblhtml.= '<td align="center">'.number_format($getthm,2,',',' ').'</td>';
				$tblhtml.= '<td align="center">'.number_format($slbase,2,',',' ').'</td>';}
				$salary = $nbr * $userp->thm;
				$ind=0;
				$thm = 0;
				if($getthm)
					$thm = $getthm;
				$salary = $nbr * $thm;
				$ind=0;
				if($slbase)
				$ind = $salary - $slbase;
				if($radio!='noprime')
				$tblhtml.= '<td align="center">'.number_format($ind,2,',',' ').'</td>';
				else
					$salary = $slbase;
				if($radio!='prime'){
				$tblhtml.= '<td align="center">'.number_format($salary,2,',',' ').'</td>';
				$tblhtml.= '<td align="center"> '.number_format($cnss,2,',',' ').'</td>';
				$tblhtml.= '<td align="center">'.number_format($avance,2,',',' ').'</td>';
				$total = $cnss+$avance;
				$tblhtml.= '<td align="center">'.$total.'</td>';
				$netap = $salary - $total;
				$tblhtml.= '<td align="center">'.number_format($netap,2,',',' ').'</td>';}
				$tblhtml.= '<td align="center">';
	                $numItems_ = count($groupslist);
					$t = 0;
	                foreach ($groupslist as $group) {
	                	$virgule = ",";
		            	if(++$t === $numItems_) {
		            		$virgule = "";
					  	}
	                	$tblhtml.= $group->name.$virgule." ";
	                }
	             $tblhtml.= '</td>';
			$tblhtml.= '</tr>';
		}

		if($slbase)
		$ind = $salary - $slbase;

		$tsalary_base += $slbase;
		$tnbrh += $nbr;
		$tind += $ind;

		$tsalary += $salary;
		$tcnss += $cnss;
		$tavance += $avance;
		$total = $cnss +$avance;
		$ttotal += $total;
		$netap = $salary - $total;
		$tnetap+= $netap;
	}
}



$output.= '<tr '.$bc[$var].'>';
if($radio!='prime'){
$output.= '<td align="center">'.number_format($tnbrh,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($tsalary_base,2,',',' ').'</td>';}
if($radio!='noprime')
$output.= '<td align="center">'.number_format($tind,2,',',' ').'</td>';
else{
$tsalary = $tsalary_base;
$tnetap= $tsalary - $ttotal;
}
if($radio!='prime'){
$output.= '<td align="center">'.number_format($tsalary,2,',',' ').'</td>';
$output.= '<td align="center"> '.number_format($tcnss,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($tavance,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($ttotal,2,',',' ').'</td>';
$output.= '<td align="center">'.number_format($tnetap,2,',',' ').'</td>';}

$output.= '</tr>';




$output.= '</tbody></table><br/> <br/>';

$output.= '<table border="1">';
$output.= '<thead>';
$output.= '<tr class="liste_titre">';

// $output.= '<td align="center"><strong>N Mle</strong></td>';
$output.= '<td align="center"><strong>Noms</strong></td>';
if($radio!='prime'){
$output.= '<td align="center"><strong>Nbres H.</strong></td>';
$output.= '<td align="center"><strong>Taux.H</strong></td>';
$output.= '<td align="center"><strong>Sal.Base</strong></td>';}
if($radio!='noprime')
$output.= '<td align="center"><strong>P. et Ind</strong></td>';
if($radio!='prime'){
$output.= '<td align="center"><strong>Sal.Brut</strong></td>';
$output.= '<td align="center"><strong>CNSS</strong></td>';
$output.= '<td align="center"><strong>Avance</strong></td>';
$output.= '<td align="center"><strong>Total</strong></td>';
$output.= '<td align="center"><strong>'.$langs->trans('netap').'</strong></td>';}
$output.= '<td align="center"><strong>Service</strong></td>';

$output.= '</tr></thead>';


$output .= $tblhtml;


$output.= '</tbody></table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>