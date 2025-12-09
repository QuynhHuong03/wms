<?php
include_once(__DIR__ . '/../model/mInventory.php');

class CInventory {
	private function currentWarehouseId() {
		// Standardize how we read warehouse from session
		if (session_status() === PHP_SESSION_NONE) session_start();
		return $_SESSION['login']['warehouse_id'] ?? $_SESSION['login']['warehouse'] ?? ($_SESSION['warehouse_id'] ?? '');
	}

	public function getInventoryList($params = []) {
		$m = new MInventory();

		$page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
		$limit = isset($params['limit']) ? max(1, min(200, intval($params['limit']))) : 20;

		$filters = [
			'q' => $params['q'] ?? '',
			// Accept warehouse_id from params if provided, otherwise use current warehouse
			'warehouse_id' => !empty($params['warehouse_id']) ? $params['warehouse_id'] : $this->currentWarehouseId(),
			'from' => $params['from'] ?? '',
			'to' => $params['to'] ?? '',
			'product_id' => $params['product_id'] ?? '',
			'product_sku' => $params['product_sku'] ?? ''
		];

		$items = $m->listEntries($filters, $page, $limit);
		$total = $m->countEntries($filters);

		return [
			'items' => $items,
			'page' => $page,
			'limit' => $limit,
			'total' => $total,
			'pages' => $limit > 0 ? (int)ceil($total / $limit) : 1
		];
	}

	// Grouped by product view
	public function getInventoryGroupedByProduct($params = []) {
		$m = new MInventory();
		$page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
		$limit = isset($params['limit']) ? max(1, min(200, intval($params['limit']))) : 20;
		$filters = [
			'q' => $params['q'] ?? '',
			// Accept warehouse_id from params if provided, otherwise use current warehouse
			'warehouse_id' => !empty($params['warehouse_id']) ? $params['warehouse_id'] : $this->currentWarehouseId(),
			'from' => $params['from'] ?? '',
			'to' => $params['to'] ?? '',
			'product_id' => $params['product_id'] ?? '',
			'product_sku' => $params['product_sku'] ?? ''
		];
		$sort = ['lastTime' => -1];
		$groups = $m->groupByProduct($filters, $page, $limit, $sort);
		$total = $m->countGroupsByProduct($filters);
		return [
			'items' => $groups,
			'page' => $page,
			'limit' => $limit,
			'total' => $total,
			'pages' => $limit > 0 ? (int)ceil($total / $limit) : 1
		];
	}

	// Details for a specific product (for modal)
	public function getInventoryDetailsForProduct($productIdOrSku, $params = []) {
		$m = new MInventory();
		$filters = [
			'warehouse_id' => $this->currentWarehouseId(),
		];
		// allow id or sku
		if (preg_match('/^[0-9a-f]{24}$/i', $productIdOrSku)) {
			$filters['product_id'] = $productIdOrSku;
		} else {
			$filters['product_sku'] = $productIdOrSku;
		}
		$filters['from'] = $params['from'] ?? '';
		$filters['to'] = $params['to'] ?? '';
		$page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
		$limit = isset($params['limit']) ? max(1, min(200, intval($params['limit']))) : 50;
		$items = $m->listEntries($filters, $page, $limit, ['received_at' => -1]);
		$total = $m->countEntries($filters);
		return [
			'items' => $items,
			'page' => $page,
			'limit' => $limit,
			'total' => $total,
			'pages' => $limit > 0 ? (int)ceil($total / $limit) : 1
		];
	}

	// Per-bin stock distribution for a product
	public function getBinDistributionForProduct($productIdOrSku, $params = []) {
		$m = new MInventory();
		$filters = [
			'warehouse_id' => $this->currentWarehouseId(),
		];
		if (preg_match('/^[0-9a-f]{24}$/i', $productIdOrSku)) {
			$filters['product_id'] = $productIdOrSku;
		} else {
			$filters['product_sku'] = $productIdOrSku;
		}
		$filters['from'] = $params['from'] ?? '';
		$filters['to'] = $params['to'] ?? '';
		$byBin = $m->aggregateByBin($filters, ['qty' => -1]);
		return $byBin;
	}

	// ⭐ Lấy tổng tồn kho của sản phẩm tại một kho
	public function getTotalStockByProduct($warehouseId, $productId) {
		$m = new MInventory();
		return $m->getTotalStockByProduct($warehouseId, $productId);
	}

	// ⭐ Lấy tồn kho của sản phẩm tại tất cả các kho
	public function getStockByProductAllWarehouses($productId) {
		$m = new MInventory();
		return $m->getStockByProductAllWarehouses($productId);
	}

	// ⭐ Lấy tất cả sản phẩm có tồn kho tại một kho
	public function getProductsStockByWarehouse($warehouseId) {
		$m = new MInventory();
		return $m->getProductsStockByWarehouse($warehouseId);
	}

	// ⭐ Tìm các kho chi nhánh có đủ hàng (sau khi trừ vẫn > min_stock)
	public function findSufficientWarehouses($requestDetails, $excludeWarehouseId = null) {
		$m = new MInventory();
		return $m->findSufficientWarehouses($requestDetails, $excludeWarehouseId);
	}
}
?>
