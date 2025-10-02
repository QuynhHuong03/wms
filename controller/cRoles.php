<?php
include_once(__DIR__ . "/../model/mRoles.php");

class CRoles {
    private $mRoles;

    public function __construct() {
        $this->mRoles = new MRoles();
    }

    // Lấy tất cả roles
    public function getAllRoles() {
        $result = $this->mRoles->SelectAllRoles();
        return $result;
    }

    // Lấy role theo id
    public function getRoleById($id) {
        return $this->mRoles->SelectRoles($id);
    }

    // Thêm role mới
    public function addRole($name, $description, $status, $create_at) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $col = $con->selectCollection('roles');

            // Tìm role_id cuối cùng dạng Rxxx
            $last = $col->findOne(
                ['role_id' => ['$regex' => '^R[0-9]+$']],
                ['sort' => ['role_id' => -1]]
            );
            $nextNumber = 1;
            if ($last && isset($last['role_id'])) {
                $num = (int)substr($last['role_id'], 1);
                $nextNumber = $num + 1;
            }
            $newRoleId = "R" . str_pad($nextNumber, 3, "0", STR_PAD_LEFT);

            $result = $this->mRoles->insertRole($newRoleId, $name, $description, $status, $create_at);
            return $result;
        }
        return false;
    }

    // Cập nhật role
    public function updateRole($id, $data) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $col = $con->selectCollection('roles');

            if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            } else {
                $filter = ['role_id' => $id];
            }

            $update = ['$set' => $data];
            $result = $col->updateOne($filter, $update);
            return $result->getModifiedCount() > 0;
        }
        return false;
    }

    // Xóa role
    public function deleteRole($id) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $col = $con->selectCollection('roles');

            if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            } else {
                $filter = ['role_id' => $id];
            }

            $result = $col->deleteOne($filter);
            return $result->getDeletedCount() > 0;
        }
        return false;
    }

    // Tìm kiếm role theo tên
    public function searchRolesByName($name) {
        return $this->mRoles->searchRolesByName($name);
    }
}
?>
