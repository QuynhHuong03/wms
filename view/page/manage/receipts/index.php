<?php
  include_once("../../../controller/cSupplier.php");
  include_once("../../../controller/cProduct.php");

  $supplierController = new CSupplier();
  $suppliers = $supplierController->getAllSuppliers();

  $productController = new CProduct();
  $products = $productController->getAllProducts();
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
    <form method="post" action="save_receipt.php">

      <!-- 🔹 Chọn loại phiếu nhập -->
      <label>Loại phiếu nhập</label>
      <select name="type" id="type" required onchange="toggleFields()">
        <option value="">-- Chọn loại phiếu --</option>
        <option value="purchase">Nhập từ nhà cung cấp</option>
        <option value="transfer">Nhập điều chuyển nội bộ</option>
        <option value="adjustment">Nhập kiểm kê / điều chỉnh</option>
      </select>

      <!-- 🔹 Nhà cung cấp (chỉ hiện khi chọn purchase) -->
      <div id="supplier-box" style="display:none;">
        <label>Nhà cung cấp</label>
        <select name="supplier_id">
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $s) { ?>
            <option value="<?= $s['_id'] ?>"><?= $s['supplier_name'] ?></option>
          <?php } ?>
        </select>
      </div>

      <!-- 🔹 Kho nguồn (chỉ hiện khi chọn transfer) -->
      <div id="source-box" style="display:none;">
        <label>Kho nguồn</label>
        <select name="source_warehouse_id">
          <option value="">-- Chọn kho nguồn --</option>
          <option value="WH01">Kho tổng</option>
          <option value="WH02">Kho chi nhánh</option>
        </select>
      </div>

      <!-- 🔹 Ghi chú (chỉ hiện khi chọn adjustment) -->
      <div id="note-box" style="display:none;">
        <label>Lý do / Ghi chú</label>
        <textarea name="note" rows="3" placeholder="Nhập lý do nhập kho..."></textarea>
      </div>

      <!-- 🔹 Phần thêm sản phẩm -->
      <div id="product-section" style="display:none;">
        <label>Thêm sản phẩm</label>
        <div class="barcode-box">
          <input type="text" id="barcode" placeholder="Nhập mã vạch..." autofocus>
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
      document.getElementById("note-box").style.display     = type === "adjustment" ? "block" : "none";
      document.getElementById("product-section").style.display = type ? "block" : "none";
    }

    // --- Quét barcode / thêm sản phẩm (giữ nguyên logic cũ) ---
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
      fetch("receipts/process.php?barcode=" + code)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            addOrUpdateRow(data.product);
            document.getElementById("barcode").value = "";
          } else {
            alert("Không tìm thấy sản phẩm!");
          }
        });
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
      const qty = row.querySelector("input[name*='[quantity]']").value || 0;
      const price = row.querySelector("input[name*='[price]']").value || 0;
      row.querySelector("input[name*='[subtotal]']").value = qty * price;
    }
  </script>
</body>
</html>
