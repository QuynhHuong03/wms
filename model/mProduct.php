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
                
                // Lấy product_id lớn nhất từ trường 'id'
                $lastItem = $col->findOne([], ['sort' => ['id' => -1]]);
                $newId = isset($lastItem['id']) ? $lastItem['id'] + 1 : 1;
                $data['id'] = $newId;
                
                // Auto-generate SKU nếu chưa có hoặc rỗng
                if (empty($data['sku']) || trim($data['sku']) === '') {
                    // Lấy category_code từ database
                    $catCode = 'PROD'; // Mặc định nếu không tìm thấy category
                    
                    if (isset($data['category']) && is_array($data['category'])) {
                        // Thử lấy từ category object trước
                        if (!empty($data['category']['code'])) {
                            $catCode = $data['category']['code'];
                        } elseif (!empty($data['category']['category_code'])) {
                            $catCode = $data['category']['category_code'];
                        } elseif (!empty($data['category']['id'])) {
                            // Nếu chỉ có id, tra database categories để lấy category_code
                            try {
                                $categoryId = $data['category']['id'];
                                $categoryCol = $con->selectCollection('categories');
                                $categoryDoc = $categoryCol->findOne(
                                    ['category_id' => $categoryId]
                                );
                                if ($categoryDoc && isset($categoryDoc['category_code'])) {
                                    $catCode = $categoryDoc['category_code'];
                                }
                            } catch (\Exception $e) {
                                error_log('Error fetching category_code: ' . $e->getMessage());
                            }
                        }
                    }
                    
                    // Xóa các ký tự không phải chữ cái/số trong category code
                    $catCode = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($catCode));
                    
                    // Tạo SKU = category_code + product_id
                    $data['sku'] = $catCode . $newId;
                }

                // Bổ sung thời gian tạo / cập nhật
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');

                $insertResult = $col->insertOne($data);
                $p->dongKetNoi($con);
                if ($insertResult->getInsertedCount() > 0) {
                    $insertedId = $insertResult->getInsertedId();
                    // Normalize ObjectId to string when possible
                    if (is_object($insertedId) && method_exists($insertedId, '__toString')) {
                        return (string)$insertedId;
                    }
                    return $insertedId;
                }
                return false;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                die("Lỗi query MongoDB: " . $e->getMessage());
            }
        }
        return false;
    }

    // ⭐ Kiểm tra sản phẩm còn tồn kho
    public function checkProductInWarehouse($sku) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                // Lấy product_id từ sku
                $prodCol = $con->selectCollection('products');
                $product = $prodCol->findOne(['sku' => $sku]);
                
                if (!$product || !isset($product['_id'])) {
                    $p->dongKetNoi($con);
                    return 0;
                }
                
                $productId = (string)$product['_id'];
                
                // Kiểm tra trong inventory
                $invCol = $con->selectCollection('inventory');
                $count = $invCol->countDocuments([
                    'product_id' => $productId,
                    'qty' => ['$gt' => 0]
                ]);
                
                $p->dongKetNoi($con);
                return $count;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi checkProductInWarehouse: " . $e->getMessage());
                return 0;
            }
        }
        return 0;
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
                $product = json_decode(json_encode($doc), true);

                // ✅ Xử lý đúng _id từ MongoDB
                $id = '';
                if (isset($product['_id'])) {
                    if (is_array($product['_id']) && isset($product['_id']['$oid'])) {
                        $id = (string)$product['_id']['$oid'];
                    } else {
                        $id = (string)$product['_id'];
                    }
                }

                return [
                    '_id' => $id,
                    'sku' => $product['sku'] ?? '',
                    'barcode' => $product['barcode'] ?? '',
                    'product_name' => $product['product_name'] ?? '',
                    'purchase_price' => $product['purchase_price'] ?? 0,
                    'baseUnit' => $product['baseUnit'] ?? 'cái',
                    'conversionUnits' => $product['conversionUnits'] ?? [],
                    'package_dimensions' => $product['package_dimensions'] ?? [],
                    'package_weight' => $product['package_weight'] ?? 0,
                    'volume_per_unit' => $product['volume_per_unit'] ?? 0,
                    'supplier' => $product['supplier']['name'] ?? '',
                    'category' => $product['category']['name'] ?? '',
                    'current_stock' => $product['current_stock'] ?? 0,
                ];
            }
            return null;

        } catch (\Exception $e) {
            $p->dongKetNoi($con);
            die("Lỗi query MongoDB: " . $e->getMessage());
        }
    }
    return null;
}

    // 🔎 Tìm sản phẩm theo _id
    public function getProductById($productId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                
                // Xử lý _id (có thể là ObjectId hoặc string)
                try {
                    $filter = ['_id' => new MongoDB\BSON\ObjectId($productId)];
                } catch (\Exception $e) {
                    // Nếu không phải ObjectId, thử tìm theo string
                    $filter = ['_id' => $productId];
                }
                
                $doc = $col->findOne($filter);
                $p->dongKetNoi($con);

                if ($doc) {
                    $product = json_decode(json_encode($doc), true);

                    // ✅ Xử lý đúng _id từ MongoDB
                    $id = '';
                    if (isset($product['_id'])) {
                        if (is_array($product['_id']) && isset($product['_id']['$oid'])) {
                            $id = (string)$product['_id']['$oid'];
                        } else {
                            $id = (string)$product['_id'];
                        }
                    }

                    // ✅ Lấy kích thước từ package_dimensions hoặc dimensions
                    $dimensions = [];
                    if (isset($product['package_dimensions']) && is_array($product['package_dimensions'])) {
                        $dimensions = $product['package_dimensions'];
                    } elseif (isset($product['dimensions']) && is_array($product['dimensions'])) {
                        $dimensions = $product['dimensions'];
                    }
                    
                    $finalDimensions = [
                        'width' => $dimensions['width'] ?? 0,
                        'depth' => $dimensions['depth'] ?? 0,
                        'height' => $dimensions['height'] ?? 0
                    ];
                    
                    return [
                        '_id' => $id,
                        'sku' => $product['sku'] ?? '',
                        'barcode' => $product['barcode'] ?? '',
                        'name' => $product['product_name'] ?? '',
                        'product_name' => $product['product_name'] ?? '',
                        'purchase_price' => $product['purchase_price'] ?? 0,
                        'baseUnit' => $product['baseUnit'] ?? 'cái',
                        'conversionUnits' => $product['conversionUnits'] ?? [],
                        'supplier' => $product['supplier']['name'] ?? '',
                        'category' => $product['category']['name'] ?? '',
                        'current_stock' => $product['current_stock'] ?? 0,
                        'dimensions' => $finalDimensions,
                        'package_dimensions' => $finalDimensions, // Add this for compatibility
                        'weight' => $product['package_weight'] ?? ($product['weight'] ?? 0),
                        'volume_per_unit' => $product['volume_per_unit'] ?? 0,
                        'stackable' => $product['stackable'] ?? false,
                        'max_stack_height' => $product['max_stack_height'] ?? 1,
                    ];
                }
                return null;

            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi getProductById: " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    // Lấy sản phẩm theo SKU
    public function getProductBySKU($sku) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $doc = $col->findOne(['sku' => $sku]);
                $p->dongKetNoi($con);

                if ($doc) {
                    $product = json_decode(json_encode($doc), true);

                    // Xử lý _id
                    $id = '';
                    if (isset($product['_id'])) {
                        if (is_array($product['_id']) && isset($product['_id']['$oid'])) {
                            $id = (string)$product['_id']['$oid'];
                        } else {
                            $id = (string)$product['_id'];
                        }
                    }

                    return [
                        '_id' => $id,
                        'sku' => $product['sku'] ?? '',
                        'barcode' => $product['barcode'] ?? '',
                        'name' => $product['product_name'] ?? '',
                        'product_name' => $product['product_name'] ?? '',
                        'purchase_price' => $product['purchase_price'] ?? 0,
                        'baseUnit' => $product['baseUnit'] ?? 'cái',
                        'conversionUnits' => $product['conversionUnits'] ?? [],
                        'supplier' => $product['supplier']['name'] ?? '',
                        'category' => $product['category']['name'] ?? '',
                        'current_stock' => $product['current_stock'] ?? 0,
                    ];
                }
                return null;

            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi getProductBySKU: " . $e->getMessage());
                return null;
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

    // ⚠️ Lấy sản phẩm dưới mức tồn kho tối thiểu (min_stock) theo kho
    // ⭐ CẬP NHẬT: Chỉ lấy sản phẩm CÓ trong inventory của warehouse đó
    public function getProductsBelowMinStock($warehouseId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $colProducts = $con->selectCollection('products');
                $colInventory = $con->selectCollection('inventory');
                
                // ⭐ Lấy danh sách product_id CÓ TRONG inventory của warehouse này
                $inventoryPipeline = [
                    ['$match' => ['warehouse_id' => $warehouseId]],
                    ['$group' => [
                        '_id' => '$product_id',
                        'current_stock' => ['$sum' => '$qty']
                    ]]
                ];
                
                $inventoryData = $colInventory->aggregate($inventoryPipeline)->toArray();
                $stockMap = [];
                $productIdsInWarehouse = [];
                
                foreach ($inventoryData as $item) {
                    $productId = (string)($item['_id'] ?? '');
                    $stockMap[$productId] = (int)($item['current_stock'] ?? 0);
                    $productIdsInWarehouse[] = $productId;
                }
                
                // ⭐ CHỈ lấy sản phẩm có trong inventory của kho này
                if (empty($productIdsInWarehouse)) {
                    $p->dongKetNoi($con);
                    return [];
                }
                
                // Chuyển đổi sang ObjectId nếu cần
                $productObjectIds = [];
                foreach ($productIdsInWarehouse as $pid) {
                    try {
                        $productObjectIds[] = new MongoDB\BSON\ObjectId($pid);
                    } catch (\Exception $e) {
                        // Nếu không phải ObjectId, giữ nguyên string
                        $productObjectIds[] = $pid;
                    }
                }
                
                // Lấy thông tin sản phẩm CHỈ từ danh sách có trong inventory
                $products = $colProducts->find([
                    '_id' => ['$in' => $productObjectIds],
                    'min_stock' => ['$exists' => true, '$gt' => 0]
                ])->toArray();
                
                $results = [];
                foreach ($products as $product) {
                    $productJson = json_decode(json_encode($product), true);
                    
                    // Lấy product_id
                    $productId = '';
                    if (isset($productJson['_id'])) {
                        if (is_array($productJson['_id']) && isset($productJson['_id']['$oid'])) {
                            $productId = $productJson['_id']['$oid'];
                        } else {
                            $productId = (string)$productJson['_id'];
                        }
                    }
                    
                    $minStock = (int)($productJson['min_stock'] ?? 0);
                    $currentStock = (int)($stockMap[$productId] ?? 0);
                    
                    // Chỉ lấy sản phẩm có tồn kho < min_stock
                    if ($currentStock < $minStock) {
                        $shortage = $minStock - $currentStock;
                        $shortagePercent = $minStock > 0 ? (($shortage / $minStock) * 100) : 0;
                        
                        $productJson['current_stock'] = $currentStock;
                        $productJson['shortage'] = $shortage;
                        $productJson['shortage_percent'] = $shortagePercent;
                        
                        $results[] = $productJson;
                    }
                }
                
                // Sort theo shortage_percent giảm dần (sản phẩm thiếu nhiều nhất trước)
                usort($results, function($a, $b) {
                    $percentA = $a['shortage_percent'] ?? 0;
                    $percentB = $b['shortage_percent'] ?? 0;
                    if ($percentA == $percentB) {
                        return ($b['shortage'] ?? 0) - ($a['shortage'] ?? 0);
                    }
                    return $percentB <=> $percentA;
                });
                
                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi getProductsBelowMinStock: " . $e->getMessage());
                return [];
            }
        }
        return [];
    }

    // ⭐ Lấy TẤT CẢ sản phẩm có trong inventory của kho với thông tin đầy đủ
    public function getAllProductsInWarehouse($warehouseId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $colProducts = $con->selectCollection('products');
                $colInventory = $con->selectCollection('inventory');
                
                // Lấy tồn kho từ inventory
                $inventoryPipeline = [
                    ['$match' => ['warehouse_id' => $warehouseId]],
                    ['$group' => [
                        '_id' => '$product_id',
                        'current_stock' => ['$sum' => '$qty']
                    ]]
                ];
                
                $inventoryData = $colInventory->aggregate($inventoryPipeline)->toArray();
                $stockMap = [];
                $productIdsInWarehouse = [];
                
                foreach ($inventoryData as $item) {
                    $productId = (string)($item['_id'] ?? '');
                    $stockMap[$productId] = (int)($item['current_stock'] ?? 0);
                    $productIdsInWarehouse[] = $productId;
                }
                
                if (empty($productIdsInWarehouse)) {
                    $p->dongKetNoi($con);
                    return [];
                }
                
                // Chuyển đổi sang ObjectId nếu cần
                $productObjectIds = [];
                foreach ($productIdsInWarehouse as $pid) {
                    try {
                        $productObjectIds[] = new MongoDB\BSON\ObjectId($pid);
                    } catch (\Exception $e) {
                        $productObjectIds[] = $pid;
                    }
                }
                
                // Lấy thông tin sản phẩm
                $products = $colProducts->find([
                    '_id' => ['$in' => $productObjectIds]
                ])->toArray();
                
                $results = [];
                foreach ($products as $product) {
                    $productJson = json_decode(json_encode($product), true);
                    
                    // Lấy product_id
                    $productId = '';
                    if (isset($productJson['_id'])) {
                        if (is_array($productJson['_id']) && isset($productJson['_id']['$oid'])) {
                            $productId = $productJson['_id']['$oid'];
                        } else {
                            $productId = (string)$productJson['_id'];
                        }
                    }
                    
                    $currentStock = (int)($stockMap[$productId] ?? 0);
                    $minStock = (int)($productJson['min_stock'] ?? 0);
                    
                    $productJson['current_stock'] = $currentStock;
                    $productJson['needs_restock'] = ($minStock > 0 && $currentStock < $minStock);
                    $productJson['shortage'] = $productJson['needs_restock'] ? ($minStock - $currentStock) : 0;
                    
                    $results[] = $productJson;
                }
                
                $p->dongKetNoi($con);
                return $results;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi getAllProductsInWarehouse: " . $e->getMessage());
                return [];
            }
        }
        return [];
    }

    // 📊 Lấy tồn kho của sản phẩm tại các kho khác
    // ⭐ CẬP NHẬT: Lấy từ inventory
    public function getStockByWarehouses($productId) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $colInventory = $con->selectCollection('inventory');
                $colWarehouses = $con->selectCollection('warehouses');
                
                // Lấy tồn kho từ inventory
                $pipeline = [
                    ['$match' => ['product_id' => $productId]],
                    ['$group' => [
                        '_id' => '$warehouse_id',
                        'quantity' => ['$sum' => '$qty']
                    ]]
                ];
                
                $stockData = $colInventory->aggregate($pipeline)->toArray();
                $result = [];
                
                foreach ($stockData as $item) {
                    $warehouseId = $item['_id'] ?? '';
                    $qty = (int)($item['quantity'] ?? 0);
                    
                    if ($warehouseId && $qty > 0) {
                        // Lấy tên kho
                        $warehouse = $colWarehouses->findOne(['warehouse_id' => $warehouseId]);
                        $warehouseName = $warehouse['warehouse_name'] ?? $warehouseId;
                        
                        $result[$warehouseId] = [
                            'warehouse_name' => $warehouseName,
                            'quantity' => $qty
                        ];
                    }
                }
                
                $p->dongKetNoi($con);
                return $result;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi getStockByWarehouses: " . $e->getMessage());
                return [];
            }
        }
        return [];
    }

    // 🔢 Tạo barcode tự động không trùng
    // Format: 8 chữ số, bắt đầu từ 10000000
    public function generateUniqueBarcode() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                
                // Tìm barcode lớn nhất hiện tại (chỉ lấy barcode số)
                $pipeline = [
                    [
                        '$project' => [
                            'barcode' => 1,
                            'barcodeNum' => [
                                '$toLong' => [
                                    '$cond' => [
                                        ['$regexMatch' => ['input' => '$barcode', 'regex' => '^[0-9]+$']],
                                        '$barcode',
                                        '0'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    ['$sort' => ['barcodeNum' => -1]],
                    ['$limit' => 1]
                ];
                
                $result = $col->aggregate($pipeline)->toArray();
                
                if (!empty($result) && isset($result[0]['barcodeNum'])) {
                    $maxBarcode = (int)$result[0]['barcodeNum'];
                    // Nếu barcode hiện tại nhỏ hơn 10000000, bắt đầu từ 10000000
                    $newBarcode = max($maxBarcode + 1, 10000000);
                } else {
                    // Không có barcode nào, bắt đầu từ 10000000
                    $newBarcode = 10000000;
                }
                
                // Kiểm tra trùng lặp (phòng trường hợp có barcode không phải số thuần)
                $maxAttempts = 100;
                $attempt = 0;
                while ($attempt < $maxAttempts) {
                    $exists = $col->findOne(['barcode' => (string)$newBarcode]);
                    if (!$exists) {
                        $p->dongKetNoi($con);
                        return str_pad($newBarcode, 8, '0', STR_PAD_LEFT);
                    }
                    $newBarcode++;
                    $attempt++;
                }
                
                $p->dongKetNoi($con);
                error_log("Không thể tạo barcode duy nhất sau $maxAttempts lần thử");
                return null;
                
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi generateUniqueBarcode: " . $e->getMessage());
                return null;
            }
        }
        return null;
    }

    // 🔍 Kiểm tra barcode có tồn tại không
    public function isBarcodeExists($barcode) {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            try {
                $col = $con->selectCollection('products');
                $exists = $col->findOne(['barcode' => $barcode]);
                $p->dongKetNoi($con);
                return $exists !== null;
            } catch (\Exception $e) {
                $p->dongKetNoi($con);
                error_log("Lỗi isBarcodeExists: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }
}
?>
