<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $warehouse_id   = trim($_POST["warehouse_id"] ?? '');
    $warehouse_name = trim($_POST["warehouse_name"] ?? '');
    $address        = trim($_POST["address"] ?? '');
    $status         = $_POST["status"] ?? '';

    $errors = [];

    // Validate dữ liệu
    if ($warehouse_name === '' || !preg_match('/^[\p{L}\s]+$/u', $warehouse_name)) {
        $errors[] = "Tên kho không hợp lệ.";
    }

    if ($address === '') {
        $errors[] = "Địa chỉ không được để trống.";
    }

    if ($status === '') {
        $errors[] = "Vui lòng chọn trạng thái.";
    }

    if (count($errors) > 0) {
        // Hiển thị thông báo lỗi và dừng xử lý
        echo "<script>
            alert('Lỗi: " . implode("\\n", $errors) . "');
            setTimeout(() => { window.location.href = 'index.php?id=$warehouse_id'; }, 1000);
        </script>";
        exit();
    }

    $cWarehouse = new CWarehouse();
    $result = $cWarehouse->updateWarehouse($warehouse_id, $warehouse_name, $address, $status);

    if ($result) {
        // Hiển thị thông báo thành công và chuyển hướng
        echo "<script>
            alert('Cập nhật kho thành công!');
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse'; }, 1000);
        </script>";
        exit();
    } else {
        // Hiển thị thông báo thất bại và chuyển hướng
        echo "<script>
            alert('Cập nhật kho thất bại. Vui lòng thử lại.');
            setTimeout(() => { window.location.href = 'index.php?id=$warehouse_id'; }, 1000);
        </script>";
        exit();
    }
}
?>