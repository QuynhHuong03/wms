<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../../model/mReceipt.php');
include_once(__DIR__ . '/../../../../../model/mProduct.php');
include_once(__DIR__ . '/../../../../../model/mWarehouse.php');
include_once(__DIR__ . '/../../../../../model/connect.php');

function normalize_items($items) {
  if (is_array($items)) return $items;
  if (is_object($items) || is_scalar($items)) {
    $arr = json_decode(json_encode($items), true);
    if (is_array($arr)) return $arr;
  }
  return [];
}

$mReceipt = new MReceipt();
$mProduct = new MProduct();
$mWarehouse = new MWarehouse();

$debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($debugMode) {
  $conn = (new clsKetNoi())->moKetNoi();
  $col = $conn->selectCollection('transactions');
  $samples = $col->find([], ['limit' => 10, 'sort' => ['created_at' => -1]]);
  $debugDocs = [];
  foreach ($samples as $s) $debugDocs[] = json_decode(json_encode($s), true);
}

$to = $_GET['to'] ?? date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$warehouse = $_GET['warehouse'] ?? '';

$cursor = $mReceipt->getReceiptsByDateRange($from, $to);

$dates = [];
$periodStart = strtotime($from);
$periodEnd = strtotime($to);
for ($ts = $periodStart; $ts <= $periodEnd; $ts += 86400) {
    $dates[date('Y-m-d', $ts)] = 0;
}

$outbound = [];
$productTotals = [];
foreach ($cursor as $r) {
    $tt = $r['transaction_type'] ?? ($r['type'] ?? null);
    // accept common transaction_type values for outbound
    if (!in_array($tt, ['export', 'issue', 'outbound'], true)) continue;
    if ($warehouse && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouse) continue;
    $d = '-';
    if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $d = date('Y-m-d', $r['created_at']->toDateTime()->getTimestamp());
    } elseif (isset($r['created_at'])) {
        $d = date('Y-m-d', strtotime($r['created_at']));
    }
    if (isset($dates[$d])) $dates[$d]++;
    $outbound[] = $r;

    // aggregate product quantities
    $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
    foreach ($items as $it) {
      $pid = $it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? ($it['sku'] ?? ($it['product_sku'] ?? null)));
      $qty = intval($it['qty'] ?? $it['quantity'] ?? $it['amount'] ?? 0);
      if (!$pid) continue;
      if (!isset($productTotals[$pid])) $productTotals[$pid] = 0;
      $productTotals[$pid] += $qty;
    }
}

arsort($productTotals);
$topProducts = array_slice($productTotals, 0, 10, true);

