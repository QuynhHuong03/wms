<?php
// Force JSON responses and suppress HTML error output which breaks AJAX
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
// Start output buffering to capture any accidental output
if (ob_get_level() === 0) ob_start();

include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
include_once(__DIR__ . "/../../../../../controller/clocation.php");
include_once(__DIR__ . "/../../../../../model/mLocation.php");
include_once(__DIR__ . "/../../../../../model/mInventory.php");
include_once(__DIR__ . "/../../../../../model/mReceipt.php");
include_once(__DIR__ . "/../../../../../controller/cProduct.php");
if (session_status() === PHP_SESSION_NONE) @session_start();

if (!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    // Fallback for form-encoded
    $data = $_POST;
}

$action = $data['action'] ?? '';
$id = $data['id'] ?? '';

try {
    $c = new CReceipt();
    switch ($action) {
        case 'save_qty': {
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã phiếu']); break; }
            // Accept either {items:[{product_id, qty_to_locate}]} or {map:{pid:qty,...}}
            $map = [];
            if (!empty($data['map']) && is_array($data['map'])) {
                $map = $data['map'];
            } elseif (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $it) {
                    if (!empty($it['product_id'])) {
                        $map[$it['product_id']] = (int)($it['qty_to_locate'] ?? 0);
                    }
                }
            }
            if (empty($map)) { echo json_encode(['success'=>false,'message'=>'Không có dữ liệu cần lưu']); break; }
            $res = $c->saveLocateQuantities($id, $map);
            echo json_encode($res);
            break;
        }
    case 'allocate_to_bin': {
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã phiếu']); break; }
            $warehouseId = $data['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? '');
            if (!$warehouseId) {
                // Fallback: get warehouse from the receipt itself (convert BSON document to array)
                $rc = $c->getReceiptById($id);
                if ($rc) {
                    $arr = is_array($rc) ? $rc : json_decode(json_encode($rc), true);
                    if (!empty($arr['warehouse_id'])) {
                        $warehouseId = $arr['warehouse_id'];
                    }
                }
            }
            if (!$warehouseId) { echo json_encode(['success'=>false,'message'=>'Không xác định được kho']); break; }

            $zone_id = trim($data['zone_id'] ?? '');
            $rack_id = trim($data['rack_id'] ?? '');
            $bin_id  = trim($data['bin_id'] ?? '');
            $product_id = trim($data['product_id'] ?? '');
            $qtyInput = (int)($data['qty'] ?? 0); // quantity in selected input unit (may be non-base)
            $inputUnit = trim($data['input_unit'] ?? '');
            $qty = $qtyInput; // keep old var name for backward compatibility in some branches
            $originalQty = $qtyInput;
            $bin_status = ''; // Will be auto-calculated based on capacity
            if (!$zone_id || !$rack_id || !$bin_id || !$product_id) {
                echo json_encode(['success'=>false,'message'=>'Thiếu thông tin Zone/Rack/Bin hoặc sản phẩm']);
                break;
            }
            if ($qtyInput < 0) {
                echo json_encode(['success'=>false,'message'=>'Số lượng phải lớn hơn hoặc bằng 0']);
                break;
            }

            // Validate against remaining quantity in the receipt for this product
            $receipt = $c->getReceiptById($id);
            if (!$receipt) {
                echo json_encode(['success'=>false,'message'=>'Không tìm thấy phiếu']);
                break;
            }
            $arrRc = is_array($receipt) ? $receipt : json_decode(json_encode($receipt), true);
            $ordered = 0; $detailUnit = '';
            foreach (($arrRc['details'] ?? []) as $d) {
                if (!empty($d['product_id']) && $d['product_id'] === $product_id) {
                    $ordered = (int)($d['quantity'] ?? $d['qty'] ?? 0);
                    $detailUnit = trim($d['unit'] ?? '');
                    break;
                }
            }
            if ($ordered <= 0) {
                echo json_encode(['success'=>false,'message'=>'Sản phẩm không tồn tại trong phiếu hoặc số lượng đặt là 0']);
                break;
            }
            // We will compute remaining in base units later after resolving base unit and factor.
            // Load product info for conversions (derive base unit from product, not from receipt unit)
            $productInfo = null; $productName = ''; $convUnits = []; $baseUnit = '';
            try { $cp = new CProduct(); $productInfo = $cp->getProductById($product_id); } catch (\Throwable $e) {}
            if (is_array($productInfo)) {
                $productName = $productInfo['product_name'] ?? ($productInfo['name'] ?? '');
                $convUnits = $productInfo['conversionUnits'] ?? [];
                $baseUnit = trim($productInfo['unit'] ?? ($productInfo['baseUnit'] ?? ''));
                // If baseUnit appears in conversions with factor>1, it's likely a pack unit; use 'cái' as true base
                if ($baseUnit) {
                    foreach ($convUnits as $cu) {
                        $u = trim($cu['unit'] ?? ''); $f = (int)($cu['factor'] ?? 0);
                        if ($u && strcasecmp($u, $baseUnit) === 0 && $f > 1) { $baseUnit = 'cái'; break; }
                    }
                }
            }
            if (!$baseUnit) { $baseUnit = 'cái'; }

            // Resolve factor for input unit
            $factor = 1;
            if ($inputUnit !== '') {
                if ($baseUnit !== '' && strcasecmp($inputUnit, $baseUnit) === 0) {
                    $factor = 1;
                } else {
                    foreach ($convUnits as $cu) {
                        $u = trim($cu['unit'] ?? '');
                        if ($u !== '' && strcasecmp($u, $inputUnit) === 0) {
                            $factor = max(1, (int)($cu['factor'] ?? 1));
                            break;
                        }
                    }
                }
            }

            // Convert requested qty to base units
            $qtyBase = (int)$qtyInput * (int)$factor;
            $originalQtyBase = $qtyBase;

            // Convert ordered to base units and compute remaining in base units
            $factorOrdered = 1;
            if ($detailUnit !== '' && $baseUnit !== '' && strcasecmp($detailUnit, $baseUnit) !== 0) {
                foreach ($convUnits as $cu) {
                    $u = trim($cu['unit'] ?? '');
                    if ($u !== '' && strcasecmp($u, $detailUnit) === 0) {
                        $factorOrdered = max(1, (int)($cu['factor'] ?? 1));
                        break;
                    }
                }
            }
            $orderedBase = (int)$ordered * (int)$factorOrdered;
            $allocatedSum = 0;
            foreach (($arrRc['allocations'] ?? []) as $a) {
                if (!empty($a['product_id']) && $a['product_id'] === $product_id) {
                    $allocatedSum += (int)($a['qty'] ?? 0); // allocations are stored in base units
                }
            }
            $remainingBase = max(0, $orderedBase - $allocatedSum);
            if ($qtyBase > $remainingBase) {
                echo json_encode(['success'=>false,'message'=>'Số lượng phân bổ vượt quá số lượng chưa xếp ('. $remainingBase .')']);
                break;
            }

            $m = new MLocation();
            // Fetch bin to respect capacity and support accumulation
            $binDoc = $m->getBinFromWarehouse($warehouseId, $zone_id, $rack_id, ['bin_id'=>$bin_id]);
            $capacity = 0; $current = 0; $b = null;
            if ($binDoc && !empty($binDoc['bin'])) {
                $b = $binDoc['bin'];
                $capacity = (int)($b['capacity'] ?? 0);
                $current = (int)($b['quantity'] ?? ($b['current_load'] ?? 0));
            }
            // Adjust qty so that current + qty does not exceed capacity
            if ($capacity > 0 && ($current + $qtyBase) > $capacity) {
                $qtyBase = max(0, $capacity - $current);
            }
            // Nếu người dùng yêu cầu >0 nhưng không còn chỗ -> báo đầy; nếu yêu cầu 0 thì vẫn cho cập nhật trạng thái
            if ($capacity > 0 && $qtyBase <= 0 && $current >= $capacity && $originalQtyBase > 0) {
                echo json_encode(['success'=>false,'message'=>'Bin đã đầy, không thể xếp thêm']);
                break;
            }

            // Auto derive bin status if not provided or set to 'auto'
            if ($bin_status === '' || strtolower($bin_status) === 'auto') {
                if ($qtyBase <= 0) $bin_status = 'empty';
                elseif ($capacity > 0 && ($current + $qtyBase) >= $capacity) $bin_status = 'full';
                else $bin_status = 'partial';
            }

            // Update bin status/qty/product
            // For storing product info on bin: include id, name, conversionUnits (fetched above)
            // Chuẩn bị dữ liệu cập nhật bin
            $upd = [ 'status' => $bin_status ?: 'partial' ];
            if ($qtyBase > 0) {
                $upd['product'] = [
                    'id' => $product_id,
                    'name' => $productName,
                    'conversionUnits' => $convUnits
                ];
                // Cộng dồn số lượng trong bin
                $upd['quantity'] = $current + $qtyBase;
            }
            $ok = $m->updateBinInWarehouse($warehouseId, $zone_id, $rack_id, $bin_id, $upd);
            
            // Update bin capacity based on product volume
            if ($ok && $qtyBase > 0 && $productInfo) {
                // Get dimensions based on input unit
                // If inputUnit is "thùng" (box), use conversion unit dimensions
                // If inputUnit is "cái" (piece), use base product dimensions
                $pDims = [];
                $unitType = $baseUnit; // Default to base unit
                $dimensionsFound = false;
                
                error_log("=== DIMENSION LOOKUP DEBUG ===");
                error_log("Input unit: $inputUnit");
                error_log("Base unit: $baseUnit");
                error_log("Available conversion units: " . print_r(array_column($convUnits, 'unit'), true));
                
                // Check if inputUnit is a conversion unit (thùng, hộp, etc)
                if ($inputUnit && strcasecmp($inputUnit, $baseUnit) !== 0) {
                    // User is allocating in a conversion unit (e.g., thùng)
                    error_log("Looking for conversion unit dimensions for: $inputUnit");
                    foreach ($convUnits as $cu) {
                        $cuUnit = trim($cu['unit'] ?? '');
                        error_log("Checking conversion unit: $cuUnit");
                        if ($cuUnit && strcasecmp($cuUnit, $inputUnit) === 0) {
                            // Found the conversion unit, check if it has dimensions
                            if (isset($cu['dimensions']) && is_array($cu['dimensions'])) {
                                $pDims = $cu['dimensions'];
                                $unitType = $cuUnit;
                                $dimensionsFound = true;
                                error_log("✓ Found dimensions in conversionUnits for '$cuUnit': " . print_r($pDims, true));
                            } else {
                                error_log("✗ Conversion unit '$cuUnit' found but no dimensions field");
                            }
                            break;
                        }
                    }
                }
                
                // Fallback to base product dimensions if no conversion unit dimensions found
                if (!$dimensionsFound) {
                    error_log("Using base product dimensions (inputUnit='$inputUnit' not found or no dimensions)");
                    
                    // ✅ Try multiple dimension sources in order of priority
                    if (isset($productInfo['package_dimensions']) && is_array($productInfo['package_dimensions'])) {
                        $pDims = $productInfo['package_dimensions'];
                        error_log("✓ Using package_dimensions: " . print_r($pDims, true));
                    } elseif (isset($productInfo['dimensions']) && is_array($productInfo['dimensions'])) {
                        $pDims = $productInfo['dimensions'];
                        error_log("✓ Using dimensions: " . print_r($pDims, true));
                    } else {
                        // ✅ Try direct width/height/depth fields
                        $directWidth = (float)($productInfo['width'] ?? $productInfo['length'] ?? 0);
                        $directDepth = (float)($productInfo['depth'] ?? $productInfo['width'] ?? 0);
                        $directHeight = (float)($productInfo['height'] ?? 0);
                        
                        if ($directWidth > 0 || $directDepth > 0 || $directHeight > 0) {
                            $pDims = [
                                'width' => $directWidth,
                                'depth' => $directDepth,
                                'height' => $directHeight
                            ];
                            error_log("✓ Using direct dimension fields: " . print_r($pDims, true));
                        } else {
                            error_log("✗ No dimensions found in product - checked package_dimensions, dimensions, and direct fields");
                        }
                    }
                    $unitType = $baseUnit;
                }
                
                $pWidth = (float)($pDims['width'] ?? 0);
                $pDepth = (float)($pDims['depth'] ?? 0);
                $pHeight = (float)($pDims['height'] ?? 0);
                $pVolume = $pWidth * $pDepth * $pHeight;
                
                error_log("Final dimensions - Width: $pWidth, Depth: $pDepth, Height: $pHeight, Volume: $pVolume, Unit: $unitType");
                error_log("=== END DEBUG ===");
                
                // Validate: Check if product/unit dimensions fit in bin
                if ($pWidth > 0 && $pDepth > 0 && $pHeight > 0 && $b && isset($b['dimensions'])) {
                    $bDims = $b['dimensions'];
                    $bWidth = (float)($bDims['width'] ?? 0);
                    $bDepth = (float)($bDims['depth'] ?? 0);
                    $bHeight = (float)($bDims['height'] ?? 0);
                    
                    if ($bWidth > 0 && $bDepth > 0 && $bHeight > 0) {
                        // Sort dimensions to check if product fits in any orientation
                        $pSorted = [$pWidth, $pDepth, $pHeight];
                        $bSorted = [$bWidth, $bDepth, $bHeight];
                        sort($pSorted);
                        sort($bSorted);
                        
                        if ($pSorted[0] > $bSorted[0] || $pSorted[1] > $bSorted[1] || $pSorted[2] > $bSorted[2]) {
                            error_log("Dimension validation failed: Product {$unitType} ({$pWidth}×{$pDepth}×{$pHeight}) does not fit in bin ({$bWidth}×{$bDepth}×{$bHeight})");
                            echo json_encode([
                                'success' => false, 
                                'message' => "Kích thước {$unitType} ({$pWidth}×{$pDepth}×{$pHeight} cm) quá lớn so với bin ({$bWidth}×{$bDepth}×{$bHeight} cm). Vui lòng chọn bin khác hoặc đơn vị nhỏ hơn."
                            ]);
                            exit; // Stop execution immediately
                        }
                    }
                }
                
                // Determine if adding or removing based on receipt type
                // 'purchase' = nhập kho (import) = tăng capacity
                // 'transfer' = xuất kho (export) = giảm capacity
                // 'return' = trả hàng = tăng capacity (hàng quay lại kho)
                $receiptType = $arrRc['type'] ?? 'purchase';
                $isAdding = ($receiptType === 'purchase' || $receiptType === 'return');
                
                error_log("Allocate - Receipt type: $receiptType, Input unit: $inputUnit, Unit type: $unitType, Dimensions: W=$pWidth, D=$pDepth, H=$pHeight, Volume=$pVolume, Qty input: $qtyInput, Qty base: $qtyBase, Factor: $factor, isAdding=" . ($isAdding ? 'true' : 'false'));
                
                error_log("🔍 CAPACITY UPDATE - warehouse_id: $warehouseId, zone: $zone_id, rack: $rack_id, bin: $bin_id");
                
                if ($pVolume > 0) {
                    // Use qtyInput (số lượng thực tế user nhập) instead of qtyBase
                    // Vì 1 thùng chiếm volume của 1 thùng, không phải volume của N cái
                    $capacityUpdated = $m->updateBinCapacity($warehouseId, $zone_id, $rack_id, $bin_id, $pVolume, $qtyInput, $isAdding);
                    error_log("✅ Bin capacity update result for $receiptType receipt: " . ($capacityUpdated ? 'success' : 'failed'));
                } else {
                    error_log("⚠️ WARNING: Product volume is 0 - dimensions not set for product: $product_id (unit: $unitType)");
                    error_log("⚠️ Product info: " . json_encode($productInfo));
                    error_log("⚠️ Dimensions checked: package_dimensions=" . json_encode($productInfo['package_dimensions'] ?? null) . ", dimensions=" . json_encode($productInfo['dimensions'] ?? null));
                    error_log("⚠️ Conversion units: " . json_encode($convUnits));
                    
                    // ✅ FALLBACK: Sử dụng default dimensions nếu không có
                    // Giả sử mỗi sản phẩm chiếm 10x10x10 cm
                    $defaultVolume = 1000; // 10x10x10 = 1000 cm³
                    error_log("⚠️ Using default volume: $defaultVolume cm³ for product $product_id");
                    $capacityUpdated = $m->updateBinCapacity($warehouseId, $zone_id, $rack_id, $bin_id, $defaultVolume, $qtyInput, $isAdding);
                    error_log("✅ Bin capacity update with default volume: " . ($capacityUpdated ? 'success' : 'failed'));
                }
            }

            // Append allocation record to receipt khi qty > 0
            if ($receipt && $qtyBase > 0) {
                $allocs = $arrRc['allocations'] ?? [];
                
                // Lưu received_at để dùng khi hoàn tất
                $rcCreatedAt = null;
                if (isset($receipt['created_at']) && $receipt['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
                    $rcCreatedAt = $receipt['created_at'];
                } elseif (!empty($arrRc['created_at'])) {
                    $raw = $arrRc['created_at'];
                    if (is_array($raw) && isset($raw['$date'])) { $raw = $raw['$date']; }
                    try {
                        $dt = new DateTime(is_string($raw) ? $raw : (string)$raw);
                        $rcCreatedAt = new MongoDB\BSON\UTCDateTime($dt->getTimestamp()*1000);
                    } catch (Throwable $e) {
                        $rcCreatedAt = null;
                    }
                }
                if (!$rcCreatedAt) { $rcCreatedAt = new MongoDB\BSON\UTCDateTime(); }
                
                $allocs[] = [
                    'time' => date(DATE_ATOM),
                    'warehouse_id' => $warehouseId,
                    'zone_id' => $zone_id,
                    'rack_id' => $rack_id,
                    'bin_id' => $bin_id,
                    'product_id' => $product_id,
                    'qty' => $qtyBase,
                    'bin_status' => $bin_status,
                    'input_unit' => $inputUnit,
                    'input_qty' => $qtyInput,
                    'base_unit' => $baseUnit,
                    'factor' => $factor,
                    'received_at' => $rcCreatedAt // Lưu received_at để dùng khi hoàn tất
                ];
                // Overwrite allocations array
                $c->updateReceiptStatus($id, $receipt['status'] ?? 1); // keep status unchanged
                // Directly call model to set allocations without changing status timestamps
                $mr = new MReceipt();
                error_log("💾 SAVING ALLOCATIONS to receipt $id:");
                error_log("   Total allocations to save: " . count($allocs));
                error_log("   Latest allocation: " . json_encode(end($allocs), JSON_UNESCAPED_UNICODE));
                $updateResult = $mr->updateReceipt($id, ['allocations' => $allocs]);
                error_log("   Update result: " . ($updateResult ? 'SUCCESS' : 'FAILED'));

                // Recompute remaining for all products in base units; if all zero -> mark completed (status=3)
                $allZero = true;
                $details = $arrRc['details'] ?? [];
                foreach ($details as $d) {
                    $pid = $d['product_id'] ?? '';
                    if (!$pid) continue;
                    $need = (int)($d['quantity'] ?? $d['qty'] ?? 0);
                    $unitDetail = trim($d['unit'] ?? '');
                    // Fetch product to get base unit and conversions
                    $baseU = ''; $convs = [];
                    try { $cp2 = new CProduct(); $p2 = $cp2->getProductById($pid); if (is_array($p2)) { $baseU = trim($p2['unit'] ?? ($p2['baseUnit'] ?? '')); $convs = $p2['conversionUnits'] ?? []; } } catch (\Throwable $e) {}
                    if (!$baseU) $baseU = 'cái';
                    $factorNeed = 1;
                    if ($unitDetail !== '' && strcasecmp($unitDetail, $baseU) !== 0) {
                        foreach ($convs as $cu) { $u = trim($cu['unit'] ?? ''); if ($u && strcasecmp($u, $unitDetail) === 0) { $factorNeed = max(1, (int)($cu['factor'] ?? 1)); break; } }
                    }
                    $needBase = (int)$need * (int)$factorNeed;
                    $sum = 0; foreach ($allocs as $a) { if (($a['product_id'] ?? '') === $pid) $sum += (int)($a['qty'] ?? 0); }
                    if ($sum < $needBase) { $allZero = false; break; }
                }
                    // Do not auto-complete; completion only when user presses the Complete button
                    $completed = false;
            }

            // Không tạo inventory entry ngay, chỉ lưu thông tin vào allocation
            // Sẽ ghi vào database khi click "Hoàn tất"

            // Include allocation index and server time for client-side updates
            $allocation_index = (isset($allocs) && $qtyBase > 0) ? max(0, count($allocs) - 1) : null;
            $server_time = (isset($allocs) && $qtyBase > 0 && isset($allocs[$allocation_index]['time'])) ? $allocs[$allocation_index]['time'] : date(DATE_ATOM);

            // Recompute remaining for this product (base units)
            $allocSumFinal = 0; foreach (($arrRc['allocations'] ?? []) as $a) { if (($a['product_id'] ?? '') === $product_id) $allocSumFinal += (int)($a['qty'] ?? 0); }
            if ($qtyBase > 0) { $allocSumFinal += $qtyBase; } // include this allocation if not yet persisted in $arrRc
            $remainingAfter = max(0, $orderedBase - $allocSumFinal);
            echo json_encode([
                'success'=>(bool)$ok,
                'completed' => isset($completed) ? $completed : false,
                'allocation_index' => $allocation_index,
                'time' => $server_time,
                'applied_qty' => $qtyBase,
                'remaining' => $remainingAfter
            ]);
            break;
        }
        case 'get_product_units': {
            // Returns available units for a product: base unit + conversion units with factors
            $product_id = trim($data['product_id'] ?? '');
            if (!$product_id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã sản phẩm']); break; }
            // Always derive base unit from product definition
            $baseUnit = '';
            $convUnits = [];
            try { $cp = new CProduct(); $p = $cp->getProductById($product_id); if (is_array($p)) { $convUnits = $p['conversionUnits'] ?? []; $baseUnit = trim($p['unit'] ?? ($p['baseUnit'] ?? '')); if ($baseUnit) { foreach (($p['conversionUnits'] ?? []) as $cu) { $u=trim($cu['unit'] ?? ''); $f=(int)($cu['factor'] ?? 0); if ($u && strcasecmp($u,$baseUnit)===0 && $f>1) { $baseUnit='cái'; break; } } } } } catch (\Throwable $e) {}
            if (!$baseUnit) { $baseUnit = 'cái'; }
            $units = [];
            if ($baseUnit) { $units[] = ['unit'=>$baseUnit, 'factor'=>1]; }
            foreach ($convUnits as $cu) {
                $u = trim($cu['unit'] ?? ''); $f = (int)($cu['factor'] ?? 0);
                if ($u && $f > 0) $units[] = ['unit'=>$u,'factor'=>$f];
            }
            echo json_encode(['success'=>true, 'baseUnit'=>$baseUnit, 'units'=>$units]);
            break;
        }
        case 'update_allocation': {
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã phiếu']); break; }
            $idx = isset($data['allocation_index']) ? intval($data['allocation_index']) : -1;
            $newQty = max(0, intval($data['qty'] ?? 0));
            $newZone = trim($data['zone_id'] ?? '');
            $newRack = trim($data['rack_id'] ?? '');
            $newBin  = trim($data['bin_id'] ?? '');
            $newStatus = trim($data['bin_status'] ?? '');
            if ($idx < 0) { echo json_encode(['success'=>false,'message'=>'Thiếu allocation_index']); break; }

            $r = $c->getReceiptById($id);
            if (!$r) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy phiếu']); break; }
            $arrRc = is_array($r) ? $r : json_decode(json_encode($r), true);
            $allocs = $arrRc['allocations'] ?? [];
            if (!isset($allocs[$idx])) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy phân bổ']); break; }
            $rec = $allocs[$idx];
            $product_id = $rec['product_id'] ?? '';
            if (!$product_id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã sản phẩm trong phân bổ']); break; }

            // Validate against ordered and other allocations
            $ordered = 0; foreach (($arrRc['details'] ?? []) as $d) { if (($d['product_id'] ?? '') === $product_id) { $ordered = (int)($d['quantity'] ?? $d['qty'] ?? 0); break; } }
            $sumOthers = 0; foreach ($allocs as $i => $a) { if ($i === $idx) continue; if (($a['product_id'] ?? '') === $product_id) $sumOthers += (int)($a['qty'] ?? 0); }
            $remainingAllowed = max(0, $ordered - $sumOthers);
            if ($newQty > $remainingAllowed) { echo json_encode(['success'=>false,'message'=>'Số lượng mới vượt quá phần chưa xếp còn lại ('.$remainingAllowed.')']); break; }

            // Determine warehouse
            $warehouseId = $arrRc['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? '');
            if (!$warehouseId) { echo json_encode(['success'=>false,'message'=>'Không xác định được kho']); break; }

            $m = new MLocation();
            // Adjust old bin (-old qty)
            $oldZone = $rec['zone_id'] ?? ''; $oldRack = $rec['rack_id'] ?? ''; $oldBin = $rec['bin_id'] ?? '';
            $oldQty = (int)($rec['qty'] ?? 0);
            if ($oldZone && $oldRack && $oldBin && $oldQty > 0) {
                $oldDoc = $m->getBinFromWarehouse($warehouseId, $oldZone, $oldRack, ['bin_id'=>$oldBin]);
                $ocur = 0; $ocap=0; if ($oldDoc && !empty($oldDoc['bin'])) { $ocur=(int)($oldDoc['bin']['quantity'] ?? 0); $ocap=(int)($oldDoc['bin']['capacity'] ?? 0); }
                $newOldQty = max(0, $ocur - $oldQty);
                $oldStatus = ($newOldQty <= 0) ? 'empty' : (($ocap>0 && $newOldQty >= $ocap) ? 'full' : 'partial');
                $m->updateBinInWarehouse($warehouseId, $oldZone, $oldRack, $oldBin, ['quantity'=>$newOldQty, 'status'=>$oldStatus]);
            }

            // Adjust new bin (+new qty)
            if ($newZone && $newRack && $newBin) {
                $newDoc = $m->getBinFromWarehouse($warehouseId, $newZone, $newRack, ['bin_id'=>$newBin]);
                $ncur = 0; $ncap=0; if ($newDoc && !empty($newDoc['bin'])) { $ncur=(int)($newDoc['bin']['quantity'] ?? 0); $ncap=(int)($newDoc['bin']['capacity'] ?? 0); }
                // If same bin, ncur included old qty; we've subtracted above
                if ($oldZone === $newZone && $oldRack === $newRack && $oldBin === $newBin) {
                    $ncur = max(0, $ncur - $oldQty);
                }
                if ($ncap > 0 && ($ncur + $newQty) > $ncap) {
                    $newQty = max(0, $ncap - $ncur);
                }
                $newNewQty = $ncur + $newQty;
                $newStatusVal = ($newNewQty <= 0) ? 'empty' : (($ncap>0 && $newNewQty >= $ncap) ? 'full' : 'partial');
                $m->updateBinInWarehouse($warehouseId, $newZone, $newRack, $newBin, ['quantity'=>$newNewQty, 'status'=>$newStatus ?: $newStatusVal]);
            }

            // Update allocation record in receipt (không cập nhật inventory vì chưa ghi vào DB)
            $allocs[$idx]['zone_id'] = $newZone ?: $oldZone;
            $allocs[$idx]['rack_id'] = $newRack ?: $oldRack;
            $allocs[$idx]['bin_id']  = $newBin ?: $oldBin;
            $allocs[$idx]['qty']     = $newQty;
            if ($newStatus) $allocs[$idx]['bin_status'] = $newStatus;
            $mr = new MReceipt();
            $mr->updateReceipt($id, ['allocations' => $allocs]);

            // Recompute completion: if not fully allocated, set status back to 1 (Đã duyệt); if fully allocated, set 3
            $details = $arrRc['details'] ?? [];
            $allZero = !empty($details);
            foreach ($details as $d) {
                $pid = $d['product_id'] ?? '';
                if (!$pid) continue;
                $need = (int)($d['quantity'] ?? $d['qty'] ?? 0);
                $unitDetail = trim($d['unit'] ?? '');
                // Convert need to base units
                $baseU = ''; $convs = [];
                try { $cp2 = new CProduct(); $p2 = $cp2->getProductById($pid); if (is_array($p2)) { $baseU = trim($p2['unit'] ?? ($p2['baseUnit'] ?? '')); $convs = $p2['conversionUnits'] ?? []; } } catch (\Throwable $e) {}
                if (!$baseU) $baseU = 'cái';
                $factorNeed = 1; if ($unitDetail !== '' && strcasecmp($unitDetail, $baseU) !== 0) { foreach ($convs as $cu) { $u = trim($cu['unit'] ?? ''); if ($u && strcasecmp($u, $unitDetail) === 0) { $factorNeed = max(1, (int)($cu['factor'] ?? 1)); break; } } }
                $needBase = (int)$need * (int)$factorNeed;
                $sum = 0; foreach ($allocs as $a) { if (($a['product_id'] ?? '') === $pid) $sum += (int)($a['qty'] ?? 0); }
                if ($sum < $needBase) { $allZero = false; break; }
            }
                // Do not auto-change receipt status here; only compute remaining in base units for this product
                $newStatusVal = $arrRc['status'] ?? 1;

            // Compute remaining for this product after update
            // Remaining in base units for this product
            $orderedFinal = 0; $unitDetailF = '';
            foreach (($arrRc['details'] ?? []) as $d) { if (($d['product_id'] ?? '') === $product_id) { $orderedFinal = (int)($d['quantity'] ?? $d['qty'] ?? 0); $unitDetailF = trim($d['unit'] ?? ''); break; } }
            $baseUF=''; $convsF=[]; try { $cpF=new CProduct(); $pF=$cpF->getProductById($product_id); if (is_array($pF)) { $baseUF=trim($pF['unit'] ?? ($pF['baseUnit'] ?? '')); $convsF=$pF['conversionUnits'] ?? []; } } catch(\Throwable $e) {}
            if (!$baseUF) $baseUF='cái';
            $factorOrderedF=1; if ($unitDetailF !== '' && strcasecmp($unitDetailF, $baseUF) !== 0) { foreach ($convsF as $cu) { $u=trim($cu['unit'] ?? ''); if ($u && strcasecmp($u, $unitDetailF)===0) { $factorOrderedF = max(1,(int)($cu['factor'] ?? 1)); break; } } }
            $orderedFinalBase = (int)$orderedFinal * (int)$factorOrderedF;
            $sumFinal = 0; foreach ($allocs as $a) { if (($a['product_id'] ?? '') === $product_id) $sumFinal += (int)($a['qty'] ?? 0); }
            $remainingAfter = max(0, $orderedFinalBase - $sumFinal);
            echo json_encode(['success'=>true, 'status'=>$newStatusVal, 'remaining'=>$remainingAfter, 'product_id'=>$product_id]);
            break;
        }
        case 'delete_allocation': {
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã phiếu']); break; }
            $idx = isset($data['allocation_index']) ? intval($data['allocation_index']) : -1;
            if ($idx < 0) { echo json_encode(['success'=>false,'message'=>'Thiếu allocation_index']); break; }
            $r = $c->getReceiptById($id);
            if (!$r) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy phiếu']); break; }
            $arrRc = is_array($r) ? $r : json_decode(json_encode($r), true);
            $allocs = $arrRc['allocations'] ?? [];
            if (!isset($allocs[$idx])) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy phân bổ']); break; }
            $rec = $allocs[$idx];
            $warehouseId = $arrRc['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? '');
            if (!$warehouseId) { echo json_encode(['success'=>false,'message'=>'Không xác định được kho']); break; }
            
            // Không cần xóa inventory entry vì chưa ghi vào DB
            
            $m = new MLocation();
            // Decrease old bin
            $oz = $rec['zone_id'] ?? ''; $or = $rec['rack_id'] ?? ''; $ob = $rec['bin_id'] ?? ''; $oq = (int)($rec['qty'] ?? 0);
            if ($oz && $or && $ob && $oq > 0) {
                $od = $m->getBinFromWarehouse($warehouseId, $oz, $or, ['bin_id'=>$ob]);
                $ocur=0; $ocap=0; if ($od && !empty($od['bin'])) { $ocur=(int)($od['bin']['quantity'] ?? 0); $ocap=(int)($od['bin']['capacity'] ?? 0); }
                $newQtyBin = max(0, $ocur - $oq);
                $st = ($newQtyBin <= 0) ? 'empty' : (($ocap>0 && $newQtyBin >= $ocap) ? 'full' : 'partial');
                $m->updateBinInWarehouse($warehouseId, $oz, $or, $ob, ['quantity'=>$newQtyBin, 'status'=>$st]);
                
                // Update bin capacity when deleting allocation
                // Need to get the correct dimensions based on the unit that was used during allocation
                $product_id_del = $rec['product_id'] ?? '';
                $input_unit_del = $rec['input_unit'] ?? '';
                $input_qty_del = (int)($rec['input_qty'] ?? 0);
                
                // Fallback: if input_qty not saved (old allocation), use qty in base units
                if ($input_qty_del <= 0) {
                    $input_qty_del = $oq; // Use base quantity
                    error_log("WARNING: input_qty not found in allocation, using base qty: $oq");
                }
                
                if ($product_id_del && $input_qty_del > 0) {
                    try {
                        $cpDel = new CProduct();
                        $pDel = $cpDel->getProductById($product_id_del);
                        if (is_array($pDel)) {
                            $convUnitsDel = $pDel['conversionUnits'] ?? [];
                            $baseUnitDel = trim($pDel['unit'] ?? ($pDel['baseUnit'] ?? 'cái'));
                            
                            // Get dimensions based on the unit that was used
                            $pDimsDel = [];
                            $unitTypeDel = $baseUnitDel;
                            $dimensionsFoundDel = false;
                            
                            error_log("=== DELETE ALLOCATION DEBUG ===");
                            error_log("Deleting allocation - Input unit: $input_unit_del, Input qty: $input_qty_del, Qty in base: $oq");
                            
                            // If input_unit is a conversion unit, get its dimensions
                            // Also handle case where input_unit is empty (old allocations)
                            if ($input_unit_del && $input_unit_del !== '' && strcasecmp($input_unit_del, $baseUnitDel) !== 0) {
                                foreach ($convUnitsDel as $cu) {
                                    $cuUnit = trim($cu['unit'] ?? '');
                                    if ($cuUnit && strcasecmp($cuUnit, $input_unit_del) === 0) {
                                        if (isset($cu['dimensions']) && is_array($cu['dimensions'])) {
                                            $pDimsDel = $cu['dimensions'];
                                            $unitTypeDel = $cuUnit;
                                            $dimensionsFoundDel = true;
                                            error_log("✓ Found dimensions for '$cuUnit': " . print_r($pDimsDel, true));
                                        } else {
                                            error_log("✗ Unit '$cuUnit' found but no dimensions - will use base");
                                        }
                                        break;
                                    }
                                }
                            } else if (empty($input_unit_del)) {
                                error_log("⚠ input_unit is empty (old allocation), using base dimensions");
                            }
                            
                            // Fallback to base dimensions
                            if (!$dimensionsFoundDel) {
                                $pDimsDel = $pDel['package_dimensions'] ?? $pDel['dimensions'] ?? [];
                                $unitTypeDel = $baseUnitDel;
                                error_log("Using base dimensions");
                            }
                            
                            $pWidthDel = (float)($pDimsDel['width'] ?? 0);
                            $pDepthDel = (float)($pDimsDel['depth'] ?? 0);
                            $pHeightDel = (float)($pDimsDel['height'] ?? 0);
                            $pVolumeDel = $pWidthDel * $pDepthDel * $pHeightDel;
                            
                            error_log("Dimensions: W=$pWidthDel, D=$pDepthDel, H=$pHeightDel, Vol=$pVolumeDel");
                            
                            if ($pVolumeDel > 0) {
                                // When deleting: always DECREASE capacity (remove items = free space)
                                // Use input_qty (số lượng theo đơn vị đã chọn), not oq (base qty)
                                $receiptTypeDel = $arrRc['type'] ?? 'purchase';
                                $isAddingDel = false; // Always false for delete = decrease capacity
                                error_log("Calling updateBinCapacity with volume=$pVolumeDel, qty=$input_qty_del, isAdding=false");
                                $capacityResult = $m->updateBinCapacity($warehouseId, $oz, $or, $ob, $pVolumeDel, $input_qty_del, $isAddingDel);
                                error_log("Capacity update result: " . ($capacityResult ? 'success' : 'failed'));
                            } else {
                                error_log("✗ Volume is 0 - cannot update capacity");
                            }
                            error_log("=== END DELETE DEBUG ===");
                        }
                    } catch (\Throwable $e) {
                        error_log('Error updating capacity on delete: ' . $e->getMessage());
                    }
                }
            }
            // Remove allocation entry
            array_splice($allocs, $idx, 1);
            $mr = new MReceipt();
            $mr->updateReceipt($id, ['allocations' => $allocs]);
            // Recompute completion and update status accordingly
            $details = $arrRc['details'] ?? [];
            $allZero = !empty($details);
            foreach ($details as $d) {
                $pid = $d['product_id'] ?? '';
                if (!$pid) continue;
                $need = (int)($d['quantity'] ?? $d['qty'] ?? 0);
                $unitDetail = trim($d['unit'] ?? '');
                $baseU = ''; $convs = [];
                try { $cp2 = new CProduct(); $p2 = $cp2->getProductById($pid); if (is_array($p2)) { $baseU = trim($p2['unit'] ?? ($p2['baseUnit'] ?? '')); $convs = $p2['conversionUnits'] ?? []; } } catch (\Throwable $e) {}
                if (!$baseU) $baseU = 'cái';
                $factorNeed = 1; if ($unitDetail !== '' && strcasecmp($unitDetail, $baseU) !== 0) { foreach ($convs as $cu) { $u = trim($cu['unit'] ?? ''); if ($u && strcasecmp($u, $unitDetail) === 0) { $factorNeed = max(1, (int)($cu['factor'] ?? 1)); break; } } }
                $needBase = (int)$need * (int)$factorNeed;
                $sum = 0; foreach ($allocs as $a) { if (($a['product_id'] ?? '') === $pid) $sum += (int)($a['qty'] ?? 0); }
                if ($sum < $needBase) { $allZero = false; break; }
            }
                // Do not auto-change receipt status; only recompute remaining for response
                $newStatusVal = $arrRc['status'] ?? 1;
            // Compute remaining for this product after delete
            $product_id = $rec['product_id'] ?? '';
            $orderedFinal = 0; $unitDetailF = '';
            foreach (($arrRc['details'] ?? []) as $d) { if (($d['product_id'] ?? '') === $product_id) { $orderedFinal = (int)($d['quantity'] ?? $d['qty'] ?? 0); $unitDetailF = trim($d['unit'] ?? ''); break; } }
            $baseUF=''; $convsF=[]; try { $cpF=new CProduct(); $pF=$cpF->getProductById($product_id); if (is_array($pF)) { $baseUF=trim($pF['unit'] ?? ($pF['baseUnit'] ?? '')); $convsF=$pF['conversionUnits'] ?? []; } } catch(\Throwable $e) {}
            if (!$baseUF) $baseUF='cái';
            $factorOrderedF=1; if ($unitDetailF !== '' && strcasecmp($unitDetailF, $baseUF) !== 0) { foreach ($convsF as $cu) { $u=trim($cu['unit'] ?? ''); if ($u && strcasecmp($u, $unitDetailF)===0) { $factorOrderedF = max(1,(int)($cu['factor'] ?? 1)); break; } } }
            $orderedFinalBase = (int)$orderedFinal * (int)$factorOrderedF;
            $sumFinal = 0; foreach ($allocs as $a) { if (($a['product_id'] ?? '') === $product_id) $sumFinal += (int)($a['qty'] ?? 0); }
            $remainingAfter = max(0, $orderedFinalBase - $sumFinal);
            echo json_encode(['success'=>true, 'status'=>$newStatusVal, 'remaining'=>$remainingAfter, 'product_id'=>$product_id]);
            break;
        }
        case 'complete_receipt': {
            if (!$id) { echo json_encode(['success'=>false,'message'=>'Thiếu mã phiếu']); break; }
            // Load receipt and verify everything is fully allocated
            $receipt = $c->getReceiptById($id);
            if (!$receipt) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy phiếu']); break; }
            $arrRc = is_array($receipt) ? $receipt : json_decode(json_encode($receipt), true);
            $details = $arrRc['details'] ?? [];
            if (empty($details)) { echo json_encode(['success'=>false,'message'=>'Phiếu không có sản phẩm']); break; }
            $allocs = $arrRc['allocations'] ?? [];

            $allZero = true;
            foreach ($details as $d) {
                $pid = $d['product_id'] ?? '';
                if (!$pid) continue;
                $need = (int)($d['quantity'] ?? $d['qty'] ?? 0);
                $unitDetail = trim($d['unit'] ?? '');
                $baseU = ''; $convs = [];
                try { $cp2 = new CProduct(); $p2 = $cp2->getProductById($pid); if (is_array($p2)) { $baseU = trim($p2['unit'] ?? ($p2['baseUnit'] ?? '')); $convs = $p2['conversionUnits'] ?? []; } } catch (\Throwable $e) {}
                if (!$baseU) $baseU = 'cái';
                $factorNeed = 1; if ($unitDetail !== '' && strcasecmp($unitDetail, $baseU) !== 0) { foreach ($convs as $cu) { $u = trim($cu['unit'] ?? ''); if ($u && strcasecmp($u, $unitDetail) === 0) { $factorNeed = max(1, (int)($cu['factor'] ?? 1)); break; } } }
                $needBase = (int)$need * (int)$factorNeed;
                $sum = 0; foreach ($allocs as $a) { if (($a['product_id'] ?? '') === $pid) $sum += (int)($a['qty'] ?? 0); }
                if ($sum < $needBase) { $allZero = false; break; }
            }

            if (!$allZero) {
                echo json_encode(['success'=>false,'message'=>'Vẫn còn sản phẩm chưa xếp hết']);
                break;
            }

            // ✅ Load models cho batch_locations và inventory_movements
            include_once(__DIR__ . "/../../../../../model/mBatchLocation.php");
            include_once(__DIR__ . "/../../../../../model/mInventoryMovement.php");
            include_once(__DIR__ . "/../../../../../controller/cBatch.php");
            
            $mBatchLocation = new MBatchLocation();
            $mInventoryMovement = new MInventoryMovement();
            $cBatch = new CBatch();

            // Ensure batches exist for this receipt. If none, attempt to create them from receipt details.
            $batches = $cBatch->getBatchesByTransaction($id);
            error_log("🔍 Found " . count($batches) . " batches for transaction $id");
            if (empty($batches) || count($batches) === 0) {
                error_log("ℹ️ No batches found for transaction $id — creating from receipt details...");
                try {
                    $createRes = $cBatch->createBatchFromReceipt($arrRc);
                    error_log('ℹ️ createBatchFromReceipt result: ' . json_encode($createRes, JSON_UNESCAPED_UNICODE));
                } catch (\Throwable $e) {
                    error_log('⚠️ createBatchFromReceipt failed: ' . $e->getMessage());
                }
                // Re-fetch batches after creation attempt
                $batches = $cBatch->getBatchesByTransaction($id);
                error_log("🔍 After create attempt found " . count($batches) . " batches for transaction $id");
            }

            // Build quick lookup map product_id (string) => batch_code
            $batchesByProduct = [];
            foreach ($batches as $batch) {
                $p = $batch['product_id'] ?? null;
                if ($p !== null) {
                    $batchesByProduct[(string)$p] = $batch['batch_code'] ?? null;
                }
            }

            // Ghi tất cả allocations vào database inventory khi hoàn tất
            $inv = new MInventory();
            $insertedCount = 0;
            $batchLocationCount = 0;
            $movementCount = 0;
            $errors = [];
            
            foreach ($allocs as $a) {
                $warehouseId = $a['warehouse_id'] ?? '';
                $productId = $a['product_id'] ?? '';
                $qtyAlloc = (int)($a['qty'] ?? 0);
                $zoneId = $a['zone_id'] ?? '';
                $rackId = $a['rack_id'] ?? '';
                $binId = $a['bin_id'] ?? '';
                $receivedAt = $a['received_at'] ?? null;
                
                if ($warehouseId && $productId && $qtyAlloc > 0) {
                    try {
                        // Chuyển đổi received_at thành UTCDateTime
                        $finalReceivedAt = null;
                        if ($receivedAt) {
                            if ($receivedAt instanceof MongoDB\BSON\UTCDateTime) {
                                // Already UTCDateTime
                                $finalReceivedAt = $receivedAt;
                            } elseif (is_array($receivedAt)) {
                                // Array format from MongoDB serialization
                                if (isset($receivedAt['$date'])) {
                                    if (is_array($receivedAt['$date']) && isset($receivedAt['$date']['$numberLong'])) {
                                        $finalReceivedAt = new MongoDB\BSON\UTCDateTime((int)$receivedAt['$date']['$numberLong']);
                                    } else {
                                        $finalReceivedAt = new MongoDB\BSON\UTCDateTime((int)$receivedAt['$date']);
                                    }
                                } else {
                                    $finalReceivedAt = new MongoDB\BSON\UTCDateTime();
                                }
                            } elseif (is_object($receivedAt)) {
                                // Try to handle generic object
                                try {
                                    if (method_exists($receivedAt, 'toDateTime')) {
                                        $finalReceivedAt = $receivedAt;
                                    } else {
                                        $finalReceivedAt = new MongoDB\BSON\UTCDateTime();
                                    }
                                } catch (\Throwable $e2) {
                                    $finalReceivedAt = new MongoDB\BSON\UTCDateTime();
                                }
                            } else {
                                $finalReceivedAt = new MongoDB\BSON\UTCDateTime();
                            }
                        }
                        
                        if (!$finalReceivedAt) {
                            $finalReceivedAt = new MongoDB\BSON\UTCDateTime();
                        }
                        
                        $entryData = [
                            'warehouse_id' => $warehouseId,
                            'product_id' => $productId,
                            'qty' => $qtyAlloc,
                            'receipt_id' => $id,
                            'zone_id' => $zoneId,
                            'rack_id' => $rackId,
                            'bin_id' => $binId,
                            'received_at' => $finalReceivedAt,
                            'status' => 'confirmed'
                        ];
                        
                        $result = $inv->insertEntry($entryData);
                        if ($result) {
                            $insertedCount++;
                        } else {
                            $errors[] = "Failed to insert inventory for product $productId";
                        }

                        // ✅ Tìm batch_code của sản phẩm này từ transaction_id
                        $batches = $cBatch->getBatchesByTransaction($id);
                        error_log("🔍 Found " . count($batches) . " batches for transaction $id");
                        error_log("🔍 Looking for product: $productId");
                        
                        // Prefer lookup from prebuilt map
                        $batchCode = $batchesByProduct[(string)$productId] ?? null;
                        if ($batchCode) {
                            error_log("✅ Found matching batch from map: $batchCode for product $productId");
                        } else {
                            // Fallback: scan batches (handles possible non-standard product_id types)
                            foreach ($batches as $batch) {
                                $bCode = $batch['batch_code'] ?? 'no-code';
                                $bProd = $batch['product_id'] ?? null;
                                $bProdStr = is_object($bProd) ? (string)$bProd : (string)$bProd;
                                error_log("🔍 Fallback Batch: " . $bCode . " - Product: " . $bProdStr);
                                if ($bProdStr === (string)$productId) {
                                    $batchCode = $batch['batch_code'] ?? null;
                                    error_log("✅ Found matching batch (fallback): $batchCode");
                                    break;
                                }
                            }
                        }

                        if ($batchCode) {
                            error_log("📦 Processing batch_location for batch $batchCode at $zoneId-$rackId-$binId with qty $qtyAlloc");
                            
                            // ⭐ BƯỚC 1: Xóa location PENDING cũ (nếu có)
                            try {
                                $p3 = new clsKetNoi();
                                $con3 = $p3->moKetNoi();
                                if ($con3) {
                                    $batchLocCol = $con3->selectCollection('batch_locations');
                                    $deletedResult = $batchLocCol->deleteMany([
                                        'batch_code' => $batchCode,
                                        'location.zone_id' => 'PENDING'
                                    ]);
                                    if ($deletedResult->getDeletedCount() > 0) {
                                        error_log("🗑️ Deleted {$deletedResult->getDeletedCount()} PENDING batch_location(s) for batch $batchCode");
                                    }
                                    $p3->dongKetNoi($con3);
                                }
                            } catch (\Throwable $e) {
                                error_log("⚠️ Failed to delete PENDING batch_location: " . $e->getMessage());
                            }
                            
                            // ⭐ BƯỚC 2: Lưu vào batch_locations với vị trí thực tế
                            $locationObject = [
                                'warehouse_id' => $warehouseId,
                                'zone_id' => $zoneId,
                                'rack_id' => $rackId,
                                'bin_id' => $binId
                            ];
                            
                            $batchLocationResult = $mBatchLocation->upsertBatchLocation(
                                $batchCode,
                                $locationObject,
                                $qtyAlloc
                            );
                            
                            if ($batchLocationResult) {
                                $batchLocationCount++;
                                error_log("✅ Batch location inserted successfully");
                                
                                // ✅ CẬP NHẬT VỊ TRÍ VÀO BATCH DOCUMENT
                                try {
                                    $p = new clsKetNoi();
                                    $con = $p->moKetNoi();
                                    if ($con) {
                                        $batchesCol = $con->selectCollection('batches');
                                        
                                        // ⭐ BƯỚC 1: Xóa tất cả locations PENDING cũ của batch này
                                        $batchesCol->updateOne(
                                            ['batch_code' => $batchCode],
                                            [
                                                '$pull' => [
                                                    'locations' => [
                                                        'zone_id' => 'PENDING'
                                                    ]
                                                ]
                                            ]
                                        );
                                        
                                        // ⭐ BƯỚC 2: Thêm vị trí mới thực tế (nếu chưa tồn tại)
                                        // Store quantity as integer and save unit info (use base_unit if available)
                                        $unitToSave = $a['base_unit'] ?? ($a['input_unit'] ?? 'cái');
                                        $inputQtyForLocation = (int)($a['input_qty'] ?? $qtyAlloc);
                                        $batchesCol->updateOne(
                                            ['batch_code' => $batchCode],
                                            [
                                                '$addToSet' => [
                                                    'locations' => [
                                                        'warehouse_id' => $warehouseId,
                                                        'zone_id' => $zoneId,
                                                        'rack_id' => $rackId,
                                                        'bin_id' => $binId,
                                                        'quantity' => (int)$qtyAlloc,
                                                        'input_qty' => $inputQtyForLocation,
                                                        'unit' => $unitToSave
                                                    ]
                                                ]
                                            ]
                                        );
                                        
                                        // Recompute batch totals from batch_locations to avoid double-counting
                                        try {
                                            $batchLocCol = $con->selectCollection('batch_locations');
                                            $agg = $batchLocCol->aggregate([
                                                ['$match' => ['batch_code' => $batchCode]],
                                                ['$group' => ['_id' => null, 'total' => ['$sum' => '$quantity']]]
                                            ])->toArray();
                                            $totalQty = !empty($agg) ? (int)($agg[0]['total'] ?? 0) : 0;

                                            // Set both imported and remaining to the aggregated total (imported = total stored for now)
                                            $batchesCol->updateOne(
                                                ['batch_code' => $batchCode],
                                                ['$set' => [
                                                    'quantity_imported' => $totalQty,
                                                    'quantity_remaining' => $totalQty,
                                                    'updated_at' => new MongoDB\BSON\UTCDateTime()
                                                ]]
                                            );

                                            error_log("✅ Removed PENDING location and set batch totals for $batchCode = $totalQty");
                                        } catch (\Throwable $e) {
                                            error_log("⚠️ Failed to recompute/update batch totals for $batchCode: " . $e->getMessage());
                                        }
                                        $p->dongKetNoi($con);
                                    }
                                } catch (\Throwable $e) {
                                    error_log("⚠️ Failed to update batch location in batches: " . $e->getMessage());
                                }
                                    // ⭐ Cập nhật trạng thái lô: sau khi xếp hàng thành công, đánh dấu lô là 'Đang lưu'
                                    try {
                                        if (isset($cBatch) && method_exists($cBatch, 'updateBatchStatus')) {
                                            $updated = $cBatch->updateBatchStatus($batchCode, 'Đang lưu');
                                            if ($updated) {
                                                error_log("✅ Batch $batchCode status updated to 'Đang lưu'");
                                            } else {
                                                error_log("⚠️ Failed to update status for batch $batchCode (maybe no change)");
                                            }
                                        }
                                    } catch (\Throwable $e) {
                                        error_log("⚠️ Error updating batch status for $batchCode: " . $e->getMessage());
                                    }
                            } else {
                                $errors[] = "Failed to insert batch_location for batch $batchCode";
                                error_log("❌ Failed to insert batch_location for batch $batchCode");
                            }

                            // ✅ Lưu vào inventory_movements: lịch sử nhập hàng
                            // Lấy thông tin nguồn từ batch (batch ở kho đích có chứa source_location và source_warehouse_id)
                            $fromLocation = null;
                            $sourceWarehouse = null;
                            $receiptType = $arrRc['type'] ?? 'purchase'; // Lấy type từ receipt
                            
                            try {
                                // Khởi tạo kết nối MongoDB (CODE UPDATED v3)
                                include_once(__DIR__ . "/../../../../../model/connect.php");
                                $p2 = new clsKetNoi();
                                $con2 = $p2->moKetNoi();
                                
                                error_log("🔄 Connecting to MongoDB to fetch batch info...");
                                error_log("📋 Receipt type: $receiptType");
                                
                                if ($con2) {
                                    // Query batch tại kho ĐÍCH (warehouse_id hiện tại)
                                    $batchDoc = $con2->selectCollection('batches')->findOne([
                                        'batch_code' => $batchCode,
                                        'warehouse_id' => $warehouseId
                                    ]);
                                
                                    if ($batchDoc) {
                                        error_log("📦 Batch found: " . $batchCode);
                                        
                                        // Kiểm tra loại phiếu (source field từ batch)
                                        $batchSource = $batchDoc['source'] ?? '';
                                        if ($batchSource === 'transfer') {
                                            $receiptType = 'transfer';
                                        }
                                        
                                        // Nếu là transfer (có source_location và source_warehouse_id)
                                        if (isset($batchDoc['source_location']) && $batchDoc['source_location'] !== null) {
                                            $fromLocation = $batchDoc['source_location'];
                                            $sourceWarehouse = $batchDoc['source_warehouse_id'] ?? null;
                                            error_log("✅ Transfer - from_location from batch: " . json_encode($fromLocation));
                                            error_log("✅ Source warehouse: " . $sourceWarehouse);
                                        } else if ($receiptType === 'transfer') {
                                            // ⭐ FALLBACK: Nếu batch không có source_location nhưng receipt type là transfer
                                            // Lấy source_location từ export_id (thông qua receipt)
                                            error_log("⚠️ Batch missing source_location, trying to get from export...");
                                            
                                            $exportId = $arrRc['export_id'] ?? null;
                                            if ($exportId) {
                                                $exportDoc = null;
                                                try {
                                                    if ($exportId instanceof MongoDB\BSON\ObjectId) {
                                                        $exportDoc = $con2->selectCollection('transactions')->findOne(['_id' => $exportId]);
                                                    } elseif (!empty($exportId)) {
                                                        $exportDoc = $con2->selectCollection('transactions')->findOne(['_id' => new MongoDB\BSON\ObjectId((string)$exportId)]);
                                                    }
                                                } catch (Throwable $e) {
                                                    error_log('locate/process.php: exportId conversion failed: ' . $e->getMessage());
                                                    $exportDoc = null;
                                                }
                                                
                                                if ($exportDoc) {
                                                    // Tìm batch info trong export details
                                                    $exportDetails = $exportDoc['details'] ?? $exportDoc['products'] ?? [];
                                                    foreach ($exportDetails as $expDetail) {
                                                        if (($expDetail['product_id'] ?? '') === $productId && isset($expDetail['batches'])) {
                                                            foreach ($expDetail['batches'] as $expBatch) {
                                                                if (($expBatch['batch_code'] ?? '') === $batchCode && isset($expBatch['source_location'])) {
                                                                    $fromLocation = $expBatch['source_location'];
                                                                    $sourceWarehouse = $exportDoc['warehouse_id'] ?? $arrRc['source_warehouse_id'] ?? null;
                                                                    error_log("✅ Found from_location from export: " . json_encode($fromLocation));
                                                                    break 2;
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            if (!$fromLocation) {
                                                error_log("⚠️ Transfer receipt but cannot find source_location - setting to null");
                                            }
                                        } else {
                                            // Nhập từ nhà cung cấp - from_location = null
                                            error_log("ℹ️ Purchase receipt - from_location is null (from supplier)");
                                            $fromLocation = null; // Explicitly set to null
                                        }
                                    } else {
                                        error_log("⚠️ Batch not found: " . $batchCode);
                                    }
                                    
                                    $p2->dongKetNoi($con2);
                                } else {
                                    error_log("⚠️ Cannot connect to MongoDB");
                                }
                            } catch (\Exception $e) {
                                error_log("❌ Error fetching batch: " . $e->getMessage());
                                // Continue to insert movement even if batch fetch failed
                            }
                            
                            // ✅ Tạo note mô tả rõ ràng dựa vào receipt type
                            $noteText = '';
                            if ($receiptType === 'transfer') {
                                // Transfer: hiển thị thông tin kho nguồn và vị trí (nếu có)
                                if ($fromLocation) {
                                    $fromZone = $fromLocation['zone_id'] ?? '';
                                    $fromRack = $fromLocation['rack_id'] ?? '';
                                    $fromBin = $fromLocation['bin_id'] ?? '';
                                    $noteText = "Nhập điều chuyển từ kho {$sourceWarehouse} vị trí {$fromZone}-{$fromRack}-{$fromBin}";
                                } else {
                                    // Transfer nhưng không có vị trí (có thể do batch cũ hoặc thiếu dữ liệu)
                                    $noteText = "Nhập điều chuyển từ kho {$sourceWarehouse} (phiếu {$id})";
                                }
                            } else {
                                // Purchase: nhập từ nhà cung cấp
                                $noteText = "Nhập hàng từ nhà cung cấp (phiếu {$id})";
                            }
                            
                            error_log("📝 Creating movement with from_location: " . json_encode($fromLocation));
                            
                            $movementData = [
                                'batch_code' => $batchCode,
                                'product_id' => $productId,
                                'movement_type' => 'nhập',
                                'from_location' => $fromLocation, // null = nhập từ NCC, có giá trị = transfer
                                'to_location' => $locationObject,
                                'quantity' => $qtyAlloc,
                                'date' => $finalReceivedAt,
                                'warehouse_id' => $warehouseId,
                                'transaction_id' => $id,
                                'note' => $noteText
                            ];
                            
                            $movementResult = $mInventoryMovement->insertMovement($movementData);
                            if ($movementResult) {
                                $movementCount++;
                                error_log("✅ Movement inserted successfully for batch $batchCode");
                            } else {
                                $errors[] = "Failed to insert movement for batch $batchCode";
                                error_log("❌ Failed to insert movement for batch $batchCode");
                            }
                        } else {
                            $errors[] = "Batch not found for product $productId";
                            error_log("❌ No batch found for product $productId in transaction $id");
                        }
                        
                    } catch (\Throwable $e) {
                        $errors[] = $e->getMessage();
                        error_log('Error in complete_receipt: ' . $e->getMessage());
                    }
                } else {
                    $errors[] = "Missing data: warehouse=$warehouseId, product=$productId, qty=$qtyAlloc";
                }
            }
            
            // ⭐ CẬP NHẬT BIN CAPACITY sau khi xếp hàng xong
            try {
                include_once(__DIR__ . "/../../../../../model/connect.php");
                $pCap = new clsKetNoi();
                $conCap = $pCap->moKetNoi();
                
                if ($conCap) {
                    $locCol = $conCap->selectCollection('locations'); // ✅ Đổi từ warehouses => locations
                    
                    // Lấy danh sách bins đã được xếp hàng (từ allocations)
                    $binsToUpdate = [];
                    foreach ($allocs as $a) {
                        $binKey = ($a['warehouse_id'] ?? '') . '|' . ($a['zone_id'] ?? '') . '|' . ($a['rack_id'] ?? '') . '|' . ($a['bin_id'] ?? '');
                        if (!isset($binsToUpdate[$binKey])) {
                            $binsToUpdate[$binKey] = [
                                'warehouse_id' => $a['warehouse_id'] ?? '',
                                'zone_id' => $a['zone_id'] ?? '',
                                'rack_id' => $a['rack_id'] ?? '',
                                'bin_id' => $a['bin_id'] ?? ''
                            ];
                        }
                    }
                    
                    foreach ($binsToUpdate as $binInfo) {
                        $whId = $binInfo['warehouse_id'];
                        $zId = $binInfo['zone_id'];
                        $rId = $binInfo['rack_id'];
                        $bId = $binInfo['bin_id'];
                        
                        if (!$whId || !$zId || !$rId || !$bId) continue;
                        
                        // Tính tổng số lượng trong bin từ inventory
                        $invCol = $conCap->selectCollection('inventory');
                        $totalQty = 0;
                        
                        $invItems = $invCol->find([
                            'warehouse_id' => $whId,
                            'zone_id' => $zId,
                            'rack_id' => $rId,
                            'bin_id' => $bId
                        ]);
                        
                        foreach ($invItems as $item) {
                            $totalQty += (int)($item['qty'] ?? 0);
                        }
                        
                        // Lấy capacity của bin từ locations collection
                        $location = $locCol->findOne(['warehouse.id' => $whId]); // ✅ Đổi từ warehouse_id => warehouse.id
                        $binCapacity = 100; // Default capacity
                        
                        if ($location && isset($location['zones'])) {
                            foreach ($location['zones'] as $zone) {
                                if (($zone['zone_id'] ?? '') === $zId && isset($zone['racks'])) {
                                    foreach ($zone['racks'] as $rack) {
                                        if (($rack['rack_id'] ?? '') === $rId && isset($rack['bins'])) {
                                            foreach ($rack['bins'] as $bin) {
                                                if (($bin['bin_id'] ?? '') === $bId) {
                                                    $binCapacity = (int)($bin['capacity'] ?? 100);
                                                    break 3;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Tính % capacity
                        $currentCapacity = 0;
                        if ($binCapacity > 0 && $totalQty > 0) {
                            $currentCapacity = ($totalQty / $binCapacity) * 100;
                        }
                        
                        error_log("📊 CAPACITY UPDATE - Bin: $whId/$zId/$rId/$bId, Qty: $totalQty, Cap: $binCapacity, Calc: " . round($currentCapacity, 1) . "%");
                        
                        // Cập nhật current_capacity vào bin trong locations collection
                        $updateResult = $locCol->updateOne(
                            [
                                'warehouse.id' => $whId, // ✅ Đổi từ warehouse_id => warehouse.id
                                'zones.zone_id' => $zId,
                                'zones.racks.rack_id' => $rId,
                                'zones.racks.bins.bin_id' => $bId
                            ],
                            [
                                '$set' => [
                                    'zones.$[z].racks.$[r].bins.$[b].current_capacity' => round($currentCapacity, 1),
                                    'zones.$[z].racks.$[r].bins.$[b].capacity' => $binCapacity // Đảm bảo có capacity
                                ]
                            ],
                            [
                                'arrayFilters' => [
                                    ['z.zone_id' => $zId],
                                    ['r.rack_id' => $rId],
                                    ['b.bin_id' => $bId]
                                ]
                            ]
                        );
                        
                        if ($updateResult->getModifiedCount() > 0) {
                            error_log("✅ Updated bin capacity: $bId = " . round($currentCapacity, 1) . "% (qty: $totalQty, cap: $binCapacity)");
                        } else {
                            error_log("⚠️ Bin capacity NOT updated (maybe no change): $bId");
                        }
                    }
                    
                    $pCap->dongKetNoi($conCap);
                }
            } catch (\Throwable $e) {
                error_log("⚠️ Failed to update bin capacity: " . $e->getMessage());
                // Continue anyway - không critical
            }

            // Update status = 3 and set completed_at
            $ok = $c->updateReceiptStatus($id, 3);
            $mr = new MReceipt();
            $mr->updateReceipt($id, ['completed_at' => new MongoDB\BSON\UTCDateTime()]);
            
            // ⭐ Nếu là phiếu nhập transfer, cập nhật status phiếu xuất thành 2 (Đã giao hàng)
            try {
                $receipt = $c->getReceiptById($id);
                if ($receipt && isset($receipt['export_id']) && !empty($receipt['export_id'])) {
                    $exportId = $receipt['export_id'];
                    error_log("📦 Updating export status for receipt $id (export: $exportId)");
                    
                    $p2 = new clsKetNoi();
                    $con2 = $p2->moKetNoi();
                    if ($con2) {
                        // Normalize export id to ObjectId when possible
                        $exportIdObj = null;
                        try {
                            if ($exportId instanceof MongoDB\BSON\ObjectId) {
                                $exportIdObj = $exportId;
                            } elseif (!empty($exportId)) {
                                $exportIdObj = new MongoDB\BSON\ObjectId((string)$exportId);
                            }
                        } catch (Throwable $e) {
                            error_log('locate/process.php: exportId -> ObjectId conversion failed: ' . $e->getMessage());
                            $exportIdObj = null;
                        }

                        if ($exportIdObj) {
                            $con2->selectCollection('transactions')->updateOne(
                                ['_id' => $exportIdObj],
                                ['$set' => [
                                    'status' => 2, // Đã giao hàng
                                    'received_at' => new MongoDB\BSON\UTCDateTime(),
                                    'received_by' => $_SESSION['login']['user_id'] ?? 'system'
                                ]]
                            );
                        } else {
                            error_log('locate/process.php: skipping export update because export_id could not be converted');
                        }
                        error_log("✅ Updated export $exportId status to 2 (Received)");
                        $p2->dongKetNoi($con2);
                    }
                }
            } catch (\Exception $e) {
                error_log("⚠️ Cannot update export status: " . $e->getMessage());
                // Continue anyway - this is not critical
            }
            
            $response = [
                'success' => (bool)$ok,
                'inventory_inserted' => $insertedCount,
                'batch_locations_inserted' => $batchLocationCount,
                'movements_inserted' => $movementCount
            ];
            
            if (!empty($errors)) {
                $response['errors'] = $errors;
            }
            
            error_log("✅ Completed receipt $id: inventory=$insertedCount, batch_locations=$batchLocationCount, movements=$movementCount");
            echo json_encode($response);
            break;
        }
        default:
            echo json_encode(['success'=>false,'message'=>'Hành động không hợp lệ']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Lỗi server: '.$e->getMessage()]);
}
?>
