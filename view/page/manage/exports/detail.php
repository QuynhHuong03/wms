<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- GIỮ NGUYÊN PHẦN LOGIC PHP ---
include_once(__DIR__ . "/../../../../model/connect.php");
include_once(__DIR__ . "/../../../../model/mProduct.php");
include_once(__DIR__ . "/../../../../model/mWarehouse.php");
include_once(__DIR__ . "/../../../../controller/cRequest.php");

$mProduct = new MProduct();
$mWarehouse = new MWarehouse();
$cRequest = new CRequest();

$export_id = $_GET['id'] ?? null;
if (!$export_id) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'></head><body>";
    echo "<div class='alert alert-danger text-center m-5'><strong>Lỗi:</strong> Không tìm thấy mã phiếu xuất! (ID = NULL)</div>";
    echo "<div class='text-center'><a href='index.php?page=exports' class='btn btn-primary'>Quay lại danh sách</a></div>";
    echo "</body></html>";
    exit();
}

$p = new clsKetNoi();
$con = $p->moKetNoi();
if (!$con) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'></head><body>";
    echo "<div class='alert alert-danger text-center m-5'><strong>Lỗi kết nối:</strong> Không thể kết nối cơ sở dữ liệu MongoDB!</div>";
    echo "<div class='text-center'><a href='index.php?page=exports' class='btn btn-primary'>Quay lại danh sách</a></div>";
    echo "</body></html>";
    exit();
}

$transactionsCol = $con->selectCollection('transactions');

$pipeline = [
    ['$match' => ['transaction_id' => $export_id, 'transaction_type' => 'export']],
    [
        '$lookup' => [
            'from' => 'users',
            'localField' => 'created_by',
            'foreignField' => 'user_id',
            'as' => 'creator_info'
        ]
    ],
    [
        '$addFields' => [
            'creator_name' => [
                '$ifNull' => [
                    ['$arrayElemAt' => ['$creator_info.full_name', 0]],
                    '$created_by'
                ]
            ]
        ]
    ]
];

try {
    $result = $transactionsCol->aggregate($pipeline);
    $exports = iterator_to_array($result);
    $export = $exports[0] ?? null;
} catch (Exception $e) {
    $export = null;
    $error_message = $e->getMessage();
}

if (!$export) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'></head><body>";
    echo "<div class='alert alert-warning text-center m-5'>";
    echo "<h4>⚠️ Không tìm thấy phiếu xuất kho!</h4>";
    echo "<p><strong>Mã phiếu tìm kiếm:</strong> " . htmlspecialchars($export_id) . "</p>";
    if (isset($error_message)) {
        echo "<p><strong>Lỗi:</strong> " . htmlspecialchars($error_message) . "</p>";
    }
    echo "<hr><p>Vui lòng kiểm tra lại mã phiếu xuất hoặc liên hệ quản trị viên.</p>";
    echo "</div>";
    echo "<div class='text-center'><a href='index.php?page=exports' class='btn btn-primary'>Quay lại danh sách</a></div>";
    echo "</body></html>";
    $p->dongKetNoi($con);
    exit();
}

$creator_name = $export['creator_name'] ?? $export['created_by'];
$sourceWarehouseId = $export['warehouse_id'] ?? ($export['source_warehouse_id'] ?? 'N/A');
$destinationWarehouseId = $export['destination_warehouse_id'] ?? 'N/A';

$sourceWarehouse = ($sourceWarehouseId !== 'N/A') ? $mWarehouse->getWarehouseById($sourceWarehouseId) : null;
$sourceWarehouseName = $sourceWarehouse['name'] ?? $sourceWarehouse['warehouse_name'] ?? $sourceWarehouseId;

$destinationWarehouse = ($destinationWarehouseId !== 'N/A') ? $mWarehouse->getWarehouseById($destinationWarehouseId) : null;
$destinationWarehouseName = $destinationWarehouse['name'] ?? $destinationWarehouse['warehouse_name'] ?? $destinationWarehouseId;

$requestInfo = (!empty($export['request_id'])) ? $cRequest->getRequestById($export['request_id']) : null;

function formatDate($date) {
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        return $date->toDateTime()->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i');
    }
    return 'N/A';
}
// --- KẾT THÚC LOGIC PHP ---
?>

