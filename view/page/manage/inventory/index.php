<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }
include_once(__DIR__ . "/../../../../controller/cInventory.php");
include_once(__DIR__ . "/../../../../controller/cWarehouse.php");
include_once(__DIR__ . "/../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../controller/cReceipt.php");

$cInventory = new CInventory();
$cWarehouse = new CWarehouse();
$cProduct = new CProduct();
$cReceipt = new CReceipt();

// ⭐ Lấy danh sách kho (cho kho tổng)
$allWarehouses = [];
try {
    $allWarehouses = $cWarehouse->getAllWarehouses();
} catch (Throwable $e) {
    $allWarehouses = [];
}

// Compute reliable web-accessible path to process.php
$processPath = '';
$dirFs = str_replace('\\', '/', realpath(__DIR__));
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : false;
if ($docRoot && $docRoot !== '' && strpos($dirFs, $docRoot) === 0) {
    $rel = substr($dirFs, strlen($docRoot));
    $processPath = '/' . ltrim($rel, '/') . '/process.php';
} else {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
    $processPath = $scriptDir . '/view/page/manage/inventory/process.php';
}
// Compute path to this inventory page (used for AJAX grouped requests)
$pagePath = '';
if ($docRoot && $docRoot !== '' && strpos($dirFs, $docRoot) === 0) {
	$rel2 = substr($dirFs, strlen($docRoot));
	$pagePath = '/' . ltrim($rel2, '/') . '/index.php';
} else {
	$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
	if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
	$pagePath = $scriptDir . '/view/page/manage/inventory/index.php';
}

// Read filters from GET
$q = $_GET['q'] ?? '';

// ⭐ Kiểm tra quyền xem: CHỈ KHO_TONG_01 được xem tất cả kho, các kho khác chỉ xem của mình
$user_warehouse_id = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? ($_SESSION['warehouse_id'] ?? '');
$user_warehouse_type = $_SESSION['login']['warehouse_type'] ?? '';
// Only KHO_TONG_01 can view all warehouses
$is_central_warehouse = ($user_warehouse_id === 'KHO_TONG_01');

// Nếu là kho tổng (main/central), cho phép chọn kho từ GET parameter
// Nếu là kho chi nhánh (branch), bắt buộc chỉ xem kho của mình
if ($is_central_warehouse) {
    // Kho tổng: có thể xem tất cả kho hoặc lọc theo warehouse_id từ GET
    $warehouse_id = $_GET['warehouse_id'] ?? '';  // Empty = xem tất cả
} else {
    // Kho chi nhánh: bắt buộc chỉ xem kho của mình
    $warehouse_id = $user_warehouse_id;
}

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$product_sku = $_GET['product_sku'] ?? '';
$p = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 20;
// View mode: list or grouped-by-product
$view = $_GET['view'] ?? 'grouped';

// Helper functions
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function id_str($v) {
	if (is_object($v)) {
		if (method_exists($v, '__toString')) return (string)$v;
		if ($v instanceof MongoDB\BSON\ObjectId) return (string)$v;
	}
	if (is_array($v) && isset($v['$oid'])) return (string)$v['$oid'];
	return $v !== null ? (string)$v : '';
}
function fmt_dt($ts) {
	try {
		if ($ts instanceof MongoDB\BSON\UTCDateTime) {
			$dt = $ts->toDateTime();
		} elseif ($ts instanceof DateTime) {
			$dt = $ts;
		} elseif (is_numeric($ts)) {
			$dt = (new DateTime())->setTimestamp((int)$ts);
		} else {
			$dt = new DateTime((string)$ts);
		}
		$dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
		return $dt->format('d/m/Y H:i');
	} catch (Throwable $e) {
		return '';
	}
}

