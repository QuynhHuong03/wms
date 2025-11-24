<?php
require_once "connect.php";

class MUsers {
    private $usersCollection;
    private $rolesCollection;
    private $connObj;

    public function __construct() {
        if (class_exists('Connect')) {
            $conn = new Connect();
            $this->connObj = $conn;
            $this->usersCollection = $conn->getCollection("users");
            $this->rolesCollection = $conn->getCollection("roles");
        } elseif (class_exists('clsKetNoi')) {
            $conn = new clsKetNoi();
            $this->connObj = $conn;
            $db = $conn->moKetNoi();
            $this->usersCollection = $db->users;
            $this->rolesCollection = $db->roles;
        } else {
            throw new Exception("Không tìm thấy class kết nối MongoDB");
        }
    }

    /* ==========================
       LẤY THÔNG TIN NGƯỜI DÙNG
    ========================== */

    // Lấy user theo email
    public function findUserByEmail($email) {
        try {
            return $this->usersCollection->findOne(['email' => $email]);
        } catch (\Throwable $e) {
            error_log("findUserByEmail error: " . $e->getMessage());
            return null;
        }
    }

    // Đăng nhập
    public function login($email, $password) {
        try {
            $user = $this->findUserByEmail($email);
            
            if (!$user) return false;
            
            // Kiểm tra status (0 = inactive, 1 = active)
            if (isset($user['status']) && $user['status'] == 0) {
                return "inactive";
            }
            
            // Kiểm tra password
            if (password_verify($password, $user['password'])) {
                return $user;
            }
            
            return false;
        } catch (\Throwable $e) {
            error_log("login error: " . $e->getMessage());
            return false;
        }
    }

    // Lấy user theo user_id (ví dụ: NV001)
    public function getUserByUserId($userId) {
        try {
            return $this->usersCollection->findOne(['user_id' => $userId]);
        } catch (\Throwable $e) {
            error_log("getUserByUserId error: " . $e->getMessage());
            return null;
        }
    }

    // Lấy user theo ObjectId (_id của Mongo)
    public function getUserByObjectId($objectId) {
        try {
            $filter = ['_id' => new \MongoDB\BSON\ObjectId($objectId)];
            return $this->usersCollection->findOne($filter);
        } catch (\Throwable $e) {
            error_log("getUserByObjectId error: " . $e->getMessage());
            return null;
        }
    }

    // Lấy tất cả users
    public function getAllUsers() {
        try {
            return $this->usersCollection->find()->toArray();
        } catch (\Throwable $e) {
            error_log("getAllUsers error: " . $e->getMessage());
            return [];
        }
    }

    // Lấy tất cả users kèm role_name
public function getAllUsersWithRole() {
    try {
        // Lấy tất cả users
        $users = $this->usersCollection->find()->toArray();
        
        // Lấy tất cả roles và index theo role_id
        $roles = $this->rolesCollection->find()->toArray();
        $rolesMap = [];
        foreach ($roles as $role) {
            $roleId = isset($role['role_id']) ? $role['role_id'] : null;
            if ($roleId !== null) {
                // Lưu cả dạng string và integer để tránh mismatch
                $rolesMap[$roleId] = $role;
                if (is_numeric($roleId)) {
                    $rolesMap[(int)$roleId] = $role;
                    $rolesMap[(string)$roleId] = $role;
                }
            }
        }
        
        // Gắn role_info cho từng user
        foreach ($users as &$user) {
            $userRoleId = isset($user['role_id']) ? $user['role_id'] : null;
            if ($userRoleId !== null && isset($rolesMap[$userRoleId])) {
                $user['role_info'] = $rolesMap[$userRoleId];
            } else {
                $user['role_info'] = null;
            }
        }
        
        return $users;
    } catch (\Throwable $e) {
        error_log("getAllUsersWithRole error: " . $e->getMessage());
        return [];
    }
}

    /* ==========================
       QUẢN LÝ NGƯỜI DÙNG
    ========================== */

