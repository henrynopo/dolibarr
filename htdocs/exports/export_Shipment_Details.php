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
$fileName = "SLY_Shipment.xls"; 

// Column names 
$fields = array('SO No', 'Supplier No', 'Shipment No', 'Customer', 'Status', 'Billed?', 'POL', 'ATD', 'POD', 'ATA', 'ETD', 'ETA', 'Shipment Company', 'Container No', 'BL_No', 'HC_No','Note_Private', 'Note_Public', 'Product', 'Unit', 'Net Weight', 'Gross Weight', 'Cartons'); 
 
// Display column names as first row 
$excelData = implode("\t", array_values($fields)) . "\n"; 
 
// Fetch records from database 
$query = $db->query("SELECT * FROM view_shipment AS s LEFT JOIN view_shipment_details AS d ON s.Shipment_ID = d.fk_expedition ORDER BY Shipment_ID DESC LIMIT 1000"); 
if($query->num_rows > 0){ 
    // Output each row of the data 
    while($row = $query->fetch_assoc()){ 
        $lineData = array($row['SO_No'], $row['Supplier_No'], $row['Shipment_No'], $row['Customer'], $row['fk_statut'], $row['billed'], $row['POL'], $row['ATD'], $row['POD'], $row['ATA'], $row['ETD'], $row['ETA'], $row['Shipment_Company'], $row['Container_No'], $row['BL_No'], $row['HC_No'], $row['Note_Private'], $row['Note_Public'], $row['Product'], $row['Unit'], $row['Net_Weight'], $row['Gross_Weight'], $row['Qty_Cartons']); 
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