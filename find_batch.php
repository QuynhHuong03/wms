<?php
require __DIR__ . '/model/connect.php';
$db = (new Database())->getConnection();
$code = $argv[1] ?? 'LH0005';
$batch = $db->batches->findOne(['batch_code' => $code]);
if (!$batch) $batch = $db->batches->findOne(['barcode' => $code]);
if ($batch) {
    echo "FOUND BATCH:\n";
    print_r(json_decode(json_encode($batch), true));
} else {
    echo "NO BATCH FOUND for $code\n";
}
// Also search transactions for details batches containing this batch_code
$txs = $db->transactions->find(['$or' => [['details.batch_code' => $code], ['products.batches.batch_code' => $code]]])->toArray();
if (count($txs) > 0) {
    echo "\nFound in transactions (count=".count($txs)."):\n";
    foreach ($txs as $t) {
        echo "- tx_id: ".($t['receipt_id'] ?? $t['_id'])."\n";
    }
} else {
    echo "\nNo transactions contain batch_code $code\n";
}
