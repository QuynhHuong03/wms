<?php
if (session_status() === PHP_SESSION_NONE) session_start();

echo "<h2>Debug Export Detail Page</h2>";
echo "<hr>";

// Test 1: Check if ID is passed
$export_id = $_GET['id'] ?? null;
echo "<h3>1. ID from URL:</h3>";
echo "ID = " . htmlspecialchars($export_id ?? 'NULL') . "<br>";

// Test 2: Check database connection
echo "<h3>2. Database Connection:</h3>";
include_once(__DIR__ . "/../../../../model/connect.php");
$p = new clsKetNoi();
$con = $p->moKetNoi();
if ($con) {
    echo "✅ Connected to MongoDB<br>";
    
    // Test 3: Check collection
    $transactionsCol = $con->selectCollection('transactions');
    echo "✅ Got transactions collection<br>";
    
    // Test 4: Try to find the export
    echo "<h3>3. Search for Export:</h3>";
    echo "Looking for transaction_id = " . htmlspecialchars($export_id) . "<br>";
    
    $filter = ['transaction_id' => $export_id, 'transaction_type' => 'export'];
    echo "Filter: <pre>" . print_r($filter, true) . "</pre>";
    
    try {
        $result = $transactionsCol->findOne($filter);
        if ($result) {
            echo "✅ Found export!<br>";
            echo "<h4>Export Data:</h4>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo "❌ Export not found!<br>";
            
            // Try to find any export
            echo "<h4>Let's find ALL exports:</h4>";
            $allExports = $transactionsCol->find(['transaction_type' => 'export'], ['limit' => 5]);
            foreach ($allExports as $exp) {
                echo "- transaction_id: " . htmlspecialchars($exp['transaction_id'] ?? 'N/A') . "<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
    
    $p->dongKetNoi($con);
} else {
    echo "❌ Cannot connect to MongoDB<br>";
}

echo "<hr>";
echo "<a href='index.php?page=exports'>Back to exports list</a>";
?>
