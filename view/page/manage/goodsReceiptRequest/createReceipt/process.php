<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' =&gt; '/', 'secure' =&gt; false, 'httponly' =&gt; true, 'samesite' =&gt; 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../../controller/cInventory.php");

// Xử lý tạo phiếu yêu cầu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouse_id = $_POST['warehouse_id'] ?? '';
    $created_by = $_POST['created_by'] ?? '';
    $source_warehouse_id = $_POST['source_warehouse_id'] ?? 'KHO_TONG_01';
    $priority = $_POST['priority'] ?? 'normal';
    $note = $_POST['note'] ?? null;
    
    $selected = $_POST['selected_products'] ?? [];
    $products = $_POST['products'] ?? [];

    if (empty($warehouse_id) || empty($created_by)) {
        $_SESSION['flash_request_error'] = 'Thiếu thông tin bắt buộc!';
        header("Location: ../../index.php?page=goodsReceiptRequest/createReceipt");
        exit();
    }

    if (empty($selected)) {
        $_SESSION['flash_request_error'] = 'Vui lòng chọn ít nhất một sản phẩm!';
        header("Location: ../../index.php?page=goodsReceiptRequest/createReceipt");
        exit();
    }

    $cInventory = new CInventory();
    $details = [];

    foreach ($selected as $index) {
        if (!isset($products[$index])) continue;
        
        $p = $products[$index];
        $qty = (int)($p['quantity'] ?? 0);
        
        if ($qty <= 0) continue;

        $productId = $p['product_id'] ?? '';
        $currentStock = (int)($p['current_stock'] ?? 0);
        $minStock = (int)($p['min_stock'] ?? 0);
        $shortage = max(0, $minStock - $currentStock);
        
        // ⭐ Lấy tồn kho từ inventory
        $sourceStock = $cInventory->getTotalStockByProduct($source_warehouse_id, $productId);
        $isSufficient = ($sourceStock >= $qty);
        
        $alternativeWarehouses = [];
        
        // Tìm kho thay thế nếu kho nguồn không đủ
        if (!$isSufficient) {
            $stockAllWarehouses = $cInventory->getStockByProductAllWarehouses($productId);
            
            foreach ($stockAllWarehouses as $whId => $availableStock) {
                if ($whId === $warehouse_id || $whId === $source_warehouse_id) continue;
                
                if ($availableStock >= $qty) {
                    $alternativeWarehouses[] = [
                        'warehouse_id' => $whId,
                        'available_stock' => (int)$availableStock
                    ];
                }
            }
        }

        $details[] = [
            'product_id' => $productId,
            'product_name' => $p['product_name'] ?? '',
            'sku' => $p['sku'] ?? '',
            'current_stock' => $currentStock,
            'min_stock' => $minStock,
            'shortage' => $shortage,
            'quantity' => $qty,
            'unit' => $p['unit'] ?? 'cái',
            'conversion_factor' => (int)($p['conversion_factor'] ?? 1),
            'base_unit' => $p['base_unit'] ?? 'cái',
            'source_stock' => (int)$sourceStock,
            'is_sufficient' => $isSufficient,
            'alternative_warehouses' => $alternativeWarehouses
        ];
    }

    if (empty($details)) {
        $_SESSION['flash_request_error'] = 'Không có sản phẩm hợp lệ để tạo yêu cầu!';
        header("Location: index.php?page=goodsReceiptRequest/createReceipt");
        exit();
    }

    $payload = [
        'warehouse_id' => $warehouse_id,
        'source_warehouse_id' => $source_warehouse_id,
        'created_by' => $created_by,
        'priority' => $priority,
        'note' => $note,
        'details' => $details
    ];

    $cRequest = new CRequest();
    list($success, $result) = $cRequest->createRequest($payload);

    if ($success) {
        $_SESSION['flash_request'] = 'Tạo phiếu yêu cầu nhập hàng thành công! Mã: ' . $result;
        header("Location: ../.././index.php?page=goodsReceiptRequest");
    } else {
        $_SESSION['flash_request_error'] = 'Tạo phiếu yêu cầu thất bại: ' . $result;
        header("Location: ../../../index.php?page=goodsReceiptRequest/createReceipt");
    }
    exit();
}

// Xử lý chuyển phiếu yêu cầu thành phiếu nhập
if (isset($_GET['action']) && $_GET['action'] === 'convert' && isset($_GET['id'])) {
    $requestId = $_GET['id'];
    $cRequest = new CRequest();
    
    list($success, $result) = $cRequest->convertToReceipt($requestId);
    
    if ($success) {
        // Tạo phiếu nhập hàng từ phiếu yêu cầu
        include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
        $cReceipt = new CReceipt();
        
        $receiptPayload = [
            'type' => 'transfer',
            'warehouse_id' => $result['warehouse_id'],
            'source_warehouse_id' => $result['assigned_warehouse_id'] ?? $result['source_warehouse_id'],
            'created_by' => $_SESSION['login']['user_id'] ?? 'SYSTEM',
            'note' => 'Chuyển từ phiếu yêu cầu: ' . $requestId,
            'details' => []
        ];
        
        foreach ($result['details'] as $d) {
            $receiptPayload['details'][] = [
                'product_id' => $d['product_id'],
                'product_name' => $d['product_name'],
                'quantity' => $d['quantity'],
                'unit' => $d['unit']
            ];
        }
        
        list($receiptSuccess, $receiptId) = $cReceipt->createReceipt($receiptPayload);
        
        if ($receiptSuccess) {
            $_SESSION['flash_receipt'] = 'Đã tạo phiếu nhập hàng từ yêu cầu: ' . $requestId;
            header("Location: index.php?page=receipts/approve");
        } else {
            $_SESSION['flash_request_error'] = 'Chuyển phiếu thất bại: ' . $receiptId;
            header("Location: ../../index.php?page=goodsReceiptRequest");
        }
    } else {
        $_SESSION['flash_request_error'] = $result;
        header("Location: ../../index.php?page=goodsReceiptRequest");
    }
    exit();
}

// Mặc định redirect về trang tạo phiếu
header("Location: ../../index.php?page=goodsReceiptRequest/createReceipt");
exit();
?>
