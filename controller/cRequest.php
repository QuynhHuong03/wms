<?php
include_once(__DIR__ . '/../model/mRequest.php');

class CRequest {
    private $mRequest;

    public function __construct() {
        $this->mRequest = new MRequest();
    }

    // Sinh mã phiếu yêu cầu tự động RQ0001, RQ0002, ...
    public function generateRequestId() {
        $last = $this->mRequest->getLastRequest();
        if (!$last || !isset($last['transaction_id'])) return 'RQ0001';
        $lastId = $last['transaction_id'];
        if (preg_match('/(\d+)$/', $lastId, $m)) {
            $num = intval($m[1]) + 1;
            return 'RQ' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
        return 'RQ0001';
    }

    // Tạo phiếu yêu cầu nhập mới
    public function createRequest($payload) {
        if (!isset($payload['warehouse_id']) || !isset($payload['created_by'])) {
            return [false, 'Thiếu thông tin bắt buộc'];
        }

        $doc = [];
        $doc['transaction_id'] = $this->generateRequestId();
        $doc['transaction_type'] = 'goods_request'; // ⭐ Phân biệt loại giao dịch
        $doc['type'] = 'transfer'; // Luôn là transfer
        
        // ⭐ Mức độ ưu tiên
        $doc['priority'] = $payload['priority'] ?? 'normal'; // normal hoặc urgent
        $doc['is_urgent'] = ($doc['priority'] === 'urgent');
        
        $doc['warehouse_id'] = $payload['warehouse_id']; // Kho đích (chi nhánh)
        $doc['source_warehouse_id'] = $payload['source_warehouse_id'] ?? 'KHO_TONG_01';
        
        // ⭐ Kho được chỉ định (ban đầu null)
        $doc['assigned_warehouse_id'] = null;
        $doc['assigned_by'] = null;
        $doc['assigned_at'] = null;
        $doc['assignment_note'] = null;
        
        $doc['created_by'] = $payload['created_by'];
        $doc['created_at'] = new MongoDB\BSON\UTCDateTime();
        $doc['approved_by'] = null;
        $doc['approved_at'] = null;
        $doc['processed_by'] = null;
        $doc['processed_at'] = null;
        $doc['note'] = $payload['note'] ?? null;
        $doc['status'] = 0; // Chờ QL chi nhánh duyệt

        // Chi tiết sản phẩm
        $details = $payload['details'] ?? [];
        $total = 0;
        $cleanDetails = [];
        $productsBelowMin = 0;
        $totalShortage = 0;
        
        foreach ($details as $d) {
            if (!isset($d['product_id']) || !isset($d['quantity'])) continue;
            
            $qty = (int)$d['quantity'];
            $price = (float)($d['unit_price'] ?? 0);
            $subtotal = $qty * $price;
            
            $currentStock = (int)($d['current_stock'] ?? 0);
            $minStock = (int)($d['min_stock'] ?? 0);
            $shortage = max(0, $minStock - $currentStock);
            
            if ($currentStock < $minStock) {
                $productsBelowMin++;
                $totalShortage += $shortage;
            }
            
            $cleanDetails[] = [
                'product_id' => $d['product_id'],
                'product_name' => $d['product_name'] ?? '',
                'sku' => $d['sku'] ?? '',
                'current_stock' => $currentStock,
                'min_stock' => $minStock,
                'shortage' => $shortage,
                'quantity' => $qty,
                'unit' => $d['unit'] ?? 'cái',
                'unit_price' => $price,
                'subtotal' => $subtotal,
                'source_stock' => (int)($d['source_stock'] ?? 0),
                'is_sufficient' => ($d['is_sufficient'] ?? false),
                'alternative_warehouses' => $d['alternative_warehouses'] ?? []
            ];
            $total += $subtotal;
        }
        
        $doc['details'] = $cleanDetails;
        $doc['total_amount'] = $total;
        
        // ⭐ Metadata
        $doc['request_metadata'] = [
            'total_products' => count($cleanDetails),
            'products_below_min' => $productsBelowMin,
            'total_shortage' => $totalShortage,
            'requires_urgent_action' => ($doc['is_urgent'] || $productsBelowMin > 0)
        ];

        $inserted = $this->mRequest->insertRequest($doc);
        if ($inserted) return [true, $doc['transaction_id']];
        return [false, 'Lưu phiếu yêu cầu thất bại'];
    }

    // Lấy tất cả phiếu yêu cầu (dành cho quản lý)
    public function getAllRequests() {
        $data = $this->mRequest->getAllRequests();
        return iterator_to_array($data);
    }

    // Lấy phiếu theo người tạo
    public function getRequestsByUser($userId) {
        $data = $this->mRequest->getRequestsByUser($userId);
        return iterator_to_array($data);
    }

    // Lấy phiếu theo warehouse_id
    public function getRequestsByWarehouse($warehouseId) {
        $data = $this->mRequest->getRequestsByWarehouse($warehouseId);
        return iterator_to_array($data);
    }

    // ⭐ Lấy phiếu yêu cầu gửi đến kho nguồn (dành cho kho tổng)
    public function getRequestsBySourceWarehouse($sourceWarehouseId, $statusFilter = [1, 4]) {
        $data = $this->mRequest->getRequestsBySourceWarehouse($sourceWarehouseId, $statusFilter);
        return iterator_to_array($data);
    }

    // ⭐ Lấy phiếu được chỉ định cho kho
    public function getRequestsAssignedToWarehouse($warehouseId) {
        $data = $this->mRequest->getRequestsAssignedToWarehouse($warehouseId);
        return iterator_to_array($data);
    }

    // Lấy chi tiết 1 phiếu yêu cầu
    public function getRequestById($id) {
        return $this->mRequest->getRequestById($id);
    }

    // Duyệt phiếu yêu cầu
    public function approveRequest($id, $approver) {
        $data = [
            'status' => 1,
            'approved_by' => $approver,
            'approved_at' => new MongoDB\BSON\UTCDateTime()
        ];
        return $this->mRequest->updateRequest($id, $data);
    }

    // Từ chối phiếu yêu cầu
    public function rejectRequest($id, $approver) {
        $data = [
            'status' => 2,
            'approved_by' => $approver,
            'approved_at' => new MongoDB\BSON\UTCDateTime()
        ];
        return $this->mRequest->updateRequest($id, $data);
    }

    // Cập nhật trạng thái phiếu yêu cầu
    public function updateRequestStatus($id, $status, $approver = null) {
        $data = [
            'status' => (int)$status
        ];
        
        if ($approver) {
            $data['approved_by'] = $approver;
            $data['approved_at'] = new MongoDB\BSON\UTCDateTime();
        }
        
        return $this->mRequest->updateRequest($id, $data);
    }

    // Xóa phiếu yêu cầu
    public function deleteRequest($id) {
        return $this->mRequest->deleteRequest($id);
    }

    // ⭐ Chỉ định kho thay thế (khi kho tổng không đủ hàng)
    public function assignAlternativeWarehouse($requestId, $warehouseId, $assignedBy, $note = null) {
        $data = [
            'status' => 5, // Đã chỉ định kho thay thế
            'assigned_warehouse_id' => $warehouseId,
            'assigned_by' => $assignedBy,
            'assigned_at' => new MongoDB\BSON\UTCDateTime(),
            'assignment_note' => $note
        ];
        return $this->mRequest->updateRequest($requestId, $data);
    }

    // ⭐ Xác nhận kho đủ hàng
    public function confirmStockAvailability($requestId, $processedBy, $isSufficient = true) {
        $status = $isSufficient ? 3 : 4; // 3: Đủ hàng, 4: Không đủ
        $data = [
            'status' => $status,
            'processed_by' => $processedBy,
            'processed_at' => new MongoDB\BSON\UTCDateTime()
        ];
        return $this->mRequest->updateRequest($requestId, $data);
    }

    // Chuyển phiếu yêu cầu thành phiếu nhập hàng
    public function convertToReceipt($requestId) {
        $request = $this->mRequest->getRequestById($requestId);
        if (!$request) return [false, 'Không tìm thấy phiếu yêu cầu'];

        $status = (int)($request['status'] ?? 0);
        if (!in_array($status, [3, 5])) {
            return [false, 'Chỉ có thể chuyển phiếu đã xác nhận đủ hàng (status 3 hoặc 5)'];
        }

        // Đánh dấu phiếu yêu cầu đã chuyển
        $this->mRequest->updateRequest($requestId, [
            'status' => 6,
            'completed_at' => new MongoDB\BSON\UTCDateTime()
        ]);

        return [true, $request];
    }
}
?>
