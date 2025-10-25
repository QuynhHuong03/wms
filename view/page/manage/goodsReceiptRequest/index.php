<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../controller/cInventory.php");

$cRequest = new CRequest();
$cInventory = new CInventory();

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

// ⭐ Xác định xem có phải Kho Tổng không
$isWarehouseMain = ($warehouse_id === 'KHO_TONG_01' || strpos($warehouse_id, 'TONG') !== false);

// ⭐ Cả nhân viên và quản lý đều có thể tạo phiếu yêu cầu
// Lấy danh sách phiếu yêu cầu nhập hàng

if ($isWarehouseMain && $isManager) {
  // ⭐ KHO TỔNG: Xem phiếu yêu cầu gửi đến kho tổng (status 1, 3, 4)
  // Status 1: Đã duyệt chờ kiểm tra kho
  // Status 3: Đủ hàng, chờ tạo phiếu xuất
  // Status 4: Thiếu hàng, chờ chỉ định kho khác
  $requestsToWarehouse = $cRequest->getRequestsBySourceWarehouse($warehouse_id, [1, 3, 4]);
  $assignedRequests = $cRequest->getRequestsAssignedToWarehouse($warehouse_id);
  
  // Xem phiếu của chính kho tổng tạo ra
  $myRequests = $cRequest->getRequestsByWarehouse($warehouse_id);
} elseif ($isManager && $warehouse_id) {
  // Quản lý chi nhánh xem tất cả phiếu thuộc kho của họ
  $requests = $cRequest->getRequestsByWarehouse($warehouse_id);
} else {
  // Nhân viên chỉ xem phiếu của họ tạo
  $requests = $cRequest->getRequestsByUser($user_id);
}
?>

