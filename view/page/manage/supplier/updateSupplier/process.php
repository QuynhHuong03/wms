<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $supplier_id   = trim($_POST["supplier_id"] ?? '');
    $supplier_name = trim($_POST["supplier_name"] ?? '');
    $contact       = trim($_POST["contact"] ?? '');
    $contact_name  = trim($_POST["contact_name"] ?? '');
    $tax_code      = trim($_POST["tax_code"] ?? '');
    $country       = trim($_POST["country"] ?? '');
    $description   = trim($_POST["description"] ?? '');
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
        // Redirect back to the update page with an error flag so the UI can show a toast
        header("Location: /KLTN/view/page/manage/index.php?page=supplier/updateSupplier&id=$supplier_id&msg=error");
        exit();
    }

    $cSupplier = new CSupplier();
    $result = $cSupplier->updateSupplier($supplier_id, $supplier_name, $contact, $status, $contact_name, $tax_code, $country, $description);
    if ($result) {
        // Redirect to supplier list with success message so toast appears
        header("Location: /KLTN/view/page/manage/index.php?page=supplier&msg=updated");
        exit();
    } else {
        // Redirect back to update page with an error flag
        header("Location: /KLTN/view/page/manage/index.php?page=supplier/updateSupplier&id=$supplier_id&msg=error");
        exit();
    }
}


?>