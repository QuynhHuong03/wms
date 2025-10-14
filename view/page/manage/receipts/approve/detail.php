<?php
 // Thêm session_start để truy cập thông tin người dùng
include_once(__DIR__ . "/../../../../../controller/cReceipt.php");

$cReceipt = new CReceipt();

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

// Chuyển thời gian
$created_date = 'N/A';
if (isset($receipt['created_at'])) {
  if ($receipt['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $created_date = date('d/m/Y H:i', $receipt['created_at']->toDateTime()->getTimestamp());
  } else {
    $created_date = date('d/m/Y H:i', strtotime($receipt['created_at']));
  }
}

// Xử lý thời gian duyệt
$approved_date = '';
if (isset($receipt['approved_at']) && $receipt['approved_at']) {
  if ($receipt['approved_at'] instanceof MongoDB\BSON\UTCDateTime) {
    $approved_date = date('d/m/Y H:i', $receipt['approved_at']->toDateTime()->getTimestamp());
  } else {
    $approved_date = date('d/m/Y H:i', strtotime($receipt['approved_at']));
  }
}

$statusText = ['Chờ duyệt', 'Đã duyệt', 'Từ chối'];
$statusClass = ['pending', 'approved', 'rejected'];
$status = (int)($receipt['status'] ?? 0);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Chi tiết phiếu nhập</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      background: #f6f8fa;
      font-family: 'Segoe UI', Tahoma, sans-serif;
      color: #333;
    }

    .receipt-detail-container {
      max-width: 950px;
      margin: 10px auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .header {
      padding: 25px 10px;
      border-bottom: 1px solid #e9ecef;
      text-align: center;
      background: #fff;
    }

    .header h2 {
      margin: 0;
      font-size: 26px;
      color: #333;
      font-weight: 700;
    }

    .info {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px 30px;
      padding: 25px 35px;
      background: #fafbfc;
      border-bottom: 1px solid #eee;
    }

    .info p {
      margin: 4px 0;
      font-size: 15px;
    }

    .info b {
      color: #222;
    }

    .status {
      padding: 6px 12px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 14px;
    }
    .pending { background: #fff3cd; color: #856404; }
    .approved { background: #d4edda; color: #155724; }
    .rejected { background: #f8d7da; color: #721c24; }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 20px 0;
    }

    th, td {
      border: 1px solid #e1e4e8;
      padding: 10px 12px;
      text-align: center;
      font-size: 15px;
    }

    th {
      background: #f9fafb;
      font-weight: 600;
    }

    tr:hover td {
      background: #f1f7ff;
    }

    .total {
      text-align: right;
      font-size: 18px;
      font-weight: bold;
      margin: 25px 35px;
      color: #007bff;
    }

    .actions {
      text-align: center;
      padding: 20px 0 30px;
    }

    .btn {
      display: inline-block;
      padding: 12px 22px;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      transition: 0.25s;
      margin: 0 8px;
      cursor: pointer;
    }

    .btn-back {
      background: #6c757d;
      color: white;
    }

    .btn-approve {
      background: #28a745;
      color: white;
    }

    .btn-reject {
      background: #dc3545;
      color: white;
    }

    .btn:hover {
      opacity: 0.9;
      transform: translateY(-2px);
    }

    @media(max-width: 700px) {
      .info {
        grid-template-columns: 1fr;
        padding: 20px;
      }
    }
  </style>
</head>

<body>

<div class="receipt-detail-container">
  <div class="header">
    <h2><i class="fa-solid fa-file-invoice"></i> Chi tiết phiếu nhập</h2>
  </div>

  <div class="info">
    <p><b>Mã phiếu:</b> <?= htmlspecialchars($receipt['receipt_id']) ?></p>
    <p><b>Ngày tạo:</b> <?= $created_date ?></p>
    <p><b>Người tạo:</b> <?= htmlspecialchars($receipt['creator_name'] ?? $receipt['created_by']) ?></p>
    <p><b>Kho:</b> <?= htmlspecialchars($receipt['warehouse_id']) ?></p>
    <p><b>Loại phiếu:</b> <?= htmlspecialchars($receipt['type']) ?></p>
    <p><b>Trạng thái:</b> 
      <span class="status <?= $statusClass[$status] ?>"><?= $statusText[$status] ?></span>
    </p>
    <?php if ($status > 0 && isset($receipt['approved_by'])) { ?>
    <p><b>Người duyệt:</b> <?= htmlspecialchars($receipt['approved_by']) ?></p>
    <?php if ($approved_date) { ?>
    <p><b>Thời gian duyệt:</b> <?= $approved_date ?></p>
    <?php } ?>
    <?php } ?>
    <?php if (!empty($receipt['note'])) { ?>
    <p><b>Ghi chú:</b> <?= htmlspecialchars($receipt['note']) ?></p>
    <?php } ?>
  </div>

  <div style="padding: 0 25px;">
    <h3 style="margin-top: 20px; color: #333;">Danh sách sản phẩm</h3>
    <table>
      <thead>
        <tr>
          <th>Mã SP</th>
          <th>Tên sản phẩm</th>
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
            echo "<tr>
              <td>".htmlspecialchars($item['product_id'])."</td>
              <td>".htmlspecialchars($item['product_name'])."</td>
              <td>".htmlspecialchars($item['quantity'])."</td>
              <td>".number_format($item['unit_price'], 0, ',', '.')." đ</td>
              <td>".number_format($subtotal, 0, ',', '.')." đ</td>
            </tr>";
          }
        } else {
          echo "<tr><td colspan='5'>Không có sản phẩm nào trong phiếu.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

  <div class="total">
    Tổng tiền: <?= number_format($receipt['total_amount'] ?? 0, 0, ',', '.') ?> đ
  </div>

  <div class="actions">
    <a href="index.php?page=receipts/approve" class="btn btn-back">
      <i class="fa-solid fa-arrow-left"></i> Quay lại danh sách
    </a>

    <?php 
    // Kiểm tra quyền và trạng thái phiếu
    $user_role = $_SESSION['login']['role'] ?? '';
    $user_role_name = $_SESSION['login']['role_name'] ?? '';
    $user_role_id = $_SESSION['login']['role_id'] ?? '';
    
    // Cho phép các role quản lý duyệt phiếu
    $allowedRoles = ['manager', 'admin', 'QL_Kho_Tong', 'QL_Kho_CN'];
    $allowedRoleIds = [2, 4]; // role_id cho quản lý kho tổng và quản lý kho chi nhánh

    $hasPermission = in_array($user_role, $allowedRoles) || 
                    in_array($user_role_name, $allowedRoles) || 
                    in_array($user_role_id, $allowedRoleIds);
                    
    if ($status === 0 && $hasPermission): 
    ?>
    <button class="btn btn-approve" onclick="confirmAction('approve', '<?= $receipt['receipt_id'] ?>')">
      <i class="fa-solid fa-check"></i> Duyệt phiếu
    </button>

    <button class="btn btn-reject" onclick="confirmAction('reject', '<?= $receipt['receipt_id'] ?>')">
      <i class="fa-solid fa-xmark"></i> Từ chối
    </button>
    <?php endif; ?>
  </div>
</div>

<script>
function confirmAction(action, id) {
  const actionText = action === 'approve' ? 'duyệt' : 'từ chối';
  const icon = action === 'approve' ? 'success' : 'error';
  const color = action === 'approve' ? '#28a745' : '#dc3545';

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
      // Đường dẫn tương đối - process.php nằm cùng thư mục với detail.php
      window.location.href = `receipts/approve/process.php?action=${action}&id=${encodeURIComponent(id)}`;
    }
  });
}
</script>

</body>
</html>
