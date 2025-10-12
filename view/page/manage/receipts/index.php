<?php
  // ensure session for flash messages and default user/warehouse
  if (session_status() === PHP_SESSION_NONE) session_start();

  // include controllers using __DIR__ to make paths reliable
  $inc1 = @include_once(__DIR__ . '/../../../../controller/cSupplier.php');
  $inc2 = @include_once(__DIR__ . '/../../../../controller/cProduct.php');

  // include warehouse controller to populate "Kho nguồn" select
  $inc3 = @include_once(__DIR__ . '/../../../../controller/cWarehouse.php');

  if (class_exists('CWarehouse')) {
    $warehouseController = new CWarehouse();
    $warehouses = $warehouseController->getAllWarehouses();
    if (!is_array($warehouses)) $warehouses = [];
  } else {
    $warehouses = [];
    error_log('index.php: CWarehouse class not found (include path issue)');
    if (!isset($_SESSION['flash_receipt_error'])) $_SESSION['flash_receipt_error'] = 'Lỗi server: không tìm thấy controller kho.';
  }

  // If controllers aren't available, avoid fatal error and show a friendly message
  if (class_exists('CSupplier')) {
    $supplierController = new CSupplier();
    $suppliers = $supplierController->getAllSuppliers();
  } else {
    $suppliers = [];
    error_log('index.php: CSupplier class not found (include path issue)');
    if (!isset($_SESSION['flash_receipt_error'])) $_SESSION['flash_receipt_error'] = 'Lỗi server: không tìm thấy controller nhà cung cấp.';
  }

  if (class_exists('CProduct')) {
    $productController = new CProduct();
    $products = $productController->getAllProducts();
  } else {
    $products = [];
    error_log('index.php: CProduct class not found (include path issue)');
    if (!isset($_SESSION['flash_receipt_error'])) $_SESSION['flash_receipt_error'] = 'Lỗi server: không tìm thấy controller sản phẩm.';
  }

  // sensible defaults if not in session - try multiple places where the app may store login info
  $created_by = 'system';
  if (isset($_SESSION['user_id'])) {
    $created_by = $_SESSION['user_id'];
  } elseif (isset($_SESSION['login'])) {
    $login = $_SESSION['login'];
    if (is_array($login) && isset($login['user_id'])) $created_by = $login['user_id'];
    if (is_object($login) && isset($login->user_id)) $created_by = $login->user_id;
  }

  $warehouse_id = 'WH01';
  if (isset($_SESSION['warehouse_id'])) {
    $warehouse_id = $_SESSION['warehouse_id'];
  } elseif (isset($login)) {
    if (is_array($login) && isset($login['warehouse_id'])) $warehouse_id = $login['warehouse_id'];
    if (is_object($login) && isset($login->warehouse_id)) $warehouse_id = $login->warehouse_id;
  }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Tạo phiếu nhập hàng</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {font-family: "Segoe UI", Tahoma, sans-serif;background:#f3f6fa;margin:0;}
    .form-container {max-width:1100px;margin:auto;background:#fff;padding:25px 30px;
      border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}
    h2 {text-align:center;margin-bottom:25px;color:#333;font-weight:600;}
    label {font-weight:600;margin-top:12px;display:block;color:#444;}
    select,input[type="text"],input[type="number"],textarea {
      width:100%;padding:10px;margin:6px 0 14px;border:1px solid #d0d7de;border-radius:8px;font-size:14px;
    }
    select:focus,input:focus{border-color:#007bff;box-shadow:0 0 0 3px rgba(0,123,255,0.15);outline:none;}
    table {width:100%;border-collapse:collapse;margin-top:15px;border-radius:8px;overflow:hidden;}
    th,td {border:1px solid #e1e4e8;padding:10px;text-align:center;font-size:14px;}
    th {background:#f9fafb;font-weight:600;}
    .btn {background:#007bff;color:#fff;padding:8px 14px;border:none;border-radius:6px;cursor:pointer;font-size:14px;}
    .btn:hover {background:#0056b3;}
    .btn-danger {background:#dc3545;}
    .btn-danger:hover {background:#a71d2a;}
    .barcode-box {display:flex;gap:10px;align-items:center;margin-bottom:10px;}
    .barcode-box input {flex:1;}
    .action-buttons {margin-top:20px;display:flex;justify-content:flex-end;gap:10px;}
    #reader {width:100%;max-width:400px;margin:15px auto;border:2px solid #ddd;border-radius:10px;
      overflow:hidden;display:none;}
  </style>
</head>
<body>
  <div class="form-container">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập hàng</h2>
    <?php
      if (isset($_SESSION['flash_receipt'])) { echo '<div style="padding:10px;background:#e6ffed;border:1px solid #b7f0c6;margin-bottom:12px;color:#256029;">'.htmlspecialchars($_SESSION['flash_receipt']).'</div>'; unset($_SESSION['flash_receipt']); }
      if (isset($_SESSION['flash_receipt_error'])) { echo '<div style="padding:10px;background:#ffecec;border:1px solid #f5c2c2;margin-bottom:12px;color:#8a1f1f;">'.htmlspecialchars($_SESSION['flash_receipt_error']).'</div>'; unset($_SESSION['flash_receipt_error']); }
    ?>
    <form method="post" action="receipts/process.php">
      <!-- hidden meta fields required by process.php -->
      <input type="hidden" name="warehouse_id" value="<?= htmlspecialchars($warehouse_id) ?>">
      <input type="hidden" name="created_by" value="<?= htmlspecialchars($created_by) ?>">

      <!-- 🔹 Chọn loại phiếu nhập -->
      <label>Loại phiếu nhập</label>
      <select name="type" id="type" required onchange="toggleFields()">
        <option value="">-- Chọn loại phiếu --</option>
        <option value="purchase">Nhập từ nhà cung cấp</option>
        <option value="transfer">Nhập điều chuyển nội bộ</option>
      </select>

      <!-- 🔹 Nhà cung cấp (chỉ hiện khi chọn purchase) -->
      <div id="supplier-box" style="display:none;">
        <label>Nhà cung cấp</label>
        <select name="supplier_id">
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $s) { ?>
            <option value="<?= $s['supplier_id'] ?>"><?= $s['supplier_name'] ?></option>
          <?php } ?>
        </select>
      </div>

      <!-- 🔹 Kho nguồn (chỉ hiện khi chọn transfer) -->
      <div id="source-box" style="display:none;">
        <label>Kho nguồn</label>
        <select name="source_warehouse_id">
          <option value="">-- Chọn kho nguồn --</option>
          <?php
            if (!empty($warehouses) && is_array($warehouses)) {
              foreach ($warehouses as $w) {
                $val = isset($w['warehouse_id']) ? $w['warehouse_id'] : (isset($w['id']) ? $w['id'] : '');
                $label = isset($w['warehouse_name']) ? $w['warehouse_name'] : (isset($w['name']) ? $w['name'] : $val);
                echo '<option value="' . htmlspecialchars($val) . '">' . htmlspecialchars($label) . '</option>';
              }
            } else {
              echo '<option value="">(Không có kho)</option>';
            }
          ?>
        </select>
      </div>

      <!-- note-box removed because adjustment type was removed -->

      <!-- 🔹 Phần thêm sản phẩm -->
      <div id="product-section" style="display:none;">
        <label>Thêm sản phẩm</label>
        <div class="barcode-box">
          <input type="text" id="barcode" name="barcode_input" placeholder="Nhập mã vạch..." autofocus>
          <button type="button" class="btn" onclick="startScanner()"><i class="fa-solid fa-camera"></i> Camera</button>
          <button type="button" class="btn" onclick="useScanner()"><i class="fa-solid fa-barcode"></i> Scanner</button>
        </div>
        <div id="reader"></div>
        <button type="button" class="btn btn-danger" onclick="stopScanner()"><i class="fa-solid fa-power-off"></i> Tắt camera</button>

        <h3 style="margin-top:25px; color:#333;">Danh sách sản phẩm</h3>
        <table id="productTable">
          <thead>
            <tr>
              <th>Mã SP</th>
              <th>Tên SP</th>
              <th>ĐVT</th>
              <th>Số lượng</th>
              <th>Giá nhập</th>
              <th>Thành tiền</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <div class="action-buttons">
        <button type="submit" class="btn"><i class="fa-solid fa-save"></i> Tạo phiếu</button>
      </div>
    </form>
  </div>

  <script src="https://unpkg.com/html5-qrcode"></script>
  <script>
    // --- Hiển thị / Ẩn phần theo loại phiếu nhập ---
    function toggleFields() {
      const type = document.getElementById("type").value;
      document.getElementById("supplier-box").style.display = type === "purchase" ? "block" : "none";
      document.getElementById("source-box").style.display   = type === "transfer" ? "block" : "none";
      document.getElementById("product-section").style.display = type ? "block" : "none";
    }

    // --- Quét barcode / thêm sản phẩm (giữ logic tìm sản phẩm) ---
    let rowIndex = 0;
    let productMap = {};
    let html5QrCode;

    document.getElementById("barcode").addEventListener("keypress", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        let code = this.value.trim();
        if (code !== "") fetchProduct(code);
      }
    });

    function useScanner() {
      document.getElementById("barcode").focus();
      alert("Đặt con trỏ vào ô barcode và quét bằng máy scanner USB.");
    }

    function startScanner() {
      document.getElementById("reader").style.display = "block";
      html5QrCode = new Html5Qrcode("reader");
      html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, (decodedText) => {
        fetchProduct(decodedText);
        stopScanner();
      }).catch(err => alert("Không mở được camera: " + err));
    }

    function stopScanner() {
      if (html5QrCode) {
        html5QrCode.stop().then(() => document.getElementById("reader").style.display = "none");
      }
    }

    function fetchProduct(code) {
      fetch("receipts/process.php?barcode=" + encodeURIComponent(code))
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            addOrUpdateRow(data.product);
            document.getElementById("barcode").value = "";
          } else {
            alert(data.message || "Không tìm thấy sản phẩm!");
          }
        })
        .catch(err => alert('Lỗi khi tìm sản phẩm: ' + err));
    }

    function addOrUpdateRow(product) {
      if (productMap[product._id] !== undefined) {
        let row = document.querySelector(`#row-${productMap[product._id]}`);
        let qtyInput = row.querySelector("input[name*='[quantity]']");
        qtyInput.value = parseInt(qtyInput.value) + 1;
        calcSubtotal(qtyInput);
      } else {
        const tbody = document.getElementById("productTable").querySelector("tbody");
        const row = tbody.insertRow();
        row.id = "row-" + rowIndex;
        row.innerHTML = `
          <td><input type="hidden" name="products[${rowIndex}][product_id]" value="${product._id}">${product._id}</td>
          <td>${product.name}</td>
          <td>${product.unit}</td>
          <td><input type="number" name="products[${rowIndex}][quantity]" value="1" min="1" oninput="calcSubtotal(this)"></td>
          <td><input type="number" name="products[${rowIndex}][price]" value="${product.import_price}" min="0" oninput="calcSubtotal(this)"></td>
          <td><input type="text" name="products[${rowIndex}][subtotal]" value="${product.import_price}" readonly></td>
          <td><button type="button" class="btn btn-danger" onclick="removeRow(this,'${product._id}')">Xóa</button></td>
        `;
        productMap[product._id] = rowIndex++;
      }
    }

    function removeRow(btn, productId) {
      const row = btn.closest("tr");
      row.remove();
      delete productMap[productId];
    }

    function calcSubtotal(input) {
      const row = input.closest("tr");
      const qty = parseFloat(row.querySelector("input[name*='[quantity]']").value) || 0;
      const price = parseFloat(row.querySelector("input[name*='[price]']").value) || 0;
      row.querySelector("input[name*='[subtotal]']").value = qty * price;
    }
  </script>
</body>
</html>
