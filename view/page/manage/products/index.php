<?php
include_once(__DIR__ . "/../../../../controller/cProduct.php");
$cProduct = new CProduct();

$products = $cProduct->getAllProducts();
?>

<!DOCTYPE html>
<html>
<head>
	<title>Quản lý sản phẩm</title>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
	<style>
		body {
			font-family: Arial, sans-serif;
			background-color: #f9f9f9;
			margin: 0;
			padding: 0;
		}
		.container.qlproduct {
			width: 100%;
			max-width: 1400px;
			margin: 20px auto;
			background: #ffffff;
			border-radius: 10px;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			padding: 20px;
		}
		button {
			border: none;
			border-radius: 10px;
			cursor: pointer;
			background-color: #3b82f6;
			color: white;
			font-weight: bold;
			transition: background-color 0.3s ease;
		}
		button a {
			text-decoration: none;
			color: white;
		}
		.header-product {
			display: flex;
			justify-content: space-between;
			align-items: center;
			flex-wrap: wrap;
			margin: 20px 0;
		}
		.header-left h3 {
			margin: 0;
			color: #333;
		}
		.header-left p {
			margin: 4px 0 0;
			color: #666;
			font-size: 14px;
		}
		.header-right {
			display: flex;
			align-items: center;
			gap: 10px;
			flex-wrap: wrap;
		}
		.qlproduct-search-container {
			position: relative;
		}
		.qlproduct-search {
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 8px 16px;
			border-radius: 8px;
			border: 1px solid #ddd;
			background: #fff;
			width: 400px;
		}
		.qlproduct-search i {
			color: #666;
			font-size: 18px;
		}
		.qlproduct-search input {
			border: none;
			outline: none;
			font-size: 14px;
			padding: 4px 6px;
			flex: 1;
		}
		#searchResult {
			position: absolute;
			top: 110%;
			left: 0;
			right: 0;
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 8px;
			box-shadow: 0 4px 6px rgba(0,0,0,0.1);
			max-height: 250px;
			overflow-y: auto;
			display: none;
			z-index: 99;
		}
		.btn-add-product {
			background: #3b82f6;
			color: white;
			border: none;
			border-radius: 8px;
			padding: 8px 16px;
			cursor: pointer;
			font-weight: bold;
			transition: background 0.3s;
		}
		.btn-add-product a {
			color: white;
			text-decoration: none;
		}
		.btn-add-product:hover {
			background: #2563eb;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 20px;
		}
		thead {
			background-color: #3b82f6;
			color: white;
			font-size: 16px;
			text-align: left;
		}
		thead th {
			padding: 10px;
			position: sticky;
			top: 0;
			z-index: 10;
		}
		tbody tr:nth-child(odd) {
			background-color: #f2f2f2;
		}
		tbody tr:nth-child(even) {
			background-color: #ffffff;
		}
		tbody td {
			padding: 10px;
			border-bottom: 1px solid #ddd;
		}
		.status-active, .status-inactive {
			font-weight: bold;
			padding: 4px 8px;
			border-radius: 6px;
		}
		.status-active {
			color: #22c55e;
			background: #e7fbe7;
		}
		.status-inactive {
			color: #ef4444;
			background: #fde7e7;
		}
	</style>
