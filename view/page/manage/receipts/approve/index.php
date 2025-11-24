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

      <input type="date" id="filter-date-from" placeholder="Từ ngày" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px;">
      <input type="date" id="filter-date-to" placeholder="Đến ngày" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px;">
      
      <button id="btn-reset-filters" class="btn" style="background:#6c757d;color:#fff;padding:6px 12px;" title="Xóa bộ lọc">
        <i class="fa-solid fa-rotate-right"></i> Reset
      </button>
    </div>
  </div>

  <?php
  // --- Prepare stats for receipts ---
  $receiptsArr = $receipts;
  if ($receiptsArr instanceof Traversable || (is_object($receiptsArr) && !is_array($receiptsArr))) {
    try { $receiptsArr = iterator_to_array($receiptsArr); } catch (Throwable $e) { $receiptsArr = (array)$receiptsArr; }
  } elseif (!is_array($receiptsArr)) {
    $receiptsArr = (array)$receiptsArr;
  }

  $totalReceipts = count($receiptsArr);
  $todayCount = 0;
  $totalProducts = 0;
  $todayDate = date('Y-m-d');
  foreach ($receiptsArr as $rr) {
    $created = $rr['created_at'] ?? null;
    $createdDate = '1970-01-01';
    if ($created instanceof MongoDB\BSON\UTCDateTime) {
      $createdDate = $created->toDateTime()->format('Y-m-d');
    } elseif (!empty($created)) {
      $createdDate = date('Y-m-d', strtotime($created));
    }
    if ($createdDate === $todayDate) $todayCount++;
    $totalProducts += count($rr['details'] ?? []);
  }

  // --- Pagination ---
  $itemsPerPage = 10;
  $currentPage = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
  $totalPages = ceil($totalReceipts / $itemsPerPage);
  $offset = ($currentPage - 1) * $itemsPerPage;
  $receiptsToDisplay = array_slice($receiptsArr, $offset, $itemsPerPage);

  if (isset($_SESSION['flash_receipt'])) {
    echo '<div style="padding:10px;background:#e6ffed;border:1px solid #b7f0c6;margin-bottom:12px;color:#256029;">'.htmlspecialchars($_SESSION['flash_receipt']).'</div>';
    unset($_SESSION['flash_receipt']);
  }
  if (isset($_SESSION['flash_receipt_error'])) {
    echo '<div style="padding:10px;background:#ffecec;border:1px solid #f5c2c2;margin-bottom:12px;color:#8a1f1f;">'.htmlspecialchars($_SESSION['flash_receipt_error']).'</div>';
    unset($_SESSION['flash_receipt_error']);
  }
  ?>

  <!-- Statistics cards -->
  <div style="display:flex;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
    <div style="flex:1;min-width:180px;padding:18px;border-radius:10px;background:linear-gradient(90deg,#2f9eff,#3db7ff);color:#fff;">
      <div style="font-size:14px;">Tổng phiếu</div>
      <div style="font-size:28px;font-weight:700;"><?= $totalReceipts ?></div>
    </div>
    <div style="flex:1;min-width:180px;padding:18px;border-radius:10px;background:linear-gradient(90deg,#18c97b,#28d399);color:#fff;">
      <div style="font-size:14px;">Phiếu hôm nay</div>
      <div style="font-size:28px;font-weight:700;"><?= $todayCount ?></div>
    </div>
    <div style="flex:1;min-width:180px;padding:18px;border-radius:10px;background:linear-gradient(90deg,#ffb400,#ffd24d);color:#fff;">
      <div style="font-size:14px;">Tổng sản phẩm</div>
      <div style="font-size:28px;font-weight:700;"><?= $totalProducts ?></div>
    </div>
  </div>

  <table id="receipt-table">
    <thead>
      <tr>
        <th>STT</th>
        <th>Mã phiếu</th>
        <th>Ngày tạo</th>
        <th>Người tạo</th>
        <th>Kho</th>
        <th>Loại phiếu</th>
        <th>Số SP</th>
        <th>Trạng thái</th>
        <th>Tổng tiền</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      if (!empty($receiptsToDisplay) && count($receiptsToDisplay) > 0) {
        $stt = $offset + 1; // Khởi tạo STT theo trang
        foreach ($receiptsToDisplay as $r) {
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

          // Đếm số lượng sản phẩm
          $productCount = count($r['details'] ?? []);

          // Lưu ngày tạo dạng YYYY-MM-DD cho filter
          $created_date_sort = 'N/A';
          if (isset($r['created_at'])) {
              if ($r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                  $created_date_sort = date('Y-m-d', $r['created_at']->toDateTime()->getTimestamp());
              } else {
                  $created_date_sort = date('Y-m-d', strtotime($r['created_at']));
              }
          }

          echo "
            <tr data-status='$class' data-type='{$r['type']}' data-date='$created_date_sort'>
              <td>$stt</td>
              <td>{$r['transaction_id']}</td>
              <td>$created_date</td>
              <td>".($r['creator_name'] ?? $r['created_by'])."</td>
              <td>{$r['warehouse_id']}</td>
              <td>{$r['type']}</td>
              <td><strong style='color:#0066cc;'>$productCount</strong></td>
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
              <a href='receipts/approve/process.php?action=approve&id={$r['transaction_id']}' class='btn btn-approve confirm-action' data-action='approve' data-id='{$r['transaction_id']}' title='Duyệt'>
                <i class=\"fa-solid fa-check\"></i>
              </a>
              <a href='receipts/approve/process.php?action=reject&id={$r['transaction_id']}' class='btn btn-reject confirm-action' data-action='reject' data-id='{$r['transaction_id']}' title='Từ chối'>
                <i class=\"fa-solid fa-xmark\"></i>
              </a>
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
          $stt++;
        }
        } else {
        echo "<tr><td colspan='10'>Không có phiếu nhập nào.</td></tr>";
      } ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="margin-top:20px;display:flex;justify-content:center;align-items:center;gap:8px;">
    <?php if ($currentPage > 1): ?>
      <a href="?page=receipts/approve&pg=<?= $currentPage - 1 ?>" style="padding:8px 12px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;">
        <i class="fa-solid fa-chevron-left"></i> 
      </a>
    <?php endif; ?>

    <?php
    $range = 2;
    $startPage = max(1, $currentPage - $range);
    $endPage = min($totalPages, $currentPage + $range);

    if ($startPage > 1) {
      echo '<a href="?page=receipts/approve&pg=1" style="padding:8px 12px;background:#f0f0f0;color:#333;text-decoration:none;border-radius:6px;font-size:14px;">1</a>';
      if ($startPage > 2) {
        echo '<span style="padding:8px;">...</span>';
      }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
      $active = $i === $currentPage ? 'background:#007bff;color:#fff;' : 'background:#f0f0f0;color:#333;';
      echo "<a href='?page=receipts/approve&pg=$i' style='padding:8px 12px;$active text-decoration:none;border-radius:6px;font-size:14px;'>$i</a>";
    }

    if ($endPage < $totalPages) {
      if ($endPage < $totalPages - 1) {
        echo '<span style="padding:8px;">...</span>';
      }
      echo "<a href='?page=receipts/approve&pg=$totalPages' style='padding:8px 12px;background:#f0f0f0;color:#333;text-decoration:none;border-radius:6px;font-size:14px;'>$totalPages</a>";
    }
    ?>

    <?php if ($currentPage < $totalPages): ?>
      <a href="?page=receipts/approve&pg=<?= $currentPage + 1 ?>" style="padding:8px 12px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;">
         <i class="fa-solid fa-chevron-right"></i>
      </a>
    <?php endif; ?>
  </div>

  <div style="text-align:center;margin-top:10px;color:#666;font-size:14px;">
    Trang <?= $currentPage ?> / <?= $totalPages ?> (Tổng <?= $totalReceipts ?> phiếu)
  </div>
  <?php endif; ?>
