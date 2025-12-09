<?php
include("../../../../../controller/cRoles.php");

if (isset($_GET['id'])) {
    $roleId = $_GET['id'];
    $cRoles = new CRoles();

    $result = $cRoles->deleteRole($roleId);

    if ($result) {
        echo "Xóa vai trò thành công!";
    } else {
        echo "Xóa vai trò thất bại!";
    }
} else {
    echo "Không tìm thấy ID vai trò!";
}
?>