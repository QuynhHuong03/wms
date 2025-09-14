<?php
include_once(__DIR__ . '/../model/mWarehouse.php');

class CWarehouse {

    // Lấy tất cả loại kho
    public function getTypes() {
        $p = new MWarehouse();
        $types = $p->getWarehouseTypes();

        if (!$types) {
            return -1; // lỗi hoặc không kết nối được
        }

        if (!empty($types->result)) {
            return $types; // có dữ liệu
        } else {
            return 0; // không có loại kho
        }
    }

    // Lấy danh sách kho theo loại
    public function getWarehousesByType($type_id) {
        $p = new MWarehouse();
        $warehouses = $p->getWarehousesByType($type_id);

        if (!$warehouses) return false;

        return $warehouses;
    }

    // Lấy tất cả kho (kho chính + kho chi nhánh)
    public function getAllWarehouses() {
    $p = new MWarehouse();

    $warehouses1 = $p->getWarehousesByType(1); // kho chính
    $warehouses2 = $p->getWarehousesByType(2); // kho chi nhánh

    $results = [];

    if ($warehouses1 && $warehouses1->num_rows > 0) {
        $warehouses1->reset(); // đảm bảo từ đầu
        while ($row = $warehouses1->fetch_assoc()) {
            $results[] = $row;
        }
    }

    if ($warehouses2 && $warehouses2->num_rows > 0) {
        $warehouses2->reset();
        while ($row = $warehouses2->fetch_assoc()) {
            $results[] = $row;
        }
    }

    return new MongoResult($results);
}


    // Tìm kho theo tên (LIKE)
    public function searchWarehousesByName($name) {
        $p = new MWarehouse();
        $warehouses = $p->searchWarehousesByName($name);

        if (!$warehouses) return false;

        return $warehouses;
    }

    // Thêm kho chi nhánh
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status) {
        $p = new MWarehouse();
        return $p->addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status);
    }
}
?>
