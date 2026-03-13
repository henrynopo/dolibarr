<?php
/* Copyright (C) 2025 SLY Custom
 * SLY SO Invoice Details export (Excel).
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

$fileName = "SLY_SO_Inv_details.xls";
$fields = array('Salesperson', 'SO No', 'Supplier No', 'ATA', 'Inv No', 'Customer', 'Bill To', 'Invoice Date', 'Due Date', 'Paid', 'Status', 'Latest Payment', 'Currency', 'Amount Received', 'Pending Amount', 'Fee', 'Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total', 'Date_Cust_Pay', 'Date_Supp_Doc_Deliver', 'Supp_Courrier', 'Supp_Courrier_No', 'Recipient', 'Date_Supp_Doc_Received', 'Date_TR', 'Date_SLY_Doc_Deliver', 'SLY_Courrier', 'SLY_Courrier_No', 'Remark');

$excelData = implode("\t", array_values($fields))."\n";

$sql = "SELECT * FROM view_SO_Inv_details_payment_2 WHERE SO_No IS NOT NULL ORDER BY Inv_ID DESC LIMIT 1500";
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
	while ($obj = $db->fetch_object($resql)) {
		$lineData = array($obj->Salesperson ?? '', $obj->SO_No ?? '', $obj->Supplier_No ?? '', $obj->ATA ?? '', $obj->Inv_No ?? '', $obj->Customer ?? '', $obj->Billing_Company ?? '', $obj->Date_Inv ?? '', $obj->Date_Due ?? '', $obj->Paid ?? '', $obj->fk_statut ?? '', $obj->Date_Payment_Latest ?? '', $obj->Currency ?? '', $obj->Amount_Received ?? '', $obj->Pending_Payment ?? '', $obj->Fees_or_Loss ?? '', $obj->Note_Private ?? '', $obj->Note_Public ?? '', $obj->Product ?? '', $obj->Description ?? '', $obj->Qty ?? '', $obj->Unit ?? '', $obj->Price ?? '', $obj->SubTotal ?? '', $obj->Date_Cust_Pay ?? '', $obj->Date_Supp_Doc_Deliver ?? '', $obj->Supp_Courrier ?? '', $obj->Supp_Courrier_No ?? '', $obj->Recipient ?? '', $obj->Date_Supp_Doc_Received ?? '', $obj->Date_TR ?? '', $obj->Date_SLY_Doc_Deliver ?? '', $obj->SLY_Courrier ?? '', $obj->SLY_Courrier_No ?? '', $obj->Remark ?? '');
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
