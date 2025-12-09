<?php
include_once(__DIR__ . '/../model/mProduct.php');
include_once(__DIR__ . '/../model/mReceipt.php');
include_once(__DIR__ . '/../model/mRequest.php');
include_once(__DIR__ . '/../model/mWarehouse.php');
include_once(__DIR__ . '/../model/mInventory.php');
include_once(__DIR__ . '/../model/mCategories.php');

class CDashboard {
	private $mProduct;
	private $mReceipt;
	private $mRequest;
	private $mWarehouse;
	private $mInventory;
	private $mCategories;

	public function __construct() {
		$this->mProduct = new MProduct();
		$this->mReceipt = new MReceipt();
		$this->mRequest = new MRequest();
		$this->mWarehouse = new MWarehouse();
		$this->mInventory = new MInventory();
		$this->mCategories = new MCategories();
	}

	public function getDashboardData($roleId = null, $warehouseId = null) {
		// Admin: Toàn hệ thống
		if ($roleId == 1 || $roleId === null) {
			return $this->getAdminDashboard();
		}
		// Manager: Theo kho
		elseif ($roleId == 2) {
			return $this->getManagerDashboard($warehouseId);
		}
		// Staff: Theo cá nhân
		else {
			return $this->getStaffDashboard($warehouseId);
		}
	}

	// Dashboard cho Admin - Toàn hệ thống
	private function getAdminDashboard() {
		return [
			// KPI Cards
			'totalSKU' => $this->getTotalSKU(),
			'totalQty' => $this->getTotalQuantity(),
			'totalValue' => $this->getTotalValue(),
			'lowStockCount' => $this->getLowStockCount(),
			
			// Warehouse Stats
			'totalWarehouses' => $this->getTotalWarehouses(),
			'warehouseUtilization' => $this->getWarehouseUtilization(),
			
			// Transaction Stats
			'receiptsToday' => $this->getReceiptsToday(),
			'exportsToday' => $this->getExportsToday(),
			'pendingRequests' => $this->getPendingRequests(),
			'urgentRequests' => $this->getUrgentRequests(),
			
			// Charts Data
			'receiptExportChart' => $this->getReceiptExportChartData(),
			'categoryDistribution' => $this->getCategoryDistribution(),
			'stockStatusChart' => $this->getStockStatusChart(),
			
			// Recent Activities
			'recentTransactions' => $this->getRecentTransactions(10),
			'topProducts' => $this->getTopMovingProducts(10),
			
			// Alerts
			'alerts' => $this->getSystemAlerts(),
			'lowStockProducts' => $this->getLowStockProducts(10)
			,
			// Per-warehouse summary
			'warehousesSummary' => $this->getWarehousesSummary()
		];
	}

