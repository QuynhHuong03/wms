<?php
// Receipt locate page: left shows receipt info, right shows warehouse locations
// URL: receipts/locate/index.php?id=<transaction_id>

if (session_status() === PHP_SESSION_NONE) @session_start();

include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

$cReceipt = new CReceipt();
$cProduct = new CProduct();

$id = $_GET['id'] ?? '';
if (!$id) {
	echo '<div style="max-width:960px;margin:24px auto;padding:16px;border:1px solid #fecaca;background:#fff1f2;color:#991b1b;border-radius:10px">Thiếu mã phiếu (id).</div>';
	exit;
}

$receipt = $cReceipt->getReceiptById($id);
if (!$receipt) {
	echo '<div style="max-width:960px;margin:24px auto;padding:16px;border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:10px">Không tìm thấy phiếu với mã: '.htmlspecialchars($id).'</div>';
	exit;
}

function fmtDate($time) {
	if (!$time) return '';
	if ($time instanceof MongoDB\BSON\UTCDateTime) {
		return date('d/m/Y H:i', $time->toDateTime()->getTimestamp());
	}
	return date('d/m/Y H:i', strtotime($time));
}

$created_date  = fmtDate($receipt['created_at'] ?? '');
$approved_date = fmtDate($receipt['approved_at'] ?? '');

$status = (int)($receipt['status'] ?? 0);
$statusText  = ['Chờ duyệt', 'Đã duyệt', 'Từ chối', 'Đã hoàn tất'];
$statusClass = ['pending', 'approved', 'rejected', 'located'];
$statusLabel = $statusText[$status] ?? 'N/A';
$statusCls   = $statusClass[$status] ?? 'pending';

