<?php
include_once(__DIR__ . '/../../../../../controller/cProduct.php');
include_once(__DIR__ . '/../../../../../controller/cCategories.php');
include_once(__DIR__ . '/../../../../../controller/cSupplier.php');

$cProduct = new CProduct();
$categories = (new CCategories())->getAllCategories();
$suppliers = (new CSupplier())->getAllSuppliers();

// Danh sách đơn vị
$unitOptions = ['cái', 'bộ', 'hộp', 'thùng', 'chiếc', 'set', 'cuộn', 'chai', 'tuýp'];

$product = null;
if (isset($_GET['id'])) {
    $sku = $_GET['id'];
    $products = $cProduct->getAllProducts();
    foreach ($products as $p) {
        if ($p['sku'] === $sku) {
            $product = $p;
            break;
        }
    }
}
if (!$product) {
    echo "<script>alert('Không tìm thấy sản phẩm.'); window.location.href='../../index.php?page=products';</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cập nhật sản phẩm</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f6fa;
      margin: 0;
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
    h2 { text-align: center; color: #222; margin-bottom: 25px; }
    form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 30px; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { font-weight: 600; margin-bottom: 6px; color: #333; }
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
    textarea { resize: vertical; min-height: 70px; }
    .full-width { grid-column: 1 / 3; }
    .conversion-box {
      grid-column: 1 / 3;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 15px;
    }
    .conversion-item { display: flex; gap: 10px; margin-bottom: 8px; align-items: center; }
    .conversion-item select, .conversion-item input { flex: 1; }
    .btn {
      border: none; border-radius: 8px; cursor: pointer;
      font-size: 14px; padding: 8px 14px; color: #fff; transition: all 0.2s;
    }
    .btn-success { background: #16a34a; } .btn-success:hover { background: #15803d; }
    .btn-secondary { background: #6b7280; } .btn-secondary:hover { background: #4b5563; }
    .btn-danger { background: #dc3545; } .btn-danger:hover { background: #b91c1c; }
    .form-actions {
      grid-column: 1 / 3;
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 15px;
    }
    /* Modal styles (reused pattern from updateUsers) */
    .modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); }
    .modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:30px; border-radius:12px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
    .modal-content h3 { margin-top:0; font-size:1.4rem; }
    .modal-actions { display:flex; justify-content:center; gap:12px; margin-top:20px; }
    .modal-actions button { padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; border:none; }
    .btn-secondary-modal { background:#e5e7eb; color:#374151; }
    .btn-secondary-modal:hover { background:#d1d5db; }
    .btn-success-modal { background:#10b981; color:#fff; }
    .btn-success-modal:hover { background:#059669; }
  </style>
</head>
<body>
  <div class="container">
    <h2><i class="fa-solid fa-pen-to-square"></i> Cập nhật sản phẩm</h2>

    <form action="products/updateProduct/process.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="old_sku" value="<?= $product['sku'] ?>">
      <input type="hidden" name="old_image" value="<?= $product['image'] ?? '' ?>">

      <div class="form-group">
        <label for="product_name">Tên sản phẩm</label>
        <input type="text" id="product_name" name="product_name" value="<?= $product['product_name'] ?? '' ?>" required>
      </div>

      <div class="form-group">
        <label for="barcode">Barcode</label>
        <input type="text" id="barcode" name="barcode" value="<?= $product['barcode'] ?? '' ?>">
      </div>

      <div class="form-group">
        <label for="category_id">Loại sản phẩm</label>
        <select id="category_id" name="category_id" required>
          <option value="">-- Chọn loại sản phẩm --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['category_id']; ?>"
              <?= ($product['category']['id'] ?? '') == $cat['category_id'] ? 'selected' : ''; ?>>
              <?= $cat['name'] ?? $cat['category_name'] ?? $cat['category_id']; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="supplier_id">Nhà cung cấp</label>
        <select id="supplier_id" name="supplier_id" required>
          <option value="">-- Chọn nhà cung cấp --</option>
          <?php foreach ($suppliers as $sup): ?>
            <option value="<?= $sup['supplier_id']; ?>"
              <?= ($product['supplier']['id'] ?? '') == $sup['supplier_id'] ? 'selected' : ''; ?>>
              <?= $sup['supplier_name'] ?? $sup['name'] ?? $sup['supplier_id']; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="model">Model</label>
        <input type="text" id="model" name="model" value="<?= is_string($product['model'] ?? '') ? $product['model'] : ($product['model']['code'] ?? '') ?>" placeholder="Nhập model sản phẩm (VD: XS-2024)">
      </div>

      <div class="form-group">
        <label for="base_unit">Đơn vị chính</label>
        <select id="base_unit" name="base_unit" required>
          <option value="">-- Chọn đơn vị --</option>
          <?php foreach ($unitOptions as $u): ?>
            <option value="<?= $u; ?>" <?= ($product['baseUnit'] ?? '') == $u ? 'selected' : ''; ?>>
              <?= ucfirst($u); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="min_stock">Tồn kho tối thiểu</label>
        <input type="number" id="min_stock" name="min_stock" min="0" value="<?= $product['min_stock'] ?? 0 ?>" required>
      </div>

      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="1" <?= ($product['status'] ?? 1) == 1 ? 'selected' : ''; ?>>Hoạt động</option>
          <option value="0" <?= ($product['status'] ?? 1) == 0 ? 'selected' : ''; ?>>Ngừng hoạt động</option>
        </select>
      </div>

      <div class="form-group">
        <label for="image">Hình ảnh sản phẩm</label>
        <input type="file" id="image" name="image" accept="image/*">
        <?php if (!empty($product['image'])): ?>
          <div style="margin-top:8px;">
            <span>Ảnh hiện tại:</span><br>
            <img src="../../../img/<?= $product['image'] ?>" alt="Ảnh sản phẩm"
                 style="max-width:120px;max-height:120px;border-radius:8px;">
          </div>
        <?php endif; ?>
      </div>

      <!-- Thông tin kích thước đơn vị chính -->
      <div class="conversion-box">
        <label><i class="fa-solid fa-box"></i> Kích thước & Trọng lượng đơn vị chính (<span id="baseUnitLabel" style="color: #3b82f6; font-weight: 700;"><?= $product['baseUnit'] ?? '-' ?></span>)</label>
        <p style="font-size: 13px; color: #dc2626; font-weight: 600; margin: 5px 0;">⚠️ Nhập kích thước của 1 đơn vị nhỏ nhất (VD: 1 cái iPhone = 8×2×15 cm)</p>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
          <div>
            <label>Chiều rộng (cm)</label>
            <input type="number" step="0.01" id="package_width" name="package_width" placeholder="VD: 8" value="<?= $product['package_dimensions']['width'] ?? 0 ?>">
          </div>
          <div>
            <label>Chiều sâu (cm)</label>
            <input type="number" step="0.01" id="package_depth" name="package_depth" placeholder="VD: 2" value="<?= $product['package_dimensions']['depth'] ?? 0 ?>">
          </div>
          <div>
            <label>Chiều cao (cm)</label>
            <input type="number" step="0.01" id="package_height" name="package_height" placeholder="VD: 15" value="<?= $product['package_dimensions']['height'] ?? 0 ?>">
          </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
          <div>
            <label>Trọng lượng (kg)</label>
            <input type="number" step="0.01" name="package_weight" placeholder="VD: 0.25" value="<?= $product['package_weight'] ?? 0 ?>">
          </div>
          <div>
            <label>Thể tích (cm³) - Tự động tính</label>
            <input type="number" step="0.01" id="volume_per_unit" name="volume_per_unit" value="<?= $product['volume_per_unit'] ?? 0 ?>" readonly style="background: #f0f0f0;">
          </div>
        </div>
      </div>

      <!-- Thông tin xếp chồng -->
      <div class="form-group">
        <label for="stackable">Có thể xếp chồng</label>
        <select id="stackable" name="stackable">
          <option value="1" <?= ($product['stackable'] ?? true) ? 'selected' : ''; ?>>Có</option>
          <option value="0" <?= !($product['stackable'] ?? true) ? 'selected' : ''; ?>>Không</option>
        </select>
      </div>

      <div class="form-group">
        <label for="max_stack_height">Chiều cao xếp chồng tối đa</label>
        <input type="number" id="max_stack_height" name="max_stack_height" min="0" value="<?= $product['max_stack_height'] ?? 0 ?>" placeholder="VD: 3">
        <p style="font-size: 13px; color: #555; margin-top: 4px;">Số tầng có thể xếp chồng lên nhau</p>
      </div>

      <!-- Quy đổi -->
      <div class="conversion-box">
        <label><i class="fa-solid fa-arrows-rotate"></i> Đơn vị quy đổi</label>
        <p style="font-size: 13px; color: #dc2626; font-weight: 600; margin: 5px 0;">⚠️ Kích thước của ĐƠN VỊ LỚN HƠN (VD: 1 thùng = 15 cái → nhập kích thước THÙNG)</p>
        <div id="conversion-container">
          <?php
          $conversions = $product['conversionUnits'] ?? [];
          if (empty($conversions)) {
            echo '<div class="conversion-item" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;">
                    <div><label style="font-size: 13px; font-weight: 600;">Đơn vị</label>
                    <select name="conversion_unit[]" class="conversion-unit-select">
                      <option value="">-- Đơn vị --</option>';
            foreach ($unitOptions as $u) echo "<option value='$u'>" . ucfirst($u) . "</option>";
            echo '</select></div>
                  <div><label style="font-size: 13px; font-weight: 600;">Hệ số quy đổi</label>
                  <input type="number" step="0.01" name="conversion_factor[]" class="conversion-factor" placeholder="VD: 15"></div>
                  <div style="grid-column: 1 / 3;"><label style="font-size: 13px; font-weight: 600;">Kích thước (<span class="unit-name-display">-</span>)</label>
                  <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <input type="number" step="0.01" name="conversion_width[]" placeholder="Rộng (cm)" class="conversion-width">
                    <input type="number" step="0.01" name="conversion_depth[]" placeholder="Sâu (cm)" class="conversion-depth">
                    <input type="number" step="0.01" name="conversion_height[]" placeholder="Cao (cm)" class="conversion-height">
                  </div></div>
                  <div><label style="font-size: 13px; font-weight: 600;">Trọng lượng (kg)</label>
                  <input type="number" step="0.01" name="conversion_weight[]" placeholder="VD: 5"></div>
                  <div><label style="font-size: 13px; font-weight: 600;">Thể tích (cm³)</label>
                  <input type="number" step="0.01" name="conversion_volume[]" class="conversion-volume" readonly style="background: #f0f0f0;" placeholder="Tự động"></div>
                 </div>';
          } else {
            foreach ($conversions as $conv) {
              $dims = $conv['dimensions'] ?? [];
              $width = $dims['width'] ?? 0;
              $depth = $dims['depth'] ?? 0;
              $height = $dims['height'] ?? 0;
              $weight = $conv['weight'] ?? 0;
              $volume = $conv['volume'] ?? 0;
              $unit = $conv['unit'] ?? '';
              
              echo "<div class='conversion-item' style='display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; position: relative;'>
                      <div><label style='font-size: 13px; font-weight: 600;'>Đơn vị</label>
                      <select name='conversion_unit[]' class='conversion-unit-select'>";
              foreach ($unitOptions as $u) {
                $selected = ($conv['unit'] ?? '') == $u ? 'selected' : '';
                echo "<option value='$u' $selected>" . ucfirst($u) . "</option>";
              }
              echo "</select></div>
                    <div><label style='font-size: 13px; font-weight: 600;'>Hệ số quy đổi</label>
                    <input type='number' step='0.01' name='conversion_factor[]' class='conversion-factor'
                      value='" . ($conv['factor'] ?? '') . "' placeholder='VD: 15'></div>
                    <div style='grid-column: 1 / 3;'><label style='font-size: 13px; font-weight: 600;'>Kích thước của 1 <span class='unit-name-display' style='color: #3b82f6;'>{$unit}</span></label>
                    <div style='display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;'>
                      <input type='number' step='0.01' name='conversion_width[]' value='{$width}' placeholder='Rộng (cm)' class='conversion-width'>
                      <input type='number' step='0.01' name='conversion_depth[]' value='{$depth}' placeholder='Sâu (cm)' class='conversion-depth'>
                      <input type='number' step='0.01' name='conversion_height[]' value='{$height}' placeholder='Cao (cm)' class='conversion-height'>
                    </div></div>
                    <div><label style='font-size: 13px; font-weight: 600;'>Trọng lượng (kg)</label>
                    <input type='number' step='0.01' name='conversion_weight[]' value='{$weight}' placeholder='VD: 5'></div>
                    <div><label style='font-size: 13px; font-weight: 600;'>Thể tích (cm³)</label>
                    <input type='number' step='0.01' name='conversion_volume[]' value='{$volume}' class='conversion-volume' readonly style='background: #f0f0f0;' placeholder='Tự động'></div>
                    <button type='button' class='btn btn-danger removeConversion' style='position: absolute; top: 5px; right: 5px; padding: 4px 8px;'><i class='fa-solid fa-xmark'></i></button>
                    </div>";
            }
          }
          ?>
        </div>
        <button type="button" id="addConversionBtn" class="btn btn-secondary" style="margin-top:8px;">
          <i class="fa-solid fa-plus"></i> Thêm quy đổi
        </button>
      </div>

      <div class="form-group full-width">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="3"><?= $product['description'] ?? '' ?></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" name="btnUpdate" class="btn btn-success"><i class="fas fa-save"></i> Cập nhật</button>
        <a href="index.php?page=products" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
      </div>
    </form>
  </div>

    <!-- Modal xác nhận cập nhật sản phẩm -->
    <div id="confirmModal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="confirmModalTitle">
      <div class="modal-content">
        <h3 id="confirmModalTitle">Xác nhận cập nhật</h3>
        <p>Bạn có chắc chắn muốn cập nhật thông tin sản phẩm này không?</p>
        <div class="modal-actions">
          <button type="button" id="cancelModalBtn" class="btn-secondary-modal">Hủy</button>
          <button type="button" id="confirmUpdateBtn" class="btn-success-modal">Xác nhận</button>
        </div>
      </div>
    </div>

  <script>
    const addBtn = document.getElementById('addConversionBtn');
    const container = document.getElementById('conversion-container');
    const baseUnitSelect = document.getElementById('base_unit');
    const baseUnitLabel = document.getElementById('baseUnitLabel');
    const form = document.querySelector('form');
    const confirmModal = document.getElementById('confirmModal');
    const confirmBtn = document.getElementById('confirmUpdateBtn');
    const cancelBtn = document.getElementById('cancelModalBtn');
    const submitBtn = document.querySelector("button[name='btnUpdate']");
    let isConfirmed = false;

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

    document.addEventListener('click', e => {
      if (e.target.closest('.removeConversion')) {
        e.target.closest('.conversion-item').remove();
      }
    });

    // Modal logic giống trang user update
    form.addEventListener('submit', function(e) {
      if (!isConfirmed) {
        e.preventDefault();
        confirmModal.style.display = 'block';
        confirmModal.setAttribute('aria-hidden','false');
      }
    });

    confirmBtn.addEventListener('click', () => {
      isConfirmed = true;
      confirmModal.style.display = 'none';
      confirmModal.setAttribute('aria-hidden','true');
      submitBtn.click();
    });

    cancelBtn.addEventListener('click', () => {
      isConfirmed = false;
      confirmModal.style.display = 'none';
      confirmModal.setAttribute('aria-hidden','true');
    });

    window.addEventListener('click', (ev) => {
      if (ev.target === confirmModal) {
        isConfirmed = false;
        confirmModal.style.display = 'none';
        confirmModal.setAttribute('aria-hidden','true');
      }
    });

    window.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && confirmModal.style.display === 'block') {
        isConfirmed = false;
        confirmModal.style.display = 'none';
        confirmModal.setAttribute('aria-hidden','true');
      }
    });
  </script>
</body>
</html>
