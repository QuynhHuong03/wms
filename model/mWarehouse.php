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
                    if (isset($item['address']) && is_array($item['address'])) {
                        $street = $item['address']['street'] ?? '';
                        $wardCity = $item['address']['ward'] ?? ($item['address']['city'] ?? '');
                        $province = $item['address']['province'] ?? '';
                        $parts = array_filter([$street, $wardCity, $province], function($v){ return $v !== '' && $v !== null; });
                        $item['address_text'] = implode(', ', $parts);
                    } else {
                        $item['address_text'] = isset($item['address']) ? (string)$item['address'] : '';
                    }
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
                    if (isset($item['address']) && is_array($item['address'])) {
                        $street = $item['address']['street'] ?? '';
                        $wardCity = $item['address']['ward'] ?? ($item['address']['city'] ?? '');
                        $province = $item['address']['province'] ?? '';
                        $parts = array_filter([$street, $wardCity, $province], function($v){ return $v !== '' && $v !== null; });
                        $item['address_text'] = implode(', ', $parts);
                    } else {
                        $item['address_text'] = isset($item['address']) ? (string)$item['address'] : '';
                    }
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

    // ⭐ Kiểm tra kho còn chứa sản phẩm
    public function checkProductsInWarehouse($warehouse_id)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                // Kiểm tra trong inventory (tồn kho hiện tại)
                $invCol = $con->selectCollection('inventory');
                $count = $invCol->countDocuments(['warehouse_id' => $warehouse_id, 'qty' => ['$gt' => 0]]);
                $p->dongKetNoi($con);
                return $count;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("❌ Lỗi MongoDB (checkProductsInWarehouse): " . $e->getMessage());
                return 0;
            }
        }
        return 0;
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
                    // Xử lý address_text nếu có address
                    if (isset($item['address']) && is_array($item['address'])) {
                        $street = $item['address']['street'] ?? '';
                        $wardCity = $item['address']['ward'] ?? ($item['address']['city'] ?? '');
                        $province = $item['address']['province'] ?? '';
                        $parts = array_filter([$street, $wardCity, $province], function($v){ return $v !== '' && $v !== null; });
                        $item['address_text'] = implode(', ', $parts);
                    }
                    // Đảm bảo có trường name (fallback từ warehouse_name)
                    if (!isset($item['name']) && isset($item['warehouse_name'])) {
                        $item['name'] = $item['warehouse_name'];
                    }
                    return $item;
                }
                return null;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("❌ Lỗi MongoDB (getWarehouseById): " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    // 🏷️ Lấy kho theo loại (warehouse_type)
    public function getWarehousesByType($type)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');
                $cursor = $col->find([
                    'warehouse_type' => (int)$type,
                    'status' => 1 // Chỉ lấy kho đang hoạt động
                ]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['address']) && is_array($item['address'])) {
                        $street = $item['address']['street'] ?? '';
                        $wardCity = $item['address']['ward'] ?? ($item['address']['city'] ?? '');
                        $province = $item['address']['province'] ?? '';
                        $parts = array_filter([$street, $wardCity, $province], function($v){ return $v !== '' && $v !== null; });
                        $item['address_text'] = implode(', ', $parts);
                    }
                    $results[] = $item;
                }

                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("❌ Lỗi MongoDB (getWarehousesByType): " . $e->getMessage());
                return [];
            }
        }
        return [];
    }

    // 🏢 Thêm kho chi nhánh
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status)
    {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('warehouses');

                // Kiểm tra trùng warehouse_id
                $existing = $col->findOne(['warehouse_id' => $warehouse_id]);
                if ($existing) {
                    $p->dongKetNoi($con);
                    return false; // Mã kho đã tồn tại
                }

                // Lấy id tự tăng
                $last = $col->findOne([], ['sort' => ['id' => -1]]);
                $id = isset($last['id']) ? $last['id'] + 1 : 1;

                // Tạo document mới
                $data = [
                    'id' => $id,
                    'warehouse_id' => $warehouse_id,
                    'warehouse_name' => $warehouse_name,
                    'address' => $address, // Lưu dạng string
                    'status' => (int)$status,
                    'warehouse_type' => 2, // 2 = Kho chi nhánh (tham chiếu warehouse_types với id=2)
                    'type' => 'Kho chi nhánh', // Tên loại kho dạng text
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $insert = $col->insertOne($data);
                $p->dongKetNoi($con);
                return $insert->getInsertedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("❌ Lỗi MongoDB (addBranchWarehouse): " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}
?>
