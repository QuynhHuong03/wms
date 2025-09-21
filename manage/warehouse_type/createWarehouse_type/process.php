<?php
include_once(__DIR__ . "/../../../../../controller/cWarehouse_type.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    $warehouse_type_id = trim($_POST["warehouse_type_id"] ?? '');
    $name = trim($_POST["name"] ?? '');
    $created_at = $_POST["created_at"] ?? date('Y-m-d H:i:s'); // Nhận thời gian từ form hoặc lấy thời gian hiện tại

    $errors = [];

    // Validate dữ liệu
    if ($warehouse_type_id === '' || !preg_match('/^[A-Za-z0-9_]+$/', $warehouse_type_id)) {
        $errors[] = "Mã loại kho không hợp lệ.";
    }

    if ($name === '' || !preg_match('/^[\p{L}\s]+$/u', $name)) {
        $errors[] = "Tên loại kho không hợp lệ.";
    }

    if (count($errors) > 0) {
        echo "<script>
            alert('Lỗi: " . implode("\\n", $errors) . "');
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse_type/createWarehouse_type'; }, 1000);
        </script>";
        exit();
    }

    $cWarehouseType = new CWarehouseType();

    // Kiểm tra mã loại kho đã tồn tại
    $existingTypes = $cWarehouseType->getAllWarehouseTypes();
    foreach ($existingTypes as $type) {
        if ($type['warehouse_type_id'] === $warehouse_type_id) {
            echo "<script>
                alert('Mã loại kho đã tồn tại. Vui lòng chọn mã khác.');
                setTimeout(() => { window.location.href = '../../index.php?page=warehouse_type/createWarehouse_type'; }, 1000);
            </script>";
            exit();
        }
    }

    // Thêm loại kho
    $result = $cWarehouseType->addWarehouseType($warehouse_type_id, $name, $created_at);

    if ($result) {
        echo "<script>
            alert('Thêm loại kho thành công!');
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse_type'; }, 1000);
        </script>";
        exit();
    } else {
        echo "<script>
            alert('Thêm loại kho thất bại. Vui lòng thử lại.');
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse_type/createWarehouse_type'; }, 1000);
        </script>";
        exit();
    }
}
?>