<style>
  .request-list-container {max-width:1400px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 15px rgba(0,0,0,0.08);}
  .request-list-container h2 {text-align:center;margin-bottom:25px;color:#333;}
  .request-list-container table {width:100%;border-collapse:collapse;margin-top:20px;}
  .request-list-container th,.request-list-container td {padding:10px 12px;border:1px solid #e1e4e8;text-align:center;font-size:14px;}
  .request-list-container th {background:#f9fafb;}
  .request-list-container tr:hover {background:#f1f7ff;}
  .request-list-container .btn {border:none;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block;margin:2px;}
  .request-list-container .btn-create {background:#28a745;color:#fff;}
  .request-list-container .btn-view {background:#17a2b8;color:#fff;}
  .request-list-container .btn-approve {background:#28a745;color:#fff;}
  .request-list-container .btn-reject {background:#dc3545;color:#fff;}
  .request-list-container .btn-convert {background:#ffc107;color:#000;}
  .request-list-container .btn-confirm {background:#28a745;color:#fff;}
  .request-list-container .btn-insufficient {background:#ffc107;color:#000;}
  .request-list-container .btn-assign {background:#6f42c1;color:#fff;}
  .request-list-container .btn:hover {opacity:0.9;}
  .request-list-container .status {font-weight:600;padding:6px 10px;border-radius:8px;display:inline-block;}
  .request-list-container .pending {background:#fff3cd;color:#856404;}
  .request-list-container .approved {background:#d4edda;color:#155724;}
  .request-list-container .rejected {background:#f8d7da;color:#721c24;}
  .request-list-container .urgent {background:#ffebee;color:#c62828;font-weight:700;}
  .request-list-container .normal {background:#e8f5e9;color:#2e7d32;}
  .request-list-container .confirmed {background:#d1f2eb;color:#0d7453;}
  .request-list-container .insufficient {background:#ffebcd;color:#d97706;}
  .request-list-container .assigned {background:#dbeafe;color:#1e40af;}
  .request-list-container .completed {background:#e0e0e0;color:#424242;}
  .request-list-container .top-actions {margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
  .filters {display:flex;gap:10px;align-items:center;}
  .filters select {padding:6px 10px;border-radius:6px;border:1px solid #ccc;font-size:14px;}
  
  /* ⭐ Tabs cho Kho Tổng */
  .tabs {display:flex;gap:10px;margin-bottom:20px;border-bottom:2px solid #e1e4e8;}
  .tab {padding:10px 20px;cursor:pointer;border:none;background:none;font-size:15px;font-weight:600;color:#666;border-bottom:3px solid transparent;transition:all 0.3s;}
  .tab.active {color:#007bff;border-bottom-color:#007bff;}
  .tab:hover {color:#007bff;}
  .tab-content {display:none;}
  .tab-content.active {display:block;}
  .alert {padding:12px;margin-bottom:15px;border-radius:8px;}
  .alert-info {background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}
</style>

<div class="request-list-container">
  <?php if ($isWarehouseMain && $isManager): ?>
    <!-- ========== VIEW CHO KHO TỔNG ========== -->
    <h2><i class="fa-solid fa-warehouse"></i> Quản lý yêu cầu nhập hàng - Kho Tổng</h2>

    <div class="tabs">
      <button class="tab active" onclick="switchTab('incoming')">
        <i class="fa-solid fa-inbox"></i> Yêu cầu đến (<?= count($requestsToWarehouse) ?>)
      </button>
      <button class="tab" onclick="switchTab('assigned')">
        <i class="fa-solid fa-arrow-right-arrow-left"></i> Đã chỉ định (<?= count($assignedRequests) ?>)
      </button>
      <button class="tab" onclick="switchTab('my-requests')">
        <i class="fa-solid fa-file-lines"></i> Phiếu của tôi (<?= count($myRequests) ?>)
      </button>
    </div>

    <?php
    if (isset($_SESSION['flash_request'])) {
      echo '<div class="alert alert-info" style="background:#e6ffed;border-color:#b7f0c6;color:#256029;">'.htmlspecialchars($_SESSION['flash_request']).'</div>';
      unset($_SESSION['flash_request']);
    }
    if (isset($_SESSION['flash_request_error'])) {
      echo '<div class="alert alert-info" style="background:#ffecec;border-color:#f5c2c2;color:#8a1f1f;">'.htmlspecialchars($_SESSION['flash_request_error']).'</div>';
      unset($_SESSION['flash_request_error']);
    }
    ?>

    <!-- Tab 1: Yêu cầu đến Kho Tổng -->
    <div id="tab-incoming" class="tab-content active">
      <div style="margin-bottom:15px;display:flex;justify-content:space-between;align-items:center;">
        <h3 style="margin:0;"><i class="fa-solid fa-clipboard-list"></i> Yêu cầu từ các chi nhánh</h3>
        <a href="index.php?page=goodsReceiptRequest/createReceipt" class="btn btn-create">
          <i class="fa-solid fa-plus"></i> Tạo yêu cầu mới
        </a>
      </div>
      
      <?php if (count($requestsToWarehouse) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>STT</th>
              <th>Mã phiếu</th>
              <th>Mức độ</th>
              <th>Ngày tạo</th>
              <th>Kho yêu cầu</th>
              <th>Người tạo</th>
              <th>Trạng thái</th>
              <th>Số SP</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $stt = 1;
            foreach ($requestsToWarehouse as $r) {
              $status = (int)($r['status'] ?? 0);
              
              // ⭐ Kiểm tra tồn kho và tìm kho thay thế
              $isSufficient = true;
              $sufficientBranchWarehouses = [];
              
              if (in_array($status, [1, 3])) {
                $details = $r['details'] ?? [];
                $destinationWarehouseId = $r['warehouse_id'] ?? '';
                
                // Kiểm tra Kho Tổng
                foreach ($details as $item) {
                  $productId = $item['product_id'] ?? '';
                  $requestedQty = (int)($item['quantity'] ?? 0);
                  $conversionFactor = (int)($item['conversion_factor'] ?? 1);
                  
                  // Tính số lượng cần (theo đơn vị cơ bản)
                  $neededQty = $requestedQty * $conversionFactor;
                  
                  // Lấy tồn kho trong Kho Tổng
                  $sourceWarehouseId = $r['source_warehouse_id'] ?? 'KHO_TONG_01';
                  $availableStock = $cInventory->getTotalStockByProduct($sourceWarehouseId, $productId);
                  
                  if ($availableStock < $neededQty) {
                    $isSufficient = false;
                    break;
                  }
                }
                
                // Nếu Kho Tổng không đủ, tìm các kho chi nhánh khác có đủ
                if (!$isSufficient) {
                  $sufficientBranchWarehouses = $cInventory->findSufficientWarehouses($details, $destinationWarehouseId);
                }
              }
              
              // Cập nhật status class và text
              $statusClass = 'approved';
              $statusText = 'Chờ kiểm tra kho';
              
              if ($status === 1) {
                $statusClass = 'approved';
                $statusText = 'Đã duyệt - Chờ kiểm tra';
              } elseif ($status === 3) {
                if ($isSufficient) {
                  $statusClass = 'confirmed';
                  $statusText = '✅ Đủ hàng';
                } else {
                  $statusClass = 'insufficient';
                  $statusText = '⚠️ Không đủ hàng';
                }
              } elseif ($status === 4) {
                $statusClass = 'insufficient';
                $statusText = '⚠️ Thiếu hàng';
              } elseif ($status === 5) {
                $statusClass = 'assigned';
                $statusText = '📦 Đã chỉ định kho';
              } elseif ($status === 6) {
                $statusClass = 'completed';
                $statusText = '✅ Hoàn tất';
              }

              $priority = $r['priority'] ?? 'normal';
              $priorityClass = $priority === 'urgent' ? 'urgent' : 'normal';
              $priorityText = $priority === 'urgent' ? '🔴 KHẨN CẤP' : '🟢 Bình thường';

              $created_date = 'N/A';
              if (isset($r['created_at'])) {
                  if ($r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                      $created_date = date('d/m/Y H:i', $r['created_at']->toDateTime()->getTimestamp());
                  } else {
                      $created_date = date('d/m/Y H:i', strtotime($r['created_at']));
                  }
              }

              $totalProducts = count($r['details'] ?? []);

              echo "
                <tr>
                  <td>$stt</td>
                  <td>{$r['transaction_id']}</td>
                  <td><span class='status $priorityClass'>$priorityText</span></td>
                  <td>$created_date</td>
                  <td>{$r['warehouse_id']}</td>
                  <td>".($r['creator_name'] ?? $r['created_by'])."</td>
                  <td><span class='status $statusClass'>$statusText</span></td>
                  <td>$totalProducts</td>
                  <td>
                    <a href='index.php?page=goodsReceiptRequest/detail&id={$r['transaction_id']}' class='btn btn-view' title='Xem chi tiết'>
                      <i class='fa-solid fa-eye'></i>
                    </a>
              ";
              
              // Status 1: Chờ kiểm tra kho - Tự động kiểm tra và hiển thị nút phù hợp
              if ($status === 1) {
                if ($isSufficient) {
                  echo "
                    <a href='goodsReceiptRequest/checkStock.php?id={$r['transaction_id']}' class='btn btn-confirm' title='Xác nhận đủ hàng'>
                      <i class='fa-solid fa-check-circle'></i> Đủ hàng
                    </a>
                  ";
                } else {
                  echo "
                    <a href='goodsReceiptRequest/checkStock.php?action=insufficient&id={$r['transaction_id']}' class='btn btn-insufficient' title='Không đủ hàng - Cần chỉ định kho'>
                      <i class='fa-solid fa-exclamation-circle'></i> Không đủ
                    </a>
                  ";
                }
              }
              
              // Status 3: Đã xác nhận - Kiểm tra lại inventory và hiển thị nút phù hợp
              if ($status === 3) {
                if ($isSufficient) {
                  echo "
                    <a href='index.php?page=exportReceipt/create&request_id={$r['transaction_id']}' class='btn btn-convert' title='Tạo phiếu xuất kho'>
                      <i class='fa-solid fa-arrow-right'></i> Tạo phiếu xuất
                    </a>
                  ";
                } else if (!empty($sufficientBranchWarehouses)) {
                  // Có kho chi nhánh khác có đủ hàng
                  $warehouseCount = count($sufficientBranchWarehouses);
                  echo "
                    <a href='index.php?page=goodsReceiptRequest/assign&id={$r['transaction_id']}' class='btn btn-assign' title='Có $warehouseCount kho chi nhánh có đủ hàng'>
                      <i class='fa-solid fa-warehouse'></i> Chỉ định kho ($warehouseCount)
                    </a>
                  ";
                } else {
                  // Không có kho nào đủ hàng
                  echo "
                    <span class='btn' style='background:#dc3545;color:#fff;cursor:not-allowed;' title='Không có kho nào đủ hàng'>
                      <i class='fa-solid fa-ban'></i> Không có kho
                    </span>
                  ";
                }
              }

              // Status 4: Thiếu hàng - Cho phép chỉ định kho khác
              if ($status === 4) {
                if (!empty($sufficientBranchWarehouses)) {
                  $warehouseCount = count($sufficientBranchWarehouses);
                  echo "
                    <a href='index.php?page=goodsReceiptRequest/assign&id={$r['transaction_id']}' class='btn btn-assign' title='Có $warehouseCount kho chi nhánh có đủ hàng'>
                      <i class='fa-solid fa-warehouse'></i> Chỉ định kho ($warehouseCount)
                    </a>
                  ";
                } else {
                  echo "
                    <span class='btn' style='background:#dc3545;color:#fff;cursor:not-allowed;' title='Không có kho nào đủ hàng'>
                      <i class='fa-solid fa-ban'></i> Không có kho
                    </span>
                  ";
                }
              }

              echo "</td></tr>";
              $stt++;
            }
            ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="alert alert-info">
          <i class="fa-solid fa-info-circle"></i> Không có yêu cầu nào chờ xử lý.
        </div>
      <?php endif; ?>
    </div>

    <!-- Tab 2: Đã chỉ định -->
    <div id="tab-assigned" class="tab-content">
      <h3><i class="fa-solid fa-share"></i> Phiếu đã chỉ định cho kho khác</h3>
      
      <?php if (count($assignedRequests) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>STT</th>
              <th>Mã phiếu</th>
              <th>Mức độ</th>
              <th>Kho yêu cầu</th>
              <th>Ngày chỉ định</th>
              <th>Người chỉ định</th>
              <th>Số SP</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $stt = 1;
            foreach ($assignedRequests as $r) {
              $priority = $r['priority'] ?? 'normal';
              $priorityClass = $priority === 'urgent' ? 'urgent' : 'normal';
              $priorityText = $priority === 'urgent' ? '🔴 KHẨN CẤP' : '🟢 Bình thường';

              $assigned_date = 'N/A';
              if (isset($r['assigned_at'])) {
                  if ($r['assigned_at'] instanceof MongoDB\BSON\UTCDateTime) {
                      $assigned_date = date('d/m/Y H:i', $r['assigned_at']->toDateTime()->getTimestamp());
                  } else {
                      $assigned_date = date('d/m/Y H:i', strtotime($r['assigned_at']));
                  }
              }

              $totalProducts = count($r['details'] ?? []);

              echo "
                <tr>
                  <td>$stt</td>
                  <td>{$r['transaction_id']}</td>
                  <td><span class='status $priorityClass'>$priorityText</span></td>
                  <td>{$r['warehouse_id']}</td>
                  <td>$assigned_date</td>
                  <td>".($r['assigned_by'] ?? 'N/A')."</td>
                  <td>$totalProducts</td>
                  <td>
                    <a href='index.php?page=goodsReceiptRequest/detail&id={$r['transaction_id']}' class='btn btn-view' title='Xem chi tiết'>
                      <i class='fa-solid fa-eye'></i> Xem
                    </a>
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
          <i class="fa-solid fa-info-circle"></i> Chưa có phiếu nào được chỉ định.
        </div>
      <?php endif; ?>
    </div>

    <!-- Tab 3: Phiếu của Kho Tổng -->
    <div id="tab-my-requests" class="tab-content">
      <?php 
      // Hiển thị danh sách phiếu của kho tổng (code tương tự view chi nhánh bên dưới)
      $requests = $myRequests;
      include(__DIR__ . '/partials/request-list-table.php');
      ?>
    </div>

  <?php else: ?>
    <!-- ========== VIEW CHO CHI NHÁNH / NHÂN VIÊN ========== -->
  <div class="top-actions">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Danh sách phiếu yêu cầu nhập hàng</h2>

    <div style="display:flex;gap:10px;align-items:center;">
      <div class="filters">
        <select id="filter-status">
          <option value="">Lọc theo trạng thái</option>
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt</option>
          <option value="rejected">Từ chối</option>
          <option value="confirmed">Xác nhận đủ hàng</option>
          <option value="insufficient">Không đủ hàng</option>
          <option value="assigned">Đã chỉ định kho</option>
          <option value="completed">Hoàn tất</option>
        </select>

        <select id="filter-priority">
          <option value="">Lọc theo mức độ</option>
          <option value="normal">Bình thường</option>
          <option value="urgent">Khẩn cấp</option>
        </select>
      </div>

      <a href="index.php?page=goodsReceiptRequest/createReceipt" class="btn btn-create">
        <i class="fa-solid fa-plus"></i> Tạo phiếu yêu cầu
      </a>
    </div>
  </div>

  <?php
  // Hiển thị thông báo flash message
  if (isset($_SESSION['flash_request'])) {
    echo '<div style="padding:10px;background:#e6ffed;border:1px solid #b7f0c6;margin-bottom:12px;color:#256029;">'.htmlspecialchars($_SESSION['flash_request']).'</div>';
    unset($_SESSION['flash_request']);
  }
  if (isset($_SESSION['flash_request_error'])) {
    echo '<div style="padding:10px;background:#ffecec;border:1px solid #f5c2c2;margin-bottom:12px;color:#8a1f1f;">'.htmlspecialchars($_SESSION['flash_request_error']).'</div>';
    unset($_SESSION['flash_request_error']);
  }
  ?>

  <table id="request-table">
    <thead>
      <tr>
        <th>STT</th>
        <th>Mã phiếu</th>
        <th>Mức độ</th>
        <th>Ngày tạo</th>
        <th>Người tạo</th>
        <th>Kho yêu cầu</th>
        <th>Kho nguồn</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      if ($requests && count($requests) > 0) {
        $stt = 1;
        foreach ($requests as $r) {
          $status = (int)($r['status'] ?? 0);
          switch ($status) {
            case 0: $class='pending'; $text='Chờ duyệt'; break;
            case 1: $class='approved'; $text='Đã duyệt'; break;
            case 2: $class='rejected'; $text='Từ chối'; break;
            case 3: $class='confirmed'; $text='Đủ hàng'; break;
            case 4: $class='insufficient'; $text='Không đủ hàng'; break;
            case 5: $class='assigned'; $text='Đã chỉ định kho'; break;
            case 6: $class='completed'; $text='Hoàn tất'; break;
            default: $class='pending'; $text='Không xác định';
          }

          $priority = $r['priority'] ?? 'normal';
          $priorityClass = $priority === 'urgent' ? 'urgent' : 'normal';
          $priorityText = $priority === 'urgent' ? '🔴 KHẨN CẤP' : '🟢 Bình thường';

          $created_date = 'N/A';
          if (isset($r['created_at'])) {
              if ($r['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                  $created_date = date('d/m/Y H:i', $r['created_at']->toDateTime()->getTimestamp());
              } else {
                  $created_date = date('d/m/Y H:i', strtotime($r['created_at']));
              }
          }

          $sourceWarehouse = $r['assigned_warehouse_id'] ?? $r['source_warehouse_id'];

          echo "
            <tr data-status='$class' data-priority='$priority'>
              <td>$stt</td>
              <td>{$r['transaction_id']}</td>
              <td><span class='status $priorityClass'>$priorityText</span></td>
              <td>$created_date</td>
              <td>".($r['creator_name'] ?? $r['created_by'])."</td>
              <td>{$r['warehouse_id']}</td>
              <td>$sourceWarehouse</td>
              <td><span class='status $class'>$text</span></td>
              <td>
                <a href='index.php?page=goodsReceiptRequest/detail&id={$r['transaction_id']}' class='btn btn-view' title='Xem chi tiết'>
                  <i class='fa-solid fa-eye'></i> Xem
                </a>
          ";

          // Nếu là quản lý và phiếu đang chờ duyệt
          if ($status === 0 && $isManager) {
            echo "
              <a href='goodsReceiptRequest/approve.php?action=approve&id={$r['transaction_id']}' class='btn btn-approve' onclick='return confirm(\"Bạn có chắc chắn muốn duyệt phiếu yêu cầu này?\");'><i class=\"fa-solid fa-check\"></i></a>
              <a href='goodsReceiptRequest/approve.php?action=reject&id={$r['transaction_id']}' class='btn btn-reject' onclick='return confirm(\"Bạn có chắc chắn muốn từ chối phiếu yêu cầu này?\");'><i class=\"fa-solid fa-xmark\"></i></a>
            ";
          }

          // Nếu là quản lý và phiếu đã duyệt hoặc đã xác nhận đủ hàng - cho phép chuyển thành phiếu nhập
          if (in_array($status, [3, 5]) && $isManager) {
            echo "
              <a href='createReceipt/process.php?action=convert&id={$r['transaction_id']}' class='btn btn-convert' onclick='return confirm(\"Bạn có chắc chắn muốn chuyển thành phiếu nhập hàng?\");'>
                <i class=\"fa-solid fa-file-export\"></i> Tạo phiếu nhập
              </a>
            ";
          }

          echo "</td></tr>";
          $stt++;
        }
      } else {
        echo "<tr><td colspan='10'>Không có phiếu yêu cầu nhập hàng nào.</td></tr>";
      } ?>
    </tbody>
  </table>
</div>

<script>
  const statusFilter = document.getElementById('filter-status');
  const priorityFilter = document.getElementById('filter-priority');
  const rows = document.querySelectorAll('#request-table tbody tr');

  function applyFilters() {
    const selectedStatus = statusFilter.value;
    const selectedPriority = priorityFilter.value;

    rows.forEach(row => {
      const rowStatus = row.getAttribute('data-status');
      const rowPriority = row.getAttribute('data-priority');

      const matchStatus = !selectedStatus || rowStatus === selectedStatus;
      const matchPriority = !selectedPriority || rowPriority === selectedPriority;

      row.style.display = (matchStatus && matchPriority) ? '' : 'none';
    });
  }

  statusFilter.addEventListener('change', applyFilters);
  priorityFilter.addEventListener('change', applyFilters);

  // ⭐ Function để chuyển tab cho Kho Tổng
  function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
      tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
      tab.classList.remove('active');
    });

    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.closest('.tab').classList.add('active');
  }
</script>

<?php endif; ?>