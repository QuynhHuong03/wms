<?php
/**
 * Process export actions: approve, reject
 */

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../model/connect.php");
include_once(__DIR__ . "/../../../../controller/cExport.php");

$action = $_POST['action'] ?? '';
$export_id = trim($_POST['export_id'] ?? '');

error_log("=== EXPORT CONFIRM DEBUG ===");
error_log("Action: " . $action);
error_log("Export ID received: '" . $export_id . "'");
error_log("Export ID length: " . strlen($export_id));

if (empty($action) || empty($export_id)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
    exit;
}

$user_id = $_SESSION['login']['user_id'] ?? '';
$warehouse_id = $_SESSION['warehouse_id'] ?? '';

$p = new clsKetNoi();
$con = $p->moKetNoi();

if (!$con) {
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database']);
    exit;
}

try {
    $transCol = $con->selectCollection('transactions');
    
    // Debug: Log export_id received
    error_log("Searching for export with transaction_id: '" . $export_id . "'");
    
    // Thử tìm với nhiều cách khác nhau
    $export = $transCol->findOne(['transaction_id' => $export_id]);
    error_log("Export found with exact match: " . ($export ? "YES" : "NO"));
    
    // Nếu không tìm thấy, thử trim trong database
    if (!$export) {
        error_log("Trying to find all transactions with type 'export'");
        $allExports = $transCol->find(['type' => 'export'], ['limit' => 10])->toArray();
        error_log("Total exports found: " . count($allExports));
        
        if (count($allExports) > 0) {
            error_log("First export transaction_id: '" . ($allExports[0]['transaction_id'] ?? 'N/A') . "'");
            
            // Thử tìm bằng regex (case-insensitive, trim spaces)
            $export = $transCol->findOne([
                'transaction_id' => new MongoDB\BSON\Regex('^\\s*' . preg_quote($export_id) . '\\s*$', 'i')
            ]);
            error_log("Export found with regex: " . ($export ? "YES" : "NO"));
        }
    }
    
    if (!$export) {
        echo json_encode([
            'success' => false, 
            'message' => 'Không tìm thấy phiếu xuất',
            'debug' => [
                'search_id' => $export_id,
                'search_length' => strlen($export_id)
            ]
        ]);
        exit;
    }
    
    if ($action === 'confirm_export') {
        // ✅ XÁC NHẬN XUẤT KHO - TRỪ INVENTORY THEO FIFO
        
        // Kiểm tra đã trừ inventory chưa
        if ($export['inventory_deducted'] ?? false) {
            echo json_encode(['success' => false, 'message' => 'Phiếu xuất này đã được xác nhận trước đó!']);
            exit;
        }
        
        $details = $export['details'] ?? [];
        $sourceWarehouse = $export['warehouse_id'] ?? '';
        $destWarehouse = $export['destination_warehouse_id'] ?? null;
        
        $allProcessed = true;
        $processedProducts = [];
        
        // Sử dụng controller cExport để trừ theo FIFO (batches)
        $cExport = new CExport();
        
        // Trừ inventory collection (collection cũ - cho UI)
        $inventoryCol = $con->selectCollection('inventory');
        
        foreach ($details as $item) {
            $itemArray = json_decode(json_encode($item), true);
            $productId = $itemArray['product_id'];
            $productName = $itemArray['product_name'] ?? $productId;
            $factor = (int)($itemArray['conversion_factor'] ?? 1);
            $qty = (int)($itemArray['quantity'] ?? 0);
            $totalQty = $qty * $factor; // Tổng số lượng cần trừ
            
            // 1. Trừ batches theo FIFO (hệ thống mới)
            $result = $cExport->exportProductFIFO(
                $productId,
                $totalQty,
                $sourceWarehouse,
                $destWarehouse,
                $export_id,
                "Xác nhận xuất kho cho phiếu $export_id"
            );
            
            if ($result['success']) {
                $processedProducts[] = "$productName ($totalQty cái)";
                error_log("✅ FIFO export success for product $productId: $totalQty units");
                
                // Lưu thông tin batches VÀ VỊ TRÍ vào product trong phiếu xuất
                if (isset($result['exported_batches']) && !empty($result['exported_batches'])) {
                    $batchInfo = [];
                    $batchLocationCol = $con->selectCollection('batch_locations');
                    
                    $batchCol = $con->selectCollection('batches');
                    
                    foreach ($result['exported_batches'] as $b) {
                        $batchCode = $b['batch_code'] ?? '';
                        
                        // Format import_date để tránh lỗi
                        $importDate = $b['import_date'] ?? date('d/m/Y');
                        if (is_object($importDate) && method_exists($importDate, 'toDateTime')) {
                            $importDate = date('d/m/Y', $importDate->toDateTime()->getTimestamp());
                        }
                        
                        // ⭐ LẤY UNIT_PRICE TỪ BATCH GỐC
                        $unitPrice = $b['unit_price'] ?? 0;
                        if (empty($unitPrice) || $unitPrice == 0) {
                            // Nếu exported_batches không có unit_price, lấy từ collection batches
                            try {
                                $batchDoc = $batchCol->findOne([
                                    'batch_code' => $batchCode,
                                    'warehouse_id' => $sourceWarehouse
                                ]);
                                if ($batchDoc && isset($batchDoc['unit_price'])) {
                                    $unitPrice = $batchDoc['unit_price'];
                                    error_log("Got unit_price from batch doc: $unitPrice for batch $batchCode");
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching batch unit_price: " . $e->getMessage());
                            }
                        }
                        
                        // ⭐ LẤY VỊ TRÍ CŨ TỪ BATCH_LOCATIONS (kho nguồn)
                        $oldLocation = null;
                        try {
                            $batchLocation = $batchLocationCol->findOne([
                                'batch_code' => $batchCode,
                                'location.warehouse_id' => $sourceWarehouse
                            ]);
                            
                            if ($batchLocation) {
                                $loc = $batchLocation['location'];
                                $oldLocation = [
                                    'warehouse_id' => $loc['warehouse_id'] ?? $sourceWarehouse,
                                    'zone_id' => $loc['zone_id'] ?? '',
                                    'rack_id' => $loc['rack_id'] ?? '',
                                    'bin_id' => $loc['bin_id'] ?? ''
                                ];
                                error_log("Found source location for batch $batchCode: " . json_encode($oldLocation));
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching batch location: " . $e->getMessage());
                        }
                        
                        $batchInfo[] = [
                            'batch_code' => $batchCode,
                            'quantity' => $b['quantity'] ?? 0,
                            'unit_price' => $unitPrice, // ⭐ LẤY TỪ BATCH GỐC
                            'import_date' => $importDate,
                            'source_location' => $oldLocation // ⭐ VỊ TRÍ CŨ Ở KHO NGUỒN
                        ];
                        
                        error_log("Batch info saved: " . json_encode($batchInfo[count($batchInfo) - 1]));
                    }
                    
                    // Cập nhật thông tin batches vào products (field mới)
                    $transCol->updateOne(
                        [
                            'transaction_id' => $export_id,
                            'products.product_id' => $productId
                        ],
                        ['$set' => ['products.$.batches' => $batchInfo]]
                    );
                    
                    // Cập nhật thông tin batches vào details (field cũ - legacy)
                    $transCol->updateOne(
                        [
                            'transaction_id' => $export_id,
                            'details.product_id' => $productId
                        ],
                        ['$set' => ['details.$.batches' => $batchInfo]]
                    );
                    
                    error_log("✅ Saved batch info for product $productId: " . json_encode($batchInfo));
                }
            } else {
                $allProcessed = false;
                error_log("❌ Failed to export product $productId from batches: " . $result['message']);
            }
            
            // ⚠️ KHÔNG CẦN trừ inventory collection nữa
            // Lý do: exportProductFIFO() đã trừ batches rồi
            // Nếu trừ thêm inventory sẽ bị trừ 2 lần (GẤP ĐÔI)
            error_log("ℹ️ Skipping inventory deduction - already handled by FIFO batches");
        }
        
        // Cập nhật status phiếu xuất
        $transCol->updateOne(
            ['transaction_id' => $export_id],
            ['$set' => [
                'status' => 1, // Đã xuất kho
                'inventory_deducted' => true,
                'confirmed_by' => $user_id,
                'confirmed_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        // Cập nhật status phiếu yêu cầu sang 6 (Đã xuất kho)
        if (isset($export['request_id'])) {
            $transCol->updateOne(
                ['transaction_id' => $export['request_id']], // ⭐ Dùng transaction_id
                ['$set' => [
                    'status' => 6, // 6 = Đã xuất kho
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
        
        $message = $allProcessed 
            ? "Đã xác nhận xuất kho phiếu $export_id. Đã trừ " . count($processedProducts) . " sản phẩm theo FIFO."
            : "Đã xác nhận phiếu $export_id nhưng một số sản phẩm không đủ tồn kho.";
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'processed' => $processedProducts
        ]);
        
    } elseif ($action === 'approve') {
        // ✅ CHI NHÁNH DUYỆT NHẬN HÀNG
        
        $destWarehouse = $export['destination_warehouse_id'] ?? '';
        
        // Kiểm tra quyền: User phải thuộc kho đích
        if ($warehouse_id !== $destWarehouse) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền duyệt phiếu xuất này!']);
            exit;
        }
        
        // Kiểm tra phiếu đã được xuất kho chưa
        if (!($export['inventory_deducted'] ?? false)) {
            echo json_encode(['success' => false, 'message' => 'Phiếu xuất chưa được xác nhận xuất kho!']);
            exit;
        }
        
        // Kiểm tra status phải là 1 (Đã xuất kho)
        if ((int)($export['status'] ?? 0) !== 1) {
            echo json_encode(['success' => false, 'message' => 'Phiếu xuất không ở trạng thái chờ duyệt!']);
            exit;
        }
        
        $inventoryCol = $con->selectCollection('inventory');
        $details = $export['details'] ?? [];
        
        if (empty($destWarehouse)) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy kho đích!']);
            exit;
        }
        
        // Thêm hàng vào inventory kho đích
        foreach ($details as $item) {
            $itemArray = json_decode(json_encode($item), true);
            $productId = $itemArray['product_id'];
            $factor = (int)($itemArray['conversion_factor'] ?? 1);
            $qty = (int)($itemArray['quantity'] ?? 0);
            $totalQty = $qty * $factor;
            
            $inventoryCol->insertOne([
                'warehouse_id' => $destWarehouse, // ⭐ Dùng kho đích từ phiếu xuất
                'product_id' => $productId,
                'transaction_id' => $export_id,
                'qty' => $totalQty,
                'received_at' => new MongoDB\BSON\UTCDateTime(),
                'created_at' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
        
        // Cập nhật trạng thái phiếu xuất → 2 (Đã giao hàng)
        $transCol->updateOne(
            ['transaction_id' => $export_id],
            ['$set' => [
                'status' => 2, // ⭐ Status 2 = Đã giao hàng
                'approved_by' => $user_id,
                'approved_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        // Cập nhật status phiếu yêu cầu → 7 (Hoàn tất)
        if (isset($export['request_id'])) {
            $transCol->updateOne(
                ['transaction_id' => $export['request_id']],
                ['$set' => [
                    'status' => 7, // 7 = Hoàn tất
                    'completed_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
        }
        
        echo json_encode(['success' => true, 'message' => "Đã duyệt nhận hàng phiếu $export_id"]);
        
    } elseif ($action === 'reject') {
        // ❌ CHI NHÁNH TỪ CHỐI NHẬN HÀNG
        
        $destWarehouse = $export['destination_warehouse_id'] ?? '';
        
        // Kiểm tra quyền: User phải thuộc kho đích
        if ($warehouse_id !== $destWarehouse) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền từ chối phiếu xuất này!']);
            exit;
        }
        
        $reason = $_POST['reason'] ?? 'Không rõ lý do';
        
        $inventoryCol = $con->selectCollection('inventory');
        $details = $export['details'] ?? [];
        $sourceWarehouse = $export['warehouse_id'] ?? '';
        
        // Hoàn lại inventory kho nguồn
        foreach ($details as $item) {
            $itemArray = json_decode(json_encode($item), true);
            $productId = $itemArray['product_id'];
            $factor = (int)($itemArray['conversion_factor'] ?? 1);
            $qty = (int)($itemArray['quantity'] ?? 0);
            $totalQty = $qty * $factor;
            
            // Thêm lại vào inventory kho nguồn
            $inventoryCol->insertOne([
                'warehouse_id' => $sourceWarehouse,
                'product_id' => $productId,
                'transaction_id' => $export_id . '_REFUND',
                'qty' => $totalQty,
                'received_at' => new MongoDB\BSON\UTCDateTime(),
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'note' => "Hoàn lại từ phiếu xuất $export_id bị từ chối"
            ]);
        }
        
        // Cập nhật trạng thái phiếu xuất
        $transCol->updateOne(
            ['transaction_id' => $export_id],
            ['$set' => [
                'status' => 2, // Từ chối
                'rejected_by' => $user_id,
                'rejected_at' => new MongoDB\BSON\UTCDateTime(),
                'reject_reason' => $reason
            ]]
        );
        
        echo json_encode(['success' => true, 'message' => "Đã từ chối phiếu xuất $export_id"]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
    }
    
    $p->dongKetNoi($con);
    
} catch (Exception $e) {
    $p->dongKetNoi($con);
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
