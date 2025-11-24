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

// Convert BSON document to pure PHP array (important for allocations)
$receipt = json_decode(json_encode($receipt), true);

function fmtDate($time) {
	if (!$time) return '';
	if ($time instanceof MongoDB\BSON\UTCDateTime) {
		return date('d/m/Y H:i', $time->toDateTime()->getTimestamp());
	}
	// Handle array format from json_decode (BSON date becomes {'$date': timestamp})
	if (is_array($time) && isset($time['$date'])) {
		$timestamp = $time['$date'];
		if (is_array($timestamp) && isset($timestamp['$numberLong'])) {
			$timestamp = (int)$timestamp['$numberLong'];
		}
		return date('d/m/Y H:i', $timestamp / 1000); // Convert milliseconds to seconds
	}
	if (is_string($time)) {
		return date('d/m/Y H:i', strtotime($time));
	}
	return '';
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
		:root {
			--bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			--card: #ffffff;
			--muted: #6b7280;
			--border: #e5e7eb;
			--primary: #2563eb;
			--primary-hover: #1d4ed8;
			--ok: #16a34a;
			--warn: #f59e0b;
			--danger: #dc2626;
			--shadow-sm: 0 2px 8px rgba(0,0,0,0.08);
			--shadow-md: 0 4px 16px rgba(0,0,0,0.12);
			--shadow-lg: 0 10px 30px rgba(0,0,0,0.2);
		}
		
		* { box-sizing: border-box; }
		
		body {
			margin: 0;
			background: #f3f4f6;
			font-family: 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
			color: #111827;
			line-height: 1.6;
		}
		
		.page {
			max-width: 1400px;
			margin: 0 auto;
			padding: 20px;
		}
		
		.header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 24px;
			padding: 20px 28px;
			background: white;
			border-radius: 16px;
			box-shadow: var(--shadow-md);
		}
		
		.header h2 {
			margin: 0;
			font-size: 26px;
			font-weight: 700;
			color: #1e293b;
			display: flex;
			align-items: center;
			gap: 12px;
		}
		
		.header h2 i {
			color: var(--primary);
			font-size: 28px;
		}
		
		.grid {
			display: grid;
			/* Stack cards vertically (top / bottom) */
			grid-template-columns: 1fr;
			gap: 20px;
			align-items: start;
		}
		
		.card {
			background: var(--card);
			border: 1px solid var(--border);
			border-radius: 16px;
			box-shadow: var(--shadow-md);
			overflow: hidden;
			transition: transform 0.2s, box-shadow 0.2s;
		}
		
		.card:hover {
			box-shadow: var(--shadow-lg);
		}
		
		.card .section {
			padding: 20px 24px;
			border-bottom: 1px solid #f1f5f9;
		}
		
		.card .section:last-child {
			border-bottom: none;
		}
		
		.card h3, .card h4 {
			font-size: 18px;
			font-weight: 600;
			color: #1e293b;
			margin: 0 0 12px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		
		.meta p {
			margin: 8px 0;
			font-size: 14px;
			display: flex;
			gap: 8px;
		}
		
		.meta p b {
			color: #64748b;
			font-weight: 500;
			min-width: 100px;
		}
		
		.meta .grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 12px 20px;
		}
		
		@media(max-width: 520px) {
			.meta .grid {
				grid-template-columns: 1fr;
			}
		}
		
		.badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 12px;
			border-radius: 999px;
			font-size: 13px;
			font-weight: 600;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		
		.badge::before {
			content: '';
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background: currentColor;
		}
		
		.pending {
			background: #fef3c7;
			color: #92400e;
		}
		
		.approved {
			background: #d1fae5;
			color: #065f46;
		}
		
		.rejected {
			background: #fee2e2;
			color: #991b1b;
		}
		
		.located {
			background: #dbeafe;
			color: #1e40af;
		}
		
		.table {
			width: 100%;
			border-collapse: collapse;
			font-size: 14px;
			border-radius: 10px;
			overflow: hidden;
		}
		
		.table th,
		.table td {
			border: 1px solid var(--border);
			padding: 12px 14px;
			text-align: center;
		}
		
		.table th {
			background: #f8fafc;
			font-weight: 600;
			color: #475569;
			text-transform: uppercase;
			font-size: 12px;
			letter-spacing: 0.5px;
		}
		
		.table tbody tr {
			transition: background 0.2s;
		}
		
		.table tbody tr:hover {
			background: #f8fafc;
		}
		
		.table tbody tr:nth-child(even) {
			background: #fafbfc;
		}
		
		.toolbar {
			display: flex;
			gap: 10px;
			align-items: center;
			flex-wrap: wrap;
		}
		
		.btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 10px 16px;
			border-radius: 10px;
			border: 1px solid var(--border);
			background: white;
			color: #374151;
			text-decoration: none;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			transition: all 0.2s;
			box-shadow: 0 2px 4px rgba(0,0,0,0.06);
		}
		
		.btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 8px rgba(0,0,0,0.12);
		}
		
		.btn.primary {
			background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
			color: white;
			border-color: var(--primary);
		}
		
		.btn.primary:hover {
			background: linear-gradient(135deg, var(--primary-hover) 0%, #1e3a8a 100%);
		}
		
		.btn i {
			font-size: 14px;
		}
		
		/* Right panel */
		.locations-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
		}
		
		#locationsPane {
			min-height: 400px;
			max-height: 80vh;
			overflow-y: auto;
		}
		
		#locationsPane .loading {
			padding: 40px;
			text-align: center;
			color: var(--muted);
			font-size: 16px;
		}
		
		#locationsPane .loading i {
			font-size: 24px;
			color: var(--primary);
		}
		
		/* Modal */
		.modal {
			position: fixed;
			inset: 0;
			display: none;
			align-items: center;
			justify-content: center;
			z-index: 9999;
			backdrop-filter: blur(4px);
		}
		
		.modal[aria-hidden="false"] {
			display: flex;
			animation: fadeIn 0.3s ease;
		}
		
		@keyframes fadeIn {
			from { opacity: 0; }
			to { opacity: 1; }
		}
		
		.modal .backdrop {
			position: absolute;
			inset: 0;
			background: rgba(0, 0, 0, 0.5);
		}
		
		.modal .panel {
			position: relative;
			background: white;
			border: 1px solid var(--border);
			border-radius: 16px;
			padding: 24px 28px;
			min-width: 400px;
			max-width: 560px;
			width: 92%;
			box-shadow: var(--shadow-lg);
			animation: slideUp 0.3s ease;
		}
		
		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(20px) scale(0.95);
			}
			to {
				opacity: 1;
				transform: translateY(0) scale(1);
			}
		}
		
		.modal h3 {
			margin: 0 0 16px;
			font-size: 20px;
			font-weight: 600;
			color: #1e293b;
		}
		
		.modal label {
			display: block;
			margin-bottom: 8px;
		}
		
		.modal label span {
			display: block;
			margin-bottom: 6px;
			font-size: 14px;
			font-weight: 500;
			color: #475569;
		}
		
		.modal input,
		.modal select {
			width: 100%;
			padding: 10px 14px;
			border: 1px solid var(--border);
			border-radius: 8px;
			font-size: 14px;
			transition: border 0.2s, box-shadow 0.2s;
		}
		
		.modal input:focus,
		.modal select:focus {
			outline: none;
			border-color: var(--primary);
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
		}
		
		.modal .actions {
			display: flex;
			justify-content: flex-end;
			gap: 10px;
			margin-top: 20px;
		}
		
		#binInfo {
			font-size: 13px;
			color: #374151;
			background: #f0f9ff;
			border: 1px solid #bae6fd;
			border-radius: 8px;
			padding: 10px 14px;
			margin: 12px 0 16px;
			display: none;
			line-height: 1.8;
		}
		
		/* Scroll styling */
		::-webkit-scrollbar {
			width: 8px;
			height: 8px;
		}
		
		::-webkit-scrollbar-track {
			background: #f1f5f9;
			border-radius: 4px;
		}
		
		::-webkit-scrollbar-thumb {
			background: #cbd5e1;
			border-radius: 4px;
		}
		
		::-webkit-scrollbar-thumb:hover {
			background: #94a3b8;
		}
		
		/* Unit Selector Modal */
		#unitSelectorModal {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.6);
			display: flex;
			align-items: center;
			justify-content: center;
			z-index: 10000;
			backdrop-filter: blur(4px);
		}
		
		#unitSelectorModal[aria-hidden="true"] {
			display: none;
		}
		
		#unitSelectorContent {
			background: white;
			border-radius: 16px;
			width: 90%;
			max-width: 700px;
			max-height: 85vh;
			overflow-y: auto;
			box-shadow: 0 20px 60px rgba(0,0,0,0.3);
			animation: modalSlideIn 0.3s ease-out;
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
		
		.unit-modal-header {
			padding: 24px 28px;
			border-bottom: 2px solid #f1f5f9;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		.unit-modal-header h3 {
			margin: 0;
			font-size: 20px;
			font-weight: 600;
			color: #1e293b;
		}
		
		.unit-modal-body {
			padding: 28px;
		}
		
		.unit-product-info {
			background: #f8fafc;
			padding: 16px 20px;
			border-radius: 12px;
			margin-bottom: 24px;
			border-left: 4px solid var(--primary);
		}
		
		.unit-product-info p {
			margin: 6px 0;
			font-size: 14px;
			color: #475569;
		}
		
		.unit-product-info strong {
			color: #1e293b;
		}
		
		.unit-options-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 20px;
		}
		
		.unit-option {
			background: white;
			border: 2px solid #e5e7eb;
			border-radius: 12px;
			padding: 20px;
			cursor: pointer;
			transition: all 0.2s;
			text-align: center;
		}
		
		.unit-option:hover {
			border-color: var(--primary);
			box-shadow: 0 4px 16px rgba(37, 99, 235, 0.15);
			transform: translateY(-2px);
		}
		
		.unit-option .unit-icon {
			font-size: 48px;
			margin-bottom: 12px;
		}
		
		.unit-option .unit-name {
			font-size: 18px;
			font-weight: 600;
			color: #1e293b;
			margin-bottom: 8px;
		}
		
		.unit-option .unit-dims {
			font-size: 13px;
			color: #64748b;
			margin: 6px 0;
		}
		
		.unit-option .unit-fit {
			font-size: 12px;
			color: #94a3b8;
			margin-top: 8px;
			font-style: italic;
		}
		
		/* Unit Selection Buttons */
		.unit-select-btn:hover {
			border-color: #8b5cf6 !important;
			background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%) !important;
			transform: translateY(-6px) scale(1.02);
			box-shadow: 0 12px 28px rgba(139, 92, 246, 0.25) !important;
		}
		
		.unit-select-btn:active {
			transform: translateY(-2px) scale(0.98);
		}
		
		/* Responsive */
		@media(max-width: 768px) {
			.page {
				padding: 12px;
			}
			
			.header {
				flex-direction: column;
				align-items: flex-start;
				gap: 12px;
			}
			
			.toolbar {
				width: 100%;
				flex-direction: column;
			}
			
			.btn {
				width: 100%;
				justify-content: center;
			}
			
			.unit-options-grid {
				grid-template-columns: 1fr;
			}
		}
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
					// Add timestamp to prevent caching
					const timestamp = new Date().getTime();
					const res = await fetch('locations/ajax_get_locations.php?warehouse_id=' + encodeURIComponent(warehouseId) + '&_t=' + timestamp, {
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
					// Prefer the real product_id on the row dataset; fallback to first cell (SKU)
					const pid = (tr.dataset.productId || (tr.querySelector('td')?.innerText||'')).trim();
					if (!pid) return;
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
			
			// ML Recommendations Handler - Step 1: Open unit selector
			let currentProductContext = null;
			
			document.addEventListener('click', async (ev) => {
				const btn = ev.target.closest('.btn-recommend');
				if (!btn) return;
				
				const productId = btn.getAttribute('data-product-id');
				const quantity = parseInt(btn.getAttribute('data-quantity') || '1', 10);
				const unit = btn.getAttribute('data-unit') || 'cái';
				
				if (!productId) {
					alert('Không xác định được sản phẩm');
					return;
				}
				
				// Store context for later use
				currentProductContext = {
					productId: productId,
					quantity: quantity,
					baseUnit: unit
				};
				
				// Open unit selector modal
				openUnitSelector(productId, quantity, unit);
			});
			
			// Open unit selector modal
			async function openUnitSelector(productId, quantity, unit) {
				const modal = document.getElementById('unitSelectorModal');
				const content = document.getElementById('unitSelectorContent');
				
				// Show modal
				modal.setAttribute('aria-hidden', 'false');
				
				// Fetch product details to get conversion units
				try {
					const url = 'products/get_product.php?id=' + encodeURIComponent(productId);
					console.log('Fetching product from:', url);
					const response = await fetch(url, {
						credentials: 'same-origin',
						headers: {
							'X-Requested-With': 'fetch'
						}
					});
					
					if (!response.ok) {
						console.error('HTTP error! status: ' + response.status, 'URL:', url);
						throw new Error('HTTP error! status: ' + response.status);
					}
					
					const result = await response.json();
					console.log('Product API Response:', result); // Debug log
					
					let product = null;
					
					// Handle different response formats
					if (result && result.success && result.data) {
						product = result.data;
					} else if (result && result._id) {
						// Direct product object
						product = result;
					}
					
					if (product) {
						// Store product in context for later use in selectUnitType
						if (currentProductContext) {
							currentProductContext.product = product;
						}
						
						document.getElementById('unitProductName').textContent = 
							product.product_name || product.name || productId;
						// Display quantity in base unit (cái)
						const baseUnit = product.baseUnit || 'cái';
						document.getElementById('unitQuantityDisplay').textContent = 
							quantity + ' ' + baseUnit;
						
						// Get dimensions for base unit (cái)
						const baseWidth = parseFloat(product.width || product.length || 0);
						const baseDepth = parseFloat(product.depth || product.width || 0);
						const baseHeight = parseFloat(product.height || 0);
						
						console.log('Base dimensions:', baseWidth, baseDepth, baseHeight); // Debug
						
						if (baseWidth && baseDepth && baseHeight) {
							document.getElementById('caiDimensions').textContent = 
								`${baseWidth} × ${baseDepth} × ${baseHeight} cm`;
						} else {
							document.getElementById('caiDimensions').textContent = 'Chưa có thông tin';
						}
						
						// Get dimensions for thùng (from conversion units)
						const convUnits = product.conversionUnits || [];
						let thungDims = null;
						let thungConversionRate = 1;
						
						console.log('Conversion units:', convUnits); // Debug
						
						for (const cu of convUnits) {
							if (cu.unit && (cu.unit.toLowerCase() === 'thùng' || cu.unit.toLowerCase() === 'thung')) {
								thungDims = cu.dimensions || cu;
								// Get conversion factor (10 cái = 1 thùng)
								thungConversionRate = parseFloat(cu.factor || 1);
								break;
							}
						}
						
						if (thungDims && thungDims.width && thungDims.height) {
							const thungW = parseFloat(thungDims.width || 0);
							const thungD = parseFloat(thungDims.depth || thungDims.width || 0);
							const thungH = parseFloat(thungDims.height || 0);
							
							document.getElementById('thungDimensions').textContent = 
								`${thungW} × ${thungD} × ${thungH} cm`;
							document.getElementById('thungContains').textContent = 
								`${thungConversionRate} cái/thùng`;
						} else {
							document.getElementById('thungDimensions').textContent = 'Chưa có thông tin';
							document.getElementById('thungContains').textContent = '-';
						}
					} else {
						console.error('Invalid product data:', result);
						document.getElementById('unitProductName').textContent = productId;
						document.getElementById('unitQuantityDisplay').textContent = quantity + ' ' + unit;
						document.getElementById('thungDimensions').textContent = 'Không tải được dữ liệu';
						document.getElementById('caiDimensions').textContent = 'Không tải được dữ liệu';
					}
				} catch (error) {
					console.error('Error fetching product:', error);
					document.getElementById('unitProductName').textContent = productId;
					document.getElementById('unitQuantityDisplay').textContent = quantity + ' ' + unit;
					document.getElementById('thungDimensions').textContent = 'Lỗi: ' + error.message;
					document.getElementById('caiDimensions').textContent = 'Lỗi: ' + error.message;
				}
			}
			
			// Select unit type and proceed to AI recommendations
			window.selectUnitType = function(selectedUnit) {
				if (!currentProductContext) {
					alert('Lỗi: Không có thông tin sản phẩm');
					return;
				}
				
				// Store selected unit type globally for later use in allocation modal
				window.selectedUnitFromSelector = selectedUnit;
				
				// Close unit selector
				document.getElementById('unitSelectorModal').setAttribute('aria-hidden', 'true');
				
				// Calculate quantity to send based on selected unit
				// currentProductContext.quantity is always in base unit (cái)
				// Example: Receipt has "3 thùng" → stored as 30 cái
				const baseUnit = currentProductContext.baseUnit || 'cái';
				let quantityToSend = currentProductContext.quantity; // Default: 30 cái
				
				// If user selected a conversion unit, convert back to that unit
				if (selectedUnit !== baseUnit) {
					const convUnits = currentProductContext.product?.conversionUnits || [];
					for (const cu of convUnits) {
						if (cu.unit && cu.unit.toLowerCase() === selectedUnit.toLowerCase()) {
							const factor = parseFloat(cu.factor || 1);
							// Convert back: 30 cái ÷ 10 = 3 thùng
							quantityToSend = Math.ceil(currentProductContext.quantity / factor);
							console.log(`Converting: ${currentProductContext.quantity} ${baseUnit} ÷ ${factor} = ${quantityToSend} ${selectedUnit}`);
							break;
						}
					}
				}
				
				console.log(`User selected: ${selectedUnit}, sending quantity=${quantityToSend} ${selectedUnit} to API`);
				
				// Open ML modal and fetch recommendations
				// Send selected unit so API knows which dimensions to use
				fetchAIRecommendations(
					currentProductContext.productId,
					quantityToSend,  // Quantity in selected unit (3 thùng or 30 cái)
					selectedUnit  // Send selected unit so API knows which dimensions to use
				);
			};
			
			// Close unit selector modal
			window.closeUnitSelector = function() {
				document.getElementById('unitSelectorModal').setAttribute('aria-hidden', 'true');
			};
			
			// Fetch AI recommendations
			async function fetchAIRecommendations(productId, quantity, unit) {
				const mlModal = document.getElementById('mlModal');
				const mlContent = document.getElementById('mlContent');
				mlModal.setAttribute('aria-hidden', 'false');
				
				// Show loading
				mlContent.innerHTML = `
					<div style="text-align:center;padding:40px;color:#6b7280">
						<i class="fa fa-spinner fa-spin" style="font-size:32px;color:#8b5cf6"></i>
						<p style="margin-top:12px">Đang phân tích vị trí tốt nhất cho ${quantity} ${unit}...</p>
					</div>
				`;
				
				try {
					const res = await fetch('receipts/locate/get_recommendations.php', {
						method: 'POST',
						headers: {'Content-Type': 'application/json'},
						body: JSON.stringify({
							product_id: productId,
							quantity: quantity,
							unit: unit,
							warehouse_id: '<?= htmlspecialchars($warehouseId) ?>'
						})
					});
					
				const data = await res.json();
				
				// Debug: Log the full response
				console.log('🔍 API Response:', data);
				console.log('📦 Product Name:', data.product_name);
				console.log('📏 Product Dimensions:', data.product_dimensions);
				console.log('🔢 Algorithm Version:', data.algorithm_version);
				console.log('📊 Bins by category:', {
					same_product: data.same_product_bins?.length || 0,
					same_category: data.same_category_bins?.length || 0,
					high_volume: data.high_volume_bins?.length || 0,
					optimal_fill: data.optimal_fill_bins?.length || 0
				});
				
			// 🔍 Debug: Show which bins were filtered out
			if (data.debug_filters) {
				console.log('🚫 Filtered Bins (WHY NOT SHOWING):');
				const filtered = Object.entries(data.debug_filters).filter(([code, info]) => !info.passed);
				filtered.forEach(([code, info]) => {
					if (info.bin_product_id) {
						console.log(`  ❌ ${code}: ${info.reason}`);
						console.log(`     └─ Bin has: Product ${info.bin_product_id}, Category: ${info.bin_category || 'N/A'}, Qty: ${info.bin_quantity || 0}, Capacity: ${info.bin_capacity || 0}%`);
						console.log(`     └─ Trying to store: Product ${info.current_product_id}, Category: ${info.current_category || 'N/A'}`);
					} else {
						console.log(`  ❌ ${code}: ${info.reason}`);
					}
				});
				console.log(`Total filtered: ${filtered.length} bins`);
				
				const passed = Object.entries(data.debug_filters).filter(([code, info]) => info.passed);
				console.log(`✅ Passed filters: ${passed.length} bins`);
				passed.forEach(([code]) => console.log(`  ✅ ${code}`));
			}				// 🔍 Debug: Log chi tiết các bin để xem fill_rate_after
				if (data.high_volume_bins && data.high_volume_bins.length > 0) {
					console.log('📦 High Volume Bins Details:');
					data.high_volume_bins.forEach((bin, idx) => {
						console.log(`  ${idx + 1}. ${bin.bin_code}: current=${bin.current_utilization?.toFixed(1)}%, after=${bin.utilization_after?.toFixed(1)}%, items=${bin.items_can_fit}, quality=${bin.quality_percentage}%`);
					});
				}					if (!data.success) {
						let errorMsg = data.error || 'Lỗi không xác định';
						if (data.debug) {
							console.error('Debug info:', data.debug);
							errorMsg += '<br><small style="font-size:12px">Xem console để biết chi tiết</small>';
						}
						mlContent.innerHTML = `
							<div style="padding:20px;text-align:center;color:#991b1b">
								<i class="fa fa-exclamation-triangle" style="font-size:32px"></i>
								<p style="margin-top:12px">${errorMsg}</p>
							</div>
						`;
						return;
					}
					
					// Get 4 categories
					const sameProduct = data.same_product_bins || [];
					const sameCategory = data.same_category_bins || [];
					const highVolume = data.high_volume_bins || [];
					const optimalFill = data.optimal_fill_bins || [];
					
					// Always display 4 categories, even if all empty (will show "Không có bin phù hợp" for each)
					
					// Display recommendations by category
					let html = `
						<div style="margin-bottom:16px;padding:14px 16px;background:linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);border-left:4px solid #0ea5e9;border-radius:8px">
							<div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
								<div style="flex:1;min-width:0">
									<div style="font-size:15px;font-weight:600;color:#0c4a6e;margin-bottom:4px">${data.product_name}</div>
									<div style="font-size:12px;color:#64748b">Kích thước: ${data.product_dimensions}</div>
								</div>
								<div style="display:flex;gap:12px;font-size:11px">
									<div style="text-align:center;padding:6px 10px;background:white;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
										<div style="font-size:16px;font-weight:700;color:#0ea5e9">${data.total_evaluated}</div>
										<div style="color:#64748b;margin-top:2px">Đánh giá</div>
									</div>
									<div style="text-align:center;padding:6px 10px;background:white;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
										<div style="font-size:16px;font-weight:700;color:#16a34a">${(data.counts?.same_product || 0) + (data.counts?.same_category || 0) + (data.counts?.high_volume || 0) + (data.counts?.optimal_fill || 0)}</div>
										<div style="color:#64748b;margin-top:2px">Gợi ý</div>
									</div>
								</div>
							</div>
						</div>
					`;
					
				// Helper function to render a bin card
				const renderBin = (rec) => {
					const currentUtil = rec.current_utilization || 0;
					const afterUtil = rec.utilization_after || 0;
					const fitMethod = rec.fit_method || 'unknown';
					const fitIcon = fitMethod === 'stacked' ? '📚' : '➡️';
					const fitLabel = fitMethod === 'stacked' ? 'Chồng' : 'Ngang';
					const itemsCanFit = rec.items_can_fit || 0;
					
					// Scoring details
					const fillScore = rec.fill_efficiency_score || 0;
					const zoneBonus = rec.zone_bonus || 0;
					const splitPenalty = rec.split_penalty || 0;
					const totalScore = rec.quality_score || 0;
					
					return `
						<div style="padding:10px 12px;margin-bottom:8px;border-left:4px solid ${rec.quality_color};border-radius:8px;background:#fff;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 4px rgba(0,0,0,0.08)" 
							 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';this.style.transform='translateX(4px) scale(1.01)'"
							 onmouseout="this.style.boxShadow='0 2px 4px rgba(0,0,0,0.08)';this.style.transform=''"
							 onclick="selectRecommendedBin('${rec.bin_id}', ${itemsCanFit})">
							
							<!-- Header -->
							<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
								<strong style="font-size:15px;color:#1e293b;font-weight:700">${rec.bin_code || rec.bin_id}</strong>
								<div style="display:flex;align-items:center;gap:6px">
									<span style="padding:4px 10px;background:${rec.quality_color};color:#fff;border-radius:12px;font-size:10px;font-weight:700;letter-spacing:0.5px;box-shadow:0 2px 4px rgba(0,0,0,0.2)">
										${rec.quality_label}
									</span>
									<span style="font-size:16px;font-weight:900;color:${rec.quality_color};text-shadow:0 1px 2px rgba(0,0,0,0.1)">
										${rec.quality_percentage}%
									</span>
								</div>
							</div>
							
							<!-- Main Info -->
							<div style="display:flex;flex-wrap:wrap;gap:10px;font-size:11px;color:#475569;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #e2e8f0">
								<span style="display:flex;align-items:center;gap:4px;background:#f1f5f9;padding:3px 8px;border-radius:6px">
									${fitIcon} <strong>${fitLabel}</strong>
								</span>
								<span style="display:flex;align-items:center;gap:4px;background:#dcfce7;padding:3px 8px;border-radius:6px;color:#166534">
									📦 <strong style="font-size:13px">${itemsCanFit}</strong> cái
								</span>
								<span style="display:flex;align-items:center;gap:4px;background:#dbeafe;padding:3px 8px;border-radius:6px;color:#1e40af">
									📊 ${currentUtil.toFixed(1)}% → <strong>${afterUtil.toFixed(1)}%</strong>
								</span>
								${rec.current_qty > 0 ? `
									<span style="display:flex;align-items:center;gap:4px;background:#fef3c7;padding:3px 8px;border-radius:6px;color:#92400e">
										🔢 Hiện: <strong>${rec.current_qty}</strong>
									</span>
								` : ''}
							</div>
							
							<!-- Scoring Breakdown -->
							<div style="display:flex;flex-wrap:wrap;gap:8px;font-size:10px;color:#64748b;background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);padding:6px 8px;border-radius:6px">
								<span style="display:flex;align-items:center;gap:3px" title="Điểm hiệu suất lấp đầy (0.8 = tối ưu khi 80-95%)">
									⚡ <strong style="color:#0ea5e9">${fillScore.toFixed(3)}</strong>
								</span>
								<span style="color:#cbd5e1">+</span>
								<span style="display:flex;align-items:center;gap:3px" title="Điểm ưu tiên vị trí zone (càng gần càng cao)">
									🎯 <strong style="color:#8b5cf6">${zoneBonus.toFixed(3)}</strong>
								</span>
								${splitPenalty > 0 ? `
									<span style="color:#cbd5e1">-</span>
									<span style="display:flex;align-items:center;gap:3px" title="Phạt khi phải chia nhỏ lô hàng">
										⚠️ <strong style="color:#f59e0b">${splitPenalty.toFixed(3)}</strong>
									</span>
								` : ''}
								<span style="color:#cbd5e1">=</span>
								<span style="display:flex;align-items:center;gap:3px;background:${rec.quality_color}20;padding:2px 6px;border-radius:4px" title="Điểm chất lượng tổng hợp">
									🏆 <strong style="color:${rec.quality_color};font-size:11px">${totalScore.toFixed(3)}</strong>
								</span>
							</div>
						</div>
					`;
				};					// Category 1: Same Product Bins
					html += `
						<div style="margin-bottom:16px">
							<div style="padding:8px 14px;background:#7c3aed;color:white;border-radius:8px 8px 0 0;font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:space-between">
								<span>Bin có cùng sản phẩm</span>
								<span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:11px">${sameProduct.length}</span>
							</div>
							<div style="padding:10px;background:#faf5ff;border:1px solid #e9d5ff;border-top:none;border-radius:0 0 8px 8px;max-height:240px;overflow-y:auto">
								${sameProduct.length > 0 ? sameProduct.map(renderBin).join('') : '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:12px">Không có bin phù hợp</div>'}
							</div>
						</div>
					`;
					
					// Category 2: Same Category Bins
					html += `
						<div style="margin-bottom:16px">
							<div style="padding:8px 14px;background:#f59e0b;color:white;border-radius:8px 8px 0 0;font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:space-between">
								<span>Bin có cùng loại sản phẩm</span>
								<span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:11px">${sameCategory.length}</span>
							</div>
							<div style="padding:10px;background:#fffbeb;border:1px solid #fde68a;border-top:none;border-radius:0 0 8px 8px;max-height:240px;overflow-y:auto">
								${sameCategory.length > 0 ? sameCategory.map(renderBin).join('') : '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:12px">Không có bin phù hợp</div>'}
							</div>
						</div>
					`;
					
					// Category 3: High Volume Bins
					html += `
						<div style="margin-bottom:16px">
							<div style="padding:8px 14px;background:#059669;color:white;border-radius:8px 8px 0 0;font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:space-between">
								<span>Bin còn nhiều thể tích</span>
								<span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:11px">${highVolume.length}</span>
							</div>
							<div style="padding:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-top:none;border-radius:0 0 8px 8px;max-height:240px;overflow-y:auto">
								${highVolume.length > 0 ? highVolume.map(renderBin).join('') : '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:12px">Không có bin phù hợp</div>'}
							</div>
						</div>
					`;
					
					// Category 4: Optimal Fill Bins
					html += `
						<div style="margin-bottom:16px">
							<div style="padding:8px 14px;background:#0ea5e9;color:white;border-radius:8px 8px 0 0;font-weight:600;font-size:13px;display:flex;align-items:center;justify-content:space-between">
								<span>Bin lấp đầy tối ưu (80-95%)</span>
								<span style="background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:12px;font-size:11px">${optimalFill.length}</span>
							</div>
							<div style="padding:10px;background:#eff6ff;border:1px solid #bfdbfe;border-top:none;border-radius:0 0 8px 8px;max-height:240px;overflow-y:auto">
								${optimalFill.length > 0 ? optimalFill.map(renderBin).join('') : '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:12px">Không có bin phù hợp</div>'}
							</div>
						</div>
					`;
					
					html += `
						<div style="margin-top:12px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;color:#64748b;text-align:center">
							Click vào bin để chọn và xếp hàng
						</div>
					`;
					
					mlContent.innerHTML = html;
					
				} catch (err) {
					mlContent.innerHTML = `
						<div style="padding:30px;text-align:center">
							<div style="width:60px;height:60px;margin:0 auto 16px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center">
								<i class="fa fa-exclamation-triangle" style="font-size:24px;color:#dc2626"></i>
							</div>
							<p style="color:#991b1b;font-weight:600;margin:0">Lỗi kết nối</p>
							<p style="color:#64748b;font-size:13px;margin:8px 0 0">${err.message}</p>
						</div>
					`;
				}
			}
			
			// Function to select recommended bin
			window.selectRecommendedBin = function(binId, itemsCanFit) {
				// Store itemsCanFit for use when opening allocation modal
				window.recommendedItemsCanFit = itemsCanFit || 0;
				
				// ✅ Store current product context to pre-select in modal
				if (currentProductContext && currentProductContext.productId) {
					window.recommendedProductId = currentProductContext.productId;
					console.log('✅ Storing recommended product ID:', window.recommendedProductId);
				}
				
				// Close ML modal
				document.getElementById('mlModal').setAttribute('aria-hidden', 'true');
				
				// Parse bin location
				const parts = binId.split('/');
				if (parts.length !== 3) return;
				
				const [zoneId, rackId, binIdRaw] = parts;
				
				// Find the bin element in locations pane
				const binElement = document.querySelector(
					`#locationsPane .bin[data-zone="${zoneId}"][data-rack="${rackId}"][data-bin="${binIdRaw}"]`
				);
				
				if (binElement) {
					// Scroll to bin
					binElement.scrollIntoView({behavior: 'smooth', block: 'center'});
					
					// Highlight bin
					binElement.style.boxShadow = '0 0 0 3px #8b5cf6';
					binElement.style.transform = 'scale(1.05)';
					setTimeout(() => {
						binElement.style.boxShadow = '';
						binElement.style.transform = '';
					}, 2000);
					
					// Trigger click after a short delay
					setTimeout(() => {
						binElement.click();
					}, 500);
				} else {
					alert('Không tìm thấy bin trong sơ đồ. Vui lòng click thủ công.');
				}
			};
			
			// Close ML modal when clicking backdrop
			document.getElementById('mlModal')?.querySelector('.backdrop')?.addEventListener('click', () => {
				document.getElementById('mlModal').setAttribute('aria-hidden', 'true');
			});
			
			// Ẩn nút Xóa nếu phiếu đã hoàn tất khi load trang
			const receiptStatus = <?= (int)$status ?>;
			if (receiptStatus === 3) {
				document.querySelectorAll('#allocTable [data-del]').forEach(btn => btn.style.display = 'none');
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
				let val = Math.max(0, Math.min(byRemain, byCap || byRemain));
				
				// If user selected from ML recommendation, use recommended quantity
				if (window.recommendedItemsCanFit && window.recommendedItemsCanFit > 0) {
					// Cap recommended quantity by actual remaining
					val = Math.min(window.recommendedItemsCanFit, val);
					// Clear recommendation after use
					window.recommendedItemsCanFit = 0;
				}
				
				qtyInput.value = String(val);
				updateProductInfo();
			}

			// Update product info box with dimensions based on selected unit
			function updateProductInfo(){
				const productInfoDiv = document.getElementById('productInfo');
				if (!productInfoDiv) return;
				
				const productId = productSelect.value;
				const selectedUnit = unitSelect.value;
				
				if (!productId) {
					productInfoDiv.style.display = 'none';
					return;
				}
				
				// Find product row in table. Prefer `data-product-id` on the row (real product_id), fallback to first cell text.
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = tr.dataset.productId || (tr.querySelector('td')?.innerText||'').trim();
					return pid === productId;
				});
				
				if (row) {
					const tds = row.querySelectorAll('td');
					const dimCell = tds[2]; // Kích thước column (index 2)
					const dimText = dimCell ? dimCell.textContent.trim() : '';
					const unitText = tds[4] ? tds[4].textContent.trim() : ''; // Đơn vị column (index 4)
					
					if (dimText && dimText !== '-') {
						productInfoDiv.style.display = 'block';
						const parts = [];
						parts.push('📦 Sản phẩm: ' + (tds[1]?.textContent.trim() || productId));
						parts.push('📏 Kích thước: ' + dimText + ' cm');
						parts.push('📊 Đơn vị phiếu: ' + unitText);
						parts.push('🔄 Đơn vị chọn: ' + selectedUnit);
						productInfoDiv.innerHTML = parts.join('<br>');
					} else {
						productInfoDiv.style.display = 'none';
					}
				} else {
					productInfoDiv.style.display = 'none';
				}
			}

			// Fill product options from left table
			(function fillProductOptions(){
				const rows = Array.from(document.querySelectorAll('#productsTable tbody tr'));
				productSelect.innerHTML = '';
				rows.forEach(tr => {
					const tds = tr.querySelectorAll('td');
					if (tds.length < 2) return;
					const pid = tr.dataset.productId || (tds[0].innerText||'').trim();
					const skuText = (tds[0].innerText||'').trim();
					const name = (tds[1].innerText||'').trim();
					if (!pid) return;
					const opt = document.createElement('option');
					opt.value = pid; opt.textContent = name ? `${name} (${skuText || pid})` : (skuText || pid);
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
						
						// Priority 1: Select unit chosen from unit selector modal
						if (window.selectedUnitFromSelector) {
							const found = Array.from(unitSelect.options).find(o => 
								(o.value||'').toLowerCase() === String(window.selectedUnitFromSelector).toLowerCase()
							);
							if (found) {
								unitSelect.value = found.value;
							}
							// Clear after use to not affect future allocations
							window.selectedUnitFromSelector = null;
						}
						// Priority 2: Select base unit by default
						else if (data.baseUnit) {
							const found = Array.from(unitSelect.options).find(o => 
								(o.value||'').toLowerCase() === String(data.baseUnit).toLowerCase()
							);
							if (found) unitSelect.value = found.value;
						}
						
						// Update product info display
						updateProductInfo();
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
				
				// ✅ Priority 1: Use recommended product ID from AI suggestion
				let defaultPid = '';
				if (window.recommendedProductId) {
					defaultPid = window.recommendedProductId;
					console.log('✅ Using recommended product ID:', defaultPid);
					// Clear after use
					window.recommendedProductId = null;
				} 
				// Priority 2: Use active row (manual selection)
				else {
					const activeRow = document.querySelector('tr.row-product.active');
					if (activeRow) {
						// Prefer the row dataset (real product id); fallback to displayed SKU cell
						defaultPid = activeRow ? (activeRow.dataset.productId || (activeRow.querySelector('td')?.innerText||'').trim()) : '';
					}
				}
				
				// Set product select value
				if (defaultPid) {
					productSelect.value = defaultPid;
					console.log('✅ Product select set to:', productSelect.value);
				}
				// Load units for current product
				await loadUnitsForProduct(productSelect.value);
				// Set qty to remaining of selected product
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = tr.dataset.productId || (tr.querySelector('td')?.innerText||'').trim();
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
				// Populate info box with bin dimensions
				const info = document.getElementById('binInfo');
				if (info) {
					info.style.display = 'block';
					const binDims = el.getAttribute('data-dimensions') || '';
					const binUtil = el.getAttribute('data-utilization') || '0';
					const productDims = el.getAttribute('data-product-dimensions') || '';
					const parts = [];
					parts.push(' Bin: ' + (code||b));
					if (binDims) parts.push('Kích thước thùng: ' + binDims + ' cm');
					if (productDims && prodInBin) parts.push('Kích thước sản phẩm: ' + productDims + ' cm');
					
					// Color based on capacity: 0% = green, 1-80% = yellow, >80% = red
					const utilFloat = parseFloat(binUtil);
					let utilColor = '#10b981'; // green
					if (utilFloat > 80) {
						utilColor = '#dc2626'; // red
					} else if (utilFloat >= 1) {
						utilColor = '#f59e0b'; // yellow
					}
					parts.push('Chiếm dụng: <strong style="color:' + utilColor + '">' + utilFloat.toFixed(1) + '%</strong>');
					
					if (prodInBin) parts.push('Sản phẩm: ' + prodInBin);
					parts.push('Đang có: ' + qtyInBin.toLocaleString('vi-VN'));
					if (capInBin>0) parts.push('Sức chứa: ' + capInBin.toLocaleString('vi-VN'));
					info.innerHTML = parts.join('<br>');
				}
				openModal();
			});

			// When switching product in modal, default qty to remaining for that product
			productSelect.addEventListener('change', async () => {
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = tr.dataset.productId || (tr.querySelector('td')?.innerText||'').trim();
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
					...currentBinPayload
				};
				// Validate: qty <= remaining (base units). Convert entered qty to base using selected unit factor.
				const row = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => {
					const pid = tr.dataset.productId || (tr.querySelector('td')?.innerText||'').trim();
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
				
				// Validate: Check if product/unit dimensions fit in bin
				const productInfoDiv = document.getElementById('productInfo');
				const binInfoDiv = document.getElementById('binInfo');
				if (productInfoDiv && binInfoDiv) {
					// Extract product dimensions from productInfo display
					const productInfoText = productInfoDiv.textContent || '';
					const productDimMatch = productInfoText.match(/Kích thước:\s*([\d.]+)×([\d.]+)×([\d.]+)/);
					
					// Extract bin dimensions from binInfo display
					const binInfoText = binInfoDiv.textContent || binInfoDiv.innerHTML.replace(/<br>/g, ' ');
					const binDimMatch = binInfoText.match(/Kích thước:\s*([\d.]+)×([\d.]+)×([\d.]+)/);
					
					if (productDimMatch && binDimMatch) {
						const [_, pW, pD, pH] = productDimMatch.map(v => parseFloat(v));
						const [__, bW, bD, bH] = binDimMatch.map(v => parseFloat(v));
						
						// Check if product fits in bin (any orientation)
						const pDims = [pW, pD, pH].sort((a, b) => a - b);
						const bDims = [bW, bD, bH].sort((a, b) => a - b);
						
						if (pDims[0] > bDims[0] || pDims[1] > bDims[1] || pDims[2] > bDims[2]) {
							alert(`❌ Không thể xếp!\n\nKích thước ${unitSelect.value}: ${pW}×${pD}×${pH} cm\nKích thước bin: ${bW}×${bD}×${bH} cm\n\n${unitSelect.value} quá lớn so với bin này.`);
							return;
						}
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
									// Resolve product row from left table (use data-product-id if present)
									const leftRow = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(tr => (tr.dataset.productId || (tr.querySelector('td')?.innerText||'').trim()) === body.product_id);
									const name = leftRow ? (leftRow.querySelectorAll('td')[1]?.innerText||'').trim() : '';
									const skuForAlloc = leftRow ? (leftRow.querySelectorAll('td')[0]?.innerText||'').trim() : (body.product_id||'');
									const tr = document.createElement('tr');
									const idx = (data && typeof data.allocation_index !== 'undefined') ? data.allocation_index : '';
									const t = (data && data.time) ? data.time : new Date().toISOString();
									tr.setAttribute('data-alloc-index', idx);
									tr.setAttribute('data-product-id', body.product_id);
									tr.innerHTML = `
										<td>${t}</td>
										<td>${skuForAlloc}</td>
										<td>${name}</td>
										<td>${(data && typeof data.applied_qty !== 'undefined' ? data.applied_qty : (body.qty||0)).toLocaleString('vi-VN')}</td>
										<td>${body.zone_id||''}</td>
										<td>${body.rack_id||''}</td>
										<td>${body.bin_id||''}</td>
										<td>
											<button type="button" class="btn" data-del style="background:#fee2e2;color:#991b1b;border-color:#fecaca"><i class="fa fa-trash"></i> Xóa</button>
										</td>`;
									tbody.appendChild(tr);
								})();
								// Reload warehouse layout to show updated capacity %
								loadLocations('<?= htmlspecialchars($warehouseId) ?>');
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
						alert('Phiếu đã chuyển sang trạng thái Đã hoàn tất\n' + 
						      'Đã lưu: ' + data.inventory_inserted + ' tồn kho, ' + 
						      data.batch_locations_inserted + ' batch locations, ' + 
						      data.movements_inserted + ' movements');
						
						// Reload sơ đồ kho để cập nhật % capacity và màu bin
						await loadLocations('<?= htmlspecialchars($warehouseId) ?>');
						
						// Wait a bit for UI to update, then reload page
						setTimeout(() => {
							location.reload();
						}, 1000);
					} else {
						alert('Không thể hoàn tất: ' + (data.message||''));
					}
				} catch(err) { alert('Lỗi kết nối: ' + err.message); }
			});

			// Delete allocation handler
			document.getElementById('allocTable')?.addEventListener('click', async (ev) => {
				function reindexAllocRows(){
					const rows = document.querySelectorAll('#allocTable tbody tr');
					rows.forEach((row, i) => row.setAttribute('data-alloc-index', String(i)));
				}
				const btnDel = ev.target.closest('[data-del]');
				const tr = ev.target.closest('tr');
				if (!tr || !btnDel) return;
				const idx = parseInt(tr.getAttribute('data-alloc-index')||'-1',10);
				const productId = tr.getAttribute('data-product-id')||'';
				if (Number.isNaN(idx) || idx < 0) return;
				
				if (!confirm('Xóa phân bổ này?')) return;
				const oldQty = parseInt((tr.children[3].textContent||'0').replace(/\D/g,''),10) || 0;
				try {
					const res = await fetch('receipts/locate/process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action:'delete_allocation', id:'<?= htmlspecialchars($receipt['transaction_id']) ?>', allocation_index: idx })});
					const data = await res.json();
					if (data && data.success) {
						tr.remove();
						reindexAllocRows();
						// adjust remaining on left table
						const leftRow = Array.from(document.querySelectorAll('#productsTable tbody tr')).find(r => (r.dataset.productId || (r.querySelector('td')?.innerText||'').trim()) === (data.product_id||productId));
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
									    <th>Mã SKU</th>
									    <th>Tên sản phẩm</th>
										<th>Kích thước (cm)</th>
										<th>Xếp chồng</th>
										<th>Đơn vị</th>
										<th>Số lượng</th>
										<th>Quy đổi (cái)</th>
										<th>Giá nhập</th>
										<th>Thành tiền</th>
										<th>Số lượng chưa xếp</th>
										<th>Gợi ý</th>
								</tr>
							</thead>
							<tbody>
											<?php 
											$computedTotal = 0;
											
											// Pre-calculate allocated quantities by product_id
											$allocatedByProduct = [];
											if (!empty($receipt['allocations']) && is_array($receipt['allocations'])) {
												foreach ($receipt['allocations'] as $idx => $alloc) {
													// Convert BSON document to array if needed
													$allocArr = is_array($alloc) ? $alloc : json_decode(json_encode($alloc), true);
													
													$allocPid = $allocArr['product_id'] ?? '';
													$allocQty = (int)($allocArr['qty'] ?? 0);
													
													error_log("  Alloc #$idx: product_id=" . ($allocPid ?: 'EMPTY') . ", qty=$allocQty, type=" . gettype($alloc));
													
													if ($allocPid) {
														$allocatedByProduct[$allocPid] = ($allocatedByProduct[$allocPid] ?? 0) + $allocQty;
													}
												}
											}
											
											// DEBUG: Log allocations
											error_log("📊 ALLOCATIONS DEBUG:");
											error_log("Total allocations: " . count($receipt['allocations'] ?? []));
											error_log("Allocated by product: " . json_encode($allocatedByProduct, JSON_UNESCAPED_UNICODE));
											error_log("Raw allocations: " . json_encode($receipt['allocations'] ?? [], JSON_UNESCAPED_UNICODE));
											
											// Output to browser console
											echo "<script>console.log('📊 ALLOCATIONS DEBUG:', " . json_encode($allocatedByProduct) . ");</script>";
											echo "<script>console.log('📊 RAW ALLOCATIONS:', " . json_encode($receipt['allocations'] ?? []) . ");</script>";
											
											if (!empty($receipt['details'])): 
												foreach ($receipt['details'] as $item):
												$qtyOrig = (int)($item['quantity'] ?? $item['qty'] ?? 0);
												$unitOrig = trim($item['unit'] ?? '');
												$price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
												$line = $qtyOrig * $price; 
												$computedTotal += $line; 
												
												// Get actual product_id (MongoDB ObjectId) for allocation matching
												$actualProductId = $item['product_id'] ?? '';
												
												// Determine conversion to base 'cái'
												$pidRow = $actualProductId ?: ($item['sku'] ?? ''); // Fallback to SKU for display
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
												
												// Get product dimensions based on unit
												$dimText = '';
												$dimLabel = '';
												$stackable = true; // Default
												$maxStackHeight = 1; // Default
												
												try {
													if ($pidRow) {
														$pinfo = $cProduct->getProductById($pidRow);
														if (is_array($pinfo)) {
															// Get stacking info
															$stackable = isset($pinfo['stackable']) ? (bool)$pinfo['stackable'] : true;
															$maxStackHeight = isset($pinfo['max_stack_height']) ? (int)$pinfo['max_stack_height'] : 1;
															if ($maxStackHeight < 1) $maxStackHeight = 1;
															
															$dims = null;
															$unitType = 'cái';
															
															// If unit is conversion unit (thùng, hộp), try to get its dimensions
															if ($unitOrig && strcasecmp($unitOrig, 'cái') !== 0) {
																$convUnits = $pinfo['conversionUnits'] ?? [];
																foreach ($convUnits as $cu) {
																	if (strcasecmp(trim($cu['unit'] ?? ''), $unitOrig) === 0) {
																		if (isset($cu['dimensions']) && is_array($cu['dimensions'])) {
																			$dims = $cu['dimensions'];
																			$unitType = $cu['unit'];
																		}
																		break;
																	}
																}
															}
															
															// Fallback to base product dimensions
															if (!$dims && isset($pinfo['dimensions'])) {
																$dims = $pinfo['dimensions'];
																$unitType = $pinfo['baseUnit'] ?? 'cái';
															}
															
															if ($dims) {
																$w = (float)($dims['width'] ?? 0);
																$d = (float)($dims['depth'] ?? 0);
																$h = (float)($dims['height'] ?? 0);
																
																if ($w > 0 || $d > 0 || $h > 0) {
																	$dimText = sprintf("%.1f×%.1f×%.1f", $w, $d, $h);
																	$dimLabel = $unitType !== 'cái' ? " ($unitType)" : '';
																}
															}
														}
													}
												} catch (Throwable $e) {}
											?>
									<?php
										// Get SKU: prefer from receipt detail, otherwise lookup from product
										$displaySkuRaw = '';
										if (!empty($item['sku'])) {
											$displaySkuRaw = $item['sku'];
										} elseif (!empty($actualProductId)) {
											try {
												$pinfo = $cProduct->getProductById($actualProductId);
												if (is_array($pinfo) && !empty($pinfo['sku'])) {
													$displaySkuRaw = $pinfo['sku'];
												}
											} catch (Throwable $e) { /* ignore lookup errors */ }
										}
										// Fallback: if still no SKU, show "N/A" instead of product_id
										if (!$displaySkuRaw) $displaySkuRaw = 'N/A';
										$displaySku = htmlspecialchars($displaySkuRaw);
									?>
									<tr data-ordered-base="<?= (int)$qtyBase ?>" data-product-id="<?= htmlspecialchars($actualProductId) ?>">
									<td><?= $displaySku ?></td>
									<td><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? '') ?></td>
									<td style="font-size:11px;color:#64748b"><?= htmlspecialchars($dimText ?: '-') ?><?php if ($dimLabel): ?><br><span style="font-size:10px;color:#8b5cf6"><?= htmlspecialchars($dimLabel) ?></span><?php endif; ?></td>
									<td style="font-size:12px;">
										<?php if ($stackable && $maxStackHeight > 1): ?>
											<span style="color:#16a34a;font-weight:600">
												<i class="fa fa-layer-group"></i> Được
											</span>
											<br>
											<span style="font-size:10px;color:#6366f1">
												Tối đa: <?= $maxStackHeight ?> tầng
											</span>
										<?php else: ?>
											<span style="color:#dc2626">
												<i class="fa fa-ban"></i> Không
											</span>
										<?php endif; ?>
									</td>
										<td><?= htmlspecialchars($unitOrig ?: '') ?></td>
										<td><?= number_format($qtyOrig, 0, ',', '.') ?></td>
										<td><?= number_format($qtyBase, 0, ',', '.') ?></td>
													<td><?= number_format($price, 0, ',', '.') ?> đ</td>
													<td><?= number_format($line, 0, ',', '.') ?> đ</td>
													<?php 
											// Calculate remaining: ordered - allocated (using actual product_id)
											$allocated = $allocatedByProduct[$actualProductId] ?? 0;
											$remaining = max(0, (int)$qtyBase - $allocated);
											
											// DEBUG
											error_log("📦 Product: " . ($displaySkuRaw ?? 'N/A') . " (ID: $actualProductId)");
											error_log("   Ordered: $qtyBase, Allocated: $allocated, Remaining: $remaining");
											
											// Output to browser console
											echo "<script>console.log('📦 Product: " . addslashes($displaySkuRaw ?? 'N/A') . " (ID: $actualProductId)', 'Ordered: $qtyBase, Allocated: $allocated, Remaining: $remaining');</script>";
													?>
													<td class="remaining-cell" data-product-id="<?= htmlspecialchars($actualProductId) ?>"><?= number_format($remaining, 0, ',', '.') ?></td>
													<td>
														<button type="button" class="btn btn-recommend" 
															data-product-id="<?= htmlspecialchars($actualProductId) ?>" 
															data-quantity="<?= (int)$qtyBase ?>"
															data-unit="<?= htmlspecialchars($unitOrig) ?>" 
															style="background:linear-gradient(135deg,#8b5cf6 0%,#6366f1 100%);color:#fff;border:none;font-size:13px;padding:6px 12px">
															<i class="fa fa-brain"></i> Gợi ý
														</button>
													</td>
												</tr>
															<?php endforeach; else: ?>
																<tr><td colspan="11">Không có sản phẩm.</td></tr>
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
									    <th>Mã SKU</th>
									<th>Tên sản phẩm</th>
									<th>Số lượng</th>
									<th>Zone</th>
									<th>Rack</th>
									<th>Bin</th>
									<th>Hành động</th>
								</tr>
							</thead>
							<tbody>
								<?php 
								$allocs = $receipt['allocations'] ?? [];
								if (!empty($allocs)):
									foreach ($allocs as $i => $a):
										$time = htmlspecialchars($a['time'] ?? '');
										$pidRaw = $a['product_id'] ?? '';
										$pid = htmlspecialchars($pidRaw);
										$name = htmlspecialchars($productNameMap[$a['product_id']] ?? '');
										$qty = (int)($a['qty'] ?? 0);
										$zone = htmlspecialchars($a['zone_id'] ?? '');
										$rack = htmlspecialchars($a['rack_id'] ?? '');
										$bin = htmlspecialchars($a['bin_id'] ?? '');
										// Determine SKU to display for this allocation: prefer allocation's sku, then product lookup, then product_id
										$allocDisplaySkuRaw = '';
										if (!empty($a['sku'])) {
											$allocDisplaySkuRaw = $a['sku'];
										} elseif (!empty($pidRaw)) {
											try {
												$pinfoAlloc = $cProduct->getProductById($pidRaw);
												if (is_array($pinfoAlloc) && !empty($pinfoAlloc['sku'])) $allocDisplaySkuRaw = $pinfoAlloc['sku'];
											} catch (Throwable $e) {}
										}
										if (!$allocDisplaySkuRaw) $allocDisplaySkuRaw = $pidRaw;
										$allocDisplaySku = htmlspecialchars($allocDisplaySkuRaw);
									?>
									<tr data-alloc-index="<?= (int)$i ?>" data-product-id="<?= $pid ?>">
										<td><?= $time ?></td>
										<td><?= $allocDisplaySku ?></td>
										<td><?= $name ?></td>
										<td><?= number_format($qty,0,',','.') ?></td>
										<td><?= $zone ?></td>
										<td><?= $rack ?></td>
										<td><?= $bin ?></td>
										<td>
											<?php if ($status !== 3): // Chỉ hiện nút Xóa khi chưa hoàn tất ?>
											<button type="button" class="btn" data-del style="background:#fee2e2;color:#991b1b;border-color:#fecaca"><i class="fa fa-trash"></i> Xóa</button>
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

	<!-- Unit Selector Modal (Choose thùng/cái before AI) -->
	<div id="unitSelectorModal" class="modal" aria-hidden="true">
		<div class="backdrop"></div>
		<div class="panel" style="max-width:600px">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
				<h3 style="margin:0;display:flex;align-items:center;gap:10px">
					<i class="fa fa-boxes" style="color:#8b5cf6"></i>
					<span>Chọn đơn vị tính</span>
				</h3>
				<button type="button" class="btn" onclick="closeUnitSelector()" style="padding:6px 10px;background:transparent;border:none">
					<i class="fa fa-times" style="font-size:20px;color:#64748b"></i>
				</button>
			</div>
			
			<div id="unitSelectorContent">
				<!-- Product Info -->
				<div style="margin-bottom:20px;padding:16px;background:#f8fafc;border-left:4px solid #8b5cf6;border-radius:8px">
					<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
						<i class="fa fa-cube" style="color:#8b5cf6;font-size:18px"></i>
						<span style="font-weight:600;font-size:15px" id="unitProductName">Loading...</span>
					</div>
					<div style="font-size:13px;color:#64748b">
						<i class="fa fa-box"></i> Số lượng: <span id="unitQuantityDisplay" style="font-weight:600;color:#1e293b">-</span>
					</div>
				</div>
				
				<!-- Unit Selection Buttons -->
				<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
					<!-- Thùng Button -->
					<button type="button" 
						class="unit-select-btn" 
						onclick="selectUnitType('thùng')"
						style="padding:24px 20px;border:3px solid #e5e7eb;border-radius:12px;background:white;cursor:pointer;transition:all 0.3s;text-align:center;position:relative">
						<div style="font-size:56px;margin-bottom:12px">📦</div>
						<div style="font-weight:700;font-size:18px;color:#1e293b;margin-bottom:8px">Thùng</div>
						<div style="font-size:13px;color:#64748b;margin-bottom:6px" id="thungDimensions">-</div>
						<div style="font-size:12px;color:#8b5cf6;font-weight:600" id="thungContains">-</div>
					</button>
					
					<!-- Cái Button -->
					<button type="button" 
						class="unit-select-btn" 
						onclick="selectUnitType('cái')"
						style="padding:24px 20px;border:3px solid #e5e7eb;border-radius:12px;background:white;cursor:pointer;transition:all 0.3s;text-align:center;position:relative">
						<div style="font-size:56px;margin-bottom:12px">📱</div>
						<div style="font-weight:700;font-size:18px;color:#1e293b;margin-bottom:8px">Cái</div>
						<div style="font-size:13px;color:#64748b" id="caiDimensions">-</div>
					</button>
				</div>
				
				<div style="margin-top:20px;padding:12px;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;font-size:12px;color:#92400e;text-align:center">
					<i class="fa fa-info-circle"></i> Chọn đơn vị để xem số lượng có thể xếp vào mỗi bin
				</div>
			</div>
		</div>
	</div>

	<!-- ML Recommendations Modal -->
	<div id="mlModal" class="modal" aria-hidden="true">
		<div class="backdrop"></div>
		<div class="panel" style="max-width:700px">
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
				<h3 style="margin:0;display:flex;align-items:center;gap:10px">
					<i class="fa fa-brain" style="color:#8b5cf6"></i>
					<span>Gợi ý vị trí tối ưu</span>
				</h3>
				<button type="button" class="btn" onclick="document.getElementById('mlModal').setAttribute('aria-hidden','true')" style="padding:6px 10px">
					<i class="fa fa-times"></i>
				</button>
			</div>
			<div id="mlContent" style="min-height:200px">
				<div style="text-align:center;padding:40px;color:#6b7280">
					<i class="fa fa-spinner fa-spin" style="font-size:32px;color:#8b5cf6"></i>
					<p style="margin-top:12px">Đang phân tích...</p>
				</div>
			</div>
		</div>
	</div>

	<!-- Allocation Modal -->
	<div id="allocModal" class="modal" aria-hidden="true">
		<div class="backdrop"></div>
		<div class="panel">
			<h3 style="margin:0 0 8px">Phân bổ vào Bin</h3>
			<div id="binInfo" style="font-size:11px;line-height:1.6;color:#374151;background:#f9fafb;border:1px solid var(--border);border-radius:6px;padding:8px 10px;margin:6px 0 10px;display:none"></div>
			<div id="productInfo" style="font-size:11px;line-height:1.6;color:#374151;background:#fef3c7;border:1px solid #fbbf24;border-radius:6px;padding:8px 10px;margin:6px 0 10px;display:none"></div>
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

