<?php
session_start();
header('Content-Type: application/json');

include_once(__DIR__ . "/../../../../controller/cUsers.php");
$p = new CUsers();

// Lấy userId từ session (nhân viên đang đăng nhập)
$userId = $_SESSION['login']['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'Không xác định được người dùng.']);
    exit;
}

// Xử lý cập nhật thông tin tài khoản
if ($_POST['action'] === 'updateProfile') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '' || $email === '') {
        echo json_encode(['status' => 'error', 'message' => 'Tên và email không được bỏ trống.']);
        exit;
    }

    $data = [
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
        'gender' => (int)($_POST['gender'] ?? 0)
    ];

    $result = $p->updateUserProfile($userId, $data);
    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Cập nhật thông tin thành công.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật thông tin.']);
    }
    exit;
}

// Xử lý đổi mật khẩu
if ($_POST['action'] === 'changePassword') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validate input
    if (empty($current) || empty($new) || empty($confirm)) {
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng điền đầy đủ thông tin.']);
        exit;
    }

    if (strlen($new) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự.']);
        exit;
    }

    if ($new !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu xác nhận không khớp.']);
        exit;
    }

    if ($current === $new) {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu mới phải khác mật khẩu hiện tại.']);
        exit;
    }

    $result = $p->updatePassword($userId, $current, $new);
    if ($result === true) {
        echo json_encode(['status' => 'success', 'message' => 'Đổi mật khẩu thành công.']);
    } elseif ($result === 'wrong_password') {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu hiện tại không chính xác.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không thể đổi mật khẩu. Vui lòng thử lại.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);
exit;
