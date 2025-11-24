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
		];
	}

	// Dashboard cho Manager
	private function getManagerDashboard($warehouseId) {
		// TODO: Implement manager dashboard
		return ['message' => 'Manager dashboard - Coming soon'];
	}

	// Dashboard cho Staff
	private function getStaffDashboard($warehouseId) {
		// TODO: Implement staff dashboard
		return ['message' => 'Staff dashboard - Coming soon'];
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
				if ($minStock > 0 && $currentStock < $minStock) {
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
			$active = 0;
			foreach ($warehouses as $w) {
				if (isset($w['status']) && $w['status'] == 1) {
					$active++;
				}
			}
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
				if (isset($r['transaction_type']) && $r['transaction_type'] == 'export') {
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

	private function getReceiptExportChartData() {
		try {
			$data = ['labels' => [], 'receipts' => [], 'exports' => []];
			
			// Lấy dữ liệu 7 ngày gần nhất
			for ($i = 6; $i >= 0; $i--) {
				$date = date('Y-m-d', strtotime("-$i days"));
				$data['labels'][] = date('d/m', strtotime($date));
				
				$receipts = $this->mReceipt->getReceiptsByDateRange($date, $date);
				$receiptCount = 0;
				$exportCount = 0;
				
				foreach ($receipts as $r) {
					if (isset($r['transaction_type']) && $r['transaction_type'] == 'export') {
						$exportCount++;
					} else {
						$receiptCount++;
					}
				}
				
				$data['receipts'][] = $receiptCount;
				$data['exports'][] = $exportCount;
			}
			
			return $data;
		} catch (\Exception $e) {
			error_log('getReceiptExportChartData error: ' . $e->getMessage());
			return ['labels' => [], 'receipts' => [], 'exports' => []];
		}
	}

	private function getCategoryDistribution() {
		try {
			$categories = $this->mCategories->getAllCategories();
			$products = $this->mProduct->getAllProducts();
			
			$catCount = [];
			foreach ($categories as $cat) {
				$catId = isset($cat['category_id']) ? $cat['category_id'] : '';
				$catName = isset($cat['category_name']) ? $cat['category_name'] : 'Unknown';
				$catCount[$catName] = 0;
			}
			
			foreach ($products as $p) {
				if (isset($p['category']['name'])) {
					$catName = $p['category']['name'];
					if (isset($catCount[$catName])) {
						$catCount[$catName]++;
					}
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

	private function getStockStatusChart() {
		try {
			$products = $this->mProduct->getAllProducts();
			$outOfStock = 0;
			$lowStock = 0;
			$inStock = 0;
			$overStock = 0;
			
			foreach ($products as $p) {
				$qty = isset($p['quantity']) ? intval($p['quantity']) : 0;
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

	private function getRecentTransactions($limit = 10) {
		try {
			$receipts = $this->mReceipt->getAllReceiptsWithUserInfo([]);
			$result = [];
			$count = 0;
			
			foreach ($receipts as $r) {
				if ($count >= $limit) break;
				
				$result[] = [
					'transaction_id' => $r['transaction_id'] ?? '',
					'type' => $r['transaction_type'] ?? 'receipt',
					'created_at' => $r['created_at'] ?? null,
					'created_by' => $r['creator_name'] ?? ($r['created_by'] ?? ''),
					'status' => $r['status'] ?? 0
				];
				$count++;
			}
			
			return $result;
		} catch (\Exception $e) {
			return [];
		}
	}

	private function getTopMovingProducts($limit = 10) {
		try {
			// Đếm số lần xuất hiện của product trong receipts
			$receipts = $this->mReceipt->getAllReceipts([]);
			$productCount = [];
			
			foreach ($receipts as $r) {
				if (isset($r['items']) && is_array($r['items'])) {
					foreach ($r['items'] as $item) {
						$productId = $item['product_id'] ?? '';
						$qty = isset($item['quantity']) ? intval($item['quantity']) : 0;
						
						if ($productId) {
							if (!isset($productCount[$productId])) {
								$productCount[$productId] = ['qty' => 0, 'sku' => '', 'name' => ''];
							}
							$productCount[$productId]['qty'] += $qty;
							$productCount[$productId]['sku'] = $item['sku'] ?? '';
							$productCount[$productId]['name'] = $item['product_name'] ?? '';
						}
					}
				}
			}
			
			// Sắp xếp theo số lượng giảm dần
			uasort($productCount, function($a, $b) {
				return $b['qty'] - $a['qty'];
			});
			
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
				
				if ($minStock > 0 && $currentStock < $minStock) {
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
