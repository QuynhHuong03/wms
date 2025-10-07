<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    $warehouse_id   = trim($_POST["warehouse_id"] ?? '');
    $warehouse_name = trim($_POST["warehouse_name"] ?? '');
    $address        = trim($_POST["address"] ?? '');
    $status         = $_POST["status"] ?? 1;
    $warehouse_type = $_POST["warehouse_type"] ?? 1;
    $created_at     = date('Y-m-d H:i:s'); // Lấy ngày hiện tại

    $errors = [];

    // Validate dữ liệu
    if ($warehouse_id === '' || !preg_match('/^[A-Za-z0-9_]+$/', $warehouse_id)) {
        $errors[] = "Mã kho không hợp lệ.";
    }

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
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse/createWarehause'; }, 1000);
        </script>";
        exit();
    }

    $cWarehouse = new CWarehouse();

    // Kiểm tra mã kho đã tồn tại
    $existingWarehouses = $cWarehouse->getAllWarehouses();
    foreach ($existingWarehouses as $warehouse) {
        if ($warehouse['warehouse_id'] === $warehouse_id) {
            echo "<script>
                alert('Mã kho đã tồn tại. Vui lòng chọn mã khác.');
                setTimeout(() => { window.location.href = '../../index.php?page=warehouse/createWarehause'; }, 1000);
            </script>";
            exit();
        }
    }

    // Thêm kho chi nhánh
    $result = $cWarehouse->addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status, $created_at);

    if ($result) {
        // Hiển thị thông báo thành công và chuyển hướng
        echo "<script>
            alert('Thêm kho chi nhánh thành công!');
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse'; }, 1000);
        </script>";
        exit();
    } else {
        // Hiển thị thông báo thất bại và chuyển hướng
        echo "<script>
            alert('Thêm kho thất bại. Vui lòng thử lại.');
            setTimeout(() => { window.location.href = '../../index.php?page=warehouse/createWarehause'; }, 1000);
        </script>";
        exit();
    }
} else {
    // Nếu không phải phương thức POST hoặc không có nút btnAdd
    echo "<script>
        alert('Yêu cầu không hợp lệ.');
        setTimeout(() => { window.location.href = '../../index.php?page=warehouse/createWarehause'; }, 1000);
    </script>";
    exit();
}
?>