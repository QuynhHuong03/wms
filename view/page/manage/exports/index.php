<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../model/connect.php");
include_once(__DIR__ . "/../../../../controller/cWarehouse.php");

$cWarehouse = new CWarehouse();

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['login']['user_id'] ?? 'U001';
$role = $_SESSION['login']['role'] ?? 'staff';
$role_name = $_SESSION['login']['role_name'] ?? '';
$role_id = $_SESSION['login']['role_id'] ?? '';
$warehouse_id = $_SESSION['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? null);

// Xác định vai trò quản lý
$allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
$allowedRoleIds = [2, 4];
$isManager = in_array($role, $allowedRoles) || in_array($role_name, $allowedRoles) || in_array($role_id, $allowedRoleIds);

// Xác định xem có phải Kho Tổng không
$isWarehouseMain = ($warehouse_id === 'KHO_TONG_01' || strpos($warehouse_id, 'TONG') !== false);

// Kết nối MongoDB để lấy phiếu xuất
$p = new clsKetNoi();
$con = $p->moKetNoi();
$transactionsCol = null;
$exports = [];

// DEBUG: Uncomment to see user info
// echo "<div style='background:#fff3cd;padding:10px;margin:10px;border:1px solid #ffc107;'>";
// echo "<strong>DEBUG Info:</strong><br>";
// echo "User ID: " . htmlspecialchars($user_id) . "<br>";
// echo "Warehouse ID: " . htmlspecialchars($warehouse_id ?? 'NULL') . "<br>";
// echo "Role: " . htmlspecialchars($role) . "<br>";
// echo "Is Manager: " . ($isManager ? 'YES' : 'NO') . "<br>";
// echo "</div>";

if ($con) {
    $transactionsCol = $con->selectCollection('transactions');
    
    // Lấy danh sách phiếu xuất
    $filter = ['transaction_type' => 'export'];
    
    // Lọc phiếu xuất theo kho của user (CHỈ với nhân viên thường, không phải manager)
    // Manager/Admin có thể xem TẤT CẢ phiếu xuất
    if ($warehouse_id && !$isManager) {
        // CHỈ xem phiếu xuất TỪ kho của mình
        // (Phiếu xuất ĐẾN kho của mình xem ở trang "Phiếu xuất chờ duyệt")
        $filter['warehouse_id'] = $warehouse_id;
    }
    
    // Aggregation để join với users và warehouses
    $pipeline = [
        ['$match' => $filter],
        // Join với users để lấy tên người tạo
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
        ],
        ['$sort' => ['created_at' => -1]]
    ];
    
    try {
        $exports = iterator_to_array($transactionsCol->aggregate($pipeline));
    } catch (Exception $e) {
        error_log("Error fetching exports: " . $e->getMessage());
    }
}
?>