	// Tổng hợp thống kê cho từng kho
	private function getWarehousesSummary() {
		try {
			$warehouses = $this->mWarehouse->getAllWarehouses();
			$result = [];
			foreach ($warehouses as $w) {
				// Accept multiple possible id fields from different data shapes
				$wid = null;
				if (isset($w['warehouse_id'])) $wid = $w['warehouse_id'];
				elseif (isset($w['warehouseId'])) $wid = $w['warehouseId'];
				elseif (isset($w['id'])) $wid = $w['id'];
				elseif (isset($w['_id'])) {
					// _id may be an array like ['$oid' => '...'] or a scalar
					if (is_array($w['_id']) && isset($w['_id']['$oid'])) $wid = $w['_id']['$oid'];
					else $wid = (string)$w['_id'];
				}
				if (!$wid) continue;
				// Get product-level stock in this warehouse
				$stockByProduct = $this->mInventory->getProductsStockByWarehouse($wid);
				$totalSku = count($stockByProduct);
				$totalQty = array_sum($stockByProduct);
				// compute total value by fetching product prices
				$totalValue = 0;
				$lowStockCount = 0;
				foreach ($stockByProduct as $pid => $qty) {
					$prod = $this->mProduct->getProductById($pid);
					$price = 0;
					if ($prod && isset($prod['purchase_price']) && floatval($prod['purchase_price']) > 0) {
						$price = floatval($prod['purchase_price']);
					} else {
						// Fallback: try to get latest receipt that contains this product in this warehouse and use its unit_price
						$latest = $this->mReceipt->getLatestReceiptByProduct($pid, $wid);
						if ($latest) {
							$latestArr = json_decode(json_encode($latest), true);
							// search details/items for this product
							$list = $latestArr['details'] ?? ($latestArr['items'] ?? []);
							foreach ($list as $it) {
								if (($it['product_id'] ?? '') == $pid && isset($it['unit_price'])) {
									$price = floatval($it['unit_price']);
									break;
								}
							}
						}
					}
					$totalValue += ($price * $qty);
					$minStock = isset($prod['min_stock']) ? intval($prod['min_stock']) : 0;
					if ($minStock > 0 && $qty < $minStock) $lowStockCount++;
				}
				// utilization fields may exist on warehouse
				$maxCap = isset($w['max_capacity']) ? floatval($w['max_capacity']) : 0;
				$currentCap = isset($w['current_capacity']) ? floatval($w['current_capacity']) : 0;
				$util = $maxCap > 0 ? round(($currentCap / $maxCap) * 100, 1) : 0;
				$result[] = [
					'warehouse_id' => $wid,
					'name' => isset($w['warehouse_name']) ? $w['warehouse_name'] : (isset($w['name']) ? $w['name'] : (isset($w['warehouseName']) ? $w['warehouseName'] : $wid)),
					'total_sku' => $totalSku,
					'total_qty' => $totalQty,
					'total_value' => $totalValue,
					'low_stock_count' => $lowStockCount,
					'utilization' => $util
				];
			}
			return $result;
		} catch (\Exception $e) {
			error_log('getWarehousesSummary error: ' . $e->getMessage());
			return [];
		}
	}

	// Dashboard cho Manager
	private function getManagerDashboard($warehouseId) {
		try {
			// Use warehouses summary and pick the requested warehouse
			$all = $this->getWarehousesSummary();
			$ws = null;
			foreach ($all as $w) {
				if ($w['warehouse_id'] === $warehouseId) { $ws = $w; break; }
			}
			if (!$ws) {
				// fallback: empty dataset
				return [
					'totalSKU' => 0,
					'totalQty' => 0,
					'totalValue' => 0,
					'lowStockCount' => 0,
					// For manager dashboard, show system-wide active warehouses count
					'totalWarehouses' => $this->getTotalWarehouses(),
					'warehouseUtilization' => 0,
					'receiptExportChart' => ['labels'=>[], 'receipts'=>[], 'exports'=>[]],
					'categoryDistribution' => ['labels'=>[], 'values'=>[]],
					'stockStatusChart' => ['labels'=>[], 'values'=>[]],
					'recentTransactions' => [],
					'topProducts' => [],
					'alerts' => [],
					'lowStockProducts' => [],
					'warehousesSummary' => []
				];
			}
			// Build manager-level payload
			return [
				'totalSKU' => $ws['total_sku'],
				'totalQty' => $ws['total_qty'],
				'totalValue' => $ws['total_value'],
				'lowStockCount' => $ws['low_stock_count'],
				'totalWarehouses' => $this->getTotalWarehouses(),
				'warehouseUtilization' => $ws['utilization'],
				// Pass warehouseId to chart and list builders so manager/staff see per-warehouse data
				'receiptExportChart' => $this->getReceiptExportChartData($warehouseId),
				'categoryDistribution' => $this->getCategoryDistribution($warehouseId),
				'stockStatusChart' => $this->getStockStatusChart($warehouseId),
				'recentTransactions' => $this->getRecentTransactions(10, $warehouseId),
				'topProducts' => $this->getTopMovingProducts(10, $warehouseId),
				'alerts' => $this->getSystemAlerts(),
				'lowStockProducts' => $this->getLowStockProducts(10),
				'warehousesSummary' => [$ws]
			];
		} catch (\Exception $e) {
			error_log('getManagerDashboard error: ' . $e->getMessage());
			return ['message' => 'Error building manager dashboard'];
		}
	}

