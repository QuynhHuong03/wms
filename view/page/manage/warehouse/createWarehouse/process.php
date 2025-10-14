<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    $warehouse_id   = trim($_POST["warehouse_id"] ?? '');
    $warehouse_name = trim($_POST["warehouse_name"] ?? '');
    $address        = trim($_POST["address"] ?? '');
    $status         = (int)($_POST["status"] ?? 1);
    $warehouse_type = (int)($_POST["warehouse_type"] ?? 2);
    $created_at     = date('Y-m-d H:i:s');

    $errors = [];

    // Validate cơ bản
    if ($warehouse_id === '' || !preg_match('/^[A-Za-z0-9_]+$/', $warehouse_id)) {
        $errors[] = "Mã kho không hợp lệ.";
    }
    if ($warehouse_name === '') {
        $errors[] = "Tên kho không được để trống.";
    }
    if ($address === '') {
        $errors[] = "Địa chỉ không được để trống.";
    }

    if (!empty($errors)) {
        echo "<script>alert('Lỗi: " . implode("\\n", $errors) . "'); history.back();</script>";
        exit();
    }

    $cWarehouse = new CWarehouse();
    $existingWarehouses = $cWarehouse->getAllWarehouses();

    // Nếu chưa có kho tổng => tự động tạo kho tổng
    $hasMainWarehouse = false;
    foreach ($existingWarehouses as $wh) {
        if ((int)$wh['warehouse_type'] === 1) {
            $hasMainWarehouse = true;
            break;
        }
    }
    if (!$hasMainWarehouse) {
        $warehouse_type = 1; // Tự tạo kho tổng đầu tiên
    }

    // Kiểm tra trùng mã kho
    foreach ($existingWarehouses as $wh) {
        if ($wh['warehouse_id'] === $warehouse_id) {
            echo "<script>alert('Mã kho đã tồn tại.'); history.back();</script>";
            exit();
        }
    }

    // Gọi model thêm
    $data = [
        'warehouse_id'   => $warehouse_id,
        'warehouse_name' => $warehouse_name,
        'address'        => $address,
        'status'         => $status,
        'warehouse_type' => $warehouse_type,
        'created_at'     => $created_at
    ];

    $result = $cWarehouse->addWarehouse($data);

    if ($result) {
        echo "<script>alert('Thêm kho thành công!'); window.location.href='../../index.php?page=warehouse';</script>";
    } else {
        echo "<script>alert('Thêm kho thất bại!'); history.back();</script>";
    }
} else {
    echo "<script>alert('Yêu cầu không hợp lệ.'); window.location.href='../../index.php?page=warehouse';</script>";
}
?>
