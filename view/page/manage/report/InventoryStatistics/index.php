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
    :root{--card:#fff;--border:rgba(15,23,42,0.06);--muted:#6b7280;--text:#0f172a}
    body{font-family:Inter,Arial;margin:18px;background:#f6f8fb;color:var(--text)}
    .container{max-width:1100px;margin:0 auto}
    .card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:14px}
  </style>
</head>
<body>
  <div class="container">
    <h2>Tồn kho (Inventory)</h2>
    <div class="card">
      <div style="display:flex;gap:18px;align-items:center">
        <div>
          <div class="muted">Tổng SKU</div>
          <div style="font-size:20px;font-weight:700"><?= number_format($totalSKU) ?></div>
        </div>
        <div>
          <div class="muted">Tổng số lượng</div>
          <div style="font-size:20px;font-weight:700;color:var(--success)"><?= number_format($totalQty) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Tồn theo kho</h3>
      <canvas id="byWarehouseChart"></canvas>
    </div>

    <div class="card">
      <h3>Sản phẩm sắp hết (theo kho)</h3>
      <form method="get" style="margin-bottom:10px">
        Kho
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
            <tr><td colspan="4">Không có sản phẩm thiếu</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    const labels = <?= json_encode(array_keys($byWarehouse)) ?>;
    const data = <?= json_encode(array_values($byWarehouse)) ?>;
    const ctx = document.getElementById('byWarehouseChart').getContext('2d');
    new Chart(ctx, {type:'bar',data:{labels:labels,datasets:[{label:'Tồn theo kho',data:data,backgroundColor:'rgba(99,102,241,0.7)'}]},options:{responsive:true}});
  </script>
</body>
</html>