	// Dashboard cho Staff
	private function getStaffDashboard($warehouseId) {
		// For staff, we provide the same view as manager for their warehouse
		return $this->getManagerDashboard($warehouseId);
	}

	// ============ KPI Methods ============

	private function getTotalSKU() {
		return $this->mProduct->getTotalSKU();
	}

	private function getTotalQuantity() {
		return $this->mProduct->getTotalQuantity();
	}

	private function getTotalValue() {
		try {
			$products = $this->mProduct->getAllProducts();
			$totalValue = 0;
			foreach ($products as $product) {
				$price = isset($product['purchase_price']) ? floatval($product['purchase_price']) : 0;
				$qty = isset($product['quantity']) ? intval($product['quantity']) : 0;
				$totalValue += ($price * $qty);
			}
			return $totalValue;
		} catch (\Exception $e) {
			error_log('getTotalValue error: ' . $e->getMessage());
			return 0;
		}
	}

	private function getLowStockCount() {
		try {
			$products = $this->mProduct->getAllProducts();
			$count = 0;
			foreach ($products as $product) {
				$minStock = isset($product['min_stock']) ? intval($product['min_stock']) : 0;
				$currentStock = isset($product['quantity']) ? intval($product['quantity']) : 0;
				// Count only products that currently have stock > 0 but below min_stock
				if ($minStock > 0 && $currentStock > 0 && $currentStock < $minStock) {
					$count++;
				}
			}
			return $count;
		} catch (\Exception $e) {
			return 0;
		}
	}

	private function getTotalWarehouses() {
		try {
			$warehouses = $this->mWarehouse->getAllWarehouses();
			// Debug: log warehouse ids and status values to help diagnose dashboard count issues
			try {
				$dbgFile = __DIR__ . '/../backups/dashboard_warehouses_debug.log';
				$lines = [];
				$lines[] = "--- getTotalWarehouses called: " . date(DATE_ATOM);
				$lines[] = "warehouses_count=" . (is_array($warehouses) ? count($warehouses) : 'not-array');
				if (is_array($warehouses)) {
					foreach ($warehouses as $w) {
						$wid = $w['warehouse_id'] ?? ($w['id'] ?? ($w['_id']['$oid'] ?? ($w['_id'] ?? '')));
						$s = $w['status'] ?? ($w['is_active'] ?? ($w['active'] ?? 'NULL'));
						$lines[] = "wid=" . (string)$wid . " status=" . json_encode($s);
					}
				}
				@file_put_contents($dbgFile, implode("\n", $lines) . "\n\n", FILE_APPEND);
			} catch (\Exception $e) { /* ignore logging failures */ }
			$active = 0;
			$hasStatusField = false;
			foreach ($warehouses as $w) {
				// status may be stored in different fields/shapes
				$s = $w['status'] ?? ($w['is_active'] ?? ($w['active'] ?? null));
				if ($s !== null) $hasStatusField = true;
				$isActive = false;
				if (is_numeric($s)) {
					if (intval($s) === 1) $isActive = true;
				} elseif (is_bool($s)) {
					if ($s === true) $isActive = true;
				} elseif (is_string($s)) {
					$sl = strtolower($s);
					if (in_array($sl, ['1', 'true', 'active', 'on', 'yes'], true)) $isActive = true;
				}
				if ($isActive) $active++;
			}
			// If no status field was present at all, assume all warehouses are active
			if (!$hasStatusField) return count(is_array($warehouses) ? $warehouses : []);
			return $active;
		} catch (\Exception $e) {
			return 0;
		}
	}

	private function getWarehouseUtilization() {
		try {
			$warehouses = $this->mWarehouse->getAllWarehouses();
			$totalCapacity = 0;
			$totalUsed = 0;
			
			foreach ($warehouses as $w) {
				if (isset($w['status']) && $w['status'] == 1) {
					$maxCap = isset($w['max_capacity']) ? floatval($w['max_capacity']) : 0;
					$currentCap = isset($w['current_capacity']) ? floatval($w['current_capacity']) : 0;
					$totalCapacity += $maxCap;
					$totalUsed += $currentCap;
				}
			}
			
			return $totalCapacity > 0 ? round(($totalUsed / $totalCapacity) * 100, 1) : 0;
		} catch (\Exception $e) {
			return 0;
		}
	}

