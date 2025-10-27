<?php
include_once(__DIR__ . '/../model/mInventorySheet.php');
include_once(__DIR__ . '/../model/mInventory.php');
include_once(__DIR__ . '/../model/mProduct.php');
include_once(__DIR__ . '/../model/mLocation.php');

class CInventorySheet {
    private function currentWarehouseId() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? ($_SESSION['warehouse_id'] ?? '');
    }

    private function currentUserId() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['login']['user_id'] ?? $_SESSION['login']['id'] ?? $_SESSION['login']['_id'] ?? '';
    }

    private function currentUserName() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['login']['name'] ?? $_SESSION['login']['username'] ?? 'Unknown';
    }

    // Generate sheet code
    private function generateSheetCode() {
        return 'INV-' . date('YmdHis') . '-' . substr(uniqid(), -4);
    }

    // Get current system stock grouped by product
    public function getSystemStock($warehouseId, $filters = []) {
        $mInventory = new MInventory();
        $mProduct = new MProduct();
        $mSheet = new MInventorySheet();
        
        $inventoryFilters = [
            'warehouse_id' => $warehouseId,
        ];
        
        if (!empty($filters['from'])) {
            $inventoryFilters['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $inventoryFilters['to'] = $filters['to'];
        }

        // Get grouped inventory by product
        $groups = $mInventory->groupByProduct($inventoryFilters, 1, 1000, ['product_sku' => 1]);
        
        $stockList = [];
        foreach ($groups as $g) {
            $productId = $g['product_id'] ?? '';
            $productSku = $g['product_sku'] ?? '';
            $totalQty = isset($g['totalQty']) ? (float)$g['totalQty'] : 0;
            
            // Get product info
            $productInfo = null;
            if (!empty($productId)) {
                $productInfo = $mProduct->getProductById($productId);
            }
            
            // Get latest inventory sheet date for this product
            $lastUpdate = null;
            $latestSheet = $mSheet->getLatestSheetByProduct($productId, $warehouseId);
            if ($latestSheet && isset($latestSheet['created_at'])) {
                $lastUpdate = $latestSheet['created_at'];
            } elseif ($latestSheet && isset($latestSheet['count_date'])) {
                $lastUpdate = $latestSheet['count_date'];
            }
            
            $productName = $productInfo['product_name'] ?? '';
            $unit = $productInfo['unit'] ?? 'cái';
            
            $stockList[] = [
                'product_id' => $productId,
                'product_sku' => $productSku,
                'product_name' => $productName,
                'unit' => $unit,
                'system_qty' => $totalQty,
                'actual_qty' => 0, // To be filled by user
                'difference' => 0,
                'note' => '',
                'last_update' => $lastUpdate
            ];
        }
        
        return $stockList;
    }

    // Create new inventory sheet
    public function createInventorySheet($data) {
        $mSheet = new MInventorySheet();
        
        // Convert status text to number: draft=0, completed=1, approved=2, rejected=3
        $statusMap = ['draft' => 0, 'completed' => 1, 'approved' => 2, 'rejected' => 3];
        $inputStatus = $data['status'] ?? 'completed';
        $status = is_numeric($inputStatus) ? (int)$inputStatus : ($statusMap[$inputStatus] ?? 1);

        // Force-disable draft creation: do not allow creating sheets with status 0 (draft).
        // If somehow status resolved to 0, promote it to 1 (completed).
        if ($status === 0) {
            $status = 1;
        }
        
        $sheetData = [
            'sheet_code' => $this->generateSheetCode(),
            'warehouse_id' => $this->currentWarehouseId(),
            'created_by' => $this->currentUserId(),
            'created_by_name' => $this->currentUserName(),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'status' => $status, // 0=draft, 1=completed, 2=approved, 3=rejected
            'note' => $data['note'] ?? '',
            'items' => $data['items'] ?? [],
            'count_date' => !empty($data['count_date']) ? new MongoDB\BSON\UTCDateTime(new DateTime($data['count_date'])) : new MongoDB\BSON\UTCDateTime()
        ];
        
        return $mSheet->createSheet($sheetData);
    }

    // Update sheet items and recalculate differences
    public function updateSheetItems($sheetId, $items) {
        $mSheet = new MInventorySheet();
        
        // Recalculate differences
        foreach ($items as &$item) {
            $systemQty = isset($item['system_qty']) ? (float)$item['system_qty'] : 0;
            $actualQty = isset($item['actual_qty']) ? (float)$item['actual_qty'] : 0;
            $item['difference'] = $actualQty - $systemQty;
        }
        
        return $mSheet->updateSheetItems($sheetId, $items);
    }

    // Update sheet status
    public function updateSheetStatus($sheetId, $status) {
        $mSheet = new MInventorySheet();
        return $mSheet->updateSheet($sheetId, ['status' => $status]);
    }

    // Get sheet by ID
    public function getSheetById($sheetId) {
        $mSheet = new MInventorySheet();
        return $mSheet->getSheetById($sheetId);
    }

    // List sheets with pagination
    public function listSheets($params = []) {
        $mSheet = new MInventorySheet();
        
        $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
        $limit = isset($params['limit']) ? max(1, min(200, intval($params['limit']))) : 20;
        
        $filters = [
            'warehouse_id' => $this->currentWarehouseId(),
            'q' => $params['q'] ?? '',
            'status' => $params['status'] ?? '',
            'from' => $params['from'] ?? '',
            'to' => $params['to'] ?? ''
        ];
        
        $items = $mSheet->listSheets($filters, $page, $limit);
        $total = $mSheet->countSheets($filters);
        
        return [
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => $limit > 0 ? (int)ceil($total / $limit) : 1
        ];
    }

    // Delete sheet
    public function deleteSheet($sheetId) {
        $mSheet = new MInventorySheet();
        return $mSheet->deleteSheet($sheetId);
    }

    // Apply inventory adjustments based on completed sheet with location info
    public function applyInventoryAdjustments($sheetId, $locations = []) {
        $mSheet = new MInventorySheet();
        $mInventory = new MInventory();
        $mLocation = new MLocation(); // For updating warehouse_structure
        
        $sheet = $mSheet->getSheetById($sheetId);
        $sheetStatus = (int)($sheet['status'] ?? 0);
        if (!$sheet || $sheetStatus !== 1) {
            return ['ok' => false, 'error' => 'Phiếu không hợp lệ hoặc chưa ở trạng thái chờ duyệt (status=1)'];
        }
        
        $items = $sheet['items'] ?? [];
        $adjustments = 0;
        $warehouseId = $sheet['warehouse_id'];
        
        foreach ($items as $item) {
            $diff = isset($item['difference']) ? (float)$item['difference'] : 0;
            if ($diff == 0) continue; // No adjustment needed
            
            $productId = $item['product_id'] ?? '';
            $locationInfo = $locations[$productId] ?? null;
            
            // If locationInfo is array, get first element
            if (is_array($locationInfo) && !empty($locationInfo)) {
                // Check if it's array of locations (has numeric keys)
                if (isset($locationInfo[0])) {
                    $locationInfo = $locationInfo[0];
                }
            }
            
            // Create inventory entry for adjustment
            $entryData = [
                'warehouse_id' => $sheet['warehouse_id'],
                'product_id' => $productId,
                'product_sku' => $item['product_sku'] ?? '',
                'product_name' => $item['product_name'] ?? '',
                'qty' => $diff, // positive if actual > system, negative if system > actual
                'receipt_id' => $sheet['sheet_code'],
                'receipt_code' => $sheet['sheet_code'],
                'note' => 'Điều chỉnh theo phiếu kiểm kê: ' . ($sheet['sheet_code'] ?? ''),
                'received_at' => new MongoDB\BSON\UTCDateTime(),
                'type' => 'adjustment'
            ];
            
            // Add location info if provided
            error_log('Product: ' . $productId . ', LocationInfo: ' . json_encode($locationInfo));
            if ($locationInfo && is_array($locationInfo)) {
                $zoneId = $locationInfo['zone_id'] ?? '';
                $rackId = $locationInfo['rack_id'] ?? '';
                $binId = $locationInfo['bin_id'] ?? '';
                
                $entryData['zone_id'] = $zoneId;
                $entryData['rack_id'] = $rackId;
                $entryData['bin_id'] = $binId;
                
                error_log('Location: ' . $zoneId . '/' . $rackId . '/' . $binId);
                
                // Find existing entry in this bin for this product
                $existingEntry = $mInventory->findEntry([
                    'warehouse_id' => $sheet['warehouse_id'],
                    'product_id' => $productId,
                    'zone_id' => $zoneId,
                    'rack_id' => $rackId,
                    'bin_id' => $binId
                ]);
                
                if ($existingEntry) {
                    // Update existing entry: increase/decrease qty
                    $currentQty = isset($existingEntry['qty']) ? (float)$existingEntry['qty'] : 0;
                    $newQty = $currentQty + $diff;
                    
                    error_log('Updating existing entry: ' . $currentQty . ' + ' . $diff . ' = ' . $newQty);
                    
                    if ($mInventory->updateEntry($existingEntry['_id'], ['qty' => $newQty])) {
                        $adjustments++;
                        
                        // Update bin quantity in warehouse_structure (locations)
                        // Get current bin quantity first
                        $binDoc = $mLocation->getBinFromWarehouse($warehouseId, $zoneId, $rackId, ['bin_id' => $binId]);
                        if ($binDoc && !empty($binDoc['bin'])) {
                            $currentBinQty = (int)($binDoc['bin']['quantity'] ?? 0);
                            $newBinQty = max(0, $currentBinQty + $diff);
                            $capacity = (int)($binDoc['bin']['capacity'] ?? 0);
                            
                            // Determine status
                            $status = 'partial';
                            if ($newBinQty <= 0) $status = 'empty';
                            elseif ($capacity > 0 && $newBinQty >= $capacity) $status = 'full';
                            
                            $mLocation->updateBinInWarehouse($warehouseId, $zoneId, $rackId, $binId, [
                                'quantity' => $newBinQty,
                                'status' => $status
                            ]);
                            
                            error_log('Updated bin quantity: ' . $currentBinQty . ' + ' . $diff . ' = ' . $newBinQty);
                        }
                    }
                } else {
                    // Insert new entry - CHỈ KHI diff > 0 (tăng inventory)
                    if ($diff > 0) {
                        error_log('Inserting new entry with qty: ' . $diff);
                        if ($mInventory->insertEntry($entryData)) {
                            $adjustments++;
                            
                            // Update bin quantity in warehouse_structure (locations)
                            $binDoc = $mLocation->getBinFromWarehouse($warehouseId, $zoneId, $rackId, ['bin_id' => $binId]);
                            if ($binDoc && !empty($binDoc['bin'])) {
                                $currentBinQty = (int)($binDoc['bin']['quantity'] ?? 0);
                                $newBinQty = max(0, $currentBinQty + $diff);
                                $capacity = (int)($binDoc['bin']['capacity'] ?? 0);
                                
                                $status = 'partial';
                                if ($newBinQty <= 0) $status = 'empty';
                                elseif ($capacity > 0 && $newBinQty >= $capacity) $status = 'full';
                                
                                $mLocation->updateBinInWarehouse($warehouseId, $zoneId, $rackId, $binId, [
                                    'quantity' => $newBinQty,
                                    'status' => $status
                                ]);
                                
                                error_log('Updated bin quantity (new): ' . $currentBinQty . ' + ' . $diff . ' = ' . $newBinQty);
                            }
                        }
                    } else {
                        // diff < 0: Không có entry tại vị trí này, tìm entry khác của product để trừ
                        error_log('Diff < 0 but no entry at this location. Finding other entries for product: ' . $productId);
                        
                        // Lấy các entry của product này trong kho (sắp xếp theo FIFO)
                        $existingEntries = $mInventory->findEntries([
                            'warehouse_id' => $sheet['warehouse_id'],
                            'product_id' => $productId,
                            'qty' => ['$gt' => 0]
                        ], ['sort' => ['received_at' => 1], 'limit' => 10]);
                        
                        $remainingDiff = abs($diff); // Số lượng cần trừ
                        
                        foreach ($existingEntries as $entry) {
                            if ($remainingDiff <= 0) break;
                            
                            $entryQty = (float)($entry['qty'] ?? 0);
                            $entryId = $entry['_id'];
                            
                            if ($entryQty >= $remainingDiff) {
                                // Entry này đủ để trừ hết
                                $newQty = $entryQty - $remainingDiff;
                                $mInventory->updateEntry($entryId, ['qty' => $newQty]);
                                $adjustments++;
                                $remainingDiff = 0;
                            } else {
                                // Entry này không đủ, trừ hết và chuyển sang entry tiếp theo
                                $mInventory->updateEntry($entryId, ['qty' => 0]);
                                $adjustments++;
                                $remainingDiff -= $entryQty;
                            }
                        }
                        
                        if ($remainingDiff > 0) {
                            error_log('WARNING: Không đủ inventory để trừ. Còn thiếu: ' . $remainingDiff);
                        }
                    }
                }
            } else {
                // No location - tìm entry của product để update thay vì tạo mới
                error_log('No location info for product: ' . $productId);
                
                if ($diff > 0) {
                    // Tăng inventory: Tạo entry mới
                    if ($mInventory->insertEntry($entryData)) {
                        $adjustments++;
                    }
                } else {
                    // Giảm inventory: Tìm entry hiện có để trừ
                    $existingEntries = $mInventory->findEntries([
                        'warehouse_id' => $sheet['warehouse_id'],
                        'product_id' => $productId,
                        'qty' => ['$gt' => 0]
                    ], ['sort' => ['received_at' => 1], 'limit' => 10]);
                    
                    $remainingDiff = abs($diff);
                    
                    foreach ($existingEntries as $entry) {
                        if ($remainingDiff <= 0) break;
                        
                        $entryQty = (float)($entry['qty'] ?? 0);
                        $entryId = $entry['_id'];
                        
                        if ($entryQty >= $remainingDiff) {
                            $newQty = $entryQty - $remainingDiff;
                            $mInventory->updateEntry($entryId, ['qty' => $newQty]);
                            $adjustments++;
                            $remainingDiff = 0;
                        } else {
                            $mInventory->updateEntry($entryId, ['qty' => 0]);
                            $adjustments++;
                            $remainingDiff -= $entryQty;
                        }
                    }
                    
                    if ($remainingDiff > 0) {
                        error_log('WARNING: Không đủ inventory để trừ. Còn thiếu: ' . $remainingDiff);
                    }
                }
            }
        }
        
        // Don't update status here - let approveSheet do it
        return ['ok' => true, 'adjustments' => $adjustments];
    }

    // Approve sheet
    public function approveSheet($sheetId, $userId, $userName, $note = '', $locations = []) {
        $mSheet = new MInventorySheet();
        
        $sheet = $mSheet->getSheetById($sheetId);
        if (!$sheet) {
            return ['ok' => false, 'error' => 'Không tìm thấy phiếu'];
        }
        
        // Check if sheet belongs to current warehouse
        $currentWarehouse = $this->currentWarehouseId();
        if (isset($sheet['warehouse_id']) && $sheet['warehouse_id'] !== $currentWarehouse) {
            return ['ok' => false, 'error' => 'Bạn không có quyền duyệt phiếu của kho khác'];
        }
        
        // Status: 0=draft, 1=completed, 2=approved, 3=rejected
        $sheetStatus = (int)($sheet['status'] ?? 0);
        if ($sheetStatus !== 1) {
            return ['ok' => false, 'error' => 'Chỉ có thể duyệt phiếu đang chờ duyệt (status=1)'];
        }
        
        // Apply inventory adjustments with location info
        $adjustResult = $this->applyInventoryAdjustments($sheetId, $locations);
        
        if (!$adjustResult['ok']) {
            return $adjustResult;
        }
        
        // Update sheet with approval info
        $mSheet->updateSheet($sheetId, [
            'status' => 2, // approved
            'approved_by' => $userId,
            'approved_by_name' => $userName,
            'approved_at' => new MongoDB\BSON\UTCDateTime(),
            'approve_note' => $note,
            'locations' => $locations // Save location assignments
        ]);
        
        return [
            'ok' => true,
            'message' => 'Đã duyệt phiếu và áp dụng ' . $adjustResult['adjustments'] . ' điều chỉnh'
        ];
    }

    // Reject sheet
    public function rejectSheet($sheetId, $userId, $userName, $note = '') {
        $mSheet = new MInventorySheet();
        
        $sheet = $mSheet->getSheetById($sheetId);
        if (!$sheet) {
            return ['ok' => false, 'error' => 'Không tìm thấy phiếu'];
        }
        
        // Check if sheet belongs to current warehouse
        $currentWarehouse = $this->currentWarehouseId();
        if (isset($sheet['warehouse_id']) && $sheet['warehouse_id'] !== $currentWarehouse) {
            return ['ok' => false, 'error' => 'Bạn không có quyền từ chối phiếu của kho khác'];
        }
        
        // Status: 0=draft, 1=completed, 2=approved, 3=rejected
        $sheetStatus = (int)($sheet['status'] ?? 0);
        if ($sheetStatus !== 1) {
            return ['ok' => false, 'error' => 'Chỉ có thể từ chối phiếu đang chờ duyệt (status=1)'];
        }
        
        $mSheet->updateSheet($sheetId, [
            'status' => 3, // rejected
            'rejected_by' => $userId,
            'rejected_by_name' => $userName,
            'rejected_at' => new MongoDB\BSON\UTCDateTime(),
            'reject_note' => $note
        ]);
        
        return ['ok' => true, 'message' => 'Đã từ chối phiếu'];
    }
}
?>
