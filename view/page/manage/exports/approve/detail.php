<?php
/**
 * Chi tiết phiếu xuất chờ duyệt (cho chi nhánh)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../model/connect.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

$cWarehouse = new CWarehouse();
$cProduct = new CProduct();

// Lấy thông tin người dùng
$user_id = $_SESSION['login']['user_id'] ?? '';
$warehouse_id = $_SESSION['warehouse_id'] ?? '';

$export_id = $_GET['id'] ?? '';

if (empty($export_id)) {
    $_SESSION['flash_error'] = 'Không tìm thấy mã phiếu xuất!';
    header("Location: ../../../index.php?page=exports/approve");
    exit;
}

// Kết nối MongoDB
$p = new clsKetNoi();
$con = $p->moKetNoi();

if (!$con) {
    $_SESSION['flash_error'] = 'Lỗi kết nối database!';
    header("Location: ../../../index.php?page=exports/approve");
    exit;
}

$transCol = $con->selectCollection('transactions');
$export = $transCol->findOne(['transaction_id' => $export_id]);

if (!$export) {
    $_SESSION['flash_error'] = 'Không tìm thấy phiếu xuất!';
    $p->dongKetNoi($con);
    header("Location: ../../../index.php?page=exports/approve");
    exit;
}

// Kiểm tra quyền: phải là phiếu xuất đến kho của user
$destWarehouse = $export['destination_warehouse_id'] ?? '';
if ($warehouse_id !== $destWarehouse) {
    $_SESSION['flash_error'] = 'Bạn không có quyền xem phiếu xuất này!';
    $p->dongKetNoi($con);
    header("Location: ../../../index.php?page=exports/approve");
    exit;
}

$sourceWarehouse = $cWarehouse->getWarehouseById($export['warehouse_id'] ?? '');
$destWarehouse = $cWarehouse->getWarehouseById($export['destination_warehouse_id'] ?? '');

$status = (int)($export['status'] ?? 0);
$inventoryDeducted = $export['inventory_deducted'] ?? false;

$p->dongKetNoi($con);
?>

<style>
  .detail-container {max-width:1200px;margin:30px auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);}
  .detail-header {border-bottom:3px solid #007bff;padding-bottom:20px;margin-bottom:30px;display:flex;justify-content:space-between;align-items:center;}
  .detail-header h2 {color:#007bff;margin:0;font-size:28px;}
  .status-badge {padding:8px 16px;border-radius:20px;font-weight:600;font-size:14px;}
  .status-badge.pending {background:#ffc107;color:#000;}
  .status-badge.confirmed {background:#28a745;color:#fff;}
  .status-badge.delivered {background:#17a2b8;color:#fff;}
  
  .info-section {background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:25px;border-left:4px solid #007bff;}
  .info-section h3 {color:#007bff;font-size:18px;margin-bottom:15px;}
  .info-grid {display:grid;grid-template-columns:repeat(2, 1fr);gap:15px;}
  .info-item {display:flex;flex-direction:column;gap:5px;}
  .info-item label {font-weight:600;color:#495057;font-size:14px;}
  .info-item span {color:#212529;font-size:15px;}
  
  .products-table {width:100%;border-collapse:collapse;margin-top:20px;box-shadow:0 2px 8px rgba(0,0,0,0.05);}
  .products-table th,.products-table td {padding:12px;border:1px solid #dee2e6;text-align:left;font-size:14px;}
  .products-table th {background:#007bff;color:#fff;font-weight:600;text-align:center;}
  .products-table tbody tr:hover {background:#e7f3ff;}
  
  .actions {margin-top:30px;display:flex;gap:15px;justify-content:flex-end;}
  .btn {padding:12px 30px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:0.3s;}
  .btn-approve {background:#28a745;color:#fff;}
  .btn-approve:hover {background:#218838;}
  .btn-reject {background:#dc3545;color:#fff;}
  .btn-reject:hover {background:#c82333;}
  .btn-back {background:#6c757d;color:#fff;}
  .btn-back:hover {background:#5a6268;}
  
  .alert {padding:15px;border-radius:8px;margin-bottom:20px;}
  .alert-success {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
  .alert-danger {background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}
  .alert-warning {background:#fff3cd;color:#856404;border:1px solid #ffeaa7;}
</style>

<div class="detail-container">
  <div class="detail-header">
    <h2><i class="fa-solid fa-file-invoice"></i> Chi Tiết Phiếu Xuất <?= htmlspecialchars($export_id) ?></h2>
    <?php
    switch ($status) {
      case 0:
        echo '<span class="status-badge pending">Chờ xác nhận</span>';
        break;
      case 1:
        echo '<span class="status-badge confirmed">Đã xuất kho</span>';
        break;
      case 2:
        echo '<span class="status-badge delivered">Đã giao hàng</span>';
        break;
      case 3:
        echo '<span class="status-badge" style="background:#dc3545;color:#fff;">Từ chối</span>';
        break;
    }
    ?>
  </div>

  <?php
  if (isset($_SESSION['flash_success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['flash_success']) . '</div>';
    unset($_SESSION['flash_success']);
  }
  if (isset($_SESSION['flash_error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['flash_error']) . '</div>';
    unset($_SESSION['flash_error']);
  }
  ?>

  <div class="info-section">
    <h3><i class="fa-solid fa-info-circle"></i> Thông Tin Phiếu Xuất</h3>
    <div class="info-grid">
      <div class="info-item">
        <label>Mã phiếu xuất:</label>
        <span><strong><?= htmlspecialchars($export_id) ?></strong></span>
      </div>
      <div class="info-item">
        <label>Ngày tạo:</label>
        <span><?= isset($export['created_at']) && $export['created_at'] instanceof MongoDB\BSON\UTCDateTime ? 
          date('d/m/Y H:i', $export['created_at']->toDateTime()->getTimestamp()) : 'N/A' ?></span>
      </div>
      <div class="info-item">
        <label>Người tạo:</label>
        <span><?= htmlspecialchars($export['created_by'] ?? 'N/A') ?></span>
      </div>
      <div class="info-item">
        <label>Kho xuất:</label>
        <span><strong><?= htmlspecialchars($sourceWarehouse['warehouse_name'] ?? $export['warehouse_id']) ?></strong></span>
      </div>
      <div class="info-item">
        <label>Kho nhận:</label>
        <span><strong><?= htmlspecialchars($destWarehouse['warehouse_name'] ?? $export['destination_warehouse_id']) ?></strong></span>
      </div>
      <div class="info-item">
        <label>Trạng thái trừ kho:</label>
        <span><?= $inventoryDeducted ? 
          '<span style="color:#28a745;font-weight:600;">✅ Đã trừ inventory</span>' : 
          '<span style="color:#dc3545;font-weight:600;">❌ Chưa trừ inventory</span>' ?></span>
      </div>
    </div>
    
    <?php if (!empty($export['note'])): ?>
      <div class="info-item" style="margin-top:15px;">
        <label>Ghi chú:</label>
        <span><?= htmlspecialchars($export['note']) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <div class="info-section">
    <h3><i class="fa-solid fa-box"></i> Danh Sách Sản Phẩm</h3>
    <table class="products-table">
      <thead>
        <tr>
          <th>STT</th>
          <th>Mã sản phẩm</th>
          <th>Tên sản phẩm</th>
          <th>Số lượng</th>
          <th>Đơn vị</th>
          <th>Hệ số quy đổi</th>
          <th>Tổng số lượng</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $details = $export['details'] ?? [];
        $stt = 1;
        $totalQty = 0;
        
        foreach ($details as $item) {
          $itemArray = json_decode(json_encode($item), true);
          $productId = $itemArray['product_id'] ?? '';
          $productName = $itemArray['product_name'] ?? '';
          $qty = $itemArray['quantity'] ?? 0;
          $unit = $itemArray['unit'] ?? '';
          $factor = $itemArray['conversion_factor'] ?? 1;
          $total = $qty * $factor;
          $totalQty += $total;
          
          // Lấy tên sản phẩm nếu chưa có
          if (empty($productName) && !empty($productId)) {
            $product = $cProduct->getProductById($productId);
            $productName = $product['product_name'] ?? $productId;
          }
          
          echo "<tr>";
          echo "<td style='text-align:center;'>$stt</td>";
          echo "<td>" . htmlspecialchars($productId) . "</td>";
          echo "<td>" . htmlspecialchars($productName) . "</td>";
          echo "<td style='text-align:center;'><strong>$qty</strong></td>";
          echo "<td style='text-align:center;'>" . htmlspecialchars($unit) . "</td>";
          echo "<td style='text-align:center;'>$factor</td>";
          echo "<td style='text-align:center;'><strong style='color:#007bff;'>$total</strong></td>";
          echo "</tr>";
          
          $stt++;
        }
        ?>
        <tr style="background:#f8f9fa;font-weight:600;">
          <td colspan="6" style="text-align:right;">Tổng cộng:</td>
          <td style="text-align:center;color:#007bff;font-size:16px;"><?= $totalQty ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="actions">
    <a href="../../../index.php?page=exports/approve" class="btn btn-back">
      <i class="fa-solid fa-arrow-left"></i> Quay lại
    </a>
    
    <?php if ($status === 1 && $inventoryDeducted): ?>
      <button onclick="approveExport('<?= $export_id ?>')" class="btn btn-approve">
        <i class="fa-solid fa-check-circle"></i> Duyệt Nhận Hàng
      </button>
      <button onclick="rejectExport('<?= $export_id ?>')" class="btn btn-reject">
        <i class="fa-solid fa-times-circle"></i> Từ Chối
      </button>
    <?php elseif ($status === 2): ?>
      <div class="alert alert-success" style="margin:0;">
        ✅ Phiếu xuất này đã được duyệt và nhận hàng vào kho.
      </div>
    <?php elseif ($status === 0): ?>
      <div class="alert alert-warning" style="margin:0;">
        ⏳ Phiếu xuất đang chờ kho tổng xác nhận xuất hàng.
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function approveExport(exportId) {
  if (!confirm('Xác nhận DUYỆT NHẬN HÀNG từ phiếu xuất ' + exportId + '?\n\nHàng sẽ được THÊM VÀO KHO của bạn!')) {
    return;
  }
  
  const btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
  
  fetch('../../exports/process.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=approve&export_id=' + encodeURIComponent(exportId)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('✅ ' + data.message);
      window.location.href = '../../../index.php?page=exports/approve';
    } else {
      alert('❌ ' + data.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Duyệt Nhận Hàng';
    }
  })
  .catch(error => {
    alert('Lỗi kết nối: ' + error);
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-check-circle"></i> Duyệt Nhận Hàng';
  });
}

function rejectExport(exportId) {
  const reason = prompt('Nhập lý do TỪ CHỐI phiếu xuất ' + exportId + ':');
  if (!reason || reason.trim() === '') {
    alert('Vui lòng nhập lý do từ chối!');
    return;
  }
  
  const btn = event.target.closest('button');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
  
  fetch('../../exports/process.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=reject&export_id=' + encodeURIComponent(exportId) + '&reason=' + encodeURIComponent(reason)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('✅ ' + data.message);
      window.location.href = '../../../index.php?page=exports/approve';
    } else {
      alert('❌ ' + data.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-times-circle"></i> Từ Chối';
    }
  })
  .catch(error => {
    alert('Lỗi kết nối: ' + error);
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-times-circle"></i> Từ Chối';
  });
}
</script>
