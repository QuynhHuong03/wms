<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnAdd"])) {
    // --- Xử lý upload hình ảnh ---
    $imageFileName = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../../../../img/';
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageFileName = uniqid('product_') . '.' . $ext;
        $targetPath = $uploadDir . $imageFileName;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imageFileName = '';
        }
    }

    // --- Nhận dữ liệu từ form ---
    $barcode         = trim($_POST["barcode"] ?? '');
    $product_name    = trim($_POST["product_name"] ?? '');
    $category_id     = trim($_POST["category_id"] ?? '');
    $supplier_id     = trim($_POST["supplier_id"] ?? '');
    $model           = trim($_POST["model"] ?? ''); // Model là text input
    $status          = (int)($_POST["status"] ?? 1);
    $min_stock       = (int)($_POST["min_stock"] ?? 0);
    $description     = trim($_POST["description"] ?? '');
    $base_unit       = trim($_POST["base_unit"] ?? '');
    $conversion_units = $_POST["conversion_unit"] ?? [];
    $conversion_factors = $_POST["conversion_factor"] ?? [];
    $conversion_widths = $_POST["conversion_width"] ?? [];
    $conversion_depths = $_POST["conversion_depth"] ?? [];
    $conversion_heights = $_POST["conversion_height"] ?? [];
    $conversion_weights = $_POST["conversion_weight"] ?? [];
    $conversion_volumes = $_POST["conversion_volume"] ?? [];

    // Thông tin kích thước và trọng lượng đơn vị chính
    $package_width  = (float)($_POST["package_width"] ?? 0);
    $package_depth  = (float)($_POST["package_depth"] ?? 0);
    $package_height = (float)($_POST["package_height"] ?? 0);
    $package_weight = (float)($_POST["package_weight"] ?? 0);
    $volume_per_unit = (float)($_POST["volume_per_unit"] ?? 0);
    
    $stackable = (int)($_POST["stackable"] ?? 1);
    $max_stack_height = (int)($_POST["max_stack_height"] ?? 0);

    $created_at = date('Y-m-d H:i:s');
    $updated_at = $created_at;

    // --- Kiểm tra hợp lệ ---
    $errors = [];
    if ($product_name === '') $errors[] = "Tên sản phẩm không được để trống.";
    if ($category_id === '')  $errors[] = "Loại sản phẩm không được để trống.";
    if ($supplier_id === '')  $errors[] = "Nhà cung cấp không được để trống.";
    if ($base_unit === '')    $errors[] = "Phải chọn đơn vị tính chính.";
    if ($min_stock < 0)       $errors[] = "Tồn kho tối thiểu không hợp lệ.";

    if (!empty($errors)) {
        echo "<script>alert('Lỗi:\\n" . implode("\\n", $errors) . "'); history.back();</script>";
        exit();
    }

    // --- Lấy thông tin liên quan ---
    include_once(__DIR__ . '/../../../../../controller/cCategories.php');
    include_once(__DIR__ . '/../../../../../controller/cSupplier.php');

    $categoryObj = new CCategories();
    $categories = $categoryObj->getAllCategories();
    $category = array_filter($categories, fn($c) => $c['category_id'] == $category_id);
    $category = reset($category);
    $category_code = $category['category_code'] ?? '';
    $category_name = $category['name'] ?? $category['category_name'] ?? '';

    $supplierObj = new CSupplier();
    $suppliers = $supplierObj->getAllSuppliers();
    $supplier = array_filter($suppliers, fn($s) => $s['supplier_id'] == $supplier_id);
    $supplier = reset($supplier);
    $supplier_name = $supplier['supplier_name'] ?? $supplier['name'] ?? '';

    // --- Lấy ID tự tăng & sinh SKU từ category_code + ID ---
    $cProduct = new CProduct();
    $products = $cProduct->getAllProducts();
    $maxId = 0;
    foreach ($products as $product) {
        if (isset($product['id']) && $product['id'] > $maxId) $maxId = $product['id'];
    }
    $nextId = $maxId + 1;
    $sku = $category_code . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT); // VD: LAPTOP-0001

    // --- Kiểm tra trùng SKU ---
    foreach ($products as $p) {
        if ($p['sku'] === $sku) {
            echo "<script>alert('SKU đã tồn tại.'); history.back();</script>";
            exit();
        }
    }

    // --- Xử lý đơn vị quy đổi ---
    $conversionList = [];
    foreach ($conversion_units as $index => $unit) {
        $unit = trim($unit);
        $factor = isset($conversion_factors[$index]) ? (float)$conversion_factors[$index] : 0;
        $width = isset($conversion_widths[$index]) ? (float)$conversion_widths[$index] : 0;
        $depth = isset($conversion_depths[$index]) ? (float)$conversion_depths[$index] : 0;
        $height = isset($conversion_heights[$index]) ? (float)$conversion_heights[$index] : 0;
        $weight = isset($conversion_weights[$index]) ? (float)$conversion_weights[$index] : 0;
        $volume = isset($conversion_volumes[$index]) ? (float)$conversion_volumes[$index] : 0;
        
        if ($unit !== '' && $factor > 0) {
            $conversionList[] = [
                'unit' => $unit,
                'factor' => $factor,
                'dimensions' => [
                    'width' => $width,
                    'depth' => $depth,
                    'height' => $height
                ],
                'weight' => $weight,
                'volume' => $volume
            ];
        }
    }

    // --- Chuẩn hóa dữ liệu để insert ---
    $productData = [
        'id' => $nextId,
        'sku' => $sku,
        'barcode' => $barcode,
        'product_name' => $product_name,
        'category' => [
            'id' => $category_id,
            'name' => $category_name
        ],
        'supplier' => [
            'id' => $supplier_id,
            'name' => $supplier_name
        ],
        'model' => $model,
        'baseUnit' => $base_unit,
        'conversionUnits' => $conversionList,
        'package_dimensions' => [
            'width' => $package_width,
            'depth' => $package_depth,
            'height' => $package_height
        ],
        'package_weight' => $package_weight,
        'volume_per_unit' => $volume_per_unit,
        'stackable' => (bool)$stackable,
        'max_stack_height' => $max_stack_height,
        'min_stock' => $min_stock,
        'description' => $description,
        'status' => $status,
        'image' => $imageFileName,
        'created_at' => $created_at,
        'updated_at' => $updated_at
    ];

    // --- Thêm vào DB ---
    $result = $cProduct->addProduct($productData);
    if ($result) {
        echo "<script>alert('✅ Thêm sản phẩm thành công!'); window.location.href='../../index.php?page=products';</script>";
    } else {
        echo "<script>alert('❌ Thêm sản phẩm thất bại!'); history.back();</script>";
    }

} else {
    echo "<script>alert('Yêu cầu không hợp lệ.'); window.location.href='../../index.php?page=products';</script>";
    exit();
}