// Helper: find last import date (phiếu nhập) for a product key (product_id or sku)
function last_import_str_for($cInventory, $cReceipt, $key, $from, $to) {
	if (!$key) return '';
	try {
		// Fetch a reasonable number of recent transactions, then pick latest inbound (qty>0)
		$res = $cInventory->getInventoryDetailsForProduct($key, [
			'from' => $from,
			'to' => $to,
			'page' => 1,
			'limit' => 100,
		]);
		$items = isset($res['items']) && is_array($res['items']) ? $res['items'] : [];
		$bestTs = null;
		$rcCache = [];
		foreach ($items as $it) {
			$qty = isset($it['qty']) ? (float)$it['qty'] : 0;
			if ($qty <= 0) continue; // only inbound
			// Prefer the receipt created_at time (ngày tạo phiếu nhập)
			$rc = $it['receipt_code'] ?? ($it['receipt_id'] ?? '');
			$dt = null;
			if ($rc) {
				if (!array_key_exists($rc, $rcCache)) {
					try { $r = $cReceipt->getReceiptById($rc); } catch (Throwable $e) { $r = null; }
					$rcCache[$rc] = $r;
				}
				$r = $rcCache[$rc];
				$ts = $r['created_at'] ?? null;
				if ($ts instanceof MongoDB\BSON\UTCDateTime) { $dt = $ts->toDateTime(); }
				elseif ($ts instanceof DateTime) { $dt = $ts; }
				elseif (!empty($ts) && is_numeric($ts)) { $dt = (new DateTime())->setTimestamp((int)$ts); }
				elseif (!empty($ts)) { $dt = new DateTime((string)$ts); }
			}
			// Fallback: use inventory time if receipt not found
			if (!$dt) {
				$ts2 = $it['received_at'] ?? ($it['created_at'] ?? null);
				if ($ts2 instanceof MongoDB\BSON\UTCDateTime) { $dt = $ts2->toDateTime(); }
				elseif ($ts2 instanceof DateTime) { $dt = $ts2; }
				elseif (!empty($ts2) && is_numeric($ts2)) { $dt = (new DateTime())->setTimestamp((int)$ts2); }
				elseif (!empty($ts2)) { $dt = new DateTime((string)$ts2); }
				else { continue; }
			}
			if (!$bestTs || $dt > $bestTs) { $bestTs = $dt; }
		}
		if ($bestTs) {
			$bestTs->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
			return $bestTs->format('d/m/Y H:i');
		}
	} catch (Throwable $e) {
		// ignore and fallback
	}
	return '';
}

// Load data
try {
	if ($view === 'grouped') {
		$result = $cInventory->getInventoryGroupedByProduct([
			'q' => $q,
			'warehouse_id' => $warehouse_id,  // ⭐ Truyền warehouse_id từ filter
			'from' => $from,
			'to' => $to,
			'page' => $p,
			'limit' => $limit
		]);
	} else {
		$result = $cInventory->getInventoryList([
			'q' => $q,
			'warehouse_id' => $warehouse_id,  // ⭐ Truyền warehouse_id từ filter
			'from' => $from,
			'to' => $to,
			'page' => $p,
			'limit' => $limit
		]);
	}
} catch (Throwable $e) {
	$result = ['items' => [], 'total' => 0, 'pages' => 1, 'page' => $p, 'limit' => $limit];
}

$items = $result['items'] ?? [];
if (!is_array($items)) { $items = []; }
$total = intval($result['total'] ?? 0);
$pages = max(1, intval($result['pages'] ?? 1));
$p = intval($result['page'] ?? $p);
$limit = intval($result['limit'] ?? $limit);

$warehouses = $cWarehouse->getAllWarehouses();

// Helper to build URL with current filters
function buildUrl($overrides = []) {
	$params = array_merge($_GET, $overrides);
	// Ensure router param stays on inventory
	$params['page'] = 'inventory';
	return 'index.php?' . http_build_query($params);
}

