<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . '/../../../../controller/cProduct.php');

if (session_status() === PHP_SESSION_NONE) session_start();

// Check authentication
if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập']);
    exit;
}

// Get product ID from query parameter
$productId = $_GET['id'] ?? '';

if (empty($productId)) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã sản phẩm']);
    exit;
}

try {
    $cProduct = new CProduct();
    $product = $cProduct->getProductById($productId);
    
    if ($product) {
        // Extract dimensions from package_dimensions or dimensions field
        $dims = $product['package_dimensions'] ?? $product['dimensions'] ?? [];
        $width = floatval($dims['width'] ?? $product['width'] ?? 0);
        $depth = floatval($dims['depth'] ?? $product['depth'] ?? 0);
        $height = floatval($dims['height'] ?? $product['height'] ?? 0);
        
        // Ensure data format is consistent
        $response = [
            'success' => true,
            'data' => [
                '_id' => $product['_id'] ?? '',
                'sku' => $product['sku'] ?? '',
                'product_name' => $product['product_name'] ?? $product['name'] ?? '',
                'name' => $product['product_name'] ?? $product['name'] ?? '',
                'barcode' => $product['barcode'] ?? '',
                'baseUnit' => $product['baseUnit'] ?? 'cái',
                'conversionUnits' => $product['conversionUnits'] ?? [],
                'width' => $width,
                'depth' => $depth,
                'height' => $height,
                'package_dimensions' => [
                    'width' => $width,
                    'depth' => $depth,
                    'height' => $height,
                ],
                'dimensions' => [
                    'width' => $width,
                    'depth' => $depth,
                    'height' => $height,
                ],
                'supplier' => $product['supplier'] ?? '',
                'category' => $product['category'] ?? '',
                'current_stock' => $product['current_stock'] ?? 0,
                'purchase_price' => $product['purchase_price'] ?? 0,
            ]
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy sản phẩm'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log('get_product.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
