<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/../../../../model/connect.php');

$export_id = $_GET['id'] ?? null;
if (!$export_id) {
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit();
}

$p = new clsKetNoi();
$con = $p->moKetNoi();
if (!$con) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit();
}

$transactionsCol = $con->selectCollection('transactions');

try {
    $export = $transactionsCol->findOne(['transaction_id' => $export_id, 'transaction_type' => 'export']);
    if (!$export) {
        echo json_encode(['success' => false, 'message' => 'Export not found']);
        $p->dongKetNoi($con);
        exit();
    }

    // Normalize details and batches for frontend
    $details = $export['details'] ?? [];
    $batches = $export['batches'] ?? [];

    // Build lookups: products by id and batches by batch_code
    $products = [];
    foreach ($details as $d) {
        $pid = $d['product_id'] ?? null;
        if ($pid) {
            $products[$pid] = [
                'product_id' => $pid,
                'sku' => $d['sku'] ?? null,
                'product_name' => $d['product_name'] ?? null,
                'quantity' => $d['quantity'] ?? 0,
                'unit' => $d['unit'] ?? null
            ];
        }
    }

    $batchList = [];
    foreach ($batches as $b) {
        $code = $b['batch_code'] ?? null;
        if ($code) {
            $batchList[$code] = [
                'batch_code' => $code,
                'product_id' => $b['product_id'] ?? null,
                'quantity' => $b['quantity'] ?? 0,
                'unit_price' => $b['unit_price'] ?? 0,
                'source_location' => $b['source_location'] ?? []
            ];
        }
    }

    $result = [
        'success' => true,
        'export' => [
            'transaction_id' => $export['transaction_id'] ?? $export_id,
            'created_at' => isset($export['created_at']) ? (
                $export['created_at'] instanceof MongoDB\BSON\UTCDateTime ? $export['created_at']->toDateTime()->format(DATE_ATOM) : strval($export['created_at'])
            ) : null,
            'warehouse_id' => $export['warehouse_id'] ?? ($export['source_warehouse_id'] ?? null),
            'destination_warehouse_id' => $export['destination_warehouse_id'] ?? null,
            'products' => array_values($products),
            'batches' => array_values($batchList)
        ]
    ];

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$p->dongKetNoi($con);
exit();

?>
