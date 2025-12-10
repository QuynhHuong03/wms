<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' =&gt; '/', 'secure' =&gt; false, 'httponly' =&gt; true, 'samesite' =&gt; 'Lax']); session_start(); }
// Role-based warehouse visibility
$user = $_SESSION['login'] ?? null;
$roleId = isset($user['role_id']) ? intval($user['role_id']) : null;
$userWarehouse = $user['warehouse_id'] ?? ($user['warehouse'] ?? '');
$isGlobalViewer = in_array($roleId, [1,2]);

include_once(__DIR__ . '/../../../../../model/mProduct.php');
include_once(__DIR__ . '/../../../../../model/mWarehouse.php');
include_once(__DIR__ . '/../../../../../model/connect.php');

$mProduct = new MProduct();
$mWarehouse = new MWarehouse();

$warehouse = $_GET['warehouse'] ?? '';
$product = $_GET['product'] ?? '';
if (!$isGlobalViewer) {
  $warehouse = $userWarehouse;
}

$totalSKU = $mProduct->getTotalSKU();
// Compute totals from inventory collection so the page reflects actual inventory records
$totalQty = 0;

// build stock by warehouse via inventory collection
$conn = (new clsKetNoi())->moKetNoi();
$col = $conn->selectCollection('inventory');

$byWarehouse = [];
$match = [];
if (!empty($product)) {
  $match['product_id'] = $product;
}
if (!empty($warehouse)) {
  $match['warehouse_id'] = $warehouse;
}

// Aggregate by warehouse (respecting optional product filter)
try {
  $pipeline = [];
  if (!empty($match)) $pipeline[] = ['$match' => $match];
  $pipeline[] = ['$group' => ['_id' => '$warehouse_id', 'total' => ['$sum' => '$qty']]];
  $res = $col->aggregate($pipeline)->toArray();
  $totalQty = 0;
  foreach ($res as $r) {
    $wid = $r['_id'] ?? '';
    $tot = intval($r['total'] ?? 0);
    $byWarehouse[$wid] = $tot;
    $totalQty += $tot;
  }
} catch (\Exception $e) {
  $byWarehouse = [];
  $totalQty = 0;
}

// Build stock totals per product for the table (respecting optional warehouse filter and optional product filter)
try {
  $prodMatch = [];
  if (!empty($warehouse)) $prodMatch['warehouse_id'] = $warehouse;
  if (!empty($product)) $prodMatch['product_id'] = $product;
  $prodPipeline = [];
  if (!empty($prodMatch)) $prodPipeline[] = ['$match' => $prodMatch];
  $prodPipeline[] = ['$group' => ['_id' => '$product_id', 'total' => ['$sum' => '$qty']]];
  $prodAgg = $col->aggregate($prodPipeline)->toArray();
  $stockByProduct = [];
  foreach ($prodAgg as $pa) {
    $pid = $pa['_id'] ?? '';
    $stockByProduct[(string)$pid] = intval($pa['total'] ?? 0);
  }
} catch (\Exception $e) {
  $stockByProduct = [];
}

// Adjust total SKU: if a warehouse is selected, show number of SKUs present in that warehouse;
// otherwise show total SKUs in the system.
if (!empty($warehouse)) {
  // count only products that have positive stock in this warehouse
  $totalSKU = 0;
  foreach ($stockByProduct as $qty) {
    if (intval($qty) > 0) $totalSKU++;
  }
} else {
  $totalSKU = $mProduct->getTotalSKU();
}

// Compute product totals for summary when a product is selected
$productTotalAll = 0;
$productTotalInWarehouse = null;
if (!empty($product)) {
  $productTotalAll = array_sum($stockByProduct);
  if (!empty($warehouse)) {
    $productTotalInWarehouse = $stockByProduct[$product] ?? 0;
  }
}

