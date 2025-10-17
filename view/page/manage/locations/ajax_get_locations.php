<?php
// Simple HTML snippet endpoint to show locations for a warehouse
// Usage: ajax_get_locations.php?warehouse_id=W1

header('Content-Type: text/html; charset=utf-8');

// Safety: keep this endpoint within the app
$ROOT = realpath(__DIR__ . '/../../../..');
if ($ROOT === false) { echo '<div style="color:red">Path error</div>'; exit; }

// Load controller
require_once($ROOT . '/controller/clocation.php');

// Start session in case controller depends on it
if (session_status() === PHP_SESSION_NONE) @session_start();

$warehouseId = isset($_GET['warehouse_id']) ? trim($_GET['warehouse_id']) : null;

$c = new CLocation();
$m = new MLocation();

// If no warehouse id passed, try from session
if (!$warehouseId) {
    $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
}

if (!$warehouseId) {
    echo '<div style="color:#991b1b;background:#fee2e2;border:1px solid #fecaca;padding:10px;border-radius:8px">Không xác định được kho.</div>';
    exit;
}

$loc = $m->getLocationByWarehouseId($warehouseId);

if (!$loc || empty($loc['zones'])) {
    echo '<div style="color:#1f2937">Kho chưa có cấu hình Zone/Rack/Bin.</div>';
    exit;
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
.bin{border:1px solid #e5e7eb;border-radius:8px;padding:6px 4px;text-align:center;background:#fff}
.bin .code{font-weight:700;font-size:11px;line-height:1.2}
.bin .meta{font-size:10px;color:#6b7280}
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
              <?php foreach ($bins as $b): ?>
                <div class="bin">
                  <div class="code"><?= htmlspecialchars($b['code'] ?? ($b['bin_id'] ?? '')) ?></div>
                  <div class="meta">
                    <?= isset($b['current_load']) ? ((int)$b['current_load']) : 0 ?>/<?= isset($b['capacity']) ? ((int)$b['capacity']) : 0 ?>
                  </div>
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
