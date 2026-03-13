<?php
/* Copyright (C) 2025 SLY Custom
 * SLY Shipment Details export (Excel).
 */

require_once __DIR__.'/../../main.inc.php';

if (!isModEnabled('slycustom')) {
	accessforbidden();
	exit;
}

@ini_set('max_execution_time', 180);

function filterData(&$str) {
	$str = preg_replace("/\t/", "\\t", $str);
	$str = preg_replace("/\r?\n/", "\\n", $str);
	if (strstr($str, '"')) {
		$str = '"'.str_replace('"', '""', $str).'"';
	}
}

$fileName = "SLY_Shipment.xls";
$fields = array('Salesperson', 'SO No', 'Supplier No', 'Shipment No', 'Customer', 'Status', 'Billed?', 'POL', 'ATD', 'POD', 'ATA', 'ETD', 'ETA', 'Shipment Company', 'Container No', 'BL_No', 'HC_No', 'Note_Private', 'Note_Public', 'Product', 'Unit', 'Net Weight', 'Gross Weight', 'Cartons');

$excelData = implode("\t", array_values($fields))."\n";

$sql = "SELECT * FROM view_shipment AS s LEFT JOIN view_shipment_details AS d ON s.Shipment_ID = d.fk_expedition ORDER BY Shipment_ID DESC LIMIT 1000";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$lineData = array($obj->SalesPerson ?? '', $obj->SO_No ?? '', $obj->Supplier_No ?? '', $obj->Shipment_No ?? '', $obj->Customer ?? '', $obj->fk_statut ?? '', $obj->billed ?? '', $obj->POL ?? '', $obj->ATD ?? '', $obj->POD ?? '', $obj->ATA ?? '', $obj->ETD ?? '', $obj->ETA ?? '', $obj->Shipment_Company ?? '', $obj->Container_No ?? '', $obj->BL_No ?? '', $obj->HC_No ?? '', $obj->Note_Private ?? '', $obj->Note_Public ?? '', $obj->Product ?? '', $obj->Unit ?? '', $obj->Net_Weight ?? '', $obj->Gross_Weight ?? '', $obj->Qty_Cartons ?? '');
		array_walk($lineData, 'filterData');
		$excelData .= implode("\t", array_values($lineData))."\n";
	}
} else {
	$excelData .= "No records found...\n";
}

header('Content-Transfer-Encoding: binary');
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"".$fileName."\"");
header('Pragma: no-cache');
header('Expires: 0');
echo chr(255).chr(254).iconv("UTF-8", "UTF-16LE//IGNORE", $excelData);
exit;