// CSV export: detailed outbound receipts
if (isset($_GET['export']) && $_GET['export'] === 'csv_receipts') {
  $fn = sprintf('outbound_receipts_%s_to_%s.csv', str_replace('-','',$from), str_replace('-','',$to));
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $fn . '"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');

  // determine maximum number of item columns needed
  $maxItems = 0;
  foreach ($outbound as $r) {
    $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
    $count = count($items);
    if ($count > $maxItems) $maxItems = $count;
  }

  $header = ['transaction_id','created_at','warehouse_id','product_count','total_units','total_value','note'];
  for ($i = 1; $i <= $maxItems; $i++) {
    $header[] = 'product' . $i . '_sku';
    $header[] = 'product' . $i . '_name';
    $header[] = 'product' . $i . '_qty';
    $header[] = 'product' . $i . '_price';
  }
  fputcsv($out, $header);

  foreach ($outbound as $r) {
    $tid = $r['transaction_id'] ?? (isset($r['_id']) ? (is_object($r['_id']) ? (string)$r['_id'] : $r['_id']) : '-');
    $created_at = '-'; if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) $created_at = $r['created_at']->toDateTime()->format('Y-m-d H:i:s'); elseif (isset($r['created_at'])) $created_at = date('Y-m-d H:i:s', strtotime($r['created_at']));
    $warehouse_id = $r['warehouse_id'] ?? '';

    $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
    $uniqueProducts = [];
    $totalUnits = 0.0;
    $totalValue = 0.0;
    $productCells = [];
    foreach ($items as $it) {
      $qty = (float)($it['qty'] ?? $it['quantity'] ?? $it['amount'] ?? 0);
      $price = (float)($it['unit_price'] ?? $it['price'] ?? $it['cost'] ?? 0);
      $pidc = (string)($it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? ''));
      $skuc = (string)($it['sku'] ?? ($it['product_sku'] ?? ''));
      $pnamec = (string)($it['product_name'] ?? ($it['name'] ?? ''));
      $key = '';
      if ($pidc !== '') $key = preg_replace('/[^A-Za-z0-9]/', '', $pidc);
      elseif ($skuc !== '') $key = preg_replace('/[^A-Za-z0-9]/', '', $skuc);
      else $key = mb_strtolower(trim($pnamec));
      if ($key !== '') $uniqueProducts[$key] = true;
      $totalUnits += $qty;
      $totalValue += ($qty * $price);
      $skuCell = trim($skuc);
      $nameCell = trim($pnamec);
      $qtyCell = ($qty + 0);
      $priceCell = ($price + 0);
      $productCells[] = $skuCell;
      $productCells[] = $nameCell;
      $productCells[] = $qtyCell;
      $productCells[] = $priceCell;
    }
    $productCount = count($uniqueProducts);
    $note = $r['note'] ?? ($r['notes'] ?? '');
    while (count($productCells) < ($maxItems * 4)) $productCells[] = '';
    $row = [$tid, $created_at, $warehouse_id, $productCount, $totalUnits, round($totalValue,2), $note];
    fputcsv($out, array_merge($row, $productCells));
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
  <title>Outbound Statistics</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root{--card:#fff;--border:rgba(15,23,42,0.06);--muted:#6b7280;--text:#0f172a}
    body{font-family:Inter,Arial;margin:18px;background:#f6f8fb;color:var(--text)}
    .container{max-width:1100px;margin:0 auto}
    .card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:14px}
    .controls{display:flex;gap:8px;align-items:center;margin-bottom:12px}
  </style>
</head>
<body>
  <div class="container">
    <?php if ($debugMode): ?>
      <div class="card">
        <h3>Debug: sample transactions (most recent 10)</h3>
        <?php if (!empty($debugDocs)): ?>
          <pre style="white-space:pre-wrap;background:#f3f4f6;padding:8px;border-radius:6px;border:1px solid var(--border);"><?= htmlspecialchars(json_encode($debugDocs, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) ?></pre>
        <?php else: ?>
          <div style="color:var(--muted)">Không tìm thấy document nào trong collection `transactions`.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <h2>Thống kê Xuất (Outbound)</h2>
    <div class="card">
      <form method="get" class="controls">
        Từ <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        Đến <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        Kho
        <select name="warehouse">
          <option value="">Tất cả</option>
          <?php foreach ($mWarehouse->getAllWarehouses() as $w):
            $wid = $w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? ''));
          ?>
            <option value="<?= htmlspecialchars($wid) ?>" <?= $warehouse == $wid ? 'selected' : '' ?>><?= htmlspecialchars($w['warehouse_name'] ?? $w['name'] ?? $wid) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Áp dụng</button>
        <a href="<?= '/kltn/view/page/manage/report/OutboundStatistics/index.php?export=csv_receipts&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) ?>" style="margin-left:8px"><button type="button">Export receipts CSV</button></a>
      </form>

      <canvas id="outboundChart"></canvas>
    </div>

    <div class="card">
      <h3>Top sản phẩm xuất nhiều</h3>
      <ol>
        <?php foreach ($topProducts as $pid => $qty):
            $pinfo = $mProduct->getProductById($pid);
            $name = $pinfo['product_name'] ?? ($pinfo['name'] ?? $pid);
        ?>
          <li><?= htmlspecialchars($name) ?> — <?= intval($qty) ?></li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="card">
      <h3>Phiếu xuất gần nhất</h3>
      <table>
        <thead><tr><th>Mã</th><th>Ngày</th><th>Kho</th><th>Số loại SP</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($outbound, 0, 20) as $r):
                $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
                $uniqueProducts = [];
                $totalUnits = 0.0;
                $totalValue = 0.0;
                foreach ($items as $it) {
                  $qty = (float)($it['qty'] ?? $it['quantity'] ?? $it['amount'] ?? 0);
                  $price = (float)($it['unit_price'] ?? $it['price'] ?? $it['cost'] ?? 0);
                  $pidc = (string)($it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? ''));
                  $skuc = (string)($it['sku'] ?? ($it['product_sku'] ?? ''));
                  $pnamec = (string)($it['product_name'] ?? ($it['name'] ?? ''));
                  $key = '';
                  if ($pidc !== '') $key = preg_replace('/[^A-Za-z0-9]/', '', $pidc);
                  elseif ($skuc !== '') $key = preg_replace('/[^A-Za-z0-9]/', '', $skuc);
                  else $key = mb_strtolower(trim($pnamec));
                  if ($key !== '') $uniqueProducts[$key] = true;
                  $totalUnits += $qty;
                  $totalValue += ($qty * $price);
                }
                $productCount = count($uniqueProducts);
                $tid = $r['transaction_id'] ?? (isset($r['_id']) ? (is_object($r['_id']) ? (string)$r['_id'] : $r['_id']) : '-');
                $created_at = '-'; if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) $created_at = $r['created_at']->toDateTime()->format('Y-m-d H:i:s'); elseif (isset($r['created_at'])) $created_at = date('Y-m-d H:i:s', strtotime($r['created_at']));
                $warehouse_id = $r['warehouse_id'] ?? '';
              ?>
                <tr>
                  <td><?= htmlspecialchars($tid) ?></td>
                  <td><?= htmlspecialchars($created_at) ?></td>
                  <td><?= htmlspecialchars($warehouse_id) ?></td>
                  <td><?= intval($productCount) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <script>
        const labels = <?= json_encode(array_keys($dates)) ?>;
        const data = <?= json_encode(array_values($dates)) ?>;
        const ctx = document.getElementById('outboundChart').getContext('2d');
        new Chart(ctx, {type:'bar',data:{labels:labels,datasets:[{label:'Số phiếu xuất',data:data,backgroundColor:'rgba(30,144,255,0.7)'}]},options:{responsive:true}});
        </script>
      </div>
      </body>
      </html>
