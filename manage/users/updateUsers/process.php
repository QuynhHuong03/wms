<?php
//include_once("../../../model/mUsers.php"); // chứa MWarehouse/CUsers hoặc tương tự
// include_once("connect.php"); // clsKetNoi MongoDB
include_once(__DIR__ . "/../../../../../controller/cUsers.php");


if (!isset($_POST['btnUpdate'])) {
    die("Không có dữ liệu gửi lên");
}

// Lấy user_id từ URL
$user_id = $_POST['id'] ?? '';
if (empty($user_id)) {
    die("Không xác định được người dùng cần cập nhật.");
}

// Lấy dữ liệu từ form
$name         = $_POST['name'] ?? '';
$email        = $_POST['email'] ?? '';
$gender       = isset($_POST['gender']) ? (int)$_POST['gender'] : 1;
$phone        = $_POST['phone'] ?? '';
$role_id      = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 1;
$status       = isset($_POST['status']) ? (int)$_POST['status'] : 1;
$warehouse_id = $_POST['warehouse_id'] ?? '';

// Basic validation
$errors = [];
if (empty($name)) $errors[] = "Họ tên không được để trống.";
if (empty($email)) $errors[] = "Email không được để trống.";
if (empty($phone)) $errors[] = "Số điện thoại không được để trống.";

if (!empty($errors)) {
    foreach ($errors as $err) {
        echo "<p style='color:red;'>$err</p>";
    }
    exit();
}

// Chuẩn bị dữ liệu cập nhật
$data = [
    'name'         => $name,
    'email'        => $email,
    'gender'       => $gender,
    'phone'        => $phone,
    'role_id'      => $role_id,
    'status'       => $status,
    'warehouse_id' => $warehouse_id
];

try {
    $p   = new clsKetNoi();
    $con = $p->moKetNoi(); // MongoDB\Database

    if (!$con) die("Không thể kết nối MongoDB");

    $col = $con->selectCollection('users');

    // UPDATE theo user_id string, không dùng ObjectId
    $result = $col->updateOne(
        ['user_id' => $user_id],  // filter
        ['$set' => $data]         // dữ liệu cập nhật
    );

    $p->dongKetNoi($con);

    if ($result->getModifiedCount() > 0) {
            echo "Cập nhật thành công!";

    } else {
        echo "<p style='color:orange;'>Không có thay đổi hoặc user không tồn tại.</p>";
    }

} catch (\Exception $e) {
    die("Lỗi update MongoDB: " . $e->getMessage());
}
?>
