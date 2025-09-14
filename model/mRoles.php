<?php
include_once("connect.php"); // connect.php phải chứa clsKetNoi (MongoDB)

class MongoResult {
    private $rows = [];
    private $pos = 0;
    public $num_rows = 0;

    public function __construct(array $rows = []) {
        $this->rows = $rows;
        $this->pos = 0;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->pos < $this->num_rows) {
            return $this->rows[$this->pos++];
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

    // Thêm vào class MongoResult
public function getRows() {
    return $this->rows;
}

}

class MRoles {
    public function SelectAllRoles() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi(); // $con là MongoDB\Database

        if ($con) {
            try {
                $col = $con->selectCollection('roles');
                $cursor = $col->find([]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = (array)$doc;
                    $item['_id'] = (string)$doc->_id; // convert ObjectId sang string
                    $results[] = $item;
                }

                return new MongoResult($results);
            } catch (\Exception $e) {
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

                if (is_string($id) && preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                    $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
                } elseif (is_numeric($id)) {
                    $filter = ['role_id' => (int)$id];
                } else {
                    $filter = ['role_id' => $id];
                }

                $doc = $col->findOne($filter);

                if ($doc) {
                    $result = (array)$doc;
                    $result['_id'] = (string)$doc->_id;
                    return $result;
                } else {
                    return false;
                }
            } catch (\Exception $e) {
                die("Lỗi truy vấn MongoDB: " . $e->getMessage());
            }
        } else {
            return false;
        }
    }
}
?>
