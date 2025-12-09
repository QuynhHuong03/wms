<?php
header('Content-Type: text/html; charset=UTF-8');
include_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

$sourceWarehouse = $_GET['source'] ?? 'KHO_TONG_01';
$destinationWarehouse = $_GET['dest'] ?? 'KHO_CN_03';

echo "<h2>Debug Export Receipts</h2>";
echo "<p><strong>Database:</strong> " . $db->getDatabaseName() . "</p>";
echo "<p><strong>Source Warehouse:</strong> $sourceWarehouse</p>";
echo "<p><strong>Destination Warehouse:</strong> $destinationWarehouse</p>";

echo "<h3>All Transactions in Database:</h3>";
$totalTrans = $db->transactions->countDocuments([]);
echo "<p>Total transactions: $totalTrans</p>";

echo "<h3>All Exports in Database:</h3>";
$allExports = $db->transactions->find(['transaction_type' => 'export'], ['limit' => 20])->toArray();
echo "<p>Total exports found: " . count($allExports) . "</p>";

// Also try without transaction_type filter
$allExportsAlt = $db->transactions->find(['type' => 'export'], ['limit' => 20])->toArray();
echo "<p>Total with 'type'='export': " . count($allExportsAlt) . "</p>";

// Try finding EX0007 directly
$ex0007 = $db->transactions->findOne(['transaction_id' => 'EX0007']);
echo "<h3>Direct search for EX0007:</h3>";
if ($ex0007) {
    echo "<pre>" . json_encode($ex0007, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
} else {
    echo "<p>Not found!</p>";
}
echo "<p>Total exports found: " . count($allExports) . "</p>";

echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
echo "<tr><th>Transaction ID</th><th>Warehouse ID</th><th>Destination</th><th>Status</th><th>Type</th><th>Created</th></tr>";
foreach ($allExports as $exp) {
    $tid = $exp['transaction_id'] ?? 'NO_ID';
    $wid = $exp['warehouse_id'] ?? 'NULL';
    $dest = $exp['destination_warehouse_id'] ?? 'NULL';
    $status = $exp['status'] ?? 'NULL';
    $type = $exp['type'] ?? 'NULL';
    $created = isset($exp['created_at']) ? date('d/m/Y H:i', $exp['created_at']->toDateTime()->getTimestamp()) : 'NULL';
    
    // Highlight matching row
    $highlight = ($wid === $sourceWarehouse && $dest === $destinationWarehouse) ? "background:#90EE90" : "";
    
    echo "<tr style='$highlight'>";
    echo "<td>$tid</td>";
    echo "<td>'$wid'</td>";
    echo "<td>'$dest'</td>";
    echo "<td>$status</td>";
    echo "<td>$type</td>";
    echo "<td>$created</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Query with Filter:</h3>";
$filter = [
    'transaction_type' => 'export',
    'warehouse_id' => $sourceWarehouse,
    'destination_warehouse_id' => $destinationWarehouse,
    '$or' => [
        ['status' => 1],
        ['status' => '1']
    ]
];
echo "<pre>" . json_encode($filter, JSON_PRETTY_PRINT) . "</pre>";

$filtered = $db->transactions->find($filter)->toArray();
echo "<p><strong>Results:</strong> " . count($filtered) . " exports</p>";

if (count($filtered) > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>Transaction ID</th><th>Warehouse ID</th><th>Destination</th><th>Status</th></tr>";
    foreach ($filtered as $exp) {
        echo "<tr>";
        echo "<td>" . ($exp['transaction_id'] ?? 'NO_ID') . "</td>";
        echo "<td>" . ($exp['warehouse_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($exp['destination_warehouse_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($exp['status'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>
