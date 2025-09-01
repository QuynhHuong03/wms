<?php
session_start();
ob_start();

// Kiểm tra nếu chưa đăng nhập
if (!isset($_SESSION["login"])) {
    header("Location: ../view/page/login/index.php");
    exit();
}

// Lấy thông tin user
$user = $_SESSION["login"];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>WMS</title>
<link rel="icon" type="image/png" href="../../../img/logo1.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
body, html {
    margin:0; 
    padding:0; 
    height:100%; 
    font-family: Arial, sans-serif;
}
.container-flex {
    display:flex; 
    height:100vh;
}
.main-content {
    display:flex; 
    flex-direction:column; 
    flex:1;
}
.content {
    padding:20px; 
    flex:1; 
    overflow-y:auto; 
    background-color:#f9fafb;
}
</style>
</head>
<body>
<div class="container-flex">
    <?php include('../../layout/sidebar.php'); 
    ?>
    <div class="main-content">
        <?php include('../../layout/header.php'); ?>
        <div class="content">
            <h2>Dashboard</h2>
            <p>Nội dung chính hiển thị ở đây...</p>
        </div>
    </div>
</div>
</body>
</html>
