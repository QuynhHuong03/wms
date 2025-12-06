<?php
header('Content-Type: application/json; charset=utf-8');
include_once(__DIR__ . "/../../../../../controller/cCategories.php");
$cCategories = new CCategories();

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing id']);
    exit();
}

try {
    $deleted = $cCategories->deleteCategory($id);
    if ($deleted === 'HAS_PRODUCTS') {
        echo json_encode(['success' => false, 'message' => 'Không thể xóa. Loại sản phẩm còn chứa sản phẩm']);
    } elseif ($deleted) {
        echo json_encode(['success' => true, 'message' => 'Xóa loại sản phẩm thành công']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hoặc không thể xóa']);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
