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

if ($con) {
    $transactionsCol = $con->selectCollection('transactions');
    
    // Lấy danh sách phiếu xuất
    $filter = ['transaction_type' => 'export'];
    
    // Nếu là chi nhánh, chỉ xem phiếu xuất từ kho của mình
    if (!$isWarehouseMain && $warehouse_id) {
        $filter['source_warehouse_id'] = $warehouse_id;
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
  .stat-card {flex:1;min-width:200px;padding:15px;border-radius:8px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;}
  .stat-card h3 {margin:0 0 8px 0;font-size:16px;opacity:0.9;}
  .stat-card .number {font-size:32px;font-weight:700;}
  .stat-card.green {background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%);}
  .stat-card.orange {background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);}
  .stat-card.blue {background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);}
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
      <input type="text" id="filter-search" placeholder="🔍 Tìm mã phiếu, kho..." style="width:250px;">
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
          <th>Người xuất</th>
          <th>Số SP</th>
          <th>Ghi chú</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $stt = 1;
        foreach ($exports as $export) {
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
          $note = $export['note'] ?? '';
          $noteShort = mb_strlen($note) > 30 ? mb_substr($note, 0, 30) . '...' : $note;

          echo "
            <tr data-date='$created_date_sort' data-search='$exportId $requestId $sourceWarehouseId $destinationWarehouseId $sourceWarehouseName $destinationWarehouseName'>
              <td>$stt</td>
              <td><strong style='color:#0366d6;'>$exportId</strong></td>
              <td>
                <a href='index.php?page=goodsReceiptRequest/detail&id=$requestId' style='color:#28a745;text-decoration:none;font-weight:600;'>
                  $requestId <i class='fa-solid fa-external-link-alt' style='font-size:11px;'></i>
                </a>
              </td>
              <td>
                <span class='warehouse-badge'>
                  <i class='fa-solid fa-warehouse'></i> $sourceWarehouseName
                </span>
              </td>
              <td>
                <span class='warehouse-badge' style='background:#e8fde8;color:#28a745;'>
                  <i class='fa-solid fa-location-dot'></i> $destinationWarehouseName
                </span>
              </td>
              <td>$created_date</td>
              <td>$creatorName</td>
              <td><span class='product-count'>$totalProducts SP</span></td>
              <td title='".htmlspecialchars($note)."' style='max-width:200px;text-align:left;'>".htmlspecialchars($noteShort)."</td>
              <td>
                <a href='index.php?page=exports/detail&id=$exportId' class='btn btn-view' title='Xem chi tiết'>
                  <i class='fa-solid fa-eye'></i>
                </a>
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
</div>

<script>
  function filterTable() {
    const searchValue = document.getElementById('filter-search').value.toLowerCase();
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;
    const rows = document.querySelectorAll('#export-table tbody tr');

    rows.forEach(row => {
      const searchText = row.getAttribute('data-search').toLowerCase();
      const rowDate = row.getAttribute('data-date');

      const matchSearch = !searchValue || searchText.includes(searchValue);
      const matchDateFrom = !dateFrom || rowDate >= dateFrom;
      const matchDateTo = !dateTo || rowDate <= dateTo;

      row.style.display = (matchSearch && matchDateFrom && matchDateTo) ? '' : 'none';
    });
  }

  function resetFilter() {
    document.getElementById('filter-search').value = '';
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

  // Tự động lọc khi gõ
  document.getElementById('filter-search')?.addEventListener('input', filterTable);
</script>

<?php
$p->dongKetNoi($con);
?>
