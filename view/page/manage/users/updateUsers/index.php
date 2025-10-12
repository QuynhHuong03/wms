<?php
// session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include_once("../../../model/mRoles.php");
$mRoles = new MRoles();
if (!isset($_SESSION["login"])) {
    header("Location: ../page/index.php?page=login");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật nhân viên</title>

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
    <form action="users/updateUsers/process.php" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
      
      <!-- Họ và tên -->
      <div class="form-group">
        <label for="name">Họ và tên</label>
        <input type="text" id="name" name="name" 
                placeholder="Nhập họ và tên" 
                value="<?php echo $user['name']?>"
            onblur="validateField(this, 'Họ và tên không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Nhập email" 
                value="<?php echo $user['email']?>"
            onblur="validateField(this, 'Email không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Giới tính -->
      <div class="form-group">
        <label for="gender">Giới tính</label>
        <select name="gender" id="gender">
          <option value="1" <?php echo ($user['gender'] == 1) ? "selected" : ""; ?>>Nam</option>
          <option value="0" <?php echo ($user['gender'] == 0) ? "selected" : ""; ?>>Nữ</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Số điện thoại -->
      <div class="form-group">
        <label for="phone">Số điện thoại</label>
        <input type="text" id="phone" name="phone" placeholder="Nhập số điện thoại" 
                value="<?php echo $user['phone']?>"
            onblur="validateField(this, 'Số điện thoại không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Vai trò -->
      <div class="form-group">
        <label for="role_id">Vai trò</label>
        <select name="role_id" id="role_id">
          <?php
            include("../../../controller/cRoles.php");
            $obj = new CRoles();
            $listRole = $obj->getAllRoles(); // Trả về array

            if (!empty($listRole)) {
                foreach ($listRole as $r) {
                    $selected = ($r['role_id'] == $user['role_id']) ? 'selected' : '';
                    echo '<option value="' . $r['role_id'] . '" ' . $selected . '>' . $r['role_name'] . '</option>';
                }
            } else {
                echo '<option value="">Không có dữ liệu vai trò</option>';
            }
          ?>

        </select>
        <span class="error-message"></span>
      </div>

      <!-- Trạng thái -->
      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="1" <?php echo ($user['status'] == 1) ? 'selected' : ''; ?>>Đang làm việc</option>
          <option value="2" <?php echo ($user['status'] == 2) ? 'selected' : ''; ?>>Nghỉ việc</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Kho làm việc -->
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
        $selected = ($r['warehouse_id'] == $user['warehouse_id']) ? 'selected' : '';
        echo '<option value="' . $r['warehouse_id'] . '" ' . $selected . '>' . $r['warehouse_name'] . '</option>';
    }
} else {
    echo '<option value="">Không có dữ liệu kho</option>';
}
?>

    </select>
    <span class="error-message"></span>
</div>

      <!-- Nút thao tác -->
      <div class="form-actions">
        <a href="index.php?page=users">Quay lại</a>
        <button type="button" class="btn-secondary" id="btnCancel">Hủy</button>
        <button type="submit" class="btn-success" name="btnUpdate">Cập nhật</button>
      </div>

    </form>
  </div>

<!-- Modal xác nhận -->
<div id="confirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
    background: rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:1000;">
  <div style="background:#fff; padding:20px 30px; border-radius:12px; max-width:400px; width:90%; text-align:center;">
    <p style="font-size:18px; margin-bottom:20px;">Bạn có chắc chắn muốn cập nhật nhân viên này không?</p>
    <button id="confirmYes" style="background:#16a34a; color:#fff; padding:10px 20px; border:none; border-radius:8px; margin-right:10px; cursor:pointer;">Có</button>
    <button id="confirmNo" style="background:#6b7280; color:#fff; padding:10px 20px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const modal = document.getElementById("confirmModal");
    const btnYes = document.getElementById("confirmYes");
    const btnNo = document.getElementById("confirmNo");

    form.addEventListener("submit", function(e){
        e.preventDefault(); // ngăn submit mặc định
        modal.style.display = "flex"; // hiện popup
    });

    btnNo.addEventListener("click", function(){
        modal.style.display = "none"; // đóng popup
    });

    btnYes.addEventListener("click", function(){
    const formData = new FormData(form);
    formData.append('id', '<?php echo $user['user_id']; ?>'); // gửi id
    formData.append('btnUpdate', '1'); // gửi để process.php nhận biết là form submit

    fetch("users/updateUsers/process.php", {  // cùng cấp với index.php
        method: "POST",
        body: formData
    })
    .then(() => {
        // Sau khi update xong, redirect về trang danh sách
        window.location.href = "index.php?page=users";
    })
    .catch(error => {
        alert("Lỗi: " + error);
    });
});

});
</script>

