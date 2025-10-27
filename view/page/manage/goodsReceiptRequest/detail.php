<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../model/mProduct.php");
include_once(__DIR__ . "/../../../../model/mWarehouse.php");

$cRequest = new CRequest();
$mProduct = new MProduct();
$mWarehouse = new MWarehouse();

// Lấy request_id từ URL
$request_id = $_GET['id'] ?? null;
if (!$request_id) {
    echo "<div style='text-align:center;padding:50px;'>Không tìm thấy mã phiếu!</div>";
    exit();
}

// Lấy thông tin phiếu yêu cầu
$request = $cRequest->getRequestById($request_id);
if (!$request) {
    echo "<div style='text-align:center;padding:50px;'>Không tìm thấy phiếu yêu cầu!</div>";
    exit();
}

// Lấy thông tin người tạo (từ aggregation nếu có, nếu không dùng ID)
$creator_name = $request['creator_name'] ?? $request['created_by'];

// Lấy thông tin người duyệt (nếu có)
$approver_name = '';
if (!empty($request['approved_by'])) {
    $approver_name = $request['approver_name'] ?? $request['approved_by'];
}

// Lấy thông tin người xử lý kho (nếu có)
$processor_name = '';
if (!empty($request['processed_by'])) {
    $processor_name = $request['processor_name'] ?? $request['processed_by'];
}

// Lấy thông tin người chỉ định kho (nếu có)
$assigner_name = '';
if (!empty($request['assigned_by'])) {
    $assigner_name = $request['assigner_name'] ?? $request['assigned_by'];
}

// Lấy thông tin kho
$warehouse = $mWarehouse->getWarehouseById($request['warehouse_id']);
$warehouse_name = $warehouse['name'] ?? $request['warehouse_id'];

$source_warehouse = $mWarehouse->getWarehouseById($request['source_warehouse_id']);
$source_warehouse_name = $source_warehouse['name'] ?? $request['source_warehouse_id'];

// Lấy thông tin kho được chỉ định (nếu có)
$assigned_warehouse_name = '';
if (!empty($request['assigned_warehouse_id'])) {
    $assigned_warehouse = $mWarehouse->getWarehouseById($request['assigned_warehouse_id']);
    $assigned_warehouse_name = $assigned_warehouse['name'] ?? $request['assigned_warehouse_id'];
}

// Status mapping
$statusMap = [
    0 => ['label' => 'Chờ duyệt', 'color' => '#ffc107', 'bg' => '#fff3cd'],
    1 => ['label' => 'Đã duyệt', 'color' => '#28a745', 'bg' => '#d4edda'],
    2 => ['label' => 'Từ chối', 'color' => '#dc3545', 'bg' => '#f8d7da'],
    3 => ['label' => 'Đủ hàng', 'color' => '#17a2b8', 'bg' => '#d1ecf1'],
    4 => ['label' => 'Thiếu hàng', 'color' => '#fd7e14', 'bg' => '#ffe5d0'],
    5 => ['label' => 'Đã chỉ định kho', 'color' => '#6f42c1', 'bg' => '#e7d6f5'],
    6 => ['label' => 'Hoàn thành', 'color' => '#20c997', 'bg' => '#d4f4e8']
];

$currentStatus = $statusMap[$request['status']] ?? ['label' => 'Không xác định', 'color' => '#6c757d', 'bg' => '#e9ecef'];

// Priority mapping
$priorityMap = [
    'normal' => ['label' => 'Bình thường', 'color' => '#007bff', 'icon' => '📋'],
    'urgent' => ['label' => 'Khẩn cấp', 'color' => '#dc3545', 'icon' => '🚨']
];

$priority = $priorityMap[$request['priority']] ?? $priorityMap['normal'];

// Format date
function formatDate($date) {
    if ($date instanceof MongoDB\BSON\UTCDateTime) {
        return $date->toDateTime()->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i');
    }
    return 'N/A';
}
?>

