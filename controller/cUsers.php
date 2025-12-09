<?php
include_once(__DIR__ . "/../model/mUsers.php");

class CUsers {
    private $mUsers;

    public function __construct() {
        $this->mUsers = new MUsers();
    }

    /* ==========================
       LẤY THÔNG TIN
    ========================== */

    // Lấy tất cả users
    public function getAllUsers() {
    $users = $this->mUsers->getAllUsersWithRole();

    if (!$users) return -1; // Lỗi kết nối
    return count($users) > 0 ? $users : 0;
}

    // Lấy user theo user_id
    public function getUserById($id) {
        return $this->mUsers->getUserByUserId($id);
    }

    // Lấy user kèm role
    public function getUserWithRole($id) {
        $user = $this->mUsers->getUserByUserId($id);
        if ($user && isset($user['role_id'])) {
            $role = $this->mUsers->getRoleById($user['role_id']);
            $user['role'] = $role;
        }
        return $user ?: null;
    }

    /* ==========================
       XỬ LÝ ĐĂNG NHẬP
    ========================== */
    public function login($email, $password) {
        $result = $this->mUsers->login($email, $password);

        if ($result === "inactive") return false;
        if ($result) {
            unset($_SESSION["login"]);
            // Convert MongoDB document to array to avoid serialization issues
            if ($result instanceof \MongoDB\Model\BSONDocument) {
                $_SESSION["login"] = iterator_to_array($result);
            } else {
                $_SESSION["login"] = (array)$result;
            }

            header("Location: ../manage");
            exit();
        }
        return false;
    }

    /* ==========================
       QUẢN LÝ NGƯỜI DÙNG
    ========================== */

    // Thêm user mới
    public function addUser($name, $email, $gender, $phone, $hashedPassword, $role_id, $status, $warehouse_id) {
        $existing = $this->mUsers->findUserByEmail($email);
        if ($existing) return false;

        return $this->mUsers->addUser(
            $name, $email, $gender, $phone,
            $hashedPassword, $role_id, $status, $warehouse_id
        );
    }

    // Admin cập nhật user (full field)
    public function updateUserAdmin($userId, $data) {
        return $this->mUsers->updateUserAdmin($userId, $data);
    }

    // Nhân viên tự cập nhật profile
    public function updateUserProfile($userId, $data) {
        return $this->mUsers->updateUserProfile($userId, $data);
    }

    // Xóa user
    public function deleteUser($userId) {
        return $this->mUsers->deleteUser($userId);
    }

    /* ==========================
       MẬT KHẨU
    ========================== */

    // Đổi mật khẩu
    public function changePassword($userId, $currentPassword, $newPassword) {
        return $this->mUsers->updatePassword($userId, $currentPassword, $newPassword);
    }

    // Alias method for updatePassword to maintain compatibility
    public function updatePassword($userId, $currentPassword, $newPassword) {
        return $this->changePassword($userId, $currentPassword, $newPassword);
    }

}