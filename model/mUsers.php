<?php
require_once "connect.php";

class MUsers {
    private $usersCollection;
    private $rolesCollection;
    private $connObj;

    public function __construct() {
        // Hỗ trợ cả Connect->getCollection(...) hoặc clsKetNoi->moKetNoi()
        if (class_exists('Connect')) {
            $conn = new Connect();
            $this->connObj = $conn;
            if (method_exists($conn, 'getCollection')) {
                $this->usersCollection = $conn->getCollection('users');
                $this->rolesCollection = $conn->getCollection('roles');
                return;
            } elseif (method_exists($conn, 'moKetNoi')) {
                $db = $conn->moKetNoi();
                $this->usersCollection = $db->selectCollection('users');
                $this->rolesCollection = $db->selectCollection('roles');
                return;
            } else {
                throw new \Exception("Connect không hỗ trợ getCollection hoặc moKetNoi");
            }
        }

        if (class_exists('clsKetNoi')) {
            $conn = new clsKetNoi();
            $db = $conn->moKetNoi();
            $this->connObj = $conn;
            $this->usersCollection = $db->selectCollection('users');
            $this->rolesCollection = $db->selectCollection('roles');
            return;
        }

        throw new \Exception('Không tìm thấy lớp Connect hoặc clsKetNoi trong connect.php');
    }

    // Chuyển BSONDocument / BSONArray -> array PHP, normalize ObjectId và số
    private function docToArray($doc) {
        if ($doc === null) return null;
        if ($doc instanceof \MongoDB\Model\BSONDocument || $doc instanceof \MongoDB\Model\BSONArray) {
            $arr = json_decode(json_encode($doc), true);
        } elseif (is_array($doc)) {
            $arr = $doc;
        } else {
            return $doc;
        }
        return $this->normalizeBson($arr);
    }

    private function normalizeBson($v) {
        if (is_array($v)) {
            // Nếu là object đặc biệt MongoDB (ví dụ { "$oid": "..." } )
            if (count($v) === 1) {
                if (isset($v['$oid'])) return (string)$v['$oid'];
                if (isset($v['$numberInt'])) return (int)$v['$numberInt'];
                if (isset($v['$numberLong'])) return (int)$v['$numberLong'];
                if (isset($v['$numberDouble'])) return (float)$v['$numberDouble'];
            }
            $out = [];
            foreach ($v as $k => $val) $out[$k] = $this->normalizeBson($val);
            return $out;
        }
        return $v;
    }

    // Lấy tất cả users (array of associative arrays) và gắn role_name
    public function SelectAllUsers() {
        try {
            $cursor = $this->usersCollection->find();
            $users = [];
            foreach ($cursor as $doc) $users[] = $this->docToArray($doc);

            // Load roles 1 lần thành map để tránh query nhiều lần
            $rolesCursor = $this->rolesCollection->find();
            $rolesByRoleId = [];
            $rolesByOid = [];
            foreach ($rolesCursor as $r) {
                $ra = $this->docToArray($r);
                if (isset($ra['role_id'])) $rolesByRoleId[(string)$ra['role_id']] = $ra;
                if (isset($ra['_id'])) $rolesByOid[(string)$ra['_id']] = $ra;
            }

            // Gắn role_name cho từng user
            foreach ($users as &$u) {
                $roleName = null;
                if (isset($u['role_id'])) {
                    $rid = (string)$u['role_id'];
                    if (isset($rolesByRoleId[$rid])) {
                        $roleName = $rolesByRoleId[$rid]['role_name'] ?? null;
                    } elseif (isset($rolesByOid[$rid])) {
                        $roleName = $rolesByOid[$rid]['role_name'] ?? null;
                    } else {
                        // fallback: tìm trong collection roles
                        if (preg_match('/^[0-9a-fA-F]{24}$/', $rid)) {
                            $found = $this->rolesCollection->findOne(['_id' => new \MongoDB\BSON\ObjectId($rid)]);
                        } else {
                            $found = $this->rolesCollection->findOne(['role_id' => is_numeric($rid) ? (int)$rid : $rid]);
                        }
                        if ($found) {
                            $fr = $this->docToArray($found);
                            $roleName = $fr['role_name'] ?? null;
                        }
                    }
                }
                $u['role_name'] = $roleName;
            }

            return $users;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi SelectAllUsers: ' . $e->getMessage());
        }
    }

