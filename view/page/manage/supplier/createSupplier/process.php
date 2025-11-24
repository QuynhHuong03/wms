<?php
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name = isset($_POST['supplier_name']) ? trim($_POST['supplier_name']) : '';
    $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
    $contact_name = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : '';
    $tax_code = isset($_POST['tax_code']) ? trim($_POST['tax_code']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $created_at = date('Y-m-d H:i:s'); // Lấy ngày hiện tại

    if ($supplier_name && $contact && $contact_name && $tax_code && $country) {
        $cSupplier = new CSupplier();
        $result = $cSupplier->addSupplier($supplier_name, $contact, $created_at, $contact_name, $tax_code, $country, $description);

        if ($result) {
            header("Location: /KLTN/view/page/manage/index.php?page=supplier&msg=success");
            exit();
        } else {
            header("Location: /KLTN/view/page/manage/index.php?page=supplier/createSupplier&msg=error");
            exit();
        }
    } else {
        header("Location: /KLTN/view/page/manage/index.php?page=supplier/createSupplier&msg=error");
        exit();
    }
}
?>