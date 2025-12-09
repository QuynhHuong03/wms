<?php
header('Content-Type: application/json; charset=UTF-8');
include_once(__DIR__ . '/../../../../model/connect.php');

$barcode = $_GET['barcode'] ?? 'LH0003';

$p = new clsKetNoi();
$con = $p->moKetNoi();

if (!$con) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$batchesCol = $con->selectCollection('batches');

// Try to find by barcode
$byBarcode = $batchesCol->findOne(['barcode' => $barcode]);

// Try to find by batch_code
$byBatchCode = $batchesCol->findOne(['batch_code' => $barcode]);

// Get all batches (first 5)
$allBatches = iterator_to_array($batchesCol->find([], ['limit' => 5, 'sort' => ['created_at' => -1]]));

$result = [
    'search_term' => $barcode,
    'found_by_barcode' => $byBarcode ? true : false,
    'found_by_batch_code' => $byBatchCode ? true : false,
    'batch_by_barcode' => $byBarcode ? [
        'batch_code' => $byBarcode['batch_code'] ?? null,
        'barcode' => $byBarcode['barcode'] ?? null,
        'product_name' => $byBarcode['product_name'] ?? null,
        'quantity_remaining' => $byBarcode['quantity_remaining'] ?? null,
    ] : null,
    'batch_by_batch_code' => $byBatchCode ? [
        'batch_code' => $byBatchCode['batch_code'] ?? null,
        'barcode' => $byBatchCode['barcode'] ?? null,
        'product_name' => $byBatchCode['product_name'] ?? null,
        'quantity_remaining' => $byBatchCode['quantity_remaining'] ?? null,
    ] : null,
    'total_batches' => $batchesCol->countDocuments([]),
    'sample_batches' => array_map(function($b) {
        return [
            'batch_code' => $b['batch_code'] ?? null,
            'barcode' => $b['barcode'] ?? null,
            'product_name' => $b['product_name'] ?? null,
            'warehouse_id' => $b['warehouse_id'] ?? null,
        ];
    }, $allBatches)
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$p->dongKetNoi($con);
?>
