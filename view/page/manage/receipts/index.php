<?php
  if (session_status() === PHP_SESSION_NONE) session_start();
  
  // DEBUG: Kiểm tra file đã được load chưa
  error_log("🔥 receipts/index.php loaded - VERSION 2.0 (export_id bug fixed)");

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
  <title>Tạo phiếu nhập kho</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {font-family: "Segoe UI", Tahoma, sans-serif;background:#f3f6fa;margin:0;}
    .form-container {max-width:1500px;margin:auto;background:#fff;padding:25px 30px;
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
    /* Inline error / confirm styles */
    .error-message {color:#856404;background:#fff3cd;border:1px solid #ffe8a1;padding:8px 10px;border-radius:6px;margin-top:8px;display:none;font-size:13px}
    .error-message.show {display:block}
    .error-field {border-color:#ffc107 !important;box-shadow:0 0 0 3px rgba(255,193,7,0.12)}
    #confirmSection {background:#fff3cd;border:2px solid #ffc107;border-radius:8px;padding:12px;margin:12px 0;display:none}
    #confirmSection.show {display:block}
    #confirmSection p {margin:0 0 8px 0;color:#856404}
    .btn-confirm {background:#28a745;color:#fff;padding:6px 10px;border-radius:6px;border:none;cursor:pointer}
    .btn-cancel {background:#6c757d;color:#fff;padding:6px 10px;border-radius:6px;border:none;cursor:pointer}
    /* Modal overlay for centered popup */
    .modal-overlay {position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:2147483646;pointer-events:auto}
    .modal-overlay.show {display:flex}
    .modal-dialog {background:#fff;padding:18px;border-radius:10px;max-width:1100px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,0.25);z-index:2147483647;pointer-events:auto}
    .modal-dialog p {margin:0 0 12px 0;color:#333}
  </style>
</head>
<body>
  <div class="form-container">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho</h2>
    <form method="post" action="receipts/process.php" id="receiptForm">
      <input type="hidden" name="warehouse_id" value="<?= htmlspecialchars($warehouse_id) ?>">
      <input type="hidden" name="created_by" value="<?= htmlspecialchars($created_by) ?>">

      <label>Loại phiếu nhập</label>
      <select name="type" id="type" required onchange="toggleFields()" style="font-size:15px;font-weight:600;">
        <option value="">-- Chọn loại phiếu --</option>
        <?php 
        // Chỉ cho phép nhập từ nhà cung cấp nếu là kho tổng
        $isMainWarehouse = (strpos($warehouse_id, 'TONG') !== false || $warehouse_id === 'WH01');
        if ($isMainWarehouse): 
        ?>
          <option value="purchase">Nhập từ nhà cung cấp (Bên ngoài)</option>
        <?php endif; ?>
        <option value="transfer">Nhập điều chuyển nội bộ (Từ kho khác)</option>
      </select>
      <div id="type-description" style="margin-top:8px;padding:10px;border-radius:6px;font-size:13px;display:none;"></div>

      <div id="supplier-box" style="display:none;">
        <label>Nhà cung cấp</label>
        <select name="supplier_id" id="supplier_id">
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['supplier_id'] ?>"><?= $s['supplier_name'] ?></option>
          <?php endforeach; ?>
        </select>
        <div id="supplier-error" class="error-message"></div>
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
          <strong> Lưu ý:</strong> Chỉ hiển thị phiếu xuất từ kho nguồn đến kho hiện tại của bạn (<?= $warehouse_id ?>)
        </div>
        <div id="export-error" class="error-message"></div>
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
        <div id="products-error" class="error-message"></div>
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
        <div id="confirmSection">
          <p id="confirmText"><i class="fa-solid fa-circle-exclamation"></i> Bạn có chắc muốn tiếp tục tạo phiếu?</p>
          <button type="button" class="btn-confirm" onclick="confirmCreate()">Xác nhận</button>
          <button type="button" class="btn-cancel" onclick="cancelCreate()">Hủy</button>
        </div>
        <button type="button" id="createBtn" class="btn" onclick="validateAndShowConfirm()"><i class="fa-solid fa-save"></i> Tạo phiếu</button>
        <button type="button" id="cancelBtn" class="btn btn-danger" style="margin-left:8px;" onclick="showCancelConfirm()">Hủy bỏ</button>
      </div>
    </form>
  </div>
  <!-- Centered cancel confirmation modal -->
  <div id="cancelModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
      <h3 id="cancelModalTitle" style="margin-top:0;color:#333;">Xác nhận hủy phiếu</h3>
      <p>Bạn có chắc chắn muốn hủy phiếu nhập này? Hành động này sẽ xóa tất cả sản phẩm đã thêm.</p>
      <div style="text-align:right;margin-top:12px;">
        <button type="button" class="btn-confirm" onclick="confirmCancel()">Xác nhận</button>
        <button type="button" class="btn-cancel" onclick="cancelCancel()" style="margin-left:8px;">Hủy bỏ</button>
      </div>
    </div>
  </div>
  
  <!-- Modal: Full Add Product form (embedded) -->
  <div id="newProductModal" class="modal-overlay" aria-hidden="true">
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="newProductModalTitle">
      <h3 id="newProductModalTitle" style="margin-top:0;color:#333;">Thêm sản phẩm mới</h3>

      <div id="fullAddFormContainer" style="display:block;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <strong>Form thêm sản phẩm đầy đủ</strong>
          <div>
            <button type="button" class="btn btn-cancel" onclick="closeNewProductModal()" style="margin-left:8px;">Đóng</button>
          </div>
        </div>
        <iframe id="fullAddFormIframe" src="about:blank" style="width:100%;height:780px;border:0;border-radius:6px;background:#fff;"></iframe>
          <!-- Product added confirmation modal -->
          <div id="productAddedModal" class="modal-overlay" aria-hidden="true">
            <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="productAddedTitle" style="max-width:420px;">
              <h3 id="productAddedTitle" style="margin-top:0;color:#333;">Sản phẩm đã thêm</h3>
              <p style="color:#444;margin-top:6px;">Sản phẩm mới đã được thêm vào danh sách phiếu.</p>
              <div style="text-align:right;margin-top:12px;">
                <button type="button" class="btn" onclick="hideProductAddedModal()">OK</button>
              </div>
            </div>
          </div>

      </div>
    </div>
  </div>

  <script src="https://unpkg.com/html5-qrcode"></script>
  <script>
    // Export ID sẽ được submit qua dropdown select[name="export_id"]
    
    // --- Inline error helpers ---
    function clearError(elementId) {
      const el = document.getElementById(elementId);
      if (el) { el.classList.remove('show'); el.textContent = ''; }
      const input = document.querySelector("#" + elementId.replace('-error','') + ", [name='" + elementId.replace('-error','') + "']");
      if (input) input.classList.remove('error-field');
    }

    function showError(elementId, message) {
      const el = document.getElementById(elementId);
      if (el) { el.textContent = message; el.classList.add('show'); }
      const input = document.querySelector("#" + elementId.replace('-error','') + ", [name='" + elementId.replace('-error','') + "']");
      if (input) input.classList.add('error-field');
    }

    // Validate and show confirm inline
    function validateAndShowConfirm() {
      // Clear previous errors
      clearError('supplier-error');
      clearError('export-error');
      clearError('products-error');

      const type = document.getElementById("type").value;
      const exportIdEl = document.getElementById("export_id");
      const exportId = exportIdEl ? exportIdEl.value : '';
      let hasError = false;

      if (type === 'transfer') {
        const sourceVal = document.getElementById('source_warehouse_id').value;
        if (!sourceVal) {
          showError('export-error', '⚠️ Vui lòng chọn kho nguồn trước khi chọn phiếu xuất.');
          hasError = true;
        }
        if (!exportId) {
          showError('export-error', '⚠️ Vui lòng chọn phiếu xuất từ kho nguồn.');
          hasError = true;
        }
      }

      if (type === 'purchase') {
        const supplier = document.querySelector("select[name='supplier_id']").value;
        if (!supplier) {
          showError('supplier-error', '⚠️ Vui lòng chọn nhà cung cấp.');
          hasError = true;
        }
      }

      // Products presence and individual checks
      const tbody = document.getElementById('productTable').querySelector('tbody');
      const rows = tbody.querySelectorAll('tr');
      if (rows.length === 0) {
        showError('products-error', '⚠️ Vui lòng thêm ít nhất một sản phẩm.');
        hasError = true;
      } else {
        // validate each product qty/price
        for (let r=0; r<rows.length; r++) {
          const row = rows[r];
          const qty = parseFloat(row.querySelector("input[name*='[quantity]']").value) || 0;
          const price = parseFloat(row.querySelector("input[name*='[price]']").value) || 0;
          const name = row.cells[1].textContent || 'Sản phẩm';
          if (qty <= 0) { showError('products-error', `⚠️ Số lượng của "${name}" phải lớn hơn 0.`); hasError = true; break; }
          if (price <= 0) { showError('products-error', `⚠️ Giá nhập của "${name}" phải lớn hơn 0.`); hasError = true; break; }
        }
      }

      if (hasError) {
        // scroll to the first visible error
        const first = document.querySelector('.error-message.show');
        if (first) first.scrollIntoView({behavior:'smooth', block:'center'});
        return false;
      }

      // If transfer and there are missing scanned items, show confirmSection with details
      if (type === 'transfer' && exportId && Object.keys(exportProducts).length > 0) {
        const scanned = {};
        tbody.querySelectorAll('tr').forEach(row => {
          const pid = row.querySelector("input[name*='[product_id]']").value;
          const qty = parseInt(row.querySelector("input[name*='[quantity]']").value) || 0;
          scanned[pid] = qty;
        });
        const missing = [];
        for (let pid in exportProducts) {
          const expect = exportProducts[pid].quantity || 0;
          const sc = scanned[pid] || 0;
          if (sc < expect) missing.push(`${exportProducts[pid].product_name}: còn thiếu ${expect - sc} ${exportProducts[pid].unit}`);
        }
        if (missing.length > 0) {
          // show missing list in confirm section
          document.getElementById('confirmText').innerHTML = '<strong>⚠️ Chưa quét đủ sản phẩm:</strong><br>' + missing.join('<br>') + '<br><br>Bạn có muốn tiếp tục?';
          document.getElementById('confirmSection').classList.add('show');
          document.getElementById('createBtn').style.display = 'none';
          document.getElementById('confirmSection').scrollIntoView({behavior:'smooth', block:'center'});
          return false;
        }
      }

      // No confirm needed; submit form
      document.getElementById('receiptForm').submit();
      return true;
    }

    function confirmCreate() { document.getElementById('receiptForm').submit(); }
    function cancelCreate() { document.getElementById('confirmSection').classList.remove('show'); document.getElementById('createBtn').style.display = 'inline-block'; }
    // --- Cancel form modal handlers ---
    function showCancelConfirm() {
      // hide other confirm if visible
      document.getElementById('confirmSection').classList.remove('show');
      document.getElementById('createBtn').style.display = 'none';
      const modal = document.getElementById('cancelModal');
      if (modal) modal.classList.add('show');
    }

    function cancelCancel() {
      const modal = document.getElementById('cancelModal');
      if (modal) modal.classList.remove('show');
      document.getElementById('createBtn').style.display = 'inline-block';
    }

    function confirmCancel() {
      // Reset the form and clear product table
      try { document.getElementById('receiptForm').reset(); } catch(e){}
      const tbody = document.getElementById('productTable').querySelector('tbody');
      if (tbody) tbody.innerHTML = '';
      productMap = {};
      rowIndex = 0;
      exportProducts = {};
      // hide modal and other confirms
      const modal = document.getElementById('cancelModal'); if (modal) modal.classList.remove('show');
      document.getElementById('confirmSection').classList.remove('show');
      document.getElementById('createBtn').style.display = 'inline-block';
      // clear inline errors
      clearError('supplier-error');
      clearError('export-error');
      clearError('products-error');
    }
    
    // Manual add modal: open popup to add a temporary new product (not saved to DB)
    function openManualModal(){
      // Ensure supplier selected for purchase
      const typeEl = document.getElementById('type') || document.getElementById('receipt_type');
      const currentType = typeEl ? typeEl.value : '';
      const supplierEl = document.getElementById('supplier_id');
      const selectedSupplierId = supplierEl ? supplierEl.value : '';
      if (currentType === 'purchase' && (!selectedSupplierId || selectedSupplierId === '')) {
        showError('supplier-error', '⚠️ Vui lòng chọn nhà cung cấp trước khi thêm sản phẩm mới');
        if (supplierEl) supplierEl.focus();
        return;
      }

      // Build prefill params safely (inputs removed — use empty defaults)
      const selSupplierName = supplierEl ? (supplierEl.options[supplierEl.selectedIndex]?.text || '') : '';
      const modal = document.getElementById('newProductModal');
      if (!modal) return;
      modal.classList.add('show');

      // Directly open full add-product form in iframe (user asked for full form immediately)
      const iframe = document.getElementById('fullAddFormIframe');
      const full = document.getElementById('fullAddFormContainer');
      if (iframe) {
        // inputs no longer exist in the receipts modal, so pass minimal prefill (supplier only)
          const params = `?prefill=1&product_name=${encodeURIComponent('')}&sku=${encodeURIComponent('')}&barcode=${encodeURIComponent('')}&base_unit=${encodeURIComponent('')}&purchase_price=${encodeURIComponent('')}&description=${encodeURIComponent(selSupplierName ? `Supplier: ${selSupplierName}` : '')}&supplier_id=${encodeURIComponent(supplierEl ? supplierEl.value : '')}`;
          iframe.src = '/view/page/manage/products/createProduct/index.php' + params;
        if (full) full.style.display = 'block';
      }
    }

    function closeNewProductModal(){
      const modal = document.getElementById('newProductModal');
      if (modal) modal.classList.remove('show');
    }

    function saveNewProductFromModal(){
      // The simple temporary form was removed; open the full add-product iframe instead
      openManualModal();
      return false;
    }

    // Open full Add Product form in a new tab with prefilled params from modal
    function openFullAddProductFormFromModal(){
      // Open the full add-product form in the iframe with minimal prefill (supplier only)
      const supplier_id = encodeURIComponent(document.getElementById('supplier_id') ? document.getElementById('supplier_id').value : '');
      const note = '';
      const params = `?prefill=1&product_name=&sku=&barcode=&base_unit=&purchase_price=&description=${encodeURIComponent(note)}&supplier_id=${supplier_id}`;
      const url = '/view/page/manage/products/createProduct/index.php' + params;
      const iframe = document.getElementById('fullAddFormIframe');
      const full = document.getElementById('fullAddFormContainer');
      if (!iframe) { window.open(url, '_blank'); return; }
      if (full) full.style.display = 'block';
      iframe.src = url;
    }

    function backToSimpleModal(){
      const full = document.getElementById('fullAddFormContainer');
      const iframe = document.getElementById('fullAddFormIframe');
      if (iframe) iframe.src = 'about:blank';
      if (full) full.style.display = 'none';
    }

    function showProductAddedModal(){
      const m = document.getElementById('productAddedModal');
      if (m) m.classList.add('show');
    }
    function hideProductAddedModal(){
      const m = document.getElementById('productAddedModal');
      if (m) m.classList.remove('show');
    }

    // Listen for messages from iframe (createProduct) when a product is created
    window.addEventListener('message', async function(e) {
      try {
        const data = e.data || {};
            // Security: only accept messages from same origin
            const allowedOrigin = window.location.origin;
            if (e.origin !== allowedOrigin) {
              console.warn('Ignored postMessage from unknown origin', e.origin);
              return;
            }

        if (data && data.type === 'wms:new_product_created' && data.product) {
          const prod = data.product;
          // If barcode present, verify it doesn't already exist as a different product
          if (prod && prod.barcode) {
            try {
              const resp = await fetch(`/view/page/manage/receipts/get_barcode_or_batch.php?barcode=${encodeURIComponent(prod.barcode)}`);
              const j = await resp.json();
              if (j && j.success && j.product && j.product._id) {
                // existing product in DB
                const existingId = j.product._id;
                if (existingId && existingId !== prod._id && !(existingId === prod._id)) {
                  showError('products-error', `⚠️ Mã vạch "${prod.barcode}" đã tồn tại trong hệ thống. Vui lòng dùng mã khác.`);
                  return;
                }
              }
            } catch (err) {
              console.warn('Barcode uniqueness check failed in parent', err);
            }
          }
            // Also ensure barcode is not already present in current receipt rows
            if (prod && prod.barcode) {
              try {
                const existingBarcodeInputs = document.querySelectorAll(`input[name^="products"][name$="[barcode]"]`);
                for (let i = 0; i < existingBarcodeInputs.length; i++) {
                  const val = (existingBarcodeInputs[i].value || '').toString().trim();
                  if (val && val === prod.barcode) {
                    showError('products-error', `⚠️ Mã vạch "${prod.barcode}" đã được thêm vào danh sách.`);
                    return;
                  }
                }
              } catch (err) { console.warn('Local barcode check failed', err); }
            }
          // Preserve full product payload from iframe, but normalize common keys
          const productObj = Object.assign({}, prod || {});
          productObj._id = productObj._id || productObj.id || productObj['_id'] || ('new_' + Date.now());
          productObj.sku = productObj.sku || productObj.SKU || productObj.sku || '';
          productObj.barcode = productObj.barcode || productObj.bar_code || productObj.barcode || '';
          productObj.product_name = productObj.product_name || productObj.name || productObj.productName || '';
          productObj.baseUnit = productObj.baseUnit || productObj.base_unit || productObj.unit || 'Cái';
          productObj.purchase_price = productObj.purchase_price || productObj.purchasePrice || productObj.import_price || 0;
          productObj.conversionUnits = productObj.conversionUnits || productObj.conversion_units || productObj.conversions || [];
          productObj.package_dimensions = productObj.package_dimensions || productObj.packageDimensions || productObj.dimensions || productObj.package_dims || {};
          productObj.package_weight = productObj.package_weight || productObj.packageWeight || productObj.weight || 0;
          productObj.volume_per_unit = productObj.volume_per_unit || productObj.volumePerUnit || productObj.volume || 0;
          productObj.min_stock = typeof productObj.min_stock !== 'undefined' ? productObj.min_stock : (productObj.minStock || 0);
          productObj.model = productObj.model || '';
          productObj.description = productObj.description || productObj.note || '';
          productObj.image = productObj.image || productObj.images || '';
          productObj.is_new = productObj.is_new || 0; // Giữ nguyên flag is_new từ iframe
          // category might be provided as object or id/name pair
          if (!productObj.category && (productObj.category_id || productObj.categoryName || productObj.category_name)) {
            productObj.category = { id: productObj.category_id || '', name: productObj.category_name || productObj.categoryName || '' };
          }
          // stacking info
          productObj.stackable = typeof productObj.stackable !== 'undefined' ? productObj.stackable : (productObj.is_stackable || false);
          productObj.max_stack_height = productObj.max_stack_height || productObj.maxStackHeight || 0;

          // Supplier: lấy từ dropdown nếu chưa có trong productObj
          if (!productObj.supplier && !productObj.supplier_id && !productObj.supplier_name) {
            const supplierEl = document.getElementById('supplier_id');
            if (supplierEl && supplierEl.value) {
              const selectedOption = supplierEl.options[supplierEl.selectedIndex];
              productObj.supplier_id = supplierEl.value;
              productObj.supplier_name = selectedOption ? selectedOption.text : '';
              productObj.supplier = {
                id: supplierEl.value,
                name: selectedOption ? selectedOption.text : ''
              };
            }
          }

          // Add directly into the receipt table with all provided details preserved
          try { addOrUpdateRow(productObj); } catch(err) { console.error('addOrUpdateRow failed', err); }

          // Close modal after adding and scroll to the product table
           try { 
             backToSimpleModal(); 
             closeNewProductModal(); 
             showProductAddedModal(); 
             
             // Scroll to the product table to show the newly added product
             setTimeout(() => {
               const productTable = document.getElementById('productTable');
               if (productTable) {
                 productTable.scrollIntoView({ behavior: 'smooth', block: 'center' });
               }
             }, 300);
           } catch(e) {}
        }
      } catch(err) { console.error('message handler error', err); }
    });

    let exportProducts = {}; // Lưu danh sách sản phẩm từ phiếu xuất

    function toggleFields() {
      const type = document.getElementById("type").value;
      const exportSelect = document.getElementById("export_id");
      const batchColumns = document.querySelectorAll(".batch-column");
      const descBox = document.getElementById("type-description");
      
      // Remove transfer descriptive note: always hide the description box
      if (type === "purchase") {
        descBox.style.display = "none";
      } else if (type === "transfer") {
        // Description box intentionally hidden per request
        descBox.style.display = "none";
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

      // Khi chuyển sang chế độ purchase, gắn sự kiện đổi NCC → xoá danh sách sản phẩm để đồng bộ
      const supplierSel = document.getElementById('supplier_id');
      if (type === 'purchase' && supplierSel) {
        supplierSel.onchange = function(){
          try {
            const tbody = document.getElementById('productTable').querySelector('tbody');
            if (tbody) tbody.innerHTML = '';
            productMap = {};
            rowIndex = 0;
            clearError('products-error');
          } catch(e){}
        };
      }
    }

    function loadExports() {
      const sourceWarehouseId = document.getElementById("source_warehouse_id").value;
      const currentWarehouseId = "<?= $warehouse_id ?>";
      const exportSelect = document.getElementById("export_id");
      
      console.log("🔍 loadExports - Source:", sourceWarehouseId, "Destination:", currentWarehouseId);
      
      if (!sourceWarehouseId) {
        document.getElementById("export-box").style.display = "none";
        exportSelect.removeAttribute("required");
        return;
      }

      // Hiển thị export-box, set required và load danh sách phiếu xuất
      document.getElementById("export-box").style.display = "block";
      exportSelect.setAttribute("required", "required");
      
      const url = `receipts/process.php?action=get_exports&source_warehouse=${sourceWarehouseId}&destination_warehouse=${currentWarehouseId}`;
      console.log("📡 Fetching exports from:", url);
      
      fetch(url)
        .then(res => res.json())
        .then(data => {
          console.log("📦 Exports response:", data);
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
          showError('export-error','⚠️ Lỗi khi tải danh sách phiếu xuất');
        });
    }

    function loadExportProducts() {
      const exportId = document.getElementById("export_id").value;
      
      if (!exportId) {
        document.getElementById("export-info").style.display = "none";
        exportProducts = {};
        return;
      }

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
            showError('export-error', data.message || "⚠️ Không tải được thông tin phiếu xuất");
          }
        })
        .catch(err => {
          console.error('Lỗi load chi tiết phiếu xuất:', err);
          showError('export-error','⚠️ Lỗi khi tải chi tiết phiếu xuất');
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
      showError('products-error','ℹ️ Đặt con trỏ vào ô barcode và quét bằng máy scanner USB.');
      setTimeout(()=>clearError('products-error'),3000);
    }

    function startScanner() {
      document.getElementById("reader").style.display = "block";
      html5QrCode = new Html5Qrcode("reader");
      html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, (decodedText) => {
        fetchProduct(decodedText);
        stopScanner();
      }).catch(err => { showError('products-error','⚠️ Không mở được camera: ' + err); });
    }

    function stopScanner() {
      if (html5QrCode) html5QrCode.stop().then(() => document.getElementById("reader").style.display = "none");
    }

    function fetchProduct(code) {
      const type = document.getElementById("type").value;
      const exportId = document.getElementById("export_id") ? document.getElementById("export_id").value : '';
      
      // Nếu là nhập điều chuyển và đã chọn phiếu xuất, kiểm tra sản phẩm có trong phiếu không
      if (type === "transfer" && exportId && Object.keys(exportProducts).length > 0) {
        fetch("receipts/get_barcode_or_batch.php?barcode=" + encodeURIComponent(code))
          .then(res => res.text())
          .then(txt => {
            try {
              const data = JSON.parse(txt);
              if (data.success) {
                const batchData = data.batch || null;
                let productPayload = data.product || null;
                if (!productPayload && batchData && batchData.product_id) {
                  productPayload = { _id: batchData.product_id, product_name: batchData.product_name || '', purchase_price: batchData.unit_price || 0, package_weight: batchData.unit_weight || 0, package_dimensions: batchData.package_dimensions || {} };
                }
                // If we have batch info, prefer batch's unit_price / unit_weight for UI display
                if (batchData && productPayload) {
                  if (batchData.unit_price) productPayload.purchase_price = batchData.unit_price;
                  if (batchData.unit_weight && (!productPayload.package_weight || productPayload.package_weight == 0)) productPayload.package_weight = batchData.unit_weight;
                  if (batchData.package_dimensions && (!productPayload.package_dimensions || Object.keys(productPayload.package_dimensions).length === 0)) productPayload.package_dimensions = batchData.package_dimensions;
                }
                const productId = productPayload ? productPayload._id : null;

                // Kiểm tra sản phẩm có trong phiếu xuất không
                if (!exportProducts[productId]) {
                  showError('products-error', `⚠️ Sản phẩm "${productPayload ? productPayload.product_name : (batchData ? batchData.product_name : 'Không rõ')}" KHÔNG có trong phiếu xuất đã chọn!`);
                  document.getElementById("barcode").value = "";
                  return;
                }

                // Kiểm tra số lượng đã quét
                const exportQty = exportProducts[productId].quantity;
                const scannedQty = exportProducts[productId].scanned_qty || 0;

                if (scannedQty >= exportQty) {
                  showError('products-error', `⚠️ Đã quét đủ số lượng cho sản phẩm "${productPayload.product_name}" (${exportQty} ${exportProducts[productId].unit})`);
                  document.getElementById("barcode").value = "";
                  return;
                }

                // merge export pricing/unit into product payload
                productPayload.purchase_price = exportProducts[productId].unit_price;
                productPayload.export_unit = exportProducts[productId].unit;
                productPayload.export_qty = exportQty;

                console.log("✅ Sản phẩm hợp lệ từ phiếu xuất:", productPayload, batchData);
                addOrUpdateRow(productPayload, batchData);

                // Cập nhật số lượng đã quét
                exportProducts[productId].scanned_qty = (exportProducts[productId].scanned_qty || 0) + 1;

                document.getElementById("barcode").value = "";
              } else {
                showError('products-error', data.message || "⚠️ Không tìm thấy sản phẩm!");
              }
            } catch (err) {
              console.error('Non-JSON response from barcode API:', txt);
              showError('products-error', '⚠️ Lỗi server: phản hồi không hợp lệ. Kiểm tra console.');
            }
          })
          .catch(err => showError('products-error','⚠️ Lỗi khi tìm sản phẩm: ' + err));
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
              showError('products-error', data.message || "⚠️ Không tìm thấy sản phẩm!");
            }
          })
          .catch(err => showError('products-error','⚠️ Lỗi khi tìm sản phẩm: ' + err));
      }
    }

    function addOrUpdateRow(product, batch) {
      console.log("addOrUpdateRow - Product ID:", product._id); // ✅ Debug
      console.log("productMap hiện tại:", productMap); // ✅ Debug
      
      // Kiểm tra xem có phải nhập từ phiếu xuất không
      const type = document.getElementById("type").value;
      const exportId = document.getElementById("export_id") ? document.getElementById("export_id").value : '';
      const isFromExport = (type === "transfer" && exportId && product.export_unit);
      
      if (Object.prototype.hasOwnProperty.call(productMap, product._id)) {
        console.log("Sản phẩm đã tồn tại - tăng số lượng"); // ✅ Debug
        let row = document.querySelector(`#row-${productMap[product._id]}`);
        let qtyInput = row.querySelector("input[name*='[quantity]']");
        
        // Kiểm tra số lượng tối đa nếu từ phiếu xuất
        if (isFromExport && exportProducts[product._id]) {
          const currentQty = parseInt(qtyInput.value) || 0;
          const maxQty = exportProducts[product._id].export_qty || exportProducts[product._id].quantity;
          
          if (currentQty >= maxQty) {
            showError('products-error', `⚠️ Đã đủ số lượng cho sản phẩm này (${maxQty} ${exportProducts[product._id].unit})`);
            return;
          }
        }
        
        qtyInput.value = parseInt(qtyInput.value) + 1;
        calcSubtotal(qtyInput);
        updateDimensions(qtyInput);
        // Nếu quét theo lô hàng và có batch payload, chỉ cập nhật số lượng —
        // không thêm lô mới hoặc tạo badge cho các lần quét kế tiếp trên cùng 1 dòng.
        if (batch) {
          console.log('Batch scan received for existing product; ignoring batch details and only incrementing quantity.');
        }
      } else {
        console.log("Thêm sản phẩm mới vào bảng"); // ✅ Debug
        console.log("Product data:", product); // ✅ Debug xem dữ liệu
        
        const tbody = document.getElementById("productTable").querySelector("tbody");
        const row = tbody.insertRow();
        row.id = "row-" + rowIndex;

        // ✅ Lưu thông tin kích thước, trọng lượng, thể tích cho từng đơn vị
        const baseUnit = product.baseUnit || 'Cái';
        const baseDim = product.package_dimensions || {};
        let baseWeight = parseFloat(product.package_weight) || 0;
        // If base weight is missing (0), try to derive from conversionUnits
        if ((!baseWeight || baseWeight === 0) && product.conversionUnits && Array.isArray(product.conversionUnits)) {
          for (let i = 0; i < product.conversionUnits.length; i++) {
            const cu = product.conversionUnits[i];
            const cuWeight = parseFloat(cu.weight || 0) || 0;
            const cuFactor = parseFloat(cu.factor || 0) || 0;
            if (cuWeight > 0 && cuFactor > 0) {
              // Weight per base unit = convUnit.weight / factor
              baseWeight = cuWeight / cuFactor;
              break;
            }
          }
        }
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
        
        // Hiển thị thông tin batches nếu nhập từ phiếu xuất hoặc có batch payload
        let batchDisplay = '-';
        let batchesJson = '[]';
        if (batch) {
          const b = {
            batch_code: batch.batch_code || batch.barcode || '',
            quantity: batch.quantity_remaining ? 1 : (batch.quantity || 1),
            source_location: batch.source_location || batch.location || null,
            location_text: batch.location_text || ''
          };
          batchDisplay = `<span style="display:inline-block;background:#e3f2fd;padding:2px 6px;margin:2px;border-radius:4px;font-size:12px;">📦 ${b.batch_code}</span>`;
          batchesJson = JSON.stringify([b]).replace(/'/g, '&apos;');
        } else if (isFromExport && exportProducts[product._id] && exportProducts[product._id].batches) {
          const batches = exportProducts[product._id].batches;
          batchDisplay = batches.map(b => `<span style="display:inline-block;background:#e3f2fd;padding:2px 6px;margin:2px;border-radius:4px;font-size:12px;">📦 ${b.batch_code}</span>`).join('');
          const mapped = batches.map(b => ({ batch_code: b.batch_code, quantity: b.quantity, source_location: b.source_location || b.location || null, location_text: b.location_text || b.location_display || '' }));
          batchesJson = JSON.stringify(mapped).replace(/'/g, '&apos;');
          console.log(`📦 Batches for ${product.product_name}:`, batches);
        }

        // If this is a temporarily created new product, add hidden inputs to mark it as new
        let newHiddenInputs = '';
        if (product.is_new) {
          const pd = product.package_dimensions || {};
          const pdJson = JSON.stringify(pd).replace(/'/g, "&apos;");
          const convJson = JSON.stringify(product.conversionUnits || []).replace(/'/g, "&apos;");
          const catId = (product.category && product.category.id) ? product.category.id : (product.category_id || '');
          const catName = (product.category && product.category.name) ? product.category.name : (product.category_name || '');
          newHiddenInputs = `
            <input type="hidden" name="products[${rowIndex}][is_new]" value="1">
            <input type="hidden" name="products[${rowIndex}][sku]" value="${product.sku}">
            <input type="hidden" name="products[${rowIndex}][barcode]" value="${product.barcode || ''}">
            <input type="hidden" name="products[${rowIndex}][note]" value='${(product.note||'').replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][supplier_id]" value='${product.supplier_id || (product.supplier && product.supplier.id) || ''}'>
            <input type="hidden" name="products[${rowIndex}][supplier_name]" value='${((product.supplier && product.supplier.name) || product.supplier_name||'').replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][package_dimensions]" value='${pdJson}'>
            <input type="hidden" name="products[${rowIndex}][package_weight]" value='${product.package_weight || 0}'>
            <input type="hidden" name="products[${rowIndex}][volume_per_unit]" value='${product.volume_per_unit || 0}'>
            <input type="hidden" name="products[${rowIndex}][conversionUnits]" value='${convJson}'>
            <input type="hidden" name="products[${rowIndex}][category_id]" value='${catId}'>
            <input type="hidden" name="products[${rowIndex}][category_name]" value='${(catName||'').replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][category]" value='${JSON.stringify(product.category || (catId ? {id:catId, name:catName} : {})).replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][dimensions]" value='${JSON.stringify(product.dimensions || {}).replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][width]" value='${(product.dimensions && product.dimensions.width) || (product.width || 0)}'>
            <input type="hidden" name="products[${rowIndex}][depth]" value='${(product.dimensions && product.dimensions.depth) || (product.depth || 0)}'>
            <input type="hidden" name="products[${rowIndex}][height]" value='${(product.dimensions && product.dimensions.height) || (product.height || 0)}'>
            <input type="hidden" name="products[${rowIndex}][weight]" value='${(product.dimensions && product.dimensions.weight) || (product.weight || 0)}'>
            <input type="hidden" name="products[${rowIndex}][volume]" value='${(product.dimensions && product.dimensions.volume) || (product.volume || 0)}'>
            <input type="hidden" name="products[${rowIndex}][stackable]" value='${product.stackable ? 1 : 0}'>
            <input type="hidden" name="products[${rowIndex}][max_stack_height]" value='${product.max_stack_height || 0}'>
            <input type="hidden" name="products[${rowIndex}][model]" value='${(product.model||'').replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][min_stock]" value='${product.min_stock || 0}'>
            <input type="hidden" name="products[${rowIndex}][status]" value='${product.status || 1}'>
            <input type="hidden" name="products[${rowIndex}][description]" value='${(product.description||'').replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][baseUnit]" value='${(product.baseUnit||'').replace(/'/g, "&apos;")}'>
            <input type="hidden" name="products[${rowIndex}][purchase_price]" value='${product.purchase_price || 0}'>
          `;
        }

        row.innerHTML = `
          <td>
            <input type="hidden" name="products[${rowIndex}][product_id]" value="${product._id}">
            <input type="hidden" name="products[${rowIndex}][product_name]" value="${product.product_name}">
            ${newHiddenInputs}
            <input type="hidden" name="products[${rowIndex}][batches]" value='${batchesJson}'>
            ${product.sku || product._id}
          </td>
          <td>${product.product_name} ${product.is_new ? '<span class="new-badge" style="color:#fff;background:#28a745;padding:2px 6px;border-radius:4px;font-size:11px;margin-left:6px;">NEW</span>' : ''}</td>
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
          <td>
            <input type="hidden" name="products[${rowIndex}][subtotal]" class="subtotal-hidden" value="${unitPrice}">
            <input type="text" class="subtotal-display" value="${unitPrice}" readonly>
          </td>
          <td><button type="button" class="btn btn-danger" onclick="removeRow(this,'${product._id}')">Xóa</button></td>
        `;
        
        // Áp dụng style display cho cột batch dựa vào loại phiếu
        const currentType = document.getElementById("type").value;
        const batchCell = row.querySelector('.batch-column');
        if (batchCell) {
          batchCell.style.display = currentType === "transfer" ? "table-cell" : "none";
        }
        
        productMap[product._id] = rowIndex++;
        // Set initial formatted subtotal for the new row
        const initialQtyInput = row.querySelector('.qty-input') || row.querySelector("input[name*='[quantity]']");
        if (initialQtyInput) calcSubtotal(initialQtyInput);
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

    // Format number as VND with dot thousands separator and trailing ' đ'
    function formatCurrencyVND(amount) {
      // Ensure number
      let n = Number(amount) || 0;
      // Round to integer (VND typically has no decimals)
      n = Math.round(n);
      // Convert to string with dot as thousand separator
      return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + ' đ';
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
      const subtotal = qty * price;

      // hidden numeric value (used for server submission)
      const hidden = row.querySelector('.subtotal-hidden');
      if (hidden) hidden.value = subtotal.toFixed(2);

      // visible formatted display
      const disp = row.querySelector('.subtotal-display');
      if (disp) disp.value = formatCurrencyVND(subtotal);
    }
  </script>
</body>
</html>
