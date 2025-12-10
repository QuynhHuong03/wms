<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

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
  /* Modern Admin Dashboard Design System */
  :root {
    --bg: #f8fafc;
    --card: #ffffff;
    --muted: #64748b;
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --success: #10b981;
    --success-dark: #059669;
    --warning: #f59e0b;
    --warning-light: #fef3c7;
    --danger: #ef4444;
    --border: #e2e8f0;
    --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
  }
  
  .export-container {
    max-width: 1400px;
    margin: 0 auto 24px;
    background: var(--card);
    padding: 28px;
    border-radius: 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
  }
  
  .export-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--border);
  }
  
  .export-header h2 {
    margin: 0;
    font-weight: 700;
    font-size: 28px;
    color: #0f172a;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  
  .export-header .subtitle {
    color: var(--muted);
    font-size: 14px;
    margin-top: 8px;
    font-weight: 500;
  }
  
  .warning-box {
    background: #fef3c7;
    border: 1px solid #fbbf24;
    border-left: 4px solid #f59e0b;
    padding: 16px;
    border-radius: 10px;
    margin-bottom: 24px;
    color: #78350f;
    font-size: 14px;
    line-height: 1.6;
    display: flex;
    align-items: start;
    gap: 12px;
  }
  
  .warning-box strong {
    color: #92400e;
    font-weight: 700;
  }
  
  .info-section {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 2px solid #bae6fd;
    box-shadow: var(--shadow);
  }
  
  .info-section h3 {
    color: #0369a1;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .info-row {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 14px;
    padding: 8px 0;
  }
  
  .info-row strong {
    color: #0c4a6e;
    font-weight: 600;
  }
  
  .info-row span {
    color: #334155;
    font-weight: 500;
  }
  
  .products-section {
    margin-top: 28px;
  }
  
  .products-section h3 {
    color: #0f172a;
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 3px solid var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .products-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    box-shadow: var(--shadow);
    border-radius: 10px;
    overflow: hidden;
  }
  
  .products-table th,
  .products-table td {
    padding: 14px 12px;
    border: 1px solid var(--border);
    text-align: left;
    font-size: 14px;
  }
  
  .products-table th {
    background: #f8fafc;
    color: #0f172a;
    font-weight: 600;
    text-align: center;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
  }
  
  .products-table tbody tr {
    transition: all 0.2s ease;
  }
  
  .products-table tbody tr:hover {
    background: #f0f9ff;
    transform: scale(1.001);
  }
  
  .products-table tbody tr:nth-child(even) {
    background: #f8fafc;
  }
  
  .products-table tbody tr:nth-child(even):hover {
    background: #f0f9ff;
  }
  
  .products-table td:nth-child(1) {
    text-align: center;
    width: 50px;
    font-weight: 600;
    color: var(--muted);
  }
  
  .products-table td:nth-child(3),
  .products-table td:nth-child(4),
  .products-table td:nth-child(5),
  .products-table td:nth-child(6) {
    text-align: center;
  }
  
  .note-section {
    margin-top: 28px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px solid var(--border);
  }
  
  .note-section label {
    display: block;
    font-weight: 600;
    margin-bottom: 12px;
    color: #0f172a;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .note-section textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    min-height: 100px;
    font-family: inherit;
    transition: all 0.2s ease;
  }
  
  .note-section textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  .actions {
    margin-top: 28px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 2px solid var(--border);
  }
  
  .btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: var(--shadow);
  }
  
  .btn-submit {
    background: var(--success);
    color: #fff;
  }
  
  .btn-submit:hover {
    background: var(--success-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
  }
  
  .btn-cancel {
    background: #6c757d;
    color: #fff;
  }
  
  .btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
  }
  
  .btn-choose-warehouse {
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  
  .btn-choose-warehouse:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
  }
  
  .warehouse-selection-info {
    margin-top: 8px;
    font-size: 12px;
    color: var(--muted);
    font-weight: 500;
  }
  
  /* Modal styles */
  .warehouse-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(11, 18, 32, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
  }
  
  .warehouse-modal.active {
    display: flex;
  }
  
  .warehouse-modal-content {
    background: #fff;
    border-radius: 16px;
    max-width: 900px;
    width: 90%;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
  }
  
  @keyframes modalSlideIn {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .warehouse-modal-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: #fff;
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .warehouse-modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  
  .warehouse-modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: 0.2s;
  }
  
  .warehouse-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
  }
  
  .warehouse-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
  }
  
  .warehouse-modal-footer {
    padding: 20px 24px;
    border-top: 2px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f8fafc;
  }
  
  .warehouse-item {
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
    background: #fff;
  }
  
  .warehouse-item:hover {
    border-color: var(--primary);
    background: #f0f9ff;
    transform: translateX(4px);
    box-shadow: var(--shadow);
  }
  
  .warehouse-info {
    flex: 1;
  }
  
  .warehouse-info .name {
    font-weight: 600;
    font-size: 15px;
    color: #0f172a;
    margin-bottom: 6px;
  }
  
  .warehouse-info .stock {
    font-size: 14px;
    color: var(--muted);
  }
  
  .warehouse-info .stock strong {
    color: var(--success);
    font-size: 16px;
    font-weight: 700;
  }
  
  .warehouse-qty-input {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  
  .warehouse-qty-input input {
    width: 100px;
    padding: 10px;
    border: 2px solid var(--border);
    border-radius: 8px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
  }
  
  .warehouse-qty-input input:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  .summary-box {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 2px solid var(--primary);
    padding: 16px;
    border-radius: 10px;
    margin-top: 20px;
  }
  
  .summary-box .summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
    color: #334155;
  }
  
  .summary-box .summary-item strong {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
  }
  
  .summary-box .total {
    border-top: 2px solid var(--primary);
    padding-top: 12px;
    margin-top: 12px;
    font-weight: 700;
    font-size: 16px;
    color: var(--primary);
  }
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
    <h2> Tạo phiếu xuất kho</h2>
    <div class="subtitle">Từ phiếu yêu cầu: <?= htmlspecialchars($request_id) ?></div>
  </div>

  <div class="warning-box">
    <strong>⚠️ Lưu ý:</strong> Sau khi tạo phiếu xuất, hàng sẽ được trừ khỏi kho <strong><?= htmlspecialchars($sourceWarehouse['name'] ?? $request['source_warehouse_id']) ?></strong> và phiếu yêu cầu sẽ chuyển sang trạng thái "Hoàn thành".
  </div>

  <!-- Thông tin xuất nhập -->
  <div class="info-section">
    <h3> Thông tin xuất nhập kho</h3>
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

  <form method="POST" action="<?= htmlspecialchars($action) ?>" id="exportForm">
    <input type="hidden" name="request_id" value="<?= htmlspecialchars($request_id) ?>">
    <!-- Danh sách sản phẩm -->
    <div class="products-section">
      <h3> Danh sách sản phẩm xuất kho</h3>
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
      <label for="note"> Ghi chú (tùy chọn):</label>
      <textarea name="note" id="note" placeholder="Nhập ghi chú cho phiếu xuất kho...">Phiếu xuất kho cho yêu cầu <?= htmlspecialchars($request_id) ?></textarea>
    </div>

    <!-- Nút hành động -->
    <div class="actions">
      <a href="../../index.php?page=goodsReceiptRequest" class="btn btn-cancel">
        <i class="fa-solid fa-xmark"></i> Hủy
      </a>
      <button type="button" class="btn btn-submit" onclick="showConfirmModal()">
        <i class="fa-solid fa-check"></i> Tạo phiếu xuất
      </button>
    </div>
  </form>
