<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../../../../../controller/cUsers.php");
$cUsers = new CUsers();

$response = ['success' => false];
if (isset($_GET['id'])) {
    $deleted = $cUsers->deleteUser($_GET['id']);
    $response['success'] = $deleted ? true : false;
}

echo json_encode($response);
?>
