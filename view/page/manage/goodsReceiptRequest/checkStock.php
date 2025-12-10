<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' =&gt; '/', 'secure' =&gt; false, 'httponly' =&gt; true, 'samesite' =&gt; 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../controller/cRequest.php");

if (!isset($_GET['id'])) {
    $_SESSION['flash_request_error'] = 'Thiếu thông tin phiếu yêu cầu!';
    header("Location: ../index.php?page=goodsReceiptRequest");
    exit();
}

$requestId = $_GET['id'];
$action = $_GET['action'] ?? 'confirm';
$processedBy = $_SESSION['login']['user_id'] ?? 'SYSTEM';

$cRequest = new CRequest();

if ($action === 'insufficient') {
    // Đánh dấu không đủ hàng (status → 4)
    $result = $cRequest->confirmStockAvailability($requestId, $processedBy, false);
    
    if ($result) {
        $_SESSION['flash_request'] = 'Đã đánh dấu không đủ hàng. Vui lòng chỉ định kho thay thế!';
    } else {
        $_SESSION['flash_request_error'] = 'Cập nhật thất bại!';
    }
} else {
    // Xác nhận đủ hàng (status → 3)
    $result = $cRequest->confirmStockAvailability($requestId, $processedBy, true);
    
    if ($result) {
        $_SESSION['flash_request'] = 'Đã xác nhận đủ hàng. Có thể tạo phiếu chuyển kho!';
    } else {
        $_SESSION['flash_request_error'] = 'Cập nhật thất bại!';
    }
}

header("Location: ../index.php?page=goodsReceiptRequest");
exit();
?>
