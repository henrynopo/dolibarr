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
$fileName = "SLY_SO_Inv_to_Receive.xls"; 

// Column names 
$fields = array('Salesperson', 'SO No', 'Inv No', 'Invoice Date', 'ATA', 'Customer', 'Bill To', 'Currency', 'Latest Payment', 'Pending Amount', 'Fee'); 
 
// Display column names as first row 
$excelData = implode("\t", array_values($fields)) . "\n"; 

// Fetch records from database 
$sql = "SELECT view_SO.SalesPerson,";
$sql .= " view_SO_Inv_to_receive.SO_No, view_SO_Inv_to_receive.Inv_No, view_SO_Inv_to_receive.Date_Inv, view_SO_Inv_to_receive.ATA, view_SO_Inv_to_receive.Customer, view_SO_Inv_to_receive.Billing_Company, view_SO_Inv_to_receive.currency, view_SO_Inv_to_receive.Date_Payment_Latest, view_SO_Inv_to_receive.Pending_Payment, view_SO_Inv_to_receive.Fees_or_Loss";
$sql .= " FROM view_SO_Inv_to_receive";
$sql .= " LEFT JOIN view_SO ON view_SO_Inv_to_receive.SO_No = view_SO.SO_No";

$query = $db->query($sql); 

if($query->num_rows > 0){ 
    // Output each row of the data 
    while($row = $query->fetch_assoc()){ 
        $lineData = array($row['SalesPerson'], $row['SO_No'], $row['Inv_No'],  $row['Date_Inv'], $row['ATA'], $row['Customer'], $row['Billing_Company'], $row['Currency'], $row['Date_Payment_Latest'], $row['Pending_Payment'], $row['Fees_or_loss']); 
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
