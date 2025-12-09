<?php
include_once(__DIR__ . "/../../../../controller/cProduct.php");
include_once(__DIR__ . "/../../../../controller/cSupplier.php");
require_once(__DIR__ . '/../../../../vendor/picqer/php-barcode-generator/src/BarcodeGeneratorPNG.php');

$cProduct = new CProduct();
$cSupplier = new CSupplier();

$products = $cProduct->getAllProducts();
$suppliers = $cSupplier->getAllSuppliers();

$barcodeGenerator = new Picqer\Barcode\BarcodeGeneratorPNG();
?>
<?php
// Server-side toast fallback: render a toast immediately if msg is present
$msg = $_GET['msg'] ?? '';
if (in_array($msg, ['success','updated','deleted','error'])) {
  $class = $msg === 'error' ? 'toast-notification error' : 'toast-notification';
  if ($msg === 'success') $text = '<i class="fa-solid fa-circle-check"></i> Thêm sản phẩm thành công!';
  elseif ($msg === 'updated') $text = '<i class="fa-solid fa-circle-check"></i> Cập nhật sản phẩm thành công!';
  elseif ($msg === 'deleted') $text = '<i class="fa-solid fa-trash-can"></i> Xóa sản phẩm thành công!';
  else $text = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
  echo "<div id=\"serverToast\" class=\"{$class}\">{$text}</div>";
  echo "<script>setTimeout(()=>{const t=document.getElementById('serverToast'); if(t){t.classList.add('hide'); setTimeout(()=>t.remove(),300);} const newUrl=window.location.pathname+'?page=products'; window.history.replaceState({},'',newUrl);},3000);</script>";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f4f7fa;
    color: #333;
}

.category-list-container {
    max-width: 1200px;
    margin: 0 auto;
    background: #ffffff;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.05);
}

.category-list-container h2 {
    text-align: left;
    margin-bottom: 0;
    font-size: 1.6rem;
    color: #1f2937;
    font-weight: 700;
}

