<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $supplier_id   = trim($_POST["supplier_id"] ?? '');
    $supplier_name = trim($_POST["supplier_name"] ?? '');
    $contact       = trim($_POST["contact"] ?? '');
    $status        = $_POST["status"] ?? '';

    $errors = [];

    // Validate dữ liệu
    if ($supplier_name === '') {
        $errors[] = "Tên nhà cung cấp không được để trống.";
    }

    if ($contact === '') {
        $errors[] = "Liên hệ không được để trống.";
    }

    if ($status === '') {
        $errors[] = "Vui lòng chọn trạng thái.";
    }

    if (count($errors) > 0) {
        // Hiển thị thông báo lỗi và dừng xử lý
        echo "<script>
            alert('Lỗi: " . implode("\\n", $errors) . "');
            setTimeout(() => { window.location.href = '../../index.php?page=supplier/updatesupplier&id=$supplier_id'; }, 1000);
        </script>";
        exit();
    }

    $cSupplier = new CSupplier();
    $result = $cSupplier->updateSupplier($supplier_id, $supplier_name, $contact, $status);
    if ($result) {
        // Hiển thị thông báo thành công và chuyển hướng
        echo "<script>
            alert('Cập nhật nhà cung cấp thành công!');
            setTimeout(() => { window.location.href = '../../index.php?page=supplier'; }, 1000);
        </script>";
        exit();
    } else {
        // Hiển thị thông báo thất bại và chuyển hướng
        echo "<script>
            alert('Cập nhật thất bại. Không có bản ghi nào được cập nhật.');
            setTimeout(() => { window.location.href = '../../index.php?page=supplier/updatesupplier&id=$supplier_id'; }, 1000);
        </script>";
        exit();
    }
}


?>