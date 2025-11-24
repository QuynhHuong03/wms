<?php
// Debug: Kiểm tra batches trong cả 2 kho
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug Batches</title>";
echo "<style>
body{font-family:Arial;padding:20px;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #ddd;padding:12px;text-align:left;}
th{background:#007bff;color:white;}
.success{background:#d4edda;color:#155724;}
.warning{background:#fff3cd;color:#856404;}
.error{background:#f8d7da;color:#721c24;}
pre{background:#f8f9fa;padding:10px;border-radius:5px;overflow-x:auto;}
</style>";
echo "</head><body>";

echo "<h1>🔍 Debug: Kiểm tra batches LH0003</h1>";

// 1. Kiểm tra trong KHO_TONG_01
echo "<h2>📦 Kho nguồn: KHO_TONG_01</h2>";
$batchesSource = $db->batches->find([
    'batch_code' => 'LH0003',
    'warehouse_id' => 'KHO_TONG_01'
])->toArray();

if (count($batchesSource) > 0) {
    echo "<div class='success'>✅ Tìm thấy " . count($batchesSource) . " batch(es)</div>";
    echo "<table>";
    echo "<tr><th>Batch Code</th><th>Product</th><th>Qty Remaining</th><th>Source</th><th>Created At</th></tr>";
    foreach ($batchesSource as $b) {
        $bArray = json_decode(json_encode($b), true);
        echo "<tr>";
        echo "<td>" . ($bArray['batch_code'] ?? '') . "</td>";
        echo "<td>" . ($bArray['product_name'] ?? '') . "</td>";
        echo "<td>" . ($bArray['quantity_remaining'] ?? 0) . "</td>";
        echo "<td>" . ($bArray['source'] ?? '') . "</td>";
        echo "<td>" . (isset($bArray['created_at']) ? date('d/m/Y H:i', $bArray['created_at']['$date']['$numberLong'] / 1000) : '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning'>⚠️ Không có batch LH0003 trong KHO_TONG_01</div>";
}

// 2. Kiểm tra trong KHO_CN_04
echo "<h2>📦 Kho đích: KHO_CN_04</h2>";
$batchesDest = $db->batches->find([
    'batch_code' => 'LH0003',
    'warehouse_id' => 'KHO_CN_04'
])->toArray();

if (count($batchesDest) > 0) {
    echo "<div class='success'>✅ Tìm thấy " . count($batchesDest) . " batch(es)</div>";
    echo "<table>";
    echo "<tr><th>Batch Code</th><th>Product</th><th>Qty</th><th>Source</th><th>Source Warehouse</th><th>Source Location</th></tr>";
    foreach ($batchesDest as $b) {
        $bArray = json_decode(json_encode($b), true);
        echo "<tr>";
        echo "<td>" . ($bArray['batch_code'] ?? '') . "</td>";
        echo "<td>" . ($bArray['product_name'] ?? '') . "</td>";
        echo "<td>" . ($bArray['quantity_remaining'] ?? 0) . "</td>";
        echo "<td>" . ($bArray['source'] ?? '') . "</td>";
        echo "<td>" . ($bArray['source_warehouse_id'] ?? 'N/A') . "</td>";
        echo "<td><pre>" . json_encode($bArray['source_location'] ?? null, JSON_PRETTY_PRINT) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='error'>❌ KHÔNG CÓ batch LH0003 trong KHO_CN_04</div>";
    echo "<p>Nguyên nhân có thể:</p>";
    echo "<ul>";
    echo "<li>Chưa submit form nhập hàng</li>";
    echo "<li>Phiếu xuất EX0008 chưa có source_location</li>";
    echo "<li>Lỗi khi tạo batch</li>";
    echo "</ul>";
}

// 3. Kiểm tra phiếu xuất EX0008
echo "<h2>📄 Phiếu xuất EX0008</h2>";
$export = $db->transactions->findOne(['transaction_id' => 'EX0008']);
if ($export) {
    $details = $export['details'] ?? [];
    if (count($details) > 0 && isset($details[0]['batches'])) {
        echo "<div class='success'>✅ Phiếu xuất có batches</div>";
        echo "<pre>" . json_encode($details[0]['batches'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    } else {
        echo "<div class='error'>❌ Phiếu xuất KHÔNG có batches trong details</div>";
    }
    
    echo "<h3>Status phiếu xuất:</h3>";
    echo "<ul>";
    echo "<li><strong>Status:</strong> " . ($export['status'] ?? 'N/A') . " (0=pending, 1=confirmed, 2=received)</li>";
    echo "<li><strong>Inventory Deducted:</strong> " . (isset($export['inventory_deducted']) && $export['inventory_deducted'] ? 'YES' : 'NO') . "</li>";
    echo "</ul>";
} else {
    echo "<div class='error'>❌ Không tìm thấy phiếu xuất EX0008</div>";
}

// 4. Kiểm tra phiếu nhập từ EX0008
echo "<h2>📥 Phiếu nhập từ EX0008</h2>";
$receipts = $db->transactions->find([
    'transaction_type' => 'import',
    'export_id' => '6914e5894a96f33a24028085' // ID của EX0008
])->toArray();

if (count($receipts) > 0) {
    echo "<div class='success'>✅ Tìm thấy " . count($receipts) . " phiếu nhập</div>";
    foreach ($receipts as $r) {
        $rArray = json_decode(json_encode($r), true);
        echo "<p><strong>Receipt ID:</strong> " . ($rArray['transaction_id'] ?? '') . "</p>";
        echo "<p><strong>Warehouse:</strong> " . ($rArray['warehouse_id'] ?? '') . "</p>";
        echo "<p><strong>Created:</strong> " . (isset($rArray['created_at']) ? date('d/m/Y H:i', $rArray['created_at']['$date']['$numberLong'] / 1000) : '') . "</p>";
    }
} else {
    echo "<div class='error'>❌ CHƯA CÓ phiếu nhập từ EX0008</div>";
    echo "<p><strong>→ Đây là nguyên nhân!</strong> Bạn chưa submit form nhập hàng.</p>";
}

// 5. Kiểm tra tất cả batches có batch_code LH0003
echo "<h2>🔍 Tất cả batches LH0003 trong hệ thống</h2>";
$allBatches = $db->batches->find(['batch_code' => 'LH0003'])->toArray();
echo "<p>Tổng số: " . count($allBatches) . " batch(es)</p>";
if (count($allBatches) > 0) {
    echo "<table>";
    echo "<tr><th>Warehouse</th><th>Product</th><th>Qty</th><th>Source</th><th>Source Warehouse</th><th>Has Source Location</th></tr>";
    foreach ($allBatches as $b) {
        $bArray = json_decode(json_encode($b), true);
        echo "<tr>";
        echo "<td>" . ($bArray['warehouse_id'] ?? '') . "</td>";
        echo "<td>" . ($bArray['product_name'] ?? '') . "</td>";
        echo "<td>" . ($bArray['quantity_remaining'] ?? 0) . "</td>";
        echo "<td>" . ($bArray['source'] ?? '') . "</td>";
        echo "<td>" . ($bArray['source_warehouse_id'] ?? 'N/A') . "</td>";
        echo "<td>" . (isset($bArray['source_location']) && $bArray['source_location'] ? '✅ YES' : '❌ NO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "</body></html>";
?>
