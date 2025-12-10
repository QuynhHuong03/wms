<?php
// session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật người dùng</title>

<?php // Reuse same styles as createUsers for consistent UI ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* --- BASE & TYPOGRAPHY --- */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f0f3f8;
    color: #1f2937;
    margin: 0;
    padding: 20px 0;
}

.page-header {
    width: 90%;
    max-width: 700px;
    margin: 30px auto 15px;
    padding-left: 10px;
    border-left: 4px solid #2563eb;
}
.page-header h2 { margin: 0; color: #111827; font-size: 2rem; font-weight:700 }
.page-header p { margin:5px 0 0; color:#6b7280 }

.container { width:90%; max-width:700px; margin:20px auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.08); padding:30px 40px }
.form-group { margin-bottom:20px }
.form-group label { display:block; margin-bottom:8px; font-weight:600; color:#374151 }
.form-group input, .form-group select { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb }
.form-group input:focus, .form-group select:focus { border-color:#2563eb; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.12) }
.error-message { font-size:0.85rem; color:#ef4444; margin-top:6px; display:block }
.password-wrapper { position:relative }
.toggle-password { position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:#9ca3af }
.form-actions { text-align:right; margin-top:30px; display:flex; justify-content:flex-end; gap:12px }
.form-actions a, .form-actions button { padding:12px 20px; border-radius:8px; font-weight:600 }
.form-actions a { text-decoration: none; }
.form-actions a:hover { text-decoration: none; }
.btn-success { background:#10b981; color:#fff; border:none }
.btn-success:hover { background:#059669 }
.btn-secondary { background:#e5e7eb; color:#374151; border:none }

/* Modal */
.modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
.modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:30px; border-radius:12px; text-align:center }
.modal-actions { display:flex; justify-content:center; gap:12px }
.modal-actions button { padding:10px 22px; border-radius:8px; font-weight:600 }

</style>
</head>
<body>
<?php
include_once("../../../controller/cUsers.php");
$id = $_GET['id'];
$p = new CUsers();
$user = $p->getUserById($id);
?>

  <div class="page-header">
    <h2>Cập nhật người dùng</h2>
    <p>Cập nhật tài khoản người dùng</p>
  </div>

  <div class="container">
  <form action="/view/page/manage/users/updateUsers/process.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="id" id="id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

      <div class="form-group">
        <label for="name">Họ và tên</label>
        <input type="text" id="name" name="name" placeholder="Nhập họ và tên" value="<?php echo htmlspecialchars($user['name']); ?>">
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Nhập email" value="<?php echo htmlspecialchars($user['email']); ?>">
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="gender">Giới tính</label>
        <select name="gender" id="gender">
          <option value="">- Chọn giới tính -</option>
          <option value="1" <?php echo (isset($user['gender']) && $user['gender']==1) ? 'selected' : ''; ?>>Nam</option>
          <option value="0" <?php echo (isset($user['gender']) && $user['gender']==0) ? 'selected' : ''; ?>>Nữ</option>
        </select>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="phone">Số điện thoại</label>
        <input type="text" id="phone" name="phone" placeholder="Nhập số điện thoại" value="<?php echo htmlspecialchars($user['phone']); ?>">
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="role_id">Vai trò</label>
        <select name="role_id" id="role_id">
          <option value="">- Chọn vai trò -</option>
          <?php
            include("../../../controller/cRoles.php");
            $obj = new CRoles();
            $tblRole = $obj->getAllRoles();
            if (is_array($tblRole) && count($tblRole)>0) {
              foreach ($tblRole as $r) {
                $value = isset($r['role_id']) ? $r['role_id'] : $r['_id'];
                $sel = (isset($user['role_id']) && $user['role_id'] == $value) ? 'selected' : '';
                echo '<option value="'.htmlspecialchars($value).'" '.$sel.'>'.htmlspecialchars($r['description'] ?? $r['role_name'] ?? '').'</option>';
              }
            } else {
              echo '<option value="">⚠ Không có dữ liệu vai trò</option>';
            }
          ?>
        </select>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="">- Chọn trạng thái -</option>
          <option value="1" <?php echo (isset($user['status']) && $user['status']==1) ? 'selected' : ''; ?>>Đang làm việc</option>
          <option value="2" <?php echo (isset($user['status']) && $user['status']==2) ? 'selected' : ''; ?>>Nghỉ việc</option>
        </select>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="warehouse_id">Kho làm việc</label>
        <select name="warehouse_id" id="warehouse_id">
          <option value="">- Chọn kho -</option>
          <?php
            include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");
            $Obj = new CWarehouse();
            $warehouses = $Obj->getAllWarehouses();
            if (!empty($warehouses)) {
              foreach ($warehouses as $r) {
                $sel = (isset($user['warehouse_id']) && $user['warehouse_id'] == $r['warehouse_id']) ? 'selected' : '';
                echo '<option value="'.htmlspecialchars($r['warehouse_id']).'" '.$sel.'>'.htmlspecialchars($r['warehouse_name']).'</option>';
              }
            } else {
              echo '<option value="">⚠ Không có dữ liệu kho</option>';
            }
          ?>
        </select>
        <span class="error-message"></span>
      </div>

      <div class="form-actions">
        <a href="/view/page/manage/index.php?page=users" class="btn-secondary">Quay lại</a>
        <button type="reset" class="btn-secondary">Hủy</button>
        <button type="submit" class="btn-success" name="btnUpdate">Cập nhật</button>
      </div>

    </form>
  </div>

  <!-- Modal xác nhận cập nhật -->
  <div id="confirmModal" class="modal">
    <div class="modal-content">
      <h3>Xác nhận cập nhật</h3>
      <p>Bạn có chắc chắn muốn cập nhật thông tin người dùng này không?</p>
      <div class="modal-actions">
        <button type="button" id="cancelModalBtn" class="btn-secondary">Hủy</button>
        <button type="button" id="confirmUpdateBtn" class="btn-success">Xác nhận</button>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.querySelector('form');
  const inputs = form.querySelectorAll('input:not([type="hidden"]), select');
  const saveBtn = form.querySelector("button[name='btnUpdate']");
  const confirmModal = document.getElementById('confirmModal');
  const confirmUpdateBtn = document.getElementById('confirmUpdateBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  let isConfirmed = false;

  // initial state: do not add visual 'is-disabled' class so button remains clickable
  // we'll control enabled state via validateForm() which toggles the class as needed
  inputs.forEach(f=> f.dataset.touched = 'false');

  function validateField(field){
    const val = field.value.trim();
    const group = field.closest('.form-group');
    if (!group) return true; // ignore inputs not wrapped in a form-group (e.g. hidden fields)
    const err = group.querySelector('.error-message');
    if (field.dataset.touched === 'false') { err.innerText = ''; return true; }
    let valid = true;
    if (['name','email','phone'].includes(field.id) && val === '') { err.innerText = 'Vui lòng nhập trường này'; return false; }
    if (field.id === 'name' && !/^[\p{L}\s]+$/u.test(val)) { err.innerText = 'Họ tên không hợp lệ'; valid=false }
    if (field.id === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { err.innerText='Email không hợp lệ'; valid=false }
    if (field.id === 'phone' && !/^[0-9]{10}$/.test(val)) { err.innerText='SĐT phải 10 số'; valid=false }
    if (['role_id','status','warehouse_id','gender'].includes(field.id) && val==='') { err.innerText='Vui lòng chọn mục này'; valid=false }
    if (valid) err.innerText='';
    return valid;
  }

  function validateForm(){
    let ok = true; inputs.forEach(f=> { if (!validateField(f)) ok=false });
    if (!ok) { saveBtn.classList.add('is-disabled'); saveBtn.setAttribute('aria-disabled','true'); }
    else { saveBtn.classList.remove('is-disabled'); saveBtn.removeAttribute('aria-disabled'); }
    return ok;
  }

  inputs.forEach(field=>{
    field.addEventListener('input', ()=>{ field.dataset.touched='true'; validateField(field); validateForm(); });
    field.addEventListener('blur', ()=>{ field.dataset.touched='true'; validateField(field); validateForm(); });
    field.addEventListener('change', ()=>{ field.dataset.touched='true'; validateField(field); validateForm(); });
  });

  form.addEventListener('submit', function(e){
    if (!validateForm()) { e.preventDefault(); return false }
    if (!isConfirmed) { e.preventDefault(); confirmModal.style.display='block'; return false }
  });

  confirmUpdateBtn.addEventListener('click', function(){ isConfirmed=true; confirmModal.style.display='none'; saveBtn.click(); });
  cancelModalBtn.addEventListener('click', function(){ confirmModal.style.display='none'; isConfirmed=false });
  window.addEventListener('click', function(ev){ if (ev.target === confirmModal) { confirmModal.style.display='none'; isConfirmed=false } });

});
</script>



</body>
</html>
