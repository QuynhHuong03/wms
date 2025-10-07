<?php
include_once("connect.php");

class MRoles {
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $this->col = $con->selectCollection("roles");
    }

    // Lấy tất cả vai trò
    public function SelectAllRoles() {
        try {
            $cursor = $this->col->find();
            $results = [];

            foreach ($cursor as $doc) {
                $results[] = [
                    "_id" => (string)$doc->_id,
                    "role_id" => $doc["role_id"],
                    "role_name" => $doc["role_name"],
                    "description" => $doc["description"]
                ];
            }

            return $results;
        } catch (Exception $e) {
            die("Lỗi query MongoDB: " . $e->getMessage());
        }
    }

    // Lấy vai trò theo id
    public function SelectRoles($id) {
        try {
            if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
            } elseif (is_numeric($id)) {
                $filter = ['role_id' => (int)$id];
            } else {
                $filter = ['role_id' => $id];
            }

            $doc = $this->col->findOne($filter);

            if ($doc) {
                return [
                    "_id" => (string)$doc->_id,
                    "role_id" => $doc["role_id"],
                    "role_name" => $doc["role_name"],
                    "description" => $doc["description"]
                ];
            }
            return false;
        } catch (Exception $e) {
            die("Lỗi query MongoDB: " . $e->getMessage());
        }
    }

    // Tìm kiếm vai trò theo tên
    public function searchRolesByName($name) {
        try {
            $cursor = $this->col->find(['role_name' => ['$regex' => $name, '$options' => 'i']]);
            $results = [];

            foreach ($cursor as $doc) {
                $results[] = [
                    "_id" => (string)$doc->_id,
                    "role_id" => $doc["role_id"],
                    "role_name" => $doc["role_name"],
                    "description" => $doc["description"]
                ];
            }

            return $results;
        } catch (Exception $e) {
            die("Lỗi query MongoDB: " . $e->getMessage());
        }
    }

    // Thêm vai trò mới
    public function insertRole($role_id, $role_name, $description, $status, $create_at) {
        try {
            $insertData = [
                "role_id"     => $role_id,
                "role_name"   => $role_name,
                "description" => $description,
                "status"      => (int)$status,
                "create_at"   => new MongoDB\BSON\UTCDateTime(strtotime($create_at) * 1000)
            ];

            $result = $this->col->insertOne($insertData);
            return $result->getInsertedCount() > 0;
        } catch (Exception $e) {
            die("Lỗi query MongoDB: " . $e->getMessage());
        }
    }
}
?>
