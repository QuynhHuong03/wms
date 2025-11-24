<?php
// Sync bin quantities from inventory to locations
// Usage: Navigate to this file in browser to run sync

if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . '/../controller/cSync.php');

$warehouseId = $_SESSION['login']['warehouse_id'] ?? 'KHO_TONG_01';

echo '<h2>Đồng bộ số lượng từ Inventory sang Locations</h2>';
echo '<p>Warehouse: ' . htmlspecialchars($warehouseId) . '</p>';

$cSync = new CSync();
$result = $cSync->syncBinQuantitiesFromInventory($warehouseId);

if ($result['ok']) {
    echo '<p style="color:green">✓ Đã cập nhật ' . ($result['updated'] ?? 0) . ' bins</p>';
} else {
    echo '<p style="color:red">✗ Lỗi: ' . ($result['error'] ?? 'Unknown') . '</p>';
}

echo '<p><a href="index.php?page=receipts/locate&id=IR0001">← Quay lại xem kho</a></p>';
?>
