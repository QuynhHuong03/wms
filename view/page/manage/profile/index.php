<?php
//session_start();
include_once(__DIR__ . '/../../../../controller/cUsers.php');
$p = new CUsers();

if (isset($_GET['id'])) {
    $userId = $_GET['id'];
} elseif (isset($_SESSION['login']['user_id'])) {
    $userId = $_SESSION['login']['user_id'];
} else {
    echo "<p>Không tìm thấy nhân viên.</p>";
    exit;
}

$user = $p->getUserWithRole($userId);
if (!$user) {
    echo "<p>Không có dữ liệu nhân viên.</p>";
    exit;
}

// Ensure compatibility with how controller returns role info
if (isset($user['role']) && is_array($user['role'])) {
  $user['role_name'] = $user['role']['role_name'] ?? $user['role']['name'] ?? ($user['role']['role_id'] ?? 'Chưa gán');
}

$initial = strtoupper(substr($user['name'] ?? $user['user_id'], 0, 1));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Hồ sơ nhân viên</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(to right, #ece9e6, #ffffff);
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 900px;
      margin: 10px auto;
      background: #fff;
      border-radius: 15px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      padding: 30px;
    }

    .profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    background: #fff;
    display: block;
    margin: 0 auto 20px; /* căn giữa + cách dưới */
  }
  h2 {
    text-align: center;
    margin-bottom: 15px;
    color: #333;
  }
    /* Tabs kiểu underline */
    .tabs {
      display: flex;
      justify-content: center;
      border-bottom: 2px solid #ddd;
      margin-bottom: 25px;
    }
    .tab {
      padding: 12px 20px;
      cursor: pointer;
      font-weight: 500;
      color: #555;
      border-bottom: 3px solid transparent;
      transition: all 0.3s ease;
      margin: 0 15px;
    }
    .tab:hover {
      color: #000;
    }
    .tab.active {
      color: #4a90e2;
      border-bottom: 3px solid #4a90e2;
    }
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
      animation: fadeIn 0.4s ease-in-out;
    }
    @keyframes fadeIn {
      from {opacity: 0;}
      to {opacity: 1;}
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
    }
    label {
      margin-bottom: 6px;
      font-weight: 600;
      color: #444;
    }
    input {
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.3s;
    }
    select {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 14px;
  transition: border-color 0.3s;
  background: #fff;
}
select:focus {
  border-color: #4a90e2;
  outline: none;
}

    input:focus {
      border-color: #4a90e2;
      outline: none;
    }
    input[disabled] {
      background: #f5f5f5;
      color: #888;
      cursor: not-allowed;
    }
    .btn {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 20px;
      background: #4a90e2;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: not-allowed;
      font-size: 15px;
      font-weight: bold;
      opacity: 0.6;
      transition: all 0.3s ease;
    }
    .btn.enabled {
      cursor: pointer;
      opacity: 1;
    }
    .btn:hover.enabled {
      background: #357ABD;
    }
    .password-wrapper {
  position: relative;
}
.password-wrapper input {
  padding-right: 40px; /* chừa chỗ cho icon */
}
.toggle-password {
  position: absolute;
  right: 10px;
  top: 45px;
  cursor: pointer;
  font-size: 16px;
  color: #888;
}
.toggle-password:hover {
  color: #333;
}

  </style>
</head>
<body>
  <div class="container">
    <h2>Hồ sơ nhân viên</h2>
    <!-- <img 
        src="<?= isset($user['avatar']) && $user['avatar'] != '' 
                  ? htmlspecialchars($user['avatar']) 
                  : 'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&background=3b82f6&color=fff&size=128' ?>" 
        alt="Avatar" 
        class="profile-avatar"> -->

    <div class="tabs">
      <div class="tab active" data-tab="info">Thông tin tài khoản</div>
      <div class="tab" data-tab="password">Quản lý mật khẩu</div>
    </div>

    <!-- Tab 1: Thông tin tài khoản -->
    <div class="tab-content active" id="info">
      <form id="profileForm">
        <div class="form-grid">
          <div class="form-group">
            <label>Mã nhân viên</label>
            <input type="text" value="<?= htmlspecialchars($user['user_id']) ?>" disabled>
          </div>
          <div class="form-group">
            <label>Họ và tên</label>
            <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
          </div>
          <div class="form-group">
            <label>Số điện thoại</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Giới tính</label>
            <select name="gender">
              <option value="1" <?= ($user['gender'] ?? 0) == 1 ? 'selected' : '' ?>>Nam</option>
              <option value="0" <?= ($user['gender'] ?? 0) == 0 ? 'selected' : '' ?>>Nữ</option>
            </select>
          </div>
          <div class="form-group">
            <label>Vai trò</label>
            <input type="text" value="<?= htmlspecialchars($user['role_name'] ?? 'Chưa gán') ?>" disabled>
          </div>
          <div class="form-group">
            <label>Kho</label>
            <input type="text" value="<?= htmlspecialchars($user['warehouse_id'] ?? 'Không có') ?>" disabled>
          </div>
          <div class="form-group">
            <label>Trạng thái</label>
            <input type="text" value="<?= ($user['status'] ?? 0) == 1 ? 'Đang hoạt động' : 'Ngừng hoạt động' ?>" disabled>
          </div>
        </div>
  <button type="submit" id="updateBtn" class="btn" disabled>Cập nhật thông tin</button>
      </form>
    </div>

    <!-- Tab 2: Quản lý mật khẩu -->
