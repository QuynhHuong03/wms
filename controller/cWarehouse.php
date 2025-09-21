<?php
include_once(__DIR__ . '/../model/mWarehouse.php');

class CWarehouse {

    // Lấy tất cả loại kho
    public function getTypes() {
        $p = new MWarehouse();
        $types = $p->getWarehouseTypes();

        if (!$types || empty($types)) {
            return []; // Trả về mảng rỗng nếu không có dữ liệu
        }

        return $types; // Trả về danh sách loại kho
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

        $warehouses1 = $p->getWarehousesByType(1); // kho tổng
        $warehouses2 = $p->getWarehousesByType(2); // kho chi nhánh

        $types = $p->getWarehouseTypes(); // Lấy danh sách loại kho
        $typeMap = [];
        foreach ($types as $type) {
            $typeMap[$type['id']] = $type['name']; // Tạo map loại kho
        }

        $results = [];

        if (is_array($warehouses1) && !empty($warehouses1)) {
            foreach ($warehouses1 as &$warehouse) {
                $warehouse['type_name'] = $typeMap[$warehouse['warehouse_type']] ?? 'Không xác định';
            }
            $results = array_merge($results, $warehouses1);
        }

        if (is_array($warehouses2) && !empty($warehouses2)) {
            foreach ($warehouses2 as &$warehouse) {
                $warehouse['type_name'] = $typeMap[$warehouse['warehouse_type']] ?? 'Không xác định';
            }
            $results = array_merge($results, $warehouses2);
        }

        return $results; // Trả về danh sách kho
    }


    // Tìm kho theo tên (LIKE)
    public function searchWarehousesByName($name) {
        $p = new MWarehouse();
        return $p->searchWarehousesByName($name);
    }

    // Thêm kho chi nhánh
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status, $created_at) {
        $p = new MWarehouse();
        return $p->addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status, $created_at);
    }

    // Xóa kho
    public function deleteWarehouse($warehouseId) {
        $p = new MWarehouse();
        return $p->deleteWarehouse($warehouseId);
    }

    // Cập nhật thông tin kho
    public function updateWarehouse($warehouse_id, $warehouse_name, $address, $status) {
        $p = new MWarehouse();
        return $p->updateWarehouse($warehouse_id, $warehouse_name, $address, $status);
    }

    // Cập nhật thông tin nhà cung cấp
    public function updateSupplier($supplier_id, $supplier_name, $contact, $status) {
        $p = new MSupplier();
        return $p->updateSupplier($supplier_id, $supplier_name, $contact, $status);
    }
}
?>