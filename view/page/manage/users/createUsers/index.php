<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* --- BASE & TYPOGRAPHY --- */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Font hiện đại hơn */
    background-color: #f0f3f8; /* Màu nền nhẹ nhàng */
    color: #1f2937; /* Màu chữ chính */
    margin: 0;
    padding: 20px 0;
}

/* --- HEADER --- */
.page-header {
    width: 90%;
    max-width: 700px; /* Giữ kích thước container vừa phải */
    margin: 30px auto 15px;
    padding-left: 10px;
    border-left: 4px solid #2563eb; /* Đường viền xanh nổi bật */
}

.page-header h2 {
    margin: 0;
    color: #111827;
    font-size: 2rem;
    font-weight: 700;
}

.page-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 1rem;
}

/* --- CONTAINER & FORM --- */
.container {
    width: 90%;
    max-width: 700px; /* Phù hợp với header */
    margin: 20px auto;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08); /* Bóng đổ mềm mại */
    padding: 30px 40px; /* Tăng padding */
}

/* Input group */
.form-group {
    margin-bottom: 25px; /* Tăng khoảng cách giữa các trường */
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 1rem;
    color: #374151;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px 15px; /* Tăng padding input */
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    color: #374151;
    background-color: #f9fafb;
    transition: all 0.3s;
    box-sizing: border-box; /* Quan trọng cho width 100% */
}

.form-group input:focus,
.form-group select:focus {
    border-color: #2563eb;
    background-color: #ffffff;
    outline: none;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); /* Ring focus xanh */
}

/* Password Toggle */
.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-right: 50px; /* Chừa chỗ cho icon rộng rãi hơn */
}

.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 1.1rem;
    color: #9ca3af;
    transition: color 0.2s;
}

.toggle-password:hover {
    color: #2563eb;
}


/* Error message */
.error-message {
    font-size: 0.85rem;
    color: #ef4444; /* Đỏ sắc nét */
    margin-top: 6px;
    display: block;
    font-weight: 500;
}
.form-group input:invalid,
.form-group select:invalid {
    /* Thêm hiệu ứng lỗi visual khi validation thất bại (chỉ khi field bị touched) */
    border-color: #f87171 !important;
}


/* Button group */
.form-actions {
    text-align: right; /* Căn nút sang phải */
    margin-top: 35px;
    display: flex;
    justify-content: flex-end; /* Căn nút sang phải */
    gap: 12px;
}

.form-actions button,
.form-actions a {
    padding: 12px 25px; /* Tăng padding nút */
    font-size: 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Primary Button (Thêm) */
.form-actions .btn-success {
    background-color: #10b981; /* Xanh lá cây nổi bật */
    color: #fff;
}
.form-actions .btn-success:hover {
    background-color: #059669;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.4);
}

/* Secondary Button (Hủy) */
.form-actions .btn-secondary {
    background-color: #9ca3af; /* Xám */
    color: #fff;
}

.form-actions .btn-secondary:hover {
    background-color: #6b7280;
}

/* Back Button (Quay lại) */
.form-actions a {
    background-color: #e5e7eb;
    color: #4b5563;
}
.form-actions a:hover {
    background-color: #d1d5db;
    color: #1f2937;
}

/* Disable State */
.form-actions button[disabled],
.form-actions button[disabled]:hover {
  /* keep behavior for any code that uses real disabled attr */
  background-color: #d1d5db !important;
  cursor: not-allowed !important;
  opacity: 0.8;
  box-shadow: none;
}

.form-actions .btn-success.is-disabled {
  background-color: #d1d5db;
  cursor: not-allowed;
  opacity: 0.8;
  box-shadow: none;
  pointer-events: none; /* prevent clicks */
}

/* Responsive adjustment */
@media (max-width: 576px) {
    .container {
        padding: 20px;
    }
    .page-header {
        padding-left: 5px;
        margin-top: 15px;
    }
    .page-header h2 {
        font-size: 1.5rem;
    }
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
}

.modal-content {
    background: #fff;
    max-width: 450px;
    margin: 15vh auto;
    padding: 35px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    text-align: center;
}

.modal-content h3 {
    color: #1f2937;
    margin: 0 0 15px 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.modal-content p {
    color: #6b7280;
    margin-bottom: 30px;
    font-size: 1.05rem;
    line-height: 1.6;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-actions button {
    padding: 12px 28px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    font-size: 1rem;
    transition: all 0.2s;
    min-width: 100px;
}

#cancelModalBtn {
    background: #e5e7eb;
    color: #374151;
}
#cancelModalBtn:hover {
    background: #d1d5db;
}

#confirmAddBtn {
    background: #10b981;
    color: white;
}
#confirmAddBtn:hover {
    background: #059669;
    box-shadow: 0 4px 10px rgba(16, 185, 129, 0.4);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Safety overrides: ensure action buttons are visible even if other global
   styles elsewhere hide or collapse them. These rules use high specificity
   and !important to override competing styles while preserving existing
   visual design from this file. */