// Build product list with stock totals (respecting optional product/warehouse filters)
$productsList = [];
if (!empty($warehouse)) {
  $prodIds = array_keys($stockByProduct);
  foreach ($prodIds as $pidVal) {
    if (!empty($product) && $pidVal !== (string)$product) continue;
    $p = $mProduct->getProductById((string)$pidVal);
    $sku = $p['sku'] ?? '';
    $name = $p['product_name'] ?? $p['name'] ?? $sku;
    $min = intval($p['min_stock'] ?? 0);
    $cur = isset($stockByProduct[$pidVal]) ? intval($stockByProduct[$pidVal]) : 0;
    $productsList[] = ['sku'=>$sku, 'name'=>$name, 'current'=>$cur, 'min'=>$min];
  }
} else {
  $allProducts = $mProduct->getAllProducts();
  foreach ($allProducts as $p) {
    $pid = $p['_id'] ?? ($p['id'] ?? ($p['product_id'] ?? ''));
    if (is_array($pid) && isset($pid['$oid'])) $pidVal = $pid['$oid']; else $pidVal = (string)$pid;
    if (!empty($product) && $pidVal !== (string)$product) continue;
    $sku = $p['sku'] ?? '';
    $name = $p['product_name'] ?? $p['name'] ?? $sku;
    $min = intval($p['min_stock'] ?? 0);
    $cur = isset($stockByProduct[$pidVal]) ? intval($stockByProduct[$pidVal]) : 0;
    $productsList[] = ['sku'=>$sku, 'name'=>$name, 'current'=>$cur, 'min'=>$min];
  }
}
// Mark low-stock products and sort so low-stock items appear first
foreach ($productsList as &$__p) {
  $__p['is_low'] = false;
  $__p['shortage'] = 0;
  $curq = intval($__p['current'] ?? 0);
  $minq = intval($__p['min'] ?? 0);
  if ($minq > 0 && $curq > 0 && $curq < $minq) {
    $__p['is_low'] = true;
    $__p['shortage'] = $minq - $curq;
  }
}
unset($__p);

// Sort: low-stock items first (by shortage desc), then by current qty desc
usort($productsList, function($a, $b){
  if (($a['is_low'] ? 1 : 0) !== ($b['is_low'] ? 1 : 0)) return ($a['is_low'] ? -1 : 1);
  if ($a['is_low'] && $b['is_low']) return ($b['shortage'] <=> $a['shortage']);
  return ($b['current'] <=> $a['current']);
});

