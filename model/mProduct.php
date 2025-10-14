<?php
include_once("connect.php");

class MProduct {

    // 🧾 Lấy tất cả sản phẩm
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

    // 🔍 Tìm sản phẩm theo tên (LIKE)
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

    // ➕ Thêm sản phẩm mới (id tự tăng)
    public function addProduct($data) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $lastItem = $col->findOne([], ['sort' => ['id' => -1]]);
                $newId = isset($lastItem['id']) ? $lastItem['id'] + 1 : 1;
                $data['id'] = $newId;

                // Bổ sung thời gian tạo / cập nhật
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');

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

    // ❌ Xóa sản phẩm theo SKU
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

    // ✏️ Cập nhật sản phẩm (hỗ trợ đổi SKU)
    public function updateProduct(
        $old_sku,
        $new_sku,
        $product_name,
        $barcode,
        $category_id,
        $category_name,
        $supplier_id,
        $supplier_name,
        $status,
        $image = null,
        $min_stock = null
    ) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');

                $updateData = [
                    'sku' => $new_sku,
                    'product_name' => $product_name,
                    'barcode' => $barcode,
                    'category' => [
                        'id' => $category_id,
                        'name' => $category_name
                    ],
                    'supplier' => [
                        'id' => $supplier_id,
                        'name' => $supplier_name
                    ],
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

    // 🔎 Tìm sản phẩm theo barcode
    public function getProductByBarcode($barcode) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $doc = $col->findOne(['barcode' => $barcode]);
                $p->dongKetNoi($con);
                if ($doc) {
                    return json_decode(json_encode($doc), true);
                }
                return null;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return null;
    }

    // 📦 Tổng số SKU duy nhất
    public function getTotalSKU() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $uniqueSkus = $col->distinct('sku');
                $p->dongKetNoi($con);
                return count($uniqueSkus);
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi lấy tổng SKU: " . $e->getMessage());
            }
        }
        return 0;
    }

    // 📊 Tổng số lượng tồn (nếu có field quantity)
    public function getTotalQuantity() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $pipeline = [['$group' => ['_id' => null, 'totalQty' => ['$sum' => '$quantity']]]];
                $result = $col->aggregate($pipeline)->toArray();
                $p->dongKetNoi($con);
                return $result ? $result[0]['totalQty'] : 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi lấy tổng số lượng: " . $e->getMessage());
            }
        }
        return 0;
    }
}
?>
