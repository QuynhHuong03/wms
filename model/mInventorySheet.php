<?php
include_once('connect.php');

class MInventorySheet {
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        if ($con) {
            $this->col = $con->selectCollection('inventory_sheet');
        }
    }

    // Create new inventory sheet
    public function createSheet($data) {
        if (!$this->col) return false;
        try {
            if (empty($data['created_at'])) {
                $data['created_at'] = new MongoDB\BSON\UTCDateTime();
            }
            if (empty($data['status'])) {
                $data['status'] = 'draft'; // draft, completed, approved
            }
            $result = $this->col->insertOne($data);
            return $result->getInsertedId();
        } catch (\Throwable $e) {
            error_log('createSheet error: '.$e->getMessage());
            return false;
        }
    }

    // Get sheet by ID
    public function getSheetById($id) {
        if (!$this->col) return null;
        try {
            $oid = is_string($id) ? new MongoDB\BSON\ObjectId($id) : $id;
            return $this->col->findOne(['_id' => $oid]);
        } catch (\Throwable $e) {
            error_log('getSheetById error: '.$e->getMessage());
            return null;
        }
    }

    // List sheets with filters
    public function listSheets($filters = [], $page = 1, $limit = 20, $sort = ['created_at' => -1]) {
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
            error_log('listSheets error: '.$e->getMessage());
            return [];
        }
    }

    // Count sheets
    public function countSheets($filters = []) {
        if (!$this->col) return 0;
        try {
            $query = $this->buildQuery($filters);
            return $this->col->countDocuments($query);
        } catch (\Throwable $e) {
            error_log('countSheets error: '.$e->getMessage());
            return 0;
        }
    }

    // Update sheet
    public function updateSheet($id, $data) {
        if (!$this->col) return false;
        try {
            $oid = is_string($id) ? new MongoDB\BSON\ObjectId($id) : $id;
            $data['updated_at'] = new MongoDB\BSON\UTCDateTime();
            $result = $this->col->updateOne(
                ['_id' => $oid],
                ['$set' => $data]
            );
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Throwable $e) {
            error_log('updateSheet error: '.$e->getMessage());
            return false;
        }
    }

    // Update items in sheet
    public function updateSheetItems($id, $items) {
        if (!$this->col) return false;
        try {
            $oid = is_string($id) ? new MongoDB\BSON\ObjectId($id) : $id;
            $result = $this->col->updateOne(
                ['_id' => $oid],
                [
                    '$set' => [
                        'items' => $items,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (\Throwable $e) {
            error_log('updateSheetItems error: '.$e->getMessage());
            return false;
        }
    }

    // Delete sheet
    public function deleteSheet($id) {
        if (!$this->col) return false;
        try {
            $oid = is_string($id) ? new MongoDB\BSON\ObjectId($id) : $id;
            $result = $this->col->deleteOne(['_id' => $oid]);
            return $result->getDeletedCount() > 0;
        } catch (\Throwable $e) {
            error_log('deleteSheet error: '.$e->getMessage());
            return false;
        }
    }

    private function buildQuery($filters) {
        $query = [];

        if (!empty($filters['warehouse_id'])) {
            $query['warehouse_id'] = $filters['warehouse_id'];
        }

        if (!empty($filters['status'])) {
            $query['status'] = $filters['status'];
        }

        if (!empty($filters['sheet_code'])) {
            $query['sheet_code'] = $filters['sheet_code'];
        }

        // Date range
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
            // ignore
        }
        if (!empty($dateFilter)) {
            $query['created_at'] = $dateFilter;
        }

        // Search
        if (!empty($filters['q'])) {
            $regex = new MongoDB\BSON\Regex($filters['q'], 'i');
            $query['$or'] = [
                ['sheet_code' => $regex],
                ['note' => $regex],
                ['created_by_name' => $regex]
            ];
        }

        return $query;
    }
    
    // Get latest inventory sheet containing a specific product
    public function getLatestSheetByProduct($productId, $warehouseId) {
        if (!$this->col) return null;
        try {
            $query = [
                'warehouse_id' => $warehouseId,
                'items.product_id' => $productId,
                'status' => ['$in' => [1, 2]] // Only completed or approved sheets
            ];
            
            $sheet = $this->col->findOne($query, [
                'sort' => ['created_at' => -1, 'count_date' => -1]
            ]);
            
            return $sheet ? json_decode(json_encode($sheet), true) : null;
        } catch (\Throwable $e) {
            error_log('getLatestSheetByProduct error: ' . $e->getMessage());
            return null;
        }
    }
}
?>
