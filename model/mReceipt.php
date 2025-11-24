<?php
// mReceipt.php
include_once('connect.php');

class MReceipt {
    private $connObj;
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $this->connObj = $p;
        if ($con) {
            $this->col = $con->selectCollection('transactions');
            // Không đóng kết nối ở đây để cursor trả về vẫn sử dụng được
        }
    }

    // Lấy phiếu cuối cùng (dùng để sinh mã)
    public function getLastReceipt() {
        if (!$this->col) return null;
        try {
            // ✅ Sort theo created_at để lấy phiếu được tạo gần nhất
            return $this->col->findOne([], ['sort' => ['created_at' => -1]]);
        } catch (\Exception $e) {
            error_log('getLastReceipt error: ' . $e->getMessage());
            return null;
        }
    }

    // ✅ Lấy số thứ tự lớn nhất từ transaction_id (IR0001 => 1)
    public function getMaxReceiptNumber() {
        if (!$this->col) return 0;
        try {
            // Lấy tất cả transaction_id và tìm số lớn nhất
            $receipts = $this->col->find([], [
                'projection' => ['transaction_id' => 1],
                'sort' => ['transaction_id' => -1],
                'limit' => 100 // Lấy 100 phiếu gần nhất để tìm max (tối ưu performance)
            ]);
            
            $maxNum = 0;
            foreach ($receipts as $r) {
                if (isset($r['transaction_id'])) {
                    if (preg_match('/IR(\d+)$/', $r['transaction_id'], $m)) {
                        $num = intval($m[1]);
                        if ($num > $maxNum) {
                            $maxNum = $num;
                        }
                    }
                }
            }
            
            return $maxNum;
        } catch (\Exception $e) {
            error_log('getMaxReceiptNumber error: ' . $e->getMessage());
            return 0;
        }
    }

    // Thêm phiếu mới
    public function insertReceipt($data) {
        if (!$this->col) return false;
        try {
            $result = $this->col->insertOne($data);
            return $result->getInsertedCount() > 0 ? $result->getInsertedId() : false;
        } catch (\Exception $e) {
            error_log('insertReceipt error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy tất cả phiếu (có thể truyền filter)
    // Trả về MongoDB\Driver\Cursor (hoặc tương đương) để foreach trực tiếp
    public function getAllReceipts($filter = []) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // Exclude export and goods_request transactions by default
            if (!isset($filter['transaction_type'])) {
                $filter['transaction_type'] = ['$nin' => ['export', 'goods_request']];
            }
            return $this->col->find($filter, ['sort' => ['created_at' => -1]]);
        } catch (\Exception $e) {
            error_log('getAllReceipts error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy tất cả phiếu với thông tin người tạo (join với users)
    public function getAllReceiptsWithUserInfo($filter = []) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // Exclude export and goods_request by default
            if (!isset($filter['transaction_type'])) {
                $filter['transaction_type'] = ['$nin' => ['export', 'goods_request']];
            }
            // Sử dụng aggregation pipeline để join với collection users
            $pipeline = [
                ['$match' => $filter],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'created_by',
                        'foreignField' => 'user_id',
                        'as' => 'creator_info'
                    ]
                ],
                [
                    '$addFields' => [
                        'creator_name' => [
                            '$ifNull' => [
                                ['$arrayElemAt' => ['$creator_info.name', 0]],
                                '$created_by'
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['created_at' => -1]]
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getAllReceiptsWithUserInfo error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy phiếu theo người tạo (created_by) với thông tin người dùng
    public function getReceiptsByUserWithUserInfo($user_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // Sử dụng aggregation pipeline
            $match = [
                'created_by' => $user_id,
                'transaction_type' => ['$nin' => ['export', 'goods_request']]
            ];
            $pipeline = [
                ['$match' => $match],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'created_by',
                        'foreignField' => 'user_id',
                        'as' => 'creator_info'
                    ]
                ],
                [
                    '$addFields' => [
                        'creator_name' => [
                            '$ifNull' => [
                                ['$arrayElemAt' => ['$creator_info.name', 0]],
                                '$created_by'
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['created_at' => -1]]
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getReceiptsByUserWithUserInfo error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy phiếu theo warehouse_id với thông tin người dùng
    public function getReceiptsByWarehouse($warehouse_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // Sử dụng aggregation pipeline
            // Exclude export and goods_request documents (transactions used for other purposes)
            $match = [
                'warehouse_id' => $warehouse_id,
                'transaction_type' => ['$nin' => ['export', 'goods_request']]
            ];
            $pipeline = [
                ['$match' => $match],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'created_by',
                        'foreignField' => 'user_id',
                        'as' => 'creator_info'
                    ]
                ],
                [
                    '$addFields' => [
                        'creator_name' => [
                            '$ifNull' => [
                                ['$arrayElemAt' => ['$creator_info.name', 0]],
                                '$created_by'
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['created_at' => -1]]
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getReceiptsByWarehouse error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy 1 phiếu theo receipt_id (chuỗi IR0001) hoặc theo _id nếu muốn
    public function getReceiptById($receipt_id) {
        if (!$this->col) return null;
        try {
            $result = $this->col->findOne(['transaction_id' => $receipt_id]);
            if ($result) {
                error_log("🔍 getReceiptById($receipt_id):");
                error_log("   Has allocations field: " . (isset($result['allocations']) ? 'YES' : 'NO'));
                if (isset($result['allocations'])) {
                    error_log("   Allocations count: " . count($result['allocations']));
                    error_log("   Allocations type: " . gettype($result['allocations']));
                }
            }
            return $result;
        } catch (\Exception $e) {
            error_log('getReceiptById error: ' . $e->getMessage());
            return null;
        }
    }

    // Cập nhật phiếu theo receipt_id (truyền mảng các trường để $set)
    public function updateReceipt($receipt_id, $updateData) {
        if (!$this->col) return false;
        try {
            $result = $this->col->updateOne(
                ['transaction_id' => $receipt_id],
                ['$set' => $updateData]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            error_log('updateReceipt error: ' . $e->getMessage());
            return false;
        }
    }

    // Xóa phiếu theo receipt_id
    public function deleteReceipt($receipt_id) {
        if (!$this->col) return false;
        try {
            $result = $this->col->deleteOne(['transaction_id' => $receipt_id]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log('deleteReceipt error: ' . $e->getMessage());
            return false;
        }
    }

    // Đếm số theo trạng thái (trả mảng kết quả aggregate)
    public function countByStatus() {
        if (!$this->col) return [];
        try {
            $pipeline = [
                ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
            ];
            return $this->col->aggregate($pipeline)->toArray();
        } catch (\Exception $e) {
            error_log('countByStatus error: ' . $e->getMessage());
            return [];
        }
    }

    // Lấy theo khoảng ngày (dạng 'YYYY-MM-DD' hoặc strtotime-compatible)
    public function getReceiptsByDateRange($startDate, $endDate) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $startTs = strtotime($startDate);
            $endTs = strtotime($endDate . ' 23:59:59');
            $filter = [
                'created_at' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime($startTs * 1000),
                    '$lte' => new MongoDB\BSON\UTCDateTime($endTs * 1000)
                ]
            ];
            return $this->col->find($filter, ['sort' => ['created_at' => -1]]);
        } catch (\Exception $e) {
            error_log('getReceiptsByDateRange error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }
    
    // Get latest receipt by product and warehouse
    public function getLatestReceiptByProduct($productId, $warehouseId) {
        if (!$this->col) return null;
        try {
            // Find receipts containing this product in items array
            $filter = [
                'warehouse_id' => $warehouseId,
                'items.product_id' => $productId
            ];
            
            $receipt = $this->col->findOne($filter, [
                'sort' => ['created_at' => -1, 'received_at' => -1]
            ]);
            
            return $receipt ? json_decode(json_encode($receipt), true) : null;
        } catch (\Exception $e) {
            error_log('getLatestReceiptByProduct error: ' . $e->getMessage());
            return null;
        }
    }
}
?>
