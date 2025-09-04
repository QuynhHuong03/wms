<?php
include_once(__DIR__ . "/../model/mRoles.php");

class CRoles {
    public function getAllRoles() {
        $p = new MRoles();
        $tblCV = $p->SelectAllRoles();
        
        if (!$tblCV) {
            return -1; 
        } else {
            if ($tblCV->num_rows > 0) {
                return $tblCV; 
            } else { 
                return 0; 
            }
        }
    }

    // Lấy role theo mã
    public function getRoles($id) {
        $p = new MRoles();
        $tblChucVu = $p->SelectRoles($id);

        if ($tblChucVu) {
            return $tblChucVu; // Trả về mảng role
        } else {
            return null; // Không có role với id đó
        }
    }
    
 
    
}
?>
