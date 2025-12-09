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
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7fa;
    color: #333;
}

.user-list-container {
    max-width: 1400px;
    margin: 30px auto;
    background: #ffffff;
    padding: 10px 10px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}

.user-list-container h2 {
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

.user-list-container table {
    width: 100%;
    border-collapse: separate; 
    border-spacing: 0;
    margin-top: 0;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden; 
}

.user-list-container th,
.user-list-container td {
    padding: 12px 15px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    font-size: 0.9rem;
}
.user-list-container td:last-child {
    text-align: center; 
}
.user-list-container th:last-child {
    text-align: center;
}

.user-list-container th {
    background: #f9fafb;
    color: #4b5563;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
}

.user-list-container tr:last-child td {
    border-bottom: none;
}

.user-list-container tbody tr:hover {
    background: #f7faff;
    transition: background-color 0.2s;
}

.status,
.role-tag {
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 20px;
    display: inline-block;
    font-size: 0.8rem;
}

.working {
    background-color: #d1fae5; 
    color: #065f46;
}
.left {
    background-color: #fee2e2; 
    color: #991b1b; 
}

.role-admin {
    background-color: #fef9c3;
    color: #a16207;
}
.role-manager {
    background-color: #dbeafe; 
    color: #1e40af;
}
.role-staff {
    background-color: #e5e7eb;
    color: #4b5563;
}

.user-list-container td:nth-child(6) span {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.btn-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.user-list-container .btn {
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

.user-list-container .btn-edit {
    background: #3b82f6;
    color: #fff;
}

.user-list-container .btn-delete {
    background: #ef4444;
    color: #fff;
}

.user-list-container .btn:hover {
    opacity: 1;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-1px);
}
.user-list-container .btn-edit:hover { background: #2563eb; }
.user-list-container .btn-delete:hover { background: #dc2626; }

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

.modal-content h3 {
    color: #1f2937;
    margin-bottom: 15px;
}

.modal-content p {
    color: #6b7280;
    margin-bottom: 25px;
}

#cancelBtn, #confirmDeleteBtn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s;
}

#cancelBtn {
    background: #e5e7eb;
    color: #374151;
}
#cancelBtn:hover {
    background: #d1d5db;
}

#confirmDeleteBtn {
    background: #ef4444;
    color: white;
}
#confirmDeleteBtn:hover {
    background: #dc2626;
}

@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@media (max-width: 768px) {
    .user-list-container {
        padding: 10px 10px;
        margin: 15px;
    }
    .top-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .filters {
        flex-wrap: wrap;
        justify-content: space-between;
    }
    .filters input, .filters select {
        flex-grow: 1;
        min-width: 45%;
    }
    .btn-create {
        width: 100%;
        text-align: center;
    }
    .user-list-container {
        overflow-x: auto;
    }
    .user-list-container table {
        min-width: 800px;
    }
}

/* Toast notification */
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

.toast-notification i {
    font-size: 1.2rem;
}

.toast-notification.success {
    background: #10b981;
}

.toast-notification.error {
    background: #ef4444;
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

.toast-notification.hide {
    animation: slideOut 0.3s ease-out forwards;
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
    // --- Hiển thị thông báo thành công/không thành công ---
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg === 'success' || msg === 'updated' || msg === 'deleted' || msg === 'error') {
        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : 'success');
        if (msg === 'success') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thêm người dùng thành công!';
        else if (msg === 'updated') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cập nhật người dùng thành công!';
        else if (msg === 'deleted') toast.innerHTML = '<i class="fa-solid fa-trash-can"></i> Xóa người dùng thành công!';
        else toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
        document.body.appendChild(toast);

        // Xóa thông báo sau 3 giây
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, 3000);

        // Xóa tham số msg khỏi URL (giữ `?page=users`)
        const newUrl = window.location.pathname + '?page=users';
        window.history.replaceState({}, '', newUrl);
    }

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
            fetch('/KLTN/view/page/manage/users/deleteUsers/process.php?id=' + encodeURIComponent(deleteUserId))
                .then(response => response.json())
                .then((data) => {
                    deleteModal.style.display = 'none';
                    if (data && data.success) {
                        // Redirect to users list with deleted message
                        window.location.href = '/KLTN/view/page/manage/index.php?page=users&msg=deleted';
                    } else {
                        // Show error toast
                        const errToast = document.createElement('div');
                        errToast.className = 'toast-notification error';
                        errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Xóa người dùng thất bại!';
                        document.body.appendChild(errToast);
                        setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
                    }
                })
                .catch(err => {
                    deleteModal.style.display = 'none';
                    console.error('Lỗi xóa user:', err);
                    const errToast = document.createElement('div');
                    errToast.className = 'toast-notification error';
                    errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối khi xóa.';
                    document.body.appendChild(errToast);
                    setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
                });
        }
    });
</script>
