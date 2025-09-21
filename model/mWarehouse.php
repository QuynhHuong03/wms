<?php
include_once("connect.php"); // connect.php phải có clsKetNoi cho MongoDB
include_once("mRoles.php");  // để dùng lại MongoResult wrapper

class MWarehouse {

    // Lấy tất cả loại kho
    public function getWarehouseTypes() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse_types');
                $cursor = $col->find([]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true); // Chuyển BSONDocument sang mảng
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results; // Trả về mảng
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Lấy danh sách kho theo loại
    public function getWarehousesByType($type_id) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');
                $cursor = $col->find(['warehouse_type' => (int)$type_id]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true); // Chuyển BSONDocument sang mảng
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results; // Trả về mảng
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

// Lấy tất cả kho
public function getAllWarehouses() {
    $p = new clsKetNoi();
    $con = $p->moKetNoi();
    if ($con) {
        try {
            $col = $con->selectCollection('warehouse');
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


    // Tìm kho theo tên (LIKE)
    public function searchWarehousesByName($name) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');
                $cursor = $col->find(['warehouse_name' => ['$regex' => $name, '$options' => 'i']]);
                $results = [];

                // Lấy danh sách loại kho để gắn type_name
                $typeCol = $con->selectCollection('warehouse_types');
                $typeCursor = $typeCol->find([]);
                $typeMap = [];
                foreach ($typeCursor as $type) {
                    $typeMap[$type['id']] = $type['name'];
                }

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true); // Chuyển BSONDocument sang mảng
                    $item['type_name'] = $typeMap[$item['warehouse_type']] ?? 'Không xác định'; // Gắn type_name
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results; // Trả về mảng
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Thêm kho chi nhánh
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status, $created_at) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');

                // Lấy giá trị id lớn nhất hiện tại
                $lastItem = $col->findOne([], ['sort' => ['id' => -1]]);
                $newId = isset($lastItem['id']) ? $lastItem['id'] + 1 : 1;

                $insertResult = $col->insertOne([
                    'id'             => $newId, // Thêm id tự tăng
                    'warehouse_id'   => $warehouse_id,
                    'warehouse_name' => $warehouse_name,
                    'address'        => $address,
                    'status'         => (int)$status,
                    'warehouse_type' => 2, // Loại kho chi nhánh
                    'created_at'     => $created_at // Ngày tạo
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

    // Thêm kho
    public function addWarehouse($warehouse_id, $warehouse_name, $address, $status, $warehouse_type, $created_at) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');

                // Lấy giá trị id lớn nhất hiện tại
                $lastItem = $col->findOne([], ['sort' => ['id' => -1]]);
                $newId = isset($lastItem['id']) ? $lastItem['id'] + 1 : 1;

                $insertResult = $col->insertOne([
                    'id'             => $newId, // Thêm id tự tăng
                    'warehouse_id'   => $warehouse_id,
                    'warehouse_name' => $warehouse_name,
                    'address'        => $address,
                    'status'         => (int)$status,
                    'warehouse_type' => (int)$warehouse_type,
                    'created_at'     => $created_at // Ngày tạo
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

    // Xóa kho
    public function deleteWarehouse($warehouseId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');
                $deleteResult = $col->deleteOne(['warehouse_id' => $warehouseId]);

                $p->dongKetNoi($con);
                return $deleteResult->getDeletedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Cập nhật thông tin kho
    public function updateWarehouse($warehouse_id, $warehouse_name, $address, $status) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');
                $updateResult = $col->updateOne(
                    ['warehouse_id' => $warehouse_id],
                    ['$set' => [
                        'warehouse_name' => $warehouse_name,
                        'address'        => $address,
                        'status'         => (int)$status
                    ]]
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
}
?>