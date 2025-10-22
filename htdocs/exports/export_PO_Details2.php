<?php

require_once '../main.inc.php';

// Check if a download request has been made
$download_requested = isset($_POST['download_xls']) && $_POST['download_xls'] == '1';

// Set headers for download only if requested
if ($download_requested) {
    @ini_set('max_execution_time', 180);
    $fileName = "SLY_PO_Details.xls";
    header('Content-Transfer-Encoding: binary');
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Filter the excel data
function filterData(&$str){
    $str = preg_replace("/\t/", "\\t", $str);
    $str = preg_replace("/\r?\n/", "\\n", $str);
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
}

// Column names
$fields = array('Purchase Person', 'SO No', 'PO No', 'Supplier_No', 'Supplier', 'Date of Order', 'Status', 'Billed?', 'Payment Term', 'Incoterm', 'POA', 'Currency', 'Total','Note_Private', 'Note_Public', 'Product', 'Description', 'Qty', 'Unit', 'Price', 'Sub Total');
$database_columns = array('SalesPerson', 'SO_No', 'PO_No', 'Supplier_No', 'Supplier', 'Date_Order', 'fk_statut', 'Billed', 'Payment_Term', 'Incoterm', 'Port_Arrival', 'Currency', 'Total','Note_Private', 'Note_Public', 'Product', 'description', 'Qty', 'Unit', 'Price', 'SubTotal');


// Build the WHERE clause based on filtered data from the form
$where_clause = '';
if ($download_requested) {
    $filter_clauses = array();
    foreach ($database_columns as $index => $column) {
        $filter_key = 'filter_' . $index;
        if (isset($_POST[$filter_key]) && !empty($_POST[$filter_key])) {
            $filter_value = $db->real_escape_string($_POST[$filter_key]);
            $filter_clauses[] = "$column LIKE '%$filter_value%'";
        }
    }
    if (!empty($filter_clauses)) {
        $where_clause = ' WHERE ' . implode(' AND ', $filter_clauses);
    }
}


// Fetch records from database
$sql = "SELECT * FROM view_PO AS PO LEFT JOIN view_PO_details AS d ON PO.PO_ID = d.PO_ID" . $where_clause . " ORDER BY PO.PO_ID DESC LIMIT 1000";
$query = $db->query($sql);


// Check if any records were found
if($query->num_rows > 0){
    // If download is requested, prepare the data for the excel file
    if ($download_requested) {
        $excelData = implode("\t", array_values($fields)) . "\n";
        while($row = $query->fetch_assoc()){
            $lineData = array($row['SalesPerson'], $row['SO_No'], $row['PO_No'], $row['Supplier_No'], $row['Supplier'], $row['Date_Order'], $row['fk_statut'], $row['Billed'], $row['Payment_Term'], $row['Incoterm'], $row['Port_Arrival'], $row['Currency'], $row['Total'], $row['Note_Private'], $row['Note_Public'], $row['Product'], $row['description'], $row['Qty'], $row['Unit'], $row['Price'], $row['SubTotal']);
            array_walk($lineData, 'filterData');
            $excelData .= implode("\t", array_values($lineData)) . "\n";
        }
        echo chr(255).chr(254).iconv("UTF-8", "UTF-16LE//IGNORE", $excelData);
        exit;
    } else {
        // Otherwise, display the data in an HTML table with filtering
        echo "<!DOCTYPE html>
              <html lang='en'>
              <head>
                  <meta charset='UTF-8'>
                  <title>PO Details</title>
                  <style>
                      body { font-family: Arial, sans-serif; margin: 20px; }
                      table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
                      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                      th { background-color: #f2f2f2; position: sticky; top: 0; z-index: 1; }
                      .download-form { text-align: center; margin-top: 20px; }
                      .download-button { padding: 10px 20px; background-color: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 5px; font-size: 16px; }
                      .clear-button { padding: 10px 20px; background-color: #f44336; color: white; border: none; cursor: pointer; border-radius: 5px; font-size: 16px; margin-left: 10px; }
                      .download-button:hover { background-color: #45a049; }
                      .clear-button:hover { background-color: #d32f2f; }
                      .filter-input { width: 100%; box-sizing: border-box; padding: 4px; border: 1px solid #ccc; border-radius: 3px; margin-top: 4px; }
                  </style>
              </head>
              <body>
                  <h1>Purchase Order Details</h1>
                  <div class='download-form'>
                      <button type='button' class='clear-button' onclick='clearFilters()'>Clear Filters</button>
                      <form id='downloadForm' action='' method='POST' style='display:inline;'>
                          <input type='hidden' name='download_xls' value='1'>
                          <button type='button' class='download-button' onclick='downloadFilteredData()'>Download as XLS</button>
                      </form>
                  </div>
                  <table>
                      <thead>
                          <tr>";
        foreach($fields as $index => $field){
            echo "<th>" . htmlspecialchars($field);
            if ($index < 7) {
                echo "<br><input type='text' class='filter-input' onkeyup='filterTable()' placeholder='Filter...'>";
            }
            echo "</th>";
        }
        echo "          </tr>
                      </thead>
                      <tbody>";
        // Reset query pointer to loop through data again
        $query->data_seek(0);
        while($row = $query->fetch_assoc()){
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['SalesPerson']) . "</td>";
            echo "<td>" . htmlspecialchars($row['SO_No']) . "</td>";
            echo "<td>" . htmlspecialchars($row['PO_No']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Supplier_No']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Supplier']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Date_Order']) . "</td>";
            echo "<td>" . htmlspecialchars($row['fk_statut']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Billed']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Payment_Term']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Incoterm']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Port_Arrival']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Currency']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Total']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Note_Private']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Note_Public']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Product']) . "</td>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Qty']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Unit']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Price']) . "</td>";
            echo "<td>" . htmlspecialchars($row['SubTotal']) . "</td>";
            echo "</tr>";
        }
        echo "         </tbody>
                  </table>
                  <div class='download-form'>
                      <button type='button' class='clear-button' onclick='clearFilters()'>Clear Filters</button>
                      <form id='downloadFormBottom' action='' method='POST' style='display:inline;'>
                          <input type='hidden' name='download_xls' value='1'>
                          <button type='button' class='download-button' onclick='downloadFilteredData()'>Download as XLS</button>
                      </form>
                  </div>
                  <script>
                    function filterTable() {
                      const table = document.querySelector('table');
                      const tr = table.getElementsByTagName('tr');
                      const inputs = document.querySelectorAll('.filter-input');
                      
                      // Loop through all table rows (excluding the header)
                      for (let i = 1; i < tr.length; i++) {
                        let display = true;
                        // Loop through all filter inputs
                        for (let j = 0; j < inputs.length; j++) {
                          const filter = inputs[j].value.toUpperCase();
                          const td = tr[i].getElementsByTagName('td')[j];
                          if (td) {
                            const textValue = td.textContent || td.innerText;
                            if (filter && textValue.toUpperCase().indexOf(filter) === -1) {
                              display = false;
                              break;
                            }
                          }
                        }
                        tr[i].style.display = display ? '' : 'none';
                      }
                    }

                    function clearFilters() {
                      const inputs = document.querySelectorAll('.filter-input');
                      for (let i = 0; i < inputs.length; i++) {
                        inputs[i].value = '';
                      }
                      filterTable(); // Call filterTable to show all rows again
                    }

                    function downloadFilteredData() {
                      const inputs = document.querySelectorAll('.filter-input');
                      const form = document.getElementById('downloadForm'); // Use the top form by default
                      
                      // Clear previous filter inputs from the form
                      const oldFilters = form.querySelectorAll('input[name^=\"filter_\"]');
                      oldFilters.forEach(el => el.remove());
                      
                      // Add new hidden inputs for each filter value
                      inputs.forEach((input, index) => {
                          if (input.value) {
                              const hiddenInput = document.createElement('input');
                              hiddenInput.type = 'hidden';
                              hiddenInput.name = 'filter_' + index;
                              hiddenInput.value = input.value;
                              form.appendChild(hiddenInput);
                          }
                      });
                      
                      form.submit();
                    }
                  </script>
              </body>
              </html>";
    }
} else {
    // No records found
    if ($download_requested) {
        $excelData .= 'No records found...'. "\n";
        echo chr(255).chr(254).iconv("UTF-8", "UTF-16LE//IGNORE", $excelData);
        exit;
    } else {
        echo "<!DOCTYPE html>
              <html lang='en'>
              <head>
                  <meta charset='UTF-8'>
                  <title>PO Details</title>
              </head>
              <body>
                  <h1>No records found...</h1>
              </body>
              </html>";
    }
}
