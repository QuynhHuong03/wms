<?php
// Simple HTML snippet endpoint to show locations for a warehouse
// Usage: ajax_get_locations.php?warehouse_id=W1

header('Content-Type: text/html; charset=utf-8');

// Safety: keep this endpoint within the app
$ROOT = realpath(__DIR__ . '/../../../..');
if ($ROOT === false) { echo '<div style="color:red">Path error</div>'; exit; }

// Load controller
require_once($ROOT . '/controller/clocation.php');
require_once($ROOT . '/model/mInventory.php');

// Start session in case controller depends on it
if (session_status() === PHP_SESSION_NONE) @session_start();

$warehouseId = isset($_GET['warehouse_id']) ? trim($_GET['warehouse_id']) : null;

$c = new CLocation();
$m = new MLocation();
$mInventory = new MInventory();

// If no warehouse id passed, try from session
if (!$warehouseId) {
    $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
}

if (!$warehouseId) {
    echo '<div style="color:#991b1b;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px">Không xác định được kho.</div>';
    exit;
}

error_log("📍 ajax_get_locations.php - Loading bins for warehouse: $warehouseId");

$loc = $m->getLocationByWarehouseId($warehouseId);

if (!$loc || empty($loc['zones'])) {
    echo '<div style="color:#1f2937">Kho chưa có cấu hình Zone/Rack/Bin.</div>';
    exit;
}

// Load all inventory data for this warehouse to map to bins
$inventoryData = [];
try {
    $allInventory = $mInventory->getInventoryByWarehouse($warehouseId);
    // Group by location (zone_id, rack_id, bin_id)
    foreach ($allInventory as $inv) {
        $zoneId = $inv['zone_id'] ?? '';
        $rackId = $inv['rack_id'] ?? '';
        $binId = $inv['bin_id'] ?? '';
        $productId = $inv['product_id'] ?? '';
        $productName = $inv['product_name'] ?? '';
        $qty = isset($inv['qty']) ? (int)$inv['qty'] : 0;
        
        if ($zoneId && $rackId && $binId) {
            $key = $zoneId . '|' . $rackId . '|' . $binId;
            if (!isset($inventoryData[$key])) {
                $inventoryData[$key] = [
                    'quantity' => 0,
                    'product_id' => '',
                    'product_name' => '',
                    'products' => []
                ];
            }
            
            $inventoryData[$key]['quantity'] += $qty;
            
            // Store product info (use first product if multiple)
            if (empty($inventoryData[$key]['product_id']) && $productId) {
                $inventoryData[$key]['product_id'] = $productId;
                $inventoryData[$key]['product_name'] = $productName;
            }
            
            // Track all products in this bin
            if ($productId && !in_array($productId, $inventoryData[$key]['products'])) {
                $inventoryData[$key]['products'][] = $productId;
            }
        }
    }
} catch (Exception $e) {
    error_log('Error loading inventory data: ' . $e->getMessage());
}

