<?php
// Script: Thêm source_location vào batch LH0003 trong KHO_CN_04
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Update Source Location</title>";
echo "<style>
body{font-family:Arial;padding:20px;}
.success{background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;color:#155724;}
.error{background:#f8d7da;padding:15px;border-radius:5px;margin:10px 0;color:#721c24;}
.btn{background:#28a745;color:white;padding:12px 25px;text-decoration:none;border-radius:5px;display:inline-block;margin:10px 0;cursor:pointer;border:none;font-size:16px;}
pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}
</style>";
echo "</head><body>";

echo "<h1>🔧 Thêm source_location vào batch LH0003</h1>";

if (isset($_GET['update']) && $_GET['update'] == '1') {
    // 1. Tìm vị trí của batch LH0003 trong KHO_TONG_01
    $batchLocationSource = $db->batch_locations->findOne([
        'batch_code' => 'LH0003',
        'location.warehouse_id' => 'KHO_TONG_01'
    ]);
    
    if ($batchLocationSource) {
        $loc = $batchLocationSource['location'];
        $sourceLocation = [
            'warehouse_id' => $loc['warehouse_id'] ?? 'KHO_TONG_01',
            'zone_id' => $loc['zone_id'] ?? '',
            'rack_id' => $loc['rack_id'] ?? '',
            'bin_id' => $loc['bin_id'] ?? ''
        ];
        
        echo "<div class='success'>✅ Tìm thấy vị trí nguồn:</div>";
        echo "<pre>" . json_encode($sourceLocation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        // 2. Cập nhật vào batch trong KHO_CN_04
        $result = $db->batches->updateOne(
            [
                'batch_code' => 'LH0003',
                'warehouse_id' => 'KHO_CN_04'
            ],
            [
                '$set' => ['source_location' => $sourceLocation]
            ]
        );
        
        if ($result->getModifiedCount() > 0) {
            echo "<div class='success'>✅ Đã cập nhật source_location vào batch LH0003 trong KHO_CN_04!</div>";
        } else {
            echo "<div class='error'>⚠️ Batch đã có source_location hoặc không tìm thấy</div>";
        }
        
        // 3. Cập nhật vào batch_location
        $resultLoc = $db->batch_locations->updateOne(
            [
                'batch_code' => 'LH0003',
                'location.warehouse_id' => 'KHO_CN_04'
            ],
            [
                '$set' => ['source_location' => $sourceLocation]
            ]
        );
        
        if ($resultLoc->getModifiedCount() > 0) {
            echo "<div class='success'>✅ Đã cập nhật source_location vào batch_location!</div>";
        }
        
        // Verify
        $verifyBatch = $db->batches->findOne([
            'batch_code' => 'LH0003',
            'warehouse_id' => 'KHO_CN_04'
        ]);
        
        echo "<h3>Verify - Batch sau khi update:</h3>";
        echo "<pre>" . json_encode($verifyBatch, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        echo "<p><a href='../trace/index.html'>→ Test truy xuất nguồn gốc</a></p>";
        
    } else {
        echo "<div class='error'>❌ Không tìm thấy vị trí của batch LH0003 trong KHO_TONG_01</div>";
        echo "<p>Batch location chưa được tạo. Cần tạo batch_location trước.</p>";
    }
    
} else {
    echo "<p>Script này sẽ:</p>";
    echo "<ol>";
    echo "<li>Tìm vị trí của batch LH0003 trong KHO_TONG_01</li>";
    echo "<li>Cập nhật source_location vào batch LH0003 trong KHO_CN_04</li>";
    echo "<li>Cập nhật source_location vào batch_location</li>";
    echo "</ol>";
    echo "<button onclick=\"window.location.href='?update=1'\" class='btn'>✓ Thực hiện cập nhật</button>";
}

echo "</body></html>";
?>
