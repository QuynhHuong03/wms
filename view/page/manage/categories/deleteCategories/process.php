<?php
include_once(__DIR__ . "/../../../../../controller/cCategories.php");
$cCategories = new CCategories();

if (isset($_GET['id'])) {
    $cCategories->deleteCategory($_GET['id']);
    echo "success";
} else {
    echo "error";
}
?>
