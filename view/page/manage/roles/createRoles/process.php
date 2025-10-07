<?php
include_once(__DIR__ . "/../../../../../controller/cRoles.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    $role_name   = trim($_POST["role_name"] ?? '');
    $description = trim($_POST["description"] ?? '');
    $status      = $_POST["status"] ?? 1;
    $create_at   = $_POST["create_at"] ?? date('Y-m-d H:i:s');

    $errors = [];

    // Validate dữ liệu
    if ($role_name === '' || !preg_match('/^[\p{L}\s]+$/u', $role_name)) {
        $errors[] = "Tên vai trò không hợp lệ.";
    }

    if ($description === '') {
        $errors[] = "Mô tả không được để trống.";
    }

    if ($status === '') {
        $errors[] = "Vui lòng chọn trạng thái.";
    } else if ($status !== '1' && $status !== '2') {
        $errors[] = "Trạng thái không hợp lệ.";
    }

    if (count($errors) > 0) {
        // Hiển thị thông báo lỗi và dừng xử lý
        echo "<script>
            alert('Lỗi: " . implode("\n", $errors) . "');
            setTimeout(() => { window.location.href = 'index.php'; }, 1000);
        </script>";
        exit();
    }

    $cRoles = new CRoles();

    // Thêm vai trò
    $result = $cRoles->addRole($role_name, $description, $status, $create_at);

    if ($result) {
        // Hiển thị thông báo thành công và chuyển hướng
        echo "<script>
            alert('Thêm vai trò thành công!');
            setTimeout(() => { window.location.href = '../../index.php?page=roles'; }, 1000);
        </script>";
        exit();
    } else {
        // Hiển thị thông báo thất bại và chuyển hướng
        echo "<script>
            alert('Thêm vai trò thất bại. Vui lòng thử lại.');
            setTimeout(() => { window.location.href = 'index.php'; }, 1000);
        </script>";
        exit();
    }
} else {
    // Nếu không phải phương thức POST hoặc không có nút btnAdd
    echo "<script>
        alert('Yêu cầu không hợp lệ.');
        setTimeout(() => { window.location.href = 'index.php'; }, 1000);
    </script>";
    exit();
}
?>