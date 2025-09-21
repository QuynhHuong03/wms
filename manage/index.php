<?php
session_start();
ob_start();

// Nếu chưa đăng nhập thì về login
if (!isset($_SESSION["login"]) || empty($_SESSION["login"])) {
    header("Location: ../login/index.php");
    exit();
}

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
    <?php include(__DIR__ . '/../../layout/sidebar.php'); ?>
    <div class="main-content">
        <?php include(__DIR__ . '/../../layout/header.php'); ?>

        <div class="content">
            <?php
            // Điều hướng page
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                $path = $page;

                // Nếu là thư mục thì load index.php
                if (is_dir($path) && file_exists($path . "/index.php")) {
                    include($path . "/index.php");
                }
                // Nếu là file
                elseif (file_exists($path . ".php")) {
                    include($path . ".php");
                }
                else {
                    echo "<h3>Trang không tồn tại!</h3>";
                }
            } else {
                // Trang mặc định khi mới vào
                include("dashboard/index.php");
            }
            ?>
        </div>
    </div>
</div>
</body>
</html>
