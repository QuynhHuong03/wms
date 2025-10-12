<?php
// Nếu chưa đăng nhập thì quay lại login
if (!isset($_SESSION["login"]) || empty($_SESSION["login"])) {
    header("Location: index.php?page=login");
    exit();
}

$users = $_SESSION["login"];

// Kiểm tra nếu $users không phải là array (có thể là __PHP_Incomplete_Class)
if (!is_array($users)) {
    // Xóa session và redirect về login
    unset($_SESSION["login"]);
    header("Location: index.php?page=login");
    exit();
}

$currentPage = $_GET['page'] ?? '';
$roleId = $users['role_id'] ?? 0;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  body {
    margin: 0;
    font-family: Arial, sans-serif;
  }

  .sidebar {
    width: 300px;
    background-color: #ffffff;
    color: black;
    min-height: 100vh;
    padding: 20px 10px;
    border-right: 1px solid #e5e7eb;
    transition: transform 0.3s ease;
  }

  .sidebar h3 {
    font-size: 18px;
    margin-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 10px;
    padding-top: 10px;
  }

  .sidebar a {
    display: block;
    padding: 10px 15px;
    color: #4b5563;
    font-size: 16px;
    text-decoration: none;
    border-radius: 6px;
    margin-bottom: 5px;
    transition: 0.2s;
  }

  .sidebar a:hover,
  .sidebar a.active {
    background-color: rgba(237, 244, 250, 1);
    color: blue;
  }

  .sidebar a i {
    margin-right: 10px;
  }

   /* Footer user info */
  .sidebar-footer {
    margin-top: auto;
    border-top: 1px solid #e5e7eb;
    padding: 15px 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f9fafb;
  }

  /* Nút hamburger */
  .menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    background: #1e3a8a;
    color: white;
    border: none;
    padding: 10px 14px;
    font-size: 20px;
    cursor: pointer;
    z-index: 1001;
    border-radius: 6px;
  }

  /* Overlay khi mở sidebar */
  .overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 999;
  }
  .submenu .submenu-items {
  display: none;
  padding-left: 20px;
  }
  .submenu .submenu-items a {
    font-size: 15px;
    padding: 8px 15px;
  }
  .submenu.open .submenu-items {
    display: block;
  }
  .submenu-toggle {
    /* cursor: pointer; */
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-right: 5px;
  }
  .submenu-toggle .arrow {
  margin-left: 15px; /* đẩy mũi tên cách chữ ra xa */
  font-size: 12px;
  transition: transform 0.3s ease;
  }
  .arrow {
  font-size: 12px;
  transition: transform 0.3s ease;
  }
  .submenu.open .arrow {
    transform: rotate(180deg);
  }

  @media (max-width: 768px) {
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      transform: translateX(-100%);
      z-index: 1000;
    }

    .sidebar.active {
      transform: translateX(0);
    }

    .menu-toggle {
      display: block;
    }

    .overlay.active {
      display: block;
    }
  }
  .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    background: #fff;
    /* display: block; */
  }
  .user-info {
    display: flex;
    align-items: center;
    flex: 1;
    margin-left: 10px;
    overflow: hidden;
  }

  .user-info span {
    font-size: 14px;
    color: #374151;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
  }
</style>

<!-- Nút mở menu -->
<button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:10px;">
    <img src="../../../img/logo1.png" alt="Logo" width="200" height="100">
    <h3 style="margin:0;">Quản Lý Kho Hàng</h3>
  </div>

  <a href="index.php?page=dashboard" class="<?= $currentPage=='dashboard'?'active':'' ?>">
    <i class="fas fa-home"></i> Dashboard
  </a>

  <?php if ($roleId == 1): ?>
    <!-- Admin -->
    <div class="submenu" id="submenu-user">
      <a href="javascript:void(0)" class="submenu-toggle">
        <span><i class="fas fa-users"></i> Quản lý người dùng</span>
        <i class="fas fa-chevron-down arrow"></i>
      </a>
      <div class="submenu-items">
        <a href="index.php?page=users" class="<?= $currentPage=='users'?'active':'' ?>"><i class="fas fa-user"></i> Tài khoản</a>
        <a href="index.php?page=roles" class="<?= $currentPage=='roles'?'active':'' ?>"><i class="fas fa-user-shield"></i> Vai trò</a>
      </div>
    </div>

    <a href="index.php?page=warehouse" class="<?= $currentPage=='warehouse'?'active':'' ?>"><i class="fas fa-warehouse"></i> Quản lý kho</a>
    <!-- <a href="index.php?page=warehouse_type" class="<?= $currentPage=='warehouse_type'?'active':'' ?>"><i class="fas fa-warehouse"></i> Loại kho</a> -->
    <a href="index.php?page=supplier" class="<?= $currentPage=='supplier'?'active':'' ?>"><i class="fas fa-truck"></i> Nhà cung cấp</a>
    <div class="submenu" id="submenu-product">
  <a href="javascript:void(0)" class="submenu-toggle">
    <span><i class="fas fa-boxes"></i> Quản lý sản phẩm</span>
    <i class="fas fa-chevron-down arrow"></i>
  </a>
  <div class="submenu-items">
    <a href="index.php?page=categories" class="<?= $currentPage=='categories'?'active':'' ?>">
      <i class="fas fa-tags"></i> Danh mục sản phẩm
    </a>
    <a href="index.php?page=products" class="<?= $currentPage=='products'?'active':'' ?>">
      <i class="fas fa-box"></i> Sản phẩm
    </a>
  </div>
