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
    // --- GET: load danh sách phiếu xuất ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_exports') {
        header('Content-Type: application/json; charset=UTF-8');
        
        try {
            include_once(__DIR__ . '/../../../../model/connect.php');
            $db = (new Database())->getConnection();
            
            $sourceWarehouse = $_GET['source_warehouse'] ?? '';
            $destinationWarehouse = $_GET['destination_warehouse'] ?? '';
            
            error_log("=== GET EXPORTS REQUEST ===");
            error_log("Source warehouse: $sourceWarehouse");
            error_log("Destination warehouse: $destinationWarehouse");
            
            if (empty($sourceWarehouse) || empty($destinationWarehouse)) {
                echo json_encode(["success" => false, "message" => "Thiếu thông tin kho"]);
                exit;
            }
            
            // Lấy danh sách phiếu xuất có status=1 (Đã xuất kho) từ kho nguồn đến kho hiện tại
            $filter = [
                'transaction_type' => 'export', // Dùng transaction_type thay vì type
                'status' => 1, // Chỉ lấy phiếu đã xuất kho
                'inventory_deducted' => true,
                'warehouse_id' => $sourceWarehouse,
                'destination_warehouse_id' => $destinationWarehouse
            ];
            
            error_log("Query filter: " . json_encode($filter));
            
            $exports = $db->transactions->find($filter, [
                'sort' => ['created_at' => -1],
                'limit' => 50
            ])->toArray();
            
            error_log("Found " . count($exports) . " exports");
        } catch (\Exception $e) {
            error_log('Error loading exports: ' . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Lỗi kết nối database: " . $e->getMessage()]);
            exit;
        }
        
        $result = [];
        foreach ($exports as $exp) {
            // Đọc từ cả 'products' và 'details' (legacy)
            $products = $exp['products'] ?? $exp['details'] ?? [];
            
            $result[] = [
                '_id' => (string)$exp['_id'],
                'receipt_id' => $exp['receipt_id'] ?? $exp['transaction_id'] ?? '',
                'created_at_formatted' => isset($exp['created_at']) ? date('d/m/Y H:i', $exp['created_at']->toDateTime()->getTimestamp()) : '',
                'product_count' => count($products)
            ];
        }
        
        echo json_encode(["success" => true, "exports" => $result]);
        exit;
    }
    
    // --- GET: load chi tiết phiếu xuất ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_export_details') {
        header('Content-Type: application/json; charset=UTF-8');
        
        try {
            include_once(__DIR__ . '/../../../../model/connect.php');
            $db = (new Database())->getConnection();
            
            $exportId = $_GET['export_id'] ?? '';
            
            if (empty($exportId)) {
                echo json_encode(["success" => false, "message" => "Thiếu ID phiếu xuất"]);
                exit;
            }
            
            // Accept either ObjectId or string for export id
            $export = null;
            try {
                if ($exportId instanceof MongoDB\BSON\ObjectId) {
                    $export = $db->transactions->findOne(['_id' => $exportId]);
                } else {
                    $export = $db->transactions->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$exportId)]);
                }
            } catch (Throwable $e) {
                error_log('process.php:get_export_details - exportId->ObjectId failed: ' . $e->getMessage());
                $export = null;
            }
            
            if (!$export) {
                echo json_encode(["success" => false, "message" => "Không tìm thấy phiếu xuất"]);
                exit;
            }
            
            // Đọc từ 'details' ƯU TIÊN (có batches), sau đó mới 'products'
            $productList = $export['details'] ?? $export['products'] ?? [];
            
            error_log("=== GET_EXPORT_DETAILS ===");
            error_log("Export ID: " . $exportId);
            error_log("Has 'products': " . (isset($export['products']) ? 'YES' : 'NO'));
            error_log("Has 'details': " . (isset($export['details']) ? 'YES' : 'NO'));
            error_log("Reading from: " . (isset($export['details']) ? 'details' : 'products'));
            
            $products = [];
            foreach ($productList as $p) {
                // Lấy thông tin batch nếu có (từ FIFO export)
                $batches = [];
                error_log("Product: " . ($p['product_name'] ?? 'Unknown'));
                error_log("Has batches field: " . (isset($p['batches']) ? 'YES' : 'NO'));
                error_log("Batches type: " . (isset($p['batches']) ? gettype($p['batches']) : 'NULL'));
                
                // MongoDB có thể trả về BSON array, cần convert
                $batchesData = $p['batches'] ?? null;
                if ($batchesData instanceof MongoDB\Model\BSONArray) {
                    $batchesData = iterator_to_array($batchesData);
                    error_log("Converted BSON array to PHP array");
                }
                
                error_log("Is array after convert: " . (is_array($batchesData) ? 'YES' : 'NO'));
                error_log("Batches count: " . (is_array($batchesData) ? count($batchesData) : 0));
                
                if ($batchesData && is_array($batchesData) && count($batchesData) > 0) {
                    error_log("Processing " . count($batchesData) . " batches...");
                    foreach ($batchesData as $idx => $b) {
                        error_log("Batch #" . $idx . ": " . json_encode($b));
                        
                        // Format import_date - có thể là string hoặc UTCDateTime
                        $importDate = '';
                        if (isset($b['import_date'])) {
                            if (is_object($b['import_date']) && method_exists($b['import_date'], 'toDateTime')) {
                                $importDate = date('d/m/Y', $b['import_date']->toDateTime()->getTimestamp());
                            } elseif (is_string($b['import_date'])) {
                                // Nếu đã là string (YYYY-MM-DD), convert sang d/m/Y
                                $timestamp = strtotime($b['import_date']);
                                $importDate = $timestamp ? date('d/m/Y', $timestamp) : $b['import_date'];
                            }
                        }
                        
                        // ⭐ Lấy thông tin vị trí từ source_location
                        $location = $b['source_location'] ?? null;
                        $locationText = '';
                        if ($location && is_array($location)) {
                            $zone = $location['zone_id'] ?? '';
                            $rack = $location['rack_id'] ?? '';
                            $bin = $location['bin_id'] ?? '';
                            $locationText = ($zone || $rack || $bin) ? "$zone-$rack-$bin" : '';
                        }
                        
                        $batches[] = [
                            'batch_code' => $b['batch_code'] ?? '',
                            'quantity' => $b['quantity'] ?? 0,
                            'unit_price' => $b['unit_price'] ?? 0,
                            'import_date' => $importDate,
                            'source_location' => $location, // ⭐ Thêm vị trí đầy đủ
                            'location_text' => $locationText // ⭐ Text hiển thị (Z1-R1-B1)
                        ];
                        error_log("Added batch to array: " . json_encode($batches[count($batches) - 1]));
                    }
                } else {
                    error_log("Batches field NOT valid or empty!");
                }
                
                // ⭐ Lấy unit_price từ batch (CHỈ cho transfer, purchase thì user tự nhập)
                // Đây là API cho phiếu xuất → luôn là transfer
                $unitPrice = $p['unit_price'] ?? 0;
                if (count($batches) > 0 && isset($batches[0]['unit_price']) && $batches[0]['unit_price'] > 0) {
                    $unitPrice = $batches[0]['unit_price'];
                }
                
                $products[] = [
                    'product_id' => (string)$p['product_id'],
                    'product_name' => $p['product_name'] ?? '',
                    'quantity' => $p['quantity'] ?? 0,
                    'unit' => $p['unit'] ?? '',
                    'unit_price' => $unitPrice, // ⭐ Giá từ batch (cho transfer)
                    'batches' => $batches // Thêm thông tin lô hàng FIFO
                ];
            }
        } catch (\Exception $e) {
            error_log('Error loading export details: ' . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Lỗi: " . $e->getMessage()]);
            exit;
        }
        
        echo json_encode([
            "success" => true,
            "export" => [
                '_id' => (string)$export['_id'],
                'receipt_id' => $export['receipt_id'] ?? $export['transaction_id'] ?? '',
                'created_at_formatted' => isset($export['created_at']) ? date('d/m/Y H:i', $export['created_at']->toDateTime()->getTimestamp()) : '',
                'products' => $products
            ]
        ]);
        exit;
    }
    
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
            $packageDimensions = $product['package_dimensions'] ?? [];
            $packageWeight = $product['package_weight'] ?? 0;
            $volumePerUnit = $product['volume_per_unit'] ?? 0;
            // Lấy thông tin nhà cung cấp nếu có để frontend kiểm tra
            $supplierId = $product['supplier_id'] ?? ($product['supplierId'] ?? null);
            $supplierName = $product['supplier_name'] ?? ($product['supplierName'] ?? null);
            
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
                    "package_dimensions" => $packageDimensions,
                    "package_weight" => $packageWeight,
                    "volume_per_unit" => $volumePerUnit,
                    "purchase_price" => $product['purchase_price'] ?? 0,
                    // Trả về thông tin NCC để hạn chế quét sai nhà cung cấp
                    "supplier_id" => $supplierId,
                    "supplier_name" => $supplierName
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
        $export_id = $_POST['export_id'] ?? null; // Lấy export_id nếu nhập từ kho nguồn
        $note = $_POST['note'] ?? null;
        
        // ✅ VALIDATION: Kiểm tra thông tin bắt buộc
        $errors = [];
        
        // Kiểm tra type
        if (empty($type)) {
            $errors[] = "Thiếu loại phiếu nhập";
        }
        
        // Kiểm tra warehouse_id
        if (empty($warehouse_id)) {
            $errors[] = "Thiếu thông tin kho";
        }
        
        // Kiểm tra supplier_id (bắt buộc cho purchase, optional cho transfer)
        if ($type === 'purchase' && empty($supplier_id)) {
            $errors[] = "Vui lòng chọn nhà cung cấp";
        }
        
        // Kiểm tra danh sách sản phẩm
        if (!isset($_POST['products']) || !is_array($_POST['products']) || count($_POST['products']) === 0) {
            $errors[] = "Vui lòng thêm ít nhất một sản phẩm";
        }
        
        // Nếu có lỗi validation, trả về thông báo
        if (!empty($errors)) {
            $_SESSION['flash_receipt_error'] = implode(', ', $errors);
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(["success" => false, "message" => implode('; ', $errors)]);
            } else {
                echo "<script>alert('" . addslashes(implode('\\n', $errors)) . "'); window.history.back();</script>";
            }
            exit;
        }
        
        // ⭐ DEBUG: Log các giá trị quan trọng
        error_log("📝 Receipt Type: " . ($type ?? 'NULL'));
        error_log("📝 Warehouse ID: " . ($warehouse_id ?? 'NULL'));
        error_log("📝 Supplier ID: " . ($supplier_id ?? 'NULL'));
        error_log("📝 Source Warehouse ID: " . ($source_warehouse_id ?? 'NULL'));
        error_log("📝 Export ID: " . ($export_id ?? 'NULL'));
        
        // ⭐ Nếu source_warehouse_id null nhưng có export_id, lấy từ phiếu xuất
        if (empty($source_warehouse_id) && !empty($export_id) && $type === 'transfer') {
            try {
                include_once(__DIR__ . '/../../../../model/connect.php');
                $dbTemp = (new Database())->getConnection();
                $exportDoc = null;
                try {
                    if ($export_id instanceof MongoDB\BSON\ObjectId) {
                        $exportDoc = $dbTemp->transactions->findOne(['_id' => $export_id]);
                    } elseif (!empty($export_id)) {
                        $exportDoc = $dbTemp->transactions->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$export_id)]);
                    }
                } catch (Throwable $e) {
                    error_log('process.php:auto-detect-source_warehouse_id - exportId conversion failed: ' . $e->getMessage());
                    $exportDoc = null;
                }
                if ($exportDoc) {
                    $source_warehouse_id = $exportDoc['warehouse_id'] ?? null; // Kho xuất = kho nguồn
                    error_log("✅ Auto-detected source_warehouse_id from export: $source_warehouse_id");
                }
            } catch (\Exception $e) {
                error_log("⚠️ Cannot fetch source_warehouse_id from export: " . $e->getMessage());
            }
        }

        $details = [];
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            foreach ($_POST['products'] as $p) {
                // Support either product_id (legacy) or sku (new) from frontend
                $productId = '';
                $lookupProductName = '';
                        // Allow temporary/new products marked by frontend with is_new flag
                        $isNewTemp = !empty($p['is_new']);
                        if (!empty($p['product_id'])) {
                            $productId = $p['product_id'];
                        } elseif (!empty($p['sku'])) {
                            if ($isNewTemp) {
                                // For new temporary products, accept provided sku/name without resolving to DB
                                $productId = $p['product_id'] ?? ('new_' . uniqid());
                                $lookupProductName = $p['product_name'] ?? '';
                            } else {
                                // Try to resolve SKU -> product_id using CProduct
                                if (!class_exists('CProduct')) include_once(__DIR__ . '/../../../../controller/cProduct.php');
                                $cp = new CProduct();
                                $found = $cp->getProductBySKU($p['sku']);
                                if ($found && !empty($found['_id'])) {
                                    $productId = $found['_id'];
                                    $lookupProductName = $found['product_name'] ?? ($found['name'] ?? '');
                                } else {
                                    $_SESSION['flash_receipt_error'] = "Không tìm thấy sản phẩm với SKU: " . ($p['sku'] ?? '');
                                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                                    if ($isAjax) {
                                        header('Content-Type: application/json; charset=UTF-8');
                                        echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm với SKU: " . ($p['sku'] ?? '')]);
                                    } else {
                                        echo "<script>alert('Không tìm thấy sản phẩm với SKU: " . addslashes($p['sku'] ?? '') . "'); window.history.back();</script>";
                                    }
                                    exit;
                                }
                            }
                        }

                if (empty($productId)) continue;

                $qty = isset($p['quantity']) ? (float)$p['quantity'] : 0;
                $price = isset($p['price']) ? (float)$p['price'] : 0.0;

                // ✅ Validation: Kiểm tra số lượng và giá
                if ($qty <= 0) {
                    $productName = $p['product_name'] ?? $lookupProductName ?? 'Unknown';
                    $_SESSION['flash_receipt_error'] = "Số lượng của sản phẩm '$productName' phải lớn hơn 0";
                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode(["success" => false, "message" => "Số lượng của sản phẩm '$productName' phải lớn hơn 0"]);
                    } else {
                        echo "<script>alert('Số lượng của sản phẩm \"$productName\" phải lớn hơn 0'); window.history.back();</script>";
                    }
                    exit;
                }

                if ($price <= 0) {
                    $productName = $p['product_name'] ?? $lookupProductName ?? 'Unknown';
                    $_SESSION['flash_receipt_error'] = "Giá nhập của sản phẩm '$productName' phải lớn hơn 0";
                    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode(["success" => false, "message" => "Giá nhập của sản phẩm '$productName' phải lớn hơn 0"]);
                    } else {
                        echo "<script>alert('Giá nhập của sản phẩm \"$productName\" phải lớn hơn 0'); window.history.back();</script>";
                    }
                    exit;
                }

                // Resolve SKU to store in DB as well when possible
                $skuVal = $p['sku'] ?? '';
                if (empty($skuVal) && isset($found) && is_array($found)) {
                    $skuVal = $found['sku'] ?? '';
                }
                // If still empty but we have product_id, attempt to fetch product info to get SKU
                if (empty($skuVal) && !empty($productId)) {
                    if (!class_exists('CProduct')) include_once(__DIR__ . '/../../../../controller/cProduct.php');
                    $cp2 = new CProduct();
                    $prodInfo = $cp2->getProductById($productId);
                    if ($prodInfo && is_array($prodInfo)) {
                        $skuVal = $prodInfo['sku'] ?? '';
                        if (empty($lookupProductName)) $lookupProductName = $prodInfo['product_name'] ?? ($prodInfo['name'] ?? '');
                    }
                }

                $detail = [
                    'product_id' => $productId,
                    'sku' => $skuVal,
                    'product_name' => $p['product_name'] ?? $lookupProductName ?? '',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit' => $p['unit'] ?? '' // thêm đơn vị tính nếu có
                ];

                // Nếu frontend gửi sản phẩm tạm (is_new), giữ nguyên các trường tạm để không lookup DB
                if (!empty($p['is_new'])) {
                    $detail['is_new'] = true;

                    // Normalize package_dimensions (may be sent as JSON string or array)
                    $packageDimensions = [];
                    if (!empty($p['package_dimensions'])) {
                        if (is_string($p['package_dimensions'])) {
                            $decoded = json_decode($p['package_dimensions'], true);
                            $packageDimensions = is_array($decoded) ? $decoded : [];
                        } elseif (is_array($p['package_dimensions'])) {
                            $packageDimensions = $p['package_dimensions'];
                        }
                    }

                    // Normalize conversionUnits (may be JSON)
                    $conversionUnits = [];
                    if (!empty($p['conversionUnits'])) {
                        if (is_string($p['conversionUnits'])) {
                            $decoded = json_decode($p['conversionUnits'], true);
                            $conversionUnits = is_array($decoded) ? $decoded : [];
                        } elseif (is_array($p['conversionUnits'])) {
                            $conversionUnits = $p['conversionUnits'];
                        }
                    }

                    // Preserve as many manual-add fields as possible into temp and top-level detail
                    $detail['temp'] = [
                        'sku' => $p['sku'] ?? '',
                        'barcode' => $p['barcode'] ?? '',
                        'note' => $p['note'] ?? '',
                        'product_name' => $p['product_name'] ?? '',
                        'model' => $p['model'] ?? '',
                        'description' => $p['description'] ?? '',
                        'purchase_price' => isset($p['purchase_price']) ? (float)$p['purchase_price'] : ($p['purchase_price'] ?? 0),
                        'min_stock' => isset($p['min_stock']) ? (int)$p['min_stock'] : ($p['min_stock'] ?? 0),
                        'baseUnit' => $p['baseUnit'] ?? ($p['base_unit'] ?? ''),
                        'conversionUnits' => $conversionUnits,
                        'package_dimensions' => $packageDimensions,
                        'package_weight' => isset($p['package_weight']) ? (float)$p['package_weight'] : ($p['package_weight'] ?? 0),
                        'volume_per_unit' => isset($p['volume_per_unit']) ? (float)$p['volume_per_unit'] : ($p['volume_per_unit'] ?? 0),
                        'status' => isset($p['status']) ? (int)$p['status'] : ($p['status'] ?? 1),
                        // Additional fields copied from manual-add UI if present
                        'category' => isset($p['category']) && is_array($p['category']) ? $p['category'] : (isset($p['category_id']) ? ['id' => $p['category_id'], 'name' => ($p['category_name'] ?? '')] : []),
                        'supplier' => isset($p['supplier']) && is_array($p['supplier']) ? $p['supplier'] : (isset($p['supplier_id']) ? ['id' => $p['supplier_id'], 'name' => ($p['supplier_name'] ?? '')] : []),
                        'stackable' => isset($p['stackable']) ? (bool)$p['stackable'] : ($p['stackable'] ?? false),
                        'max_stack_height' => isset($p['max_stack_height']) ? (int)$p['max_stack_height'] : ($p['max_stack_height'] ?? 0),
                        'image' => $p['image'] ?? ''
                    ];

                    // Also keep some of those values at top-level detail for easier access elsewhere
                    if (!empty($detail['temp']['package_dimensions'])) $detail['package_dimensions'] = $detail['temp']['package_dimensions'];
                    if (isset($detail['temp']['package_weight'])) $detail['package_weight'] = $detail['temp']['package_weight'];
                    if (isset($detail['temp']['volume_per_unit'])) $detail['volume_per_unit'] = $detail['temp']['volume_per_unit'];
                    if (!empty($detail['temp']['model'])) $detail['model'] = $detail['temp']['model'];
                    if (!empty($detail['temp']['description'])) $detail['description'] = $detail['temp']['description'];
                    if (!empty($detail['temp']['purchase_price'])) $detail['unit_price'] = $detail['temp']['purchase_price'];
                    if (!empty($detail['temp']['min_stock'])) $detail['min_stock'] = (int)$detail['temp']['min_stock'];
                    if (!empty($detail['temp']['baseUnit'])) $detail['baseUnit'] = $detail['temp']['baseUnit'];
                    if (!empty($detail['temp']['conversionUnits'])) $detail['conversionUnits'] = $detail['temp']['conversionUnits'];
                    // Mirror additional fields to top-level detail so detail view can read them directly
                    if (isset($detail['temp']['stackable'])) $detail['stackable'] = $detail['temp']['stackable'];
                    if (!empty($detail['temp']['max_stack_height'])) $detail['max_stack_height'] = $detail['temp']['max_stack_height'];
                    if (!empty($detail['temp']['image'])) $detail['image'] = $detail['temp']['image'];
                    if (!empty($detail['temp']['category'])) $detail['category'] = $detail['temp']['category'];

                    // Ensure each temp item is explicitly associated with the chosen supplier
                    if (!empty($supplier_id)) {
                        $detail['supplier_id'] = $supplier_id;
                        if (empty($detail['temp']['supplier']) || !is_array($detail['temp']['supplier'])) {
                            // Nếu chưa có supplier từ form, lấy từ supplier_id của phiếu và tra tên từ DB
                            $supplierName = $p['supplier_name'] ?? '';
                            
                            // Nếu supplier_name rỗng hoặc trùng với supplier_id, tra database
                            if (empty($supplierName) || $supplierName === $supplier_id) {
                                try {
                                    include_once(__DIR__ . '/../../../../model/connect.php');
                                    $db = (new Database())->getConnection();
                                    $supplierDoc = $db->suppliers->findOne(['supplier_id' => $supplier_id]);
                                    if ($supplierDoc) {
                                        $supplierName = $supplierDoc['supplier_name'] ?? $supplierDoc['name'] ?? $supplier_id;
                                    }
                                } catch (\Exception $e) {
                                    error_log('Error fetching supplier name: ' . $e->getMessage());
                                }
                            }
                            
                            $detail['temp']['supplier_id'] = $supplier_id;
                            $detail['temp']['supplier_name'] = $supplierName;
                            $detail['temp']['supplier'] = [
                                'id' => $supplier_id,
                                'name' => $supplierName
                            ];
                        }
                    }
                }
                
                // ⭐ Thêm batch info nếu có (cho transfer)
                if (!empty($p['batches']) && $type === 'transfer') {
                    // batches được gửi dưới dạng JSON string
                    $batchesData = json_decode($p['batches'], true);
                    if ($batchesData && is_array($batchesData)) {
                        $detail['batches'] = $batchesData;
                        error_log("✅ Added batches to detail for product {$p['product_id']}: " . count($batchesData) . " batches");
                    }
                }
                
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
            'export_id' => $export_id, // Thêm export_id vào payload
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
            $receiptId = $result[1] ?? null;
            
            // ⭐ CHÚ Ý: Không tạo batch ở đây nữa!
            // Batch sẽ được tạo khi APPROVE phiếu (qua cReceipt->approveReceipt)
            // Phiếu xuất sẽ được cập nhật khi HOÀN TẤT xếp kho (qua locate/process.php complete_receipt)
            
            $_SESSION['flash_receipt'] = 'Tạo phiếu thành công.';
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(["success" => true, "message" => 'Tạo phiếu thành công.', "receipt_id" => $receiptId]);
            } else {
                echo "<script>alert('Tạo phiếu thành công!'); window.location.href = '../index.php?page=receipts';</script>";
            }
            exit;
        } else {
            $msg = is_array($result) ? ($result[1] ?? 'Lưu phiếu thất bại') : 'Lưu phiếu thất bại';
            $_SESSION['flash_receipt_error'] = $msg;
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(["success" => false, "message" => $msg]);
            } else {
                echo "<script>alert('" . addslashes($msg) . "'); window.location.href = '../../index.php';</script>";
            }
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