// Basic styles (scoped)
?>
<style>
.location-wrap{display:flex;flex-direction:column;gap:10px}
.zone-card{border:1px solid #e5e7eb;border-radius:10px;padding:10px;background:#fff}
.zone-title{font-weight:700;margin:0 0 6px;color:#111827;font-size:14px}
/* Make racks lay out more horizontally with compact width */
.rack-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px}
.rack-card{border:1px dashed #d1d5db;border-radius:8px;padding:8px;background:#f9fafb}
.rack-title{font-weight:600;margin:0 0 6px;color:#374151;font-size:13px}
/* Compact bins and allow more columns */
.bin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:6px}
.bin{border:2px solid #e5e7eb;border-radius:8px;padding:6px 4px;text-align:center;background:#fff;cursor:pointer;transition:all 0.2s}
.bin:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,0.15)}
.bin .code{font-weight:700;font-size:11px;line-height:1.2}
/* Quantity line */
.bin .qty{font-size:10px;line-height:1.2;opacity:.85;margin-top:2px}
/* Dimensions line */
.bin .dims{font-size:9px;line-height:1.2;opacity:.75;margin-top:2px;color:#6b7280}
/* Utilization line */
.bin .util{font-size:10px;line-height:1.2;margin-top:3px;font-weight:600}
.bin .util.low{color:#10b981;background:#d1fae5;padding:2px 4px;border-radius:4px}
.bin .util.medium{color:#d97706;background:#fef3c7;padding:2px 4px;border-radius:4px}
.bin .util.high{color:#dc2626;background:#fecaca;padding:2px 4px;border-radius:4px}
/* Status colors based on capacity percentage */
/* 0% = green (empty) */
.bin[data-status="empty"]{background:#ecfdf5;border-color:#10b981;color:#065f46}
.bin[data-status="empty"] .qty{color:#047857;opacity:1;font-weight:600}
/* 1-80% = yellow (partial) */
.bin[data-status="partial"]{background:#fffbeb;border-color:#f59e0b;color:#78350f}
.bin[data-status="partial"] .qty{color:#92400e;opacity:1;font-weight:600}
/* >80% = red (full/nearly full) */
.bin[data-status="full"]{background:#fef2f2;border-color:#dc2626;color:#7f1d1d}
.bin[data-status="full"] .qty{color:#991b1b;opacity:1;font-weight:700}
</style>
<div class="location-wrap">
<?php foreach ($loc['zones'] as $z): ?>
  <div class="zone-card">
    <div class="zone-title">Zone: <?= htmlspecialchars($z['name'] ?? ($z['_id'] ?? '')) ?> (<?= htmlspecialchars($z['_id'] ?? '') ?>)</div>
    <?php $racks = $z['racks'] ?? []; if (empty($racks)): ?>
      <div class="muted">Chưa có rack.</div>
    <?php else: ?>
      <div class="rack-grid">
      <?php foreach ($racks as $r): $bins = $r['bins'] ?? []; ?>
        <div class="rack-card">
          <div class="rack-title">Rack: <?= htmlspecialchars($r['name'] ?? ($r['rack_id'] ?? '')) ?> (<?= htmlspecialchars($r['rack_id'] ?? '') ?>)</div>
          <?php if (empty($bins)): ?>
            <div class="muted">Chưa có bin.</div>
          <?php else: ?>
            <div class="bin-grid">
              <?php foreach ($bins as $b): 
                $binId = $b['bin_id'] ?? ($b['id'] ?? '');
                $binCode = $b['code'] ?? $binId;
                $cap = $b['capacity'] ?? 0;
                
                // Lấy status từ cột status trong warehouse_structure (bin)
                $status = $b['status'] ?? 'empty';
                
                // Get data from inventory for quantity and product only
                $zoneId = $z['_id'] ?? ($z['zone_id'] ?? '');
                $rackId = $r['rack_id'] ?? '';
                $locationKey = $zoneId . '|' . $rackId . '|' . $binId;
                
                $qty = 0;
                $product = '';
                $productId = '';
                
                if (isset($inventoryData[$locationKey])) {
                    $qty = $inventoryData[$locationKey]['quantity'];
                    $productId = $inventoryData[$locationKey]['product_id'];
                    $product = $inventoryData[$locationKey]['product_name'];
                    if (!$product && $productId) {
                        $product = $productId;
                    }
                }
                
                $productLabel = is_string($product) ? trim($product) : '';
                
                // Get bin dimensions
                $dimensions = $b['dimensions'] ?? [];
                $binWidth = isset($dimensions['width']) ? (float)$dimensions['width'] : 0;
                $binDepth = isset($dimensions['depth']) ? (float)$dimensions['depth'] : 0;
                $binHeight = isset($dimensions['height']) ? (float)$dimensions['height'] : 0;
                $dimText = '';
                if ($binWidth > 0 || $binDepth > 0 || $binHeight > 0) {
                    $dimText = sprintf("%.1f×%.1f×%.1f", $binWidth, $binDepth, $binHeight);
                }
                
                // Get product dimensions if product exists in bin
                $productDimText = '';
                if ($productId) {
                    try {
                        require_once($ROOT . '/controller/cProduct.php');
                        $cProduct = new CProduct();
                        $productData = $cProduct->getProductById($productId);
                        if ($productData) {
                            $pWidth = (float)($productData['width'] ?? $productData['length'] ?? 0);
                            $pDepth = (float)($productData['depth'] ?? $productData['width'] ?? 0);
                            $pHeight = (float)($productData['height'] ?? 0);
                            if ($pWidth > 0 || $pDepth > 0 || $pHeight > 0) {
                                $productDimText = sprintf("%.1f×%.1f×%.1f", $pWidth, $pDepth, $pHeight);
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Error fetching product dimensions: ' . $e->getMessage());
                    }
                }
                
                // Get current utilization/capacity from database
                $currentCapacity = (float)($b['current_capacity'] ?? 0);
                
                // ✅ Debug log để kiểm tra giá trị TRƯỚC khi xử lý
                error_log("📊 Bin $binId - DB current_capacity: {$currentCapacity}%, Inventory qty: $qty");
                
                // ⭐ FIX: Reset current_capacity = 0 khi bin không có hàng (qty = 0)
                // Không quan tâm database có giá trị gì, inventory mới là nguồn chân lý
                if ($qty <= 0) {
                    $currentCapacity = 0;
                    error_log("🔴 Bin $binId is empty - capacity reset to 0");
                }
                
                // Color logic: 0% = green, 1-80% = yellow, >80% = red
                $utilClass = 'low'; // green
                if ($currentCapacity > 80) {
                    $utilClass = 'high'; // red
                    $status = 'full'; // Override status based on capacity
                } elseif ($currentCapacity >= 1) {
                    $utilClass = 'medium'; // yellow
                    $status = 'partial'; // Override status based on capacity
                } else {
                    $utilClass = 'low'; // green
                    $status = 'empty'; // Override status based on capacity
                }
                
                error_log("📊 Bin $binId - Final capacity: {$currentCapacity}%, Status: $status, Class: $utilClass");
                
                $titleParts = [];
                $titleParts[] = 'Trạng thái: ' . htmlspecialchars($status);
                $titleParts[] = 'Sản phẩm: ' . ($productLabel !== '' ? htmlspecialchars($productLabel) : '(trống)');
                $titleParts[] = 'Số lượng: ' . (int)$qty;
                if ((int)$cap > 0) { $titleParts[] = 'Sức chứa: ' . (int)$cap; }
                if ($dimText) { $titleParts[] = 'Kích thước (cm): ' . $dimText; }
                $titleParts[] = 'Chiếm dụng: ' . number_format($currentCapacity, 1) . '%';
                $titleText = implode(' | ', $titleParts);
              ?>
       <div class="bin" 
         data-zone="<?= htmlspecialchars($zoneId) ?>"
                     data-rack="<?= htmlspecialchars($rackId) ?>"
                     data-bin="<?= htmlspecialchars($binId) ?>"
                     data-code="<?= htmlspecialchars($binCode) ?>"
                     data-quantity="<?= (int)$qty ?>"
                     data-capacity="<?= (int)$cap ?>"
                     data-status="<?= htmlspecialchars($status) ?>"
                     data-product="<?= htmlspecialchars($productId) ?>"
                     data-dimensions="<?= htmlspecialchars($dimText) ?>"
                     data-product-dimensions="<?= htmlspecialchars($productDimText) ?>"
                     data-utilization="<?= number_format($currentCapacity, 1, '.', '') ?>"
                     title="<?= $titleText ?>">
                  <div class="code"><?= htmlspecialchars($binCode) ?></div>
                  <div class="qty"><?= number_format((int)$qty, 0, ',', '.') ?></div>
                  <?php if ($dimText): ?>
                  <div class="dims"><?= htmlspecialchars($dimText) ?></div>
                  <?php endif; ?>
                  <div class="util <?= $utilClass ?>"><?= number_format($currentCapacity, 1) ?>%</div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
