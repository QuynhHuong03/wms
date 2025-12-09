<?php
header('Content-Type: application/json; charset=utf-8');
include("../../../../../controller/cProduct.php");

if (isset($_GET['id'])) {
    $sku = $_GET['id'];
    $cProduct = new CProduct();
    $result = $cProduct->deleteProduct($sku);
    
    if ($result === 'HAS_INVENTORY') {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa. Sản phẩm còn tồn kho']);
    } elseif ($result) {
        echo json_encode(['success' => true, 'message' => 'Xóa sản phẩm thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Xóa sản phẩm thất bại!']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy ID sản phẩm!']);
}
