<?php
// mBatchLocation.php - Quản lý số lượng lô ở từng vị trí
include_once('connect.php');

class MBatchLocation {
    private $connObj;
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $this->connObj = $p;
        if ($con) {
            $this->col = $con->selectCollection('batch_locations');
        }
    }

    // Thêm/Cập nhật số lượng lô tại vị trí
    public function upsertBatchLocation($batch_code, $location, $quantity) {
        if (!$this->col) return false;
        try {
            // location là object chứa: warehouse_id, zone_id, rack_id, bin_id
            $warehouse_id = $location['warehouse_id'] ?? '';
            $zone_id = $location['zone_id'] ?? '';
            $rack_id = $location['rack_id'] ?? '';
            $bin_id = $location['bin_id'] ?? '';

            if (!$warehouse_id || !$zone_id || !$rack_id || !$bin_id) {
                error_log('upsertBatchLocation: Missing location fields');
                return false;
            }

            // Kiểm tra xem đã tồn tại chưa (match theo location fields)
            $existing = $this->col->findOne([
                'batch_code' => $batch_code,
                'location.warehouse_id' => $warehouse_id,
                'location.zone_id' => $zone_id,
                'location.rack_id' => $rack_id,
                'location.bin_id' => $bin_id
            ]);

            if ($existing) {
                // Cập nhật: cộng thêm số lượng
                $result = $this->col->updateOne(
                    [
                        'batch_code' => $batch_code,
                        'location.warehouse_id' => $warehouse_id,
                        'location.zone_id' => $zone_id,
                        'location.rack_id' => $rack_id,
                        'location.bin_id' => $bin_id
                    ],
                    [
                        '$inc' => ['quantity' => (int)$quantity],
                        '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                    ]
                );
                return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
            } else {
                // Thêm mới
                $data = [
                    'batch_code' => $batch_code,
                    'location' => [
                        'warehouse_id' => $warehouse_id,
                        'zone_id' => $zone_id,
                        'rack_id' => $rack_id,
                        'bin_id' => $bin_id
                    ],
                    'quantity' => (int)$quantity,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ];
                $result = $this->col->insertOne($data);
                return $result->getInsertedCount() > 0;
            }
        } catch (\Exception $e) {
            error_log('upsertBatchLocation error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy tất cả vị trí của một lô
    public function getLocationsByBatch($batch_code, $excludePending = false) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $filter = ['batch_code' => $batch_code];
            
            // ⭐ Loại bỏ PENDING nếu được yêu cầu (dùng cho xuất FIFO)
            if ($excludePending) {
                $filter['location.zone_id'] = ['$ne' => 'PENDING'];
            }
            
            return $this->col->find(
                $filter,
                ['sort' => ['created_at' => -1]]
            );
        } catch (\Exception $e) {
            error_log('getLocationsByBatch error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy tất cả lô tại một vị trí (theo warehouse/zone/rack/bin)
    public function getBatchesByLocation($warehouse_id, $zone_id = null, $rack_id = null, $bin_id = null) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $filter = ['location.warehouse_id' => $warehouse_id];
            if ($zone_id) $filter['location.zone_id'] = $zone_id;
            if ($rack_id) $filter['location.rack_id'] = $rack_id;
            if ($bin_id) $filter['location.bin_id'] = $bin_id;

            return $this->col->find($filter, ['sort' => ['created_at' => -1]]);
        } catch (\Exception $e) {
            error_log('getBatchesByLocation error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Giảm số lượng tại vị trí (khi xuất hàng)
    public function reduceBatchLocation($batch_code, $location, $quantity) {
        if (!$this->col) return false;
        try {
            $warehouse_id = $location['warehouse_id'] ?? '';
            $zone_id = $location['zone_id'] ?? '';
            $rack_id = $location['rack_id'] ?? '';
            $bin_id = $location['bin_id'] ?? '';

            if (!$warehouse_id || !$zone_id || !$rack_id || !$bin_id) {
                error_log('reduceBatchLocation: Missing location fields');
                return false;
            }

            $result = $this->col->updateOne(
                [
                    'batch_code' => $batch_code,
                    'location.warehouse_id' => $warehouse_id,
                    'location.zone_id' => $zone_id,
                    'location.rack_id' => $rack_id,
                    'location.bin_id' => $bin_id
                ],
                [
                    '$inc' => ['quantity' => -(int)$quantity],
                    '$set' => ['updated_at' => new MongoDB\BSON\UTCDateTime()]
                ]
            );
            
            // Xóa nếu số lượng <= 0
            $updated = $this->col->findOne([
                'batch_code' => $batch_code,
                'location.warehouse_id' => $warehouse_id,
                'location.zone_id' => $zone_id,
                'location.rack_id' => $rack_id,
                'location.bin_id' => $bin_id
            ]);
            
            if ($updated && ($updated['quantity'] ?? 0) <= 0) {
                $this->col->deleteOne([
                    'batch_code' => $batch_code,
                    'location.warehouse_id' => $warehouse_id,
                    'location.zone_id' => $zone_id,
                    'location.rack_id' => $rack_id,
                    'location.bin_id' => $bin_id
                ]);
            }
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            error_log('reduceBatchLocation error: ' . $e->getMessage());
            return false;
        }
    }

    // Xóa vị trí của lô
    public function deleteBatchLocation($batch_code, $location) {
        if (!$this->col) return false;
        try {
            $warehouse_id = $location['warehouse_id'] ?? '';
            $zone_id = $location['zone_id'] ?? '';
            $rack_id = $location['rack_id'] ?? '';
            $bin_id = $location['bin_id'] ?? '';

            if (!$warehouse_id || !$zone_id || !$rack_id || !$bin_id) {
                error_log('deleteBatchLocation: Missing location fields');
                return false;
            }

            $result = $this->col->deleteOne([
                'batch_code' => $batch_code,
                'location.warehouse_id' => $warehouse_id,
                'location.zone_id' => $zone_id,
                'location.rack_id' => $rack_id,
                'location.bin_id' => $bin_id
            ]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log('deleteBatchLocation error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy tổng số lượng của lô tại tất cả vị trí
    public function getTotalQuantityByBatch($batch_code) {
        if (!$this->col) return 0;
        try {
            $pipeline = [
                ['$match' => ['batch_code' => $batch_code]],
                ['$group' => [
                    '_id' => null,
                    'total' => ['$sum' => '$quantity']
                ]]
            ];
            $result = $this->col->aggregate($pipeline)->toArray();
            return !empty($result) ? (int)($result[0]['total'] ?? 0) : 0;
        } catch (\Exception $e) {
            error_log('getTotalQuantityByBatch error: ' . $e->getMessage());
            return 0;
        }
    }
}
?>
