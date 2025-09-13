<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<style>
body {
  font-family: 'Arial', sans-serif;
  background-color: #f9f9f9;
  color: #333;
  margin: 0;
}

.page-header {
  width: 90%;
  max-width: 800px;
  margin: 30px auto 10px;
}

.page-header h2 {
  margin: 0;
  color: #222;
}

.page-header p {
  margin: 5px 0 0;
  color: #666;
  font-size: 16px;
}

.container {
  width: 90%;
  max-width: 800px;
  margin: 20px auto;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  padding: 25px 30px;
}

/* Input group */
.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  font-size: 18px;
  color: #333;
}

.form-group input,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 18px;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
  border-color: #3b82f6;
  outline: none;
  box-shadow: 0 0 5px rgba(59,130,246,0.3);
}

.form-group .radio-group {
  display: flex;
  gap: 20px;
  align-items: center;
}

.form-group .radio-group input {
  width: auto;
}

.password-wrapper {
  position: relative;
}

.password-wrapper input {
  width: 100%;
  padding-right: 40px; /* chừa chỗ cho icon */
}

.toggle-password {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  font-size: 18px;
  color: #666;
  user-select: none;
}

.toggle-password:hover {
  color: #111;
}


/* Error message */
.error-message {
  font-size: 14px;
  color: #e11d48;
  margin-top: 4px;
  display: block;
}

/* Button group */
.form-actions {
  text-align: center;
  margin-top: 20px;
  display: flex;
  justify-content: center;
  gap: 15px;
}

.form-actions button,
.form-actions a {
  background-color: #3b82f6;
  color: #fff;
  padding: 10px 20px;
  font-size: 15px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
  text-decoration: none;
  display: inline-block;
}

.form-actions button:hover,
.form-actions a:hover {
  background-color: #2563eb;
}

.form-actions .btn-secondary {
  background-color: #6b7280;
}

.form-actions .btn-secondary:hover {
  background-color: #4b5563;
}

.form-actions .btn-success {
  background-color: #16a34a;
}

.form-actions .btn-success:hover {
  background-color: #15803d;
}
</style>
<head>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>
  <div class="page-header">
    <h2>Thêm người dùng</h2>
    <p>Tạo mới tài khoản người dùng</p>
  </div>

  <div class="container">
    <form action="" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
      
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
            var_dump($tblRole);
            if ($tblRole && $tblRole instanceof mysqli_result && $tblRole->num_rows > 0) {
                while ($r = $tblRole->fetch_assoc()) {
                    echo '<option value="' . $r['role_id'] . '">' . $r['role_name'] . '</option>';
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
          <!-- PHP đổ dữ liệu -->
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Nút thao tác -->
      <div class="form-actions">
        <a href="index.php?page=users">Quay lại</a>
        <button type="reset" class="btn-secondary">Hủy</button>
        <button type="submit" class="btn-success" name="btnAdd">Lưu</button>
      </div>

    </form>
  </div>
</body>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const inputs = form.querySelectorAll("input, select");
  const saveBtn = form.querySelector("button[name='btnAdd']");

  saveBtn.disabled = true;
  saveBtn.style.opacity = "0.6";
  saveBtn.style.cursor = "not-allowed";

  // Đánh dấu field nào đã "touched"
  inputs.forEach((field) => (field.dataset.touched = "false"));

function validateField(field) {
  let value = field.value.trim();
  let error = field.closest(".form-group").querySelector(".error-message"); 
  let valid = true;

  if (field.dataset.touched === "false") return true; //chưa focus chưa kiểm tra

  if (field.id === "name") {
    let regex = /^[\p{L}\s]+$/u;
    if (!regex.test(value)) {
      error.innerText = "Họ tên chỉ được chứa chữ cái và khoảng trắng";
      valid = false;
    } else error.innerText = "";
  }

  if (field.id === "email") {
    let regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!regex.test(value)) {
      error.innerText = "Email không hợp lệ";
      valid = false;
    } else error.innerText = "";
  }

  if (field.id === "phone") {
    let regex = /^[0-9]{10}$/;
    if (!regex.test(value)) {
      error.innerText = "Số điện thoại không hợp lệ (10 số)";
      valid = false;
    } else error.innerText = "";
  }

  if (field.id === "password") {
    if (value.length < 6) {
      error.innerText = "Mật khẩu phải từ 6 ký tự";
      valid = false;
    } else error.innerText = "";
  }

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

    saveBtn.disabled = !isValid;
    saveBtn.style.opacity = isValid ? "1" : "0.6";
    saveBtn.style.cursor = isValid ? "pointer" : "not-allowed";

    return isValid;
  }

  // Khi input thay đổi
  inputs.forEach((field) => {
    if (field.type !== "radio") {
      field.addEventListener("input", function () {
        field.dataset.touched = "true"; // đánh dấu đã đụng
        validateField(field);
        validateForm();
      });

      field.addEventListener("blur", function () {
        field.dataset.touched = "true"; // nếu rời khỏi thì cũng xem như touched
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

  form.addEventListener("submit", function (e) {
    if (!validateForm()) e.preventDefault();
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
