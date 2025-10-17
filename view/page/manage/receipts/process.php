<?php
// process.php
// Hỗ trợ 2 chế độ:
//  - GET ?barcode=... => trả JSON thông tin sản phẩm (AJAX)
//  - POST => lưu phiếu nhập qua CReceipt

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();

$incProduct = @include_once(__DIR__ . '/../../../../controller/cProduct.php');
$incReceipt = @include_once(__DIR__ . '/../../../../controller/cReceipt.php');
$buffer = ob_get_clean();

if ($incProduct === false) {
    error_log('process.php: Không thể include cProduct.php');
}
if ($incReceipt === false) {
    error_log('process.php: Không thể include cReceipt.php');
}

try {
    // --- GET: tra cứu sản phẩm theo barcode ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['barcode'])) {
        header('Content-Type: application/json; charset=UTF-8');

        $barcode = trim($_GET['barcode']);
        if ($barcode === '') {
            echo json_encode(["success" => false, "message" => "Thiếu mã barcode"]);
            exit;
        }

        if (!class_exists('CProduct')) {
            error_log('process.php: class CProduct không tồn tại');
            echo json_encode(["success" => false, "message" => "Lỗi server"]);
            exit;
        }

        $p = new CProduct();
        $product = $p->getProductByBarcode($barcode);

        if ($product) {
            // ✅ Chuẩn hóa dữ liệu trả về
            $baseUnit = $product['baseUnit'] ?? 'Cái';
            $conversionUnits = $product['conversionUnits'] ?? [];
$id = '';
if (isset($product['_id'])) {
    if ($product['_id'] instanceof MongoDB\BSON\ObjectId) {
        $id = (string)$product['_id']; // ✅ chuyển ObjectId -> chuỗi
    } elseif (is_array($product['_id']) && isset($product['_id']['$oid'])) {
        $id = (string)$product['_id']['$oid']; // ✅ trường hợp từ JSON
    } else {
        $id = (string)$product['_id'];
    }
}

echo json_encode([
    "success" => true,
    "product" => [
        "_id" => $id, // ✅ luôn có chuỗi _id
        "sku" => $product['sku'] ?? '',
        "barcode" => $product['barcode'] ?? '',
        "product_name" => $product['product_name'] ?? '',
        "baseUnit" => $baseUnit,
        "conversionUnits" => $conversionUnits,
        "purchase_price" => $product['purchase_price'] ?? 0
    ]
]);

        } else {
            echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm"]);
        }
        exit;
    }

    // --- POST: tạo phiếu nhập ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ✅ DEBUG: Log dữ liệu POST để kiểm tra
        error_log('=== DEBUG POST DATA ===');
        error_log('POST products: ' . print_r($_POST['products'] ?? [], true));
        
        $type = $_POST['type'] ?? null;
        $warehouse_id = $_POST['warehouse_id'] ?? ($_SESSION['warehouse_id'] ?? null);
        $created_by = $_POST['created_by'] ?? ($_SESSION['user_id'] ?? 'system');
        $supplier_id = $_POST['supplier_id'] ?? null;
        $source_warehouse_id = $_POST['source_warehouse_id'] ?? null;
        $note = $_POST['note'] ?? null;

        $details = [];
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $p) {
                if (empty($p['product_id'])) continue;
                $qty = isset($p['quantity']) ? (float)$p['quantity'] : 0;
                $price = isset($p['price']) ? (float)$p['price'] : 0.0;
                if ($qty <= 0) continue;

                $detail = [
                    'product_id' => $p['product_id'],
                    'product_name' => $p['product_name'] ?? '',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit' => $p['unit'] ?? '' // thêm đơn vị tính nếu có
                ];
                
                // ✅ DEBUG: Log từng detail
                error_log('Detail item: ' . print_r($detail, true));
                
                $details[] = $detail;
            }
        }

        $payload = [
            'type' => $type,
            'warehouse_id' => $warehouse_id,
            'created_by' => $created_by,
            'supplier_id' => $supplier_id,
            'source_warehouse_id' => $source_warehouse_id,
            'note' => $note,
            'status' => 0,
            'details' => $details
        ];

        if (!class_exists('CReceipt')) {
            error_log('process.php: CReceipt class missing');
            $_SESSION['flash_receipt_error'] = 'Lỗi server: không thể xử lý yêu cầu.';
            echo "<script>window.location.href = '../../index.php';</script>";
            exit;
        }

        $rc = new CReceipt();
        $result = $rc->createReceipt($payload);

        if (is_array($result) && isset($result[0]) && $result[0] === true) {
            $_SESSION['flash_receipt'] = 'Tạo phiếu thành công.';
            echo "<script>alert('Tạo phiếu thành công!'); window.location.href = '../index.php?page=receipts';</script>";
            exit;
        } else {
            $msg = is_array($result) ? ($result[1] ?? 'Lưu phiếu thất bại') : 'Lưu phiếu thất bại';
            $_SESSION['flash_receipt_error'] = $msg;
            echo "<script>alert('" . addslashes($msg) . "'); window.location.href = '../../index.php';</script>";
            exit;
        }
    }

    // --- method không hợp lệ ---
    echo "<script>alert('Method not allowed'); window.location.href = '../../index.php';</script>";

} catch (\Throwable $e) {
    error_log('process.php exception: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(["success" => false, "message" => "Lỗi server"]);
    } else {
        $_SESSION['flash_receipt_error'] = 'Lỗi server';
        echo "<script>alert('Lỗi server'); window.location.href = '../../index.php';</script>";
    }
}
?>

<form method="post" action="receipts/process.php">
    <input type="hidden" name="type" value="import">
    <input type="hidden" name="warehouse_id" value="warehouse_1">
    <input type="hidden" name="created_by" value="system">
    <input type="hidden" name="supplier_id" value="supplier_1">
    <input type="hidden" name="source_warehouse_id" value="warehouse_2">
    <input type="hidden" name="note" value="Note for import">

    <input type="text" name="products[0][product_id]" value="product_1">
    <input type="number" name="products[0][quantity]" value="10">
    <input type="number" name="products[0][price]" value="100.0">

    <input type="text" name="products[1][product_id]" value="product_2">
    <input type="number" name="products[1][quantity]" value="5">
    <input type="number" name="products[1][price]" value="200.0">

    <button type="submit">Tạo phiếu nhập</button>
</form>