</div>

<!-- Confirm Modal -->
<div class="warehouse-modal" id="confirmModal" style="backdrop-filter:blur(4px);">
  <div class="warehouse-modal-content" style="max-width:500px;">
    <div class="warehouse-modal-header" style="background:linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);">
      <h3><i class="fa-solid fa-circle-check"></i> Xác nhận tạo phiếu xuất</h3>
      <button class="warehouse-modal-close" onclick="closeConfirmModal()">×</button>
    </div>
    <div class="warehouse-modal-body">
      <div style="padding:20px 0;text-align:center;">
        <i class="fa-solid fa-file-export" style="font-size:64px;color:var(--success);margin-bottom:20px;"></i>
        <p style="font-size:16px;color:#334155;line-height:1.6;margin:0;">
          Bạn có chắc chắn muốn tạo phiếu xuất kho?
        </p>
        <p style="font-size:14px;color:var(--muted);margin-top:12px;line-height:1.5;">
          Hành động này sẽ trừ hàng khỏi kho và không thể hoàn tác.
        </p>
      </div>
    </div>
    <div class="warehouse-modal-footer" style="background:#f8fafc;">
      <button type="button" class="btn btn-cancel" onclick="closeConfirmModal()">
        <i class="fa-solid fa-xmark"></i> Hủy
      </button>
      <button type="button" class="btn btn-submit" onclick="submitExportForm()">
        <i class="fa-solid fa-check"></i> Xác nhận tạo phiếu
      </button>
    </div>
  </div>
</div>

<script>
function showConfirmModal() {
  console.log('showConfirmModal called');
  console.log('warehouseSelections:', window.warehouseSelections || 'not defined');
  document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
  console.log('closeConfirmModal called');
  document.getElementById('confirmModal').classList.remove('active');
}

function submitExportForm() {
  console.log('submitExportForm called');
  console.log('warehouseSelections:', window.warehouseSelections || {});
  
  // Get form
  const form = document.getElementById('exportForm');
  if (!form) {
    console.error('Export form not found!');
    alert('Lỗi: Không tìm thấy form!');
    return;
  }
  
  console.log('Form found:', form);
  
  // Add warehouse selections as hidden input before submit
  const existingInput = form.querySelector('input[name="warehouse_selections"]');
  if (existingInput) {
    existingInput.remove();
    console.log('Removed existing warehouse_selections input');
  }
  
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'warehouse_selections';
  input.value = JSON.stringify(window.warehouseSelections || {});
  form.appendChild(input);
  
  console.log('Added warehouse_selections input with value:', input.value);
  
  // Submit form
  console.log('Submitting form...');
  form.submit();
}

// Close modal on backdrop click
document.addEventListener('DOMContentLoaded', function() {
  const confirmModal = document.getElementById('confirmModal');
  if (confirmModal) {
    confirmModal.addEventListener('click', function(e) {
      if (e.target === this) {
        closeConfirmModal();
      }
    });
  }
});
</script>

<!-- Modal and script will be added -->
<?php include 'warehouse_selection_modal.php'; ?>
