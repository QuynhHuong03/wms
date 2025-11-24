<?php
/**
 * API xử lý batch: detail, delete
 */

// Bật output buffering để tránh output lỗi trước header
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear any previous output
ob_clean();

header('Content-Type: application/json; charset=utf-8');

/**
 * Respond with JSON safely: clear output buffer, log stray output, then echo JSON and exit
 */
function respond($data, $options = JSON_UNESCAPED_UNICODE) {
    // Capture and remove any stray output that would break JSON
    if (ob_get_length() !== false) {
        $stray = ob_get_clean();
        if (trim($stray) !== '') {
            error_log("[Batch API] Stray output detected before JSON response: " . $stray);
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, $options);
    exit;
}

try {
    include_once(__DIR__ . "/../../../../controller/cBatch.php");
    include_once(__DIR__ . "/../../../../model/connect.php");
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi load file: ' . $e->getMessage()]);
    exit;
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$batchCode = $_GET['batch_code'] ?? $_POST['batch_code'] ?? '';

error_log("Batch API - Action: $action, Batch: $batchCode");

try {
    $cBatch = new CBatch();
    // Use the same connection helper as the rest of the code (clsKetNoi -> default DB 'wms')
    // Previously code used Database() which connects to DB 'WMS' (different name/case),
    // causing findOne to not locate documents. Use clsKetNoi for compatibility.
    $db = (new clsKetNoi())->moKetNoi();
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Lỗi kết nối: ' . $e->getMessage()]);
    exit;
}

try {
    switch ($action) {
        case 'get_detail':
            if (empty($batchCode)) {
                respond(['success' => false, 'message' => 'Thiếu mã lô']);
            }
            
            $batch = $db->batches->findOne(['batch_code' => $batchCode]);
            if (!$batch) {
                respond(['success' => false, 'message' => 'Không tìm thấy lô hàng']);
            }
            
            // Lấy locations
            $locations = $db->batch_locations->find(['batch_code' => $batchCode])->toArray();
            
            // Lấy movement history
            $movements = $db->inventory_movements->find(
                ['batch_code' => $batchCode],
                ['sort' => ['date' => -1]]
            )->toArray();
            
            // Format data
            $batchData = json_decode(json_encode($batch), true);

            // If this batch was created from a transfer, expose the source batch code
            if (isset($batch['source_batch_code'])) {
                $batchData['source_batch_code'] = $batch['source_batch_code'];
                // For UI convenience, set 'source' to the source batch code and mark type as transfer
                $batchData['source'] = $batch['source_batch_code'];
                $batchData['type'] = 'transfer';
            }
            $batchData['locations'] = array_map(function($loc) {
                $locArray = json_decode(json_encode($loc), true);
                if (isset($locArray['location'])) {
                    $l = $locArray['location'];
                    $locArray['location_string'] = 
                        ($l['warehouse_id'] ?? 'N/A') . '/' .
                        ($l['zone_id'] ?? 'N/A') . '/' .
                        ($l['rack_id'] ?? 'N/A') . '/' .
                        ($l['bin_id'] ?? 'N/A');
                }
                return $locArray;
            }, $locations);
            
            $batchData['movements'] = array_map(function($mov) {
                $movArray = json_decode(json_encode($mov), true);
                if (isset($movArray['date']) && is_object($mov['date'])) {
                    $movArray['date'] = date('d/m/Y H:i', $mov['date']->toDateTime()->getTimestamp());
                }
                
                // Format from_location
                if (isset($movArray['from_location']) && is_array($movArray['from_location'])) {
                    $from = $movArray['from_location'];
                    $movArray['from_location'] = 
                        ($from['warehouse_id'] ?? '') . '/' .
                        ($from['zone_id'] ?? '') . '/' .
                        ($from['rack_id'] ?? '') . '/' .
                        ($from['bin_id'] ?? '');
                } elseif (!isset($movArray['from_location']) || $movArray['from_location'] === null) {
                    // ⭐ Nếu from_location = null → nhập từ nhà cung cấp
                    $movArray['from_location'] = 'Nhà cung cấp';
                }
                
                // Format to_location
                if (isset($movArray['to_location']) && is_array($movArray['to_location'])) {
                    $to = $movArray['to_location'];
                    $movArray['to_location'] = 
                        ($to['warehouse_id'] ?? '') . '/' .
                        ($to['zone_id'] ?? '') . '/' .
                        ($to['rack_id'] ?? '') . '/' .
                        ($to['bin_id'] ?? '');
                } elseif (!isset($movArray['to_location']) || $movArray['to_location'] === null) {
                    // ⭐ Nếu to_location = null → xuất bán cho khách hàng
                    $movArray['to_location'] = 'Khách hàng';
                }
                
                return $movArray;
            }, $movements);
            
            if (isset($batchData['import_date']) && is_object($batch['import_date'])) {
                $batchData['import_date'] = date('d/m/Y H:i', $batch['import_date']->toDateTime()->getTimestamp());
            }
            
            respond(['success' => true, 'data' => $batchData], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'delete':
            if (empty($batchCode)) {
                respond(['success' => false, 'message' => 'Thiếu mã lô']);
            }
            
            $batch = $db->batches->findOne(['batch_code' => $batchCode]);
            if (!$batch) {
                respond(['success' => false, 'message' => 'Không tìm thấy lô hàng']);
            }
            
            // Kiểm tra số lượng còn
            if (($batch['quantity_remaining'] ?? 0) > 0) {
                respond([
                    'success' => false, 
                    'message' => 'Không thể xóa lô còn hàng! Số lượng còn: ' . ($batch['quantity_remaining'] ?? 0)
                ]);
            }
            
            // Xóa batch_locations
            $db->batch_locations->deleteMany(['batch_code' => $batchCode]);
            
            // Xóa batch
            $result = $db->batches->deleteOne(['batch_code' => $batchCode]);
            
            if ($result->getDeletedCount() > 0) {
                $_SESSION['flash_batch'] = "Đã xóa lô $batchCode thành công";
                respond(['success' => true, 'message' => 'Đã xóa lô hàng']);
            } else {
                respond(['success' => false, 'message' => 'Không thể xóa lô hàng']);
            }
            break;
            
        default:
            respond(['success' => false, 'message' => 'Action không hợp lệ']);
    }
    
} catch (Exception $e) {
    respond([
        'success' => false, 
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// Fallback - ensure no accidental output
respond(['success' => false, 'message' => 'Unexpected termination']);
