<?php
// session_start();
// require_once "connect.php"; 
// $userId = $_SESSION['login']['user_id'] ?? "U001";

// $client = new MongoDB\Client("mongodb://127.0.0.1:27017");
// $db = $client->wms;
// $suppliers = $db->suppliers->find();
    error_reporting();
    include_once(__DIR__ . '/../../../../controller/cSupplier.php');
    $p = new CSupplier();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Tạo phiếu nhập hàng</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { 
        /* font-family: Arial, sans-serif;  */
        /* margin: 20px;  */
        background: #f9f9f9; 
    }
    .form-container { 
        max-width: 1100px; 
        margin: auto; 
        background: #fff; 
        padding: 20px; 
        border-radius: 10px; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.15); 
    }
    h2 { 
        text-align: center; 
        margin-bottom: 20px; 
    }
    label { 
        font-weight: bold; 
        margin-top: 10px; 
        display: block; 
    }
    select, input[type="text"], input[type="number"], textarea {
      width: 100%; 
      padding: 8px; 
      margin: 5px 0 10px; 
      border: 1px solid #ccc; 
      border-radius: 6px;
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-top: 15px; 
    }
    table th, table td { 
        border: 1px solid #ccc; 
        padding: 8px; 
        text-align: center; 
    }
    table th { 
        background: #f4f4f4; 
    }
    .btn { 
        background: #007bff; 
        color: #fff; 
        padding: 8px 14px; 
        border: none; 
        border-radius: 6px; 
        cursor: pointer; 
    }
    .btn:hover { 
        background: #0056b3; 
    }
    .btn-danger { 
        background: #dc3545; 
    }
    .btn-danger:hover { 
        background: #a71d2a; 
    }
    #reader { 
        width:400px; 
        margin-top:15px; 
        display:none; 
    }
    .barcode-box { 
        display:flex; 
        gap:10px; 
        align-items:center; 
    }
    .barcode-box input { 
        flex:1; 
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập hàng</h2>
    <form method="post" action="save_receipt.php">
      <label>Nhà cung cấp</label>
      <select name="supplier_id" required>
        <option value="">-- Chọn nhà cung cấp --</option>
        <?php foreach ($suppliers as $s) { ?>
          <option value="<?= $s['_id'] ?>"><?= $s['name'] ?></option>
        <?php } ?>
      </select>

      <label>Thêm sản phẩm</label>
      <div class="barcode-box">
        <input type="text" id="barcode" placeholder="Nhập mã vạch..." autofocus>
        <button type="button" class="btn" onclick="startScanner()">
          <i class="fa-solid fa-camera"></i> Camera
        </button>
        <button type="button" class="btn" onclick="useScanner()">
          <i class="fa-solid fa-barcode"></i> Scanner
        </button>
      </div>
      <div id="reader"></div>

      <h3>Danh sách sản phẩm</h3>
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
      <br>
      <button type="submit" class="btn"><i class="fa-solid fa-save"></i> Tạo phiếu</button>
    </form>
  </div>

  <script src="https://unpkg.com/html5-qrcode"></script>
  <script>
    let rowIndex = 0;
    let productMap = {};

    // --- Nhập barcode thủ công ---
    document.getElementById("barcode").addEventListener("keypress", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        let code = this.value.trim();
        if (code !== "") fetchProduct(code);
      }
    });

    // --- Nút Scanner USB (thực ra scanner USB gõ vào input luôn) ---
    function useScanner() {
      document.getElementById("barcode").focus();
      alert("Đặt con trỏ vào ô barcode và quét bằng máy scanner USB.");
    }

    // --- Quét bằng Camera ---
    let html5QrCode;
    function startScanner() {
      document.getElementById("reader").style.display = "block";
      html5QrCode = new Html5Qrcode("reader");

      html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        (decodedText) => {
          fetchProduct(decodedText);
          stopScanner();
        }
      ).catch(err => {
        alert("Không mở được camera: " + err);
      });
    }

    function stopScanner() {
      if (html5QrCode) {
        html5QrCode.stop().then(() => {
          document.getElementById("reader").style.display = "none";
        });
      }
    }

    // --- Gọi API lấy sản phẩm ---
    function fetchProduct(code) {
      fetch("get_product.php?barcode=" + code)
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
        productMap[product._id] = rowIndex;
        rowIndex++;
      }
    }

    function removeRow(btn, productId) {
      const row = btn.closest("tr");
      row.parentNode.removeChild(row);
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
