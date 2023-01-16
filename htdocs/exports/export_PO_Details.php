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
$fileName = "SLY_PO_Details.xls"; 

// Column names 
$fields = array('Purchase Person', 'SO No', 'Supplier_No', 'Supplier', 'Date of Order', 'Status', 'Billed?', 'Payment Term', 'Incoterm', 'POA', 'Currency', 'Total','Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total'); 
 
// Display column names as first row 
$excelData = implode("\t", array_values($fields)) . "\n"; 
 
// Fetch records from database 
$query = $db->query("SELECT * FROM view_PO AS PO LEFT JOIN view_PO_details AS d ON PO.PO_ID = d.PO_ID ORDER BY PO.PO_ID DESC LIMIT 1000"); 
if($query->num_rows > 0){ 
    // Output each row of the data 
    while($row = $query->fetch_assoc()){ 
        $lineData = array($row['SalesPerson'], $row['SO_No'], $row['Supplier_No'], $row['Supplier'], $row['Date_Order'], $row['fk_statut'], $row['Billed'], $row['Payment_Term'], $row['Incoterm'], $row['Port_Arrival'], $row['Currency'], $row['Total'], $row['Note_Private'], $row['Note_Public'], $row['Product'], $row['description'], $row['Qty'], $row['Unit'], $row['Price'], $row['SubTotal']); 
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