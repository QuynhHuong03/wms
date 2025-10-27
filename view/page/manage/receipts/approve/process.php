<?php
include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['login'])) {
    $_SESSION['flash_receipt_error'] = "Bạn cần đăng nhập để thực hiện chức năng này.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

$cReceipt = new CReceipt();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if (!$action || !$id) {
    $_SESSION['flash_receipt_error'] = "Thiếu thông tin phiếu.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    $_SESSION['flash_receipt_error'] = "Hành động không hợp lệ.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

// Kiểm tra quyền - chỉ quản lý mới được duyệt/từ chối
$role = $_SESSION['login']['role'] ?? '';
$role_name = $_SESSION['login']['role_name'] ?? '';
$role_id = $_SESSION['login']['role_id'] ?? '';

// Cho phép các role quản lý duyệt phiếu
$allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
$allowedRoleIds = [2, 4]; // role_id cho quản lý kho tổng và quản lý kho chi nhánh

$hasPermission = in_array($role, $allowedRoles) || 
                in_array($role_name, $allowedRoles) || 
                in_array($role_id, $allowedRoleIds);

if (!$hasPermission) {
    $_SESSION['flash_receipt_error'] = "Bạn không có quyền thực hiện chức năng này. Chỉ quản lý mới được duyệt/từ chối phiếu.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

// Lấy thông tin người duyệt
$approver = $_SESSION['login']['user_id'] ?? '';

// Xác định trạng thái: 1 = duyệt, 2 = từ chối
$status = $action === 'approve' ? 1 : 2;

// Thực hiện cập nhật trạng thái
$success = $cReceipt->updateReceiptStatus($id, $status, $approver);

if ($success) {
    $_SESSION['flash_receipt'] = $action === 'approve' ? " Phiếu đã được duyệt thành công." : " Phiếu đã bị từ chối.";
} else {
    $_SESSION['flash_receipt_error'] = "Không thể cập nhật trạng thái phiếu. Vui lòng thử lại.";
}

header("Location: ../index.php?page=receipts/approve");
exit;
?>
