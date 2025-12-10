<?php
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

include_once(__DIR__ . "/../../../../controller/cRequest.php");
include_once(__DIR__ . "/../../../../controller/cWarehouse.php");
include_once(__DIR__ . "/../../../../controller/cInventory.php");

if (!isset($_GET['id'])) {
    $_SESSION['flash_request_error'] = 'Thiếu thông tin phiếu yêu cầu!';
    header("Location: index.php?page=goodsReceiptRequest");
    exit();
}

$requestId = $_GET['id'];
$cRequest = new CRequest();
$request = $cRequest->getRequestById($requestId);

if (!$request) {
    $_SESSION['flash_request_error'] = 'Không tìm thấy phiếu yêu cầu!';
    header("Location: index.php?page=goodsReceiptRequest");
    exit();
}

// Xử lý form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignedWarehouseId = $_POST['assigned_warehouse_id'] ?? '';
    $note = $_POST['note'] ?? null;
    $assignedBy = $_SESSION['login']['user_id'] ?? 'SYSTEM';
    
    if (empty($assignedWarehouseId)) {
        $_SESSION['flash_request_error'] = 'Vui lòng chọn kho!';
    } else {
        $result = $cRequest->assignAlternativeWarehouse($requestId, $assignedWarehouseId, $assignedBy, $note);
        
        if ($result) {
            $_SESSION['flash_request'] = 'Đã chỉ định kho thay thế thành công!';
            header("Location: index.php?page=goodsReceiptRequest");
            exit();
        } else {
            $_SESSION['flash_request_error'] = 'Chỉ định kho thất bại!';
        }
    }
}

// Lấy danh sách kho
$warehouseController = new CWarehouse();
$warehouses = $warehouseController->getAllWarehouses() ?? [];

// Lấy tồn kho của các sản phẩm tại các kho
$cInventory = new CInventory();
$productStocks = [];

foreach ($request['details'] as $detail) {
    $productId = $detail['product_id'] ?? '';
    $requiredQty = (int)($detail['quantity'] ?? 0);
    
    $stocks = $cInventory->getStockByProductAllWarehouses($productId);
    $productStocks[$productId] = [
        'name' => $detail['product_name'] ?? '',
        'required' => $requiredQty,
        'stocks' => $stocks
    ];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chỉ định kho thay thế</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {font-family: "Segoe UI", Tahoma, sans-serif;background:#f3f6fa;margin:0;padding:20px;}
    .container {max-width:1000px;margin:auto;background:#fff;padding:25px 30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}
    h2 {text-align:center;margin-bottom:25px;color:#333;}
    .info-box {background:#e7f3ff;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #007bff;}
    label {font-weight:600;margin-top:12px;display:block;color:#444;}
    select,textarea {width:100%;padding:10px;margin:6px 0 14px;border:1px solid #d0d7de;border-radius:8px;font-size:14px;}
    table {width:100%;border-collapse:collapse;margin:15px 0;}
    th,td {border:1px solid #e1e4e8;padding:10px;text-align:left;font-size:14px;}
    th {background:#f9fafb;font-weight:600;}
    .sufficient {color:#28a745;font-weight:600;}
    .insufficient {color:#dc3545;font-weight:600;}
    .btn {background:#007bff;color:#fff;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block;}
    .btn:hover {background:#0056b3;}
    .btn-secondary {background:#6c757d;}
    .btn-secondary:hover {background:#5a6268;}
    .actions {margin-top:20px;display:flex;justify-content:flex-end;gap:10px;}
  </style>
</head>
<body>
  <div class="container">
    <h2><i class="fa-solid fa-arrow-right-arrow-left"></i> Chỉ định kho thay thế</h2>

    <div class="info-box">
      <strong>Mã phiếu:</strong> <?= htmlspecialchars($request['transaction_id']) ?><br>
      <strong>Kho yêu cầu:</strong> <?= htmlspecialchars($request['warehouse_id']) ?><br>
      <strong>Kho nguồn dự kiến:</strong> <?= htmlspecialchars($request['source_warehouse_id']) ?> <span class="insufficient">(Không đủ hàng)</span>
    </div>

    <h3>Tình trạng tồn kho sản phẩm:</h3>
    <table>
      <thead>
        <tr>
          <th>Sản phẩm</th>
          <th>Yêu cầu</th>
          <th>Kho có đủ hàng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($productStocks as $productId => $data): ?>
          <tr>
            <td><?= htmlspecialchars($data['name']) ?></td>
            <td><?= $data['required'] ?></td>
            <td>
              <?php
              $availableWarehouses = [];
              foreach ($data['stocks'] as $whId => $qty) {
                if ($qty >= $data['required'] && $whId !== $request['warehouse_id']) {
                  $availableWarehouses[] = "$whId ($qty)";
                }
              }
              
              if (!empty($availableWarehouses)) {
                echo '<span class="sufficient">✓ ' . implode(', ', $availableWarehouses) . '</span>';
              } else {
                echo '<span class="insufficient">✗ Không có kho nào đủ</span>';
              }
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post">
      <label>Chọn kho thay thế <span style="color:red;">*</span></label>
      <select name="assigned_warehouse_id" required>
        <option value="">-- Chọn kho --</option>
        <?php foreach ($warehouses as $w): ?>
          <?php if ($w['warehouse_id'] !== $request['warehouse_id'] && $w['warehouse_id'] !== $request['source_warehouse_id']): ?>
            <option value="<?= $w['warehouse_id'] ?>"><?= $w['warehouse_name'] ?> (<?= $w['warehouse_id'] ?>)</option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>

      <label>Ghi chú</label>
      <textarea name="note" rows="3" placeholder="Ghi chú về việc chỉ định kho..."></textarea>

      <div class="actions">
        <a href="index.php?page=goodsReceiptRequest" class="btn btn-secondary">
          <i class="fa-solid fa-arrow-left"></i> Quay lại
        </a>
        <button type="submit" class="btn">
          <i class="fa-solid fa-check"></i> Xác nhận chỉ định
        </button>
      </div>
    </form>
  </div>
</body>
</html>