.top-actions {
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filters input,
.filters select {
    padding: 10px 12px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 0.95rem;
    background-color: #f9fafb;
}
.filters input:focus, .filters select:focus { outline: none; box-shadow: 0 0 0 2px rgba(37,99,235,0.08); border-color:#2563eb; }

/* Thumbnails and barcode sizing for products */
.product-image { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; }
.barcode-box { display: flex; flex-direction: column; align-items: center; }
.barcode-box img { height: 32px; }
.barcode-text { font-size: 12px; font-family: monospace; color: #222; background: #f4f6f8; padding: 2px 6px; border-radius: 4px; margin-top: 4px; }

.btn-create { background: #2563eb; color: #fff; padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:600; }
.btn-create:hover { background:#1e40af }

.category-list-container table { width:100%; border-collapse:separate; border-spacing:0; margin-top:12px; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden }
.category-list-container th, .category-list-container td { padding:12px 14px; border-bottom:1px solid #e5e7eb; text-align:left; font-size:0.95rem }
.category-list-container th { background:#f9fafb; color:#4b5563; font-weight:600; text-transform:uppercase; font-size:0.8rem }
.category-list-container td:last-child { text-align:center }
.category-list-container tbody tr:hover { background:#f7faff }

.status { font-weight:600; padding:6px 10px; border-radius:18px; display:inline-block; font-size:0.85rem }
.active { background:#d1fae5; color:#065f46 }
.inactive { background:#fee2e2; color:#991b1b }

.btn { border:none; padding:8px 8px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center }
.btn-edit { background:#3b82f6; color:#fff }
.btn-view { background:#10b981; color:#fff }
.btn-delete { background:#ef4444; color:#fff }
.btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.08) }

/* Action buttons container */
.category-list-container td:last-child {
  display: flex;
  gap: 6px;
  justify-content: center;
  align-items: center;
}

/* Smaller action icons inside tables */
.category-list-container td a.btn {
  padding: 4px;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  box-sizing: border-box;
}
.category-list-container td a.btn i { font-size: 14px; line-height: 1 }

.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.4) }
.modal-content { background:#fff; max-width:400px; margin:15vh auto; padding:26px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.25); text-align:center }
.modal-content h3 { color:#1f2937; margin-bottom:12px }
.modal-content p { color:#6b7280; margin-bottom:18px }
#cancelBtn, #confirmDeleteBtn { padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer; border:none }
#cancelBtn { background:#e5e7eb; color:#374151 }
#confirmDeleteBtn { background:#ef4444; color:#fff }

.toast-notification { position:fixed; top:20px; right:20px; background:#10b981; color:#fff; padding:14px 20px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.12); display:flex; gap:10px; align-items:center; font-weight:600; z-index:10000; animation:slideIn 0.28s }
.toast-notification.error { background:#ef4444 }
.toast-notification.hide { animation:slideOut 0.3s forwards }
@keyframes slideIn { from { transform:translateX(400px); opacity:0 } to { transform:translateX(0); opacity:1 } }
@keyframes slideOut { from { transform:translateX(0); opacity:1 } to { transform:translateX(400px); opacity:0 } }

@media (max-width:768px) { .category-list-container { padding:10px } .top-actions { flex-direction:column; align-items:stretch } .filters { flex-wrap:wrap } .filters input, .filters select { flex-grow:1; min-width:45% } .btn-create { width:100% } .category-list-container table { min-width:720px } }

/* Keep product-specific detail modal styles (preserve existing product modal look) */
#detailModal .modal-content { max-width: 900px; width: 95%; margin: 40px auto; padding: 0; border-radius: 16px; background: #f8f9fa; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); border: none; }
.modal-detail-header { background: linear-gradient(135deg, #007bff, #0056b3); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; color: #fff; }
.modal-detail-header h3 { margin: 0; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; border: none; color: #fff; padding: 0; }
.btn-close-icon { background: rgba(255,255,255,0.2); border: none; color: #fff; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
.btn-close-icon:hover { background: rgba(255,255,255,0.4); }
.modal-detail-body { display: flex; flex-wrap: wrap; padding: 30px; gap: 30px; }
.detail-sidebar { flex: 0 0 300px; display: flex; flex-direction: column; align-items: center; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); text-align: center; }
.detail-image-box { width: 100%; height: 180px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; background: #f9fafb; border-radius: 8px; border: 1px dashed #dee2e6; }
.detail-image-box img { max-width: 100%; max-height: 160px; object-fit: contain; border-radius: 6px; }
.detail-sku-tag { background: #e9ecef; color: #495057; padding: 5px 12px; border-radius: 20px; font-family: monospace; font-weight: bold; font-size: 14px; margin-bottom: 10px; display: inline-block; }
.detail-main { flex: 1; display: flex; flex-direction: column; gap: 20px; }
.info-section { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
.section-title { font-size: 15px; font-weight: 700; color: #007bff; text-transform: uppercase; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid #eee; letter-spacing: 0.5px; }
.info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px 20px; }
.info-item { display: flex; flex-direction: column; }
.info-label { font-size: 12px; color: #8898aa; margin-bottom: 4px; font-weight: 500; }
.info-value { font-size: 14px; color: #32325d; font-weight: 600; word-break: break-word; }
.detail-status-badge { display: inline-block; padding: 8px 16px; border-radius: 50px; font-weight: 600; font-size: 14px; margin-top: 10px; }
@media (max-width: 768px) { .modal-detail-body { flex-direction: column; } .detail-sidebar { width: 100%; flex: none; } .info-grid { grid-template-columns: 1fr; } }

</style>

<div class="category-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-boxes"></i> Quản lý sản phẩm</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên hoặc SKU...">

      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="1">Có sẵn</option>
        <option value="0">Hết hàng</option>
      </select>

      <select id="filter-supplier">
        <option value="">Lọc theo nhà cung cấp</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= htmlspecialchars($s['_id']) ?>">
            <?= htmlspecialchars($s['supplier_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <a href="index.php?page=products/createProduct" class="btn-create">
        <i class="fa-solid fa-plus"></i> Thêm sản phẩm
      </a>
    </div>
  </div>
<?php
function formatCurrency($amount) {
  return number_format((float)$amount, 0, ',', '.') . '₫';
}
?>
  <table id="category-table">
    <thead>
      <tr>
        <th>STT</th>
        <th>Hình ảnh</th>
        <th>Tên SP</th>
        <th>SKU</th>
        <th>Loại SP</th>
        <th>Nhà cung cấp</th>
        <th>Tồn kho tối thiểu</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if (is_array($products) && !empty($products)) {
        $counter = 1;
        foreach ($products as $p) {
          $id = $p['sku'];
          // Ensure id is always a string
          $id = is_array($id) ? ($id['_id'] ?? (string)($id[0] ?? '')) : (string)$id;
          
          $name = $p['product_name'];
          $name = is_array($name) ? (string)($name[0] ?? '') : (string)$name;
          
          $barcode = $p['barcode'];
          $barcode = is_array($barcode) ? (string)($barcode[0] ?? '') : (string)$barcode;
          
          $model = $p['model'] ?? '-';
          $model = is_array($model) ? (string)($model[0] ?? '-') : (string)$model;
          
          // Handle category - check if it's an array or string
          $type = '';
          if (isset($p['category'])) {
            $type = is_array($p['category']) ? ($p['category']['name'] ?? '') : $p['category'];
          }
          
          // Handle supplier - check if it's an array or string and extract a stable supplier ID
          $supplier = '';
          $supplier_id = '';
          if (isset($p['supplier'])) {
            if (is_array($p['supplier'])) {
              // supplier name may be under different keys depending on source
              $supplier = $p['supplier']['name'] ?? $p['supplier']['supplier_name'] ?? '';

              // try common id fields in order
              if (isset($p['supplier']['_id'])) {
                $supplier_id = $p['supplier']['_id'];
              } elseif (isset($p['supplier']['id'])) {
                $supplier_id = $p['supplier']['id'];
              } elseif (isset($p['supplier']['supplier_id'])) {
                $supplier_id = $p['supplier']['supplier_id'];
              } else {
                // fallback to supplier name (not ideal but keeps something)
                $supplier_id = $supplier;
              }

              // if supplier_id is an array (nested), try to extract its _id or first element
              if (is_array($supplier_id)) {
                $supplier_id = $supplier_id['_id'] ?? (string)($supplier_id[0] ?? '');
              }
            } else {
              $supplier = $p['supplier'];
              $supplier_id = $p['supplier'];
            }
          }
          // Final safeguard to ensure supplier_id is always a string
          $supplier_id = is_array($supplier_id) ? '' : (string)$supplier_id;
          
          // Đơn vị và kích thước
          $baseUnit = $p['baseUnit'] ?? '-';
          $baseUnit = is_array($baseUnit) ? (string)($baseUnit[0] ?? '-') : (string)$baseUnit;
          
          $packageDim = $p['package_dimensions'] ?? [];
          $packageWeight = $p['package_weight'] ?? 0;
          $volumePerUnit = $p['volume_per_unit'] ?? 0;
          
          $dimensionText = '-';
          if (!empty($packageDim)) {
            $w = $packageDim['width'] ?? 0;
            $d = $packageDim['depth'] ?? 0;
            $h = $packageDim['height'] ?? 0;
            $dimensionText = "{$w}×{$d}×{$h} cm";
          }
          
          $weightText = $packageWeight > 0 ? $packageWeight . ' kg' : '-';
          $volumeText = $volumePerUnit > 0 ? number_format($volumePerUnit, 0) . ' cm³' : '-';
          
          // Đơn vị quy đổi
          $conversionUnits = $p['conversionUnits'] ?? [];
          $conversionText = '-';
          if (!empty($conversionUnits)) {
            $conversionList = [];
            foreach ($conversionUnits as $conv) {
              $unit = $conv['unit'] ?? '';
              $factor = $conv['factor'] ?? 0;
              if ($unit && $factor > 0) {
                $conversionList[] = "1 {$unit} = {$factor} {$baseUnit}";
              }
            }
            $conversionText = !empty($conversionList) ? implode('<br>', $conversionList) : '-';
          }
          
          $min_stock = $p['min_stock'] ?? '';
          $min_stock = is_array($min_stock) ? (string)($min_stock[0] ?? '') : (string)$min_stock;
          
          $stackable = isset($p['stackable']) ? ($p['stackable'] ? 'Có' : 'Không') : '-';
          $status = $p['status'];
          $status = is_array($status) ? (string)($status[0] ?? '0') : (string)$status;

          $imagePath = isset($p['image']) ? "../../../img/" . $p['image'] : "";
          $imageTag = $imagePath ? "<img src='{$imagePath}' alt='Ảnh sản phẩm' class='product-image'>" : "";

          $barcodeImg = '';
          if ($barcode) {
            $barcodeBinary = $barcodeGenerator->getBarcode($barcode, $barcodeGenerator::TYPE_CODE_128, 2, 40);
            $barcodeBase64 = base64_encode($barcodeBinary);
            $barcodeDataUri = 'data:image/png;base64,' . $barcodeBase64;
            $barcodeImg = "
              <div class='barcode-box'>
                <img src='" . $barcodeDataUri . "' alt='Barcode'>
                <div class='barcode-text'>{$barcode}</div>
              </div>";
          }

          // use existing CSS classes 'active' / 'inactive' for the status badge
          $statusClass = $status == 1 ? 'active' : 'inactive';
          $statusText = $status == 1 ? 'Có sẵn' : 'Hết hàng';

          // Prepare product details for modal (JSON encoded for data attribute)
          $productDetails = json_encode([
            'sku' => $id,
            'name' => $name,
            'barcode' => $barcode,
            'barcode_img' => $barcodeDataUri ?? '',
            'model' => $model,
            'category' => $type,
            'supplier' => $supplier,
            'baseUnit' => $baseUnit,
            'dimensions' => $dimensionText,
            'weight' => $weightText,
            'volume' => $volumeText,
            'conversion' => $conversionText,
            'min_stock' => $min_stock,
            'stackable' => $stackable,
            'status' => $statusText,
            'image' => $imagePath
          ], JSON_HEX_APOS | JSON_HEX_QUOT);

          $dataSupplier = htmlspecialchars($supplier_id, ENT_QUOTES);
          $dataSupplierName = htmlspecialchars($supplier, ENT_QUOTES);
          echo "
            <tr data-status='{$status}' data-supplier='{$dataSupplier}' data-supplier-name='{$dataSupplierName}'>
              <td>{$counter}</td>
              <td>{$imageTag}</td>
              <td>{$name}</td>
              <td>{$id}</td>
              <td>{$type}</td>
              <td>{$supplier}</td>
              <td>{$min_stock}</td>
              <td><span class='status {$statusClass}'>{$statusText}</span></td>
              <td>
                <a href='#' class='btn btn-view btn-view-detail' data-product='{$productDetails}' title='Xem chi tiết'><i class='fa-solid fa-eye'></i></a>
                <a href='index.php?page=products/updateProduct&id={$id}' class='btn btn-edit' title='Chỉnh sửa'><i class='fa-solid fa-pen'></i></a>
                <a href='#' class='btn btn-delete' data-id='{$id}' title='Xóa'><i class='fa-solid fa-trash'></i></a>
              </td>
            </tr>";
          $counter++;
        }
      } else {
        echo "<tr><td colspan='10'>Không có sản phẩm nào.</td></tr>";
      }
      ?>
    </tbody>
  </table>
  <div id="pagination" style="display:flex; gap:8px; justify-content:flex-end; margin-top:16px; align-items:center;"></div>
</div>

<!-- Modal xác nhận xóa -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
    <h3 style="margin-top:0;">Xác nhận xóa</h3>
    <p>Bạn có chắc chắn muốn xóa sản phẩm này?</p>
    <div style="margin-top:20px;">
      <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
      <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
    </div>
  </div>
  <div class="modal-overlay"></div>
</div>

<!-- Modal chi tiết sản phẩm -->
<div id="detailModal" class="modal" style="display:none;">
  <div class="modal-content">
    
    <div class="modal-detail-header">
        <h3><i class="fa-solid fa-box-open"></i> Thông tin sản phẩm</h3>
        <button class="btn-close-icon" id="closeDetailBtnHeader">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>

    <div class="modal-detail-body">
        
        <div class="detail-sidebar">
            <div class="detail-sku-tag">SKU: <span id="detail-sku"></span></div>
            <div class="detail-image-box" id="detailImage">
                </div>
            <div id="detail-barcode-area" style="margin-top: 10px; font-family: monospace; font-size: 16px; letter-spacing: 2px; background: #f1f1f1; padding: 5px 10px; border-radius: 4px;">
                <span id="detail-barcode"></span>
            </div>
            <div id="detail-status-container" style="margin-top: 15px; width: 100%;">
                <span id="detail-status-badge" class="detail-status-badge"></span>
            </div>
        </div>

        <div class="detail-main">
            
            <div class="info-section">
                <div class="section-title"><i class="fa-solid fa-circle-info"></i> Tổng quan</div>
                <div class="info-grid">
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Tên sản phẩm</span>
                        <span class="info-value" id="detail-name" style="font-size: 16px; color: #007bff;"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Model</span>
                        <span class="info-value" id="detail-model"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Danh mục</span>
                        <span class="info-value" id="detail-category"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nhà cung cấp</span>
                        <span class="info-value" id="detail-supplier"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Đơn vị tính (Cơ sở)</span>
                        <span class="info-value" id="detail-baseUnit"></span>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <div class="section-title"><i class="fa-solid fa-truck-ramp-box"></i> Vận chuyển & Kho</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Kích thước (D×R×C)</span>
                        <span class="info-value" id="detail-dimensions"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Trọng lượng</span>
                        <span class="info-value" id="detail-weight"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Thể tích</span>
                        <span class="info-value" id="detail-volume"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Xếp chồng</span>
                        <span class="info-value" id="detail-stackable"></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tồn kho tối thiểu</span>
                        <span class="info-value" id="detail-minStock" style="color: #dc3545;"></span>
                    </div>
                     <div class="info-item" style="grid-column: 1 / -1;">
                        <span class="info-label">Đơn vị quy đổi</span>
                        <span class="info-value" id="detail-conversion"></span>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!-- <div style="text-align: right; padding: 0 30px 30px;">
         <button id="closeDetailBtnBottom" style="padding: 8px 20px; border: 1px solid #ccc; background: #fff; border-radius: 6px; cursor: pointer;">Đóng</button>
    </div> -->
  </div>
  <div class="modal-overlay"></div>
</div>

<script>
  const statusFilter = document.getElementById('filter-status');
  const searchInput = document.getElementById('searchInput');
  const supplierFilter = document.getElementById('filter-supplier');
  const rows = document.querySelectorAll('#category-table tbody tr');
  const paginationContainer = document.getElementById('pagination');
  const pageSize = 10;
  let currentPage = 1;

  // Helper: convert NodeList to array
  const rowsArray = Array.from(rows);

  function applyFilters() {
    const statusValue = (statusFilter.value || '').toString().trim();
    const supplierValue = (supplierFilter.value || '').toString().trim();
    const supplierText = (supplierFilter.options[supplierFilter.selectedIndex] || {}).text ? supplierFilter.options[supplierFilter.selectedIndex].text.toString().trim() : '';
    const searchValue = searchInput.value.toLowerCase();
    // mark rows with data-filtered instead of directly hiding; pagination will handle display
    rowsArray.forEach(row => {
      const rowStatus = (row.dataset.status || '').toString().trim();
      const rowSupplier = (row.dataset.supplier || '').toString().trim();
      const rowSupplierName = (row.dataset.supplierName || '').toString().trim();
      const rowName = row.children[2].textContent.toLowerCase();
      const rowSKU = row.children[3].textContent.toLowerCase();

      const matchStatus = !statusValue || rowStatus === statusValue;
      const matchSupplier = !supplierValue || rowSupplier === supplierValue || rowSupplierName === supplierValue || rowSupplier === supplierText || rowSupplierName === supplierText;
      const matchSearch = !searchValue || rowName.includes(searchValue) || rowSKU.includes(searchValue);

      row.dataset.filtered = (matchStatus && matchSupplier && matchSearch) ? '1' : '0';
    });

    // reset to first page whenever filters change
    currentPage = 1;
    paginate();
  }

  statusFilter.addEventListener('change', applyFilters);
  searchInput.addEventListener('input', applyFilters);
  supplierFilter.addEventListener('change', applyFilters);

  // --- Pagination functions ---
  function paginate() {
    const visibleRows = rowsArray.filter(r => r.dataset.filtered === '1');
    const total = visibleRows.length;
    const totalPages = Math.max(1, Math.ceil(total / pageSize));

    // clamp currentPage
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    // hide all rows first
    rowsArray.forEach(r => r.style.display = 'none');

    // show slice for current page
    const start = (currentPage - 1) * pageSize;
    const slice = visibleRows.slice(start, start + pageSize);
    slice.forEach(r => r.style.display = '');

    renderPaginationControls(totalPages);
  }

  function renderPaginationControls(totalPages) {
    // clear
    paginationContainer.innerHTML = '';

    if (totalPages <= 1) return; // no controls if only one page

    const prev = document.createElement('button');
    prev.className = 'btn';
    prev.textContent = '‹';
    prev.disabled = currentPage === 1;
    prev.addEventListener('click', () => { currentPage--; paginate(); });
    paginationContainer.appendChild(prev);

    // show up to 7 page numbers (with truncation)
    const maxButtons = 7;
    let startPage = Math.max(1, currentPage - Math.floor(maxButtons/2));
    let endPage = startPage + maxButtons - 1;
    if (endPage > totalPages) { endPage = totalPages; startPage = Math.max(1, endPage - maxButtons + 1); }

    for (let p = startPage; p <= endPage; p++) {
      const btn = document.createElement('button');
      btn.className = 'btn';
      btn.textContent = p;
      if (p === currentPage) { btn.style.fontWeight = '700'; }
      btn.addEventListener('click', () => { currentPage = p; paginate(); });
      paginationContainer.appendChild(btn);
    }

    const next = document.createElement('button');
    next.className = 'btn';
    next.textContent = '›';
    next.disabled = currentPage === totalPages;
    next.addEventListener('click', () => { currentPage++; paginate(); });
    paginationContainer.appendChild(next);
  }

  // initialize: mark all rows as filtered then paginate
  rowsArray.forEach(r => r.dataset.filtered = '1');
  paginate();

  // --- Toast notification for success/error messages ---
  const urlParams = new URLSearchParams(window.location.search);
  const msg = urlParams.get('msg');
  if (msg === 'success' || msg === 'updated' || msg === 'deleted' || msg === 'error') {
    // If server already rendered a toast, don't create a duplicate
    if (!document.getElementById('serverToast')) {
      const toast = document.createElement('div');
      toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : '');
      if (msg === 'success') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thêm sản phẩm thành công!';
      else if (msg === 'updated') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cập nhật sản phẩm thành công!';
      else if (msg === 'deleted') toast.innerHTML = '<i class="fa-solid fa-trash-can"></i> Xóa sản phẩm thành công!';
      else toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra.';
      document.body.appendChild(toast);

      setTimeout(() => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 300); }, 3000);

      const newUrl = window.location.pathname + '?page=products';
      window.history.replaceState({}, '', newUrl);
    }
  }

  // --- Modal Xóa ---
  const deleteModal = document.getElementById('deleteModal');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  let deleteProductId = null;

  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      deleteProductId = btn.dataset.id;
      deleteModal.style.display = 'block';
    });
  });

  cancelBtn.addEventListener('click', () => {
    deleteModal.style.display = 'none';
    deleteProductId = null;
  });

  confirmDeleteBtn.addEventListener('click', () => {
    if (deleteProductId) {
      fetch(`../../../view/page/manage/products/deleteProduct/deleteProduct.php?id=${deleteProductId}`)
        .then(res => res.json())
        .then((data) => {
          deleteModal.style.display = 'none';
          if (data && data.success) {
            window.location.reload();
          } else {
            const errToast = document.createElement('div');
            errToast.className = 'toast-notification error';
            const errorMessage = data.message || 'Xóa sản phẩm thất bại!';
            errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errorMessage;
            document.body.appendChild(errToast);
            setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
          }
        })
        .catch(err => {
          deleteModal.style.display = 'none';
          console.error('Lỗi xóa sản phẩm:', err);
          const errToast = document.createElement('div');
          errToast.className = 'toast-notification error';
          errToast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Lỗi kết nối khi xóa.';
          document.body.appendChild(errToast);
          setTimeout(() => { errToast.classList.add('hide'); setTimeout(() => errToast.remove(), 300); }, 3000);
        });
    }
  });

  // --- Modal Chi tiết sản phẩm (Logic mới) ---
  const detailModal = document.getElementById('detailModal');
  const closeDetailBtnHeader = document.getElementById('closeDetailBtnHeader');
  const closeDetailBtnBottom = document.getElementById('closeDetailBtnBottom');

  document.addEventListener('click', (e) => {
    if (e.target.closest('.btn-view-detail')) {
      e.preventDefault();
      const btn = e.target.closest('.btn-view-detail');
      // Parse dữ liệu JSON
      let productData = {};
      try {
          productData = JSON.parse(btn.dataset.product);
      } catch (err) {
          console.error("Lỗi parse JSON", err);
          return;
      }
      
      // Fill dữ liệu text
      const setText = (id, val) => {
          const el = document.getElementById(id);
          if(el) el.textContent = (val && val !== 'null') ? val : '-';
      };

      setText('detail-sku', productData.sku);
      setText('detail-name', productData.name);
      // Render barcode image (if available) and code text
      const barcodeContainer = document.getElementById('detail-barcode');
      if (barcodeContainer) {
        if (productData.barcode_img) {
          barcodeContainer.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;gap:6px;"><img src="${productData.barcode_img}" alt="Barcode" style="height:40px;"/><div style="font-family:monospace;background:#f4f6f8;padding:4px 8px;border-radius:6px;font-size:12px;color:#222;">${productData.barcode || ''}</div></div>`;
        } else {
          barcodeContainer.textContent = productData.barcode || '-';
        }
      }
      setText('detail-model', productData.model);
      setText('detail-category', productData.category);
      setText('detail-supplier', productData.supplier);
      setText('detail-baseUnit', productData.baseUnit);
      setText('detail-dimensions', productData.dimensions);
      setText('detail-weight', productData.weight);
      setText('detail-volume', productData.volume);
      setText('detail-minStock', productData.min_stock);
      setText('detail-stackable', productData.stackable);

      // Xử lý HTML inner (cho conversion)
      const conversionEl = document.getElementById('detail-conversion');
      if(conversionEl) conversionEl.innerHTML = productData.conversion || '-';
      
      // Xử lý ảnh
      const imageContainer = document.getElementById('detailImage');
      if (productData.image) {
        imageContainer.innerHTML = `<img src="${productData.image}" alt="${productData.name}">`;
      } else {
        imageContainer.innerHTML = '<div style="color: #adb5bd; display:flex; flex-direction:column; align-items:center;"><i class="fa-solid fa-image" style="font-size:40px; margin-bottom:10px;"></i><span>No Image</span></div>';
      }

      // Xử lý badge trạng thái (Màu sắc)
      const statusEl = document.getElementById('detail-status-badge');
      const statusText = productData.status || '';
      statusEl.textContent = statusText;
      
      // Reset class cũ và gán class màu mới
      statusEl.className = 'detail-status-badge'; 
      if (statusText === 'Có sẵn' || statusText.includes('Available')) {
          statusEl.style.background = '#d4edda';
          statusEl.style.color = '#155724';
      } else {
          statusEl.style.background = '#f8d7da';
          statusEl.style.color = '#721c24';
      }
      
      detailModal.style.display = 'block';
    }
  });

  // Sự kiện đóng modal
  const closeDetailModal = () => { detailModal.style.display = 'none'; };
  
  if(closeDetailBtnHeader) closeDetailBtnHeader.addEventListener('click', closeDetailModal);
  if(closeDetailBtnBottom) closeDetailBtnBottom.addEventListener('click', closeDetailModal);

  // Close modals when clicking overlay
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', () => {
      deleteModal.style.display = 'none';
      detailModal.style.display = 'none';
    });
  });

// --- Menu icon hành động ---
document.querySelectorAll('.action-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const menu = btn.nextElementSibling;
    const isVisible = menu.style.display === 'flex';
    document.querySelectorAll('.icon-menu').forEach(m => m.style.display = 'none');
    menu.style.display = isVisible ? 'none' : 'flex';
  });
});

document.addEventListener('click', () => {
  document.querySelectorAll('.icon-menu').forEach(menu => menu.style.display = 'none');
});


</script>
