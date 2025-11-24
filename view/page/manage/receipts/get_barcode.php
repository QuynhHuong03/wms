<?php
require_once(__DIR__ . '/../../../../model/connect.php');

$db = (new Database())->getConnection();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Barcode sản phẩm</title>";
echo "<style>body{font-family:Arial;padding:20px;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:12px;text-align:left;}th{background:#4CAF50;color:white;}.barcode{font-size:20px;font-weight:bold;background:#fff3cd;padding:10px;border-radius:5px;display:inline-block;}</style>";
echo "</head><body>";

echo "<h1>🔍 Barcode sản phẩm trong phiếu xuất EX0008</h1>";

// Lấy phiếu xuất EX0008
$export = $db->transactions->findOne(['transaction_id' => 'EX0008']);

if (!$export) {
    echo "<p>Không tìm thấy phiếu xuất EX0008</p>";
} else {
    $details = $export['details'] ?? $export['products'] ?? [];
    
    echo "<h2>Sản phẩm trong phiếu:</h2>";
    echo "<table>";
    echo "<tr><th>Tên sản phẩm</th><th>SKU</th><th>Product ID</th><th>Số lượng</th><th>Barcode</th></tr>";
    
    foreach ($details as $item) {
        $productId = $item['product_id'] ?? '';
        
        // Lấy thông tin sản phẩm từ collection products
        $product = $db->products->findOne(['_id' => new MongoDB\BSON\ObjectId($productId)]);
        
        if ($product) {
            $barcode = $product['barcode'] ?? 'Chưa có barcode';
            
            echo "<tr>";
            echo "<td><strong>" . ($item['product_name'] ?? 'N/A') . "</strong></td>";
            echo "<td>" . ($item['sku'] ?? ($product['sku'] ?? '-')) . "</td>";
            echo "<td><small>" . $productId . "</small></td>";
            echo "<td>" . ($item['quantity'] ?? 0) . " " . ($item['unit'] ?? '') . "</td>";
            echo "<td><span class='barcode'>" . $barcode . "</span></td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    
    echo "<hr>";
    echo "<h2>📱 Hướng dẫn quét barcode:</h2>";
    echo "<ol>";
    echo "<li>Copy mã barcode ở trên</li>";
    echo "<li>Vào trang <a href='../index.php?page=receipts'>Tạo phiếu nhập hàng</a></li>";
    echo "<li>Chọn 'Nhập điều chuyển nội bộ' → Chọn 'Kho Tổng Hà Nội' → Chọn phiếu xuất</li>";
    echo "<li>Paste mã barcode vào ô 'Nhập mã vạch' và nhấn Enter</li>";
    echo "<li>Sản phẩm sẽ xuất hiện trong bảng!</li>";
    echo "</ol>";
}

echo "</body></html>";
?>
