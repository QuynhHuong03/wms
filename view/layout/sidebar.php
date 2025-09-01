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
  .sidebar {
    width: 300px;
    background-color: #ffffff;
    color: black;
    min-height: 100vh;
    padding: 20px 10px;
    font-family: Arial, sans-serif;
    border-right: 1px solid #e5e7eb;
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
  .sidebar a:hover, .sidebar a.active {
    background-color: rgba(237, 244, 250, 1);
    color: blue;
  }
  .sidebar a i {
    margin-right: 10px;
  }
</style>

<div class="sidebar">
  <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:20px;">
    <img src="../../../img/logo1.png" alt="Logo" width="200" height="100">
    <h2 style="margin:0;"><b>Quản Lý Kho Hàng</b></h2>
  </div>

  <a href="index.php?page=quanly"><i class="fas fa-home"></i> Dashboard</a>

  <?php if (!empty($users['role_id']) && $users['role_id'] == 1): ?>
    <!-- Menu cho Admin -->
    <a href="index.php?page=quanly/quanlynhanvien"><i class="fas fa-users"></i> Quản lý người dùng</a>
    <a href="index.php?page=quanly/warehouse"><i class="fas fa-warehouse"></i> Quản lý kho</a>
    <a href="index.php?page=quanly/products"><i class="fas fa-tags"></i> Danh mục sản phẩm</a>
    <a href="index.php?page=quanly/report"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>
  <?php elseif ($users['role_id'] == 2): ?>
    <!-- Menu cho Quản lý kho tổng -->
    <a href="index.php?page=quanly/duyetphieunhap"><i class="fas fa-file-import"></i> Duyệt phiếu nhập kho</a>
    <a href="index.php?page=quanly/duyetphieuxuat"><i class="fas fa-file-export"></i> Duyệt phiếu xuất kho</a>
    <a href="index.php?page=quanly/tonkhochinhanh"><i class="fas fa-boxes"></i> tồn kho chi nhánh</a>
    <a href="index.php?page=quanly/products"><i class="fas fa-tags"></i> Danh mục sản phẩm</a>
    <a href="index.php?page=quanly/report"><i class="fas fa-chart-line"></i> Báo cáo thống kê</a>
  <?php elseif ($users['role_id'] == 3): ?>
    <!-- Menu cho Nhân viên kho tổng -->
    <a href="index.php?page=quanly/phieunhap"><i class="fas fa-file-import"></i> Tạo phiếu nhập kho tổng</a>
    <a href="index.php?page=quanly/phieuxuat"><i class="fas fa-file-export"></i> Tạo phiếu xuất kho tổng</a>
    <a href="index.php?page=quanly/tonkho"><i class="fas fa-boxes"></i> Xem tồn kho tổng</a>
    <a href="index.php?page=quanly/report"><i class="fas fa-chart-line"></i> Báo cáo tổng</a>
  <?php elseif ($users['role_id'] == 4): ?>
    <!-- Menu cho Quản lý kho chi nhánh -->
    <a href="index.php?page=quanly/duyetphieunhapchinhanh"><i class="fas fa-file-import"></i> Duyệt phiếu nhập kho chi nhánh</a>
    <a href="index.php?page=quanly/duyetphieuxuatchinhanh"><i class="fas fa-file-export"></i> Duyệt phiếu xuất kho chi nhánh</a>
    <a href="index.php?page=quanly/tonkhochinhanh"><i class="fas fa-boxes"></i> Xem tồn kho chi nhánh</a>
    <a href="index.php?page=quanly/reportchinhanh"><i class="fas fa-chart-line"></i> Báo cáo chi nhánh</a>
  <?php elseif ($users['role_id'] == 5): ?>
    <!-- Menu cho Nhân viên kho chi nhánh -->
    <a href="index.php?page=quanly/phieunhapchinhanh"><i class="fas fa-file-import"></i> Tạo phiếu nhập kho chi nhánh</a>
    <a href="index.php?page=quanly/phieuxuatchinhanh"><i class="fas fa-file-export"></i> Tạo phiếu xuất kho chi nhánh</a>
    <a href="index.php?page=quanly/tonkhochinhanh"><i class="fas fa-boxes"></i> Xem tồn kho chi nhánh</a>
  <?php endif; ?>

  <a href="index.php?page=hoso"><i class="fas fa-user"></i> Hồ sơ</a>
  <a href="../logout/index.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
</div>
