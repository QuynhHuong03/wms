<?php
// Script: Kiểm tra số lượng batch trước và sau khi xuất
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Verify Export Quantity</title>";
echo "<style>
body{font-family:Arial;padding:20px;}
table{border-collapse:collapse;width:100%;margin:20px 0;}
th,td{border:1px solid #ddd;padding:12px;text-align:left;}
th{background:#007bff;color:white;}
.success{background:#d4edda;}
.warning{background:#fff3cd;}
.error{background:#f8d7da;}
h2{margin-top:30px;}
</style>";
echo "</head><body>";

echo "<h1>🔍 Verify: Kiểm tra số lượng xuất</h1>";

// Lấy batch LH0003 trong KHO_TONG_01
$batch = $db->batches->findOne([
    'batch_code' => 'LH0003',
    'warehouse_id' => 'KHO_TONG_01'
]);

if ($batch) {
    $bArray = json_decode(json_encode($batch), true);
    
    echo "<h2>📦 Batch LH0003 trong KHO_TONG_01:</h2>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Batch Code</td><td>" . ($bArray['batch_code'] ?? '') . "</td></tr>";
    echo "<tr><td>Product</td><td>" . ($bArray['product_name'] ?? '') . "</td></tr>";
    echo "<tr><td>Quantity Imported</td><td>" . ($bArray['quantity_imported'] ?? 0) . "</td></tr>";
    echo "<tr><td><strong>Quantity Remaining</strong></td><td><strong style='font-size:20px;color:#007bff;'>" . ($bArray['quantity_remaining'] ?? 0) . "</strong></td></tr>";
    echo "</table>";
    
    $qtyRemaining = $bArray['quantity_remaining'] ?? 0;
    $qtyImported = $bArray['quantity_imported'] ?? 0;
    $qtyExported = $qtyImported - $qtyRemaining;
    
    echo "<h3>📊 Tổng kết:</h3>";
    echo "<ul>";
    echo "<li>Số lượng nhập ban đầu: <strong>$qtyImported</strong></li>";
    echo "<li>Số lượng đã xuất: <strong>$qtyExported</strong></li>";
    echo "<li>Số lượng còn lại: <strong style='color:#28a745;'>$qtyRemaining</strong></li>";
    echo "</ul>";
    
    if ($qtyRemaining == 20) {
        echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;color:#721c24;'>";
        echo "⚠️ <strong>Chưa xuất gì!</strong> Batch còn nguyên 20 cái.";
        echo "</div>";
    } elseif ($qtyRemaining == 15) {
        echo "<div style='background:#d4edda;padding:15px;border-radius:5px;color:#155724;'>";
        echo "✅ <strong>Đúng!</strong> Đã xuất 5 cái, còn 15 cái.";
        echo "</div>";
    } elseif ($qtyRemaining == 10) {
        echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;color:#721c24;'>";
        echo "❌ <strong>BỊ TRỪ 2 LẦN!</strong> Xuất 5 nhưng trừ 10 (còn 10 thay vì 15).";
        echo "</div>";
    } else {
        echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;color:#856404;'>";
        echo "ℹ️ Số lượng còn: $qtyRemaining cái";
        echo "</div>";
    }
} else {
    echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;color:#721c24;'>";
    echo "❌ Không tìm thấy batch LH0003 trong KHO_TONG_01";
    echo "</div>";
}

// Lịch sử xuất
echo "<h2>📜 Lịch sử inventory_movements:</h2>";
$movements = $db->inventory_movements->find(
    ['batch_code' => 'LH0003', 'movement_type' => 'xuất'],
    ['sort' => ['date' => -1], 'limit' => 10]
)->toArray();

if (count($movements) > 0) {
    echo "<table>";
    echo "<tr><th>Date</th><th>Batch</th><th>From</th><th>To</th><th>Quantity</th><th>Transaction</th></tr>";
    foreach ($movements as $m) {
        $mArray = json_decode(json_encode($m), true);
        $date = isset($mArray['date']['$date']['$numberLong']) ? 
            date('d/m/Y H:i', $mArray['date']['$date']['$numberLong'] / 1000) : 'N/A';
        
        echo "<tr>";
        echo "<td>$date</td>";
        echo "<td>" . ($mArray['batch_code'] ?? '') . "</td>";
        echo "<td>" . ($mArray['from_location']['warehouse_id'] ?? 'N/A') . "</td>";
        echo "<td>" . ($mArray['to_location']['warehouse_id'] ?? 'N/A') . "</td>";
        echo "<td><strong>" . ($mArray['quantity'] ?? 0) . "</strong></td>";
        echo "<td>" . ($mArray['transaction_id'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Chưa có lịch sử xuất nào.</p>";
}

echo "</body></html>";
?>
