<?php

	$output.= '<meta charset="utf-8" />';
	$output.= '<h1 align="center"> État '.$langs->trans("medical").' '.$etat_year.'</h1>';
$output.= '<table border="1" style="width:100%;">';
	$output.= '<tr  bgcolor="##FFFF00">';
	$output.= '<td align="center" ><strong>'. $langs->trans("Ref").'</strong></td>';
	$output.= '<td align="center" ><strong>'. $langs->trans("fk_user").'</strong></td>';
	$output.= '<td align="center" ><strong>'. $langs->trans("datec").'</strong></td>';

	if ($search_status == 2) {
		$output.= '<td align="center" ><strong>'. $langs->trans("DateEnd").'</strong></td>';
	}

	$output.= '<td align="center" ><strong>'. $langs->trans("poste").'</strong></td>';
	$output.= '<td align="center" ><strong>'. $langs->trans("Status").'</strong></td>';
	$output.= '<td align="center" ><strong>'. $langs->trans("apte").'</strong></td>';
	
	$output.= "</tr>\n";


if (count($medical->rows)) {
		//$createdBy = new userm($db);
		foreach($medical->rows as $line) {
			$var = !$var;
			$id 			= $line->id;
			$datec          = dol_print_date($line->datec,'day');
			$fk_user         = $line->fk_user;
		  	$status         = $line->status;
		  	$risques          = $line->risques;
		  	$apte = $exmcontrl->getAptebyDI($line->id);
		  	if($search_apte!=='' && $search_apte!=-1 && $search_apte!==$apte)
		  		continue;
		  	$userm->fetch($fk_user);
			$output.= '<tr '.$bc[$var].'>';
			$output.= '<td align="center" style ="white-space: nowrap;">';
				$output.= $line->id;
			$output.= '</td>';
			 
			$output.= '<td align="center">'.$userm->firstname.' '.$userm->lastname.'</td>';
			$output.= '<td align="center">'.date("d-m-Y",$line->datec).'</td>';
			
	if ($search_status == 2) {
		$output.= '<td align="center">'.date("d-m-Y",$line->datef).'</td>';
	}
			$output.= '<td align="center">'.$userm->job.'</td>';
			$output.= '<td align="center">'.$status_array[$status].'</td>';
			if($apte != -1)
				$output.= '<td align="center">'.$apte_array[$apte].'</td>';
			else
				$output.= '<td align="center"></td>';
			
			
			$output.= '</tr>'."\n";

		}
	}

	$output.= '</table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
?>