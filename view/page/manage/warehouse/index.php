<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . "/../../../../controller/cWarehouse.php");
$cWarehouse = new CWarehouse();

$warehouses = $cWarehouse->getAllWarehouses();
?>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7fa;
    color: #333;
}

.warehouse-list-container {
    max-width: 1300px;
    margin: 30px auto;
    background: #ffffff;
    padding: 10px 10px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}

.warehouse-list-container h2 {
    text-align: left;
    margin-bottom: 0;
    font-size: 1.8rem;
    color: #1f2937;
    font-weight: 700;
}

.top-actions {
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filters input,
.filters select {
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 0.9rem;
    transition: border-color 0.3s;
    background-color: #f9fafb;
}
.filters input:focus,
.filters select:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.btn-create {
    background: #2563eb;
    color: #fff;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    transition: background-color 0.3s, transform 0.1s;
}
.btn-create:hover {
    background: #1e40af;
    transform: translateY(-1px);
}

.warehouse-list-container table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 0;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
}

.warehouse-list-container th,
.warehouse-list-container td {
    padding: 12px 15px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 0.9rem;
}
.warehouse-list-container td:last-child {
    text-align: center;
}
.warehouse-list-container th:last-child {
    text-align: center;
}

.warehouse-list-container th {
    background: #f9fafb;
    color: #4b5563;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.warehouse-list-container tbody tr:hover {
    background: #f7faff;
    transition: background-color 0.2s;
}

