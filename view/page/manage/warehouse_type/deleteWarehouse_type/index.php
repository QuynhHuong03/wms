<?php
include_once(__DIR__ . "/../../../../../controller/cWarehouse_type.php");

if (isset($_GET['id'])) {
    $warehouseTypeId = trim($_GET['id']);
    $cWarehouseType = new CWarehouseType();

    $result = $cWarehouseType->deleteWarehouseType($warehouseTypeId);

    if ($result) {
        echo "Xóa loại kho thành công!";
    } else {
        echo "Xóa loại kho thất bại!";
    }
} else {
    echo "Không tìm thấy ID loại kho để xóa!";
}
?>