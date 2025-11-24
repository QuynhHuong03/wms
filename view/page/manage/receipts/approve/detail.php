<?php
include_once(__DIR__ . "/../../../../../controller/cReceipt.php");
include_once(__DIR__ . "/../../../../../controller/cProduct.php");

$cReceipt = new CReceipt();
$cProduct = new CProduct();

$id = $_GET['id'] ?? '';
if (!$id) {
  echo "Thiếu ID phiếu.";
  exit;
}

$receipt = $cReceipt->getReceiptById($id);
if (!$receipt) {
  echo "Không tìm thấy phiếu nhập.";
  exit;
}

// 🔹 Xử lý thời gian
function fmtDate($time) {
  if (!$time) return '';
  if ($time instanceof MongoDB\BSON\UTCDateTime)
    return date('d/m/Y H:i', $time->toDateTime()->getTimestamp());
  return date('d/m/Y H:i', strtotime($time));
}

$created_date  = fmtDate($receipt['created_at'] ?? '');
$approved_date = fmtDate($receipt['approved_at'] ?? '');

// 🔹 Trạng thái (an toàn khi status nằm ngoài phạm vi)
$status = (int)($receipt['status'] ?? 0);
$statusTextMap = [
  0 => 'Chờ duyệt',
  1 => 'Đã duyệt',
  2 => 'Từ chối',
  3 => 'Đã hoàn tất'
];
$statusClassMap = [
  0 => 'pending',
  1 => 'approved',
  2 => 'rejected',
  3 => 'located'
];