    // Đăng nhập: trả về mảng user ngắn hoặc false / "inactive"
    public function login($email, $password) {
        try {
            $doc = $this->usersCollection->findOne(['email' => $email]);
            if (!$doc) return false;
            $user = $this->docToArray($doc);

            if (isset($user['status']) && (int)$user['status'] === 0) return "inactive";

            $stored = $user['password'] ?? null;
            if ($stored === null) return false;

            // Nếu hash: password_verify; nếu không thì so sánh plain fallback
            if (password_verify($password, $stored) || $password === $stored) {
                return [
                    "user_id"      => $user["user_id"] ?? ($user["_id"] ?? null),
                    "email"        => $user["email"] ?? null,
                    "role_id"      => $user["role_id"] ?? null,
                    "warehouse_id" => $user["warehouse_id"] ?? null
                ];
            }
            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi login: ' . $e->getMessage());
        }
    }

    // Lấy user theo ID (hỗ trợ user_id numeric hoặc _id ObjectId)
    public function SelectUserById($id) {
        try {
            if (is_string($id) && preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            } elseif (is_numeric($id)) {
                $filter = ['user_id' => (int)$id];
            } else {
                $filter = ['user_id' => $id];
            }

            $doc = $this->usersCollection->findOne($filter);
            if (!$doc) return false;
            $user = $this->docToArray($doc);

            // Gắn role_name
            $roleName = null;
            if (isset($user['role_id'])) {
                $rid = (string)$user['role_id'];
                if (preg_match('/^[0-9a-fA-F]{24}$/', $rid)) {
                    $found = $this->rolesCollection->findOne(['_id' => new \MongoDB\BSON\ObjectId($rid)]);
                } else {
                    $found = $this->rolesCollection->findOne(['role_id' => is_numeric($rid) ? (int)$rid : $rid]);
                }
                if ($found) {
                    $fr = $this->docToArray($found);
                    $roleName = $fr['role_name'] ?? null;
                }
            }
            $user['role_name'] = $roleName;

            return $user;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi SelectUserById: ' . $e->getMessage());
        }
    }

    // Tìm user theo email
    public function findUserByEmail($email) {
        try {
            $doc = $this->usersCollection->findOne(['email' => $email]);
            return $doc ? $this->docToArray($doc) : null;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi findUserByEmail: ' . $e->getMessage());
        }
    }

        // Thêm user mới
    public function addUser($name, $email, $gender, $phone, $password, $role_id, $status, $warehouse_id) {
    try {
        // Lấy user_id cuối cùng dạng NVxxx, sắp xếp giảm dần
        $lastUser = $this->usersCollection->findOne(
            ['user_id' => ['$regex' => '^NV[0-9]+$']],
            ['sort' => ['user_id' => -1]]
        );

        // Tính số tiếp theo
        $nextNumber = 1;
        if ($lastUser && isset($lastUser['user_id'])) {
            $lastCode = $lastUser['user_id']; // VD: NV002
            $num = (int)substr($lastCode, 2); // lấy số: 2
            $nextNumber = $num + 1;
        }

        $newUserId = "NV" . str_pad($nextNumber, 3, "0", STR_PAD_LEFT);

        // Hash mật khẩu trước khi lưu
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Dữ liệu insert
        $insertData = [
            "user_id"      => $newUserId,
            "name"         => $name,
            "email"        => $email,
            "gender"       => $gender,
            "phone"        => $phone,
            "password"     => $hashedPassword,
            "role_id"      => $role_id,
            "status"       => (int)$status,
            "warehouse_id" => $warehouse_id,
            "created_at"   => new \MongoDB\BSON\UTCDateTime()
        ];

        // Thêm vào collection
        $result = $this->usersCollection->insertOne($insertData);

        return $result->getInsertedCount() > 0;

    } catch (\Throwable $e) {
        error_log("AddUser error: " . $e->getMessage());
        return false;
    }
}


public function updateUserById($id, $data) {
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    if ($con) {
        try {
            $col = $con->selectCollection('users');
            $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
            $update = ['$set' => $data];
            $result = $col->updateOne($filter, $update);
            $p->dongKetNoi($con);
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            $p->dongKetNoi($con);
            die("Lỗi update MongoDB: " . $e->getMessage());
        }
    }
    return false;
}

public function deleteUser($id) {
    try {
        $filter = [];

        // Nếu là ObjectId hợp lệ thì xóa theo _id
        if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
            $filter['_id'] = new \MongoDB\BSON\ObjectId($id);
        } else {
            // Nếu không, xóa theo user_id
            $filter['user_id'] = $id;
        }

        $result = $this->usersCollection->deleteOne($filter);

        // Trả về true nếu xóa thành công ít nhất 1 document
        return $result->getDeletedCount() > 0;

    } catch (\Throwable $e) {
        error_log("deleteUser error: " . $e->getMessage());
        return false;
    }
}



}
?>