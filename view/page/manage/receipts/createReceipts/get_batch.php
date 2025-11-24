<?php
header("Content-Type: application/json; charset=UTF-8");
include_once(__DIR__ . "/../../../../controller/cBatch.php");
include_once(__DIR__ . "/../../../../controller/cProduct.php");

if (!isset($_GET['barcode']) || empty($_GET['barcode'])) {
    echo json_encode(["success" => false, "message" => "Thiếu mã barcode"]);
    exit;
}

$barcode = $_GET['barcode'];
$cBatch = new CBatch();
$batch = $cBatch->getBatchByBarcode($barcode);

if ($batch) {
    // Lấy thông tin sản phẩm từ batch
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
    
    // Lấy thông tin chi tiết sản phẩm để lấy giá và thông tin khác
    $cProduct = new CProduct();
    $product = $cProduct->getProductById($product_id);
    
    $import_price = $batch['unit_price'] ?? ($product['purchase_price'] ?? 0);
    $unit = $batch['unit'] ?? ($product['baseUnit'] ?? 'Cái');
    $sku = $product['sku'] ?? '';
    
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
            "source" => $batch['source'] ?? '',
            "source_warehouse_id" => $batch['source_warehouse_id'] ?? ''
        ]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Không tìm thấy lô hàng với mã: " . $barcode]);
}
?>
