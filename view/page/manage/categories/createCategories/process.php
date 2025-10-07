<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

session_start();
include_once(__DIR__ . '/../../../../../controller/cCategories.php');

// Khởi tạo controller
$cCategories = new CCategories();

// Kiểm tra request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $name        = trim($_POST['category_name'] ?? '');
    $code        = trim($_POST['category_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = isset($_POST['status']) ? (int)$_POST['status'] : 1;

    // Validate cơ bản
    if ($name === '' || $code === '') {
        // Có thể set session để hiển thị lỗi ở view
        $_SESSION['error'] = "Tên danh mục và mã code không được để trống";
        header("Location: index.php?page=categories_add");
        exit;
    }

    // Gọi controller để thêm category
    $result = $cCategories->addCategory($name, $code, $description, $status);

    if ($result) {
        // thành công → về danh sách categories
        header("Location: ../../index.php?page=categories&msg=success");
        exit();
    } else {
        echo "Thêm danh mục sản phẩm thất bại. <a href='../page/index.php?page=users_add'>Thử lại</a>";
    }
} else {
    header("Location: ../page/index.php?page=categories");
    exit();
}
