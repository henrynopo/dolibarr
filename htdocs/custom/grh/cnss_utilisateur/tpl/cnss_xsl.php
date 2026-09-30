<?php
$y = ($search_year != -1) ? $search_year : "";
$m = ($search_month != -1) ? ": ".$search_month." / " : "";
$output.= '<meta charset="utf-8" />';
$output.= '<h3 align="center"> CNSS des salariés '.$m.''.$y.'</h3>';


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

$tmontant_cnss = 0;
$sortfield =  $_GET['sortfield'];
$sortorder =  $_GET['sortorder'];

$filter .= (!empty($search_id) && $search_id != -1) ? " AND id = ". $db->escape($search_id)."\n" : "";
$filter .= (!empty($search_idu) && $search_idu != -1) ? " AND idutilisateur = ". $db->escape($search_idu)."\n" : "";
$filter .= (!empty($search_montant_cnss) && $search_montant_cnss != -1) ? " AND cast(montant_cnss as decimal(5,1)) = '".str_replace(",", ".", $db->escape($search_montant_cnss))."'":"";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) = ".$db->escape($search_year) : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) = ".$db->escape($search_month) : "";


$cnss_utilisateur->fetchAll($sortorder, $sortfield, 20, $offset, $filter);

 for ($i=0; $i < count($cnss_utilisateur->rows) ; $i++) {
 	$var = !$var;
 	$item = $cnss_utilisateur->rows[$i];
 	$userp->fetch($item->idutilisateur);

$output.= '<tr>';
	 	$output.= '<td align="left" >'.$item->id.'</td>';
		$output.= '<td>'.$userp->firstname.'  '.$userp->lastname.'</td>';
		$output.= '<td align="center">'.date("Y-m-d", $item->datec).'</td>';
		$output.= '<td align="right">'.number_format($item->montant_cnss,2).'</td>';
	$tmontant_cnss += $item->montant_cnss;

$output.= '</tr>';
 }
$output.= '<tr >';
	$output.= '<td align="center" colspan="3" style="font-weight: bold;" ><strong>TOTAL DU MONTANT CNSS</strong></td>';
	$output.= '<td align="center" style="font-weight: bold;">'.number_format($tmontant_cnss,2,',',' ').'</td>';
$output.= '</tr>';
$output.= '</tbody>';
$output.= '</table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>