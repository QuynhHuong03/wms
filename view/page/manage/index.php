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
/* Toast styles */
.toast-flash-wrapper { position:fixed; top:20px; right:20px; z-index:20000; display:flex; flex-direction:column; gap:10px; }
.toast-flash { min-width:300px; max-width:420px; padding:14px 18px; border-radius:10px; display:flex; align-items:center; gap:12px; font-weight:600; box-shadow:0 8px 24px -6px rgba(0,0,0,0.15); animation:slideIn .5s ease; }
.toast-flash.success { background:#10b981; color:#fff; }
.toast-flash.error { background:#ef4444; color:#fff; }
.toast-flash .icon { display:flex; width:30px; height:30px; align-items:center; justify-content:center; border-radius:50%; background:rgba(255,255,255,0.25); }
@keyframes slideIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:translateX(0); } }
.toast-hide { animation:fadeOut .4s forwards; }
@keyframes fadeOut { to { opacity:0; transform:translateX(40px); } }
</style>
</head>
<body>
<?php if (!empty($_SESSION['flash_success']) || !empty($_SESSION['flash_error'])): ?>
    <div class="toast-flash-wrapper" id="toastWrapper">
        <?php if(!empty($_SESSION['flash_success'])): ?>
            <div class="toast-flash success" role="alert">
                <div class="icon"><i class="fa-solid fa-check"></i></div>
                <div><?= htmlspecialchars($_SESSION['flash_success']); ?></div>
                <button type="button" class="btn-close btn-close-white" style="margin-left:auto; filter:invert(1);" aria-label="Đóng"></button>
            </div>
        <?php endif; unset($_SESSION['flash_success']); ?>
        <?php if(!empty($_SESSION['flash_error'])): ?>
            <div class="toast-flash error" role="alert">
                <div class="icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><?= htmlspecialchars($_SESSION['flash_error']); ?></div>
                <button type="button" class="btn-close btn-close-white" style="margin-left:auto; filter:invert(1);" aria-label="Đóng"></button>
            </div>
        <?php endif; unset($_SESSION['flash_error']); ?>
    </div>
    <script>
        // Auto hide after 4s and allow manual close
        document.querySelectorAll('.toast-flash').forEach(t => {
            const closeBtn = t.querySelector('.btn-close');
            let timer = setTimeout(() => { t.classList.add('toast-hide'); setTimeout(()=> t.remove(), 400); }, 4000);
            closeBtn.addEventListener('click', ()=> { clearTimeout(timer); t.classList.add('toast-hide'); setTimeout(()=> t.remove(), 400); });
        });
    </script>
<?php endif; ?>
<div class="container-flex">
    <?php include(__DIR__ . '/../../layout/sidebar.php'); ?>
    <div class="main-content">
        <?php include(__DIR__ . '/../../layout/header.php'); ?>

        <div class="content">
            <?php
            // Điều hướng page
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                
                // Tách page path và parameters
                $pageParts = explode('&', $page);
                $path = $pageParts[0]; // Lấy phần đầu tiên (path thực)

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
                    echo "<p>Đường dẫn: " . htmlspecialchars($path) . "</p>";
                    echo "<p>Full path: " . htmlspecialchars(__DIR__ . '/' . $path . ".php") . "</p>";
                    echo "<p>File exists: " . (file_exists(__DIR__ . '/' . $path . ".php") ? 'YES' : 'NO') . "</p>";
                    echo "<p>Current dir: " . htmlspecialchars(getcwd()) . "</p>";
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
