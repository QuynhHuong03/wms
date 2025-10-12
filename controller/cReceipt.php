<?php
include_once(__DIR__ . '/../model/mReceipt.php');

class CReceipt {
    private $mReceipt;

    public function __construct() {
        $this->mReceipt = new MReceipt();
    }

    // Sinh mã phiếu tự động IR0001, IR0002, ...
    public function generateReceiptId() {
        $last = $this->mReceipt->getLastReceipt();
        if (!$last || !isset($last['receipt_id'])) return 'IR0001';
        $lastId = $last['receipt_id'];
        if (preg_match('/(\d+)$/', $lastId, $m)) {
            $num = intval($m[1]) + 1;
            return 'IR' . str_pad($num, 4, '0', STR_PAD_LEFT);
        }
        return 'IR0001';
    }

    // Tạo phiếu nhập mới
    public function createReceipt($payload) {
        if (!isset($payload['type']) || !isset($payload['warehouse_id']) || !isset($payload['created_by'])) {
            return [false, 'Thiếu thông tin bắt buộc'];
        }

        $doc = [];
        $doc['receipt_id'] = $this->generateReceiptId();
        $doc['type'] = $payload['type'];
        $doc['warehouse_id'] = $payload['warehouse_id'];
        $doc['source_warehouse_id'] = $payload['source_warehouse_id'] ?? null;
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
            $cleanDetails[] = [
                'product_id' => $d['product_id'],
                'quantity' => $qty,
                'unit_price' => $price,
                'subtotal' => $subtotal
            ];
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
        
        return $this->mReceipt->updateReceipt($id, $data);
    }

    // Xóa phiếu 
    public function deleteReceipt($id) {
        return $this->mReceipt->deleteReceipt($id);
    }
}
?>
