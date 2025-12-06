<?php
// test_debug_reports.php
// Usage (PowerShell): php test_debug_reports.php --from=2025-10-30 --to=2025-11-28

require __DIR__ . '/model/connect.php';
require __DIR__ . '/model/mReceipt.php';

$options = getopt('', ['from::', 'to::', 'limit::', 'warehouse::', 'debug::']);
$from = $options['from'] ?? date('Y-m-d', strtotime('-29 days'));
$to = $options['to'] ?? date('Y-m-d');
$limit = isset($options['limit']) ? intval($options['limit']) : 20;
$warehouse = $options['warehouse'] ?? null;
$debug = isset($options['debug']);

$m = new MReceipt();
$cursor = $m->getReceiptsByDateRange($from, $to);

$rows = [];
$types = [];
$count = 0;
foreach ($cursor as $r) {
    $count++;
    $tid = $r['transaction_type'] ?? ($r['type'] ?? null);
    $types[$tid] = ($types[$tid] ?? 0) + 1;
    if ($warehouse && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouse) continue;
    $rows[] = $r;
    if (count($rows) >= $limit) break;
}

$output = [
    'from' => $from,
    'to' => $to,
    'warehouse' => $warehouse,
    'total_matched_in_range' => $count,
    'transaction_type_counts' => $types,
    'sample_count' => count($rows),
    'samples' => []
];

foreach ($rows as $r) {
    $sample = [];
    $sample['transaction_id'] = $r['transaction_id'] ?? (isset($r['_id']) ? (is_object($r['_id']) ? (string)$r['_id'] : $r['_id']) : null);
    $sample['type'] = $r['transaction_type'] ?? ($r['type'] ?? null);
    if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $sample['created_at'] = $r['created_at']->toDateTime()->format(DateTime::ATOM);
    } else {
        $sample['created_at'] = $r['created_at'] ?? null;
    }
    $sample['warehouse_id'] = $r['warehouse_id'] ?? null;
    $output['samples'][] = $sample;
}

if ($debug) {
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    // human-friendly
    echo "Receipts between $from and $to:\n";
    echo "  Total matched: " . $output['total_matched_in_range'] . "\n";
    echo "  Transaction types:\n";
    foreach ($output['transaction_type_counts'] as $t => $c) {
        echo "    - " . ($t ?? '(null)') . ": $c\n";
    }
    echo "\nSample rows (up to $limit):\n";
    foreach ($output['samples'] as $s) {
        echo "  * " . ($s['transaction_id'] ?? '-') . " | " . ($s['type'] ?? '-') . " | " . ($s['created_at'] ?? '-') . " | " . ($s['warehouse_id'] ?? '-') . "\n";
    }
}

?>