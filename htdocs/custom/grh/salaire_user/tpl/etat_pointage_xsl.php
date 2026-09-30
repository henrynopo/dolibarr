<?php
$output.= '<meta charset="utf-8" />';
	$output.= '<h1 align="center"> Etat pointage '.$periodyears.'</h1>';
	global $form, $formother, $pointage, $taskstatic;
	global $periodyears,$users,$search_datef,$search_dated,$pointage;
     global $lines;
     global $numlines,$user_array,$project;
     $filter="and year_point=".$periodyears;

     $sd = 0 ;
     $ed = 0 ;
    //$filter.=" and month_point=".$periodmonths;
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

    $pointage->fetchAll('','p.rowid',0,0,$filter);
    
	//$lastprojectid=0;
	$var=true;
	$array=['J'];
    $numlines=count($array);
    $num=count($lines);
    $nbdaymonth = 0;
    $monthArray = monthArray($langs);
    $months = array() ;
    $total_day = array();
    $old_month = 0;
            if (count($pointage->rows)) {

     foreach($pointage->rows as $line) {
     	
     		$time = mktime(0, 0, 0, $line->month_point+1, 1, $periodyears); // premier jour du mois suivant
			$time--; // Recule d'une seconde
			$nbdaymonth=date('d', $time); 
			if (!empty($search_datef) && !empty($search_dated) ){
				if($line->month_point == $sm)
					$numtd = ($nbdaymonth-$sd)+3;
				elseif($line->month_point == $em)
					$numtd = $ed+1;
				else
					$numtd = ($ed-$sd)+2;
			}
			else
				$numtd = $nbdaymonth+1;
			$munis = 0;
			if($show == 0){
				for ($day=1;$day <= $nbdaymonth ;$day++)
				{ $curday=mktime(0, 0, 0, $line->month_point, $day, $periodyears);
					if (date('N', $curday) == 6 || date('N', $curday) == 7 )
						$munis ++;
				}
			}
			$numtd = $numtd - $munis;
     	if (!empty($search_datef) && !empty($search_dated) ) {
				if($line->month_point < $sm || $line->month_point>$em)
					continue ;
			}
     	if(!empty($users) && !in_array($line->fk_user, $users))
     		continue ;
     	if(!in_array($line->month_point, $months)){
     		if(!empty($months)){
     		$output.= '<tr><td  align="left"><strong>'.$langs->trans("total").'</strong></td>';
     		 for ($i = 0 ; $i <$numlines ; $i++)
	  		 {   
	  		 	$total_heurs = 0;
     		 for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 
					
					$curday=mktime(0, 0, 0, $old_month, $day, $periodyears);
			
					if(isset($total_day[$old_month.'-'.$day])){
						$total_heurs +=  $total_day[$old_month.'-'.$day];
						if (date('N', $curday) == 6 || date('N', $curday) == 7 ){
							if($show ==1){
						$output.= '<td align=center bgcolor="#808080" ><strong>';
						$output.= $total_day[$old_month.'-'.$day].'</strong></td>';}
						}else{
							$output.= '<td align=center><strong>';
							$output.= $total_day[$old_month.'-'.$day].'</strong></td>';
						}
						}
					}
				}
				$output.= '<td  align=center><strong>'.$total_heurs.'</strong></td>';
				$output.= "</tr>\n";
     		$output.= "</table>";
     	}
     		$months[]=$line->month_point;
     		$output.= '<table border="1" width="100%" >';
     		$output.='<tr><td colspan="'.$numtd.'" align="center"><strong>'.$monthArray[$line->month_point].' '.$periodyears.'</strong></td></tr>';
			$output.= '<tr  >';
			$output.= '<td  align="center" >'.$langs->trans("Names").'</td>';
			//$output.= '<td  align="center">'.$langs->trans("Qualif").'</td>';

			$output.= '<td colspan="'.intval($numtd-1).'" align="right"></td>';
			$output.= '<td  align="center"><strong>'.$langs->trans("total").'</strong></td>';
			$output.= "</tr>\n";
     		
				
     	$output.= '<tr><td></td>';
            	for ($i = 0 ; $i <$numlines ; $i++)
	  		 {      $nbr=0;
	   
	                for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 

					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd && $line->month_point == $sm || $day>$ed && $line->month_point == $em)
							continue ;
					}
					  $curday=mktime(0, 0, 0, $line->month_point, $day, $periodyears);
					
							if (date('N', $curday) == 6 || date('N', $curday) == 7 )
					{	if($show==1){
						$output.= '<td bgcolor="#808080" >';
						$output.= substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';}
						}else{
							$output.= '<td align=center>';
							$output.= substr($langs->trans(date('l', $curday)),0,1)." ".$day.'</td>';
						}
						
	        }
	        }
           $output.= '</tr>';
       }

	       	$user_array->fetch($line->fk_user);
	       	$title = '';
	     
	   		for ($i = 0 ; $i <$numlines ; $i++)
	  		 {      $nbr=0;
	   
		
	    $output.= '<tr>';
	    $user_array->fetch($line->fk_user);
		$output.= '<td align="left" style="white-space: nowrap;" >'.$user_array->getFullName().'</td>';
	               
	            for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 
					if (!empty($search_datef) && !empty($search_dated) ) {
						if($day<$sd && $line->month_point == $sm || $day>$ed && $line->month_point == $em)
							continue ;
					}
					  $curday=mktime(0, 0, 0, $line->month_point, $day, $periodyears);
					  if(!empty($array[$i]))
					    {
					    	$val=$pointage->getVal($line->month_point,$periodyears,$line->fk_user,$array[$i],$day);
					if (date('N', $curday) == 6 || date('N', $curday) == 7 )
					{
						if($show ==1){
						$output.= '<td bgcolor="#808080" align=center >';
						$output.= $val.'</td>';}
						
						}else{

								$output.= '<td align=center >';
								$output.= $val.'</td>';
							}
				
				
				$nbr=$nbr+$val;
				if(!array_key_exists($line->month_point.'-'.$day, $total_day))
					$total_day[$line->month_point.'-'.$day] = $val;
				else
					$total_day[$line->month_point.'-'.$day] += $val;
				
					}

   }

 /*for ($com=1; $com <$i ; $com++) {
    if(!empty($array[$i])) 
$output.= '<td align="center"></td>';
 	
 }

   
for ($c=$com; $c<1 ; $c++) {
   if(!empty($array[$i])) 
$output.= '<td align="center"></td>';
 	
 }*/
 if(!empty($array[$i]))
$output.= '<td align="center">'.$nbr.'</td>';
$output.='</tr>';
}
$output.='</tr>';
$old_month = $line->month_point;
}
$output.= '<tr><td  align="left"><strong>'.$langs->trans("total").'</strong></td>';
     		 for ($i = 0 ; $i <$numlines ; $i++)
	  		 {   $total_heurs = 0;
     		 for ($day=1;$day <= $nbdaymonth ;$day++)
				{ 
					
					$curday=mktime(0, 0, 0, $old_month, $day, $periodyears);
					if(isset($total_day[$old_month.'-'.$day])){
						$total_heurs +=  $total_day[$old_month.'-'.$day];
					
						if (date('N', $curday) == 6 || date('N', $curday) == 7 )
					{
						if($show ==1){
						$output.= '<td align=center bgcolor="#808080" ><strong>';
						$output.= $total_day[$old_month.'-'.$day].'</strong></td>';}
						}else{
							$output.= '<td align=center><strong>';
							$output.= $total_day[$old_month.'-'.$day].'</strong></td>';
						}
						}
					}
				}
				$output.= '<td  align=center><strong>'.$total_heurs.'</strong></td>';
				$output.= "</tr>\n";
}
			
$output.= "</table>";

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
?>