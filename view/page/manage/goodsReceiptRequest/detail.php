<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../model/mProduct.php");
include_once(__DIR__ . "/../../../../model/mWarehouse.php");
include_once(__DIR__ . "/../../../../model/mUsers.php");

$cRequest = new CRequest();
$mProduct = new MProduct();
$mWarehouse = new MWarehouse();
$mUsers = new MUsers();

// Lấy request_id từ URL
$request_id = $_GET['id'] ?? null;
if (!$request_id) {
    echo "<div class='error-state'>🚫 Không tìm thấy mã phiếu!</div>";
    exit();
}

// Lấy thông tin phiếu yêu cầu
$request = $cRequest->getRequestById($request_id);
if (!$request) {
    echo "<div class='error-state'>🚫 Không tìm thấy phiếu yêu cầu!</div>";
    exit();
}

// --- XỬ LÝ DATA (Giữ nguyên logic cũ) ---
// Lấy tên người tạo
$creator_name = $request['creator_name'] ?? '';
if (empty($creator_name) && !empty($request['created_by'])) {
    $creator_user = $mUsers->getUserByUserId($request['created_by']);
    $creator_name = $creator_user['name'] ?? $request['created_by'];
} elseif (empty($creator_name)) {
    $creator_name = $request['created_by'] ?? 'N/A';
}

// Lấy tên người duyệt
$approver_name = '';
if (!empty($request['approved_by'])) {
    $approver_name = $request['approver_name'] ?? '';
    if (empty($approver_name)) {
        $approver_user = $mUsers->getUserByUserId($request['approved_by']);
        $approver_name = $approver_user['name'] ?? $request['approved_by'];
    }
}

// Lấy tên người xử lý
$processor_name = '';
if (!empty($request['processed_by'])) {
    $processor_name = $request['processor_name'] ?? '';
    if (empty($processor_name)) {
        $processor_user = $mUsers->getUserByUserId($request['processed_by']);
        $processor_name = $processor_user['name'] ?? $request['processed_by'];
    }
}

// Lấy tên người chỉ định
$assigner_name = '';
if (!empty($request['assigned_by'])) {
    $assigner_name = $request['assigner_name'] ?? '';
    if (empty($assigner_name)) {
        $assigner_user = $mUsers->getUserByUserId($request['assigned_by']);
        $assigner_name = $assigner_user['name'] ?? $request['assigned_by'];
    }
}

$warehouse = $mWarehouse->getWarehouseById($request['warehouse_id']);
$warehouse_name = $warehouse['name'] ?? $request['warehouse_id'];

$source_warehouse = $mWarehouse->getWarehouseById($request['source_warehouse_id']);
$source_warehouse_name = $source_warehouse['name'] ?? $request['source_warehouse_id'];

$assigned_warehouse_name = '';
if (!empty($request['assigned_warehouse_id'])) {
    $assigned_warehouse = $mWarehouse->getWarehouseById($request['assigned_warehouse_id']);
    $assigned_warehouse_name = $assigned_warehouse['name'] ?? $request['assigned_warehouse_id'];
}

// Status & Priority Mapping
$statusMap = [
    0 => ['label' => 'Chờ duyệt', 'color' => '#b45309', 'bg' => '#fffbeb'],
    1 => ['label' => 'Đã duyệt', 'color' => '#15803d', 'bg' => '#dcfce7'],
    2 => ['label' => 'Từ chối', 'color' => '#b91c1c', 'bg' => '#fee2e2'],
    3 => ['label' => 'Đủ hàng', 'color' => '#0e7490', 'bg' => '#cffafe'],
    4 => ['label' => 'Thiếu hàng', 'color' => '#c2410c', 'bg' => '#ffedd5'],
    5 => ['label' => 'Đã chỉ định kho', 'color' => '#7e22ce', 'bg' => '#f3e8ff'],
    6 => ['label' => 'Hoàn thành', 'color' => '#0f766e', 'bg' => '#ccfbf1']
];

$currentStatus = $statusMap[$request['status']] ?? ['label' => 'Không xác định', 'color' => '#374151', 'bg' => '#f3f4f6'];

$priorityMap = [
    'normal' => ['label' => 'Bình thường', 'color' => '#3b82f6', 'bg' => '#eff6ff'],
    'urgent' => ['label' => 'Khẩn cấp', 'color' => '#ef4444', 'bg' => '#fef2f2']
];
$priority = $priorityMap[$request['priority']] ?? $priorityMap['normal'];

function formatDate($date) {
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        return $date->toDateTime()->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('H:i - d/m/Y');
    }
    return '---';
}
?>

