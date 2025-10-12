<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . "/../../../../controller/cWarehouse.php");
$cWarehouse = new CWarehouse();

$warehouses = $cWarehouse->getAllWarehouses();
?>

<style>
  .warehouse-list-container {
    max-width: 1200px;
    margin: 30px auto;
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
  }

  .warehouse-list-container h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
  }

  .warehouse-list-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
  }

  .warehouse-list-container th,
  .warehouse-list-container td {
    padding: 10px 12px;
    border: 1px solid #e1e4e8;
    text-align: center;
    font-size: 14px;
  }

  .warehouse-list-container th {
    background: #f9fafb;
  }

  .warehouse-list-container tr:hover {
    background: #f1f7ff;
  }

  .warehouse-list-container .btn {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
  }

  .warehouse-list-container .btn-edit {
    background: #17a2b8;
    color: #fff;
  }

  .warehouse-list-container .btn-delete {
    background: #dc3545;
    color: #fff;
  }

  .warehouse-list-container .btn:hover {
    opacity: 0.9;
  }

  .warehouse-list-container .status {
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-block;
  }

  .warehouse-list-container .active {
    background: #d4edda;
    color: #155724;
  }

  .warehouse-list-container .inactive {
    background: #f8d7da;
    color: #721c24;
  }

  .warehouse-list-container .type-main {
    background: #fde68a;
    color: #92400e;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
  }

  .warehouse-list-container .type-branch {
    background: #e0f2fe;
    color: #1e3a8a;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
  }

  .warehouse-list-container .top-actions {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .warehouse-list-container .btn-create {
    background: #007bff;
    color: #fff;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 8px;
  }

  .warehouse-list-container .btn-create:hover {
    background: #0056b3;
  }

  .filters {
    display: flex;
    gap: 10px;
    align-items: center;
  }

  .filters input {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
  }

  .filters select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
  }

  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
  }

  .modal-content {
    position: relative;
    z-index: 1001;
  }

  .modal-overlay {
    position: fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background: rgba(0,0,0,0.4);
    z-index: 1000;
  }
</style>

<div class="warehouse-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-warehouse"></i> Danh sách kho</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên kho...">
      <select id="filter-type">
        <option value="">Lọc theo loại kho</option>
        <option value="Tổng">Kho tổng</option>
        <option value="Chi nhánh">Kho chi nhánh</option>
      </select>
      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="Đang hoạt động">Đang hoạt động</option>
        <option value="Ngừng hoạt động">Ngừng hoạt động</option>
      </select>
      <a href="index.php?page=warehouse/createWarehouse" class="btn-create"><i class="fa-solid fa-plus"></i> Thêm kho</a>
    </div>
  </div>

  <table id="warehouse-table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Mã kho</th>
        <th>Tên kho</th>
        <th>Địa chỉ</th>
        <th>Loại kho</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if (is_array($warehouses) && !empty($warehouses)) {
        foreach ($warehouses as $w) {
          $statusText = $w['status'] == 1 ? 'Đang hoạt động' : 'Ngừng hoạt động';
          $statusClass = $w['status'] == 1 ? 'active' : 'inactive';

          $typeName = $w['type_name'] ?? '';
          $typeClass = (mb_strtolower($typeName, 'UTF-8') === 'tổng' || str_contains(mb_strtolower($typeName, 'UTF-8'), 'tong')) 
            ? 'type-main' : 'type-branch';

          echo "
            <tr data-type='{$typeName}' data-status='{$statusText}' data-name='{$w['warehouse_name']}'>
              <td>{$w['id']}</td>
              <td>{$w['warehouse_id']}</td>
              <td>{$w['warehouse_name']}</td>
              <td>{$w['address']}</td>
              <td><span class='{$typeClass}'>{$typeName}</span></td>
              <td><span class='status {$statusClass}'>{$statusText}</span></td>
              <td>
                <a href='index.php?page=warehouse/updateWarehouse&id={$w['warehouse_id']}' class='btn btn-edit'>
                  <i class='fa-solid fa-pen'></i>
                </a>
                <a href='#' class='btn btn-delete' data-id='{$w['warehouse_id']}'>
                  <i class='fa-solid fa-trash'></i>
                </a>
              </td>
            </tr>
          ";
        }
      } else {
        echo "<tr><td colspan='7'>Không có kho nào.</td></tr>";
      }
      ?>
    </tbody>
  </table>

  <div style="margin-top:20px;">
    <a href="index.php?page=manage" class="btn-create" style="background:#6b7280;">⬅ Quay lại</a>
  </div>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
    <h3 style="margin-top:0;">Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa kho này?</p>
    <div style="margin-top:20px;">
      <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
      <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
    </div>
  </div>
  <div class="modal-overlay"></div>
</div>

<script>
  // --- Bộ lọc và tìm kiếm ---
  const searchInput = document.getElementById('searchInput');
  const typeFilter = document.getElementById('filter-type');
  const statusFilter = document.getElementById('filter-status');
  const rows = document.querySelectorAll('#warehouse-table tbody tr');

  function applyFilters() {
    const searchValue = searchInput.value.toLowerCase();
    const typeValue = typeFilter.value.toLowerCase();
    const statusValue = statusFilter.value.toLowerCase();

    rows.forEach(row => {
      const name = row.getAttribute('data-name').toLowerCase();
      const type = row.getAttribute('data-type').toLowerCase();
      const status = row.getAttribute('data-status').toLowerCase();

      const matchName = !searchValue || name.includes(searchValue);
      const matchType = !typeValue || type.includes(typeValue);
      const matchStatus = !statusValue || status.includes(statusValue);

      row.style.display = (matchName && matchType && matchStatus) ? '' : 'none';
    });
  }

  [searchInput, typeFilter, statusFilter].forEach(el => {
    el.addEventListener('input', applyFilters);
    el.addEventListener('change', applyFilters);
  });

  // --- Modal Xóa ---
  const deleteModal = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  let deleteWarehouseId = null;

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      deleteWarehouseId = this.dataset.id;
      deleteModal.style.display = 'block';
    });
  });

  cancelBtn.addEventListener('click', function(){
    deleteModal.style.display = 'none';
    deleteWarehouseId = null;
  });

  confirmDeleteBtn.addEventListener('click', function(){
    if(deleteWarehouseId){
      fetch(`../../../view/page/manage/warehouse/deleteWarehouse/deleteWarehouse.php?id=` + deleteWarehouseId)
        .then(response => response.text())
        .then(() => {
          deleteModal.style.display = 'none';
          window.location.reload();
        })
        .catch(err => console.error('Lỗi xóa kho:', err));
    }
  });
</script>
