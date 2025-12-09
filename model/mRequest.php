<?php
include_once('connect.php');

class MRequest {
    private $connObj;
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $this->connObj = $p;
        if ($con) {
            // ⭐ Sử dụng collection transactions chung
            $this->col = $con->selectCollection('transactions');
        }
    }

    // Lấy phiếu yêu cầu cuối cùng (để sinh mã)
    public function getLastRequest() {
        if (!$this->col) return null;
        try {
            // Lọc theo transaction_type = 'goods_request'
            return $this->col->findOne(
                ['transaction_type' => 'goods_request'],
                ['sort' => ['transaction_id' => -1]]
            );
        } catch (\Exception $e) {
            error_log('getLastRequest error: ' . $e->getMessage());
            return null;
        }
    }

    // Thêm phiếu yêu cầu mới
    public function insertRequest($data) {
        if (!$this->col) return false;
        try {
            // ⭐ Đảm bảo có transaction_type
            $data['transaction_type'] = 'goods_request';
            
            $result = $this->col->insertOne($data);
            return $result->getInsertedCount() > 0 ? $result->getInsertedId() : false;
        } catch (\Exception $e) {
            error_log('insertRequest error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy tất cả phiếu yêu cầu
    public function getAllRequests($filter = []) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // ⭐ Thêm filter transaction_type
            $filter['transaction_type'] = 'goods_request';
            
            return $this->col->find($filter, ['sort' => ['created_at' => -1]]);
        } catch (\Exception $e) {
            error_log('getAllRequests error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy phiếu yêu cầu với thông tin người tạo (join với users)
    public function getAllRequestsWithUserInfo($filter = []) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // ⭐ Thêm filter transaction_type
            $filter['transaction_type'] = 'goods_request';
            
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
            error_log('getAllRequestsWithUserInfo error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy phiếu yêu cầu theo người tạo
    public function getRequestsByUser($user_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $pipeline = [
                ['$match' => [
                    'transaction_type' => 'goods_request',
                    'created_by' => $user_id
                ]],
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
                        ],
                        // ⭐ Chuyển priority thành số để sort
                        'priority_order' => [
                            '$cond' => [
                                ['$eq' => ['$priority', 'urgent']],
                                1,
                                0
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['priority_order' => -1, 'created_at' => -1]]
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getRequestsByUser error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy phiếu yêu cầu theo warehouse_id
    public function getRequestsByWarehouse($warehouse_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $pipeline = [
                ['$match' => [
                    'transaction_type' => 'goods_request',
                    'warehouse_id' => $warehouse_id
                ]],
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
                        ],
                        // ⭐ Chuyển priority thành số để sort
                        'priority_order' => [
                            '$cond' => [
                                ['$eq' => ['$priority', 'urgent']],
                                1,
                                0
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['priority_order' => -1, 'created_at' => -1]]
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getRequestsByWarehouse error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // ⭐ Lấy phiếu yêu cầu gửi đến kho nguồn (source_warehouse_id)
    public function getRequestsBySourceWarehouse($source_warehouse_id, $statusFilter = [1, 4]) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $matchFilter = [
                'transaction_type' => 'goods_request',
                'source_warehouse_id' => $source_warehouse_id,
                'status' => ['$in' => $statusFilter]
            ];
            
            $pipeline = [
                ['$match' => $matchFilter],
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
                        ],
                        // ⭐ Chuyển priority thành số để sort: urgent=1, normal=0
                        'priority_order' => [
                            '$cond' => [
                                ['$eq' => ['$priority', 'urgent']],
                                1,
                                0
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['priority_order' => -1, 'created_at' => -1]] // Urgent trước (1 > 0)
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getRequestsBySourceWarehouse error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // ⭐ Lấy phiếu yêu cầu được chỉ định cho kho (assigned_warehouse_id)
    public function getRequestsAssignedToWarehouse($warehouse_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $pipeline = [
                ['$match' => [
                    'transaction_type' => 'goods_request',
                    'assigned_warehouse_id' => $warehouse_id,
                    'status' => 5 // Đã được chỉ định
                ]],
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
                        ],
                        // ⭐ Chuyển priority thành số để sort
                        'priority_order' => [
                            '$cond' => [
                                ['$eq' => ['$priority', 'urgent']],
                                1,
                                0
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['priority_order' => -1, 'assigned_at' => -1]]
            ];
            
            return $this->col->aggregate($pipeline);
        } catch (\Exception $e) {
            error_log('getRequestsAssignedToWarehouse error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy 1 phiếu yêu cầu theo transaction_id
    public function getRequestById($request_id) {
        if (!$this->col) return null;
        try {
            return $this->col->findOne([
                'transaction_type' => 'goods_request',
                'transaction_id' => $request_id
            ]);
        } catch (\Exception $e) {
            error_log('getRequestById error: ' . $e->getMessage());
            return null;
        }
    }

    // Cập nhật phiếu yêu cầu
    public function updateRequest($request_id, $updateData) {
        if (!$this->col) return false;
        try {
            $result = $this->col->updateOne(
                [
                    'transaction_type' => 'goods_request',
                    'transaction_id' => $request_id
                ],
                ['$set' => $updateData]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            error_log('updateRequest error: ' . $e->getMessage());
            return false;
        }
    }

    // Xóa phiếu yêu cầu
    public function deleteRequest($request_id) {
        if (!$this->col) return false;
        try {
            $result = $this->col->deleteOne([
                'transaction_type' => 'goods_request',
                'transaction_id' => $request_id
            ]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log('deleteRequest error: ' . $e->getMessage());
            return false;
        }
    }

    // Đếm số phiếu theo trạng thái
    public function countByStatus() {
        if (!$this->col) return [];
        try {
            $pipeline = [
                ['$match' => ['transaction_type' => 'goods_request']],
                ['$group' => ['_id' => '$status', 'count' => ['$sum' => 1]]]
            ];
            return $this->col->aggregate($pipeline)->toArray();
        } catch (\Exception $e) {
            error_log('countByStatus error: ' . $e->getMessage());
            return [];
        }
    }

    // Lấy phiếu yêu cầu theo khoảng ngày
    public function getRequestsByDateRange($startDate, $endDate) {
        if (!$this->col) return new ArrayObject([]);
        try {
            $startTs = strtotime($startDate);
            $endTs = strtotime($endDate . ' 23:59:59');
            $filter = [
                'transaction_type' => 'goods_request',
                'created_at' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime($startTs * 1000),
                    '$lte' => new MongoDB\BSON\UTCDateTime($endTs * 1000)
                ]
            ];
            return $this->col->find($filter, ['sort' => ['created_at' => -1]]);
        } catch (\Exception $e) {
            error_log('getRequestsByDateRange error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }
}
?>
