<?php
include_once(__DIR__ . '/../../../../../controller/cCategories.php');
include_once(__DIR__ . '/../../../../../controller/cSupplier.php');

$categories = (new CCategories())->getAllCategories();
$suppliers = (new CSupplier())->getAllSuppliers();

// Đơn vị có sẵn
$unitOptions = ['cái', 'bộ', 'hộp', 'thùng', 'chiếc', 'set', 'cuộn', 'chai', 'tuýp'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Thêm sản phẩm mới</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f6fa;
      color: #333;
      margin: 0;
      padding: 0;
    }
    .container {
      width: 95%;
      max-width: 1000px;
      margin: 40px auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      padding: 25px 35px;
    }
    h2 {
      text-align: center;
      color: #222;
      margin-bottom: 25px;
    }
    form {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px 30px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
    }
    .form-group label {
      font-weight: 600;
      margin-bottom: 6px;
      color: #333;
    }
    input, select, textarea {
      padding: 9px 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 15px;
      transition: all 0.2s;
    }
    input:focus, select:focus, textarea:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 5px rgba(59,130,246,0.3);
      outline: none;
    }
    textarea {
      resize: vertical;
      min-height: 70px;
    }
    .full-width {
      grid-column: 1 / 3;
    }
    .conversion-box {
      grid-column: 1 / 3;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 15px;
    }
    .conversion-item {
      display: flex;
      gap: 10px;
      margin-bottom: 8px;
      align-items: center;
    }
    .conversion-item select, .conversion-item input {
      flex: 1;
    }
    .btn {
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      padding: 8px 14px;
      color: #fff;
      transition: all 0.2s;
    }
    .btn-success { background: #16a34a; }
    .btn-success:hover { background: #15803d; }
    .btn-secondary { background: #6b7280; }
    .btn-secondary:hover { background: #4b5563; }
    .btn-danger { background: #dc3545; }
    .btn-danger:hover { background: #b91c1c; }
    .form-actions {
      grid-column: 1 / 3;
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 15px;
    }
    .hint {
      font-size: 13px;
      color: #555;
      margin-top: 4px;
    }
      /* Inline error message style (match supplier form) */
      .error-message {
        display: block;
        margin-top: 8px;
        color: #ef4444;
        font-size: 0.85rem;
      }
    /* Validation styles */
    .input-error { border-color: #dc2626 !important; box-shadow: 0 0 0 3px rgba(220,37,37,0.06); }
    .error-text { color: #dc2626; font-size: 13px; margin-top: 6px; }
  </style>
</head>
<body>
  <div class="container">
    <h2><i class="fa-solid fa-boxes-stacked"></i> Thêm sản phẩm mới</h2>

    <form id="createProductForm" action="products/createProduct/process.php" method="post" enctype="multipart/form-data" novalidate>

      <div class="form-group">
        <label for="image">Hình ảnh sản phẩm</label>
        <input type="file" id="image" name="image" accept="image/*">
      </div>

      <div class="form-group">
        <label for="barcode">Barcode</label>
        <input type="text" id="barcode" name="barcode" placeholder="Barcode..." required>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="product_name">Tên sản phẩm</label>
        <input type="text" id="product_name" name="product_name" required>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="model">Model</label>
        <input type="text" id="model" name="model" placeholder="Nhập model sản phẩm (VD: XS-2024)" required>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="category_id">Loại sản phẩm</label>
        <select id="category_id" name="category_id" required>
          <option value="">-- Chọn loại sản phẩm --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['category_id']; ?>">
              <?= $cat['name'] ?? $cat['category_name'] ?? $cat['category_id']; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="supplier_id">Nhà cung cấp</label>
        <select id="supplier_id" name="supplier_id" required>
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $sup): ?>
            <option value="<?= $sup['supplier_id']; ?>">
              <?= $sup['supplier_name'] ?? $sup['name'] ?? $sup['supplier_id']; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="base_unit">Đơn vị chính</label>
        <select id="base_unit" name="base_unit" required>
          <option value="">-- Chọn đơn vị --</option>
          <?php foreach ($unitOptions as $u): ?>
            <option value="<?= $u; ?>"><?= ucfirst($u); ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Đơn vị chính là đơn vị nhỏ nhất (ví dụ: cái, chiếc)</p>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="min_stock">Tồn kho tối thiểu</label>
        <input type="number" id="min_stock" name="min_stock" min="0" value="0" required>
        <span class="error-message"></span>
      </div>

      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="1">Hoạt động</option>
          <option value="0">Ngừng hoạt động</option>
        </select>
      </div>

      <!-- Thông tin kích thước đơn vị chính -->
      <div class="conversion-box">
        <label><i class="fa-solid fa-box"></i> Kích thước & Trọng lượng đơn vị chính (<span id="baseUnitLabel" style="color: #3b82f6; font-weight: 700;">-</span>)</label>
        <p class="hint" style="color: #dc2626; font-weight: 600;">⚠️ Nhập kích thước của 1 đơn vị nhỏ nhất (VD: 1 cái iPhone = 8×2×15 cm)</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
          <div>
            <label>Chiều rộng (cm)</label>
            <input type="number" step="0.01" id="package_width" name="package_width" placeholder="VD: 8" value="0">
          </div>
          <div>
            <label>Chiều sâu (cm)</label>
            <input type="number" step="0.01" id="package_depth" name="package_depth" placeholder="VD: 2" value="0">
          </div>
          <div>
            <label>Chiều cao (cm)</label>
            <input type="number" step="0.01" id="package_height" name="package_height" placeholder="VD: 15" value="0">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
          <div>
            <label>Trọng lượng (kg)</label>
            <input type="number" step="0.01" name="package_weight" placeholder="VD: 0.25" value="0">
          </div>
          <div>
            <label>Thể tích (cm³) - Tự động tính</label>
            <input type="number" step="0.01" id="volume_per_unit" name="volume_per_unit" value="0" readonly style="background: #f0f0f0;">
          </div>
        </div>
      </div>

      <!-- Đơn vị quy đổi -->
      <div class="conversion-box">
        <label><i class="fa-solid fa-arrows-rotate"></i> Đơn vị quy đổi</label>
        <p class="hint" style="color: #dc2626; font-weight: 600;">⚠️ Kích thước của ĐƠN VỊ LỚN HƠN (VD: 1 thùng = 15 cái → nhập kích thước THÙNG, phải lớn hơn kích thước 1 cái)</p>
        <div id="conversion-container">
          <div class="conversion-item" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;">
            <div>
              <label style="font-size: 13px; font-weight: 600;">Đơn vị</label>
              <select name="conversion_unit[]" class="conversion-unit-select">
                <option value="">-- Đơn vị --</option>
                <?php foreach ($unitOptions as $u): ?>
                  <option value="<?= $u; ?>"><?= ucfirst($u); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Hệ số quy đổi</label>
              <input type="number" step="0.01" name="conversion_factor[]" class="conversion-factor" placeholder="VD: 15">
            </div>
            <div style="grid-column: 1 / 3;">
              <label style="font-size: 13px; font-weight: 600; margin-bottom: 5px; display: block;">
                Kích thước của 1 <span class="unit-name-display" style="color: #3b82f6;">-</span>
                <span style="color: #dc2626; font-size: 12px;">(phải lớn hơn đơn vị chính)</span>
              </label>
              <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                <input type="number" step="0.01" name="conversion_width[]" placeholder="Rộng (cm)" class="conversion-width">
                <input type="number" step="0.01" name="conversion_depth[]" placeholder="Sâu (cm)" class="conversion-depth">
                <input type="number" step="0.01" name="conversion_height[]" placeholder="Cao (cm)" class="conversion-height">
              </div>
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Trọng lượng (kg)</label>
              <input type="number" step="0.01" name="conversion_weight[]" placeholder="VD: 5">
            </div>
            <div>
              <label style="font-size: 13px; font-weight: 600;">Thể tích (cm³)</label>
              <input type="number" step="0.01" name="conversion_volume[]" class="conversion-volume" readonly style="background: #f0f0f0;" placeholder="Tự động">
            </div>
          </div>
        </div>
        <button type="button" id="addConversionBtn" class="btn btn-secondary" style="margin-top:8px;">
          <i class="fa-solid fa-plus"></i> Thêm quy đổi
        </button>
      </div>

      <!-- Thông tin xếp chồng -->
      <div class="form-group">
        <label for="stackable">Có thể xếp chồng</label>
        <select id="stackable" name="stackable">
          <option value="1">Có</option>
          <option value="0">Không</option>
        </select>
      </div>

      <div class="form-group">
        <label for="max_stack_height">Chiều cao xếp chồng tối đa</label>
        <input type="number" id="max_stack_height" name="max_stack_height" min="0" value="0" placeholder="VD: 3">
        <p class="hint">Số tầng có thể xếp chồng lên nhau</p>
      </div>

      <div class="form-group full-width">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="3"></textarea>
      </div>

      <div class="form-actions">
        <a href="index.php?page=products" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Quay lại
        </a>
        <button type="reset" class="btn btn-secondary">Hủy</button>
        <button type="submit" name="btnAdd" id="saveBtn" class="btn btn-success is-disabled" aria-disabled="true">
          <i class="fas fa-plus"></i> Thêm sản phẩm
        </button>
      </div>
    </form>
  </div>

  <!-- Modal xác nhận thêm -->
  <div id="confirmModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:450px; margin:15vh auto; padding:30px; border-radius:12px; text-align:center;">
      <h3>Xác nhận thêm sản phẩm</h3>
      <p>Bạn có chắc chắn muốn thêm sản phẩm này không?</p>
      <div style="display:flex; gap:12px; justify-content:center; margin-top:18px;">
        <button type="button" id="cancelModalBtn" style="background:#e5e7eb; color:#374151; padding:10px 20px; border-radius:8px; border:none;">Hủy</button>
        <button type="button" id="confirmAddBtn" style="background:#16a34a; color:#fff; padding:10px 20px; border-radius:8px; border:none;">Xác nhận</button>
      </div>
    </div>
  </div>

  <script>
    const addBtn = document.getElementById('addConversionBtn');
    const container = document.getElementById('conversion-container');
    const baseUnitSelect = document.getElementById('base_unit');
    const baseUnitLabel = document.getElementById('baseUnitLabel');

    // Cập nhật label đơn vị chính
    baseUnitSelect.addEventListener('change', function() {
      baseUnitLabel.textContent = this.value || '-';
    });

    // Tính thể tích đơn vị chính
    function calcPackageVolume() {
      const w = parseFloat(document.getElementById('package_width').value) || 0;
      const d = parseFloat(document.getElementById('package_depth').value) || 0;
      const h = parseFloat(document.getElementById('package_height').value) || 0;
      const volume = w * d * h;
      document.getElementById('volume_per_unit').value = volume.toFixed(2);
    }

    document.getElementById('package_width').addEventListener('input', calcPackageVolume);
    document.getElementById('package_depth').addEventListener('input', calcPackageVolume);
    document.getElementById('package_height').addEventListener('input', calcPackageVolume);

    // Tính thể tích đơn vị quy đổi
    function calcConversionVolume(item) {
      const w = parseFloat(item.querySelector('.conversion-width').value) || 0;
      const d = parseFloat(item.querySelector('.conversion-depth').value) || 0;
      const h = parseFloat(item.querySelector('.conversion-height').value) || 0;
      const volume = w * d * h;
      item.querySelector('.conversion-volume').value = volume.toFixed(2);
    }

    // Cập nhật tên đơn vị trong label
    function updateUnitName(item) {
      const unitSelect = item.querySelector('.conversion-unit-select');
      const unitDisplay = item.querySelector('.unit-name-display');
      unitDisplay.textContent = unitSelect.value || '-';
    }

    // Event delegation cho conversion items
    container.addEventListener('input', function(e) {
      const item = e.target.closest('.conversion-item');
      if (!item) return;

      if (e.target.classList.contains('conversion-width') || 
          e.target.classList.contains('conversion-depth') || 
          e.target.classList.contains('conversion-height')) {
        calcConversionVolume(item);
      }
    });

    container.addEventListener('change', function(e) {
      if (e.target.classList.contains('conversion-unit-select')) {
        const item = e.target.closest('.conversion-item');
        updateUnitName(item);
      }
    });

    // Thêm đơn vị quy đổi mới
    addBtn.addEventListener('click', () => {
      const div = document.createElement('div');
      div.className = 'conversion-item';
      div.style.cssText = 'display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; position: relative;';
      div.innerHTML = `
        <div>
          <label style="font-size: 13px; font-weight: 600;">Đơn vị</label>
          <select name="conversion_unit[]" class="conversion-unit-select">
            <option value="">-- Đơn vị --</option>
            <?php foreach ($unitOptions as $u): ?>
              <option value="<?= $u; ?>"><?= ucfirst($u); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size: 13px; font-weight: 600;">Hệ số quy đổi</label>
          <input type="number" step="0.01" name="conversion_factor[]" class="conversion-factor" placeholder="VD: 15">
        </div>
        <div style="grid-column: 1 / 3;">
          <label style="font-size: 13px; font-weight: 600; margin-bottom: 5px; display: block;">
            Kích thước của 1 <span class="unit-name-display" style="color: #3b82f6;">-</span>
            <span style="color: #dc2626; font-size: 12px;">(phải lớn hơn đơn vị chính)</span>
          </label>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
            <input type="number" step="0.01" name="conversion_width[]" placeholder="Rộng (cm)" class="conversion-width">
            <input type="number" step="0.01" name="conversion_depth[]" placeholder="Sâu (cm)" class="conversion-depth">
            <input type="number" step="0.01" name="conversion_height[]" placeholder="Cao (cm)" class="conversion-height">
          </div>
        </div>
        <div>
          <label style="font-size: 13px; font-weight: 600;">Trọng lượng (kg)</label>
          <input type="number" step="0.01" name="conversion_weight[]" placeholder="VD: 5">
        </div>
        <div>
          <label style="font-size: 13px; font-weight: 600;">Thể tích (cm³)</label>
          <input type="number" step="0.01" name="conversion_volume[]" class="conversion-volume" readonly style="background: #f0f0f0;" placeholder="Tự động">
        </div>
        <button type="button" class="btn btn-danger removeConversion" style="position: absolute; top: 5px; right: 5px; padding: 4px 8px;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      `;
      container.appendChild(div);
    });

    // Xóa conversion item
    document.addEventListener('click', e => {
      if (e.target.closest('.removeConversion')) {
        e.target.closest('.conversion-item').remove();
      }
    });

    // --- Client-side validation for required fields ---
    const form = document.getElementById('createProductForm');
    const requiredFields = [
      { id: 'barcode', label: 'Barcode' },
      { id: 'model', label: 'Model' },
      { id: 'product_name', label: 'Tên sản phẩm' },
      { id: 'category_id', label: 'Loại sản phẩm' },
      { id: 'supplier_id', label: 'Nhà cung cấp' },
      { id: 'base_unit', label: 'Đơn vị chính' },
      { id: 'min_stock', label: 'Tồn kho tối thiểu' }
    ];

    function showError(el, msg) {
      el.classList.add('input-error');
      // prefer existing span.error-message inside the same form-group
      const parent = el.parentNode || document;
      const msgSpan = parent.querySelector('.error-message');
      if (msgSpan) {
        msgSpan.textContent = msg;
        return;
      }
      // fallback to .error-text element (created if missing)
      let next = el.nextElementSibling;
      if (next && next.classList && next.classList.contains('error-text')) {
        next.textContent = msg;
        return;
      }
      const div = document.createElement('div');
      div.className = 'error-text';
      div.textContent = msg;
      el.parentNode.insertBefore(div, el.nextSibling);
    }

    function clearError(el) {
      el.classList.remove('input-error');
      const parent = el.parentNode || document;
      const msgSpan = parent.querySelector('.error-message');
      if (msgSpan) msgSpan.textContent = '';
      let next = el.nextElementSibling;
      if (next && next.classList && next.classList.contains('error-text')) {
        next.remove();
      }
    }

    // clear errors on input/change and wire up save button enable
    const saveBtn = document.getElementById('saveBtn');
    requiredFields.forEach(f => {
      const el = document.getElementById(f.id);
      if (!el) return;
      el.addEventListener('input', () => { clearError(el); updateSaveButton(); });
      el.addEventListener('change', () => { clearError(el); updateSaveButton(); });
      // show validation message on blur (when user focuses then leaves)
      el.addEventListener('blur', () => {
        const val = (el.value || '').toString().trim();
        if (val === '' || val === null) {
          showError(el, 'Trường này là bắt buộc');
        } else if (f.id === 'min_stock') {
          const num = Number(val);
          if (Number.isNaN(num) || num < 0) {
            showError(el, `${f.label} phải là số >= 0.`);
          } else {
            clearError(el);
          }
        } else {
          clearError(el);
        }
        updateSaveButton();
      });
    });

    function updateSaveButton() {
      // consider all controls with the `required` attribute inside the form
      const requiredControls = Array.from(form.querySelectorAll('[required]'));
      let allFilled = true;
      requiredControls.forEach(el => {
        // For selects and inputs
        const val = (el.value || '').toString().trim();
        if (val === '') allFilled = false;
      });
      if (allFilled) {
        saveBtn.classList.remove('is-disabled');
        saveBtn.removeAttribute('aria-disabled');
        saveBtn.disabled = false;
      } else {
        saveBtn.classList.add('is-disabled');
        saveBtn.setAttribute('aria-disabled', 'true');
        saveBtn.disabled = true;
      }
    }

    // initial check
    updateSaveButton();

    // Confirmation modal logic
    const confirmModal = document.getElementById('confirmModal');
    const confirmAddBtn = document.getElementById('confirmAddBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    let isConfirmed = false;

    form.addEventListener('submit', function(e) {
      // prevent submission while button visually disabled
      if (saveBtn.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return false; }

      let hasError = false;
      // validate required
      requiredFields.forEach(f => {
        const el = document.getElementById(f.id);
        if (!el) return;
        const val = (el.value || '').toString().trim();
        if (val === '' || val === null) {
          showError(el, `${f.label} là bắt buộc.`);
          hasError = true;
        } else if (f.id === 'min_stock') {
          const num = Number(val);
          if (Number.isNaN(num) || num < 0) {
            showError(el, `${f.label} phải là số >= 0.`);
            hasError = true;
          }
        }
      });

      if (hasError) {
        e.preventDefault();
        const firstError = document.querySelector('.input-error');
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
      }

      if (!isConfirmed) { e.preventDefault(); confirmModal.style.display = 'block'; return false; }
      // otherwise allow submit
    });

    confirmAddBtn && confirmAddBtn.addEventListener('click', function(){ isConfirmed = true; confirmModal.style.display = 'none'; saveBtn.click(); });
    cancelModalBtn && cancelModalBtn.addEventListener('click', function(){ confirmModal.style.display = 'none'; isConfirmed = false; });
    window.addEventListener('click', function(e){ if (e.target === confirmModal) { confirmModal.style.display = 'none'; isConfirmed = false; } });
  </script>
</body>
</html>
