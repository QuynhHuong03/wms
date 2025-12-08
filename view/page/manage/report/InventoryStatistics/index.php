<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../../model/mProduct.php');
include_once(__DIR__ . '/../../../../../model/mWarehouse.php');
include_once(__DIR__ . '/../../../../../model/connect.php');

$mProduct = new MProduct();
$mWarehouse = new MWarehouse();

$totalSKU = $mProduct->getTotalSKU();
$totalQty = $mProduct->getTotalQuantity();

// build stock by warehouse via inventory collection
$conn = (new clsKetNoi())->moKetNoi();
$col = $conn->selectCollection('inventory');
$pipeline = [['$group' => ['_id' => '$warehouse_id','total' => ['$sum' => '$qty']]]];
$res = $col->aggregate($pipeline)->toArray();
$byWarehouse = [];
foreach ($res as $r) {
    $wid = $r['_id'] ?? '';
    $byWarehouse[$wid] = intval($r['total'] ?? 0);
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inventory Statistics</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      padding: 30px 20px;
    }
    
    .container {
      max-width: 1400px;
      margin: 0 auto;
    }
    
    h2 {
      font-size: 32px;
      font-weight: 700;
      color: black;
      margin-bottom: 30px;
      text-shadow: 0 2px 4px rgba(0,0,0,0.1);
      letter-spacing: -0.5px;
    }
    
    h3 {
      font-size: 20px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .card {
      background: #ffffff;
      border-radius: 16px;
      padding: 30px;
      margin-bottom: 24px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 15px 40px rgba(0,0,0,0.2);
    }
    
    .stats-grid {
      display: flex;
      gap: 30px;
      align-items: center;
      padding: 20px;
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      border-radius: 12px;
      border-left: 4px solid #0ea5e9;
    }
    
    .stat-item {
      flex: 1;
    }
    
    .stat-label {
      font-size: 14px;
      color: #64748b;
      font-weight: 500;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: #0369a1;
    }
    
    .stat-value.success {
      color: #059669;
    }
    
    .chart-container {
      margin-top: 16px;
      padding: 20px;
      background: #f8fafc;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }
    
    .filter-form {
      display: flex;
      gap: 15px;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 2px solid #f1f5f9;
    }
    
    .filter-form label {
      font-size: 14px;
      font-weight: 600;
      color: #475569;
    }
    
    .filter-form select {
      height: 42px;
      padding: 0 14px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      background: #ffffff;
      font-size: 14px;
      color: #1e293b;
      transition: all 0.3s ease;
      min-width: 200px;
    }
    
    .filter-form select:hover {
      border-color: #cbd5e1;
    }
    
    .filter-form select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
    }
    
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      font-size: 14px;
    }
    
    table thead th {
      background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
      color: #1e293b;
      font-weight: 600;
      padding: 16px;
      text-align: left;
      border-bottom: 2px solid #e2e8f0;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    table thead th:first-child {
      border-top-left-radius: 8px;
    }
    
    table thead th:last-child {
      border-top-right-radius: 8px;
    }
    
    table tbody td {
      padding: 16px;
      border-bottom: 1px solid #f1f5f9;
      color: #334155;
    }
    
    table tbody tr {
      transition: all 0.2s ease;
    }
    
    table tbody tr:hover {
      background: #f8fafc;
      transform: scale(1.01);
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    table tbody tr:last-child td:first-child {
      border-bottom-left-radius: 8px;
    }
    
    table tbody tr:last-child td:last-child {
      border-bottom-right-radius: 8px;
    }
    
    .table-wrapper {
      overflow-x: auto;
      margin-top: 16px;
      border-radius: 8px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Tồn kho (Inventory)</h2>
    <div class="card">
      <div class="stats-grid">
        <div class="stat-item">
          <div class="stat-label">Tổng SKU</div>
          <div class="stat-value"><?= number_format($totalSKU) ?></div>
        </div>
        <div class="stat-item">
          <div class="stat-label">Tổng số lượng</div>
          <div class="stat-value success"><?= number_format($totalQty) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Tồn theo kho</h3>
      <div class="chart-container">
        <canvas id="byWarehouseChart"></canvas>
      </div>
    </div>

    <div class="card">
      <h3>Sản phẩm sắp hết (theo kho)</h3>
      <form method="get" class="filter-form">
        <label>Kho</label>
        <select name="warehouse">
          <option value="">Chọn kho</option>
          <?php foreach ($mWarehouse->getAllWarehouses() as $w):
            $wid = $w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? ''));
          ?>
            <option value="<?= htmlspecialchars($wid) ?>"><?= htmlspecialchars($w['warehouse_name'] ?? $w['name'] ?? $wid) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php
        // show global low stock across warehouses (no warehouse selected)
        $lowProducts = [];
        foreach ($mProduct->getAllProducts() as $p) {
          $min = intval($p['min_stock'] ?? 0);
          $cur = intval($p['current_stock'] ?? 0);
          if ($min > 0 && $cur < $min) {
            $lowProducts[] = ['sku'=>$p['sku'] ?? '', 'name'=>$p['product_name'] ?? $p['name'] ?? '', 'current'=>$cur, 'min'=>$min];
          }
        }
      ?>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>SKU</th><th>Tên</th><th>Tồn</th><th>Min</th></tr></thead>
          <tbody>
            <?php if (!empty($lowProducts)): foreach ($lowProducts as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= number_format($p['current']) ?></td>
                <td><?= number_format($p['min']) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="4" style="text-align:center;color:#64748b">Không có sản phẩm thiếu</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    const labels = <?= json_encode(array_keys($byWarehouse)) ?>;
    const data = <?= json_encode(array_values($byWarehouse)) ?>;
    const ctx = document.getElementById('byWarehouseChart').getContext('2d');
    new Chart(ctx, {
      type:'bar',
      data:{
        labels:labels,
        datasets:[{
          label:'Tồn theo kho',
          data:data,
          backgroundColor:'rgba(102,126,234,0.7)',
          borderColor:'rgba(102,126,234,1)',
          borderWidth:2,
          borderRadius:6,
          hoverBackgroundColor:'rgba(102,126,234,0.9)'
        }]
      },
      options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
          legend:{
            display:true,
            labels:{color:'#475569',font:{size:13,weight:'500'}}
          },
          tooltip:{
            backgroundColor:'#0f172a',
            titleColor:'#fff',
            bodyColor:'#cbd5e1',
            padding:12,
            borderColor:'#334155',
            borderWidth:1
          }
        },
        scales:{
          x:{
            grid:{display:false},
            ticks:{color:'#64748b',font:{size:12}}
          },
          y:{
            grid:{color:'rgba(226,232,240,0.8)',drawBorder:false},
            ticks:{color:'#64748b',font:{size:12},stepSize:1}
          }
        }
      }
    });
  </script>
</body>
</html>