form .form-actions,
.container .form-actions,
body .form-actions {
  display: flex !important;
  justify-content: flex-end !important;
  gap: 12px !important;
  margin-top: 35px !important;
  padding-top: 0 !important;
  z-index: 999 !important;
  visibility: visible !important;
  position: relative !important;
  width: 100% !important;
  height: auto !important;
}

form .form-actions button,
form .form-actions a,
.container .form-actions button,
.container .form-actions a,
body .form-actions button,
body .form-actions a {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  min-width: 88px !important;
  min-height: 40px !important;
  visibility: visible !important;
  opacity: 1 !important;
  position: relative !important;
  pointer-events: auto !important;
  overflow: visible !important;
}

/* Re-assert the intended colors so buttons remain visible */
form .form-actions .btn-success,
.container .form-actions .btn-success { 
  background-color: #10b981 !important; 
  color: #fff !important; 
  border: none !important;
}

form .form-actions .btn-secondary,
.container .form-actions .btn-secondary { 
  background-color: #9ca3af !important; 
  color: #fff !important; 
  border: none !important;
}

form .form-actions a,
.container .form-actions a { 
  background-color: #e5e7eb !important; 
  color: #4b5563 !important; 
  border: none !important;
}

/* Override disabled state visual */
form .form-actions .btn-success.is-disabled,
.container .form-actions .btn-success.is-disabled {
  background-color: #d1d5db !important;
  cursor: not-allowed !important;
  opacity: 0.8 !important;
  box-shadow: none !important;
}
  </style>

  <div class="page-header">
    <h2>Thêm người dùng</h2>
    <p>Tạo mới tài khoản người dùng</p>
  </div>

  <div class="container">
  <form action="/KLTN/view/page/manage/users/createUsers/process.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
      
      <!-- Họ và tên -->
      <div class="form-group">
        <label for="name">Họ và tên</label>
        <input type="text" id="name" name="name" placeholder="Nhập họ và tên">
        <span class="error-message"></span>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Nhập email">
        <span class="error-message"></span>
      </div>

      <!-- Giới tính -->
      <div class="form-group">
        <label for="gender">Giới tính</label>
        <select name="gender" id="gender">
          <option value="">- Chọn giới tính -</option>
          <option value="1">Nam</option>
          <option value="0">Nữ</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Số điện thoại -->
      <div class="form-group">
        <label for="phone">Số điện thoại</label>
        <input type="text" id="phone" name="phone" placeholder="Nhập số điện thoại">
        <span class="error-message"></span>
      </div>

      <!-- Mật khẩu -->
      <div class="form-group password-group">
        <label for="password">Mật khẩu</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" placeholder="Nhập mật khẩu">
          <span class="toggle-password" onclick="togglePassword()">
            <i class="fa-solid fa-eye"></i>
          </span>
        </div>
        <span class="error-message"></span>
      </div>

      <!-- Vai trò -->
      <div class="form-group">
        <label for="role_id">Vai trò</label>
        <select name="role_id" id="role_id">
      <option value="">- Chọn vai trò -</option>
      <?php
      include("../../../controller/cRoles.php");
      $obj = new CRoles();
      $tblRole = $obj->getAllRoles();
      if (is_array($tblRole) && count($tblRole) > 0) {
        foreach ($tblRole as $r) {
          $value = isset($r['role_id']) ? $r['role_id'] : $r['_id'];
          echo '<option value="' . $value . '">' . $r['description'] . '</option>';
        }
      } else {
        echo '<option value="">⚠ Không có dữ liệu vai trò</option>';
      }
      ?>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Trạng thái -->
      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="">- Chọn trạng thái -</option>
          <option value="1">Đang làm việc</option>
          <option value="2">Nghỉ việc</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Kho làm việc -->
      <div class="form-group">
        <label for="warehouse_id">Kho làm việc</label>
        <select name="warehouse_id" id="warehouse_id">
      <option value="">- Chọn kho -</option>
      <?php
      include("../../../controller/cWarehouse.php");
      $obj = new CWarehouse();
      $warehouses = $obj->getAllWarehouses(); // Lấy tất cả kho
      if (is_array($warehouses) && count($warehouses) > 0) {
        foreach ($warehouses as $row) {
          echo '<option value="' . $row['warehouse_id'] . '">' . $row['warehouse_name'] . '</option>';
        }
      } else {
        echo '<option value="">⚠ Không có dữ liệu kho</option>';
      }
      ?>
        </select>
        <span class="error-message"></span>
      </div>


      <!-- Nút thao tác -->
      <div class="form-actions" style="display: flex !important; visibility: visible !important;">
        <a href="index.php?page=users" class="btn-secondary" style="display: inline-flex !important;">Quay lại</a>
        <button type="reset" class="btn-secondary" style="display: inline-flex !important;">Hủy</button>
        <button type="submit" class="btn-success" name="btnAdd" style="display: inline-flex !important;">Thêm</button>
      </div>

    </form>
  </div>