.status,
.type-tag {
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    display: inline-block;
    font-size: 0.8rem;
}
.active {
    background-color: #d1fae5;
    color: #065f46;
}
.inactive {
    background-color: #fee2e2;
    color: #991b1b;
}
.type-main { background-color: #fef9c3; color: #a16207;padding: 6px 12px; border-radius: 20px; }
.type-branch { background-color: #dbeafe; color: #1e40af; padding: 6px 12px; border-radius: 20px;}

.btn-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn {
    border: none;
    padding: 8px 10px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.btn-edit { background: #3b82f6; color: #fff; }
.btn-delete { background: #ef4444; color: #fff; }
.btn-edit:hover { background: #2563eb; }
.btn-delete:hover { background: #dc2626; }

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background: rgba(0,0,0,0.4);
}
.modal-content {
    background: #fff;
    max-width: 400px;
    margin: 15vh auto;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
    text-align: center;
    animation: fadeIn 0.3s;
}

#cancelBtn, #confirmDeleteBtn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s;
}
#cancelBtn { background: #e5e7eb; color: #374151; }
#confirmDeleteBtn { background: #ef4444; color: white; }

.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
}
.toast-notification.error { background: #ef4444; }
.toast-notification.hide { animation: slideOut 0.3s ease-out forwards; }

@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }

@media (max-width: 768px) {
    .warehouse-list-container { padding: 10px 10px; margin: 15px; }
    .top-actions { flex-direction: column; align-items: stretch; }
    .filters { flex-wrap: wrap; justify-content: space-between; }
    .filters input, .filters select { flex-grow: 1; min-width: 45%; }
    .btn-create { width: 100%; text-align: center; }
    .warehouse-list-container { overflow-x: auto; }
    .warehouse-list-container table { min-width: 800px; }
}
</style>

<div class="warehouse-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-warehouse"></i> Danh sách kho</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên...">
      <select id="filter-type">
        <option value="">Lọc theo loại</option>
        <option value="tổng">Kho tổng</option>
        <option value="chi nhánh">Kho chi nhánh</option>
      </select>
      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="đang hoạt động">Đang hoạt động</option>
        <option value="ngừng hoạt động">Ngừng hoạt động</option>
      </select>
      <a href="index.php?page=warehouse/createWarehouse" class="btn-create"><i class="fa-solid fa-plus"></i> Thêm kho</a>
    </div>
  </div>

  <table id="warehouse-table">
    <thead>
      <tr>
        <th>STT</th>
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
        $i = 1;
        foreach ($warehouses as $w) {
          $statusText = ($w['status'] ?? 0) == 1 ? 'Đang hoạt động' : 'Ngừng hoạt động';
          $statusClass = ($w['status'] ?? 0) == 1 ? 'active' : 'inactive';

          $typeName = $w['type_name'] ?? ($w['type'] ?? 'Không rõ');
          $typeLower = mb_strtolower($typeName, 'UTF-8');
          $typeClass = (mb_stripos($typeLower, 'tổng') !== false || mb_stripos($typeLower, 'tong') !== false) ? 'type-main' : 'type-branch';

          // Xử lý địa chỉ
          $addressText = '';
          if (isset($w['address']) && is_array($w['address'])) {
            $parts = [];
            if (!empty($w['address']['street'])) $parts[] = $w['address']['street'];
            $wardCity = $w['address']['ward'] ?? ($w['address']['city'] ?? '');
            if (!empty($wardCity)) $parts[] = $wardCity;
            if (!empty($w['address']['province'])) $parts[] = $w['address']['province'];
            $addressText = implode(', ', $parts);
          } else {
            $addressText = htmlspecialchars($w['address_text'] ?? '');
          }

          $safeName = htmlspecialchars($w['warehouse_name']);
          echo "
            <tr data-type='" . htmlspecialchars($typeLower) . "' data-status='" . mb_strtolower($statusText, 'UTF-8') . "' data-name='" . mb_strtolower($safeName, 'UTF-8') . "'>
              <td>{$i}</td>
              <td>{$w['warehouse_id']}</td>
              <td>{$safeName}</td>
              <td>{$addressText}</td>
              <td><span class='{$typeClass}'>" . htmlspecialchars($typeName) . "</span></td>
              <td><span class='status {$statusClass}'>{$statusText}</span></td>
              <td>
                <a href='index.php?page=warehouse/updateWarehouse&id={$w['warehouse_id']}' class='btn btn-edit'>
                  <i class='fa-solid fa-pen'></i>
                </a>
                <a href='#' class='btn btn-delete' data-id='" . htmlspecialchars($w['warehouse_id']) . "'>
                  <i class='fa-solid fa-trash'></i>
                </a>
              </td>
            </tr>
          ";
          $i++;
        }
      } else {
        echo "<tr><td colspan='7'>Không có kho nào.</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa kho này?</p>
    <div style="margin-top:20px;">
      <button id="cancelBtn">Hủy</button>
      <button id="confirmDeleteBtn">Xóa</button>
    </div>
  </div>
</div>

<script>
  // --- Hiển thị thông báo thành công/không thành công giống users page ---
  const urlParams = new URLSearchParams(window.location.search);
  const msg = urlParams.get('msg');
  if (msg === 'success' || msg === 'updated' || msg === 'deleted' || msg === 'error') {
      const toast = document.createElement('div');
      toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : '');
      if (msg === 'success') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thêm kho thành công!';
      else if (msg === 'updated') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cập nhật kho thành công!';
      else if (msg === 'deleted') toast.innerHTML = '<i class="fa-solid fa-trash-can"></i> Xóa kho thành công!';
      else toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
      document.body.appendChild(toast);

      setTimeout(() => {
          toast.classList.add('hide');
          setTimeout(() => toast.remove(), 300);
      }, 3000);

      const newUrl = window.location.pathname + '?page=warehouse';
      window.history.replaceState({}, '', newUrl);
  }

  // --- Bộ lọc loại + tìm kiếm ---
  const roleTypeFilter = document.getElementById('filter-type');
  const statusFilter = document.getElementById('filter-status');
  const searchInput = document.getElementById('searchInput');
  const rows = document.querySelectorAll('#warehouse-table tbody tr');

  function applyFilters() {
    const typeValue = (roleTypeFilter.value || '').toLowerCase();
    const statusValue = (statusFilter.value || '').toLowerCase();
    const searchValue = (searchInput.value || '').toLowerCase();

    rows.forEach(row => {
      const rowType = (row.getAttribute('data-type') || '').toLowerCase();
      const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
      const rowName = (row.getAttribute('data-name') || row.children[2].textContent || '').toLowerCase();

      const matchType = !typeValue || rowType.includes(typeValue);
      const matchStatus = !statusValue || rowStatus.includes(statusValue);
      const matchName = !searchValue || rowName.includes(searchValue);

      row.style.display = (matchType && matchStatus && matchName) ? '' : 'none';
    });
  }

  [roleTypeFilter, statusFilter].forEach(el => el.addEventListener('change', applyFilters));
  searchInput.addEventListener('input', applyFilters);

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
      fetch('/KLTN/view/page/manage/warehouse/deleteWarehouse/deleteWarehouse.php?id=' + encodeURIComponent(deleteWarehouseId))
        .then(response => response.json())
        .then((data) => {
          deleteModal.style.display = 'none';
          if (data && data.success) {
            window.location.href = '/KLTN/view/page/manage/index.php?page=warehouse&msg=deleted';
          } else {
            const errToast = document.createElement('div');
            errToast.className = 'toast-notification error';
            errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + (data.message || 'Xóa kho thất bại!');
            document.body.appendChild(errToast);
            setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
          }
        })
        .catch(err => {
          deleteModal.style.display = 'none';
          console.error('Lỗi xóa kho:', err);
          const errToast = document.createElement('div');
          errToast.className = 'toast-notification error';
          errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối khi xóa.';
          document.body.appendChild(errToast);
          setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
        });
    }
  });
</script>
