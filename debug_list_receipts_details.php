<?php
require __DIR__ . '/model/mReceipt.php';
$m = new MReceipt();
$receipts = $m->getAllReceipts([]);
$count = 0;
foreach ($receipts as $r) {
    $count++;
    $arr = json_decode(json_encode($r), true);
    echo "Receipt: " . ($arr['transaction_id'] ?? $arr['_id']['\$oid'] ?? $count) . "\n";
    if (isset($arr['details'])) {
        echo " details count: " . count($arr['details']) . "\n";
        print_r($arr['details']);
    } elseif (isset($arr['items'])) {
        echo " items count: " . count($arr['items']) . "\n";
        print_r($arr['items']);
    } else {
        echo " no details/items\n";
    }
    echo "---\n";
    if ($count > 5) break;
}
echo "Total receipts iterated: $count\n";
