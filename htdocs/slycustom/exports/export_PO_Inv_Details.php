<?php
/* Copyright (C) 2025 SLY Custom
 * SLY PO Invoice Details export (Excel).
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

$fileName = "SLY_PO_Inv_details.xls";
$fields = array('SO No', 'PO No', 'Supplier_No', 'ATA', 'Inv No', 'Supplier', 'Invoice Date', 'Due Date', 'Paid', 'Status', 'Latest Payment', 'Currency', 'Amount Paid', 'Pending Amount', 'Fee', 'Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total');

$excelData = implode("\t", array_values($fields))."\n";

$sql = "SELECT * FROM view_PO_Inv_details_payment_2 WHERE PO_No IS NOT NULL ORDER BY Inv_ID DESC LIMIT 1500";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$lineData = array($obj->SO_No ?? '', $obj->PO_No ?? '', $obj->Supplier_No ?? '', $obj->ATA ?? '', $obj->Inv_No ?? '', $obj->Supplier ?? '', $obj->Date_Inv ?? '', $obj->Date_Due ?? '', $obj->Paid ?? '', $obj->fk_statut ?? '', $obj->Date_Payment_Latest ?? '', $obj->Currency ?? '', $obj->Amount_Paid ?? '', $obj->Pending_Payment ?? '', $obj->Fees_or_Loss ?? '', $obj->Note_Private ?? '', $obj->Note_Public ?? '', $obj->Product ?? '', $obj->Description ?? '', $obj->Qty ?? '', $obj->Unit ?? '', $obj->Price ?? '', $obj->SubTotal ?? '');
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
