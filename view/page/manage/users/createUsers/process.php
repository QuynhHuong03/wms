<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

session_start();
include_once(__DIR__ . "/../../../../../controller/cUsers.php");


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    $name         = trim($_POST["name"] ?? '');
    $email        = trim($_POST["email"] ?? '');
    $gender       = $_POST["gender"] ?? '';
    $phone        = trim($_POST["phone"] ?? '');
    $password     = $_POST["password"] ?? '';
    $role_id      = $_POST["role_id"] ?? '';
    $status       = $_POST["status"] ?? '';
    $warehouse_id = $_POST["warehouse_id"] ?? '';

    $errors = [];

    // Validate dữ liệu
    if ($name === '' || !preg_match('/^[\p{L}\s]+$/u', $name)) {
        $errors[] = "Tên không hợp lệ.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Số điện thoại phải có 10 số.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Mật khẩu phải từ 6 ký tự.";
    }

    if ($role_id === '' || $status === '' || $gender === '' || $warehouse_id === '') {
        $errors[] = "Vui lòng chọn đầy đủ thông tin bắt buộc.";
    }

    if (count($errors) > 0) {
        // Xuất lỗi ra (bạn có thể render ra view đẹp hơn)
        echo "<h3>Lỗi:</h3><ul>";
        foreach ($errors as $err) {
            echo "<li>$err</li>";
        }
        echo "</ul><a href='../page/index.php?page=users_add'>Quay lại</a>";
        exit();
    }

    // Hash mật khẩu
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // Gọi controller để thêm người dùng
    $obj = new CUsers();
    $result = $obj->addUser($name, $email, $gender, $phone, $hashedPassword, $role_id, $status, $warehouse_id);

    if ($result) {
        // thành công → về danh sách users
        header("Location: ../../index.php?page=users&msg=success");
        exit();
    } else {
        echo "Thêm người dùng thất bại. <a href='../page/index.php?page=users_add'>Thử lại</a>";
    }
} else {
    header("Location: ../page/index.php?page=users");
    exit();
}
?>
