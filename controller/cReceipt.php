<?php
include_once(__DIR__ . '/../model/mReceipt.php');
include_once(__DIR__ . '/cBatch.php');

class CReceipt {
    private $mReceipt;
    private $cBatch;

    public function __construct() {
        $this->mReceipt = new MReceipt();
        $this->cBatch = new CBatch();
    }

    // Sinh mã phiếu tự động IR0001, IR0002, ...
    public function generateReceiptId() {
        // ✅ Lấy số thứ tự lớn nhất và tăng lên 1
        $maxNum = $this->mReceipt->getMaxReceiptNumber();
        $newNum = $maxNum + 1;
        return 'IR' . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }

    // Tạo phiếu nhập mới
    public function createReceipt($payload) {
        if (!isset($payload['type']) || !isset($payload['warehouse_id']) || !isset($payload['created_by'])) {
            return [false, 'Thiếu thông tin bắt buộc'];
        }

        $doc = [];
        $doc['transaction_id'] = $this->generateReceiptId();
        $doc['type'] = $payload['type'];
        $doc['warehouse_id'] = $payload['warehouse_id'];
        $doc['source_warehouse_id'] = $payload['source_warehouse_id'] ?? null;
        // Lưu export_id nếu có (phiếu xuất nguồn khi là transfer)
        $exportId = $payload['export_id'] ?? null;
        if (!empty($exportId)) {
            // Convert to MongoDB\BSON\ObjectId when possible to keep a consistent type in DB
            try {
                if ($exportId instanceof MongoDB\BSON\ObjectId) {
                    $doc['export_id'] = $exportId;
                } else {
                    $doc['export_id'] = new MongoDB\BSON\ObjectId((string)$exportId);
                }
            } catch (Throwable $e) {
                // If conversion fails, store raw value (fallback)
                error_log('createReceipt: export_id conversion failed: ' . $e->getMessage());
                $doc['export_id'] = $exportId;
            }
        } else {
            $doc['export_id'] = null;
        }
        $doc['supplier_id'] = $payload['supplier_id'] ?? null;
        $doc['created_by'] = $payload['created_by'];
        $doc['created_at'] = new MongoDB\BSON\UTCDateTime();
        $doc['approved_by'] = null;
        $doc['approved_at'] = null;
        $doc['note'] = $payload['note'] ?? null;
        $doc['status'] = isset($payload['status']) ? intval($payload['status']) : 0;

        // --- Chi tiết sản phẩm ---
        $details = $payload['details'] ?? [];
        $total = 0;
        $cleanDetails = [];
        foreach ($details as $d) {
            if (!isset($d['product_id']) || !isset($d['quantity']) || !isset($d['unit_price'])) continue;
            $qty = (int)$d['quantity'];
            $price = (float)$d['unit_price'];
            $subtotal = $qty * $price;
            $detailItem = [
                'product_id' => $d['product_id'],
                'product_name' => $d['product_name'] ?? '',
                'quantity' => $qty,
                'unit_price' => $price,
                'unit' => $d['unit'] ?? 'cái',
                'subtotal' => $subtotal
            ];

            // Nếu có thông tin batches (ví dụ nhập điều chuyển), lưu nguyên vào detail
            if (isset($d['batches']) && is_array($d['batches'])) {
                $detailItem['batches'] = $d['batches'];
            }

            $cleanDetails[] = $detailItem;
            $total += $subtotal;
        }
        $doc['details'] = $cleanDetails;
        $doc['total_amount'] = $total;

        $inserted = $this->mReceipt->insertReceipt($doc);
        if ($inserted) return [true, $inserted];
        return [false, 'Lưu phiếu thất bại'];
    }

    // Lấy tất cả phiếu nhập (dành cho quản lý / admin)
    public function getAllReceipts() {
    $data = $this->mReceipt->getAllReceipts();
    return iterator_to_array($data);
    }

    // Lấy phiếu theo người tạo (nhân viên)
    public function getReceiptsByUserWithUserInfo($userId) {
    $data = $this->mReceipt->getReceiptsByUserWithUserInfo($userId);
    return iterator_to_array($data);
    }

    // Lấy phiếu theo warehouse_id
    public function getReceiptsByWarehouse($warehouseId) {
    $data = $this->mReceipt->getReceiptsByWarehouse($warehouseId);
    return iterator_to_array($data);
    }

    // Lấy chi tiết 1 phiếu nhập
    public function getReceiptById($id) {
    return $this->mReceipt->getReceiptById($id);
}

