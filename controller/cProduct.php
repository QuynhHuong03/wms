<?php
include_once(__DIR__ . '/../model/mProduct.php');

class CProduct {
	// Lấy tất cả sản phẩm
	public function getAllProducts() {
		$p = new MProduct();
		return $p->getAllProducts();
	}

	// Tìm sản phẩm theo tên
	public function searchProductsByName($name) {
		$p = new MProduct();
		return $p->searchProductsByName($name);
	}

	// Thêm sản phẩm mới
	public function addProduct($data) {
		$p = new MProduct();
		return $p->addProduct($data);
	}

    // Xóa sản phẩm
    public function deleteProduct($sku) {
        $p = new MProduct();
        return $p->deleteProduct($sku);
    }

	// Cập nhật sản phẩm
	// Cập nhật sản phẩm, cho phép đổi SKU
	public function updateProduct($old_sku, $new_sku, $product_name, $barcode, $category_id, $category_name, $supplier_id, $supplier_name, $status, $image = null, $min_stock = null)
{
		$p = new MProduct();
		return $p->updateProduct($old_sku, $new_sku, $product_name, $barcode, $category_name, $supplier_name, $warehouse_id, $status, $image, $min_stock);
	}
	

	// Tìm sản phẩm theo barcode
    public function getProductByBarcode($barcode) {
        $p = new MProduct();
        return $p->getProductByBarcode($barcode);
    }

    // Tìm sản phẩm theo _id
    public function getProductById($productId) {
        $p = new MProduct();
        return $p->getProductById($productId);
    }
}
