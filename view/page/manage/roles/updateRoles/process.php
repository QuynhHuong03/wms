<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cRoles.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $role_id   = trim($_POST["role_id"] ?? '');
    $role_name = trim($_POST["role_name"] ?? '');
    $description = trim($_POST["description"] ?? '');

    $errors = [];

    // Validate dữ liệu
    if ($role_name === '' || !preg_match('/^[\p{L}\s]+$/u', $role_name)) {
        $errors[] = "Tên vai trò không hợp lệ.";
    }
    if ($description === '') {
        $errors[] = "Mô tả không được để trống.";
    }

    if (count($errors) > 0) {
        // Hiển thị thông báo lỗi và dừng xử lý
        echo "<script>
            alert('Lỗi: " . implode("\\n", $errors) . "');
            setTimeout(() => { window.location.href = './../index.php?id=$role_id'; }, 1000);
        </script>";
        exit();
    }

    $cRoles = new CRoles();
    $data = [
        'role_name' => $role_name,
        'description' => $description
    ];
    $result = $cRoles->updateRole($role_id, $data);

    if ($result) {
        // Hiển thị thông báo thành công và chuyển hướng
        echo "<script>
            alert('Cập nhật vai trò thành công!');
            setTimeout(() => { window.location.href = '../../index.php?page=roles'; }, 1000);
        </script>";
        exit();
    } else {
        // Hiển thị thông báo thất bại và chuyển hướng
        echo "<script>
            alert('Cập nhật vai trò thất bại. Vui lòng thử lại.');
            setTimeout(() => { window.location.href = 'index.php?id=$role_id'; }, 1000);
        </script>";
        exit();
    }
}
?>
