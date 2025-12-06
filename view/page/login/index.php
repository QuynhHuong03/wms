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
    font-family: 'Inter', "Segoe UI", Arial, sans-serif;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background: linear-gradient(135deg, #f0f4f8 0%, #e0e7ee 100%);
    color: #1f2937;
}
.container {
    display: flex;
    width: 90%;
    max-width: 1100px;
    height: 80vh;
    min-height: 550px;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    background-color: #ffffff;
}
.left {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
    background-color: #ffffff;
}

.card {
    background: transparent;
    padding: 0;
    border-radius: 0;
    width: 100%;
    max-width: 380px;
    text-align: center;
}

.logo {
    width: 125px;
    height: 150px;
    object-fit: contain;
}

h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 4px;
    color: #111827;
}

h3 {
    font-size: 1rem;
    font-weight: 400;
    margin-bottom: 30px;
    color: #6b7280;
}

.error-message {
    color: #ef4444;
    background-color: #fee2e2;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #fca5a5;
    font-weight: 500;
}
.error-field {
    color: #ef4444;
    font-size: 0.8rem;
    margin: 4px 0 12px 0;
    text-align: left;
}

.input-group {
    display: flex;
    align-items: center;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 15px;
    margin-bottom: 15px;
    background: #ffffff;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.input-group:focus-within {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.input-group i {
    color: #9ca3af;
    margin-right: 12px;
    font-size: 1.1rem;
}

.input-group input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    font-size: 1rem;
    color: #1f2937;
}

.button {
    width: 100%;
    padding: 14px;
    border-radius: 12px;
    color: white;
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    border: none;
    cursor: pointer;
    font-size: 1.05rem;
    font-weight: 600;
    transition: 0.3s ease;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}
.button:hover {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
    transform: translateY(-1px);
}
.button:active {
    transform: translateY(0);
}

.forgot {
    margin-top: 20px;
    font-size: 0.9rem;
}
.forgot a {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
}
.forgot a:hover {
    text-decoration: underline;
    color: #1d4ed8;
}

.right {
    flex: 1.5;
    background-image: url('../../../img/wms1.png');
    background-size: cover;
    background-position: center;
    position: relative;
}
.right::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(30, 64, 175, 0.1);
    border-radius: 0 20px 20px 0;
}

@media (max-width: 900px) {
    .right {
      display: none;
    }
    .container {
        width: 90%;
        max-width: 450px;
        height: auto;
        min-height: 0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border-radius: 16px;
    }
    .left {
        flex: 1;
    }
    .card {
        max-width: 100%;
    }
    body {
        padding: 20px;
    }
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
        <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input type="text" id="email" name="email" placeholder="Email">
        </div>
        <div id="errorEmail" class="error-field"></div>

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
