<?php
include_once(__DIR__ . '/../model/mSupplier.php');

class CSupplier {

    // Lấy tất cả nhà cung cấp
    public function getAllSuppliers() {
        $p = new MSupplier();
        return $p->getAllSuppliers();
    }

    // Tìm nhà cung cấp theo tên
    public function searchSuppliersByName($name) {
        $p = new MSupplier();
        return $p->searchSuppliersByName($name);
    }

    // Thêm nhà cung cấp
    public function addSupplier($supplier_name, $contact, $created_at, $contact_name, $tax_code, $country, $description) {
        $p = new MSupplier();
        return $p->addSupplier($supplier_name, $contact, $created_at, $contact_name, $tax_code, $country, $description);
    }

    // Xóa nhà cung cấp
    public function deleteSupplier($supplierId) {
        $p = new MSupplier();
        // Kiểm tra nhà cung cấp còn sản phẩm không
        $productCount = $p->checkProductsBySupplier($supplierId);
        if ($productCount > 0) {
            return 'HAS_PRODUCTS'; // Trả về mã lỗi đặc biệt
        }
        $result = $p->deleteSupplier($supplierId);
        return $result;
    }

    // Cập nhật thông tin nhà cung cấp
    public function updateSupplier($supplier_id, $supplier_name, $contact, $status, $contact_name = '', $tax_code = '', $country = '', $description = '') {
        $p = new MSupplier();
        return $p->updateSupplier($supplier_id, $supplier_name, $contact, $status, $contact_name, $tax_code, $country, $description);
    }

    // Lấy thông tin nhà cung cấp theo ID
    public function getSupplierById($supplierId) {
        $p = new MSupplier();
        return $p->getSupplierById($supplierId);
    }
}
?>