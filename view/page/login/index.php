<?php
session_start();
ob_start();

include_once("../../../controller/cUsers.php");

$loginError = '';

if (isset($_POST["btDangnhap"])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $obj = new CUsers();
    $result = $obj->login($email, $password);

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
  font-family: "Segoe UI", Arial, sans-serif;
  height: 100vh;
  display: flex;
  background: #f3f4f6;
}

.container {
  display: flex;
  width: 100%;
  height: 100vh;
}

.left {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #f9fafb;
  padding: 40px;
}

.card {
  background: #fff;
  padding: 40px;
  border-radius: 16px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.1);
  width: 100%;
  max-width: 500px;
  text-align: center;
}

.logo {
  width: 100px;
  margin-bottom: 15px;
}

h2 {
  margin-bottom: 6px;
  color: #111827;
}
h3 {
  font-size: 1.3rem;
  font-weight: 600;
  margin-bottom: 20px;
  color: #374151;
}

.error-message {
  color: #dc2626;
  margin-bottom: 15px;
}
.error-field {
  color: #dc2626;
  font-size: 0.85rem;
  margin: 4px 0 12px 28px;
  text-align: left;
}

.input-group {
  display: flex;
  align-items: center;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 15px;
  background: #f9fafb;
}
.input-group i {
  color: #6b7280;
  margin-right: 8px;
}
.input-group input {
  width: 100%;
  border: none;
  outline: none;
  background: transparent;
  font-size: 1rem;
}
.input-group input:focus {
  background: #fff;
}

.button {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  color: white;
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  border: none;
  cursor: pointer;
  font-size: 1rem;
  font-weight: bold;
  transition: 0.3s;
}
.button:hover {
  background: linear-gradient(135deg, #1d4ed8, #2563eb);
}

.forgot {
  margin-top: 12px;
  font-size: 0.9rem;
}
.forgot a {
  color: #2563eb;
  text-decoration: none;
}
.forgot a:hover {
  text-decoration: underline;
}

.right {
  flex: 1.2;
  background-image: url('../../../img/wms1.png');
  background-size: cover;
  background-position: center;
}
</style>
</head>
<body>

<div class="container">
  <div class="left">
    <div class="card">
      <img src="../../../img/logo1.png" alt="Logo" class="logo">
      <h2>Hệ thống quản lý kho hàng</h2>
      <h3>Đăng nhập</h3>

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

        <button type="submit" class="button" name="btDangnhap">Đăng nhập</button>
      </form>

      <div class="forgot">
        <a href="#">Quên mật khẩu?</a>
      </div>
    </div>
  </div>

  <!-- Bên phải: hình -->
  <div class="right"></div>
</div>

<script>
document.getElementById("loginForm").addEventListener("submit", function(e) {
  let email = document.getElementById("email").value.trim();
  let password = document.getElementById("password").value.trim();

  let errorEmail = document.getElementById("errorEmail");
  let errorPassword = document.getElementById("errorPassword");

  errorEmail.innerText = "";
  errorPassword.innerText = "";

  let isValid = true;

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

  if (password === "") {
    errorPassword.innerText = "Vui lòng nhập mật khẩu";
    isValid = false;
  } else if (password.length < 6) {
    errorPassword.innerText = "Mật khẩu phải có ít nhất 6 ký tự";
    isValid = false;
  }

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

  if (type === "text") {
    this.classList.remove("fa-eye");
    this.classList.add("fa-eye-slash");
  } else {
    this.classList.remove("fa-eye-slash");
    this.classList.add("fa-eye");
  }
});
</script>

</body>
</html>