    // Duyệt phiếu
    public function approveReceipt($id, $approver) {
        $data = [
            'status' => 1,
            'approved_by' => $approver,
            'approved_at' => new MongoDB\BSON\UTCDateTime()
        ];
        $ok = $this->mReceipt->updateReceipt($id, $data);
        if ($ok) {
            // Ensure batches are created when approving via this method
            try {
                $this->createBatchesFromReceipt($id);
            } catch (Throwable $e) {
                error_log('approveReceipt: createBatchesFromReceipt failed: ' . $e->getMessage());
            }
        }
        return $ok;
    }

    // Từ chối phiếu
    public function rejectReceipt($id, $approver) {
        $data = [
            'status' => 2,
            'approved_by' => $approver,
            'approved_at' => new MongoDB\BSON\UTCDateTime()
        ];
        return $this->mReceipt->updateReceipt($id, $data);
    }

    // Cập nhật trạng thái phiếu (dành cho process.php)
    public function updateReceiptStatus($id, $status, $approver = null) {
        $data = [
            'status' => (int)$status
        ];
        
        // Nếu có người duyệt thì cập nhật thông tin
        if ($approver) {
            $data['approved_by'] = $approver;
            $data['approved_at'] = new MongoDB\BSON\UTCDateTime();
        }
        
        $result = $this->mReceipt->updateReceipt($id, $data);
        
        // ✅ Tự động tạo lô hàng khi duyệt phiếu (status = 1)
        if ($result && (int)$status === 1) {
            $this->createBatchesFromReceipt($id);
        }
        
        return $result;
    }

