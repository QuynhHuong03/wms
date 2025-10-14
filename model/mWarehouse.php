<?php
include_once("connect.php");

class MWarehouse
{
    // 📦 Lấy tất cả kho
    public function getAllWarehouses()
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');
                $cursor = $col->find([]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    $item['address_text'] = "{$item['address']['street']}, {$item['address']['city']}, {$item['address']['province']}";
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("❌ Lỗi MongoDB (getAllWarehouses): " . $e->getMessage());
            }
        }
        return false;
    }

    // 🔍 Tìm kho theo tên
    public function searchWarehousesByName($name)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');
                $cursor = $col->find([
                    'warehouse_name' => ['$regex' => $name, '$options' => 'i']
                ]);
                $results = [];
                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    $item['address_text'] = "{$item['address']['street']}, {$item['address']['city']}, {$item['address']['province']}";
                    $results[] = $item;
                }
                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("❌ Lỗi MongoDB (searchWarehousesByName): " . $e->getMessage());
            }
        }
        return false;
    }

    // ➕ Thêm kho mới
    public function addWarehouse($data)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');

                // Lấy id tự tăng
                $last = $col->findOne([], ['sort' => ['id' => -1]]);
                $data['id'] = isset($last['id']) ? $last['id'] + 1 : 1;

                // Thời gian tạo
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = $data['created_at'];

                $insert = $col->insertOne($data);
                $p->dongKetNoi($con);
                return $insert->getInsertedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("❌ Lỗi MongoDB (addWarehouse): " . $e->getMessage());
            }
        }
        return false;
    }

    // 🗑️ Xóa kho
    public function deleteWarehouse($warehouse_id)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');
                $result = $col->deleteOne(['warehouse_id' => $warehouse_id]);
                $p->dongKetNoi($con);
                return $result->getDeletedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("❌ Lỗi MongoDB (deleteWarehouse): " . $e->getMessage());
            }
        }
        return false;
    }

    // ✏️ Cập nhật kho
    public function updateWarehouse($warehouse_id, $data)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $result = $col->updateOne(
                    ['warehouse_id' => $warehouse_id],
                    ['$set' => $data]
                );
                $p->dongKetNoi($con);
                return $result->getModifiedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("❌ Lỗi MongoDB (updateWarehouse): " . $e->getMessage());
            }
        }
        return false;
    }

    // 📍 Lấy kho theo ID
    public function getWarehouseById($warehouse_id)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');
                $doc = $col->findOne(['warehouse_id' => $warehouse_id]);
                $p->dongKetNoi($con);
                if ($doc) {
                    $item = json_decode(json_encode($doc), true);
                    $item['address_text'] = "{$item['address']['street']}, {$item['address']['city']}, {$item['address']['province']}";
                    return $item;
                }
                return null;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("❌ Lỗi MongoDB (getWarehouseById): " . $e->getMessage());
            }
        }
        return null;
    }
}
?>