</div>

    <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>

  <?php elseif ($roleId == 2): ?>
    <!-- Quản lý kho tổng -->
    <a href="index.php?page=receipts/approve" class="<?= $currentPage=='duyetphieunhap'?'active':'' ?>"><i class="fas fa-file-import"></i> Duyệt phiếu nhập kho</a>
    <a href="index.php?page=duyetphieuxuat" class="<?= $currentPage=='duyetphieuxuat'?'active':'' ?>"><i class="fas fa-file-export"></i> Duyệt phiếu xuất kho</a>
    <a href="index.php?page=tonkhochinhanh" class="<?= $currentPage=='tonkhochinhanh'?'active':'' ?>"><i class="fas fa-boxes"></i> Tồn kho chi nhánh</a>
    <a href="index.php?page=products" class="<?= $currentPage=='products'?'active':'' ?>"><i class="fas fa-tags"></i> Danh mục sản phẩm</a>
    <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>

  <?php elseif ($roleId == 3): ?>
    <!-- Nhân viên kho tổng -->
    <a href="index.php?page=receipts" class="<?= $currentPage=='receipts'?'active':'' ?>"><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho tổng</a>
    <a href="index.php?page=phieuxuat" class="<?= $currentPage=='phieuxuat'?'active':'' ?>"><i class="fas fa-file-export"></i> Tạo phiếu xuất kho tổng</a>
    <a href="index.php?page=tonkho" class="<?= $currentPage=='tonkho'?'active':'' ?>"><i class="fas fa-boxes"></i> Xem tồn kho tổng</a>
    <a href="index.php?page=report" class="<?= $currentPage=='report'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo tổng</a>

  <?php elseif ($roleId == 4): ?>
    <!-- Quản lý kho chi nhánh -->
    <a href="index.php?page=duyetphieunhapchinhanh" class="<?= $currentPage=='duyetphieunhapchinhanh'?'active':'' ?>"><i class="fas fa-file-import"></i> Duyệt phiếu nhập kho chi nhánh</a>
    <a href="index.php?page=duyetphieuxuatchinhanh" class="<?= $currentPage=='duyetphieuxuatchinhanh'?'active':'' ?>"><i class="fas fa-file-export"></i> Duyệt phiếu xuất kho chi nhánh</a>
    <a href="index.php?page=tonkhochinhanh" class="<?= $currentPage=='tonkhochinhanh'?'active':'' ?>"><i class="fas fa-boxes"></i> Xem tồn kho chi nhánh</a>
    <a href="index.php?page=reportchinhanh" class="<?= $currentPage=='reportchinhanh'?'active':'' ?>"><i class="fas fa-chart-line"></i> Báo cáo chi nhánh</a>

  <?php elseif ($roleId == 5): ?>
    <!-- Nhân viên kho chi nhánh -->
    <a href="index.php?page=phieunhapchinhanh" class="<?= $currentPage=='phieunhapchinhanh'?'active':'' ?>"><i class="fas fa-file-import"></i> Tạo phiếu nhập kho chi nhánh</a>
    <a href="index.php?page=phieuxuatchinhanh" class="<?= $currentPage=='phieuxuatchinhanh'?'active':'' ?>"><i class="fas fa-file-export"></i> Tạo phiếu xuất kho chi nhánh</a>
    <a href="index.php?page=tonkhochinhanh" class="<?= $currentPage=='tonkhochinhanh'?'active':'' ?>"><i class="fas fa-boxes"></i> Xem tồn kho chi nhánh</a>
  <?php endif; ?>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="index.php?page=profile" style="display:flex; align-items:center; text-decoration:none; color:#111; flex:1;">
      <img 
        src="<?= isset($users['avatar']) && $users['avatar'] != '' 
                  ? htmlspecialchars($users['avatar']) 
                  : 'https://ui-avatars.com/api/?name=' . urlencode($users['name'] ?? 'User') . '&background=3b82f6&color=fff&size=128' ?>" 
        alt="Avatar" 
        class="avatar"
        style="cursor:pointer; margin-right:10px;">
      <div class="user-info">
        <span><?= htmlspecialchars($users['email'] ?? '') ?></span>
      </div>
    </a>
    <a href="../logout/index.php" style="color:#4b5563; margin-left:10px; font-size:18px;">
      <i class="fas fa-sign-out-alt"></i>
    </a>
  </div>
</div>

<script>
  const menuToggle = document.getElementById("menuToggle");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");

  menuToggle.addEventListener("click", () => {
    sidebar.classList.toggle("active");
    overlay.classList.toggle("active");
  });
  overlay.addEventListener("click", () => {
    sidebar.classList.remove("active");
    overlay.classList.remove("active");
  });

  // Toggle submenu + nhớ trạng thái
  document.querySelectorAll(".submenu-toggle").forEach(toggle => {
    toggle.addEventListener("click", (e) => {
      e.preventDefault();
      const parent = toggle.closest(".submenu");
      const id = parent.id;
      parent.classList.toggle("open");
      localStorage.setItem("submenu_" + id, parent.classList.contains("open") ? "open" : "closed");
    });
  });

  // Khôi phục trạng thái submenu khi load
  window.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".submenu").forEach(menu => {
      const id = menu.id;
      if (localStorage.getItem("submenu_" + id) === "open") {
        menu.classList.add("open");
      }
      if (menu.querySelector(".submenu-items a.active")) {
        menu.classList.add("open");
      }
    });
  });
</script>
