<?php
session_start();
session_destroy();

// quay về trang login
header("Location: ../login/index.php"); 
exit();
?>
