<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");
$cSupplier = new CSupplier();

$response = ['success' => false];
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Ensure numeric conversion if supplier_id is numeric in DB
    if (is_numeric($id)) $id = (int)$id;
    $deleted = $cSupplier->deleteSupplier($id);
    $response['success'] = $deleted ? true : false;
}

echo json_encode($response);
?>