<style>
    :root {
        --primary-color: #435ebe; /* Màu chủ đạo admin */
        --secondary-color: #6c757d;
        --success-color: #198754;
        --bg-light: #f2f7ff;
        --border-color: #dfe3e7;
        --card-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
    }

    body { background-color: #f5f7fa; font-family: 'Nunito', 'Segoe UI', sans-serif; color: #25396f; }
    
    .invoice-card {
        background: white;
        border-radius: 15px;
        box-shadow: var(--card-shadow);
        border: none;
        overflow: hidden;
        margin-bottom: 30px;
    }

    .invoice-header {
        background: #fff;
        padding: 25px 30px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .invoice-title h4 { margin: 0; font-weight: 700; color: var(--primary-color); }
    .invoice-title span { font-size: 0.9rem; color: var(--secondary-color); }

    .status-badge {
        background: #e8fdf0;
        color: var(--success-color);
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 0.85rem;
        font-weight: 700;
        border: 1px solid #d1e7dd;
    }

    .info-section { padding: 30px; }

    /* Timeline Route Style */
    .route-container {
        display: flex;
        align-items: stretch;
        background: var(--bg-light);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid #e6eaf0;
    }

    .route-box { flex: 1; display: flex; flex-direction: column; gap: 5px; }
    .route-box .label { font-size: 0.8rem; text-transform: uppercase; color: var(--secondary-color); font-weight: 600; letter-spacing: 0.5px; }
    .route-box .wh-name { font-size: 1.1rem; font-weight: 700; color: #000; }
    .route-box .wh-detail { font-size: 0.9rem; color: #555; display: flex; align-items: center; gap: 6px; }

    .route-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 30px;
        color: var(--primary-color);
        font-size: 1.5rem;
        opacity: 0.5;
    }

    .meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .meta-item strong { display: block; color: var(--secondary-color); font-size: 0.85rem; margin-bottom: 4px; }
    .meta-item span { font-size: 1rem; font-weight: 600; color: #25396f; }

    /* Table Styling */
    .custom-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .custom-table thead th {
        background: #f8f9fa;
        color: #444;
        font-weight: 600;
        padding: 12px 15px;
        border-bottom: 2px solid var(--border-color);
        text-align: left;
    }
    .custom-table tbody td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }
    .custom-table tbody tr:last-child td { border-bottom: none; }
    .custom-table .num { text-align: center; font-weight: 600; color: var(--secondary-color); }
    .custom-table .qty-badge {
        background: #ebf3ff; color: var(--primary-color);
        padding: 4px 10px; border-radius: 6px; font-weight: 700;
    }
    
    .summary-box {
        background: #fff;
        border-top: 1px solid var(--border-color);
        padding: 20px 30px;
        display: flex;
        justify-content: flex-end;
        gap: 40px;
    }
    .summary-item { text-align: right; }
    .summary-item .s-label { font-size: 0.9rem; color: var(--secondary-color); }
    .summary-item .s-value { font-size: 1.3rem; font-weight: 800; color: var(--primary-color); }

    .note-box {
        margin: 0 30px 30px 30px;
        padding: 15px;
        background: #fff8e1;
        border-left: 4px solid #ffc107;
        border-radius: 4px;
        color: #856404;
        font-size: 0.95rem;
    }

    .btn-action {
        padding: 8px 16px; border-radius: 8px; text-decoration: none;
        font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;
        transition: all 0.2s; border: none; cursor: pointer;
    }
    .btn-back { background: #e9ecef; color: #495057; }
    .btn-back:hover { background: #dee2e6; color: #212529; }
    .btn-print { background: var(--primary-color); color: white; }
    .btn-print:hover { background: #30419e; color: white; }
    .link-request { color: #0dcaf0; text-decoration: none; font-weight: 600; }
    
    @media print {
        body { background: white; }
        .invoice-card { box-shadow: none; border: 1px solid #ccc; margin: 0; border-radius: 0; }
        .btn-action, .detail-header button { display: none !important; }
        .route-container { background: white; border: 1px solid #ddd; }
        .status-badge { border: 1px solid #000; color: #000; }
    }
    @media (max-width: 768px) {
        .route-container { flex-direction: column; gap: 15px; }
        .route-arrow { transform: rotate(90deg); padding: 10px; }
        .summary-box { flex-direction: column; gap: 15px; align-items: flex-end; }
    }
</style>

<div class="container-fluid p-4" style="max-width: 1200px; margin: 0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="index.php?page=exports" class="btn-action btn-back">
            <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
        </a>
        <div class="d-flex gap-2">
            <?php if (!empty($export['request_id'])): ?>
                <a href="index.php?page=goodsReceiptRequest/detail&id=<?= htmlspecialchars($export['request_id']) ?>" class="btn-action" style="background:#e0f7fa; color:#006064;">
                    <i class="fa-solid fa-file-import"></i> Xem phiếu yêu cầu
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-action btn-print">
                <i class="fa-solid fa-print"></i> In phiếu xuất
            </button>
        </div>
    </div>

    <div class="invoice-card">
        <div class="invoice-header">
            <div class="invoice-title">
                <h4><i class="fa-solid fa-dolly"></i> PHIẾU XUẤT KHO</h4>
                <span>Mã phiếu: #<?= htmlspecialchars($export['transaction_id']) ?></span>
            </div>
            <div class="status-badge">
                <i class="fa-solid fa-check-circle"></i> ĐÃ HOÀN THÀNH
            </div>
        </div>

        <div class="info-section">
            <div class="meta-grid">
                <div class="meta-item">
                    <strong><i class="fa-regular fa-calendar"></i> Ngày tạo</strong>
                    <span><?= formatDate($export['created_at']) ?></span>
                </div>
                <div class="meta-item">
                    <strong><i class="fa-regular fa-user"></i> Người thực hiện</strong>
                    <span><?= htmlspecialchars($creator_name) ?></span>
                </div>
                <div class="meta-item">
                    <strong><i class="fa-solid fa-sort-amount-down"></i> Phương pháp</strong>
                    <span>FIFO (Nhập trước xuất trước)</span>
                </div>
                <?php if ($requestInfo): ?>
                <div class="meta-item">
                    <strong><i class="fa-solid fa-link"></i> Chứng từ gốc</strong>
                    <span class="link-request">#<?= htmlspecialchars($export['request_id']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="route-container">
                <div class="route-box">
                    <div class="label">Từ kho (Nguồn)</div>
                    <div class="wh-name text-danger"><?= htmlspecialchars($sourceWarehouseName) ?></div>
                    <div class="wh-detail">
                        <i class="fa-solid fa-location-dot"></i> 
                        <?= htmlspecialchars($sourceWarehouse['address_text'] ?? $sourceWarehouse['location'] ?? 'N/A') ?>
                    </div>
                    <?php if(isset($sourceWarehouse['manager'])): ?>
                        <div class="wh-detail"><i class="fa-solid fa-user-tie"></i> QL: <?= htmlspecialchars($sourceWarehouse['manager']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="route-arrow">
                    <i class="fa-solid fa-circle-arrow-right"></i>
                </div>

                <div class="route-box" style="text-align: right; align-items: flex-end;">
                    <div class="label">Đến kho (Đích)</div>
                    <div class="wh-name text-success"><?= htmlspecialchars($destinationWarehouseName) ?></div>
                    <div class="wh-detail">
                        <?= htmlspecialchars($destinationWarehouse['address_text'] ?? $destinationWarehouse['location'] ?? 'N/A') ?>
                        <i class="fa-solid fa-location-dot"></i> 
                    </div>
                    <?php if(isset($destinationWarehouse['manager'])): ?>
                        <div class="wh-detail">QL: <?= htmlspecialchars($destinationWarehouse['manager']) ?> <i class="fa-solid fa-user-tie"></i></div>
                    <?php endif; ?>
                </div>
            </div>

            <h5 style="margin-bottom: 15px; font-weight: 700; color: #333;">Chi tiết hàng hóa</h5>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Mặt hàng / Mã SKU</th>
                            <th class="text-center">ĐVT</th>
                            <th class="text-center">Số lượng xuất</th>
                            <th class="text-center">Quy đổi</th>
                            <th class="text-end">Tổng ĐV cơ bản</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stt = 1; $totalBaseQty = 0;
                        if (!empty($export['details'])):
                            foreach ($export['details'] as $item):
                                $product = $mProduct->getProductBySKU($item['product_id']);
                                if (!$product) $product = $mProduct->getProductById($item['product_id']);
                                
                                $pName = $product['name'] ?? $product['product_name'] ?? 'Unknown';
                                $pSku = $product['sku'] ?? $item['product_id'];
                                $qty = $item['quantity'] ?? 0;
                                $unit = $item['unit'] ?? 'cái';
                                $factor = $item['conversion_factor'] ?? 1;
                                $base_qty = $qty * $factor;
                                $base_unit = $product['baseUnit'] ?? 'cái';
                                $totalBaseQty += $base_qty;
                        ?>
                        <tr>
                            <td class="num"><?= $stt++ ?></td>
                            <td>
                                <div style="font-weight: 600; color: #2d3436;"><?= htmlspecialchars($pName) ?></div>
                                <small style="color: #999;">SKU: <?= htmlspecialchars($pSku) ?></small>
                            </td>
                            <td class="text-center"><?= htmlspecialchars($unit) ?></td>
                            <td class="text-center">
                                <span class="qty-badge"><?= number_format($qty) ?></span>
                            </td>
                            <td class="text-center" style="color: #777;">
                                <?= ($factor != 1) ? "x " . number_format($factor) : "-" ?>
                            </td>
                            <td class="text-end" style="font-weight: 700; color: var(--primary-color);">
                                <?= number_format($base_qty) ?> <small><?= htmlspecialchars($base_unit) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($export['note'])): ?>
            <div class="note-box">
                <strong><i class="fa-regular fa-sticky-note"></i> Ghi chú:</strong><br>
                <?= nl2br(htmlspecialchars($export['note'])) ?>
            </div>
        <?php endif; ?>

        <div class="summary-box">
            <div class="summary-item">
                <div class="s-label">Tổng số mặt hàng</div>
                <div class="s-value" style="color: #555;"><?= $stt - 1 ?></div>
            </div>
            <div class="summary-item">
                <div class="s-label">Tổng số lượng thực tế</div>
                <div class="s-value"><?= number_format($totalBaseQty) ?></div>
            </div>
        </div>
    </div>
    
    <div class="text-center text-muted" style="font-size: 0.8rem;">
        Hệ thống quản lý kho - In lúc: <?= date('H:i d/m/Y') ?>
    </div>
</div>

<?php $p->dongKetNoi($con); ?>
