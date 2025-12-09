<?php
include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['login'])) {
    $_SESSION['flash_receipt_error'] = "Bạn cần đăng nhập để thực hiện chức năng này.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

$cReceipt = new CReceipt();

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if (!$action || !$id) {
    $_SESSION['flash_receipt_error'] = "Thiếu thông tin phiếu.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

if (!in_array($action, ['approve', 'reject'])) {
    $_SESSION['flash_receipt_error'] = "Hành động không hợp lệ.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

// Kiểm tra quyền - chỉ quản lý mới được duyệt/từ chối
$role = $_SESSION['login']['role'] ?? '';
$role_name = $_SESSION['login']['role_name'] ?? '';
$role_id = $_SESSION['login']['role_id'] ?? '';

// Cho phép các role quản lý duyệt phiếu
$allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
$allowedRoleIds = [2, 4]; // role_id cho quản lý kho tổng và quản lý kho chi nhánh

$hasPermission = in_array($role, $allowedRoles) || 
                in_array($role_name, $allowedRoles) || 
                in_array($role_id, $allowedRoleIds);

if (!$hasPermission) {
    $_SESSION['flash_receipt_error'] = "Bạn không có quyền thực hiện chức năng này. Chỉ quản lý mới được duyệt/từ chối phiếu.";
    header("Location: ../../index.php?page=receipts");
    exit;
}

// Lấy thông tin người duyệt
$approver = $_SESSION['login']['user_id'] ?? '';

// Xác định trạng thái: 1 = duyệt, 2 = từ chối
$status = $action === 'approve' ? 1 : 2;

// If approving, check for temporary products that need manager input
if ($action === 'approve') {
    // If POST, manager submitted values for new products -> merge and proceed
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_approve'])) {
        // Load receipt
        $receipt = $cReceipt->getReceiptById($id);
        if (!$receipt) {
            $_SESSION['flash_receipt_error'] = "Không tìm thấy phiếu.";
            header("Location: ../../index.php?page=receipts");
            exit;
        }

        $details = is_array($receipt['details']) ? $receipt['details'] : json_decode(json_encode($receipt['details']), true);

        $minStocks = $_POST['min_stock'] ?? [];
        $barcodes = $_POST['barcode'] ?? [];

        // Validate barcode trùng trước khi lưu
        include_once(__DIR__ . '/../../../../../model/mProduct.php');
        $mProduct = new MProduct();
        $barcodeErrors = [];
        
        foreach ($barcodes as $idx => $barcode) {
            $barcode = trim($barcode);
            if ($barcode !== '' && $mProduct->isBarcodeExists($barcode)) {
                $productName = $details[$idx]['product_name'] ?? "Sản phẩm #$idx";
                $barcodeErrors[] = "Barcode '$barcode' (của $productName) đã tồn tại trong hệ thống";
            }
        }
        
        // Kiểm tra barcode trùng trong cùng phiếu
        $barcodeInReceipt = [];
        foreach ($barcodes as $idx => $barcode) {
            $barcode = trim($barcode);
            if ($barcode !== '') {
                if (isset($barcodeInReceipt[$barcode])) {
                    $productName = $details[$idx]['product_name'] ?? "Sản phẩm #$idx";
                    $barcodeErrors[] = "Barcode '$barcode' bị trùng trong phiếu này (sản phẩm: $productName)";
                }
                $barcodeInReceipt[$barcode] = true;
            }
        }
        
        if (!empty($barcodeErrors)) {
            if (!empty($_GET['ajax'])) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => false, 'message' => implode(', ', $barcodeErrors)]);
                exit;
            } else {
                $_SESSION['flash_receipt_error'] = implode('<br>', $barcodeErrors);
                header("Location: ../../index.php?page=receipts");
                exit;
            }
        }

        // Merge submitted values into receipt details temp fields
        $changed = false;
        foreach ($details as $idx => &$d) {
            if ((!empty($d['is_new']) || (isset($d['product_id']) && strpos((string)$d['product_id'], 'new_') === 0)) ) {
                if (!isset($d['temp']) || !is_array($d['temp'])) $d['temp'] = [];
                if (isset($minStocks[$idx]) && $minStocks[$idx] !== '') {
                    $d['temp']['min_stock'] = (int)$minStocks[$idx];
                    $changed = true;
                }
                if (isset($barcodes[$idx]) && $barcodes[$idx] !== '') {
                    $d['temp']['barcode'] = trim($barcodes[$idx]);
                    $changed = true;
                }
            }
        }
        unset($d);

        if ($changed) {
            // Save updated details back to receipt document
            include_once(__DIR__ . '/../../../../../model/mReceipt.php');
            $m = new MReceipt();
            $ok = $m->updateReceipt($id, ['details' => $details]);
            if (!$ok) {
                $_SESSION['flash_receipt_error'] = "Không thể lưu thông tin sản phẩm mới. Vui lòng thử lại.";
                header("Location: ../../index.php?page=receipts");
                exit;
            }
        }

        // Now perform approval (this will create products and batches)
        $success = $cReceipt->updateReceiptStatus($id, $status, $approver);

        // If AJAX POST (modal), return JSON instead of redirecting
        if (!empty($_GET['ajax'])) {
            header('Content-Type: application/json; charset=UTF-8');
            if ($success) echo json_encode(['success' => true, 'message' => 'Đã duyệt phiếu và tạo lô hàng']);
            else echo json_encode(['success' => false, 'message' => 'Không thể duyệt phiếu.']);
            exit;
        }
    } else {
        // GET: render a simple form for manager to confirm min_stock/barcode for new products
        $receipt = $cReceipt->getReceiptById($id);
        if (!$receipt) {
            $_SESSION['flash_receipt_error'] = "Không tìm thấy phiếu.";
            header("Location: ../../index.php?page=receipts");
            exit;
        }

        $details = is_array($receipt['details']) ? $receipt['details'] : json_decode(json_encode($receipt['details']), true);
        $newItems = [];
        foreach ($details as $idx => $d) {
            if (!empty($d['is_new']) || (isset($d['product_id']) && strpos((string)$d['product_id'], 'new_') === 0)) {
                $newItems[$idx] = $d;
            }
        }

        if (count($newItems) === 0) {
            // Nothing to collect, proceed with approval
            $success = $cReceipt->updateReceiptStatus($id, $status, $approver);
            // If AJAX request, return JSON instead of rendering
            if (!empty($_GET['ajax'])) {
                header('Content-Type: application/json; charset=UTF-8');
                if ($success) echo json_encode(['success' => true, 'message' => 'Đã duyệt phiếu và tạo lô hàng']);
                else echo json_encode(['success' => false, 'message' => 'Không thể duyệt phiếu.']);
                exit;
            }
        } else {
            // Build the form HTML fragment so it can be returned as a full page or AJAX fragment
            ob_start();
            ?>
            <h2>Những sản phẩm mới cần nhập thông tin trước khi duyệt phiếu <?php echo htmlspecialchars($id); ?></h2>
            <div id="error-message" style="display:none;padding:10px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:4px;margin-bottom:15px;"></div>
            <form method="post" action="" id="approve-new-products-form">
                <input type="hidden" name="confirm_approve" value="1" />
                <p style="color:#856404;background:#fff3cd;padding:10px;border:1px solid #ffeaa7;border-radius:4px;"><strong>⚠️ Lưu ý:</strong> Barcode phải là duy nhất, không được trùng với sản phẩm đã có trong hệ thống.</p>
                <table>
                    <thead><tr><th>#</th><th>Tên sản phẩm</th><th>SKU</th><th>Barcode (bắt buộc)*</th><th>Tồn tối thiểu (min_stock)</th></tr></thead>
                    <tbody>
                    <?php foreach ($newItems as $idx => $item) {
                        $temp = is_array($item['temp']) ? $item['temp'] : [];
                        $sku = $temp['sku'] ?? ($item['sku'] ?? '');
                        $barcode = $temp['barcode'] ?? ($item['barcode'] ?? '');
                        $min = $temp['min_stock'] ?? 0;
                    ?>
                    <tr>
                        <td><?php echo $idx; ?></td>
                        <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($sku); ?></td>
                        <td>
                            <input type="text" 
                                   name="barcode[<?php echo $idx; ?>]" 
                                   value="<?php echo htmlspecialchars($barcode); ?>" 
                                   required 
                                   pattern="[A-Za-z0-9]+" 
                                   title="Barcode chỉ được chứa chữ cái và số"
                                   onblur="checkBarcodeDuplicate(this, <?php echo $idx; ?>)"
                                   style="width:200px;" />
                            <span id="barcode-status-<?php echo $idx; ?>" style="margin-left:5px;"></span>
                        </td>
                        <td><input type="number" name="min_stock[<?php echo $idx; ?>]" value="<?php echo htmlspecialchars($min); ?>" min="0" /></td>
                    </tr>
                    
                    <?php } ?></tbody>
                </table>
                <p><button type="submit" id="submit-btn">Xác nhận và Duyệt</button> <a href="../../index.php?page=receipts">Hủy</a></p>
            </form>
            <script>
                let barcodeCheckInProgress = {};
                
                function showError(msg) {
                    const errorDiv = document.getElementById('error-message');
                    if (errorDiv) {
                        errorDiv.textContent = msg;
                        errorDiv.style.display = 'block';
                        setTimeout(() => { errorDiv.style.display = 'none'; }, 5000);
                    }
                }
                
                async function checkBarcodeDuplicate(input, idx) {
                    const barcode = input.value.trim();
                    const statusSpan = document.getElementById('barcode-status-' + idx);
                    
                    if (!barcode) {
                        if (statusSpan) statusSpan.innerHTML = '';
                        return;
                    }
                    
                    // Kiểm tra trùng trong form
                    const allBarcodeInputs = document.querySelectorAll('input[name^="barcode["]');
                    for (let i = 0; i < allBarcodeInputs.length; i++) {
                        if (allBarcodeInputs[i] !== input && allBarcodeInputs[i].value.trim() === barcode) {
                            if (statusSpan) statusSpan.innerHTML = '<span style="color:red;">❌ Trùng trong phiếu</span>';
                            input.setCustomValidity('Barcode bị trùng trong phiếu này');
                            return;
                        }
                    }
                    
                    // Kiểm tra trùng trong CSDL
                    if (statusSpan) statusSpan.innerHTML = '<span style="color:#666;">⏳ Kiểm tra...</span>';
                    barcodeCheckInProgress[idx] = true;
                    
                    try {
                        const response = await fetch('/kltn/view/page/manage/receipts/get_barcode_or_batch.php?barcode=' + encodeURIComponent(barcode));
                        const data = await response.json();
                        
                        if (data.success && data.product && data.product._id) {
                            if (statusSpan) statusSpan.innerHTML = '<span style="color:red;">❌ Đã tồn tại</span>';
                            input.setCustomValidity('Barcode đã tồn tại trong hệ thống');
                            showError('Barcode "' + barcode + '" đã tồn tại trong hệ thống. Vui lòng nhập barcode khác.');
                        } else {
                            if (statusSpan) statusSpan.innerHTML = '<span style="color:green;">✅ Hợp lệ</span>';
                            input.setCustomValidity('');
                        }
                    } catch (err) {
                        console.error('Lỗi kiểm tra barcode:', err);
                        if (statusSpan) statusSpan.innerHTML = '<span style="color:orange;">⚠️ Không kiểm tra được</span>';
                    } finally {
                        delete barcodeCheckInProgress[idx];
                    }
                }
                
                // Validate trước khi submit
                document.getElementById('approve-new-products-form').addEventListener('submit', function(e) {
                    // Kiểm tra có đang check barcode không
                    if (Object.keys(barcodeCheckInProgress).length > 0) {
                        e.preventDefault();
                        showError('Vui lòng đợi kiểm tra barcode hoàn tất');
                        return false;
                    }
                    
                    // Kiểm tra tất cả barcode inputs
                    const allBarcodeInputs = document.querySelectorAll('input[name^="barcode["]');
                    const barcodes = [];
                    for (let i = 0; i < allBarcodeInputs.length; i++) {
                        const val = allBarcodeInputs[i].value.trim();
                        if (!val) {
                            e.preventDefault();
                            showError('Vui lòng nhập barcode cho tất cả sản phẩm');
                            allBarcodeInputs[i].focus();
                            return false;
                        }
                        if (barcodes.includes(val)) {
                            e.preventDefault();
                            showError('Barcode "' + val + '" bị trùng trong phiếu này');
                            allBarcodeInputs[i].focus();
                            return false;
                        }
                        barcodes.push(val);
                    }
                });
            </script>
            <?php
            $formHtml = ob_get_clean();

            // If AJAX requested, return only the fragment
            if (!empty($_GET['ajax'])) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $formHtml;
                exit;
            }

            // Else render as a full simple page
            ?><!doctype html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Xác nhận sản phẩm mới trước khi duyệt</title>
                <style>body{font-family:Arial,Helvetica,sans-serif;padding:20px;} table{border-collapse:collapse;width:100%} td,th{border:1px solid #ddd;padding:8px}</style>
            </head>
            <body>
            <?php echo $formHtml; ?>
            </body>
            </html>
            <?php
            exit;
        }
    }
} else {
    // Non-approve actions (reject) -> just update status
    $success = $cReceipt->updateReceiptStatus($id, $status, $approver);
}

if ($success) {
    if ($action === 'approve') {
        $_SESSION['flash_receipt'] = "Phiếu đã được duyệt thành công và lô hàng đã được tạo tự động.";
    } else {
        $_SESSION['flash_receipt'] = "Phiếu đã bị từ chối.";
    }
} else {
    $_SESSION['flash_receipt_error'] = "Không thể cập nhật trạng thái phiếu. Vui lòng thử lại.";
}

header("Location: ../../index.php?page=receipts/approve");
exit;
?>
