<?php

// Load Dolibase
include_once '../autoload.php';
// Load Dolibase AboutPage class
dolibase_include_once('/core/pages/about.php');

// Create About Page using Dolibase
$page = new AboutPage('About', '$user->admin');

$page->begin();

$page->printModuleInformations('translate.png');

print '<br>';
print '<div class="info">';
print '<b>Customized version</b><br>';
print 'This module has been customized for this Dolibarr instance (Dolibarr 22.0.x):<br>';
print '<ul style="margin:6px 0 0 18px;">';
print '<li>Language picker button moved into top header (between version and user menu) to match UI layout.</li>';
print '<li>Top header styling adjusted to follow the active theme (font, alignment, spacing, dropdown).</li>';
print '<li>Legacy pixel-position setting (<code>LANG_PICKER_POSITION</code>) removed from setup and cleaned up from database.</li>';
print '</ul>';
print '</div>';

$page->end();
