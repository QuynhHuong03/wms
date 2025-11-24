<?php
include_once(__DIR__ . "/../../../../../controller/cWarehouse_type.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $warehouse_type_id = trim($_POST["warehouse_type_id"] ?? '');
    $name = trim($_POST["name"] ?? '');

    $errors = [];

    // Validate dữ liệu
    if ($name === '' || !preg_match('/^[\p{L}\s]+$/u', $name)) {
        $errors[] = "Tên loại kho không hợp lệ.";
    }

    if (count($errors) > 0) {
        echo "<script>
            alert('Lỗi: " . implode("\\n", $errors) . "');
            window.history.back();
        </script>";
        exit();
    }

    $cWarehouseType = new CWarehouseType();
    $result = $cWarehouseType->updateWarehouseType($warehouse_type_id, $name);

    if ($result) {
        echo "<script>
            alert('Cập nhật loại kho thành công!');
            window.location.href = '../../index.php?page=warehouse_type';
        </script>";
    } else {
        echo "<script>
            alert('Cập nhật loại kho thất bại. Vui lòng thử lại.');
            window.history.back();
        </script>";
    }
}
?>