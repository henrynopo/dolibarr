<?php
/* Copyright (C) 2025 SLY Custom
 * SLY SO Details export (Excel).
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

$fileName = "SLY_SO_Details.xls";
$fields = array('Salesperson', 'SO No', 'Supplier No', 'Customer', 'Cust_Contact', 'Bill_To', 'Date of Order', 'Status', 'Billed?', 'Shipment Schedule', 'Payment Term', 'Incoterm', 'POA', 'Consignee Appointed by', 'Currency', 'Total', 'Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total');

$excelData = implode("\t", array_values($fields))."\n";

$sql = "SELECT * FROM view_SO AS SO LEFT JOIN view_SO_details AS d ON SO.SO_ID = d.SO_ID ORDER BY SO.SO_ID DESC LIMIT 1000";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$lineData = array($obj->SalesPerson ?? '', $obj->SO_No ?? '', $obj->Supplier_No ?? '', $obj->Customer ?? '', $obj->Cust_Contact ?? '', $obj->Bill_To ?? '', $obj->Date_Order ?? '', $obj->fk_statut ?? '', $obj->Billed ?? '', $obj->Shipment_Schedule ?? '', $obj->Payment_Term ?? '', $obj->Incoterm ?? '', $obj->Port_Arrival ?? '', $obj->Consignee_Appointed_By ?? '', $obj->Currency ?? '', $obj->Total ?? '', $obj->Note_Private ?? '', $obj->Note_Public ?? '', $obj->Product ?? '', $obj->description ?? '', $obj->Qty ?? '', $obj->Unit ?? '', $obj->Price ?? '', $obj->SubTotal ?? '');
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
