<?php
include_once(__DIR__ . "/../model/mUsers.php");

class CUsers {
    // Lấy tất cả users
    public function getAllUsers() {
        $p = new MUsers();
        $users = $p->SelectAllUsers();

        if (!$users) {
            return -1; // Lỗi kết nối
        } elseif (iterator_count($users) > 0) {
            return $users; // Trả về danh sách users
        } else {
            return 0; // Không có users
        }
    }

    // Đăng nhập
    public function dangnhaptaikhoan($email, $password) {
        $p = new MUsers();
        $result = $p->login($email, $password);

        if ($result === "inactive") {
            return false; // Tài khoản không hoạt động
        } elseif ($result) {
            // Xóa session cũ và tạo session mới
            unset($_SESSION["login"]);
            $_SESSION["login"] = $result;
            header("Location: ../manage");
            exit();
        }
        return false; // Sai email hoặc mật khẩu
    }

    // Lấy user theo ID
    public function get($id) {
        $p = new MUsers();
        $user = $p->SelectUserById($id);

        return $user ?: null; // Trả về user hoặc null nếu không tìm thấy
    }

    // Lấy user kèm role
    public function getUserwithRole($id) {
        $p = new MUsers();
        $user = $p->SelectUserwithRole($id);

        return $user ?: null; // Trả về user hoặc null nếu không tìm thấy
    }

    // Thêm user mới
    public function addUser($name, $email, $gender, $phone, $hashedPassword, $role_id, $status, $warehouse_id) {
        $p = new MUsers();
        // Kiểm tra email đã tồn tại chưa
        $existing = $p->findUserByEmail($email); // Bạn cần tạo hàm này trong model nếu chưa có
        if ($existing) {
            return false; // email đã tồn tại
        }
        return $p->addUser($name, $email, $gender, $phone, $hashedPassword, $role_id, $status, $warehouse_id);
    }

    public function update($id, $data) {
    $m = new MUsers();
    return $m->updateUserById($id, $data); // MUsers thực hiện update MongoDB
}

    // Xóa user theo _id hoặc user_id
    public function deleteUser($id) {
        $p = new MUsers();
        return $p->deleteUser($id); // Trả về true nếu xóa thành công, false nếu thất bại
    }


}
?>
