<?php
// download_automator.php
// Based on export.php template to keep the same menu and layout.

require_once '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Load translation files required by the page
$langs->load("other");

// We'll use this array to hold the list of files to download
// The key is the user-friendly name, and the value is the filename.
$report_files = array(
    'SLY SO Details'        => 'export_SO_Details.php',
    'SLY SO Inv Details'    => 'export_SO_Inv_Details.php',
    'SLY PO Details'        => 'export_PO_Details.php',
    'SLY PO Inv Details'    => 'export_PO_Inv_Details.php',
    'SLY Shipment Details'  => 'export_Shipment_Details.php',
);
// We need to pass the PHP array to JavaScript.
$js_report_files = json_encode($report_files);

llxHeader('', $langs->trans("AutomaticReportDownload"), 'EN:Automatic_Reports_En|FR:Automatic_Reports');

// This part of the code is the main content area, following the standard Dolibarr template.
print dol_get_fiche_head(array(), 0, '', -2);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

print '<div class="download-container" style="text-align: center;">';
print '<h1>'.$langs->trans("Downloading").'...</h1>';
print '<p>'.$langs->trans("Your browser will now prompt you to download the following files one by one:").'</p>';
print '<ul id="file-list" style="list-style: none; padding: 0; margin: 20px auto; max-width: 400px; text-align: left;"></ul>';
print '<p id="status" style="margin-top: 20px; font-style: italic;"></p>';
print '</div>';

print '</div>';

llxFooter();

$db->close();

?>

<script>
    // Get the list of files from the PHP variable
    const reportFiles = <?php echo $js_report_files; ?>;
    const fileListElement = document.getElementById('file-list');
    const statusElement = document.getElementById('status');
    let fileIndex = 0;
    const fileKeys = Object.keys(reportFiles);

    // Create list items for each report file with default black color
    const fileListItems = [];
    fileKeys.forEach(displayName => {
        const listItem = document.createElement('li');
        listItem.textContent = displayName;
        listItem.style.color = 'black'; // Default color for files yet to be triggered
        fileListElement.appendChild(listItem);
        fileListItems.push(listItem);
    });

    // Function to create and submit a form for a single file download
    function downloadFile(fileName) {
        const form = document.createElement('form');
        form.action = fileName;
        form.method = 'post';
        // The download_xls hidden input tells the PHP script to initiate the download
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'download_xls';
        input.value = '1';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form); // Clean up the form element
    }

    // Function to loop through the files and trigger downloads
    function startDownloads() {
        if (fileIndex < fileKeys.length) {
            // Set the previous item to green
            if (fileIndex > 0) {
                fileListItems[fileIndex - 1].style.color = '#28a745'; // Green for downloaded files
            }

            const displayName = fileKeys[fileIndex];
            const fileName = reportFiles[displayName];

            statusElement.textContent = `Preparing download for: ${displayName}`;
            statusElement.style.color = '#dc3545'; // A shade of red for emphasis
            
            // Set the current item to amber yellow
            fileListItems[fileIndex].style.color = '#ffc107'; // Amber yellow for current download

            // Initiate the download
            downloadFile(fileName);

            // Move to the next file after a short delay
            fileIndex++;
            // Increased the timeout to 10 seconds to give the server and browser time to process the download.
            // You can adjust this value (in milliseconds) if needed.
            setTimeout(startDownloads, 10000); 
        } else {
            // Set the final item to green
            fileListItems[fileIndex - 1].style.color = '#28a745'; // Green for downloaded files

            statusElement.textContent = 'All downloads have been initiated.';
            statusElement.style.color = '#28a745'; // Change to green when all downloads are done
        }
    }
    
    // Start the process when the page loads
    window.onload = startDownloads;

</script>
