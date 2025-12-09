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

    // Ensure user exists before attempting password update for clearer errors
    $userExists = $p->getUserById($userId);
    if (!$userExists) {
        echo json_encode(['status' => 'error', 'message' => 'Người dùng không tồn tại.']);
        exit;
    }

    $result = $p->updatePassword($userId, $current, $new);
    if ($result === 'updated') {
        // Destroy session so user is forced to re-login with new password
        // Unset all of the session variables.
        $_SESSION = [];
        // If it's desired to kill the session, also delete the session cookie.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        // Finally destroy the session.
        session_destroy();

        // Return success and a redirect URL to the login page
        echo json_encode([
            'status' => 'success',
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
            // Redirect to the project's logout page (matches sidebar link)
            'redirect' => '../logout/index.php'
        ]);
    } elseif ($result === 'wrong_password') {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu hiện tại không chính xác.']);
    } elseif ($result === 'not_updated') {
        echo json_encode(['status' => 'error', 'message' => 'Mật khẩu mới giống mật khẩu cũ hoặc không có thay đổi được ghi nhận.']);
    } else {
        // Unexpected result — return safe debug info to help trace the issue
        $type = gettype($result);
        $valDescription = null;
        if (is_null($result)) $valDescription = 'null';
        elseif (is_bool($result)) $valDescription = $result ? 'true' : 'false';
        elseif (is_scalar($result)) $valDescription = (string)$result;
        else $valDescription = 'non-scalar';

        echo json_encode([
            'status' => 'error',
            'message' => 'Có lỗi xảy ra khi đổi mật khẩu.',
            'debug' => [
                'result_type' => $type,
                'result_value' => $valDescription
            ]
        ]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.']);
exit;