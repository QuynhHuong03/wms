<?php
include_once(__DIR__ . '/../model/mProduct.php');

class CDashboard {
	private $mProduct;

	public function __construct() {
		$this->mProduct = new MProduct();
	}

	public function getDashboardData() {
		return [
			'totalSKU' => $this->mProduct->getTotalSKU(),
			'totalQty' => $this->mProduct->getTotalQuantity(),
			// 'totalValue' => $this->mProduct->getTotalValue(),
			// 'lowStock' => $this->mProduct->getLowStockCount()
		];
	}
}
