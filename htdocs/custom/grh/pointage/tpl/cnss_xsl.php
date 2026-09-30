<?php
$output.= '<meta charset="utf-8" />';
$output.= '<h1 align="center"> '.$langs->trans('etatcnss').' '.$mid.'/'.$periodyear.'</h1>';


// TABLE
if(!$pointage)
die();
$all_users = $pointage->getUsersCNSS();
if($salaire_user->Check_exist_SU($mid,$periodyear))
    $all_users = $salaire_user->getUsersCNSS($periodyear,$mid);
 	$total_salary = 0 ;
			$total_day = 0 ;
$output.= '<table border="1">';
	$output.= "<tr class=\"liste_titre\" >";
	 $output.= '<th style="text-align: center" >Noms</th>';
	 $output.= '<th style="text-align: center"> Nombre de jours </th>';
	$output.= '<th style="text-align: center"> Salaire ('.$langs->getCurrencySymbol($conf->currency).') </th>';
	$output.= '<th style="text-align: center"> CIN </th>';
	$output.= '<th style="text-align: center"> CNSS </th>';
	$output.= '</tr>';
	$extrafields->fetch_name_optionals_label('user');
	//foreach($all_users as $index=>$data) {
    foreach($all_users as $key=>$value) {
    	$userp->fetch($key);
        $user_arr = $pointage->nc_getUserInfo($key);
       if (isset($radio2) && !empty($radio2) && $radio2=='paie') 
                if(  !$userp->thm && !$userp->salary )
                    continue;
        if (isset($radio2) && !empty($radio2) && $radio2=='nopaie') 
                if(  $userp->thm || $userp->salary )
                    continue;
    	if(!$userp->array_options['options_nx_is_declared'])
    		continue;
    	$output.= '<tr>';
    	$output.= '<td align="left">'.$userp->getFullName($trans).'</td>';
        $nb_holiday = $userp->array_options['options_nx_num_holiday'];
        // $nb_holiday = $user_arr->nb_holiday;
 		// $nb_holiday = $extrafields->showOutputField('nb_holiday',$nb_holiday);
 		$total_day += intval($nb_holiday);
    	$output.= '<td align="center">'.$nb_holiday.'</td>';
    	$salary = 0;
        if($salaire_user->getSalary($mid,$periodyear,$key))
            $salary = $salaire_user->getSalary($mid,$periodyear,$key);
        elseif($salaire_user->getThm($mid,$periodyear,$key)){
            $nbr = $pointage->getValByMonth($periodyear,$mid,$key);
            $salary = $nbr * $salaire_user->getThm($mid,$periodyear,$key);
        }
    	$total_salary += $salary;
    	$output.= '<td align="center">'.number_format($salary,2,',',' ').'</td>';
        $cin = $userp->array_options['options_nx_cin'];
    	// $cin = $user_arr->cin;
 		// $cin = $extrafields->showOutputField('cin',$cin);
    	$output.= '<td align="center">'.$cin.'</td>';
        $cnss = $userp->array_options['options_nx_cnss'];
		// $cnss = $user_arr->immatriculation;
 		// $cnss = $extrafields->showOutputField('immatriculation',$cnss);
		
    	$output.= '<td align="center">'.$cnss.'</td>';
    	$output.= '</tr>';
    	}
	//}
	$output.= '<tr>';
	$output.= '<td align="center"><strong>Total</strong></td>';
	$output.= '<td align="center">'.$total_day.'</td>';
	$output.= '<td align="center">'.number_format($total_salary,2,',',' ').'</td>';
	$output.= '</tr>';
	 $output.= '</table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>