<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../model/connect.php");
include_once(__DIR__ . "/../../../../model/mProduct.php");
include_once(__DIR__ . "/../../../../model/mWarehouse.php");
include_once(__DIR__ . "/../../../../controller/cRequest.php");

$mProduct = new MProduct();
$mWarehouse = new MWarehouse();
$cRequest = new CRequest();

// Lấy export_id từ URL
$export_id = $_GET['id'] ?? null;
if (!$export_id) {
    echo "<div style='text-align:center;padding:50px;'>Không tìm thấy mã phiếu xuất!</div>";
    exit();
}

// Kết nối MongoDB
$p = new clsKetNoi();
$con = $p->moKetNoi();
if (!$con) {
    echo "<div style='text-align:center;padding:50px;'>Không thể kết nối cơ sở dữ liệu!</div>";
    exit();
}

$transactionsCol = $con->selectCollection('transactions');
$usersCol = $con->selectCollection('users');

// Lấy thông tin phiếu xuất với aggregation để join user info
$pipeline = [
    ['$match' => [
        'transaction_id' => $export_id,
        'transaction_type' => 'export'
    ]],
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
    error_log("Error fetching export: " . $e->getMessage());
    $export = null;
}

if (!$export) {
    echo "<div style='text-align:center;padding:50px;'>Không tìm thấy phiếu xuất kho!</div>";
    $p->dongKetNoi($con);
    exit();
}

// Lấy thông tin người tạo
$creator_name = $export['creator_name'] ?? $export['created_by'];

// DEBUG: Hiển thị cấu trúc export để kiểm tra
// echo "<pre>"; print_r($export); echo "</pre>";

// Lấy thông tin kho
// Trong phiếu xuất: warehouse_id = kho xuất, destination_warehouse_id = kho nhận
$sourceWarehouseId = $export['warehouse_id'] ?? ($export['source_warehouse_id'] ?? 'N/A');
$destinationWarehouseId = $export['destination_warehouse_id'] ?? 'N/A';

// Lấy thông tin chi tiết kho
$sourceWarehouse = null;
$sourceWarehouseName = $sourceWarehouseId;
if ($sourceWarehouseId !== 'N/A') {
    $sourceWarehouse = $mWarehouse->getWarehouseById($sourceWarehouseId);
    $sourceWarehouseName = $sourceWarehouse['name'] ?? $sourceWarehouse['warehouse_name'] ?? $sourceWarehouseId;
}

$destinationWarehouse = null;
$destinationWarehouseName = $destinationWarehouseId;
if ($destinationWarehouseId !== 'N/A') {
    $destinationWarehouse = $mWarehouse->getWarehouseById($destinationWarehouseId);
    $destinationWarehouseName = $destinationWarehouse['name'] ?? $destinationWarehouse['warehouse_name'] ?? $destinationWarehouseId;
}

// Lấy thông tin phiếu yêu cầu gốc (nếu có)
$requestInfo = null;
if (!empty($export['request_id'])) {
    $requestInfo = $cRequest->getRequestById($export['request_id']);
}

// Format date
function formatDate($date) {
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        return $date->toDateTime()->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i');
    }
    return 'N/A';
}
?>

