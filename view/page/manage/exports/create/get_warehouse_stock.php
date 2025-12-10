<?php
/**
 * API: Get product stock across all warehouses
 * Lấy số lượng tồn kho của sản phẩm tại tất cả các kho
 */

// CRITICAL: Start output buffering FIRST
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../../model/connect.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

try {
    // Clean any accidental output from includes
    ob_clean();
    
    // Get and validate input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    $productId = $input['product_id'] ?? '';
    $destinationWarehouse = $input['destination_warehouse'] ?? null;
    
    if (!$productId) {
        throw new Exception('Missing product_id');
    }
    
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Get all warehouses
    $warehouseCol = $con->selectCollection('warehouses');
    $warehouses = $warehouseCol->find([]);
    
    // Get inventory for this product from all warehouses
    $inventoryCol = $con->selectCollection('inventory');
    $stockByWarehouse = [];
    
    foreach ($warehouses as $wh) {
        $warehouseId = $wh['warehouse_id'] ?? '';
        $warehouseName = $wh['warehouse_name'] ?? $warehouseId;
        $warehouseType = $wh['type'] ?? 'branch';
        
        // Bỏ qua kho đích (kho yêu cầu)
        if ($destinationWarehouse && $warehouseId === $destinationWarehouse) {
            continue;
        }
        
        // Sum up quantity for this product in this warehouse
        $pipeline = [
            ['$match' => [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'qty' => ['$gt' => 0]
            ]],
            ['$group' => [
                '_id' => null,
                'total_qty' => ['$sum' => '$qty']
            ]]
        ];
        
        $result = $inventoryCol->aggregate($pipeline)->toArray();
        $totalQty = !empty($result) ? (int)($result[0]['total_qty'] ?? 0) : 0;
        
        $stockByWarehouse[] = [
            'warehouse_id' => $warehouseId,
            'warehouse_name' => $warehouseName,
            'warehouse_type' => $warehouseType,
            'available_qty' => $totalQty
        ];
    }
    
    // Sort by quantity descending (warehouses with most stock first)
    usort($stockByWarehouse, function($a, $b) {
        return $b['available_qty'] - $a['available_qty'];
    });
    
    // Final clean before output
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'product_id' => $productId,
        'warehouses' => $stockByWarehouse
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename(__FILE__),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => basename(__FILE__),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
exit;
?>
