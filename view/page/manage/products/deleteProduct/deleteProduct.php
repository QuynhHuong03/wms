<?php
include("../../../../../controller/cProduct.php");

if (isset($_GET['id'])) {
    $sku = $_GET['id'];
    $cProduct = new CProduct();
    $result = $cProduct->deleteProduct($sku);
    if ($result) {
        echo "Xóa sản phẩm thành công!";
    } else {
        echo "Xóa sản phẩm thất bại!";
    }
} else {
    echo "Không tìm thấy ID sản phẩm!";
}
