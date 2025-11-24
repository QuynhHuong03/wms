<?php
header("Content-Type: application/json; charset=UTF-8");
include_once(__DIR__ . "/../../../../controller/cProduct.php");

if (!isset($_GET['barcode']) || empty($_GET['barcode'])) {
    echo json_encode(["success" => false, "message" => "Thiếu mã barcode"]);
    exit;
}

$barcode = $_GET['barcode'];
$p = new CProduct();
$product = $p->getProductByBarcode($barcode);

if ($product) {
    echo json_encode([
        "success" => true,
        "product" => [
            "_id" => $product['sku'], // hoặc dùng $product['_id'] nếu Mongo có ObjectId
            "name" => $product['product_name'],
            "unit" => $product['unit'] ?? "Cái",
            "import_price" => $product['import_price'] ?? 0
        ]
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm"]);
}
