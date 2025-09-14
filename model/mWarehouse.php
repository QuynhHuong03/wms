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
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['_id']['$oid'])) {
                        $item['_id'] = $item['_id']['$oid'];
                    }
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return new MongoResult($results);
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
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['_id']['$oid'])) {
                        $item['_id'] = $item['_id']['$oid'];
                    }
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return new MongoResult($results);
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
            $cursor = $col->find([]);
            $results = [];

            foreach ($cursor as $doc) {
                 var_dump($doc);
                $item = json_decode(json_encode($doc), true);
                if (isset($item['_id']['$oid'])) {
                    $item['_id'] = $item['_id']['$oid'];
                }
                $results[] = $item;
            }

            $p->dongKetNoi($con);
            return new MongoResult($results); // Trả về MongoResult
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
                $cursor = $col->find([
                    'warehouse_name' => ['$regex' => $name, '$options' => 'i']
                ]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['_id']['$oid'])) {
                        $item['_id'] = $item['_id']['$oid'];
                    }
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return new MongoResult($results);
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Thêm kho chi nhánh
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouse');
                $insertResult = $col->insertOne([
                    'warehouse_id'   => $warehouse_id,
                    'warehouse_name' => $warehouse_name,
                    'address'        => $address,
                    'status'         => (int)$status,
                    'warehouse_type' => 2 // 2 = kho chi nhánh
                ]);

                $p->dongKetNoi($con);
                return $insertResult->getInsertedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi insert MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }
}
?>
