<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' =&gt; '/', 'secure' =&gt; false, 'httponly' =&gt; true, 'samesite' =&gt; 'Lax']); session_start(); }

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body>";
echo "<h2>🔍 Database & Collection Check</h2><hr>";

require_once(__DIR__ . "/../../../../vendor/autoload.php");

use MongoDB\Client;

$client = new Client('mongodb://127.0.0.1:27017');

echo "<h3>1. Available Databases:</h3>";
$databases = $client->listDatabases();
foreach ($databases as $db) {
    echo "- " . $db['name'] . " (size: " . ($db['sizeOnDisk'] ?? 0) . " bytes)<br>";
}

echo "<h3>2. Check database 'wms' (lowercase):</h3>";
$db_wms = $client->selectDatabase('wms');
$collections = iterator_to_array($db_wms->listCollections());
echo "Collections in 'wms': " . count($collections) . "<br>";
foreach ($collections as $col) {
    $name = $col->getName();
    $count = $db_wms->selectCollection($name)->countDocuments();
    echo "- $name: $count documents<br>";
}

echo "<h3>3. Check database 'WMS' (uppercase):</h3>";
$db_WMS = $client->selectDatabase('WMS');
$collections = iterator_to_array($db_WMS->listCollections());
echo "Collections in 'WMS': " . count($collections) . "<br>";
foreach ($collections as $col) {
    $name = $col->getName();
    $count = $db_WMS->selectCollection($name)->countDocuments();
    echo "- $name: $count documents<br>";
}

echo "<h3>4. Search for export EX0004:</h3>";

// Try in wms
$transCol_wms = $db_wms->selectCollection('transactions');
$export_wms = $transCol_wms->findOne(['transaction_id' => 'EX0004']);
if ($export_wms) {
    echo "✅ Found in 'wms' database!<br>";
} else {
    echo "❌ Not found in 'wms' database<br>";
}

// Try in WMS
$transCol_WMS = $db_WMS->selectCollection('transactions');
$export_WMS = $transCol_WMS->findOne(['transaction_id' => 'EX0004']);
if ($export_WMS) {
    echo "✅ Found in 'WMS' database!<br>";
    echo "<pre>";
    print_r($export_WMS);
    echo "</pre>";
} else {
    echo "❌ Not found in 'WMS' database<br>";
}

echo "<hr><a href='index.php?page=exports'>Back to exports</a>";
echo "</body></html>";
?>
