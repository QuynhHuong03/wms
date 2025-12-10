<?php
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

$cWarehouse = new CWarehouse();
$warehouse = null;

if (isset($_GET['id'])) {
    $warehouseId = $_GET['id'];
    $warehouses = $cWarehouse->getAllWarehouses();

    foreach ($warehouses as $w) {
        if ($w['warehouse_id'] === $warehouseId) {
            $warehouse = $w;
            break;
        }
    }
}

if (!$warehouse) {
    echo "<script>
        alert('Không tìm thấy kho.');
        window.location.href = '../index.php?page=warehouse';
    </script>";
    exit();
}

// Parse address từ warehouse
$street = '';
$ward = '';
$province = '';

if (isset($warehouse['address'])) {
    if (is_array($warehouse['address'])) {
        $street = $warehouse['address']['street'] ?? '';
        $ward = $warehouse['address']['ward'] ?? ($warehouse['address']['city'] ?? '');
        $province = $warehouse['address']['province'] ?? '';
    } else {
        // Nếu address là string, thử tách ra
        $addrStr = (string)$warehouse['address'];
        $parts = array_map('trim', explode(',', $addrStr));
        if (count($parts) >= 3) {
            $street = $parts[0];
            $ward = $parts[1];
            $province = $parts[2];
        } elseif (count($parts) == 2) {
            $street = $parts[0];
            $province = $parts[1];
        } else {
            $street = $addrStr;
        }
    }
} elseif (!empty($warehouse['address_text'])) {
    $addrStr = (string)$warehouse['address_text'];
    $parts = array_map('trim', explode(',', $addrStr));
    if (count($parts) >= 3) {
        $street = $parts[0];
        $ward = $parts[1];
        $province = $parts[2];
    } elseif (count($parts) == 2) {
        $street = $parts[0];
        $province = $parts[1];
    } else {
        $street = $addrStr;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật kho</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* Reuse create user page styling to match UI */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f0f3f8;
        color: #1f2937;
        margin: 0;
        padding: 20px 0;
    }

    .page-header {
        width: 90%;
        max-width: 700px;
        margin: 30px auto 15px;
        padding-left: 10px;
        border-left: 4px solid #2563eb;
    }

    .page-header h2 { margin: 0; color: #111827; font-size: 2rem; font-weight: 700; }
    .page-header p { margin: 5px 0 0; color: #6b7280; font-size: 1rem; }

    .container {
        width: 90%; max-width: 700px; margin: 20px auto; background: #ffffff; border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px 40px;
    }

    .form-group { margin-bottom: 20px; }
    .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#374151; }
    .form-group input, .form-group select { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; background:#f9fafb; }
    .form-group input:focus, .form-group select:focus { border-color:#2563eb; outline:none; background:#fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }

    .form-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:30px; }
    .form-actions a, .form-actions button { padding:12px 25px; border-radius:8px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
    .btn-success { background:#10b981; color:#fff; }
    .btn-success:hover { background:#059669; }
    .btn-secondary { background:#9ca3af; color:#fff; }
    .btn-secondary:hover { background:#6b7280; }

    /* Modal */
    .modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:auto; background: rgba(0,0,0,0.5); }
    .modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:35px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center; }
    .modal-actions { display:flex; gap:12px; justify-content:center; margin-top:20px; }
    #cancelModalBtn { background:#e5e7eb; color:#374151; padding:12px 28px; border-radius:8px; border:none; }
    #confirmUpdateBtn { background:#10b981; color:#fff; padding:12px 28px; border-radius:8px; border:none; }

    @media (max-width:576px) { .container { padding:20px; } .page-header { margin-top:15px; } .form-actions { flex-direction:column; } }
    </style>
</head>
<body>

  <div class="page-header">
    <h2>Cập nhật kho</h2>
    <p>Cập nhật thông tin kho chi nhánh</p>
  </div>

  <div class="container">
    <form id="warehouseForm" action="/view/page/manage/warehouse/updateWarehouse/process.php" method="post" onsubmit="return handleSubmit(event)">
      <input type="hidden" name="warehouse_id" value="<?php echo $warehouse['warehouse_id']; ?>">

      <div class="form-group">
        <label for="warehouse_name">Tên kho</label>
        <input type="text" id="warehouse_name" name="warehouse_name" value="<?php echo htmlspecialchars($warehouse['warehouse_name']); ?>" required>
      </div>

      <div class="form-group">
        <label for="province_name">Tỉnh/Thành phố</label>
        <input type="text" id="province_name" name="province_name" placeholder="Nhập tỉnh/thành phố..." value="<?php echo htmlspecialchars($province); ?>" required>
      </div>

      <div class="form-group">
        <label for="ward_name">Phường/Xã</label>
        <input type="text" id="ward_name" name="ward_name" placeholder="Nhập phường/xã..." value="<?php echo htmlspecialchars($ward); ?>" required>
      </div>

      <div class="form-group">
        <label for="street">Tên đường</label>
        <input type="text" id="street" name="street" placeholder="Nhập tên đường..." value="<?php echo htmlspecialchars($street); ?>" required>
      </div>

      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status" required>
            <option value="1" <?php echo $warehouse['status'] == 1 ? 'selected' : ''; ?>>Đang hoạt động</option>
            <option value="0" <?php echo $warehouse['status'] == 0 ? 'selected' : ''; ?>>Ngừng hoạt động</option>
        </select>
      </div>

      <div class="form-actions">
        <a href="../manage/index.php?page=warehouse" class="btn-secondary">Quay lại</a>
        <button type="submit" name="btnUpdate" class="btn-success">Cập nhật</button>
      </div>
    </form>
  </div>

  <!-- Modal xác nhận cập nhật -->
  <div id="confirmModal" class="modal">
    <div class="modal-content">
      <h3>Xác nhận cập nhật</h3>
      <p>Bạn có chắc chắn muốn cập nhật thông tin kho này không?</p>
      <div class="modal-actions">
        <button id="cancelModalBtn" type="button">Hủy</button>
        <button id="confirmUpdateBtn" type="button">Xác nhận</button>
      </div>
    </div>
  </div>

  <script>
  // Modal confirm logic
  const confirmModal = document.getElementById('confirmModal');
  const confirmUpdateBtn = document.getElementById('confirmUpdateBtn');
  const cancelModalBtn = document.getElementById('cancelModalBtn');
  const form = document.getElementById('warehouseForm');
  let isConfirmed = false;

  function handleSubmit(e) {
    if (!isConfirmed) {
      e.preventDefault();
      confirmModal.style.display = 'block';
      return false;
    }
    return true;
  }

  confirmUpdateBtn.addEventListener('click', function() {
      isConfirmed = true;
      confirmModal.style.display = 'none';
      form.submit();
  });

  cancelModalBtn.addEventListener('click', function() {
      confirmModal.style.display = 'none';
      isConfirmed = false;
  });

  window.addEventListener('click', function(event) {
      if (event.target === confirmModal) {
          confirmModal.style.display = 'none';
          isConfirmed = false;
      }
  });
  </script>

</body>
</html>