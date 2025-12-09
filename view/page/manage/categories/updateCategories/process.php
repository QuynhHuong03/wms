<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once(__DIR__ . '/../../../../../controller/cCategories.php');

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btnUpdate'])) {
    $id          = $_POST['id'] ?? null;
    $name        = trim($_POST['category_name'] ?? '');
    $code        = trim($_POST['category_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    if (!$id || $name === '') {
        $_SESSION['error'] = "Tên danh mục không được để trống.";
        header("Location: /KLTN/view/page/manage/index.php?page=categories&msg=error");
        exit();
    }

    $cCategories = new CCategories();

    $data = [
        "category_name" => $name,
        "category_code" => $code,
        "description"   => $description,
        "status"        => $status,
        // use `update_at` to match display field in list view
        "update_at"     => new MongoDB\BSON\UTCDateTime()
    ];

    $result = $cCategories->updateCategory($id, $data);

    if ($result) {
        // Redirect to categories list and include msg=updated to trigger a toast there
        header("Location: /KLTN/view/page/manage/index.php?page=categories&msg=updated");
        exit();
    } else {
        $_SESSION['error'] = "Cập nhật danh mục sản phẩm thất bại.";
        header("Location: /KLTN/view/page/manage/index.php?page=categories&msg=error");
        exit();
    }
} else {
    header("Location: /KLTN/view/page/manage/index.php?page=categories");
    exit();
}
