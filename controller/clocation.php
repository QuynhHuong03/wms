<?php
include_once(__DIR__ . '/../model/mLocation.php');

class CLocation {

    const MAX_ZONES = 3;
    const MAX_RACKS_PER_ZONE = 4;
    const MAX_BINS_PER_RACK = 10;
    const MAX_CAPACITY_PER_BIN = 20;

    public function __construct() {
        $this->m = new MLocation();
    }

    public function listLocations() {
        // Return only zones for the current session warehouse (to show only warehouse's locations)
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if (!$warehouseId) {
            // fallback: return empty array if no warehouse in session
            return [];
        }
        // Get the warehouse document and flatten its zones
        $loc = $this->m->getLocationByWarehouseId($warehouseId);
        if ($loc && !empty($loc['zones'])) {
            $zones = [];
            foreach ($loc['zones'] as $z) {
                $z['warehouse'] = $loc['warehouse'] ?? [];
                $zones[] = $z;
            }
            return $zones;
        }
        return [];
    }

    // Save a location document for the current session warehouse containing multiple zones
    // $zones: array of zone objects { zone_id, name, racks, description? }
    // If location doesn't exist for warehouse, create it; otherwise replace zones[]
    public function saveLocationForWarehouse($zones, $locationName = '', $description = '') {
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if (!$warehouseId) return ['success' => false, 'message' => 'warehouse id required'];

        // Validate zones count
        if (!is_array($zones)) return ['success' => false, 'message' => 'zones must be array'];
        if (count($zones) > self::MAX_ZONES) return ['success' => false, 'message' => 'Max zones reached'];

        // Validate each zone structure and ensure names are unique (case-insensitive)
        $zoneNameSet = [];
        foreach ($zones as $z) {
            $nr = isset($z['racks']) && is_array($z['racks']) ? count($z['racks']) : 0;
            if ($nr > self::MAX_RACKS_PER_ZONE) return ['success' => false, 'message' => 'Số rack vượt quá giới hạn'];
            if (!isset($z['zone_id'])) return ['success' => false, 'message' => 'zone_id required for each zone'];
            $zn = strtolower(trim($z['name'] ?? $z['zone_name'] ?? ''));
            if ($zn !== '') {
                if (isset($zoneNameSet[$zn])) return ['success' => false, 'message' => 'Tên zone bị trùng: ' . ($z['name'] ?? $z['zone_name'] ?? '')];
                $zoneNameSet[$zn] = true;
            }
            // Ensure rack names unique within a zone
            $rackNameSet = [];
            $racks = $z['racks'] ?? [];
            foreach ($racks as $rk) {
                $rn = strtolower(trim($rk['name'] ?? ''));
                if ($rn === '') continue;
                if (isset($rackNameSet[$rn])) return ['success' => false, 'message' => 'Tên rack bị trùng trong zone ' . ($z['name'] ?? $z['zone_id']) . ': ' . ($rk['name'] ?? '')];
                $rackNameSet[$rn] = true;
            }
        }

        // Transform zones to ensure expected fields and timestamps
        $now = date(DATE_ATOM);
        $prepared = [];
        foreach ($zones as $z) {
            $prepared[] = [
                'zone_id' => $z['zone_id'] ?? ($z['_id'] ?? null),
                'name' => $z['name'] ?? $z['zone_name'] ?? '',
                'description' => $z['description'] ?? '',
                'racks' => $z['racks'] ?? [],
                'created_at' => $z['created_at'] ?? $now,
                'updated_at' => $z['updated_at'] ?? $now
            ];
        }

        // Save zones[] into warehouse document (upsert true)
        $ok = $this->m->setZonesForWarehouse($warehouseId, $prepared, true);
        if ($ok) return ['success' => true, 'action' => 'updated'];

        // If update returned false, try to detect reason and attempt fallback create
        $existing = $this->m->getLocationByWarehouseId($warehouseId);
        if ($existing === null) {
            // No existing document found, try to create
            $created = $this->m->addLocationForWarehouse($warehouseId, $prepared, $locationName, $description);
            if ($created) return ['success' => true, 'action' => 'created (fallback)'];
            return ['success' => false, 'message' => 'Failed to create location document for warehouse'];
        }

        // If existing doc found but update reported false, check if stored zones already equal payload
        $existingZones = $existing['zones'] ?? [];
        // Normalize for comparison
        $normA = json_encode($existingZones, JSON_UNESCAPED_UNICODE);
        $normB = json_encode($prepared, JSON_UNESCAPED_UNICODE);
        if ($normA === $normB) {
            return ['success' => true, 'action' => 'no_change', 'message' => 'Payload equals stored zones'];
        }

        // Try a retry update (without upsert) to see if it succeeds this time
        $retry = $this->m->setZonesForWarehouse($warehouseId, $prepared, false);
        if ($retry) return ['success' => true, 'action' => 'updated (retry)'];

        // Otherwise return informative failure
        return ['success' => false, 'message' => 'Failed to update location document for warehouse (no modification)'];
    }

