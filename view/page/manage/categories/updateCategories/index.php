<?php
// session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include_once("../../../controller/cCategories.php");

if (!isset($_SESSION["login"])) {
    header("Location: ../page/index.php?page=login");
    exit();
}

$id = $_GET['id'] ?? null;
$cCategories = new CCategories();
$category = $id ? $cCategories->getCategoryById($id) : null;

if (!$category) {
    $_SESSION['error'] = "Không tìm thấy danh mục cần cập nhật";
    header("Location: index.php?page=categories");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cập nhật loại sản phẩm</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  /* Adopt users/createUsers styling for consistency */
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color:#f0f3f8; color:#1f2937; margin:0; padding:20px 0 }
  .page-header { width:90%; max-width:700px; margin:30px auto 15px; padding-left:10px; border-left:4px solid #2563eb }
  .page-header h2 { margin:0; color:#111827; font-size:2rem; font-weight:700 }
  .page-header p { margin:5px 0 0; color:#6b7280; font-size:1rem }
  .container { width:90%; max-width:700px; margin:20px auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.08); padding:30px 40px }
  .form-group { margin-bottom:20px }
  .form-group label { display:block; margin-bottom:8px; font-weight:600; font-size:1rem; color:#374151 }
  .form-group input, .form-group textarea, .form-group select { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; color:#374151; background:#f9fafb; box-sizing:border-box }
  .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:#2563eb; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.15) }
  .error-message { font-size:0.85rem; color:#ef4444; margin-top:6px; display:block; font-weight:500 }
  .form-actions { text-align:right; margin-top:30px; display:flex; justify-content:flex-end; gap:12px }
  .form-actions button, .form-actions a { padding:12px 25px; font-size:1rem; border:none; border-radius:8px; cursor:pointer; transition:all .2s; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; font-weight:600 }
  .form-actions .btn-success { background:#10b981; color:#fff }
  .form-actions .btn-success:hover { background:#059669; box-shadow:0 4px 10px rgba(16,185,129,0.4) }
  .form-actions .btn-secondary { background:#9ca3af; color:#fff }
  .form-actions a { background:#e5e7eb; color:#4b5563 }

  /* Modal styles */
  .modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.5); animation:fadeIn .3s }
  .modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:35px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center }
  .modal-actions { display:flex; gap:12px; justify-content:center }
  #cancelUpdateBtn { background:#fff; color:#374151; border:1px solid #cbd5e1; padding:10px 18px; border-radius:8px }
  #cancelUpdateBtn:hover { background:#f3f4f6 }
  #confirmUpdateBtn { background:#10b981; color:#fff; border:1px solid #059669; padding:10px 18px; border-radius:8px }
  #confirmUpdateBtn:hover { background:#059669; box-shadow:0 6px 18px rgba(5,150,105,0.18) }
  @keyframes fadeIn { from{opacity:0} to{opacity:1} }
  form .form-actions, .container .form-actions { display:flex !important; justify-content:flex-end !important; gap:12px !important }
  form .form-actions button, form .form-actions a { display:inline-flex !important; align-items:center !important; justify-content:center !important }
  /* Toast notification */
  .toast-notification { position:fixed; top:20px; right:20px; background:#10b981; color:#fff; padding:12px 18px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.12); z-index:10000; font-weight:700; display:flex; gap:10px; align-items:center }
  .toast-notification.error { background:#ef4444 }
  .toast-notification.hide { animation: toastOut .28s forwards }
  @keyframes toastOut { from { transform: translateX(0); opacity:1 } to { transform: translateX(300px); opacity:0 } }
  </style>
</head>

<body>
  <?php
  // Server-side toast: show a success toast when redirected with msg=updated or success
  $msg = $_GET['msg'] ?? '';
  if ($msg === 'updated' || $msg === 'success') {
    $text = ($msg === 'updated') ? 'Cập nhật thành công' : 'Thao tác thành công';
    echo '<div id="serverToast" class="toast-notification">' . $text . '</div>';
    // Remove msg from URL after showing (preserve page and id)
    echo "<script>
      setTimeout(()=>{const t=document.getElementById('serverToast'); if(t){t.classList.add('hide'); setTimeout(()=>t.remove(),300);} const newUrl = window.location.pathname + '?page=categories/updateCategories&id=' + encodeURIComponent('" . addslashes($id) . "'); window.history.replaceState({},'',newUrl);},3000);
    </script>";
  }
  ?>
  <div class="page-header">
    <h2>Cập nhật loại sản phẩm</h2>
    <p>Cập nhật thông tin loại sản phẩm</p>
  </div>

  <div class="container">
    <form action="categories/updateCategories/process.php" method="post">
      <input type="hidden" name="id" value="<?php echo $category['category_id'] ?? $category['_id']; ?>">

      <!-- Mã code danh mục -->
      <div class="form-group">
        <label for="category_code">Mã code</label>
        <input type="text" id="category_code" name="category_code"
               value="<?php echo htmlspecialchars($category['category_code'] ?? ''); ?>"
               placeholder="Nhập mã code (VD: CODE123)"
               required
               onblur="validateField(this, 'Mã code không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Tên danh mục -->
      <div class="form-group">
        <label for="category_name">Tên loại sản phẩm</label>
        <input type="text" id="category_name" name="category_name"
               value="<?php echo htmlspecialchars($category['category_name']); ?>"
               placeholder="Nhập tên loại sản phẩm"
               required
               onblur="validateField(this, 'Tên loại sản phẩm không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Mô tả -->
      <div class="form-group">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="Nhập mô tả loại sản phẩm"><?php echo htmlspecialchars($category['description']); ?></textarea>
        <span class="error-message"></span>
      </div>

      <!-- Trạng thái -->
      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="1" <?php echo ($category['status'] == 1) ? 'selected' : ''; ?>>Hoạt động</option>
          <option value="0" <?php echo ($category['status'] == 0) ? 'selected' : ''; ?>>Ngừng hoạt động</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Nút thao tác -->
      <div class="form-actions">
        <a href="index.php?page=categories">Quay lại</a>
        <button type="reset" class="btn-secondary" id="btnCancel">Hủy</button>
        <button type="submit" class="btn-success" name="btnUpdate" id="saveBtn" disabled aria-disabled="true">Cập nhật</button>
      </div>
    </form>
  </div>

  <!-- Modal xác nhận cập nhật -->
  <div id="confirmModal" class="modal" style="display:none;">
    <div class="modal-content">
      <h3>Xác nhận cập nhật loại sản phẩm</h3>
      <p>Bạn có chắc chắn muốn cập nhật loại sản phẩm này không?</p>
      <div class="modal-actions">
        <button type="button" id="cancelUpdateBtn">Hủy</button>
        <button type="button" id="confirmUpdateBtn">Xác nhận</button>
      </div>
    </div>
  </div>

  <script>
    const form = document.querySelector("form");
    const inputs = form.querySelectorAll("input, textarea, select");
    const saveBtn = form.querySelector("button[name='btnUpdate']");
    const cancelBtn = document.getElementById("btnCancel");

    const originalData = {};
    inputs.forEach(field => originalData[field.id] = field.value);

    cancelBtn.addEventListener("click", function () {
      inputs.forEach(field => field.value = originalData[field.id]);
      const errors = form.querySelectorAll(".error-message");
      errors.forEach(error => error.innerText = "");
      saveBtn.disabled = true;
      saveBtn.style.opacity = "0.6";
      saveBtn.style.cursor = "not-allowed";
    });

    saveBtn.disabled = true;
    saveBtn.style.opacity = "0.6";
    saveBtn.style.cursor = "not-allowed";

    function validateField(field, message, rule) {
      let error = field.closest(".form-group").querySelector(".error-message");
      if (!rule(field.value.trim())) {
        error.innerText = message;
        return false;
      }
      // Validate riêng cho mã code
      // if (field.id === "category_code" && field.value.trim() !== "") {
      //   let regex = /^[A-Za-z]$/;
      //   if (!regex.test(field.value.trim())) {
      //     error.innerText = "Mã code chỉ gồm chữ, số, gạch dưới/gạch ngang (3-20 ký tự)";
      //     return false;
      //   }
      // }
      error.innerText = "";
      return true;
    }

    function isChanged() {
      for (let field of inputs) {
        const id = field.id;
        const orig = originalData[id] || '';
        if ((field.value || '') !== orig) return true;
      }
      return false;
    }

    function validateForm() {
      let valid = true;
      inputs.forEach(field => {
        if (field.hasAttribute("required")) {
          if (!validateField(field, "Trường này không được để trống.", v => v.length > 0)) valid = false;
        }
      });

      // Enable save only when valid and something changed
      const enable = valid && isChanged();
      saveBtn.disabled = !enable;
      saveBtn.style.opacity = enable ? "1" : "0.6";
      saveBtn.style.cursor = enable ? "pointer" : "not-allowed";
      return enable;
    }

    // Modal + submit handling
    const confirmModal = document.getElementById('confirmModal');
    const confirmUpdateBtn = document.getElementById('confirmUpdateBtn');
    const cancelUpdateBtn = document.getElementById('cancelUpdateBtn');
    let isConfirmed = false;

    form.addEventListener('submit', function (e) {
      if (!validateForm()) { e.preventDefault(); return false; }
      if (!isConfirmed) { e.preventDefault(); confirmModal.style.display = 'block'; return false; }
      // if confirmed, allow submit
    });

    confirmUpdateBtn.addEventListener('click', function () {
      isConfirmed = true;
      confirmModal.style.display = 'none';
      // When submitting via JS, the submit button's name/value isn't included.
      // Add a hidden input carrying the submit name so server-side checks like
      // `isset($_POST['btnUpdate'])` will succeed.
      const submitBtn = form.querySelector("button[name='btnUpdate']");
      if (submitBtn) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitBtn.name;
        hidden.value = submitBtn.value || '1';
        form.appendChild(hidden);
      }
      form.submit();
    });

    cancelUpdateBtn.addEventListener('click', function () {
      isConfirmed = false;
      confirmModal.style.display = 'none';
    });

    window.addEventListener('click', function(event) { if (event.target === confirmModal) { confirmModal.style.display = 'none'; isConfirmed = false; } });

    // Wire inputs to validation/change detection
    inputs.forEach(field => {
      field.addEventListener('input', () => {
        field.closest('.form-group').querySelector('.error-message').innerText = '';
        validateForm();
      });
      field.addEventListener('change', () => validateForm());
    });
  </script>
</body>
</html>
