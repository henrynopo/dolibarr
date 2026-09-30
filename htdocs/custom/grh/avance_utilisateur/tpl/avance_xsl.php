<?php
$output.= '<meta charset="utf-8" />';
$output.= '<h3 align="center"> Les avances salariés : '.$search_month.'/'.$search_year.'</h3>';


$output.= '<table  border="1" >';
$output.= '<thead>';
$output.= '<tr class="liste_titre">';
$output.= '<td align="center"><strong>Reference</strong></td>';
$output.= '<td align="center"><strong>Utilisateur</strong></td>';
$output.= '<td align="center"><strong>Date</strong></td>';
$output.= '<td align="center"><strong>Montant</strong></td>';
$output.= '</tr>';
$output.= '</thead>';
$output.= '<tbody>';

$tmontant = 0;
$sortfield =  $_GET['sortfield'];
$sortorder =  $_GET['sortorder'];
					
$filter .= (!empty($search_id) && $search_id != -1) ? " AND id = ". $db->escape($search_id)."\n" : "";
$filter .= (!empty($search_idu) && $search_idu != -1) ? " AND idavance_utilisateur = ". $db->escape($search_idu)."\n" : "";
$filter .= (!empty($search_montant) && $search_montant != -1) ? " AND cast(montant as decimal(5,1)) = '".str_replace(",", ".", $db->escape($search_montant))."'":"";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) = ".$db->escape($search_year) : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) = ".$db->escape($search_month) : "";


$avance_utilisateur->fetchAll($sortorder, $sortfield, 20, $offset, $filter);

for ($i=0; $i < count($avance_utilisateur->rows) ; $i++) {

	 	$var = !$var;
	 	$item = $avance_utilisateur->rows[$i]; 	
	 	$userp->fetch($item->idavance_utilisateur);

		$output.= '<tr >';
				$output.= '<td align="left" >'.$item->id.'</td>';
				$output.= '<td align="center">'.$userp->firstname.'  '.$userp->lastname.'</td>';
				$output.= '<td align="center">'.date("Y-m-d", $item->datec).'</td>';
				$output.= '<td align="right">'.number_format($item->montant,2).'</td>';
				$tmontant += $item->montant;
		$output.= '</tr>';
} 
$output.= '<tr >';
		$output.= '<td align="center" colspan="3" style="font-weight: bold;" ><strong>TOTAL DU MONTANT</strong></td>';
		$output.= '<td align="center" style="font-weight: bold;">'.number_format($tmontant,2,',',' ').'</td>';
$output.= '</tr>';
$output.= '</tbody>';
$output.= '</table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>