<style>
  .detail-container {max-width:1200px;margin:30px auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
  .detail-header {border-bottom:3px solid #007bff;padding-bottom:20px;margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;}
  .detail-header h2 {color:#333;margin:0;font-size:28px;}
  .detail-header .btn-back {background:#6c757d;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;display:inline-block;transition:0.3s;}
  .detail-header .btn-back:hover {background:#5a6268;}
  
  .status-badge {display:inline-block;padding:8px 16px;border-radius:20px;font-weight:600;font-size:14px;margin-left:10px;}
  .priority-badge {display:inline-block;padding:6px 12px;border-radius:15px;font-weight:600;font-size:13px;margin-left:10px;}
  
  .info-section {background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:25px;}
  .info-section h3 {color:#007bff;font-size:18px;margin-bottom:15px;border-bottom:2px solid #007bff;padding-bottom:8px;}
  .info-row {display:grid;grid-template-columns:200px 1fr;gap:10px;margin-bottom:12px;font-size:14px;}
  .info-row strong {color:#495057;}
  .info-row span {color:#212529;}
  
  .products-section {margin-top:30px;}
  .products-section h3 {color:#007bff;font-size:18px;margin-bottom:15px;border-bottom:2px solid #007bff;padding-bottom:8px;}
  .products-table {width:100%;border-collapse:collapse;margin-top:15px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
  .products-table th,.products-table td {padding:12px;border:1px solid #dee2e6;text-align:left;font-size:14px;}
  .products-table th {background:#007bff;color:#fff;font-weight:600;text-align:center;}
  .products-table tbody tr:hover {background:#f1f7ff;}
  .products-table td:nth-child(1) {text-align:center;width:50px;}
  .products-table td:nth-child(3),.products-table td:nth-child(4),.products-table td:nth-child(5) {text-align:center;}
  
  .history-section {margin-top:30px;background:#fff8e1;padding:20px;border-radius:8px;border-left:4px solid #ffc107;}
  .history-section h3 {color:#f57c00;font-size:18px;margin-bottom:15px;}
  .history-item {background:#fff;padding:12px 16px;border-radius:6px;margin-bottom:10px;border-left:3px solid #28a745;font-size:14px;}
  .history-item:last-child {margin-bottom:0;}
  .history-item strong {color:#007bff;}
  .history-item .time {color:#6c757d;font-size:12px;margin-top:5px;}
  
  .note-section {margin-top:25px;background:#e7f3ff;padding:20px;border-radius:8px;border-left:4px solid #007bff;}
  .note-section h4 {color:#007bff;font-size:16px;margin-bottom:10px;}
  .note-section p {color:#495057;font-size:14px;line-height:1.6;margin:0;}
</style>

<div class="detail-container">
  <div class="detail-header">
    <div>
      <h2>Chi tiết phiếu yêu cầu: <?= htmlspecialchars($request['transaction_id']) ?></h2>
      <span class="priority-badge" style="background:<?= $priority['color'] ?>20;color:<?= $priority['color'] ?>;">
        <?= $priority['icon'] ?> <?= $priority['label'] ?>
      </span>
      <span class="status-badge" style="background:<?= $currentStatus['bg'] ?>;color:<?= $currentStatus['color'] ?>;">
        <?= $currentStatus['label'] ?>
      </span>
    </div>
    <a href="index.php?page=goodsReceiptRequest" class="btn-back">← Quay lại</a>
  </div>

  <!-- Thông tin chung -->
  <div class="info-section">
    <h3>📄 Thông tin chung</h3>
    <div class="info-row">
      <strong>Mã phiếu:</strong>
      <span><?= htmlspecialchars($request['transaction_id']) ?></span>
    </div>
    <div class="info-row">
      <strong>Loại phiếu:</strong>
      <span>Yêu cầu nhập hàng</span>
    </div>
    <div class="info-row">
      <strong>Mức độ ưu tiên:</strong>
      <span style="color:<?= $priority['color'] ?>;font-weight:600;">
        <?= $priority['icon'] ?> <?= $priority['label'] ?>
      </span>
    </div>
    <div class="info-row">
      <strong>Trạng thái:</strong>
      <span style="color:<?= $currentStatus['color'] ?>;font-weight:600;">
        <?= $currentStatus['label'] ?>
      </span>
    </div>
    <div class="info-row">
      <strong>Kho yêu cầu (đích):</strong>
      <span><?= htmlspecialchars($warehouse_name) ?> (<?= htmlspecialchars($request['warehouse_id']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Kho nguồn:</strong>
      <span><?= htmlspecialchars($source_warehouse_name) ?> (<?= htmlspecialchars($request['source_warehouse_id']) ?>)</span>
    </div>
    <?php if (!empty($request['assigned_warehouse_id'])): ?>
    <div class="info-row">
      <strong>Kho được chỉ định:</strong>
      <span style="color:#6f42c1;font-weight:600;">
        <?= htmlspecialchars($assigned_warehouse_name) ?> (<?= htmlspecialchars($request['assigned_warehouse_id']) ?>)
      </span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Thông tin người xử lý -->
  <div class="info-section">
    <h3>👤 Thông tin người xử lý</h3>
    <div class="info-row">
      <strong>Người tạo:</strong>
      <span><?= htmlspecialchars($creator_name) ?> (<?= htmlspecialchars($request['created_by']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Ngày tạo:</strong>
      <span><?= formatDate($request['created_at']) ?></span>
    </div>
    <?php if (!empty($request['approved_by'])): ?>
    <div class="info-row">
      <strong>Người duyệt:</strong>
      <span><?= htmlspecialchars($approver_name) ?> (<?= htmlspecialchars($request['approved_by']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Ngày duyệt:</strong>
      <span><?= formatDate($request['approved_at']) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($request['processed_by'])): ?>
    <div class="info-row">
      <strong>Người xử lý kho:</strong>
      <span><?= htmlspecialchars($processor_name) ?> (<?= htmlspecialchars($request['processed_by']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Ngày xử lý:</strong>
      <span><?= formatDate($request['processed_at']) ?></span>
    </div>
    <?php endif; ?>
    <?php if (!empty($request['assigned_by'])): ?>
    <div class="info-row">
      <strong>Người chỉ định kho:</strong>
      <span><?= htmlspecialchars($assigner_name) ?> (<?= htmlspecialchars($request['assigned_by']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Ngày chỉ định:</strong>
      <span><?= formatDate($request['assigned_at']) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Danh sách sản phẩm -->
  <div class="products-section">
    <h3>📦 Danh sách sản phẩm yêu cầu</h3>
    <table class="products-table">
      <thead>
        <tr>
          <th>STT</th>
          <th>Tên sản phẩm</th>
          <th>Số lượng</th>
          <th>Đơn vị</th>
          <th>Quy đổi</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $details = $request['details'] ?? [];
        if (empty($details)): 
        ?>
          <tr>
            <td colspan="5" style="text-align:center;color:#999;">Không có sản phẩm nào</td>
          </tr>
        <?php else: 
          $stt = 1;
          foreach ($details as $item):
            // Lấy thông tin sản phẩm - thử tìm theo SKU trước
            $product = $mProduct->getProductBySKU($item['product_id']);
            // Nếu không tìm thấy theo SKU, thử theo ID
            if (!$product) {
                $product = $mProduct->getProductById($item['product_id']);
            }
            $product_name = $product['name'] ?? $product['product_name'] ?? 'Không tìm thấy sản phẩm';
            $product_sku = $product['sku'] ?? $item['product_id'];
            
            // Hiển thị factor quy đổi
            $factor_display = '';
            
            // Lấy factor từ $item nếu có
            $factor = $item['conversion_factor'] ?? null;
            
            // Nếu không có factor trong $item, tìm từ product's conversionUnits
            if (empty($factor) || $factor == 1) {
                $item_unit = $item['unit'] ?? '';
                $base_unit = $product['baseUnit'] ?? 'cái';
                
                // Nếu đơn vị khác đơn vị cơ bản, tìm factor
                if ($item_unit != $base_unit && $product && isset($product['conversionUnits'])) {
                    foreach ($product['conversionUnits'] as $conv) {
                        if (isset($conv['unit']) && $conv['unit'] == $item_unit) {
                            $factor = $conv['factor'] ?? null;
                            break;
                        }
                    }
                }
            }
            
            // Hiển thị factor nếu có và khác 1
            if (!empty($factor) && $factor != 1) {
                $factor_display = "x" . number_format($factor, 0);
            }
        ?>
          <tr>
            <td><?= $stt++ ?></td>
            <td>
              <strong><?= htmlspecialchars($product_name) ?></strong><br>
              <small style="color:#6c757d;">SKU: <?= htmlspecialchars($product_sku) ?></small>
            </td>
            <td><strong><?= number_format($item['quantity']) ?></strong></td>
            <td><?= htmlspecialchars($item['unit']) ?></td>
            <td>
              <?php if (!empty($factor_display)): ?>
                <span style="color:#007bff;font-size:14px;font-weight:600;"><?= $factor_display ?></span>
              <?php else: ?>
                <span style="color:#999;">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Lịch sử xử lý -->
  <div class="history-section">
    <h3>📋 Lịch sử xử lý</h3>
    
    <div class="history-item">
      <strong>Tạo phiếu</strong> - <?= htmlspecialchars($creator_name) ?>
      <div class="time">🕒 <?= formatDate($request['created_at']) ?></div>
    </div>
    
    <?php if ($request['status'] >= 1): ?>
    <div class="history-item">
      <strong>Duyệt phiếu</strong> - <?= htmlspecialchars($approver_name) ?>
      <div class="time">🕒 <?= formatDate($request['approved_at']) ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($request['status'] == 2): ?>
    <div class="history-item" style="border-left-color:#dc3545;">
      <strong style="color:#dc3545;">Từ chối phiếu</strong> - <?= htmlspecialchars($approver_name) ?>
      <div class="time">🕒 <?= formatDate($request['approved_at']) ?></div>
      <?php if (!empty($request['rejection_reason'])): ?>
      <div style="margin-top:8px;color:#dc3545;font-size:13px;">
        Lý do: <?= htmlspecialchars($request['rejection_reason']) ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($request['status'] >= 3): ?>
    <div class="history-item">
      <strong>Kiểm tra kho</strong> - <?= htmlspecialchars($processor_name) ?>
      <div class="time">🕒 <?= formatDate($request['processed_at']) ?></div>
      <div style="margin-top:5px;color:#28a745;font-size:13px;">
        ✅ Kho có đủ hàng
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($request['status'] == 4): ?>
    <div class="history-item" style="border-left-color:#fd7e14;">
      <strong style="color:#fd7e14;">Kiểm tra kho</strong> - <?= htmlspecialchars($processor_name) ?>
      <div class="time">🕒 <?= formatDate($request['processed_at']) ?></div>
      <div style="margin-top:5px;color:#fd7e14;font-size:13px;">
        ⚠️ Kho thiếu hàng - Cần chỉ định kho khác
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($request['status'] >= 5): ?>
    <div class="history-item" style="border-left-color:#6f42c1;">
      <strong style="color:#6f42c1;">Chỉ định kho</strong> - <?= htmlspecialchars($assigner_name) ?>
      <div class="time">🕒 <?= formatDate($request['assigned_at']) ?></div>
      <div style="margin-top:5px;color:#6f42c1;font-size:13px;">
        📍 Kho: <?= htmlspecialchars($assigned_warehouse_name) ?>
      </div>
    </div>
    <?php endif; ?>
    
    <?php if ($request['status'] == 6): ?>
    <div class="history-item" style="border-left-color:#20c997;">
      <strong style="color:#20c997;">Hoàn thành</strong>
      <div class="time">🕒 <?= formatDate($request['updated_at']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Ghi chú -->
  <?php if (!empty($request['note'])): ?>
  <div class="note-section">
    <h4>📝 Ghi chú</h4>
    <p><?= nl2br(htmlspecialchars($request['note'])) ?></p>
  </div>
  <?php endif; ?>
  
  <?php if (!empty($request['assignment_note'])): ?>
  <div class="note-section" style="background:#f3e5f5;border-left-color:#6f42c1;">
    <h4 style="color:#6f42c1;">📝 Ghi chú chỉ định kho</h4>
    <p><?= nl2br(htmlspecialchars($request['assignment_note'])) ?></p>
  </div>
  <?php endif; ?>
</div>