	// ============ Transaction Stats ============

	private function getReceiptsToday() {
		try {
			$today = date('Y-m-d');
			$receipts = $this->mReceipt->getReceiptsByDateRange($today, $today);
			$count = 0;
			foreach ($receipts as $r) {
				// Normalize type: prefer explicit transaction_type, fallback to type
				$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
				// Ignore exports and other request-like transactions
				if ($tt === 'export' || $tt === 'goods_request') continue;
				$count++;
			}
			return $count;
		} catch (\Exception $e) {
			return 0;
		}
	}

	private function getExportsToday() {
		try {
			$today = date('Y-m-d');
			$receipts = $this->mReceipt->getReceiptsByDateRange($today, $today);
			$count = 0;
			foreach ($receipts as $r) {
				$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
				if ($tt === 'export') {
					$count++;
				}
			}
			return $count;
		} catch (\Exception $e) {
			return 0;
		}
	}

	private function getPendingRequests() {
		try {
			$requests = $this->mRequest->getAllRequests(['status' => ['$in' => [1, 4, 5]]]);
			$count = 0;
			foreach ($requests as $r) {
				$count++;
			}
			return $count;
		} catch (\Exception $e) {
			return 0;
		}
	}

	private function getUrgentRequests() {
		try {
			$requests = $this->mRequest->getAllRequests([
				'priority' => 'urgent',
				'status' => ['$in' => [1, 4, 5]]
			]);
			$count = 0;
			foreach ($requests as $r) {
				$count++;
			}
			return $count;
		} catch (\Exception $e) {
			return 0;
		}
	}

	// ============ Chart Data ============

