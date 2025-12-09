<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");
$cSupplier = new CSupplier();

$response = ['success' => false, 'message' => ''];
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Ensure numeric conversion if supplier_id is numeric in DB
    if (is_numeric($id)) $id = (int)$id;
    $deleted = $cSupplier->deleteSupplier($id);
    
    if ($deleted === 'HAS_PRODUCTS') {
        $response['success'] = false;
        $response['message'] = 'Không thể xóa. Nhà cung cấp còn sản phẩm';
    } else if ($deleted) {
        $response['success'] = true;
        $response['message'] = 'Xóa nhà cung cấp thành công';
    } else {
        $response['success'] = false;
        $response['message'] = 'Xóa nhà cung cấp thất bại';
    }
}

echo json_encode($response);
?>