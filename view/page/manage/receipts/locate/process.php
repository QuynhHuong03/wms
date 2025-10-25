<?php
header('Content-Type: application/json; charset=utf-8');
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
            $bin_status = trim($data['bin_status'] ?? '');
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
            $capacity = 0; $current = 0;
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
                $mr->updateReceipt($id, ['allocations' => $allocs]);

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

            // Ghi tất cả allocations vào database inventory khi hoàn tất
            $inv = new MInventory();
            $insertedCount = 0;
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
                            $errors[] = "Failed to insert for product $productId";
                        }
                    } catch (\Throwable $e) {
                        $errors[] = $e->getMessage();
                        error_log('Error inserting inventory entry: ' . $e->getMessage());
                    }
                } else {
                    $errors[] = "Missing data: warehouse=$warehouseId, product=$productId, qty=$qtyAlloc";
                }
            }

            // Update status = 3 and set completed_at
            $ok = $c->updateReceiptStatus($id, 3);
            $mr = new MReceipt();
            $mr->updateReceipt($id, ['completed_at' => new MongoDB\BSON\UTCDateTime()]);
            
            $response = ['success'=>(bool)$ok, 'inventory_inserted'=>$insertedCount];
            if (!empty($errors)) {
                $response['errors'] = $errors;
            }
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