<!-- Modal xác nhận thêm -->
<div id="confirmModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Xác nhận thêm người dùng</h3>
    <p>Bạn có chắc chắn muốn thêm người dùng này không?</p>
    <div class="modal-actions">
      <button type="button" id="cancelModalBtn">Hủy</button>
      <button type="button" id="confirmAddBtn">Xác nhận</button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const inputs = form.querySelectorAll("input, select");
  const saveBtn = form.querySelector("button[name='btnAdd']");

  // Start visually disabled using class (avoid setting disabled attribute which
  // some global CSS might hide). Use aria-disabled for accessibility.
  saveBtn.classList.add('is-disabled');
  saveBtn.setAttribute('aria-disabled', 'true');

  // Gán touched = false cho tất cả
  inputs.forEach((field) => (field.dataset.touched = "false"));

  function validateField(field) {
    let value = field.value.trim();
    let error = field.closest(".form-group").querySelector(".error-message");
    let valid = true;

    // Nếu chưa touched thì bỏ qua
    if (field.dataset.touched === "false") {
      error.innerText = "";
      return true;
    }

    // Kiểm tra required cho các input text
    if (["name","email","phone","password"].includes(field.id) && value === "") {
      switch (field.id) {
        case "name": error.innerText = "Họ và tên là bắt buộc"; break;
        case "email": error.innerText = "Email là bắt buộc"; break;
        case "phone": error.innerText = "Số điện thoại là bắt buộc"; break;
        case "password": error.innerText = "Mật khẩu là bắt buộc"; break;
      }
      return false;
    }

    // Họ và tên
    if (field.id === "name") {
      let regex = /^[\p{L}\s]+$/u;
      if (!regex.test(value)) {
        error.innerText = "Họ tên chỉ được chứa chữ cái và khoảng trắng";
        valid = false;
      } else error.innerText = "";
    }

    // Email
    if (field.id === "email") {
      let regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!regex.test(value)) {
        error.innerText = "Email không hợp lệ";
        valid = false;
      } else error.innerText = "";
    }

    // Phone
    if (field.id === "phone") {
      let regex = /^[0-9]{10}$/;
      if (!regex.test(value)) {
        error.innerText = "Số điện thoại không hợp lệ (10 số)";
        valid = false;
      } else error.innerText = "";
    }

    // Password
    if (field.id === "password") {
      if (value.length < 6) {
        error.innerText = "Mật khẩu phải từ 6 ký tự";
        valid = false;
      } else error.innerText = "";
    }

    // Select box
    if (["role_id","status","warehouse_id","gender"].includes(field.id)) {
      if (value === "") {
        error.innerText = "Vui lòng chọn mục này";
        valid = false;
      } else error.innerText = "";
    }

    return valid;
  }

  function validateForm() {
    let isValid = true;
    inputs.forEach((field) => {
      if (field.type !== "radio") {
        if (!validateField(field)) isValid = false;
      }
    });

    // Toggle class-based disabled state so the button remains visible but
    // inert when form invalid. Keep aria-disabled in sync.
    if (!isValid) {
      saveBtn.classList.add('is-disabled');
      saveBtn.setAttribute('aria-disabled', 'true');
    } else {
      saveBtn.classList.remove('is-disabled');
      saveBtn.removeAttribute('aria-disabled');
    }

    return isValid;
  }

  // Sự kiện input / blur / change
  inputs.forEach((field) => {
    if (field.type !== "radio") {
      field.addEventListener("input", function () {
        field.dataset.touched = "true";
        validateField(field);
        validateForm();
      });

      field.addEventListener("blur", function () {
        field.dataset.touched = "true"; // đánh dấu khi out
        validateField(field);
        validateForm();
      });

      field.addEventListener("change", function () {
        field.dataset.touched = "true";
        validateField(field);
        validateForm();
      });
    }
  });

  // Modal xác nhận
  const confirmModal = document.getElementById('confirmModal');
  const confirmAddBtn = document.getElementById('confirmAddBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  let isConfirmed = false;

  form.addEventListener("submit", function (e) {
    // Nếu form không hợp lệ thì chặn submit
    if (!validateForm()) {
      e.preventDefault();
      return false;
    }

    // Nếu chưa xác nhận, chặn submit và hiện modal
    if (!isConfirmed) {
      e.preventDefault();
      confirmModal.style.display = 'block';
      return false;
    }

    // Nếu đã xác nhận, không chặn — cho phép hành vi submit mặc định
  });

  // Xác nhận thêm
  confirmAddBtn.addEventListener('click', function() {
    // Đánh dấu đã xác nhận rồi thực hiện click thật trên nút submit
    isConfirmed = true;
    confirmModal.style.display = 'none';
    // Thực hiện click trên nút submit để gửi yêu cầu giống hành động người dùng
    saveBtn.click();
  });

  // Hủy modal
  cancelModalBtn.addEventListener('click', function() {
    confirmModal.style.display = 'none';
    isConfirmed = false;
  });

  // Đóng modal khi click bên ngoài
  window.addEventListener('click', function(event) {
    if (event.target === confirmModal) {
      confirmModal.style.display = 'none';
      isConfirmed = false;
    }
  });
});

function togglePassword() {
  const passwordInput = document.getElementById("password");
  const toggleIcon = document.querySelector(".toggle-password i");

  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleIcon.classList.remove("fa-eye");
    toggleIcon.classList.add("fa-eye-slash");
  } else {
    passwordInput.type = "password";
    toggleIcon.classList.remove("fa-eye-slash");
    toggleIcon.classList.add("fa-eye");
  }
}
</script>
