<?php
/* Copyright (C) 2025 SLY Custom
 * SLY PO Details export (Excel).
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

$fileName = "SLY_PO_Details.xls";
$fields = array('Purchase Person', 'SO No', 'PO No', 'Supplier_No', 'Supplier', 'Date of Order', 'Status', 'Billed?', 'Payment Term', 'Incoterm', 'POA', 'Currency', 'Total', 'Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total');

$excelData = implode("\t", array_values($fields))."\n";

$sql = "SELECT * FROM view_PO AS PO LEFT JOIN view_PO_details AS d ON PO.PO_ID = d.PO_ID ORDER BY PO.PO_ID DESC LIMIT 1000";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$lineData = array($obj->SalesPerson ?? '', $obj->SO_No ?? '', $obj->PO_No ?? '', $obj->Supplier_No ?? '', $obj->Supplier ?? '', $obj->Date_Order ?? '', $obj->fk_statut ?? '', $obj->Billed ?? '', $obj->Payment_Term ?? '', $obj->Incoterm ?? '', $obj->Port_Arrival ?? '', $obj->Currency ?? '', $obj->Total ?? '', $obj->Note_Private ?? '', $obj->Note_Public ?? '', $obj->Product ?? '', $obj->description ?? '', $obj->Qty ?? '', $obj->Unit ?? '', $obj->Price ?? '', $obj->SubTotal ?? '');
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
