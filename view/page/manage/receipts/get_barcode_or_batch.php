<?php
// Ensure any PHP warnings/notices don't break JSON responses
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

require_once(__DIR__ . '/../../../../controller/cProduct.php');
try {
    $barcode = trim($_GET['barcode'] ?? '');
    if ($barcode === '') {
        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => false, "message" => "Thiếu mã barcode"]);
        exit;
    }

    $cp = new CProduct();
    $product = $cp->getProductByBarcode($barcode);
    if ($product) {
        // Normalize minimal product payload
        $id = '';
        if (isset($product['_id'])) {
            if ($product['_id'] instanceof MongoDB\BSON\ObjectId) $id = (string)$product['_id'];
            elseif (is_array($product['_id']) && isset($product['_id']['$oid'])) $id = (string)$product['_id']['$oid'];
            else $id = (string)$product['_id'];
        }
        $resp = [
            'success' => true,
            'product' => [
                '_id' => $id,
                'sku' => $product['sku'] ?? '',
                'barcode' => $product['barcode'] ?? '',
                'product_name' => $product['product_name'] ?? '',
                'baseUnit' => $product['baseUnit'] ?? 'Cái',
                'conversionUnits' => $product['conversionUnits'] ?? [],
                'package_dimensions' => $product['package_dimensions'] ?? [],
                'package_weight' => $product['package_weight'] ?? 0,
                'volume_per_unit' => $product['volume_per_unit'] ?? 0,
                'purchase_price' => $product['purchase_price'] ?? 0
            ]
        ];
        if (ob_get_length()) ob_clean();
        echo json_encode($resp);
        exit;
    }

    // Not a product - try batches collection
    include_once(__DIR__ . '/../../../../model/connect.php');
    // Use clsKetNoi to connect to the project's canonical 'WMS' database
    $db = (new clsKetNoi())->moKetNoi();
    $batch = $db->batches->findOne(['batch_code' => $barcode]);
    if (!$batch) $batch = $db->batches->findOne(['barcode' => $barcode]);

    if ($batch) {
        $b = json_decode(json_encode($batch), true);
        $batchId = '';
        if (isset($b['_id'])) {
            if (is_array($b['_id']) && isset($b['_id']['$oid'])) $batchId = (string)$b['_id']['$oid'];
            else $batchId = (string)$b['_id'];
        }

        $batchPayload = [
            '_id' => $batchId,
            'batch_code' => $b['batch_code'] ?? ($b['barcode'] ?? ''),
            'barcode' => $b['barcode'] ?? ($b['batch_code'] ?? ''),
            'product_id' => isset($b['product_id']) ? (string)$b['product_id'] : '',
            'product_name' => $b['product_name'] ?? '',
            'quantity_imported' => $b['quantity_imported'] ?? $b['quantity'] ?? 0,
            'quantity_remaining' => $b['quantity_remaining'] ?? $b['quantity'] ?? 0,
            'import_date' => $b['import_date'] ?? null,
            'unit_price' => $b['unit_price'] ?? 0,
            'unit' => $b['unit'] ?? '',
            'warehouse_id' => $b['warehouse_id'] ?? '',
            'source' => $b['source'] ?? '',
            'source_warehouse_id' => $b['source_warehouse_id'] ?? '',
            'locations' => $b['source_location'] ?? ($b['locations'] ?? [])
        ];

        $productPayload = null;
        if (!empty($batchPayload['product_id'])) {
            $cp2 = new CProduct();
            $prod = $cp2->getProductById($batchPayload['product_id']);
            if ($prod) {
                $pid = '';
                if (isset($prod['_id'])) {
                    if ($prod['_id'] instanceof MongoDB\BSON\ObjectId) $pid = (string)$prod['_id'];
                    elseif (is_array($prod['_id']) && isset($prod['_id']['$oid'])) $pid = (string)$prod['_id']['$oid'];
                    else $pid = (string)$prod['_id'];
                }
                $productPayload = [
                    '_id' => $pid,
                    'sku' => $prod['sku'] ?? '',
                    'barcode' => $prod['barcode'] ?? '',
                    'product_name' => $prod['product_name'] ?? '',
                    'baseUnit' => $prod['baseUnit'] ?? 'Cái',
                    'conversionUnits' => $prod['conversionUnits'] ?? [],
                    'package_dimensions' => $prod['package_dimensions'] ?? [],
                    'package_weight' => $prod['package_weight'] ?? 0,
                    'volume_per_unit' => $prod['volume_per_unit'] ?? 0,
                    'purchase_price' => $prod['purchase_price'] ?? 0
                ];
            }
        }

        if (ob_get_length()) ob_clean();
        echo json_encode(["success" => true, "batch" => $batchPayload, "product" => $productPayload]);
        exit;
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm hoặc lô hàng"]);
    exit;

} catch (\Throwable $e) {
    error_log('get_barcode_or_batch.php exception: ' . $e->getMessage());
    if (ob_get_length()) ob_clean();
    echo json_encode(["success" => false, "message" => "Lỗi server"]);
    exit;
}

?>
