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
            <style>
                .modal-content-wrapper {
                    padding: 20px;
                    background: #fff;
                }
                .modal-subtitle {
                    color: #666;
                    font-size: 14px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                #error-message {
                    display: none;
                    padding: 12px 15px;
                    background: #fee;
                    color: #c33;
                    border: 1px solid #fcc;
                    border-radius: 8px;
                    margin-bottom: 15px;
                    font-size: 14px;
                }
                .warning-box {
                    color: #856404;
                    background: #fff3cd;
                    padding: 12px 15px;
                    border: 1px solid #ffeaa7;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .warning-box strong {
                    font-weight: 600;
                }
                .products-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                    border-radius: 8px;
                    overflow: hidden;
                }
                .products-table thead {
                    background: #667eea;
                }
                .products-table th {
                    padding: 14px 12px;
                    text-align: left;
                    font-size: 13px;
                    font-weight: 600;
                    color: #fff;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .products-table tbody tr {
                    border-bottom: 1px solid #e9ecef;
                    transition: background 0.2s;
                }
                .products-table tbody tr:hover {
                    background: #f8f9ff;
                }
                .products-table tbody tr:last-child {
                    border-bottom: none;
                }
                .products-table td {
                    padding: 12px;
                    font-size: 14px;
                    color: #333;
                }
                .products-table td:first-child {
                    font-weight: 600;
                    color: #667eea;
                    text-align: center;
                    width: 50px;
                }
                .products-table input[type="text"],
                .products-table input[type="number"] {
                    width: 100%;
                    padding: 8px 12px;
                    border: 2px solid #e1e4e8;
                    border-radius: 6px;
                    font-size: 14px;
                    transition: all 0.3s;
                }
                .products-table input[type="text"]:focus,
                .products-table input[type="number"]:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                .barcode-status {
                    display: inline-block;
                    margin-left: 8px;
                    font-size: 13px;
                    font-weight: 500;
                }
                .form-actions {
                    margin-top: 25px;
                    padding-top: 20px;
                    border-top: 1px solid #e9ecef;
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    align-items: center;
                }
                .btn-submit {
                    background: #28a745;
                    color: #fff;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                }
                .btn-submit:hover {
                    background: #218838;
                    transform: translateY(-1px);
                }
                .btn-submit:active {
                    transform: translateY(0);
                }
                .btn-cancel {
                    background: #6c757d;
                    color: #fff;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                    text-decoration: none;
                    display: inline-block;
                }
                .btn-cancel:hover {
                    background: #5a6268;
                    transform: translateY(-1px);
                }
                .product-name {
                    font-weight: 500;
                    color: #2c3e50;
                }
                .sku-code {
                    font-family: 'Courier New', monospace;
                    color: #667eea;
                    font-weight: 500;
                }
            </style>
            <div class="modal-content-wrapper">
                <div class="modal-subtitle">
                    Phiếu <strong><?php echo htmlspecialchars($id); ?></strong>
                </div>
                <div id="error-message"></div>
                <div class="warning-box">
                    <span style="font-size:20px;">⚠️</span>
                    <div>
                        <strong>Lưu ý:</strong> Barcode phải là duy nhất, không được trùng với sản phẩm đã có trong hệ thống.
                    </div>
                </div>
                <form method="post" action="" id="approve-new-products-form">
                    <input type="hidden" name="confirm_approve" value="1" />
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tên sản phẩm</th>
                                <th>SKU</th>
                                <th>Barcode (bắt buộc)*</th>
                                <th>Tồn tối thiểu (min_stock)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($newItems as $idx => $item) {
                            $temp = is_array($item['temp']) ? $item['temp'] : [];
                            $sku = $temp['sku'] ?? ($item['sku'] ?? '');
                            $barcode = $temp['barcode'] ?? ($item['barcode'] ?? '');
                            $min = $temp['min_stock'] ?? 0;
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td class="product-name"><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                            <td class="sku-code"><?php echo htmlspecialchars($sku); ?></td>
                            <td>
                                <input type="text" 
                                       name="barcode[<?php echo $idx; ?>]" 
                                       value="<?php echo htmlspecialchars($barcode); ?>" 
                                       required 
                                       pattern="[A-Za-z0-9]+" 
                                       title="Barcode chỉ được chứa chữ cái và số"
                                       onblur="checkBarcodeDuplicate(this, <?php echo $idx; ?>)"
                                       placeholder="Nhập barcode..." />
                                <span id="barcode-status-<?php echo $idx; ?>" class="barcode-status"></span>
                            </td>
                            <td>
                                <input type="number" 
                                       name="min_stock[<?php echo $idx; ?>]" 
                                       value="<?php echo htmlspecialchars($min); ?>" 
                                       min="0" 
                                       placeholder="10" />
                            </td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <div class="form-actions">
                        <button type="submit" class="btn-submit" id="submit-btn">
                            <i class="fa-solid fa-check"></i> Xác nhận và Duyệt
                        </button>
                        <button type="button" class="btn-cancel" onclick="Swal.close()">
                            <i class="fa-solid fa-times"></i> Hủy
                        </button>
                    </div>
                </form>
            </div>
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
                        input.setCustomValidity('');
                        return;
                    }
                    
                    // Kiểm tra trùng trong form
                    const allBarcodeInputs = document.querySelectorAll('input[name^="barcode["]');
                    for (let i = 0; i < allBarcodeInputs.length; i++) {
                        if (allBarcodeInputs[i] !== input && allBarcodeInputs[i].value.trim() === barcode) {
                            if (statusSpan) statusSpan.innerHTML = '<span style="color:#dc3545;">❌ Trùng trong phiếu</span>';
                            input.setCustomValidity('Barcode bị trùng trong phiếu này');
                            return;
                        }
                    }
                    
                    // Kiểm tra trùng trong CSDL
                    if (statusSpan) statusSpan.innerHTML = '<span style="color:#6c757d;">⏳ Đang kiểm tra...</span>';
                    barcodeCheckInProgress[idx] = true;
                    
                    try {
                        const response = await fetch('/kltn/view/page/manage/receipts/get_barcode_or_batch.php?barcode=' + encodeURIComponent(barcode));
                        const data = await response.json();
                        
                        if (data.success && data.product && data.product._id) {
                            if (statusSpan) statusSpan.innerHTML = '<span style="color:#dc3545;font-weight:600;">❌ Đã tồn tại</span>';
                            input.setCustomValidity('Barcode đã tồn tại trong hệ thống');
                            showError('Barcode "' + barcode + '" đã tồn tại trong hệ thống. Vui lòng nhập barcode khác.');
                        } else {
                            if (statusSpan) statusSpan.innerHTML = '<span style="color:#28a745;font-weight:600;">✅ Hợp lệ</span>';
                            input.setCustomValidity('');
                        }
                    } catch (err) {
                        console.error('Lỗi kiểm tra barcode:', err);
                        if (statusSpan) statusSpan.innerHTML = '<span style="color:#ffc107;">⚠️ Không kiểm tra được</span>';
                    } finally {
                        delete barcodeCheckInProgress[idx];
                    }
                }
                
                // Validate trước khi submit
                const form = document.getElementById('approve-new-products-form');
                if (form) {
                    form.addEventListener('submit', function(e) {
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
                }
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
