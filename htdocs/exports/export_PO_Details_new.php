<?php
//export.php  

require_once '../main.inc.php';

@ini_set('max_execution_time', 180);

// Excel file name for download 
$fileName = "SLY_PO_Details.xls"; 

$result= $db->query("SELECT * FROM view_PO AS PO LEFT JOIN view_PO_details AS d ON PO.PO_ID = d.PO_ID ORDER BY PO.PO_ID DESC LIMIT 1000");
$output = '';
if(mysqli_num_rows($result) > 0) {
	$output .= '
		<table class="table" bordered="1">  
			<tr>  
				<th>SO No</th>  
				<th>PO No</th>  
				<th>Ref Supplier</th>  
				<th>Supplier</th>
				<th>Date of Order</th>
				<th>Status</th>
				<th>Billed?</th>
				<th>Payment Term</th>
				<th>Incoterm</th>
				<th>POA</th>
				<th>Currency</th>
				<th>Total</th>
				<th>Note_Private</th>
				<th>Note_Public</th>
				<th>Product</th>
				<th>Description</th>
				<th>Qty</th>
				<th>Unit</th>
				<th>Price</th>
				<th>Sub Total</th>
			</tr>
		';
	while($row = mysqli_fetch_array($result))
	{
		$output .= '
			<tr>  
				<td>'.$row["SO_No"].'</td>  
				<td>'.$row["PO_No"].'</td>  
				<td>'.$row["Ref_Supplier"].'</td>  
				<td>'.$row["Supplier"].'</td>  
				<td>'.$row["Date_Order"].'</td>
				<td>'.$row["fk_statut"].'</td>
				<td>'.$row["Billed"].'</td>
				<td>'.$row["Payment_Term"].'</td>
				<td>'.$row["Incoterm"].'</td>
				<td>'.$row["Port_Arrival"].'</td>
				<td>'.$row["Currency"].'</td>
				<td>'.$row["Total"].'</td>
				<td>'.$row["Note_Private"].'</td>
				<td>'.$row["Note_Public"].'</td>
				<td>'.$row["Product"].'</td>
				<td>'.$row["description"].'</td>
				<td>'.$row["Qty"].'</td>
				<td>'.$row["Unit"].'</td>
				<td>'.$row["Price"].'</td>
				<td>'.$row["SubTotal"].'</td>
			</tr>
		';
	}
	$output .= '</table>';
} else{ 
    $output .= 'No records found...'. "\n"; 
} 

header("Content-Type: application/xls"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 
header('Pragma: no-cache');
header('Expires: 0');

// Render excel data 
echo $output; 
exit;

?>
