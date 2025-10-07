<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $old_sku = trim($_POST["old_sku"] ?? '');
    $new_sku = trim($_POST["new_sku"] ?? '');
    $barcode = trim($_POST["barcode"] ?? '');
    $product_name = trim($_POST["product_name"] ?? '');
    $category_name = trim($_POST["category_name"] ?? '');
    $supplier_name = trim($_POST["supplier_name"] ?? '');
    $warehouse_id = trim($_POST["warehouse_id"] ?? '');
    $status = $_POST["status"] ?? 1;
    $min_stock = $_POST["min_stock"] ?? 0;
    // Xử lý upload hình ảnh mới
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../../../../img/';
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageFileName = uniqid('product_') . '.' . $ext;
        $targetPath = $uploadDir . $imageFileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image = $imageFileName;
        }
    } else {
        // Nếu không upload ảnh mới, giữ nguyên ảnh cũ
        $image = trim($_POST['old_image'] ?? '');
    }
            // Removed misplaced HTML input tag causing syntax error

    $errors = [];
    if ($new_sku === '') {
        $errors[] = "SKU không được để trống.";
    }
    if ($product_name === '') {
        $errors[] = "Tên sản phẩm không được để trống.";
    }
    if ($category_name === '') {
        $errors[] = "Loại sản phẩm không được để trống.";
    }
    if ($supplier_name === '') {
        $errors[] = "Nhà cung cấp không được để trống.";
    }
    if ($warehouse_id === '') {
        $errors[] = "Kho không được để trống.";
    }
    if (count($errors) > 0) {
        echo "<script>alert('Lỗi: " . implode("\\n", $errors) . "'); window.location.href = '../../index.php?page=products/updateProduct&id=$old_sku';</script>";
        exit();
    }

    $cProduct = new CProduct();
    $result = $cProduct->updateProduct($old_sku, $new_sku, $product_name, $barcode, $category_name, $supplier_name, $warehouse_id, $status, $image, $min_stock);
    if ($result) {
        echo "<script>alert('Cập nhật sản phẩm thành công!'); window.location.href = '../../index.php?page=products';</script>";
        exit();
    } else {
        echo "<script>alert('Cập nhật sản phẩm thất bại!'); window.location.href = '../../index.php?page=products/updateProduct&id=$old_sku';</script>";
        exit();
    }
} else {
    echo "<script>alert('Yêu cầu không hợp lệ.'); window.location.href = '../../index.php?page=products';</script>";
    exit();
}
