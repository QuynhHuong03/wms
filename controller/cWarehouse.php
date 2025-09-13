<?php
include_once(__DIR__ . '/../model/mWarehouse.php');

class CWarehouse {
    public function getTypes() {
        $p = new MWarehouse();
        $types = $p->getWarehouseTypes();
        if (!$types) return -1;
        return (count($types) > 0) ? $types : 0;
    }

    public function getWarehousesByType($type_id) {
        $p = new MWarehouse();
        return $p->getWarehousesByType($type_id);
    }

    public function searchWarehousesByName($name) {
        $p = new MWarehouse();
        return $p->searchWarehousesByName($name);
    }

    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status) {
        $p = new MWarehouse();
        return $p->addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status);
    }

    // Có thể bổ sung các hàm khác nếu cần
}
?>