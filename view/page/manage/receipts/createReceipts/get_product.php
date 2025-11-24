<?php
header("Content-Type: application/json; charset=UTF-8");
include_once(__DIR__ . "/../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../controller/cBatch.php");

if (!isset($_GET['barcode']) || empty($_GET['barcode'])) {
    echo json_encode(["success" => false, "message" => "Thiếu mã barcode"]);
    exit;
}

$barcode = $_GET['barcode'];

// Ưu tiên kiểm tra batch trước (cho trường hợp nhập từ kho nội bộ)
$cBatch = new CBatch();

// ⭐ Tìm batch theo barcode HOẶC batch_code
$batch = $cBatch->getBatchByBarcode($barcode);

// Nếu không tìm thấy theo barcode, thử tìm theo batch_code
if (!$batch) {
    $batch = $cBatch->getBatchByCode($barcode);
}

if ($batch) {
    // Tìm thấy batch - lấy thông tin sản phẩm từ batch
    $product_id = '';
    if (isset($batch['product_id'])) {
        if ($batch['product_id'] instanceof MongoDB\BSON\ObjectId) {
            $product_id = (string)$batch['product_id'];
        } elseif (is_array($batch['product_id']) && isset($batch['product_id']['$oid'])) {
            $product_id = (string)$batch['product_id']['$oid'];
        } else {
            $product_id = (string)$batch['product_id'];
        }
    }
    
    // Lấy thông tin chi tiết sản phẩm
    $cProduct = new CProduct();
    $product = $cProduct->getProductById($product_id);
    
    // Sử dụng giá từ batch (quan trọng cho transfer)
    $import_price = $batch['unit_price'] ?? ($product['purchase_price'] ?? 0);
    $unit = $batch['unit'] ?? ($product['baseUnit'] ?? 'Cái');
    $sku = $product['sku'] ?? '';
    
    // ⭐ Lấy thông tin location nếu có
    $source_location = null;
    if (isset($batch['locations']) && is_array($batch['locations']) && count($batch['locations']) > 0) {
        $source_location = $batch['locations'][0]; // Lấy location đầu tiên
    }
    
    echo json_encode([
        "success" => true,
        "type" => "batch",
        "product" => [
            "_id" => $product_id,
            "sku" => $sku,
            "name" => $batch['product_name'] ?? ($product['product_name'] ?? ''),
            "unit" => $unit,
            "import_price" => $import_price,
            "batch_code" => $batch['batch_code'] ?? '',
            "batch_barcode" => $batch['barcode'] ?? '',
            "quantity_remaining" => $batch['quantity_remaining'] ?? 0,
            "source" => 'transfer', // ⭐ Đánh dấu là transfer vì đang lấy batch có sẵn
            "source_warehouse_id" => $batch['warehouse_id'] ?? '', // Kho nguồn là kho hiện tại của batch
            "source_location" => $source_location // ⭐ Vị trí tại kho nguồn
        ]
    ]);
} else {
    // Không tìm thấy batch - kiểm tra barcode sản phẩm
    $p = new CProduct();
    $product = $p->getProductByBarcode($barcode);

    if ($product) {
        // Lấy ID sản phẩm
        $id = '';
        if (isset($product['_id'])) {
            if ($product['_id'] instanceof MongoDB\BSON\ObjectId) {
                $id = (string)$product['_id'];
            } elseif (is_array($product['_id']) && isset($product['_id']['$oid'])) {
                $id = (string)$product['_id']['$oid'];
            } else {
                $id = (string)$product['_id'];
            }
        }
        
        echo json_encode([
            "success" => true,
            "type" => "product",
            "product" => [
                "_id" => $id,
                "sku" => $product['sku'] ?? '',
                "name" => $product['product_name'] ?? '',
                "unit" => $product['baseUnit'] ?? 'Cái',
                "import_price" => $product['purchase_price'] ?? 0
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm hoặc lô hàng"]);
    }
}
?>
