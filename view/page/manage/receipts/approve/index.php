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
  
  /* Custom SweetAlert2 styles */
  .swal-custom-popup {
    border-radius: 16px !important;
    padding: 0 !important;
  }
  .swal-custom-title {
    padding: 20px 20px 10px !important;
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 0 !important;
  }
  .swal2-html-container {
    margin: 0 !important;
    padding: 0 !important;
  }
  
  /* Toast notification styles */
  .toast {
    min-width: 300px;
    background: #fff;
    padding: 16px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    animation: slideIn 0.3s ease-out;
    border-left: 4px solid #28a745;
  }
  .toast.success {
    border-left-color: #28a745;
  }
  .toast.success .toast-icon {
    color: #28a745;
  }
  .toast.error {
    border-left-color: #dc3545;
  }
  .toast.error .toast-icon {
    color: #dc3545;
  }
  .toast-icon {
    font-size: 24px;
  }
  .toast-message {
    flex: 1;
    color: #333;
    font-size: 14px;
    font-weight: 500;
  }
  .toast-close {
    background: none;
    border: none;
    font-size: 20px;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .toast-close:hover {
    color: #666;
  }
  @keyframes slideIn {
    from {
      transform: translateX(400px);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  @keyframes slideOut {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(400px);
      opacity: 0;
    }
  }
  .toast.hiding {
    animation: slideOut 0.3s ease-out forwards;
  }
</style>

<div class="receipt-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-file-lines"></i> Danh sách phiếu nhập kho</h2>

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
  ?>

  <!-- Toast notification container -->
  <div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>
  
  <script>
  // Define showToast function early so it's available for inline scripts
  function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) {
      console.error('Toast container not found');
      return;
    }
    
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    
    const icon = type === 'success' 
      ? '<i class="fa-solid fa-circle-check toast-icon"></i>'
      : '<i class="fa-solid fa-circle-exclamation toast-icon"></i>';
    
    toast.innerHTML = `
      ${icon}
      <div class="toast-message">${message}</div>
      <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    container.appendChild(toast);
    
    // Tự động ẩn sau 4 giây
    setTimeout(() => {
      toast.classList.add('hiding');
      setTimeout(() => toast.remove(), 300);
    }, 4000);
  }
  </script>
  
  <?php
  // Hiển thị toast cho thông báo thành công
  if (isset($_SESSION['flash_receipt'])) {
    $message = htmlspecialchars($_SESSION['flash_receipt']);
    echo "<script>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('$message', 'success');
      });
    </script>";
    unset($_SESSION['flash_receipt']);
  }
  // Hiển thị toast cho thông báo lỗi
  if (isset($_SESSION['flash_receipt_error'])) {
    $message = htmlspecialchars($_SESSION['flash_receipt_error']);
    echo "<script>
      document.addEventListener('DOMContentLoaded', function() {
        showToast('$message', 'error');
      });
    </script>";
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
              <a href='receipts/approve/process.php?action=approve&id={$r['transaction_id']}' class='btn btn-approve confirm-action' data-action='approve' data-id='{$r['transaction_id']}' data-receipt-type='{$r['type']}' title='Duyệt'>
                <i class=\"fa-solid fa-check\"></i>
              </a>
              <a href='receipts/approve/process.php?action=reject&id={$r['transaction_id']}' class='btn btn-reject confirm-action' data-action='reject' data-id='{$r['transaction_id']}' title='Từ chối'>
                <i class=\"fa-solid fa-xmark\"></i>
              </a>
            ";
          }

          // Nếu là nhân viên và phiếu đã duyệt (được phép xem vị trí)
          if (!$isManager && $status === 1) {
            echo "
                <a href='index.php?page=receipts/locate&id={$r['transaction_id']}' class='btn btn-locate' title='Xem vị trí xếp hàng'>
                  <i class=\"fa-solid fa-location-dot\"></i> Vị trí
                </a>
            ";
          }

          // Nút 'Xếp hàng' cho quản lý khi phiếu đã duyệt, hoặc nút 'Vị trí' cho phiếu hoàn tất
          if (($status === 1 && $isManager) || $status === 3) {
            $btnText = $status === 3 ? 'Vị trí' : 'Xếp hàng';
            $btnIcon = $status === 3 ? 'fa-location-dot' : 'fa-dolly';
            $btnTitle = $status === 3 ? 'Xem/Sửa vị trí đã xếp' : 'Xếp hàng vào vị trí';
            echo "
                <a href='index.php?page=receipts/locate&id={$r['transaction_id']}' class='btn btn-locate' title='$btnTitle'>
                  <i class=\"fa-solid $btnIcon\"></i> $btnText
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

    // Default: confirmation for approval with modal
    const actionText = action === 'approve' ? 'duyệt' : 'thực hiện';
    const color = action === 'approve' ? '#28a745' : '#6c757d';
    Swal.fire({
      title: `Xác nhận ${actionText} phiếu này?`,
      text: '',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'OK',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#aaa',
    }).then((result) => {
      if (result.isConfirmed) {
        // Check if this is a transfer receipt - if so, approve directly without modal
        const receiptType = document.querySelector(`[data-id="${id}"]`)?.getAttribute('data-receipt-type');
        
        if (receiptType === 'transfer') {
          // For transfer receipts, approve directly without showing the modal
          window.location.href = href || `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}`;
          return;
        }
        
        // For purchase receipts, load approval form fragment via AJAX and show in modal
        const url = `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}&ajax=1`;
        fetch(url, { credentials: 'same-origin' })
          .then(res => {
            const contentType = res.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
              // Response is JSON - means no form needed, directly approved
              return res.json().then(data => {
                if (data.success) {
                  showToast(data.message || 'Đã duyệt phiếu thành công!', 'success');
                  setTimeout(() => window.location.reload(), 1500);
                } else {
                  Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message || 'Không thể duyệt phiếu' });
                }
              });
            } else {
              // Response is HTML form - show in modal
              return res.text().then(html => {
                Swal.fire({
                  title: '<div style="color:#667eea;font-size:20px;"><i class="fa-solid fa-box"></i> Nhập thông tin sản phẩm mới</div>',
                  html: html,
                  width: '900px',
                  showCancelButton: false,
                  showConfirmButton: false,
                  customClass: {
                    popup: 'swal-custom-popup',
                    title: 'swal-custom-title'
                  },
                  didOpen: () => {
                    const container = Swal.getHtmlContainer();
                    if (!container) return;
                    const form = container.querySelector('form#approve-new-products-form');
                    if (form) {
                      form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const fd = new FormData(form);
                        // send POST with ajax=1
                        const postUrl = `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}&ajax=1`;
                        fetch(postUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                          .then(r => r.json())
                          .then(data => {
                            if (data.success) {
                              Swal.close();
                              showToast(data.message || 'Đã duyệt phiếu thành công!', 'success');
                              setTimeout(() => window.location.reload(), 1500);
                            } else {
                              Swal.fire({ icon: 'error', title: 'Lỗi', text: data.message || 'Không thể duyệt phiếu' });
                            }
                          })
                          .catch(err => {
                            Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Lỗi kết nối' });
                          });
                      });
                    }
                  }
                });
              });
            }
          })
          .catch(err => {
            Swal.fire({ icon: 'error', title: 'Lỗi', text: 'Không thể tải form duyệt' });
          });
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
