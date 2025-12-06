<?php
include_once(__DIR__ . '/../model/mBatch.php');

class CBatch {
    private $mBatch;

    public function __construct() {
        $this->mBatch = new MBatch();
    }

    // Sinh mã lô tự động LH0001, LH0002, ...
    public function generateBatchCode() {
        $maxNum = $this->mBatch->getMaxBatchNumber();
        $newNum = $maxNum + 1;
        return 'LH' . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }

    // Tạo lô mới từ thông tin sản phẩm trong phiếu nhập
    public function createBatchFromReceipt($receiptData) {
        if (!isset($receiptData['details']) || !is_array($receiptData['details'])) {
            return ['success' => false, 'message' => 'Thiếu thông tin chi tiết sản phẩm'];
        }

        $transaction_id = $receiptData['transaction_id'] ?? null;
        $warehouse_id = $receiptData['warehouse_id'] ?? null;
        
        // ⭐ Lấy thông tin nguồn (source)
        $type = $receiptData['type'] ?? 'purchase';
        $source = ($type === 'transfer') ? 'transfer' : 'purchase';
        $source_warehouse_id = $receiptData['source_warehouse_id'] ?? null;
        
        // ⭐ Nếu là transfer, lấy source_location từ export_id (nếu có)
        $batchSourceLocations = []; // Map: batch_code => source_location
        if ($type === 'transfer' && isset($receiptData['export_id']) && !empty($receiptData['export_id'])) {
            try {
                include_once(__DIR__ . '/../model/connect.php');
                $p = new clsKetNoi();
                $con = $p->moKetNoi();
                if ($con) {
                    // Support both string and ObjectId types for export_id
                    $exportIdObj = null;
                    try {
                        if (isset($receiptData['export_id']) && $receiptData['export_id'] instanceof MongoDB\BSON\ObjectId) {
                            $exportIdObj = $receiptData['export_id'];
                        } elseif (!empty($receiptData['export_id'])) {
                            $exportIdObj = new MongoDB\BSON\ObjectId((string)$receiptData['export_id']);
                        }
                    } catch (Throwable $e) {
                        error_log('cBatch:createBatchFromReceipt export_id -> ObjectId failed: ' . $e->getMessage());
                        $exportIdObj = null;
                    }

                    if ($exportIdObj) {
                        $exportDoc = $con->selectCollection('transactions')->findOne([
                            '_id' => $exportIdObj
                        ]);
                    } else {
                        $exportDoc = null;
                    }
                    
                    if ($exportDoc && isset($exportDoc['details'])) {
                        foreach ($exportDoc['details'] as $exportDetail) {
                            if (isset($exportDetail['batches'])) {
                                foreach ($exportDetail['batches'] as $batchInfo) {
                                    $bCode = $batchInfo['batch_code'] ?? '';
                                    if (!empty($bCode) && isset($batchInfo['source_location'])) {
                                        $batchSourceLocations[$bCode] = $batchInfo['source_location'];
                                    }
                                }
                            }
                        }
                    }
                    $p->dongKetNoi($con);
                }
            } catch (\Exception $e) {
                error_log("⚠️ Cannot fetch source_location from export: " . $e->getMessage());
            }
        }
        
        // Xử lý ngày nhập - kiểm tra nhiều trường hợp
        $import_date = date('Y-m-d'); // Mặc định là ngày hiện tại
        if (isset($receiptData['created_at'])) {
            if ($receiptData['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                // Nếu là MongoDB UTCDateTime object
                $import_date = date('Y-m-d', $receiptData['created_at']->toDateTime()->getTimestamp());
            } elseif (is_array($receiptData['created_at']) && isset($receiptData['created_at']['$date'])) {
                // Nếu là array sau khi json_decode (có key $date)
                $timestamp = is_array($receiptData['created_at']['$date']) 
                    ? ($receiptData['created_at']['$date']['$numberLong'] ?? time() * 1000) / 1000
                    : $receiptData['created_at']['$date'] / 1000;
                $import_date = date('Y-m-d', $timestamp);
            } elseif (is_string($receiptData['created_at'])) {
                // Nếu là string
                $import_date = date('Y-m-d', strtotime($receiptData['created_at']));
            }
        }

        $createdBatches = [];
        $errors = [];

        foreach ($receiptData['details'] as $detail) {
            $product_id = $detail['product_id'] ?? null;
            $quantity = $detail['quantity'] ?? 0;

            if (!$product_id || $quantity <= 0) {
                continue; // Bỏ qua sản phẩm không hợp lệ
            }

            // ⭐ Nếu là transfer và có batches array, tạo từng batch với thông tin từ export
            // Convert BSON array to PHP array if needed
            $batches = null;
            if (isset($detail['batches'])) {
                if ($detail['batches'] instanceof MongoDB\Model\BSONArray) {
                    $batches = iterator_to_array($detail['batches']);
                    error_log("🔄 Converted BSONArray to PHP array");
                } elseif (is_array($detail['batches'])) {
                    $batches = $detail['batches'];
                } elseif (is_object($detail['batches'])) {
                    $batches = json_decode(json_encode($detail['batches']), true);
                    error_log("🔄 Converted object to PHP array via JSON");
                }
            }
            
            if ($source === 'transfer' && $batches && is_array($batches) && count($batches) > 0) {
                error_log("📦 Processing " . count($batches) . " batches for product $product_id (transfer)");
                
                foreach ($batches as $batchInfo) {
                    $original_batch_code = $batchInfo['batch_code'] ?? null;
                    $batch_qty = $batchInfo['quantity'] ?? 0;
                    $source_location = $batchInfo['source_location'] ?? null;

                    if (!$original_batch_code || $batch_qty <= 0) {
                        error_log("⚠️ Skip invalid batch: " . json_encode($batchInfo));
                        continue;
                    }

                    // Generate a NEW batch code for the receiving warehouse and keep reference to the original
                    $batch_code = $this->generateBatchCode();
                    $barcode = $batch_code;

                    error_log("✅ Creating new batch $batch_code (qty: $batch_qty) from original $original_batch_code (transfer)");

                    $batchData = [
                        'batch_code' => $batch_code,
                        'barcode' => $barcode,
                        'product_id' => $product_id,
                        'product_name' => $detail['product_name'] ?? '',
                        'quantity_imported' => (int)$batch_qty,
                        'quantity_remaining' => (int)$batch_qty,
                        'import_date' => $import_date,
                        'status' => 'Đang lưu',
                        'transaction_id' => $transaction_id,
                        'receipt_id' => $transaction_id,
                        'warehouse_id' => $warehouse_id,
                        'source' => 'transfer',
                        'source_warehouse_id' => $source_warehouse_id,
                        // Reference to the original batch at source warehouse
                        'source_batch_code' => $original_batch_code,
                        'source_location' => $source_location, // ⭐ Vị trí cũ ở kho nguồn
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'unit_price' => $detail['unit_price'] ?? 0,
                        'unit' => $detail['unit'] ?? 'cái'
                    ];

                    $result = $this->mBatch->insertBatch($batchData);

                    if ($result) {
                        $createdBatches[] = $batch_code;
                    } else {
                        $errors[] = "Không thể tạo lô $batch_code";
                    }
                }
            } else {
                // ⭐ Purchase hoặc không có batch info: tạo batch mới
                $batch_code = $this->generateBatchCode();
                error_log("✅ Generated new batch_code: $batch_code (purchase)");
                
                $barcode = $batch_code;

                $batchData = [
                    'batch_code' => $batch_code,
                    'barcode' => $barcode,
                    'product_id' => $product_id,
                    'product_name' => $detail['product_name'] ?? '',
                    'quantity_imported' => (int)$quantity,
                    'quantity_remaining' => (int)$quantity,
                    'import_date' => $import_date,
                    'status' => 'Đang lưu',
                    'transaction_id' => $transaction_id,
                    'receipt_id' => $transaction_id,
                    'warehouse_id' => $warehouse_id,
                    'source' => $source,
                    'source_warehouse_id' => $source_warehouse_id,
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'unit_price' => $detail['unit_price'] ?? 0,
                    'unit' => $detail['unit'] ?? 'cái'
                ];

                $result = $this->mBatch->insertBatch($batchData);
                
                if ($result) {
                    $createdBatches[] = $batch_code;
                } else {
                    $errors[] = "Không thể tạo lô cho sản phẩm " . ($detail['product_name'] ?? $product_id);
                }
            }
        }

        if (count($createdBatches) > 0) {
            return [
                'success' => true, 
                'message' => 'Đã tạo ' . count($createdBatches) . ' lô hàng',
                'batches' => $createdBatches,
                'errors' => $errors
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Không thể tạo lô hàng nào',
                'errors' => $errors
            ];
        }
    }

    // Lấy tất cả lô hàng
    public function getAllBatches() {
        $data = $this->mBatch->getAllBatches();
        $batches = iterator_to_array($data);
        
        // Enrich với SKU từ products
        include_once(__DIR__ . "/../model/mProduct.php");
        $mProduct = new MProduct();
        
        foreach ($batches as &$batch) {
            if (isset($batch['product_id'])) {
                $product = $mProduct->getProductById($batch['product_id']);
                if ($product && isset($product['sku'])) {
                    $batch['product_sku'] = $product['sku'];
                }
            }
        }
        
        return $batches;
    }

    // Lấy lô theo sản phẩm
    public function getBatchesByProduct($product_id) {
        $data = $this->mBatch->getBatchesByProduct($product_id);
        return iterator_to_array($data);
    }

    // Lấy lô theo phiếu nhập
    public function getBatchesByTransaction($transaction_id) {
        $data = $this->mBatch->getBatchesByTransaction($transaction_id);
        return iterator_to_array($data);
    }

    // ✅ Tìm lô theo barcode (để tra cứu khi quét)
    public function getBatchByBarcode($barcode) {
        return $this->mBatch->getBatchByBarcode($barcode);
    }
    
    // ✅ Tìm lô theo batch_code
    public function getBatchByCode($batch_code) {
        return $this->mBatch->getBatchByCode($batch_code);
    }

    // Cập nhật trạng thái lô
    public function updateBatchStatus($batch_code, $status) {
        return $this->mBatch->updateBatch($batch_code, ['status' => $status]);
    }

    // Cập nhật số lượng còn lại (khi xuất hàng)
    public function reduceBatchQuantity($batch_code, $quantity) {
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Số lượng phải lớn hơn 0'];
        }

        $batch = $this->mBatch->getBatchByCode($batch_code);
        if (!$batch) {
            return ['success' => false, 'message' => 'Không tìm thấy lô hàng'];
        }

        $remaining = $batch['quantity_remaining'] ?? 0;
        if ($quantity > $remaining) {
            return ['success' => false, 'message' => 'Số lượng xuất vượt quá số lượng còn lại'];
        }

        $result = $this->mBatch->updateBatchQuantity($batch_code, -$quantity);
        
        if ($result) {
            // Cập nhật trạng thái nếu hết hàng
            $newRemaining = $remaining - $quantity;
            if ($newRemaining <= 0) {
                $this->mBatch->updateBatch($batch_code, ['status' => 'Đã hết']);
            }
            return ['success' => true, 'message' => 'Đã cập nhật số lượng'];
        }

        return ['success' => false, 'message' => 'Không thể cập nhật số lượng'];
    }

    // Xóa lô
    public function deleteBatch($batch_code) {
        return $this->mBatch->deleteBatch($batch_code);
    }
}
?>
