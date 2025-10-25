<?php
header('Content-Type: application/json');
include_once(__DIR__ . '/../../../../controller/clocation.php');

// Thêm session_start và lấy warehouse id từ session,
// ép vào dữ liệu trước khi gọi CLocation
session_start();


$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    echo json_encode(['success'=>false,'message'=>'Invalid JSON']); exit;
}

    try {
    $c = new CLocation();
    $action = $data['action'] ?? '';
    // temporary debug log path (will be removed after debugging)
    $debugLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'debug_add_bin.log';
    // workspace-local debug log (readable from repo) for quicker inspection
    $workspaceLog = __DIR__ . DIRECTORY_SEPARATOR . 'debug_add_bin_workspace.log';

    // lấy warehouse id của người tạo (từ session login)
    $sessionWarehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
    // nếu session tồn tại và có warehouse id, ép luôn vào payload khi cần tạo/luu zone
    switch($action){
        case 'create_zone':
            // For safety: require an explicit confirm_create flag so preview/other calls can't create zones accidentally
            if (empty($data['confirm_create'])) {
                echo json_encode(['success' => false, 'message' => 'confirm_create required']);
                break;
            }
            // override warehouse with session warehouse id if có
            $warehouse = $sessionWarehouseId ? ['id' => $sessionWarehouseId] : ($data['warehouse'] ?? []);
            echo json_encode($c->createZone($data['zone_id'] ?? '', $data['name'] ?? '', $warehouse, $data['description'] ?? ''));
            break;
        case 'save_zone':
            $zone = $data['zone'] ?? $data;
            if ($sessionWarehouseId) $zone['warehouse'] = ['id' => $sessionWarehouseId];
            echo json_encode($c->saveZoneConfig($zone));
            break;
        case 'save_matrix':
            // frontend gửi zone_id, zone_name, racks
            $zone_id = $data['zone_id'] ?? ($data['zone']['_id'] ?? null);
            $zone_name = $data['zone_name'] ?? ($data['zone']['name'] ?? '');
            $racks = $data['racks'] ?? [];
            if (!$zone_id) {
                echo json_encode(['success'=>false,'message'=>'zone_id required']); break;
            }
            // Use controller's saveMatrix which will consult session warehouse and create zone when needed
            // pass confirm_create flag from client (only Save All should set this)
            $confirmCreate = !empty($data['confirm_create']);
            $res = $c->saveMatrix($zone_id, $racks, $zone_name, $confirmCreate);
            // normalize response to object with success flag
            if (is_array($res)) {
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res]);
            }
            break;
        case 'save_location':
            // Expect payload: { action: 'save_location', zones: [ { zone_id, name, racks, ... }, ... ] }
            $zones = $data['zones'] ?? [];
            $res = $c->saveLocationForWarehouse($zones, $data['name'] ?? '', $data['description'] ?? '');
            echo json_encode($res);
            break;
        case 'delete_zone':
            $res = $c->deleteZone($data['zone_id'] ?? '');
            if (is_array($res)) {
                // controller provided structured response
                if (!empty($res['success'])) {
                    $res['message'] = $res['message'] ?? 'Xóa thành công';
                } else {
                    $res['message'] = $res['message'] ?? 'Xóa thất bại';
                }
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Xóa thành công' : 'Xóa thất bại']);
            }
            break;
        case 'add_zone':
            // payload: { action:'add_zone', zone_id, name, racks }
            $zone_id = $data['zone_id'] ?? '';
            $name = $data['name'] ?? '';
            $racks = $data['racks'] ?? [];
            $res = $c->addZone($zone_id, $name, $racks);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Thêm thành công';
                else $res['message'] = $res['message'] ?? 'Thêm thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Thêm thành công' : 'Thêm thất bại']);
            }
            break;
        case 'add_rack':
            // log request
            @file_put_contents($debugLog, "--- add_rack request @ " . date(DATE_ATOM) . "\n" . json_encode(['payload'=>$data, 'sessionWarehouse'=>$sessionWarehouseId], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            // Allow caller to specify a different warehouse id in payload to perform the action
            $originalWarehouse = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
            if (!empty($data['warehouse'])) {
                // warehouse may be string or object/array
                $w = $data['warehouse'];
                $wid = null;
                if (is_array($w)) $wid = $w['id'] ?? $w['warehouse_id'] ?? null;
                elseif (is_object($w)) $wid = $w->id ?? $w->warehouse_id ?? null;
                elseif (is_string($w)) $wid = $w;
                if ($wid) {
                    if (session_status() == PHP_SESSION_NONE) @session_start();
                    $_SESSION['login']['warehouse_id'] = $wid;
                }
            }
            // if caller requests force=true or manual/manual_add, bypass controller limits and call model directly
            // Accept 'manual' or 'manual_add' as aliases for clarity from clients
            $force = !empty($data['force']) || !empty($data['manual']) || !empty($data['manual_add']);
            if ($force) {
                @file_put_contents($debugLog, "--- add_rack: force mode enabled\n", FILE_APPEND);
                try {
                    // dynamic property created in CLocation constructor
                    $resModel = $c->m->addRack($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['name'] ?? '');
                    $res = ['success' => (bool)$resModel];
                } catch (\Throwable $e) {
                    $res = ['success' => false, 'message' => 'Model error: '.$e->getMessage()];
                }
            } else {
                $res = $c->addRack($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['name'] ?? '');
            }
            // If addRack failed because zone doesn't exist, try to create the zone (using session warehouse or payload) and retry
            $needZoneCreate = false;
            if (is_array($res) && empty($res['success'])) {
                $msg = trim(strtolower($res['message'] ?? ''));
                if (strpos($msg, 'zone không tồn tại') !== false || strpos($msg, 'zone khong ton tai') !== false || strpos($msg, 'zone does not exist') !== false) {
                    $needZoneCreate = true;
                }
            } elseif (!$res) {
                // boolean false; can't know exact reason — attempt zone create as a last resort
                $needZoneCreate = true;
            }
            if ($needZoneCreate) {
                @file_put_contents($debugLog, "--- add_rack: detected missing zone, attempting to create zone\n", FILE_APPEND);
                // prepare warehouse arg
                $warehouseArg = $sessionWarehouseId ? ['id' => $sessionWarehouseId] : ($data['warehouse'] ?? []);
                try {
                    $createZoneRes = $c->createZone($data['zone_id'] ?? '', $data['zone_id'] ?? '', $warehouseArg, $data['description'] ?? '');
                    if (is_array($createZoneRes) ? (!empty($createZoneRes['success'])) : (bool)$createZoneRes) {
                        @file_put_contents($debugLog, "--- add_rack: zone created, retrying addRack\n", FILE_APPEND);
                        // retry addRack (respect force)
                        if ($force) {
                            try {
                                $resModel = $c->m->addRack($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['name'] ?? '');
                                $res = ['success' => (bool)$resModel];
                            } catch (\Throwable $e) {
                                $res = ['success' => false, 'message' => 'Model error: '.$e->getMessage()];
                            }
                        } else {
                            $res = $c->addRack($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['name'] ?? '');
                        }
                    } else {
                        @file_put_contents($debugLog, "--- add_rack: createZone failed or returned false\n", FILE_APPEND);
                    }
                } catch (\Throwable $e) {
                    @file_put_contents($debugLog, "--- add_rack: createZone exception: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
            // restore original session warehouse id if we changed it
            if (isset($originalWarehouse)) {
                $_SESSION['login']['warehouse_id'] = $originalWarehouse;
            } else {
                // unset if there was none
                unset($_SESSION['login']['warehouse_id']);
            }
            // log response
            @file_put_contents($debugLog, "--- add_rack response @ " . date(DATE_ATOM) . "\n" . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Thêm thành công';
                else $res['message'] = $res['message'] ?? 'Thêm thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Thêm thành công' : 'Thêm thất bại']);
            }
            break;
        case 'add_rack_manual':
            // Shortcut endpoint to add a rack directly via model (thêm rack thủ công)
            // Payload: { action: 'add_rack_manual', zone_id, rack_id, name, warehouse?(id or object) }
            @file_put_contents($debugLog, "--- add_rack_manual request @ " . date(DATE_ATOM) . "\n" . json_encode(['payload'=>$data, 'sessionWarehouse'=>$sessionWarehouseId], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            $originalWarehouse = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
            if (!empty($data['warehouse'])) {
                $w = $data['warehouse'];
                $wid = null;
                if (is_array($w)) $wid = $w['id'] ?? $w['warehouse_id'] ?? null;
                elseif (is_object($w)) $wid = $w->id ?? $w->warehouse_id ?? null;
                elseif (is_string($w)) $wid = $w;
                if ($wid) {
                    if (session_status() == PHP_SESSION_NONE) @session_start();
                    $_SESSION['login']['warehouse_id'] = $wid;
                }
            }
            try {
                $resModel = $c->m->addRack($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['name'] ?? '');
                $res = ['success' => (bool)$resModel];
            } catch (\Throwable $e) {
                $res = ['success' => false, 'message' => 'Model error: ' . $e->getMessage()];
            }
            // restore session
            if (isset($originalWarehouse)) {
                $_SESSION['login']['warehouse_id'] = $originalWarehouse;
            } else {
                unset($_SESSION['login']['warehouse_id']);
            }
            @file_put_contents($debugLog, "--- add_rack_manual response @ " . date(DATE_ATOM) . "\n" . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);
            if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Thêm thành công (manual)';
            else $res['message'] = $res['message'] ?? 'Thêm thất bại (manual)';
            echo json_encode($res);
            break;
        case 'delete_rack':
            $res = $c->deleteRack($data['zone_id'] ?? '', $data['rack_id'] ?? '');
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Xóa thành công';
                else $res['message'] = $res['message'] ?? 'Xóa thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Xóa thành công' : 'Xóa thất bại']);
            }
            break;
    case 'add_bin':
            // payload: { action:'add_bin', zone_id, rack_id, bin_id, code }
            @file_put_contents($debugLog, "--- add_bin request @ " . date(DATE_ATOM) . "\n" . json_encode(['payload'=>$data, 'sessionWarehouse'=>$sessionWarehouseId], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            // Optional warehouse override in payload
            $originalWarehouse = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
            if (!empty($data['warehouse'])) {
                $w = $data['warehouse'];
                $wid = null;
                if (is_array($w)) $wid = $w['id'] ?? $w['warehouse_id'] ?? null;
                elseif (is_object($w)) $wid = $w->id ?? $w->warehouse_id ?? null;
                elseif (is_string($w)) $wid = $w;
                if ($wid) { $_SESSION['login']['warehouse_id'] = $wid; $sessionWarehouseId = $wid; }
            }

            $zoneId = $data['zone_id'] ?? '';
            $rackId = $data['rack_id'] ?? '';
            $binId  = $data['bin_id'] ?? '';
            $binName = $data['bin_name'] ?? ($data['name'] ?? '');
            // Do not default bin id here; let controller compute next available when empty
            $code   = $data['code'] ?? '';
            $capacity = $data['capacity'] ?? 0; // kept for compatibility
            $rackName = $data['rack_name'] ?? ($data['name'] ?? '');

            // Auto-pick next rack id if requested or missing
            $autoRack = !empty($data['auto_rack']) || (is_string($rackId) && strtolower($rackId) === 'auto') || !$rackId;
            if ($autoRack) {
                $nextNum = 1; $existingRackIds = [];
                try {
                    if ($sessionWarehouseId) {
                        $wh = $c->m->getLocationByWarehouseId($sessionWarehouseId);
                        if ($wh && !empty($wh['zones'])) {
                            foreach ($wh['zones'] as $z) {
                                if (($z['zone_id'] ?? $z['_id'] ?? null) === $zoneId) {
                                    $racks = $z['racks'] ?? [];
                                    foreach ($racks as $rk) {
                                        $rid = $rk['rack_id'] ?? '';
                                        if (preg_match('/^R(\d+)$/i', $rid, $m)) { $existingRackIds[] = intval($m[1]); }
                                    }
                                    break;
                                }
                            }
                        }
                    } else {
                        // fallback: try zone-level doc
                        $loc = $c->m->getLocationByZoneId($zoneId);
                        if ($loc && !empty($loc['racks'])) {
                            foreach ($loc['racks'] as $rk) {
                                $rid = $rk['rack_id'] ?? '';
                                if (preg_match('/^R(\d+)$/i', $rid, $m)) { $existingRackIds[] = intval($m[1]); }
                            }
                        }
                    }
                } catch (\Throwable $e) {}
                if (!empty($existingRackIds)) $nextNum = max($existingRackIds) + 1;
                if ($nextNum > \CLocation::MAX_RACKS_PER_ZONE) {
                    echo json_encode(['success'=>false,'message'=>'Đã đạt số rack tối đa ('.\CLocation::MAX_RACKS_PER_ZONE.')']);
                    break;
                }
                $rackId = 'R'.$nextNum;
                $rackName = 'Rack '.$nextNum;
            }

            // Ensure friendly rack name when not auto path and name not provided or equals raw rack id
            if (!$rackName || strcasecmp($rackName, $rackId) === 0) {
                $rn = 'Rack ' . $rackId;
                if (preg_match('/^R(\d+)$/i', $rackId, $mm)) { $rn = 'Rack ' . intval($mm[1]); }
                $rackName = $rn;
            }

            // Build final code only if binId already known; otherwise controller will compute
            if (!$code && $binId) { $code = $zoneId.'-'.$rackId.'-'.$binId; }

            // Before creating, enforce max bins per rack by looking up current count (prefer warehouse-level doc)
            try {
                $cntBins = 0;
                if ($sessionWarehouseId) {
                    $wh = $c->m->getLocationByWarehouseId($sessionWarehouseId);
                    if ($wh && !empty($wh['zones'])) {
                        foreach ($wh['zones'] as $z) {
                            if (($z['zone_id'] ?? $z['_id'] ?? null) !== $zoneId) continue;
                            $racks = $z['racks'] ?? [];
                            foreach ($racks as $rk) {
                                if (($rk['rack_id'] ?? null) !== $rackId) continue;
                                $cntBins = isset($rk['bins']) && is_array($rk['bins']) ? count($rk['bins']) : 0;
                                break 2;
                            }
                        }
                    }
                }
                if ($cntBins === 0) {
                    $locForCount = $c->m->getLocationByZoneId($zoneId);
                    if ($locForCount && !empty($locForCount['racks'])) {
                        foreach ($locForCount['racks'] as $rk) {
                            if (($rk['rack_id'] ?? null) !== $rackId) continue;
                            $cntBins = isset($rk['bins']) && is_array($rk['bins']) ? count($rk['bins']) : 0;
                            break;
                        }
                    }
                }
                if ($cntBins >= \CLocation::MAX_BINS_PER_RACK) {
                    echo json_encode(['success'=>false,'message'=>'Đã đạt số bin tối đa ('.\CLocation::MAX_BINS_PER_RACK.')']);
                    exit;
                }
            } catch (\Throwable $e) {}

            // 1) Ensure rack exists using model; prefer warehouse-level doc when we know it
            $createdRack = false;
            try {
                if ($sessionWarehouseId) {
                    $createdRack = (bool)$c->m->addRackToWarehouse($sessionWarehouseId, $zoneId, $rackId, $rackName);
                } else {
                    $createdRack = (bool)$c->m->addRack($zoneId, $rackId, $rackName);
                }
            } catch (\Throwable $e) {
                @file_put_contents($debugLog, "--- add_bin: model addRack exception: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            // 2) Try add bin via controller (warehouse-level preferred inside)
            // Note: model/controller will auto-assign an integer 'id' for the bin if missing
            $initialAdd = $c->addBin($zoneId, $rackId, $binId, $code, $capacity, $binName);
            $res = $initialAdd;

            // 3) If still failed, push directly into warehouse-level document using arrayFilters
            $failed = (!is_array($res) && !$res) || (is_array($res) && empty($res['success']));
            $directPush = null;
            if ($failed && $sessionWarehouseId) {
                // Compute fallback bin_id/code if missing so UI has proper titles
                if (!$binId) {
                    try {
                        $maxNum = 0; $foundRack = false;
                        $wh = $c->m->getLocationByWarehouseId($sessionWarehouseId);
                        if ($wh && !empty($wh['zones'])) {
                            foreach ($wh['zones'] as $z) {
                                if (($z['zone_id'] ?? $z['_id'] ?? null) !== $zoneId) continue;
                                $racks = $z['racks'] ?? [];
                                foreach ($racks as $rk) {
                                    if (($rk['rack_id'] ?? null) !== $rackId) continue;
                                    $foundRack = true;
                                    $binsExisting = isset($rk['bins']) && is_array($rk['bins']) ? $rk['bins'] : [];
                                    foreach ($binsExisting as $b0) {
                                        $bid0 = $b0['bin_id'] ?? '';
                                        if (preg_match('/^B(\d+)$/i', (string)$bid0, $mm)) {
                                            $maxNum = max($maxNum, (int)$mm[1]);
                                        }
                                    }
                                    break;
                                }
                                if ($foundRack) break;
                            }
                        }
                        if (!$foundRack) {
                            // Fallback: check legacy zone doc
                            $loc = $c->m->getLocationByZoneId($zoneId);
                            if ($loc && !empty($loc['racks'])) {
                                foreach ($loc['racks'] as $rk) {
                                    if (($rk['rack_id'] ?? null) !== $rackId) continue;
                                    $binsExisting = isset($rk['bins']) && is_array($rk['bins']) ? $rk['bins'] : [];
                                    foreach ($binsExisting as $b0) {
                                        $bid0 = $b0['bin_id'] ?? '';
                                        if (preg_match('/^B(\d+)$/i', (string)$bid0, $mm)) {
                                            $maxNum = max($maxNum, (int)$mm[1]);
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        $binId = 'B' . ($maxNum + 1);
                    } catch (\Throwable $e) {
                        $binId = $binId ?: 'B1';
                    }
                }
                if (!$code && $binId) { $code = $zoneId.'-'.$rackId.'-'.$binId; }
                try {
                    $ok = $c->m->addBinToWarehouse($sessionWarehouseId, $zoneId, $rackId, [
                        'bin_id' => $binId,
                        'code' => $code,
                        'quantity' => 0,
                        'status' => 'empty',
                        'product' => null,
                        'name' => $binName
                    ]);
                    $directPush = (bool)$ok;
                    $res = ['success' => (bool)$ok];
                } catch (\Throwable $e) {
                    @file_put_contents($debugLog, "--- add_bin: direct warehouse push exception: " . $e->getMessage() . "\n", FILE_APPEND);
                    $res = ['success' => false, 'message' => 'Internal error adding bin'];
                }
            }

            // 4) If arrayFilters push failed (or not attempted), do a read-modify-write fallback into warehouse doc
            $rmwOk = null;
            if ($sessionWarehouseId) {
                $stillFailed = (!is_array($res) && !$res) || (is_array($res) && empty($res['success']));
                if ($stillFailed) {
                    // Ensure we have sane identifiers for RMW as well
                    if (!$binId) { $binId = 'B1'; }
                    if (!$code) { $code = $zoneId.'-'.$rackId.'-'.$binId; }
                    try {
                        $rmwOk = (bool)$c->m->upsertBinIntoWarehouseByRMW($sessionWarehouseId, $zoneId, $rackId, [
                            'bin_id' => $binId,
                            'code' => $code,
                            'quantity' => 0,
                            'status' => 'empty',
                            'product' => null,
                            'name' => $binName
                        ]);
                        $res = ['success' => (bool)$rmwOk];
                    } catch (\Throwable $e) {
                        @file_put_contents($debugLog, "--- add_bin: RMW fallback exception: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }
            // restore original session warehouse id if we changed it
            if (isset($originalWarehouse)) {
                $_SESSION['login']['warehouse_id'] = $originalWarehouse;
            } else {
                unset($_SESSION['login']['warehouse_id']);
            }
            // Try to fetch the inserted bin to expose its numeric id in response
            $insertedBin = null;
            try {
                if ($sessionWarehouseId) {
                    $insertedBin = $c->m->getBinFromWarehouse($sessionWarehouseId, $zoneId, $rackId, ['bin_id' => $binId, 'code' => $code]);
                } else {
                    $insertedBin = $c->m->getBinFromZone($zoneId, $rackId, ['bin_id' => $binId, 'code' => $code]);
                }
            } catch (\Throwable $e) {}

            // prepare debug info and log response (use correct variable names)
            $debugInfo = [
                'sessionWarehouse' => $sessionWarehouseId,
                'initialAdd' => $initialAdd ?? null,
                'createdRack' => $createdRack ?? false,
                'retryAdd' => $retryAdd ?? null,
                'zoneId' => $zoneId,
                'rackId' => $rackId,
                'binId' => $binId,
                'code' => $code,
                'numericId' => is_array($insertedBin) ? ($insertedBin['id'] ?? null) : null,
                'autoRack' => $autoRack,
                'directPush' => $directPush,
                'rmwOk' => $rmwOk,
                'finalRes' => $res
            ];
            @file_put_contents($debugLog, "--- add_bin response @ " . date(DATE_ATOM) . "\n" . json_encode($debugInfo, JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND);
            // also write a workspace-local log file so you can read it easily
            @file_put_contents($workspaceLog, json_encode($debugInfo, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . "\n---\n", FILE_APPEND);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Thêm thành công';
                else $res['message'] = $res['message'] ?? 'Thêm thất bại';
                // include debug info in response when client requests it (debug=true)
                if (!empty($data['debug'])) {
                    $res['__debug'] = $debugInfo;
                }
                // If we managed to fetch the inserted bin and it has a numeric id, include it
                if (is_array($insertedBin) && isset($insertedBin['id'])) {
                    $res['id'] = $insertedBin['id'];
                }
                echo json_encode($res);
            } else {
                $out = ['success' => (bool)$res, 'message' => $res ? 'Thêm thành công' : 'Thêm thất bại'];
                if (is_array($insertedBin) && isset($insertedBin['id'])) {
                    $out['id'] = $insertedBin['id'];
                }
                if (!empty($data['debug'])) $out['__debug'] = $debugInfo;
                echo json_encode($out);
            }
            break;
        case 'normalize_rack_names':
            // Normalize rack names across all zones for the current session warehouse document
            $wid = $sessionWarehouseId;
            if (!$wid) { echo json_encode(['success'=>false,'message'=>'warehouse id required']); break; }
            try {
                $ok = method_exists($c->m, 'normalizeRackNamesForWarehouse') ? $c->m->normalizeRackNamesForWarehouse($wid) : false;
                echo json_encode(['success' => (bool)$ok, 'message' => $ok ? 'Đã chuẩn hóa tên rack' : 'Không có thay đổi']);
            } catch (\Throwable $e) {
                echo json_encode(['success'=>false,'message'=>'Error: '.$e->getMessage()]);
            }
            break;
        case 'update_bin':
            // payload: { action:'update_bin', zone_id, rack_id, bin_id, binData }
            $res = $c->updateBin($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['bin_id'] ?? '', $data['binData'] ?? []);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Cập nhật thành công';
                else $res['message'] = $res['message'] ?? 'Cập nhật thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Cập nhật thành công' : 'Cập nhật thất bại']);
            }
            break;
        case 'delete_bin':
            // Accept either bin_id (string code like B1) or numeric id (auto-increment)
            $binNumericId = isset($data['id']) ? $data['id'] : (isset($data['bin_numeric_id']) ? $data['bin_numeric_id'] : null);
            $res = $c->deleteBin($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['bin_id'] ?? '', $binNumericId);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Xóa thành công';
                else $res['message'] = $res['message'] ?? 'Xóa thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Xóa thành công' : 'Xóa thất bại']);
            }
            break;
        case 'update_bin_status':
            // Update only the status field of a bin
            // payload: { action:'update_bin_status', zone_id, rack_id, bin_id, status }
            $status = $data['status'] ?? 'empty';
            $validStatuses = ['empty', 'partial', 'full'];
            if (!in_array($status, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
                break;
            }
            $res = $c->updateBin($data['zone_id'] ?? '', $data['rack_id'] ?? '', $data['bin_id'] ?? '', ['status' => $status]);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Cập nhật trạng thái thành công';
                else $res['message'] = $res['message'] ?? 'Cập nhật trạng thái thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Cập nhật trạng thái thành công' : 'Cập nhật trạng thái thất bại']);
            }
            break;
        case 'get_bin_data':
            // Get bin data for editing
            // payload: { action:'get_bin_data', zone_id, rack_id, bin_id }
            $zone_id = $data['zone_id'] ?? '';
            $rack_id = $data['rack_id'] ?? '';
            $bin_id = $data['bin_id'] ?? '';
            
            if (!$zone_id || !$rack_id || !$bin_id) {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
                break;
            }
            
            try {
                $bin = null;
                if ($sessionWarehouseId) {
                    $binData = $c->m->getBinFromWarehouse($sessionWarehouseId, $zone_id, $rack_id, ['bin_id' => $bin_id]);
                    $bin = $binData['bin'] ?? null;
                } else {
                    $binData = $c->m->getBinFromZone($zone_id, $rack_id, ['bin_id' => $bin_id]);
                    $bin = $binData;
                }
                
                if ($bin) {
                    echo json_encode([
                        'success' => true,
                        'name' => $bin['name'] ?? '',
                        'status' => $bin['status'] ?? 'empty',
                        'quantity' => $bin['quantity'] ?? 0
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy bin']);
                }
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
            break;
        case 'update_bin_full':
            // Update both name and status of a bin
            // payload: { action:'update_bin_full', zone_id, rack_id, bin_id, name, status }
            $zone_id = $data['zone_id'] ?? '';
            $rack_id = $data['rack_id'] ?? '';
            $bin_id = $data['bin_id'] ?? '';
            $name = $data['name'] ?? '';
            $status = $data['status'] ?? 'empty';
            
            $validStatuses = ['empty', 'partial', 'full'];
            if (!in_array($status, $validStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
                break;
            }
            
            $updateData = [
                'name' => $name,
                'status' => $status
            ];
            
            $res = $c->updateBin($zone_id, $rack_id, $bin_id, $updateData);
            if (is_array($res)) {
                if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Cập nhật thành công';
                else $res['message'] = $res['message'] ?? 'Cập nhật thất bại';
                echo json_encode($res);
            } else {
                echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Cập nhật thành công' : 'Cập nhật thất bại']);
            }
            break;
        case 'clear_bin_product':
            // Clear product from bin (only if quantity is 0)
            // payload: { action:'clear_bin_product', zone_id, rack_id, bin_id }
            $zone_id = $data['zone_id'] ?? '';
            $rack_id = $data['rack_id'] ?? '';
            $bin_id = $data['bin_id'] ?? '';
            
            if (!$zone_id || !$rack_id || !$bin_id) {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin zone/rack/bin']);
                break;
            }
            
            // Check if bin has quantity = 0 first (from inventory)
            try {
                include_once(__DIR__ . '/../../../../model/mInventory.php');
                $mInv = new MInventory();
                
                // Get total quantity from inventory
                $totalQty = 0;
                if ($sessionWarehouseId) {
                    $totalQty = $mInv->sumQuantityByBin($sessionWarehouseId, $zone_id, $rack_id, $bin_id);
                }
                
                if ($totalQty > 0) {
                    echo json_encode(['success' => false, 'message' => 'Chỉ có thể xóa sản phẩm khi số lượng = 0 trong inventory']);
                    break;
                }
                
                // Clear product and set status to empty
                $res = $c->updateBin($zone_id, $rack_id, $bin_id, [
                    'product' => null,
                    'status' => 'empty',
                    'quantity' => 0
                ]);
                
                if (is_array($res)) {
                    if (!empty($res['success'])) $res['message'] = $res['message'] ?? 'Đã xóa sản phẩm khỏi bin';
                    else $res['message'] = $res['message'] ?? 'Xóa sản phẩm thất bại';
                    echo json_encode($res);
                } else {
                    echo json_encode(['success' => (bool)$res, 'message' => $res ? 'Đã xóa sản phẩm khỏi bin' : 'Xóa sản phẩm thất bại']);
                }
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
            break;
        case 'update_bin_quantity':
            // Update bin quantity in inventory
            // payload: { action:'update_bin_quantity', zone_id, rack_id, bin_id, quantity }
            $zone_id = $data['zone_id'] ?? '';
            $rack_id = $data['rack_id'] ?? '';
            $bin_id = $data['bin_id'] ?? '';
            $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;
            
            if (!$zone_id || !$rack_id || !$bin_id) {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin zone/rack/bin']);
                break;
            }
            
            if ($quantity < 0) {
                echo json_encode(['success' => false, 'message' => 'Số lượng phải >= 0']);
                break;
            }
            
            try {
                include_once(__DIR__ . '/../../../../model/mInventory.php');
                $mInv = new MInventory();
                
                if (!$sessionWarehouseId) {
                    echo json_encode(['success' => false, 'message' => 'Không xác định được warehouse']);
                    break;
                }
                
                // Find existing inventory entry for this bin
                $existingEntry = $mInv->findEntry([
                    'warehouse_id' => $sessionWarehouseId,
                    'zone_id' => $zone_id,
                    'rack_id' => $rack_id,
                    'bin_id' => $bin_id
                ]);
                
                $success = false;
                if ($existingEntry) {
                    // Update existing entry
                    $entryId = $existingEntry['_id'];
                    $success = $mInv->updateEntry($entryId, ['qty' => $quantity]);
                } else if ($quantity > 0) {
                    // Insert new entry only if quantity > 0
                    $entryData = [
                        'warehouse_id' => $sessionWarehouseId,
                        'zone_id' => $zone_id,
                        'rack_id' => $rack_id,
                        'bin_id' => $bin_id,
                        'qty' => $quantity,
                        'product_id' => '',
                        'product_sku' => '',
                        'product_name' => 'Manual Entry',
                        'receipt_id' => 'MANUAL-' . date('YmdHis'),
                        'receipt_code' => 'MANUAL',
                        'note' => 'Cập nhật thủ công từ quản lý locations',
                        'received_at' => new MongoDB\BSON\UTCDateTime(),
                        'type' => 'manual'
                    ];
                    $success = $mInv->insertEntry($entryData);
                } else {
                    // quantity = 0 and no existing entry, nothing to do
                    $success = true;
                }
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Cập nhật số lượng thành công']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không thể cập nhật số lượng']);
                }
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
            break;
        default:
            echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage(),'trace'=> $e->getTraceAsString()]);
}
?>
