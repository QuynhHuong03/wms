<?php
include_once(__DIR__ . "/../../../../../controller/cUsers.php");
$cUsers = new CUsers();

if (isset($_GET['id'])) {
    $cUsers->deleteUser($_GET['id']);
}
?>