// Lightweight JSON endpoint for bin distribution (works with current filters)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'bins') {
	header('Content-Type: application/json; charset=utf-8');
	$key = $_GET['key'] ?? '';
	$byBin = [];
	if ($key) {
		$byBin = $cInventory->getBinDistributionForProduct($key, [
			'from' => $from,
			'to' => $to,
		]);
	}
	echo json_encode($byBin);
	exit;
}
// Lightweight JSON endpoint to return grouped inventory by product for AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'grouped') {
	header('Content-Type: application/json; charset=utf-8');
	$w = $_GET['warehouse_id'] ?? '';
	$fromF = $_GET['from'] ?? '';
	$toF = $_GET['to'] ?? '';
	
	// ⭐ Enforce warehouse access control: branch users can only see their own warehouse
	if (!$is_central_warehouse) {
		$w = $user_warehouse_id;  // Force branch users to their own warehouse
	}
	
	try {
		$res = $cInventory->getInventoryGroupedByProduct([
			'q' => $_GET['q'] ?? '',
			'warehouse_id' => $w,
			'from' => $fromF,
			'to' => $toF,
			'page' => 1,
			'limit' => 1000
		]);
		$items = $res['items'] ?? [];
		
		// ⭐ Enrich items with product details (name, SKU)
					foreach ($items as $idx => $item) {
						$pid = $item['product_id'] ?? null;
						// Ensure we use a string id when calling product lookup
						$pidStr = id_str($pid);
						$product = null;
						if (!empty($pidStr)) {
							try {
								$product = $cProduct->getProductById($pidStr);
								if ($product) {
									$items[$idx]['product_name'] = $product['product_name'] ?? ($items[$idx]['product_name'] ?? '');
									// Ưu tiên lấy 'sku' field, sau đó mới 'product_sku'
									$items[$idx]['product_sku'] = $product['sku'] ?? ($product['product_sku'] ?? ($items[$idx]['product_sku'] ?? ''));
								}
							} catch (Throwable $e) {
								// Continue if product not found
							}
						}
						// If product not found by id, try lookup by SKU (some inventories only have SKU)
						$skuCandidate = $items[$idx]['product_sku'] ?? ($item['product_sku'] ?? '');
						if ((empty($product) || empty($items[$idx]['product_name'])) && !empty($skuCandidate)) {
							try {
								$mProd = new MProduct();
								$bySku = $mProd->getProductBySKU($skuCandidate);
								if ($bySku) {
									$items[$idx]['product_name'] = $bySku['product_name'] ?? ($bySku['name'] ?? ($items[$idx]['product_name'] ?? ''));
									$items[$idx]['product_sku'] = $bySku['sku'] ?? ($items[$idx]['product_sku'] ?? $skuCandidate);
									// If we found product id via SKU, set it for client detail lookups
									if (!empty($bySku['_id'])) {
										$items[$idx]['product_id'] = $bySku['_id'];
									}
								}
							} catch (Throwable $e) {
								// ignore
							}
						}
						// Convert lastTime to human-readable string to avoid JSON object display
						if (isset($items[$idx]['lastTime'])) {
							$items[$idx]['lastTime'] = fmt_dt($items[$idx]['lastTime']);
						}
					}
		
					echo json_encode(['ok' => true, 'data' => $items, 'warehouse_id' => $w, 'count' => count($items)]);
	} catch (Throwable $e) {
		echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
	}
	exit;
}
?>

<style>
  /* MODERN ADMIN DASHBOARD STYLES */
  :root {
    --bg: #f8fafc;
    --card: #ffffff;
    --muted: #64748b;
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --accent: #10b981;
    --accent-dark: #059669;
    --danger: #ef4444;
    --warning: #f59e0b;
    --border: #e2e8f0;
    --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
  }

  * { box-sizing: border-box; }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--bg);
    color: #1e293b;
    line-height: 1.6;
    margin: 0;
    padding: 0;
  }

  .inv-container {
    background: var(--card);
    border-radius: 16px;
    padding: 28px;
    box-shadow: var(--shadow);
    max-width: 1400px;
    margin: 24px auto;
    border: 1px solid var(--border);
  }

  .inv-header {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border);
  }

  .inv-title {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.5px;
  }

  /* Bộ lọc */
  .inv-filters {
    width: 100%;
  }

  .inv-filters form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .inv-filters input,
  .inv-filters select {
    padding: 10px 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    transition: all 0.2s ease;
    font-family: inherit;
  }
  
  .inv-filters input:focus,
  .inv-filters select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .inv-filters label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
    font-size: 14px;
    color: #334155;
  }

  /* Nút */
  .inv-btn {
    background: var(--primary);
    color: #fff;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  
  .inv-btn:hover {
    background: var(--primary-dark);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
    transform: translateY(-1px);
  }
  
  .inv-btn.secondary {
    background: #6b7280;
    box-shadow: 0 2px 4px rgba(107, 114, 128, 0.2);
  }
  
  .inv-btn.secondary:hover {
    background: #4b5563;
    box-shadow: 0 4px 8px rgba(107, 114, 128, 0.3);
  }
  
  .inv-btn.ghost {
    background: transparent;
    color: var(--primary);
    box-shadow: none;
    border: 1.5px solid var(--primary);
    padding: 6px 12px;
  }
  
  .inv-btn.ghost:hover {
    background: #eff6ff;
    transform: translateY(-1px);
  }

  /* Bảng */
  .inv-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow);
  }

  .inv-table th, 
  .inv-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    text-align: center;
    font-size: 14px;
  }

  .inv-table th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 600;
    color: #1e293b;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
  }

  .inv-table tbody tr {
    transition: all 0.2s ease;
  }

  .inv-table tbody tr:hover {
    background: #f8fafc;
    transform: scale(1.001);
  }

  .qty-pos { 
    color: var(--accent); 
    font-weight: 700;
    font-size: 15px;
  }
  
  .qty-neg { 
    color: var(--danger); 
    font-weight: 700;
    font-size: 15px;
  }

  /* Modal */
  .inv-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    backdrop-filter: blur(4px);
    overflow-y: auto;
    background: rgba(0, 0, 0, 0.5);
  }
  
  .inv-modal .overlay {
    position: absolute;
    inset: 0;
    animation: fadeIn 0.3s ease;
  }
  
  .inv-modal .content {
    position: relative;
    z-index: 1001;
    max-width: 900px;
    margin: 60px auto;
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
    border: 1px solid var(--border);
  }
  
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  @keyframes modalSlideIn {
    from { 
      opacity: 0; 
      transform: translateY(-30px) scale(0.95); 
    }
    to { 
      opacity: 1; 
      transform: translateY(0) scale(1); 
    }
  }

  .inv-modal table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow);
  }
  
  .inv-modal th, 
  .inv-modal td {
    padding: 12px 14px;
    border: 1px solid var(--border);
    text-align: center;
    font-size: 14px;
  }
  
  .inv-modal th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid var(--border);
  }
  
  .inv-modal tbody tr:hover {
    background: #f8fafc;
  }

  /* Phân trang */
  .inv-pagination {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 24px;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 1px solid var(--border);
  }

  .inv-pagination span {
    font-size: 14px;
    color: #475569;
    font-weight: 500;
  }

  .inv-page-link {
    padding: 8px 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    text-decoration: none;
    color: #334155;
    transition: all 0.2s ease;
    font-weight: 500;
    min-width: 40px;
    text-align: center;
  }
  
  .inv-page-link:hover {
    background: #f1f5f9;
    border-color: var(--primary);
    transform: translateY(-1px);
  }
  
  .inv-page-link.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
  }

  /* Responsive */
  @media (max-width: 768px) {
    .inv-container {
      padding: 16px;
      margin: 12px;
    }
    
    .inv-header {
      flex-direction: column;
      align-items: stretch;
    }
    
    .inv-filters form {
      flex-direction: column;
      align-items: stretch;
    }
    
    .inv-filters input,
    .inv-filters select {
      width: 100%;
    }
    
    .inv-table th, 
    .inv-table td {
      font-size: 13px;
      padding: 10px 8px;
    }
    
    .inv-title {
      font-size: 24px;
    }
  }
