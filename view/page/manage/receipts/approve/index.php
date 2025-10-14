<?php

include_once(__DIR__ . "/../../../../../controller/cReceipt.php"); // controller quản lý phiếu nhập

$cReceipt = new CReceipt();

// Lấy danh sách phiếu nhập (nếu là nhân viên, chỉ lấy của nhân viên đó)
$user_id = $_SESSION['login']['user_id'] ?? 'U001';
$role = $_SESSION['login']['role'] ?? 'staff';
$role_name = $_SESSION['login']['role_name'] ?? '';
$role_id = $_SESSION['login']['role_id'] ?? '';

$allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
$allowedRoleIds = [2, 4];
$isManager = in_array($role, $allowedRoles) || in_array($role_name, $allowedRoles) || in_array($role_id, $allowedRoleIds);

if ($isManager) {
  $receipts = $cReceipt->getAllReceipts();
} else {
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
  .receipt-list-container .btn:hover {opacity:0.9;}
  .receipt-list-container .status {font-weight:600;padding:6px 10px;border-radius:8px;display:inline-block;}
  .receipt-list-container .pending {background:#fff3cd;color:#856404;}
  .receipt-list-container .approved {background:#d4edda;color:#155724;}
  .receipt-list-container .rejected {background:#f8d7da;color:#721c24;}
  .receipt-list-container .top-actions {margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
  .receipt-list-container a.btn-create {background:#007bff;color:#fff;text-decoration:none;padding:8px 14px;border-radius:8px;}
  .receipt-list-container a.btn-create:hover {background:#0056b3;}
  .filters {display:flex;gap:10px;align-items:center;}
  .filters select {padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px;}
</style>

<div class="receipt-list-container">
    <div class="top-actions">
      <h2><i class="fa-solid fa-file-lines"></i> Danh sách phiếu nhập hàng</h2>

      <div class="filters">
        <select id="filter-status">
          <option value=""> Lọc theo trạng thái </option>
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt</option>
          <option value="rejected">Từ chối</option>
        </select>

        <select id="filter-type">
          <option value=""> Lọc theo loại phiếu </option>
          <option value="purchase">Phiếu nhập hàng</option>
          <option value="transfer">Phiếu chuyển kho</option>
        </select>

        <!-- <a href="index.php?page=receipts" class="btn-create"><i class="fa-solid fa-plus"></i> Tạo phiếu mới</a> -->
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
          foreach ($receipts as $r) {
            $status = (int)($r['status'] ?? 0);
            $class = $status === 1 ? 'approved' : ($status === 2 ? 'rejected' : 'pending');
            $text = $status === 1 ? 'Đã duyệt' : ($status === 2 ? 'Từ chối' : 'Chờ duyệt');
            $total = number_format($r['total_amount'] ?? 0, 0, ',', '.');

            $created_date = 'N/A';
            if (isset($r['created_at'])) {
                if ($r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                    $created_date = date('d/m/Y H:i', $r['created_at']->toDateTime()->getTimestamp());
                } else {
                    $created_date = date('d/m/Y H:i', strtotime($r['created_at']));
                }
            }

            $type = strtolower($r['type'] ?? '');
            $typeLabel = $type === 'purchase' ? 'Nhập hàng' : ($type === 'transfer' ? 'Chuyển kho' : ucfirst($type));

            echo "
              <tr data-status='$class' data-type='{$r['type']}'>
                <td>{$r['receipt_id']}</td>
                <td>$created_date</td>
                <td>".($r['creator_name'] ?? $r['created_by'])."</td>
                <td>{$r['warehouse_id']}</td>
                <td>{$r['type']}</td>
                <td><span class='status $class'>$text</span></td>
                <td>{$total} đ</td>
                <td>
                  <a href='index.php?page=receipts/approve/detail&id={$r['receipt_id']}' class='btn btn-view'><i class='fa-solid fa-eye'></i></a>
            ";
            if ($status === 0 && $isManager) {
                echo "
                  <a href='receipts/approve/process.php?action=approve&id={$r['receipt_id']}' class='btn btn-approve' onclick='return confirm(\"Bạn có chắc chắn muốn duyệt phiếu này?\");'><i class='fa-solid fa-check'></i></a>
                  <a href='receipts/approve/process.php?action=reject&id={$r['receipt_id']}' class='btn btn-reject' onclick='return confirm(\"Bạn có chắc chắn muốn từ chối phiếu này?\");'><i class='fa-solid fa-xmark'></i></a>
                ";
              }
            echo "</td></tr>";
          }
        } else {
          echo "<tr><td colspan='8'>Không có phiếu nhập nào.</td></tr>";
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
