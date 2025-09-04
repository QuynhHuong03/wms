<?php
include_once("connect.php");

class MRoles {
    public function SelectAllRoles() {
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    
    if ($con) {
        $str = "SELECT * FROM roles";
        $tblroles = $con->query($str);

        if (!$tblroles) {
            die("Lỗi query: " . $con->error);
        }

        // Debug xem có dữ liệu không
        echo "Số dòng: " . $tblroles->num_rows;

        $p->dongKetNoi($con);
        return $tblroles;
    } else {
        die("Không thể kết nối CSDL");
    }
}

    
   // Lấy role theo id
   public function SelectRoles($id) {
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    
    if ($con) {
        $str = "SELECT * FROM roles WHERE role_id = ?";
        $stmt = $con->prepare($str);
        $stmt->bind_param("i", $id); // Sử dụng prepared statement để bảo vệ khỏi SQL Injection
        $stmt->execute();
        $result = $stmt->get_result();
        $p->dongKetNoi($con);

        if ($result->num_rows > 0) {
            return $result->fetch_assoc(); // Trả về 1 dòng kết quả
        } else {
            return false; // Không tìm thấy role với id đó
        }
    } else {
        return false; // Không thể kết nối đến CSDL
    }
}
    
    
}
?>
