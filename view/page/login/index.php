<?php
session_start();
ob_start(); // Bật output buffering để dùng header()

include_once("../../../controller/cUsers.php");

$loginError = '';

if (isset($_POST["btDangnhap"])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $obj = new CUsers();
    $result = $obj->dangnhaptaikhoan($email, $password);

    if ($result === false) {
        $loginError = "Email hoặc mật khẩu không đúng, hoặc tài khoản bị khóa.";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Đăng nhập WMS</title>
<link rel="icon" type="image/png" href="../../../img/logo1.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
body {
  font-family: Arial, sans-serif;
}

.background {
  width: 100%;
  height: 100vh;
  background-image: url('../../../img/Warehouse-Management-Systems-WMS.png');
  background-size: cover;
  background-position: center 35%;
  background-repeat: no-repeat;
}

.header {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 10px 80px;
  border-bottom-left-radius: 50px;
  border-bottom-right-radius: 50px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.header img {
    width: 130px;
    height: 80px;
}

.btn-login {
  background: #3b82f6;
  color: #fff;
  border: none;
  padding: 14px 28px;
  font-size: 15px;
  font-weight: bold;
  border-radius: 8px;
  cursor: pointer;
  transition: 0.3s;
}
.btn-login:hover {
  opacity: 0.9;
}

.modal {
  display: none; /* Ẩn mặc định */
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0,0,0,0.6);
  justify-content: center;
  align-items: center;
}
.modal-content {
  background-color: white;
  border-radius: 12px;
  box-shadow: 0px 4px 12px rgba(0,0,0,0.2);
  padding: 32px;
  width: 400px;
  text-align: center;
  position: relative;
}
.close {
  position: absolute;
  top: 10px; right: 15px;
  font-size: 30px;
  cursor: pointer;
}

.logo-container {
  background-color: #ebf5ff;
  border-radius: 50%;
  padding: 5px;
  display: inline-block;
  margin-bottom: 10px;
}
.logo {
  width: 60px;
  height: 50px;
}
h3 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 15px;
}
.input-group {
  display: flex;
  align-items: center;
  border-bottom: 2px solid #d1d5db;
  margin-bottom: 15px;
  padding-bottom: 5px;
}
.input-group i {
  color: #4a5568;
  margin-right: 8px;
}
.input-group input {
  width: 100%;
  border: none;
  outline: none;
  padding: 8px 0;
}
.input-group input:focus {
  border-bottom-color: #3b82f6;
}
.button-group {
  margin-top: 20px;
}
.button {
  width: 100%;
  padding: 10px;
  border-radius: 8px;
  color: white;
  background-color: #3b82f6;
  border: none;
  cursor: pointer;
  font-size: 1rem;
}
.error-message {
  color: red;
  margin-bottom: 15px;
}
.error-field {
  color: red;
  font-size: 0.9rem;
  margin: 4px 0 12px 28px; /* căn dưới input */
  text-align: left;
}

</style>
</head>
<body>

<div class="background"></div>

<header class="header">
  <img src="../../../img/logo1.png" alt="" >
  <h2>Hệ thống quản lý kho hàng</h2>
  <button class="btn-login" id="openLogin">ĐĂNG NHẬP</button>
</header>

<div class="modal" id="loginModal">
  <div class="modal-content">
    <span class="close" id="closeModal">&times;</span>
    <div class="logo-container">
      <img src="../../../img/logo1.png" class="logo" alt="Logo">
    </div>
    <h3>Đăng nhập vào hệ thống</h3>

    <?php if($loginError): ?>
      <div class="error-message"><?php echo $loginError; ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm">
<!-- Email -->
<div class="input-group">
  <i class="fas fa-envelope"></i>
  <input type="text" id="email" name="email" placeholder="Email">
</div>
<div id="errorEmail" class="error-field"></div>

<!-- Password -->
<div class="input-group">
  <i class="fas fa-lock"></i>
  <input type="password" id="password" name="password" placeholder="Mật khẩu">
  <i class="fas fa-eye" id="togglePassword" style="cursor:pointer; margin-left:8px;"></i>
</div>
<div id="errorPassword" class="error-field"></div>


  <div class="button-group">
    <button type="submit" class="button" name="btDangnhap">Đăng nhập</button>
  </div>
</form>


<!-- Thông báo lỗi -->
<div id="clientError" style="color:red; margin-top:10px;"></div>

  </div>
</div>

<script>
// Mở modal
document.getElementById("openLogin").onclick = function() {
  document.getElementById("loginModal").style.display = "flex";
}
// Đóng modal
document.getElementById("closeModal").onclick = function() {
  document.getElementById("loginModal").style.display = "none";
}
// Click ra ngoài cũng đóng
window.onclick = function(e) {
  if (e.target == document.getElementById("loginModal")) {
    document.getElementById("loginModal").style.display = "none";
  }
}
</script>

</body>
</html>

<script>
document.getElementById("loginForm").addEventListener("submit", function(e) {
  let email = document.getElementById("email").value.trim();
  let password = document.getElementById("password").value.trim();

  let errorEmail = document.getElementById("errorEmail");
  let errorPassword = document.getElementById("errorPassword");

  // reset lỗi
  errorEmail.innerText = "";
  errorPassword.innerText = "";

  let isValid = true;

  // Kiểm tra email (bằng regex, không dùng validate HTML)
  if (email === "") {
    errorEmail.innerText = "Vui lòng nhập email";
    isValid = false;
  } else {
    let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
      errorEmail.innerText = "Vui lòng nhập email hợp lệ";
      isValid = false;
    }
  }

  // Kiểm tra password
  if (password === "") {
    errorPassword.innerText = "Vui lòng nhập mật khẩu";
    isValid = false;
  } else if (password.length < 6) {
    errorPassword.innerText = "Mật khẩu phải có ít nhất 6 ký tự";
    isValid = false;
  }

  // Nếu có lỗi → chặn submit
  if (!isValid) {
    e.preventDefault();
  }
});

// Toggle password eye 
const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");

togglePassword.addEventListener("click", function () {
  const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
  passwordInput.setAttribute("type", type);

  // đổi icon
  if (type === "text") {
    this.classList.remove("fa-eye");
    this.classList.add("fa-eye-slash"); // mắt có gạch chéo
  } else {
    this.classList.remove("fa-eye-slash");
    this.classList.add("fa-eye"); // mắt mở
  }
});

</script>

