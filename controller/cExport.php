<?php
/**
 * Controller Xuất Hàng theo FIFO (First In First Out)
 * Tự động chọn lô cũ nhất để xuất trước
 */

include_once(__DIR__ . '/../model/mBatch.php');
include_once(__DIR__ . '/../model/mBatchLocation.php');
include_once(__DIR__ . '/../model/mInventoryMovement.php');
include_once(__DIR__ . '/../model/mProduct.php');

class CExport {
    private $mBatch;
    private $mBatchLocation;
    private $mInventoryMovement;
    private $mProduct;

    public function __construct() {
        $this->mBatch = new MBatch();
        $this->mBatchLocation = new MBatchLocation();
        $this->mInventoryMovement = new MInventoryMovement();
        $this->mProduct = new MProduct();
    }

    /**
     * Xuất hàng theo FIFO (First In First Out)
     * Tự động chọn lô cũ nhất, vị trí đầu tiên để xuất
     * 
     * @param string $productId ID sản phẩm cần xuất
     * @param int $quantityNeeded Số lượng cần xuất
     * @param string $fromWarehouse Kho xuất hàng
     * @param string|null $toWarehouse Kho đích (null nếu xuất bán)
     * @param string $transactionId Mã phiếu xuất (EX0001, ...)
     * @param string $note Ghi chú
     * @return array Kết quả xuất hàng
     */
    public function exportProductFIFO($productId, $quantityNeeded, $fromWarehouse, $toWarehouse = null, $transactionId = '', $note = '') {
        try {
            if ($quantityNeeded <= 0) {
                return ['success' => false, 'message' => 'Số lượng xuất phải lớn hơn 0'];
            }

            // 1. Lấy tất cả lô của sản phẩm, sắp xếp theo FIFO (cũ nhất trước)
            $batches = $this->mBatch->getBatchesByProductSortedByDate($productId, $fromWarehouse);
            
            if (empty($batches)) {
                return [
                    'success' => false, 
                    'message' => 'Không tìm thấy lô hàng nào còn tồn kho',
                    'product_id' => $productId
                ];
            }

            $remainingQty = $quantityNeeded;
            $exportedBatches = [];
            $totalExported = 0;

            // 2. Duyệt qua các lô từ cũ đến mới (FIFO)
            foreach ($batches as $batch) {
                if ($remainingQty <= 0) break;

                $batchCode = $batch['batch_code'] ?? '';
                $batchRemaining = (int)($batch['quantity_remaining'] ?? 0);

                if ($batchRemaining <= 0 || empty($batchCode)) continue;

                // 3. Lấy danh sách vị trí của lô này (loại bỏ PENDING)
                $locations = $this->mBatchLocation->getLocationsByBatch($batchCode, true);
                
                foreach ($locations as $loc) {
                    if ($remainingQty <= 0) break;

                    $locQty = (int)($loc['quantity'] ?? 0);
                    if ($locQty <= 0) continue;

                    $location = $loc['location'] ?? null;
                    if (!$location) continue;
                    
                    // ⭐ Double-check: bỏ qua PENDING nếu có
                    if (($location['zone_id'] ?? '') === 'PENDING') {
                        error_log("⚠️ Skipped PENDING location for batch $batchCode");
                        continue;
                    }

                    // Số lượng lấy từ vị trí này (không vượt quá số còn và số cần)
                    $qtyFromLocation = min($remainingQty, $locQty);

                    // 4. Ghi log inventory_movements
                    $movementData = [
                        'batch_code' => $batchCode,
                        'product_id' => $productId,
                        'movement_type' => 'xuất',
                        'from_location' => $location,
                        'to_location' => $toWarehouse ? ['warehouse_id' => $toWarehouse] : null,
                        'quantity' => $qtyFromLocation,
                        'transaction_id' => $transactionId,
                        'warehouse_id' => $fromWarehouse,
                        'note' => $note ?: "Xuất $qtyFromLocation sản phẩm từ lô $batchCode (FIFO)",
                        'date' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $movementInserted = $this->mInventoryMovement->insertMovement($movementData);
                    
                    if (!$movementInserted) {
                        error_log("⚠️ Failed to insert movement for batch $batchCode");
                    }

                    // 5. Giảm số lượng tại batch_locations
                    $locReduced = $this->mBatchLocation->reduceBatchLocation($batchCode, $location, $qtyFromLocation);
                    
                    if (!$locReduced) {
                        error_log("⚠️ Failed to reduce batch_location for batch $batchCode");
                    }

                    // 6. Giảm quantity_remaining trong batches
                    $batchReduced = $this->mBatch->reduceBatchQuantity($batchCode, $qtyFromLocation);
                    
                    if (!$batchReduced) {
                        error_log("⚠️ Failed to reduce batch quantity for $batchCode");
                    }

                    // Cập nhật số liệu
                    $remainingQty -= $qtyFromLocation;
                    $totalExported += $qtyFromLocation;

                    // Lưu thông tin để trả về
                    $exportedBatches[] = [
                        'batch_code' => $batchCode,
                        'location' => $location,
                        'quantity' => $qtyFromLocation,
                        'unit_price' => $batch['unit_price'] ?? 0, // ⭐ LẤY GIÁ TỪ BATCH
                        'import_date' => isset($batch['import_date']) ? 
                            (is_object($batch['import_date']) ? 
                                date('d/m/Y', $batch['import_date']->toDateTime()->getTimestamp()) : 
                                $batch['import_date']) : 
                            'N/A'
                    ];

                    error_log("✅ Exported $qtyFromLocation from batch $batchCode at " . 
                        $location['warehouse_id'] . '/' . 
                        ($location['zone_id'] ?? '') . '/' . 
                        ($location['rack_id'] ?? '') . '/' . 
                        ($location['bin_id'] ?? ''));
                }

                // Nếu đã đủ số lượng thì dừng
                if ($remainingQty <= 0) break;
            }

            // 7. Trừ collection inventory (để đồng bộ với UI)
            if ($totalExported > 0) {
                include_once(__DIR__ . '/../model/connect.php');
                $p = new clsKetNoi();
                $con = $p->moKetNoi();
                
                if ($con) {
                    $inventoryCol = $con->selectCollection('inventory');
                    
                    // Trừ inventory theo FIFO (cũ nhất trước)
                    $inventoryEntries = $inventoryCol->find(
                        [
                            'warehouse_id' => $fromWarehouse,
                            'product_id' => $productId,
                            'qty' => ['$gt' => 0]
                        ],
                        [
                            'sort' => ['received_at' => 1], // FIFO: Cũ nhất trước
                            'limit' => 50
                        ]
                    )->toArray();
                    
                    $remainingToDeduct = $totalExported;
                    
                    foreach ($inventoryEntries as $entry) {
                        if ($remainingToDeduct <= 0) break;
                        
                        $entryQty = (int)($entry['qty'] ?? 0);
                        $entryId = $entry['_id'];
                        
                        if ($entryQty >= $remainingToDeduct) {
                            // Entry này đủ để trừ hết
                            $inventoryCol->updateOne(
                                ['_id' => $entryId],
                                ['$inc' => ['qty' => -$remainingToDeduct]]
                            );
                            $remainingToDeduct = 0;
                        } else {
                            // Entry này không đủ, trừ hết và chuyển sang entry tiếp
                            $inventoryCol->updateOne(
                                ['_id' => $entryId],
                                ['$set' => ['qty' => 0]]
                            );
                            $remainingToDeduct -= $entryQty;
                        }
                    }
                    
                    if ($remainingToDeduct > 0) {
                        error_log("⚠️ Warning: Còn $remainingToDeduct chưa trừ được trong inventory collection");
                    }
                    
                    $p->dongKetNoi($con);
                }
            }
            
            // 8. Kiểm tra kết quả
            if ($remainingQty > 0) {
                // Không đủ hàng
                return [
                    'success' => false,
                    'message' => "Chỉ xuất được $totalExported/$quantityNeeded sản phẩm. Còn thiếu $remainingQty sản phẩm trong kho!",
                    'exported_quantity' => $totalExported,
                    'requested_quantity' => $quantityNeeded,
                    'shortage' => $remainingQty,
                    'exported_batches' => $exportedBatches
                ];
            }

            // Thành công
            return [
                'success' => true,
                'message' => "Đã xuất thành công $totalExported sản phẩm từ " . count($exportedBatches) . " vị trí (FIFO)",
                'exported_quantity' => $totalExported,
                'exported_batches' => $exportedBatches,
                'transaction_id' => $transactionId
            ];

        } catch (\Exception $e) {
            error_log("exportProductFIFO error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Truy xuất nguồn gốc sản phẩm theo mã lô
     * Xem lô hàng này đã đi đâu, còn ở đâu
     * 
     * @param string $batchCode Mã lô hàng (LH0001, ...)
     * @return array Thông tin chi tiết về lô
     */
    public function traceProductByBatch($batchCode) {
        try {
            // 1. Thông tin lô hàng
            $batch = $this->mBatch->getBatchByCode($batchCode);
            
            if (!$batch) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy lô hàng: ' . $batchCode
                ];
            }

            // 2. Thông tin sản phẩm
            $product = null;
            if (isset($batch['product_id'])) {
                $product = $this->mProduct->getProductById($batch['product_id']);
            }

            // 3. Vị trí hiện tại của lô
            $locations = $this->mBatchLocation->getLocationsByBatch($batchCode);
            $currentLocations = [];
            foreach ($locations as $loc) {
                $currentLocations[] = [
                    'location' => $loc['location'] ?? [],
                    'quantity' => $loc['quantity'] ?? 0,
                    'updated_at' => isset($loc['updated_at']) ? 
                        date('d/m/Y H:i', $loc['updated_at']->toDateTime()->getTimestamp()) : 
                        'N/A'
                ];
            }

            // 4. Lịch sử di chuyển
            $movements = $this->mInventoryMovement->getMovementsByBatch($batchCode);
            $movementHistory = [];
            foreach ($movements as $m) {
                $movementHistory[] = [
                    'type' => $m['movement_type'] ?? '',
                    'quantity' => $m['quantity'] ?? 0,
                    'from' => $m['from_location'] ?? null,
                    'to' => $m['to_location'] ?? null,
                    'transaction_id' => $m['transaction_id'] ?? '',
                    'date' => isset($m['date']) ? 
                        date('d/m/Y H:i', $m['date']->toDateTime()->getTimestamp()) : 
                        'N/A',
                    'note' => $m['note'] ?? ''
                ];
            }

            // 5. Tính toán thống kê
            $quantityImported = (int)($batch['quantity_imported'] ?? 0);
            $quantityRemaining = (int)($batch['quantity_remaining'] ?? 0);
            $quantityExported = $quantityImported - $quantityRemaining;

            return [
                'success' => true,
                'batch' => [
                    'batch_code' => $batch['batch_code'] ?? '',
                    'barcode' => $batch['barcode'] ?? '',
                    'product_id' => $batch['product_id'] ?? '',
                    'product_name' => $product ? ($product['name'] ?? '') : ($batch['product_name'] ?? ''),
                    'quantity_imported' => $quantityImported,
                    'quantity_remaining' => $quantityRemaining,
                    'quantity_exported' => $quantityExported,
                    'import_date' => isset($batch['import_date']) ? 
                        (is_object($batch['import_date']) ? 
                            date('d/m/Y H:i', $batch['import_date']->toDateTime()->getTimestamp()) : 
                            $batch['import_date']) : 
                        'N/A',
                    'transaction_id' => $batch['transaction_id'] ?? '',
                    'warehouse_id' => $batch['warehouse_id'] ?? '',
                    'status' => $batch['status'] ?? ''
                ],
                'current_locations' => $currentLocations,
                'movement_history' => $movementHistory,
                'summary' => [
                    'total_locations' => count($currentLocations),
                    'total_movements' => count($movementHistory),
                    'export_percentage' => $quantityImported > 0 ? 
                        round(($quantityExported / $quantityImported) * 100, 1) : 0
                ]
            ];

        } catch (\Exception $e) {
            error_log("traceProductByBatch error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Kiểm tra số lượng có thể xuất của một sản phẩm
     * 
     * @param string $productId ID sản phẩm
     * @param string $warehouseId Kho cần kiểm tra
     * @return array Thông tin tồn kho
     */
    public function checkAvailableQuantity($productId, $warehouseId) {
        try {
            $batches = $this->mBatch->getBatchesByProductSortedByDate($productId, $warehouseId);
            
            $totalAvailable = 0;
            $batchDetails = [];

            foreach ($batches as $batch) {
                $qty = (int)($batch['quantity_remaining'] ?? 0);
                if ($qty > 0) {
                    $totalAvailable += $qty;
                    $batchDetails[] = [
                        'batch_code' => $batch['batch_code'] ?? '',
                        'quantity' => $qty,
                        'import_date' => isset($batch['import_date']) ? 
                            (is_object($batch['import_date']) ? 
                                date('d/m/Y', $batch['import_date']->toDateTime()->getTimestamp()) : 
                                $batch['import_date']) : 
                            'N/A'
                    ];
                }
            }

            $product = $this->mProduct->getProductById($productId);

            return [
                'success' => true,
                'product_id' => $productId,
                'product_name' => $product ? ($product['name'] ?? '') : 'N/A',
                'warehouse_id' => $warehouseId,
                'total_available' => $totalAvailable,
                'total_batches' => count($batchDetails),
                'batches' => $batchDetails
            ];

        } catch (\Exception $e) {
            error_log("checkAvailableQuantity error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ];
        }
    }
}
?>