</body>
</html>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const inputs = form.querySelectorAll("input, select");
  const saveBtn = form.querySelector("button[name='btnUpdate']");
  const cancelBtn = document.getElementById("btnCancel");

  // Lưu dữ liệu gốc ban đầu
  const originalData = {};
  inputs.forEach((field) => {
    if (field.type === "checkbox" || field.type === "radio") {
      originalData[field.id] = field.checked;
    } else {
      originalData[field.id] = field.value;
    }
  });

  // Hàm reset khi nhấn Hủy
  cancelBtn.addEventListener("click", function () {
    // Reset lại giá trị ban đầu
    inputs.forEach((field) => {
      if (field.type === "checkbox" || field.type === "radio") {
        field.checked = originalData[field.id];
      } else {
        field.value = originalData[field.id];
      }
      field.dataset.touched = "false"; // reset trạng thái touched
    });

    // Xóa lỗi hiển thị
    const errors = form.querySelectorAll(".error-message");
    errors.forEach((error) => {
      error.innerText = "";
    });

    // Disable lại nút cập nhật
    saveBtn.disabled = true;
    saveBtn.style.opacity = "0.6";
    saveBtn.style.cursor = "not-allowed";
  });

  // Disable nút cập nhật lúc đầu
  saveBtn.disabled = true;
  saveBtn.style.opacity = "0.6";
  saveBtn.style.cursor = "not-allowed";

  inputs.forEach((field) => (field.dataset.touched = "false"));

  function validateField(field) {
    let value = field.value.trim();
    let error = field.closest(".form-group").querySelector(".error-message"); 
    let valid = true;

    if (field.dataset.touched === "false") return true;

    // --- Check rỗng ---
    if (value === "") {
      switch (field.id) {
        case "name":
          error.innerText = "Họ tên không được để trống";
          break;
        case "email":
          error.innerText = "Email không được để trống";
          break;
        case "phone":
          error.innerText = "Số điện thoại không được để trống";
          break;
        case "gender":
          error.innerText = "Vui lòng chọn giới tính";
          break;
        case "role_id":
          error.innerText = "Vui lòng chọn vai trò";
          break;
        case "status":
          error.innerText = "Vui lòng chọn trạng thái";
          break;
        default:
          error.innerText = "Trường này không được để trống";
      }
      return false;
    } else {
      error.innerText = "";
    }

    // --- Check chi tiết ---
    if (field.id === "name") {
      let regex = /^[\p{L}\s]+$/u;
      if (!regex.test(value)) {
        error.innerText = "Họ tên chỉ được chứa chữ cái và khoảng trắng";
        valid = false;
      }
    }

    if (field.id === "email") {
      let regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!regex.test(value)) {
        error.innerText = "Email không hợp lệ";
        valid = false;
      }
    }

    if (field.id === "phone") {
      let regex = /^[0-9]{10}$/;
      if (!regex.test(value)) {
        error.innerText = "Số điện thoại phải gồm 10 chữ số";
        valid = false;
      }
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

  inputs.forEach((field) => {
    if (field.type !== "radio") {
      field.addEventListener("input", function () {
        field.dataset.touched = "true";
        validateField(field);
        validateForm();
      });

      field.addEventListener("blur", function () {
        field.dataset.touched = "true";
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
  
});
</script>
