<?php
// session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include_once(__DIR__ . '/../../../../../controller/cCategories.php');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btnUpdate'])) {
    $id          = $_POST['id'] ?? null;
    $name        = trim($_POST['category_name'] ?? '');
    $code        = trim($_POST['category_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    if (!$id || $name === '') {
        $_SESSION['error'] = "Tên danh mục không được để trống.";
        header("Location: index.php?page=categories&action=update&id=" . $id);
        exit();
    }

    $cCategories = new CCategories();

    $data = [
        "category_name" => $name,
        "category_code" => $code,
        "description"   => $description,
        "status"        => $status,
        "updated_at"    => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $cCategories->updateCategory($id, $data);

    if ($result) {
        // $_SESSION['success'] = "Cập nhật danh mục thành công!";
        header("Location: ../../index.php?page=categories&msg=success");
        exit();
    } else {
        // $_SESSION['error'] = "Cập nhật danh mục thất bại!";
        echo "Cập nhật danh mục sản phẩm thất bại. <a href='../page/index.php?page=users_add'>Thử lại</a>";
    }
} else {
    header("Location: ../page/index.php?page=categories");
    exit();
}
