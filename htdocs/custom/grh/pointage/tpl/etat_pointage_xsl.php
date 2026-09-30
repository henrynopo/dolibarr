<?php
$output.= '<meta charset="utf-8" />';
	$output.= '<h1 align="center"> Etat pointage '.$periodmonths.'/'.$periodyears.'</h1>';
	global $form, $formother, $pointage,$salaire_user, $taskstatic;
	global $periodyears,$periodmonths,$users,$search_datef,$search_dated,$pointage;
     global $lines,$langs;
     global $numlines,$user_array,$project;
     $filter="and year_point=".$periodyears;
	if (empty($search_datef) && empty($search_dated) )
     $filter.=" and month_point=".$periodmonths;

     $sd = 0 ;
     $ed = 0 ;
    
     if (isset($search_dated) && !empty($search_dated)) {
	list($sd, $sm, $sy)  = explode("/", $search_dated);
	if (isset($search_datef) && !empty($search_datef)) {
	list($ed, $em, $ey)  = explode("/", $search_datef);
	/*$filter .=  " AND month_point BETWEEN ". $sm ." AND ". $em ;
	$filter .=  " AND jour BETWEEN ". $sd ." AND ". $ed ;*/
}
}
$sd = intval($sd);
$ed = intval($ed);	
$numtd = 0 ;
$num = 0 ;

    $pointage->fetchAll('','p.rowid',0,0,$filter);
     $all_users['salary'] = $pointage->getUsersWithS();
 	$all_users['nosalary'] = $pointage->getUsersWithS(false);
 		if($salaire_user->Check_exist_SU($periodmonths,$periodyears))
 	{ $all_users['salary'] = $salaire_user->getUsersWithS(true,$periodyears,$periodmonths);
	$all_users['nosalary'] = $salaire_user->getUsersWithS(false,$periodyears,$periodmonths);}
	//$lastprojectid=0;
	$var=true;
	$array=['J'];
    $numlines=count($array);
    if($lines){
	    $num=count($lines);
    }
    $nbdaymonth = 0;
    
    $monthArray = array(
	     1 => 'Janvier',
	     2 => 'Février',
	     3 => 'Mars',
	     4 => 'Avril',
	     5 => 'Mai',
	     6 => 'Juin',
	     7 => 'Juillet',
	     8 => 'Août',
	     9=> 'Septembre',
	    10=> 'Octobre',
	    11 => 'Novembre',
	    12 => 'Décembre'
	);
    $total_day = array();
    $total_month = array();
   
    $test = 0;
            

