<?php
// Script: Restore batch LH0003 về 20 cái để test
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Restore Batch</title>";
echo "<style>
body{font-family:Arial;padding:20px;}
.success{background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;color:#155724;}
.btn{background:#28a745;color:white;padding:12px 25px;text-decoration:none;border-radius:5px;display:inline-block;margin:10px 0;cursor:pointer;border:none;font-size:16px;}
pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}
</style>";
echo "</head><body>";

echo "<h1>🔄 Restore Batch LH0003</h1>";

if (isset($_GET['restore']) && $_GET['restore'] == '1') {
    // Restore batch về 20
    $result = $db->batches->updateOne(
        [
            'batch_code' => 'LH0003',
            'warehouse_id' => 'KHO_TONG_01'
        ],
        [
            '$set' => [
                'quantity_remaining' => 20,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ]
    );
    
    if ($result->getModifiedCount() > 0) {
        echo "<div class='success'>✅ Đã restore batch LH0003 về 20 cái!</div>";
    } else {
        echo "<div class='success'>ℹ️ Batch đã ở 20 cái hoặc không tìm thấy</div>";
    }
    
    // Restore batch_location
    $db->batch_locations->updateOne(
        [
            'batch_code' => 'LH0003',
            'location.warehouse_id' => 'KHO_TONG_01'
        ],
        [
            '$set' => [
                'quantity' => 20,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]
        ]
    );
    
    // Verify
    $batch = $db->batches->findOne([
        'batch_code' => 'LH0003',
        'warehouse_id' => 'KHO_TONG_01'
    ]);
    
    if ($batch) {
        $bArray = json_decode(json_encode($batch), true);
        echo "<h3>Batch sau khi restore:</h3>";
        echo "<pre>" . json_encode($bArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }
    
    echo "<p><a href='../exports/verify_quantity.php'>→ Xem lại số lượng</a></p>";
    
} else {
    echo "<p>Batch LH0003 hiện đang <strong>HẾT HÀNG</strong> (0 cái).</p>";
    echo "<p>Script này sẽ restore lại về <strong>20 cái</strong> để bạn có thể test tiếp.</p>";
    echo "<button onclick=\"window.location.href='?restore=1'\" class='btn'>✓ Restore về 20 cái</button>";
}

echo "</body></html>";
?>
