<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    // Xử lý upload hình ảnh
    $imageFileName = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../../../../img/';
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageFileName = uniqid('product_') . '.' . $ext;
        $targetPath = $uploadDir . $imageFileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            // Lưu tên file ảnh vào DB
        } else {
            $imageFileName = '';
        }
    }
    $barcode = trim($_POST["barcode"] ?? '');
    $product_name = trim($_POST["product_name"] ?? '');
    $category_id = trim($_POST["category_id"] ?? '');
    $supplier_id = trim($_POST["supplier_id"] ?? '');
    $warehouse_id = trim($_POST["warehouse_id"] ?? '');
    $model_id = trim($_POST["model_id"] ?? '');
    $status = $_POST["status"] ?? 1;
    $purchase_price = $_POST["purchase_price"] ?? 0;
    $quantity_in_stock = $_POST["quantity_in_stock"] ?? 0;
        $min_stock = $_POST["min_stock"] ?? 0;
    $description = trim($_POST["description"] ?? '');
    $created_at = date('Y-m-d H:i:s');
    $updated_at = $created_at;

    $errors = [];
    if ($product_name === '') {
        $errors[] = "Tên sản phẩm không được để trống.";
    }
    if ($category_id === '') {
        $errors[] = "Loại sản phẩm không được để trống.";
    }
    if ($supplier_id === '') {
        $errors[] = "Nhà cung cấp không được để trống.";
    }
    if ($warehouse_id === '') {
        $errors[] = "Kho không được để trống.";
    }
    if ($purchase_price < 0) {
        $errors[] = "Giá nhập không hợp lệ.";
    }
    if ($min_stock < 0) {
        $errors[] = "Số lượng tồn kho không hợp lệ.";
    }
    if (count($errors) > 0) {
        echo "<script>alert('Lỗi: " . implode("\\n", $errors) . "'); window.location.href = 'index.php';</script>";
        exit();
    }

    // Lấy category_code và category_name từ bảng categories
    include_once(__DIR__ . '/../../../../../controller/cCategories.php');
    $categoryObj = new CCategories();
    $category = null;
    $categories = $categoryObj->getAllCategories();
    foreach ($categories as $cat) {
        if ($cat['category_id'] == $category_id) {
            $category = $cat;
            break;
        }
    }
    $category_code = $category['category_code'] ?? '';
    $category_name = $category['name'] ?? $category['category_name'] ?? '';

    // Lấy model_code từ bảng models
    include_once(__DIR__ . '/../../../../../controller/cModel.php');
    $modelObj = new CModel();
    $model = null;
    $models = $modelObj->getAllModels();
    foreach ($models as $m) {
        if ($m['model_id'] == $model_id) {
            $model = $m;
            break;
        }
    }
    $model_code = $model['model_code'] ?? '';

    // Lấy supplier_name từ bảng suppliers
    include_once(__DIR__ . '/../../../../../controller/cSupplier.php');
    $supplierObj = new CSupplier();
    $supplier = null;
    $suppliers = $supplierObj->getAllSuppliers();
    foreach ($suppliers as $sup) {
        if ($sup['supplier_id'] == $supplier_id) {
            $supplier = $sup;
            break;
        }
    }
    $supplier_name = $supplier['supplier_name'] ?? $supplier['name'] ?? '';

    // Lấy id tự tăng tiếp theo
    include_once(__DIR__ . '/../../../../../controller/cProduct.php');
    $cProduct = new CProduct();
    $products = $cProduct->getAllProducts();
    $maxId = 0;
    foreach ($products as $product) {
        if (isset($product['id']) && $product['id'] > $maxId) {
            $maxId = $product['id'];
        }
    }
    $nextId = $maxId + 1;

    // Sinh SKU: CATCODE-MODELCODE-ID
    $sku = $category_code . '-' . $model_code . '-' . $nextId;

    // Kiểm tra trùng SKU
    foreach ($products as $product) {
        if ($product['sku'] === $sku) {
            echo "<script>alert('SKU đã tồn tại.'); window.location.href = 'index.php';</script>";
            exit();
        }
    }

    // Thêm sản phẩm mới vào DB
    $productData = [
    'image' => $imageFileName,
        'sku' => $sku,
        'barcode' => $barcode,
        'product_name' => $product_name,
        'category_id' => $category_id,
        'category_name' => $category_name,
        'supplier_id' => $supplier_id,
        'supplier_name' => $supplier_name,
        'warehouse_id' => $warehouse_id,
        'model_id' => $model_id,
        'status' => (int)$status,
        'purchase_price' => (int)$purchase_price,
    'min_stock' => (int)$min_stock,
        'description' => $description,
    'created_at' => $created_at,
    'updated_at' => $updated_at
    ];
    $result = $cProduct->addProduct($productData);
    if ($result) {
        echo "<script>alert('Thêm sản phẩm thành công!'); window.location.href = '../../index.php?page=products';</script>";
        exit();
    } else {
        echo "<script>alert('Thêm sản phẩm thất bại!'); window.location.href = '../../index.php?page=products/createProduct';</script>";
        exit();
    }
} else {
    echo "<script>alert('Yêu cầu không hợp lệ.'); window.location.href = '../../index.php?page=products/createProduc';</script>";
    exit();
}