    // Lưu cấu hình zone (nhận toàn bộ object zone như JSON)
    public function saveZoneConfig($zoneData) {
        $zone_id = $zoneData['_id'] ?? ($zoneData['id'] ?? null);
        if (!$zone_id) return ['success' => false, 'message' => 'zone_id required'];

        // chuẩn hóa warehouse id (nếu frontend gửi object hoặc id)
        $warehouse = $zoneData['warehouse'] ?? [];
        $warehouseId = null;
        if (is_array($warehouse)) {
            $warehouseId = $warehouse['id'] ?? $warehouse['warehouse_id'] ?? null;
        } elseif (is_string($warehouse)) {
            $warehouseId = $warehouse;
        } elseif (is_object($warehouse)) {
            $warehouseId = $warehouse->id ?? $warehouse->warehouse_id ?? null;
        }

        // nếu không có warehouse trong payload, thử lấy từ session (nếu session đang bật)
        if (!$warehouseId) {
            if (session_status() == PHP_SESSION_NONE) @session_start();
            $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        }

        if (!$warehouseId) return ['success' => false, 'message' => 'warehouse id required'];

        // kiểm tra: mỗi warehouse chỉ có 1 location - nếu đã có location khác của warehouse này thì từ chối tạo
        $all = $this->m->getAllLocations();
        if ($all !== false) {
            foreach ($all as $loc) {
                $locWarehouseId = $loc['warehouse']['id'] ?? $loc['warehouse_id'] ?? null;
                $locZoneId = $loc['zone_id'] ?? $loc['_id'] ?? null;
                if ($locWarehouseId && $locWarehouseId == $warehouseId && $locZoneId != $zone_id) {
                    return ['success' => false, 'message' => 'Warehouse already has a location'];
                }
            }
        }

        $name = $zoneData['name'] ?? '';
        $description = $zoneData['description'] ?? '';
        $created_at = $zoneData['created_at'] ?? date(DATE_ATOM);
        $updated_at = $zoneData['updated_at'] ?? date(DATE_ATOM);
        $racks = $zoneData['racks'] ?? [];

        // lưu warehouse dưới dạng object tối giản { id: ... }
        $warehouseObj = ['id' => $warehouseId];

        // Only update existing zone here. Creation must be explicit via createZone.
        $existing = $this->m->getLocationByZoneId($zone_id);
        if (!$existing) {
            return ['success' => false, 'message' => 'Zone does not exist. Use create_zone to create new zone.'];
        }

        $updateData = [
            'name' => $name,
            'warehouse' => $warehouseObj,
            'description' => $description,
            'updated_at' => $updated_at,
            'racks' => $racks
        ];
        $ok = $this->m->updateLocation($zone_id, $updateData);
        return ['success' => (bool)$ok, 'action' => 'updated'];
    }

    public function createZone($zone_id, $name, $warehouse, $description) {
        $locations = $this->m->getAllLocations();
        if ($locations !== false && count($locations) >= self::MAX_ZONES) {
            return ['success' => false, 'message' => 'Max zones reached'];
        }

        // chuẩn hóa warehouse id
        $warehouseId = null;
        if (is_array($warehouse)) $warehouseId = $warehouse['id'] ?? $warehouse['warehouse_id'] ?? null;
        elseif (is_string($warehouse)) $warehouseId = $warehouse;
        elseif (is_object($warehouse)) $warehouseId = $warehouse->id ?? $warehouse->warehouse_id ?? null;

        if (!$warehouseId) {
            if (session_status() == PHP_SESSION_NONE) @session_start();
            $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        }

        if (!$warehouseId) return ['success' => false, 'message' => 'warehouse id required'];

        // nếu warehouse đã có location thì không tạo thêm
        $all = $this->m->getAllLocations();
        if ($all !== false) {
            foreach ($all as $loc) {
                $locWid = $loc['warehouse']['id'] ?? $loc['warehouse_id'] ?? null;
                if ($locWid && $locWid == $warehouseId) {
                    return ['success' => false, 'message' => 'Warehouse already has a location'];
                }
            }
        }

        $created_at = date(DATE_ATOM);
        $ok = $this->m->addLocation($zone_id, $name, ['id' => $warehouseId], $description, $created_at);
        return ['success' => $ok];
    }

