<?php
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'>";
echo "</head><body class='p-4'>";

echo "<h2>🔍 Debug: Exports Filter Issue</h2><hr>";

include_once(__DIR__ . "/../../../../model/connect.php");

// User info
$user_id = $_SESSION['login']['user_id'] ?? 'NONE';
$warehouse_id = $_SESSION['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? null);

echo "<div class='alert alert-info'>";
echo "<h4>👤 Session Info:</h4>";
echo "<strong>User ID:</strong> " . htmlspecialchars($user_id) . "<br>";
echo "<strong>Warehouse ID:</strong> " . htmlspecialchars($warehouse_id ?? 'NULL') . "<br>";
echo "<strong>Role:</strong> " . htmlspecialchars($_SESSION['login']['role'] ?? 'NONE') . "<br>";
echo "</div>";

// Connect to MongoDB
$p = new clsKetNoi();
$con = $p->moKetNoi();

if ($con) {
    $transactionsCol = $con->selectCollection('transactions');
    
    echo "<h3>📊 Export Statistics:</h3>";
    
    // Total exports
    $totalExports = $transactionsCol->countDocuments(['transaction_type' => 'export']);
    echo "<div class='alert alert-success'>";
    echo "<strong>Total exports in database:</strong> $totalExports<br>";
    echo "</div>";
    
    // Exports filtered by warehouse_id
    if ($warehouse_id) {
        $filteredCount = $transactionsCol->countDocuments([
            'transaction_type' => 'export',
            'warehouse_id' => $warehouse_id
        ]);
        echo "<div class='alert alert-warning'>";
        echo "<strong>Exports from your warehouse ($warehouse_id):</strong> $filteredCount<br>";
        echo "</div>";
    }
    
    // Show all exports
    echo "<h3>📋 All Exports in Database:</h3>";
    echo "<table class='table table-bordered'>";
    echo "<thead><tr><th>Transaction ID</th><th>Warehouse (Source)</th><th>Destination</th><th>Status</th><th>Created</th></tr></thead>";
    echo "<tbody>";
    
    $allExports = $transactionsCol->find(['transaction_type' => 'export'], ['sort' => ['created_at' => -1], 'limit' => 10]);
    foreach ($allExports as $exp) {
        $txId = $exp['transaction_id'] ?? 'N/A';
        $whId = $exp['warehouse_id'] ?? 'N/A';
        $destId = $exp['destination_warehouse_id'] ?? 'N/A';
        $status = $exp['status'] ?? 0;
        $created = 'N/A';
        if (isset($exp['created_at']) && $exp['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
            $created = $exp['created_at']->toDateTime()->format('Y-m-d H:i');
        }
        
        $highlight = ($warehouse_id && $whId == $warehouse_id) ? "style='background:#d4edda;'" : "";
        
        echo "<tr $highlight>";
        echo "<td><strong>$txId</strong></td>";
        echo "<td>$whId</td>";
        echo "<td>$destId</td>";
        echo "<td>$status</td>";
        echo "<td>$created</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    
    echo "<div class='alert alert-info'>";
    echo "<strong>Note:</strong> Rows with green background match your warehouse_id filter.<br>";
    echo "If no green rows, that's why you see no exports!";
    echo "</div>";
    
    $p->dongKetNoi($con);
}

echo "<hr>";
echo "<a href='index.php?page=exports' class='btn btn-primary'>Back to Exports</a> ";
echo "<a href='index.php?page=dashboard' class='btn btn-secondary'>Dashboard</a>";
echo "</body></html>";
?>
