<?php
// process.php
// Supports two modes:
//  - GET with `barcode` => return JSON product info (AJAX)
//  - POST form submit => create receipt via CReceipt, set session flash and redirect back

// Disable display errors to client
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session if not already
if (session_status() === PHP_SESSION_NONE) session_start();

// Use output buffering to avoid accidental output breaking redirects / JSON
ob_start();

// Include controllers
$incProduct = @include_once(__DIR__ . '/../../../../controller/cProduct.php');
$incReceipt = @include_once(__DIR__ . '/../../../../controller/cReceipt.php');
$buffer = ob_get_clean(); // discard any unintended output from includes

if ($incProduct === false) {
    error_log('process.php: Không thể include cProduct.php (path check)');
}
if ($incReceipt === false) {
    error_log('process.php: Không thể include cReceipt.php (path check)');
}

try {
    // --- GET: barcode lookup (AJAX expects JSON) ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['barcode'])) {
        header('Content-Type: application/json; charset=UTF-8');

        $barcode = trim($_GET['barcode']);
        if ($barcode === '') {
            echo json_encode(["success" => false, "message" => "Thiếu mã barcode"]);
            exit;
        }

        if (!class_exists('CProduct')) {
            error_log('process.php: class CProduct không tồn tại sau include');
            echo json_encode(["success" => false, "message" => "Lỗi server"]);
            exit;
        }

        $p = new CProduct();
        $product = $p->getProductByBarcode($barcode);

        if ($product) {
            echo json_encode([
                "success" => true,
                "product" => [
                    "_id" => isset($product['sku']) ? $product['sku'] : ($product['_id'] ?? ''),
                    "name" => $product['product_name'] ?? ($product['name'] ?? ''),
                    "unit" => $product['unit'] ?? "Cái",
                    "import_price" => isset($product['import_price']) ? $product['import_price'] : 0
                ]
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm"]);
        }
        exit;
    }

    // --- POST: form submission to create a receipt ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Build payload for CReceipt
        $type = $_POST['type'] ?? null;
        $warehouse_id = $_POST['warehouse_id'] ?? ($_SESSION['warehouse_id'] ?? null);
        $created_by = $_POST['created_by'] ?? ($_SESSION['user_id'] ?? 'system');
        $supplier_id = $_POST['supplier_id'] ?? null;
        $source_warehouse_id = $_POST['source_warehouse_id'] ?? null;
        $note = $_POST['note'] ?? null;

        // collect details
        $details = [];
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $p) {
                // expected fields from the form: product_id, quantity, price
                if (empty($p['product_id'])) continue;
                $qty = isset($p['quantity']) ? (int)$p['quantity'] : 0;
                $price = isset($p['price']) ? (float)$p['price'] : 0.0;
                if ($qty <= 0) continue;
                $details[] = [
                    'product_id' => $p['product_id'],
                    'product_name' => $p['product_name'] ?? '',
                    'quantity' => $qty,
                    'unit_price' => $price
                ];
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
            // client-side redirect back to form (use JS so redirects work even after output)
            echo "<script>window.location.href = '../../index.php';</script>";
            exit;
        }

        $rc = new CReceipt();
        $result = $rc->createReceipt($payload);
        if (is_array($result) && isset($result[0]) && $result[0] === true) {
            $insertedId = $result[1];
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

    // If reached here, unsupported method
    // If reached here, method not allowed — notify client via JS so page-based flow handles it
    echo "<script>alert('Method not allowed'); window.location.href = '../../index.php';</script>";

} catch (\Throwable $e) {
    error_log('process.php exception: ' . $e->getMessage());
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(["success" => false, "message" => "Lỗi server"]);
    } else {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash_receipt_error'] = 'Lỗi server';
        echo "<script>alert('Lỗi server'); window.location.href = '../../index.php';</script>";
    }
}