<style>
  .detail-container {max-width:1400px;margin:30px auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
  .detail-header {border-bottom:3px solid #28a745;padding-bottom:20px;margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;}
  .detail-header h2 {color:#333;margin:0;font-size:28px;display:flex;align-items:center;gap:10px;}
  .detail-header .btn-back {background:#6c757d;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;display:inline-block;transition:0.3s;}
  .detail-header .btn-back:hover {background:#5a6268;}
  .detail-header .btn-print {background:#28a745;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;display:inline-block;transition:0.3s;margin-left:10px;border:none;cursor:pointer;}
  .detail-header .btn-print:hover {background:#218838;}
  
  .export-badge {display:inline-block;padding:8px 16px;border-radius:20px;font-weight:600;font-size:14px;background:#d4edda;color:#155724;}
  
  .info-grid {display:grid;grid-template-columns:repeat(2, 1fr);gap:25px;margin-bottom:30px;}
  .info-section {background:#f8f9fa;padding:20px;border-radius:8px;border-left:4px solid #28a745;}
  .info-section.destination {border-left-color:#007bff;}
  .info-section h3 {color:#28a745;font-size:18px;margin-bottom:15px;display:flex;align-items:center;gap:8px;}
  .info-section.destination h3 {color:#007bff;}
  .info-row {display:grid;grid-template-columns:180px 1fr;gap:10px;margin-bottom:12px;font-size:14px;}
  .info-row strong {color:#495057;}
  .info-row span {color:#212529;font-weight:500;}
  
  .warehouse-card {background:#fff;border:2px solid #28a745;border-radius:8px;padding:15px;text-align:center;}
  .warehouse-card.destination {border-color:#007bff;}
  .warehouse-card h4 {margin:0 0 5px 0;color:#28a745;font-size:16px;}
  .warehouse-card.destination h4 {color:#007bff;}
  .warehouse-card .warehouse-name {font-size:18px;font-weight:700;color:#333;margin-top:8px;}
  .warehouse-card .warehouse-id {font-size:13px;color:#6c757d;margin-top:5px;}
  
  .products-section {margin-top:30px;}
  .products-section h3 {color:#28a745;font-size:20px;margin-bottom:15px;border-bottom:2px solid #28a745;padding-bottom:8px;display:flex;align-items:center;gap:8px;}
  .products-table {width:100%;border-collapse:collapse;margin-top:15px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
  .products-table th,.products-table td {padding:12px;border:1px solid #dee2e6;font-size:14px;}
  .products-table th {background:#28a745;color:#fff;font-weight:600;text-align:center;}
  .products-table tbody tr:hover {background:#f1f7ff;}
  .products-table td:nth-child(1) {text-align:center;width:50px;font-weight:600;}
  .products-table td:nth-child(3),.products-table td:nth-child(4),.products-table td:nth-child(5),.products-table td:nth-child(6) {text-align:center;}
  .products-table .sku-text {color:#6c757d;font-size:12px;margin-top:3px;}
  
  .note-section {margin-top:25px;background:#fff3cd;padding:20px;border-radius:8px;border-left:4px solid #ffc107;}
  .note-section h4 {color:#856404;font-size:16px;margin-bottom:10px;display:flex;align-items:center;gap:8px;}
  .note-section p {color:#495057;font-size:14px;line-height:1.6;margin:0;}
  
  .summary-section {margin-top:25px;background:#e7f3ff;padding:20px;border-radius:8px;display:flex;justify-content:space-between;align-items:center;}
  .summary-item {text-align:center;}
  .summary-item .label {font-size:14px;color:#6c757d;margin-bottom:5px;}
  .summary-item .value {font-size:24px;font-weight:700;color:#007bff;}
  
  .request-link {margin-top:25px;background:#d1ecf1;padding:15px 20px;border-radius:8px;border-left:4px solid #17a2b8;display:flex;justify-content:space-between;align-items:center;}
  .request-link .info {font-size:14px;color:#0c5460;}
  .request-link .info strong {font-size:16px;display:block;margin-bottom:5px;}
  .request-link .btn-view-request {background:#17a2b8;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:14px;transition:0.3s;}
  .request-link .btn-view-request:hover {background:#138496;}
  
  .fifo-badge {background:#ffc107;color:#000;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:600;display:inline-block;margin-left:10px;}
  
  @media print {
    .detail-header .btn-back, .detail-header .btn-print, .request-link .btn-view-request {display:none;}
    .detail-container {box-shadow:none;padding:0;}
  }
</style>

<div class="detail-container">
  <div class="detail-header">
    <div>
      <h2>
        <i class="fa-solid fa-truck-fast"></i>
        Chi tiết phiếu xuất kho
      </h2>
      <span style="font-size:24px;color:#28a745;font-weight:700;margin-top:10px;display:block;">
        <?= htmlspecialchars($export['transaction_id']) ?>
      </span>
    </div>
    <div>
      <a href="../index.php?page=exports" class="btn-back">
        <i class="fa-solid fa-arrow-left"></i> Quay lại
      </a>
      <button onclick="window.print()" class="btn-print">
        <i class="fa-solid fa-print"></i> In phiếu
      </button>
    </div>
  </div>

  <!-- Link đến phiếu yêu cầu gốc -->
  <?php if (!empty($export['request_id']) && $requestInfo): ?>
  <div class="request-link">
    <div class="info">
      <strong>📋 Phiếu yêu cầu gốc: <?= htmlspecialchars($export['request_id']) ?></strong>
      <span>Được tạo từ phiếu yêu cầu nhập hàng của <?= htmlspecialchars($requestInfo['warehouse_id'] ?? 'N/A') ?></span>
    </div>
    <a href="index.php?page=goodsReceiptRequest/detail&id=<?= htmlspecialchars($export['request_id']) ?>" class="btn-view-request" target="_blank">
      <i class="fa-solid fa-external-link-alt"></i> Xem phiếu yêu cầu
    </a>
  </div>
  <?php endif; ?>

  <!-- Thông tin kho nguồn và kho đích -->
  <div class="info-grid">
    <div class="info-section">
      <h3><i class="fa-solid fa-warehouse"></i> Kho xuất hàng</h3>
      <div class="warehouse-card">
        <h4><i class="fa-solid fa-arrow-up-from-bracket"></i> XUẤT TỪ</h4>
        <div class="warehouse-name"><?= htmlspecialchars($sourceWarehouseName) ?></div>
        <?php if ($sourceWarehouseId !== 'N/A'): ?>
        <div class="warehouse-id">Mã: <?= htmlspecialchars($sourceWarehouseId) ?></div>
        <?php else: ?>
        <div class="warehouse-id" style="color:#dc3545;">Chưa có thông tin kho</div>
        <?php endif; ?>
      </div>
      <div style="margin-top:15px;">
        <?php if ($sourceWarehouse): ?>
          <?php if (isset($sourceWarehouse['address'])): ?>
          <div class="info-row">
            <strong>Địa chỉ:</strong>
            <span><?= htmlspecialchars($sourceWarehouse['address_text'] ?? 'N/A') ?></span>
          </div>
          <?php elseif (isset($sourceWarehouse['location'])): ?>
          <div class="info-row">
            <strong>Địa chỉ:</strong>
            <span><?= htmlspecialchars($sourceWarehouse['location']) ?></span>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($sourceWarehouse['manager'])): ?>
          <div class="info-row">
            <strong>Quản lý:</strong>
            <span><?= htmlspecialchars($sourceWarehouse['manager']) ?></span>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($sourceWarehouse['phone'])): ?>
          <div class="info-row">
            <strong>Điện thoại:</strong>
            <span><?= htmlspecialchars($sourceWarehouse['phone']) ?></span>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="info-row" style="color:#6c757d;">
            <em>Không có thông tin chi tiết kho</em>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="info-section destination">
      <h3><i class="fa-solid fa-location-dot"></i> Kho nhận hàng</h3>
      <div class="warehouse-card destination">
        <h4><i class="fa-solid fa-arrow-down-to-bracket"></i> NHẬN VỀ</h4>
        <div class="warehouse-name"><?= htmlspecialchars($destinationWarehouseName) ?></div>
        <?php if ($destinationWarehouseId !== 'N/A'): ?>
        <div class="warehouse-id">Mã: <?= htmlspecialchars($destinationWarehouseId) ?></div>
        <?php else: ?>
        <div class="warehouse-id" style="color:#dc3545;">Chưa có thông tin kho</div>
        <?php endif; ?>
      </div>
      <div style="margin-top:15px;">
        <?php if ($destinationWarehouse): ?>
          <?php if (isset($destinationWarehouse['address'])): ?>
          <div class="info-row">
            <strong>Địa chỉ:</strong>
            <span><?= htmlspecialchars($destinationWarehouse['address_text'] ?? 'N/A') ?></span>
          </div>
          <?php elseif (isset($destinationWarehouse['location'])): ?>
          <div class="info-row">
            <strong>Địa chỉ:</strong>
            <span><?= htmlspecialchars($destinationWarehouse['location']) ?></span>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($destinationWarehouse['manager'])): ?>
          <div class="info-row">
            <strong>Quản lý:</strong>
            <span><?= htmlspecialchars($destinationWarehouse['manager']) ?></span>
          </div>
          <?php endif; ?>
          
          <?php if (!empty($destinationWarehouse['phone'])): ?>
          <div class="info-row">
            <strong>Điện thoại:</strong>
            <span><?= htmlspecialchars($destinationWarehouse['phone']) ?></span>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="info-row" style="color:#6c757d;">
            <em>Không có thông tin chi tiết kho</em>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Thông tin xuất kho -->
  <div class="info-section" style="border-left-color:#6c757d;">
    <h3 style="color:#6c757d;"><i class="fa-solid fa-info-circle"></i> Thông tin xuất kho</h3>
    <div class="info-row">
      <strong>Mã phiếu xuất:</strong>
      <span style="font-weight:700;color:#28a745;"><?= htmlspecialchars($export['transaction_id']) ?></span>
    </div>
    <div class="info-row">
      <strong>Ngày xuất:</strong>
      <span><?= formatDate($export['created_at']) ?></span>
    </div>
    <div class="info-row">
      <strong>Người lập phiếu:</strong>
      <span style="font-weight:600;color:#007bff;"><?= htmlspecialchars($creator_name) ?></span>
    </div>
    <div class="info-row">
      <strong>Phương pháp trừ kho:</strong>
      <span>
        FIFO - First In First Out <span class="fifo-badge">⏰ Xuất hàng cũ trước</span>
      </span>
    </div>
  </div>

  <!-- Danh sách sản phẩm -->
  <div class="products-section">
    <h3><i class="fa-solid fa-boxes-stacked"></i> Danh sách sản phẩm xuất kho</h3>
    <table class="products-table">
      <thead>
        <tr>
          <th>STT</th>
          <th style="text-align:left;">Tên sản phẩm</th>
          <th>Số lượng</th>
          <th>Đơn vị</th>
          <th>Quy đổi</th>
          <th>Tổng (ĐV cơ bản)</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        if (!empty($export['details'])):
          $stt = 1;
          $totalItems = 0;
          $totalBaseQty = 0;
          
          foreach ($export['details'] as $item):
            // Lấy thông tin sản phẩm
            $product = $mProduct->getProductBySKU($item['product_id']);
            if (!$product) {
                $product = $mProduct->getProductById($item['product_id']);
            }
            $product_name = $product['name'] ?? $product['product_name'] ?? 'Không tìm thấy sản phẩm';
            $product_sku = $product['sku'] ?? $item['product_id'];
            
            $quantity = $item['quantity'] ?? 0;
            $unit = $item['unit'] ?? 'cái';
            $conversion_factor = $item['conversion_factor'] ?? 1;
            
            // Tính tổng theo đơn vị cơ bản
            $base_quantity = $quantity * $conversion_factor;
            $base_unit = $product['baseUnit'] ?? 'cái';
            
            $totalItems++;
            $totalBaseQty += $base_quantity;
            
            // Hiển thị factor
            $factor_display = '';
            if ($conversion_factor != 1) {
                $factor_display = "x" . number_format($conversion_factor, 0);
            } else {
                $factor_display = "-";
            }
        ?>
          <tr>
            <td><?= $stt++ ?></td>
            <td style="text-align:left;">
              <strong><?= htmlspecialchars($product_name) ?></strong>
              <div class="sku-text">SKU: <?= htmlspecialchars($product_sku) ?></div>
            </td>
            <td><strong style="font-size:16px;color:#007bff;"><?= number_format($quantity) ?></strong></td>
            <td><?= htmlspecialchars($unit) ?></td>
            <td>
              <?php if ($conversion_factor != 1): ?>
                <span style="color:#28a745;font-weight:600;"><?= $factor_display ?></span>
              <?php else: ?>
                <span style="color:#999;"><?= $factor_display ?></span>
              <?php endif; ?>
            </td>
            <td>
              <strong style="font-size:15px;color:#dc3545;">
                <?= number_format($base_quantity) ?> <?= htmlspecialchars($base_unit) ?>
              </strong>
            </td>
          </tr>
        <?php 
          endforeach;
        endif; 
        ?>
      </tbody>
    </table>
  </div>

  <!-- Tổng kết -->
  <div class="summary-section">
    <div class="summary-item">
      <div class="label">Tổng số mặt hàng</div>
      <div class="value"><?= $totalItems ?? 0 ?></div>
    </div>
    <div class="summary-item">
      <div class="label">Tổng số lượng xuất</div>
      <div class="value"><?= number_format($totalBaseQty ?? 0) ?></div>
    </div>
    <div class="summary-item">
      <div class="label">Trạng thái</div>
      <div class="value" style="color:#28a745;">
        <i class="fa-solid fa-check-circle"></i> Đã xuất
      </div>
    </div>
  </div>

  <!-- Ghi chú -->
  <?php if (!empty($export['note'])): ?>
  <div class="note-section">
    <h4><i class="fa-solid fa-note-sticky"></i> Ghi chú</h4>
    <p><?= nl2br(htmlspecialchars($export['note'])) ?></p>
  </div>
  <?php endif; ?>

  <div style="margin-top:30px;text-align:center;color:#6c757d;font-size:12px;padding-top:20px;border-top:1px solid #e9ecef;">
    <p>Phiếu xuất kho được tạo tự động từ hệ thống quản lý kho</p>
    <p>Người lập phiếu: <strong><?= htmlspecialchars($creator_name) ?></strong> | Ngày in: <?= date('d/m/Y H:i:s') ?></p>
  </div>
</div>

<?php
$p->dongKetNoi($con);
?>
