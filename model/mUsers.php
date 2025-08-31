<?php
include_once("connect.php");

class MUsers {
    public function SelectAllUsers() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $str = "SELECT * FROM users";
            $tblSP = $con->query($str);
            $p->dongKetNoi($con);
            return $tblSP;
        }
        return false; 
    }

    public function login($email, $password) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $stmt = $con->prepare("SELECT user_id, email, password, status, role_id, warehouse_id 
                                   FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();

                if ($row['status'] == 0) return "inactive";

                if (password_verify($password, $row['password'])) {
                    $userData = [
                        "user_id"      => $row["user_id"],
                        "email"        => $row["email"],
                        "role_id"      => $row["role_id"],
                        "warehouse_id" => $row["warehouse_id"]
                    ];
                    return $userData;   // chỉ trả về data
                }
            }
            $stmt->close();
            $p->dongKetNoi($con);
        }
        return false;
    }
}
?>
