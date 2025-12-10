<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }
include_once(__DIR__ . '/../../../../../controller/cInventorySheet.php');

$cSheet = new CInventorySheet();

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
    // Check user role - only managers can approve/reject
    // role_id: 1=Admin, 2=QL_Kho_Tong, 4=QL_Kho_CN
    $roleId = $_SESSION['login']['role_id'] ?? null;
    
    $isManager = in_array($roleId, [1, 2, 4]);
    
    switch ($action) {
        case 'approve':
            // Check authorization
            if (!$isManager) {
                echo json_encode(['ok' => false, 'error' => 'Chỉ quản lý mới có quyền duyệt phiếu']);
                break;
            }
            
            // Approve sheet
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $sheetId = $input['sheet_id'] ?? '';
            $note = $input['note'] ?? '';
            $locations = $input['locations'] ?? []; // Get location assignments
            
            if (!$sheetId) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu ID phiếu']);
                break;
            }
            
            $userId = $_SESSION['login']['user_id'] ?? $_SESSION['login']['id'] ?? '';
            $userName = $_SESSION['login']['name'] ?? $_SESSION['login']['username'] ?? 'Unknown';
            
            
            $result = $cSheet->approveSheet($sheetId, $userId, $userName, $note, $locations);
            
            
            echo json_encode($result);
            break;

        case 'reject':
            // Check authorization
            if (!$isManager) {
                echo json_encode(['ok' => false, 'error' => 'Chỉ quản lý mới có quyền từ chối phiếu']);
                break;
            }
            
            // Reject sheet
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $sheetId = $input['sheet_id'] ?? '';
            $note = $input['note'] ?? '';
            
            if (!$sheetId) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu ID phiếu']);
                break;
            }
            
            $userId = $_SESSION['login']['user_id'] ?? $_SESSION['login']['id'] ?? '';
            $userName = $_SESSION['login']['name'] ?? $_SESSION['login']['username'] ?? 'Unknown';
            
            
            $result = $cSheet->rejectSheet($sheetId, $userId, $userName, $note);
            
            
            echo json_encode($result);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Hành động không hợp lệ']);
    }
} catch (Throwable $e) {
    error_log('inventory_sheets/process.php error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
