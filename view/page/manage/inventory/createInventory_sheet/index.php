<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// From: view/page/manage/inventory/createInventory_sheet/index.php
// To: controller/cInventorySheet.php
include_once(__DIR__ . '/../../../../../controller/cInventorySheet.php');

$cSheet = new CInventorySheet();
$warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? ($_SESSION['warehouse_id'] ?? '');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 12px; box-shadow: 0 3px 15px rgba(0,0,0,0.08); }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 16px; }
        .header h2 { font-size: 24px; color: #111827; }
        .btn { padding: 10px 16px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-success { background: #059669; color: #fff; }
        .btn-success:hover { background: #047857; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .table-container { overflow-x: auto; margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border: 1px solid #e5e7eb; text-align: left; font-size: 14px; }
        th { background: #f9fafb; font-weight: 600; color: #111827; position: sticky; top: 0; z-index: 10; }
        tbody tr:hover { background: #f9fafb; }
        .qty-input { width: 100px; padding: 6px; border: 1px solid #d1d5db; border-radius: 6px; text-align: center; }
        .diff-positive { color: #059669; font-weight: 600; }
        .diff-negative { color: #dc2626; font-weight: 600; }
        .diff-zero { color: #6b7280; }
        .note-input { width: 100%; padding: 6px; border: 1px solid #d1d5db; border-radius: 6px; }
        .loading { text-align: center; padding: 40px; color: #6b7280; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0; }
        .stat-card { padding: 16px; background: #f9fafb; border-radius: 8px; border-left: 4px solid #2563eb; }
        .stat-card h4 { font-size: 14px; color: #6b7280; margin-bottom: 8px; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: #111827; }
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        .filter-box { background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .filter-box h3 { font-size: 16px; margin-bottom: 12px; color: #111827; }
        .filter-row { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
        #sheetInfo { background: #eff6ff; padding: 12px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #2563eb; }
        #sheetInfo strong { color: #1e40af; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #fff; padding: 24px; border-radius: 12px; max-width: 900px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; }
        .product-selector-table { width: 100%; margin-top: 16px; }
        .product-selector-table th, .product-selector-table td { padding: 8px; border: 1px solid #e5e7eb; font-size: 13px; }
        .product-selector-table tbody tr:hover { background: #f9fafb; cursor: pointer; }
        .product-selector-table tbody tr.selected { background: #dbeafe; }
        .search-box { margin-bottom: 16px; }
</style>

<div class="container">
        <div class="header">
            <h2>📋 Tạo phiếu kiểm kê hàng tồn kho</h2>
            <a href="index.php?page=inventory" class="btn btn-secondary">← Quay lại</a>
        </div>

        <div id="alertBox"></div>

        <div class="filter-box">
            <h3>Chọn sản phẩm kiểm kê</h3>
            <div style="margin-bottom: 12px;">
                <button class="btn btn-success" onclick="openProductSelector()">➕ Chọn sản phẩm cần kiểm kê</button>
                <button class="btn btn-primary" onclick="loadAllStock()">📦 Tải tất cả sản phẩm</button>
            </div>
            <div class="filter-row">
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label>Từ ngày:</label>
                    <input type="date" id="filterFrom" class="form-control">
                </div>
                <div class="form-group" style="flex: 1; min-width: 200px;">
                    <label>Đến ngày:</label>
                    <input type="date" id="filterTo" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
        </div>

        <div id="sheetInfo" style="display: none;">
            <strong>Mã phiếu:</strong> <span id="sheetCode"></span> | 
            <strong>Kho:</strong> <?= h($warehouseId) ?> | 
            <strong>Người tạo:</strong> <?= h($_SESSION['login']['name'] ?? $_SESSION['login']['username'] ?? 'Unknown') ?> | 
            <strong>Ngày tạo:</strong> <span id="sheetDate"></span>
        </div>

        <div class="stats" id="statsBox" style="display: none;">
            <div class="stat-card">
                <h4>Tổng số mặt hàng</h4>
                <div class="value" id="statTotal">0</div>
            </div>
            <div class="stat-card" style="border-left-color: #059669;">
                <h4>Thừa (thực tế > hệ thống)</h4>
                <div class="value" id="statPositive" style="color: #059669;">0</div>
            </div>
            <div class="stat-card" style="border-left-color: #dc2626;">
                <h4>Thiếu (thực tế < hệ thống)</h4>
                <div class="value" id="statNegative" style="color: #dc2626;">0</div>
            </div>
            <div class="stat-card" style="border-left-color: #6b7280;">
                <h4>Khớp (không chênh lệch)</h4>
                <div class="value" id="statZero" style="color: #6b7280;">0</div>
            </div>
        </div>

        <div class="form-group">
            <label>Ghi chú phiếu kiểm kê:</label>
            <input type="text" id="sheetNote" class="form-control" placeholder="Nhập ghi chú cho phiếu kiểm kê (tùy chọn)">
        </div>

        <div class="table-container">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th style="width: 50px;">STT</th>
                        <th>Mã SKU</th>
                        <th>Tên sản phẩm</th>
                        <th style="width: 80px;">ĐVT</th>
                        <th style="width: 120px;">SL Hệ thống</th>
                        <th style="width: 120px;">SL Thực tế</th>
                        <th style="width: 120px;">Chênh lệch</th>
                        <th style="width: 200px;">Ghi chú</th>
                    </tr>
                </thead>
                <tbody id="inventoryBody">
                    <tr>
                        <td colspan="8" class="loading">Nhấn "Tải dữ liệu" để bắt đầu kiểm kê</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="saveSheet('completed')" id="btnSaveComplete" disabled title="Vui lòng tải dữ liệu trước">✅ Hoàn thành và lưu</button>
            <button class="btn btn-danger" onclick="clearData()">🗑️ Xóa dữ liệu</button>
        </div>
        
        <div style="margin-top: 16px; padding: 12px; background: #dbeafe; border-left: 4px solid #2563eb; border-radius: 8px; font-size: 14px;">
            <strong>ℹ️ Quy trình kiểm kê:</strong>
            <ol style="margin: 8px 0 0 0; padding-left: 20px;">
                <li>Nhấn <strong>"� Tải tất cả sản phẩm"</strong> hoặc <strong>"➕ Chọn sản phẩm cần kiểm kê"</strong></li>
                <li>Nhập <strong>số lượng thực tế</strong> đếm được vào cột "SL Thực tế"</li>
                <li>Nhấn <strong>"✅ Hoàn thành và lưu"</strong> để tạo phiếu kiểm kê</li>
                <li><strong>Chờ quản lý duyệt phiếu</strong> - Chỉ khi được duyệt, số liệu mới được cập nhật vào hệ thống</li>
            </ol>
        </div>
    </div>

    <!-- Modal chọn sản phẩm -->
    <div class="modal" id="productSelectorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Chọn sản phẩm cần kiểm kê</h3>
                <button class="btn btn-secondary btn-sm" onclick="closeProductSelector()">✕</button>
            </div>
            <div class="search-box">
                <input type="text" id="productSearch" class="form-control" placeholder="🔍 Tìm kiếm sản phẩm (tên, SKU)..." onkeyup="filterProducts()">
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="product-selector-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAllProducts" onchange="toggleSelectAll()"></th>
                            <th>Mã SKU</th>
                            <th>Tên sản phẩm</th>
                            <th style="width: 100px;">SL Hệ thống</th>
                            <th style="width: 150px;">Cập nhật gần nhất</th>
                        </tr>
                    </thead>
                    <tbody id="productSelectorBody">
                        <tr><td colspan="5" class="loading">Đang tải...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                <button class="btn btn-secondary" onclick="closeProductSelector()">Hủy</button>
                <button class="btn btn-success" onclick="addSelectedProducts()">✔️ Thêm sản phẩm đã chọn</button>
            </div>
        </div>
    </div>

    <script>
        let stockData = [];
        let currentSheetId = null;
        let isSaving = false;
        let allProducts = [];
        let selectedProductIds = new Set();

        function showAlert(message, type = 'info') {
            const alertBox = document.getElementById('alertBox');
            alertBox.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => { alertBox.innerHTML = ''; }, 5000);
        }

        async function openProductSelector() {
            document.getElementById('productSelectorModal').classList.add('show');
            await loadAllProductsForSelector();
        }

        function closeProductSelector() {
            document.getElementById('productSelectorModal').classList.remove('show');
        }

        async function loadAllProductsForSelector() {
            try {
                const from = document.getElementById('filterFrom').value;
                const to = document.getElementById('filterTo').value;
                const url = `inventory/createInventory_sheet/process.php?action=get_stock&from=${from}&to=${to}`;
                
                const response = await fetch(url);
                const result = await response.json();
                
                console.log('Products loaded:', result);
                if (result.ok && result.data && result.data.length > 0) {
                    console.log('Sample product with last_update:', result.data[0]);
                }
                
                if (result.ok && result.data) {
                    allProducts = result.data;
                    renderProductSelector();
                } else {
                    showAlert('Không thể tải danh sách sản phẩm', 'danger');
                }
            } catch (error) {
                console.error('Load products error:', error);
                showAlert('Lỗi: ' + error.message, 'danger');
            }
        }

        function renderProductSelector() {
            const tbody = document.getElementById('productSelectorBody');
            if (allProducts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #6b7280;">Không có sản phẩm</td></tr>';
                return;
            }

            let html = '';
            allProducts.forEach(product => {
                const productId = product.product_id || product.id;
                const productSku = product.product_sku || product.sku || productId;
                const productName = product.product_name || product.name || 'N/A';
                const systemQty = product.system_qty || product.quantity || 0;
                const isChecked = selectedProductIds.has(productId) ? 'checked' : '';
                const isSelected = selectedProductIds.has(productId) ? 'selected' : '';
                
                // Format last update date
                let lastUpdate = 'Chưa kiểm kê';
                if (product.last_update) {
                    try {
                        let date;
                        // Check for MongoDB BSON format {sec: ..., usec: ...}
                        if (product.last_update.sec !== undefined) {
                            // MongoDB UTCDateTime format: convert sec to milliseconds
                            date = new Date(product.last_update.sec * 1000);
                        } else if (product.last_update.$date) {
                            // MongoDB JSON format: {$date: ...}
                            const dateValue = product.last_update.$date;
                            // Check if $date contains $numberLong (Extended JSON v2)
                            if (typeof dateValue === 'object' && dateValue.$numberLong) {
                                date = new Date(parseInt(dateValue.$numberLong));
                            } else if (typeof dateValue === 'object' && dateValue.sec !== undefined) {
                                // {$date: {sec: ..., usec: ...}}
                                date = new Date(dateValue.sec * 1000);
                            } else if (typeof dateValue === 'number') {
                                date = new Date(dateValue);
                            } else if (typeof dateValue === 'string') {
                                date = new Date(dateValue);
                            } else {
                                date = new Date(dateValue);
                            }
                        } else if (typeof product.last_update === 'object' && product.last_update.date) {
                            date = new Date(product.last_update.date);
                        } else if (typeof product.last_update === 'string') {
                            date = new Date(product.last_update);
                        } else {
                            // Try direct conversion
                            date = new Date(product.last_update);
                        }
                        
                        if (!isNaN(date.getTime())) {
                            lastUpdate = date.toLocaleString('vi-VN', {
                                year: 'numeric',
                                month: '2-digit',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                        } else {
                            console.warn('Invalid date for product:', product.product_id, product.last_update);
                        }
                    } catch (e) {
                        console.error('Date parse error:', e, product.last_update);
                        lastUpdate = 'Lỗi định dạng';
                    }
                }
                
                html += `
                    <tr class="${isSelected}" onclick="toggleProductRow('${productId}', event)">
                        <td><input type="checkbox" ${isChecked} onclick="event.stopPropagation();" onchange="toggleProduct('${productId}')"></td>
                        <td>${productSku}</td>
                        <td>${productName}</td>
                        <td style="text-align: center;">${systemQty}</td>
                        <td style="font-size: 12px; color: #6b7280;">${lastUpdate}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function toggleProduct(productId) {
            const checkbox = event.target;
            if (checkbox.checked) {
                selectedProductIds.add(productId);
            } else {
                selectedProductIds.delete(productId);
            }
            renderProductSelector();
        }

        function toggleProductRow(productId, event) {
            if (event.target.type === 'checkbox') return;
            
            if (selectedProductIds.has(productId)) {
                selectedProductIds.delete(productId);
            } else {
                selectedProductIds.add(productId);
            }
            renderProductSelector();
        }

        function toggleSelectAll() {
            const checkbox = document.getElementById('selectAllProducts');
            if (checkbox.checked) {
                allProducts.forEach(p => selectedProductIds.add(p.product_id || p.id));
            } else {
                selectedProductIds.clear();
            }
            renderProductSelector();
        }

        function filterProducts() {
            const search = document.getElementById('productSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#productSelectorBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        }

        function addSelectedProducts() {
            if (selectedProductIds.size === 0) {
                showAlert('Vui lòng chọn ít nhất 1 sản phẩm', 'danger');
                return;
            }

            stockData = allProducts.filter(p => selectedProductIds.has(p.product_id || p.id));
            renderTable();
            closeProductSelector();
            
            document.getElementById('btnSaveComplete').disabled = false;
            showAlert(`Đã thêm ${stockData.length} sản phẩm vào phiếu kiểm kê`, 'success');
        }

        async function loadAllStock() {
            const from = document.getElementById('filterFrom').value;
            const to = document.getElementById('filterTo').value;
            
            try {
                document.getElementById('inventoryBody').innerHTML = '<tr><td colspan="8" class="loading">Đang tải dữ liệu...</td></tr>';
                
                const url = `inventory/createInventory_sheet/process.php?action=get_stock&from=${from}&to=${to}`;
                console.log('Loading stock from:', url);
                
                const response = await fetch(url);
                const text = await response.text();
                console.log('Raw response:', text);
                
                const result = JSON.parse(text);
                console.log('Parsed result:', result);
                
                if (result.ok && result.data) {
                    stockData = result.data;
                    console.log('Stock data loaded:', stockData.length, 'items');
                    renderTable();
                    document.getElementById('btnSaveComplete').disabled = false;
                    showAlert(`Đã tải ${stockData.length} mặt hàng từ hệ thống`, 'success');
                } else {
                    console.error('Load failed:', result);
                    showAlert('Không thể tải dữ liệu: ' + (result.error || 'Lỗi không xác định'), 'danger');
                }
            } catch (error) {
                console.error('Load stock error:', error);
                showAlert('Lỗi khi tải dữ liệu: ' + error.message, 'danger');
            }
        }

        function renderTable() {
            const tbody = document.getElementById('inventoryBody');
            if (stockData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="loading">Không có dữ liệu</td></tr>';
                return;
            }

            tbody.innerHTML = stockData.map((item, index) => `
                <tr>
                    <td>${index + 1}</td>
                    <td>${escapeHtml(item.product_sku || '')}</td>
                    <td>${escapeHtml(item.product_name || '')}</td>
                    <td>${escapeHtml(item.unit || 'cái')}</td>
                    <td style="text-align: center; font-weight: 600;">${formatNumber(item.system_qty)}</td>
                    <td style="text-align: center;">
                        <input type="number" class="qty-input" value="${item.actual_qty || 0}" 
                               onchange="updateActualQty(${index}, this.value)" step="1" min="0">
                    </td>
                    <td style="text-align: center;" id="diff-${index}" class="diff-zero">0</td>
                    <td>
                        <input type="text" class="note-input" value="${escapeHtml(item.note || '')}" 
                               onchange="updateNote(${index}, this.value)">
                    </td>
                </tr>
            `).join('');

            updateStats();
        }

        function updateActualQty(index, value) {
            const actualQty = parseFloat(value) || 0;
            stockData[index].actual_qty = actualQty;
            stockData[index].difference = actualQty - stockData[index].system_qty;
            
            const diffCell = document.getElementById(`diff-${index}`);
            const diff = stockData[index].difference;
            diffCell.textContent = formatNumber(diff);
            
            if (diff > 0) {
                diffCell.className = 'diff-positive';
            } else if (diff < 0) {
                diffCell.className = 'diff-negative';
            } else {
                diffCell.className = 'diff-zero';
            }

            updateStats();
        }

        function updateNote(index, value) {
            stockData[index].note = value;
        }

        function updateStats() {
            let positive = 0, negative = 0, zero = 0;
            
            stockData.forEach(item => {
                if (item.difference > 0) positive++;
                else if (item.difference < 0) negative++;
                else zero++;
            });

            document.getElementById('statsBox').style.display = 'grid';
            document.getElementById('statTotal').textContent = stockData.length;
            document.getElementById('statPositive').textContent = positive;
            document.getElementById('statNegative').textContent = negative;
            document.getElementById('statZero').textContent = zero;
        }

        async function saveSheet(status) {
            // Prevent double submission
            if (isSaving) {
                showAlert('⚠️ Đang lưu phiếu, vui lòng chờ...', 'danger');
                return;
            }

            const note = document.getElementById('sheetNote').value;
            
            if (stockData.length === 0) {
                showAlert('⚠️ Vui lòng nhấn "Tải dữ liệu" trước khi lưu phiếu!', 'danger');
                return;
            }

            // Set saving flag and disable button to prevent double-click
            isSaving = true;
            const btnComplete = document.getElementById('btnSaveComplete');
            const originalCompleteText = btnComplete.innerHTML;
            
            btnComplete.disabled = true;
            btnComplete.innerHTML = '⏳ Đang lưu...';

            console.log('Saving sheet with status:', status);
            console.log('Stock data:', stockData);

            try {
                const data = {
                    action: currentSheetId ? 'update_items' : 'create',
                    sheet_id: currentSheetId,
                    items: stockData,
                    note: note,
                    status: status,
                    count_date: document.getElementById('filterTo').value
                };
                
                console.log('Sending data:', data);

                const response = await fetch('inventory/createInventory_sheet/process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                
                if (result.ok) {
                    if (result.sheet_id) {
                        currentSheetId = result.sheet_id;
                        document.getElementById('sheetInfo').style.display = 'block';
                        document.getElementById('sheetCode').textContent = 'INV-' + currentSheetId.substring(0, 8);
                        document.getElementById('sheetDate').textContent = new Date().toLocaleString('vi-VN');
                    }
                    
                    showAlert('Đã hoàn thành phiếu kiểm kê thành công!', 'success');
                    
                    if (status === 'completed') {
                        setTimeout(() => {
                            showAlert('✅ Phiếu đã hoàn thành! Vui lòng chờ quản lý duyệt để áp dụng vào hệ thống.', 'success');
                            setTimeout(() => {
                                window.location.href = 'index.php?page=inventory/inventory_sheets';
                            }, 2000);
                        }, 1000);
                    }
                } else {
                    showAlert('Lỗi: ' + (result.error || 'Không thể lưu phiếu'), 'danger');
                    // Re-enable button on error
                    isSaving = false;
                    btnComplete.disabled = false;
                    btnComplete.innerHTML = originalCompleteText;
                }
            } catch (error) {
                console.error('Save sheet error:', error);
                showAlert('Lỗi khi lưu phiếu: ' + error.message, 'danger');
                // Re-enable button on error
                isSaving = false;
                btnComplete.disabled = false;
                btnComplete.innerHTML = originalCompleteText;
            }
        }

        function clearData() {
            if (confirm('Bạn có chắc muốn xóa toàn bộ dữ liệu?')) {
                stockData = [];
                currentSheetId = null;
                renderTable();
                document.getElementById('statsBox').style.display = 'none';
                document.getElementById('sheetInfo').style.display = 'none';
                document.getElementById('sheetNote').value = '';
                document.getElementById('btnSaveComplete').disabled = true;
                showAlert('Đã xóa dữ liệu', 'info');
            }
        }

        function formatNumber(num) {
            return parseFloat(num || 0).toLocaleString('vi-VN');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Set default date to today
        document.getElementById('filterTo').value = new Date().toISOString().split('T')[0];
    </script>
</div>