</head>
<body>
	<div class="header-product">
		<div class="header-left">
			<h3>Quản lý sản phẩm</h3>
			<p>Danh sách sản phẩm trong kho</p>
		</div>
		<div class="header-right">
			<div class="qlproduct-search-container">
				<div class="qlproduct-search">
					<i class="fas fa-search"></i>
					<input type="text" id="searchInput" placeholder="Tìm kiếm sản phẩm...">
				</div>
				<div id="searchResult"></div>
			</div>
			<button class="btn-add-product"><a href="index.php?page=products/createProduct">+ Thêm sản phẩm</a></button>
		</div>
	</div>
	<div class="container qlproduct">
		<?php
		if (is_array($products) && !empty($products)) {
			echo '<table><thead><tr>';
			echo '<th>Hình ảnh</th><th>Mã SKU</th><th>Tên sản phẩm</th><th>Barcode</th><th>Loại</th><th>Nhà cung cấp</th><th>Tồn kho tối thiểu</th><th>Trạng thái</th><th>Thao tác</th>';
			echo '</tr></thead><tbody>';
			require_once(__DIR__ . '/../../../../vendor/picqer/php-barcode-generator/src/BarcodeGeneratorPNG.php');
			$barcodeGenerator = new Picqer\Barcode\BarcodeGeneratorPNG();
			foreach ($products as $product) {
				$id = $product['sku'];
				$name = $product['product_name'];
				$barcode = $product['barcode'];
				$type = $product['category_name'] ?? '';
				$supplier = $product['supplier_name'] ?? '';
				// $warehouse = $product['warehouse_id'] ?? '';
				$min_stock = $product['min_stock'] ?? '';
				$status = $product['status'];
				// Hình ảnh sản phẩm: lấy theo sku hoặc trường image
				$imageFile = isset($product['image']) ? $product['image'] : ($id ? $id . '.jpg' : '');
				$imagePath = $imageFile ? "../../../img/{$imageFile}" : "";
				$imageTag = $imagePath ? "<img src='{$imagePath}' alt='Ảnh sản phẩm' style='width:60px;height:60px;border-radius:8px;object-fit:cover;'>" : "";
				// Barcode hình ảnh
				$barcodeImg = '';
				if ($barcode) {
					$barcodeBinary = $barcodeGenerator->getBarcode($barcode, $barcodeGenerator::TYPE_CODE_128, 2, 40);
					$barcodeBase64 = base64_encode($barcodeBinary);
					$barcodeImg = "<div style='display:flex;flex-direction:column;align-items:center;'>"
						. "<img src='data:image/png;base64,{$barcodeBase64}' alt='Barcode cho {$barcode}' style='height:40px;'>"
						. "<div style='font-size:13px;font-family:monospace;color:#222;margin-top:2px;background:#f4f6f8;padding:2px 8px;border-radius:4px;'>{$barcode}</div>"
						. "</div>";
				}
				// Thao tác
				$actionBtns = "<a href='index.php?page=products/updateProduct&id={$id}' title='Sửa' style='margin-right:10px; color:##3b82f6;'><i class='fas fa-edit'></i></a>";
				$actionBtns .= "<a href='#' class='btn-delete' data-id='{$id}' title='Xóa' style='margin-left:10px; color:red;'><i class='fas fa-trash-alt'></i></a>";
				echo "<tr>";
				echo "<td>{$imageTag}</td><td>{$id}</td><td>{$name}</td><td>{$barcodeImg}</td><td>{$type}</td><td>{$supplier}</td><td>{$min_stock}</td><td>" . ($status == 1 ? '<span class=\'status-active\'>Đang bán</span>' : '<span class=\'status-inactive\'>Ngừng bán</span>') . "</td><td>{$actionBtns}</td></tr>";
			}
			echo '</tbody></table>';
		} else {
			echo '<div class="no-result">Không có sản phẩm nào</div>';
		}
		?>
	</div>
	<script>
		const searchInput = document.getElementById('searchInput');
		const searchResult = document.getElementById('searchResult');
		searchInput.addEventListener('input', function() {
			const query = searchInput.value.trim();
			if (query !== '') {
				fetch(`../../../view/page/manage/products/searchProduct.php?q=${query}`)
					.then(response => response.text())
					.then(data => {
						searchResult.innerHTML = data;
						searchResult.style.display = 'block';
					})
					.catch(error => console.error('Error:', error));
			} else {
				searchResult.style.display = 'none';
			}
		});
		document.addEventListener('click', function(event) {
			if (!searchResult.contains(event.target) && event.target !== searchInput) {
				searchResult.style.display = 'none';
			}
		});
		document.querySelectorAll('.btn-delete').forEach(btn => {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				const productId = this.dataset.id;
				if (confirm('Bạn có chắc chắn muốn xóa sản phẩm này?')) {
					fetch(`../../../view/page/manage/products/deleteProduct/deleteProduct.php?id=${productId}`)
						.then(response => response.text())
						.then(data => {
							alert(data);
							window.location.reload();
						})
						.catch(err => console.error('Lỗi xóa sản phẩm:', err));
				}
			});
		});
	</script>
</body>
</html>
