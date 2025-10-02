<?php
include_once(__DIR__ . '/../model/mWarehouse_type.php');

class CWarehouseType {

    // Lấy tất cả loại kho
    public function getAllWarehouseTypes() {
        $p = new MWarehouseType();
        return $p->getAllWarehouseTypes();
    }

    // Thêm loại kho
    public function addWarehouseType($warehouse_type_id, $name, $created_at) {
        $p = new MWarehouseType();
        return $p->addWarehouseType($warehouse_type_id, $name, $created_at);
    }

    // Xóa loại kho
    public function deleteWarehouseType($warehouse_type_id) {
        $p = new MWarehouseType();
        return $p->deleteWarehouseType($warehouse_type_id);
    }

    // Cập nhật loại kho
    public function updateWarehouseType($warehouse_type_id, $name) {
        $p = new MWarehouseType();
        return $p->updateWarehouseType($warehouse_type_id, $name);
    }

    // Tìm kiếm loại kho theo tên
    public function searchWarehouseTypesByName($name) {
        $p = new MWarehouseType();
        return $p->searchWarehouseTypesByName($name);
    }
}
?>