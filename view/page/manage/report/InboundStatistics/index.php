<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }
// Role-based warehouse visibility
$user = $_SESSION['login'] ?? null;
$roleId = isset($user['role_id']) ? intval($user['role_id']) : null;
$userWarehouse = $user['warehouse_id'] ?? ($user['warehouse'] ?? '');
$isGlobalViewer = in_array($roleId, [1,2]);
include_once(__DIR__ . '/../../../../../model/mReceipt.php');
include_once(__DIR__ . '/../../../../../model/mWarehouse.php');
include_once(__DIR__ . '/../../../../../model/mProduct.php');

$mReceipt = new MReceipt();
$mWarehouse = new MWarehouse();
 $mProduct = new MProduct();

$to = $_GET['to'] ?? date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$warehouse = $_GET['warehouse'] ?? '';
if (!$isGlobalViewer) {
  $warehouse = $userWarehouse;
}

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
    // If supplier not provided on receipt, try to infer from the first product's supplier
    if ((empty($supplier_name) || $supplier_name === 'Không rõ') && !empty($items) && isset($mProduct)) {
      foreach ($items as $it) {
        $pid = $it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? null);
        if (!$pid) continue;
        // Normalize pid to scalar string
        if (is_object($pid)) {
          if (method_exists($pid, '__toString')) $pid = (string)$pid;
          elseif (isset($pid->{'$oid'})) $pid = (string)$pid->{'$oid'};
          else $pid = json_encode($pid);
        } elseif (is_array($pid)) {
          if (isset($pid['$oid'])) $pid = $pid['$oid']; else $pid = json_encode($pid);
        }
        if (!$pid) continue;
        try {
          $prodInfo = $mProduct->getProductById($pid);
          if ($prodInfo && !empty($prodInfo['supplier'])) {
            $supplier_name = $prodInfo['supplier'];
            break;
          }
        } catch (Throwable $e) {
          // ignore lookup errors
        }
      }
    }
    $uniqueProducts = [];
    $totalUnits = 0.0;
    $totalValue = 0.0;
    $productCells = [];
    foreach ($items as $it) {
      $qty = (float)($it['qty'] ?? $it['quantity'] ?? $it['amount'] ?? 0);
      $price = (float)($it['unit_price'] ?? $it['price'] ?? $it['purchase_price'] ?? $it['import_price'] ?? $it['cost'] ?? 0);
      $pidc = (string)($it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? ''));
      $skuc = (string)($it['sku'] ?? ($it['product_sku'] ?? ''));
      // If SKU missing on the item, try to lookup product by id to get SKU
      if (($skuc === '' || $skuc === null) && $pidc !== '' && isset($mProduct)) {
        try {
          $lookupSku = null;
          // normalize pidc for getProductById (function handles ObjectId string or raw)
          $prodInfo = $mProduct->getProductById($pidc);
          if ($prodInfo && !empty($prodInfo['sku'])) {
            $lookupSku = $prodInfo['sku'];
          }
          if ($lookupSku) $skuc = (string)$lookupSku;
        } catch (Throwable $e) {
          // ignore lookup errors
        }
      }
      $pnamec = (string)($it['product_name'] ?? ($it['name'] ?? ''));
      $key = '';
      if ($pidc !== '') $key = preg_replace('/[^A-Za-z0-9]/', '', $pidc);
      elseif ($skuc !== '') $key = preg_replace('/[^A-Za-z0-9]/', '', $skuc);
      else $key = mb_strtolower(trim($pnamec));
      if ($key !== '') $uniqueProducts[$key] = true;
      $totalUnits += $qty;
      $totalValue += ($qty * $price);
      // push 4 columns per product (format numbers to avoid Excel scientific notation)
      $productCells[] = $skuc;
      $productCells[] = $pnamec;
      if (intval($qty) == $qty) {
        $qtyOut = (string)intval($qty);
      } else {
        $qtyOut = number_format($qty, 2, '.', '');
      }
      $priceOut = number_format($price, 2, '.', '');
      $productCells[] = $qtyOut;
      $productCells[] = $priceOut;
    }
    $productCount = count($uniqueProducts);
    $note = $r['note'] ?? ($r['notes'] ?? '');

    // pad product cells to maxItems * 4 (sku,name,qty,price)
    while (count($productCells) < $maxItems * 4) $productCells[] = '';

    // Format totals for CSV to avoid scientific notation in Excel
    if (intval($totalUnits) == $totalUnits) {
      $totalUnitsOut = (string)intval($totalUnits);
    } else {
      $totalUnitsOut = number_format($totalUnits, 2, '.', '');
    }
    $totalValueOut = number_format($totalValue, 2, '.', '');
    $row = [$tid, $created_at, $warehouse_id, $supplier_name, $productCount, $totalUnitsOut, $totalValueOut, $note];
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
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
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
    
    .filters {
      display: flex;
      gap: 15px;
      align-items: center;
      flex-wrap: wrap;
      padding-bottom: 20px;
      border-bottom: 2px solid #f1f5f9;
    }
    
    .filters label {
      font-size: 14px;
      font-weight: 600;
      color: #475569;
      white-space: nowrap;
    }
    
    .filters input[type="date"],
    .filters select {
      height: 42px;
      padding: 0 14px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      background: #ffffff;
      font-size: 14px;
      color: #1e293b;
      transition: all 0.3s ease;
      min-width: 150px;
    }
    
    .filters input[type="date"]:hover,
    .filters select:hover {
      border-color: #cbd5e1;
    }
    
    .filters input[type="date"]:focus,
    .filters select:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
    }
    
    .filters button {
      height: 42px;
      padding: 0 24px;
      border-radius: 8px;
      border: none;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #ffffff;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
      box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    }
    
    .filters button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(102,126,234,0.4);
    }
    
    .filters button:active {
      transform: translateY(0);
    }
    
    .filters .btn-export {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
    
    .filters .btn-export:hover {
      box-shadow: 0 6px 16px rgba(16,185,129,0.4);
    }
    
    .summary {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 20px;
      margin-top: 20px;
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      border-radius: 12px;
      border-left: 4px solid #0ea5e9;
    }
    
    .summary span {
      font-size: 16px;
      color: #475569;
      font-weight: 500;
    }
    
    .summary strong {
      color: #0369a1;
      font-size: 28px;
      font-weight: 700;
    }
    
    .chart-container {
      margin-top: 24px;
      padding: 20px;
      background: #f8fafc;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
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
      position: sticky;
      top: 0;
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
    
    ol {
      padding-left: 0;
      margin-top: 16px;
      list-style: none;
      counter-reset: item;
    }
    
    ol li {
      padding: 16px 20px;
      color: #1e293b;
      border-bottom: 1px solid #f1f5f9;
      counter-increment: item;
      position: relative;
      padding-left: 60px;
      transition: all 0.2s ease;
    }
    
    ol li:before {
      content: counter(item);
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
    }
    
    ol li:hover {
      background: #f8fafc;
      padding-left: 70px;
    }
    
    ol li:last-child {
      border-bottom: none;
    }
    
    ol li strong {
      color: #0f172a;
      font-weight: 600;
    }
    
    .table-wrapper {
      overflow-x: auto;
      margin-top: 16px;
      border-radius: 8px;
    }
    
    code {
      background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 13px;
      font-family: 'Courier New', monospace;
      color: #475569;
      font-weight: 600;
    }
    
    .badge {
      background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
      color: #1e40af;
      padding: 4px 12px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 13px;
      display: inline-block;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Thống kê Nhập kho</h2>
    
    <div class="card">
      <form method="get" action="/view/page/manage/index.php" class="filters">
        <input type="hidden" name="page" value="report/InboundStatistics">
        <label>Từ ngày:</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        <label>Đến ngày:</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        <label>Kho:</label>
        <select name="warehouse">
          <?php if ($isGlobalViewer): ?>
            <option value="">-- Tất cả kho --</option>
          <?php endif; ?>
          <?php
            $wareList = $mWarehouse->getAllWarehouses();
            if (!$isGlobalViewer) {
              $wareList = array_filter($wareList, fn($w) => (($w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? ''))) == $userWarehouse));
            }
            foreach ($wareList as $w): $wid = $w['warehouse_id'] ?? ($w['_id']['$oid'] ?? ($w['id'] ?? '')); ?>
            <option value="<?= htmlspecialchars($wid) ?>" <?= $warehouse == $wid ? 'selected' : '' ?>><?= htmlspecialchars($w['warehouse_name'] ?? $w['name'] ?? $wid) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">Xem báo cáo</button>
        <a href="<?= '/view/page/manage/report/InboundStatistics/index.php?export=csv_receipts&from=' . rawurlencode($from) . '&to=' . rawurlencode($to) . (isset($warehouse) && $warehouse !== '' ? '&warehouse=' . rawurlencode($warehouse) : '') ?>"><button type="button" class="btn-export">Xuất file</button></a>
      </form>
      
      <div class="summary">
        <span>Tổng số phiếu nhập:</span><strong><?= number_format(count($inboundReceipts)) ?></strong><span>phiếu</span>
      </div>
      
      <div class="chart-container">
        <canvas id="inboundChart" style="width:100%;height:280px"></canvas>
      </div>
    </div>

    <div class="card">
      <h3>Top 10 sản phẩm nhập nhiều nhất</h3>
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
      <h3>Chi tiết phiếu nhập</h3>
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
              
              // Nếu không có thông tin nhà cung cấp trên phiếu, lấy từ sản phẩm
              if ((empty($supplier_name) || $supplier_name === 'Không rõ') && !empty($items)) {
                foreach ($items as $it) {
                  $pid = $it['product_id'] ?? $it['productId'] ?? ($it['_id'] ?? null);
                  if (!$pid) continue;
                  // Chuẩn hóa pid thành string
                  if (is_object($pid)) {
                    if (method_exists($pid, '__toString')) $pid = (string)$pid;
                    elseif (isset($pid->{'$oid'})) $pid = (string)$pid->{'$oid'};
                    else $pid = json_encode($pid);
                  } elseif (is_array($pid)) {
                    if (isset($pid['$oid'])) $pid = $pid['$oid']; else $pid = json_encode($pid);
                  }
                  if (!$pid) continue;
                  try {
                    $prodInfo = $mProduct->getProductById($pid);
                    if ($prodInfo && !empty($prodInfo['supplier'])) {
                      $supplier_name = $prodInfo['supplier'];
                      break; // Tìm thấy thì dừng
                    }
                  } catch (Throwable $e) {
                    // Bỏ qua lỗi tra cứu
                  }
                }
              }
              
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
                <td><code><?= htmlspecialchars($tid) ?></code></td>
                <td><?= htmlspecialchars($created_at) ?></td>
                <td><?= htmlspecialchars($warehouse_id) ?></td>
                <td><?= htmlspecialchars($supplier_name) ?></td>
                <td style="text-align:center"><span class="badge"><?= $productCount ?></span></td>
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
