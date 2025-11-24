<?php
require_once(__DIR__ . "/../../../../vendor/autoload.php");
use MongoDB\Client;

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{font-family:Arial;padding:20px;} .ok{color:green;} .err{color:red;}</style></head><body>";
echo "<h2>🔍 Quick Database Check</h2>";

$client = new Client('mongodb://127.0.0.1:27017');

echo "<h3>Databases:</h3>";
foreach ($client->listDatabases() as $db) {
    echo "- <strong>" . $db['name'] . "</strong><br>";
}

echo "<h3>Check 'wms':</h3>";
$wms = $client->selectDatabase('wms');
$trans_wms = $wms->selectCollection('transactions');
$count_wms = $trans_wms->countDocuments(['transaction_type' => 'export']);
echo "Exports in 'wms': <span class='" . ($count_wms > 0 ? 'ok' : 'err') . "'>$count_wms</span><br>";

echo "<h3>Check 'WMS' (uppercase):</h3>";
$WMS = $client->selectDatabase('WMS');
$trans_WMS = $WMS->selectCollection('transactions');
$count_WMS = $trans_WMS->countDocuments(['transaction_type' => 'export']);
echo "Exports in 'WMS': <span class='" . ($count_WMS > 0 ? 'ok' : 'err') . "'>$count_WMS</span><br>";

if ($count_WMS > 0) {
    echo "<h3>Sample exports in WMS:</h3>";
    $samples = $trans_WMS->find(['transaction_type' => 'export'], ['limit' => 5]);
    foreach ($samples as $s) {
        echo "- " . ($s['transaction_id'] ?? 'N/A') . " (" . ($s['warehouse_id'] ?? 'N/A') . " → " . ($s['destination_warehouse_id'] ?? 'N/A') . ")<br>";
    }
}

echo "<hr><h3>✅ Solution:</h3>";
if ($count_WMS > 0 && $count_wms == 0) {
    echo "<p class='ok'>Data is in <strong>'WMS'</strong> database. Need to change connect.php to use 'WMS' instead of 'wms'</p>";
} elseif ($count_wms > 0) {
    echo "<p class='ok'>Data is in <strong>'wms'</strong> database. Connect.php is correct.</p>";
}

echo "</body></html>";
?>
