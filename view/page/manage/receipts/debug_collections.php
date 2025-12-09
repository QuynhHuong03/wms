<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<h2>Debug MongoDB Collections</h2>";

// List all collections
$collections = $db->listCollections();

echo "<h3>All Collections in Database:</h3>";
echo "<ul>";
foreach ($collections as $collection) {
    $name = $collection->getName();
    $count = $db->$name->countDocuments([]);
    echo "<li><strong>$name</strong> - $count documents</li>";
}
echo "</ul>";

// Check transactions collection specifically
echo "<h3>Transactions Collection:</h3>";
$totalTransactions = $db->transactions->countDocuments([]);
echo "<p>Total documents: $totalTransactions</p>";

if ($totalTransactions > 0) {
    // Sample first 5 transactions
    echo "<h4>Sample Transactions:</h4>";
    $samples = $db->transactions->find([], ['limit' => 5])->toArray();
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Transaction ID</th><th>Transaction Type</th><th>Type</th><th>Warehouse</th><th>Status</th></tr>";
    foreach ($samples as $s) {
        echo "<tr>";
        echo "<td>" . (string)$s['_id'] . "</td>";
        echo "<td>" . ($s['transaction_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($s['transaction_type'] ?? 'NULL') . "</td>";
        echo "<td>" . ($s['type'] ?? 'NULL') . "</td>";
        echo "<td>" . ($s['warehouse_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($s['status'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check for exports collection (might be named differently)
$possibleNames = ['exports', 'export_receipts', 'export_transactions', 'receipts'];
echo "<h3>Checking Alternative Collection Names:</h3>";
foreach ($possibleNames as $name) {
    try {
        $count = $db->$name->countDocuments([]);
        if ($count > 0) {
            echo "<p><strong>$name:</strong> $count documents found!</p>";
            $sample = $db->$name->findOne([]);
            if ($sample) {
                echo "<pre>" . json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            }
        }
    } catch (Exception $e) {
        // Collection doesn't exist
    }
}

// Search for EX0007 specifically across all collections
echo "<h3>Searching for EX0007:</h3>";
foreach ($collections as $collection) {
    $name = $collection->getName();
    $result = $db->$name->findOne(['transaction_id' => 'EX0007']);
    if ($result) {
        echo "<p><strong>Found in collection: $name</strong></p>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
}
?>
