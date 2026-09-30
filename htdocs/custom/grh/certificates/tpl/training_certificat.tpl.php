<?php

// $employeeCIN 		= $field['options_nx_cin'] ? strtoupper($field['options_nx_cin']) : '';
// $employeeEstab 		= $field['options_nx_etablissement'] ? strtoupper($field['options_nx_etablissement']) : '';
// $employeeEstabOpt 	= $field['options_nx_etab_opt'] ? ucwords($field['options_nx_etab_opt']) : '';

$pdf->ln(20);
//la date
$top_right_date = $city." ". $langs->trans('Le') ." : ". $today;

// $pdf->SetFont('Times','',17);
$pdf->Cell(0,6,$top_right_date,0,1,'R');
$pdf->ln(30);
//le titre
// $pdf->SetFont('Times','BU',20);
$pdf->Cell(0,6, 'ATTESTATION DE STAGE',0,1,'C');
$pdf->ln(20);
//le contenu
$pdf->setCellHeightRatio(2);
// $pdf->SetFont('Times','',17);
$txt = $langs->trans('TrainingCertletter', $directorName, $societe, $employeeName, $employeeCIN) .' '. $langs->trans('TrainingCertletterRest', $employeeEstab, $employeeEstabOpt, $datePeriod);

$pdf->SetFillColor(255, 255, 255);
$pdf->writeHTMLCell(0, 0, '', '', $txt, 0, 1, 0, true, '', true);
$pdf->ln(20);
//signe
$signe = $langs->trans("Signé");
// $pdf->SetFont('Times','U',17);
$pdf->Cell(136);
$pdf->Cell(0,10,$signe,0,1,'C');

//le signature
// $pdf->SetFont('Times','B',17);
$pdf->Cell(130);
$pdf->Cell(0,10,$directorCivility .' '. $directorName,0,1,'C');
?>