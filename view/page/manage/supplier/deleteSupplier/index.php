<?php
include_once("../../../../../controller/cSupplier.php");

if (isset($_GET['id'])) {
    $supplierId = $_GET['id'];
    $supplierId = (int)$supplierId;
    $cSupplier = new CSupplier();

    $result = $cSupplier->deleteSupplier($supplierId);

    if ($result) {
        echo "Xóa nhà cung cấp thành công!";
    } else {
        echo "Xóa nhà cung cấp thất bại!";
    }
} else {
    echo "Không tìm thấy ID nhà cung cấp!";
}
?>