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

            // Nếu frontend gửi sản phẩm tạm (is_new), giữ nguyên các trường tạm để không lookup DB
            if (!empty($d['is_new'])) {
                $detailItem['is_new'] = true;
                // Preserve any temp metadata sent from frontend so we can create product on approval
                $detailItem['temp'] = is_array($d['temp']) ? $d['temp'] : [];
                // Copy some commonly used top-level temp fields if provided
                if (!empty($d['sku'])) $detailItem['temp']['sku'] = $d['sku'];
                if (!empty($d['barcode'])) $detailItem['temp']['barcode'] = $d['barcode'];
                if (!empty($d['supplier_id'])) {
                    $detailItem['supplier_id'] = $d['supplier_id'];
                    $detailItem['temp']['supplier_id'] = $d['supplier_id'];
                    $detailItem['temp']['supplier_name'] = $d['supplier_name'] ?? ($d['temp']['supplier_name'] ?? '');
                }
                if (!empty($d['conversionUnits'])) $detailItem['temp']['conversionUnits'] = $d['conversionUnits'];
                if (!empty($d['package_dimensions'])) $detailItem['temp']['package_dimensions'] = $d['package_dimensions'];
                if (!empty($d['package_weight'])) $detailItem['temp']['package_weight'] = $d['package_weight'];
                if (!empty($d['volume_per_unit'])) $detailItem['temp']['volume_per_unit'] = $d['volume_per_unit'];
                if (!empty($d['model'])) $detailItem['temp']['model'] = $d['model'];
                if (!empty($d['min_stock'])) $detailItem['temp']['min_stock'] = $d['min_stock'];
                if (!empty($d['status'])) $detailItem['temp']['status'] = $d['status'];
                if (!empty($d['purchase_price'])) $detailItem['temp']['purchase_price'] = $d['purchase_price'];
                if (!empty($d['category_id'])) $detailItem['temp']['category_id'] = $d['category_id'];
                if (!empty($d['category_name'])) $detailItem['temp']['category_name'] = $d['category_name'];
                if (!empty($d['description'])) $detailItem['temp']['description'] = $d['description'];
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
        // First attempt to create products/batches. Only mark as approved if that succeeds.
        try {
            $batchOk = $this->createBatchesFromReceipt($id);
        } catch (Throwable $e) {
            error_log('approveReceipt: createBatchesFromReceipt threw: ' . $e->getMessage());
            $batchOk = false;
        }

        if (!$batchOk) {
            error_log('approveReceipt: batch creation failed, aborting approval for ' . $id);
            return false;
        }

        $data = [
            'status' => 1,
            'approved_by' => $approver,
            'approved_at' => new MongoDB\BSON\UTCDateTime()
        ];
        return $this->mReceipt->updateReceipt($id, $data);
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
        
        // If approving (status 1), try to create products/batches first and only update status if successful
        if ((int)$status === 1) {
            try {
                $ok = $this->createBatchesFromReceipt($id);
            } catch (Throwable $e) {
                error_log('updateReceiptStatus: createBatchesFromReceipt failed: ' . $e->getMessage());
                $ok = false;
            }
            if (!$ok) return false;
            // now mark approved
            if ($approver) {
                $data['approved_by'] = $approver;
                $data['approved_at'] = new MongoDB\BSON\UTCDateTime();
            }
            return $this->mReceipt->updateReceipt($id, $data);
        }

        // For non-approve statuses, just update
        return $this->mReceipt->updateReceipt($id, $data);
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
            // --- Persist temporary products (is_new) into products collection before creating batches ---
            if (!class_exists('CProduct')) include_once(__DIR__ . '/cProduct.php');
            $cprod = new CProduct();

            foreach ($details as &$detail) {
                $productId = $detail['product_id'] ?? null;

                // If this detail was a frontend temporary product, attempt to create a DB product
                if (!empty($detail['is_new']) && (!isset($productId) || strpos((string)$productId, 'new_') === 0)) {
                    $temp = is_array($detail['temp']) ? $detail['temp'] : [];
                    $barcode = trim($temp['barcode'] ?? '');
                    $sku = trim($temp['sku'] ?? '');
                    $name = $detail['product_name'] ?? $temp['product_name'] ?? '';

                    $existing = null;
                    if ($barcode !== '') {
                        try { $existing = $cprod->getProductByBarcode($barcode); } catch (Throwable $e) { $existing = null; }
                    }
                    if (!$existing && $sku !== '') {
                        try { $existing = $cprod->getProductBySKU($sku); } catch (Throwable $e) { $existing = null; }
                    }

                    if ($existing && !empty($existing['_id'])) {
                        // Use existing product
                        $newProductId = $existing['_id'];
                        file_put_contents($logFile, "Using existing product for temp item: sku={$sku} barcode={$barcode} id={$newProductId}\n", FILE_APPEND);
                    } else {
                        // Build product payload
                        // Prefer dimensions provided on the receipt detail (manual-add), otherwise use temp
                        $pkgDims = [];
                        if (!empty($detail['package_dimensions'])) $pkgDims = $detail['package_dimensions'];
                        elseif (!empty($detail['dimensions'])) $pkgDims = $detail['dimensions'];
                        elseif (!empty($temp['package_dimensions'])) $pkgDims = $temp['package_dimensions'];
                        elseif (!empty($temp['dimensions'])) $pkgDims = $temp['dimensions'];
                        if (is_string($pkgDims) && $pkgDims !== '') {
                            $decoded = json_decode($pkgDims, true);
                            if (is_array($decoded)) $pkgDims = $decoded;
                        }
                        if (!is_array($pkgDims)) $pkgDims = [];

                        $newProduct = [
                            // Let MProduct->addProduct set sku if empty (it will use category code + id)
                            'sku' => $sku ?: '',
                            'product_name' => $name,
                            'barcode' => $barcode,
                            'purchase_price' => $temp['purchase_price'] ?? $detail['unit_price'] ?? 0,
                            'baseUnit' => $temp['baseUnit'] ?? $detail['unit'] ?? 'Cái',
                            'conversionUnits' => $temp['conversionUnits'] ?? [],
                            'package_dimensions' => $pkgDims,
                            'package_weight' => $temp['package_weight'] ?? ($temp['weight'] ?? 0),
                            'volume_per_unit' => $temp['volume_per_unit'] ?? 0,
                            'model' => $temp['model'] ?? '',
                            'description' => $temp['description'] ?? '',
                            'min_stock' => isset($temp['min_stock']) ? (int)$temp['min_stock'] : 0,
                            'status' => isset($temp['status']) ? (int)$temp['status'] : 1,
                            'stackable' => isset($temp['stackable']) ? (bool)$temp['stackable'] : false,
                            'max_stack_height' => isset($temp['max_stack_height']) ? (int)$temp['max_stack_height'] : 1,
                            'image' => $temp['image'] ?? '',
                        ];

                        // Category and supplier - hỗ trợ cả object và id/name riêng
                        if (!empty($temp['category']) && is_array($temp['category'])) {
                            $newProduct['category'] = $temp['category'];
                        } elseif (!empty($temp['category_id']) || !empty($temp['category_name'])) {
                            $newProduct['category'] = [
                                'id' => $temp['category_id'] ?? null,
                                'name' => $temp['category_name'] ?? null
                            ];
                        }
                        
                        if (!empty($temp['supplier']) && is_array($temp['supplier'])) {
                            $newProduct['supplier'] = $temp['supplier'];
                        } elseif (!empty($detail['supplier_id']) || !empty($temp['supplier_id'])) {
                            $newProduct['supplier'] = [
                                'id' => $detail['supplier_id'] ?? ($temp['supplier_id'] ?? null),
                                'name' => $temp['supplier_name'] ?? null
                            ];
                        }

                        // Try to add product
                        try {
                            // Log the product payload we're about to insert (helpful for debugging)
                            file_put_contents($logFile, "Attempting addProduct with payload: " . json_encode($newProduct, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                            // Expect addProduct to return inserted id (string) or false
                            $insertedId = $cprod->addProduct($newProduct);
                            file_put_contents($logFile, "addProduct returned: " . json_encode($insertedId) . "\n", FILE_APPEND);
                        } catch (Throwable $e) {
                            $insertedId = false;
                            file_put_contents($logFile, "Error adding product for temp item: " . $e->getMessage() . "\n", FILE_APPEND);
                        }

                        if ($insertedId) {
                            $newProductId = (string)$insertedId;
                        } else {
                            // As a fallback try to look up by barcode/sku
                            $newProductId = null;
                            if (!empty($barcode)) {
                                $found = $cprod->getProductByBarcode($barcode);
                                if ($found && !empty($found['_id'])) $newProductId = $found['_id'];
                            }
                            if (empty($newProductId) && !empty($sku)) {
                                $found2 = $cprod->getProductBySKU($sku);
                                if ($found2 && !empty($found2['_id'])) $newProductId = $found2['_id'];
                            }
                        }

                        if (empty($newProductId)) {
                            // Failed to create product -> log and abort batch creation
                            file_put_contents($logFile, "Failed to create product for temp item (sku={$sku}, barcode={$barcode}). Aborting batch creation.\n", FILE_APPEND);
                            return false;
                        }
                        file_put_contents($logFile, "Created product for temp item: sku={$sku} barcode={$barcode} id={$newProductId}\n", FILE_APPEND);
                    }

                    // Update detail to reference new product id
                    $detail['product_id'] = $newProductId;
                    // remove temp flags now that persisted
                    unset($detail['is_new']);
                    unset($detail['temp']);
                }
            }
            unset($detail);

            // Persist any updated details (with created product ids)
            try {
                $this->mReceipt->updateReceipt($receipt['transaction_id'] ?? $receiptId, ['details' => $details]);
                file_put_contents($logFile, "Updated receipt details with persisted product ids for receipt " . ($receipt['transaction_id'] ?? $receiptId) . "\n", FILE_APPEND);
            } catch (Throwable $e) {
                file_put_contents($logFile, "Failed to update receipt details after product creation: " . $e->getMessage() . "\n", FILE_APPEND);
                return false;
            }

            // Now build detailData for batch creation using possibly-updated details
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

            // ✅ Không tạo lô hàng tự động khi duyệt phiếu nhập
            // Lô hàng sẽ được tạo riêng biệt sau khi duyệt
            file_put_contents($logFile, "Receipt approved successfully. Products created. Batches not auto-created.\n", FILE_APPEND);
            return true;
            
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