// CSV export for Inventory list (table-only). Respects optional warehouse and product filters.
if (isset($_GET['export']) && $_GET['export'] === 'csv_inventory') {
  $fn = 'inventory_' . date('Ymd_His') . (isset($warehouse) && $warehouse !== '' ? ('_w' . preg_replace('/[^A-Za-z0-9_-]/','', $warehouse)) : '') . '.csv';
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $fn . '"');
  // UTF-8 BOM for Excel
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, ['sku','name','warehouse_id','current','min']);
  foreach ($productsList as $pr) {
    $row = [
      $pr['sku'] ?? '',
      $pr['name'] ?? '',
      (!empty($warehouse) ? $warehouse : 'ALL'),
      isset($pr['current']) ? (string)intval($pr['current']) : '0',
      isset($pr['min']) ? (string)intval($pr['min']) : '0'
    ];
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
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
    <h2>Thống kê tồn kho</h2>
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

    <?php if (!empty($product)): ?>
      <div class="card">
        <h3>Thông tin sản phẩm đã chọn</h3>
        <div class="summary">
          <?php
            $pinfo = $mProduct->getProductById($product);
            $pname = $pinfo['product_name'] ?? ($pinfo['name'] ?? ($pinfo['sku'] ?? $product));
          ?>
          <span>Sản phẩm:</span><strong><?= htmlspecialchars($pname) ?></strong>
          <span style="margin-left:16px">Tổng tồn (tất cả kho):</span><strong><?= number_format($productTotalAll) ?></strong>
          <?php if ($productTotalInWarehouse !== null): ?>
            <span style="margin-left:16px">Tồn tại kho <?= htmlspecialchars($warehouse) ?>:</span><strong><?= number_format($productTotalInWarehouse) ?></strong>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3>Sản phẩm sắp hết (theo kho)</h3>
      <form method="get" action="/view/page/manage/index.php" id="inventory-filter-form" class="filter-form">
        <input type="hidden" name="page" value="report/InventoryStatistics">
        <label>Kho</label>
        <?php $warehouseDisabled = !$isGlobalViewer ? 'disabled' : ''; ?>
        <select name="warehouse" <?= $warehouseDisabled ?> onchange="document.getElementById('inventory-filter-form').submit();">
            <?php if ($isGlobalViewer): ?>
              <option value="">Chọn kho</option>
            <?php endif; ?>
            <?php
              $wareList = $mWarehouse->getAllWarehouses();
              if (!$isGlobalViewer) {
                $wareList = array_filter($wareList, fn($w) => (($w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? ''))) == $userWarehouse));
              }
              foreach ($wareList as $w):
                $wid = $w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? ''));
            ?>
              <option value="<?= htmlspecialchars($wid) ?>" <?= (isset($warehouse) && $warehouse == $wid) ? 'selected' : '' ?>><?= htmlspecialchars($w['warehouse_name'] ?? $w['name'] ?? $wid) ?></option>
            <?php endforeach; ?>
          </select>
        <?php if (!$isGlobalViewer): ?>
          <input type="hidden" name="warehouse" value="<?= htmlspecialchars($warehouse) ?>">
        <?php endif; ?>

        <label>Sản phẩm</label>
        <select name="product" onchange="document.getElementById('inventory-filter-form').submit();">
          <option value="">-- Tất cả sản phẩm --</option>
          <?php
            // If a warehouse is selected (or user is branch), limit products to those with stock in that warehouse
            if (!empty($warehouse)) {
              $prodIds = array_keys($stockByProduct);
              foreach ($prodIds as $pidVal) {
                // fetch product info for label
                $pp = $mProduct->getProductById((string)$pidVal);
                $label = ($pp['sku'] ?? '') . ' - ' . ($pp['product_name'] ?? ($pp['name'] ?? $pidVal));
                ?>
                <option value="<?= htmlspecialchars($pidVal) ?>" <?= (!empty($product) && $product == $pidVal) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php }
            } else {
              $prodList = $mProduct->getAllProducts();
              foreach ($prodList as $pp) {
                $pid = $pp['_id'] ?? ($pp['id'] ?? ($pp['product_id'] ?? ''));
                if (is_array($pid) && isset($pid['$oid'])) $pidVal = $pid['$oid']; else $pidVal = (string)$pid;
                $label = ($pp['sku'] ?? '') . ' - ' . ($pp['product_name'] ?? ($pp['name'] ?? $pidVal));
                ?>
                <option value="<?= htmlspecialchars($pidVal) ?>" <?= (!empty($product) && $product == $pidVal) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php }
            }
          ?>
        </select>

      </form>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>SKU</th><th>Tên</th><th>Tồn</th><th>Min</th></tr></thead>
          <tbody>
            <?php if (!empty($productsList)): foreach ($productsList as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['sku']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= number_format($p['current']) ?></td>
                <td><?= number_format($p['min']) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="4" style="text-align:center;color:#64748b">Không có sản phẩm</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h3>Tồn theo kho</h3>
      <div class="chart-container">
        <canvas id="byWarehouseChart"></canvas>
      </div>
    </div>
    
    <div class="card">
      <h3>Số lượng theo sản phẩm trong kho <?= $warehouse ? htmlspecialchars($warehouse) : 'Tất cả kho' ?></h3>
      <div class="chart-container" style="padding:16px;">
        <canvas id="productsByWarehouseChart"></canvas>
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
    // Prepare product-by-warehouse chart data from PHP-generated $productsList
    (function(){
      const prodLabels = <?= json_encode(array_map(function($p){ return ($p['sku'] ? $p['sku'] . ' - ' : '') . $p['name']; }, $productsList)) ?>;
      const prodData = <?= json_encode(array_map(function($p){ return intval($p['current']); }, $productsList)) ?>;
      // Limit to top 40 by quantity for readability
      const combined = prodLabels.map((l,i)=>({label:l, value: prodData[i]})).sort((a,b)=>b.value - a.value);
      const top = combined.slice(0,40).reverse(); // reverse so largest at top when horizontal
      const labelsH = top.map(x=>x.label);
      const dataH = top.map(x=>x.value);
      const canvas = document.getElementById('productsByWarehouseChart');
      if (canvas) {
        try { canvas.style.height = '420px'; } catch(e){}
        const ctxH = canvas.getContext('2d');
        // build a color palette cycling through some pleasant colors
        const palette = [
          '#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f472b6','#60a5fa','#34d399','#fb923c'
        ];
        const bg = labelsH.map((_,i) => palette[i % palette.length]);
        new Chart(ctxH, {
          type: 'doughnut',
          data: { labels: labelsH, datasets: [{ data: dataH, backgroundColor: bg, borderWidth: 0 }] },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '48%',
            plugins: {
              legend: { position: 'right', labels: { color: '#334155', boxWidth:12 } },
              tooltip: { callbacks: { label: function(ctx){ return ctx.label + ': ' + ctx.formattedValue; } } }
            }
          }
        });
      }
    })();
  </script>
</body>
</html>
