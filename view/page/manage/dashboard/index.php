<?php
include_once(__DIR__ . '/../../../../controller/cDashboard.php');
$cDashboard = new CDashboard();
$data = $cDashboard->getDashboardData(); // chứa totalSKU, totalQty, totalValue, lowStock
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
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
      animation: fadeUp 0.6s ease;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.4);
      backdrop-filter: blur(10px);
      transition: all 0.25s ease;
    }
    .card:hover { transform: translateY(-3px); }

    .kpi-title {
      font-size: 14px;
      color: var(--muted);
    }

    .kpi-value {
      font-size: 22px;
      font-weight: 700;
      margin: 6px 0 4px;
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
    }

    canvas {
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.02);
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
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <div>
        <h1>📊 Dashboard - Kho Linh Kiện & Laptop</h1>
        <div class="muted">Tổng quan nhanh về tồn kho, nhập/xuất và cảnh báo</div>
      </div>
      <div class="controls">
        <button class="btn" onclick="window.location='index.php?mod=product'">Quản lý kho</button>
      </div>
    </header>

    <!-- KPI Cards -->
    <section class="kpis">
      <div class="card">
        <div class="kpi-title">Tổng SKU</div>
        <div class="kpi-value"><?= $data['totalSKU'] ?></div>
        <div class="muted">Số mã sản phẩm khác nhau</div>
      </div>
      <div class="card">
        <div class="kpi-title">Tổng số lượng</div>
        <div class="kpi-value"><?= $data['totalQty'] ?></div>
        <div class="muted">Số lượng đơn vị (cái / thùng)</div>
      </div>
      <div class="card">
        <div class="kpi-title">Tổng giá trị kho</div>
        <div class="kpi-value"><?= number_format($data['totalValue'] ?? 0, 0, ',', '.') ?> ₫</div>
        <div class="muted">Tổng giá trị ước tính toàn kho</div>
      </div>
      <div class="card">
        <div class="kpi-title">Sản phẩm sắp hết hàng</div>
        <div class="kpi-value"><?= $data['lowStock'] ?? 0 ?></div>
        <div class="muted">Số SKU dưới mức tối thiểu</div>
      </div>
    </section>

    <!-- Main Charts -->
    <section class="grid-2">
      <div class="card">
        <h3 style="margin-top:0">Thống kê nhập - xuất</h3>
        <div class="charts">
          <canvas id="chartInOut" height="200"></canvas>
          <canvas id="chartGroups" height="200"></canvas>
        </div>
      </div>
      <div class="card">
        <h3 style="margin-top:0">Cảnh báo & Hoạt động gần đây</h3>
        <ul id="alerts" style="padding-left:18px;margin:6px 0;"></ul>
        <hr style="border:none;border-top:1px dashed var(--border)">
        <div class="muted">Phiếu gần nhất</div>
        <table>
          <thead><tr><th>Mã</th><th>Loại</th><th>Ngày</th><th>Nhân viên</th></tr></thead>
          <tbody id="recentTable"></tbody>
        </table>
      </div>
    </section>

    <!-- Warehouse map -->
    <section style="margin-top:18px;display:grid;grid-template-columns:1fr 400px;gap:16px">
      <div class="card">
        <h3 style="margin-top:0">Sơ đồ kho (Zone A)</h3>
        <div class="map" id="warehouseMap"></div>
      </div>
      <div class="card">
        <h3 style="margin-top:0">Top sản phẩm xuất nhiều</h3>
        <table>
          <thead><tr><th>SKU</th><th>Tên</th><th>Số lượng</th></tr></thead>
          <tbody id="topProducts"></tbody>
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
    // Giữ nguyên phần biểu đồ, bạn sẽ thêm fetch dữ liệu sau.
    const ctx1 = document.getElementById('chartInOut').getContext('2d');
    const ctx2 = document.getElementById('chartGroups').getContext('2d');
    new Chart(ctx1, { type:'bar', data:{labels:[],datasets:[]}, options:{plugins:{legend:{position:'bottom'}}} });
    new Chart(ctx2, { type:'doughnut', data:{labels:[],datasets:[{data:[]}]}, options:{plugins:{legend:{position:'bottom'}}} });
  </script>
</body>
</html>