$time = mktime(0, 0, 0, $periodmonths+1, 1, $periodyears); // premier jour du mois suivant
			$time--; // Recule d'une seconde
			$nbdaymonth=date('d', $time); 
			if (!empty($search_datef) && !empty($search_dated) ){
				if($periodmonths == $sm)
					$numtd = ($nbdaymonth-$sd)+3;
				elseif($periodmonths == $em)
					$numtd = $ed+2;
				else
					$numtd = ($ed-$sd)+3;
			}
			else
				$numtd = $nbdaymonth+2;
     		$output.= '<table border="1" width="100%" >';
     		$output.='<tr><td bgcolor="#28A828" colspan="'.intval($numtd+2).'" align="center"><h2><strong>'.$monthArray[$periodmonths].'</strong></h2></td></tr>';
			$output.= '<tr class="liste_titre">';
			// $output.= '<td  align="center" >'.$langs->trans("Matricule").'</td>';
			$output.= '<td  align="center" >'.$langs->trans("Names").'</td>';
			$output.= '<td  align="center">'.$langs->trans("t.h").' ('.$langs->getCurrencySymbol($conf->currency).')</td>';
			for ($i = 0 ; $i <$numlines ; $i++)
	  		 {      $nbr=0;
	   
	                for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 

					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd  || $day>$ed )
							continue ;
					}
					  $curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);
					  $bgcolor="";
					
				    	
							if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						
						$output.= '<td bgcolor="#808080" >';
						$output.= substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
						}else{
							$output.= '<td align=center>';
							$output.= substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
						}
						
	        }
	        }

			//$output.= '<td colspan="'.intval($numtd-1).'" align="right"></td>';
			$output.= '<td  align="center">'.$langs->trans("total").'</td>';
			$output.= '<td  align="left">'.$langs->trans("netap").' ('.$langs->getCurrencySymbol($conf->currency).')</td>';
			$output.= "</tr>\n";

			/*$output.= '<tr><td></td><td></td><td></td>';
            	
           $output.= '</tr>';*/
   foreach($all_users as $index=>$data) {
    foreach($data as $key=>$value) {
    	$user_array->fetch($key);
    	$user_arr = $pointage->nc_getUserInfo($key);
    	if (isset($radio) && !empty($radio)) {
    			if($radio=='declar' && $user_arr->declar==0 )
    				continue;
    			elseif($radio=='nodeclar' && $user_arr->declar==1)
    				continue;
    	}
    		if(!empty($users) && !in_array($key, $users))
     		continue ;
            if (count($pointage->rows)) {

     foreach($pointage->rows as $line) {
if($line->fk_user == $key){
     		$test = 1;

	       	$user_array->fetch($line->fk_user);
	       	$title = '';
	     
	   		for ($i = 0 ; $i <$numlines ; $i++)
	  		 {      $nbr=0;

				if(!empty($array[$i])){
					$output.= '<tr >';
					// $output.= '<td align="center">'.$user_array->mat.'</td>';
					$output.= '<td align="left" style="white-space: nowrap;" >'.$user_array->getFullName($trans).'</td>';

					if($salaire_user->getThm($periodmonths,$periodyears,$key))
						$output.= '<td align="center">'.number_format($salaire_user->getThm($periodmonths,$periodyears,$key),2).'</td>';
					else
						$output.= '<td></td>';
				}

	            for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 
					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd  || $day>$ed )
							continue ;
					}
					  $curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);
					  $bgcolor="";
					  if(!empty($array[$i]))
					    {
					    	$val=$pointage->getVal($periodmonths,$periodyears,$line->fk_user,$array[$i],$day);
					if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						
						$output.= '<td bgcolor="#808080" align=center >';
						$output.= $val.'</td>';
						
						}else{

								$output.= '<td align=center >';
								$output.= $val.'</td>';
							}
				
				
				$nbr=$nbr+$val;
				if(!array_key_exists($periodmonths.'-'.$day, $total_day))
					$total_day[$periodmonths.'-'.$day] = $val;
				else
					$total_day[$periodmonths.'-'.$day] += $val;
				
					}

   }

 for ($com=1; $com <$i ; $com++) {
    if(!empty($array[$i])) 
$output.= '<td align="center"></td>';
 	
 }

   if(!empty($array[$i]))
$output.= '<td align="center">'.$nbr.'</td>';
$total = 0;
if($salaire_user->getThm($periodmonths,$periodyears,$key)){
	$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
	if($salaire_user->getSalary($periodmonths,$periodyears,$key)){
		$total = $total + $salaire_user->getSalary($periodmonths,$periodyears,$key);
	}
	$output.= '<td align="center">'.number_format($total,2).'</td>';
	if(!array_key_exists($periodmonths.'_'.$index, $total_month))
		$total_month[$periodmonths.'_'.$index] = $total;
	else
		$total_month[$periodmonths.'_'.$index] += $total;
}
for ($c=$com; $c<1 ; $c++) {
   if(!empty($array[$i])) 
$output.= '<td align="center"></td>';
 	
 }
$output.='</tr>';
}
$output.='</tr>';
}

}


}
if($test==0){

     $user_array->fetch($key);
	    $output.= '<tr>';
	    // $output.= '<td align="center">'.$user_array->mat.'</td>';
		$output.= '<td align="left" style="white-space: nowrap;" >'.$user_array->getFullName($trans).'</td>';
		if($salaire_user->getThm($periodmonths,$periodyears,$key))
          	$output.= '<td align="center">'.number_format($salaire_user->getThm($periodmonths,$periodyears,$key),2).'</td>';
	    else
    	 	$output.= '<td></td>';
		for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 
					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd  || $day>$ed )
							continue ;
					}
					  $curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);
					  $bgcolor="";
					
					if (date('N', $curday) == 6 || date('N', $curday) == 7)
					{
						
						$output.= '<td bgcolor="#808080" align=center ></td>';
						
						}else{

								$output.= '<td align=center ></td>';
							}
					

 


 					}
	            
   	for ($com=1; $com <$i ; $com++) {
    if(!empty($array[$i])) 
		$output.= '<td align="center"></td>';	
 		}

   $total = 0;
		$output.= '<td align="center">'.$nbr.'</td>';
		if($salaire_user->getThm($periodmonths,$periodyears,$key)){
			$total = $nbr*$salaire_user->getThm($periodmonths,$periodyears,$key);
			if($salaire_user->getSalary($periodmonths,$periodyears,$key)){
				$total = $total + $salaire_user->getSalary($periodmonths,$periodyears,$key);
			}
			$output.= '<td align="center">'.number_format($total,2).'</td>';
			if(!array_key_exists($periodmonths.'_'.$index, $total_month))
				$total_month[$periodmonths.'_'.$index] = $total;
			else
				$total_month[$periodmonths.'_'.$index] += $total;
		}
	elseif($salaire_user->getSalary($periodmonths,$periodyears,$key)){
		$total = $salaire_user->getSalary($periodmonths,$periodyears,$key);
		$output.= '<td align="center">'.number_format($total,2).'</td>';
		if(!array_key_exists($periodmonths.'_'.$index, $total_month))
		$total_month[$periodmonths.'_'.$index] = $total;
		else
		$total_month[$periodmonths.'_'.$index] += $total;}
	else
		$output.= '<td align="center"></td>';
	}
	$test = 0;
	$nbr = 0;
}
		$output.= '<tr bgcolor="#287EA8"  class="liste_titre"><td colspan="'.intval($numtd-1).'" align="center">'.$langs->trans("total").'</td><td></td>';


     		 for ($i = 0 ; $i <$numlines ; $i++)
	  		 {   $total_heurs = 0;
     		 for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 
					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd  || $day>$ed )
							continue ;
					}
					
					$curday=mktime(0, 0, 0, $periodmonths, $day, $periodyears);

					  $bgcolor="";
					if(isset($total_day[$periodmonths.'-'.$day])){
						$total_heurs +=  $total_day[$periodmonths.'-'.$day];

						}
					}
				}

				$output.= '<td  align=center><strong>'.$total_heurs.'</strong></td>';
				$output.= '<td  align=center><strong>'.number_format($total_month[$periodmonths.'_'.$index],2).'</strong></td>';
				$output.= "</tr>\n";	

}
$output.= '<tr class="liste_titre">';
			$output.= '<td colspan="'.intval($numtd).'" align="right"><strong>TOTAL GlOBAL</strong></td>';
			$output.= '<td  align="center"></td>';
			$total_global = 0;
			foreach ($total_month as $value) {
				$total_global += $value;
			}
			$output.= '<td  align="center"><strong>'.number_format($total_global,2).'</strong></td>';
			$output.= "</tr>\n";

$output.= "</table>";

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
?>