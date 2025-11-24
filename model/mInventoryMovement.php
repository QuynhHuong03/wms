<?php
// mInventoryMovement.php - Lịch sử nhập/xuất/di chuyển
include_once('connect.php');

class MInventoryMovement {
    private $connObj;
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $this->connObj = $p;
        if ($con) {
            $this->col = $con->selectCollection('inventory_movements');
        }
    }

    // Thêm lịch sử di chuyển
    public function insertMovement($data) {
        if (!$this->col) return false;
        try {
            // Đảm bảo có timestamp
            if (!isset($data['date'])) {
                $data['date'] = new MongoDB\BSON\UTCDateTime();
            } elseif (is_string($data['date'])) {
                $data['date'] = new MongoDB\BSON\UTCDateTime(strtotime($data['date']) * 1000);
            }

            if (!isset($data['created_at'])) {
                $data['created_at'] = new MongoDB\BSON\UTCDateTime();
            }

            $result = $this->col->insertOne($data);
            return $result->getInsertedCount() > 0 ? $result->getInsertedId() : false;
        } catch (\Exception $e) {
            error_log('insertMovement error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy lịch sử theo lô hàng
    public function getMovementsByBatch($batch_code) {
        if (!$this->col) return new ArrayObject([]);
        try {
            return $this->col->find(
                ['batch_code' => $batch_code],
                ['sort' => ['date' => -1]]
            );
        } catch (\Exception $e) {
            error_log('getMovementsByBatch error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy lịch sử theo sản phẩm
    public function getMovementsByProduct($product_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            return $this->col->find(
                ['product_id' => $product_id],
                ['sort' => ['date' => -1]]
            );
        } catch (\Exception $e) {
            error_log('getMovementsByProduct error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy lịch sử theo vị trí
    public function getMovementsByLocation($location_id, $type = null) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $filter = ['$or' => [
                ['from_location' => $location_id],
                ['to_location' => $location_id]
            ]];
            
            if ($type) {
                $filter['movement_type'] = $type;
            }

            return $this->col->find($filter, ['sort' => ['date' => -1]]);
        } catch (\Exception $e) {
            error_log('getMovementsByLocation error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy lịch sử theo kho
    public function getMovementsByWarehouse($warehouse_id, $startDate = null, $endDate = null) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $filter = ['warehouse_id' => $warehouse_id];
            
            if ($startDate && $endDate) {
                $startTs = strtotime($startDate);
                $endTs = strtotime($endDate . ' 23:59:59');
                $filter['date'] = [
                    '$gte' => new MongoDB\BSON\UTCDateTime($startTs * 1000),
                    '$lte' => new MongoDB\BSON\UTCDateTime($endTs * 1000)
                ];
            }

            return $this->col->find($filter, ['sort' => ['date' => -1]]);
        } catch (\Exception $e) {
            error_log('getMovementsByWarehouse error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy tất cả lịch sử
    public function getAllMovements($limit = 100) {
        if (!$this->col) return new ArrayObject([]);
        try {
            return $this->col->find(
                [],
                [
                    'sort' => ['date' => -1],
                    'limit' => $limit
                ]
            );
        } catch (\Exception $e) {
            error_log('getAllMovements error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Thống kê theo loại di chuyển
    public function countByType($warehouse_id = null) {
        if (!$this->col) return [];
        try {
            $match = $warehouse_id ? ['warehouse_id' => $warehouse_id] : [];
            $pipeline = [
                ['$match' => $match],
                ['$group' => [
                    '_id' => '$movement_type',
                    'count' => ['$sum' => 1],
                    'total_quantity' => ['$sum' => '$quantity']
                ]]
            ];
            return $this->col->aggregate($pipeline)->toArray();
        } catch (\Exception $e) {
            error_log('countByType error: ' . $e->getMessage());
            return [];
        }
    }
}
?>
