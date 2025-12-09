<?php
// mBatch.php
include_once('connect.php');

class MBatch {
    private $connObj;
    private $col;

    public function __construct() {
        $p = new clsKetNoi();
        $con = $p->moKetNoi();
        $this->connObj = $p;
        if ($con) {
            $this->col = $con->selectCollection('batches');
        }
    }

    // Lấy số thứ tự lớn nhất từ batch_code (LH0001 => 1)
    public function getMaxBatchNumber() {
        if (!$this->col) return 0;
        try {
            $batches = $this->col->find([], [
                'projection' => ['batch_code' => 1],
                'sort' => ['batch_code' => -1],
                'limit' => 100
            ]);
            
            $maxNum = 0;
            foreach ($batches as $b) {
                if (isset($b['batch_code'])) {
                    if (preg_match('/LH(\d+)$/', $b['batch_code'], $m)) {
                        $num = intval($m[1]);
                        if ($num > $maxNum) {
                            $maxNum = $num;
                        }
                    }
                }
            }
            
            return $maxNum;
        } catch (\Exception $e) {
            error_log('getMaxBatchNumber error: ' . $e->getMessage());
            return 0;
        }
    }

    // Thêm lô mới
    public function insertBatch($data) {
        if (!$this->col) return false;
        try {
            $result = $this->col->insertOne($data);
            return $result->getInsertedCount() > 0 ? $result->getInsertedId() : false;
        } catch (\Exception $e) {
            error_log('insertBatch error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy tất cả lô
    public function getAllBatches($filter = []) {
        if (!$this->col) return new ArrayObject([]);
        try {
            return $this->col->find($filter, ['sort' => ['import_date' => -1]]);
        } catch (\Exception $e) {
            error_log('getAllBatches error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Lấy lô theo batch_code
    public function getBatchByCode($batch_code) {
        if (!$this->col) return null;
        try {
            return $this->col->findOne(['batch_code' => $batch_code]);
        } catch (\Exception $e) {
            error_log('getBatchByCode error: ' . $e->getMessage());
            return null;
        }
    }

    // ✅ Lấy lô theo barcode (để tra cứu khi quét)
    public function getBatchByBarcode($barcode) {
        if (!$this->col) return null;
        try {
            return $this->col->findOne(['barcode' => $barcode]);
        } catch (\Exception $e) {
            error_log('getBatchByBarcode error: ' . $e->getMessage());
            return null;
        }
    }

    // Lấy lô theo product_id
    public function getBatchesByProduct($product_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            return $this->col->find(['product_id' => $product_id], ['sort' => ['import_date' => -1]]);
        } catch (\Exception $e) {
            error_log('getBatchesByProduct error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // Cập nhật lô theo batch_code
    public function updateBatch($batch_code, $updateData) {
        if (!$this->col) return false;
        try {
            $result = $this->col->updateOne(
                ['batch_code' => $batch_code],
                ['$set' => $updateData]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            error_log('updateBatch error: ' . $e->getMessage());
            return false;
        }
    }

    // Cập nhật số lượng còn lại
    public function updateBatchQuantity($batch_code, $quantity_change) {
        if (!$this->col) return false;
        try {
            $result = $this->col->updateOne(
                ['batch_code' => $batch_code],
                ['$inc' => ['quantity_remaining' => $quantity_change]]
            );
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            error_log('updateBatchQuantity error: ' . $e->getMessage());
            return false;
        }
    }

    // Xóa lô
    public function deleteBatch($batch_code) {
        if (!$this->col) return false;
        try {
            $result = $this->col->deleteOne(['batch_code' => $batch_code]);
            return $result->getDeletedCount() > 0;
        } catch (\Exception $e) {
            error_log('deleteBatch error: ' . $e->getMessage());
            return false;
        }
    }

    // Lấy lô theo transaction_id (để biết lô nào được tạo từ phiếu nhập nào)
    public function getBatchesByTransaction($transaction_id) {
        if (!$this->col) return new ArrayObject([]);
        try {
            // Support both string IDs and MongoDB ObjectId stored in the documents.
            $filter = ['transaction_id' => $transaction_id];

            // If caller passed a 24-hex string, try matching both string and ObjectId forms
            if (is_string($transaction_id) && preg_match('/^[0-9a-fA-F]{24}$/', $transaction_id)) {
                try {
                    $objId = new MongoDB\BSON\ObjectId($transaction_id);
                    $filter = ['$or' => [ ['transaction_id' => $transaction_id], ['transaction_id' => $objId] ]];
                } catch (\Exception $e) {
                    // ignore conversion error and use original string filter
                }
            }

            return $this->col->find($filter, ['sort' => ['import_date' => -1]]);
        } catch (\Exception $e) {
            error_log('getBatchesByTransaction error: ' . $e->getMessage());
            return new ArrayObject([]);
        }
    }

    // ✅ Lấy batches theo product_id, sắp xếp theo ngày nhập (FIFO)
    // Chỉ lấy lô còn hàng (quantity_remaining > 0)
    public function getBatchesByProductSortedByDate($product_id, $warehouse_id = null) {
        if (!$this->col) return [];
        try {
            $filter = [
                'product_id' => $product_id,
                'quantity_remaining' => ['$gt' => 0] // Chỉ lấy lô còn hàng
                // ⭐ BỎ FILTER STATUS - Chỉ cần quantity > 0 là đủ
                // Lý do: Status có thể bị sai (ví dụ restore batch thủ công)
            ];
            
            if ($warehouse_id) {
                $filter['warehouse_id'] = $warehouse_id;
            }
            
            $cursor = $this->col->find($filter, [
                'sort' => ['import_date' => 1] // FIFO: cũ nhất trước (ascending)
            ]);
            
            return iterator_to_array($cursor);
        } catch (\Exception $e) {
            error_log('getBatchesByProductSortedByDate error: ' . $e->getMessage());
            return [];
        }
    }

    // ✅ Giảm quantity_remaining của batch
    public function reduceBatchQuantity($batch_code, $quantity) {
        if (!$this->col) return false;
        try {
            $batch = $this->getBatchByCode($batch_code);
            if (!$batch) return false;

            $currentRemaining = (int)($batch['quantity_remaining'] ?? 0);
            $newRemaining = max(0, $currentRemaining - $quantity);

            $updateData = [
                'quantity_remaining' => $newRemaining,
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];

            // Nếu hết hàng thì đổi status
            if ($newRemaining <= 0) {
                $updateData['status'] = 'Đã xuất hết';
            }

            $result = $this->col->updateOne(
                ['batch_code' => $batch_code],
                ['$set' => $updateData]
            );
            
            return $result->getModifiedCount() > 0;
        } catch (\Exception $e) {
            error_log('reduceBatchQuantity error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
