<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../../../controller/cDashboard.php');

// Lấy thông tin user từ session
$user = $_SESSION['login'] ?? null;
$roleId = isset($user['role_id']) ? intval($user['role_id']) : null;
$warehouseId = isset($user['warehouse_id']) ? $user['warehouse_id'] : null;

$cDashboard = new CDashboard();
$data = $cDashboard->getDashboardData($roleId, $warehouseId);
// Build warehouse options used by selects.
// If user is Admin (roleId == 1) prefer the already computed summary (which may include per-warehouse totals).
// For Managers/Staff prefer the full raw list so they can select other branches.
$warehouseOptions = [];
// Helper: fetch and normalize raw warehouses
function __load_raw_warehouses_options() {
  $opts = [];
  if (file_exists(__DIR__ . '/../../../../model/mWarehouse.php')) {
    include_once(__DIR__ . '/../../../../model/mWarehouse.php');
    try {
      $mw = new MWarehouse();
      $raw = $mw->getAllWarehouses();
      if (is_array($raw)) {
        foreach ($raw as $w) {
          $wid = null;
          if (isset($w['warehouse_id'])) $wid = $w['warehouse_id'];
          elseif (isset($w['warehouseId'])) $wid = $w['warehouseId'];
          elseif (isset($w['id'])) $wid = $w['id'];
          elseif (isset($w['_id'])) {
            if (is_array($w['_id']) && isset($w['_id']['$oid'])) $wid = $w['_id']['$oid'];
            else $wid = (string)$w['_id'];
          }
          if (!$wid) continue;
          // include all warehouses; mark inactive ones visibly
          $name = isset($w['warehouse_name']) ? $w['warehouse_name'] : (isset($w['name']) ? $w['name'] : (isset($w['warehouseName']) ? $w['warehouseName'] : $wid));
          if (isset($w['status']) && intval($w['status']) !== 1) {
            $name .= ' (Không hoạt động)';
          }
          $opts[] = ['warehouse_id' => $wid, 'name' => $name];
        }
      }
    } catch (Exception $e) {
      // ignore
    }
  }
  return $opts;
}

// Helper: resolve SKU by product_id using mProduct when sku not provided
function __resolve_sku_by_product_id($productId) {
  if (!$productId) return '';
  $sku = '';
  $mpPath = __DIR__ . '/../../../../model/mProduct.php';
  if (file_exists($mpPath)) {
    include_once($mpPath);
    try {
      if (class_exists('MProduct')) {
        $mp = new MProduct();
        // Try common method names
        if (method_exists($mp, 'getProductById')) {
          $p = $mp->getProductById($productId);
        } elseif (method_exists($mp, 'getById')) {
          $p = $mp->getById($productId);
        } else {
          $p = null;
        }
        if (is_array($p)) {
          if (!empty($p['sku'])) $sku = $p['sku'];
          elseif (!empty($p['product_code'])) $sku = $p['product_code'];
          elseif (!empty($p['code'])) $sku = $p['code'];
        }
      }
    } catch (Exception $e) {
      // ignore
    }
  }
  return $sku;
}