    public function deleteZone($zone_id) {
        // Try to remove zone from warehouse-level document if session warehouse exists
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if ($warehouseId) {
            $ok = $this->m->removeZoneFromWarehouse($warehouseId, $zone_id);
            return ['success' => (bool)$ok];
        }
        // fallback: remove legacy per-zone doc
        $ok = $this->m->deleteLocation($zone_id);
        return ['success' => $ok];
    }

    // Add a new zone to current session warehouse
    public function addZone($zone_id, $name, $racks = []) {
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if (!$warehouseId) return ['success' => false, 'message' => 'warehouse id required'];

        // Validate limits
        $existing = $this->m->getLocationByWarehouseId($warehouseId);
        $countZones = 0;
        if ($existing && !empty($existing['zones'])) $countZones = count($existing['zones']);
        if ($countZones >= self::MAX_ZONES) return ['success' => false, 'message' => 'Max zones reached for warehouse'];

        // Enforce unique zone name within warehouse (case-insensitive)
        if ($existing && !empty($existing['zones'])) {
            $lname = strtolower(trim($name));
            if ($lname !== '') {
                foreach ($existing['zones'] as $z) {
                    $zn = strtolower(trim($z['name'] ?? ''));
                    if ($zn !== '' && $zn === $lname) {
                        return ['success' => false, 'message' => 'Tên zone đã tồn tại'];
                    }
                }
            }
        }

        $zone = [
            'zone_id' => $zone_id,
            'name' => $name,
            'racks' => $racks,
            'created_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM)
        ];

        $ok = $this->m->addZoneToWarehouse($warehouseId, $zone);
        return ['success' => (bool)$ok];
    }

    public function addRack($zone_id, $rack_id, $name) {
        // check existing racks count
        $loc = $this->m->getLocationByZoneId($zone_id);
        if (!$loc) return ['success' => false, 'message' => 'Zone không tồn tại'];
        $count = isset($loc['racks']) ? count($loc['racks']) : 0;
        if ($count >= self::MAX_RACKS_PER_ZONE) return ['success' => false, 'message' => 'Đã đạt số rack tối đa (' . self::MAX_RACKS_PER_ZONE . ')'];

        // Enforce unique rack name (case-insensitive) within this zone
        $lname = strtolower(trim($name));
        if ($lname !== '' && !empty($loc['racks'])) {
            foreach ($loc['racks'] as $rk) {
                $rn = strtolower(trim($rk['name'] ?? ''));
                if ($rn !== '' && $rn === $lname) {
                    return ['success' => false, 'message' => 'Tên rack đã tồn tại trong zone'];
                }
            }
        }
        $ok = $this->m->addRack($zone_id, $rack_id, $name);
        return ['success' => $ok];
    }

    public function deleteRack($zone_id, $rack_id) {
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if ($warehouseId && method_exists($this->m, 'removeRackFromWarehouse')) {
            $okWh = $this->m->removeRackFromWarehouse($warehouseId, $zone_id, $rack_id);
            if ($okWh) return ['success' => true];
        }
        $ok = $this->m->deleteRack($zone_id, $rack_id);
        return ['success' => $ok];
    }

