<?php

include_once(__DIR__ . "/../../../../../controller/cReceipt.php");

$cReceipt = new CReceipt();

// Lấy danh sách phiếu nhập
$user_id = $_SESSION['login']['user_id'] ?? 'U001';
$role = $_SESSION['login']['role'] ?? 'staff';
$role_name = $_SESSION['login']['role_name'] ?? '';
$role_id = $_SESSION['login']['role_id'] ?? '';
$warehouse_id = $_SESSION['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? null);

$allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
$allowedRoleIds = [2, 4];
$isManager = in_array($role, $allowedRoles) || in_array($role_name, $allowedRoles) || in_array($role_id, $allowedRoleIds);

// Lấy phiếu theo warehouse_id của người đăng nhập
if ($warehouse_id) {
  $receipts = $cReceipt->getReceiptsByWarehouse($warehouse_id);
} else {
  // Nếu không có warehouse_id, lấy theo user_id như cũ
  $receipts = $cReceipt->getReceiptsByUserWithUserInfo($user_id);
}
?>

<style>
  .receipt-list-container {max-width:1200px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 15px rgba(0,0,0,0.08);}
  .receipt-list-container h2 {text-align:center;margin-bottom:25px;color:#333;}
  .receipt-list-container table {width:100%;border-collapse:collapse;margin-top:20px;}
  .receipt-list-container th,.receipt-list-container td {padding:10px 12px;border:1px solid #e1e4e8;text-align:center;font-size:14px;}
  .receipt-list-container th {background:#f9fafb;}
  .receipt-list-container tr:hover {background:#f1f7ff;}
  .receipt-list-container .btn {border:none;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block;}
  .receipt-list-container .btn-view {background:#17a2b8;color:#fff;}
  .receipt-list-container .btn-approve {background:#28a745;color:#fff;}
  .receipt-list-container .btn-reject {background:#dc3545;color:#fff;}
  .receipt-list-container .btn-locate {background:#ffc107;color:#000;}
  .receipt-list-container .btn:hover {opacity:0.9;}
  .receipt-list-container .status {font-weight:600;padding:6px 10px;border-radius:8px;display:inline-block;}
  .receipt-list-container .pending {background:#fff3cd;color:#856404;}
  .receipt-list-container .approved {background:#d4edda;color:#155724;}
  .receipt-list-container .rejected {background:#f8d7da;color:#721c24;}
  .receipt-list-container .located {background:#cce5ff;color:#004085;}
  .receipt-list-container .top-actions {margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
  .filters {display:flex;gap:10px;align-items:center;}
  .filters select {padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px;}
</style>

<div class="receipt-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-file-lines"></i> Danh sách phiếu nhập hàng</h2>

    <div class="filters">
      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="pending">Chờ duyệt</option>
        <option value="approved">Đã duyệt</option>
        <option value="rejected">Từ chối</option>
        <option value="located">Hoàn tất xếp hàng</option>
      </select>

      <select id="filter-type">
        <option value="">Lọc theo loại phiếu</option>
        <option value="purchase">Phiếu nhập hàng</option>
        <option value="transfer">Phiếu chuyển kho</option>
      </select>
    </div>
  </div>

  <?php
  if (isset($_SESSION['flash_receipt'])) {
    echo '<div style="padding:10px;background:#e6ffed;border:1px solid #b7f0c6;margin-bottom:12px;color:#256029;">'.htmlspecialchars($_SESSION['flash_receipt']).'</div>';
    unset($_SESSION['flash_receipt']);
  }
  if (isset($_SESSION['flash_receipt_error'])) {
    echo '<div style="padding:10px;background:#ffecec;border:1px solid #f5c2c2;margin-bottom:12px;color:#8a1f1f;">'.htmlspecialchars($_SESSION['flash_receipt_error']).'</div>';
    unset($_SESSION['flash_receipt_error']);
  }
  ?>

  <table id="receipt-table">
    <thead>
      <tr>
        <th>STT</th>
        <th>Mã phiếu</th>
        <th>Ngày tạo</th>
        <th>Người tạo</th>
        <th>Kho</th>
        <th>Loại phiếu</th>
        <th>Trạng thái</th>
        <th>Tổng tiền</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      if ($receipts && iterator_count($receipts) > 0) {
        $stt = 1; // Khởi tạo STT
        foreach ($receipts as $r) {
          $status = (int)($r['status'] ?? 0);
          switch ($status) {
            case 0: $class='pending'; $text='Chờ duyệt'; break;
            case 1: $class='approved'; $text='Đã duyệt'; break;
            case 2: $class='rejected'; $text='Từ chối'; break;
            case 3: $class='located'; $text='Đã hoàn tất'; break;
            default: $class='pending'; $text='Không xác định';
          }

          $total = number_format($r['total_amount'] ?? 0, 0, ',', '.');

          $created_date = 'N/A';
          if (isset($r['created_at'])) {
              if ($r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                  $created_date = date('d/m/Y H:i', $r['created_at']->toDateTime()->getTimestamp());
              } else {
                  $created_date = date('d/m/Y H:i', strtotime($r['created_at']));
              }
          }

          echo "
            <tr data-status='$class' data-type='{$r['type']}'>
              <td>$stt</td>
              <td>{$r['transaction_id']}</td>
              <td>$created_date</td>
              <td>".($r['creator_name'] ?? $r['created_by'])."</td>
              <td>{$r['warehouse_id']}</td>
              <td>{$r['type']}</td>
              <td><span class='status $class'>$text</span></td>
              <td>{$total} đ</td>
              <td>
                <a href='index.php?page=receipts/approve/detail&id={$r['transaction_id']}' class='btn btn-view' title='Xem chi tiết'>
                  <i class='fa-solid fa-eye'></i> Xem
                </a>

          ";

          // Nếu là quản lý và phiếu đang chờ duyệt
          if ($status === 0 && $isManager) {
            echo "
              <a href='receipts/approve/process.php?action=approve&id={$r['transaction_id']}' class='btn btn-approve' onclick='return confirm(\"Bạn có chắc chắn muốn duyệt phiếu này?\");'><i class=\"fa-solid fa-check\"></i></a>
              <a href='receipts/approve/process.php?action=reject&id={$r['transaction_id']}' class='btn btn-reject' onclick='return confirm(\"Bạn có chắc chắn muốn từ chối phiếu này?\");'><i class=\"fa-solid fa-xmark\"></i></a>
            ";
          }

          // Nếu là nhân viên và phiếu đã duyệt (được phép xếp hàng)
          if (!$isManager && $status === 1) {
            echo "
                <a href='index.php?page=receipts/locate&id={$r['transaction_id']}' class='btn btn-locate' title='Xếp hàng'>
                  <i class=\"fa-solid fa-dolly\"></i> Xếp hàng
                </a>
            ";
          }

          // Nút 'Vị trí' cho duyệt/hoàn tất (quản lý hoặc nhân viên đều xem lại/sửa được)
          if (($status === 1 && $isManager) || $status === 3) {
            echo "
                <a href='index.php?page=receipts/locate&id={$r['transaction_id']}' class='btn btn-locate' title='Xem/Sửa vị trí đã xếp'>
                  <i class=\"fa-solid fa-location-dot\"></i> Vị trí
                </a>
            ";
          }

          echo "</td></tr>";
          $stt++; // Tăng STT sau mỗi dòng
        }
      } else {
        echo "<tr><td colspan='9'>Không có phiếu nhập nào.</td></tr>";
      } ?>
    </tbody>
  </table>
</div>

<script>
  const statusFilter = document.getElementById('filter-status');
  const typeFilter = document.getElementById('filter-type');
  const rows = document.querySelectorAll('#receipt-table tbody tr');

  function applyFilters() {
    const selectedStatus = statusFilter.value;
    const selectedType = typeFilter.value.toLowerCase();

    rows.forEach(row => {
      const rowStatus = row.getAttribute('data-status');
      const rowType = row.getAttribute('data-type').toLowerCase();

      const matchStatus = !selectedStatus || rowStatus === selectedStatus;
      const matchType = !selectedType || rowType.includes(selectedType);

      row.style.display = (matchStatus && matchType) ? '' : 'none';
    });
  }

  statusFilter.addEventListener('change', applyFilters);
  typeFilter.addEventListener('change', applyFilters);
</script>
