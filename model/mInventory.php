<?php
include_once('connect.php');

class MInventory {
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $this->col = $con->selectCollection('inventory');
        }
    }

    // Insert a stock entry document
    // Expected fields: warehouse_id, product_id, qty, receipt_id, zone_id, rack_id, bin_id, received_at
    public function insertEntry($data) {
        if (!$this->col) return false;
        try {
            if (empty($data['received_at'])) {
                $data['received_at'] = new MongoDB\BSON\UTCDateTime();
            }
            $result = $this->col->insertOne($data);
            return $result->getInsertedCount() > 0;
        } catch (\Throwable $e) {
            error_log('insertEntry error: '.$e->getMessage());
            return false;
        }
    }

    // Build a MongoDB filter from UI params
    private function buildQuery($filters) {
        $query = [];

        // Warehouse filter
        if (!empty($filters['warehouse_id'])) {
            $query['warehouse_id'] = $filters['warehouse_id'];
        }

        // Product filters
        if (!empty($filters['product_id'])) {
            $vals = [(string)$filters['product_id']];
            // If looks like a Mongo ObjectId, add it too
            if (preg_match('/^[0-9a-fA-F]{24}$/', (string)$filters['product_id'])) {
                try {
                    $vals[] = new MongoDB\BSON\ObjectId((string)$filters['product_id']);
                } catch (\Throwable $e) { /* ignore */ }
            }
            $query['product_id'] = ['$in' => $vals];
        }
        if (!empty($filters['product_sku'])) {
            $query['product_sku'] = $filters['product_sku'];
        }

        // Date range on received_at
        $dateFilter = [];
        try {
            if (!empty($filters['from'])) {
                $dt = new DateTime($filters['from'] . ' 00:00:00');
                $dateFilter['$gte'] = new MongoDB\BSON\UTCDateTime($dt);
            }
            if (!empty($filters['to'])) {
                $dt = new DateTime($filters['to'] . ' 23:59:59');
                $dateFilter['$lte'] = new MongoDB\BSON\UTCDateTime($dt);
            }
        } catch (\Throwable $e) {
            // ignore date parse errors
        }
        if (!empty($dateFilter)) {
            $query['received_at'] = $dateFilter;
        }

        // Free-text search across likely string fields
        if (!empty($filters['q'])) {
            $regex = new MongoDB\BSON\Regex($filters['q'], 'i');
            $or = [];
            // Only target fields that are commonly stored as strings
            foreach ([
                'receipt_id','receipt_code','product_id','product_sku','product_name',
                'warehouse_id','zone_id','rack_id','bin_id','bin_code'
            ] as $f) {
                $or[] = [$f => $regex];
            }
            if (!empty($or)) {
                $query['$or'] = $or;
            }
        }

        return $query;
    }

    // List inventory movements with pagination and sorting
    public function listEntries($filters = [], $page = 1, $limit = 20, $sort = ['received_at' => -1]) {
        if (!$this->col) return [];
        $page = max(1, intval($page));
        $limit = max(1, min(200, intval($limit)));
        $skip = ($page - 1) * $limit;
        try {
            $query = $this->buildQuery($filters);
            $cursor = $this->col->find($query, [
                'sort' => $sort,
                'skip' => $skip,
                'limit' => $limit
            ]);
            return iterator_to_array($cursor, false);
        } catch (\Throwable $e) {
            error_log('listEntries error: '.$e->getMessage());
            return [];
        }
    }

    // Count entries for pagination
    public function countEntries($filters = []) {
        if (!$this->col) return 0;
        try {
            $query = $this->buildQuery($filters);
            return $this->col->countDocuments($query);
        } catch (\Throwable $e) {
            error_log('countEntries error: '.$e->getMessage());
            return 0;
        }
    }

    // Group inventory by product (within filters) and return paginated groups
    public function groupByProduct($filters = [], $page = 1, $limit = 20, $sort = ['lastTime' => -1]) {
        if (!$this->col) return [];
        $page = max(1, intval($page));
        $limit = max(1, min(200, intval($limit)));
        $skip = ($page - 1) * $limit;
        try {
            $match = $this->buildQuery($filters);
            $pipeline = [];
            if (!empty($match)) $pipeline[] = ['$match' => $match];
            $pipeline[] = [
                '$group' => [
                    '_id' => [
                        'product_id' => ['$ifNull' => ['$product_id', '']],
                        'product_sku' => ['$ifNull' => ['$product_sku', '']]
                    ],
                    'totalQty' => ['$sum' => ['$ifNull' => ['$qty', 0]]],
                    'lastTime' => ['$max' => ['$ifNull' => ['$received_at', '$created_at']]],
                    'product_id' => ['$first' => '$product_id'],
                    'product_sku' => ['$first' => '$product_sku']
                ]
            ];
            // Project to flatten the structure
            $pipeline[] = [
                '$project' => [
                    '_id' => 0,
                    'product_id' => '$product_id',
                    'product_sku' => '$product_sku',
                    'totalQty' => '$totalQty',
                    'lastTime' => '$lastTime'
                ]
            ];
            // Sort
            if (!empty($sort)) {
                $pipeline[] = ['$sort' => $sort];
            }
            if ($skip > 0) $pipeline[] = ['$skip' => $skip];
            if ($limit > 0) $pipeline[] = ['$limit' => $limit];

            $cursor = $this->col->aggregate($pipeline);
            return iterator_to_array($cursor, false);
        } catch (\Throwable $e) {
            error_log('groupByProduct error: '.$e->getMessage());
            return [];
        }
    }

    public function countGroupsByProduct($filters = []) {
        if (!$this->col) return 0;
        try {
            $match = $this->buildQuery($filters);
            $pipeline = [];
            if (!empty($match)) $pipeline[] = ['$match' => $match];
            $pipeline[] = [
                '$group' => [
                    '_id' => [
                        'product_id' => ['$ifNull' => ['$product_id', '']],
                        'product_sku' => ['$ifNull' => ['$product_sku', '']]
                    ]
                ]
            ];
            $pipeline[] = ['$count' => 'cnt'];
            $res = $this->col->aggregate($pipeline)->toArray();
            if ($res && isset($res[0]['cnt'])) return (int)$res[0]['cnt'];
            return 0;
        } catch (\Throwable $e) {
            error_log('countGroupsByProduct error: '.$e->getMessage());
            return 0;
        }
    }

    // Aggregate quantity by bin for a given product (and filters)
    public function aggregateByBin($filters = [], $sort = ['totalQty' => -1]) {
        if (!$this->col) return [];
        try {
            $match = $this->buildQuery($filters);
            $pipeline = [];
            if (!empty($match)) $pipeline[] = ['$match' => $match];
            $pipeline[] = [
                '$group' => [
                    '_id' => [
                        'warehouse_id' => '$warehouse_id',
                        'zone_id' => ['$ifNull' => ['$zone_id', '']],
                        'rack_id' => ['$ifNull' => ['$rack_id', '']],
                        'bin_id' => ['$ifNull' => ['$bin_id', '']],
                        'bin_code' => ['$ifNull' => ['$bin_code', '']]
                    ],
                    'totalQty' => ['$sum' => ['$ifNull' => ['$qty', 0]]],
                    'lastTime' => ['$max' => ['$ifNull' => ['$received_at', '$created_at']]]
                ]
            ];
            // Only keep positive stock
            $pipeline[] = ['$match' => ['totalQty' => ['$gt' => 0]]];
            if (!empty($sort)) $pipeline[] = ['$sort' => $sort];
            $pipeline[] = ['$project' => [
                '_id' => 0,
                'warehouse_id' => '$_id.warehouse_id',
                'zone_id' => '$_id.zone_id',
                'rack_id' => '$_id.rack_id',
                'bin_id' => '$_id.bin_id',
                'bin_code' => '$_id.bin_code',
                'qty' => '$totalQty',
                'lastTime' => '$lastTime'
            ]];
            $cursor = $this->col->aggregate($pipeline);
            return iterator_to_array($cursor, false);
        } catch (\Throwable $e) {
            error_log('aggregateByBin error: '.$e->getMessage());
            return [];
        }
    }

    // Get latest inventory entry for a product in a warehouse
    public function getLatestEntryByProduct($productId, $warehouseId) {
        if (!$this->col) return null;
        try {
            $query = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId
            ];
            
            $entry = $this->col->findOne($query, [
                'sort' => ['received_at' => -1, 'created_at' => -1]
            ]);
            
            return $entry ? json_decode(json_encode($entry), true) : null;
        } catch (\Throwable $e) {
            error_log('getLatestEntryByProduct error: ' . $e->getMessage());
            return null;
        }
    }
    
    // Find one inventory entry by filter
    public function findEntry($filter) {
        if (!$this->col) return null;
        try {
            return $this->col->findOne($filter);
        } catch (\Throwable $e) {
            error_log('findEntry error: ' . $e->getMessage());
            return null;
        }
    }
    
    // Find multiple inventory entries
    public function findEntries($filter, $options = []) {
        if (!$this->col) return [];
        try {
            $cursor = $this->col->find($filter, $options);
            return iterator_to_array($cursor);
        } catch (\Throwable $e) {
            error_log('findEntries error: ' . $e->getMessage());
            return [];
        }
    }
    
    // Update inventory entry by ID
    public function updateEntry($id, $data) {
        if (!$this->col) return false;
        try {
            // Convert string ID to ObjectId if needed
            if (is_string($id)) {
                $id = new MongoDB\BSON\ObjectId($id);
            }
            
            $result = $this->col->updateOne(
                ['_id' => $id],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Throwable $e) {
            error_log('updateEntry error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Delete inventory entry by ID
    public function deleteEntry($id) {
        if (!$this->col) return false;
        try {
            // Convert string ID to ObjectId if needed
            if (is_string($id)) {
                $id = new MongoDB\BSON\ObjectId($id);
            }
            
            $result = $this->col->deleteOne(['_id' => $id]);
            return $result->getDeletedCount() > 0;
        } catch (\Throwable $e) {
            error_log('deleteEntry error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Sum all quantities for a specific bin
    public function sumQuantityByBin($warehouseId, $zoneId, $rackId, $binId) {
        if (!$this->col) return 0;
        try {
            $pipeline = [
                [
                    '$match' => [
                        'warehouse_id' => $warehouseId,
                        'zone_id' => $zoneId,
                        'rack_id' => $rackId,
                        'bin_id' => $binId
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'totalQty' => ['$sum' => '$qty']
                    ]
                ]
            ];
            
            $result = $this->col->aggregate($pipeline)->toArray();
            if (!empty($result)) {
                return (float)($result[0]['totalQty'] ?? 0);
            }
            return 0;
        } catch (\Throwable $e) {
            error_log('sumQuantityByBin error: ' . $e->getMessage());
            return 0;
        }
    }
    
    // Get all inventory entries for a warehouse
    public function getInventoryByWarehouse($warehouseId) {
        if (!$this->col) return [];
        try {
            $cursor = $this->col->find(['warehouse_id' => $warehouseId]);
            return iterator_to_array($cursor, false);
        } catch (\Throwable $e) {
            error_log('getInventoryByWarehouse error: ' . $e->getMessage());
            return [];
        }
    }

    // ⭐ Lấy tổng tồn kho của sản phẩm tại một kho cụ thể
    public function getTotalStockByProduct($warehouseId, $productId) {
        if (!$this->col) return 0;
        try {
            $pipeline = [
                [
                    '$match' => [
                        'warehouse_id' => $warehouseId,
                        'product_id' => $productId
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'totalQty' => ['$sum' => '$qty']
                    ]
                ]
            ];
            
            $result = $this->col->aggregate($pipeline)->toArray();
            if (!empty($result)) {
                return (float)($result[0]['totalQty'] ?? 0);
            }
            return 0;
        } catch (\Throwable $e) {
            error_log('getTotalStockByProduct error: ' . $e->getMessage());
            return 0;
        }
    }

    // ⭐ Lấy tồn kho của sản phẩm tại tất cả các kho
    public function getStockByProductAllWarehouses($productId) {
        if (!$this->col) return [];
        try {
            $pipeline = [
                [
                    '$match' => [
                        'product_id' => $productId
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$warehouse_id',
                        'totalQty' => ['$sum' => '$qty']
                    ]
                ],
                ['$sort' => ['totalQty' => -1]]
            ];
            
            $result = $this->col->aggregate($pipeline)->toArray();
            $stockByWarehouse = [];
            
            foreach ($result as $item) {
                $warehouseId = $item['_id'] ?? '';
                $qty = (float)($item['totalQty'] ?? 0);
                if ($warehouseId && $qty > 0) {
                    $stockByWarehouse[$warehouseId] = $qty;
                }
            }
            
            return $stockByWarehouse;
        } catch (\Throwable $e) {
            error_log('getStockByProductAllWarehouses error: ' . $e->getMessage());
            return [];
        }
    }

    // ⭐ Lấy tất cả sản phẩm có tồn kho tại một kho (group by product)
    public function getProductsStockByWarehouse($warehouseId) {
        if (!$this->col) return [];
        try {
            $pipeline = [
                [
                    '$match' => [
                        'warehouse_id' => $warehouseId
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$product_id',
                        'totalQty' => ['$sum' => '$qty']
                    ]
                ],
                ['$sort' => ['_id' => 1]]
            ];
            
            $result = $this->col->aggregate($pipeline)->toArray();
            $stockByProduct = [];
            
            foreach ($result as $item) {
                $productId = $item['_id'] ?? '';
                $qty = (float)($item['totalQty'] ?? 0);
                if ($productId) {
                    $stockByProduct[$productId] = $qty;
                }
            }
            
            return $stockByProduct;
        } catch (\Throwable $e) {
            error_log('getProductsStockByWarehouse error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Tìm các kho có đủ hàng cho phiếu yêu cầu
     * Kiểm tra: stock >= neededQty + min_stock
     * @param array $requestDetails - Chi tiết phiếu yêu cầu
     * @param string $excludeWarehouseId - Kho không cần kiểm tra (kho yêu cầu)
     * @return array - Danh sách warehouse_id có đủ hàng
     */
    public function findSufficientWarehouses($requestDetails, $excludeWarehouseId = null) {
        if (!$this->col) return [];
        
        try {
            include_once(__DIR__ . '/mProduct.php');
            $mProduct = new MProduct();
            
            // Lấy tất cả kho trong hệ thống
            $allWarehouses = $this->col->distinct('warehouse_id');
            $sufficientWarehouses = [];
            
            foreach ($allWarehouses as $warehouseId) {
                // Bỏ qua kho được loại trừ (thường là kho yêu cầu)
                if ($warehouseId === $excludeWarehouseId) continue;
                
                // Bỏ qua kho tổng
                if (strpos($warehouseId, 'TONG') !== false) continue;
                
                $canFulfill = true;
                
                // Kiểm tra từng sản phẩm trong yêu cầu
                foreach ($requestDetails as $item) {
                    $productId = $item['product_id'] ?? '';
                    $requestedQty = (int)($item['quantity'] ?? 0);
                    $conversionFactor = (int)($item['conversion_factor'] ?? 1);
                    
                    // Số lượng cần (đơn vị cơ bản)
                    $neededQty = $requestedQty * $conversionFactor;
                    
                    // Lấy tồn kho hiện tại trong kho này
                    $currentStock = $this->getTotalStockByProduct($warehouseId, $productId);
                    
                    // Lấy min_stock của sản phẩm trong kho này
                    $product = $mProduct->getProductById($productId);
                    
                    // Tìm min_stock cho warehouse này
                    $minStock = 0;
                    if ($product && isset($product['stock_by_warehouse'])) {
                        foreach ($product['stock_by_warehouse'] as $stockInfo) {
                            if ($stockInfo['warehouse_id'] === $warehouseId) {
                                $minStock = (int)($stockInfo['min_stock'] ?? 0);
                                break;
                            }
                        }
                    }
                    
                    // Kiểm tra: Sau khi trừ đi số lượng yêu cầu, phải còn lớn hơn min_stock
                    // currentStock - neededQty > min_stock
                    // => currentStock > neededQty + min_stock
                    if ($currentStock <= ($neededQty + $minStock)) {
                        $canFulfill = false;
                        break;
                    }
                }
                
                if ($canFulfill) {
                    $sufficientWarehouses[] = $warehouseId;
                }
            }
            
            return $sufficientWarehouses;
            
        } catch (\Throwable $e) {
            error_log('findSufficientWarehouses error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate bin occupancy percentage based on volume
     * @param string $warehouseId
     * @param string $zoneId
     * @param string $rackId
     * @param string $binId
     * @return float Occupancy percentage (0-100)
     */
    public function calculateBinOccupancy($warehouseId, $zoneId, $rackId, $binId) {
        try {
            // 1. Get bin dimensions from warehouse_structure
            require_once(__DIR__ . '/mLocation.php');
            $mLocation = new MLocation();
            $binData = $mLocation->getBinFromWarehouse($warehouseId, $zoneId, $rackId, ['bin_id' => $binId]);
            
            if (!$binData || !isset($binData['bin'])) {
                return 0;
            }
            
            $bin = $binData['bin'];
            $binDims = $bin['dimensions'] ?? [];
            $binWidth = (float)($binDims['width'] ?? 0);
            $binDepth = (float)($binDims['depth'] ?? 0);
            $binHeight = (float)($binDims['height'] ?? 0);
            
            // If bin has no dimensions, cannot calculate
            if ($binWidth <= 0 || $binDepth <= 0 || $binHeight <= 0) {
                return 0;
            }
            
            // Calculate bin volume (cm³)
            $binVolume = $binWidth * $binDepth * $binHeight;
            
            // 2. Get all products in this bin from inventory
            $inventoryItems = $this->col->find([
                'warehouse_id' => $warehouseId,
                'zone_id' => $zoneId,
                'rack_id' => $rackId,
                'bin_id' => $binId
            ])->toArray();
            
            if (empty($inventoryItems)) {
                return 0; // Empty bin
            }
            
            // 3. Calculate total volume of products
            require_once(__DIR__ . '/mProduct.php');
            $mProduct = new MProduct();
            $totalProductVolume = 0;
            
            foreach ($inventoryItems as $item) {
                $productId = $item['product_id'] ?? '';
                $qty = (int)($item['qty'] ?? 0);
                
                if (!$productId || $qty <= 0) continue;
                
                // Get product dimensions
                $product = $mProduct->getProductById($productId);
                if (!$product) continue;
                
                // Get volume per unit from product
                $volumePerUnit = 0;
                
                // Check if product has package_dimensions
                if (isset($product['package_dimensions'])) {
                    $pDims = $product['package_dimensions'];
                    $pWidth = (float)($pDims['width'] ?? 0);
                    $pDepth = (float)($pDims['depth'] ?? 0);
                    $pHeight = (float)($pDims['height'] ?? 0);
                    
                    if ($pWidth > 0 && $pDepth > 0 && $pHeight > 0) {
                        $volumePerUnit = $pWidth * $pDepth * $pHeight;
                    }
                }
                
                // Fallback to volume_per_unit field
                if ($volumePerUnit <= 0 && isset($product['volume_per_unit'])) {
                    $volumePerUnit = (float)$product['volume_per_unit'];
                }
                
                // Add to total volume
                $totalProductVolume += ($volumePerUnit * $qty);
            }
            
            // 4. Calculate occupancy percentage
            if ($totalProductVolume <= 0) {
                return 0;
            }
            
            $occupancy = ($totalProductVolume / $binVolume) * 100;
            
            // Cap at 100% (though it could exceed in reality if overpacked)
            return min(100, round($occupancy, 2));
            
        } catch (\Throwable $e) {
            error_log('calculateBinOccupancy error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update bin occupancy after inventory changes
     * Also automatically updates bin status based on occupancy percentage
     * @param string $warehouseId
     * @param string $zoneId
     * @param string $rackId
     * @param string $binId
     * @return bool
     */
    public function updateBinOccupancy($warehouseId, $zoneId, $rackId, $binId) {
        try {
            // Calculate current occupancy
            $occupancy = $this->calculateBinOccupancy($warehouseId, $zoneId, $rackId, $binId);
            
            // Determine status based on occupancy percentage
            $status = 'empty';
            if ($occupancy >= 80) {
                $status = 'full';
            } elseif ($occupancy > 0) {
                $status = 'partial';
            }
            
            // Update bin's current_capacity and status in warehouse_structure
            require_once(__DIR__ . '/mLocation.php');
            $mLocation = new MLocation();
            
            $updateData = [
                'current_capacity' => (int)round($occupancy),
                'status' => $status
            ];
            
            return $mLocation->updateBinInWarehouse($warehouseId, $zoneId, $rackId, $binId, $updateData);
            
        } catch (\Throwable $e) {
            error_log('updateBinOccupancy error: ' . $e->getMessage());
            return false;
        }
    }
    
    
    // Sum total quantity for all bins in a specific rack
    public function sumQuantityByRack($warehouseId, $zoneId, $rackId) {
        if (!$this->col) return 0;
        try {
            $pipeline = [
                [
                    '$match' => [
                        'warehouse_id' => $warehouseId,
                        'zone_id' => $zoneId,
                        'rack_id' => $rackId
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'total_qty' => ['$sum' => '$qty']
                    ]
                ]
            ];
            
            $result = $this->col->aggregate($pipeline)->toArray();
            
            if (!empty($result)) {
                return (int)($result[0]['total_qty'] ?? 0);
            }
            
            return 0;
        } catch (\Throwable $e) {
            error_log('sumQuantityByRack error: ' . $e->getMessage());
            return 0;
        }
    }
    
    // Sum total quantity for all bins in a specific zone (across all racks)
    public function sumQuantityByZone($warehouseId, $zoneId) {
        if (!$this->col) return 0;
        try {
            $pipeline = [
                [
                    '$match' => [
                        'warehouse_id' => $warehouseId,
                        'zone_id' => $zoneId
                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        'total_qty' => ['$sum' => '$qty']
                    ]
                ]
            ];
            
            $result = $this->col->aggregate($pipeline)->toArray();
            
            if (!empty($result)) {
                return (int)($result[0]['total_qty'] ?? 0);
            }
            
            return 0;
        } catch (\Throwable $e) {
            error_log('sumQuantityByZone error: ' . $e->getMessage());
            return 0;
        }
    }
}
?>
