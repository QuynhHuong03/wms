<?php
// Show generated category_id automatically
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
include_once(__DIR__ . '/../../../../../controller/cCategories.php');
$cCat = new CCategories();
$nextCategoryId = $cCat->getNextCategoryId();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* --- Match styling from users/createUsers --- */
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
.page-header h2 { margin:0; color:#111827; font-size:2rem; font-weight:700 }
.page-header p { margin:5px 0 0; color:#6b7280; font-size:1rem }

.container {
    width: 90%;
    max-width: 700px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    padding: 30px 40px;
}

.form-group { margin-bottom: 20px }
.form-group label { display:block; margin-bottom:8px; font-weight:600; font-size:1rem; color:#374151 }
.form-group input, .form-group select, .form-group textarea { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; color:#374151; background:#f9fafb; box-sizing:border-box }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:#2563eb; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.15) }
.error-message { font-size:0.85rem; color:#ef4444; margin-top:6px; display:block; font-weight:500 }

.form-actions { text-align:right; margin-top:30px; display:flex; justify-content:flex-end; gap:12px }
.form-actions button, .form-actions a { padding:12px 25px; font-size:1rem; border:none; border-radius:8px; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; font-weight:600 }
.form-actions .btn-success { background:#10b981; color:#fff }
.form-actions .btn-success:hover { background:#059669; box-shadow:0 4px 10px rgba(16,185,129,0.4) }
.form-actions .btn-secondary { background:#9ca3af; color:#fff }
.form-actions a { background:#e5e7eb; color:#4b5563 }

/* Modal */
.modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.5); animation:fadeIn .3s }
.modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:35px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center }
.modal-actions { display:flex; gap:12px; justify-content:center }
/* Modal buttons: clearer border, padding, and hover states */
#cancelModalBtn {
  background: #ffffff;
  color: #374151;
  border: 1px solid #cbd5e1;
  padding: 10px 18px;
  border-radius: 8px;
}
#cancelModalBtn:hover {
  background: #f3f4f6;
}
#confirmAddBtn {
  background: #10b981;
  color: #fff;
  border: 1px solid #059669;
  padding: 10px 18px;
  border-radius: 8px;
}
#confirmAddBtn:hover {
  background: #059669;
  box-shadow: 0 6px 18px rgba(5,150,105,0.18);
}

@keyframes fadeIn { from { opacity:0 } to { opacity:1 } }

/* Ensure buttons visible */
form .form-actions, .container .form-actions { display:flex !important; justify-content:flex-end !important; gap:12px !important }
form .form-actions button, form .form-actions a { display:inline-flex !important; align-items:center !important; justify-content:center !important }
</style>

<div class="page-header">
  <h2>Thêm loại sản phẩm</h2>
  <p>Tạo mới loại sản phẩm</p>
</div>

<div class="container">
  <form action="categories/createCategories/process.php" method="post" onsubmit="return validateForm()">

    <!-- Mã danh mục -->
    <div class="form-group">
      <label for="category_id">Mã loại</label>
      <input type="text" id="category_id" name="category_id" placeholder="Mã loại tự sinh" value="<?php echo htmlspecialchars($nextCategoryId ?? ''); ?>" readonly>
      <span class="error-message"></span>
    </div>

    <!-- Tên danh mục -->
    <div class="form-group">
      <label for="category_name">Tên loại sản phẩm</label>
      <input type="text" id="category_name" name="category_name" placeholder="Nhập tên loại sản phẩm">
      <span class="error-message"></span>
    </div>

    <!-- Mã code danh mục -->
    <div class="form-group">
      <label for="category_code">Mã code</label>
      <input type="text" id="category_code" name="category_code" placeholder="Nhập mã code (VD: CODE123)">
      <span class="error-message"></span>
    </div>

    <!-- Mô tả -->
    <div class="form-group">
      <label for="description">Mô tả</label>
      <textarea id="description" name="description" placeholder="Nhập mô tả loại sản phẩm"></textarea>
      <span class="error-message"></span>
    </div>

    <!-- Trạng thái -->
    <div class="form-group">
      <label for="status">Trạng thái</label>
      <select id="status" name="status">
        <option value="">- Chọn trạng thái -</option>
        <option value="1">Hoạt động</option>
        <option value="0">Không hoạt động</option>
      </select>
      <span class="error-message"></span>
    </div>

    <!-- Nút thao tác -->
    <div class="form-actions">
      <a href="index.php?page=categories">Quay lại</a>
      <button type="reset" class="btn-secondary">Hủy</button>
      <button type="submit" class="btn-success" name="btnAdd" id="saveBtn" disabled aria-disabled="true">Thêm</button>
    </div>

  </form>
</div>

<!-- Modal xác nhận thêm -->
<div id="confirmModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Xác nhận thêm loại sản phẩm</h3>
    <p>Bạn có chắc chắn muốn thêm loại sản phẩm này không?</p>
    <div class="modal-actions">
      <button type="button" id="cancelModalBtn">Hủy</button>
      <button type="button" id="confirmAddBtn">Xác nhận</button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const inputs = form.querySelectorAll("input, select, textarea");
  const saveBtn = form.querySelector("button[name='btnAdd']");

  // Start visually disabled using class; keep button visible
  saveBtn.classList.add('is-disabled');
  saveBtn.setAttribute('aria-disabled', 'true');

  inputs.forEach((field) => (field.dataset.touched = "false"));

  function validateField(field) {
    let value = field.value.trim();
    let error = field.closest(".form-group").querySelector(".error-message");
    let valid = true;

    if (field.dataset.touched === "false") {
      error.innerText = "";
      return true;
    }

    if (["category_id","category_name","category_code"].includes(field.id) && value === "") {
      error.innerText = "Trường này là bắt buộc";
      return false;
    }

    if (field.id === "category_id") {
      // Allow alphanumeric category IDs (3-20 characters)
      let regex = /^[A-Za-z0-9]{3,20}$/;
      if (!regex.test(value)) {
        error.innerText = "Mã danh mục chỉ gồm chữ và số (3-20 ký tự)";
        valid = false;
      } else error.innerText = "";
    }

    if (field.id === "category_name") {
      let regex = /^[\p{L}\s0-9]+$/u;
      if (!regex.test(value)) {
        error.innerText = "Tên danh mục không hợp lệ";
        valid = false;
      } else error.innerText = "";
    }

    if (field.id === "category_code") {
      let regex = /^[A-Za-z0-9_-]{2,20}$/;
      if (!regex.test(value)) {
        error.innerText = "Mã code chỉ gồm chữ, số, gạch dưới/gạch ngang (3-20 ký tự)";
        valid = false;
      } else error.innerText = "";
    }

    if (field.id === "status" && value === "") {
      error.innerText = "Vui lòng chọn trạng thái";
      valid = false;
    }

    return valid;
  }

  function validateForm() {
    let isValid = true;
    inputs.forEach((field) => { if (!validateField(field)) isValid = false; });
    // Keep visual disabled class AND toggle the real disabled property so
    // the button becomes clickable when the form is valid.
    if (!isValid) {
      saveBtn.classList.add('is-disabled');
      saveBtn.disabled = true;
      saveBtn.setAttribute('aria-disabled', 'true');
    } else {
      saveBtn.classList.remove('is-disabled');
      saveBtn.disabled = false;
      saveBtn.removeAttribute('aria-disabled');
    }

    return isValid;
  }

  inputs.forEach((field) => {
    field.addEventListener("input", function () { field.dataset.touched = "true"; validateField(field); validateForm(); });
    field.addEventListener("blur", function () { field.dataset.touched = "true"; validateField(field); validateForm(); });
    field.addEventListener("change", function () { field.dataset.touched = "true"; validateField(field); validateForm(); });
  });

  // Modal confirmation behavior
  const confirmModal = document.getElementById('confirmModal');
  const confirmAddBtn = document.getElementById('confirmAddBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  let isConfirmed = false;

  form.addEventListener('submit', function (e) {
    if (!validateForm()) { e.preventDefault(); return false; }
    if (!isConfirmed) { e.preventDefault(); confirmModal.style.display = 'block'; return false; }
  });

  confirmAddBtn.addEventListener('click', function() {
    isConfirmed = true;
    confirmModal.style.display = 'none';
    // Submit form programmatically (bypasses disabled attribute)
    form.submit();
  });

  // Reset handler: clear touched flags and disable save button
  form.addEventListener('reset', function () {
    inputs.forEach((field) => (field.dataset.touched = "false"));
    saveBtn.disabled = true;
    saveBtn.setAttribute('aria-disabled', 'true');
  });
  cancelModalBtn.addEventListener('click', function() { confirmModal.style.display = 'none'; isConfirmed = false; });
  window.addEventListener('click', function(event) { if (event.target === confirmModal) { confirmModal.style.display = 'none'; isConfirmed = false; } });

});

// Removed global stub validateForm
</script>
