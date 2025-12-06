<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../../../controller/cDashboard.php');
include_once(__DIR__ . '/../../../../model/mProduct.php');

// Lấy thông tin user từ session
$user = $_SESSION['login'] ?? null;
$roleId = isset($user['role_id']) ? intval($user['role_id']) : null;
$warehouseId = isset($user['warehouse_id']) ? $user['warehouse_id'] : null;

$cDashboard = new CDashboard();
$data = $cDashboard->getDashboardData($roleId, $warehouseId);

// Helper function to resolve SKU by product ID
function __resolve_sku_by_product_id($productId) {
    try {
        $mProduct = new MProduct();
        $product = $mProduct->getProductById($productId);
        if ($product) {
            return $product['sku'] ?? $product['product_code'] ?? $product['code'] ?? '';
        }
    } catch (Exception $e) {
        error_log("Error resolving SKU for product ID {$productId}: " . $e->getMessage());
    }
    return '';
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
      --bg: #0e1422;
      --card: rgba(255, 255, 255, 0.04);
      --border: rgba(255, 255, 255, 0.08);
      --text: #e6eef8;
      --muted: #94a3b8;
      --accent: #1e90ff;
      --success: #22c55e;
      --warning: #f59e0b;
      --danger: #ef4444;
      --glass: rgba(255,255,255,0.08);
    }

    * { 
      box-sizing: border-box; 
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', system-ui, Segoe UI, Roboto, Arial;
      /* background: linear-gradient(180deg, #08111f 0%, #0a1427 100%); */
      color: var(--text);
      margin: 0;
      line-height: 1.0;
      overflow-x: hidden;
    }

    .container {
      max-width: 1250px;
      /* margin: 30px auto; */
      /* padding: 0 16px; */
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
    
    @media (max-width: 1400px) {
      .kpis {
        grid-template-columns: repeat(4, 1fr);
      }
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
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
      backdrop-filter: blur(10px);
      transition: all 0.25s ease;
      min-height: 90px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .card:hover { transform: translateY(-2px); }

    .kpi-title {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 500;
    }

    .kpi-value {
      font-size: 24px;
      font-weight: 700;
      margin: 6px 0;
      line-height: 1.1;
    }
    
    .kpi-desc {
      font-size: 11px;
      color: var(--muted);
      margin-top: 6px;
      line-height: 1.3;
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
      gap: 14px;
      min-height: 250px;
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
    <header>
      <div>
        <h1>📊 Dashboard <?= $roleId == 1 ? '- Admin' : ($roleId == 2 ? '- Quản lý kho' : '- Nhân viên') ?></h1>
        <div class="muted">Tổng quan nhanh về tồn kho, nhập/xuất và cảnh báo</div>
      </div>
    </header>

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
      <div class="card" style="padding: 20px;">
        <h3 style="margin-top:0; margin-bottom: 16px;">Thống kê nhập - xuất</h3>
        <div class="charts">
          <canvas id="chartInOut"></canvas>
          <canvas id="chartGroups"></canvas>
        </div>
      </div>
      <div class="card" style="padding: 20px;">
        <h3 style="margin-top:0">Cảnh báo & Hoạt động gần đây</h3>
        <ul id="alerts" style="padding-left:18px;margin:6px 0;">
          <?php if (isset($data['alerts']) && !empty($data['alerts'])): ?>
            <?php foreach ($data['alerts'] as $alert): ?>
              <li style="color: <?= $alert['type'] == 'danger' ? 'var(--danger)' : ($alert['type'] == 'warning' ? 'var(--warning)' : ($alert['type'] == 'info' ? 'var(--accent)' : 'var(--success)')) ?>; margin-bottom: 10px; cursor: pointer;">
                <?= $alert['icon'] ?> <?= htmlspecialchars($alert['message']) ?>
                <?php if ($roleId == 1): // Admin có nút xử lý nhanh ?>
                  <?php if ($alert['type'] == 'danger' && strpos($alert['message'], 'URGENT') !== false): ?>
                    <button onclick="window.location='index.php?page=goodsReceiptRequest'" style="margin-left: 10px; font-size: 10px; padding: 3px 8px; background: var(--danger); color: white; border: none; border-radius: 4px; cursor: pointer;">Xử lý ngay</button>
                  <?php elseif ($alert['type'] == 'warning' && strpos($alert['message'], 'sắp hết') !== false): ?>
                    <button onclick="window.location='index.php?page=products'" style="margin-left: 10px; font-size: 10px; padding: 3px 8px; background: var(--warning); color: white; border: none; border-radius: 4px; cursor: pointer;">Xem chi tiết</button>
                  <?php endif; ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li>Không có cảnh báo</li>
          <?php endif; ?>
        </ul>
        <hr style="border:none;border-top:1px dashed var(--border)">
        <div class="muted" style="margin-bottom: 8px;">Phiếu gần nhất</div>
        <table>
          <thead><tr><th>Mã</th><th>Loại</th><th>Ngày</th><th>Người tạo</th><?php if ($roleId == 1): ?><th>Thao tác</th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (isset($data['recentTransactions']) && !empty($data['recentTransactions'])): ?>
              <?php foreach (array_slice($data['recentTransactions'], 0, 5) as $trans): ?>
                <tr>
                  <td><a href="index.php?page=receipts/detail&id=<?= urlencode($trans['transaction_id']) ?>" style="color: var(--accent); text-decoration: none;"><?= htmlspecialchars($trans['transaction_id']) ?></a></td>
                  <td>
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
                  <td>
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
                  <td><?= htmlspecialchars($trans['created_by']) ?></td>
                  <?php if ($roleId == 1): // Admin có thể quick approve ?>
                    <td>
                      <?php if (isset($trans['status']) && $trans['status'] == 1): ?>
                        <button onclick="approveTransaction('<?= $trans['transaction_id'] ?>')" style="font-size: 11px; padding: 4px 8px; background: var(--success); color: white; border: none; border-radius: 4px; cursor: pointer;">✓ Duyệt</button>
                      <?php else: ?>
                        <span style="color: var(--muted); font-size: 11px;">-</span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="<?= $roleId == 1 ? 5 : 4 ?>" style="text-align:center;color:var(--muted)">Chưa có giao dịch</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
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
