<?php
include_once("connect.php");

class MProduct {
	// Lấy tất cả sản phẩm
	public function getAllProducts() {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('products');
				$cursor = $col->find([], ['sort' => ['sku' => 1]]);
				$results = [];
				foreach ($cursor as $doc) {
					$item = json_decode(json_encode($doc), true);
					$results[] = $item;
				}
				$p->dongKetNoi($con);
				return $results;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Tìm sản phẩm theo tên (LIKE)
	public function searchProductsByName($name) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('products');
				$cursor = $col->find(['product_name' => ['$regex' => $name, '$options' => 'i']]);
				$results = [];
				foreach ($cursor as $doc) {
					$item = json_decode(json_encode($doc), true);
					$results[] = $item;
				}
				$p->dongKetNoi($con);
				return $results;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Thêm sản phẩm mới, id tự tăng
	public function addProduct($data) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('products');
				// Lấy id lớn nhất hiện tại
				$lastItem = $col->findOne([], ['sort' => ['id' => -1]]);
				$newId = isset($lastItem['id']) ? $lastItem['id'] + 1 : 1;
				$data['id'] = $newId;
				$insertResult = $col->insertOne($data);
				$p->dongKetNoi($con);
				return $insertResult->getInsertedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

    // Xóa sản phẩm
    public function deleteProduct($sku) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $deleteResult = $col->deleteOne(['sku' => $sku]);
                $p->dongKetNoi($con);
                return $deleteResult->getDeletedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

	// Cập nhật sản phẩm, cho phép đổi SKU và cập nhật updated_at
	public function updateProduct($old_sku, $new_sku, $product_name, $barcode, $category_name, $supplier_name, $warehouse_id, $status, $image = null, $min_stock = null) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('products');
				$updateData = [
					'sku' => $new_sku,
					'product_name' => $product_name,
					'barcode' => $barcode,
					'category_name' => $category_name,
					'supplier_name' => $supplier_name,
					'warehouse_id' => $warehouse_id,
					'status' => (int)$status,
					'updated_at' => date('Y-m-d H:i:s')
				];
				if ($min_stock !== null) {
					$updateData['min_stock'] = (int)$min_stock;
				}
				if ($image !== null) {
					$updateData['image'] = $image;
				}
				$updateResult = $col->updateOne(
					['sku' => $old_sku],
					['$set' => $updateData]
				);
				$p->dongKetNoi($con);
				return $updateResult->getModifiedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}
}
