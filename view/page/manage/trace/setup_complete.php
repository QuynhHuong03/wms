<?php
// Script: Tạo batch_location cho LH0003 và cập nhật source_location
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Create Batch Location</title>";
echo "<style>
body{font-family:Arial;padding:20px;}
.success{background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;color:#155724;}
.error{background:#f8d7da;padding:15px;border-radius:5px;margin:10px 0;color:#721c24;}
.warning{background:#fff3cd;padding:15px;border-radius:5px;margin:10px 0;color:#856404;}
.btn{background:#28a745;color:white;padding:12px 25px;text-decoration:none;border-radius:5px;display:inline-block;margin:10px 0;cursor:pointer;border:none;font-size:16px;}
pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}
h3{margin-top:20px;}
</style>";
echo "</head><body>";

echo "<h1>🔧 Setup Batch Location + Source Location</h1>";

if (isset($_GET['create']) && $_GET['create'] == '1') {
    try {
        // 1. Tạo batch_location cho LH0003 trong KHO_TONG_01
        $existingLoc = $db->batch_locations->findOne([
            'batch_code' => 'LH0003',
            'location.warehouse_id' => 'KHO_TONG_01'
        ]);
        
        $sourceLocation = [
            'warehouse_id' => 'KHO_TONG_01',
            'zone_id' => 'A',
            'rack_id' => 'R1',
            'bin_id' => 'S1'
        ];
        
        if (!$existingLoc) {
            // Tạo batch_location mới
            $batchLocationData = [
                'batch_code' => 'LH0003',
                'product_id' => '690343b8de544e1ede0649f6',
                'location' => $sourceLocation,
                'quantity' => 20,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $db->batch_locations->insertOne($batchLocationData);
            echo "<div class='success'>✅ Đã tạo batch_location cho LH0003 trong KHO_TONG_01</div>";
            echo "<pre>" . json_encode($sourceLocation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='warning'>⚠️ Batch_location đã tồn tại trong KHO_TONG_01</div>";
            $existingLocArray = json_decode(json_encode($existingLoc), true);
            $sourceLocation = $existingLocArray['location'];
            echo "<pre>" . json_encode($sourceLocation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
        
        // 2. Cập nhật source_location vào batch trong KHO_CN_04
        $result = $db->batches->updateOne(
            [
                'batch_code' => 'LH0003',
                'warehouse_id' => 'KHO_CN_04'
            ],
            [
                '$set' => ['source_location' => $sourceLocation]
            ]
        );
        
        echo "<h3>📦 Cập nhật batch trong KHO_CN_04:</h3>";
        if ($result->getModifiedCount() > 0) {
            echo "<div class='success'>✅ Đã cập nhật source_location vào batch LH0003 trong KHO_CN_04!</div>";
        } else {
            echo "<div class='warning'>⚠️ Batch đã có source_location hoặc không thay đổi (matched: " . $result->getMatchedCount() . ")</div>";
        }
        
        // 3. Cập nhật source_location vào batch_location trong KHO_CN_04
        $resultLoc = $db->batch_locations->updateOne(
            [
                'batch_code' => 'LH0003',
                'location.warehouse_id' => 'KHO_CN_04'
            ],
            [
                '$set' => ['source_location' => $sourceLocation]
            ]
        );
        
        echo "<h3>📍 Cập nhật batch_location trong KHO_CN_04:</h3>";
        if ($resultLoc->getMatchedCount() > 0) {
            if ($resultLoc->getModifiedCount() > 0) {
                echo "<div class='success'>✅ Đã cập nhật source_location vào batch_location!</div>";
            } else {
                echo "<div class='warning'>⚠️ Batch_location đã có source_location</div>";
            }
        } else {
            echo "<div class='warning'>⚠️ Không tìm thấy batch_location trong KHO_CN_04</div>";
            
            // Tạo batch_location cho KHO_CN_04 nếu chưa có
            echo "<p>Đang tạo batch_location mới...</p>";
            $batchLocationDestData = [
                'batch_code' => 'LH0003',
                'product_id' => '690343b8de544e1ede0649f6',
                'location' => [
                    'warehouse_id' => 'KHO_CN_04',
                    'zone_id' => 'PENDING',
                    'rack_id' => null,
                    'bin_id' => null
                ],
                'quantity' => 5,
                'source_location' => $sourceLocation,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $db->batch_locations->insertOne($batchLocationDestData);
            echo "<div class='success'>✅ Đã tạo batch_location mới trong KHO_CN_04 với source_location!</div>";
        }
        
        // 4. Verify - Hiển thị kết quả
        echo "<h3>🔍 Verify - Batch trong KHO_CN_04:</h3>";
        $verifyBatch = $db->batches->findOne([
            'batch_code' => 'LH0003',
            'warehouse_id' => 'KHO_CN_04'
        ]);
        
        if ($verifyBatch) {
            $batchArray = json_decode(json_encode($verifyBatch), true);
            echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Batch Code</td><td>" . ($batchArray['batch_code'] ?? '') . "</td></tr>";
            echo "<tr><td>Warehouse</td><td>" . ($batchArray['warehouse_id'] ?? '') . "</td></tr>";
            echo "<tr><td>Quantity</td><td>" . ($batchArray['quantity_remaining'] ?? 0) . "</td></tr>";
            echo "<tr><td>Source</td><td>" . ($batchArray['source'] ?? '') . "</td></tr>";
            echo "<tr><td>Source Warehouse</td><td>" . ($batchArray['source_warehouse_id'] ?? 'N/A') . "</td></tr>";
            echo "<tr><td><strong>Source Location</strong></td><td>";
            if (isset($batchArray['source_location']) && $batchArray['source_location']) {
                echo "<pre>" . json_encode($batchArray['source_location'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "<span style='color:red;'>NULL</span>";
            }
            echo "</td></tr>";
            echo "</table>";
        }
        
        echo "<h3>🔍 Verify - Batch Location trong KHO_CN_04:</h3>";
        $verifyLoc = $db->batch_locations->findOne([
            'batch_code' => 'LH0003',
            'location.warehouse_id' => 'KHO_CN_04'
        ]);
        
        if ($verifyLoc) {
            $locArray = json_decode(json_encode($verifyLoc), true);
            echo "<pre>" . json_encode($locArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        } else {
            echo "<div class='error'>❌ Không tìm thấy batch_location</div>";
        }
        
        echo "<hr>";
        echo "<h3>✅ Hoàn tất!</h3>";
        echo "<p><a href='index.html' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>→ Test truy xuất nguồn gốc</a></p>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Lỗi: " . $e->getMessage() . "</div>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
    
} else {
    echo "<p>Script này sẽ thực hiện:</p>";
    echo "<ol>";
    echo "<li>✅ Tạo <strong>batch_location</strong> cho LH0003 trong KHO_TONG_01 (Zone A, Rack R1, Bin S1)</li>";
    echo "<li>✅ Cập nhật <strong>source_location</strong> vào batch LH0003 trong KHO_CN_04</li>";
    echo "<li>✅ Cập nhật <strong>source_location</strong> vào batch_location trong KHO_CN_04</li>";
    echo "<li>✅ Verify kết quả</li>";
    echo "</ol>";
    
    echo "<div class='warning'>";
    echo "<strong>⚠️ Lưu ý:</strong> Vị trí mặc định sẽ là:<br>";
    echo "• Kho: KHO_TONG_01<br>";
    echo "• Zone: A<br>";
    echo "• Rack: R1<br>";
    echo "• Bin: S1";
    echo "</div>";
    
    echo "<button onclick=\"window.location.href='?create=1'\" class='btn'>✓ Thực hiện setup</button>";
}

echo "</body></html>";
?>
