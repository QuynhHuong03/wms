<?php
// Nếu chưa đăng nhập thì quay lại login
if (!isset($_SESSION["login"]) || empty($_SESSION["login"])) {
    header("Location: index.php?page=login");
    exit();
}

$users = $_SESSION["login"];
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
  body {
    margin: 0;
    font-family: Arial, sans-serif;
  }

  /* Sidebar mặc định */
  .sidebar {
    width: 300px;
    background-color: #ffffff;
    color: black;
    min-height: 100vh;
    padding: 20px 10px;
    border-right: 1px solid #e5e7eb;
    transition: transform 0.3s ease;
  }

  .sidebar h2 {
    font-size: 20px;
    margin-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 10px;
    padding-top: 10px;
  }

  .sidebar a {
    display: block;
    padding: 10px 15px;
    color: #4b5563;
    font-size: 18px;
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

  /* Responsive cho mobile */
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
</style>

<!-- Nút mở menu -->
<button class="menu-toggle" id="menuToggle">
  <i class="fas fa-bars"></i>
</button>

<!-- Overlay -->
<div class="overlay" id="overlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:20px;">
    <img src="../../../img/logo1.png" alt="Logo" width="200" height="100">
    <h2 style="margin:0;"><b>Quản Lý Kho Hàng</b></h2>
  </div>

  <a href="index.php?page=dashboard"><i class="fas fa-home"></i> Dashboard</a>

  <?php if (!empty($users['role_id']) && $users['role_id'] == 1): ?>
    <!-- Menu cho Admin -->
    <a href="index.php?page=users"><i class="fas fa-users"></i> Quản lý người dùng</a>
    <a href="index.php?page=roles"><i class="fas fa-users"></i> Quản lý vai trò</a>
    <a href="index.php?page=warehouse"><i class="fas fa-warehouse"></i> Quản lý kho</a>
    <a href="index.php?page=warehouse_type"><i class="fas fa-warehouse"></i> Quản lý loại kho</a>
    <a href="index.php?page=supplier"><i class="fas fa-box"></i> Quản lý nhà cung cấp</a>
    <a href="index.php?page=categories"><i class="fas fa-tags"></i> Danh mục sản phẩm</a>
    <a href="index.php?page=products"><i class="fas fa-product-hunt"></i> Quản lý sản phẩm</a>
    <a href="index.php?page=report"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>

  <?php elseif ($users['role_id'] == 2): ?>
    <!-- Menu cho Quản lý kho tổng -->
    <a href="index.php?page=duyetphieunhap"><i class="fas fa-file-import"></i> Duyệt phiếu nhập kho</a>
    <a href="index.php?page=duyetphieuxuat"><i class="fas fa-file-export"></i> Duyệt phiếu xuất kho</a>
    <a href="index.php?page=tonkhochinhanh"><i class="fas fa-boxes"></i> Tồn kho chi nhánh</a>
    <a href="index.php?page=products"><i class="fas fa-tags"></i> Danh mục sản phẩm</a>
    <a href="index.php?page=report"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>

  <?php elseif ($users['role_id'] == 3): ?>
    <!-- Menu cho Nhân viên kho tổng -->
    <a href="index.php?page=phieunhap"><i class="fas fa-file-import"></i> Tạo phiếu nhập kho tổng</a>
    <a href="index.php?page=phieuxuat"><i class="fas fa-file-export"></i> Tạo phiếu xuất kho tổng</a>
    <a href="index.php?page=tonkho"><i class="fas fa-boxes"></i> Xem tồn kho tổng</a>
    <a href="index.php?page=report"><i class="fas fa-chart-line"></i> Báo cáo tổng</a>

  <?php elseif ($users['role_id'] == 4): ?>
    <!-- Menu cho Quản lý kho chi nhánh -->
    <a href="index.php?page=duyetphieunhapchinhanh"><i class="fas fa-file-import"></i> Duyệt phiếu nhập kho chi nhánh</a>
    <a href="index.php?page=duyetphieuxuatchinhanh"><i class="fas fa-file-export"></i> Duyệt phiếu xuất kho chi nhánh</a>
    <a href="index.php?page=tonkhochinhanh"><i class="fas fa-boxes"></i> Xem tồn kho chi nhánh</a>
    <a href="index.php?page=reportchinhanh"><i class="fas fa-chart-line"></i> Báo cáo chi nhánh</a>

  <?php elseif ($users['role_id'] == 5): ?>
    <!-- Menu cho Nhân viên kho chi nhánh -->
    <a href="index.php?page=phieunhapchinhanh"><i class="fas fa-file-import"></i> Tạo phiếu nhập kho chi nhánh</a>
    <a href="index.php?page=phieuxuatchinhanh"><i class="fas fa-file-export"></i> Tạo phiếu xuất kho chi nhánh</a>
    <a href="index.php?page=tonkhochinhanh"><i class="fas fa-boxes"></i> Xem tồn kho chi nhánh</a>
  <?php endif; ?>

  <a href="index.php?page=hoso"><i class="fas fa-user"></i> Hồ sơ</a>
  <a href="../logout/index.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
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
</script>