<style>
    :root {
        --primary: #4f46e5; /* Indigo 600 */
        --primary-light: #eef2ff;
        --text-dark: #1f2937;
        --text-gray: #6b7280;
        --border: #e5e7eb;
        --bg-body: #f3f4f6;
        --white: #ffffff;
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --radius: 12px;
    }

    body { background: var(--bg-body); font-family: 'Segoe UI', system-ui, sans-serif; color: var(--text-dark); }

    .error-state { text-align: center; padding: 50px; font-size: 18px; color: #ef4444; background: #fff; margin: 20px; border-radius: var(--radius); }

    .wms-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }

    /* Header Card */
    .card-header { 
        background: var(--white); border-radius: var(--radius); padding: 24px; 
        box-shadow: var(--shadow); margin-bottom: 24px; 
        display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;
    }
    .header-title h2 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
    .header-title .sub-id { color: var(--text-gray); font-size: 14px; margin-top: 4px; }
    
    .header-actions { display: flex; align-items: center; gap: 12px; }
    .btn-back {
        padding: 8px 16px; background: var(--white); border: 1px solid var(--border);
        color: var(--text-dark); border-radius: 8px; text-decoration: none; 
        font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-back:hover { background: #f9fafb; border-color: #d1d5db; }

    /* Badges */
    .badge { padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    .badge-status { background: <?= $currentStatus['bg'] ?>; color: <?= $currentStatus['color'] ?>; border: 1px solid <?= $currentStatus['color'] ?>20; }
    .badge-priority { background: <?= $priority['bg'] ?>; color: <?= $priority['color'] ?>; border: 1px solid <?= $priority['color'] ?>20; }

    /* Grid Layout */
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
    @media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }

    .card-box { background: var(--white); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); height: 100%; }
    .card-title { 
        font-size: 16px; font-weight: 600; color: var(--text-dark); 
        margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border);
        display: flex; align-items: center; gap: 8px;
    }
    
    /* Data List Styles */
    .data-list { display: flex; flex-direction: column; gap: 16px; }
    .data-item { display: flex; justify-content: space-between; align-items: flex-start; font-size: 14px; }
    .data-label { color: var(--text-gray); font-weight: 500; min-width: 120px; }
    .data-value { color: var(--text-dark); font-weight: 600; text-align: right; flex: 1; }
    .highlight-val { color: var(--primary); }

    /* Table Styles */
    .table-responsive { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--border); }
    .custom-table { width: 100%; border-collapse: collapse; background: var(--white); }
    .custom-table th { 
        background: #f9fafb; color: var(--text-gray); font-weight: 600; font-size: 13px; 
        text-transform: uppercase; letter-spacing: 0.5px; padding: 14px 16px; text-align: left;
        border-bottom: 1px solid var(--border);
    }
    .custom-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; vertical-align: middle; }
    .custom-table tr:last-child td { border-bottom: none; }
    .custom-table tr:hover { background-color: #f9fafb; }
    
    .product-info h4 { margin: 0 0 4px 0; font-size: 14px; color: var(--text-dark); }
    .product-sku { font-size: 12px; color: var(--text-gray); font-family: monospace; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    
    .qty-badge { font-weight: 700; font-size: 15px; }
    .factor-badge { font-size: 12px; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 6px; font-weight: 600; }

    /* Note Section */
    .note-box { 
        margin-top: 24px; background: #fff; padding: 20px; border-radius: var(--radius); 
        border-left: 4px solid var(--primary); box-shadow: var(--shadow);
    }
    .note-label { font-size: 13px; color: var(--text-gray); font-weight: 700; text-transform: uppercase; margin-bottom: 8px; }
    .note-content { color: var(--text-dark); line-height: 1.6; font-size: 14px; }

    .note-box.assign { border-left-color: #9333ea; background: #faf5ff; }
    .note-box.assign .note-label { color: #9333ea; }
</style>

<div class="wms-container">
    <div class="card-header">
        <div class="header-title">
            <h2>Yêu cầu nhập hàng</h2>
            <div class="sub-id">Mã phiếu: <strong><?= htmlspecialchars($request['transaction_id']) ?></strong></div>
        </div>
        <div class="header-actions">
            <span class="badge badge-priority"><?= $priority['label'] ?></span>
            <span class="badge badge-status"><?= $currentStatus['label'] ?></span>
            <a href="index.php?page=goodsReceiptRequest" class="btn-back">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Quay lại
            </a>
        </div>
    </div>

    <div class="info-grid">
        <div class="card-box">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Thông tin vận hành
            </div>
            <div class="data-list">
                <div class="data-item">
                    <span class="data-label">Kho đích (nhận):</span>
                    <span class="data-value highlight-val"><?= htmlspecialchars($warehouse_name) ?></span>
                </div>
                <div class="data-item">
                    <span class="data-label">Kho nguồn:</span>
                    <span class="data-value"><?= htmlspecialchars($source_warehouse_name) ?></span>
                </div>
                <?php if (!empty($request['assigned_warehouse_id'])): ?>
                <div class="data-item">
                    <span class="data-label">Kho được chỉ định:</span>
                    <span class="data-value" style="color: #9333ea;">
                        <?= htmlspecialchars($assigned_warehouse_name) ?>
                        <div style="font-size:12px; font-weight:400; color:#666;"><?= htmlspecialchars($request['assigned_warehouse_id']) ?></div>
                    </span>
                </div>
                <?php endif; ?>
                <div class="data-item">
                    <span class="data-label">Ngày tạo phiếu:</span>
                    <span class="data-value"><?= formatDate($request['created_at']) ?></span>
                </div>
            </div>
        </div>

        <div class="card-box">
            <div class="card-title">
                <svg width="20" height="20" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                Nhân sự xử lý
            </div>
            <div class="data-list">
                <div class="data-item">
                    <span class="data-label">Người tạo:</span>
                    <span class="data-value"><?= htmlspecialchars($creator_name) ?></span>
                </div>

                <?php if (!empty($approver_name)): ?>
                <div class="data-item">
                    <span class="data-label">Người duyệt:</span>
                    <span class="data-value">
                        <?= htmlspecialchars($approver_name) ?>
                        <div style="font-size:11px; color:#15803d; font-weight:400;"><?= formatDate($request['approved_at']) ?></div>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($processor_name)): ?>
                <div class="data-item">
                    <span class="data-label">Thủ kho xử lý:</span>
                    <span class="data-value">
                        <?= htmlspecialchars($processor_name) ?>
                        <div style="font-size:11px; color:#0e7490; font-weight:400;"><?= formatDate($request['processed_at']) ?></div>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($assigner_name)): ?>
                <div class="data-item">
                    <span class="data-label">Người chỉ định:</span>
                    <span class="data-value">
                        <?= htmlspecialchars($assigner_name) ?>
                        <div style="font-size:11px; color:#7e22ce; font-weight:400;"><?= formatDate($request['assigned_at']) ?></div>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-box" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display:flex; align-items:center; gap:8px;">
            <svg width="20" height="20" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            <span style="font-weight: 600; font-size: 16px;">Chi tiết hàng hóa</span>
        </div>
        <div class="table-responsive" style="border: none; border-radius: 0;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">STT</th>
                        <th>Sản phẩm</th>
                        <th style="text-align: center;">Số lượng</th>
                        <th style="text-align: center;">Đơn vị</th>
                        <th style="text-align: right;">Quy đổi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $details = $request['details'] ?? [];
                    if (empty($details)): 
                    ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: #9ca3af;">Chưa có sản phẩm nào trong phiếu này.</td></tr>
                    <?php else: 
                        $stt = 1;
                        foreach ($details as $item):
                            // Logic tìm sản phẩm (giữ nguyên)
                            $product = $mProduct->getProductBySKU($item['product_id']);
                            if (!$product) $product = $mProduct->getProductById($item['product_id']);
                            
                            $product_name = $product['name'] ?? $product['product_name'] ?? 'Không tìm thấy tên SP';
                            $product_sku = $product['sku'] ?? $item['product_id'];
                            
                            // Logic Factor (giữ nguyên)
                            $factor_display = '';
                            $factor = $item['conversion_factor'] ?? null;
                            if (empty($factor) || $factor == 1) {
                                $item_unit = $item['unit'] ?? '';
                                $base_unit = $product['baseUnit'] ?? 'cái';
                                if ($item_unit != $base_unit && $product && isset($product['conversionUnits'])) {
                                    foreach ($product['conversionUnits'] as $conv) {
                                        if (isset($conv['unit']) && $conv['unit'] == $item_unit) {
                                            $factor = $conv['factor'] ?? null;
                                            break;
                                        }
                                    }
                                }
                            }
                            if (!empty($factor) && $factor != 1) $factor_display = "x " . number_format($factor, 0);
                    ?>
                        <tr>
                            <td style="text-align: center; color: var(--text-gray);"><?= $stt++ ?></td>
                            <td>
                                <div class="product-info">
                                    <h4><?= htmlspecialchars($product_name) ?></h4>
                                    <span class="product-sku"><?= htmlspecialchars($product_sku) ?></span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="qty-badge"><?= number_format($item['quantity']) ?></span>
                            </td>
                            <td style="text-align: center; color: var(--text-gray);">
                                <?= htmlspecialchars($item['unit']) ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if (!empty($factor_display)): ?>
                                    <span class="factor-badge"><?= $factor_display ?></span>
                                <?php else: ?>
                                    <span style="color: #e5e7eb;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($request['note'])): ?>
    <div class="note-box">
        <div class="note-label"> Ghi chú yêu cầu</div>
        <div class="note-content"><?= nl2br(htmlspecialchars($request['note'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($request['assignment_note'])): ?>
    <div class="note-box assign">
        <div class="note-label"> Ghi chú chỉ định kho</div>
        <div class="note-content"><?= nl2br(htmlspecialchars($request['assignment_note'])) ?></div>
    </div>
    <?php endif; ?>
</div>
