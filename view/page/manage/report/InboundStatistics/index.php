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
// compute summary stats
$totalReceipts = 0;
$totalUnitsAll = 0.0;
$totalValueAll = 0.0;
$distinctProductKeys = [];
foreach ($inboundReceipts as $r) {
  $items = normalize_items($r['items'] ?? ($r['details'] ?? []));
  foreach ($items as $it) {
    $pid = $it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? ($it['sku'] ?? ($it['product_sku'] ?? null)));
    $qty = intval($it['qty'] ?? $it['quantity'] ?? $it['amount'] ?? 0);
    $price = (float)($it['unit_price'] ?? $it['price'] ?? $it['purchase_price'] ?? $it['import_price'] ?? $it['cost'] ?? 0);
    if (!$pid) continue;
    if (!isset($productTotals[$pid])) $productTotals[$pid] = 0;
    $productTotals[$pid] += $qty;

    $key = is_scalar($pid) ? preg_replace('/[^A-Za-z0-9]/','', (string)$pid) : '';
    if ($key !== '') $distinctProductKeys[$key] = true;
    $totalUnitsAll += $qty;
    $totalValueAll += ($qty * $price);
  }
  $totalReceipts++;
}
arsort($productTotals);
$topProducts = array_slice($productTotals, 0, 10, true);
// prefetch product names for top list to reduce calls
$topProductInfo = [];
foreach (array_keys($topProducts) as $pid) {
  try { $info = $mProduct->getProductById($pid); } catch (Throwable $e) { $info = []; }
  $topProductInfo[$pid] = $info;
}

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
  <title>Thống kê Nhập kho</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f0f4f8;color:#1e293b;line-height:1.6}
    .container{max-width:1200px;margin:0 auto;padding:20px}
    h2{font-size:28px;font-weight:600;color:#0f172a;margin-bottom:20px;border-left:4px solid #0ea5e9;padding-left:12px}
    h3{font-size:18px;font-weight:600;color:#334155;margin-bottom:14px}
    .card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.06)}
    .filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding-bottom:16px;border-bottom:1px solid #f1f5f9}
    .filters label{font-size:14px;font-weight:500;color:#64748b}
    .filters input[type="date"],.filters select{height:36px;padding:0 12px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-size:14px;transition:all 0.2s}
    .filters input[type="date"]:focus,.filters select:focus{outline:none;border-color:#0ea5e9;box-shadow:0 0 0 3px rgba(14,165,233,0.1)}
    .filters button{height:36px;padding:0 16px;border-radius:6px;border:none;background:#0ea5e9;color:#fff;font-weight:500;cursor:pointer;transition:all 0.2s;font-size:14px}
    .filters button:hover{background:#0284c7;transform:translateY(-1px);box-shadow:0 2px 4px rgba(14,165,233,0.3)}
    .filters a button{background:#64748b}
    .filters a button:hover{background:#475569}
    .filters .btn-export{background:#10b981}
    .filters .btn-export:hover{background:#059669}
    .summary{display:flex;align-items:center;gap:8px;padding:12px 0;font-size:15px;color:#475569}
    .summary strong{color:#0f172a;font-size:24px;font-weight:700;margin-left:4px}
    .chart-container{margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px}
    table{width:100%;border-collapse:collapse;font-size:14px}
    table thead th{background:#f8fafc;color:#475569;font-weight:600;padding:12px;border-bottom:2px solid #e2e8f0;text-align:left;position:sticky;top:0}
    table tbody td{padding:12px;border-bottom:1px solid #f1f5f9}
    table tbody tr{transition:background 0.15s}
    table tbody tr:hover{background:#f8fafc}
    ol{padding-left:24px;margin-top:8px}
    ol li{padding:8px 0;color:#334155;border-bottom:1px solid #f1f5f9}
    ol li:last-child{border-bottom:none}
    .table-wrapper{overflow-x:auto;margin-top:12px}
  </style>
</head>
<body>
  <div class="container">
    <h2> Thống kê Nhập kho</h2>
    
    <div class="card">
      <form method="get" action="/kltn/view/page/manage/index.php" class="filters">
        <input type="hidden" name="page" value="report/InboundStatistics">
        <label>Từ ngày:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        <label>Đến ngày:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        <label>Kho:</label>
        <select name="warehouse">
          <option value="">-- Tất cả kho --</option>
          <?php foreach ($mWarehouse->getAllWarehouses() as $w): $wid = $w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? '')); ?>
            <option value="<?= htmlspecialchars($wid) ?>" <?= $warehouse == $wid ? 'selected' : '' ?>><?= htmlspecialchars($w['warehouse_name'] ?? $w['name'] ?? $wid) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit"> Xem báo cáo</button>
        <a href="<?= '/kltn/view/page/manage/report/InboundStatistics/index.php?export=csv_receipts&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) ?>"><button type="button" class="btn-export"> Xuất file</button></a>
      </form>
      
      <div class="summary">
        Tổng số phiếu nhập:<strong><?= number_format(count($inboundReceipts)) ?></strong> phiếu
      </div>
      
      <div class="chart-container">
        <canvas id="inboundChart" style="width:100%;height:280px"></canvas>
      </div>
    </div>

    <div class="card">
      <h3> Top 10 sản phẩm nhập nhiều nhất</h3>
      <ol>
        <?php foreach ($topProducts as $pid => $qty):
            $pinfo = $topProductInfo[$pid] ?? [];
            $name = $pinfo['product_name'] ?? ($pinfo['name'] ?? $pid);
        ?>
          <li><strong><?= htmlspecialchars($name) ?></strong> — <?= number_format($qty) ?> đơn vị</li>
        <?php endforeach; ?>
      </ol>
    </div>

    <div class="card">
      <h3> Chi tiết phiếu nhập</h3>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Mã phiếu</th>
              <th>Ngày tạo</th>
              <th>Kho</th>
              <th>Nhà cung cấp</th>
              <th style="text-align:center">Số loại SP</th>
              <th>Ghi chú</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inboundReceipts as $r):
              $tid = $r['transaction_id'] ?? (isset($r['_id']) ? (is_object($r['_id']) ? (string)$r['_id'] : $r['_id']) : '-');
              $created_at = '-'; 
              if (isset($r['created_at']) && $r['created_at'] instanceof MongoDB\BSON\UTCDateTime) 
                $created_at = $r['created_at']->toDateTime()->format('d/m/Y H:i'); 
              elseif (isset($r['created_at'])) 
                $created_at = date('d/m/Y H:i', strtotime($r['created_at']));
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
                <td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:13px"><?= htmlspecialchars($tid) ?></code></td>
                <td><?= htmlspecialchars($created_at) ?></td>
                <td><?= htmlspecialchars($warehouse_id) ?></td>
                <td><?= htmlspecialchars($supplier_name) ?></td>
                <td style="text-align:center"><span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:12px;font-weight:600;font-size:13px"><?= $productCount ?></span></td>
                <td style="color:#64748b"><?= htmlspecialchars($r['note'] ?? ($r['notes'] ?? '-')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    const labels = <?= json_encode(array_keys($dates)) ?>;
    const data = <?= json_encode(array_values($dates)) ?>;
    const ctx = document.getElementById('inboundChart').getContext('2d');
    new Chart(ctx, {
      type:'bar',
      data:{
        labels:labels,
        datasets:[{
          label:'Số phiếu nhập',
          data:data,
          backgroundColor:'rgba(14,165,233,0.7)',
          borderColor:'rgba(14,165,233,1)',
          borderWidth:2,
          borderRadius:6,
          hoverBackgroundColor:'rgba(14,165,233,0.9)'
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
