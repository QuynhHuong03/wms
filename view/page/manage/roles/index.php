<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once(__DIR__ . '/../../../../controller/cRoles.php');
$p = new CRoles();

// Lấy danh sách vai trò
if (isset($_POST['btnTK']) && !empty($_POST['txtTK'])) {
    $tblRoles = $p->getAllRolesByName($_POST['txtTK']);
} else {
    $tblRoles = $p->getAllRoles();
}
?>

<style>
/* Reused styles from users list for consistent UI */
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7fa; color: #333; }
.user-list-container { max-width: 1000px; margin: 30px auto; background: #ffffff; padding: 10px 10px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
.user-list-container h2 { text-align: left; margin-bottom: 0; font-size: 1.6rem; color: #1f2937; font-weight: 700; }
.top-actions { margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; padding-bottom: 15px; border-bottom: 1px solid #e5e7eb; }
.filters { display: flex; gap: 10px; align-items: center; }
.filters input, .filters select { padding: 10px 14px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 0.9rem; transition: border-color 0.3s; background-color: #f9fafb; }
.filters input:focus, .filters select:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1); }
.btn-create { background: #2563eb; color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; transition: background-color 0.3s, transform 0.1s; }
.btn-create:hover { background: #1e40af; transform: translateY(-1px); }
.user-list-container table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 0; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.user-list-container th, .user-list-container td { padding: 12px 15px; border-bottom: 1px solid #e5e7eb; text-align: left; font-size: 0.95rem; }
.user-list-container td:last-child { text-align: center; }
.user-list-container th:last-child { text-align: center; }
.user-list-container th { background: #f9fafb; color: #4b5563; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; }
.user-list-container tbody tr:hover { background: #f7faff; transition: background-color 0.2s; }
.btn { border: none; padding: 8px 10px; border-radius: 8px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; }
.btn-edit { background: #3b82f6; color: #fff; }
.btn-delete { background: #ef4444; color: #fff; }
.btn:hover { opacity: 1; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transform: translateY(-1px); }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.4); }
.modal-content { background: #fff; max-width: 400px; margin: 15vh auto; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); text-align: center; animation: fadeIn 0.3s; }
.modal-content h3 { color: #1f2937; margin-bottom: 15px; }
.modal-content p { color: #6b7280; margin-bottom: 25px; }
#cancelBtn, #confirmDeleteBtn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: background-color 0.2s; }
#cancelBtn { background: #e5e7eb; color: #374151; }
#confirmDeleteBtn { background: #ef4444; color: white; }
@keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
.toast-notification { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; font-weight: 600; z-index: 10000; animation: slideIn 0.3s ease-out; }
.toast-notification.error { background: #ef4444; }
@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
.toast-notification.hide { animation: slideOut 0.3s ease-out forwards; }
</style>

<div class="user-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-user-shield"></i> Quản lý vai trò</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên vai trò...">
      <!-- <select id="filter-role">
        <option value="">Lọc theo...</option>
        <option value="active">Tất cả</option>
      </select> -->
      <a href="index.php?page=roles/createRoles" class="btn-create"><i class="fa-solid fa-plus"></i> Thêm vai trò</a>
    </div>
  </div>

  <table id="role-table">
    <thead>
      <tr>
        <th>Mã vai trò</th>
        <th>Tên vai trò</th>
        <th>Mô tả</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php
if ($tblRoles && is_array($tblRoles)) {
  foreach ($tblRoles as $r) {
    $roleNameRaw = $r['role_name'] ?? '';
    $roleName = trim(mb_strtolower($roleNameRaw, 'UTF-8'));
    echo "\n      <tr data-role='" . htmlspecialchars($roleName, ENT_QUOTES) . "'>\n        <td>" . htmlspecialchars($r['role_id']) . "</td>\n        <td>" . htmlspecialchars($roleNameRaw) . "</td>\n        <td>" . htmlspecialchars($r['description'] ?? '') . "</td>\n        <td>\n          <a href='index.php?page=roles/updateRoles&id=" . urlencode($r['role_id']) . "' class='btn btn-edit' title='Sửa'>\n            <i class='fa-solid fa-pen'></i>\n          </a>\n          <a href='#' class='btn btn-delete' data-id='" . htmlspecialchars($r['role_id'], ENT_QUOTES) . "'>\n            <i class='fa-solid fa-trash'></i>\n          </a>\n        </td>\n      </tr>\n    ";
  }
} else {
  echo "<tr><td colspan='4'>Không có vai trò nào.</td></tr>";
}
?>

    </tbody>
  </table>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
    <h3 style="margin-top:0;">Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa vai trò này?</p>
    <div style="margin-top:20px;">
      <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
      <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
    </div>
  </div>
  <div class="modal-overlay"></div>
</div>

<script>
  // Thông báo toast từ query param
  const urlParams = new URLSearchParams(window.location.search);
  const msg = urlParams.get('msg');
  if (msg === 'success' || msg === 'updated' || msg === 'deleted' || msg === 'error') {
      const toast = document.createElement('div');
      toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : 'success');
      if (msg === 'success') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thêm vai trò thành công!';
      else if (msg === 'updated') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cập nhật vai trò thành công!';
      else if (msg === 'deleted') toast.innerHTML = '<i class="fa-solid fa-trash-can"></i> Xóa vai trò thành công!';
      else toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
      document.body.appendChild(toast);
      setTimeout(() => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 300); }, 3000);
      const newUrl = window.location.pathname + '?page=roles';
      window.history.replaceState({}, '', newUrl);
  }

  // Bộ lọc và tìm kiếm client-side
  const roleFilter = document.getElementById('filter-role');
  const searchInput = document.getElementById('searchInput');
  const rows = document.querySelectorAll('#role-table tbody tr');

  function applyFilters() {
    const roleValue = roleFilter.value.toLowerCase();
    const searchValue = searchInput.value.toLowerCase();
    rows.forEach(row => {
      const rowRole = (row.getAttribute('data-role') || '').toLowerCase();
      const rowName = row.children[1].textContent.toLowerCase();
      const matchRole = !roleValue || rowRole.includes(roleValue);
      const matchName = !searchValue || rowName.includes(searchValue);
      row.style.display = (matchRole && matchName) ? '' : 'none';
    });
  }
  roleFilter.addEventListener('change', applyFilters);
  searchInput.addEventListener('input', applyFilters);

  // Modal Xóa
  const deleteModal = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  let deleteRoleId = null;

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e){
      e.preventDefault();
      deleteRoleId = this.dataset.id;
      deleteModal.style.display = 'block';
    });
  });

  cancelBtn.addEventListener('click', function(){ deleteModal.style.display = 'none'; deleteRoleId = null; });

  confirmDeleteBtn.addEventListener('click', function(){
    if (deleteRoleId) {
      fetch('/view/page/manage/roles/deleteRoles/deleteRoles.php?id=' + encodeURIComponent(deleteRoleId))
        .then(res => res.json().catch(() => res.text()))
        .then(data => {
          deleteModal.style.display = 'none';
          if (typeof data === 'object' && data.success) {
            window.location.href = '/view/page/manage/index.php?page=roles&msg=deleted';
          } else if (typeof data === 'string' && data.trim().length > 0) {
            // fallback: assume success if non-empty text returned
            window.location.href = '/view/page/manage/index.php?page=roles&msg=deleted';
          } else {
            const errToast = document.createElement('div');
            errToast.className = 'toast-notification error';
            errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Xóa vai trò thất bại!';
            document.body.appendChild(errToast);
            setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
          }
        })
        .catch(err => {
          deleteModal.style.display = 'none';
          console.error('Lỗi xóa vai trò:', err);
          const errToast = document.createElement('div');
          errToast.className = 'toast-notification error';
          errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối khi xóa.';
          document.body.appendChild(errToast);
          setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
        });
    }
  });
</script>