<style>
  .export-list-container {max-width:1600px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 15px rgba(0,0,0,0.08);}
  .export-list-container h2 {text-align:center;margin-bottom:25px;color:#333;display:flex;align-items:center;justify-content:center;gap:10px;}
  .export-list-container table {width:100%;border-collapse:collapse;margin-top:20px;}
  .export-list-container th,.export-list-container td {padding:12px;border:1px solid #e1e4e8;text-align:center;font-size:14px;}
  .export-list-container th {background:#f9fafb;font-weight:600;}
  .export-list-container tr:hover {background:#f1f7ff;}
  .export-list-container .btn {border:none;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block;margin:2px;}
  .export-list-container .btn-view {background:#17a2b8;color:#fff;}
  .export-list-container .btn-print {background:#28a745;color:#fff;}
  .export-list-container .btn:hover {opacity:0.85;transform:translateY(-1px);}
  .export-list-container .status {font-weight:600;padding:6px 12px;border-radius:8px;display:inline-block;}
  .export-list-container .completed {background:#d4edda;color:#155724;}
  .export-list-container .pending {background:#fff3cd;color:#856404;}
  .export-list-container .top-actions {margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
  .filters {display:flex;gap:10px;align-items:center;}
  .filters select, .filters input {padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:14px;}
  .alert {padding:12px;margin-bottom:15px;border-radius:8px;}
  .alert-info {background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}
  .alert-success {background:#d4edda;color:#155724;border:1px solid #c3e6cb;}
  .stats-row {display:flex;gap:15px;margin-bottom:20px;flex-wrap:wrap;}
  .stat-card {flex:1;min-width:200px;padding:15px;border-radius:8px;color:#fff;}
  .stat-card h3 {margin:0 0 8px 0;font-size:16px;opacity:0.9;}
  .stat-card .number {font-size:32px;font-weight:700;}
  /* Match gradients with receipts/requests pages: cyan, green, yellow */
  .stat-card.blue {background:linear-gradient(90deg,#2f9eff,#3db7ff);} /* cyan */
  .stat-card.green {background:linear-gradient(90deg,#18c97b,#28d399);} /* green */
  .stat-card.orange {background:linear-gradient(90deg,#ffb400,#ffd24d);} /* yellow/orange */
  .warehouse-badge {background:#e8f4fd;color:#0366d6;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;display:inline-block;}
  .product-count {background:#f0f0f0;padding:4px 8px;border-radius:6px;font-size:13px;font-weight:600;color:#555;}
</style>

<div class="export-list-container">
  <h2>
    <i class="fa-solid fa-truck-fast"></i> Danh sách phiếu xuất kho
  </h2>

  <?php
  // Hiển thị thông báo flash message
  if (isset($_SESSION['flash_request'])) {
    echo '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> '.htmlspecialchars($_SESSION['flash_request']).'</div>';
    unset($_SESSION['flash_request']);
  }
  if (isset($_SESSION['flash_request_error'])) {
    echo '<div class="alert alert-info" style="background:#ffecec;border-color:#f5c2c2;color:#8a1f1f;"><i class="fa-solid fa-exclamation-circle"></i> '.htmlspecialchars($_SESSION['flash_request_error']).'</div>';
    unset($_SESSION['flash_request_error']);
  }

  // Tính toán thống kê
  $totalExports = count($exports);
  $todayExports = 0;
  $totalProducts = 0;
  
  $today = date('Y-m-d');
  foreach ($exports as $export) {
    if (isset($export['created_at'])) {
      $exportDate = $export['created_at'] instanceof MongoDB\BSON\UTCDateTime 
        ? $export['created_at']->toDateTime()->format('Y-m-d')
        : date('Y-m-d', strtotime($export['created_at']));
      if ($exportDate === $today) {
        $todayExports++;
      }
    }
    $totalProducts += count($export['details'] ?? []);
  }

  // --- Pagination ---
  $itemsPerPage = 10;
  $currentPage = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;
  $totalPages = ceil($totalExports / $itemsPerPage);
  $offset = ($currentPage - 1) * $itemsPerPage;
  $exportsToDisplay = array_slice($exports, $offset, $itemsPerPage);
  ?>

  <div class="stats-row">
    <div class="stat-card blue">
      <h3><i class="fa-solid fa-file-export"></i> Tổng phiếu xuất</h3>
      <div class="number"><?= $totalExports ?></div>
    </div>
    <div class="stat-card green">
      <h3><i class="fa-solid fa-calendar-day"></i> Xuất hôm nay</h3>
      <div class="number"><?= $todayExports ?></div>
    </div>
    <div class="stat-card orange">
      <h3><i class="fa-solid fa-box"></i> Tổng sản phẩm</h3>
      <div class="number"><?= $totalProducts ?></div>
    </div>
  </div>

  <div class="top-actions">
    <div class="filters">
      <input type="text" id="filter-search" placeholder=" Tìm mã phiếu, kho..." style="width:250px;">
      
      <select id="filter-status" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;font-size:14px;">
        <option value="">Lọc theo trạng thái</option>
        <option value="pending">Chờ xác nhận</option>
        <option value="completed">Đã xuất kho</option>
        <option value="delivered">Đã giao hàng</option>
      </select>
      
      <input type="date" id="filter-date-from" placeholder="Từ ngày">
      <input type="date" id="filter-date-to" placeholder="Đến ngày">
      <button class="btn btn-print" onclick="filterTable()" style="background:#6c757d;">
        <i class="fa-solid fa-filter"></i> Lọc
      </button>
      <button class="btn btn-print" onclick="resetFilter()" style="background:#dc3545;">
        <i class="fa-solid fa-rotate-right"></i> Reset
      </button>
    </div>
  </div>

  <?php if (count($exports) > 0): ?>
    <table id="export-table">
      <thead>
        <tr>
          <th>STT</th>
          <th>Mã phiếu xuất</th>
          <th>Mã phiếu yêu cầu</th>
          <th>Kho xuất</th>
          <th>Kho nhận</th>
          <th>Ngày xuất</th>
          <!-- <th>Người xuất</th> -->
          <th>Số SP</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $stt = $offset + 1;
        foreach ($exportsToDisplay as $export) {
          $exportId = $export['transaction_id'] ?? 'N/A';
          $requestId = $export['request_id'] ?? 'N/A';
          
          // Trong phiếu xuất: warehouse_id = kho xuất, destination_warehouse_id = kho nhận
          $sourceWarehouseId = $export['warehouse_id'] ?? ($export['source_warehouse_id'] ?? 'N/A');
          $destinationWarehouseId = $export['destination_warehouse_id'] ?? 'N/A';
          
          // Lấy tên kho
          $sourceWarehouse = null;
          $sourceWarehouseName = $sourceWarehouseId;
          if ($sourceWarehouseId !== 'N/A') {
            $sourceWarehouse = $cWarehouse->getWarehouseById($sourceWarehouseId);
            $sourceWarehouseName = $sourceWarehouse['name'] ?? $sourceWarehouse['warehouse_name'] ?? $sourceWarehouseId;
          }
          
          $destinationWarehouse = null;
          $destinationWarehouseName = $destinationWarehouseId;
          if ($destinationWarehouseId !== 'N/A') {
            $destinationWarehouse = $cWarehouse->getWarehouseById($destinationWarehouseId);
            $destinationWarehouseName = $destinationWarehouse['name'] ?? $destinationWarehouse['warehouse_name'] ?? $destinationWarehouseId;
          }
          
          $created_date = 'N/A';
          $created_date_sort = '';
          if (isset($export['created_at'])) {
            if ($export['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
              $created_date = date('d/m/Y H:i', $export['created_at']->toDateTime()->getTimestamp());
              $created_date_sort = date('Y-m-d', $export['created_at']->toDateTime()->getTimestamp());
            } else {
              $created_date = date('d/m/Y H:i', strtotime($export['created_at']));
              $created_date_sort = date('Y-m-d', strtotime($export['created_at']));
            }
          }
          
          $creatorName = $export['creator_name'] ?? ($export['created_by'] ?? 'N/A');
          $totalProducts = count($export['details'] ?? []);
          
          // Kiểm tra status và inventory_deducted
          $status = (int)($export['status'] ?? 0);
          $inventoryDeducted = $export['inventory_deducted'] ?? false;
          
          // Trạng thái
          $statusBadge = '';
          $statusClass = '';
          $confirmButton = '';
          if ($status == 0 && !$inventoryDeducted) {
            $statusBadge = '<span class="status pending"><i class="fa-solid fa-clock"></i> Chờ xác nhận</span>';
            $statusClass = 'pending';
            // Nút xác nhận xuất kho (chỉ manager mới thấy)
            if ($isManager) {
              $confirmButton = "
                <button class='btn btn-print' onclick='confirmExport(\"$exportId\")' title='Xác nhận xuất kho (Trừ inventory)' style='background:#28a745;'>
                  <i class='fa-solid fa-check-circle'></i> Xác nhận
                </button>
              ";
            }
          } elseif ($status == 1 || $inventoryDeducted) {
            $statusBadge = '<span class="status completed"><i class="fa-solid fa-check-circle"></i> Đã xuất kho</span>';
            $statusClass = 'completed';
          } elseif ($status == 2) {
            $statusBadge = '<span class="status completed" style="background:#d4edda;"><i class="fa-solid fa-truck"></i> Đã giao hàng</span>';
            $statusClass = 'delivered';
          }

          echo "
            <tr data-date='$created_date_sort' data-search='$exportId $requestId $sourceWarehouseId $destinationWarehouseId $sourceWarehouseName $destinationWarehouseName' data-status='$statusClass'>
              <td>$stt</td>
              <td><strong style='color:#0366d6;'>$exportId</strong></td>
              <td>
                <a href='index.php?page=goodsReceiptRequest/detail&id=$requestId' style='color:#28a745;text-decoration:none;font-weight:600;'>
                  $requestId <i class='fa-solid fa-external-link-alt' style='font-size:11px;'></i>
                </a>
              </td>
              <td>
                <span class='warehouse-badge'>
                  <i class=''></i> $sourceWarehouseName
                </span>
              </td>
              <td>
                <span class='warehouse-badge' style='background:#e8fde8;color:#28a745;'>
                  <i class=''></i> $destinationWarehouseName
                </span>
              </td>
              <td>$created_date</td>
              
              <td><span class='product-count'>$totalProducts SP</span></td>
              <td>$statusBadge</td>
              <td>
                <a href='index.php?page=exports/detail&id=$exportId' class='btn btn-view' title='Xem chi tiết'>
                  <i class='fa-solid fa-eye'></i>
                </a>
                $confirmButton
                <button class='btn btn-print' onclick='printExport(\"$exportId\")' title='In phiếu'>
                  <i class='fa-solid fa-print'></i>
                </button>
              </td>
            </tr>
          ";
          $stt++;
        }
        ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-info">
      <i class="fa-solid fa-info-circle"></i> Chưa có phiếu xuất kho nào.
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="margin-top:20px;display:flex;justify-content:center;align-items:center;gap:8px;">
    <?php if ($currentPage > 1): ?>
      <a href="?page=exports&pg=<?= $currentPage - 1 ?>" style="padding:8px 12px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;">
        <i class="fa-solid fa-chevron-left"></i> 
      </a>
    <?php endif; ?>

    <?php
    $range = 2;
    $startPage = max(1, $currentPage - $range);
    $endPage = min($totalPages, $currentPage + $range);

    if ($startPage > 1) {
      echo '<a href="?page=exports&pg=1" style="padding:8px 12px;background:#f0f0f0;color:#333;text-decoration:none;border-radius:6px;font-size:14px;">1</a>';
      if ($startPage > 2) {
        echo '<span style="padding:8px;">...</span>';
      }
    }

    for ($i = $startPage; $i <= $endPage; $i++) {
      $active = $i === $currentPage ? 'background:#007bff;color:#fff;' : 'background:#f0f0f0;color:#333;';
      echo "<a href='?page=exports&pg=$i' style='padding:8px 12px;$active text-decoration:none;border-radius:6px;font-size:14px;'>$i</a>";
    }

    if ($endPage < $totalPages) {
      if ($endPage < $totalPages - 1) {
        echo '<span style="padding:8px;">...</span>';
      }
      echo "<a href='?page=exports&pg=$totalPages' style='padding:8px 12px;background:#f0f0f0;color:#333;text-decoration:none;border-radius:6px;font-size:14px;'>$totalPages</a>";
    }
    ?>

    <?php if ($currentPage < $totalPages): ?>
      <a href="?page=exports&pg=<?= $currentPage + 1 ?>" style="padding:8px 12px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;">
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    <?php endif; ?>
  </div>

  <div style="text-align:center;margin-top:10px;color:#666;font-size:14px;">
    Trang <?= $currentPage ?> / <?= $totalPages ?> (Tổng <?= $totalExports ?> phiếu)
  </div>
  <?php endif; ?>
</div>

<!-- Confirm Export Modal -->
<div class="warehouse-modal" id="confirmExportModal" style="backdrop-filter:blur(4px);display:none;">
  <div class="warehouse-modal-content" style="max-width:500px;">
    <div class="warehouse-modal-header" style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);">
      <h3><i class="fa-solid fa-circle-check"></i> Xác nhận xuất kho</h3>
      <button class="warehouse-modal-close" onclick="closeConfirmExportModal()">×</button>
    </div>
    <div class="warehouse-modal-body">
      <div style="padding:20px 0;text-align:center;">
        <i class="fa-solid fa-truck-loading" style="font-size:64px;color:#10b981;margin-bottom:20px;"></i>
        <p style="font-size:16px;color:#334155;line-height:1.6;margin:0;">
          Bạn có chắc chắn muốn xác nhận xuất kho?
        </p>
        <p style="font-size:14px;color:#64748b;margin-top:12px;line-height:1.5;">
          Hành động này sẽ trừ hàng khỏi kho và không thể hoàn tác.
        </p>
      </div>
    </div>
    <div class="warehouse-modal-footer" style="background:#f8fafc;">
      <button type="button" class="btn" style="background:#6c757d;color:#fff;padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;" onclick="closeConfirmExportModal()">
        <i class="fa-solid fa-xmark"></i> Hủy
      </button>
      <button type="button" class="btn" style="background:#10b981;color:#fff;padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;" onclick="proceedConfirmExport()">
        <i class="fa-solid fa-check"></i> Xác nhận xuất
      </button>
    </div>
  </div>
</div>

<style>
  .warehouse-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    background: rgba(11, 18, 32, 0.5);
  }
  
  .warehouse-modal.active {
    display: flex !important;
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
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #fff;
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
    border-top: 2px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }
</style>

<script>
  function filterTable() {
    const searchValue = document.getElementById('filter-search').value.toLowerCase();
    const statusValue = document.getElementById('filter-status').value;
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    const rows = document.querySelectorAll('#export-table tbody tr');

    rows.forEach(row => {
      const searchText = row.getAttribute('data-search').toLowerCase();
      const rowStatus = row.getAttribute('data-status');
      const rowDate = row.getAttribute('data-date');

      const matchSearch = !searchValue || searchText.includes(searchValue);
      const matchStatus = !statusValue || rowStatus === statusValue;
      const matchDateFrom = !dateFrom || rowDate >= dateFrom;
      const matchDateTo = !dateTo || rowDate <= dateTo;

      row.style.display = (matchSearch && matchStatus && matchDateFrom && matchDateTo) ? '' : 'none';
    });
  }

  function resetFilter() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    
    const rows = document.querySelectorAll('#export-table tbody tr');
    rows.forEach(row => {
      row.style.display = '';
    });
  }

  function printExport(exportId) {
    // Có thể tạo trang in riêng hoặc mở cửa sổ mới
    window.open('index.php?page=exports/print&id=' + exportId, '_blank');
  }
  
  let currentExportId = null;
  
  function confirmExport(exportId) {
    console.log('confirmExport called with:', exportId);
    console.log('Type:', typeof exportId);
    
    // Store export ID and show modal
    currentExportId = exportId;
    console.log('currentExportId set to:', currentExportId);
    
    const modal = document.getElementById('confirmExportModal');
    console.log('Modal element:', modal);
    
    if (modal) {
      modal.classList.add('active');
      console.log('Modal opened');
    } else {
      console.error('Modal not found!');
    }
  }
  
  function closeConfirmExportModal() {
    document.getElementById('confirmExportModal').classList.remove('active');
    currentExportId = null;
  }
  
  function proceedConfirmExport() {
    console.log('proceedConfirmExport called');
    console.log('currentExportId value:', currentExportId);
    console.log('currentExportId type:', typeof currentExportId);
    
    if (!currentExportId) {
      alert('Lỗi: Không tìm thấy mã phiếu xuất!');
      console.error('currentExportId is null/undefined');
      return;
    }
    
    console.log('Proceeding with export ID:', currentExportId);
    
    // LƯU export ID vào biến local TRƯỚC KHI đóng modal
    const exportIdToConfirm = currentExportId;
    
    // Close modal
    closeConfirmExportModal();
    
    // Show loading overlay or disable all buttons
    const confirmBtn = document.querySelector(`button[onclick*="confirmExport('${exportIdToConfirm}')"]`);
    let originalHTML = '';
    
    if (confirmBtn) {
      originalHTML = confirmBtn.innerHTML;
      confirmBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang xử lý...';
      confirmBtn.disabled = true;
    }
    
    // Make API call với biến local
    fetch('exports/process.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `action=confirm_export&export_id=${encodeURIComponent(exportIdToConfirm)}`
    })
    .then(res => res.json())
    .then(data => {
      console.log('Response:', data);
      if (data.success) {
        alert('✅ ' + data.message);
        location.reload();
      } else {
        alert('❌ ' + data.message);
        if (confirmBtn && originalHTML) {
          confirmBtn.innerHTML = originalHTML;
          confirmBtn.disabled = false;
        }
      }
    })
    .catch(err => {
      console.error('Error:', err);
      alert('❌ Lỗi: ' + err.message);
      if (confirmBtn && originalHTML) {
        confirmBtn.innerHTML = originalHTML;
        confirmBtn.disabled = false;
      }
    });
  }
  
  // Close modal on backdrop click
  document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('confirmExportModal');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          closeConfirmExportModal();
        }
      });
    }
  });

  // Tự động lọc khi gõ hoặc thay đổi select
  document.getElementById('filter-search')?.addEventListener('input', filterTable);
  document.getElementById('filter-status')?.addEventListener('change', filterTable);
  document.getElementById('filter-date-from')?.addEventListener('change', filterTable);
  document.getElementById('filter-date-to')?.addEventListener('change', filterTable);
</script>

<?php
$p->dongKetNoi($con);
?>
