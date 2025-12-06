<?php
header('Content-Type: application/json');
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

if (isset($_GET['id'])) {
    $warehouseId = $_GET['id'];
    $cWarehouse = new CWarehouse();

    $result = $cWarehouse->deleteWarehouse($warehouseId);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Xóa kho thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Xóa kho thất bại!']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy ID kho!']);
}
?>