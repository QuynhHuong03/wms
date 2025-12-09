<?php
session_start();
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["btnUpdate"])) {
    $old_sku       = trim($_POST["old_sku"] ?? '');
    $product_name  = trim($_POST["product_name"] ?? '');
    $barcode       = trim($_POST["barcode"] ?? '');
    $category_id   = trim($_POST["category_id"] ?? '');
    $supplier_id   = trim($_POST["supplier_id"] ?? '');
    $model         = trim($_POST["model"] ?? ''); // Model là text
    $status        = (int)($_POST["status"] ?? 1);
    $min_stock     = (int)($_POST["min_stock"] ?? 0);
    $description   = trim($_POST["description"] ?? '');
    $base_unit     = trim($_POST["base_unit"] ?? '');
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

    // --- Upload hình ảnh ---
    $image = $_POST['old_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../../../../img/';
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('product_') . '.' . $ext;
        $target = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) $image = $fileName;
    }

    // --- Validate ---
    $errors = [];
    if ($product_name === '') $errors[] = "Tên sản phẩm không được để trống.";
    if ($category_id === '')  $errors[] = "Loại sản phẩm không được để trống.";
    if ($supplier_id === '')  $errors[] = "Nhà cung cấp không được để trống.";
    if ($base_unit === '')    $errors[] = "Phải chọn đơn vị tính chính.";
    if (!empty($errors)) {
        echo "<script>alert('Lỗi:\\n" . implode("\\n", $errors) . "'); history.back();</script>";
        exit();
    }

    // --- Lấy thông tin phụ ---
    include_once(__DIR__ . '/../../../../../controller/cCategories.php');
    include_once(__DIR__ . '/../../../../../controller/cSupplier.php');

    $catObj = new CCategories();
    $categories = $catObj->getAllCategories();
    $cat = array_values(array_filter($categories, fn($c) => $c['category_id'] == $category_id))[0] ?? [];
    $category_name = $cat['name'] ?? $cat['category_name'] ?? '';

    $supObj = new CSupplier();
    $suppliers = $supObj->getAllSuppliers();
    $sup = array_values(array_filter($suppliers, fn($s) => $s['supplier_id'] == $supplier_id))[0] ?? [];
    $supplier_name = $sup['supplier_name'] ?? $sup['name'] ?? '';

    // --- Chuẩn hóa conversionUnits ---
    $conversionList = [];
    foreach ($conversion_units as $i => $unit) {
        $unit = trim($unit);
        $factor = isset($conversion_factors[$i]) ? (float)$conversion_factors[$i] : 0;
        $width = isset($conversion_widths[$i]) ? (float)$conversion_widths[$i] : 0;
        $depth = isset($conversion_depths[$i]) ? (float)$conversion_depths[$i] : 0;
        $height = isset($conversion_heights[$i]) ? (float)$conversion_heights[$i] : 0;
        $weight = isset($conversion_weights[$i]) ? (float)$conversion_weights[$i] : 0;
        $volume = isset($conversion_volumes[$i]) ? (float)$conversion_volumes[$i] : 0;
        
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

    // --- Dữ liệu cập nhật ---
    $updateData = [
        'product_name' => $product_name,
        'barcode' => $barcode,
        'category' => ['id' => $category_id, 'name' => $category_name],
        'supplier' => ['id' => $supplier_id, 'name' => $supplier_name],
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
        'status' => $status,
        'description' => $description,
        'image' => $image,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    // --- Gọi DB ---
    $p = new CProduct();
    $mongo = new MProduct();
    $col = $mongo->getAllProducts();
    $con = (new clsKetNoi())->moKetNoi();
    if ($con) {
        $collection = $con->selectCollection('products');
        $collection->updateOne(['sku' => $old_sku], ['$set' => $updateData]);
        (new clsKetNoi())->dongKetNoi($con);
    }

    // Flash success message và chuyển hướng về trang danh sách sản phẩm
    $_SESSION['flash_success'] = 'Cập nhật sản phẩm thành công!';
    header("Location: ../../index.php?page=products");
    exit();
} else {
    $_SESSION['flash_error'] = 'Yêu cầu không hợp lệ.';
    header("Location: ../../index.php?page=products");
    exit();
}
