<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../../model/mReceipt.php');
include_once(__DIR__ . '/../../../../../model/mWarehouse.php');
include_once(__DIR__ . '/../../../../../model/mProduct.php');

$mReceipt = new MReceipt();
$mWarehouse = new MWarehouse();
 $mProduct = new MProduct();

$to = $_GET['to'] ?? date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$warehouse = $_GET['warehouse'] ?? '';

function normText($s) { return mb_strtolower(trim((string)$s)); }

function normalize_items($items) {
  if (is_array($items)) return $items;
  if (is_object($items) || is_scalar($items)) {
    $arr = json_decode(json_encode($items), true);
    if (is_array($arr)) return $arr;
  }
  return [];
}

$cursor = $mReceipt->getReceiptsByDateRange($from, $to);

$dates = [];
$periodStart = strtotime($from);
$periodEnd = strtotime($to);
for ($ts = $periodStart; $ts <= $periodEnd; $ts += 86400) {
    $dates[date('Y-m-d', $ts)] = 0;
}

$inboundReceipts = [];
foreach ($cursor as $r) {
    $tt = $r['transaction_type'] ?? ($r['type'] ?? null);
    $tt_l = is_string($tt) ? strtolower($tt) : '';
    // skip non-inbound transaction types (exports, outbounds, and request forms)
    if (in_array($tt_l, ['export', 'outbound', 'issue', 'goods_request', 'request', 'requisition', 'purchase_request', 'return_to_supplier'], true)) continue;
    if ($warehouse && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouse) continue;
    $d = '-';
    if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $d = date('Y-m-d', $r['created_at']->toDateTime()->getTimestamp());
    } elseif (isset($r['created_at'])) {
        $d = date('Y-m-d', strtotime($r['created_at']));
    }
    if (isset($dates[$d])) $dates[$d]++;
    $inboundReceipts[] = $r;
}

