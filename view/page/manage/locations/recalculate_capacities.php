<?php
/**
 * AJAX endpoint to recalculate bin capacities
 * POST: { "warehouse_id": "KHO_TONG_01" }
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/../../../../model/mLocation.php');

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }
    
    // Get warehouse ID
    $input = json_decode(file_get_contents('php://input'), true);
    $warehouseId = $input['warehouse_id'] ?? $_SESSION['login']['warehouse_id'] ?? null;
    
    if (!$warehouseId) {
        echo json_encode([
            'success' => false,
            'error' => 'Warehouse ID is required'
        ]);
        exit;
    }
    
    // Recalculate
    $mLocation = new MLocation();
    $result = $mLocation->recalculateAllBinCapacities($warehouseId);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Đã cập nhật capacity cho tất cả bins',
            'stats' => $result['stats'],
            'warehouse_id' => $warehouseId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
