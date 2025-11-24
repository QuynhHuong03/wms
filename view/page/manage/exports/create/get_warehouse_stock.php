<?php
/**
 * API: Get product stock across all warehouses
 * Lấy số lượng tồn kho của sản phẩm tại tất cả các kho
 */
error_reporting(0); // Suppress errors that might break JSON
ini_set('display_errors', 0);
ob_start(); // Start output buffering

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../model/connect.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

try {
    ob_clean(); // Clean any output before JSON
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = $input['product_id'] ?? '';
    
    if (!$productId) {
        echo json_encode(['success' => false, 'error' => 'Missing product_id']);
        exit;
    }
    
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    if (!$con) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
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
    
    ob_clean(); // Clean before final output
    echo json_encode([
        'success' => true,
        'product_id' => $productId,
        'warehouses' => $stockByWarehouse
    ]);
    
} catch (Exception $e) {
    ob_clean(); // Clean before error output
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
ob_end_flush();
?>
