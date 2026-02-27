<?php
/* Copyright (C) 2025 SLY Custom
 * SLY ALL-in-One: triggers sequential download of all SLY export reports.
 */

require_once __DIR__.'/../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

if (!isModEnabled('slycustom')) {
	accessforbidden();
	exit;
}

$langs->load("other");

$report_files = array(
	'SLY SO Details'        => 'export_SO_Details.php',
	'SLY SO Inv Details'    => 'export_SO_Inv_Details.php',
	'SLY PO Details'        => 'export_PO_Details.php',
	'SLY PO Inv Details'    => 'export_PO_Inv_Details.php',
	'SLY Shipment Details'  => 'export_Shipment_Details.php',
);
$js_report_files = json_encode($report_files);

llxHeader('', $langs->trans("AutomaticReportDownload"), 'EN:Automatic_Reports_En|FR:Automatic_Reports');

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
    const reportFiles = <?php echo $js_report_files; ?>;
    const fileListElement = document.getElementById('file-list');
    const statusElement = document.getElementById('status');
    let fileIndex = 0;
    const fileKeys = Object.keys(reportFiles);

    const fileListItems = [];
    fileKeys.forEach(function(displayName) {
        const listItem = document.createElement('li');
        listItem.textContent = displayName;
        listItem.style.color = 'black';
        fileListElement.appendChild(listItem);
        fileListItems.push(listItem);
    });

    function downloadFile(fileName) {
        const form = document.createElement('form');
        form.action = fileName;
        form.method = 'post';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'download_xls';
        input.value = '1';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function startDownloads() {
        if (fileIndex < fileKeys.length) {
            if (fileIndex > 0) {
                fileListItems[fileIndex - 1].style.color = '#28a745';
            }
            const displayName = fileKeys[fileIndex];
            const fileName = reportFiles[displayName];
            statusElement.textContent = 'Preparing download for: ' + displayName;
            statusElement.style.color = '#dc3545';
            fileListItems[fileIndex].style.color = '#ffc107';
            downloadFile(fileName);
            fileIndex++;
            setTimeout(startDownloads, 10000);
        } else {
            if (fileIndex > 0) fileListItems[fileIndex - 1].style.color = '#28a745';
            statusElement.textContent = 'All downloads have been initiated.';
            statusElement.style.color = '#28a745';
        }
    }
    window.onload = startDownloads;
</script>
