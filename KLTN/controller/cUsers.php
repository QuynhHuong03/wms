<?php
include_once(__DIR__ . "/../model/mUsers.php");

class CUsers {
    public function getAllUsers() {
        $p = new MUsers();
        $tblSP = $p->SelectAllUsers();
        if (!$tblSP) return -1; 
        return ($tblSP->num_rows > 0) ? $tblSP : 0; 
    }

    public function dangnhaptaikhoan($email, $password) {
        $p = new MUsers();
        $result = $p->login($email, $password);

        if ($result === "inactive") return false;
        elseif ($result) {
            // clear session cũ
            unset($_SESSION["login"]);
            // set session mới
            $_SESSION["login"] = $result;
            header("Location: ../manage");
            exit();
        }
        return false;
    }


    //Lấy nhân viên theo mã
        public function get($id) {
            $p = new MUsers();
            $tblNV = $p->SelectAllUsers($id);

            if ($tblNV) {
                return $tblNV;
            } else {
                return null; // Không có nv với id đó
            }
        }

        public function getUserwithRole($id) {
            $p = new MUsers();
            $tblNV = $p->SelectUserwithRole($id);

            if ($tblNV) {
                return $tblNV;
            } else {
                return null; // Không có nv với id đó
            }
        }
}
?>
