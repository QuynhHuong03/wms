<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../../controller/cInventory.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

// Lấy request_id từ URL
$request_id = $_GET['request_id'] ?? null;

if (!$request_id) {
    $_SESSION['flash_request_error'] = 'Không tìm thấy mã phiếu yêu cầu!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

$cRequest = new CRequest();
$cInventory = new CInventory();
$cWarehouse = new CWarehouse();

// Lấy thông tin phiếu yêu cầu
$request = $cRequest->getRequestById($request_id);

if (!$request) {
    $_SESSION['flash_request_error'] = 'Không tìm thấy phiếu yêu cầu!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

// Kiểm tra status phải là 3 (đã xác nhận đủ hàng)
if ($request['status'] != 3) {
    $_SESSION['flash_request_error'] = 'Phiếu chưa được xác nhận đủ hàng!';
    header("Location: ../../index.php?page=goodsReceiptRequest");
    exit();
}

// Lấy thông tin kho
$sourceWarehouse = $cWarehouse->getWarehouseById($request['source_warehouse_id']);
$destWarehouse = $cWarehouse->getWarehouseById($request['warehouse_id']);
?>

<style>
  .export-container {max-width:1200px;margin:30px auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
  .export-header {border-bottom:3px solid #ff9800;padding-bottom:20px;margin-bottom:30px;}
  .export-header h2 {color:#ff9800;margin:0;font-size:28px;}
  .export-header .subtitle {color:#666;font-size:14px;margin-top:8px;}
  
  .info-section {background:#fff8e1;padding:20px;border-radius:8px;margin-bottom:25px;border-left:4px solid #ff9800;}
  .info-section h3 {color:#ff9800;font-size:18px;margin-bottom:15px;}
  .info-row {display:grid;grid-template-columns:200px 1fr;gap:10px;margin-bottom:12px;font-size:14px;}
  .info-row strong {color:#495057;}
  
  .products-section {margin-top:30px;}
  .products-section h3 {color:#ff9800;font-size:18px;margin-bottom:15px;border-bottom:2px solid #ff9800;padding-bottom:8px;}
  .products-table {width:100%;border-collapse:collapse;margin-top:15px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
  .products-table th,.products-table td {padding:12px;border:1px solid #dee2e6;text-align:left;font-size:14px;}
  .products-table th {background:#ff9800;color:#fff;font-weight:600;text-align:center;}
  .products-table tbody tr:hover {background:#fff8e1;}
  .products-table td:nth-child(1) {text-align:center;width:50px;}
  .products-table td:nth-child(3),.products-table td:nth-child(4),.products-table td:nth-child(5) {text-align:center;}
  
  .note-section {margin-top:25px;}
  .note-section label {display:block;font-weight:600;margin-bottom:10px;color:#333;}
  .note-section textarea {width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;min-height:100px;font-family:inherit;}
  
  .actions {margin-top:30px;display:flex;gap:15px;justify-content:flex-end;}
  .btn {padding:12px 30px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:0.3s;}
  .btn-submit {background:#ff9800;color:#fff;}
  .btn-submit:hover {background:#f57c00;}
  .btn-cancel {background:#6c757d;color:#fff;}
  .btn-cancel:hover {background:#5a6268;}
  
  .warning-box {background:#fff3cd;border:1px solid #ffc107;padding:15px;border-radius:8px;margin-bottom:20px;color:#856404;}
  .warning-box strong {color:#d97706;}
  
  /* Modal styles */
  .warehouse-modal {display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;}
  .warehouse-modal.active {display:flex;}
  .warehouse-modal-content {background:#fff;border-radius:12px;max-width:800px;width:90%;max-height:80vh;overflow:hidden;display:flex;flex-direction:column;}
  .warehouse-modal-header {background:#007bff;color:#fff;padding:20px;display:flex;justify-content:space-between;align-items:center;}
  .warehouse-modal-header h3 {margin:0;font-size:20px;}
  .warehouse-modal-close {background:transparent;border:none;color:#fff;font-size:24px;cursor:pointer;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:0.3s;}
  .warehouse-modal-close:hover {background:rgba(255,255,255,0.2);}
  .warehouse-modal-body {padding:20px;overflow-y:auto;flex:1;}
  .warehouse-modal-footer {padding:20px;border-top:1px solid #dee2e6;display:flex;justify-content:flex-end;gap:10px;}
  
  .warehouse-item {border:1px solid #dee2e6;border-radius:8px;padding:15px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;transition:0.3s;}
  .warehouse-item:hover {border-color:#007bff;background:#f0f8ff;}
  .warehouse-info {flex:1;}
  .warehouse-info .name {font-weight:600;font-size:15px;color:#333;margin-bottom:5px;}
  .warehouse-info .stock {font-size:14px;color:#666;}
  .warehouse-info .stock strong {color:#28a745;font-size:16px;}
  .warehouse-qty-input {display:flex;align-items:center;gap:10px;}
  .warehouse-qty-input input {width:100px;padding:8px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:14px;}
  .warehouse-qty-input input:focus {border-color:#007bff;outline:none;}
  
  .summary-box {background:#e3f2fd;border:1px solid #2196f3;padding:15px;border-radius:8px;margin-top:15px;}
  .summary-box .summary-item {display:flex;justify-content:space-between;padding:8px 0;font-size:14px;}
  .summary-box .summary-item strong {font-size:16px;}
  .summary-box .total {border-top:2px solid #2196f3;padding-top:10px;margin-top:10px;font-weight:600;font-size:16px;}
</style>

<?php
// Compute web app root (e.g. '/KLTN' or '/kltn') so we can build absolute URLs from document root
$appRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($appRoot === '/') $appRoot = '';

// Build form action robustly:
// - If this file is included via the main entry point (index.php?page=...), use an absolute path from app root
// - If this file is accessed directly (e.g. /view/page/manage/exports/create/index.php), use the local relative script
if (basename($_SERVER['SCRIPT_NAME']) === 'index.php' && isset($_GET['page'])) {
  $action = $appRoot . '/exports/create/process_multi_warehouse.php';
} else {
  // Accessed directly from its folder
  $action = 'process_multi_warehouse.php';
}
?>

<div class="export-container">
  <div class="export-header">
    <h2>📤 Tạo phiếu xuất kho</h2>
    <div class="subtitle">Từ phiếu yêu cầu: <?= htmlspecialchars($request_id) ?></div>
  </div>

  <div class="warning-box">
    <strong>⚠️ Lưu ý:</strong> Sau khi tạo phiếu xuất, hàng sẽ được trừ khỏi kho <strong><?= htmlspecialchars($sourceWarehouse['name'] ?? $request['source_warehouse_id']) ?></strong> và phiếu yêu cầu sẽ chuyển sang trạng thái "Hoàn thành".
  </div>

  <!-- Thông tin xuất nhập -->
  <div class="info-section">
    <h3>🏪 Thông tin xuất nhập kho</h3>
    <div class="info-row">
      <strong>Kho xuất hàng:</strong>
      <span><?= htmlspecialchars($sourceWarehouse['name'] ?? $request['source_warehouse_id']) ?> (<?= htmlspecialchars($request['source_warehouse_id']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Kho nhận hàng:</strong>
      <span><?= htmlspecialchars($destWarehouse['name'] ?? $request['warehouse_id']) ?> (<?= htmlspecialchars($request['warehouse_id']) ?>)</span>
    </div>
    <div class="info-row">
      <strong>Loại phiếu:</strong>
      <span>Chuyển kho (Transfer)</span>
    </div>
    <div class="info-row">
      <strong>Phiếu yêu cầu gốc:</strong>
      <span><?= htmlspecialchars($request_id) ?></span>
    </div>
  </div>

  <form method="POST" action="<?= htmlspecialchars($action) ?>">
    <input type="hidden" name="request_id" value="<?= htmlspecialchars($request_id) ?>">
    <!-- Danh sách sản phẩm -->
    <div class="products-section">
      <h3>📦 Danh sách sản phẩm xuất kho</h3>
      <table class="products-table">
        <thead>
          <tr>
            <th>STT</th>
            <th>Sản phẩm</th>
            <th>Số lượng</th>
            <th>Đơn vị</th>
            <th>Quy đổi</th>
            <th>Tồn kho hiện tại</th>
            <th>Chọn kho xuất</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $stt = 1;
          $details = $request['details'] ?? [];
          foreach ($details as $item):
            $productId = $item['product_id'];
            $productName = $item['product_name'] ?? 'N/A';
            $sku = $item['sku'] ?? $productId;
            $qty = (int)($item['quantity'] ?? 0);
            $unit = $item['unit'] ?? 'cái';
            $factor = (int)($item['conversion_factor'] ?? 1);
            $baseUnit = $item['base_unit'] ?? 'cái';
            $requiredQty = $qty * $factor; // Số lượng cần xuất (đã quy đổi)
            
            // Lấy tồn kho hiện tại ở kho nguồn
            $currentStock = $cInventory->getTotalStockByProduct($request['source_warehouse_id'], $productId);
            
            $factorDisplay = '';
            if ($factor != 1) {
              $factorDisplay = "x" . number_format($factor, 0);
            }
          ?>
          <tr data-product-id="<?= htmlspecialchars($productId) ?>" data-required-qty="<?= $requiredQty ?>">
            <td><?= $stt++ ?></td>
            <td>
              <strong><?= htmlspecialchars($productName) ?></strong><br>
              <small style="color:#6c757d;">SKU: <?= htmlspecialchars($sku) ?></small>
            </td>
            <td><strong style="color:#ff9800;"><?= number_format($qty) ?></strong></td>
            <td><?= htmlspecialchars($unit) ?></td>
            <td>
              <?php if (!empty($factorDisplay)): ?>
                <span style="color:#007bff;font-weight:600;"><?= $factorDisplay ?></span>
              <?php else: ?>
                <span style="color:#999;">-</span>
              <?php endif; ?>
            </td>
            <td>
              <strong style="color:<?= $currentStock >= $requiredQty ? '#28a745' : '#dc3545' ?>;">
                <?= number_format($currentStock) ?> <?= htmlspecialchars($baseUnit) ?>
              </strong>
            </td>
            <td style="text-align:center;">
              <button type="button" class="btn-choose-warehouse" 
                      data-product-id="<?= htmlspecialchars($productId) ?>"
                      data-product-name="<?= htmlspecialchars($productName) ?>"
                      data-required-qty="<?= $requiredQty ?>"
                      data-base-unit="<?= htmlspecialchars($baseUnit) ?>"
                      data-destination-warehouse="<?= htmlspecialchars($request['warehouse_id']) ?>"
                      style="background:#007bff;color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;">
                <i class="fa-solid fa-warehouse"></i> Chọn kho
              </button>
              <div class="warehouse-selection-info" style="margin-top:8px;font-size:12px;color:#666;">
                <span class="selected-count">Chưa chọn</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Ghi chú -->
    <div class="note-section">
      <label for="note">📝 Ghi chú (tùy chọn):</label>
      <textarea name="note" id="note" placeholder="Nhập ghi chú cho phiếu xuất kho...">Phiếu xuất kho cho yêu cầu <?= htmlspecialchars($request_id) ?></textarea>
    </div>

    <!-- Nút hành động -->
    <div class="actions">
      <a href="../../index.php?page=goodsReceiptRequest" class="btn btn-cancel">
        <i class="fa-solid fa-xmark"></i> Hủy
      </a>
      <button type="submit" class="btn btn-submit" onclick="return confirm('Xác nhận tạo phiếu xuất kho và trừ hàng khỏi kho?');">
        <i class="fa-solid fa-check"></i> Tạo phiếu xuất
      </button>
    </div>
  </form>
</div>


<!-- Modal and script will be added -->
<?php include 'warehouse_selection_modal.php'; ?>
