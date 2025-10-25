<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once(__DIR__ . "/../../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

$created_by = $_SESSION['user_id'] ?? ($_SESSION['login']['user_id'] ?? 'system');
$warehouse_id = $_SESSION['warehouse_id'] ?? ($_SESSION['login']['warehouse_id'] ?? 'WH01');

// ⭐ Xác định xem có phải kho chi nhánh không
$isWarehouseMain = ($warehouse_id === 'KHO_TONG_01' || strpos($warehouse_id, 'TONG') !== false);

// Lấy danh sách sản phẩm dưới min_stock trong kho (ưu tiên hàng đầu)
$productController = new CProduct();
$productsBelowMin = $productController->getProductsBelowMinStock($warehouse_id);

// ⭐ Lấy TẤT CẢ sản phẩm trong HỆ THỐNG (không chỉ có trong kho)
$allProducts = $productController->getAllProducts();

// Lấy thông tin tồn kho từ inventory của kho hiện tại
include_once(__DIR__ . "/../../../../../controller/cInventory.php");
$inventoryController = new CInventory();
$stockByProduct = [];
try {
    $stockData = $inventoryController->getProductsStockByWarehouse($warehouse_id);
    if (is_array($stockData)) {
        $stockByProduct = $stockData;
    }
} catch (Exception $e) {
    error_log("Error getting stock: " . $e->getMessage());
}

// Gắn thông tin tồn kho vào sản phẩm và phân loại
$productsBelowMinIds = array_map(function($p) {
    if (is_array($p['_id']) && isset($p['_id']['$oid'])) {
        return $p['_id']['$oid'];
    }
    return (string)$p['_id'];
}, $productsBelowMin);

$productsNormal = [];
foreach ($allProducts as $p) {
    $productId = '';
    if (is_array($p['_id']) && isset($p['_id']['$oid'])) {
        $productId = $p['_id']['$oid'];
    } else {
        $productId = (string)$p['_id'];
    }
    
    // Bỏ qua sản phẩm đã có trong danh sách cần nhập hàng
    if (in_array($productId, $productsBelowMinIds)) {
        continue;
    }
    
    // Gắn thông tin tồn kho hiện tại
    $p['current_stock'] = isset($stockByProduct[$productId]) ? (int)$stockByProduct[$productId] : 0;
    $productsNormal[] = $p;
}

// Sort sản phẩm normal theo tên
usort($productsNormal, function($a, $b) {
    return strcasecmp($a['product_name'] ?? '', $b['product_name'] ?? '');
});

// Lấy danh sách kho
$warehouseController = new CWarehouse();
$warehouses = $warehouseController->getAllWarehouses() ?? [];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Tạo phiếu yêu cầu nhập hàng</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {font-family: "Segoe UI", Tahoma, sans-serif;background:#f3f6fa;margin:0;padding:20px;}
    .form-container {max-width:1200px;margin:auto;background:#fff;padding:25px 30px;
      border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}
    h2 {text-align:center;margin-bottom:25px;color:#333;font-weight:600;}
    .alert {padding:12px;margin-bottom:15px;border-radius:8px;font-size:14px;}
    .alert-info {background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb;}
    .alert-warning {background:#fff3cd;color:#856404;border:1px solid #ffeaa7;}
    label {font-weight:600;margin-top:12px;display:block;color:#444;}
    select,input[type="text"],input[type="number"],textarea {
      width:100%;padding:10px;margin:6px 0 14px;border:1px solid #d0d7de;border-radius:8px;font-size:14px;
    }
    select:focus,input:focus,textarea:focus{border-color:#007bff;box-shadow:0 0 0 3px rgba(0,123,255,0.15);outline:none;}
    table {width:100%;border-collapse:collapse;margin-top:15px;border-radius:8px;overflow:hidden;}
    th,td {border:1px solid #e1e4e8;padding:10px;text-align:center;font-size:14px;}
    th {background:#f9fafb;font-weight:600;}
    .btn {background:#007bff;color:#fff;padding:8px 14px;border:none;border-radius:6px;cursor:pointer;font-size:14px;}
    .btn:hover {background:#0056b3;}
    .btn-success {background:#28a745;}
    .btn-success:hover {background:#1e7e34;}
    .action-buttons {margin-top:20px;display:flex;justify-content:flex-end;gap:10px;}
    .shortage {color:#dc3545;font-weight:700;}
    .stock-info {font-size:12px;color:#666;}
    .priority-box {display:flex;gap:15px;align-items:center;margin:15px 0;}
    .priority-box label {display:inline-flex;align-items:center;gap:5px;margin:0;font-weight:normal;cursor:pointer;}
    .priority-box input[type="radio"] {margin:0;}
    .urgent-label {color:#dc3545;font-weight:700;}
    .product-row {transition:background 0.2s;}
    .product-row:hover {background:#f8f9fa;}
    h3 i {margin-right:8px;}
    #searchProduct {transition:border-color 0.2s;}
    #searchProduct:focus {border-color:#007bff;outline:none;box-shadow:0 0 0 3px rgba(0,123,255,0.15);}
    select {cursor:pointer;transition:border-color 0.2s;}
    select:focus {border-color:#007bff;outline:none;box-shadow:0 0 0 3px rgba(0,123,255,0.15);}
    .unit-info {font-size:11px;color:#666;margin-top:3px;}
  </style>
</head>
<body>
  <div class="form-container">
    <h2><i class="fa-solid fa-file-circle-plus"></i> Tạo phiếu yêu cầu nhập hàng</h2>

    <?php if (count($productsBelowMin) > 0): ?>
      <div class="alert alert-warning">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> Cảnh báo:</strong> 
        Có <strong><?= count($productsBelowMin) ?></strong> sản phẩm dưới mức tồn kho tối thiểu!
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        <i class="fa-solid fa-circle-check"></i> Tất cả sản phẩm đều đủ tồn kho.
      </div>
    <?php endif; ?>

    <div class="alert alert-info" style="background:#e7f3ff;border-color:#b3d9ff;">
      <strong><i class="fa-solid fa-info-circle"></i> Hướng dẫn:</strong>
      <ul style="margin:8px 0 0 20px;padding:0;">
        <li>Bạn có thể chọn <strong>bất kỳ sản phẩm nào</strong> trong hệ thống để yêu cầu nhập hàng</li>
        <li>Chọn <strong>đơn vị</strong> phù hợp (cái, thùng, v.v.) - hệ thống sẽ tự động quy đổi</li>
        <li>Ví dụ: <em>1 thùng = 15 cái</em> → Nhập <strong>10 thùng</strong> = yêu cầu <strong>150 cái</strong></li>
      </ul>
    </div>

    <form method="post" action="goodsReceiptRequest/createReceipt/process.php">
      <input type="hidden" name="warehouse_id" value="<?= htmlspecialchars($warehouse_id) ?>">
      <input type="hidden" name="created_by" value="<?= htmlspecialchars($created_by) ?>">
      <input type="hidden" name="type" value="transfer">

      <label>Kho nguồn <?= !$isWarehouseMain ? '(Chỉ chọn được Kho Tổng)' : '' ?></label>
      <select name="source_warehouse_id" required <?= !$isWarehouseMain ? 'disabled' : '' ?>>
        <option value="KHO_TONG_01" selected>Kho Tổng</option>
        <?php if ($isWarehouseMain): ?>
          <?php foreach ($warehouses as $w): ?>
            <?php if ($w['warehouse_id'] !== $warehouse_id && $w['warehouse_id'] !== 'KHO_TONG_01'): ?>
              <option value="<?= $w['warehouse_id'] ?>"><?= $w['warehouse_name'] ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
      <?php if (!$isWarehouseMain): ?>
        <!-- Hidden input để gửi giá trị khi select bị disabled -->
        <input type="hidden" name="source_warehouse_id" value="KHO_TONG_01">
      <?php endif; ?>

      <div class="priority-box">
        <label><strong>Mức độ ưu tiên:</strong></label>
        <label>
          <input type="radio" name="priority" value="normal" checked> 
          <span>🟢 Bình thường</span>
        </label>
        <label class="urgent-label">
          <input type="radio" name="priority" value="urgent"> 
          <span>🔴 KHẨN CẤP</span>
        </label>
      </div>

      <label>Ghi chú</label>
      <textarea name="note" rows="3" placeholder="Ghi chú về yêu cầu nhập hàng..."></textarea>

      <!-- ===== PHẦN 1: SẢN PHẨM CẦN NHẬP HÀNG (DƯỚi MIN_STOCK) ===== -->
      <?php if (count($productsBelowMin) > 0): ?>
        <h3 style="margin-top:25px; color:#dc3545;">
          <i class="fa-solid fa-triangle-exclamation"></i> Sản phẩm cần nhập hàng (<?= count($productsBelowMin) ?>)
        </h3>
        <table>
          <thead>
            <tr>
              <th style="width:50px;">
                <input type="checkbox" id="selectAllUrgent" onchange="toggleAllSection(this, 'urgent')">
              </th>
              <th>Mã SP</th>
              <th>Tên sản phẩm</th>
              <th>Tồn hiện tại</th>
              <th>Tồn tối thiểu</th>
              <th>Thiếu hụt</th>
              <th>Đơn vị</th>
              <th>Số lượng yêu cầu</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productsBelowMin as $index => $p): 
              $productId = '';
              if (isset($p['_id'])) {
                  if (is_array($p['_id']) && isset($p['_id']['$oid'])) {
                      $productId = $p['_id']['$oid'];
                  } else {
                      $productId = (string)$p['_id'];
                  }
              }
              
              $currentStock = (int)($p['current_stock'] ?? 0);
              $minStock = (int)($p['min_stock'] ?? 0);
              $shortage = (int)($p['shortage'] ?? 0);
              $suggestedQty = $shortage > 0 ? $shortage : 10;
              
              // Lấy thông tin đơn vị chuyển đổi
              $baseUnit = $p['baseUnit'] ?? 'cái';
              $conversionUnits = $p['conversionUnits'] ?? [];
            ?>
            <tr style="background:#fff3cd;">
              <td>
                <input type="checkbox" name="selected_products[]" value="urgent_<?= $index ?>" 
                       class="cb-urgent" onchange="toggleRow(this, 'urgent_<?= $index ?>')">
              </td>
              <td><?= htmlspecialchars($p['sku'] ?? 'N/A') ?></td>
              <td><strong><?= htmlspecialchars($p['product_name'] ?? 'N/A') ?></strong></td>
              <td><?= $currentStock ?></td>
              <td><?= $minStock ?></td>
              <td class="shortage"><?= $shortage ?></td>
              <td>
                <select name="products[urgent_<?= $index ?>][unit]" 
                        id="unit_urgent_<?= $index ?>"
                        onchange="updateQuantityByUnit(this, 'urgent_<?= $index ?>', <?= $suggestedQty ?>)"
                        style="padding:5px;border-radius:4px;border:1px solid #ccc;" disabled>
                  <option value="<?= htmlspecialchars($baseUnit) ?>" data-factor="1" selected>
                    <?= htmlspecialchars($baseUnit) ?> (x1)
                  </option>
                  <?php if (is_array($conversionUnits)): ?>
                    <?php foreach ($conversionUnits as $cu): ?>
                      <?php if (isset($cu['unit']) && isset($cu['factor'])): ?>
                        <option value="<?= htmlspecialchars($cu['unit']) ?>" data-factor="<?= (int)$cu['factor'] ?>">
                          <?= htmlspecialchars($cu['unit']) ?> (x<?= (int)$cu['factor'] ?>)
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </td>
              <td>
                <input type="number" 
                       name="products[urgent_<?= $index ?>][quantity]" 
                       id="qty_urgent_<?= $index ?>"
                       value="<?= $suggestedQty ?>" 
                       min="1" 
                       disabled
                       style="width:100px;padding:5px;">
                <input type="hidden" name="products[urgent_<?= $index ?>][product_id]" value="<?= $productId ?>">
                <input type="hidden" name="products[urgent_<?= $index ?>][product_name]" value="<?= htmlspecialchars($p['product_name'] ?? '') ?>">
                <input type="hidden" name="products[urgent_<?= $index ?>][sku]" value="<?= htmlspecialchars($p['sku'] ?? '') ?>">
                <input type="hidden" name="products[urgent_<?= $index ?>][base_unit]" value="<?= htmlspecialchars($baseUnit) ?>">
                <input type="hidden" name="products[urgent_<?= $index ?>][current_stock]" value="<?= $currentStock ?>">
                <input type="hidden" name="products[urgent_<?= $index ?>][min_stock]" value="<?= $minStock ?>">
                <input type="hidden" name="products[urgent_<?= $index ?>][conversion_factor]" id="factor_urgent_<?= $index ?>" value="1">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <!-- ===== PHẦN 2: TẤT CẢ SẢN PHẨM TRONG HỆ THỐNG ===== -->
      <?php if (count($productsNormal) > 0): ?>
        <h3 style="margin-top:30px; color:#333;">
          <i class="fa-solid fa-box"></i> Tất cả sản phẩm trong hệ thống (<?= count($productsNormal) ?>)
        </h3>
        <div style="margin-bottom:10px;">
          <input type="text" id="searchProduct" placeholder="🔍 Tìm kiếm sản phẩm..." 
                 style="width:300px;padding:8px;border-radius:6px;border:1px solid #ccc;"
                 onkeyup="filterProducts()">
          <span style="margin-left:15px;color:#666;font-size:13px;">
            <i class="fa-solid fa-info-circle"></i> Bao gồm cả sản phẩm chưa có trong kho
          </span>
        </div>
        <table id="normalProductsTable">
          <thead>
            <tr>
              <th style="width:50px;">
                <input type="checkbox" id="selectAllNormal" onchange="toggleAllSection(this, 'normal')">
              </th>
              <th>Mã SP</th>
              <th>Tên sản phẩm</th>
              <th>Tồn hiện tại</th>
              <th>Đơn vị</th>
              <th>Số lượng yêu cầu</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $normalIndex = 0;
            foreach ($productsNormal as $p): 
              $productId = '';
              if (isset($p['_id'])) {
                  if (is_array($p['_id']) && isset($p['_id']['$oid'])) {
                      $productId = $p['_id']['$oid'];
                  } else {
                      $productId = (string)$p['_id'];
                  }
              }
              
              $currentStock = (int)($p['current_stock'] ?? 0);
              $baseUnit = $p['baseUnit'] ?? 'cái';
              $conversionUnits = $p['conversionUnits'] ?? [];
              $normalIndex++;
            ?>
            <tr class="product-row" data-name="<?= strtolower($p['product_name'] ?? '') ?>" data-sku="<?= strtolower($p['sku'] ?? '') ?>">
              <td>
                <input type="checkbox" name="selected_products[]" value="normal_<?= $normalIndex ?>" 
                       class="cb-normal" onchange="toggleRow(this, 'normal_<?= $normalIndex ?>')">
              </td>
              <td><?= htmlspecialchars($p['sku'] ?? 'N/A') ?></td>
              <td><?= htmlspecialchars($p['product_name'] ?? 'N/A') ?></td>
              <td>
                <?php if ($currentStock > 0): ?>
                  <span style="color:#28a745;font-weight:600;"><?= $currentStock ?></span>
                <?php else: ?>
                  <span style="color:#dc3545;font-weight:600;">Chưa có</span>
                <?php endif; ?>
              </td>
              <td>
                <select name="products[normal_<?= $normalIndex ?>][unit]" 
                        id="unit_normal_<?= $normalIndex ?>"
                        onchange="updateQuantityByUnit(this, 'normal_<?= $normalIndex ?>', 10)"
                        style="padding:5px;border-radius:4px;border:1px solid #ccc;" disabled>
                  <option value="<?= htmlspecialchars($baseUnit) ?>" data-factor="1" selected>
                    <?= htmlspecialchars($baseUnit) ?> (x1)
                  </option>
                  <?php if (is_array($conversionUnits)): ?>
                    <?php foreach ($conversionUnits as $cu): ?>
                      <?php if (isset($cu['unit']) && isset($cu['factor'])): ?>
                        <option value="<?= htmlspecialchars($cu['unit']) ?>" data-factor="<?= (int)$cu['factor'] ?>">
                          <?= htmlspecialchars($cu['unit']) ?> (x<?= (int)$cu['factor'] ?>)
                        </option>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </td>
              <td>
                <input type="number" 
                       name="products[normal_<?= $normalIndex ?>][quantity]" 
                       id="qty_normal_<?= $normalIndex ?>"
                       value="10" 
                       min="1" 
                       disabled
                       style="width:100px;padding:5px;">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][product_id]" value="<?= $productId ?>">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][product_name]" value="<?= htmlspecialchars($p['product_name'] ?? '') ?>">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][sku]" value="<?= htmlspecialchars($p['sku'] ?? '') ?>">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][base_unit]" value="<?= htmlspecialchars($baseUnit) ?>">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][current_stock]" value="<?= $currentStock ?>">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][min_stock]" value="<?= $p['min_stock'] ?? 0 ?>">
                <input type="hidden" name="products[normal_<?= $normalIndex ?>][conversion_factor]" id="factor_normal_<?= $normalIndex ?>" value="1">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="text-align:center;color:#666;padding:20px;">Không có sản phẩm nào trong hệ thống.</p>
      <?php endif; ?>

      <div class="action-buttons">
        <a href="index.php?page=goodsReceiptRequest" class="btn" style="background:#6c757d;">
          <i class="fa-solid fa-arrow-left"></i> Quay lại
        </a>
        <button type="submit" class="btn btn-success">
          <i class="fa-solid fa-paper-plane"></i> Gửi yêu cầu
        </button>
      </div>
    </form>
  </div>

  <script>
    // Toggle tất cả checkbox trong một section
    function toggleAllSection(checkbox, section) {
      const className = section === 'urgent' ? '.cb-urgent' : '.cb-normal';
      const checkboxes = document.querySelectorAll(className);
      checkboxes.forEach((cb) => {
        cb.checked = checkbox.checked;
        const value = cb.value;
        toggleRow(cb, value);
      });
    }

    // Toggle enable/disable input số lượng VÀ select đơn vị
    function toggleRow(checkbox, rowId) {
      const qtyInput = document.getElementById('qty_' + rowId);
      const unitSelect = document.getElementById('unit_' + rowId);
      
      if (qtyInput) {
        qtyInput.disabled = !checkbox.checked;
        if (!checkbox.checked) {
          qtyInput.value = rowId.startsWith('urgent_') ? 
            (qtyInput.getAttribute('data-shortage') || 10) : 10;
        }
      }
      
      if (unitSelect) {
        unitSelect.disabled = !checkbox.checked;
        if (!checkbox.checked) {
          unitSelect.selectedIndex = 0; // Reset về baseUnit
          // Reset factor
          const factorInput = document.getElementById('factor_' + rowId);
          if (factorInput) factorInput.value = 1;
        }
      }
    }

    // Cập nhật số lượng khi thay đổi đơn vị (quy đổi tự động)
    function updateQuantityByUnit(selectElement, rowId, baseQty) {
      const selectedOption = selectElement.options[selectElement.selectedIndex];
      const factor = parseInt(selectedOption.getAttribute('data-factor')) || 1;
      const qtyInput = document.getElementById('qty_' + rowId);
      const factorInput = document.getElementById('factor_' + rowId);
      
      if (qtyInput && factorInput) {
        // Lưu factor để xử lý khi submit
        factorInput.value = factor;
        
        // Tự động điều chỉnh số lượng (ví dụ: 150 cái = 10 thùng nếu factor=15)
        if (factor > 1) {
          const convertedQty = Math.ceil(baseQty / factor);
          qtyInput.value = convertedQty;
          qtyInput.setAttribute('data-original-qty', baseQty);
        } else {
          const originalQty = qtyInput.getAttribute('data-original-qty') || baseQty;
          qtyInput.value = originalQty;
        }
      }
    }

    // Tìm kiếm sản phẩm
    function filterProducts() {
      const searchValue = document.getElementById('searchProduct').value.toLowerCase();
      const rows = document.querySelectorAll('.product-row');
      
      rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const sku = row.getAttribute('data-sku') || '';
        
        if (name.includes(searchValue) || sku.includes(searchValue)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }
  </script>
</body>
</html>
