<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../../controller/cInventorySheet.php');

$cSheet = new CInventorySheet();

// Kiểm tra quyền: chỉ quản lý mới được duyệt/từ chối
// role_id: 1=Admin, 2=QL_Kho_Tong, 4=QL_Kho_CN
$roleId = $_SESSION['login']['role_id'] ?? null;
$isManager = in_array($roleId, [1, 2, 4]); // Admin, QL_Kho_Tong, QL_Kho_CN

// Get current user's warehouse
$currentWarehouse = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? '';

// Get filters
$q = $_GET['q'] ?? '';
$status = $_GET['status'] ?? '';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$p = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$limit = isset($_GET['limit']) ? max(1, min(200, intval($_GET['limit']))) : 20;

// Load data
$result = $cSheet->listSheets([
    'q' => $q,
    'status' => $status,
    'from' => $from,
    'to' => $to,
    'page' => $p,
    'limit' => $limit
]);

$items = $result['items'] ?? [];
$total = $result['total'] ?? 0;
$pages = $result['pages'] ?? 1;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmt_dt($ts) {
    try {
        if ($ts instanceof MongoDB\BSON\UTCDateTime) {
            $dt = $ts->toDateTime();
        } elseif ($ts instanceof DateTime) {
            $dt = $ts;
        } elseif (is_array($ts) && isset($ts['$date'])) {
            // Handle MongoDB datetime as array
            $timestamp = $ts['$date'];
            if (is_numeric($timestamp)) {
                $dt = (new DateTime())->setTimestamp($timestamp / 1000);
            } else {
                $dt = new DateTime($timestamp);
            }
        } elseif (is_numeric($ts)) {
            $dt = (new DateTime())->setTimestamp((int)$ts);
        } else {
            $dt = new DateTime((string)$ts);
        }
        $dt->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dt->format('d/m/Y H:i');
    } catch (Throwable $e) {
        error_log('fmt_dt error: ' . $e->getMessage() . ', input: ' . json_encode($ts));
        return '';
    }
}

function getStatusBadge($status) {
    // 0 = draft, 1 = completed (chờ duyệt), 2 = approved, 3 = rejected
    switch ((int)$status) {
        case 0:
            return '<span class="badge badge-secondary">📝 Nháp</span>';
        case 1:
            return '<span class="badge badge-warning">⏳ Chờ duyệt</span>';
        case 2:
            return '<span class="badge badge-success">✔️ Đã hoàn thành</span>';
        case 3:
            return '<span class="badge badge-danger">❌ Từ chối</span>';
        default:
            return '<span class="badge badge-light">❓ Không rõ</span>';
    }
}

function buildUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    $params['page'] = 'inventory/inventory_sheets';
    return 'index.php?' . http_build_query($params);
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    .sheets-container {
        max-width: 1400px;
        margin: 20px auto;
        background: #fff;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    }
    .sheets-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 16px;
    }
    .sheets-title {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .sheets-filters {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .sheets-filters input,
    .sheets-filters select {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
    }
    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.2s;
    }
    .btn-primary {
        background: #2563eb;
        color: #fff;
    }
    .btn-primary:hover {
        background: #1d4ed8;
    }
    .btn-success {
        background: #059669;
        color: #fff;
    }
    .btn-success:hover {
        background: #047857;
    }
    .btn-secondary {
        background: #6b7280;
        color: #fff;
    }
    .btn-secondary:hover {
        background: #4b5563;
    }
    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
    }
    .sheets-table {
        width: 100%;
        border-collapse: collapse;
    }
    .sheets-table th,
    .sheets-table td {
        padding: 12px;
        border: 1px solid #e5e7eb;
        text-align: left;
        font-size: 14px;
    }
    .sheets-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #111827;
    }
    .sheets-table tbody tr:hover {
        background: #f9fafb;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    .badge-secondary {
        background: #e5e7eb;
        color: #374151;
    }
    .badge-warning {
        background: #fef3c7;
        color: #92400e;
    }
    .badge-success {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-light {
        background: #f3f4f6;
        color: #6b7280;
    }
    .pagination {
        display: flex;
        gap: 6px;
        justify-content: flex-end;
        align-items: center;
        margin-top: 16px;
    }
    .page-link {
        padding: 6px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        text-decoration: none;
        color: #111827;
    }
    .page-link.active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }
    .btn-icon {
        border: none;
        padding: 6px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        margin-right: 4px;
        transition: opacity 0.2s;
    }
    .btn-icon:hover {
        opacity: 0.9;
    }
    .btn-view {
        background: #17a2b8;
        color: #fff;
    }
    .btn-approve {
        background: #28a745;
        color: #fff;
    }
    .btn-reject {
        background: #dc3545;
        color: #fff;
    }
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1000;
        background: rgba(0, 0, 0, 0.5);
    }
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background: #fff;
        border-radius: 12px;
        padding: 24px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
    }
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: #374151;
    }
    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
    }
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    .location-selection-container {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 16px;
        min-height: 400px;
        max-height: 600px;
    }
    .product-list-panel {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        background: #f9fafb;
        overflow-y: auto;
    }
    .product-item {
        padding: 12px;
        margin-bottom: 8px;
        border: 2px solid #d1d5db;
        border-radius: 8px;
        background: #fff;
        cursor: pointer;
        transition: all 0.2s;
    }
    .product-item:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .product-item.active {
        border-color: #3b82f6;
        background: #dbeafe;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }
    .product-item.completed {
        border-color: #10b981;
        background: #d1fae5;
    }
    .product-name {
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
    }
    .product-diff {
        font-size: 18px;
        font-weight: 700;
    }
    .diff-positive { color: #059669; }
    .diff-negative { color: #dc2626; }
    .location-panel {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        background: #fff;
        overflow-y: auto;
    }
    .location-instruction {
        padding: 12px;
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        border-radius: 6px;
        margin-bottom: 12px;
        font-size: 14px;
    }
    .bin.selectable {
        cursor: pointer;
        transition: all 0.2s;
    }
    .bin.selectable:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .bin.selected {
        outline: 3px solid #3b82f6;
        box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.2);
    }
    .quantity-input-panel {
        margin-top: 16px;
        padding: 12px;
        background: #eff6ff;
        border: 2px solid #3b82f6;
        border-radius: 8px;
        position: relative;
        z-index: 10;
    }
    .quantity-input-panel button {
        cursor: pointer;
        pointer-events: auto;
    }
</style>

<div class="sheets-container">
    <div class="sheets-header">
        <h2 class="sheets-title">📋 Danh sách phiếu kiểm kê</h2>
        <div>
            <a href="index.php?page=inventory/createInventory_sheet" class="btn btn-success">➕ Tạo phiếu mới</a>
            <a href="index.php?page=inventory" class="btn btn-secondary">⬅ Quay lại</a>
        </div>
    </div>

    <?php if (!$isManager) { ?>
        <div style="padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            <strong>ℹ️ Lưu ý:</strong> Bạn có thể tạo và xem phiếu kiểm kê. Chỉ <strong>Quản lý</strong> mới có quyền duyệt/từ chối phiếu.
        </div>
    <?php } else { ?>
        <div style="padding: 12px; background: #d1fae5; border-left: 4px solid #059669; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
            <strong>✅ Vai trò:</strong> Bạn là <strong>Quản lý</strong> - Có quyền duyệt/từ chối các phiếu kiểm kê đã hoàn thành.
        </div>
    <?php } ?>

    <div id="alertBox"></div>

    <form method="get" action="index.php">
        <input type="hidden" name="page" value="inventory/inventory_sheets">
        <div class="sheets-filters">
            <input type="text" name="q" placeholder="Tìm kiếm (mã phiếu, ghi chú...)" value="<?= h($q) ?>" style="flex: 1; min-width: 200px;">
            <select name="status">
                <option value="">-- Tất cả trạng thái --</option>
                <option value="1" <?= $status === '1' ? 'selected' : '' ?>>⏳ Chờ duyệt</option>
                <option value="2" <?= $status === '2' ? 'selected' : '' ?>>✔️ Đã hoàn thành</option>
                <option value="3" <?= $status === '3' ? 'selected' : '' ?>>❌ Từ chối</option>
            </select>
            <label>Từ: <input type="date" name="from" value="<?= h($from) ?>"></label>
            <label>Đến: <input type="date" name="to" value="<?= h($to) ?>"></label>
            <select name="limit">
                <?php foreach ([10, 20, 50, 100] as $opt) { ?>
                    <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?> / trang</option>
                <?php } ?>
            </select>
            <button type="submit" class="btn btn-primary">🔍 Lọc</button>
        </div>
    </form>

    <table class="sheets-table">
        <thead>
            <tr>
                <th>Mã phiếu</th>
                <th>Ngày tạo</th>
                <th>Người tạo</th>
                <th>Ngày kiểm kê</th>
                <th>Trạng thái</th>
                <th>Người duyệt</th>
                <th>Ngày duyệt</th>
                <th>Ghi chú</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)) {
                foreach ($items as $item) {
                    $sheetCode = $item['sheet_code'] ?? '';
                    $sheetId = isset($item['_id']) ? (string)$item['_id'] : '';
                    $createdAt = isset($item['created_at']) ? fmt_dt($item['created_at']) : '';
                    $createdBy = $item['created_by_name'] ?? '';
                    $countDate = isset($item['count_date']) ? fmt_dt($item['count_date']) : '';
                    $statusVal = $item['status'] ?? 'draft';
                    $approvedBy = $item['approved_by_name'] ?? '';
                    $approvedAt = isset($item['approved_at']) ? fmt_dt($item['approved_at']) : '';
                    
                    // Show approve note if approved/rejected, otherwise show sheet note
                    $note = '';
                    if (in_array((int)$statusVal, [2, 3])) {
                        $note = $item['approve_note'] ?? $item['reject_note'] ?? '';
                    }
                    if (empty($note)) {
                        $note = $item['note'] ?? '';
                    }
                    
                    $itemsCount = isset($item['items']) && is_array($item['items']) ? count($item['items']) : 0;
            ?>
                    <tr>
                        <td><strong><?= h($sheetCode) ?></strong><br><small><?= $itemsCount ?> mặt hàng</small></td>
                        <td><?= h($createdAt) ?></td>
                        <td><?= h($createdBy) ?></td>
                        <td><?= h($countDate) ?></td>
                        <td><?= getStatusBadge($statusVal) ?></td>
                        <td><?= h($approvedBy) ?></td>
                        <td><?= h($approvedAt) ?></td>
                        <td><?= h($note) ?></td>
                        <td>
                            <button class="btn-icon btn-view" onclick="viewSheet('<?= h($sheetId) ?>')" title="Xem chi tiết">
                                <i class="fas fa-eye"></i>
                            </button>
                            
                            <?php if ($isManager && (int)$statusVal === 1) { ?>
                                <button class="btn-icon btn-approve" onclick="openApproveModal('<?= h($sheetId) ?>')" title="Duyệt phiếu">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn-icon btn-reject" onclick="rejectSheet('<?= h($sheetId) ?>')" title="Từ chối">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php } ?>
                        </td>
                    </tr>
            <?php }
            } else { ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px; color: #6b7280;">Không có phiếu kiểm kê nào</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <div class="pagination">
        <span>Tổng: <?= number_format($total) ?> phiếu</span>
        <?php if ($pages > 1) { ?>
            <a class="page-link" href="<?= buildUrl(['p' => 1]) ?>">« Đầu</a>
            <?php
            $start = max(1, $p - 2);
            $end = min($pages, $p + 2);
            for ($i = $start; $i <= $end; $i++) {
                $active = $i == $p ? 'active' : '';
                echo '<a class="page-link ' . $active . '" href="' . buildUrl(['p' => $i]) . '">' . $i . '</a>';
            }
            ?>
            <a class="page-link" href="<?= buildUrl(['p' => $pages]) ?>">Cuối »</a>
        <?php } ?>
    </div>
</div>

<!-- Modal xem chi tiết -->
<div class="modal" id="viewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Chi tiết phiếu kiểm kê</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeModal()">✕</button>
        </div>
        <div id="modalBody">
            <p>Đang tải...</p>
        </div>
    </div>
</div>

<!-- Modal duyệt phiếu -->
<div class="modal" id="approveModal">
    <div class="modal-content" style="max-width: 1200px;">
        <div class="modal-header">
            <h3 class="modal-title">Duyệt phiếu kiểm kê - Chọn vị trí điều chỉnh</h3>
            <button class="btn btn-secondary btn-sm" onclick="closeApproveModal()">✕</button>
        </div>
        
        <div id="locationSelectionArea" style="display: none;">
            <div class="location-selection-container">
                <!-- Bên trái: Danh sách sản phẩm -->
                <div class="product-list-panel">
                    <h4 style="margin: 0 0 12px; font-size: 16px;">Sản phẩm cần điều chỉnh</h4>
                    <div id="productList"></div>
                </div>
                
                <!-- Bên phải: Sơ đồ kho -->
                <div class="location-panel">
                    <div id="locationInstruction" class="location-instruction" style="display: none;">
                        📍 Chọn vị trí để <strong id="actionText"></strong>
                    </div>
                    <div id="warehouseMap">
                        <div style="text-align: center; padding: 40px; color: #6b7280;">
                            ← Chọn sản phẩm bên trái để bắt đầu
                        </div>
                    </div>
                    <div id="quantityInputPanel" style="display: none;" class="quantity-input-panel">
                        <div id="quantityLabel" style="font-weight: 600; display: block; margin-bottom: 8px;">
                            Số lượng cần <span id="adjustAction"></span>:
                        </div>
                        <div id="quantityHint"></div>
                        <input type="number" id="adjustQuantity" class="form-control" min="1" placeholder="Nhập số lượng">
                        <button type="button" class="btn btn-primary" id="btnConfirmLocation" style="margin-top: 8px; width: 100%;">
                            ✔️ Xác nhận vị trí này
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 16px;">
            <label>Ghi chú duyệt:</label>
            <textarea id="approveNote" class="form-control" rows="3" placeholder="Nhập ghi chú khi duyệt (tùy chọn)"></textarea>
        </div>
        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-secondary" onclick="closeApproveModal()">Hủy</button>
            <button class="btn btn-success" id="btnConfirmApprove" onclick="confirmApproveWithLocations()">✔️ Xác nhận duyệt</button>
        </div>
    </div>
</div>

<script>
    let currentSheetId = null;
    let currentSheetData = null;
    let locationAssignments = {}; // {product_id: [{zone, rack, bin, qty}]}
    let itemsNeedLocation = [];
    let currentSelectedProduct = null;
    let currentSelectedBin = null;
    let warehouseLocationsHTML = '';

    function showAlert(message, type = 'info') {
        const alertBox = document.getElementById('alertBox');
        const className = type === 'success' ? 'alert-success' : 'alert-danger';
        alertBox.innerHTML = `<div class="alert ${className}">${message}</div>`;
        setTimeout(() => {
            alertBox.innerHTML = '';
        }, 5000);
    }

    async function viewSheet(sheetId) {
        try {
            const response = await fetch(`/KLTN/view/page/manage/inventory/createInventory_sheet/process.php?action=get_sheet&id=${sheetId}`);
            const result = await response.json();

            if (result.ok && result.data) {
                const sheet = result.data;
                const items = sheet.items || [];
                
                let html = `
                    <p><strong>Mã phiếu:</strong> ${sheet.sheet_code || ''}</p>
                    <p><strong>Người tạo:</strong> ${sheet.created_by_name || ''}</p>
                    <p><strong>Ngày tạo:</strong> ${formatDate(sheet.created_at)}</p>
                    <p><strong>Ngày kiểm kê:</strong> ${formatDate(sheet.count_date) || 'Chưa có'}</p>
                    <p><strong>Trạng thái:</strong> ${getStatusText(sheet.status)}</p>
                    ${sheet.approved_by_name ? `<p><strong>Người duyệt:</strong> ${sheet.approved_by_name}</p>` : ''}
                    ${sheet.approved_at ? `<p><strong>Ngày duyệt:</strong> ${formatDate(sheet.approved_at)}</p>` : ''}
                    <p><strong>Ghi chú:</strong> ${sheet.note || 'Không có'}</p>
                    ${sheet.approve_note ? `<p><strong>Ghi chú duyệt:</strong> ${sheet.approve_note}</p>` : ''}
                    ${sheet.reject_note ? `<p><strong>Lý do từ chối:</strong> ${sheet.reject_note}</p>` : ''}
                    <hr>
                    <h4>Danh sách hàng hóa (${items.length} mặt hàng)</h4>
                    <table class="sheets-table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Tên SP</th>
                                <th>SL Hệ thống</th>
                                <th>SL Thực tế</th>
                                <th>Chênh lệch</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                items.forEach(item => {
                    const diff = (item.actual_qty || 0) - (item.system_qty || 0);
                    const diffClass = diff > 0 ? 'color: #059669' : (diff < 0 ? 'color: #dc2626' : 'color: #6b7280');
                    html += `
                        <tr>
                            <td>${item.product_sku || ''}</td>
                            <td>${item.product_name || ''}</td>
                            <td style="text-align: center;">${item.system_qty || 0}</td>
                            <td style="text-align: center;">${item.actual_qty || 0}</td>
                            <td style="text-align: center; font-weight: 600; ${diffClass}">${diff}</td>
                        </tr>
                    `;
                });

                html += `
                        </tbody>
                    </table>
                `;

                document.getElementById('modalBody').innerHTML = html;
                document.getElementById('viewModal').classList.add('show');
            } else {
                showAlert('Không thể tải chi tiết phiếu', 'danger');
            }
        } catch (error) {
            console.error('View sheet error:', error);
            showAlert('Lỗi: ' + error.message, 'danger');
        }
    }

    function closeModal() {
        document.getElementById('viewModal').classList.remove('show');
    }

    function openApproveModal(sheetId) {
        currentSheetId = sheetId;
        locationAssignments = {};
        itemsNeedLocation = [];
        currentSelectedProduct = null;
        currentSelectedBin = null;
        
        // Load sheet data to check items with differences
        fetch(`/KLTN/view/page/manage/inventory/createInventory_sheet/process.php?action=get_sheet&id=${sheetId}`)
            .then(res => res.json())
            .then(result => {
                if (result.ok && result.data) {
                    currentSheetData = result.data;
                    const items = result.data.items || [];
                    itemsNeedLocation = items.filter(item => {
                        const diff = parseFloat(item.difference || 0);
                        return diff !== 0;
                    });
                    
                    if (itemsNeedLocation.length > 0) {
                        loadLocationSelection(itemsNeedLocation);
                    }
                    
                    document.getElementById('approveModal').classList.add('show');
                } else {
                    showAlert('Không thể tải thông tin phiếu', 'danger');
                }
            })
            .catch(error => {
                console.error('Load sheet error:', error);
                showAlert('Lỗi: ' + error.message, 'danger');
            });
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.remove('show');
        currentSheetId = null;
        currentSheetData = null;
        locationAssignments = {};
    }
    
    async function loadLocationSelection(items) {
        itemsNeedLocation = items;
        
        // Render product list
        let html = '';
        items.forEach((item, index) => {
            const diff = parseFloat(item.difference || 0);
            const diffText = diff > 0 ? `+${diff}` : `${diff}`;
            const diffClass = diff > 0 ? 'diff-positive' : 'diff-negative';
            const action = diff > 0 ? '(Thêm)' : '(Trừ)';
            const isCompleted = locationAssignments[item.product_id] && locationAssignments[item.product_id].length > 0;
            
            html += `
                <div class="product-item ${isCompleted ? 'completed' : ''}" 
                     data-product-id="${item.product_id}"
                     data-index="${index}"
                     onclick="selectProduct('${item.product_id}', ${index})">
                    <div class="product-name">${item.product_name || item.product_sku}</div>
                    <div>
                        <span class="product-diff ${diffClass}">${diffText}</span>
                        <span style="color: #6b7280; font-size: 13px; margin-left: 8px;">${action}</span>
                    </div>
                    ${isCompleted ? '<div style="margin-top: 4px; color: #059669; font-size: 12px;">✓ Đã chọn vị trí</div>' : ''}
                </div>
            `;
        });
        
        document.getElementById('productList').innerHTML = html;
        document.getElementById('locationSelectionArea').style.display = 'block';
        
        // Load warehouse map once
        const warehouseId = currentSheetData?.warehouse_id || '';
        try {
            const response = await fetch(`/KLTN/view/page/manage/locations/ajax_get_locations.php?warehouse_id=${warehouseId}`);
            warehouseLocationsHTML = await response.text();
        } catch (error) {
            console.error('Load locations error:', error);
            warehouseLocationsHTML = '<div style="color: #dc2626;">Lỗi tải sơ đồ kho</div>';
        }
    }
    
    function selectProduct(productId, index) {
        currentSelectedProduct = itemsNeedLocation[index];
        currentSelectedBin = null;
        
        // Highlight active product
        document.querySelectorAll('.product-item').forEach(el => el.classList.remove('active'));
        document.querySelector(`[data-product-id="${productId}"]`).classList.add('active');
        
        // Show warehouse map
        const diff = parseFloat(currentSelectedProduct.difference || 0);
        const action = diff > 0 ? 'THÊM sản phẩm' : 'TRỪ sản phẩm';
        const actionVerb = diff > 0 ? 'thêm' : 'trừ';
        
        document.getElementById('actionText').textContent = action;
        document.getElementById('adjustAction').textContent = actionVerb;
        document.getElementById('locationInstruction').style.display = 'block';
        document.getElementById('warehouseMap').innerHTML = warehouseLocationsHTML;
        document.getElementById('quantityInputPanel').style.display = 'none';
        
        // Make bins selectable based on action
        let visibleCount = 0;
        document.querySelectorAll('.bin').forEach(bin => {
            const binQty = parseInt(bin.dataset.quantity || 0);
            const binProduct = bin.dataset.product || ''; // product ID from data-product
            
            if (diff < 0) {
                // TRỪ: Chỉ hiện vị trí có sản phẩm này VÀ có số lượng > 0
                if (binProduct === productId && binQty > 0) {
                    bin.classList.add('selectable');
                    bin.style.display = 'block';
                    bin.style.border = '2px solid #3b82f6'; // Highlight bins có sản phẩm
                    bin.addEventListener('click', function(e) {
                        e.stopPropagation();
                        selectBin(this);
                    });
                    visibleCount++;
                } else {
                    bin.style.display = 'none';
                }
            } else {
                // THÊM: Hiện tất cả vị trí
                bin.classList.add('selectable');
                bin.style.display = 'block';
                bin.style.border = '1px solid #e5e7eb';
                bin.addEventListener('click', function(e) {
                    e.stopPropagation();
                    selectBin(this);
                });
                visibleCount++;
            }
        });
        
        // Show warning if no bins found for subtract
        if (diff < 0 && visibleCount === 0) {
            document.getElementById('warehouseMap').innerHTML = `
                <div style="padding: 40px; text-align: center; color: #dc2626; background: #fee2e2; border-radius: 8px; border: 1px solid #fca5a5;">
                    <strong>⚠️ Không tìm thấy vị trí nào chứa sản phẩm này</strong>
                    <div style="margin-top: 8px; font-size: 14px;">Sản phẩm "${currentSelectedProduct.product_name}" không có trong kho</div>
                </div>
            `;
        }
    }
    
    function selectBin(binElement) {
        const binCurrentQty = parseInt(binElement.dataset.quantity || 0);
        const diff = parseFloat(currentSelectedProduct.difference || 0);
        
        currentSelectedBin = {
            zone_id: binElement.dataset.zone,
            rack_id: binElement.dataset.rack,
            bin_id: binElement.dataset.bin,
            bin_code: binElement.dataset.code,
            current_qty: binCurrentQty
        };
        
        // Highlight selected bin
        document.querySelectorAll('.bin').forEach(el => el.classList.remove('selected'));
        binElement.classList.add('selected');
        
        // Calculate max quantity and message based on action
        let maxQty;
        let hintText = '';
        const actionVerb = diff > 0 ? 'thêm' : 'trừ';
        
        if (diff < 0) {
            // TRỪ: Không được trừ nhiều hơn số lượng hiện tại ở vị trí
            maxQty = Math.min(Math.abs(diff), binCurrentQty);
            if (maxQty === 0) {
                showAlert('Vị trí này không có sản phẩm để trừ', 'danger');
                return;
            }
            hintText = `<div style="font-size: 12px; color: #dc2626; margin-top: 4px; margin-bottom: 8px;">⚠️ Vị trí này có ${binCurrentQty} sản phẩm. Tối đa trừ ${maxQty}</div>`;
        } else {
            // THÊM: Chỉ giới hạn theo số chênh lệch
            maxQty = Math.abs(diff);
            hintText = `<div style="font-size: 12px; color: #059669; margin-top: 4px; margin-bottom: 8px;">✓ Vị trí hiện có ${binCurrentQty} sản phẩm. Bạn có thể thêm tối đa ${maxQty}</div>`;
        }
        
        // Update label and hint
        document.getElementById('adjustAction').textContent = actionVerb;
        document.getElementById('quantityHint').innerHTML = hintText;
        
        // Update input
        const inputElement = document.getElementById('adjustQuantity');
        inputElement.value = maxQty;
        inputElement.max = maxQty;
        inputElement.min = 1;
        
        // Show panel
        document.getElementById('quantityInputPanel').style.display = 'block';
    }
    
    function confirmLocationSelection() {
        if (!currentSelectedProduct || !currentSelectedBin) {
            showAlert('Vui lòng chọn vị trí', 'danger');
            return;
        }
        
        const qty = parseFloat(document.getElementById('adjustQuantity').value || 0);
        const diff = parseFloat(currentSelectedProduct.difference || 0);
        const binCurrentQty = currentSelectedBin.current_qty;
        const absDiff = Math.abs(diff);
        
        if (qty <= 0) {
            showAlert('Vui lòng nhập số lượng hợp lệ', 'danger');
            return;
        }
        
        // Validation khác nhau cho THÊM và TRỪ
        if (diff < 0) {
            // TRỪ: Kiểm tra không được trừ quá số lượng tại vị trí
            const maxSubtract = Math.min(absDiff, binCurrentQty);
            if (qty > maxSubtract) {
                showAlert(`Vị trí ${currentSelectedBin.bin_code} chỉ có ${binCurrentQty} sản phẩm. Tối đa trừ ${maxSubtract}`, 'danger');
                return;
            }
        } else {
            // THÊM: Chỉ kiểm tra không vượt quá chênh lệch
            if (qty > absDiff) {
                showAlert(`Số lượng không được vượt quá ${absDiff}`, 'danger');
                return;
            }
        }
        
        // Save assignment
        const productId = currentSelectedProduct.product_id;
        if (!locationAssignments[productId]) {
            locationAssignments[productId] = [];
        }
        
        locationAssignments[productId].push({
            ...currentSelectedBin,
            adjust_qty: qty
        });
        
        // Update product item display
        const productItem = document.querySelector(`[data-product-id="${productId}"]`);
        productItem.classList.add('completed');
        
        // Check if all quantity is assigned
        const totalAssigned = locationAssignments[productId].reduce((sum, loc) => sum + loc.adjust_qty, 0);
        const remainingQty = absDiff - totalAssigned;
        
        if (remainingQty > 0) {
            showAlert(`Đã chọn ${qty} cho vị trí ${currentSelectedBin.bin_code}. Còn lại ${remainingQty} cần chọn vị trí`, 'success');
            // Continue selecting for remaining quantity
            document.getElementById('adjustQuantity').value = remainingQty;
            document.getElementById('adjustQuantity').max = remainingQty;
            document.querySelectorAll('.bin').forEach(el => el.classList.remove('selected'));
            currentSelectedBin = null;
        } else {
            showAlert(`✓ Đã hoàn thành chọn vị trí cho sản phẩm: ${currentSelectedProduct.product_name}`, 'success');
            // Reset and move to next product
            currentSelectedProduct = null;
            currentSelectedBin = null;
            document.getElementById('warehouseMap').innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;">← Chọn sản phẩm bên trái để tiếp tục</div>';
            document.getElementById('locationInstruction').style.display = 'none';
            document.getElementById('quantityInputPanel').style.display = 'none';
        }
    }
    
    async function confirmApproveWithLocations() {
        if (!currentSheetId) return;
        
        // Check if all items have location assigned
        for (const item of itemsNeedLocation) {
            const productId = item.product_id;
            const assignments = locationAssignments[productId] || [];
            const totalAssigned = assignments.reduce((sum, loc) => sum + loc.adjust_qty, 0);
            const requiredQty = Math.abs(parseFloat(item.difference || 0));
            
            if (totalAssigned < requiredQty) {
                showAlert(`Sản phẩm "${item.product_name}" còn thiếu ${requiredQty - totalAssigned} chưa chọn vị trí`, 'danger');
                return;
            }
        }
        
        // Disable button to prevent double submit
        const btn = document.getElementById('btnConfirmApprove');
        btn.disabled = true;
        btn.innerHTML = '⏳ Đang xử lý...';
        
        try {
            const note = document.getElementById('approveNote').value;
            const response = await fetch('/KLTN/view/page/manage/inventory/inventory_sheets/process.php?action=approve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'approve',
                    sheet_id: currentSheetId,
                    note: note,
                    locations: locationAssignments
                })
            });

            const text = await response.text();
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                showAlert('Lỗi: Server trả về dữ liệu không hợp lệ. Chi tiết: ' + text.substring(0, 200), 'danger');
                btn.disabled = false;
                btn.innerHTML = '✔️ Xác nhận duyệt';
                return;
            }

            if (result.ok) {
                const message = result.message || 'Đã duyệt phiếu và cập nhật tồn kho thành công!';
                showAlert('✅ ' + message, 'success');
                closeApproveModal();
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showAlert('Lỗi: ' + (result.error || 'Không thể duyệt phiếu'), 'danger');
            }
        } catch (error) {
            console.error('Approve error:', error);
            showAlert('Lỗi: ' + error.message, 'danger');
        }
    }

    async function rejectSheet(sheetId) {
        const note = prompt('Lý do từ chối:');
        if (!note) return;

        try {
            const response = await fetch('/KLTN/view/page/manage/inventory/inventory_sheets/process.php?action=reject', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reject',
                    sheet_id: sheetId,
                    note: note
                })
            });

            const result = await response.json();

            if (result.ok) {
                showAlert('Đã từ chối phiếu', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('Lỗi: ' + (result.error || 'Không thể từ chối phiếu'), 'danger');
            }
        } catch (error) {
            console.error('Reject error:', error);
            showAlert('Lỗi: ' + error.message, 'danger');
        }
    }

    function formatDate(dateObj) {
        if (!dateObj) return '';
        try {
            let timestamp;
            
            // Handle MongoDB BSON UTCDateTime format
            if (typeof dateObj === 'object' && dateObj.$date) {
                if (typeof dateObj.$date === 'object' && dateObj.$date.$numberLong) {
                    // MongoDB Extended JSON v2 format: {$date: {$numberLong: "1234567890"}}
                    timestamp = parseInt(dateObj.$date.$numberLong);
                } else if (typeof dateObj.$date === 'number') {
                    // Numeric timestamp
                    timestamp = dateObj.$date;
                } else if (typeof dateObj.$date === 'string') {
                    // ISO date string
                    return new Date(dateObj.$date).toLocaleString('vi-VN', {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
            } else if (typeof dateObj === 'number') {
                // Direct timestamp
                timestamp = dateObj;
            } else if (typeof dateObj === 'string') {
                // ISO string or other date string
                return new Date(dateObj).toLocaleString('vi-VN', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            // If we have a timestamp, convert it to date
            if (timestamp) {
                const d = new Date(timestamp);
                return d.toLocaleString('vi-VN', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
            
            return '';
        } catch (e) {
            console.error('formatDate error:', e, 'input:', dateObj);
            return '';
        }
    }

    function getStatusText(status) {
        const map = {
            0: '📝 Nháp',
            1: '⏳ Chờ duyệt',
            2: '✔️ Đã hoàn thành',
            3: '❌ Từ chối'
        };
        return map[status] || '❓ Không rõ';
    }

    // Event listener for confirm location button
    document.addEventListener('DOMContentLoaded', function() {
        const btnConfirmLocation = document.getElementById('btnConfirmLocation');
            if (btnConfirmLocation) {
            btnConfirmLocation.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                confirmLocationSelection();
            });
        }
    });
</script>
