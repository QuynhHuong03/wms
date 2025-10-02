<?php
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
    $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
    $created_at = date('Y-m-d H:i:s'); // Lấy ngày hiện tại

    if ($supplier_name && $contact) {
        $cSupplier = new CSupplier();
        $result = $cSupplier->addSupplier($supplier_name, $contact, $created_at);

        if ($result) {
            echo "<script>alert('Thêm nhà cung cấp thành công!'); window.location.href='../../index.php?page=supplier';</script>";
        } else {
            echo "<script>alert('Thêm nhà cung cấp thất bại!'); window.location.href='../../index.php?page=supplier/createSupplier';</script>";
        }
    } else {
        echo "<script>alert('Vui lòng nhập đầy đủ thông tin!'); window.location.href='../../index.php?page=supplier/createSupplier';</script>";
    }
}
?>