<div class="tab-content" id="password">
  <form id="passwordForm">
    <div class="form-group password-wrapper">
      <label>Mật khẩu hiện tại *</label>
      <input type="password" name="current_password" required>
      <span class="toggle-password fa fa-eye" onclick="togglePassword(this)"></span>
    </div>
    <div class="form-group password-wrapper">
      <label>Mật khẩu mới * (ít nhất 6 ký tự)</label>
      <input type="password" name="new_password" minlength="6" required>
      <span class="toggle-password fa fa-eye" onclick="togglePassword(this)"></span>
    </div>
    <div class="form-group password-wrapper">
      <label>Xác nhận mật khẩu mới *</label>
      <input type="password" name="confirm_password" minlength="6" required>
      <span class="toggle-password fa fa-eye" onclick="togglePassword(this)"></span>
    </div>
    <div style="color: #666; font-size: 13px; margin-bottom: 15px;">
      <strong>Lưu ý:</strong> Mật khẩu mới phải có ít nhất 6 ký tự và khác với mật khẩu hiện tại.
    </div>
    <button type="submit" class="btn enabled">Đổi mật khẩu</button>
  </form>
</div>

  </div>

  <script>
    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.tab).classList.add('active');
      });
    });

    // Enable update button when form changes
    const profileForm = document.getElementById('profileForm');
    const updateBtn = document.getElementById('updateBtn');

    profileForm.addEventListener('input', () => {
      updateBtn.classList.add('enabled');
      updateBtn.removeAttribute('disabled');
    });

    // Cập nhật thông tin tài khoản
profileForm.addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(profileForm);
  formData.append('action', 'updateProfile');

  fetch('profile/process.php', {
    method: 'POST',
    body: formData
  }).then(res => res.json()).then(data => {
    alert(data.message);
    if (data.status === 'success') {
      updateBtn.classList.remove('enabled');
    }
    if (data.redirect) {
      window.location.href = data.redirect;
    }
  });
});

// Đổi mật khẩu
const passwordForm = document.getElementById('passwordForm');
passwordForm.addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Validate form
  const currentPassword = this.current_password.value.trim();
  const newPassword = this.new_password.value.trim();
  const confirmPassword = this.confirm_password.value.trim();

  if (!currentPassword || !newPassword || !confirmPassword) {
    alert('Vui lòng điền đầy đủ thông tin.');
    return;
  }

  if (newPassword.length < 6) {
    alert('Mật khẩu mới phải có ít nhất 6 ký tự.');
    return;
  }

  if (newPassword !== confirmPassword) {
    alert('Mật khẩu xác nhận không khớp.');
    return;
  }

  const formData = new FormData(passwordForm);
  formData.append('action', 'changePassword');

  fetch('profile/process.php', {
    method: 'POST',
    body: formData
  }).then(res => res.json()).then(data => {
    // Show message and any debug info returned by the backend to help troubleshooting
  let alertMsg = data.message || 'No message returned.';
    if (data.debug) {
      try {
        alertMsg += '\n\nDebug: ' + JSON.stringify(data.debug);
      } catch (e) {
        alertMsg += '\n\nDebug available (could not stringify).';
      }
    }
    alert(alertMsg);
    if (data.status === 'success') {
      passwordForm.reset();
    }
    if (data.redirect) {
      // Give user a tiny moment to read the success message before redirect
      setTimeout(() => window.location.href = data.redirect, 700);
    }
  }).catch(error => {
    alert('Có lỗi xảy ra khi đổi mật khẩu.');
    console.error('Error:', error);
  });
});

function togglePassword(el) {
  const input = el.previousElementSibling; // lấy input ngay trước span
  if (input.type === "password") {
    input.type = "text";
    el.classList.remove("fa-eye");
    el.classList.add("fa-eye-slash");
  } else {
    input.type = "password";
    el.classList.remove("fa-eye-slash");
    el.classList.add("fa-eye");
  }
}

  </script>
</body>
</html>