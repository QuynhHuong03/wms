<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' =&gt; '/', 'secure' =&gt; false, 'httponly' =&gt; true, 'samesite' =&gt; 'Lax']); session_start(); }
// From: view/page/manage/inventory/createInventory_sheet/process.php
// To: controller/cInventorySheet.php
include_once(__DIR__ . '/../../../../../controller/cInventorySheet.php');

$cSheet = new CInventorySheet();

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'create';

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        case 'get_stock':
            // Get current system stock for inventory count
            $filters = [
                'from' => $_GET['from'] ?? '',
                'to' => $_GET['to'] ?? ''
            ];
            $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? ($_SESSION['warehouse_id'] ?? '');
            error_log('get_stock: warehouse=' . $warehouseId . ', filters=' . json_encode($filters));
            
            $stock = $cSheet->getSystemStock($warehouseId, $filters);
            error_log('get_stock result: ' . count($stock) . ' items');
            
            echo json_encode(['ok' => true, 'data' => $stock, 'debug' => ['warehouse' => $warehouseId, 'count' => count($stock)]]);
            break;

        case 'create':
            // Create new inventory sheet
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            // Log items để kiểm tra SKU
            if (isset($input['items']) && is_array($input['items'])) {
                error_log('Creating sheet with ' . count($input['items']) . ' items');
                if (count($input['items']) > 0) {
                    error_log('First item: ' . json_encode($input['items'][0]));
                }
            }
            
            $sheetId = $cSheet->createInventorySheet($input);
            if ($sheetId) {
                echo json_encode(['ok' => true, 'sheet_id' => (string)$sheetId]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Không thể tạo phiếu kiểm kê']);
            }
            break;

        case 'update_items':
            // Update items in sheet
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $sheetId = $input['sheet_id'] ?? '';
            $items = $input['items'] ?? [];
            
            if (!$sheetId || empty($items)) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu thông tin']);
                break;
            }
            
            $result = $cSheet->updateSheetItems($sheetId, $items);
            echo json_encode(['ok' => $result]);
            break;

        case 'get_sheet':
            // Get sheet by ID
            $sheetId = $_GET['id'] ?? '';
            if (!$sheetId) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu ID phiếu']);
                break;
            }
            
            $sheet = $cSheet->getSheetById($sheetId);
            if ($sheet) {
                echo json_encode(['ok' => true, 'data' => $sheet]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Không tìm thấy phiếu']);
            }
            break;

        case 'update_status':
            // Update sheet status
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $sheetId = $input['sheet_id'] ?? '';
            $status = $input['status'] ?? '';
            
            if (!$sheetId || !$status) {
                echo json_encode(['ok' => false, 'error' => 'Thiếu thông tin']);
                break;
            }
            
            $result = $cSheet->updateSheetStatus($sheetId, $status);
            echo json_encode(['ok' => $result]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Hành động không hợp lệ']);
    }
} catch (Throwable $e) {
    error_log('process.php error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Lỗi: ' . $e->getMessage()]);
}
?>
