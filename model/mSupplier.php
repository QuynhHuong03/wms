<?php
include_once("connect.php");

class MSupplier {

    // Lấy tất cả nhà cung cấp
    public function getAllSuppliers() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('suppliers');
                $cursor = $col->find([]);
                $results = [];

                foreach ($cursor as $doc) {
                    $item = json_decode(json_encode($doc), true);
                    if (isset($item['_id']['$oid'])) {
                        $item['_id'] = $item['_id']['$oid'];
                    }
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

    // Tìm nhà cung cấp theo tên
    public function searchSuppliersByName($name) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('suppliers');
                $cursor = $col->find(['supplier_name' => ['$regex' => $name, '$options' => 'i']]);
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

    // Thêm nhà cung cấp
    public function addSupplier($supplier_name, $contact, $created_at, $contact_name, $tax_code, $country, $description) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('suppliers');

                // Tìm giá trị lớn nhất của supplier_id
                $maxIdDoc = $col->find([], [
                    'projection' => ['supplier_id' => 1],
                    'sort' => ['supplier_id' => -1],
                    'limit' => 1
                ])->toArray();

                $maxId = isset($maxIdDoc[0]['supplier_id']) ? (int)$maxIdDoc[0]['supplier_id'] : 0;

                // Tạo supplier_id mới
                $newSupplierId = $maxId + 1;

                // Thêm nhà cung cấp mới với ngày tạo và các trường bổ sung
                $insertResult = $col->insertOne([
                    'supplier_id'   => $newSupplierId,
                    'supplier_name' => $supplier_name,
                    'contact'       => $contact,
                    'contact_name'  => $contact_name,
                    'tax_code'      => $tax_code,
                    'country'       => $country,
                    'description'   => $description,
                    'status'        => 1, // Mặc định là 1
                    'created_at'    => $created_at // Ngày tạo
                ]);

                $p->dongKetNoi($con);
                return $insertResult->getInsertedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Kiểm tra nhà cung cấp còn sản phẩm
    public function checkProductsBySupplier($supplierId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                // Kiểm tra cả supplier_id và supplier.id
                $count = $col->countDocuments([
                    '$or' => [
                        ['supplier_id' => (int)$supplierId],
                        ['supplier_id' => (string)$supplierId],
                        ['supplier.id' => (string)$supplierId],
                        ['supplier.id' => (int)$supplierId]
                    ]
                ]);
                $p->dongKetNoi($con);
                return $count;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return 0;
    }

    // Xóa nhà cung cấp
    public function deleteSupplier($supplierId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('suppliers'); // Kiểm tra tên collection hoặc bảng
                $deleteResult = $col->deleteOne(['supplier_id' => $supplierId]); // Kiểm tra điều kiện xóa

                $p->dongKetNoi($con);
                return $deleteResult->getDeletedCount() > 0; // Kiểm tra số lượng bản ghi bị xóa
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage()); // Hiển thị lỗi chi tiết
            }
        }
        return false;
    }

    // Cập nhật thông tin nhà cung cấp
    public function updateSupplier($supplier_id, $supplier_name, $contact, $status, $contact_name = '', $tax_code = '', $country = '', $description = '') {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('suppliers');
                $existingSupplier = $col->findOne(['supplier_id' => (int)$supplier_id]);
                if (!$existingSupplier) {
                    die("Không tìm thấy nhà cung cấp với ID: $supplier_id");
                }

                $updateFields = [
                    'supplier_name' => $supplier_name,
                    'contact'       => $contact,
                    'status'        => (int)$status,
                    'contact_name'  => $contact_name,
                    'tax_code'      => $tax_code,
                    'country'       => $country,
                    'description'   => $description
                ];

                $updateResult = $col->updateOne(
                    ['supplier_id' => (int)$supplier_id],
                    ['$set' => $updateFields]
                );

                if ($updateResult->getModifiedCount() === 0) {
                    die("Không có bản ghi nào được cập nhật. Kiểm tra điều kiện hoặc dữ liệu.");
                }

                $p->dongKetNoi($con);
                return $updateResult->getModifiedCount() > 0;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // Lấy nhà cung cấp theo ID
    public function getSupplierById($supplierId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('suppliers');
                $supplier = $col->findOne(['supplier_id' => (int)$supplierId]);
                $p->dongKetNoi($con);
                return $supplier;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return null;
    }
}
?>