    public function addBin($zone_id, $rack_id, $bin_id, $code, $capacity, $bin_name = '') {
        // Determine session warehouse if any
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;

        // Compute next available bin_id within the target rack if missing/empty
        $computedBinId = $bin_id;
        $existingBinsCount = 0;
        $locCheck = null;
        // Prefer warehouse-level document when warehouse is known
        if ($warehouseId) {
            $loc = $this->m->getLocationByWarehouseId($warehouseId);
            if ($loc && !empty($loc['zones'])) {
                foreach ($loc['zones'] as $z) {
                    $zid = $z['zone_id'] ?? ($z['_id'] ?? null);
                    if ($zid === $zone_id) { $locCheck = $z; break; }
                }
            }
        }
        // Fallback to legacy per-zone document
        if (!$locCheck) { $locCheck = $this->m->getLocationByZoneId($zone_id); }
        if ($locCheck && !empty($locCheck['racks'])) {
            foreach ($locCheck['racks'] as $rk) {
                if (($rk['rack_id'] ?? null) !== $rack_id) continue;
                $binsExisting = $rk['bins'] ?? [];
                $existingBinsCount = is_array($binsExisting) ? count($binsExisting) : 0;
                if (!$computedBinId) {
                    // find next B<number>
                    $maxNum = 0;
                    foreach ($binsExisting as $b0) {
                        $bid0 = $b0['bin_id'] ?? '';
                        if (preg_match('/^B(\d+)$/i', (string)$bid0, $mm)) {
                            $maxNum = max($maxNum, (int)$mm[1]);
                        }
                    }
                    $computedBinId = 'B' . ($maxNum + 1);
                }
                break;
            }
        }
        if (!$computedBinId) $computedBinId = 'B1';

        // enforce bins per rack limit
        if ($existingBinsCount >= self::MAX_BINS_PER_RACK) {
            return ['success' => false, 'message' => 'Đã đạt số bin tối đa (' . self::MAX_BINS_PER_RACK . ')'];
        }

        // If code not provided, build from computed bin id
        if (!$code) {
            $code = $zone_id . '-' . $rack_id . '-' . $computedBinId;
        }

        $bin = [
            'bin_id' => $computedBinId,
            'code' => $code,
            'quantity' => 0,
            'status' => 'empty',
            'product' => null,
            'name' => $bin_name
        ];

        // Prevent duplicate bin_id or code in the same rack (case-insensitive for code)
        if ($locCheck && !empty($locCheck['racks'])) {
            foreach ($locCheck['racks'] as $rk) {
                if (($rk['rack_id'] ?? null) !== $rack_id) continue;
                $binsExisting = $rk['bins'] ?? [];
                foreach ($binsExisting as $b0) {
                    $dupId = (($b0['bin_id'] ?? null) === $bin['bin_id']);
                    $dupCode = false;
                    if (isset($b0['code']) && $code !== '') {
                        $dupCode = (mb_strtolower((string)$b0['code']) === mb_strtolower((string)$code));
                    }
                    if ($dupId || $dupCode) {
                        return ['success' => false, 'message' => 'Tên hoặc code bin đã tồn tại trong rack'];
                    }
                }
            }
        }

        // Prefer warehouse-level when a session warehouse exists (this is what the UI reads)
        if ($warehouseId) {
            $ok2 = $this->m->addBinToWarehouse($warehouseId, $zone_id, $rack_id, $bin);
            if ($ok2) return ['success' => true];
            // fallback to legacy document if warehouse-level didn't match (e.g., legacy structure)
            $ok = $this->m->addBin($zone_id, $rack_id, $bin);
            return ['success' => (bool)$ok];
        }

        // No warehouse in session: fallback to legacy structure only
        $ok = $this->m->addBin($zone_id, $rack_id, $bin);
        return ['success' => (bool)$ok];
    }

    public function updateBin($zone_id, $rack_id, $bin_id, $binData) {
        // Map current_load -> quantity if present
        if (isset($binData['current_load']) && !isset($binData['quantity'])) {
            $binData['quantity'] = $binData['current_load'];
            unset($binData['current_load']);
        }
        $ok = $this->m->updateBin($zone_id, $rack_id, $bin_id, $binData);
        if ($ok) return ['success' => true];

        // fallback: try warehouse-level update
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if ($warehouseId) {
            $ok2 = $this->m->updateBinInWarehouse($warehouseId, $zone_id, $rack_id, $bin_id, $binData);
            return ['success' => (bool)$ok2];
        }
        return ['success' => false];
    }

    public function deleteBin($zone_id, $rack_id, $bin_id, $binNumericId = null) {
        // Prefer numeric id deletion when provided (works even without zone/rack)
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;
        if ($binNumericId !== null && $binNumericId !== '') {
            // Try warehouse-level by numeric id first
            if ($warehouseId && method_exists($this->m, 'removeBinByNumericIdWarehouse')) {
                $okNum = $this->m->removeBinByNumericIdWarehouse($warehouseId, $binNumericId);
                if ($okNum) return ['success' => true];
            }
            // Fallback to legacy structure removal by numeric id within provided zone/rack
            $okLegacyNum = $this->m->deleteBin($zone_id, $rack_id, $bin_id, $binNumericId);
            if ($okLegacyNum) return ['success' => true];
        }

        // Otherwise, delete by textual bin_id within the specified zone/rack
        $ok = $this->m->deleteBin($zone_id, $rack_id, $bin_id, null);
        if ($ok) return ['success' => true];

        // fallback to warehouse-level deletion by textual id
        if ($warehouseId) {
            $ok2 = $this->m->removeBinFromWarehouse($warehouseId, $zone_id, $rack_id, $bin_id, null);
            return ['success' => (bool)$ok2];
        }
        return ['success' => false];
    }

