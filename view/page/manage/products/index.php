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

<style>
  .product-list-container {
    max-width: 1400px;
    margin: 30px auto;
    background: #fff;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
  }

  .product-list-container h2 {
    text-align: center;
    margin-bottom: 25px;
    color: #333;
  }

  .product-list-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
  }

  .product-list-container th,
  .product-list-container td {
    padding: 10px 12px;
    border: 1px solid #e1e4e8;
    text-align: center;
    font-size: 14px;
    vertical-align: middle;
  }

  .product-list-container th {
    background: #f9fafb;
  }

  .product-list-container tr:hover {
    background: #f1f7ff;
  }

  .product-list-container .btn {
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
  }

  .btn-edit { background: #17a2b8; color: #fff; }
  .btn-delete { background: #dc3545; color: #fff; }
  .btn:hover { opacity: 0.9; }

  .status {
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 8px;
    display: inline-block;
  }
  .status-active { background: #d4edda; color: #155724; }
  .status-inactive { background: #f8d7da; color: #721c24; }

  .top-actions {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }

  .btn-create {
    background: #007bff;
    color: #fff;
    padding: 8px 14px;
    border-radius: 8px;
    text-decoration: none;
  }
  .btn-create:hover { background: #0056b3; }

  .filters {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
  }

  .filters input,
  .filters select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
  }

  .product-image {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    object-fit: cover;
  }

  .barcode-box {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .barcode-box img { height: 40px; }

  .barcode-text {
    font-size: 12px;
    font-family: monospace;
    color: #222;
    background: #f4f6f8;
    padding: 2px 6px;
    border-radius: 4px;
    margin-top: 2px;
  }

  /* Modal */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
  }

  .modal-content {
    position: relative;
    z-index: 1001;
  }

  .modal-overlay {
    position: fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background: rgba(0,0,0,0.4);
    z-index: 1000;
  }
  
.action-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  color: #555;
  padding: 6px;
  border-radius: 50%;
  transition: 0.2s;
}

.action-btn:hover {
  background: #f1f3f5;
  color: #007bff;
}

/* Menu icon bật ra */
.icon-menu {
  display: none; /* Ẩn mặc định */
  position: absolute;
  top: 65%;
  right: 0;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 6px 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 10;
  gap: 8px;
  animation: fadeIn 0.15s ease-out;
}


@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-3px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Icon trong menu */
.icon-item {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  font-size: 14px;
  transition: 0.2s;
}

.icon-item.edit {
  color: #007bff;
  background: #e7f0ff;
}

.icon-item.edit:hover {
  background: #007bff;
  color: #fff;
}

.icon-item.delete {
  color: #dc3545;
  background: #fde8ea;
}

.icon-item.delete:hover {
  background: #dc3545;
  color: #fff;
}


/* Mục trong menu */
.action-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  font-size: 14px;
  color: #333;
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
}

.action-item i {
  font-size: 14px;
}

/* Hiệu ứng hover */
.action-item:hover {
  background: #f0f6ff;
  color: #007bff;
}

/* Phân biệt hai nút */
.action-item:nth-child(2):hover {
  background: #ffeaea;
  color: #dc3545;
}



</style>

<div class="product-list-container">
  <div class="top-actions">
    <h2><i class="fa-solid fa-boxes"></i> Danh sách sản phẩm</h2>

    <div class="filters">
      <input type="text" id="searchInput" placeholder="Tìm kiếm theo tên hoặc SKU...">

      <select id="filter-status">
        <option value="">Lọc theo trạng thái</option>
        <option value="1">Đang bán</option>
        <option value="0">Ngừng bán</option>
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
  <table id="product-table">
    <thead>
      <tr>
        <th>Hình ảnh</th>
        <th>SKU</th>
        <th>Tên sản phẩm</th>
        <th>Barcode</th>
        <th>Loại</th>
        <th>Nhà cung cấp</th>
        <th>Giá nhập gần nhất</th>
        <!-- <th>Giá vốn bình quân</th> -->
        <th>Tồn hiện tại</th>
        <th>Tồn kho tối thiểu</th>
        <th>Trạng thái</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if (is_array($products) && !empty($products)) {
        foreach ($products as $p) {
          $id = $p['sku'];
          $name = $p['product_name'];
          $barcode = $p['barcode'];
          $type = $p['category']['name'] ?? '';
          $supplier = $p['supplier']['name'] ?? '';
          $supplier_id = $p['supplier']['id'] ?? '';
          
          $min_stock = $p['min_stock'] ?? '';
          $status = $p['status'];

          $imagePath = isset($p['image']) ? "../../../img/" . $p['image'] : "";
          $imageTag = $imagePath ? "<img src='{$imagePath}' alt='Ảnh sản phẩm' class='product-image'>" : "";

          $barcodeImg = '';
          if ($barcode) {
            $barcodeBinary = $barcodeGenerator->getBarcode($barcode, $barcodeGenerator::TYPE_CODE_128, 2, 40);
            $barcodeBase64 = base64_encode($barcodeBinary);
            $barcodeImg = "
              <div class='barcode-box'>
                <img src='data:image/png;base64,{$barcodeBase64}' alt='Barcode'>
                <div class='barcode-text'>{$barcode}</div>
              </div>";
          }

          $statusClass = $status == 1 ? 'status-active' : 'status-inactive';
          $statusText = $status == 1 ? 'Có sẵn' : 'Hết hàng';

          echo "
            <tr data-status='{$status}' data-supplier='{$supplier_id}'>
              <td>{$imageTag}</td>
              <td>{$id}</td>
              <td>{$name}</td>
              <td>{$barcodeImg}</td>
              <td>{$type}</td>
              <td>{$supplier}</td>
              <td></td>
              <td></td>
              <td>{$min_stock}</td>
              <td><span class='status {$statusClass}'>{$statusText}</span></td>
              <td style='position: relative;'>
  <button class='action-btn'><i class='fa-solid fa-gear'></i></button>
  <div class='icon-menu'>
    <a href='index.php?page=products/updateProduct&id={$id}' class='icon-item edit'><i class='fa-solid fa-pen'></i></a>
    <a href='#' class='icon-item delete btn-delete' data-id='{$id}'><i class='fa-solid fa-trash'></i></a>
  </div>
</td>


            </tr>";
        }
      } else {
        echo "<tr><td colspan='9'>Không có sản phẩm nào.</td></tr>";
      }
      ?>
    </tbody>
  </table>
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

<script>
  const statusFilter = document.getElementById('filter-status');
  const searchInput = document.getElementById('searchInput');
  const supplierFilter = document.getElementById('filter-supplier');
  const rows = document.querySelectorAll('#product-table tbody tr');

  function applyFilters() {
    const statusValue = statusFilter.value;
    const supplierValue = supplierFilter.value;
    const searchValue = searchInput.value.toLowerCase();

    rows.forEach(row => {
      const rowStatus = row.dataset.status;
      const rowSupplier = row.dataset.supplier;
      const rowName = row.children[2].textContent.toLowerCase();
      const rowSKU = row.children[1].textContent.toLowerCase();

      const matchStatus = !statusValue || rowStatus === statusValue;
      const matchSupplier = !supplierValue || rowSupplier === supplierValue;
      const matchSearch = !searchValue || rowName.includes(searchValue) || rowSKU.includes(searchValue);

      row.style.display = (matchStatus && matchSupplier && matchSearch) ? '' : 'none';
    });
  }

  statusFilter.addEventListener('change', applyFilters);
  searchInput.addEventListener('input', applyFilters);
  supplierFilter.addEventListener('change', applyFilters);

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
        .then(res => res.text())
        .then(() => {
          deleteModal.style.display = 'none';
          window.location.reload();
        })
        .catch(err => console.error('Lỗi xóa sản phẩm:', err));
    }
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