	private function getReceiptExportChartData($warehouseFilter = null) {
		try {
			// Debug logging to help trace reload/loop issues
			$logFile = dirname(__DIR__) . '/backups/dashboard_chart_debug.log';
			@file_put_contents($logFile, "--- getReceiptExportChartData called: " . date(DATE_ATOM) . "\n", FILE_APPEND);
			@file_put_contents($logFile, "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '') . "\n", FILE_APPEND);
			@file_put_contents($logFile, "GET: " . json_encode($_GET) . "\n", FILE_APPEND);
			
			// Support per-chart filters via GET params: inout_period, inout_warehouse
			$period = array_key_exists('inout_period', $_GET) ? $_GET['inout_period'] : (array_key_exists('period', $_GET) ? $_GET['period'] : '7d');
			// Allow explicit parameter; fall back to GET param when not provided
			if ($warehouseFilter === null) {
				$warehouseFilter = array_key_exists('inout_warehouse', $_GET) ? $_GET['inout_warehouse'] : null;
			}
			$data = ['labels' => [], 'receipts' => [], 'exports' => []];
			
			if ($period === '7d') {
				for ($i = 6; $i >= 0; $i--) {
					$start = date('Y-m-d', strtotime("-$i days"));
					$end = $start;
					$data['labels'][] = date('d/m', strtotime($start));
					$receipts = $this->mReceipt->getReceiptsByDateRange($start, $end);
					$receiptCount = 0; $exportCount = 0;
					foreach ($receipts as $r) {
						$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
						if ($tt === 'goods_request') continue;
							// Skip receipts not belonging to the warehouse when filter provided
							if ($warehouseFilter && isset($r['warehouse_id']) && (string)$r['warehouse_id'] != (string)$warehouseFilter) continue;
						if ($tt === 'export') $exportCount++; else $receiptCount++;
					}
					@file_put_contents($logFile, "7d-bin: start={$start} receipt={$receiptCount} export={$exportCount}\n", FILE_APPEND);
					$data['receipts'][] = $receiptCount;
					$data['exports'][] = $exportCount;
				}
			} elseif ($period === 'week') {
				for ($i = 7; $i >= 0; $i--) {
					$ts = strtotime("-{$i} weeks");
					$start = date('Y-m-d', strtotime('monday this week', $ts));
					$end = date('Y-m-d', strtotime('sunday this week', $ts));
					$data['labels'][] = date('d/m', strtotime($start));
					$receipts = $this->mReceipt->getReceiptsByDateRange($start, $end);
					$receiptCount = 0; $exportCount = 0;
					foreach ($receipts as $r) {
						$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
						if ($tt === 'goods_request') continue;
						if ($warehouseFilter && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouseFilter) continue;
						if ($tt === 'export') $exportCount++; else $receiptCount++;
					}
					@file_put_contents($logFile, "week-bin: start={$start} end={$end} receipt={$receiptCount} export={$exportCount}\n", FILE_APPEND);
					$data['receipts'][] = $receiptCount;
					$data['exports'][] = $exportCount;
				}
			} elseif ($period === 'month') {
				for ($i = 11; $i >= 0; $i--) {
					$ts = strtotime("first day of -$i month");
					$start = date('Y-m-01', $ts);
					$end = date('Y-m-t', $ts);
					$data['labels'][] = date('m/Y', $ts);
					$receipts = $this->mReceipt->getReceiptsByDateRange($start, $end);
					$receiptCount = 0; $exportCount = 0;
					foreach ($receipts as $r) {
						$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
						if ($tt === 'goods_request') continue;
						if ($warehouseFilter && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouseFilter) continue;
						if ($tt === 'export') $exportCount++; else $receiptCount++;
					}
					@file_put_contents($logFile, "month-bin: start={$start} end={$end} receipt={$receiptCount} export={$exportCount}\n", FILE_APPEND);
					$data['receipts'][] = $receiptCount;
					$data['exports'][] = $exportCount;
				}
			} elseif ($period === 'quarter') {
				for ($i = 7; $i >= 0; $i--) {
					$month = date('n') - ($i * 3);
					$year = date('Y');
					while ($month <= 0) { $month += 12; $year -= 1; }
					$start = date('Y-m-d', strtotime("{$year}-{$month}-01"));
					$end = date('Y-m-d', strtotime(date('Y-m-t', strtotime("{$start} +2 months"))));
					$qnum = intval(ceil($month / 3));
					$data['labels'][] = "Q{$qnum} " . date('Y', strtotime($start));
					$receipts = $this->mReceipt->getReceiptsByDateRange($start, $end);
					$receiptCount = 0; $exportCount = 0;
					foreach ($receipts as $r) {
						$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
						if ($tt === 'goods_request') continue;
						if ($warehouseFilter && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouseFilter) continue;
						if ($tt === 'export') $exportCount++; else $receiptCount++;
					}
					@file_put_contents($logFile, "quarter-bin: start={$start} end={$end} receipt={$receiptCount} export={$exportCount}\n", FILE_APPEND);
					$data['receipts'][] = $receiptCount;
					$data['exports'][] = $exportCount;
				}
			} elseif ($period === 'year') {
				// Aggregate by year for the last 5 years
				$numYears = 5;
				for ($i = $numYears - 1; $i >= 0; $i--) {
					$year = date('Y') - $i;
					$start = "$year-01-01";
					$end = "$year-12-31";
					$data['labels'][] = (string)$year;
					$receipts = $this->mReceipt->getReceiptsByDateRange($start, $end);
					$receiptCount = 0; $exportCount = 0;
					foreach ($receipts as $r) {
						$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
						if ($tt === 'goods_request') continue;
						if ($warehouseFilter && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouseFilter) continue;
						if ($tt === 'export') $exportCount++; else $receiptCount++;
					}
					@file_put_contents($logFile, "year-bin: start={$start} end={$end} receipt={$receiptCount} export={$exportCount}\n", FILE_APPEND);
					$data['receipts'][] = $receiptCount;
					$data['exports'][] = $exportCount;
				}
			} else {
				// Fallback: generate last 7 days (avoid recursive call)
				for ($i = 6; $i >= 0; $i--) {
					$start = date('Y-m-d', strtotime("-$i days"));
					$end = $start;
					$data['labels'][] = date('d/m', strtotime($start));
					$receipts = $this->mReceipt->getReceiptsByDateRange($start, $end);
					$receiptCount = 0; $exportCount = 0;
					foreach ($receipts as $r) {
						$tt = $r['transaction_type'] ?? ($r['type'] ?? null);
						if ($tt === 'goods_request') continue;
						if ($warehouseFilter && isset($r['warehouse_id']) && $r['warehouse_id'] != $warehouseFilter) continue;
						if ($tt === 'export') $exportCount++; else $receiptCount++;
					}
					@file_put_contents($logFile, "fallback-bin: start={$start} receipt={$receiptCount} export={$exportCount}\n", FILE_APPEND);
					$data['receipts'][] = $receiptCount;
					$data['exports'][] = $exportCount;
				}

				}

				return $data;
		} catch (\Exception $e) {
			error_log('getReceiptExportChartData error: ' . $e->getMessage());
			return ['labels' => [], 'receipts' => [], 'exports' => []];
		}
	}

	private function getCategoryDistribution($warehouseFilter = null) {
		try {
			$categories = $this->mCategories->getAllCategories();
			$products = $this->mProduct->getAllProducts();
			// Allow explicit parameter; fall back to GET param when not provided
			if ($warehouseFilter === null) {
				$warehouseFilter = array_key_exists('category_warehouse', $_GET) ? $_GET['category_warehouse'] : null;
			}
			$catCount = [];
			foreach ($categories as $cat) {
				// category record may be array or object; normalize to string name
				$catName = 'Unknown';
				if (is_array($cat)) {
					if (isset($cat['category_name'])) $catName = (string)$cat['category_name'];
					elseif (isset($cat['name'])) $catName = (string)$cat['name'];
				} elseif (is_object($cat)) {
					if (isset($cat->category_name)) $catName = (string)$cat->category_name;
					elseif (isset($cat->name)) $catName = (string)$cat->name;
				} elseif (is_string($cat)) {
					$catName = $cat;
				}
				$catCount[$catName] = 0;
			}
			
			$productsInWarehouse = null;
			if ($warehouseFilter && method_exists($this->mInventory, 'getProductsStockByWarehouse')) {
				$productsInWarehouse = $this->mInventory->getProductsStockByWarehouse($warehouseFilter);
				if (!is_array($productsInWarehouse)) $productsInWarehouse = null;
			}
			
			foreach ($products as $p) {
				// If warehouse filter set, skip products not present in that warehouse
				$pid = $p['_id'] ?? ($p['product_id'] ?? null);
				// Normalize pid to scalar string to safely index arrays
				if (is_object($pid)) {
					if (method_exists($pid, '__toString')) $pid = (string)$pid;
					elseif (isset($pid->{'$oid'})) $pid = (string)$pid->{'$oid'};
					else $pid = json_encode($pid);
				} elseif (is_array($pid)) {
					if (isset($pid['$oid'])) $pid = $pid['$oid'];
					else $pid = json_encode($pid);
				}
				if ($productsInWarehouse !== null) {
					if (!$pid || !isset($productsInWarehouse[$pid]) || intval($productsInWarehouse[$pid]) <= 0) continue;
				}
				// product's category may be stored in different shapes (array/object/string)
				$catField = $p['category'] ?? null;
				$catName = null;
				if (is_array($catField) && isset($catField['name'])) {
					$catName = (string)$catField['name'];
				} elseif (is_object($catField) && isset($catField->name)) {
					$catName = (string)$catField->name;
				} elseif (is_string($catField)) {
					$catName = $catField;
				}
				if ($catName !== null && isset($catCount[$catName])) {
					$catCount[$catName]++;
				}
			}
			
			// Sắp xếp giảm dần và lấy top 5
			arsort($catCount);
			$top5 = array_slice($catCount, 0, 5, true);
			
			return [
				'labels' => array_keys($top5),
				'values' => array_values($top5)
			];
		} catch (\Exception $e) {
			return ['labels' => [], 'values' => []];
		}
	}

	// Public wrapper to expose category distribution for AJAX calls
	public function fetchCategoryDistribution($warehouseFilter = null) {
		return $this->getCategoryDistribution($warehouseFilter);
	}

	private function getStockStatusChart($warehouseFilter = null) {
		try {
			// Allow explicit parameter; fall back to GET param when not provided
			if ($warehouseFilter === null) {
				$warehouseFilter = array_key_exists('stock_warehouse', $_GET) ? $_GET['stock_warehouse'] : null;
			}
			$products = $this->mProduct->getAllProducts();
			$stockByProduct = null;
			if ($warehouseFilter && method_exists($this->mInventory, 'getProductsStockByWarehouse')) {
				$stockByProduct = $this->mInventory->getProductsStockByWarehouse($warehouseFilter);
				if (!is_array($stockByProduct)) $stockByProduct = null;
			}
			$outOfStock = 0;
			$lowStock = 0;
			$inStock = 0;
			$overStock = 0;
			
			foreach ($products as $p) {
				// Use warehouse-specific qty when available
				$pid = $p['_id'] ?? ($p['product_id'] ?? null);
				// Normalize pid to a scalar string so it can be used as array key safely
				$pidKey = $pid;
				if (is_object($pidKey)) {
					if (method_exists($pidKey, '__toString')) $pidKey = (string)$pidKey;
					elseif (isset($pidKey->{'$oid'})) $pidKey = (string)$pidKey->{'$oid'};
					else $pidKey = json_encode($pidKey);
				} elseif (is_array($pidKey)) {
					if (isset($pidKey['$oid'])) $pidKey = $pidKey['$oid'];
					else $pidKey = json_encode($pidKey);
				} else {
					$pidKey = (string)$pidKey;
				}

				if ($stockByProduct !== null && $pidKey !== '') {
					$qty = isset($stockByProduct[$pidKey]) ? intval($stockByProduct[$pidKey]) : 0;
				} else {
					$qty = isset($p['quantity']) ? intval($p['quantity']) : 0;
				}
				$minStock = isset($p['min_stock']) ? intval($p['min_stock']) : 0;
				
				if ($qty == 0) {
					$outOfStock++;
				} elseif ($minStock > 0 && $qty < $minStock) {
					$lowStock++;
				} elseif ($minStock > 0 && $qty >= ($minStock * 3)) {
					$overStock++;
				} else {
					$inStock++;
				}
			}
			
			return [
				'labels' => ['Hết hàng', 'Sắp hết', 'Đủ hàng', 'Dư thừa'],
				'values' => [$outOfStock, $lowStock, $inStock, $overStock]
			];
		} catch (\Exception $e) {
			return ['labels' => [], 'values' => []];
		}
	}

	// ============ Recent Activities ============

	private function getRecentTransactions($limit = 10, $warehouseFilter = null) {
		try {
			// Allow optional warehouse filter to return only transactions for that warehouse
			$receipts = $this->mReceipt->getAllReceiptsWithUserInfo([]);
			$result = [];
			$count = 0;
			
			foreach ($receipts as $r) {
				// If warehouse filter provided, skip any receipts that belong to another warehouse
				if ($warehouseFilter && isset($r['warehouse_id']) && (string)$r['warehouse_id'] !== (string)$warehouseFilter) continue;
				if ($count >= $limit) break;

				$result[] = [
					'transaction_id' => $r['transaction_id'] ?? '',
					'type' => $r['transaction_type'] ?? 'receipt',
					'created_at' => $r['created_at'] ?? null,
					'created_by' => $r['creator_name'] ?? ($r['created_by'] ?? ''),
					'status' => $r['status'] ?? 0,
					'warehouse_id' => $r['warehouse_id'] ?? null
				];
				$count++;
			}
			
			return $result;
		} catch (\Exception $e) {
			return [];
		}
	}

	private function getTopMovingProducts($limit = 10, $warehouseFilter = null) {
		try {
			// Đếm số lượng di chuyển dựa trên trường 'details' hoặc 'items'
			$receipts = $this->mReceipt->getAllReceipts([]);
			$productCount = [];
			foreach ($receipts as $r) {
				// If warehouse filter provided, skip receipts from other warehouses
				if ($warehouseFilter && isset($r['warehouse_id']) && (string)$r['warehouse_id'] !== (string)$warehouseFilter) continue;
				// Normalize MongoDB document to PHP array
				$ra = json_decode(json_encode($r), true);
				$list = [];
				if (isset($ra['details']) && is_array($ra['details'])) {
					$list = $ra['details'];
				} elseif (isset($ra['items']) && is_array($ra['items'])) {
					$list = $ra['items'];
				}
				foreach ($list as $item) {
					$productId = $item['product_id'] ?? '';
					$qty = 0;
					if (isset($item['quantity'])) $qty = intval($item['quantity']);
					elseif (isset($item['qty'])) $qty = intval($item['qty']);
					if ($productId) {
						if (!isset($productCount[$productId])) $productCount[$productId] = ['qty' => 0, 'sku' => '', 'name' => ''];
						$productCount[$productId]['qty'] += $qty;
						$productCount[$productId]['sku'] = $item['sku'] ?? ($productCount[$productId]['sku'] ?? '');
						$productCount[$productId]['name'] = $item['product_name'] ?? ($productCount[$productId]['name'] ?? '');
					}
				}
			}
			// Sắp xếp theo số lượng giảm dần
			uasort($productCount, function($a, $b) { return $b['qty'] - $a['qty']; });
			return array_slice($productCount, 0, $limit, true);
		} catch (\Exception $e) {
			return [];
		}
	}

	// ============ Alerts ============

	private function getSystemAlerts() {
		$alerts = [];
		
		try {
			// Cảnh báo urgent requests
			$urgentCount = $this->getUrgentRequests();
			if ($urgentCount > 0) {
				$alerts[] = [
					'type' => 'danger',
					'icon' => '🚨',
					'message' => "Có {$urgentCount} phiếu yêu cầu URGENT cần xử lý ngay!"
				];
			}
			
			// Cảnh báo low stock
			$lowStockCount = $this->getLowStockCount();
			if ($lowStockCount > 0) {
				$alerts[] = [
					'type' => 'warning',
					'icon' => '⚠️',
					'message' => "{$lowStockCount} sản phẩm sắp hết hàng"
				];
			}
			
			// Cảnh báo warehouse utilization
			$utilization = $this->getWarehouseUtilization();
			if ($utilization > 90) {
				$alerts[] = [
					'type' => 'warning',
					'icon' => '📦',
					'message' => "Công suất kho đạt {$utilization}% - Cần mở rộng!"
				];
			}
			
			// Cảnh báo pending requests
			$pendingCount = $this->getPendingRequests();
			if ($pendingCount > 5) {
				$alerts[] = [
					'type' => 'info',
					'icon' => '📋',
					'message' => "Có {$pendingCount} phiếu yêu cầu chờ xử lý"
				];
			}
			
			if (empty($alerts)) {
				$alerts[] = [
					'type' => 'success',
					'icon' => '✅',
					'message' => 'Hệ thống hoạt động bình thường'
				];
			}
			
		} catch (\Exception $e) {
			error_log('getSystemAlerts error: ' . $e->getMessage());
		}
		
		return $alerts;
	}

	private function getLowStockProducts($limit = 10) {
		try {
			$products = $this->mProduct->getAllProducts();
			$lowStock = [];
			
			foreach ($products as $p) {
				$minStock = isset($p['min_stock']) ? intval($p['min_stock']) : 0;
				$currentStock = isset($p['quantity']) ? intval($p['quantity']) : 0;
				
				// Only include products that currently have some stock ( > 0 ) but below min_stock
				if ($minStock > 0 && $currentStock > 0 && $currentStock < $minStock) {
					$shortage = $minStock - $currentStock;
					$shortagePercent = ($shortage / $minStock) * 100;
					
					$lowStock[] = [
						'sku' => $p['sku'] ?? '',
						'name' => $p['product_name'] ?? '',
						'current_stock' => $currentStock,
						'min_stock' => $minStock,
						'shortage' => $shortage,
						'shortage_percent' => round($shortagePercent, 1)
					];
				}
			}
			
			// Sắp xếp theo shortage_percent giảm dần
			usort($lowStock, function($a, $b) {
				return $b['shortage_percent'] - $a['shortage_percent'];
			});
			
			return array_slice($lowStock, 0, $limit);
		} catch (\Exception $e) {
			return [];
		}
	}
}
