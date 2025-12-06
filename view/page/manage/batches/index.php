
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . "/../../../../controller/cBatch.php");

// Kiểm tra đăng nhập
if (!isset($_SESSION['login'])) {
    header("Location: ../../index.php?page=login");
    exit;
}

$cBatch = new CBatch();
$batches = $cBatch->getAllBatches();
// Compute a reliable web-accessible path to this folder's process.php.
// Prefer converting filesystem path to a path under the web document root.
// If that fails (e.g. DOCUMENT_ROOT mismatch), fall back to a safe web-root-relative path.
$processPath = '';
$dirFs = str_replace('\\', '/', realpath(__DIR__));
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : false;
if ($docRoot && $docRoot !== '' && strpos($dirFs, $docRoot) === 0) {
    $rel = substr($dirFs, strlen($docRoot));
    $processPath = '/' . ltrim($rel, '/') . '/process.php';
} else {
    // Fallback: build path relative to the web root using script name.
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
    $processPath = $scriptDir . '/view/page/manage/batches/process.php';
}
?>

<style>
  .batch-list-container {max-width:1400px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 15px rgba(0,0,0,0.08);}
  .batch-list-container h2 {text-align:center;margin-bottom:25px;color:#333;}
  .batch-list-container table {width:100%;border-collapse:collapse;margin-top:20px;}
  .batch-list-container th,.batch-list-container td {padding:10px 12px;border:1px solid #e1e4e8;text-align:center;font-size:14px;}
  .batch-list-container th {background:#f9fafb;font-weight:600;}
  .batch-list-container tr:hover {background:#f1f7ff;}
  .batch-list-container .btn {border:none;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block;margin:2px;}
  .batch-list-container .btn-view {background:#17a2b8;color:#fff;}
  .batch-list-container .btn-delete {background:#dc3545;color:#fff;}
  .batch-list-container .btn:hover {opacity:0.9;}
  .batch-list-container .status {font-weight:600;padding:6px 10px;border-radius:8px;display:inline-block;}
  .batch-list-container .storing {background:#d4edda;color:#155724;}
  .batch-list-container .low-stock {background:#fff3cd;color:#856404;}
  .batch-list-container .out-of-stock {background:#f8d7da;color:#721c24;}
  .batch-list-container .top-actions {margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
  .stats {display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin:20px 0;}
  .stat-card {padding:20px;border-radius:12px;color:#fff;box-shadow:0 3px 10px rgba(0,0,0,0.1);}
  .stat-card.blue {background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);}
  .stat-card.green {background:linear-gradient(135deg, #10b981 0%, #059669 100%);}
  .stat-card.yellow {background:linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);}
  .stat-card.red {background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%);}
  .stat-card h4 {font-size:14px;margin:0 0 8px 0;font-weight:500;opacity:0.95;}
  .stat-card .value {font-size:36px;font-weight:700;margin:0;}
  .alert {padding:12px;margin-bottom:15px;border-radius:8px;}
  .alert-success {background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
  .alert-danger {background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
  .btn-back {padding:8px 16px;background:#6c757d;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;transition:all 0.2s;}
  .btn-back:hover {background:#5a6268;color:#fff;}
</style>

<div class="batch-list-container">
    <div class="top-actions">
        <h2><i class="fa-solid fa-boxes"></i> Quản lý Lô Hàng</h2>
        <!-- <a href="index.php?page=receipts" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Quay lại
        </a> -->
    </div>

    <?php if (isset($_SESSION['flash_batch'])): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= $_SESSION['flash_batch'] ?>
        </div>
        <?php unset($_SESSION['flash_batch']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_batch_error'])): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i> <?= $_SESSION['flash_batch_error'] ?>
        </div>
        <?php unset($_SESSION['flash_batch_error']); ?>
    <?php endif; ?>

    <!-- Thống kê -->
    <div class="stats">
        <div class="stat-card blue">
            <h4>Tổng số lô</h4>
            <div class="value"><?= count($batches) ?></div>
        </div>
        <div class="stat-card green">
            <h4>Đang lưu</h4>
            <div class="value"><?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'Đang lưu')) ?></div>
        </div>
        <div class="stat-card yellow">
            <h4>Sắp hết</h4>
            <div class="value"><?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'Sắp hết')) ?></div>
        </div>
        <div class="stat-card red">
            <h4>Đã hết</h4>
            <div class="value"><?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'Đã hết')) ?></div>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Mã Lô</th>
                    <th>Mã SKU</th>
                    <th>Tên Sản Phẩm</th>
                    <th>SL Nhập</th>
                    <th>SL Còn</th>
                    <th>Đơn Vị</th>
                    <th>Ngày Nhập</th>
                    <th>Trạng Thái</th>
                    <th>Mã Phiếu</th>
                    <th>Thao Tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center;padding:40px;color:#6b7280;">
                            Chưa có lô hàng nào
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($batches as $batch): ?>
                        <?php
                        $statusText = $batch['status'] ?? 'Đang lưu';
                        $statusClass = 'storing';
                        if ($statusText === 'Đang lưu') {
                            $statusClass = 'storing';
                        } elseif ($statusText === 'Sắp hết') {
                            $statusClass = 'low-stock';
                        } elseif ($statusText === 'Đã hết' || $statusText === 'Đã xuất hết') {
                            $statusClass = 'out-of-stock';
                        }
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($batch['batch_code'] ?? '') ?></strong></td>
                            <td><?= htmlspecialchars($batch['product_sku'] ?? $batch['product_id'] ?? '') ?></td>
                            <td style="text-align:left;"><?= htmlspecialchars($batch['product_name'] ?? 'N/A') ?></td>
                            <td><?= number_format($batch['quantity_imported'] ?? 0) ?></td>
                            <td>
                                <strong style="color:<?= ($batch['quantity_remaining'] ?? 0) <= 0 ? '#dc2626' : '#059669' ?>">
                                    <?= number_format($batch['quantity_remaining'] ?? 0) ?>
                                </strong>
                            </td>
                            <td><?= htmlspecialchars($batch['unit'] ?? 'cái') ?></td>
                            <td>
                                <?php 
                                $importDate = $batch['import_date'] ?? '';
                                if (is_numeric($importDate) && $importDate > 1000000000000) {
                                    echo date('d/m/Y', intval($importDate / 1000));
                                } elseif (is_object($importDate) && method_exists($importDate, 'toDateTime')) {
                                    echo $importDate->toDateTime()->format('d/m/Y');
                                } else {
                                    echo htmlspecialchars($importDate);
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status <?= $statusClass ?>">
                                    <?= htmlspecialchars($statusText) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($batch['transaction_id'])): ?>
                                    <a href="?page=receipts/approve/detail&id=<?= urlencode($batch['transaction_id']) ?>" 
                                       style="color:#2563eb;text-decoration:none;">
                                        <?= htmlspecialchars($batch['transaction_id']) ?>
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-view" 
                                        onclick="viewBatchDetail('<?= htmlspecialchars($batch['batch_code'] ?? '') ?>')"
                                        title="Xem chi tiết">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?php if (($batch['quantity_remaining'] ?? 0) <= 0): ?>
                                    <button class="btn btn-delete" 
                                            onclick="deleteBatch('<?= htmlspecialchars($batch['batch_code'] ?? '') ?>')"
                                            title="Xóa lô">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    
</div>

<!-- Modal Chi tiết Lô -->
<div id="batchDetailModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto;">
    <div style="max-width:1200px;margin:50px auto;background:#fff;border-radius:12px;padding:24px;position:relative;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:2px solid #e5e7eb;padding-bottom:12px;">
            <h3 style="margin:0;"><i class="fa-solid fa-box"></i> Chi tiết Lô Hàng</h3>
            <button onclick="closeModal()" style="background:#dc3545;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;">
                <i class="fa-solid fa-xmark"></i> Đóng
            </button>
        </div>
        <div id="batchDetailContent">
            <div style="text-align:center;padding:40px;">
                <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                <p style="margin-top:10px;">Đang tải...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Use server-computed API path to avoid fragile client-side path detection
const API_BATCH_PROCESS = '<?= $processPath ?>';

function closeModal() {
    document.getElementById('batchDetailModal').style.display = 'none';
}

// Map batch status to bootstrap badge class
function mapStatusClass(status) {
    if (!status) return 'bg-info';
    if (status === 'Đang lưu') return 'bg-success';
    if (status === 'Sắp hết') return 'bg-warning';
    if (status === 'Đã hết' || status === 'Đã xuất hết') return 'bg-danger';
    return 'bg-info';
}

function copyToClipboard(text) {
    if (!text && text !== 0) return;
    try {
        navigator.clipboard.writeText(text.toString());
        alert('Đã copy: ' + text);
    } catch (e) {
        // fallback
        const tmp = document.createElement('textarea');
        tmp.value = text;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        document.body.removeChild(tmp);
        alert('Đã copy: ' + text);
    }
}

function openSourceWarehouse(whId) {
    if (!whId) return;
    // Redirect to a warehouse page — adjust path if your app differs
    window.location.href = `../index.php?page=warehouses&warehouse_id=${encodeURIComponent(whId)}`;
}

async function viewBatchDetail(batchCode) {
    const modal = document.getElementById('batchDetailModal');
    const content = document.getElementById('batchDetailContent');
    
    modal.style.display = 'block';
    content.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p style="margin-top:10px;">Đang tải...</p></div>';

    try {
        const response = await fetch(`${API_BATCH_PROCESS}?action=get_detail&batch_code=${encodeURIComponent(batchCode)}`);
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('Invalid JSON from batch detail API:', text);
            content.innerHTML = `<div class="alert alert-danger"><strong>Server trả về dữ liệu không hợp lệ.</strong><pre style="max-height:300px;overflow:auto">${text.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</pre></div>`;
            return;
        }

        if (!data.success) {
            content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            return;
        }

        const batch = data.data;

        const statusBadgeClass = mapStatusClass(batch.status);

        let sourceHtml = 'N/A';
        if (batch.source_batch_code) {
            sourceHtml = `
                <div style="display:flex;align-items:center;gap:8px;">
                    <a href="#" onclick='event.preventDefault(); viewBatchDetail(${JSON.stringify(batch.source_batch_code)});' style="color:#2563eb;text-decoration:none;">${batch.source_batch_code}</a>
                    <button onclick='copyToClipboard(${JSON.stringify(batch.source_batch_code)})' title="Copy mã nguồn" style="background:#6c757d;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;"><i class="far fa-copy"></i></button>
                    <button onclick='viewBatchDetail(${JSON.stringify(batch.source_batch_code)})' style="background:#2563eb;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;">Xem nguồn</button>
                </div>`;
        } else if (batch.source) {
            sourceHtml = `${batch.source}`;
        }

        let html = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px;">
                <div>
                    <h6 style="margin-bottom:16px;"><i class="fa-solid fa-box"></i> Thông tin lô hàng</h6>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;width:150px;">Mã lô:</th><td style="padding:8px;border:1px solid #e5e7eb;"><div style="display:flex;align-items:center;gap:8px;"><strong>${batch.batch_code}</strong><button onclick='copyToClipboard(${JSON.stringify(batch.batch_code)})' title="Copy mã lô" style="background:#6c757d;color:#fff;border:none;padding:4px 8px;border-radius:4px;cursor:pointer;"><i class="far fa-copy"></i></button></div></td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Sản phẩm:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.product_name}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">SKU:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.product_id}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Barcode:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.barcode || 'N/A'}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Số lượng nhập:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.quantity_imported || 0}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Số lượng còn:</th><td style="padding:8px;border:1px solid #e5e7eb;"><strong style="color:${(batch.quantity_remaining || 0) > 0 ? '#059669' : '#dc2626'}">${batch.quantity_remaining || 0}</strong></td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Đơn vị:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.unit || 'cái'}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Đơn giá:</th><td style="padding:8px;border:1px solid #e5e7eb;">${(batch.unit_price || 0).toLocaleString('vi-VN')} đ</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Ngày nhập:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.import_date || 'N/A'}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Trạng thái:</th><td style="padding:8px;border:1px solid #e5e7eb;"><span class="status ${statusBadgeClass}">${batch.status || ''}</span></td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Loại:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.type || (batch.source === 'transfer' ? 'transfer' : (batch.source || 'purchase'))}</td></tr>
                        <tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Nguồn:</th><td style="padding:8px;border:1px solid #e5e7eb;">${sourceHtml}</td></tr>
                        ${batch.source_warehouse_id ? `<tr><th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;">Kho nguồn:</th><td style="padding:8px;border:1px solid #e5e7eb;">${batch.source_warehouse_id}</td></tr>` : ''}
                    </table>
                </div>
                
                <div>
                    <h6 style="margin-bottom:16px;"><i class="fa-solid fa-map-marker-alt"></i> Vị trí (${(batch.locations && batch.locations.length) || 0})</h6>
                    ${batch.locations && batch.locations.length > 0 ? `
                        <div style="overflow-x:auto;">
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Vị trí</th>
                                        <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Số lượng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${batch.locations.map(loc => `
                                        <tr>
                                            <td style="padding:8px;border:1px solid #e5e7eb;">${loc.location_string || 'N/A'}</td>
                                            <td style="padding:8px;border:1px solid #e5e7eb;"><strong>${loc.quantity || 0}</strong></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p style="color:#6b7280;">Chưa có vị trí lưu kho</p>'}
                </div>
            </div>
            
            <hr style="margin:20px 0;border:none;border-top:1px solid #e5e7eb;">
            
            <h6 style="margin-bottom:16px;"><i class="fa-solid fa-history"></i> Lịch sử di chuyển (${(batch.movements && batch.movements.length) || 0} lần)</h6>
            ${batch.movements && batch.movements.length > 0 ? `
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Thời gian</th>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Loại</th>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Số lượng</th>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Từ</th>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Đến</th>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Phiếu</th>
                                <th style="padding:8px;border:1px solid #e5e7eb;background:#f9fafb;">Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${batch.movements.map(mov => `
                                <tr>
                                    <td style="padding:8px;border:1px solid #e5e7eb;font-size:12px;">${mov.date || 'N/A'}</td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;">
                                        <span class="status ${mov.movement_type === 'nhập' ? 'storing' : 'out-of-stock'}">
                                            ${mov.movement_type === 'nhập' ? '📥' : '📤'} ${mov.movement_type}
                                        </span>
                                    </td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;"><strong>${mov.quantity || 0}</strong></td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;font-size:12px;">${mov.from_location || 'N/A'}</td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;font-size:12px;">${mov.to_location || 'N/A'}</td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;font-size:12px;">${mov.transaction_id || 'N/A'}</td>
                                    <td style="padding:8px;border:1px solid #e5e7eb;font-size:12px;">${mov.note || ''}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            ` : '<p style="color:#6b7280;">Chưa có lịch sử di chuyển</p>'}
        `;

        content.innerHTML = html;

    } catch (error) {
        content.innerHTML = `<div class="alert alert-danger">Lỗi: ${error.message}</div>`;
    }
}

async function deleteBatch(batchCode) {
    const result = await Swal.fire({
        title: 'Xác nhận xóa lô hàng',
        html: `Bạn có chắc muốn xóa lô <strong>${batchCode}</strong>?<br><small>Chỉ có thể xóa lô đã hết hàng (số lượng = 0)</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6c757d',
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('batch_code', batchCode);
        
        const response = await fetch(API_BATCH_PROCESS, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            await Swal.fire({
                title: 'Thành công!',
                text: 'Đã xóa lô hàng thành công!',
                icon: 'success',
                confirmButtonColor: '#059669'
            });
            location.reload();
        } else {
            Swal.fire({
                title: 'Lỗi!',
                text: data.message,
                icon: 'error',
                confirmButtonColor: '#dc2626'
            });
        }
    } catch (error) {
        Swal.fire({
            title: 'Lỗi!',
            text: error.message,
            icon: 'error',
            confirmButtonColor: '#dc2626'
        });
    }
}
</script>


