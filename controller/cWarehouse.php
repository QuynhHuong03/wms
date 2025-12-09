<?php
include_once(__DIR__ . '/../model/mWarehouse.php');

class CWarehouse
{
    // 📦 Lấy tất cả kho
    public function getAllWarehouses()
    {
        $m = new MWarehouse();
        return $m->getAllWarehouses();
    }

    // 🔍 Tìm kho theo tên
    public function searchWarehousesByName($name)
    {
        $m = new MWarehouse();
        return $m->searchWarehousesByName($name);
    }

    // ➕ Thêm kho mới
    public function addWarehouse($data)
    {
        $m = new MWarehouse();
        return $m->addWarehouse($data);
    }

    // 🗑️ Xóa kho
    public function deleteWarehouse($warehouse_id)
    {
        $m = new MWarehouse();
        // Kiểm tra kho còn chứa sản phẩm không
        $productCount = $m->checkProductsInWarehouse($warehouse_id);
        if ($productCount > 0) {
            return 'HAS_PRODUCTS'; // Trả về mã lỗi đặc biệt
        }
        return $m->deleteWarehouse($warehouse_id);
    }

    // ✏️ Cập nhật kho
    public function updateWarehouse($warehouse_id, $data)
    {
        $m = new MWarehouse();
        return $m->updateWarehouse($warehouse_id, $data);
    }

    // 📍 Lấy thông tin kho theo ID
    public function getWarehouseById($warehouse_id)
    {
        $m = new MWarehouse();
        return $m->getWarehouseById($warehouse_id);
    }

    // 🏷️ Lấy kho theo loại (warehouse_type)
    public function getWarehousesByType($type)
    {
        $m = new MWarehouse();
        return $m->getWarehousesByType($type);
    }

    // 🏢 Thêm kho chi nhánh
    public function addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status)
    {
        $m = new MWarehouse();
        return $m->addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status);
    }
}
?>
