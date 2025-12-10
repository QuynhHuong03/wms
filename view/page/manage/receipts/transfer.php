<?php
  if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']); session_start(); }

  include_once(__DIR__ . '/../../../../controller/cSupplier.php');
  include_once(__DIR__ . '/../../../../controller/cProduct.php');
  include_once(__DIR__ . '/../../../../controller/cWarehouse.php');

  $warehouseController = new CWarehouse();
  $warehouses = $warehouseController->getAllWarehouses() ?? [];

  $supplierController = new CSupplier();
  $suppliers = $supplierController->getAllSuppliers() ?? [];

  $productController = new CProduct();
  $products = $productController->getAllProducts() ?? [];

  $created_by = $_SESSION['user_id'] ?? ($_SESSION['login']['user_id'] ?? 'system');
  $warehouse_id = $_SESSION['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? 'WH01');
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
    table {width:100%;border-collapse:collapse;margin-top:15px;border-radius:8px;overflow:hidden;min-width:1200px;}
    th,td {border:1px solid #e1e4e8;padding:10px;text-align:center;font-size:13px;}
    th {background:#f9fafb;font-weight:600;white-space:nowrap;}
    .dimension-display, .weight-display, .volume-display {
      font-weight:600;
      color:#333;
      padding:4px 8px;
      background:#f0f6ff;
      border-radius:4px;
      display:inline-block;
    }
    .form-container {overflow-x:auto;}
    .btn {background:#007bff;color:#fff;padding:8px 14px;border:none;border-radius:6px;cursor:pointer;font-size:14px;}
    .btn:hover {background:#0056b3;}
    .btn-danger {background:#dc3545;}
    .btn-danger:hover {background:#a71d2a;}
    .barcode-box {display:flex;gap:10px;align-items:center;margin-bottom:10px;}
    .barcode-box input {flex:1;}
    .action-buttons {margin-top:20px;display:flex;justify-content:flex-end;gap:10px;}
    #reader {width:100%;max-width:400px;margin:15px auto;border:2px solid #ddd;border-radius:10px;
      overflow:hidden;display:none;}
    /* Ẩn cột Lô hàng mặc định */
    .batch-column {display:none;}
  </style>
</head>
<body>
  <div class="form-container">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập hàng</h2>
    <form method="post" action="receipts/process.php" id="receiptForm">
      <input type="hidden" name="warehouse_id" value="<?= htmlspecialchars($warehouse_id) ?>">
      <input type="hidden" name="created_by" value="<?= htmlspecialchars($created_by) ?>">
      <!-- Hidden field để lưu export_id khi submit form -->
      <input type="hidden" id="export_id_hidden" name="export_id" value="">

      <label>Loại phiếu nhập <span style="color:red;">*</span></label>
      <select name="type" id="type" required onchange="toggleFields()" style="font-weight:600;font-size:15px;">
        <option value="">-- Chọn loại phiếu nhập --</option>
        <option value="purchase">Nhập từ nhà cung cấp (Bên ngoài)</option>
        <option value="transfer">Nhập điều chuyển nội bộ (Từ kho khác)</option>
      </select>
      <div id="type-description" style="margin-top:8px;padding:10px;border-radius:6px;display:none;"></div>

      <div id="supplier-box" style="display:none;">
        <label>Nhà cung cấp</label>
        <select name="supplier_id">
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>"><?= $s['supplier_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="source-box" style="display:none;">
        <label>Kho nguồn</label>
        <select name="source_warehouse_id" id="source_warehouse_id" onchange="loadExports()">
          <option value="">-- Chọn kho nguồn --</option>
          <?php foreach ($warehouses as $w): ?>
            <option value="<?= $w['warehouse_id'] ?>"><?= $w['warehouse_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="export-box" style="display:none;">
        <label>Phiếu xuất từ kho nguồn</label>
        <select name="export_id" id="export_id" onchange="loadExportProducts()">
          <option value="">-- Chọn phiếu xuất --</option>
        </select>
        <div id="export-info" style="margin-top:10px;padding:10px;background:#f0f6ff;border-radius:6px;display:none;">
          <strong>Thông tin phiếu xuất:</strong>
          <div id="export-details"></div>
        </div>
        <div style="margin-top:10px;padding:8px;background:#fff3cd;border-radius:4px;font-size:12px;color:#856404;">
          <strong>ℹ️ Lưu ý:</strong> Chỉ hiển thị phiếu xuất từ kho nguồn đến kho hiện tại của bạn (<?= $warehouse_id ?>)
        </div>
      </div>

      <div id="product-section" style="display:none;">
        <label>Thêm sản phẩm</label>
        <div class="barcode-box">
          <input type="text" id="barcode" name="barcode_input" placeholder="Nhập mã vạch..." autofocus>
          <button type="button" class="btn" onclick="startScanner()"><i class="fa-solid fa-camera"></i> Camera</button>
          <button type="button" class="btn" onclick="useScanner()"><i class="fa-solid fa-barcode"></i> Scanner</button>
          <button type="button" class="btn" onclick="openManualModal()"><i class="fa-solid fa-plus"></i> Thêm thủ công</button>
        </div>
        <div id="reader"></div>
        <button type="button" class="btn btn-danger" onclick="stopScanner()"><i class="fa-solid fa-power-off"></i> Tắt camera</button>

        <h3 style="margin-top:25px; color:#333;">Danh sách sản phẩm</h3>
        <table id="productTable">
          <thead>
            <tr>
              <th>Mã SKU</th>
              <th>Tên SP</th>
              <th class="batch-column">Lô hàng</th>
              <th>ĐVT</th>
              <th>Số lượng</th>
              <th>Kích thước (W×D×H cm)</th>
              <th>Trọng lượng (kg)</th>
              <th>Thể tích (cm³)</th>
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
    // Form validation
    document.getElementById("receiptForm").addEventListener("submit", function(e) {
      const type = document.getElementById("type").value;
      const exportId = document.getElementById("export_id_hidden").value;
      
      // ⭐ Validation cho TRANSFER
      if (type === "transfer") {
        const sourceWarehouseId = document.getElementById("source_warehouse_id").value;
        if (!sourceWarehouseId) {
          e.preventDefault();
          alert("❌ NHẬP ĐIỀU CHUYỂN: Vui lòng chọn kho nguồn!");
          return false;
        }
        
        if (!exportId) {
          e.preventDefault();
          alert("❌ NHẬP ĐIỀU CHUYỂN: Vui lòng chọn phiếu xuất từ kho nguồn!\n\nKhông có phiếu xuất = không thể nhập điều chuyển nội bộ.");
          return false;
        }
        
        // Debug: Log giá trị sẽ được gửi
        console.log("=== TRANSFER RECEIPT SUBMIT ===");
        console.log("Type:", type);
        console.log("Source Warehouse ID:", sourceWarehouseId);
        console.log("Export ID:", exportId);
      }
      
      // ⭐ Validation cho PURCHASE
      if (type === "purchase") {
        const supplierId = document.querySelector("select[name='supplier_id']").value;
        if (!supplierId) {
          const confirm = window.confirm("⚠️ Bạn chưa chọn nhà cung cấp.\n\nTiếp tục tạo phiếu không có nhà cung cấp?");
          if (!confirm) {
            e.preventDefault();
            return false;
          }
        }
        console.log("=== PURCHASE RECEIPT SUBMIT ===");
        console.log("Type:", type);
        console.log("Supplier ID:", supplierId);
      }
      
      // Nếu là nhập điều chuyển và đã chọn phiếu xuất
      if (type === "transfer" && exportId && Object.keys(exportProducts).length > 0) {
        // Kiểm tra xem đã quét tất cả sản phẩm chưa
        const tbody = document.getElementById("productTable").querySelector("tbody");
        const scannedProducts = {};
        
        tbody.querySelectorAll("tr").forEach(row => {
          const productId = row.querySelector("input[name*='[product_id]']").value;
          const qty = parseInt(row.querySelector("input[name*='[quantity]']").value) || 0;
          scannedProducts[productId] = qty;
        });
        
        let missing = [];
        for (let productId in exportProducts) {
          const expectedQty = exportProducts[productId].quantity;
          const scannedQty = scannedProducts[productId] || 0;
          
          if (scannedQty < expectedQty) {
            missing.push(`${exportProducts[productId].product_name}: còn thiếu ${expectedQty - scannedQty} ${exportProducts[productId].unit}`);
          }
        }
        
        if (missing.length > 0) {
          const confirm = window.confirm("⚠️ Chưa quét đủ sản phẩm:\n\n" + missing.join("\n") + "\n\nBạn có chắc muốn tiếp tục?");
          if (!confirm) {
            e.preventDefault();
            return false;
          }
        }
      }
      
      return true;
    });
    
    // Manual add modal
    function openManualModal(){
      const name = prompt('Tên sản phẩm');
      if (name === null) return;
      const sku = prompt('Mã SP (SKU)');
      if (sku === null) return;
      const unit = prompt('Đơn vị (ví dụ: Cái)') || 'Cái';
      const qtyStr = prompt('Số lượng', '1');
      if (qtyStr === null) return;
      const priceStr = prompt('Giá nhập', '0');
      if (priceStr === null) return;
      const qty = Math.max(1, parseFloat(qtyStr)||1);
      const price = Math.max(0, parseFloat(priceStr)||0);
      const tempId = 'manual_' + Date.now();
      const product = { _id: tempId, sku: sku || tempId, product_name: name || sku || tempId, baseUnit: unit, conversionUnits: [], purchase_price: price };
      // Ensure row is added and then set qty/price
      addOrUpdateRow(product);
      // Set the last inserted row quantities and price if needed
      const row = document.querySelector(`#row-${rowIndex-1}`);
      if (row) {
        const qtyInput = row.querySelector("input[name*='[quantity]']");
        const priceInput = row.querySelector("input[name*='[price]']");
        if (qtyInput) qtyInput.value = qty;
        if (priceInput) priceInput.value = price;
        calcSubtotal(qtyInput || priceInput);
      }
    }

    let exportProducts = {}; // Lưu danh sách sản phẩm từ phiếu xuất

    function toggleFields() {
      const type = document.getElementById("type").value;
      const exportSelect = document.getElementById("export_id");
      const batchColumns = document.querySelectorAll(".batch-column");
      const descBox = document.getElementById("type-description");
      
      // Hiển thị mô tả loại phiếu
      if (type === "purchase") {
        descBox.style.display = "block";
        descBox.style.background = "#e7f3ff";
        descBox.style.borderLeft = "4px solid #2196F3";
        descBox.innerHTML = `
          <strong>📝 Nhập từ nhà cung cấp:</strong><br>
          • Hàng đến từ bên ngoài (nhà cung cấp, đối tác)<br>
          • Không cần chọn phiếu xuất<br>
          • Sẽ tạo lô hàng mới cho sản phẩm
        `;
      } else if (type === "transfer") {
        descBox.style.display = "block";
        descBox.style.background = "#fff4e6";
        descBox.style.borderLeft = "4px solid #FF9800";
        descBox.innerHTML = `
          <strong>🔄 Nhập điều chuyển nội bộ:</strong><br>
          • Hàng đến từ kho khác trong hệ thống<br>
          • <strong style="color:#d32f2f;">BẮT BUỘC</strong> chọn phiếu xuất từ kho nguồn<br>
          • Sẽ giữ nguyên mã lô hàng và vị trí nguồn
        `;
      } else {
        descBox.style.display = "none";
      }
      
      document.getElementById("supplier-box").style.display = type === "purchase" ? "block" : "none";
      document.getElementById("source-box").style.display   = type === "transfer" ? "block" : "none";
      document.getElementById("export-box").style.display = "none"; // Ẩn export-box ban đầu
      document.getElementById("product-section").style.display = type ? "block" : "none";
      
      // Ẩn cột Lô hàng khi nhập từ nhà cung cấp, hiện khi nhập điều chuyển
      if (type === "purchase") {
        batchColumns.forEach(col => col.style.display = "none");
      } else if (type === "transfer") {
        batchColumns.forEach(col => col.style.display = "table-cell");
      }
      
      // Set/remove required cho export_id dựa vào loại phiếu
      if (type === "transfer") {
        exportSelect.setAttribute("required", "required");
      } else {
        exportSelect.removeAttribute("required");
      }
      
      // Reset khi đổi loại phiếu
      if (type !== "transfer") {
        exportSelect.innerHTML = '<option value="">-- Chọn phiếu xuất --</option>';
        exportProducts = {};
      }
    }

    function loadExports() {
      const sourceWarehouseId = document.getElementById("source_warehouse_id").value;
      const currentWarehouseId = "<?= $warehouse_id ?>";
      const exportSelect = document.getElementById("export_id");
      
      if (!sourceWarehouseId) {
        document.getElementById("export-box").style.display = "none";
        exportSelect.removeAttribute("required");
        return;
      }

      // Hiển thị export-box, set required và load danh sách phiếu xuất
      document.getElementById("export-box").style.display = "block";
      exportSelect.setAttribute("required", "required");
      
      fetch(`receipts/process.php?action=get_exports&source_warehouse=${sourceWarehouseId}&destination_warehouse=${currentWarehouseId}`)
        .then(res => res.json())
        .then(data => {
          const select = document.getElementById("export_id");
          select.innerHTML = '<option value="">-- Chọn phiếu xuất --</option>';
          
          if (data.success && data.exports.length > 0) {
            data.exports.forEach(exp => {
              const option = document.createElement('option');
              option.value = exp._id;
              option.textContent = `${exp.receipt_id} - ${exp.created_at_formatted} (${exp.product_count} SP)`;
              select.appendChild(option);
            });
          } else {
            select.innerHTML = '<option value="">Không có phiếu xuất nào</option>';
          }
        })
        .catch(err => {
          console.error('Lỗi load phiếu xuất:', err);
          alert('Lỗi khi tải danh sách phiếu xuất');
        });
    }

    function loadExportProducts() {
      const exportId = document.getElementById("export_id").value;
      
      if (!exportId) {
        document.getElementById("export-info").style.display = "none";
        document.getElementById("export_id_hidden").value = "";
        exportProducts = {};
        return;
      }

      // Cập nhật export_id vào hidden field
      document.getElementById("export_id_hidden").value = exportId;

      fetch(`receipts/process.php?action=get_export_details&export_id=${exportId}`)
        .then(res => res.json())
        .then(data => {
          console.log("=== API Response ===", data); // Debug toàn bộ response
          if (data.success) {
            // Lưu thông tin sản phẩm từ phiếu xuất - đọc từ products hoặc details
            const productList = data.export.products || data.export.details || [];
            console.log("Product list source:", data.export.products ? 'products' : 'details');
            console.log("Product list:", JSON.stringify(productList, null, 2));
            
            exportProducts = {};
            productList.forEach(p => {
              exportProducts[p.product_id] = {
                ...p,
                scanned_qty: 0
              };
            });
            
            // Hiển thị thông tin phiếu xuất và danh sách sản phẩm với batches
            const infoDiv = document.getElementById("export-info");
            const detailsDiv = document.getElementById("export-details");
            
            let productListHtml = '<ul style="margin-top:10px;font-size:13px;">';
            productList.forEach(p => {
              console.log("Product batches:", p.product_name, p.batches); // Debug
              
              let batchInfo = '';
              if (p.batches && p.batches.length > 0) {
                batchInfo = '<br><span style="color:#666;font-size:12px;">📦 Lô: ' + 
                  p.batches.map(b => {
                    const locationInfo = b.location_text ? ` 📍 ${b.location_text}` : '';
                    return `${b.batch_code} (${b.quantity} cái)${locationInfo}`;
                  }).join(', ') + 
                  '</span>';
              } else {
                batchInfo = '<br><span style="color:#dc3545;font-size:12px;">⚠️ Chưa có thông tin lô hàng</span>';
              }
              productListHtml += `<li>${p.product_name}: <strong>${p.quantity} ${p.unit}</strong>${batchInfo}</li>`;
            });
            productListHtml += '</ul>';
            
            detailsDiv.innerHTML = `
              <div style="margin-top:5px;">
                <strong>Mã phiếu:</strong> ${data.export.receipt_id}<br>
                <strong>Ngày tạo:</strong> ${data.export.created_at_formatted}<br>
                <strong>Tổng SP:</strong> ${productList.length} sản phẩm
                ${productListHtml}
              </div>
            `;
            infoDiv.style.display = "block";
            
            console.log("Loaded export products:", exportProducts);
          } else {
            alert(data.message || "Không tải được thông tin phiếu xuất");
          }
        })
        .catch(err => {
          console.error('Lỗi load chi tiết phiếu xuất:', err);
          alert('Lỗi khi tải chi tiết phiếu xuất');
        });
    }

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
      if (html5QrCode) html5QrCode.stop().then(() => document.getElementById("reader").style.display = "none");
    }

    function fetchProduct(code) {
      const type = document.getElementById("type").value;
      const exportId = document.getElementById("export_id") ? document.getElementById("export_id").value : '';
      
      // Nếu là nhập điều chuyển và đã chọn phiếu xuất, kiểm tra sản phẩm có trong phiếu không
      if (type === "transfer" && exportId && Object.keys(exportProducts).length > 0) {
        fetch("receipts/process.php?barcode=" + encodeURIComponent(code))
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              const productId = data.product._id;
              
              // Kiểm tra sản phẩm có trong phiếu xuất không
              if (!exportProducts[productId]) {
                alert(`⚠️ Sản phẩm "${data.product.product_name}" KHÔNG có trong phiếu xuất đã chọn!`);
                document.getElementById("barcode").value = "";
                return;
              }
              
              // Kiểm tra số lượng đã quét
              const exportQty = exportProducts[productId].quantity;
              const scannedQty = exportProducts[productId].scanned_qty || 0;
              
              if (scannedQty >= exportQty) {
                alert(`⚠️ Đã quét đủ số lượng cho sản phẩm "${data.product.product_name}" (${exportQty} ${exportProducts[productId].unit})`);
                document.getElementById("barcode").value = "";
                return;
              }
              
              // Sử dụng giá từ phiếu xuất
              data.product.purchase_price = exportProducts[productId].unit_price;
              data.product.export_unit = exportProducts[productId].unit;
              data.product.export_qty = exportQty;
              
              console.log("✅ Sản phẩm hợp lệ từ phiếu xuất:", data.product);
              addOrUpdateRow(data.product);
              
              // Cập nhật số lượng đã quét
              exportProducts[productId].scanned_qty = (exportProducts[productId].scanned_qty || 0) + 1;
              
              document.getElementById("barcode").value = "";
            } else {
              alert(data.message || "Không tìm thấy sản phẩm!");
            }
          })
          .catch(err => alert('Lỗi khi tìm sản phẩm: ' + err));
      } else {
        // Nhập từ nhà cung cấp - không kiểm tra phiếu xuất
        fetch("receipts/process.php?barcode=" + encodeURIComponent(code))
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              console.log("Sản phẩm nhận được:", data.product);
              addOrUpdateRow(data.product);
              document.getElementById("barcode").value = "";
            } else {
              alert(data.message || "Không tìm thấy sản phẩm!");
            }
          })
          .catch(err => alert('Lỗi khi tìm sản phẩm: ' + err));
      }
    }

    function addOrUpdateRow(product) {
      console.log("addOrUpdateRow - Product ID:", product._id); // ✅ Debug
      console.log("productMap hiện tại:", productMap); // ✅ Debug
      
      // Kiểm tra xem có phải nhập từ phiếu xuất không
      const type = document.getElementById("type").value;
      const exportId = document.getElementById("export_id") ? document.getElementById("export_id").value : '';
      const isFromExport = (type === "transfer" && exportId && product.export_unit);
      
      if (productMap[product._id]) {
        console.log("Sản phẩm đã tồn tại - tăng số lượng"); // ✅ Debug
        let row = document.querySelector(`#row-${productMap[product._id]}`);
        let qtyInput = row.querySelector("input[name*='[quantity]']");
        
        // Kiểm tra số lượng tối đa nếu từ phiếu xuất
        if (isFromExport && exportProducts[product._id]) {
          const currentQty = parseInt(qtyInput.value) || 0;
          const maxQty = exportProducts[product._id].export_qty || exportProducts[product._id].quantity;
          
          if (currentQty >= maxQty) {
            alert(`⚠️ Đã đủ số lượng cho sản phẩm này (${maxQty} ${exportProducts[product._id].unit})`);
            return;
          }
        }
        
        qtyInput.value = parseInt(qtyInput.value) + 1;
        calcSubtotal(qtyInput);
        updateDimensions(qtyInput);
      } else {
        console.log("Thêm sản phẩm mới vào bảng"); // ✅ Debug
        console.log("Product data:", product); // ✅ Debug xem dữ liệu
        
        const tbody = document.getElementById("productTable").querySelector("tbody");
        const row = tbody.insertRow();
        row.id = "row-" + rowIndex;

        // ✅ Lưu thông tin kích thước, trọng lượng, thể tích cho từng đơn vị
        const baseUnit = product.baseUnit || 'Cái';
        const baseDim = product.package_dimensions || {};
        const baseWeight = parseFloat(product.package_weight) || 0;
        const baseVolume = parseFloat(product.volume_per_unit) || 0;
        
        const baseWidth = parseFloat(baseDim.width) || 0;
        const baseDepth = parseFloat(baseDim.depth) || 0;
        const baseHeight = parseFloat(baseDim.height) || 0;
        
        // ✅ Hiển thị thông báo nếu chưa có dữ liệu kích thước
        const dimensionText = (baseWidth === 0 && baseDepth === 0 && baseHeight === 0) 
          ? '<span style="color:#dc3545;">Chưa có dữ liệu</span>' 
          : `${baseWidth}×${baseDepth}×${baseHeight}`;
        const weightText = baseWeight === 0 ? '<span style="color:#dc3545;">-</span>' : baseWeight;
        const volumeText = baseVolume === 0 ? '<span style="color:#dc3545;">-</span>' : baseVolume;
        
        console.log("Base dimensions:", baseWidth, baseDepth, baseHeight); // ✅ Debug
        
        // ✅ Nếu từ phiếu xuất, chỉ hiển thị đơn vị từ phiếu xuất
        let unitOptions;
        let selectedUnit;
        let unitPrice;
        
        if (isFromExport && exportProducts[product._id]) {
          const exportUnit = exportProducts[product._id].unit;
          const exportPrice = exportProducts[product._id].unit_price;
          selectedUnit = exportUnit;
          unitPrice = exportPrice;
          
          // Chỉ hiển thị đơn vị từ phiếu xuất (readonly)
          unitOptions = `<option value="${exportUnit}" 
            data-factor="1"
            data-width="${baseWidth}"
            data-depth="${baseDepth}"
            data-height="${baseHeight}"
            data-weight="${baseWeight}"
            data-volume="${baseVolume}">${exportUnit}</option>`;
        } else {
          // Nhập từ nhà cung cấp - hiển thị tất cả đơn vị
          selectedUnit = baseUnit;
          unitPrice = product.purchase_price || 0;
          
          unitOptions = `<option value="${baseUnit}" 
            data-factor="1"
            data-width="${baseWidth}"
            data-depth="${baseDepth}"
            data-height="${baseHeight}"
            data-weight="${baseWeight}"
            data-volume="${baseVolume}">${baseUnit}</option>`;
          
          if (product.conversionUnits && Array.isArray(product.conversionUnits) && product.conversionUnits.length > 0) {
            product.conversionUnits.forEach(u => {
              if (u.unit && u.factor) {
                const dims = u.dimensions || {};
                const convWidth = parseFloat(dims.width) || 0;
                const convDepth = parseFloat(dims.depth) || 0;
                const convHeight = parseFloat(dims.height) || 0;
                const convWeight = parseFloat(u.weight) || 0;
                const convVolume = parseFloat(u.volume) || 0;
                
                unitOptions += `<option value="${u.unit}" 
                  data-factor="${u.factor}"
                  data-width="${convWidth}"
                  data-depth="${convDepth}"
                  data-height="${convHeight}"
                  data-weight="${convWeight}"
                  data-volume="${convVolume}">${u.unit} (x${u.factor})</option>`;
              }
            });
          }
        }

        const unitSelectDisabled = isFromExport ? 'disabled' : '';
        const maxQtyAttr = isFromExport && exportProducts[product._id] ? `max="${exportProducts[product._id].quantity}"` : '';
        
        // Hiển thị thông tin batches nếu nhập từ phiếu xuất
        let batchDisplay = '-';
        if (isFromExport && exportProducts[product._id] && exportProducts[product._id].batches) {
          const batches = exportProducts[product._id].batches;
          batchDisplay = batches.map(b => {
            const locationInfo = b.location_text ? `<br><small style="color:#666;">📍 ${b.location_text}</small>` : '';
            return `<span style="display:inline-block;background:#e3f2fd;padding:4px 8px;margin:2px;border-radius:4px;font-size:12px;">
              📦 ${b.batch_code} (${b.quantity})${locationInfo}
            </span>`;
          }).join('');
        }

        // ⭐ Lưu batch info vào hidden field để gửi lên server (cho transfer)
        let batchesJson = '';
        if (isFromExport && exportProducts[product._id] && exportProducts[product._id].batches) {
          batchesJson = JSON.stringify(exportProducts[product._id].batches);
        }
        
        row.innerHTML = `
          <td>
            <input type="hidden" name="products[${rowIndex}][product_id]" value="${product._id}">
            <input type="hidden" name="products[${rowIndex}][product_name]" value="${product.product_name}">
            <input type="hidden" name="products[${rowIndex}][batches]" value='${batchesJson}'>
            ${product.sku || product._id}
          </td>
          <td>${product.product_name}</td>
          <td class="batch-column">${batchDisplay}</td>
          <td>
            <select name="products[${rowIndex}][unit]" class="unit-select" onchange="updateByUnit(this)" ${unitSelectDisabled}>
              ${unitOptions}
            </select>
          </td>
          <td><input type="number" name="products[${rowIndex}][quantity]" value="1" min="1" ${maxQtyAttr} class="qty-input" oninput="calcSubtotal(this); updateDimensions(this);"></td>
          <td><span class="dimension-display">${dimensionText}</span></td>
          <td><span class="weight-display">${weightText}</span></td>
          <td><span class="volume-display">${volumeText}</span></td>
          <td><input type="number" name="products[${rowIndex}][price]" value="${unitPrice}" min="0" oninput="calcSubtotal(this)" ${isFromExport ? 'readonly' : ''}></td>
          <td><input type="text" name="products[${rowIndex}][subtotal]" value="${unitPrice}" readonly></td>
          <td><button type="button" class="btn btn-danger" onclick="removeRow(this,'${product._id}')">Xóa</button></td>
        `;
        productMap[product._id] = rowIndex++;
        console.log("productMap sau khi thêm:", productMap); // ✅ Debug
        console.log("Row HTML created with dimensions:", baseWidth, baseDepth, baseHeight); // ✅ Debug
      }
    }

    function updateByUnit(select) {
      const row = select.closest("tr");
      const option = select.selectedOptions[0];
      const factor = parseFloat(option.dataset.factor || 1);
      const priceInput = row.querySelector("input[name*='[price]']");
      const qtyInput = row.querySelector("input[name*='[quantity]']");
      const basePrice = parseFloat(priceInput.dataset.base || priceInput.value);
      if (!priceInput.dataset.base) priceInput.dataset.base = basePrice;

      priceInput.value = (basePrice * factor).toFixed(2);
      
      // ✅ Cập nhật kích thước, trọng lượng, thể tích theo đơn vị được chọn
      const width = parseFloat(option.dataset.width || 0);
      const depth = parseFloat(option.dataset.depth || 0);
      const height = parseFloat(option.dataset.height || 0);
      const weight = parseFloat(option.dataset.weight || 0);
      const volume = parseFloat(option.dataset.volume || 0);
      
      row.querySelector('.dimension-display').textContent = `${width}×${depth}×${height}`;
      row.querySelector('.weight-display').textContent = weight;
      row.querySelector('.volume-display').textContent = volume;
      
      calcSubtotal(qtyInput);
      updateDimensions(qtyInput);
    }

    // ✅ Cập nhật tổng kích thước/trọng lượng/thể tích khi thay đổi số lượng
    function updateDimensions(input) {
      const row = input.closest("tr");
      const qty = parseFloat(row.querySelector("input[name*='[quantity]']").value) || 0;
      const select = row.querySelector('.unit-select');
      const option = select.selectedOptions[0];
      
      const width = parseFloat(option.dataset.width || 0);
      const depth = parseFloat(option.dataset.depth || 0);
      const height = parseFloat(option.dataset.height || 0);
      const weight = parseFloat(option.dataset.weight || 0);
      const volume = parseFloat(option.dataset.volume || 0);
      
      // Hiển thị kích thước của 1 đơn vị (không nhân số lượng vì kích thước không thay đổi)
      row.querySelector('.dimension-display').textContent = `${width}×${depth}×${height}`;
      
      // Hiển thị tổng trọng lượng và thể tích
      const totalWeight = (weight * qty).toFixed(2);
      const totalVolume = (volume * qty).toFixed(0);
      
      row.querySelector('.weight-display').textContent = totalWeight;
      row.querySelector('.volume-display').textContent = totalVolume;
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
      row.querySelector("input[name*='[subtotal]']").value = (qty * price).toFixed(2);
    }
  </script>
</body>
</html>
