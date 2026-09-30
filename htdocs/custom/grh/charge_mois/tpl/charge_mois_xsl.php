<?php
$y = ($search_year != -1) ? $search_year : "";
$m = ($search_month != -1) ? $search_month." / " : "";
$output.= '<meta charset="utf-8" />';
$output.= '<h3 align="center"> Charges / Mois : '.$m.''.$y.'</h3>';

$output.= '<table  border="1" >';
$output.= '<thead>';
$output.= '<tr class="liste_titre">';
$output.= '<td align="center"><strong>Réference</strong></td>';
$output.= '<td align="center"><strong>Description</strong></td>';
$output.= '<td align="center"><strong>Cétegorie</strong></td>';
$output.= '<td align="center"><strong>Date</strong></td>';
$output.= '<td align="center"><strong>Montant</strong></td>';
$output.= '</tr>';
$output.= '</thead>';
$output.= '<tbody>';

$sortfield 			=  $_GET['sortfield'];
$sortorder 			=  $_GET['sortorder'];

$filter .= (!empty($search_id) && $search_id != -1) ? " AND id = ". $db->escape($search_id)."\n" : "";
$filter .= (!empty($search_montant) && $search_montant != -1) ? " AND cast(montant as decimal(10,1)) = '".str_replace(",", ".", $db->escape($search_montant))."'":"";
$filter .= (!empty($search_year) && $search_year != -1) ? " AND YEAR(datec) = ".$db->escape($search_year) : "";
$filter .= (!empty($search_month) && $search_month != -1) ? " AND MONTH(datec) = ".$db->escape($search_month) : "";
$filter .= (!empty($search_cat) && $search_cat != -1) ? " AND cat = ".$db->escape($search_cat)."" : "";
$filter .= (!empty($search_discription)) ? " AND discription like '%".$db->escape($search_discription)."%'" : "";

$charge_mois->fetchAll($sortorder, $sortfield, 20, $offset, $filter);
$total ;
	for ($i=0; $i < count($charge_mois->rows) ; $i++) {
		$item = $charge_mois->rows[$i];
			$output.= '<tr>';
		    	$output.= '<td align="right" >'.$item->id.'</td>';
				$output.= '<td align="left">'.$item->discription.'</td>';
				$output.= '<td align="left">'.$charge_mois->get_cat_libele($item->cat).'</td>';
				$output.= '<td align="center">'.date("Y-m-d", $item->datec).'</td>';
				$output.= '<td align="right">'.$item->montant.'</td>';
				$total += $item->montant;
			$output.= '</tr>';
	}
            $output.= '<tr>';
                $output.= '<td align="center" colspan="2"></td>';
                $output.= '<td align="left" >'.$langs->trans('total').' : </td>';
                $output.= '<td align="right" >'. number_format($total,2) .'</td>';
            $output.= '</tr>';
 


$output.= '</tbody>';
$output.= '</table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>