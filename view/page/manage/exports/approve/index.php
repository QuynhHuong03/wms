<?php
/**
 * Danh sách phiếu xuất kho cần duyệt (cho chi nhánh)
 * View: approve/index.php
 */

if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../../model/connect.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

$cWarehouse = new CWarehouse();

// Lấy thông tin người dùng từ session
$user_id = $_SESSION['login']['user_id'] ?? 'U001';
$role = $_SESSION['login']['role'] ?? 'staff';
$role_name = $_SESSION['login']['role_name'] ?? '';
$role_id = (int)($_SESSION['login']['role_id'] ?? 0);
$warehouse_id = $_SESSION['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? null);

// ⚠️ PHÂN QUYỀN: Quản lý kho được duyệt phiếu xuất ĐẾN kho của mình
// role_id = 2: QL_Kho_Tong - duyệt phiếu đến kho tổng
// role_id = 4: QL_Kho_CN - duyệt phiếu đến kho chi nhánh
$allowedRoleIds = [2, 4];
$isManager = in_array($role_id, $allowedRoleIds);

if (!$isManager) {
    echo '<div style="max-width:800px;margin:50px auto;padding:30px;background:#fff3cd;border:2px solid #ffc107;border-radius:12px;text-align:center;">';
    echo '<h2 style="color:#856404;margin-bottom:20px;"><i class="fa-solid fa-ban"></i> Không có quyền truy cập</h2>';
    echo '<p style="font-size:16px;color:#856404;margin-bottom:20px;">Chỉ <strong>Quản lý kho</strong> mới được duyệt phiếu xuất kho.</p>';
    echo '<p style="color:#6c757d;">Vai trò của bạn: <strong>' . htmlspecialchars($role_name) . '</strong> (ID: ' . $role_id . ')</p>';
    echo '<p style="color:#0c5460;font-size:14px;margin-top:15px;">💡 <strong>Lưu ý:</strong> Bạn chỉ có thể duyệt các phiếu xuất <strong>GỬI ĐẾN</strong> kho mà bạn quản lý.</p>';
    echo '<a href="../../index.php" style="display:inline-block;margin-top:20px;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:6px;"><i class="fa fa-arrow-left"></i> Quay lại trang chủ</a>';
    echo '</div>';
    exit;
}

// ⚠️ Kiểm tra warehouse_id trước khi query
if (empty($warehouse_id)) {
    error_log("⚠️ EXPORTS APPROVE - User $user_id has no warehouse_id assigned");
} else {
    error_log("📦 EXPORTS APPROVE - Loading exports for warehouse: $warehouse_id (User: $user_id, Role: $role_id)");
}

// Kết nối MongoDB để lấy phiếu xuất
$p = new clsKetNoi();
$con = $p->moKetNoi();
$exports = [];

if ($con && !empty($warehouse_id)) {
    $transactionsCol = $con->selectCollection('transactions');
    
    // ✅ CHỈ LẤY phiếu xuất GỬI ĐẾN kho của user hiện tại
    // destination_warehouse_id phải ĐÚNG BẰNG warehouse_id của user
    $filter = [
        'transaction_type' => 'export',
        'destination_warehouse_id' => $warehouse_id, // ⭐ Quan trọng: chỉ lấy phiếu đến kho này
        'status' => 1, // Status 1 = Đã xuất kho (chờ chi nhánh duyệt nhận hàng)
        'inventory_deducted' => true // Chỉ lấy phiếu đã trừ kho
    ];
    
    error_log("🔍 Filter: " . json_encode($filter));
    
    // Aggregation để join với users
    $pipeline = [
        ['$match' => $filter],
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
        error_log("✅ Found " . count($exports) . " exports for warehouse $warehouse_id");
        
        // Debug: Log chi tiết các phiếu tìm được
        foreach ($exports as $idx => $exp) {
            $tid = $exp['transaction_id'] ?? 'N/A';
            $dest = $exp['destination_warehouse_id'] ?? 'N/A';
            error_log("  - Export #" . ($idx+1) . ": $tid → Dest: $dest");
        }
    } catch (Exception $e) {
        error_log("❌ Error fetching exports for approval: " . $e->getMessage());
    }
}

// Format status
function getStatusBadge($status) {
    switch ($status) {
        case 0: return '<span class="badge bg-warning text-dark">Chờ xác nhận</span>';
        case 1: return '<span class="badge bg-primary">Đã xuất kho</span>'; // Chờ chi nhánh duyệt
        case 2: return '<span class="badge bg-success">Đã giao hàng</span>';
        case 3: return '<span class="badge bg-danger">Từ chối</span>';
        default: return '<span class="badge bg-secondary">N/A</span>';
    }
}
?>

<style>
  .exports-approve-container {max-width:1600px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 3px 15px rgba(0,0,0,0.08);}
  .exports-approve-container h2 {text-align:center;margin-bottom:25px;color:#333;}
  .exports-approve-container table {width:100%;border-collapse:collapse;margin-top:20px;}
  .exports-approve-container th,.exports-approve-container td {padding:12px;border:1px solid #e1e4e8;text-align:center;font-size:14px;}
  .exports-approve-container th {background:#f8f9fa;font-weight:600;}
  .exports-approve-container tr:hover {background:#f1f7ff;}
  .exports-approve-container .btn {border:none;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block;margin:2px;}
  .exports-approve-container .btn-view {background:#17a2b8;color:#fff;}
  .exports-approve-container .btn-approve {background:#28a745;color:#fff;}
  .exports-approve-container .btn-reject {background:#dc3545;color:#fff;}
  .exports-approve-container .btn:hover {opacity:0.85;transform:translateY(-1px);}
  .alert {padding:12px;margin-bottom:15px;border-radius:8px;}
  .alert-info {background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}
  .alert-warning {background:#fff3cd;color:#856404;border:1px solid #ffeaa7;}
</style>

<div class="exports-approve-container">
  <h2>
    <i class="fa-solid fa-clipboard-check"></i> Phiếu Xuất Kho Chờ Duyệt
  </h2>

  <?php if (empty($warehouse_id)): ?>
    <div class="alert alert-warning">
      <strong>⚠️ Cảnh báo:</strong> Bạn chưa được gán kho. Vui lòng liên hệ quản trị viên để được phân công kho quản lý.<br>
      <small style="color:#856404;">User ID: <?= htmlspecialchars($user_id) ?> | Role: <?= htmlspecialchars($role_name) ?> (<?= $role_id ?>)</small>
    </div>
  <?php elseif (empty($exports)): ?>
    <div class="alert alert-info">
      <strong>ℹ️ Thông báo:</strong> Không có phiếu xuất kho nào chờ duyệt tại kho <strong><?= htmlspecialchars($warehouse_id) ?></strong>.
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      Có <strong><?= count($exports) ?></strong> phiếu xuất kho chờ duyệt <strong>GỬI ĐẾN</strong> kho <strong><?= htmlspecialchars($warehouse_id) ?></strong> của bạn.<br>
      <small style="color:#0c5460;">Chỉ hiển thị các phiếu có destination_warehouse_id = <?= htmlspecialchars($warehouse_id) ?></small>
    </div>

    <table>
      <thead>
        <tr>
          <th>STT</th>
          <th>Mã phiếu xuất</th>
          <th>Từ kho</th>
          <th>Đến kho</th>
          <th>Người tạo</th>
          <th>Ngày tạo</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $stt = 1;
        foreach ($exports as $export): 
          $exportId = $export['transaction_id'] ?? 'N/A';
          $sourceWarehouse = $export['warehouse_id'] ?? 'N/A';
          $destWarehouse = $export['destination_warehouse_id'] ?? 'N/A';
          $creatorName = $export['creator_name'] ?? 'N/A';
          $createdAt = isset($export['created_at']) ? 
            date('d/m/Y H:i', $export['created_at']->toDateTime()->getTimestamp()) : 
            'N/A';
          $status = (int)($export['status'] ?? 0);
          $inventoryDeducted = $export['inventory_deducted'] ?? false;
        ?>
        <tr>
          <td><?= $stt++ ?></td>
          <td><strong><?= htmlspecialchars($exportId) ?></strong></td>
          <td><?= htmlspecialchars($sourceWarehouse) ?></td>
          <td><?= htmlspecialchars($destWarehouse) ?></td>
          <td><?= htmlspecialchars($creatorName) ?></td>
          <td><?= htmlspecialchars($createdAt) ?></td>
          <td><?= getStatusBadge($status) ?></td>
          <td>
            <a href="?page=exports/approve/detail&id=<?= urlencode($exportId) ?>" 
               class="btn btn-view" title="Xem chi tiết">
              <i class="fa-solid fa-eye"></i> Xem
            </a>
            <?php if ($isManager && $status == 1 && $inventoryDeducted): ?>
            <button onclick="approveExport('<?= htmlspecialchars($exportId) ?>')" 
                    class="btn btn-approve" title="Duyệt nhận hàng">
              <i class="fa-solid fa-check"></i> Duyệt
            </button>
            <button onclick="rejectExport('<?= htmlspecialchars($exportId) ?>')" 
                    class="btn btn-reject" title="Từ chối nhận hàng">
              <i class="fa-solid fa-xmark"></i> Từ chối
            </button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
function approveExport(exportId) {
  if (!confirm(`Xác nhận duyệt phiếu xuất ${exportId}?\n\nHàng sẽ được thêm vào kho của bạn.`)) {
    return;
  }
  
  fetch('../exports/process.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=approve&export_id=${encodeURIComponent(exportId)}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('✅ ' + data.message);
      location.reload();
    } else {
      alert('❌ ' + data.message);
    }
  })
  .catch(err => {
    alert('❌ Lỗi: ' + err.message);
  });
}

function rejectExport(exportId) {
  const reason = prompt(`Lý do từ chối phiếu xuất ${exportId}:`);
  if (!reason) return;
  
  fetch('../exports/process.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=reject&export_id=${encodeURIComponent(exportId)}&reason=${encodeURIComponent(reason)}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('✅ ' + data.message);
      location.reload();
    } else {
      alert('❌ ' + data.message);
    }
  })
  .catch(err => {
    alert('❌ Lỗi: ' + err.message);
  });
}
</script>
