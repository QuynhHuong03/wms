<?php
include_once("connect.php");

class MWarehouseType {

    // Lấy tất cả loại kho
    public function getAllWarehouseTypes() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse_types');
                $cursor = $col->find([], ['sort' => ['id' => 1]]); // Sắp xếp theo id tăng dần
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Thêm loại kho
    public function addWarehouseType($warehouse_type_id, $name, $created_at) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse_types');

                // Lấy giá trị id lớn nhất hiện tại
                $lastItem = $col->findOne([], ['sort' => ['id' => -1]]);
                $newId = isset($lastItem['id']) ? $lastItem['id'] + 1 : 1;

                $insertResult = $col->insertOne([
                    'id'               => $newId, // Thêm id tự tăng
                    'warehouse_type_id' => $warehouse_type_id,
                    'name'              => $name,
                    'created_at'        => $created_at // Lưu thời gian tạo
                ]);

                $p->dongKetNoi($con);
                return $insertResult->getInsertedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Xóa loại kho
    public function deleteWarehouseType($warehouse_type_id) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse_types');
                $deleteResult = $col->deleteOne(['warehouse_type_id' => $warehouse_type_id]);

                $p->dongKetNoi($con);
                return $deleteResult->getDeletedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Cập nhật loại kho
    public function updateWarehouseType($warehouse_type_id, $name) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse_types');
                $updateResult = $col->updateOne(
                    ['warehouse_type_id' => $warehouse_type_id],
                    ['$set' => ['name' => $name]]
                );

                $p->dongKetNoi($con);
                return $updateResult->getModifiedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Tìm kiếm loại kho theo tên
    public function searchWarehouseTypesByName($name) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse_types');
                $cursor = $col->find(['name' => new MongoDB\BSON\Regex($name, 'i')]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }
}
?>