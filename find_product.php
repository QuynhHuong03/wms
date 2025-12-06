<?php
require __DIR__ . '/model/connect.php';
$db = (new Database())->getConnection();
$key = $argv[1] ?? '-0005';
// Try SKU exact
$prod = $db->products->findOne(['sku' => (string)$key]);
if (!$prod) {
    // try product_name contains
    $cursor = $db->products->find(['product_name' => new MongoDB\BSON\Regex($key, 'i')]);
    $arr = $cursor->toArray();
    if (count($arr) > 0) $prod = $arr[0];
}
if ($prod) {
    echo "FOUND PRODUCT:\n";
    print_r(json_decode(json_encode($prod), true));
} else {
    echo "NO PRODUCT FOUND for key: $key\n";
}
