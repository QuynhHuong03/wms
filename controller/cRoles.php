<?php
include_once(__DIR__ . "/../model/mRoles.php");

class CRoles {
    // Lấy tất cả roles
    public function getAllRoles() {
        $p = new MRoles();
        $roles = $p->SelectAllRoles();

        if (!$roles) {
            return -1; // Lỗi kết nối
        } elseif (iterator_count($roles) > 0) {
            return $roles; // Trả về danh sách roles
        } else {
            return 0; // Không có roles
        }
    }

    // Lấy role theo ID
    public function getRoles($id) {
        $p = new MRoles();
        $role = $p->SelectRoles($id);

        return $role ?: null; // Trả về role hoặc null nếu không tìm thấy
    }
}
?>
