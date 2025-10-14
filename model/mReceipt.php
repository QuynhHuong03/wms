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
            return $this->col->findOne([], ['sort' => ['receipt_id' => -1]]);
        } catch (\Exception $e) {
            error_log('getLastReceipt error: ' . $e->getMessage());
            return null;
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
            $pipeline = [
                ['$match' => ['created_by' => $user_id]],
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

    // Lấy 1 phiếu theo receipt_id (chuỗi IR0001) hoặc theo _id nếu muốn
    public function getReceiptById($receipt_id) {
        if (!$this->col) return null;
        try {
            return $this->col->findOne(['receipt_id' => $receipt_id]);
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
                ['receipt_id' => $receipt_id],
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
            $result = $this->col->deleteOne(['receipt_id' => $receipt_id]);
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
}
?>
