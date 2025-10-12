<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once("../../../controller/cSupplier.php");
$cSupplier = new CSupplier();

$suppliers = $cSupplier->getAllSuppliers();

?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Quản lý nhà cung cấp</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f9fafb;
      margin: 0;
      padding: 0;
    }

    .supplier-container {
      max-width: 1200px;
      margin: 30px auto;
      background: #fff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 3px 15px rgba(0,0,0,0.08);
    }

    .supplier-container h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #333;
    }

    .top-actions {
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
    }

    .filters {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .filters input, .filters select {
      padding: 6px 10px;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 14px;
    }

    .btn-create {
      background: #007bff;
      color: #fff;
      text-decoration: none;
      padding: 8px 14px;
      border-radius: 8px;
      transition: 0.3s;
    }

    .btn-create:hover {
      background: #0056b3;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }

    th, td {
      border: 1px solid #e1e4e8;
      padding: 10px 12px;
      text-align: center;
      font-size: 14px;
    }

    th {
      background: #f9fafb;
    }

    tr:hover {
      background: #f1f7ff;
    }

    .btn {
      border: none;
      padding: 6px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      text-decoration: none;
      display: inline-block;
    }

    .btn-edit {
      background: #17a2b8;
      color: #fff;
    }

    .btn-delete {
      background: #dc3545;
      color: #fff;
    }

    .btn:hover {
      opacity: 0.9;
    }

    .status {
      font-weight: 600;
      padding: 6px 10px;
      border-radius: 8px;
      display: inline-block;
    }

    .active {
      background: #d4edda;
      color: #155724;
    }

    .inactive {
      background: #f8d7da;
      color: #721c24;
    }

    /* Modal */
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
</head>
<body>
  <div class="supplier-container">
    <div class="top-actions">
      <h2><i class="fa-solid fa-truck-field"></i> Danh sách nhà cung cấp</h2>
      <div class="filters">
        <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên...">
        <select id="filter-status">
          <option value="">Lọc theo trạng thái</option>
          <option value="active">Đang hoạt động</option>
          <option value="inactive">Ngừng hoạt động</option>
        </select>
        <a href="index.php?page=supplier/createSupplier" class="btn-create">
          <i class="fa-solid fa-plus"></i> Thêm nhà cung cấp
        </a>
      </div>
    </div>

    <table id="supplier-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Tên nhà cung cấp</th>
          <th>Liên hệ</th>
          <th>Tên người liên hệ</th>
          <th>Mã số thuế</th>
          <th>Quốc gia</th>
          <th>Mô tả</th>
          <th>Trạng thái</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if (is_array($suppliers) && !empty($suppliers)) {
          foreach ($suppliers as $supplier) {
            $id = $supplier['supplier_id'];
            $name = $supplier['supplier_name'];
            $contact = $supplier['contact'] ?? '';
            $contact_name = $supplier['contact_name'] ?? '';
            $tax_code = $supplier['tax_code'] ?? '';
            $country = $supplier['country'] ?? '';
            $description = $supplier['description'] ?? '';
            $isActive = isset($supplier['status']) && $supplier['status'] == 1;
            $statusText = $isActive ? 'Đang hoạt động' : 'Ngừng hoạt động';
            $statusClass = $isActive ? 'active' : 'inactive';

            echo "
              <tr data-status='" . ($isActive ? "active" : "inactive") . "'>
                <td>{$id}</td>
                <td>{$name}</td>
                <td>{$contact}</td>
                <td>{$contact_name}</td>
                <td>{$tax_code}</td>
                <td>{$country}</td>
                <td>{$description}</td>
                <td><span class='status {$statusClass}'>{$statusText}</span></td>
                <td>
                  <a href='index.php?page=supplier/updateSupplier&id={$id}' class='btn btn-edit'>
                    <i class='fa-solid fa-pen'></i>
                  </a>
                  <a href='#' class='btn btn-delete' data-id='{$id}'>
                    <i class='fa-solid fa-trash'></i>
                  </a>
                </td>
              </tr>
            ";
          }
        } else {
          echo "<tr><td colspan='9'>Không có nhà cung cấp nào.</td></tr>";
        }
        ?>
      </tbody>
    </table>

    <div style="padding-top: 20px; text-align:center;">
      <a href='index.php?page=quanly' class='btn-create' style='background:#6c757d;'>
        <i class='fa-solid fa-arrow-left'></i> Quay lại
      </a>
    </div>
  </div>

  <!-- Modal xác nhận xóa -->
  <div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
      <h3 style="margin-top:0;">Xác nhận xóa</h3>
      <p>Bạn có chắc chắn muốn xóa nhà cung cấp này?</p>
      <div style="margin-top:20px;">
        <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
        <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
      </div>
    </div>
    <div class="modal-overlay"></div>
  </div>

  <script>
    // --- Bộ lọc & tìm kiếm ---
    const statusFilter = document.getElementById('filter-status');
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('#supplier-table tbody tr');

    function applyFilters() {
      const statusValue = statusFilter.value.toLowerCase();
      const searchValue = searchInput.value.toLowerCase();

      rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status').toLowerCase();
        const rowName = row.children[1].textContent.toLowerCase();
        const matchStatus = !statusValue || rowStatus.includes(statusValue);
        const matchName = !searchValue || rowName.includes(searchValue);
        row.style.display = (matchStatus && matchName) ? '' : 'none';
      });
    }

    statusFilter.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);

    // --- Modal xóa ---
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    let deleteId = null;

    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function(e){
        e.preventDefault();
        deleteId = this.dataset.id;
        deleteModal.style.display = 'block';
      });
    });

    cancelBtn.addEventListener('click', function(){
      deleteModal.style.display = 'none';
      deleteId = null;
    });

    confirmDeleteBtn.addEventListener('click', function(){
      if(deleteId){
        fetch(`../../../view/page/manage/supplier/deleteSupplier/index.php?id=${deleteId}`)
          .then(response => response.text())
          .then(() => {
            deleteModal.style.display = 'none';
            window.location.reload();
          })
          .catch(err => console.error('Lỗi xóa nhà cung cấp:', err));
      }
    });
  </script>
</body>
</html>
