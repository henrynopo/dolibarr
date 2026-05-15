<?php
require 'main.inc.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');
echo "Checking classes...<br>";
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
$o = new FactureFournisseur($db);
echo "FactureFournisseur loaded.<br>";
if (method_exists($o, 'getSubtotalColors')) {
    echo "getSubtotalColors EXISTS!<br>";
} else {
    echo "getSubtotalColors MISSING!<br>";
}
echo "Debug: CommonSubtotal trait check...<br>";
// phpinfo(); // Commented out for now to keep output clean, will enable if needed.
