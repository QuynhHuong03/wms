<?php
include("../../../../controller/cUsers.php");
$p = new CUsers();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $tblNV = $p->getAllUserbyName($q);
    if ($tblNV && $tblNV->num_rows > 0) {
        while ($r = $tblNV->fetch_assoc()) {
            echo "<div style='padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;' 
                        onclick=\"window.location.href='index.php?page=users/updateUsers&id={$r['user_id']}'\">
                        {$r['fullname']} - {$r['email']}
                  </div>";
        }
    } else {
        echo "<div style='padding: 8px 12px; color: #999;'>Không tìm thấy nhân viên</div>";
    }
}
?>
