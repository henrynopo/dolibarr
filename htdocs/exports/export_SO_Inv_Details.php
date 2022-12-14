<?php

require_once '../main.inc.php';

@ini_set('max_execution_time', 180);

// Filter the excel data 
function filterData(&$str){ 
	$str = preg_replace("/\t/", "\\t", $str); 
	$str = preg_replace("/\r?\n/", "\\n", $str); 
	if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
} 

// Excel file name for download 
$fileName = "SLY_SO_Inv_details.xls"; 

// Column names 
$fields = array('SO No', 'ATA', 'Inv No', 'Customer', 'Bill To', 'Invoice Date', 'Due Date', 'Paid', 'Status', 'Latest Payment', 'Currency', 'Amount Received', 'Pending Amount', 'Fee', 'Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total', 'Date_Cust_Pay', 'Date_Supp_Doc_Deliver', 'Supp_Courrier', 'Supp_Courrier_No', 'Recipient', 'Date_Supp_Doc_Received', 'Date_TR', 'Date_SLY_Doc_Deliver', 'SLY_Courrier', 'SLY_Courrier_No', 'Remark'); 
 
// Display column names as first row 
$excelData = implode("\t", array_values($fields)) . "\n"; 
 
// Fetch records from database 
$query = $db->query("SELECT * FROM view_SO_Inv_details_payment WHERE SO_No IS NOT NULL  ORDER BY Inv_ID DESC LIMIT 1500"); 
if($query->num_rows > 0){ 
	// Output each row of the data 
	while($row = $query->fetch_assoc()){ 
		$lineData = array($row['SO_No'], $row['ATA'], $row['Inv_No'], $row['Customer'], $row['Billing_Company'], $row['Date_Inv'], $row['Date_Due'], $row['Paid'], $row['fk_statut'], $row['Date_Payment_Latest'], $row['Currency'], $row['Amount_Received'], $row['Pending_Payment'], $row['Fees_or_Loss'], $row['Note_Private'], $row['Note_Public'], $row['Product'], $row['Description'], $row['Qty'], $row['Unit'], $row['Price'], $row['SubTotal'], $row['Date_Cust_Pay'], $row['Date_Supp_Doc_Deliver'], $row['Supp_Courrier'], $row['Supp_Courrier_No'], $row['Recipient'], $row['Date_Supp_Doc_Received'], $row['Date_TR'], $row['Date_SLY_Doc_Deliver'], $row['SLY_Courrier'], $row['SLY_Courrier_No'], $row['Remark']); 
		array_walk($lineData, 'filterData'); 
		$excelData .= implode("\t", array_values($lineData)) . "\n"; 
	} 
}else{ 
	$excelData .= 'No records found...'. "\n"; 
} 
 
// Headers for download 
header('Content-Transfer-Encoding: binary');
header("Content-Type: application/octet-stream"); 
header("Content-Transfer-Encoding: binary");
header("Content-Disposition: attachment; filename=\"$fileName\""); 
header('Pragma: no-cache');
header('Expires: 0');
 
// Render excel data 
echo chr(255).chr(254).iconv("UTF-8", "UTF-16LE//IGNORE", $excelData); 
 
exit;