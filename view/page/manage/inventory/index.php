<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . "/../../../../controller/cInventory.php");
include_once(__DIR__ . "/../../../../controller/cWarehouse.php");
include_once(__DIR__ . "/../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../controller/cReceipt.php");

$cInventory = new CInventory();
$cWarehouse = new CWarehouse();
$cProduct = new CProduct();
$cReceipt = new CReceipt();

// Read filters from GET
$q = $_GET['q'] ?? '';
$warehouse_id = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? ($_SESSION['warehouse_id'] ?? '');
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
			'from' => $from,
			'to' => $to,
			'page' => $p,
			'limit' => $limit
		]);
	} else {
		$result = $cInventory->getInventoryList([
			'q' => $q,
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
?>

<style>
  body {
    font-family: 'Segoe UI', Roboto, sans-serif;
    background: #f3f4f6;
  }

  .inv-container {
    max-width: 1300px;
    margin: 20px auto;
    background: #ffffff;
    padding: 28px;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.07);
    transition: 0.3s ease;
  }

  .inv-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 20px;
  }

  .inv-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  /* Bộ lọc */
  .inv-filters form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .inv-filters input,
  .inv-filters select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    transition: 0.2s;
  }
  .inv-filters input:focus,
  .inv-filters select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 2px rgba(37,99,235,0.2);
  }

  /* Nút */
  .inv-btn {
    background: #2563eb;
    color: #fff;
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: 0.2s ease;
  }
  .inv-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
  }
  .inv-btn.secondary {
    background: #6b7280;
  }
  .inv-btn.secondary:hover {
    background: #4b5563;
  }
  .inv-btn.ghost {
    background: #f9fafb;
    color: #2563eb;
    border: 1px solid #c7d2fe;
  }
  .inv-btn.ghost:hover {
    background: #eff6ff;
  }

  /* Bảng */
  .inv-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
    border-radius: 10px;
    overflow: hidden;
  }

  .inv-table th, .inv-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: center;
    font-size: 14px;
  }

  .inv-table th {
    background: #f1f5f9;
    font-weight: 600;
    color: #374151;
  }

  .inv-table tr:hover {
    background: #f9fafb;
  }

  .qty-pos { color: #059669; font-weight: 600; }
  .qty-neg { color: #dc2626; font-weight: 600; }

  /* Modal */
  .inv-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1000;
    backdrop-filter: blur(2px);
  }
  .inv-modal .overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
  }
  .inv-modal .content {
    position: relative;
    z-index: 1001;
    max-width: 800px;
    margin: 100px auto;
    background: #fff;
    border-radius: 14px;
    padding: 20px 24px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    animation: modalFadeIn 0.25s ease;
  }
  @keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .inv-modal table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
  }
  .inv-modal th, .inv-modal td {
    padding: 8px 10px;
    border: 1px solid #e5e7eb;
    text-align: center;
    font-size: 14px;
  }
  .inv-modal th {
    background: #f9fafb;
  }

  /* Phân trang */
  .inv-pagination {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    align-items: center;
    margin-top: 18px;
    flex-wrap: wrap;
  }

  .inv-pagination span {
    font-size: 14px;
    color: #374151;
  }

  .inv-page-link {
    padding: 6px 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    text-decoration: none;
    color: #111827;
    transition: 0.2s;
  }
  .inv-page-link:hover {
    background: #f3f4f6;
  }
  .inv-page-link.active {
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .inv-filters form {
      flex-direction: column;
      align-items: stretch;
    }
    .inv-table th, .inv-table td {
      font-size: 13px;
      padding: 8px;
    }
    .inv-container {
      padding: 16px;
    }
  }
</style>


<div class="inv-container">
	<div class="inv-header">
		<h2 class="inv-title">📦 Lịch sử tồn kho (Inventory)</h2>
		<div class="inv-filters">
			<form method="get" action="index.php" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
						<input type="hidden" name="page" value="inventory" />
				<input type="text" name="q" placeholder="Tìm kiếm (phiếu/SP/ô kho)" value="<?=h($q)?>" />
						<input type="text" value="<?=h($warehouse_id)?>" readonly style="background:#f3f4f6; color:#111827; font-weight:600;" title="Kho của bạn" />
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
				<a class="inv-btn" href="index.php?page=inventory/createInventory_sheet" style="background:#059669;" title="Tạo phiếu kiểm kê">📋 Tạo phiếu kiểm kê</a>
				<a class="inv-btn" href="index.php?page=inventory/inventory_sheets" style="background:#7c3aed;" title="Quản lý phiếu kiểm kê">📄 Quản lý phiếu</a>
				<a class="inv-btn secondary" href="index.php?page=manage"> Quay lại</a>
			</form>
		</div>
	</div>

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
					$sku = $g['product_sku'] ?? '';
					$pid = $g['product_id'] ?? '';
					$pidStr = id_str($pid);
						$pinfo = null;
						if (!empty($pid)) { $pinfo = $cProduct->getProductById($pid); }
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
				<div class="content">
					<div style="display:flex; justify-content:space-between; align-items:center;">
						<h3 style="margin:0;">Vị trí lưu trữ theo ô</h3>
						<button id="binModalClose" class="inv-btn secondary" type="button">Đóng</button>
					</div>
					<div id="binModalBody" style="margin-top:10px;">
						<table>
							<thead>
								<tr>
									<th>Kho</th>
									<th>Khu/Kệ/Ô</th>
									<th>Mã ô</th>
									<th>Số lượng (cái)</th>
									<th>Ngày nhập hàng</th>
								</tr>
							</thead>
							<tbody id="binModalRows"></tbody>
						</table>
					</div>
				</div>
			</div>

			<script>
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
					const url = '/KLTN/view/page/manage/inventory/process.php?' + params.toString();
					const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
						if (!res.ok) throw new Error('HTTP ' + res.status);
						const payload = await res.json();
						const data = payload && payload.ok ? (payload.data || []) : [];
						binModalRows.innerHTML = '';
						if (Array.isArray(data) && data.length) {
							data.forEach(row => {
								const tr = document.createElement('tr');
								const zr = (row.zone_id||'') + '/' + (row.rack_id||'') + '/' + (row.bin_id||'');
								tr.innerHTML = `
									<td>${row.warehouse_id||''}</td>
									<td>${zr.replace(/^\/+|\/+$/g,'')}</td>
									<td>${row.bin_code||''}</td>
									<td style="font-weight:600; color:#065f46;">${(row.qty||0).toLocaleString('vi-VN')}</td>
									<td>${row.importDate || row.lastTime || ''}</td>
								`;
								binModalRows.appendChild(tr);
							});
						} else {
							const tr = document.createElement('tr');
							tr.innerHTML = '<td colspan="5">Không có số lượng trong kho.</td>';
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
