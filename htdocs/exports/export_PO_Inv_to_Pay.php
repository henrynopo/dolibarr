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
$fileName = "SLY_PO_Inv_to_Pay.xls"; 

// Column names 
$fields = array('SO No', 'PO No', 'ATA', 'PO Inv No', 'Supplier', 'Inv Date', 'Currency', 'Latest Payment', 'Pending Amount');
 
// Display column names as first row 
$excelData = implode("\t", array_values($fields)) . "\n"; 

// Fetch records from database 
$sql = "SELECT SO_No, Supplier_No, ATA, Inv_No, Supplier, Date_Inv, Currency, Date_Payment_Latest, Pending_Payment";
$sql .= " FROM view_PO_Inv_not_paid";

$query = $db->query($sql); 

if($query->num_rows > 0){ 
    // Output each row of the data 
    while($row = $query->fetch_assoc()){ 
        $lineData = array($row['SO_No'], $row['Supplier_No'], $row['ATA'], $row['Inv_No'],  $row['Supplier'], $row['Date_Inv'], $row['Currency'], $row['Date_Payment_Latest'], $row['Pending_Payment']); 
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
