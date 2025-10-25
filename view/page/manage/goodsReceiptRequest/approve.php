<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../controller/cRequest.php");

if (!isset($_GET['action']) || !isset($_GET['id'])) {
    $_SESSION['flash_request_error'] = 'Thao tác không hợp lệ!';
    header("Location: ../index.php?page=goodsReceiptRequest");
    exit();
}

$action = $_GET['action'];
$request_id = $_GET['id'];
$approver = $_SESSION['login']['user_id'] ?? 'SYSTEM';

$cRequest = new CRequest();

if ($action === 'approve') {
    // Duyệt phiếu yêu cầu
    $result = $cRequest->approveRequest($request_id, $approver);
    if ($result) {
        $_SESSION['flash_request'] = 'Duyệt phiếu yêu cầu thành công!';
    } else {
        $_SESSION['flash_request_error'] = 'Duyệt phiếu yêu cầu thất bại!';
    }
} elseif ($action === 'reject') {
    // Từ chối phiếu yêu cầu
    $result = $cRequest->rejectRequest($request_id, $approver);
    if ($result) {
        $_SESSION['flash_request'] = 'Đã từ chối phiếu yêu cầu!';
    } else {
        $_SESSION['flash_request_error'] = 'Từ chối phiếu yêu cầu thất bại!';
    }
} else {
    $_SESSION['flash_request_error'] = 'Hành động không hợp lệ!';
}

header("Location: ../index.php?page=goodsReceiptRequest");
exit();
?>
