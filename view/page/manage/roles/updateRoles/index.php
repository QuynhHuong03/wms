<?php
include_once(__DIR__ . "/../../../../../controller/cRoles.php");

$cRoles = new CRoles();
$role = null;

if (isset($_GET['id'])) {
    $roleId = $_GET['id'];
    $role = $cRoles->getRoleById($roleId);
}

if (!$role) {
    echo "<script>
        alert('Không tìm thấy vai trò.');
        window.location.href = '../index.php?page=roles';
    </script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật vai trò</title>
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
.form-group input, .form-group textarea { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb }
.form-group input:focus, .form-group textarea:focus { border-color:#2563eb; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.12) }
.form-group textarea { resize: vertical; min-height: 100px; font-family: inherit; }
.error-message { font-size:0.85rem; color:#ef4444; margin-top:6px; display:block }
.form-actions { text-align:right; margin-top:30px; display:flex; justify-content:flex-end; gap:12px }
.form-actions a, .form-actions button { padding:12px 20px; border-radius:8px; font-weight:600 }
.form-actions a { text-decoration: none; }
.form-actions a:hover { text-decoration: none; }
.btn-success { background:#10b981; color:#fff; border:none; cursor:pointer; }
.btn-success:hover { background:#059669 }
.btn-secondary { background:#e5e7eb; color:#374151; border:none; cursor:pointer; }
.btn-secondary:hover { background:#d1d5db; }

/* Modal */
.modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
.modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:30px; border-radius:12px; text-align:center }
.modal-actions { display:flex; justify-content:center; gap:12px }
.modal-actions button { padding:10px 22px; border-radius:8px; font-weight:600; cursor:pointer; }

    </style>
</head>
<body>

  <div class="page-header">
    <h2>Cập nhật vai trò</h2>
    <p>Chỉnh sửa thông tin vai trò trong hệ thống</p>
  </div>

  <div class="container">
    <form action="roles/updateRoles/process.php" method="post">
        <input type="hidden" name="role_id" value="<?php echo htmlspecialchars($role['role_id']); ?>">
        
        <div class="form-group">
            <label for="role_name">Tên vai trò</label>
            <input type="text" id="role_name" name="role_name" placeholder="Nhập tên vai trò" value="<?php echo htmlspecialchars($role['role_name']); ?>">
            <span class="error-message"></span>
        </div>
        
        <div class="form-group">
            <label for="description">Mô tả</label>
            <textarea id="description" name="description" placeholder="Nhập mô tả vai trò"><?php echo htmlspecialchars($role['description']); ?></textarea>
            <span class="error-message"></span>
        </div>
        
        <div class="form-actions">
            <a href="index.php?page=roles" class="btn-secondary">Quay lại</a>
            <button type="reset" class="btn-secondary">Hủy</button>
            <button type="submit" name="btnUpdate" class="btn-success">Cập nhật</button>
        </div>
    </form>
  </div>

  <!-- Modal xác nhận cập nhật -->
  <div id="confirmModal" class="modal">
    <div class="modal-content">
      <h3>Xác nhận cập nhật</h3>
      <p>Bạn có chắc chắn muốn cập nhật thông tin vai trò này không?</p>
      <div class="modal-actions">
        <button type="button" id="cancelModalBtn" class="btn-secondary">Hủy</button>
        <button type="button" id="confirmUpdateBtn" class="btn-success">Xác nhận</button>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.querySelector('form');
  const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
  const saveBtn = form.querySelector("button[name='btnUpdate']");
  const confirmModal = document.getElementById('confirmModal');
  const confirmUpdateBtn = document.getElementById('confirmUpdateBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  let isConfirmed = false;

  inputs.forEach(f=> f.dataset.touched = 'false');

  function validateField(field){
    const val = field.value.trim();
    const group = field.closest('.form-group');
    if (!group) return true;
    const err = group.querySelector('.error-message');
    if (field.dataset.touched === 'false') { err.innerText = ''; return true; }
    let valid = true;
    
    if (field.id === 'role_name' && val === '') { 
      err.innerText = 'Vui lòng nhập tên vai trò'; 
      return false; 
    }
    if (field.id === 'role_name' && val.length < 2) { 
      err.innerText = 'Tên vai trò phải có ít nhất 2 ký tự'; 
      valid = false; 
    }
    if (field.id === 'description' && val === '') { 
      err.innerText = 'Vui lòng nhập mô tả'; 
      return false; 
    }
    if (field.id === 'description' && val.length < 5) { 
      err.innerText = 'Mô tả phải có ít nhất 5 ký tự'; 
      valid = false; 
    }
    
    if (valid) err.innerText='';
    return valid;
  }

  function validateForm(){
    let ok = true; 
    inputs.forEach(f=> { if (!validateField(f)) ok=false });
    return ok;
  }

  inputs.forEach(field=>{
    field.addEventListener('input', ()=>{ field.dataset.touched='true'; validateField(field); validateForm(); });
    field.addEventListener('blur', ()=>{ field.dataset.touched='true'; validateField(field); validateForm(); });
  });

  form.addEventListener('submit', function(e){
    if (!validateForm()) { e.preventDefault(); return false }
    if (!isConfirmed) { e.preventDefault(); confirmModal.style.display='block'; return false }
  });

  confirmUpdateBtn.addEventListener('click', function(){ isConfirmed=true; confirmModal.style.display='none'; form.submit(); });
  cancelModalBtn.addEventListener('click', function(){ confirmModal.style.display='none'; isConfirmed=false });
  window.addEventListener('click', function(ev){ if (ev.target === confirmModal) { confirmModal.style.display='none'; isConfirmed=false } });

});
</script>

</body>
</html>



