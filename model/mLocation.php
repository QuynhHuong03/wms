<?php
include_once("connect.php");

class MLocation {

	// Lấy tất cả zone (locations)
	public function getAllLocations() {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$cursor = $col->find([]);
				$results = [];
				foreach ($cursor as $doc) {
					$docArr = json_decode(json_encode($doc), true);
					// If this document contains zones[] (warehouse-level), flatten them into zone records
					if (!empty($docArr['zones']) && is_array($docArr['zones'])) {
						foreach ($docArr['zones'] as $z) {
							$zone = $z;
							// ensure zone_id exists
							$zone['zone_id'] = $zone['zone_id'] ?? ($zone['_id'] ?? null);
							// attach warehouse info
							$zone['warehouse'] = $docArr['warehouse'] ?? [];
							// attach created/updated if not present
							$zone['created_at'] = $zone['created_at'] ?? $docArr['created_at'] ?? null;
							$zone['updated_at'] = $zone['updated_at'] ?? $docArr['updated_at'] ?? null;
							$results[] = $zone;
						}
					} else {
						// legacy single-zone document
						$results[] = $docArr;
					}
				}
				$p->dongKetNoi($con);
				return $results;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	public function getLocationByZoneId($zone_id) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Try legacy per-zone document
				$doc = $col->findOne(['zone_id' => $zone_id]);
				if ($doc) {
					$p->dongKetNoi($con);
					return json_decode(json_encode($doc), true);
				}
				// Otherwise, search within warehouse-level documents' zones[]
				$doc = $col->findOne(['zones.zone_id' => $zone_id]);
				if ($doc) {
					$arr = json_decode(json_encode($doc), true);
					// find the zone inside zones
					foreach ($arr['zones'] as $z) {
						if (($z['zone_id'] ?? $z['_id'] ?? null) == $zone_id) {
							$zone = $z;
							$zone['warehouse'] = $arr['warehouse'] ?? [];
							$zone['created_at'] = $zone['created_at'] ?? $arr['created_at'] ?? null;
							$zone['updated_at'] = $zone['updated_at'] ?? $arr['updated_at'] ?? null;
							$p->dongKetNoi($con);
							return $zone;
						}
					}
				}
				$p->dongKetNoi($con);
				return null;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// New: get a location document by warehouse id (the document that contains zones[])
	public function getLocationByWarehouseId($warehouseId) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$doc = $col->findOne(['warehouse.id' => $warehouseId]);
				$p->dongKetNoi($con);
				return $doc ? json_decode(json_encode($doc), true) : null;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// --- Auto-increment helpers for Bin IDs ---
	// Atomic counter increment using a dedicated 'counters' collection
	public function getNextCounter($key) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('counters');
				// Atomically increment; upsert ensures the doc exists
				$col->updateOne(['_id' => $key], ['$inc' => ['seq' => 1]], ['upsert' => true]);
				$doc = $col->findOne(['_id' => $key]);
				$p->dongKetNoi($con);
				$seq = 0;
				if ($doc) {
					$docArr = json_decode(json_encode($doc), true);
					$seq = isset($docArr['seq']) ? (int)$docArr['seq'] : 0;
				}
				// Fallback if somehow not present
				if ($seq <= 0) $seq = 1;
				return $seq;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				// On error, still return a non-zero id to avoid hard failures
				return time();
			}
		}
		return time();
	}

	// Get next auto-increment Bin ID (per-warehouse if available; global otherwise)
	public function getNextBinId($warehouseId = null) {
		$key = $warehouseId ? ('binid:' . $warehouseId) : 'binid:global';
		return $this->getNextCounter($key);
	}

	// Ensure a bin array has an integer 'id' field; mutate and return the array
	private function ensureBinHasId($bin, $warehouseId = null) {
		if (!is_array($bin)) return $bin;
		if (isset($bin['id'])) {
			if (is_numeric($bin['id'])) {
				$bin['id'] = (int)$bin['id'];
			} else {
				// Non-numeric provided id: override with a valid generated numeric id
				$bin['id'] = $this->getNextBinId($warehouseId);
			}
		} else {
			$bin['id'] = $this->getNextBinId($warehouseId);
		}
		return $bin;
	}

	// Attempt to create unique indexes to prevent duplicate bin ids
	public function ensureUniqueBinIdIndexes() {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Unique per warehouse for nested bins
				$col->createIndex(['warehouse.id' => 1, 'zones.racks.bins.id' => 1], ['unique' => true, 'sparse' => true, 'name' => 'uniq_bin_id_per_warehouse']);
				// Legacy docs (no warehouse.id)
				$col->createIndex(['racks.bins.id' => 1], ['unique' => true, 'sparse' => true, 'name' => 'uniq_legacy_bin_id']);
				$p->dongKetNoi($con);
				return true;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return false;
			}
		}
		return false;
	}

	// Optional: enforce unique zone names per warehouse at DB level
	public function ensureUniqueZoneNameIndex() {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Zones are embedded; we use case-sensitive index. For strict CI uniqueness, application layer enforces lowercased comparisons.
				$col->createIndex(['warehouse.id' => 1, 'zones.name' => 1], ['unique' => true, 'sparse' => true, 'name' => 'uniq_zone_name_per_warehouse']);
				$p->dongKetNoi($con);
				return true;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return false;
			}
		}
		return false;
	}

	// Check if a numeric bin id exists anywhere within a warehouse document
	private function existsBinIdInWarehouse($warehouseId, $binNumericId) {
		if (!$warehouseId || !is_numeric($binNumericId)) return false;
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$doc = $col->findOne([
					'warehouse.id' => $warehouseId,
					'zones.racks.bins.id' => (int)$binNumericId
				]);
				$p->dongKetNoi($con);
				return (bool)$doc;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return false;
			}
		}
		return false;
	}

	// Tạo zone mới (Zone max 3 sẽ được controller kiểm tra trước)
	// signature mở rộng để nhận đầy đủ fields (racks, updated_at...)
	public function addLocation($zone_id, $name, $warehouse, $description, $created_at, $updated_at = null, $racks = []) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// store both _id (for backward compatibility) and zone_id (used in queries/filters)
				$doc = [
					'_id' => $zone_id,
					'zone_id' => $zone_id,
					'name' => $name,
					'warehouse' => $warehouse,
					'description' => $description,
					'created_at' => $created_at,
					'updated_at' => $updated_at ?? $created_at,
					'racks' => $racks
				];
				$res = $col->insertOne($doc);
				$p->dongKetNoi($con);
				return ($res->getInsertedCount() > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				// Log hoặc trả về lỗi nếu cần
			}
		}
		return false;
	}

	// New: create a location document for a warehouse (with zones array)
	public function addLocationForWarehouse($warehouseId, $zones, $name = '', $description = '') {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$now = date(DATE_ATOM);
				$doc = [
					'_id' => 'loc_' . $warehouseId,
					'warehouse' => ['id' => $warehouseId],
					'name' => $name,
					'description' => $description,
					'zones' => $zones,
					'created_at' => $now,
					'updated_at' => $now
				];
				$res = $col->insertOne($doc);
				$p->dongKetNoi($con);
				return ($res->getInsertedCount() > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				// swallow/log
			}
		}
		return false;
	}

	// Add a single zone into warehouse document (push to zones[]). Upsert will create document if missing.
	public function addZoneToWarehouse($warehouseId, $zone) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$now = date(DATE_ATOM);
				// Avoid duplicate zones: push only when there isn't a zone with same zone_id already
				$zoneId = $zone['zone_id'] ?? ($zone['_id'] ?? null);
				if ($zoneId) {
					// Try conditional push into existing doc: only when zones.zone_id != zoneId
					$res = $col->updateOne(
						['warehouse.id' => $warehouseId, 'zones.zone_id' => ['$ne' => $zoneId]],
						['$push' => ['zones' => $zone], '$set' => ['updated_at' => $now, 'name' => 'Location ' . $warehouseId]],
						['upsert' => true]
					);

					$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
					$upserted = 0;
					if (method_exists($res, 'getUpsertedCount')) $upserted = $res->getUpsertedCount();
					// If we modified/created the doc, return true
					if ($modified > 0 || $upserted > 0) { $p->dongKetNoi($con); return true; }

					// Otherwise check if the zone already exists (another writer may have created it)
					$exists = $col->findOne(['warehouse.id' => $warehouseId, 'zones.zone_id' => $zoneId]);
					$p->dongKetNoi($con);
					return (bool)$exists;
				} else {
					// zone has no id; fall back to unconditional push (rare)
					$res = $col->updateOne(
						['warehouse.id' => $warehouseId],
						['$push' => ['zones' => $zone], '$set' => ['updated_at' => $now, 'name' => 'Location ' . $warehouseId]],
						['upsert' => true]
					);
					$p->dongKetNoi($con);
					$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
					$upserted = 0;
					if (method_exists($res, 'getUpsertedCount')) $upserted = $res->getUpsertedCount();
					return ($modified > 0 || $upserted > 0);
				}
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				// swallow/log
			}
		}
		return false;
	}

	// Remove a zone from a warehouse document's zones[] by zone_id
	public function removeZoneFromWarehouse($warehouseId, $zone_id) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// First try pull by zone_id
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$pull' => ['zones' => ['zone_id' => $zone_id]]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				if ($modified > 0) return true;
				// If not removed, try matching by _id field inside zones[]
				$col = $p->moKetNoi()->selectCollection('locations');
				$res2 = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$pull' => ['zones' => ['_id' => $zone_id]]]
				);
				$modified2 = method_exists($res2, 'getModifiedCount') ? $res2->getModifiedCount() : 0;
				$p->dongKetNoi($con);
				return $modified2 > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Remove a bin inside a nested zones[].racks[].bins by warehouseId
	public function removeBinFromWarehouse($warehouseId, $zone_id, $rack_id, $bin_id, $binNumericId = null) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$match = ['bin_id' => $bin_id];
				if ($binNumericId !== null && $binNumericId !== '') {
					$match = ['id' => (int)$binNumericId];
				}
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$pull' => ['zones.$[z].racks.$[r].bins' => $match]],
					['arrayFilters' => [['z.zone_id' => $zone_id], ['r.rack_id' => $rack_id]]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				return $modified > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Remove a bin by numeric id across all zones/racks within a warehouse document
	public function removeBinByNumericIdWarehouse($warehouseId, $binNumericId) {
		if (!$warehouseId || $binNumericId === null || $binNumericId === '') return false;
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$binNumericId = (int)$binNumericId;
				// Try direct $pull using all-positional operator (MongoDB 3.6+)
				try {
					$res = $col->updateOne(
						['warehouse.id' => $warehouseId],
						['$pull' => ['zones.$[].racks.$[].bins' => ['id' => $binNumericId]]]
					);
					$p->dongKetNoi($con);
					if (method_exists($res, 'getModifiedCount') && $res->getModifiedCount() > 0) return true;
				} catch (\Exception $e1) {
					// fall through to RMW
				}

				// Fallback: read-modify-write
				$col = $p->moKetNoi()->selectCollection('locations');
				$doc = $col->findOne(['warehouse.id' => $warehouseId]);
				if (!$doc) { $p->dongKetNoi($con); return false; }
				$arr = json_decode(json_encode($doc), true);
				$zones = isset($arr['zones']) && is_array($arr['zones']) ? $arr['zones'] : [];
				$changed = false;
				for ($i=0; $i<count($zones); $i++) {
					$racks = isset($zones[$i]['racks']) && is_array($zones[$i]['racks']) ? $zones[$i]['racks'] : [];
					for ($j=0; $j<count($racks); $j++) {
						$bins = isset($racks[$j]['bins']) && is_array($racks[$j]['bins']) ? $racks[$j]['bins'] : [];
						$newBins = [];
						foreach ($bins as $b) {
							$bid = isset($b['id']) ? (int)$b['id'] : null;
							if ($bid !== $binNumericId) $newBins[] = $b; else $changed = true;
						}
						$racks[$j]['bins'] = $newBins;
					}
					$zones[$i]['racks'] = $racks;
				}
				if (!$changed) { $p->dongKetNoi($con); return false; }
				$res2 = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$set' => ['zones' => $zones, 'updated_at' => date(DATE_ATOM)]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res2, 'getModifiedCount') ? $res2->getModifiedCount() : 0;
				$matched = method_exists($res2, 'getMatchedCount') ? $res2->getMatchedCount() : 0;
				return ($modified > 0 || $matched > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return false;
			}
		}
		return false;
	}

	// Add bin into nested zones[].racks[].bins; will push into the racks.$.bins when matching zone and rack
	public function addBinToWarehouse($warehouseId, $zone_id, $rack_id, $bin) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Assign auto-increment id if missing and ensure uniqueness within warehouse
				$tries = 0; $maxTries = 5;
				do {
					$bin = $this->ensureBinHasId($bin, $warehouseId);
					$uniqueOk = !$this->existsBinIdInWarehouse($warehouseId, $bin['id']);
					if ($uniqueOk) break;
					$bin['id'] = $this->getNextBinId($warehouseId);
					$tries++;
				} while ($tries < $maxTries);
				// Reject if duplicate bin_id or code already exists in this rack (use nested $elemMatch to avoid cross-element matches)
				$dup = false;
				if (!empty($bin['bin_id'])) {
					$dupId = $col->findOne([
						'warehouse.id' => $warehouseId,
						'zones' => ['$elemMatch' => [
							'zone_id' => $zone_id,
							'racks' => ['$elemMatch' => [
								'rack_id' => $rack_id,
								'bins' => ['$elemMatch' => ['bin_id' => $bin['bin_id']]]
							]]
						]]
					]);
					if ($dupId) $dup = true;
				}
				if (!$dup && isset($bin['code']) && $bin['code'] !== '') {
					$dupCode = $col->findOne([
						'warehouse.id' => $warehouseId,
						'zones' => ['$elemMatch' => [
							'zone_id' => $zone_id,
							'racks' => ['$elemMatch' => [
								'rack_id' => $rack_id,
								'bins' => ['$elemMatch' => ['code' => $bin['code']]]
							]]
						]]
					]);
					if ($dupCode) $dup = true;
				}
				if ($dup) { $p->dongKetNoi($con); return false; }
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$push' => ['zones.$[z].racks.$[r].bins' => $bin]],
					['arrayFilters' => [['z.zone_id' => $zone_id], ['r.rack_id' => $rack_id]]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				return $modified > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Update a bin inside nested zones[].racks[].bins using arrayFilters
	public function updateBinInWarehouse($warehouseId, $zone_id, $rack_id, $bin_id, $binData) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$updatePaths = [];
				foreach ($binData as $k => $v) {
					$updatePaths["zones.$[z].racks.$[r].bins.$[b].$k"] = $v;
				}
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$set' => $updatePaths],
					['arrayFilters' => [['z.zone_id' => $zone_id], ['r.rack_id' => $rack_id], ['b.bin_id' => $bin_id]]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				return $modified > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Fallback: read-modify-write to insert a bin into zones[].racks[].bins for a specific warehouse doc
	// This works even if arrayFilters are not supported or nested positional updates fail
	public function upsertBinIntoWarehouseByRMW($warehouseId, $zone_id, $rack_id, $bin) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$doc = $col->findOne(['warehouse.id' => $warehouseId]);
				if (!$doc) { $p->dongKetNoi($con); return false; }
				$arr = json_decode(json_encode($doc), true);
				$zones = isset($arr['zones']) && is_array($arr['zones']) ? $arr['zones'] : [];
				$zFound = false; $rFound = false;
				// Ensure bin id and uniqueness within warehouse
				$tries = 0; $maxTries = 5;
				do {
					$bin = $this->ensureBinHasId($bin, $warehouseId);
					$uniqueOk = !$this->existsBinIdInWarehouse($warehouseId, $bin['id']);
					if ($uniqueOk) break;
					$bin['id'] = $this->getNextBinId($warehouseId);
					$tries++;
				} while ($tries < $maxTries);
				for ($i = 0; $i < count($zones); $i++) {
					$zid = $zones[$i]['zone_id'] ?? ($zones[$i]['_id'] ?? null);
					if ($zid === $zone_id) {
						$zFound = true;
						$racks = isset($zones[$i]['racks']) && is_array($zones[$i]['racks']) ? $zones[$i]['racks'] : [];
						// find rack
						for ($j = 0; $j < count($racks); $j++) {
							if (($racks[$j]['rack_id'] ?? null) === $rack_id) {
								$rFound = true;
								if (!isset($racks[$j]['bins']) || !is_array($racks[$j]['bins'])) {
									$racks[$j]['bins'] = [];
								}
								// avoid duplicate same bin_id or same code (case-insensitive)
								$exists = false;
								foreach ($racks[$j]['bins'] as $b) {
									$bidEq = (($b['bin_id'] ?? null) === ($bin['bin_id'] ?? null));
									$codeEq = false;
									if (isset($b['code']) && isset($bin['code'])) {
										$codeEq = (mb_strtolower((string)$b['code']) === mb_strtolower((string)$bin['code']));
									}
									if ($bidEq || $codeEq) { $exists = true; break; }
								}
								if (!$exists) { $racks[$j]['bins'][] = $bin; }
								$zones[$i]['racks'] = $racks;
								break;
							}
						}
						// if rack not found, create it and add bin
						if (!$rFound) {
							// Generate friendly rack name from rack_id (e.g., R3 -> Rack 3)
							$friendly = 'Rack ' . $rack_id;
							if (preg_match('/^R(\d+)/i', $rack_id, $mm)) { $friendly = 'Rack ' . intval($mm[1]); }
							$racks[] = [ 'rack_id' => $rack_id, 'name' => $friendly, 'bins' => [$bin] ];
							$zones[$i]['racks'] = $racks;
						}
						break;
					}
				}
				if (!$zFound) { $p->dongKetNoi($con); return false; }
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$set' => ['zones' => $zones, 'updated_at' => date(DATE_ATOM)]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				$matched = method_exists($res, 'getMatchedCount') ? $res->getMatchedCount() : 0;
				return ($modified > 0 || $matched > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Cập nhật zone
	public function updateLocation($zone_id, $data) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$data['updated_at'] = date(DATE_ATOM);
				$updateResult = $col->updateOne(['zone_id' => $zone_id], ['$set' => $data]);
				$p->dongKetNoi($con);
				return $updateResult->getModifiedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Xóa zone
	public function deleteLocation($zone_id) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$del = $col->deleteOne(['zone_id' => $zone_id]);
				$p->dongKetNoi($con);
				return $del->getDeletedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Thêm rack vào zone (enforce rack limit in controller)
	public function addRack($zone_id, $rack_id, $name) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Build rack object
				$rack = [ 'rack_id' => $rack_id, 'name' => $name, 'bins' => [] ];
				// First: quick existence check across both legacy and warehouse-level shapes
				$exists = $col->findOne([
					'$or' => [
						['zone_id' => $zone_id, 'racks.rack_id' => $rack_id],
						['zones.zone_id' => $zone_id, 'zones.racks.rack_id' => $rack_id]
					]
				]);
				if ($exists) { $p->dongKetNoi($con); return true; }

				// Prefer adding into warehouse-level document if present
				// 3.6-compatible: use positional operator with $elemMatch (broad support)
				$resWarehouse = $col->updateOne(
					['zones' => ['$elemMatch' => ['zone_id' => $zone_id]]],
					['$push' => ['zones.$.racks' => $rack], '$set' => ['updated_at' => date(DATE_ATOM)]]
				);
				if (method_exists($resWarehouse, 'getModifiedCount') && $resWarehouse->getModifiedCount() > 0) { $p->dongKetNoi($con); return true; }

				// Fallback: try arrayFilters variant in case driver needs it
				$resWarehouseAF = $col->updateOne(
					['zones.zone_id' => $zone_id],
					['$push' => ['zones.$[z].racks' => $rack], '$set' => ['updated_at' => date(DATE_ATOM)]],
					['arrayFilters' => [['z.zone_id' => $zone_id]]]
				);
				if (method_exists($resWarehouseAF, 'getModifiedCount') && $resWarehouseAF->getModifiedCount() > 0) { $p->dongKetNoi($con); return true; }

				// If warehouse-level insert didn't happen, try legacy per-zone document
				$resLegacy = $col->updateOne(
					['zone_id' => $zone_id, 'racks.rack_id' => ['$ne' => $rack_id]],
					['$push' => ['racks' => $rack]]
				);
				if (method_exists($resLegacy, 'getModifiedCount') && $resLegacy->getModifiedCount() > 0) { $p->dongKetNoi($con); return true; }

				// Final existence check (in case of races) across both structures
				$final = $col->findOne([
					'$or' => [
						['zone_id' => $zone_id, 'racks.rack_id' => $rack_id],
						['zones.zone_id' => $zone_id, 'zones.racks.rack_id' => $rack_id]
					]
				]);
				$p->dongKetNoi($con);
				return (bool)$final;

			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Thêm rack vào zone trong document warehouse cụ thể (tránh ghi nhầm vào legacy doc)
	public function addRackToWarehouse($warehouseId, $zone_id, $rack_id, $name) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$rack = [ 'rack_id' => $rack_id, 'name' => $name, 'bins' => [] ];
				// Existence check within this warehouse doc only
				$exists = $col->findOne([
					'warehouse.id' => $warehouseId,
					'zones.zone_id' => $zone_id,
					'zones.racks.rack_id' => $rack_id
				]);
				if ($exists) { $p->dongKetNoi($con); return true; }

				// Try positional operator first
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId, 'zones' => ['$elemMatch' => ['zone_id' => $zone_id]]],
					['$push' => ['zones.$.racks' => $rack], '$set' => ['updated_at' => date(DATE_ATOM)]]
				);
				if (method_exists($res, 'getModifiedCount') && $res->getModifiedCount() > 0) { $p->dongKetNoi($con); return true; }

				// Fallback: arrayFilters
				$resAF = $col->updateOne(
					['warehouse.id' => $warehouseId, 'zones.zone_id' => $zone_id],
					['$push' => ['zones.$[z].racks' => $rack], '$set' => ['updated_at' => date(DATE_ATOM)]],
					['arrayFilters' => [['z.zone_id' => $zone_id]]]
				);
				if (method_exists($resAF, 'getModifiedCount') && $resAF->getModifiedCount() > 0) { $p->dongKetNoi($con); return true; }

				// Final existence check
				$final = $col->findOne([
					'warehouse.id' => $warehouseId,
					'zones.zone_id' => $zone_id,
					'zones.racks.rack_id' => $rack_id
				]);
				$p->dongKetNoi($con);
				return (bool)$final;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Remove a rack from a zone inside a warehouse-level locations document
	public function removeRackFromWarehouse($warehouseId, $zone_id, $rack_id) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$pull' => ['zones.$[z].racks' => ['rack_id' => $rack_id]]],
					['arrayFilters' => [['z.zone_id' => $zone_id]]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				return $modified > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return false;
			}
		}
		return false;
	}

	// Xóa rack
	public function deleteRack($zone_id, $rack_id) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$res = $col->updateOne(['zone_id' => $zone_id], ['$pull' => ['racks' => ['rack_id' => $rack_id]]]);
				$p->dongKetNoi($con);
				return $res->getModifiedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Thêm bin vào rack (capacity and bin count validations should be done in controller/frontend)
	public function addBin($zone_id, $rack_id, $bin) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Legacy/zone-level document: use global counter for bin id
				// Ensure numeric
				if (!isset($bin['id']) || !is_numeric($bin['id'])) {
					$bin['id'] = $this->getNextBinId(null);
				} else {
					$bin['id'] = (int)$bin['id'];
				}
				// Reject duplicates in legacy doc by bin_id or code using nested $elemMatch
				$dup = false;
				if (!empty($bin['bin_id'])) {
					$dupId = $col->findOne([
						'zone_id' => $zone_id,
						'racks' => ['$elemMatch' => [
							'rack_id' => $rack_id,
							'bins' => ['$elemMatch' => ['bin_id' => $bin['bin_id']]]
						]]
					]);
					if ($dupId) $dup = true;
				}
				if (!$dup && isset($bin['code']) && $bin['code'] !== '') {
					$dupCode = $col->findOne([
						'zone_id' => $zone_id,
						'racks' => ['$elemMatch' => [
							'rack_id' => $rack_id,
							'bins' => ['$elemMatch' => ['code' => $bin['code']]]
						]]
					]);
					if ($dupCode) $dup = true;
				}
				if ($dup) { $p->dongKetNoi($con); return false; }
				$res = $col->updateOne(
					['zone_id' => $zone_id, 'racks.rack_id' => $rack_id],
					['$push' => ['racks.$.bins' => $bin]]
				);
				$p->dongKetNoi($con);
				return $res->getModifiedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Cập nhật bin (find by zone->rack->bin)
	public function updateBin($zone_id, $rack_id, $bin_id, $binData) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$filter = ['zone_id' => $zone_id, 'racks.rack_id' => $rack_id, 'racks.bins.bin_id' => $bin_id];
				// Build positional path for nested update
				$updatePaths = [];
				foreach ($binData as $k => $v) {
					// rename current_load -> quantity if present
					if ($k === 'current_load') $k = 'quantity';
					$updatePaths["racks.$.bins.$[b].$k"] = $v;
				}
				$res = $col->updateOne($filter, ['$set' => $updatePaths], ['arrayFilters' => [['b.bin_id' => $bin_id]]]);
				$p->dongKetNoi($con);
				return $res->getModifiedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Xóa bin
	public function deleteBin($zone_id, $rack_id, $bin_id, $binNumericId = null) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$match = ['bin_id' => $bin_id];
				if ($binNumericId !== null && $binNumericId !== '') {
					$match = ['id' => (int)$binNumericId];
				}
				$res = $col->updateOne(
					['zone_id' => $zone_id, 'racks.rack_id' => $rack_id],
					['$pull' => ['racks.$.bins' => $match]]
				);
				$p->dongKetNoi($con);
				return $res->getModifiedCount() > 0;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Thay thế toàn bộ racks cho một zone (dùng khi lưu ma trận)
	public function setRacksForZone($zone_id, $racks) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				// Do NOT upsert here - require explicit creation via addLocation.
				$res = $col->updateOne(
					['zone_id' => $zone_id],
					['$set' => ['racks' => $racks, 'updated_at' => date(DATE_ATOM)]]
				);
				$p->dongKetNoi($con);
				// Success if matched or modified. We don't create here (no upsert).
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				$matched = method_exists($res, 'getMatchedCount') ? $res->getMatchedCount() : 0;
				$upserted = 0;
				if (method_exists($res, 'getUpsertedCount')) {
					$upserted = $res->getUpsertedCount();
				} elseif (method_exists($res, 'getUpsertedId')) {
					$uid = $res->getUpsertedId();
					$upserted = $uid ? 1 : 0;
				}
				// Success if matched/modified or an upsert was performed
				return ($modified > 0 || $matched > 0 || $upserted > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// New: replace zones[] for a warehouse document (create if needed)
	public function setZonesForWarehouse($warehouseId, $zones, $upsert = false) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$now = date(DATE_ATOM);
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$set' => ['zones' => $zones, 'updated_at' => $now, 'name' => 'Location ' . $warehouseId]],
					['upsert' => $upsert]
				);
				$p->dongKetNoi($con);
				// consider success if matched or modified
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				$matched = method_exists($res, 'getMatchedCount') ? $res->getMatchedCount() : 0;
				return ($modified > 0 || $matched > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// Normalize rack names for all zones in the warehouse doc: R3 -> Rack 3, 'R4' -> 'Rack 4'
	public function normalizeRackNamesForWarehouse($warehouseId) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$doc = $col->findOne(['warehouse.id' => $warehouseId]);
				if (!$doc) { $p->dongKetNoi($con); return false; }
				$arr = json_decode(json_encode($doc), true);
				$zones = isset($arr['zones']) && is_array($arr['zones']) ? $arr['zones'] : [];
				$changed = false;
				for ($i=0; $i<count($zones); $i++) {
					$racks = isset($zones[$i]['racks']) && is_array($zones[$i]['racks']) ? $zones[$i]['racks'] : [];
					for ($j=0; $j<count($racks); $j++) {
						$rid = $racks[$j]['rack_id'] ?? '';
						$nm = $racks[$j]['name'] ?? '';
						if (!$rid) continue;
						// Compute friendly
						$friendly = 'Rack ' . $rid;
						if (preg_match('/^R(\d+)/i', $rid, $mm)) { $friendly = 'Rack ' . intval($mm[1]); }
						// If name missing, equals raw id, or deviates from friendly pattern, set it
						if (!$nm || strcasecmp($nm, $rid) === 0 || strcasecmp($nm, $friendly) !== 0) {
							$racks[$j]['name'] = $friendly;
							$changed = true;
						}
					}
					$zones[$i]['racks'] = $racks;
				}
				if (!$changed) { $p->dongKetNoi($con); return false; }
				$res = $col->updateOne(
					['warehouse.id' => $warehouseId],
					['$set' => ['zones' => $zones, 'updated_at' => date(DATE_ATOM)]]
				);
				$p->dongKetNoi($con);
				$modified = method_exists($res, 'getModifiedCount') ? $res->getModifiedCount() : 0;
				$matched = method_exists($res, 'getMatchedCount') ? $res->getMatchedCount() : 0;
				return ($modified > 0 || $matched > 0);
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				die("Lỗi query MongoDB: " . $e->getMessage());
			}
		}
		return false;
	}

	// --- Helpers to fetch a single bin (including numeric id) ---
	public function getBinFromWarehouse($warehouseId, $zone_id, $rack_id, $binMatch = []) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$doc = $col->findOne(['warehouse.id' => $warehouseId, 'zones.zone_id' => $zone_id, 'zones.racks.rack_id' => $rack_id]);
				$p->dongKetNoi($con);
				if (!$doc) return null;
				$arr = json_decode(json_encode($doc), true);
				foreach ($arr['zones'] as $z) {
					$zid = $z['zone_id'] ?? ($z['_id'] ?? null);
					if ($zid !== $zone_id) continue;
					$racks = isset($z['racks']) && is_array($z['racks']) ? $z['racks'] : [];
					foreach ($racks as $rk) {
						if (($rk['rack_id'] ?? null) !== $rack_id) continue;
						$bins = isset($rk['bins']) && is_array($rk['bins']) ? $rk['bins'] : [];
						foreach ($bins as $b) {
							$ok = true;
							foreach ($binMatch as $k => $v) {
								if (!isset($b[$k]) || (string)$b[$k] !== (string)$v) { $ok = false; break; }
							}
							if ($ok) return $b;
						}
					}
				}
				return null;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return null;
			}
		}
		return null;
	}

	public function getBinFromZone($zone_id, $rack_id, $binMatch = []) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if ($con) {
			try {
				$col = $con->selectCollection('locations');
				$doc = $col->findOne(['zone_id' => $zone_id, 'racks.rack_id' => $rack_id]);
				$p->dongKetNoi($con);
				if (!$doc) return null;
				$arr = json_decode(json_encode($doc), true);
				$racks = isset($arr['racks']) && is_array($arr['racks']) ? $arr['racks'] : [];
				foreach ($racks as $rk) {
					if (($rk['rack_id'] ?? null) !== $rack_id) continue;
					$bins = isset($rk['bins']) && is_array($rk['bins']) ? $rk['bins'] : [];
					foreach ($bins as $b) {
						$ok = true;
						foreach ($binMatch as $k => $v) {
							if (!isset($b[$k]) || (string)$b[$k] !== (string)$v) { $ok = false; break; }
						}
						if ($ok) return $b;
					}
				}
				return null;
			} catch (\Exception $e) {
				$p->dongKetNoi($con);
				return null;
			}
		}
		return null;
	}
	
	// Update bin quantity in warehouse_structure
	public function updateBinQuantity($warehouseId, $zoneId, $rackId, $binId, $qtyChange) {
		$p = new clsKetNoi();
		$con = $p->moKetNoi();
		if (!$con) return false;
		
		try {
			$col = $con->selectCollection('locations');
			
			// Find the location document for this warehouse
			$doc = $col->findOne(['warehouse_id' => $warehouseId]);
			if (!$doc) {
				error_log("updateBinQuantity: Location not found for warehouse $warehouseId");
				return false;
			}
			
			// Navigate to the specific bin and update quantity
			$zones = $doc['zones'] ?? [];
			$updated = false;
			
			foreach ($zones as $zIdx => $zone) {
				if (($zone['_id'] ?? '') !== $zoneId && ($zone['zone_id'] ?? '') !== $zoneId) continue;
				
				$racks = $zone['racks'] ?? [];
				foreach ($racks as $rIdx => $rack) {
					if (($rack['rack_id'] ?? '') !== $rackId) continue;
					
					$bins = $rack['bins'] ?? [];
					foreach ($bins as $bIdx => $bin) {
						if (($bin['bin_id'] ?? ($bin['id'] ?? '')) !== $binId) continue;
						
						// Update quantity
						$currentQty = $bin['quantity'] ?? ($bin['current_load'] ?? 0);
						$newQty = max(0, $currentQty + $qtyChange);
						
						$zones[$zIdx]['racks'][$rIdx]['bins'][$bIdx]['quantity'] = $newQty;
						$updated = true;
						
						error_log("Updated bin $binId quantity: $currentQty + $qtyChange = $newQty");
						break 3;
					}
				}
			}
			
			if ($updated) {
				$result = $col->updateOne(
					['warehouse_id' => $warehouseId],
					['$set' => ['zones' => $zones]]
				);
				return $result->getModifiedCount() > 0;
			}
			
			return false;
		} catch (\Exception $e) {
			error_log('updateBinQuantity error: ' . $e->getMessage());
			return false;
		} finally {
			$p->dongKetNoi($con);
		}
	}
}

?>
