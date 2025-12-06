<?php
require __DIR__ . '/model/connect.php';
$db = (new Database())->getConnection();
$cursor = $db->products->find([], ['limit'=>30])->toArray();
foreach ($cursor as $p) {
    $a = json_decode(json_encode($p), true);
    echo ($a['_id'] ?? '') . " | " . ($a['sku'] ?? '') . " | " . ($a['product_name'] ?? '') . "\n";
}