    // Thêm user mới
    public function addUser($name, $email, $gender, $phone, $hashedPassword, $role_id, $status, $warehouse_id, $first_login = true) {
        try {
            // Tạo user_id tự động (ví dụ: NV001, NV002, ...)
            $lastUser = $this->usersCollection->findOne(
                [],
                ['sort' => ['user_id' => -1]]
            );
            
            $nextId = 1;
            if ($lastUser && isset($lastUser['user_id'])) {
                $currentNum = (int)substr($lastUser['user_id'], 2);
                $nextId = $currentNum + 1;
            }
            
            $user_id = 'NV' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
            
            $userData = [
                'user_id' => $user_id,
                'name' => $name,
                'email' => $email,
                'gender' => $gender,
                'phone' => $phone,
                'password' => $hashedPassword,
                // Normalize role_id and warehouse_id types so MongoDB lookup matches
                'role_id' => (is_numeric($role_id) ? (int)$role_id : $role_id),
                'status' => (is_numeric($status) ? (int)$status : $status),
                'warehouse_id' => (is_numeric($warehouse_id) ? (int)$warehouse_id : $warehouse_id),
                'first_login' => $first_login,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'updated_at' => new \MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $this->usersCollection->insertOne($userData);
            return $result->getInsertedId() ? $user_id : false;
        } catch (\Throwable $e) {
            error_log("addUser error: " . $e->getMessage());
            return false;
        }
    }

    // Xóa user
    public function deleteUser($userId) {
        try {
            $result = $this->usersCollection->deleteOne(['user_id' => $userId]);
            return $result->getDeletedCount() > 0;
        } catch (\Throwable $e) {
            error_log("deleteUser error: " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       CẬP NHẬT THÔNG TIN
    ========================== */

    // Admin cập nhật user (full field)
    public function updateUserAdmin($userId, $data) {
        try {
            $result = $this->usersCollection->updateOne(
                ['user_id' => $userId],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Throwable $e) {
            error_log("updateUserAdmin error: " . $e->getMessage());
            return false;
        }
    }

    // Nhân viên tự cập nhật profile (chỉ sửa một số field cho phép)
    public function updateUserProfile($userId, $data) {
        try {
            $allowedFields = ['name', 'email', 'phone', 'gender'];
            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (empty($updateData)) return false;

            $result = $this->usersCollection->updateOne(
                ['user_id' => $userId],
                ['$set' => $updateData]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Throwable $e) {
            error_log("updateUserProfile error: " . $e->getMessage());
            return false;
        }
    }

    // Update theo ObjectId (_id)
    public function updateUserByObjectId($objectId, $data) {
        try {
            $filter = ['_id' => new \MongoDB\BSON\ObjectId($objectId)];
            $result = $this->usersCollection->updateOne($filter, ['$set' => $data]);
            return $result->getModifiedCount() > 0;
        } catch (\Throwable $e) {
            error_log("updateUserByObjectId error: " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       QUẢN LÝ MẬT KHẨU
    ========================== */

    // Đổi mật khẩu (dùng khi nhân viên đổi pass)
    public function updatePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->getUserByUserId($userId);
            if (!$user) return false;

            // Check mật khẩu cũ
            if (!password_verify($currentPassword, $user['password'])) {
                return 'wrong_password';
            }

            // Hash mật khẩu mới
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

            $result = $this->usersCollection->updateOne(
                ['user_id' => $userId],
                ['$set' => ['password' => $hashedPassword]]
            );

            // Provide more detailed result codes for debugging
            $matched = method_exists($result, 'getMatchedCount') ? $result->getMatchedCount() : null;
            $modified = method_exists($result, 'getModifiedCount') ? $result->getModifiedCount() : null;

            if ($modified !== null && $modified > 0) return 'updated';
            if ($matched !== null && $matched > 0 && $modified === 0) return 'not_updated';

            // Fallback: if driver didn't provide counts, return boolean
            return false;
        } catch (\Throwable $e) {
            error_log("updatePassword error: " . $e->getMessage());
            return false;
        }
    }

    /* ==========================
       HỖ TRỢ VAI TRÒ / ROLE
    ========================== */

    public function getRoleById($roleId) {
        try {
            return $this->rolesCollection->findOne(['role_id' => $roleId]);
        } catch (\Throwable $e) {
            error_log("getRoleById error: " . $e->getMessage());
            return null;
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

}