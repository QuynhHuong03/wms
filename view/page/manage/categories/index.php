<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once(__DIR__ . '/../../../../controller/cCategories.php');
$p = new CCategories();
$tblCategory = $p->getAllCategories();
?>
<?php
// Server-side toast fallback: render a toast immediately if msg is present
$msg = $_GET['msg'] ?? '';
if (in_array($msg, ['success','updated','deleted','error'])) {
  $class = $msg === 'error' ? 'toast-notification error' : 'toast-notification';
  if ($msg === 'success') $text = '<i class="fa-solid fa-circle-check"></i> Thêm loại sản phẩm thành công!';
  elseif ($msg === 'updated') $text = '<i class="fa-solid fa-circle-check"></i> Cập nhật loại sản phẩm thành công!';
  elseif ($msg === 'deleted') $text = '<i class="fa-solid fa-trash-can"></i> Xóa loại sản phẩm thành công!';
  else $text = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
  echo "<div id=\"serverToast\" class=\"{$class}\">{$text}</div>";
  echo "<script>setTimeout(()=>{const t=document.getElementById('serverToast'); if(t){t.classList.add('hide'); setTimeout(()=>t.remove(),300);} const newUrl=window.location.pathname+'?page=categories'; window.history.replaceState({},'',newUrl);},3000);</script>";
}
?>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7fa;
    color: #333;
}

.category-list-container {
    max-width: 1200px;
    margin: 0 auto;
    background: #ffffff;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}

.category-list-container h2 {
    text-align: left;
    margin-bottom: 0;
    font-size: 1.6rem;
    color: #1f2937;
    font-weight: 700;
}

.top-actions {
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filters input,
.filters select {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 0.95rem;
    background-color: #f9fafb;
}
.filters input:focus, .filters select:focus { outline: none; box-shadow: 0 0 0 2px rgba(37,99,235,0.08); border-color:#2563eb; }

.btn-create { background: #2563eb; color: #fff; padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:600; }
.btn-create:hover { background:#1e40af }

.category-list-container table { width:100%; border-collapse:separate; border-spacing:0; margin-top:12px; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden }
.category-list-container th, .category-list-container td { padding:12px 14px; border-bottom:1px solid #e5e7eb; text-align:left; font-size:0.95rem }
.category-list-container th { background:#f9fafb; color:#4b5563; font-weight:600; text-transform:uppercase; font-size:0.8rem }
.category-list-container td:last-child { text-align:center }
.category-list-container tbody tr:hover { background:#f7faff }

.status { font-weight:600; padding:6px 10px; border-radius:18px; display:inline-block; font-size:0.85rem }
.active { background:#d1fae5; color:#065f46 }
.inactive { background:#fee2e2; color:#991b1b }

.btn { border:none; padding:8px 10px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center }
.btn-edit { background:#3b82f6; color:#fff }
.btn-delete { background:#ef4444; color:#fff }
.btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.08) }

/* Smaller action icons inside the categories list so both buttons sit on one row */
.category-list-container td a.btn {
  padding: 4px;           /* reduce internal padding */
  width: 28px;            /* fixed square to keep icons compact */
  height: 28px;
  border-radius: 6px;
  box-sizing: border-box;
}
.category-list-container td a.btn i {
  font-size: 14px;        /* slightly smaller icon */
  line-height: 1;
}

.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.4) }
.modal-content { background:#fff; max-width:400px; margin:15vh auto; padding:26px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.25); text-align:center }
.modal-content h3 { color:#1f2937; margin-bottom:12px }
.modal-content p { color:#6b7280; margin-bottom:18px }
#cancelBtn, #confirmDeleteBtn { padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer; border:none }
#cancelBtn { background:#e5e7eb; color:#374151 }
#confirmDeleteBtn { background:#ef4444; color:#fff }

.toast-notification { position:fixed; top:20px; right:20px; background:#10b981; color:#fff; padding:14px 20px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.12); display:flex; gap:10px; align-items:center; font-weight:600; z-index:10000; animation:slideIn 0.28s }
.toast-notification.error { background:#ef4444 }
.toast-notification.hide { animation:slideOut 0.3s forwards }
@keyframes slideIn { from { transform:translateX(400px); opacity:0 } to { transform:translateX(0); opacity:1 } }
@keyframes slideOut { from { transform:translateX(0); opacity:1 } to { transform:translateX(400px); opacity:0 } }

@media (max-width:768px) { .category-list-container { padding:10px } .top-actions { flex-direction:column; align-items:stretch } .filters { flex-wrap:wrap } .filters input, .filters select { flex-grow:1; min-width:45% } .btn-create { width:100% } .category-list-container table { min-width:720px } }
</style>

<div class="category-list-container">
  <div class="top-actions">
    <h2><i class="fa fa-list"></i> Quản lý loại sản phẩm</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên...">
      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="1">Hoạt động</option>
        <option value="0">Ngừng hoạt động</option>
      </select>
      <a href="index.php?page=categories/createCategories" class="btn-create"><i class="fa-solid fa-plus"></i> Thêm loại sản phẩm</a>
    </div>
  </div>

  <table id="category-table">
    <thead>
      <tr>
        <th>Mã loại</th>
        <th>Tên loại SP</th>
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
              <a href='index.php?page=categories/updateCategories&id={$id}' class='btn btn-edit' title='Chỉnh sửa'>
                <i class='fa-solid fa-pen'></i>
              </a>
              <a href='#' class='btn btn-delete' data-id='{$id}' title='Xóa'>
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
  <div class="modal-content">
    <h3>Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa danh mục này?</p>
    <div>
      <button id="cancelBtn">Hủy</button>
      <button id="confirmDeleteBtn">Xóa</button>
    </div>
  </div>
</div>

<script>
  // --- Hiển thị thông báo thành công/không thành công ---
  const urlParams = new URLSearchParams(window.location.search);
  const msg = urlParams.get('msg');
    if (msg === 'success' || msg === 'updated' || msg === 'deleted' || msg === 'error') {
      // If server already rendered a toast, don't create a duplicate
      if (!document.getElementById('serverToast')) {
        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : '');
        if (msg === 'success') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thêm loại sản phẩm thành công!';
        else if (msg === 'updated') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cập nhật loại sản phẩm thành công!';
        else if (msg === 'deleted') toast.innerHTML = '<i class="fa-solid fa-trash-can"></i> Xóa loại sản phẩm thành công!';
        else toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
        document.body.appendChild(toast);

        setTimeout(() => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 300); }, 3000);

        const newUrl = window.location.pathname + '?page=categories';
        window.history.replaceState({}, '', newUrl);
      }
    }

  // --- Bộ lọc trạng thái và tìm kiếm ---
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
          fetch('/view/page/manage/categories/deleteCategories/process.php?id=' + encodeURIComponent(deleteCategoryId))
              .then(response => response.json())
              .then((data) => {
                  deleteModal.style.display = 'none';
                  if (data && data.success) {
                      window.location.href = '/view/page/manage/index.php?page=categories&msg=deleted';
                  } else {
                      const errToast = document.createElement('div');
                      errToast.className = 'toast-notification error';
                      const errorMessage = data.message || 'Xóa loại sản phẩm thất bại!';
                      errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errorMessage;
                      document.body.appendChild(errToast);
                      setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
                  }
              })
              .catch(err => {
                  deleteModal.style.display = 'none';
                  console.error('Lỗi xóa category:', err);
                  const errToast = document.createElement('div');
                  errToast.className = 'toast-notification error';
                  errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối khi xóa.';
                  document.body.appendChild(errToast);
                  setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
              });
      }
  });
</script>
