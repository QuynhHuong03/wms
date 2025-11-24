<?php
require_once "connect.php";

class MCategories {
    private $categoriesCollection;
    private $connObj;

    public function __construct() {
        if (class_exists('Connect')) {
            $conn = new Connect();
            $this->connObj = $conn;
            if (method_exists($conn, 'getCollection')) {
                $this->categoriesCollection = $conn->getCollection('categories');
                return;
            } elseif (method_exists($conn, 'moKetNoi')) {
                $db = $conn->moKetNoi();
                $this->categoriesCollection = $db->selectCollection('categories');
                return;
            } else {
                throw new \Exception("Connect không hỗ trợ getCollection hoặc moKetNoi");
            }
        }

        if (class_exists('clsKetNoi')) {
            $conn = new clsKetNoi();
            $db = $conn->moKetNoi();
            $this->connObj = $conn;
            $this->categoriesCollection = $db->selectCollection('categories');
            return;
        }

        throw new \Exception('Không tìm thấy lớp Connect hoặc clsKetNoi trong connect.php');
    }

    // Chuyển BSON -> array PHP
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
            if (count($v) === 1) {
                if (isset($v['$oid'])) return (string)$v['$oid'];
                if (isset($v['$numberInt'])) return (int)$v['$numberInt'];
                if (isset($v['$numberLong'])) return (int)$v['$numberLong'];
                if (isset($v['$numberDouble'])) return (float)$v['$numberDouble'];
                if (isset($v['$date'])) return $v['$date'];
            }
            $out = [];
            foreach ($v as $k => $val) $out[$k] = $this->normalizeBson($val);
            return $out;
        }
        return $v;
    }

    // ================= CRUD ================= //

    // Lấy tất cả categories
    public function SelectAllCategories() {
        try {
            $cursor = $this->categoriesCollection->find([], ['sort' => ['category_id' => 1]]);
            $cats = [];
            foreach ($cursor as $doc) $cats[] = $this->docToArray($doc);
            return $cats;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi SelectAllCategories: ' . $e->getMessage());
        }
    }

    // Alias cho getAllCategories (để tương thích)
    public function getAllCategories() {
        return $this->SelectAllCategories();
    }

    // Lấy 1 category theo ID
    public function SelectCategoryById($id) {
        try {
            if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            } else {
                $filter = ['category_id' => $id];
            }
            $doc = $this->categoriesCollection->findOne($filter);
            return $doc ? $this->docToArray($doc) : false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi SelectCategoryById: ' . $e->getMessage());
        }
    }

    // Thêm category mới
    public function addCategory($name, $code, $description, $status) {
        try {
            // Lấy category_id cuối cùng DMxxx
            $last = $this->categoriesCollection->findOne(
                ['category_id' => ['$regex' => '^DM[0-9]+$']],
                ['sort' => ['category_id' => -1]]
            );
            $nextNumber = 1;
            if ($last && isset($last['category_id'])) {
                $lastCode = $last['category_id'];
                $num = (int)substr($lastCode, 2);
                $nextNumber = $num + 1;
            }
            $newCatId = "DM" . str_pad($nextNumber, 3, "0", STR_PAD_LEFT);

            $insertData = [
                "category_id"   => $newCatId,
                "category_name" => $name,
                "category_code" => $code, // thêm dòng này
                "description"   => $description,
                "status"        => (int)$status,
                "create_at"     => new \MongoDB\BSON\UTCDateTime()
            ];

            $result = $this->categoriesCollection->insertOne($insertData);
            return $result->getInsertedCount() > 0;
        } catch (\Throwable $e) {
            error_log("addCategory error: " . $e->getMessage());
            return false;
        }
    }

    // Lấy mã category tiếp theo theo pattern DMxxx (ví dụ DM001)
    public function getNextCategoryId() {
        try {
            $last = $this->categoriesCollection->findOne(
                ['category_id' => ['$regex' => '^DM[0-9]+$']],
                ['sort' => ['category_id' => -1]]
            );
            $nextNumber = 1;
            if ($last && isset($last['category_id'])) {
                $lastCode = $last['category_id'];
                $num = (int)substr($lastCode, 2);
                $nextNumber = $num + 1;
            }
            return "DM" . str_pad($nextNumber, 3, "0", STR_PAD_LEFT);
        } catch (\Throwable $e) {
            error_log('getNextCategoryId error: ' . $e->getMessage());
            return null;
        }
    }

    // Update category
    public function updateCategory($id, $data) {
        try {
            if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter = ['_id' => new \MongoDB\BSON\ObjectId($id)];
            } else {
                $filter = ['category_id' => $id];
            }
            $update = ['$set' => $data];
            $result = $this->categoriesCollection->updateOne($filter, $update);
            // If a document was matched it's a successful operation even if modifiedCount is 0
            // (e.g., only timestamps or identical values). Treat matchedCount>0 as success.
            if ($result->getMatchedCount() > 0) return true;
            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Lỗi updateCategory: ' . $e->getMessage());
        }
    }

    // Xóa category
    public function deleteCategory($id) {
        try {
            $filter = [];
            if (preg_match('/^[0-9a-fA-F]{24}$/', $id)) {
                $filter['_id'] = new \MongoDB\BSON\ObjectId($id);
            } else {
                $filter['category_id'] = $id;
            }
            $result = $this->categoriesCollection->deleteOne($filter);
            return $result->getDeletedCount() > 0;
        } catch (\Throwable $e) {
            error_log("deleteCategory error: " . $e->getMessage());
            return false;
        }
    }
}
?>
