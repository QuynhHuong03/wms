<?php
// Quick helper to activate a warehouse by warehouse_id via browser.
// Usage: debug_activate_warehouse.php?id=KHO_CN_04&confirm=1
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id'])) {
    echo "Usage: ?id=WAREHOUSE_ID&confirm=1\n";
    exit;
}
if (!isset($_GET['confirm']) || $_GET['confirm'] != '1') {
    echo "You must pass confirm=1 to perform changes. Example: ?id=KHO_CN_04&confirm=1\n";
    exit;
}
$wid = $_GET['id'];
require_once __DIR__ . '/model/mWarehouse.php';
$mw = new MWarehouse();
$result = $mw->updateWarehouse($wid, ['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
if ($result) {
    echo "Warehouse {$wid} activated (status=1).\n";
} else {
    echo "Failed to activate warehouse {$wid}. It may not exist or no changes were made.\n";
}

?>