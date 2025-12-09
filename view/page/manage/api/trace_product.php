<?php
/**
 * API: Truy xuất nguồn gốc sản phẩm qua barcode hoặc batch_code
 * URL: trace_product.php?barcode=XXX hoặc ?batch_code=LH0003
 */

header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . "/../../../../model/connect.php");

$barcode = $_GET['barcode'] ?? '';
$batchCode = $_GET['batch_code'] ?? '';
$warehouseId = $_GET['warehouse_id'] ?? '';

if (empty($barcode) && empty($batchCode)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Vui lòng nhập barcode hoặc batch_code'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    // 1. Tìm batch theo barcode hoặc batch_code
    $query = [];
    $searchValue = !empty($barcode) ? $barcode : $batchCode;
    
    if (!empty($searchValue)) {
        // Thử tìm product trước (barcode sản phẩm như BAR100012)
        $product = $db->products->findOne(['barcode' => $searchValue]);
        
        if ($product) {
            // Tìm batches của product này
            $query['product_id'] = (string)$product['_id'];
            error_log("Found product: " . $product['product_name'] . " (ID: {$query['product_id']})");
        } else {
            // Không phải product barcode, tìm theo batch_code (như LH0003)
            $query['batch_code'] = $searchValue;
            error_log("Not a product barcode, searching batch_code: $searchValue");
        }
    }
    
    if (!empty($warehouseId)) {
        $query['warehouse_id'] = $warehouseId;
    }
    
    // Debug log
    error_log("Trace API Query: " . json_encode($query));
    
    $batches = $db->batches->find($query, ['sort' => ['created_at' => -1]])->toArray();
    
    error_log("Trace API Found: " . count($batches) . " batches");
    
    if (empty($batches)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Không tìm thấy batch nào với thông tin này',
            'debug' => [
                'barcode' => $barcode,
                'batch_code' => $batchCode,
                'warehouse_id' => $warehouseId,
                'query' => $query
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $traceData = [];
    
    foreach ($batches as $batch) {
        $batchArray = json_decode(json_encode($batch), true);
        $bCode = $batchArray['batch_code'];
        
        // 2. Lấy vị trí hiện tại từ batch_locations
        $currentLocations = $db->batch_locations->find([
            'batch_code' => $bCode
        ])->toArray();
        
        $locationsList = [];
        foreach ($currentLocations as $loc) {
            $locArray = json_decode(json_encode($loc), true);
            $location = $locArray['location'] ?? [];
            
            $locationsList[] = [
                'warehouse_id' => $location['warehouse_id'] ?? '',
                'zone_id' => $location['zone_id'] ?? '',
                'rack_id' => $location['rack_id'] ?? '',
                'bin_id' => $location['bin_id'] ?? '',
                'quantity' => $locArray['quantity'] ?? 0,
                'source_location' => $locArray['source_location'] ?? null
            ];
        }
        
        // 3. Lấy lịch sử di chuyển từ inventory_movements (nếu có)
        $movements = $db->inventory_movements->find(
            ['batch_code' => $bCode],
            ['sort' => ['created_at' => 1], 'limit' => 50]
        )->toArray();
        
        $movementHistory = [];
        foreach ($movements as $m) {
            $mArray = json_decode(json_encode($m), true);
            
            $movementDate = '';
            if (isset($mArray['created_at']['$date']['$numberLong'])) {
                $timestamp = (int)$mArray['created_at']['$date']['$numberLong'] / 1000;
                $movementDate = date('d/m/Y H:i', $timestamp);
            }
            
            $movementHistory[] = [
                'type' => $mArray['movement_type'] ?? '',
                'from_location' => $mArray['from_location'] ?? null,
                'to_location' => $mArray['to_location'] ?? null,
                'quantity' => $mArray['quantity'] ?? 0,
                'date' => $movementDate,
                'note' => $mArray['note'] ?? '',
                'transaction_id' => $mArray['transaction_id'] ?? ''
            ];
        }
        
        // 4. Format dates
        $importDate = '';
        if (isset($batchArray['import_date']['$date']['$numberLong'])) {
            $timestamp = (int)$batchArray['import_date']['$date']['$numberLong'] / 1000;
            $importDate = date('d/m/Y H:i', $timestamp);
        } elseif (is_string($batchArray['import_date'])) {
            $importDate = $batchArray['import_date'];
        }
        
        $createdAt = '';
        if (isset($batchArray['created_at']['$date']['$numberLong'])) {
            $timestamp = (int)$batchArray['created_at']['$date']['$numberLong'] / 1000;
            $createdAt = date('d/m/Y H:i', $timestamp);
        }
        
        // 5. Lấy thông tin sản phẩm
        $productId = $batchArray['product_id'];
        if (is_array($productId) && isset($productId['$oid'])) {
            $productId = $productId['$oid'];
        }
        
        $product = null;
        try {
            $product = $db->products->findOne(['_id' => new MongoDB\BSON\ObjectId($productId)]);
        } catch (Exception $e) {
            // Ignore if product not found
        }
        
        $productInfo = null;
        if ($product) {
            $productArray = json_decode(json_encode($product), true);
            $productInfo = [
                'product_id' => $productId,
                'product_name' => $productArray['product_name'] ?? '',
                'sku' => $productArray['sku'] ?? '',
                'barcode' => $productArray['barcode'] ?? ''
            ];
        }
        
        // 6. Tổng hợp dữ liệu
        $traceData[] = [
            'batch_code' => $bCode,
            'product' => $productInfo,
            'current_warehouse' => $batchArray['warehouse_id'] ?? '',
            'quantity_imported' => $batchArray['quantity_imported'] ?? 0,
            'quantity_remaining' => $batchArray['quantity_remaining'] ?? 0,
            'unit_price' => $batchArray['unit_price'] ?? 0,
            'unit' => $batchArray['unit'] ?? '',
            'import_date' => $importDate,
            'created_at' => $createdAt,
            'source' => $batchArray['source'] ?? '', // import / transfer
            'source_warehouse_id' => $batchArray['source_warehouse_id'] ?? null,
            'source_location' => $batchArray['source_location'] ?? null,
            'receipt_id' => $batchArray['receipt_id'] ?? '',
            'status' => $batchArray['status'] ?? '',
            'current_locations' => $locationsList,
            'movement_history' => $movementHistory
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($traceData),
        'data' => $traceData
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
