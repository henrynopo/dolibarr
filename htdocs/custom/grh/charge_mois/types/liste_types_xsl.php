<?php

$output.= '<meta charset="utf-8" />';
$output.= '<h3 align="center"> Liste des Catégories </h3>';
$output.= '<table border="1" style="width:100%;">';
$output.= '<thead>';	
	$output.= '<tr class="liste_titre">';
	$output.= '<td align="center"><strong>Réference</strong></td>';
	$output.= '<td align="center"><strong>Libellé</strong></td>';
	$output.= '</tr>';
$output.= '</thead>';

$output.= '<tbody>';

for ($i=0; $i < count($charge_mois_cat->rows) ; $i++) {
	$var = !$var;
	$item = $charge_mois_cat->rows[$i];

	$output.= '<tr '.$bc[$var].' >';
		$output.= '<td align="center" style="padding:1%;">'.$item->id_cat.'</td>';
		$output.= '<td align="left">'.$item->libele.'</td>';
	$output.= '</tr>';
}

$output.= '</tbody>';
$output.= '</table>';

header("Content-Type: application/xls");
header("Content-Disposition: attachment; filename=".$filename."");
echo $output;
  ?>