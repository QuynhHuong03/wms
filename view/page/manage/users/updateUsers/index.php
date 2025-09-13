<?php
// session_start();
include_once("../../../model/mRoles.php");
$mChucVu = new MRoles();
if (!isset($_SESSION["login"])) {
    header("Location: ../page/index.php?page=login");
    exit();
}

?>
 <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
</head>

<body>
    <?php
    include_once("../../../controller/cUsers.php");
    $id = $_GET['id'];
    $p = new CUsers();
    $user = $p->getAllUsers($id);
    ?>
  <div class="page-header">
    <h2>Cập nhật người dùng</h2>
    <p>Cập nhật tài khoản người dùng</p>
  </div>

  <div class="container">
    <form action="" method="post" enctype="multipart/form-data" onsubmit="return validateForm()">
      
      <!-- Họ và tên -->
      <div class="form-group">
        <label for="name">Họ và tên</label>
        <input type="text" id="name" name="name" 
                placeholder="Nhập họ và tên" 
                value="<?php echo isset($user['fullname']) ? htmlspecialchars($user['fullname']) : ''; ?>">
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
  <?php
// Nếu nhấn nút Lưu thì mới xử lý cập nhật
if (isset($_POST['btnSua'])) {
    include("xuly.php");
}
?>
</body>


</html>

<script>
  function validateField(field, message, validator) {
    const errorSpan = field.nextElementSibling;
    if (!validator(field.value.trim())) {
      errorSpan.textContent = message;
      field.classList.add("is-invalid"); // Thêm class để làm nổi bật lỗi
    } else {
      errorSpan.textContent = "";
      field.classList.remove("is-invalid");
    }
  }

  function validateForm(){
    // Kiểm tra lại toàn bộ form trước khi gửi
    const name = document.getElementById("name");
    const phone = document.getElementById("phone");
    const email = document.getElementsByName("email")[0];
    const status = document.getElementById("status");
    const chucvu = document.getElementsByName("chucvu")[0];

    let isValid = true;

    // Kiểm tra trường nhập liệu
    validateField(name, "Họ và tên không được để trống.", value => value.length > 0);
    validateField(chucvu, "Vui lòng chọn chức vụ.", value => value !== "");
    validateField(status, "Vui lòng chọn trạng thái.", value => value !== "");


    function validateName(name) {
    const nameRegex = /^[a-zA-ZÀ-ỹ\s]+$/; // Cho phép ký tự alphabet (bao gồm có dấu) và dấu cách
    if (name.trim() === "") {
        return { valid: false, message: "Họ và tên không được để trống." };
    }
    if (!nameRegex.test(name)) {
        return { valid: false, message: "Họ và tên chỉ được chứa ký tự chữ cái và dấu cách." };
    }
    return { valid: true, message: "" };
    }

    function validatePhoneNumber(phoneNumber) {
  // Kiểm tra số bắt đầu bằng mã vùng hợp lệ ở Việt Nam và có 10 chữ số
  const phoneRegex = /^(03|05|07|08|09)\d{8}$/; 
  if (phoneNumber.trim() === "") {
    return { valid: false, message: "Số điện thoại không được để trống." };
  }
  if (!phoneRegex.test(phoneNumber)) {
    return { 
      valid: false, 
      message: "Số điện thoại không hợp lệ. Số điện thoại phải gồm 10 chữ số và bắt đầu là 03, 05, 07, 08, 09." 
    };
  }
  return { valid: true, message: "" };
}


    function validateEmail(email) {
      const emailRegex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/; // Định dạng email chuẩn
      if (email.trim() === "") {
        return { valid: false, message: "Email không được để trống." };
      }
      if (!emailRegex.test(email)) {
        return { valid: false, message: "Email không hợp lệ. Email có định dạng là abc@xxx.yy" };
      }
      return { valid: true, message: "" };
    }



    // Kiểm tra họ tên
    const nameValidation = validateName(name.value);
    if (!nameValidation.valid) {
        const nameError = name.nextElementSibling;
        nameError.textContent = nameValidation.message;
        name.classList.add("is-invalid");
        isValid = false;
    } else {
        name.nextElementSibling.textContent = "";
        name.classList.remove("is-invalid");
    }

    // Kiểm tra số điện thoại
    const phoneValidation = validatePhoneNumber(phone.value);
      if (!phoneValidation.valid) {
        const phoneError = phone.nextElementSibling;
        phoneError.textContent = phoneValidation.message;
        phone.classList.add("is-invalid");
        isValid = false;
      } else {
        phone.nextElementSibling.textContent = "";
        phone.classList.remove("is-invalid");
      }

    // Kiểm tra email
    const emailValidation = validateEmail(email.value);
    if (!emailValidation.valid) {
      const emailError = email.nextElementSibling;
      emailError.textContent = emailValidation.message;
      email.classList.add("is-invalid");
      isValid = false;
    } else {
      email.nextElementSibling.textContent = "";
      email.classList.remove("is-invalid");
    }

    // Kiểm tra các trường select
    if (status.value === "") {
      const statusError = status.nextElementSibling;
      statusError.textContent = "Vui lòng chọn trạng thái.";
      status.classList.add("is-invalid");
      isValid = false;
    } else {
      status.nextElementSibling.textContent = "";
      status.classList.remove("is-invalid");
    }

    if (chucvu.value === "") {
      const chucvuError = chucvu.nextElementSibling;
      chucvuError.textContent = "Vui lòng chọn chức vụ.";
      chucvu.classList.add("is-invalid");
      isValid = false;
    } else {
      chucvu.nextElementSibling.textContent = "";
      chucvu.classList.remove("is-invalid");
    }

    // If the form is valid, show the confirmation prompt
    if (isValid) {
      return confirm("Bạn có chắc chắn muốn cập nhật nhân viên này không?");
        
    }
    return false; // If the form is invalid, prevent submission

  }
</script>
