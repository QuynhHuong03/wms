<?php
include_once(__DIR__ . '/../../../../../controller/cCategories.php');
include_once(__DIR__ . '/../../../../../controller/cSupplier.php');
include_once(__DIR__ . '/../../../../../controller/cModel.php');

$categories = (new CCategories())->getAllCategories();
$suppliers = (new CSupplier())->getAllSuppliers();
$models = (new CModel())->getAllModels();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm sản phẩm mới</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            color: #333;
            margin: 0;
        }
        .container {
            width: 90%;
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 25px 30px;
        }
        h2 {
            text-align: center;
            color: #222;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 18px;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 18px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 5px rgba(59,130,246,0.3);
        }
        .form-actions {
            text-align: center;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .form-actions button,
        .form-actions a {
            background-color: #3b82f6;
            color: #fff;
            padding: 10px 20px;
            font-size: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .form-actions button:hover,
        .form-actions a:hover {
            background-color: #2563eb;
        }
        .form-actions .btn-secondary {
            background-color: #6b7280;
        }
        .form-actions .btn-secondary:hover {
            background-color: #4b5563;
        }
        .form-actions .btn-success {
            background-color: #16a34a;
        }
        .form-actions .btn-success:hover {
            background-color: #15803d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Thêm sản phẩm mới</h2>
    <form action="products/createProduct/process.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="image">Hình ảnh sản phẩm</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>
            <!-- Mã SKU sẽ tự sinh, không nhập tay -->
            <div class="form-group">
                <label for="barcode">Barcode (có thể để trống)</label>
                <input type="text" id="barcode" name="barcode" placeholder="Barcode...">
            </div>
            <div class="form-group">
                <label for="product_name">Tên sản phẩm</label>
                <input type="text" id="product_name" name="product_name" required>
            </div>
            <div class="form-group">
                <label for="category_id">Loại sản phẩm</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Chọn loại sản phẩm --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>">
                            <?php echo $cat['name'] ?? $cat['category_name'] ?? $cat['category_id']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="supplier_id">Nhà cung cấp</label>
                <select id="supplier_id" name="supplier_id" required>
                    <option value="">-- Chọn nhà cung cấp --</option>
                    <?php foreach ($suppliers as $sup): ?>
                        <option value="<?php echo $sup['supplier_id']; ?>">
                            <?php echo $sup['supplier_name'] ?? $sup['name'] ?? $sup['supplier_id']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="model_id">Model</label>
                <select id="model_id" name="model_id">
                    <option value="">-- Chọn model --</option>
                    <?php foreach ($models as $model): ?>
                        <option value="<?php echo $model['model_id']; ?>">
                            <?php echo $model['model_name'] ?? $model['model_id']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="warehouse_id">Kho</label>
                <input type="text" id="warehouse_id" name="warehouse_id" required>
            </div>
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="1">Hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>
            <div class="form-group">
                <label for="purchase_price">Giá nhập</label>
                <input type="number" id="purchase_price" name="purchase_price" min="0" value="0" required>
            </div>
            <div class="form-group">
                <label for="min_stock">Tồn kho tối thiểu</label>
                <input type="number" id="min_stock" name="min_stock" min="0" value="0" required>
            </div>
            <div class="form-group">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" rows="3" style="width:100%;border-radius:8px;"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="btnAdd" class="btn-success"><i class="fas fa-plus"></i> Thêm sản phẩm</button>
                <a href="index.php?page=products" class="btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
            </div>
        </form>
    </div>
</body>
</html>