if ($roleId == 1 && !empty($data['warehousesSummary']) && is_array($data['warehousesSummary'])) {
  $warehouseOptions = $data['warehousesSummary'];
} else {
  $warehouseOptions = __load_raw_warehouses_options();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard Admin - Kho Linh Kiện</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      /* Refined neutral palette for admin clarity */
      --bg: #f1f5f9; /* page background */
      --bg-grad-start: #667eea;
      --bg-grad-end: #764ba2;
      --bg-secondary: #f8fafc;
      --card: #ffffff;
      --border: rgba(226, 232, 240, 0.9);
      --divider: rgba(148,163,184,0.25);
      --text: #1e293b;
      --text-light: #64748b;
      --muted: #94a3b8;
      --accent: #3b82f6;
      --accent-light: #e0f2fe;
      --success: #10b981;
      --success-light: #d1fae5;
      --warning: #f59e0b;
      --warning-light: #fef3c7;
      --danger: #ef4444;
      --danger-light: #fee2e2;
      --purple: #8b5cf6;
      --purple-light: #ede9fe;
      --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
      --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
      --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
      --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    }

    * { 
      box-sizing: border-box; 
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.55;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      min-height: 100vh;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 10px 24px;
    }

    /* header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 25px;
      padding: 24px 32px;
      background: linear-gradient(90deg, var(--bg-grad-start), var(--bg-grad-end));
      box-shadow: var(--shadow-lg);
      animation: slideDown 0.5s ease;
      color: #fff;
    } */

    h1 {
      margin: 0;
      font-size: 26px;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .muted { 
      color: var(--text-light); 
      font-size: 13px;
      font-weight: 500;
      margin-top: 6px;
    }

    .controls {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .btn {
      background: linear-gradient(135deg, var(--accent), var(--accent-light));
      border: none;
      color: #ffffff;
      padding: 10px 20px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      box-shadow: var(--shadow);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: var(--shadow-xl);
    }
    .btn:active {
      transform: translateY(0) scale(0.98);
    }

    .kpis {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 30px;
      margin-bottom: 32px;
      animation: fadeUp 0.6s ease;
    }
    
    @media (max-width: 1100px) {
      .kpis {
        grid-template-columns: repeat(3, 1fr);
      }
    }
    
    @media (max-width: 768px) {
      .kpis {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    
    @media (max-width: 480px) {
      .kpis {
        grid-template-columns: 1fr;
      }
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      box-shadow: var(--shadow);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
      min-height: 120px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--accent), var(--purple));
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .card:hover { 
      box-shadow: var(--shadow-xl); 
      transform: translateY(-6px);
    }
    .card:hover::before {
      opacity: 1;
    }

    .kpi-title {
      font-size: 13px;
      color: var(--text-light);
      margin-bottom: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .kpi-value {
      font-size: 30px;
      font-weight: 700;
      margin: 6px 0 4px;
      line-height: 1.1;
      color: var(--text);
    }
    
    .kpi-desc {
      font-size: 13px;
      color: var(--muted);
      margin-top: 12px;
      line-height: 1.4;
      font-weight: 500;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 24px;
      margin-bottom: 24px;
    }

    .charts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      min-height: 300px;
    }

    canvas {
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.02);
      width: 100% !important;
      height: auto !important;
      min-height: 220px;
      position: relative;
      z-index: 1;
      display: block;
    }

    /* Ensure chart container stays above other floating elements */
    .grid-2 > .card { z-index: 3; }
    /* Make the doughnut chart a bit larger and centered */
    #chartGroups { max-width: 420px; margin: 6px auto; min-height: 260px; }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      margin-top: 12px;
    }
    th, td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }
    th {
      color: var(--text-light);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 12px;
      letter-spacing: 0.5px;
      background: var(--bg-secondary);
    }
    tbody tr {
      transition: all 0.2s ease;
    }
    tbody tr:hover {
      background: var(--bg-secondary);
    }

    .map {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 6px;
    }

    .bin {
      height: 44px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 500;
      transition: 0.2s;
    }
    .bin.empty { background: rgba(255,255,255,0.03); color: var(--muted); }
    .bin.medium { background: rgba(30,144,255,0.15); color: var(--accent); }
    .bin.full { background: rgba(239,68,68,0.15); color: var(--danger); }
    .bin:hover { transform: scale(1.05); }

    footer {
      margin-top: 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 13px;
      color: var(--text-light);
      padding: 12px 4px;
    }

    @media (max-width: 980px) {
      .grid-2 { grid-template-columns: 1fr; }
      .charts { grid-template-columns: 1fr; }
      h1 { font-size: 18px; }
    }

    @keyframes slideDown {
      from { 
        opacity: 0; 
        transform: translateY(-30px); 
      }
      to { 
        opacity: 1; 
        transform: translateY(0); 
      }
    }

    @keyframes fadeUp {
      from { 
        opacity: 0; 
        transform: translateY(20px); 
      }
      to { 
        opacity: 1; 
        transform: translateY(0); 
      }
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; }
    }
    
    /* Quick Actions for Admin */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
      animation: fadeUp 0.6s ease 0.15s both;
    }
    
    .action-btn {
      background: linear-gradient(135deg, var(--accent), var(--accent-light));
      border: none;
      color: #ffffff;
      padding: 16px 24px;
      border-radius: 14px;
      cursor: pointer;
      font-size: 15px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: var(--shadow-lg);
      position: relative;
      overflow: hidden;
    }
    
    .action-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s ease;
    }
    
    .action-btn:hover::before {
      left: 100%;
    }
    
    .action-btn:hover {
      transform: translateY(-4px) scale(1.02);
      box-shadow: var(--shadow-xl);
    }
    
    .action-btn:active {
      transform: translateY(-2px) scale(0.98);
    }
    
    .action-btn.success {
      background: linear-gradient(135deg, var(--success), var(--success-light));
      box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
    }
    
    .action-btn.success:hover {
      box-shadow: 0 8px 24px rgba(16, 185, 129, 0.6);
    }
    
    .action-btn.warning {
      background: linear-gradient(135deg, var(--warning), var(--warning-light));
      box-shadow: 0 4px 16px rgba(245, 158, 11, 0.4);
    }
    
    .action-btn.warning:hover {
      box-shadow: 0 8px 24px rgba(245, 158, 11, 0.6);
    }
    
    .action-btn.purple {
      background: linear-gradient(135deg, var(--purple), var(--purple-light));
      box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
    }
    
    .action-btn.purple:hover {
      box-shadow: 0 8px 24px rgba(139, 92, 246, 0.6);
    }
    
    .action-btn .badge {
      background: rgba(255, 255, 255, 0.95);
      color: var(--text);
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      box-shadow: var(--shadow-sm);
    }

    /* Progress bars */
    .progress {
      width: 100%;
      height: 8px;
      background: var(--bg-secondary);
      border-radius: 6px;
      overflow: hidden;
      position: relative;
      margin-top: 6px;
      border: 1px solid var(--border);
    }
    .progress-bar {
      height: 100%;
      background: linear-gradient(90deg, var(--accent), var(--purple));
      transition: width 0.5s ease;
    }
    .progress-bar.warn { background: linear-gradient(90deg, var(--warning), #f97316); }
    .progress-bar.danger { background: linear-gradient(90deg, var(--danger), #dc2626); }

    /* KPI color accents */
    .kpi-accent { background: var(--accent-light); }
    .kpi-success { background: var(--success-light); }
    .kpi-warning { background: var(--warning-light); }
    .kpi-danger { background: var(--danger-light); }
    .kpi-accent .kpi-value { color: var(--accent); }
    .kpi-success .kpi-value { color: var(--success); }
    .kpi-warning .kpi-value { color: var(--warning); }
    .kpi-danger .kpi-value { color: var(--danger); }
  </style>
</head>
<body>
  <div class="container">

    <!-- Filters removed per user request -->

    <?php if ($roleId == 1): // Chỉ Admin mới thấy Quick Actions ?>
    <!-- Quick Actions for Admin -->
    <section class="quick-actions">
      <button class="action-btn success" onclick="window.location='index.php?page=receipts/create'">
        <span>➕</span> Tạo phiếu nhập
      </button>
      <button class="action-btn" onclick="window.location='index.php?page=exports/create'">
        <span>📤</span> Tạo phiếu xuất
      </button>
      <button class="action-btn warning" onclick="window.location='index.php?page=receipts/approve'">
        <span>✅</span> Duyệt phiếu
        <?php if (($data['pendingRequests'] ?? 0) > 0): ?>
          <span class="badge"><?= $data['pendingRequests'] ?></span>
        <?php endif; ?>
      </button>
      <button class="action-btn purple" onclick="window.location='index.php?page=users'">
        <span>👥</span> Quản lý User
      </button>
      <button class="action-btn" onclick="window.location='index.php?page=warehouses'">
        <span>🏭</span> Quản lý Kho
      </button>
      <button class="action-btn success" onclick="window.location='index.php?page=products'">
        <span>📦</span> Quản lý SP
      </button>
    </section>
    <?php endif; ?>

    <?php if ($roleId == 1 || $roleId == 2): // Admin hoặc Quản lý kho thấy tổng quan theo kho ?>
    <!-- Per-warehouse summary -->
    <section style="margin-bottom:10px;">
      <div>
        <h1> Dashboard <?= $roleId == 1 ? '- Admin' : ($roleId == 2 ? '- Quản lý kho' : '- Nhân viên') ?></h1>
        <div class="muted">Tổng quan nhanh về tồn kho, nhập/xuất và cảnh báo</div>
      </div>  
    <!-- <h3 style="margin-bottom:16px;font-size:20px;font-weight:700;color:rgba(255,255,255,0.95);letter-spacing:-0.3px;">Tổng quan theo kho</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
        <?php if (isset($data['warehousesSummary']) && !empty($data['warehousesSummary'])): ?>
          <?php foreach ($data['warehousesSummary'] as $ws): ?>
            <div class="card" style="animation:fadeUp 0.6s ease;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div>
                  <div style="font-size:12px;color:var(--text-light);text-transform:uppercase;letter-spacing:0.5px;font-weight:600;">Kho</div>
                  <div style="font-weight:700;font-size:18px;margin-top:8px;color:var(--text);"><?= htmlspecialchars($ws['name']) ?></div>
                  <?php $u = floatval($ws['utilization'] ?? 0); $uClass = $u > 90 ? 'danger' : ($u > 80 ? 'warn' : ''); ?>
                  <div style="margin-top:10px;width:160px;">
                    <div style="font-size:11px;color:var(--text-light);font-weight:600;">Sử dụng: <?= number_format($u,1) ?>%</div>
                    <div class="progress" style="height:6px;margin-top:4px;">
                      <div class="progress-bar <?= $uClass ?>" style="width: <?= min(max($u,0),100) ?>%"></div>
                    </div>
                  </div>
                </div>
                <div style="text-align:right;background:linear-gradient(135deg,var(--accent),var(--purple));color:#fff;padding:12px 16px;border-radius:12px;box-shadow:var(--shadow);min-width:120px;">
                  <div style="font-size:11px;opacity:0.85;font-weight:600;">Tổng giá trị</div>
                  <div style="font-weight:700;font-size:16px;margin-top:4px;white-space:nowrap;"><?= number_format($ws['total_value'],0,',','.') ?> ₫</div>
                </div>
              </div>
              <hr style="border:none;border-top:1px solid var(--divider);margin:12px 0;">
              <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                <div style="display:flex;flex-direction:column;gap:4px;">
                  <span style="font-size:11px;color:var(--text-light);font-weight:600;">Tổng SKU</span>
                  <span style="font-size:16px;font-weight:600;color:var(--text);"><?= number_format($ws['total_sku']) ?></span>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;">
                  <span style="font-size:11px;color:var(--text-light);font-weight:600;">Tổng SL</span>
                  <span style="font-size:16px;font-weight:600;color:var(--text);"><?= number_format($ws['total_qty']) ?></span>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;">
                  <span style="font-size:11px;color:var(--text-light);font-weight:600;">Sắp hết</span>
                  <span style="font-size:16px;font-weight:600;color:var(--warning);"><?= number_format($ws['low_stock_count']) ?></span>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;">
                  <span style="font-size:11px;color:var(--text-light);font-weight:600;">Giá trị/ SKU</span>
                  <span style="font-size:16px;font-weight:600;color:var(--success);"><?= $ws['total_sku'] > 0 ? number_format($ws['total_value'] / $ws['total_sku'],0,',','.') : '0' ?> ₫</span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="card">Chưa có thông tin kho</div>
        <?php endif; ?> -->
      </div>
    </section>
    <?php endif; ?>

    <!-- KPI Cards -->
    <section class="kpis">
      <div class="card">
        <div class="kpi-title">Tổng SKU</div>
        <div class="kpi-value"><?= number_format($data['totalSKU'] ?? 0) ?></div>
        <div class="kpi-desc">Mã sản phẩm đang quản lý</div>
      </div>
      <div class="card">
        <div class="kpi-title">Tổng số lượng</div>
        <div class="kpi-value"><?= number_format($data['totalQty'] ?? 0) ?></div>
        <div class="kpi-desc">Tổng đơn vị tồn kho</div>
      </div>
      <!-- <div class="card kpi-purple">
        <div class="kpi-title">Tổng giá trị kho</div>
        <div class="kpi-value" style="font-size: 20px; color: var(--purple)"><?= number_format($data['totalValue'] ?? 0, 0, ',', '.') ?> ₫</div>
        <div class="kpi-desc">Ước tính toàn bộ hàng hóa</div>
      </div> -->
      <div class="card">
        <div class="kpi-title">Sắp hết hàng</div>
        <div class="kpi-value" style="color: var(--warning)"><?= number_format($data['lowStockCount'] ?? 0) ?></div>
        <div class="kpi-desc">SKU dưới ngưỡng an toàn</div>
      </div>
      <div class="card">
        <div class="kpi-title">Số kho</div>
        <div class="kpi-value" style="color: var(--success)"><?= number_format($data['totalWarehouses'] ?? 0) ?></div>
        <div class="kpi-desc">Kho hoạt động</div>
      </div>
      <!-- <div class="card kpi-<?= ($data['warehouseUtilization'] ?? 0) > 80 ? 'warning' : 'accent' ?>">
        <div class="kpi-title">Công suất kho</div>
        <div class="kpi-value" style="color: <?= ($data['warehouseUtilization'] ?? 0) > 90 ? 'var(--danger)' : (($data['warehouseUtilization'] ?? 0) > 80 ? 'var(--warning)' : 'var(--accent)') ?>;">
          <?= number_format($data['warehouseUtilization'] ?? 0, 1) ?>%
        </div>
        <div class="progress" aria-label="Tỷ lệ sử dụng">
          <?php $util = floatval($data['warehouseUtilization'] ?? 0); $utilClass = $util > 90 ? 'danger' : ($util > 80 ? 'warn' : ''); ?>
          <div class="progress-bar <?= $utilClass ?>" style="width: <?= min(max($util,0),100) ?>%"></div>
        </div>
        <div class="kpi-desc">Tỷ lệ sử dụng</div>
      </div> -->
      <div class="card">
        <div class="kpi-title">Nhập hôm nay</div>
        <div class="kpi-value" style="color: var(--success)"><?= number_format($data['receiptsToday'] ?? 0) ?></div>
        <div class="kpi-desc">Phiếu nhập</div>
      </div>
      <div class="card">
        <div class="kpi-title">Xuất hôm nay</div>
        <div class="kpi-value" style="color: var(--accent)"><?= number_format($data['exportsToday'] ?? 0) ?></div>
        <div class="kpi-desc">Phiếu xuất</div>
      </div>
      <div class="card">
        <div class="kpi-title">Chờ xử lý</div>
        <div class="kpi-value" style="color: var(--warning)"><?= number_format($data['pendingRequests'] ?? 0) ?></div>
        <div class="kpi-desc">Yêu cầu chờ</div>
      </div>
      <div class="card">
        <div class="kpi-title">URGENT</div>
        <div class="kpi-value" style="color: var(--danger)"><?= number_format($data['urgentRequests'] ?? 0) ?></div>
        <div class="kpi-desc">Cần xử lý ngay!</div>
      </div>
    </section>

    <!-- Main Charts -->
    <section class="grid-2">
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="card" style="padding: 24px;">
          <h3 style="margin-top:0; margin-bottom: 12px;font-size:18px;font-weight:700;color:var(--text);">Thống kê nhập - xuất</h3>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;flex-wrap:wrap;">
            <div class="muted">Tần suất nhập và xuất</div>
            <div style="display:flex;gap:8px;align-items:center;">
              <!-- InOut chart filters -->
              <?php $inout_period = $_GET['inout_period'] ?? ($_GET['period'] ?? '7d'); ?>
              <?php $inout_wh = $_GET['inout_warehouse'] ?? ''; ?>
              <select id="inout_period" onchange="onChartFilterChange('inout')" style="padding:8px 12px;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--text);font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s ease;">
                <option value="7d" <?= $inout_period === '7d' ? 'selected' : '' ?>>7 ngày</option>
                <option value="week" <?= $inout_period === 'week' ? 'selected' : '' ?>>Tuần</option>
                <option value="month" <?= $inout_period === 'month' ? 'selected' : '' ?>>Tháng</option>
                <option value="quarter" <?= $inout_period === 'quarter' ? 'selected' : '' ?>>Quý</option>
                <option value="year" <?= $inout_period === 'year' ? 'selected' : '' ?>>Năm</option>
              </select>
              <select id="inout_warehouse" onchange="onChartFilterChange('inout')" style="padding:8px 12px;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--text);font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s ease;">
                <option value="">Tất cả kho</option>
                <?php foreach ($warehouseOptions as $wsOpt): ?>
                  <option value="<?= htmlspecialchars($wsOpt['warehouse_id']) ?>" <?= $inout_wh == $wsOpt['warehouse_id'] ? 'selected' : '' ?>><?= htmlspecialchars($wsOpt['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div style="width:100%">
            <canvas id="chartInOut"></canvas>
          </div>
        </div>

        <div class="card" style="padding: 24px;">
          <h3 style="margin-top:0; margin-bottom:12px;font-size:18px;font-weight:700;color:var(--text);">Phân loại sản phẩm</h3>
          <div style="width:100%;display:flex;justify-content:flex-end;margin-bottom:8px;">
            <?php $cat_wh = $_GET['category_warehouse'] ?? ''; ?>
              <select id="category_warehouse" onchange="onChartFilterChange('category')" style="padding:8px 12px;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--text);font-size:13px;font-weight:500;cursor:pointer;transition:all 0.2s ease;">
                <option value="">Tất cả kho</option>
                <?php foreach ($warehouseOptions as $wsOpt): ?>
                  <option value="<?= htmlspecialchars($wsOpt['warehouse_id']) ?>" <?= $cat_wh == $wsOpt['warehouse_id'] ? 'selected' : '' ?>><?= htmlspecialchars($wsOpt['name']) ?></option>
                <?php endforeach; ?>
              </select>
          </div>
          <div style="width:100%;display:flex;justify-content:center;">
            <canvas id="chartGroups"></canvas>
          </div>
        </div>
      </div>
      <div class="card" style="padding: 24px; justify-content: flex-start; align-items: flex-start;">
        <h3 style="margin-top:0; margin-bottom:16px;font-size:18px;font-weight:700;color:var(--text);">Cảnh báo & Hoạt động gần đây</h3>
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
          <div style="flex:1;min-width:220px;">
            <div class="muted" style="margin-bottom:6px;">Cảnh báo</div>
            <ul id="alerts" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;">
              <?php if (isset($data['alerts']) && !empty($data['alerts'])): ?>
                <?php foreach ($data['alerts'] as $alert): ?>
                  <li style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:10px;background:var(--bg-secondary);border-left:3px solid <?= $alert['type'] == 'danger' ? 'var(--danger)' : ($alert['type'] == 'warning' ? 'var(--warning)' : ($alert['type'] == 'info' ? 'var(--accent)' : 'var(--success)')) ?>;box-shadow:var(--shadow-sm);transition:all 0.2s ease;">
                    <div style="font-size:14px;color:var(--text);flex:1;font-weight:500;"><?= htmlspecialchars($alert['message']) ?></div>
                    <?php if ($roleId == 1): // Admin quick actions ?>
                      <?php if ($alert['type'] == 'danger' && strpos($alert['message'], 'URGENT') !== false): ?>
                        <button onclick="window.location='index.php?page=goodsReceiptRequest'" style="font-size:12px;padding:8px 14px;border-radius:8px;background:var(--danger);color:#fff;border:none;cursor:pointer;font-weight:600;box-shadow:var(--shadow-sm);transition:all 0.2s ease;">Xử lý</button>
                      <?php elseif ($alert['type'] == 'warning' && strpos($alert['message'], 'sắp hết') !== false): ?>
                        <button onclick="window.location='index.php?page=products'" style="font-size:12px;padding:8px 14px;border-radius:8px;background:var(--warning);color:#fff;border:none;cursor:pointer;font-weight:600;box-shadow:var(--shadow-sm);transition:all 0.2s ease;">Chi tiết</button>
                      <?php endif; ?>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li style="color:var(--muted);padding:8px;">Không có cảnh báo</li>
              <?php endif; ?>
            </ul>
          </div>

          <div style="flex:2;min-width:320px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
              <div class="muted" style="font-weight:600;font-size:14px;">Phiếu gần nhất</div>
            </div>
            <div class="recent-table-wrapper" style="border-radius:8px;border:1px solid var(--border);background:var(--card);padding:6px;width:100%;">
              <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                  <tr>
                    <th style="text-align:left;padding:6px;color:var(--muted);">Mã</th>
                    <th style="text-align:left;padding:6px;color:var(--muted);">Loại</th>
                    <th style="text-align:left;padding:6px;color:var(--muted);">Ngày</th>
                    <th style="text-align:left;padding:6px;color:var(--muted);">Người tạo</th>
                    <?php if ($roleId == 1): ?><th style="text-align:left;padding:6px;color:var(--muted);">Thao tác</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php if (isset($data['recentTransactions']) && !empty($data['recentTransactions'])): ?>
                    <?php foreach (array_slice($data['recentTransactions'], 0, 8) as $trans): ?>
                      <tr style="border-top:1px dashed var(--border);">
                        <td style="padding:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;"><a href="index.php?page=receipts/detail&id=<?= urlencode($trans['transaction_id']) ?>" style="color: var(--accent); text-decoration: none;"><?= htmlspecialchars($trans['transaction_id']) ?></a></td>
                        <td style="padding:8px;">
                          <?php 
                            $typeText = 'Nhập';
                            $typeColor = 'var(--success)';
                            if ($trans['type'] == 'export') {
                              $typeText = 'Xuất';
                              $typeColor = 'var(--accent)';
                            }
                          ?>
                          <span style="color: <?= $typeColor ?>"><?= $typeText ?></span>
                        </td>
                        <td style="padding:8px;">
                          <?php
                            if (isset($trans['created_at'])) {
                              if ($trans['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                                echo date('d/m H:i', $trans['created_at']->toDateTime()->getTimestamp());
                              } else {
                                echo date('d/m H:i', strtotime($trans['created_at']));
                              }
                            } else {
                              echo '-';
                            }
                          ?>
                        </td>
                        <td style="padding:8px;"><?= htmlspecialchars($trans['created_by']) ?></td>
                        <?php if ($roleId == 1): // Admin quick approve ?>
                          <td style="padding:8px;">
                            <?php if (isset($trans['status']) && $trans['status'] == 1): ?>
                              <button onclick="approveTransaction('<?= $trans['transaction_id'] ?>')" style="font-size:12px;padding:8px 14px;border-radius:8px;background:var(--success);color:#fff;border:none;cursor:pointer;font-weight:600;box-shadow:var(--shadow-sm);transition:all 0.2s ease;">✓ Duyệt</button>
                            <?php else: ?>
                              <span style="color:var(--muted);font-size:12px;">–</span>
                            <?php endif; ?>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="<?= $roleId == 1 ? 5 : 4 ?>" style="text-align:center;color:var(--muted);padding:12px;">Chưa có giao dịch</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Bottom sections -->
    <section style="margin-top:24px;display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div class="card" style="padding: 24px;">
        <h3 style="margin-top:0; margin-bottom: 16px;font-size:18px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;">
          <span style="font-size:24px;">🔥</span>
          <span>Top sản phẩm xuất nhiều</span>
        </h3>
        <table>
          <thead><tr><th>SKU</th><th>Tên sản phẩm</th><th>Số lượng</th></tr></thead>
          <tbody>
            <?php if (isset($data['topProducts']) && !empty($data['topProducts'])): ?>
              <?php foreach ($data['topProducts'] as $productId => $product): ?>
                <?php 
                  $sku = '';
                  if (!empty($product['sku'])) {
                    $sku = $product['sku'];
                  } elseif (!empty($product['product_code'])) {
                    $sku = $product['product_code'];
                  } elseif (!empty($product['code'])) {
                    $sku = $product['code'];
                  } else {
                    $sku = __resolve_sku_by_product_id($productId);
                    if ($sku === '' && is_scalar($productId)) {
                      // Fallback last resort, but avoid showing raw ID if we can resolve
                      $sku = (string)$productId;
                    }
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars($sku) ?></td>
                  <td><?= htmlspecialchars($product['name'] ?? '') ?></td>
                  <td style="color: var(--success); font-weight: 600;">
                    <?= number_format($product['qty'] ?? 0) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" style="text-align:center;color:var(--muted)">Chưa có dữ liệu</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card" style="padding: 24px;">
        <h3 style="margin-top:0; margin-bottom: 16px;font-size:18px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:10px;">
          <span style="font-size:24px;">⚠️</span>
          <span>Sản phẩm sắp hết hàng</span>
        </h3>
        <table>
          <thead><tr><th>SKU</th><th>Tên</th><th>Tồn kho</th><th>Min</th></tr></thead>
          <tbody>
            <?php if (isset($data['lowStockProducts']) && !empty($data['lowStockProducts'])): ?>
              <?php foreach ($data['lowStockProducts'] as $product): ?>
                <tr>
                  <td><?= htmlspecialchars($product['sku']) ?></td>
                  <td><?= htmlspecialchars($product['name']) ?></td>
                  <td style="color: <?= $product['current_stock'] == 0 ? 'var(--danger)' : 'var(--warning)' ?>; font-weight: 600;">
                    <?= number_format($product['current_stock']) ?>
                  </td>
                  <td><?= number_format($product['min_stock']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4" style="text-align:center;color:var(--success)"> Tất cả sản phẩm đều đủ hàng</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <footer>
      <div>Last sync: <?= date('d/m/Y H:i') ?></div>
      <div>Dashboard PHP + MongoDB</div>
    </footer>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Handler for period filter: preserve other filters (from/to/warehouse/category/page)
    function onPeriodChange(selectEl) {
      const sp = new URLSearchParams(window.location.search);
      const page = sp.get('page');
      // clear existing params but keep page
      sp.forEach((v, k) => sp.delete(k));
      if (page) sp.set('page', page);
      const fromEl = document.getElementById('filter_from');
      const toEl = document.getElementById('filter_to');
      const whEl = document.getElementById('filter_warehouse');
      const catEl = document.getElementById('filter_category');
      if (fromEl && fromEl.value) sp.set('from', fromEl.value);
      if (toEl && toEl.value) sp.set('to', toEl.value);
      if (whEl && whEl.value) sp.set('warehouse', whEl.value);
      if (catEl && catEl.value) sp.set('category', catEl.value);
      if (selectEl && selectEl.value) sp.set('period', selectEl.value);
      const target = window.location.pathname + (sp.toString() ? '?' + sp.toString() : '');
      redirectOnce(target);
    }

    // Per-chart filter change handler. chartKey: 'inout' | 'category' | 'stock'
    function onChartFilterChange(chartKey) {
      const sp = new URLSearchParams(window.location.search);
      const page = sp.get('page');
      // Keep other params but update chart-specific ones
      if (chartKey === 'inout') {
        const period = document.getElementById('inout_period').value;
        const wh = document.getElementById('inout_warehouse').value;
        if (period) sp.set('inout_period', period); else sp.delete('inout_period');
        if (wh) sp.set('inout_warehouse', wh); else sp.delete('inout_warehouse');
      } else if (chartKey === 'category') {
        const wh = document.getElementById('category_warehouse').value;
        if (wh) sp.set('category_warehouse', wh); else sp.delete('category_warehouse');
      } else if (chartKey === 'stock') {
        const wh = document.getElementById('stock_warehouse').value;
        if (wh) sp.set('stock_warehouse', wh); else sp.delete('stock_warehouse');
      }
      // keep page param if existed
      sp.delete('page');
      if (page) sp.set('page', page);
      const target = window.location.pathname + (sp.toString() ? '?' + sp.toString() : '');
      redirectOnce(target);
    }

    // Redirect helper: prevent rapid repeated redirects (throttle by 2s)
    function redirectOnce(url) {
      console.log('[dashboard] redirectOnce called', new Date().toISOString(), url);
      try {
        const key = 'dashboard_last_redirect';
        const last = parseInt(sessionStorage.getItem(key) || '0', 10);
        const now = Date.now();
        if (last && now - last < 2000) {
          console.warn('[dashboard] Redirect suppressed to avoid loop:', url, 'last:', new Date(last).toISOString());
          return;
        }
        sessionStorage.setItem(key, String(now));
        console.log('[dashboard] performing redirect to', url);
        window.location.href = url;
      } catch (e) {
        // Fallback to direct redirect if sessionStorage unavailable
        console.error('[dashboard] redirectOnce error, fallback to direct redirect', e);
        window.location.href = url;
      }
    }
    // Initialize filter inputs and wire apply/clear buttons
    (function(){
      const params = new URLSearchParams(window.location.search);
      const from = params.get('from') || '';
      const to = params.get('to') || '';
      const wh = params.get('warehouse') || '';
      const cat = params.get('category') || '';
      const elFrom = document.getElementById('filter_from');
      const elTo = document.getElementById('filter_to');
      const elWh = document.getElementById('filter_warehouse');
      const elCat = document.getElementById('filter_category');
      if (elFrom) elFrom.value = from;
      if (elTo) elTo.value = to;
      if (elWh) elWh.value = wh;
      if (elCat) elCat.value = cat;

      const applyBtn = document.getElementById('applyFilters');
      const clearBtn = document.getElementById('clearFilters');
      function buildAndRedirect() {
        const sp = new URLSearchParams(window.location.search);
        // preserve page param
        const page = sp.get('page');
        sp.forEach((v,k)=>sp.delete(k));
        if (page) sp.set('page', page);
        if (elFrom && elFrom.value) sp.set('from', elFrom.value);
        if (elTo && elTo.value) sp.set('to', elTo.value);
        if (elWh && elWh.value) sp.set('warehouse', elWh.value);
        if (elCat && elCat.value) sp.set('category', elCat.value);
        const target = window.location.pathname + (sp.toString() ? '?' + sp.toString() : '');
        redirectOnce(target);
      }
      if (applyBtn) applyBtn.addEventListener('click', buildAndRedirect);
      if (clearBtn) clearBtn.addEventListener('click', function(){
        const sp = new URLSearchParams();
        const page = (new URLSearchParams(window.location.search)).get('page');
        if (page) sp.set('page', page);
        redirectOnce(window.location.pathname + (sp.toString() ? '?' + sp.toString() : ''));
      });
    })();

    // Small runtime logs to help debug continuous reload/scroll issues
    window.addEventListener('load', function(){
      try { console.log('[dashboard] page loaded', new Date().toISOString(), 'href=', window.location.href); } catch(e){}
    });
    document.addEventListener('visibilitychange', function(){
      try { console.log('[dashboard] visibilitychange', document.visibilityState, new Date().toISOString()); } catch(e){}
    });
    
  </script>
  <script>
    // Dữ liệu từ PHP
    const receiptExportData = <?= json_encode($data['receiptExportChart'] ?? ['labels' => [], 'receipts' => [], 'exports' => []]) ?>;
    const categoryData = <?= json_encode($data['categoryDistribution'] ?? ['labels' => [], 'values' => []]) ?>;
    const stockStatusData = <?= json_encode($data['stockStatusChart'] ?? ['labels' => [], 'values' => []]) ?>;

    // Biểu đồ Nhập - Xuất (7 ngày) - bar chart
    const canvasInOut = document.getElementById('chartInOut');
    // Explicitly fix canvas size to avoid Chart.js responsive resize loops
    try { canvasInOut.style.height = '320px'; canvasInOut.style.width = '100%'; canvasInOut.height = 320; } catch(e){}
    const ctx1 = canvasInOut.getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: receiptExportData.labels,
        datasets: [
          {
            label: 'Phiếu nhập',
            data: receiptExportData.receipts,
            backgroundColor: 'rgba(34, 197, 94, 0.7)',
            borderColor: 'rgba(34, 197, 94, 1)',
            borderWidth: 1
          },
          {
            label: 'Phiếu xuất',
            data: receiptExportData.exports,
            backgroundColor: 'rgba(30, 144, 255, 0.7)',
            borderColor: 'rgba(30, 144, 255, 1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        // Disable Chart.js automatic responsiveness to prevent repeated layout/resizing loops
        responsive: false,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { 
              color: '#0f172a', 
              font: { size: 11 },
              padding: 10
            }
          },
          tooltip: {
            backgroundColor: '#ffffff',
            borderColor: 'rgba(15,23,42,0.08)',
            borderWidth: 1,
            titleColor: '#0f172a',
            bodyColor: '#0f172a',
            titleFont: { weight: '600' },
            bodyFont: { weight: '400' },
            padding: 8
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { 
              color: '#6b7280', 
              font: { size: 10 },
              stepSize: 1
            },
            grid: { color: 'rgba(15,23,42,0.04)' }
          },
          x: {
            ticks: { color: '#6b7280', font: { size: 10 } },
            grid: { display: false }
          }
        }
      }
    });

    // Biểu đồ phân bố danh mục
    const canvasGroups = document.getElementById('chartGroups');
    try { canvasGroups.style.height = '320px'; canvasGroups.style.width = '100%'; canvasGroups.height = 320; } catch(e){}
    const ctx2 = canvasGroups.getContext('2d');
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: categoryData.labels,
        datasets: [{
          data: categoryData.values,
          backgroundColor: [
            'rgba(30, 144, 255, 0.8)',
            'rgba(34, 197, 94, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(168, 85, 247, 0.8)'
          ],
          borderWidth: 2,
          borderColor: '#0e1422'
        }]
      },
      options: {
        responsive: false,
        maintainAspectRatio: false,
        // let the donut resize inside its card
        plugins: {
          legend: {
            position: 'right',
            labels: { 
              color: '#0f172a', 
              font: { size: 10 },
              padding: 8,
              boxWidth: 12
            }
          },
          tooltip: {
            backgroundColor: '#ffffff',
            borderColor: 'rgba(15,23,42,0.08)',
            borderWidth: 1,
            titleColor: '#0f172a',
            bodyColor: '#0f172a',
            titleFont: { weight: '600' },
            bodyFont: { weight: '400' },
            padding: 8
          }
        }
      }
    });

    

    // Biểu đồ tình trạng tồn kho (tùy chọn - có thể thêm vào phần charts)
    // Nếu muốn hiển thị thêm biểu đồ này, có thể thêm canvas mới
    
    // Quick Approve function for Admin
    function approveTransaction(transactionId) {
      if (!confirm('Bạn có chắc muốn duyệt phiếu ' + transactionId + ' không?')) {
        return;
      }
      
      // TODO: Implement AJAX call to approve
      fetch('index.php?page=receipts/approve/process', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'transaction_id=' + encodeURIComponent(transactionId) + '&action=approve'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('✅ Đã duyệt phiếu thành công!');
          location.reload();
        } else {
          alert('❌ Lỗi: ' + (data.message || 'Không thể duyệt phiếu'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('❌ Có lỗi xảy ra khi duyệt phiếu');
      });
    }
  </script>
</body>
</html>
