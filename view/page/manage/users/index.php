<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../controller/cUsers.php');
$p = new CUsers();

// Lấy danh sách người dùng
if (isset($_POST['btnTK']) && !empty($_POST['txtTK'])) {
    $tblNV = $p->getAllNVbyName($_POST['txtTK']);
} else {
    $tblNV = $p->getAllUsers();
}
?>

<style>
  .user-list-container {
    max-width: 1200px;
    margin: 30px auto;
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
  }

  .user-list-container h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
  }

  .user-list-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
  }

  .user-list-container th,
  .user-list-container td {
    padding: 10px 12px;
    border: 1px solid #e1e4e8;
    text-align: center;
    font-size: 14px;
  }

  .user-list-container th {
    background: #f9fafb;
  }

  .user-list-container tr:hover {
    background: #f1f7ff;
  }

  .user-list-container .btn {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
  }

  .user-list-container .btn-edit {
    background: #17a2b8;
    color: #fff;
  }

  .user-list-container .btn-delete {
    background: #dc3545;
    color: #fff;
  }

  .user-list-container .btn:hover {
    opacity: 0.9;
  }

  .user-list-container .status {
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-block;
  }

  .user-list-container .working {
    background: #d4edda;
    color: #155724;
  }

  .user-list-container .left {
    background: #f8d7da;
    color: #721c24;
  }

  .user-list-container .role-admin {
    background: #fde68a;
    color: #92400e;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
  }

  .user-list-container .role-manager {
    background: #e0f2fe;
    color: #1e3a8a;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
  }

  .user-list-container .role-staff {
    background: #f3f4f6;
    color: #374151;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
  }

  .user-list-container .top-actions {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .user-list-container .btn-create {
    background: #007bff;
    color: #fff;
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 8px;
  }

  .user-list-container .btn-create:hover {
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

<div class="user-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-users"></i> Danh sách người dùng</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên...">
      <select id="filter-role">
        <option value="">Lọc theo vai trò</option>
        <option value="admin">Admin</option>
        <option value="manager">Quản lý</option>
        <option value="staff">Nhân viên</option>
      </select>
      <a href="index.php?page=users/createUsers" class="btn-create"><i class="fa-solid fa-plus"></i> Thêm người dùng</a>
    </div>
  </div>

  <table id="user-table">
    <thead>
      <tr>
        <th>Mã NV</th>
        <th>Họ và tên</th>
        <th>Email</th>
        <th>Giới tính</th>
        <th>SĐT</th>
        <th>Vai trò</th>
        <th>Trạng thái</th>
        <th>Kho</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php
if ($tblNV && is_array($tblNV)) {
  foreach ($tblNV as $r) {
    $statusClass = $r['status'] == 1 ? 'working' : 'left';
    $statusText = $r['status'] == 1 ? 'Đang làm việc' : 'Nghỉ việc';

    $roleNameRaw = $r['role_info']['role_name'] ?? $r['role_name'] ?? '';
    $roleName = trim(mb_strtolower($roleNameRaw, 'UTF-8'));

    $roleClass = 'role-staff';
    if ($roleName === 'admin') $roleClass = 'role-admin';
    elseif (in_array($roleName, ['quản lý','quan ly','manager'])) $roleClass = 'role-manager';

    echo "
      <tr data-role='$roleName'>
        <td>{$r['user_id']}</td>
        <td>{$r['name']}</td>
        <td>{$r['email']}</td>
        <td>" . ($r['gender'] == 1 ? 'Nam' : 'Nữ') . "</td>
        <td>{$r['phone']}</td>
        <td><span class='$roleClass'>{$roleNameRaw}</span></td>
        <td><span class='status $statusClass'>$statusText</span></td>
        <td>" . ($r['warehouse_id'] ?? '') . "</td>
        <td>
          <a href='index.php?page=users/updateUsers&id={$r['user_id']}' class='btn btn-edit'>
            <i class='fa-solid fa-pen'></i>
          </a>
          <a href='#' class='btn btn-delete' data-id='{$r['user_id']}'>
            <i class='fa-solid fa-trash'></i>
          </a>
        </td>
      </tr>
    ";
  }
} else {
  echo "<tr><td colspan='9'>Không có người dùng nào.</td></tr>";
}
?>

    </tbody>
  </table>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
    <h3 style="margin-top:0;">Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa người dùng này?</p>
    <div style="margin-top:20px;">
      <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
      <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
    </div>
  </div>
  <div class="modal-overlay"></div>
</div>

<script>
  // --- Bộ lọc vai trò và tìm kiếm ---
  const roleFilter = document.getElementById('filter-role');
  const searchInput = document.getElementById('searchInput');
  const rows = document.querySelectorAll('#user-table tbody tr');

  function applyFilters() {
    const roleValue = roleFilter.value.toLowerCase();
    const searchValue = searchInput.value.toLowerCase();

    rows.forEach(row => {
      const rowRole = row.getAttribute('data-role').toLowerCase();
      const rowName = row.children[1].textContent.toLowerCase();
      const matchRole = !roleValue || rowRole.includes(roleValue);
      const matchName = !searchValue || rowName.includes(searchValue);
      row.style.display = (matchRole && matchName) ? '' : 'none';
    });
  }

  roleFilter.addEventListener('change', applyFilters);
  searchInput.addEventListener('input', applyFilters);

  // --- Modal Xóa ---
  const deleteModal = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  let deleteUserId = null;

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      deleteUserId = this.dataset.id;
      deleteModal.style.display = 'block';
    });
  });

  cancelBtn.addEventListener('click', function(){
    deleteModal.style.display = 'none';
    deleteUserId = null;
  });

  confirmDeleteBtn.addEventListener('click', function(){
    if(deleteUserId){
      fetch('users/deleteUsers/process.php?id=' + deleteUserId)
        .then(response => response.text())
        .then(() => {
          deleteModal.style.display = 'none';
          window.location.reload();
        })
        .catch(err => console.error('Lỗi xóa user:', err));
    }
  });
</script>