$statusText = isset($statusTextMap[$status]) ? $statusTextMap[$status] : 'Không xác định';
$statusClass = isset($statusClassMap[$status]) ? $statusClassMap[$status] : 'pending';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chi tiết phiếu nhập hàng</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {background:#f6f8fa;font-family:'Segoe UI',Tahoma,sans-serif;color:#333;}
    .receipt-detail-container {
      max-width:1000px;margin:20px auto;background:#fff;border-radius:16px;
      box-shadow:0 6px 20px rgba(0,0,0,0.08);overflow:hidden;
    }
    .header {padding:25px 10px;text-align:center;border-bottom:1px solid #e9ecef;}
    .header h2 {margin:0;font-size:26px;color:#333;font-weight:700;}
    .info {
      display:grid;grid-template-columns:repeat(2,1fr);
      gap:10px 30px;padding:25px 35px;background:#fafbfc;border-bottom:1px solid #eee;
    }
    .info p {margin:4px 0;font-size:15px;}
    .info b {color:#222;}
    .status {padding:6px 12px;border-radius:6px;font-weight:600;font-size:14px;}
    .pending{background:#fff3cd;color:#856404;}
    .approved{background:#d4edda;color:#155724;}
    .rejected{background:#f8d7da;color:#721c24;}
  .located{background:#cce5ff;color:#004085;}
    table {width:100%;border-collapse:collapse;margin:20px 0;}
    th,td {border:1px solid #e1e4e8;padding:10px 12px;text-align:center;font-size:15px;}
    th {background:#f9fafb;font-weight:600;}
    tr:hover td {background:#f1f7ff;}
    .total {text-align:right;font-size:18px;font-weight:bold;margin:25px 35px;color:#007bff;}
    .actions {text-align:center;padding:20px 0 30px;}
    .btn {display:inline-block;padding:12px 22px;border-radius:8px;font-weight:600;
      text-decoration:none;transition:0.25s;margin:0 8px;cursor:pointer;}
    .btn-back{background:#6c757d;color:#fff;}
    .btn-approve{background:#28a745;color:#fff;}
    .btn-reject{background:#dc3545;color:#fff;}
    .btn:hover{opacity:0.9;transform:translateY(-2px);}
    @media(max-width:700px){.info{grid-template-columns:1fr;padding:20px;}}
  </style>
</head>

<body>
<div class="receipt-detail-container">
  <div class="header">
    <h2><i class="fa-solid fa-file-circle-info"></i> Chi tiết phiếu nhập hàng</h2>
  </div>

  <div class="info">
    <p><b>Mã phiếu:</b> <?= htmlspecialchars($receipt['transaction_id']) ?></p>
    <p><b>Ngày tạo:</b> <?= $created_date ?></p>
    <p><b>Người tạo:</b> <?= htmlspecialchars($receipt['creator_name'] ?? $receipt['created_by']) ?></p>
    <p><b>Kho nhập:</b> <?= htmlspecialchars($receipt['warehouse_id']) ?></p>

    <?php if ($receipt['type'] === 'purchase'): ?>
      <p><b>Loại phiếu:</b> Nhập từ nhà cung cấp</p>
      <p><b>Nhà cung cấp:</b> <?= htmlspecialchars($receipt['supplier_name'] ?? $receipt['supplier_id'] ?? 'N/A') ?></p>
    <?php elseif ($receipt['type'] === 'transfer'): ?>
      <p><b>Loại phiếu:</b> Nhập điều chuyển nội bộ</p>
      <p><b>Kho nguồn:</b> <?= htmlspecialchars($receipt['source_warehouse_name'] ?? $receipt['source_warehouse_id'] ?? 'N/A') ?></p>
    <?php else: ?>
      <p><b>Loại phiếu:</b> Không xác định</p>
    <?php endif; ?>

    <p><b>Trạng thái:</b> <span class="status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusText) ?></span></p>

    <?php if ($status > 0 && isset($receipt['approved_by'])): ?>
      <p><b>Người duyệt:</b> <?= htmlspecialchars($receipt['approved_by']) ?></p>
      <?php if ($approved_date): ?>
        <p><b>Thời gian duyệt:</b> <?= $approved_date ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($receipt['note'])): ?>
      <p><b>Ghi chú:</b> <?= htmlspecialchars($receipt['note']) ?></p>
    <?php endif; ?>
  </div>

  <div style="padding:0 25px;">
    <h3 style="margin-top:20px;color:#333;">Danh sách sản phẩm</h3>
    <table>
      <thead>
        <tr>
          <th>Mã SKU</th>
          <th>Tên sản phẩm</th>
          <th>Kích thước (cm)</th>
          <th>Đơn vị</th>
          <th>Số lượng</th>
          <th>Giá nhập</th>
          <th>Thành tiền</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if (!empty($receipt['details'])) {
          foreach ($receipt['details'] as $item) {
            $subtotal = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
            
            // Lấy thông tin sản phẩm để hiển thị đơn vị, quy đổi và kích thước
            $productInfo = $cProduct->getProductById($item['product_id']);
            $baseUnit = $productInfo['baseUnit'] ?? 'cái';
            $conversionUnits = $productInfo['conversionUnits'] ?? [];
            
            // Lấy kích thước sản phẩm - kiểm tra nhiều nguồn dữ liệu
            $dimensions = [];
            
            // Nguồn 1: Từ thông tin sản phẩm trong database
            if (isset($productInfo['dimensions']) && is_array($productInfo['dimensions'])) {
              $dimensions = $productInfo['dimensions'];
            }
            // Nguồn 2: Có thể lưu trực tiếp trong productInfo (không nested)
            elseif (isset($productInfo['width']) || isset($productInfo['depth']) || isset($productInfo['height'])) {
              $dimensions = [
                'width' => $productInfo['width'] ?? 0,
                'depth' => $productInfo['depth'] ?? 0,
                'height' => $productInfo['height'] ?? 0
              ];
            }
            
            $width = isset($dimensions['width']) ? floatval($dimensions['width']) : 0;
            $depth = isset($dimensions['depth']) ? floatval($dimensions['depth']) : 0;
            $height = isset($dimensions['height']) ? floatval($dimensions['height']) : 0;
            
            // Hiển thị kích thước
            if ($width > 0 || $depth > 0 || $height > 0) {
              $dimensionText = sprintf("%.1f×%.1f×%.1f", $width, $depth, $height);
              $volume = $width * $depth * $height;
              $dimensionDisplay = $dimensionText . "<br><small style='color:#6c757d;'>V: " . number_format($volume, 0, ',', '.') . " cm³</small>";
            } else {
              $dimensionDisplay = "<span style='color:#dc3545;'>Chưa có</span>";
            }
            
            // Đơn vị được chọn khi tạo phiếu
            $selectedUnit = $item['unit'] ?? $baseUnit;
            $quantity = $item['quantity'] ?? 0;
            
            // Tìm hệ số quy đổi nếu đơn vị không phải là đơn vị cơ bản
            $conversionInfo = '';
            $totalBaseQty = $quantity; // Mặc định là số lượng gốc
            
            if ($selectedUnit !== $baseUnit && !empty($conversionUnits)) {
              foreach ($conversionUnits as $conv) {
                if ($conv['unit'] === $selectedUnit) {
                  $factor = $conv['factor'] ?? 1;
                  $totalBaseQty = $quantity * $factor;
                  $conversionInfo = " <small style='color:#6c757d;'>(= $totalBaseQty $baseUnit)</small>";
                  break;
                }
              }
            }
            
            $displayQty = $quantity . ' ' . htmlspecialchars($selectedUnit) . $conversionInfo;

            // Hiển thị SKU (ưu tiên trường sku trong chi tiết, sau đó lấy từ productInfo, cuối cùng fallback product_id)
            $displaySku = '';
            if (!empty($item['sku'])) {
              $displaySku = $item['sku'];
            } elseif (!empty($productInfo) && !empty($productInfo['sku'])) {
              $displaySku = $productInfo['sku'];
            } else {
              $displaySku = $item['product_id'] ?? '';
            }

            echo "<tr>
              <td>".htmlspecialchars($displaySku)."</td>
              <td>".htmlspecialchars($item['product_name'])."</td>
              <td>".$dimensionDisplay."</td>
              <td>".htmlspecialchars($selectedUnit)."</td>
              <td>".$displayQty."</td>
              <td>".number_format($item['unit_price'] ?? 0, 0, ',', '.')." đ</td>
              <td>".number_format($subtotal, 0, ',', '.')." đ</td>
            </tr>";
          }
        } else {
          echo "<tr><td colspan='7'>Không có sản phẩm nào trong phiếu.</td></tr>";
        }
        ?>
      </tbody>
    </table>
    
    <?php
    // Tính tổng thể tích của tất cả sản phẩm trong phiếu
    $totalVolume = 0;
    $productWithoutDimension = 0;
    if (!empty($receipt['details'])) {
      foreach ($receipt['details'] as $item) {
        $productInfo = $cProduct->getProductById($item['product_id']);
        $dimensions = $productInfo['dimensions'] ?? [];
        $width = isset($dimensions['width']) ? floatval($dimensions['width']) : 0;
        $depth = isset($dimensions['depth']) ? floatval($dimensions['depth']) : 0;
        $height = isset($dimensions['height']) ? floatval($dimensions['height']) : 0;
        
        if ($width > 0 && $depth > 0 && $height > 0) {
          $productVolume = $width * $depth * $height;
          $quantity = $item['quantity'] ?? 0;
          
          // Quy đổi về đơn vị cơ bản nếu cần
          $selectedUnit = $item['unit'] ?? ($productInfo['baseUnit'] ?? 'cái');
          $baseUnit = $productInfo['baseUnit'] ?? 'cái';
          $totalQty = $quantity;
          
          if ($selectedUnit !== $baseUnit && !empty($productInfo['conversionUnits'])) {
            foreach ($productInfo['conversionUnits'] as $conv) {
              if ($conv['unit'] === $selectedUnit) {
                $totalQty = $quantity * ($conv['factor'] ?? 1);
                break;
              }
            }
          }
          
          $totalVolume += $productVolume * $totalQty;
        } else {
          $productWithoutDimension++;
        }
      }
    }
    ?>
    
    <div style="margin-top:16px;padding:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div style="font-size:13px;color:#0c4a6e;margin-bottom:4px">📦 Tổng số sản phẩm:</div>
          <div style="font-size:20px;font-weight:700;color:#0369a1"><?= count($receipt['details'] ?? []) ?> loại</div>
        </div>
        <div>
          <div style="font-size:13px;color:#0c4a6e;margin-bottom:4px">📐 Tổng thể tích:</div>
          <div style="font-size:20px;font-weight:700;color:#0369a1"><?= number_format($totalVolume, 0, ',', '.') ?> cm³</div>
          <?php if ($productWithoutDimension > 0): ?>
            <div style="font-size:11px;color:#dc3545;margin-top:4px">
              ⚠️ <?= $productWithoutDimension ?> sản phẩm chưa có kích thước
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="total">
    Tổng tiền: <?= number_format($receipt['total_amount'] ?? 0, 0, ',', '.') ?> đ
  </div>

  <div class="actions">
    <a href="index.php?page=receipts/approve" class="btn btn-back">
      <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
    </a>

    <?php 
    $user_role = $_SESSION['login']['role'] ?? '';
    $user_role_name = $_SESSION['login']['role_name'] ?? '';
    $user_role_id = $_SESSION['login']['role_id'] ?? '';

    $allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
    $allowedRoleIds = [2, 4];

    $hasPermission = in_array($user_role, $allowedRoles) || 
                     in_array($user_role_name, $allowedRoles) || 
                     in_array($user_role_id, $allowedRoleIds);

    if ($status === 0 && $hasPermission): ?>
      <button class="btn btn-approve" onclick="confirmAction('approve', '<?= $receipt['transaction_id'] ?>')">
        <i class="fa-solid fa-check"></i> Duyệt phiếu
      </button>
      <button class="btn btn-reject" onclick="confirmAction('reject', '<?= $receipt['transaction_id'] ?>')">
        <i class="fa-solid fa-xmark"></i> Từ chối
      </button>
    <?php endif; ?>

    <?php if ($status === 1 || $status === 3): ?>
      <a href="index.php?page=receipts/locate&id=<?= $receipt['transaction_id'] ?>" class="btn btn-approve" title="Xem/Sửa vị trí đã xếp">
        <i class="fa-solid fa-location-dot"></i> Xem/Sửa vị trí đã xếp
      </a>
    <?php endif; ?>
  </div>
</div>

<script>
function confirmAction(action, id) {
  // If rejecting, prompt for a reason via textarea
  if (action === 'reject') {
    Swal.fire({
      title: 'Nhập lý do từ chối',
      input: 'textarea',
      inputPlaceholder: 'Nhập lý do từ chối...',
      inputAttributes: { 'aria-label': 'Lý do từ chối' },
      showCancelButton: true,
      confirmButtonText: 'Xác nhận',
      cancelButtonText: 'Hủy',
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      preConfirm: (reason) => {
        if (!reason || !reason.trim()) {
          Swal.showValidationMessage('Vui lòng nhập lý do từ chối');
        }
        return reason;
      }
    }).then((result) => {
      if (result.isConfirmed) {
        const reason = result.value || '';
        window.location.href = `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}&reason=${encodeURIComponent(reason)}`;
      }
    });
    return;
  }

  // Default confirmation (approve)
  const actionText = action === 'approve' ? 'duyệt' : 'thực hiện';
  const color = action === 'approve' ? '#28a745' : '#6c757d';
  Swal.fire({
    title: `Xác nhận ${actionText} phiếu này?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: `Xác nhận`,
    cancelButtonText: `Hủy`,
    confirmButtonColor: color,
    cancelButtonColor: '#6c757d',
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}`;
    }
  });
}
</script>
</body>
</html>
