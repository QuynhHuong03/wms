<?php
include_once(__DIR__ . '/../../../../../controller/cCategories.php');
include_once(__DIR__ . '/../../../../../controller/cSupplier.php');
include_once(__DIR__ . '/../../../../../controller/cModel.php');

$categories = (new CCategories())->getAllCategories();
$suppliers = (new CSupplier())->getAllSuppliers();
$models = (new CModel())->getAllModels();

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
  </style>
</head>
<body>
  <div class="container">
    <h2><i class="fa-solid fa-boxes-stacked"></i> Thêm sản phẩm mới</h2>

    <form action="products/createProduct/process.php" method="post" enctype="multipart/form-data">

      <div class="form-group">
        <label for="image">Hình ảnh sản phẩm</label>
        <input type="file" id="image" name="image" accept="image/*">
      </div>

      <div class="form-group">
        <label for="barcode">Barcode (có thể để trống)</label>
        <input type="text" id="barcode" name="barcode" placeholder="Barcode...">
      </div>

      <div class="form-group">
        <label for="product_name">Tên sản phẩm</label>
        <input type="text" id="product_name" name="product_name" required>
      </div>

      <div class="form-group">
        <label for="model_id">Model</label>
        <select id="model_id" name="model_id">
          <option value="">-- Chọn model --</option>
          <?php foreach ($models as $m): ?>
            <option value="<?= $m['model_id']; ?>">
              <?= $m['model_name'] ?? $m['model_id']; ?>
            </option>
          <?php endforeach; ?>
        </select>
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
      </div>

      <div class="form-group">
        <label for="purchase_price">Giá nhập (theo đơn vị chính)</label>
        <input type="number" id="purchase_price" name="purchase_price" min="0" value="0" required>
      </div>

      <div class="form-group">
        <label for="min_stock">Tồn kho tối thiểu</label>
        <input type="number" id="min_stock" name="min_stock" min="0" value="0" required>
      </div>

      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="1">Hoạt động</option>
          <option value="0">Ngừng hoạt động</option>
        </select>
      </div>

      <!-- Đơn vị quy đổi -->
      <div class="conversion-box">
        <label><i class="fa-solid fa-arrows-rotate"></i> Đơn vị quy đổi</label>
        <p class="hint">Ví dụ: 1 thùng = 10 hộp, 1 hộp = 10 cái</p>
        <div id="conversion-container">
          <div class="conversion-item">
            <select name="conversion_unit[]">
              <option value="">-- Đơn vị --</option>
              <?php foreach ($unitOptions as $u): ?>
                <option value="<?= $u; ?>"><?= ucfirst($u); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" name="conversion_factor[]" placeholder="Hệ số (vd: 10)">
          </div>
        </div>
        <button type="button" id="addConversionBtn" class="btn btn-secondary" style="margin-top:8px;">
          <i class="fa-solid fa-plus"></i> Thêm quy đổi
        </button>
      </div>

      <div class="form-group full-width">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="3"></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" name="btnAdd" class="btn btn-success">
          <i class="fas fa-plus"></i> Thêm sản phẩm
        </button>
        <a href="index.php?page=products" class="btn btn-secondary">
          <i class="fas fa-arrow-left"></i> Quay lại
        </a>
      </div>
    </form>
  </div>

  <script>
    const addBtn = document.getElementById('addConversionBtn');
    const container = document.getElementById('conversion-container');

    addBtn.addEventListener('click', () => {
      const div = document.createElement('div');
      div.className = 'conversion-item';
      div.innerHTML = `
        <select name="conversion_unit[]">
          <option value="">-- Đơn vị --</option>
          <?php foreach ($unitOptions as $u): ?>
            <option value="<?= $u; ?>"><?= ucfirst($u); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="number" step="0.01" name="conversion_factor[]" placeholder="Hệ số (vd: 10)">
        <button type="button" class="btn btn-danger removeConversion"><i class="fa-solid fa-xmark"></i></button>
      `;
      container.appendChild(div);
    });

    document.addEventListener('click', e => {
      if (e.target.closest('.removeConversion')) {
        e.target.closest('.conversion-item').remove();
      }
    });
  </script>
</body>
</html>
