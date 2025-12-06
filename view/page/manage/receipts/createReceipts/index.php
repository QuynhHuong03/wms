<?php
    session_start();
    error_reporting();
    include_once(__DIR__ . '/../../../../controller/cSupplier.php');
    include_once(__DIR__ . '/../../../../model/connect.php');
    
    $p = new CSupplier();
    $suppliers = $p->getAllSuppliers() ?? []; // đảm bảo có mảng supplier
    
    // Lấy danh sách phiếu xuất (cho trường hợp nhập từ kho nội bộ)
    $warehouse_id = $_SESSION['login']['warehouse_id'] ?? '';
    $exports = [];
    
    $db = new clsKetNoi();
    $con = $db->moKetNoi();
    if ($con) {
        $transactionsCol = $con->selectCollection('transactions');
        // Lấy các phiếu xuất ĐẾN kho này (destination_warehouse_id) và chưa tạo phiếu nhập
        $cursor = $transactionsCol->find([
            'transaction_type' => 'export',
            'destination_warehouse_id' => $warehouse_id,
            'status' => 1 // Đã xuất kho
        ], ['sort' => ['created_at' => -1], 'limit' => 50]);
        $exports = iterator_to_array($cursor);
        $db->dongKetNoi($con);
    }
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Tạo phiếu nhập kho</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { 
        background: #f9f9f9; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }
    .form-container { 
        max-width: 98%; 
        width: 100%;
        margin: 10px auto; 
        background: #fff; 
        padding: 20px; 
        border-radius: 10px; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        overflow-x: auto;
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
        min-width: 1200px;
        border-collapse: collapse; 
        margin-top: 15px; 
    }
    table th, table td { 
        border: 1px solid #ccc; 
        padding: 8px; 
        text-align: center;
        white-space: nowrap;
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
        transition: 0.2s;
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
    .price-display, 
    input[name*='[subtotal]'] {
        text-align: right;
        font-weight: 500;
    }
    .error-message {
        color: #dc3545;
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 13px;
        margin-top: 5px;
        display: none;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .error-message.show {
        display: block;
    }
    .form-group {
        margin-bottom: 15px;
    }
    select.error, input.error {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    .success-message {
        color: #155724;
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 13px;
        margin-top: 5px;
        display: none;
        animation: slideDown 0.3s ease;
    }
    .success-message.show {
        display: block;
    }
    #confirmSection {
        background-color: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        display: none;
    }
    #confirmSection.show {
        display: block;
        animation: slideDown 0.3s ease;
    }
    #confirmSection p {
        margin: 0 0 10px 0;
        color: #856404;
        font-weight: 500;
    }
    .btn-confirm {
        background: #28a745;
        margin-right: 10px;
    }
    .btn-confirm:hover {
        background: #218838;
    }
    .btn-cancel {
        background: #6c757d;
    }
    .btn-cancel:hover {
        background: #5a6268;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu nhập kho</h2>
    <form id="receiptForm" method="post" action="../process.php">
      <?php
        // Thêm các trường ẩn bắt buộc
        $user_id = $_SESSION['login']['user_id'] ?? 'system';
        $warehouse_id = $_SESSION['login']['warehouse_id'] ?? '';
      ?>
      <input type="hidden" name="type" value="purchase">
      <input type="hidden" name="created_by" value="<?= $user_id ?>">
      <input type="hidden" name="warehouse_id" value="<?= $warehouse_id ?>">
      
      <div class="form-group">
        <label>Loại phiếu nhập</label>
        <select name="type" id="receipt_type" onchange="toggleReceiptType()">
          <option value="transfer">Nhập điều chuyển nội bộ (Từ kho khác)</option>
          <option value="purchase">Nhập từ nhà cung cấp</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Kho nguồn</label>
        <select name="source_warehouse_id" id="source_warehouse_id">
          <option value="">-- Chọn kho nguồn --</option>
          <option value="KHO_TONG_01">Kho Tổng Hà Nội</option>
        </select>
      </div>
      
      <div class="form-group" id="export-group">
        <label>Phiếu xuất từ kho nguồn</label>
        <select name="export_id" id="export_id">
          <option value="">-- Không có phiếu xuất nào --</option>
          <?php foreach ($exports as $exp) {
            $expId = $exp['transaction_id'] ?? '';
            $createdAt = 'N/A';
            if (isset($exp['created_at']) && $exp['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
              $createdAt = $exp['created_at']->toDateTime()->format('d/m/Y H:i');
            }
            $productCount = count($exp['details'] ?? []);
            $sourceWh = $exp['warehouse_id'] ?? '';
          ?>
            <option value="<?= $expId ?>"><?= $expId ?> - <?= $createdAt ?> (<?= $productCount ?> SP)</option>
          <?php } ?>
        </select>
      </div>

      <div class="form-group" id="supplier-group" style="display:none;">
        <label>Nhà cung cấp <span style="color:red;">*</span></label>
        <select name="supplier_id" id="supplier_id">
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $s) { ?>
            <option value="<?= $s['_id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php } ?>
        </select>
        <div id="supplier-error" class="error-message"></div>
      </div>

      <!-- Export summary (filled when an export is selected) -->
      <div id="exportSummary" class="alert alert-info" style="display:none;margin-bottom:10px;padding:10px;border-radius:6px;color:#0c5460;background:#e9f7ff;border-color:#b6e0ff;">
      </div>
      <pre id="exportDebug" style="display:none;background:#f8f9fa;border:1px solid #ececec;padding:10px;border-radius:6px;max-height:200px;overflow:auto;font-size:12px;color:#333;margin-bottom:10px;"></pre>
      <pre id="lastFetchDebug" style="display:none;background:#fff7f7;border:1px solid #f5c6cb;padding:10px;border-radius:6px;max-height:200px;overflow:auto;font-size:12px;color:#333;margin-bottom:10px;"></pre>

      <label>Thêm sản phẩm</label>
      <div class="barcode-box">
        <input type="text" id="barcode" placeholder="Nhập mã vạch hoặc mã lô (batch code)..." autofocus title="Quét barcode sản phẩm hoặc nhập mã lô để thêm hàng từ kho nội bộ">
        <button type="button" class="btn" onclick="startScanner()">
          <i class="fa-solid fa-camera"></i> Camera
        </button>
        <button type="button" class="btn" onclick="useScanner()">
          <i class="fa-solid fa-barcode"></i> Scanner
        </button>
        <button type="button" class="btn" onclick="addManualProduct()" style="background:#28a745;" title="Thêm sản phẩm thủ công">
          <i class="fa-solid fa-plus"></i> Thêm thủ công
        </button>
      </div>
      <div id="reader"></div>

      <h3>Danh sách sản phẩm</h3>
      <div id="products-error" class="error-message"></div>
      <table id="productTable">
        <thead>
          <tr>
            <th>Mã SKU</th> 
            <th>Tên SP</th>
            <th>Mã lô</th>
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
      
      <div id="confirmSection">
        <p><i class="fa-solid fa-circle-exclamation"></i> Bạn có chắc chắn muốn tạo phiếu nhập kho này không?</p>
        <button type="button" class="btn btn-confirm" onclick="confirmCreate()"><i class="fa-solid fa-check"></i> Xác nhận</button>
        <button type="button" class="btn btn-cancel" onclick="cancelCreate()"><i class="fa-solid fa-times"></i> Hủy</button>
      </div>
      
      <button type="button" id="createBtn" class="btn" onclick="validateAndShowConfirm()"><i class="fa-solid fa-save"></i> Tạo phiếu</button>
    </form>
  </div>

  <script src="https://unpkg.com/html5-qrcode"></script>
  <script>
    let rowIndex = 0;
    let productMap = {};
    // If user selects an export (internal transfer) we will store it here
    let currentExport = null;

    // Toggle receipt type (purchase vs transfer)
    function toggleReceiptType() {
      const type = document.getElementById('receipt_type').value;
      const exportGroup = document.getElementById('export-group');
      const supplierGroup = document.getElementById('supplier-group');
      
      if (type === 'transfer') {
        // Internal transfer - show export selection
        exportGroup.style.display = 'block';
        supplierGroup.style.display = 'none';
        document.getElementById('supplier_id').removeAttribute('required');
      } else {
        // Purchase from supplier
        exportGroup.style.display = 'none';
        supplierGroup.style.display = 'block';
        document.getElementById('supplier_id').setAttribute('required', 'required');
      }
    }
    
    // Initialize on page load
    toggleReceiptType();

    // If there's an export dropdown on the page with id 'export_id', attach change handler
    const exportSelect = document.getElementById('export_id');
    if (exportSelect) {
      exportSelect.addEventListener('change', function() {
        const val = this.value;
        if (!val) {
          currentExport = null;
          clearProductError();
          const exportSummary = document.getElementById('exportSummary');
          if (exportSummary) exportSummary.style.display = 'none';
          return;
        }
        loadExportDetails(val);
      });
    }

    function loadExportDetails(exportId) {
      fetch('../exports/get_export_json.php?id=' + encodeURIComponent(exportId))
        .then(res => res.json())
        .then(data => {
          if (data.success && data.export) {
            currentExport = data.export;
            // show debug JSON
            const ed = document.getElementById('exportDebug');
            if (ed) { ed.style.display = 'block'; ed.textContent = JSON.stringify(currentExport, null, 2); }
            // show a brief summary to user in exportSummary element
            const exportSummary = document.getElementById('exportSummary');
            if (exportSummary) {
              exportSummary.style.display = 'block';
              const batches = currentExport.batches || [];
              const products = currentExport.products || [];
              let batchList = batches.map(b => `<li><strong>${b.batch_code}</strong> - ${b.product_id ? (products.find(p=>p.product_id===b.product_id)||{}).product_name || 'N/A' : 'N/A'} (SL: ${b.quantity})</li>`).join('');
              exportSummary.innerHTML = `
                <strong>📦 Thông tin phiếu xuất:</strong> ${currentExport.transaction_id} — 
                <strong>Kho nguồn:</strong> ${currentExport.warehouse_id}<br>
                <small style='color:#856404;'><i class='fa-solid fa-circle-info'></i> Chỉ cho phép quét các mã lô/sản phẩm có trong phiếu xuất này:</small>
                ${batchList ? `<ul style='margin:5px 0 0 20px;font-size:13px;'>${batchList}</ul>` : ''}
              `;
            }
            } else {
            currentExport = null;
            showError('products-error', '⚠️ Không tải được thông tin phiếu xuất: ' + (data.message || 'Unknown'));
          }
        })
        .catch(err => {
          currentExport = null;
          showError('products-error', '⚠️ Lỗi tải phiếu xuất: ' + err.message);
        });
    }

    // --- Xóa thông báo lỗi ---
    function clearError(elementId) {
      const errorElement = document.getElementById(elementId);
      if (errorElement) {
        errorElement.classList.remove('show');
        errorElement.textContent = '';
      }
      // Xóa border đỏ nếu có
      const inputElement = document.getElementById(elementId.replace('-error', ''));
      if (inputElement) {
        inputElement.classList.remove('error');
      }
    }

    // --- Hiển thị thông báo lỗi ---
    function showError(elementId, message) {
      const errorElement = document.getElementById(elementId);
      if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('success-message');
        errorElement.classList.add('error-message', 'show');
      }
      // Thêm border đỏ cho input
      const inputElement = document.getElementById(elementId.replace('-error', ''));
      if (inputElement) {
        inputElement.classList.add('error');
        inputElement.focus();
      }
    }
    
    // --- Hiển thị thông báo thành công ---
    function showSuccess(elementId, message) {
      const errorElement = document.getElementById(elementId);
      if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('error-message');
        errorElement.classList.add('success-message', 'show');
        // Tự động ẩn sau 3 giây
        setTimeout(() => {
          errorElement.classList.remove('show');
        }, 3000);
      }
    }

    // --- Validation form trước khi submit ---
    function validateForm() {
      // Xóa tất cả lỗi cũ
      clearError('supplier-error');
      clearError('products-error');
      
      let hasError = false;

      // Kiểm tra nhà cung cấp
      const supplierId = document.getElementById('supplier_id').value;
      if (!supplierId || supplierId === '') {
        showError('supplier-error', '⚠️ Vui lòng chọn nhà cung cấp!');
        hasError = true;
      }

      // Kiểm tra danh sách sản phẩm
      const tbody = document.getElementById('productTable').querySelector('tbody');
      const rows = tbody.querySelectorAll('tr');
      if (rows.length === 0) {
        showError('products-error', '⚠️ Vui lòng thêm ít nhất một sản phẩm!');
        if (!hasError) {
          document.getElementById('barcode').focus();
        }
        hasError = true;
      }

      // Kiểm tra số lượng và giá của từng sản phẩm
      for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const qty = parseNumber(row.querySelector("input[name*='[quantity]']").value);
        const price = parseNumber(row.querySelector("input[type='hidden'][name*='[price]']").value);
        const productName = row.cells[1].textContent;

        if (qty <= 0) {
          showError('products-error', '⚠️ Số lượng của "' + productName + '" phải lớn hơn 0!');
          hasError = true;
          break;
        }

        if (price <= 0) {
          showError('products-error', '⚠️ Giá nhập của "' + productName + '" phải lớn hơn 0!');
          hasError = true;
          break;
        }
      }

      return !hasError;
    }

    // --- Validate và hiển thị phần confirm ---
    function validateAndShowConfirm() {
      if (validateForm()) {
        // Ẩn nút tạo phiếu, hiển thị phần confirm
        document.getElementById('createBtn').style.display = 'none';
        document.getElementById('confirmSection').classList.add('show');
        // Scroll đến phần confirm
        document.getElementById('confirmSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }

    // --- Xác nhận tạo phiếu ---
    function confirmCreate() {
      document.getElementById('receiptForm').submit();
    }

    // --- Hủy tạo phiếu ---
    function cancelCreate() {
      document.getElementById('confirmSection').classList.remove('show');
      document.getElementById('createBtn').style.display = 'inline-block';
    }

    // --- Xóa lỗi khi người dùng thay đổi nhà cung cấp ---
    document.getElementById('supplier_id').addEventListener('change', function() {
      if (this.value) {
        clearError('supplier-error');
        
        // Xóa tất cả sản phẩm đã thêm khi đổi nhà cung cấp
        const tbody = document.getElementById('productTable').querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        if (rows.length > 0) {
          if (confirm('⚠️ Thay đổi nhà cung cấp sẽ xóa tất cả sản phẩm đã thêm. Bạn có chắc chắn?')) {
            tbody.innerHTML = '';
            productMap = {};
            rowIndex = 0;
            showSuccess('products-error', 'ℹ️ Đã xóa danh sách sản phẩm. Vui lòng thêm sản phẩm của nhà cung cấp mới.');
          } else {
            // Hoàn tác việc chọn nhà cung cấp mới
            const previousSupplier = this.getAttribute('data-previous-value');
            if (previousSupplier) {
              this.value = previousSupplier;
            }
          }
        }
        // Lưu giá trị hiện tại
        this.setAttribute('data-previous-value', this.value);
      }
    });

    // --- Xóa lỗi sản phẩm khi thêm sản phẩm mới ---
    function clearProductError() {
      clearError('products-error');
    }

    // --- Format number theo chuẩn Việt Nam ---
    function formatNumber(n) {
      if (n === null || n === undefined) return '';
      const num = Number(n) || 0;
      return num.toLocaleString('vi-VN');
    }

    // --- Parse lại từ chuỗi có dấu . ---
    function parseNumber(s) {
      if (s === null || s === undefined) return 0;
      return Number(String(s).replace(/\./g, '').replace(/,/g, '')) || 0;
    }

    // --- Nhập barcode thủ công ---
    document.getElementById("barcode").addEventListener("keypress", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        let code = this.value.trim();
        if (code !== "") fetchProduct(code);
      }
    });

    // Also listen for keydown as some browsers/platforms don't fire keypress for Enter
    document.getElementById("barcode").addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();
        let code = this.value.trim();
        if (code !== "") fetchProduct(code);
      }
    });

    // --- Nút Scanner USB ---
    function useScanner() {
      document.getElementById("barcode").focus();
      showSuccess('products-error', 'ℹ️ Đặt con trỏ vào ô barcode và quét bằng máy scanner USB.');
    }
    
    // --- Thêm sản phẩm thủ công ---
    function addManualProduct() {
      const code = prompt('Nhập mã SKU, mã vạch hoặc mã lô:');
      if (code && code.trim() !== '') {
        fetchProduct(code.trim());
      }
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
        showError('products-error', '⚠️ Không mở được camera: ' + err);
        document.getElementById("reader").style.display = "none";
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
      // Normalize scanned code: trim and remove non-printable characters
      if (typeof code === 'string') {
        code = code.trim().replace(/\s+/g, '');
      }

      // Get receipt type and selected supplier
      const receiptType = document.getElementById('receipt_type').value;
      const selectedSupplierId = document.getElementById('supplier_id').value || '';
      
      // For purchase receipts, supplier must be selected first
      if (receiptType === 'purchase' && !selectedSupplierId) {
        showError('products-error', '⚠️ Vui lòng chọn nhà cung cấp trước khi quét mã!');
        document.getElementById('barcode').value = '';
        return;
      }

      // If an export is selected, prefer to validate against its batches/products
      if (currentExport) {
        // Check batch codes first (case-insensitive, normalized)
        const batches = currentExport.batches || [];
        const normCode = (code || '').toString().toLowerCase();
        const matchedBatch = batches.find(b => {
          const bc = (b.batch_code || '').toString().trim().replace(/\s+/g, '').toLowerCase();
          const bb = (b.batch_barcode || '').toString().trim().replace(/\s+/g, '').toLowerCase();
          return (bc && bc === normCode) || (bb && bb === normCode);
        });
        if (matchedBatch) {
          console.log('Matched batch in currentExport:', matchedBatch);
          console.log('currentExport batches:', batches);
          // Instead of relying solely on export data, fetch full batch info from server
          // so we get accurate product name, unit, import_price and quantity_remaining
          fetch('get_product.php?barcode=' + encodeURIComponent(code))
            .then(res => res.json())
            .then(data => {
                const lf = document.getElementById('lastFetchDebug');
                if (lf) { lf.style.display = 'block'; lf.textContent = JSON.stringify(data, null, 2); }
                if (data.success && data.product) {
                  addOrUpdateRow(data.product, 'batch');
                  document.getElementById('barcode').value = '';
                } else {
                  showError('products-error', '⚠️ Không thể lấy thông tin lô từ server: ' + (data.message || ''));
                }
            })
            .catch(err => {
                const lf = document.getElementById('lastFetchDebug');
                if (lf) { lf.style.display = 'block'; lf.textContent = 'ERROR: ' + err.message; }
                showError('products-error', '⚠️ Lỗi khi tải lô: ' + err.message);
            });
          return;
        }

        // Check product SKU or id in export products
        const products = currentExport.products || [];
        const matchedProduct = products.find(p => (p.sku && p.sku === code) || (p.product_id && p.product_id === code));
        if (matchedProduct) {
          // Fetch full product info from server to get import_price and base details
          fetch('get_product.php?barcode=' + encodeURIComponent(code))
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                // ensure product belongs to export
                if (products.find(p => p.product_id === data.product._id)) {
                  addOrUpdateRow(data.product, data.type || 'product');
                } else {
                  showError('products-error', '⚠️ Sản phẩm không thuộc phiếu xuất đã chọn.');
                }
                document.getElementById('barcode').value = '';
              } else {
                showError('products-error', '⚠️ Không tìm thấy sản phẩm với mã: ' + code);
                document.getElementById('barcode').value = '';
              }
            }).catch(err => {
              showError('products-error', '⚠️ Lỗi khi tìm sản phẩm!');
            });
          return;
        }

        // Not in export — show error
        showError('products-error', '⚠️ Mã quét không thuộc phiếu xuất đã chọn. Vui lòng chọn đúng lô/sản phẩm.');
        document.getElementById('barcode').value = '';
        return;
      }

      // Fallback: no export selected, proceed as before
      fetch("get_product.php?barcode=" + encodeURIComponent(code))
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            // Enforce supplier restriction for purchase receipts
            if (receiptType === 'purchase') {
              const supplierEl = document.getElementById('supplier_id');
              const selectedText = supplierEl.options[supplierEl.selectedIndex]?.text?.trim() || '';
              const prodSupplierId = (data.product.supplier && (data.product.supplier._id || data.product.supplier.id))
                                    || data.product.supplier_id || '';
              const prodSupplierName = (data.product.supplier && (data.product.supplier.name || data.product.supplier_name))
                                       || data.product.supplier_name || '';

              const idMatches = prodSupplierId && String(prodSupplierId) === String(selectedSupplierId);
              const nameMatches = prodSupplierName && selectedText && prodSupplierName.trim().toLowerCase() === selectedText.toLowerCase();

              // Block when product supplier missing OR mismatched
              if (!idMatches && !nameMatches) {
                const supplierName = selectedText || 'nhà cung cấp đã chọn';
                showError('products-error', `⚠️ Sản phẩm "${data.product.name}" không thuộc nhà cung cấp "${supplierName}". Vui lòng chỉ thêm sản phẩm đúng nhà cung cấp!`);
                document.getElementById("barcode").value = "";
                return;
              }
            }
            addOrUpdateRow(data.product, data.type || 'product');
            document.getElementById("barcode").value = "";
          } else {
            showError('products-error', '⚠️ Không tìm thấy sản phẩm với mã: ' + code);
            document.getElementById("barcode").value = "";
          }
        })
        .catch(err => {
          showError('products-error', '⚠️ Lỗi khi tìm sản phẩm!');
          document.getElementById("barcode").value = "";
        });
    }

    function addOrUpdateRow(product, type) {
      // Final guard: if creating purchase, ensure product supplier matches selected supplier
      const receiptType = document.getElementById('receipt_type').value;
      const selectedSupplierId = document.getElementById('supplier_id').value || '';
      if (receiptType === 'purchase') {
        const supplierEl = document.getElementById('supplier_id');
        const selectedText = supplierEl.options[supplierEl.selectedIndex]?.text?.trim() || '';
        const prodSupplierId = (product.supplier && (product.supplier._id || product.supplier.id))
                              || product.supplier_id || '';
        const prodSupplierName = (product.supplier && (product.supplier.name || product.supplier_name))
                                 || product.supplier_name || '';
        const idMatches = prodSupplierId && String(prodSupplierId) === String(selectedSupplierId);
        const nameMatches = prodSupplierName && selectedText && prodSupplierName.trim().toLowerCase() === selectedText.toLowerCase();
        if (!idMatches && !nameMatches) {
          const supplierName = selectedText || 'nhà cung cấp đã chọn';
          showError('products-error', `⚠️ Sản phẩm "${product.name || product.product_name || ''}" không thuộc nhà cung cấp "${supplierName}".`);
          return; // Block row addition
        }
      }
      clearProductError(); // Xóa lỗi khi thêm sản phẩm
      
      // Tìm row đã tồn tại bằng cách duyệt qua tất cả các row và so sánh
      const tbody = document.getElementById("productTable").querySelector("tbody");
      const existingRows = tbody.querySelectorAll('tr');
      let existingRow = null;
      
      const newProductId = String(product._id || '').trim();
      const newBatchCode = String(product.batch_code || '').trim();
      
      console.log('🔍 [v2.0] Đang quét:', {
        type: type,
        productId: newProductId,
        batchCode: newBatchCode,
        productName: product.name || product.product_name,
        timestamp: new Date().toISOString()
      });
      
      for (let i = 0; i < existingRows.length; i++) {
        const row = existingRows[i];
        const rowProductId = String(row.querySelector("input[name*='[product_id]']")?.value || '').trim();
        const batchCodeInput = row.querySelector("input[name*='[batch_code]']");
        const rowBatchCode = batchCodeInput ? String(batchCodeInput.value || '').trim() : '';
        
        console.log(`  Row ${i+1}:`, {
          rowProductId: rowProductId,
          rowBatchCode: rowBatchCode,
          rowText: row.cells[1]?.textContent?.trim()
        });
        
        // Nếu là batch: phải khớp cả product_id VÀ batch_code
        // Nếu là product: chỉ cần khớp product_id VÀ row đó không có batch_code
        let isMatch = false;
        
        if (type === 'batch' && newBatchCode) {
          // Quét lô: khớp product_id và batch_code (cả 2 đều phải có giá trị)
          isMatch = (rowProductId === newProductId && rowBatchCode === newBatchCode && rowBatchCode !== '');
          console.log(`    Kiểm tra batch: productId match=${rowProductId === newProductId}, batchCode match=${rowBatchCode === newBatchCode}, result=${isMatch}`);
        } else if (type !== 'batch') {
          // Quét sản phẩm: khớp product_id và row không có batch
          isMatch = (rowProductId === newProductId && rowBatchCode === '');
          console.log(`    Kiểm tra product: productId match=${rowProductId === newProductId}, no batch=${rowBatchCode === ''}, result=${isMatch}`);
        }
        
        if (isMatch) {
          console.log('✅ Tìm thấy row trùng khớp!');
          existingRow = row;
          break;
        }
      }
      
      if (!existingRow) {
        console.log('❌ Không tìm thấy row trùng → Thêm dòng mới');
      }
      
      if (existingRow) {
        // Nếu đã tồn tại, tăng số lượng
        let qtyInput = existingRow.querySelector("input[name*='[quantity]']");
        qtyInput.value = parseInt(qtyInput.value) + 1;
        calcSubtotal(qtyInput);
        
        // Hiển thị thông báo
        const msg = type === 'batch' && product.batch_code
          ? `✅ Đã cập nhật số lượng lô "${product.batch_code}"`
          : `✅ Đã cập nhật số lượng sản phẩm "${product.name || product.product_name}"`;
        showSuccess('products-error', msg);
      } else {
        // Tạo unique key để lưu vào productMap (không dùng nữa nhưng giữ để tương thích)
        const uniqueKey = (type === 'batch' && newBatchCode) 
          ? (newProductId + '_' + newBatchCode) 
          : newProductId;
        
        const tbody = document.getElementById("productTable").querySelector("tbody");
        const row = tbody.insertRow();
        row.id = "row-" + rowIndex;
        
        // Thêm hidden inputs cho batch_code nếu là batch
        let batchInputs = '';
        let batchDisplay = '<span style="color:#999;">-</span>';
        if (type === 'batch' && product.batch_code) {
          const qtyRemaining = product.quantity_remaining || 0;
          const sourceWh = product.source_warehouse_id || '';
          
          batchInputs = `
            <input type="hidden" name="products[${rowIndex}][batch_code]" value="${product.batch_code}">
            <input type="hidden" name="products[${rowIndex}][batch_barcode]" value="${product.batch_barcode || ''}">
            <input type="hidden" name="products[${rowIndex}][source]" value="${product.source || 'transfer'}">
            <input type="hidden" name="products[${rowIndex}][source_warehouse_id]" value="${sourceWh}">
            <input type="hidden" name="products[${rowIndex}][source_batch_code]" value="${product.batch_code}">
            ${product.source_location ? `<input type="hidden" name="products[${rowIndex}][source_location]" value='${JSON.stringify(product.source_location)}'>` : ''}
          `;
          batchDisplay = `
            <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
              <i class="fa-solid fa-box" style="color:#007bff;"></i>
              <strong style="color:#007bff;">${product.batch_code}</strong>
            </div>
            ${sourceWh ? `<small style="color:#666;"><i class="fa-solid fa-warehouse"></i> Từ: ${sourceWh}</small><br>` : ''}
            ${qtyRemaining > 0 ? `<small style="color:#28a745;"><i class="fa-solid fa-cubes"></i> Tồn: ${qtyRemaining} ${product.unit || ''}</small>` : ''}
          `;
        }
        
        row.innerHTML = `
          <td>
            <input type="hidden" name="products[${rowIndex}][sku]" value="${product.sku || ''}">
            <input type="hidden" name="products[${rowIndex}][product_id]" value="${product._id}">
            ${batchInputs}
            <strong>${product.sku || 'N/A'}</strong>
          </td>
          <td><strong>${product.name}</strong></td>
          <td>${batchDisplay}</td>
          <td><strong>${product.unit}</strong></td>
          <td>
            <input type="number" name="products[${rowIndex}][quantity]" value="1" min="1" 
                   ${type === 'batch' && product.quantity_remaining ? `max="${product.quantity_remaining}"` : ''}
                   oninput="calcSubtotal(this)" 
                   onchange="validateBatchQuantity(this, ${product.quantity_remaining || 0})">
            ${type === 'batch' && product.quantity_remaining ? `<input type="hidden" name="products[${rowIndex}][max_quantity]" value="${product.quantity_remaining}">` : ''}
          </td>
          <td>
            <input type="hidden" name="products[${rowIndex}][price]" value="${product.import_price}">
            <input type="text" class="price-display" value="${formatNumber(product.import_price)}" 
                   oninput="onPriceInput(this)" onblur="formatPriceOnBlur(this)">
          </td>
          <td><input type="text" name="products[${rowIndex}][subtotal]" 
                     value="${formatNumber(product.import_price)}" readonly></td>
          <td><button type="button" class="btn btn-danger" onclick="removeRow(this)">Xóa</button></td>
        `;
        
        // Không cần productMap nữa vì đã dùng DOM để tìm row trùng
        rowIndex++;
        
        // Hiển thị thông báo thành công
        const msg = type === 'batch' 
          ? `✅ Đã thêm lô hàng "${product.batch_code}" - ${product.name || product.product_name}`
          : `✅ Đã thêm sản phẩm "${product.name || product.product_name}"`;
        showSuccess('products-error', msg);
      }
    }

    function removeRow(btn) {
      const row = btn.closest("tr");
      if (row) {
        row.parentNode.removeChild(row);
      }
    }

    function calcSubtotal(input) {
      const row = input.closest("tr");
      const qty = parseNumber(row.querySelector("input[name*='[quantity]']").value) || 0;
      const price = parseNumber(row.querySelector("input[type='hidden'][name*='[price]']").value) || 0;
      row.querySelector("input[name*='[subtotal]']").value = formatNumber(qty * price);
    }
    
    function validateBatchQuantity(input, maxQty) {
      const qty = parseInt(input.value) || 0;
      if (maxQty > 0 && qty > maxQty) {
        showError('products-error', `⚠️ Số lượng nhập (${qty}) vượt quá tồn kho (${maxQty})!`);
        input.value = maxQty;
        calcSubtotal(input);
      }
    }

    function onPriceInput(el) {
      const row = el.closest('tr');
      const hidden = row.querySelector("input[type='hidden'][name*='[price]']");
      let value = el.value.replace(/[^0-9]/g, '');
      hidden.value = value;
      el.value = value;
    }

    function formatPriceOnBlur(el) {
      const row = el.closest('tr');
      const hidden = row.querySelector("input[type='hidden'][name*='[price]']");
      const qtyInput = row.querySelector("input[name*='[quantity]']");
      const subtotalInput = row.querySelector("input[name*='[subtotal]']");
      
      const raw = parseNumber(el.value);
      hidden.value = raw;
      el.value = formatNumber(raw);
      
      const qty = parseNumber(qtyInput.value) || 0;
      subtotalInput.value = formatNumber(raw * qty);
    }
  </script>
</body>
</html>