</style>


<div class="inv-container">
	<div class="inv-header">
		<h2 class="inv-title"><i class="fas fa-boxes"></i>  Tồn kho</h2>
		<div class="inv-filters">
			<form method="get" action="index.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
						<input type="hidden" name="page" value="inventory" />
				<input type="text" name="q" placeholder="Tìm kiếm (phiếu/SP/ô kho)" value="<?=h($q)?>" />
				
				<?php // Warehouse selector: central users can change, branch users see their own (disabled) ?>
				<?php
					$user_warehouse_name = '';
					foreach ($allWarehouses as $wh) {
						if (!empty($wh['warehouse_id']) && $wh['warehouse_id'] === $user_warehouse_id) { $user_warehouse_name = $wh['warehouse_name'] ?? $wh['warehouse_id']; break; }
					}
				?>
				<select name="warehouse_id" style="min-width:220px;" <?= $is_central_warehouse ? '' : 'disabled' ?>> 
					<?php if ($is_central_warehouse): ?>
						<option value="">-- Tất cả kho --</option>
					<?php endif; ?>
					<?php foreach ($allWarehouses as $wh): ?>
						<?php $wid = $wh['warehouse_id'] ?? ''; ?>
						<option value="<?=h($wid)?>" <?=($warehouse_id === $wid) ? 'selected' : ''?>>
							<?=h($wh['warehouse_name'] ?? $wid)?>
						</option>
					<?php endforeach; ?>
				</select>
				<!-- <?php if (!$is_central_warehouse): ?>
					<div style="margin-top:6px; color:#475569; font-size:13px;">Bạn đang ở: <strong><?=h($user_warehouse_name ?: $user_warehouse_id)?></strong>. Chỉ kho tổng KHO_TONG_01 mới có quyền xem tất cả kho.</div>
				<?php endif; ?> -->
				
				<label>Từ: <input type="date" name="from" value="<?=h($from)?>"></label>
				<label>Đến: <input type="date" name="to" value="<?=h($to)?>"></label>
				<select name="limit">
					<?php foreach ([10,20,50,100] as $opt) { ?>
						<option value="<?=$opt?>" <?=$limit==$opt?'selected':''?>><?=$opt?> / trang</option>
					<?php } ?>
				</select>
								<select name="view" title="Chế độ hiển thị">
							<option value="grouped" <?=$view==='grouped'?'selected':''?>>Theo sản phẩm</option>
							<option value="list" <?=$view==='list'?'selected':''?>>Theo giao dịch</option>
						</select>
				<button class="inv-btn" type="submit">Lọc</button>
				<!-- <a class="inv-btn secondary" href="index.php?page=manage"> Quay lại</a> -->
			</form>
		</div>
	</div>

	<!-- AJAX-rendered inventory container (updated when warehouse select changes) -->
	<div id="ajaxInventoryContainer" style="margin-top:18px"></div>

			<?php if ($view === 'grouped') { ?>
				<table class="inv-table">
					<thead>
					<tr>
						<th>SKU</th>
						<th>Sản phẩm</th>
						<th>Tổng SL (cái)</th>
						<th>Nhập gần nhất</th>
						<th>Chi tiết</th>
					</tr>
					</thead>
					<tbody>
					<?php if (!empty($items)) { foreach ($items as $g) {
					$pid = $g['product_id'] ?? '';
					$pidStr = id_str($pid);
						$pinfo = null;
						if (!empty($pid)) { $pinfo = $cProduct->getProductById($pid); }
						
						// Ưu tiên lấy SKU từ thông tin sản phẩm, sau đó mới từ dữ liệu inventory
						$sku = ($pinfo['sku'] ?? ($pinfo['product_sku'] ?? ($g['product_sku'] ?? '')));
						
						if (!$pinfo && !empty($sku)) {
							// best-effort: search by SKU from all products list (optional)
						}
						$pname = ($pinfo['product_name'] ?? ($g['product_name'] ?? ''));
						// Last import time based on inbound transactions (qty > 0)
						$keyForImport = !empty($pidStr) ? $pidStr : ($sku ?? '');
						$lastStr = last_import_str_for($cInventory, $cReceipt, $keyForImport, $from, $to);
						$totalQty = isset($g['totalQty']) ? (float)$g['totalQty'] : 0;
					?>
						<tr>
										<td><?=h($sku)?></td>
										<td>
														<?php
														$linkParams = ['view' => 'list', 'q' => ''];
												if (!empty($pid)) { $linkParams['product_id'] = $pid; }
												elseif (!empty($sku)) { $linkParams['product_sku'] = $sku; }
											?>
											<a class="inv-btn ghost" href="<?=h(buildUrl($linkParams))?>" title="Xem chi tiết giao dịch"><?=h($pname)?></a>
										</td>
							<td class="<?=($totalQty<0?'qty-neg':'qty-pos')?>"><?=number_format($totalQty, 0, ',', '.')?></td>
							<td><?=h($lastStr)?></td>
										<td>
													<?php
														$lp = ['view' => 'list', 'q' => ''];
														if (!empty($pidStr)) { $lp['product_id'] = $pidStr; }
														elseif (!empty($sku)) { $lp['product_sku'] = $sku; }
													?>
													<button type="button" class="inv-btn ghost js-show-details" data-product-id="<?=h($pidStr)?>" data-product-sku="<?=h($sku)?>">Xem</button>
										</td>
						</tr>
					<?php } } else { ?>
						<tr><td colspan="5">Không có sản phẩm phù hợp.</td></tr>
					<?php } ?>
					</tbody>
				</table>
			<?php } else { ?>
				<table class="inv-table">
					<thead>
					<tr>
						<th>Thời gian</th>
						<th>Kho</th>
						<th>Khu/Kệ/Ô</th>
						<th>Mã ô</th>
						<th>Phiếu</th>
						<th>SKU</th>
						<th>Sản phẩm</th>
						<th>Số lượng (cái)</th>
						<th>Ghi chú</th>
					</tr>
					</thead>
					<tbody>
					<?php if (!empty($items)) { foreach ($items as $it) { 
						$dt = isset($it['received_at']) ? fmt_dt($it['received_at']) : (isset($it['created_at'])?fmt_dt($it['created_at']):'');
						$wh = $it['warehouse_name'] ?? ($it['warehouse_id'] ?? '');
						$zone = $it['zone_id'] ?? '';
						$rack = $it['rack_id'] ?? '';
						$bin = $it['bin_id'] ?? '';
						// Build bin_code from zone-rack-bin if not provided
						$binCode = $it['bin_code'] ?? '';
						if (empty($binCode) && !empty($zone) && !empty($rack) && !empty($bin)) {
							$binCode = $zone . '-' . $rack . '-' . $bin;
						}
						$receipt = $it['receipt_code'] ?? ($it['receipt_id'] ?? '');
						$sku = $it['product_sku'] ?? ($it['product_id'] ?? '');
						// Ensure product name shows: enrich via product id if missing
						$pname = $it['product_name'] ?? '';
						if ($pname === '' && !empty($it['product_id'])) {
							$pi = $cProduct->getProductById($it['product_id']);
							if ($pi && !empty($pi['product_name'])) { $pname = $pi['product_name']; }
						}
						$qty = isset($it['qty']) ? (float)$it['qty'] : 0;
						$note = $it['note'] ?? '';
						$qtyClass = $qty < 0 ? 'qty-neg' : 'qty-pos';
					?>
						<tr>
							<td><?=h($dt)?></td>
							<td><?=h($wh)?></td>
							<td><?=h(trim($zone."/".$rack."/".$bin, '/'))?></td>
							<td><?=h($binCode)?></td>
							<td><?=h($receipt)?></td>
							<td><?=h($sku)?></td>
							<td>
								<?php
									$linkParams = ['view' => 'list', 'q' => ''];
									if (!empty($it['product_id'])) { $linkParams['product_id'] = $it['product_id']; }
									elseif (!empty($sku)) { $linkParams['product_sku'] = $sku; }
								?>
								<a class="inv-btn ghost" href="<?=h(buildUrl($linkParams))?>" title="Xem chi tiết giao dịch"><?=h($pname)?></a>
							</td>
							<td class="<?=$qtyClass?>"><?=number_format($qty, 0, ',', '.')?></td>
							<td><?=h($note)?></td>
						</tr>
					<?php } } else { ?>
						<tr><td colspan="9">Chưa có dữ liệu tồn kho.</td></tr>
					<?php } ?>
					</tbody>
				</table>
			<?php } ?>

			<!-- Modal: vị trí theo ô cho 1 sản phẩm (hiển thị cho cả 2 chế độ) -->
			<div class="inv-modal" id="binModal">
				<div class="overlay"></div>
				<div class="content" style="max-width: 900px;">
					<div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
						<h3 style="margin:0; display:flex; align-items:center; gap:8px; color:#1e293b;">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
								<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
								<line x1="12" y1="22.08" x2="12" y2="12"></line>
							</svg>
							Vị trí lưu trữ sản phẩm
						</h3>
						<button id="binModalClose" class="inv-btn secondary" type="button" style="padding:6px 12px;">✕ Đóng</button>
					</div>
					<div id="binModalBody" style="margin-top:16px;">
						<table style="width:100%; border-collapse:collapse;">
							<thead>
								<tr style="background:#f8fafc;">
									<th style="padding:12px; border:1px solid #e5e7eb; font-weight:600; color:#475569;"> Kho</th>
									<th style="padding:12px; border:1px solid #e5e7eb; font-weight:600; color:#475569;"> Vị trí (Zone/Rack/Bin)</th>
									<th style="padding:12px; border:1px solid #e5e7eb; font-weight:600; color:#475569;"> Số lượng</th>
									<th style="padding:12px; border:1px solid #e5e7eb; font-weight:600; color:#475569;"> Ngày nhập</th>
								</tr>
							</thead>
							<tbody id="binModalRows"></tbody>
						</table>
					</div>
				</div>
		</div>

		<script>
		// Use server-computed API path
		const API_INVENTORY_PROCESS = '<?= $processPath ?>';
		const API_INVENTORY_PAGE = '<?= $pagePath ?>';
		
		(function(){
			const binModal = document.getElementById('binModal');
			const binModalClose = document.getElementById('binModalClose');
			const binModalRows = document.getElementById('binModalRows');
				if (!binModal || !binModalClose || !binModalRows) return;
				function hideBinModal(){ binModal.style.display = 'none'; binModalRows.innerHTML=''; }
				function showBinModal(){ binModal.style.display = 'block'; }
				binModalClose.addEventListener('click', hideBinModal);
				binModal.querySelector('.overlay').addEventListener('click', hideBinModal);

				function getFilter(name){
					const el = document.querySelector(`[name="${name}"]`);
					return el ? el.value : '';
				}

				async function handleShowDetails(btn){
					const pid = btn.dataset.productId || '';
					const sku = btn.dataset.productSku || '';
					const key = pid || sku;
					if (!key) { alert('Không xác định được sản phẩm.'); return; }
					try {
						const params = new URLSearchParams();
						params.set('action', 'bins');
						params.set('key', key);
					const from = getFilter('from');
					const to = getFilter('to');
				if (from) params.set('from', from);
				if (to) params.set('to', to);
				// Use server-computed API path
				const url = API_INVENTORY_PROCESS + '?' + params.toString();
				console.log('Fetching:', url); // Debug
					const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
						console.log('Response status:', res.status); // Debug
						if (!res.ok) {
							const errorText = await res.text();
							console.error('Error response:', errorText);
							throw new Error('HTTP ' + res.status + ': ' + errorText);
						}
						const payload = await res.json();
						console.log('Payload:', payload); // Debug
						const data = payload && payload.ok ? (payload.data || []) : [];
						binModalRows.innerHTML = '';
						if (Array.isArray(data) && data.length) {
							// Sort by zone, rack, bin for better display
							data.sort((a, b) => {
								const za = a.zone_id || '';
								const zb = b.zone_id || '';
								if (za !== zb) return za.localeCompare(zb);
								const ra = a.rack_id || '';
								const rb = b.rack_id || '';
								if (ra !== rb) return ra.localeCompare(rb);
								return (a.bin_id || '').localeCompare(b.bin_id || '');
							});
							
							let totalQty = 0;
							data.forEach(row => {
								const tr = document.createElement('tr');
								tr.style.transition = 'background 0.2s';
								tr.onmouseover = () => tr.style.background = '#f8fafc';
								tr.onmouseout = () => tr.style.background = '';
								
								const zone = row.zone_id || '';
								const rack = row.rack_id || '';
								const bin = row.bin_id || '';
								const location = [zone, rack, bin].filter(x => x).join('/');
								
								const qty = parseFloat(row.qty || 0);
								totalQty += qty;
								
								// Format date
								let dateStr = row.importDate || row.lastTime || '';
								if (dateStr) {
									// Convert from YYYY-MM-DD HH:mm:ss to DD/MM/YYYY HH:mm
									const match = dateStr.match(/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}:\d{2})/);
									if (match) {
										dateStr = `${match[3]}/${match[2]}/${match[1]} ${match[4]}`;
									}
								}
								
								tr.innerHTML = `
									<td style="padding:10px; border:1px solid #e5e7eb; text-align:center;">${row.warehouse_id || '-'}</td>
									<td style="padding:10px; border:1px solid #e5e7eb; text-align:center; font-family:monospace; font-weight:500; color:#1e40af;">
										${location || '-'}
									</td>
									<td style="padding:10px; border:1px solid #e5e7eb; text-align:center; font-weight:700; color:#059669; font-size:15px;">
										${qty.toLocaleString('vi-VN')} <span style="font-weight:400; color:#6b7280; font-size:13px;">cái</span>
									</td>
									<td style="padding:10px; border:1px solid #e5e7eb; text-align:center; color:#64748b; font-size:13px;">
										${dateStr || '<span style="color:#94a3b8;">Chưa có</span>'}
									</td>
								`;
								binModalRows.appendChild(tr);
							});
							
							// Add total row
							const totalRow = document.createElement('tr');
							totalRow.style.background = '#f1f5f9';
							totalRow.style.fontWeight = '700';
							totalRow.innerHTML = `
								<td colspan="2" style="padding:12px; border:1px solid #e5e7eb; text-align:right; color:#1e293b;">
									<strong> Tổng cộng:</strong>
								</td>
								<td style="padding:12px; border:1px solid #e5e7eb; text-align:center; color:#059669; font-size:16px;">
									${totalQty.toLocaleString('vi-VN')} <span style="font-weight:400; color:#6b7280; font-size:14px;">cái</span>
								</td>
								<td style="padding:12px; border:1px solid #e5e7eb; text-align:center; color:#64748b;">
									${data.length} <span style="font-weight:400;">vị trí</span>
								</td>
							`;
							binModalRows.appendChild(totalRow);
						} else {
							const tr = document.createElement('tr');
							tr.innerHTML = `
								<td colspan="5" style="padding:20px; text-align:center; color:#94a3b8; border:1px solid #e5e7eb;">
									<div style="font-size:48px; margin-bottom:8px;">📭</div>
									<div style="font-size:15px; font-weight:500; color:#64748b;">Không có sản phẩm trong kho</div>
								</td>
							`;
							binModalRows.appendChild(tr);
						}
						showBinModal();
					} catch (err) {
						console.error('Load bins error', err);
						alert('Không tải được vị trí ô. Vui lòng thử lại.');
					}
				}

				// Robust delegated click handler
				document.addEventListener('click', (e) => {
					const btn = e.target.closest('.js-show-details');
					if (!btn) return;
					e.preventDefault();
					handleShowDetails(btn);
				}, true);
			})();

			// --- Warehouse selector dynamic load ---
			(function(){
				const ajaxContainer = document.getElementById('ajaxInventoryContainer');
				const whSelect = document.querySelector('select[name="warehouse_id"]');
				const fromInput = document.querySelector('input[name="from"]');
				const toInput = document.querySelector('input[name="to"]');

				function buildUrlForGrouped(warehouseId){
					const params = new URLSearchParams();
					params.set('ajax','grouped');
					if (warehouseId !== undefined && warehouseId !== null) params.set('warehouse_id', warehouseId);
					const from = fromInput ? fromInput.value : '';
					const to = toInput ? toInput.value : '';
					if (from) params.set('from', from);
					if (to) params.set('to', to);
					return API_INVENTORY_PAGE + '?' + params.toString();
				}

			async function loadGrouped(warehouseId){
				if (!ajaxContainer) return;
				ajaxContainer.innerHTML = '<div style="padding:12px;color:#64748b;">Đang tải tồn kho…</div>';
				// Hide server-rendered tables
				const serverTables = document.querySelectorAll('.inv-table');
				serverTables.forEach(t => t.style.display = 'none');
				try{
						const url = buildUrlForGrouped(warehouseId);
						const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
						if (!res.ok) throw new Error('HTTP ' + res.status);
						const payload = await res.json();
						if (!payload.ok) {
							ajaxContainer.innerHTML = '<div class="alert alert-danger">Lỗi tải dữ liệu</div>';
							return;
						}
						const items = payload.data || [];
						if (!items.length) {
							const usedWarehouse = payload.warehouse_id || warehouseId || '';
							const msg = usedWarehouse ? 'Không tồn tại tồn kho cho kho được chọn.' : 'Không có sản phẩm phù hợp.';
							ajaxContainer.innerHTML = `<div style="padding:12px;color:#64748b;">${msg}</div>`;
							return;
						}
					let html = '<table class="inv-table" style="margin-top:6px;"><thead><tr><th>SKU</th><th>Sản phẩm</th><th>Tổng SL (cái)</th><th>Nhập gần nhất</th><th>Chi tiết</th></tr></thead><tbody>';
					for (const g of items) {
						// Extract SKU - prioritize product_sku from enriched data
						const sku = g.product_sku || '';
						const pid = g.product_id || '';
						// Extract product_id string from ObjectId if needed
						const pidStr = (typeof pid === 'object' && pid && pid['$oid']) ? pid['$oid'] : (pid || '');
						// Product name - handle both string and object formats
						let pname = '';
						if (typeof g.product_name === 'string') {
							pname = g.product_name;
						} else if (g.product_name && typeof g.product_name === 'object') {
							pname = g.product_name.name || g.product_name.product_name || JSON.stringify(g.product_name);
						}
						if (!pname) pname = sku || pidStr || '(Chưa có tên)';
						
						const totalQty = (g.totalQty || 0).toLocaleString('vi-VN');
						const last = g.lastTime || '';
						// Display SKU in first column, product name in second
						html += `<tr><td><strong>${sku || '-'}</strong></td><td>${pname}</td><td style="color:#059669;font-weight:700">${totalQty}</td><td>${last}</td><td><button type="button" class="inv-btn ghost js-show-details" data-product-id="${pidStr}" data-product-sku="${sku}">Xem</button></td></tr>`;
					}
					html += '</tbody></table>';
					ajaxContainer.innerHTML = html;
				} catch (err){
				console.error('Load grouped error', err);
				ajaxContainer.innerHTML = '<div class="alert alert-danger">Lỗi khi tải tồn kho</div>';
				// Show server tables back on error
				const serverTables = document.querySelectorAll('.inv-table');
				serverTables.forEach(t => t.style.display = '');
			}
		}
		
		if (whSelect) {
			whSelect.addEventListener('change', function(){
				// For branch users, always load their own warehouse regardless of selection
				const isCentral = <?= $is_central_warehouse ? 'true' : 'false' ?>;
				const userWarehouse = '<?= $user_warehouse_id ?>';
				const selectedWarehouse = isCentral ? this.value : userWarehouse;
				loadGrouped(selectedWarehouse);
			});
			// Load initial for current selection
			const isCentral = <?= $is_central_warehouse ? 'true' : 'false' ?>;
			const userWarehouse = '<?= $user_warehouse_id ?>';
			const initialWarehouse = isCentral ? whSelect.value : userWarehouse;
			loadGrouped(initialWarehouse);
		}				if (fromInput) fromInput.addEventListener('change', ()=> whSelect && loadGrouped(whSelect.value));
				if (toInput) toInput.addEventListener('change', ()=> whSelect && loadGrouped(whSelect.value));
			})();
			</script>

	<div class="inv-pagination">
		<span>Tổng: <?=number_format($total)?></span>
		<?php if ($pages > 1) { ?>
					<a class="inv-page-link" href="<?=buildUrl(['p'=>1])?>">« Đầu</a>
			<?php
						$start = max(1, $p - 2);
						$end = min($pages, $p + 2);
				for ($i = $start; $i <= $end; $i++) {
							$active = $i == $p ? 'active' : '';
							echo '<a class=\"inv-page-link '.$active.'\" href=\"'.buildUrl(['p'=>$i]).'\">'.$i.'</a>';
				}
			?>
					<a class="inv-page-link" href="<?=buildUrl(['p'=>$pages])?>">Cuối »</a>
		<?php } ?>
	</div>
</div>
