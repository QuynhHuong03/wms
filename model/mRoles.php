<?php/*
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
    
    
}*/
?>
<?php
include_once("connect.php"); // connect.php phải chứa clsKetNoi (phiên bản MongoDB)

// Wrapper để giả lập mysqli_result (num_rows, fetch_assoc, ...)
class MongoResult {
    private $rows = [];
    private $pos = 0;
    public $num_rows = 0;

    public function __construct(array $rows = []) {
        $this->rows = $rows;
        $this->pos = 0;
        $this->num_rows = count($rows);
    }

    // Lấy từng dòng (tương tự fetch_assoc của mysqli)
    public function fetch_assoc() {
        if ($this->pos < $this->num_rows) {
            $row = $this->rows[$this->pos];
            $this->pos++;
            return $row;
        }
        return null;
    }

    public function fetch_array() {
        return $this->fetch_assoc();
    }

    public function fetch_all() {
        return $this->rows;
    }

    public function reset() {
        $this->pos = 0;
    }
}

class MRoles {
    public function SelectAllRoles() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi(); // $con là MongoDB\Database

        if ($con) {
            try {
                $col = $con->selectCollection('roles');
                $cursor = $col->find([]); // lấy tất cả
                $results = [];

                foreach ($cursor as $doc) {
                    // convert BSONDocument -> array (chuyển _id thành chuỗi nếu cần)
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['_id']['$oid'])) {
                        $item['_id'] = $item['_id']['$oid'];
                    }
                    $results[] = $item;
                }

                $mongoResult = new MongoResult($results);
                echo "Số dòng: " . $mongoResult->num_rows;

                $p->dongKetNoi($con);
                return $mongoResult;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        } else {
            die("Không thể kết nối CSDL");
        }
    }

    public function SelectRoles($id) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();

        if ($con) {
            try {
                $col = $con->selectCollection('roles');

                // Thử tìm theo _id (ObjectId) nếu $id là chuỗi 24 ký tự hex,
                // nếu là số thì tìm theo trường role_id (nếu bạn có trường này trong doc)
                if (is_string($id) && preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                    $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
                } elseif (is_numeric($id)) {
                    $filter = ['role_id' => (int)$id];
                } else {
                    $filter = ['role_id' => $id];
                }

                $doc = $col->findOne($filter);
                $p->dongKetNoi($con);

                if ($doc) {
                    $result = json_decode(json_encode($doc), true);
                    if (isset($result['_id']['$oid'])) {
                        $result['_id'] = $result['_id']['$oid'];
                    }
                    return $result; // trả về 1 mảng associative (tương tự fetch_assoc)
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi truy vấn MongoDB: " . $e->getMessage());
            }
        } else {
            return false;
        }
    }
}
?>