$warehouseId = $receipt['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? '');

// Build product name map for allocations table
$productNameMap = [];
if (!empty($receipt['details'])) {
	foreach ($receipt['details'] as $it) {
		$pid = $it['product_id'] ?? ($it['sku'] ?? '');
		if ($pid) $productNameMap[$pid] = $it['product_name'] ?? ($it['name'] ?? '');
	}
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<title>Xếp hàng cho phiếu <?= htmlspecialchars($receipt['transaction_id']) ?></title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
		<style>
		:root{--bg:#f6f8fa;--card:#fff;--muted:#6b7280;--border:#e5e7eb;--primary:#2563eb;--ok:#16a34a;--warn:#f59e0b;--danger:#dc2626}
		*{box-sizing:border-box}
		body{margin:0;background:var(--bg);font-family:'Segoe UI',Tahoma,sans-serif;color:#111827}
		.page{max-width:1200px;margin:24px auto;padding:0 16px}
		.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
		.header h2{margin:0;font-size:22px}
		.grid{display:grid;grid-template-columns:380px 1fr;gap:16px}
		@media(max-width:980px){.grid{grid-template-columns:1fr}}
		.card{background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
		.card .section{padding:16px 18px}
		.meta p{margin:6px 0;font-size:14px}
		.meta .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px}
		@media(max-width:520px){.meta .grid{grid-template-columns:1fr}}
		.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;font-weight:600}
		.pending{background:#fff3cd;color:#856404}
		.approved{background:#d4edda;color:#155724}
		.rejected{background:#f8d7da;color:#721c24}
		.located{background:#cce5ff;color:#004085}
		.table{width:100%;border-collapse:collapse}
		.table th,.table td{border:1px solid var(--border);padding:8px 10px;text-align:center;font-size:13px}
		.table th{background:#f9fafb}
		.toolbar{display:flex;gap:8px;align-items:center}
		.btn{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:#fff;color:#111827;text-decoration:none;font-size:14px}
		.btn.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
		.btn:hover{opacity:.92}
		/* Right panel */
		.locations-header{display:flex;justify-content:space-between;align-items:center}
		#locationsPane{min-height:300px}
		#locationsPane .loading{padding:16px;color:var(--muted)}
		/* Modal basic */
		.modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;}
		.modal[aria-hidden="false"]{display:flex}
		.modal .backdrop{position:absolute;inset:0;background:rgba(0,0,0,.35)}
		.modal .panel{position:relative;background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px 18px;min-width:320px;max-width:520px;width:92%}
		.modal .actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
	</style>
		<script>
		// load locations HTML snippet for given warehouse
		async function loadLocations(warehouseId){
			const pane = document.getElementById('locationsPane');
			if(!warehouseId){
				pane.innerHTML = '<div style="padding:12px;color:#991b1b;background:#fee2e2;border:1px solid #fecaca;border-radius:10px">Không xác định được kho.</div>';
				return;
			}
			pane.innerHTML = '<div class="loading"><i class="fa fa-spinner fa-spin"></i> Đang tải sơ đồ kho...</div>';
			try{
					// Path is relative to manage/index.php (router): manage/locations/ajax_get_locations.php
					const res = await fetch('locations/ajax_get_locations.php?warehouse_id=' + encodeURIComponent(warehouseId), {
					headers: {'X-Requested-With':'fetch'}
				});
				const html = await res.text();
				pane.innerHTML = html;
			}catch(err){
				pane.innerHTML = '<div style="padding:12px;color:#991b1b;background:#fee2e2;border:1px solid #fecaca;border-radius:10px">Lỗi tải vị trí: ' + String(err) + '</div>';
			}
		}
		document.addEventListener('DOMContentLoaded', () => {
			loadLocations('<?= htmlspecialchars($warehouseId) ?>');

			// Compute and show remaining quantity per product using allocations from server (if any embedded later)
			const receiptAllocations = <?php echo json_encode($receipt['allocations'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
			if (Array.isArray(receiptAllocations) && receiptAllocations.length) {
				const sumByPid = {};
				receiptAllocations.forEach(a => {
					if (!a || !a.product_id) return;
					sumByPid[a.product_id] = (sumByPid[a.product_id]||0) + (parseInt(a.qty||0,10));
				});
				// Update table cells labeled remaining-cell
				const rows = Array.from(document.querySelectorAll('#productsTable tbody tr'));
				rows.forEach(tr => {
					const pidCell = tr.querySelector('td');
					if (!pidCell) return;
					const pid = (pidCell.innerText||'').trim();
					if (!pid) return;
					// Prefer data-ordered-base attribute; fallback to converted column (index 4)
					let ordered = parseInt(tr.getAttribute('data-ordered-base')||'0',10) || 0;
					if (!ordered) {
						const convCell = tr.querySelectorAll('td')[4]; // Quy đổi (cái)
						ordered = convCell ? (parseInt(convCell.textContent.replace(/[^0-9]/g,''),10) || 0) : 0;
					}
					const allocated = sumByPid[pid] || 0;
					const remaining = Math.max(0, ordered - allocated);
					const remCell = tr.querySelector('.remaining-cell');
					if (remCell) remCell.textContent = remaining.toLocaleString('vi-VN');
				});
			}

			// Helper: toggle Complete button visibility
			function toggleCompleteButton(){
				const btn = document.getElementById('btnComplete');
				if (!btn) return;
				const remCells = Array.from(document.querySelectorAll('.remaining-cell'));
				if (!remCells.length) { btn.style.display = 'none'; return; }
				const allZero = remCells.every(td => (parseInt((td.textContent||'').replace(/[^0-9]/g,''),10) || 0) === 0);
				btn.style.display = allZero ? '' : 'none';
			}
			// Helper: if any remaining > 0, ensure badge is not 'Đã hoàn tất'
			function updateBadgeByRemaining(){
				const remCells = Array.from(document.querySelectorAll('#productsTable .remaining-cell'));
				if (!remCells.length) return;
				const anyNonZero = remCells.some(td => (parseInt((td.textContent||'').replace(/[^0-9]/g,''),10) || 0) > 0);
				const badge = document.querySelector('.badge');
				if (badge && anyNonZero) { badge.className = 'badge approved'; badge.textContent = 'Đã duyệt'; }
			}
			// Initial check after DOM paint
			setTimeout(toggleCompleteButton, 0);
			
			// Ẩn nút Sửa/Xóa nếu phiếu đã hoàn tất khi load trang
			const receiptStatus = <?= (int)$status ?>;
			if (receiptStatus === 3) {
				document.querySelectorAll('#allocTable [data-edit], #allocTable [data-del]').forEach(btn => btn.style.display = 'none');
				// Thêm thông báo phiếu đã hoàn tất
				const allocSection = document.querySelector('#allocTable').closest('.section');
				if (allocSection && !document.getElementById('completedNotice')) {
					const notice = document.createElement('div');
					notice.id = 'completedNotice';
					notice.style.cssText = 'margin-top:12px;padding:10px 14px;background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;border-radius:8px;font-size:14px;display:flex;align-items:center;gap:8px';
					notice.innerHTML = '<i class="fa fa-check-circle"></i> <span>Phiếu này đã hoàn tất. Dữ liệu đã được ghi vào kho và không thể chỉnh sửa.</span>';
					allocSection.appendChild(notice);
				}
			}

			// Allocation modal workflow
			const modal = document.getElementById('allocModal');
			const modalForm = document.getElementById('allocForm');
			const modalClose = () => modal && modal.setAttribute('aria-hidden', 'true');
			const openModal = () => modal && modal.setAttribute('aria-hidden', 'false');
			const productSelect = document.getElementById('allocProduct');
			const qtyInput = document.getElementById('allocQty');
			const statusSelect = document.getElementById('allocStatus');
			const unitSelect = document.getElementById('allocUnit');
			let currentBinPayload = null;
			let currentCapLeftBase = 0; // capacity left in base units ('cái')
			let currentRemainingBase = 0; // remaining for selected product in base units

			function getSelectedFactor(){
				if (!unitSelect) return 1;
				const opt = unitSelect.options[unitSelect.selectedIndex];
				const f = parseInt(opt?.getAttribute('data-factor')||'1',10);
				return Number.isFinite(f) && f>0 ? f : 1;
			}

			function recalcQtyFromUnit(){
				const factor = getSelectedFactor();
				// Convert base quantities to selected unit, cap to capacity
				const byRemain = Math.floor(currentRemainingBase / factor);
				const byCap = Math.floor(currentCapLeftBase / factor);
				const val = Math.max(0, Math.min(byRemain, byCap || byRemain));
				qtyInput.value = String(val);
			}

			// Fill product options from left table
			(function fillProductOptions(){
				const rows = Array.from(document.querySelectorAll('#productsTable tbody tr'));
				productSelect.innerHTML = '';
				rows.forEach(tr => {
					const tds = tr.querySelectorAll('td');
					if (tds.length < 2) return;
					const pid = (tds[0].innerText||'').trim();
					const name = (tds[1].innerText||'').trim();
					if (!pid) return;
					const opt = document.createElement('option');
					opt.value = pid; opt.textContent = name ? `${name} (${pid})` : pid;
					productSelect.appendChild(opt);
				});
			})();

			// Helper to load unit options for selected product
			async function loadUnitsForProduct(productId){
				if (!unitSelect) return { baseUnit: '', factor: 1 };
				unitSelect.innerHTML = '';
				try{
					const res = await fetch('receipts/locate/process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'get_product_units', id:'<?= htmlspecialchars($receipt['transaction_id']) ?>', product_id: productId })});
					const data = await res.json();
					if (data && data.success) {
						const units = Array.isArray(data.units) ? data.units : [];
						units.forEach(u => {
							const opt = document.createElement('option');
							opt.value = u.unit; opt.textContent = `${u.unit}${u.factor && u.factor>1 ? ` (x${u.factor})` : ''}`;
							opt.setAttribute('data-factor', String(u.factor||1));
							unitSelect.appendChild(opt);
						});
						// Select base unit by default
						if (data.baseUnit) {
							const found = Array.from(unitSelect.options).find(o => (o.value||'').toLowerCase() === String(data.baseUnit).toLowerCase());
							if (found) unitSelect.value = found.value;
						}
						return { baseUnit: data.baseUnit || '', factor: 1 };
					}
				}catch(e){}
				return { baseUnit: '', factor: 1 };
			}

			// Open modal when clicking a bin
			const pane = document.getElementById('locationsPane');
			pane.addEventListener('click', async (ev) => {
				const el = ev.target.closest('.bin');
				if (!el) return;
				
				// Không cho xếp hàng nếu đã hoàn tất
				const receiptStatus = <?= (int)$status ?>;
				if (receiptStatus === 3) {
					alert('Phiếu đã hoàn tất, không thể xếp hàng nữa');
					return;
				}
				
				const z = el.getAttribute('data-zone');
				const r = el.getAttribute('data-rack');
				const b = el.getAttribute('data-bin');
				const code = el.getAttribute('data-code');
				const status = el.getAttribute('data-status') || 'empty';
				const qtyInBin = parseInt(el.getAttribute('data-quantity')||'0',10) || 0;
				const capInBin = parseInt(el.getAttribute('data-capacity')||'0',10) || 0;
				const prodInBin = el.getAttribute('data-product')||'';
				currentBinPayload = { zone_id: z, rack_id: r, bin_id: b, code, status };
				// Default quantity = quantity on selected product row if any, else 0
				const activeRow = document.querySelector('tr.row-product.active');
				let defaultQty = 0; let defaultPid = '';
				if (activeRow) {
					defaultQty = parseInt(activeRow.getAttribute('data-qty')||'0',10);
					const pidCell = activeRow.querySelector('td');
					defaultPid = pidCell ? (pidCell.innerText||'').trim() : '';
				}
				if (defaultPid) productSelect.value = defaultPid;
				// Load units for current product
				await loadUnitsForProduct(productSelect.value);
				// Set qty to remaining of selected product
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = (tr.querySelector('td')?.innerText||'').trim();
					return pid === (productSelect.value||'');
				});
				if (row) {
					const remCell = row.querySelector('.remaining-cell');
					currentRemainingBase = parseInt(remCell?.textContent.replace(/[^0-9]/g,'')||'0',10);
					// If bin has capacity, cap default by remaining capacity (both base units)
					currentCapLeftBase = capInBin > 0 ? Math.max(0, capInBin - qtyInBin) : currentRemainingBase;
					recalcQtyFromUnit();
				} else {
					qtyInput.value = defaultQty;
					currentRemainingBase = parseInt(qtyInput.value||'0',10);
					currentCapLeftBase = currentRemainingBase;
				}
				statusSelect.value = status;
				// Populate info box
				const info = document.getElementById('binInfo');
				if (info) {
					info.style.display = 'block';
					const parts = [];
					parts.push('Bin: ' + (code||b));
					if (prodInBin) parts.push('Sản phẩm: ' + prodInBin);
					parts.push('Đang có: ' + qtyInBin.toLocaleString('vi-VN'));
					if (capInBin>0) parts.push('Sức chứa: ' + capInBin.toLocaleString('vi-VN'));
					info.textContent = parts.join(' | ');
				}
				openModal();
			});

			// When switching product in modal, default qty to remaining for that product
			productSelect.addEventListener('change', async () => {
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = (tr.querySelector('td')?.innerText||'').trim();
					return pid === (productSelect.value||'');
				});
				if (row) {
					const remCell = row.querySelector('.remaining-cell');
					currentRemainingBase = parseInt(remCell?.textContent.replace(/[^0-9]/g,'')||'0',10);
					currentCapLeftBase = currentCapLeftBase || currentRemainingBase;
					await loadUnitsForProduct(productSelect.value);
					recalcQtyFromUnit();
				}
			});

			// When switching unit, adjust qty to selected unit
			unitSelect.addEventListener('change', () => {
				recalcQtyFromUnit();
			});

			// Submit allocation with validation against remaining quantity
			modalForm && modalForm.addEventListener('submit', async (e)=>{
				e.preventDefault();
				if (!currentBinPayload) return;
				const body = {
					action: 'allocate_to_bin',
					id: '<?= htmlspecialchars($receipt['transaction_id']) ?>',
					product_id: productSelect.value,
					qty: parseInt(qtyInput.value||'0',10),
					input_unit: unitSelect ? (unitSelect.value||'') : '',
					bin_status: statusSelect.value,
					...currentBinPayload
				};
				// Validate: qty <= remaining (base units). Convert entered qty to base using selected unit factor.
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = (tr.querySelector('td')?.innerText||'').trim();
					return pid === body.product_id;
				});
				if (row) {
					// Prefer base-ordered
					let qtyOrdered = parseInt(row.getAttribute('data-ordered-base')||'0',10) || 0;
					if (!qtyOrdered) qtyOrdered = parseInt(row.querySelectorAll('td')[4]?.textContent.replace(/[^0-9]/g,'')||'0',10);
					const remCell = row.querySelector('.remaining-cell');
					let remaining = qtyOrdered;
					if (remCell) remaining = parseInt(remCell.textContent.replace(/[^0-9]/g,'')||'0',10);
					const factor = getSelectedFactor();
					const baseQty = (parseInt(body.qty,10) || 0) * factor;
					if (baseQty > remaining) {
						alert('Số lượng phân bổ vượt quá số lượng chưa xếp (' + remaining + ').');
						return;
					}
				}
				try {
					const res = await fetch('receipts/locate/process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ ...body, warehouse_id: '<?= htmlspecialchars($warehouseId) ?>' })});
					const data = await res.json();
						if (data && data.success) {
							if ((body.qty||0) > 0) {
								// Update remaining in UI
								if (row) {
									const remCell = row.querySelector('.remaining-cell');
									if (remCell) {
										// Prefer server remaining for accuracy with unit conversion
										const next = (data && typeof data.remaining !== 'undefined') ? parseInt(data.remaining,10) : NaN;
										if (Number.isFinite(next)) {
											remCell.textContent = Math.max(0, next).toLocaleString('vi-VN');
										} else {
											const cur = parseInt(remCell.textContent.replace(/[^0-9]/g,'')||'0',10);
											const fallback = Math.max(0, cur - body.qty);
											remCell.textContent = fallback.toLocaleString('vi-VN');
										}
									}
								}
								// Re-check the Complete button
								toggleCompleteButton();
								// Append new allocation row
								(function appendAllocRow(){
									const tbody = document.querySelector('#allocTable tbody');
									if (!tbody) return;
									// Resolve product name from left table
									const leftRow = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => (tr.querySelector('td')?.innerText||'').trim() === body.product_id);
									const name = leftRow ? (leftRow.querySelectorAll('td')[1]?.innerText||'').trim() : '';
									const tr = document.createElement('tr');
									const idx = (data && typeof data.allocation_index !== 'undefined') ? data.allocation_index : '';
									const t = (data && data.time) ? data.time : new Date().toISOString();
									tr.setAttribute('data-alloc-index', idx);
									tr.setAttribute('data-product-id', body.product_id);
									tr.innerHTML = `
										<td>${t}</td>
										<td>${body.product_id}</td>
										<td>${name}</td>
										<td>${(data && typeof data.applied_qty !== 'undefined' ? data.applied_qty : (body.qty||0)).toLocaleString('vi-VN')}</td>
										<td>${body.zone_id||''}</td>
										<td>${body.rack_id||''}</td>
										<td>${body.bin_id||''}</td>
										<td>${body.bin_status||''}</td>
										<td>
											<button type=\"button\" class=\"btn\" data-edit>Sửa</button>
											<button type=\"button\" class=\"btn\" data-del>Xóa</button>
										</td>`;
									tbody.appendChild(tr);
								})();
								alert('Đã lưu phân bổ vào Bin');
							} else {
								// qty == 0: chỉ cập nhật trạng thái bin, reload sơ đồ để thấy màu/số lượng mới
								loadLocations('<?= htmlspecialchars($warehouseId) ?>');
								alert('Đã cập nhật trạng thái Bin');
							}
							modalClose();
						} else {
							alert('Lỗi lưu: ' + (data.message || '')); 
						}
				} catch(err){ alert('Lỗi kết nối: ' + err.message); }
			});

			// Close modal when clicking backdrop
			modal && modal.querySelector('.backdrop')?.addEventListener('click', modalClose);

			// Complete button handler
			document.getElementById('btnComplete')?.addEventListener('click', async () => {
				if (!confirm('Xác nhận hoàn tất phiếu này?')) return;
				try {
					const res = await fetch('receipts/locate/process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'complete_receipt', id:'<?= htmlspecialchars($receipt['transaction_id']) ?>' })});
					const data = await res.json();
					if (data && data.success) {
						alert('Phiếu đã chuyển sang trạng thái Đã hoàn tất');
						const badge = document.querySelector('.badge');
						if (badge) { badge.className = 'badge located'; badge.textContent = 'Đã hoàn tất'; }
						document.getElementById('btnComplete').style.display = 'none';
						// Ẩn tất cả nút Sửa và Xóa
						document.querySelectorAll('#allocTable [data-edit], #allocTable [data-del]').forEach(btn => btn.style.display = 'none');
					} else {
						alert('Không thể hoàn tất: ' + (data.message||''));
					}
				} catch(err) { alert('Lỗi kết nối: ' + err.message); }
			});

			// Edit/Delete allocation handlers
			document.getElementById('allocTable')?.addEventListener('click', async (ev) => {
				function reindexAllocRows(){
					const rows = document.querySelectorAll('#allocTable tbody tr');
					rows.forEach((row, i) => row.setAttribute('data-alloc-index', String(i)));
				}
				const btnEdit = ev.target.closest('[data-edit]');
				const btnDel = ev.target.closest('[data-del]');
				const tr = ev.target.closest('tr');
				if (!tr) return;
				const idx = parseInt(tr.getAttribute('data-alloc-index')||'-1',10);
				const productId = tr.getAttribute('data-product-id')||'';
				if (Number.isNaN(idx) || idx < 0) return;
				if (btnDel) {
					if (!confirm('Xóa phân bổ này?')) return;
					const oldQty = parseInt((tr.children[3].textContent||'0').replace(/\D/g,''),10) || 0;
					try {
						const res = await fetch('receipts/locate/process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_allocation', id:'<?= htmlspecialchars($receipt['transaction_id']) ?>', allocation_index: idx })});
						const data = await res.json();
						if (data && data.success) {
							tr.remove();
							reindexAllocRows();
							// adjust remaining on left table
							const leftRow = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(r => (r.querySelector('td')?.innerText||'').trim() === (data.product_id||productId));
							if (leftRow && typeof data.remaining !== 'undefined') {
								const remCell = leftRow.querySelector('.remaining-cell');
								if (remCell) remCell.textContent = Math.max(0, parseInt(data.remaining,10)||0).toLocaleString('vi-VN');
							}
							toggleCompleteButton();
							updateBadgeByRemaining();
							// Refresh locations UI to reflect bin changes
							loadLocations('<?= htmlspecialchars($warehouseId) ?>');
							alert('Đã xóa phân bổ');
						} else alert('Không thể xóa: ' + (data.message||''));
					} catch(err){ alert('Lỗi: ' + err.message); }
					return;
				}
				if (btnEdit) {
					const cur = Array.from(tr.children).map(td => td.textContent.trim());
					const prevQty = parseInt((cur[3]||'0').replace(/\D/g,''),10) || 0;
					const newQty = parseInt(prompt('Số lượng mới', prevQty)||'0', 10);
					const newZone = prompt('Zone', cur[4]||'');
					const newRack = prompt('Rack', cur[5]||'');
					const newBin  = prompt('Bin', cur[6]||'');
					const newStatus = prompt('Trạng thái (empty/partial/full)', cur[7]||'');
					try {
						const res = await fetch('receipts/locate/process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'update_allocation', id:'<?= htmlspecialchars($receipt['transaction_id']) ?>', allocation_index: idx, qty: newQty, zone_id: newZone, rack_id: newRack, bin_id: newBin, bin_status: newStatus })});
						const data = await res.json();
						if (data && data.success) {
							// Update row cells
							tr.children[3].textContent = (newQty||0).toLocaleString('vi-VN');
							tr.children[4].textContent = newZone||'';
							tr.children[5].textContent = newRack||'';
							tr.children[6].textContent = newBin||'';
							tr.children[7].textContent = newStatus||'';
							// adjust remaining by delta
							const leftRow = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(r => (r.querySelector('td')?.innerText||'').trim() === (data.product_id||productId));
							if (leftRow && typeof data.remaining !== 'undefined') {
								const remCell = leftRow.querySelector('.remaining-cell');
								if (remCell) remCell.textContent = Math.max(0, parseInt(data.remaining,10)||0).toLocaleString('vi-VN');
							}
							toggleCompleteButton();
							updateBadgeByRemaining();
							// Refresh locations UI to reflect bin changes
							loadLocations('<?= htmlspecialchars($warehouseId) ?>');
							alert('Đã cập nhật phân bổ');
						} else alert('Không thể cập nhật: ' + (data.message||''));
					} catch(err){ alert('Lỗi: ' + err.message); }
				}
			});
		});
	</script>
	</head>
<body>
	<div class="page">
		<div class="header">
			<h2><i class="fa-solid fa-boxes-stacked"></i> Xếp hàng cho phiếu</h2>
					<div class="toolbar">
								<a class="btn" href="index.php?page=receipts/approve"><i class="fa fa-arrow-left"></i> Quay lại danh sách</a>
								<?php if ($status !== 3): // Chỉ hiện nút Hoàn tất khi chưa hoàn tất ?>
								<button id="btnComplete" class="btn primary" style="display:none"><i class="fa fa-flag-checkered"></i> Hoàn tất</button>
								<?php else: ?>
								<span class="btn" style="background:#d1fae5;color:#065f46;border-color:#6ee7b7;cursor:default"><i class="fa fa-check-circle"></i> Đã hoàn tất</span>
								<?php endif; ?>
			</div>
		</div>

		<div class="grid">
			<!-- Left: receipt info -->
			<div class="card">
				<div class="section">
					<h3 style="margin:0 0 6px">Phiếu: <?= htmlspecialchars($receipt['transaction_id']) ?></h3>
									<div class="meta">
										<div class="grid">
											<p><b>Ngày tạo:</b> <?= $created_date ?: 'N/A' ?></p>
											<p><b>Người tạo:</b> <?= htmlspecialchars($receipt['creator_name'] ?? $receipt['created_by'] ?? 'N/A') ?></p>
											<p><b>Kho nhập:</b> <?= htmlspecialchars($warehouseId ?: 'N/A') ?></p>
											<p><b>Loại phiếu:</b> <?= htmlspecialchars($receipt['type'] ?? 'N/A') ?></p>
											<p><b>Trạng thái:</b> <span class="badge <?= $statusCls ?>"><?= htmlspecialchars($statusLabel) ?></span></p>
											<?php if (!empty($approved_date)): ?>
												<p><b>Duyệt lúc:</b> <?= $approved_date ?></p>
											<?php endif; ?>
											<?php if (!empty($receipt['approved_by'])): ?>
												<p><b>Người duyệt:</b> <?= htmlspecialchars($receipt['approved_by']) ?></p>
											<?php endif; ?>
											<?php if (($receipt['type'] ?? '') === 'purchase'): ?>
												<p><b>Nhà cung cấp:</b> <?= htmlspecialchars($receipt['supplier_name'] ?? $receipt['supplier_id'] ?? 'N/A') ?></p>
											<?php elseif (($receipt['type'] ?? '') === 'transfer'): ?>
												<p><b>Kho nguồn:</b> <?= htmlspecialchars($receipt['source_warehouse_name'] ?? $receipt['source_warehouse_id'] ?? 'N/A') ?></p>
											<?php endif; ?>
											<?php if (!empty($receipt['note'])): ?>
												<p style="grid-column:1/-1"><b>Ghi chú:</b> <?= htmlspecialchars($receipt['note']) ?></p>
											<?php endif; ?>
										</div>
									</div>
				</div>
				<div class="section">
								<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
									<h4 style="margin:0">Sản phẩm trong phiếu</h4>
								</div>
					<div style="overflow:auto">
						<table class="table" id="productsTable">
							<thead>
								<tr>
									<th>Mã SP</th>
									<th>Tên sản phẩm</th>
									<th>Đơn vị</th>
									<th>Số lượng</th>
									<th>Quy đổi (cái)</th>
													<th>Giá nhập</th>
																		<th>Thành tiền</th>
																		<th>Số lượng chưa xếp</th>
								</tr>
							</thead>
							<tbody>
											<?php 
											$computedTotal = 0;
											if (!empty($receipt['details'])): 
												foreach ($receipt['details'] as $item):
												$qtyOrig = (int)($item['quantity'] ?? $item['qty'] ?? 0);
												$unitOrig = trim($item['unit'] ?? '');
												$price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
												$line = $qtyOrig * $price; 
												$computedTotal += $line; 
												// Determine conversion to base 'cái'
												$pidRow = $item['product_id'] ?? $item['sku'] ?? '';
												$factor = 1;
												try {
													if ($pidRow) {
														$pinfo = $cProduct->getProductById($pidRow);
														if (is_array($pinfo)) {
															$conv = $pinfo['conversionUnits'] ?? [];
															if ($unitOrig !== '' && strcasecmp($unitOrig, 'cái') !== 0) {
																foreach ($conv as $cu) {
																	$u = trim($cu['unit'] ?? '');
																	if ($u && strcasecmp($u, $unitOrig) === 0) { $factor = max(1, (int)($cu['factor'] ?? 1)); break; }
																}
															}
														}
													}
												} catch (Throwable $e) {}
												$qtyBase = (int)$qtyOrig * (int)$factor;
											?>
									<tr data-ordered-base="<?= (int)$qtyBase ?>">
									<td><?= htmlspecialchars($item['product_id'] ?? $item['sku'] ?? '') ?></td>
									<td><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? '') ?></td>
										<td><?= htmlspecialchars($unitOrig ?: '') ?></td>
										<td><?= number_format($qtyOrig, 0, ',', '.') ?></td>
										<td><?= number_format($qtyBase, 0, ',', '.') ?></td>
													<td><?= number_format($price, 0, ',', '.') ?> đ</td>
													<td><?= number_format($line, 0, ',', '.') ?> đ</td>
													<?php 
											// default remaining in base units; will be recalculated in JS using allocations if present
											$remaining = (int)$qtyBase; 
													?>
													<td class="remaining-cell" data-product-id="<?= htmlspecialchars($item['product_id'] ?? $item['sku'] ?? '') ?>"><?= number_format($remaining, 0, ',', '.') ?></td>
												</tr>
															<?php endforeach; else: ?>
																<?php $colspan = ($status === 1) ? 7 : 6; ?>
																<tr><td colspan="<?= $colspan ?>">Không có sản phẩm.</td></tr>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
									<div style="text-align:right;margin-top:8px;font-weight:600;">
										Tổng tiền: <?= number_format(($receipt['total_amount'] ?? $computedTotal ?? 0), 0, ',', '.') ?> đ
									</div>
				</div>

				<!-- Allocations history -->
				<div class="section">
					<h4 style="margin:0 0 8px">Hàng đã xếp</h4>
					<div style="overflow:auto">
						<table class="table" id="allocTable">
							<thead>
								<tr>
									<th>Thời gian</th>
									<th>Mã SP</th>
									<th>Tên sản phẩm</th>
									<th>Số lượng</th>
									<th>Zone</th>
									<th>Rack</th>
									<th>Bin</th>
									<th>Trạng thái Bin</th>
									<th>Hành động</th>
								</tr>
							</thead>
							<tbody>
								<?php 
								$allocs = $receipt['allocations'] ?? [];
								if (!empty($allocs)):
									foreach ($allocs as $i => $a):
										$time = htmlspecialchars($a['time'] ?? '');
										$pid = htmlspecialchars($a['product_id'] ?? '');
										$name = htmlspecialchars($productNameMap[$a['product_id']] ?? '');
										$qty = (int)($a['qty'] ?? 0);
										$zone = htmlspecialchars($a['zone_id'] ?? '');
										$rack = htmlspecialchars($a['rack_id'] ?? '');
										$bin = htmlspecialchars($a['bin_id'] ?? '');
										$bst = htmlspecialchars($a['bin_status'] ?? '');
									?>
									<tr data-alloc-index="<?= (int)$i ?>" data-product-id="<?= $pid ?>">
										<td><?= $time ?></td>
										<td><?= $pid ?></td>
										<td><?= $name ?></td>
										<td><?= number_format($qty,0,',','.') ?></td>
										<td><?= $zone ?></td>
										<td><?= $rack ?></td>
										<td><?= $bin ?></td>
										<td><?= $bst ?></td>
										<td>
											<?php if ($status !== 3): // Chỉ hiện nút Sửa/Xóa khi chưa hoàn tất ?>
											<button type="button" class="btn" data-edit>Sửa</button>
											<button type="button" class="btn" data-del>Xóa</button>
											<?php endif; ?>
										</td>
									</tr>
									<?php endforeach; else: ?>
									<tr><td colspan="9">Chưa có phân bổ nào.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Right: warehouse locations -->
			<div class="card">
				<div class="section locations-header">
					<h3 style="margin:0">Sơ đồ vị trí trong kho <?= htmlspecialchars($warehouseId) ?></h3>
					<span class="muted" style="font-size:13px">Chọn Bin khi xếp hàng</span>
				</div>
				<div class="section" id="locationsPane"></div>
			</div>
		</div>
	</div>

	<!-- Allocation Modal -->
	<div id="allocModal" class="modal" aria-hidden="true">
		<div class="backdrop"></div>
		<div class="panel">
			<h3 style="margin:0 0 8px">Phân bổ vào Bin</h3>
			<div id="binInfo" style="font-size:12px;color:#374151;background:#f9fafb;border:1px solid var(--border);border-radius:6px;padding:6px 8px;margin:6px 0 10px;display:none"></div>
			<form id="allocForm">
				<div style="display:grid;grid-template-columns:1fr;gap:10px">
					<label>
						<span>Sản phẩm</span>
						<select id="allocProduct" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px"></select>
					</label>
					<label>
						<span>Đơn vị</span>
						<select id="allocUnit" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px"></select>
					</label>
					<label>
						<span>Số lượng</span>
						<input type="number" id="allocQty" min="0" value="0" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px"/>
					</label>
					<label>
						<span>Trạng thái Bin</span>
						<select id="allocStatus" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px">
							<option value="empty">empty</option>
							<option value="partial">partial</option>
							<option value="full">full</option>
						</select>
					</label>
				</div>
				<div class="actions">
					<button type="button" class="btn" onclick="document.getElementById('allocModal').setAttribute('aria-hidden','true')">Hủy</button>
					<button type="submit" class="btn primary"><i class="fa fa-save"></i> Lưu</button>
				</div>
			</form>
		</div>
	</div>
</body>
</html>

