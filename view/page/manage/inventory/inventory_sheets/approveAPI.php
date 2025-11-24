<?php
// Wrapper để xử lý approve/reject qua routing
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../../controller/cInventorySheet.php');

header('Content-Type: application/json; charset=utf-8');

$cSheet = new CInventorySheet();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $roleId = $_SESSION['login']['role_id'] ?? null;
    $isManager = in_array($roleId, [1, 2, 4]);
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    switch ($action) {
        case 'approve':
            if (!$isManager) {
                echo json_encode(['ok' => false, 'error' => 'Chỉ quản lý mới có quyền duyệt phiếu']);
                exit;
            }
            
            $sheetId = $input['sheet_id'] ?? '';
            $note = $input['note'] ?? '';
            $locations = $input['locations'] ?? [];
            
            if (!$sheetId) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu ID phiếu']);
                exit;
            }
            
            $userId = $_SESSION['login']['user_id'] ?? '';
            $userName = $_SESSION['login']['name'] ?? 'Unknown';
            
            $result = $cSheet->approveSheet($sheetId, $userId, $userName, $note, $locations);
            echo json_encode($result);
            break;
            
        case 'reject':
            if (!$isManager) {
                echo json_encode(['ok' => false, 'error' => 'Chỉ quản lý mới có quyền từ chối phiếu']);
                exit;
            }
            
            $sheetId = $input['sheet_id'] ?? '';
            $note = $input['note'] ?? '';
            
            if (!$sheetId) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu ID phiếu']);
                exit;
            }
            
            $userId = $_SESSION['login']['user_id'] ?? '';
            $userName = $_SESSION['login']['name'] ?? 'Unknown';
            
            $result = $cSheet->rejectSheet($sheetId, $userId, $userName, $note);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['ok' => false, 'error' => 'Hành động không hợp lệ']);
    }
} catch (Throwable $e) {
    error_log('api.php error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