// aggregate inbound product totals (for Top Products)
$productTotals = [];
foreach ($inboundReceipts as $r) {
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

if (isset($_GET['export']) && $_GET['export'] === 'csv_summary') {
  $fn = sprintf('inbound_summary_%s_to_%s.csv', str_replace('-','',$from), str_replace('-','',$to));
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $fn . '"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, ['date','count']);
  $total = 0;
  foreach ($dates as $d => $cnt) { fputcsv($out, [$d, intval($cnt)]); $total += intval($cnt); }
  fputcsv($out, ['TOTAL', $total]);
  fclose($out);
  exit;
}

// CSV export: detailed receipts table
if (isset($_GET['export']) && $_GET['export'] === 'csv_receipts') {
  $fn = sprintf('inbound_receipts_%s_to_%s.csv', str_replace('-','',$from), str_replace('-','',$to));
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $fn . '"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');

  // determine maximum number of item columns needed
  $maxItems = 0;
  foreach ($inboundReceipts as $r) {
    $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
    $count = is_array($items) ? count($items) : 0;
    if ($count > $maxItems) $maxItems = $count;
  }

  // header: base columns + product columns per item (sku, name, qty, price)
  $header = ['transaction_id','created_at','warehouse_id','supplier','product_count','total_units','total_value','note'];
  for ($i = 1; $i <= $maxItems; $i++) {
    $header[] = 'product' . $i . '_sku';
    $header[] = 'product' . $i . '_name';
    $header[] = 'product' . $i . '_qty';
    $header[] = 'product' . $i . '_price';
  }
  fputcsv($out, $header);

  foreach ($inboundReceipts as $r) {
    $tid = $r['transaction_id'] ?? (isset($r['_id']) ? (is_object($r['_id']) ? (string)$r['_id'] : $r['_id']) : '-');
    $created_at = '-'; if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) $created_at = $r['created_at']->toDateTime()->format('Y-m-d H:i:s'); elseif (isset($r['created_at'])) $created_at = date('Y-m-d H:i:s', strtotime($r['created_at']));
    $warehouse_id = $r['warehouse_id'] ?? '';
    $supplier_name = 'Không rõ';
    if (isset($r['supplier']['name'])) $supplier_name = $r['supplier']['name'];
    elseif (isset($r['supplier_name'])) $supplier_name = $r['supplier_name'];
    elseif (isset($r['vendor'])) $supplier_name = $r['vendor'];

    $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
    $uniqueProducts = [];
    $totalUnits = 0.0;
    $totalValue = 0.0;
    $productCells = [];
    foreach ($items as $it) {
      $qty = (float)($it['qty'] ?? $it['quantity'] ?? $it['amount'] ?? 0);
      $price = (float)($it['unit_price'] ?? $it['price'] ?? $it['purchase_price'] ?? $it['import_price'] ?? $it['cost'] ?? 0);
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
      // push 4 columns per product
      $productCells[] = $skuc;
      $productCells[] = $pnamec;
      $productCells[] = $qty + 0;
      $productCells[] = $price + 0;
    }
    $productCount = count($uniqueProducts);
    $note = $r['note'] ?? ($r['notes'] ?? '');

    // pad product cells to maxItems * 4 (sku,name,qty,price)
    while (count($productCells) < $maxItems * 4) $productCells[] = '';

    $row = [$tid, $created_at, $warehouse_id, $supplier_name, $productCount, $totalUnits, round($totalValue, 2), $note];
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
  <title>Inbound Statistics</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:12px;background:#f6f8fb;color:#0f172a}
    .container{max-width:1100px;margin:0 auto}
    .card{background:#fff;border:1px solid #e6eef7;border-radius:8px;padding:14px;margin-bottom:12px}
    table{width:100%;border-collapse:collapse}
    table th, table td{padding:8px;border-bottom:1px solid #f3f4f6;text-align:left}
  </style>
</head>
<body>
  <div class="container">
    <h2>Thống kê Nhập (Inbound)</h2>
    <div class="card">
      <form method="get" action="/kltn/view/page/manage/index.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="page" value="report/InboundStatistics">
        Từ <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        Đến <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        Kho
        <select name="warehouse">
          <option value="">Tất cả</option>
          <?php foreach ($mWarehouse->getAllWarehouses() as $w): $wid = $w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? '')); ?>
            <option value="<?= htmlspecialchars($wid) ?>" <?= $warehouse == $wid ? 'selected' : '' ?>><?= htmlspecialchars($w['warehouse_name'] ?? $w['name'] ?? $wid) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Áp dụng</button>
        <a href="<?= '/kltn/view/page/manage/report/InboundStatistics/index.php?export=csv_summary&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) ?>" style="margin-left:auto"><button type="button">Export summary CSV</button></a>
        <a href="<?= '/kltn/view/page/manage/report/InboundStatistics/index.php?export=csv_receipts&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) ?>" style="margin-left:8px"><button type="button">Export receipts CSV</button></a>
      </form>
      <div style="margin-top:12px">
        <canvas id="inboundChart" style="width:100%;height:260px"></canvas>
      </div>
      <div style="margin-top:8px">Tổng phiếu: <strong><?= intval(count($inboundReceipts)) ?></strong></div>
    </div>

    <div class="card">
      <h3>Top sản phẩm nhập nhiều</h3>
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
      <h3>Chi tiết phiếu nhập</h3>
      <table>
        <thead><tr><th>Mã phiếu</th><th>Ngày</th><th>Kho</th><th>Nhà cung cấp</th><th>Số loại SP</th><th>Ghi chú</th></tr></thead>
        <tbody>
          <?php foreach ($inboundReceipts as $r):
            $tid = $r['transaction_id'] ?? (isset($r['_id']) ? (is_object($r['_id']) ? (string)$r['_id'] : $r['_id']) : '-');
            $created_at = '-'; if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) $created_at = $r['created_at']->toDateTime()->format('Y-m-d H:i:s'); elseif (isset($r['created_at'])) $created_at = date('Y-m-d H:i:s', strtotime($r['created_at']));
            $warehouse_id = $r['warehouse_id'] ?? '-';
            $supplier_name = 'Không rõ';
            if (isset($r['supplier']['name'])) $supplier_name = $r['supplier']['name'];
            elseif (isset($r['supplier_name'])) $supplier_name = $r['supplier_name'];
            elseif (isset($r['vendor'])) $supplier_name = $r['vendor'];
            $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
            $unique = [];
            foreach ($items as $it) {
              $pidc = (string)($it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? ''));
              $skuc = (string)($it['sku'] ?? ($it['product_sku'] ?? ''));
              $pnamec = (string)($it['product_name'] ?? ($it['name'] ?? ''));
              if ($pidc !== '') $key = preg_replace('/[^A-Za-z0-9]/','',$pidc);
              elseif ($skuc !== '') $key = preg_replace('/[^A-Za-z0-9]/','',$skuc);
              else $key = mb_strtolower(trim($pnamec));
              if ($key !== '') $unique[$key] = true;
            }
            $productCount = count($unique);
          ?>
            <tr>
              <td><?= htmlspecialchars($tid) ?></td>
              <td><?= htmlspecialchars($created_at) ?></td>
              <td><?= htmlspecialchars($warehouse_id) ?></td>
              <td><?= htmlspecialchars($supplier_name) ?></td>
              <td><?= htmlspecialchars($productCount) ?></td>
              <td><?= htmlspecialchars($r['note'] ?? ($r['notes'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    const labels = <?= json_encode(array_keys($dates)) ?>;
    const data = <?= json_encode(array_values($dates)) ?>;
    const ctx = document.getElementById('inboundChart').getContext('2d');
    new Chart(ctx, {type:'bar',data:{labels:labels,datasets:[{label:'Số phiếu nhập',data:data,backgroundColor:'rgba(34,197,94,0.7)'}]},options:{responsive:true}});
  </script>
</body>
</html>
