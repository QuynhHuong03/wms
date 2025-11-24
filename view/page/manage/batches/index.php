<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Lô Hàng</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* UI tweaks: responsive stat cards and compact modal */
        .stat-card .card-body h5 { margin: 0; font-size: 0.95rem; }
        .stat-card .card-body h2 { margin: 0; font-size: 1.6rem; }
        @media (max-width: 575px) {
            .stat-card { margin-bottom: 0.75rem; }
        }
        .table th.small-col { width: 110px; }
        .btn-compact { padding: 0.25rem 0.45rem; font-size: 0.85rem; }
        .copy-btn { border: none; background: transparent; color: #6c757d; }
    </style>
</head>
<body>
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

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-boxes"></i> Quản lý Lô Hàng</h2>
        <a href="../index.php?page=receipts" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
    </div>

    <?php if (isset($_SESSION['flash_batch'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['flash_batch'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_batch']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_batch_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['flash_batch_error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_batch_error']); ?>
    <?php endif; ?>

    <!-- Thống kê -->
    <div class="row mb-4">
        <div class="col-6 col-sm-6 col-md-3 stat-card">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5>Tổng số lô</h5>
                    <h2><?= count($batches) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-6 col-md-3 stat-card">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5>Đang lưu</h5>
                    <h2><?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'Đang lưu')) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-6 col-md-3 stat-card">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5>Sắp hết</h5>
                    <h2><?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'Sắp hết')) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-6 col-sm-6 col-md-3 stat-card">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h5>Đã hết</h5>
                    <h2><?= count(array_filter($batches, fn($b) => ($b['status'] ?? '') === 'Đã hết')) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th class="small-col">Mã Lô</th>
                            <th class="small-col">Mã Sản Phẩm</th>
                            <th>Tên Sản Phẩm</th>
                            <th class="small-col">Số Lượng Nhập</th>
                            <th class="small-col">Số Lượng Còn</th>
                            <th class="small-col">Đơn Vị</th>
                            <th class="small-col">Ngày Nhập</th>
                            <th class="small-col">Trạng Thái</th>
                            <th class="small-col">Mã Phiếu</th>
                            <th class="small-col">Thao Tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                    Chưa có lô hàng nào
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $batch): ?>
                                <?php
                                $statusText = $batch['status'] ?? 'Đang lưu';
                                $statusClass = 'bg-info';
                                if ($statusText === 'Đang lưu') {
                                    $statusClass = 'bg-success';
                                } elseif ($statusText === 'Sắp hết') {
                                    $statusClass = 'bg-warning';
                                } elseif ($statusText === 'Đã hết' || $statusText === 'Đã xuất hết') {
                                    $statusClass = 'bg-danger';
                                }
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($batch['batch_code'] ?? '') ?></strong></td>
                                    <td><?= htmlspecialchars($batch['product_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($batch['product_name'] ?? 'N/A') ?></td>
                                    <td><?= number_format($batch['quantity_imported'] ?? 0) ?></td>
                                    <td>
                                        <strong class="<?= ($batch['quantity_remaining'] ?? 0) <= 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= number_format($batch['quantity_remaining'] ?? 0) ?>
                                        </strong>
                                    </td>
                                    <td><?= htmlspecialchars($batch['unit'] ?? 'cái') ?></td>
                                    <td>
                                        <?php 
                                        $importDate = $batch['import_date'] ?? '';
                                        // Nếu là timestamp Unix (số lớn hơn 1000000000000)
                                        if (is_numeric($importDate) && $importDate > 1000000000000) {
                                            echo date('Y-m-d', intval($importDate / 1000));
                                        } elseif (is_object($importDate) && method_exists($importDate, 'toDateTime')) {
                                            // MongoDB DateTime object
                                            echo $importDate->toDateTime()->format('Y-m-d');
                                        } else {
                                            echo htmlspecialchars($importDate);
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($statusText) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($batch['transaction_id'])): ?>
                                            <a href="?page=receipts/approve/detail&id=<?= urlencode($batch['transaction_id']) ?>" 
                                               class="text-decoration-none">
                                                <?= htmlspecialchars($batch['transaction_id']) ?>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                                <button class="btn btn-info btn-compact" 
                                                        onclick="viewBatchDetail('<?= htmlspecialchars($batch['batch_code'] ?? '') ?>')"
                                                        title="Xem chi tiết">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php if (($batch['quantity_remaining'] ?? 0) <= 0): ?>
                                                <button class="btn btn-danger btn-compact" 
                                                        onclick="deleteBatch('<?= htmlspecialchars($batch['batch_code'] ?? '') ?>')"
                                                        title="Xóa lô">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    
</div>

<!-- Modal Chi tiết Lô -->
<div class="modal fade" id="batchDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết Lô Hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="batchDetailContent">
                <div class="text-center">
                    <div class="spinner-border" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Use server-computed API path to avoid fragile client-side path detection
const API_BATCH_PROCESS = '<?= $processPath ?>';

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
    const modalEl = document.getElementById('batchDetailModal');
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (!modal) modal = new bootstrap.Modal(modalEl);
    const content = document.getElementById('batchDetailContent');

    // If modal not shown, show it. If already shown, we'll reuse it and just replace content.
    if (!modalEl.classList.contains('show')) {
        content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Đang tải...</p></div>';
        modal.show();
    } else {
        // indicate loading while replacing
        content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p class="mt-2">Đang tải...</p></div>';
    }

    try {
        const response = await fetch(`${API_BATCH_PROCESS}?action=get_detail&batch_code=${encodeURIComponent(batchCode)}`);
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('Invalid JSON from batch detail API:', text);
            // Show raw response to help debugging
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
                <div class="d-flex align-items-center">
                    <a href="#" onclick='event.preventDefault(); viewBatchDetail(${JSON.stringify(batch.source_batch_code)});'>${batch.source_batch_code}</a>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick='copyToClipboard(${JSON.stringify(batch.source_batch_code)})' title="Copy mã nguồn"><i class="far fa-copy"></i></button>
                    <button class="btn btn-sm btn-outline-primary ms-2" onclick='viewBatchDetail(${JSON.stringify(batch.source_batch_code)})'>Xem nguồn</button>
                </div>`;
        } else if (batch.source) {
            sourceHtml = `${batch.source}`;
        }

        // removed warehouse button (Mở kho gốc) as requested
        let warehouseBtn = '';

        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-box"></i> Thông tin lô hàng</h6>
                    <table class="table table-sm">
                        <tr><th>Mã lô:</th><td><div class="d-flex align-items-center"><strong>${batch.batch_code}</strong><button class="btn btn-sm btn-outline-secondary ms-2" onclick='copyToClipboard(${JSON.stringify(batch.batch_code)})' title="Copy mã lô"><i class="far fa-copy"></i></button></div></td></tr>
                        <tr><th>Sản phẩm:</th><td>${batch.product_name}</td></tr>
                        <tr><th>SKU:</th><td>${batch.product_id}</td></tr>
                        <tr><th>Barcode:</th><td>${batch.barcode || 'N/A'}</td></tr>
                        <tr><th>Số lượng nhập:</th><td>${batch.quantity_imported || 0}</td></tr>
                        <tr><th>Số lượng còn:</th><td><strong class="${(batch.quantity_remaining || 0) > 0 ? 'text-success' : 'text-danger'}">${batch.quantity_remaining || 0}</strong></td></tr>
                        <tr><th>Đơn vị:</th><td>${batch.unit || 'cái'}</td></tr>
                        <tr><th>Đơn giá:</th><td>${(batch.unit_price || 0).toLocaleString('vi-VN')} đ</td></tr>
                        <tr><th>Ngày nhập:</th><td>${batch.import_date || 'N/A'}</td></tr>
                        <tr><th>Trạng thái:</th><td><span class="badge ${statusBadgeClass}">${batch.status || ''}</span></td></tr>
                        <tr><th>Loại:</th><td>${batch.type || (batch.source === 'transfer' ? 'transfer' : (batch.source || 'purchase'))}</td></tr>
                        <tr><th>Nguồn:</th><td>${sourceHtml}</td></tr>
                        ${batch.source_warehouse_id ? `<tr><th>Kho nguồn:</th><td>${batch.source_warehouse_id}</td></tr>` : ''}
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h6><i class="fas fa-map-marker-alt"></i> Vị trí (${(batch.locations && batch.locations.length) || 0})</h6>
                    ${batch.locations && batch.locations.length > 0 ? `
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Vị trí</th>
                                        <th>Số lượng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${batch.locations.map(loc => `
                                        <tr>
                                            <td>${loc.location_string || 'N/A'}</td>
                                            <td><strong>${loc.quantity || 0}</strong></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p class="text-muted">Chưa có vị trí lưu kho</p>'}
                </div>
            </div>
            
            <hr>
            
            <h6><i class="fas fa-history"></i> Lịch sử di chuyển (${(batch.movements && batch.movements.length) || 0} lần)</h6>
            ${batch.movements && batch.movements.length > 0 ? `
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Thời gian</th>
                                <th>Loại</th>
                                <th>Số lượng</th>
                                <th>Từ</th>
                                <th>Đến</th>
                                <th>Phiếu</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${batch.movements.map(mov => `
                                <tr>
                                    <td style="font-size:11px">${mov.date || 'N/A'}</td>
                                    <td>
                                        <span class="badge ${mov.movement_type === 'nhập' ? 'bg-success' : 'bg-danger'}">
                                            ${mov.movement_type === 'nhập' ? '📥' : '📤'} ${mov.movement_type}
                                        </span>
                                    </td>
                                    <td><strong>${mov.quantity || 0}</strong></td>
                                    <td style="font-size:11px">${mov.from_location || 'N/A'}</td>
                                    <td style="font-size:11px">${mov.to_location || 'N/A'}</td>
                                    <td style="font-size:11px">${mov.transaction_id || 'N/A'}</td>
                                    <td style="font-size:11px">${mov.note || ''}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            ` : '<p class="text-muted">Chưa có lịch sử di chuyển</p>'}
        `;

        content.innerHTML = html;

        // Ensure stray backdrops are removed when modal hides
        modalEl.addEventListener('hidden.bs.modal', function cleanupBackdrop() {
            // remove any leftover modal-backdrop elements
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            // ensure body doesn't keep modal-open class
            document.body.classList.remove('modal-open');
            modalEl.removeEventListener('hidden.bs.modal', cleanupBackdrop);
        });

    } catch (error) {
        content.innerHTML = `<div class="alert alert-danger">Lỗi: ${error.message}</div>`;
    }
}

async function deleteBatch(batchCode) {
    if (!confirm(`Bạn có chắc muốn xóa lô ${batchCode}?\nChỉ có thể xóa lô đã hết hàng (số lượng = 0)`)) {
        return;
    }
    
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
            alert('Đã xóa lô hàng thành công!');
            location.reload();
        } else {
            alert('Lỗi: ' + data.message);
        }
    } catch (error) {
        alert('Lỗi: ' + error.message);
    }
}
</script>

</body>
</html>