</div><script>
  const statusFilter = document.getElementById('filter-status');
  const typeFilter = document.getElementById('filter-type');
  const dateFromFilter = document.getElementById('filter-date-from');
  const dateToFilter = document.getElementById('filter-date-to');
  const resetBtn = document.getElementById('btn-reset-filters');
  const rows = document.querySelectorAll('#receipt-table tbody tr');

  function applyFilters() {
    const selectedStatus = statusFilter.value;
    const selectedType = typeFilter.value.toLowerCase();
    const dateFrom = dateFromFilter.value;
    const dateTo = dateToFilter.value;

    rows.forEach(row => {
      const rowStatus = row.getAttribute('data-status');
      const rowType = row.getAttribute('data-type').toLowerCase();
      const rowDate = row.getAttribute('data-date');

      const matchStatus = !selectedStatus || rowStatus === selectedStatus;
      const matchType = !selectedType || rowType.includes(selectedType);
      
      let matchDate = true;
      if (rowDate && rowDate !== 'N/A') {
        if (dateFrom && rowDate < dateFrom) matchDate = false;
        if (dateTo && rowDate > dateTo) matchDate = false;
      }

      row.style.display = (matchStatus && matchType && matchDate) ? '' : 'none';
    });
  }

  statusFilter.addEventListener('change', applyFilters);
  typeFilter.addEventListener('change', applyFilters);
  dateFromFilter.addEventListener('change', applyFilters);
  dateToFilter.addEventListener('change', applyFilters);

  resetBtn.addEventListener('click', function() {
    statusFilter.value = '';
    typeFilter.value = '';
    dateFromFilter.value = '';
    dateToFilter.value = '';
    applyFilters();
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function confirmAction(action, id, href) {
    // If rejecting, require a reason via textarea input
    if (action === 'reject') {
      Swal.fire({
        title: 'Nhập lý do từ chối',
        input: 'textarea',
        inputPlaceholder: 'Nhập lý do từ chối...',
        inputAttributes: { 'aria-label': 'Lý do từ chối' },
        showCancelButton: true,
        confirmButtonText: 'Xác nhận',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        preConfirm: (reason) => {
          if (!reason || !reason.trim()) {
            Swal.showValidationMessage('Vui lòng nhập lý do từ chối');
          }
          return reason;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const reason = result.value || '';
          const baseUrl = href ? href : `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}`;
          const sep = baseUrl.indexOf('?') === -1 ? '?' : '&';
          window.location.href = baseUrl + sep + 'reason=' + encodeURIComponent(reason);
        }
      });
      return;
    }

    // Default: simple confirmation (approve)
    const actionText = action === 'approve' ? 'duyệt' : 'thực hiện';
    const color = action === 'approve' ? '#28a745' : '#6c757d';
    Swal.fire({
      title: `Xác nhận ${actionText} phiếu này?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: `Xác nhận`,
      cancelButtonText: `Hủy`,
      confirmButtonColor: color,
      cancelButtonColor: '#6c757d',
    }).then((result) => {
      if (result.isConfirmed) {
        if (href) {
          window.location.href = href;
        } else {
          window.location.href = `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}`;
        }
      }
    });
  }

  document.addEventListener('click', function(e) {
    const el = e.target.closest && e.target.closest('.confirm-action');
    if (!el) return;
    e.preventDefault();
    const action = el.getAttribute('data-action');
    const id = el.getAttribute('data-id');
    const href = el.getAttribute('href');
    confirmAction(action, id, href);
  });
</script>
