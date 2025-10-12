<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../../../../controller/cCategories.php');
$p = new CCategories();
$tblCategory = $p->getAllCategories();
?>

<style>
  .category-list-container {
    max-width: 1200px;
    margin: 30px auto;
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
  }

  .category-list-container h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
  }

  .category-list-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
  }

  .category-list-container th,
  .category-list-container td {
    padding: 10px 12px;
    border: 1px solid #e1e4e8;
    text-align: center;
    font-size: 14px;
  }

  .category-list-container th {
    background: #f9fafb;
  }

  .category-list-container tr:hover {
    background: #f1f7ff;
  }

  .category-list-container .btn {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
  }

  .category-list-container .btn-edit {
    background: #17a2b8;
    color: #fff;
  }

  .category-list-container .btn-delete {
    background: #dc3545;
    color: #fff;
  }

  .category-list-container .btn:hover {
    opacity: 0.9;
  }

  .category-list-container .status {
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-block;
  }

  .category-list-container .active {
    background: #d4edda;
    color: #155724;
  }

  .category-list-container .inactive {
    background: #f8d7da;
    color: #721c24;
  }

  .category-list-container .top-actions {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .category-list-container .btn-create {
    background: #007bff;
    color: #fff;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 8px;
  }

  .category-list-container .btn-create:hover {
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

<div class="category-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-list"></i> Quản lý danh mục sản phẩm</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm danh mục...">
      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="1">Hoạt động</option>
        <option value="0">Ngừng hoạt động</option>
      </select>
      <a href="index.php?page=categories/createCategories" class="btn-create"><i class="fa-solid fa-plus"></i> Thêm danh mục</a>
    </div>
  </div>

  <table id="category-table">
    <thead>
      <tr>
        <th>Mã danh mục</th>
        <th>Tên danh mục</th>
        <th>Mã code</th>
        <th>Mô tả</th>
        <th>Trạng thái</th>
        <th>Ngày tạo</th>
        <th>Ngày cập nhật</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if ($tblCategory && is_array($tblCategory)) {
        foreach ($tblCategory as $c) {
          $id = $c['category_id'] ?? ($c['_id'] ?? '');
          $statusClass = $c['status'] == 1 ? 'active' : 'inactive';
          $statusText = $c['status'] == 1 ? 'Hoạt động' : 'Ngừng hoạt động';

          $createDate = '-';
          if (!empty($c['create_at'])) {
              if (is_array($c['create_at']) && isset($c['create_at']['$numberLong'])) {
                  $timestamp = (int) ($c['create_at']['$numberLong'] / 1000);
                  $createDate = date('Y-m-d H:i:s', $timestamp);
              } elseif ($c['create_at'] instanceof MongoDB\BSON\UTCDateTime) {
                  $createDate = $c['create_at']->toDateTime()->format('Y-m-d H:i:s');
              }
          }

          $updateDate = '-';
          if (!empty($c['update_at'])) {
              if (is_array($c['update_at']) && isset($c['update_at']['$numberLong'])) {
                  $timestamp = (int) ($c['update_at']['$numberLong'] / 1000);
                  $updateDate = date('Y-m-d H:i:s', $timestamp);
              } elseif ($c['update_at'] instanceof MongoDB\BSON\UTCDateTime) {
                  $updateDate = $c['update_at']->toDateTime()->format('Y-m-d H:i:s');
              }
          }

          echo "
          <tr data-status='{$c['status']}'>
            <td>{$id}</td>
            <td>{$c['category_name']}</td>
            <td>{$c['category_code']}</td>
            <td>{$c['description']}</td>
            <td><span class='status {$statusClass}'>{$statusText}</span></td>
            <td>{$createDate}</td>
            <td>{$updateDate}</td>
            <td>
              <a href='index.php?page=categories/updateCategories&id={$id}' class='btn btn-edit'>
                <i class='fa-solid fa-pen'></i>
              </a>
              <a href='#' class='btn btn-delete' data-id='{$id}'>
                <i class='fa-solid fa-trash'></i>
              </a>
            </td>
          </tr>";
        }
      } else {
        echo "<tr><td colspan='8'>Không có danh mục nào.</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
    <h3 style="margin-top:0;">Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa danh mục này?</p>
    <div style="margin-top:20px;">
      <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
      <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
    </div>
  </div>
  <div class="modal-overlay"></div>
</div>

<script>
  // --- Bộ lọc và tìm kiếm ---
  const statusFilter = document.getElementById('filter-status');
  const searchInput = document.getElementById('searchInput');
  const rows = document.querySelectorAll('#category-table tbody tr');

  function applyFilters() {
    const statusValue = statusFilter.value;
    const searchValue = searchInput.value.toLowerCase();

    rows.forEach(row => {
      const rowStatus = row.getAttribute('data-status');
      const rowName = row.children[1].textContent.toLowerCase();
      const matchStatus = !statusValue || rowStatus === statusValue;
      const matchName = !searchValue || rowName.includes(searchValue);
      row.style.display = (matchStatus && matchName) ? '' : 'none';
    });
  }

  statusFilter.addEventListener('change', applyFilters);
  searchInput.addEventListener('input', applyFilters);

  // --- Modal Xóa ---
  const deleteModal = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  let deleteCategoryId = null;

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      deleteCategoryId = this.dataset.id;
      deleteModal.style.display = 'block';
    });
  });

  cancelBtn.addEventListener('click', function(){
    deleteModal.style.display = 'none';
    deleteCategoryId = null;
  });

  confirmDeleteBtn.addEventListener('click', function(){
    if(deleteCategoryId){
      fetch('categories/deleteCategories/process.php?id=' + deleteCategoryId)
        .then(response => response.text())
        .then(result => {
          deleteModal.style.display = 'none';
          if(result.trim() === "success"){
            window.location.reload();
          } else {
            alert("Không thể xóa danh mục!");
          }
        })
        .catch(err => console.error('Lỗi xóa category:', err));
    }
  });
</script>