    // ✅ Tạo lô hàng từ phiếu nhập đã duyệt
    private function createBatchesFromReceipt($receiptId) {
        try {
            // Lấy thông tin phiếu nhập
            $receipt = $this->mReceipt->getReceiptById($receiptId);
            if (!$receipt) {
                // Log and return
                $logFile = __DIR__ . '/../../backups/createBatches_debug.log';
                file_put_contents($logFile, "createBatchesFromReceipt: Không tìm thấy phiếu $receiptId\n", FILE_APPEND);
                return false;
            }

            // Prepare debug log file (use normalized base dir)
            $logFile = dirname(__DIR__) . '/backups/createBatches_debug.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            // attempt write (silently continue on failure)
            @file_put_contents($logFile, "\n--- createBatchesFromReceipt start: " . date(DATE_ATOM) . " receipt=" . $receiptId . "\n", FILE_APPEND);

            // Chuẩn bị dữ liệu để tạo lô hàng
            $receiptData = [
                'transaction_id' => $receipt['transaction_id'] ?? $receiptId,
                'warehouse_id' => $receipt['warehouse_id'] ?? null,
                'type' => $receipt['type'] ?? 'purchase', // ⭐ Loại phiếu nhập
                'source_warehouse_id' => $receipt['source_warehouse_id'] ?? null, // ⭐ Kho nguồn (cho transfer)
                'export_id' => $receipt['export_id'] ?? null, // ⭐ ID phiếu xuất (để lấy batch_code và source_location)
                'created_at' => $receipt['created_at'] ?? null,
                'details' => []
            ];

            // ⭐ Nếu là transfer và có export_id, lấy batch info từ phiếu xuất
            $exportBatchMap = []; // Map: product_id => [batches]
            if ($receiptData['type'] === 'transfer' && !empty($receiptData['export_id'])) {
                try {
                    include_once(__DIR__ . '/../model/connect.php');
                    $p = new clsKetNoi();
                    $con = $p->moKetNoi();
                    if ($con) {
                        // Support both string and ObjectId for stored export_id
                        $exportIdObj = null;
                        try {
                            if ($receiptData['export_id'] instanceof MongoDB\BSON\ObjectId) {
                                $exportIdObj = $receiptData['export_id'];
                            } elseif (!empty($receiptData['export_id'])) {
                                $exportIdObj = new MongoDB\BSON\ObjectId((string)$receiptData['export_id']);
                            }
                        } catch (Throwable $e) {
                            error_log('cReceipt:createBatchesFromReceipt export_id -> ObjectId failed: ' . $e->getMessage());
                            $exportIdObj = null;
                        }

                        if ($exportIdObj) {
                            $exportDoc = $con->selectCollection('transactions')->findOne([
                                '_id' => $exportIdObj
                            ]);
                        } else {
                            $exportDoc = null;
                        }
                        
                        if ($exportDoc) {
                            $exportProducts = $exportDoc['products'] ?? $exportDoc['details'] ?? [];
                            if (!is_array($exportProducts)) {
                                $exportProducts = json_decode(json_encode($exportProducts), true);
                            }
                            foreach ($exportProducts as $expProd) {
                                $pid = $expProd['product_id'] ?? '';
                                if ($pid && isset($expProd['batches'])) {
                                    $exportBatchMap[$pid] = is_array($expProd['batches']) ? $expProd['batches'] : json_decode(json_encode($expProd['batches']), true);
                                }
                            }
                            file_put_contents($logFile, "Loaded batch info from export for " . count($exportBatchMap) . " products\n", FILE_APPEND);
                        }
                        $p->dongKetNoi($con);
                    }
                } catch (\Exception $e) {
                    file_put_contents($logFile, "⚠️ Cannot load batches from export: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }

            // Lấy danh sách sản phẩm từ phiếu (convert BSON arrays to PHP arrays when needed)
            $details = [];
            if (isset($receipt['details'])) {
                $details = is_array($receipt['details']) ? $receipt['details'] : json_decode(json_encode($receipt['details']), true);
            }
            file_put_contents($logFile, "Receipt details count: " . count($details) . "\n", FILE_APPEND);
            foreach ($details as $detail) {
                $productId = $detail['product_id'] ?? null;
                
                $detailData = [
                    'product_id' => $productId,
                    'product_name' => $detail['product_name'] ?? '',
                    'quantity' => $detail['quantity'] ?? 0,
                    'unit_price' => $detail['unit_price'] ?? 0,
                    'unit' => $detail['unit'] ?? 'cái'
                ];
                
                // ⭐ ƯU TIÊN: Lấy batches từ receipt details (nếu có)
                if (isset($detail['batches']) && is_array($detail['batches']) && count($detail['batches']) > 0) {
                    $detailData['batches'] = $detail['batches'];
                    file_put_contents($logFile, "Using batches from receipt details for product $productId: " . count($detail['batches']) . " batches\n", FILE_APPEND);
                }
                // FALLBACK: Nếu receipt không có batches, lấy từ export
                elseif (!empty($exportBatchMap[$productId])) {
                    $detailData['batches'] = $exportBatchMap[$productId];
                    file_put_contents($logFile, "Using batches from export for product $productId: " . count($exportBatchMap[$productId]) . " batches\n", FILE_APPEND);
                } else {
                    file_put_contents($logFile, "No batches found for product $productId in receipt or export\n", FILE_APPEND);
                }
                
                $receiptData['details'][] = $detailData;
            }

            // Tạo lô hàng cho từng sản phẩm trong phiếu
            $batchResult = $this->cBatch->createBatchFromReceipt($receiptData);
            
            if ($batchResult['success']) {
                error_log("✅ Đã tạo lô hàng cho phiếu $receiptId: " . json_encode($batchResult['batches']));
                return true;
            } else {
                error_log("❌ Lỗi tạo lô hàng cho phiếu $receiptId: " . $batchResult['message']);
                return false;
            }
        } catch (\Exception $e) {
            error_log("createBatchesFromReceipt error: " . $e->getMessage());
            return false;
        }
    }

    // Xóa phiếu 
    public function deleteReceipt($id) {
        return $this->mReceipt->deleteReceipt($id);
    }

    // Lưu số lượng cần xếp cho từng sản phẩm trong phiếu (sau khi duyệt)
    // $qtyMap: [product_id => qty_to_locate]
    public function saveLocateQuantities($transactionId, $qtyMap) {
        if (!$transactionId || !is_array($qtyMap)) {
            return ['success' => false, 'message' => 'Thiếu dữ liệu'];
        }

        $receipt = $this->mReceipt->getReceiptById($transactionId);
        if (!$receipt) return ['success' => false, 'message' => 'Không tìm thấy phiếu'];

        $status = (int)($receipt['status'] ?? 0);
        if ($status !== 1) {
            // Chỉ cho phép nhập số lượng cần xếp khi phiếu đã duyệt
            return ['success' => false, 'message' => 'Phiếu chưa ở trạng thái Đã duyệt'];
        }

        $details = $receipt['details'] ?? [];
        $changed = false;
        foreach ($details as &$d) {
            $pid = $d['product_id'] ?? '';
            if ($pid && array_key_exists($pid, $qtyMap)) {
                $val = (int)$qtyMap[$pid];
                $max = (int)($d['quantity'] ?? 0);
                if ($val < 0) $val = 0;
                if ($val > $max) $val = $max;
                $d['qty_to_locate'] = $val;
                $changed = true;
            }
        }
        unset($d);

        if (!$changed) return ['success' => false, 'message' => 'Không có sản phẩm khớp để cập nhật'];

        $ok = $this->mReceipt->updateReceipt($transactionId, ['details' => $details]);
        return ['success' => (bool)$ok, 'updated' => $changed];
    }
}
?>
