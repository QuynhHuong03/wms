<?php
include("../../../../../controller/cWarehouse.php");

if (isset($_GET['id'])) {
    $warehouseId = $_GET['id'];
    $cWarehouse = new CWarehouse();

    $result = $cWarehouse->deleteWarehouse($warehouseId);

    if ($result) {
        echo "Xóa kho thành công!";
    } else {
        echo "Xóa kho thất bại!";
    }
} else {
    echo "Không tìm thấy ID kho!";
}
?>