    // Lưu ma trận racks/bins cho một zone. $matrix là mảng racks với bins (mỗi bin có bin_id, code, capacity, current_load, status)
    // $confirmCreate (bool) - when true, allow creating a new zone if it doesn't exist. Default false.
    public function saveMatrix($zone_id, $racks, $zone_name = '', $confirmCreate = false) {
        // validate zone exists; if not, create it only when $confirmCreate === true
        $loc = $this->m->getLocationByZoneId($zone_id);
        // lấy warehouse id từ session nếu có
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $warehouseId = $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? null;

        if (!$loc) {
            // If we are not allowed to create, return a clear message so caller can prompt the user
            if (!$confirmCreate) {
                return ['success' => false, 'message' => 'Zone does not exist. Set confirm_create to true to create new zone.'];
            }

            // check max zones
            $all = $this->m->getAllLocations();
            if ($all !== false && count($all) >= self::MAX_ZONES) return ['success' => false, 'message' => 'Đã đạt số zone tối đa, không thể tạo zone mới'];

            // nếu warehouse đã có location thì không tạo thêm (cho trường hợp saveMatrix tạo zone mới)
            if ($warehouseId) {
                foreach ($all as $existing) {
                    $locWid = $existing['warehouse']['id'] ?? $existing['warehouse_id'] ?? null;
                    if ($locWid && $locWid == $warehouseId) {
                        return ['success' => false, 'message' => 'Warehouse already has a location'];
                    }
                }
            }

            // create minimal zone record (use session warehouse id if present)
            $warehouseObj = $warehouseId ? ['id' => $warehouseId] : [];
            $created = $this->m->addLocation($zone_id, ($zone_name ?: $zone_id), $warehouseObj, '', date(DATE_ATOM));
            if (!$created) return ['success' => false, 'message' => 'Không thể tạo zone mới'];
            $loc = $this->m->getLocationByZoneId($zone_id);
            $action = 'created';
        } else {
            $action = 'updated';
        }

        // Validate counts (capacity limit removed). Ensure bin field 'quantity' exists and normalize
        if (count($racks) > self::MAX_RACKS_PER_ZONE) return ['success' => false, 'message' => 'Số rack vượt quá giới hạn'];
        // Check unique rack names in this payload (case-insensitive)
        $rackNameSet = [];
        foreach ($racks as &$r) {
            $rn = strtolower(trim($r['name'] ?? ''));
            if ($rn !== '') {
                if (isset($rackNameSet[$rn])) return ['success' => false, 'message' => 'Tên rack bị trùng trong payload'];
                $rackNameSet[$rn] = true;
            }
            $bins = isset($r['bins']) && is_array($r['bins']) ? $r['bins'] : [];
            if (count($bins) > self::MAX_BINS_PER_RACK) return ['success' => false, 'message' => 'Số bin vượt quá giới hạn'];
            // Prevent duplicate bins inside this rack by bin_id and by code (case-insensitive)
            $seenIds = [];
            $seenCodes = [];
            foreach ($bins as &$b) {
                // If legacy payload contains current_load, map it to quantity; ensure quantity exists
                if (isset($b['current_load']) && !isset($b['quantity'])) {
                    $b['quantity'] = (int)$b['current_load'];
                    unset($b['current_load']);
                }
                if (!isset($b['quantity'])) $b['quantity'] = 0;
                $bid = $b['bin_id'] ?? '';
                $bcode = isset($b['code']) ? mb_strtolower((string)$b['code']) : '';
                if ($bid !== '') {
                    if (isset($seenIds[$bid])) return ['success' => false, 'message' => 'Trùng bin_id trong cùng rack'];
                    $seenIds[$bid] = true;
                }
                if ($bcode !== '') {
                    if (isset($seenCodes[$bcode])) return ['success' => false, 'message' => 'Trùng code bin trong cùng rack'];
                    $seenCodes[$bcode] = true;
                }
                // Ensure bin has numeric auto-increment id; use per-warehouse counter when available
                if (!isset($b['id']) || $b['id'] === null || $b['id'] === '') {
                    try {
                        $b['id'] = $this->m->getNextBinId($warehouseId);
                    } catch (\Throwable $e) {
                        // fallback: use timestamp to avoid nulls
                        $b['id'] = time();
                    }
                }
            }
            $r['bins'] = $bins;
        }
        unset($r);

        $ok = $this->m->setRacksForZone($zone_id, $racks);
        return ['success' => (bool)$ok, 'action' => $action];
    }
}

?>
