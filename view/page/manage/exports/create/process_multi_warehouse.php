<?php
/**
 * Process export receipt with multi-warehouse support
 * Xử lý tạo phiếu xuất từ nhiều kho
 */

if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

include_once(__DIR__ . "/../../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../../model/connect.php");

$request_id = $_POST['request_id'] ?? $_GET['request_id'] ?? null;
$warehouse_selections = json_decode($_POST['warehouse_selections'] ?? '{}', true);

if (!$request_id) {
    $_SESSION['flash_request_error'] = 'Không tìm thấy mã phiếu yêu cầu!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

$cRequest = new CRequest();
$request = $cRequest->getRequestById($request_id);

if (!$request || $request['status'] != 3) {
    $_SESSION['flash_request_error'] = 'Phiếu yêu cầu không hợp lệ!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

$p = new clsKetNoi();
$con = $p->moKetNoi();
if (!$con) {
    $_SESSION['flash_request_error'] = 'Lỗi kết nối database!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

try {
    $transCol = $con->selectCollection('transactions');
    $inventoryCol = $con->selectCollection('inventory');
    $batchCol = $con->selectCollection('batches'); // ⭐ Để lấy unit_price từ FIFO
    $user_id = $_SESSION['login']['user_id'] ?? 'U001';
    
    // Generate export IDs (one per warehouse)
    $exportIds = [];
    $warehousesUsed = [];
    
    // Collect all warehouses used
    foreach ($warehouse_selections as $productId => $warehouses) {
        foreach ($warehouses as $warehouseId => $qty) {
            if (!in_array($warehouseId, $warehousesUsed)) {
                $warehousesUsed[] = $warehouseId;
            }
        }
    }
    
    // Generate export ID for each warehouse
    foreach ($warehousesUsed as $warehouseId) {
        $lastExport = $transCol->findOne(
            ['transaction_type' => 'export'],
            ['sort' => ['transaction_id' => -1]]
        );
        
        $num = 1;
        if ($lastExport && isset($lastExport['transaction_id'])) {
            if (preg_match('/(\d+)$/', $lastExport['transaction_id'], $m)) {
                $num = intval($m[1]) + 1;
            }
        }
        $num += count($exportIds); // Increment for each new export
        
        $exportId = 'EX' . str_pad($num, 4, '0', STR_PAD_LEFT);
        $exportIds[$warehouseId] = $exportId;
    }
    
    // Create export receipts for each warehouse
    $allSuccess = true;
    $createdExports = [];
    
    foreach ($warehousesUsed as $warehouseId) {
        $exportId = $exportIds[$warehouseId];
        
        // Build details for this warehouse
        $exportDetails = [];
        foreach ($request['details'] as $item) {
            // Convert BSONDocument to array
            $itemArray = json_decode(json_encode($item), true);
            $productId = $itemArray['product_id'];
            if (isset($warehouse_selections[$productId][$warehouseId])) {
                $qtyToExport = (int)$warehouse_selections[$productId][$warehouseId];
                if ($qtyToExport > 0) {
                    // ⭐ LẤY UNIT_PRICE TỪ BATCH FIFO (lô cũ nhất)
                    $unitPrice = $itemArray['unit_price'] ?? 0; // Default từ request
                    try {
                        $oldestBatch = $batchCol->findOne([
                            'product_id' => $productId,
                            'warehouse_id' => $warehouseId,
                            'quantity_remaining' => ['$gt' => 0]
                        ], [
                            'sort' => ['import_date' => 1] // FIFO: cũ nhất trước
                        ]);
                        
                        if ($oldestBatch && isset($oldestBatch['unit_price'])) {
                            $unitPrice = $oldestBatch['unit_price'];
                            error_log("✅ Got FIFO unit_price = $unitPrice for product $productId from batch " . ($oldestBatch['batch_code'] ?? 'N/A'));
                        }
                    } catch (Exception $e) {
                        error_log("⚠️ Error getting FIFO price for product $productId: " . $e->getMessage());
                    }
                    
                    $exportDetails[] = array_merge($itemArray, [
                        'quantity' => $qtyToExport,
                        'warehouse_source' => $warehouseId,
                        'unit_price' => $unitPrice // ⭐ LƯU GIÁ TỪ FIFO
                    ]);
                }
            }
        }
        
        if (empty($exportDetails)) continue;
        
        // Create export document
        // Status: 0 = Chờ xác nhận xuất (chưa trừ kho)
        //         1 = Đã xác nhận xuất (đã trừ kho)
        //         2 = Đã giao hàng (kho đích đã nhận)
        $exportDoc = [
            'transaction_id' => $exportId,
            'transaction_type' => 'export',
            'type' => 'transfer',
            'warehouse_id' => $warehouseId,
            'destination_warehouse_id' => $request['warehouse_id'],
            'request_id' => $request_id,
            'created_by' => $user_id,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => 0, // Chờ xác nhận xuất (chưa trừ inventory)
            'inventory_deducted' => false, // Flag để biết đã trừ kho chưa
            'details' => $exportDetails,
            'note' => ($_POST['note'] ?? '') . " (Xuất từ kho $warehouseId)"
        ];
        
        $result = $transCol->insertOne($exportDoc);
        
        if ($result->getInsertedCount() > 0) {
            $createdExports[] = $exportId;
            
            // ✅ KHÔNG trừ inventory ngay - Chờ nhân viên xác nhận bằng nút tick
            // Inventory sẽ được trừ khi click nút "Xác nhận xuất" trong danh sách
        } else {
            $allSuccess = false;
        }
    }
    
    // Update request status - Đổi sang status 5 (Đã tạo phiếu xuất, chờ xác nhận)
    if ($allSuccess && !empty($createdExports)) {
        $requestCol = $con->selectCollection('transactions'); // ⭐ Sử dụng collection đúng
        $requestCol->updateOne(
            ['transaction_id' => $request_id], // ⭐ Dùng transaction_id chứ không phải request_id
            [
                '$set' => [
                    'status' => 5, // 5 = Đã tạo phiếu xuất (chờ xác nhận xuất kho)
                    'export_ids' => $createdExports, // Lưu danh sách mã phiếu xuất
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        $exportList = implode(', ', $createdExports);
        $_SESSION['flash_request'] = " Tạo phiếu xuất thành công: $exportList. Vui lòng vào danh sách phiếu xuất để xác nhận xuất kho.";
    } else {
        $_SESSION['flash_request_error'] = "⚠️ Có lỗi xảy ra khi tạo phiếu xuất!";
    }
    
    $p->dongKetNoi($con);
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
    
} catch (Exception $e) {
    $p->dongKetNoi($con);
    $_SESSION['flash_request_error'] = 'Lỗi: ' . $e->getMessage();
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}
?>
