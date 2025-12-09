<?php
// Script để thêm thông tin batches vào phiếu xuất EX0008
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Update Batches</title>";
echo "<style>body{font-family:Arial;padding:20px;}.success{background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;color:#155724;}.error{background:#f8d7da;padding:15px;border-radius:5px;margin:10px 0;color:#721c24;}</style>";
echo "</head><body>";

echo "<h1>🔧 Thêm thông tin batches vào phiếu xuất EX0008</h1>";

// Lấy phiếu xuất
$export = $db->transactions->findOne(['transaction_id' => 'EX0008']);

if (!$export) {
    echo "<div class='error'>Không tìm thấy phiếu xuất EX0008</div>";
} else {
    $details = $export['details'] ?? [];
    
    echo "<h3>Chi tiết hiện tại:</h3>";
    echo "<pre>" . json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    if (isset($_GET['update']) && $_GET['update'] == '1') {
        // Lấy batches từ kho nguồn cho sản phẩm này
        foreach ($details as $idx => $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'] ?? 0;
            
            // Tìm batch của sản phẩm này trong KHO_TONG_01
            $batches = $db->batches->find([
                'product_id' => $productId,
                'warehouse_id' => 'KHO_TONG_01',
                'quantity_remaining' => ['$gt' => 0]
            ], [
                'sort' => ['import_date' => 1],
                'limit' => 5
            ])->toArray();
            
            if (count($batches) > 0) {
                $batchesInfo = [];
                $remainingQty = $quantity;
                
                foreach ($batches as $batch) {
                    if ($remainingQty <= 0) break;
                    
                    $batchQty = min($remainingQty, $batch['quantity_remaining'] ?? 0);
                    
                    // Format import_date
                    $importDate = date('d/m/Y');
                    if (isset($batch['import_date'])) {
                        if (is_object($batch['import_date']) && method_exists($batch['import_date'], 'toDateTime')) {
                            $importDate = date('d/m/Y', $batch['import_date']->toDateTime()->getTimestamp());
                        } elseif (is_string($batch['import_date'])) {
                            $importDate = $batch['import_date'];
                        }
                    }
                    
                    $batchesInfo[] = [
                        'batch_code' => $batch['batch_code'] ?? '',
                        'quantity' => $batchQty,
                        'unit_price' => $batch['unit_price'] ?? 0,
                        'import_date' => $importDate
                    ];
                    
                    $remainingQty -= $batchQty;
                }
                
                // Cập nhật details với batches
                $details[$idx]['batches'] = $batchesInfo;
            }
        }
        
        // Update phiếu xuất
        try {
            $result = $db->transactions->updateOne(
                ['transaction_id' => 'EX0008'],
                ['$set' => ['details' => $details]]
            );
            
            // Kiểm tra cả matchedCount (tìm thấy document) và modifiedCount (có thay đổi)
            if ($result->getMatchedCount() > 0) {
                echo "<div class='success'>";
                echo "✅ Đã cập nhật thông tin batches vào phiếu xuất EX0008!<br>";
                echo "Matched: " . $result->getMatchedCount() . " | Modified: " . $result->getModifiedCount() . "<br>";
                echo "Số sản phẩm có batches: " . count($details);
                echo "</div>";
                
                echo "<h3>Details sau khi update:</h3>";
                echo "<pre>" . json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                
                // Verify bằng cách đọc lại từ database
                $verifyExport = $db->transactions->findOne(['transaction_id' => 'EX0008']);
                $verifyDetails = $verifyExport['details'] ?? [];
                
                echo "<h3>Verify - Đọc lại từ database:</h3>";
                if (isset($verifyDetails[0]['batches'])) {
                    echo "<div class='success'>✅ Batches đã được lưu thành công!</div>";
                    echo "<pre>" . json_encode($verifyDetails[0]['batches'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                } else {
                    echo "<div class='error'>⚠️ Batches chưa có trong database</div>";
                }
                
                echo "<p><a href='../index.php?page=receipts'>→ Quay lại trang nhập hàng để test</a></p>";
            } else {
                echo "<div class='error'>❌ Không tìm thấy phiếu xuất EX0008 để cập nhật</div>";
            }
        } catch (\Exception $e) {
            echo "<div class='error'>❌ Lỗi: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<p><a href='?update=1' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>✓ Thêm thông tin batches</a></p>";
    }
}

echo "</body></html>";
?>
