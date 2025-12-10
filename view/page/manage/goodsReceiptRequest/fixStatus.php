<?php
/**
 * Script sửa trạng thái các phiếu yêu cầu đã có phiếu xuất
 * Chạy 1 lần để fix data
 */

if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../model/connect.php");

$p = new clsKetNoi();
$con = $p->moKetNoi();

if (!$con) {
    die("Không thể kết nối database!");
}

$transCol = $con->selectCollection('transactions');

// Tìm tất cả phiếu xuất
$exportReceipts = $transCol->find([
    'transaction_type' => 'export'
])->toArray();

$updatedCount = 0;
$errors = [];

echo "<h2>Đang sửa trạng thái các phiếu yêu cầu...</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr>
    <th>Phiếu xuất</th>
    <th>Phiếu yêu cầu</th>
    <th>Status cũ</th>
    <th>Status mới</th>
    <th>Kết quả</th>
</tr>";

foreach ($exportReceipts as $export) {
    $exportId = $export['transaction_id'] ?? 'N/A';
    $requestId = $export['request_id'] ?? null;
    
    if (!$requestId) {
        echo "<tr>
            <td>$exportId</td>
            <td colspan='4' style='color:orange;'>Không có request_id</td>
        </tr>";
        continue;
    }
    
    // Lấy phiếu yêu cầu
    $request = $transCol->findOne([
        'transaction_type' => 'goods_request',
        'transaction_id' => $requestId
    ]);
    
    if (!$request) {
        echo "<tr>
            <td>$exportId</td>
            <td>$requestId</td>
            <td colspan='3' style='color:red;'>Không tìm thấy phiếu yêu cầu</td>
        </tr>";
        $errors[] = "Export $exportId: Không tìm thấy request $requestId";
        continue;
    }
    
    $oldStatus = $request['status'] ?? 0;
    
    // Nếu status đã là 6, bỏ qua
    if ($oldStatus == 6) {
        echo "<tr>
            <td>$exportId</td>
            <td>$requestId</td>
            <td>$oldStatus</td>
            <td>6</td>
            <td style='color:green;'>✓ Đã đúng</td>
        </tr>";
        continue;
    }
    
    // Cập nhật status thành 6
    try {
        $updateResult = $transCol->updateOne(
            [
                'transaction_type' => 'goods_request',
                'transaction_id' => $requestId
            ],
            [
                '$set' => [
                    'status' => 6,
                    'export_id' => $exportId,
                    'completed_at' => new MongoDB\BSON\UTCDateTime(),
                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        if ($updateResult->getModifiedCount() > 0) {
            $updatedCount++;
            echo "<tr>
                <td>$exportId</td>
                <td>$requestId</td>
                <td style='color:orange;'>$oldStatus</td>
                <td style='color:green;'>6</td>
                <td style='color:green;'>✓ Đã cập nhật</td>
            </tr>";
        } else {
            echo "<tr>
                <td>$exportId</td>
                <td>$requestId</td>
                <td>$oldStatus</td>
                <td>6</td>
                <td style='color:orange;'>⚠ Không có thay đổi</td>
            </tr>";
        }
    } catch (Exception $e) {
        echo "<tr>
            <td>$exportId</td>
            <td>$requestId</td>
            <td>$oldStatus</td>
            <td>-</td>
            <td style='color:red;'>✗ Lỗi: " . $e->getMessage() . "</td>
        </tr>";
        $errors[] = "Export $exportId: " . $e->getMessage();
    }
}

echo "</table>";

echo "<h3 style='margin-top:30px;'>Kết quả:</h3>";
echo "<p><strong>Đã cập nhật:</strong> $updatedCount phiếu</p>";

if (!empty($errors)) {
    echo "<h4 style='color:red;'>Lỗi:</h4>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

$p->dongKetNoi($con);

echo "<p style='margin-top:30px;'><a href='../index.php?page=goodsReceiptRequest'>← Quay lại danh sách</a></p>";
?>
