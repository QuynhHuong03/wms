<?php
include_once(__DIR__ . "/../model/mRoles.php");

class CRoles {
    // Lấy tất cả roles
    public function getAllRoles() {
        $p = new MRoles();
        $roles = $p->SelectAllRoles(); // Trả về MongoResult hoặc false

        if ($roles === false) {
            return -1; // Lỗi kết nối hoặc query
        } elseif ($roles->num_rows > 0) {
            return $roles; // Trả về MongoResult
        } else {
            return 0; // Không có dữ liệu
        }
    }

    // Lấy role theo ID (string ObjectId hoặc role_id số)
    public function getRoles($id) {
        $p = new MRoles();
        $role = $p->SelectRoles($id);

        return $role ?: null; // Trả về mảng hoặc null
    }

    // Thêm role mới
    public function addRole($data) {
        $p = new MRoles();
        return $p->InsertRole($data);
    }

    // Cập nhật role
    public function updateRole($id, $data) {
        $p = new MRoles();
        return $p->UpdateRole($id, $data);
    }

    // Xóa role
    public function deleteRole($id) {
        $p = new MRoles();
        return $p->DeleteRole($id);
    }
}
?>
