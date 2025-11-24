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

    * { box-sizing: border-box; }
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

    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      animation: fadeIn 0.6s ease;
    }

    h1 {
      margin: 0;
      font-size: 22px;
      font-weight: 700;
      color: #fff;
    }

    .muted { color: var(--muted); font-size: 13px; }

    .controls {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .btn {
      background: var(--glass);
      border: 1px solid var(--border);
      color: #fff;
      padding: 8px 14px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
      backdrop-filter: blur(8px);
      transition: all 0.25s ease;
    }
    .btn:hover {
      background: var(--accent);
      border-color: var(--accent);
      transform: translateY(-2px);
      box-shadow: 0 0 12px rgba(30,144,255,0.4);
    }

    .kpis {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 14px;
      margin-bottom: 24px;
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
      gap: 16px;
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
      max-height: 250px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-top: 8px;
    }
    th, td {
      padding: 8px;
      border-bottom: 1px dashed var(--border);
    }
    th {
      color: var(--muted);
      text-align: left;
      font-weight: 600;
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
      margin-top: 20px;
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: var(--muted);
      border-top: 1px solid var(--border);
      padding-top: 12px;
    }

    @media (max-width: 980px) {
      .grid-2 { grid-template-columns: 1fr; }
      .charts { grid-template-columns: 1fr; }
      h1 { font-size: 18px; }
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Quick Actions for Admin */
    .quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
      animation: fadeUp 0.6s ease 0.2s both;
    }
    
    .action-btn {
      background: linear-gradient(135deg, var(--accent), #1565c0);
      border: none;
      color: #fff;
      padding: 14px 18px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(30, 144, 255, 0.3);
    }
    
    .action-btn:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(30, 144, 255, 0.5);
    }
    
    .action-btn.success {
      background: linear-gradient(135deg, var(--success), #16a34a);
      box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
    }
    
    .action-btn.success:hover {
      box-shadow: 0 6px 20px rgba(34, 197, 94, 0.5);
    }
    
    .action-btn.warning {
      background: linear-gradient(135deg, var(--warning), #d97706);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
    
    .action-btn.warning:hover {
      box-shadow: 0 6px 20px rgba(245, 158, 11, 0.5);
    }
    
    .action-btn.purple {
      background: linear-gradient(135deg, #a855f7, #7c3aed);
      box-shadow: 0 4px 12px rgba(168, 85, 247, 0.3);
    }
    
    .action-btn.purple:hover {
      box-shadow: 0 6px 20px rgba(168, 85, 247, 0.5);
    }
    
    .action-btn .badge {
      background: rgba(255, 255, 255, 0.3);
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 700;
    }
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
        <div class="kpi-desc">Mã sản phẩm</div>
      </div>
      <div class="card">
        <div class="kpi-title">Tổng số lượng</div>
        <div class="kpi-value"><?= number_format($data['totalQty'] ?? 0) ?></div>
        <div class="kpi-desc">Đơn vị tồn kho</div>
      </div>
      <div class="card">
        <div class="kpi-title">Tổng giá trị kho</div>
        <div class="kpi-value" style="font-size: 16px;"><?= number_format($data['totalValue'] ?? 0, 0, ',', '.') ?> ₫</div>
        <div class="kpi-desc">Ước tính toàn kho</div>
      </div>
      <div class="card">
        <div class="kpi-title">Sắp hết hàng</div>
        <div class="kpi-value" style="color: var(--warning)"><?= number_format($data['lowStockCount'] ?? 0) ?></div>
        <div class="kpi-desc">SKU < min_stock</div>
      </div>
      <div class="card">
        <div class="kpi-title">Số kho</div>
        <div class="kpi-value" style="color: var(--success)"><?= number_format($data['totalWarehouses'] ?? 0) ?></div>
        <div class="kpi-desc">Đang hoạt động</div>
      </div>
      <div class="card">
        <div class="kpi-title">Công suất kho</div>
        <div class="kpi-value" style="color: <?= ($data['warehouseUtilization'] ?? 0) > 80 ? 'var(--warning)' : 'var(--accent)' ?>"><?= number_format($data['warehouseUtilization'] ?? 0, 1) ?>%</div>
        <div class="kpi-desc">Tỷ lệ sử dụng</div>
      </div>
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
    <section style="margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="card" style="padding: 20px;">
        <h3 style="margin-top:0; margin-bottom: 12px;">🔥 Top sản phẩm xuất nhiều</h3>
        <table>
          <thead><tr><th>SKU</th><th>Tên sản phẩm</th><th>Số lượng</th></tr></thead>
          <tbody>
            <?php if (isset($data['topProducts']) && !empty($data['topProducts'])): ?>
              <?php foreach ($data['topProducts'] as $productId => $product): ?>
                <tr>
                  <td><?= htmlspecialchars($product['sku']) ?></td>
                  <td><?= htmlspecialchars($product['name']) ?></td>
                  <td style="color: var(--success); font-weight: 600;"><?= number_format($product['qty']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" style="text-align:center;color:var(--muted)">Chưa có dữ liệu</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card" style="padding: 20px;">
        <h3 style="margin-top:0; margin-bottom: 12px;">⚠️ Sản phẩm sắp hết hàng</h3>
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
              <tr><td colspan="4" style="text-align:center;color:var(--success)">✅ Tất cả sản phẩm đều đủ hàng</td></tr>
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
    // Dữ liệu từ PHP
    const receiptExportData = <?= json_encode($data['receiptExportChart'] ?? ['labels' => [], 'receipts' => [], 'exports' => []]) ?>;
    const categoryData = <?= json_encode($data['categoryDistribution'] ?? ['labels' => [], 'values' => []]) ?>;
    const stockStatusData = <?= json_encode($data['stockStatusChart'] ?? ['labels' => [], 'values' => []]) ?>;

    // Biểu đồ Nhập - Xuất (7 ngày)
    const ctx1 = document.getElementById('chartInOut').getContext('2d');
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
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { 
              color: '#e6eef8', 
              font: { size: 11 },
              padding: 10
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { 
              color: '#94a3b8', 
              font: { size: 10 },
              stepSize: 1
            },
            grid: { color: 'rgba(255,255,255,0.05)' }
          },
          x: {
            ticks: { color: '#94a3b8', font: { size: 10 } },
            grid: { display: false }
          }
        }
      }
    });

    // Biểu đồ phân bố danh mục
    const ctx2 = document.getElementById('chartGroups').getContext('2d');
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
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 1.5,
        plugins: {
          legend: {
            position: 'right',
            labels: { 
              color: '#e6eef8', 
              font: { size: 10 },
              padding: 8,
              boxWidth: 12
            }
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
