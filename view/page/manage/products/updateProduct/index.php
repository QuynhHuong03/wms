<?php
include_once(__DIR__ . '/../../../../../controller/cProduct.php');
include_once(__DIR__ . '/../../../../../controller/cCategories.php');
include_once(__DIR__ . '/../../../../../controller/cSupplier.php');
include_once(__DIR__ . '/../../../../../controller/cModel.php');

$cProduct = new CProduct();
$categories = (new CCategories())->getAllCategories();
$suppliers = (new CSupplier())->getAllSuppliers();
$models = (new CModel())->getAllModels();

$product = null;
if (isset($_GET['id'])) {
    $sku = $_GET['id'];
    $products = $cProduct->getAllProducts();
    foreach ($products as $p) {
        if ($p['sku'] === $sku) {
            $product = $p;
            break;
        }
    }
}
if (!$product) {
    echo "<script>alert('Không tìm thấy sản phẩm.'); window.location.href = '../../index.php?page=products';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật sản phẩm</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; background-color: #f9f9f9; color: #333; margin: 0; }
        .container { width: 90%; max-width: 800px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 25px 30px; }
        h2 { text-align: center; color: #222; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 18px; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 18px; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 5px rgba(59,130,246,0.3); }
        .form-actions { text-align: center; margin-top: 20px; display: flex; justify-content: center; gap: 15px; }
        .form-actions button, .form-actions a { background-color: #3b82f6; color: #fff; padding: 10px 20px; font-size: 15px; border: none; border-radius: 8px; cursor: pointer; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .form-actions button:hover, .form-actions a:hover { background-color: #2563eb; }
        .form-actions .btn-secondary { background-color: #6b7280; }
        .form-actions .btn-secondary:hover { background-color: #4b5563; }
        .form-actions .btn-success { background-color: #16a34a; }
        .form-actions .btn-success:hover { background-color: #15803d; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Cập nhật sản phẩm</h2>
    <form action="products/updateProduct/process.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="old_image" value="<?php echo $product['image'] ?? ''; ?>">
            <input type="hidden" name="old_sku" value="<?php echo $product['sku']; ?>">
            <div class="form-group">
                <label for="new_sku">Mã SKU</label>
                <input type="text" id="new_sku" name="new_sku" value="<?php echo $product['sku']; ?>" required>
            </div>
            <div class="form-group">
                <label for="barcode">Barcode</label>
                <input type="text" id="barcode" name="barcode" value="<?php echo $product['barcode'] ?? ''; ?>">
            </div>
            <div class="form-group">
                <label for="product_name">Tên sản phẩm</label>
                <input type="text" id="product_name" name="product_name" value="<?php echo $product['product_name'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="category_name">Loại sản phẩm</label>
                <select id="category_name" name="category_name" required>
                    <option value="">-- Chọn loại sản phẩm --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['name'] ?? $cat['category_name'] ?? $cat['category_id']; ?>" <?php echo ($product['category_name'] ?? '') == ($cat['name'] ?? $cat['category_name'] ?? $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo $cat['name'] ?? $cat['category_name'] ?? $cat['category_id']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="supplier_name">Nhà cung cấp</label>
                <select id="supplier_name" name="supplier_name" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?php echo $sup['supplier_name'] ?? $sup['name'] ?? $sup['supplier_id']; ?>" <?php echo ($product['supplier_name'] ?? '') == ($sup['supplier_name'] ?? $sup['name'] ?? $sup['supplier_id']) ? 'selected' : ''; ?>>
                            <?php echo $sup['supplier_name'] ?? $sup['name'] ?? $sup['supplier_id']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="warehouse_id">Kho</label>
                <input type="text" id="warehouse_id" name="warehouse_id" value="<?php echo $product['warehouse_id'] ?? ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="1" <?php echo ($product['status'] ?? 1) == 1 ? 'selected' : ''; ?>>Hoạt động</option>
                    <option value="0" <?php echo ($product['status'] ?? 1) == 0 ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                </select>
            </div>
            <div class="form-group">
                <label for="min_stock">Tồn kho tối thiểu</label>
                <input type="number" id="min_stock" name="min_stock" min="0" value="<?php echo $product['min_stock'] ?? 0; ?>" required>
            </div>
            <div class="form-group">
                <label for="image">Hình ảnh sản phẩm</label>
                <input type="file" id="image" name="image" accept="image/*">
                <?php if (!empty($product['image'])): ?>
                    <div style="margin-top:8px;">
                        <span>Ảnh hiện tại:</span>
                        <img src="../../../img/<?php echo $product['image']; ?>" alt="Ảnh sản phẩm" style="max-width:120px;max-height:120px;border-radius:8px;">
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" name="btnUpdate" class="btn-success"><i class="fas fa-save"></i> Cập nhật</button>
                <a href="index.php?page=products" class="btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
            </div>
        </form>
    </div>
</body>
</html>
