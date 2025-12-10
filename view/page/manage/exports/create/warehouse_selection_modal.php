<!-- Warehouse Selection Modal -->
<div class="warehouse-modal" id="warehouseModal">
  <div class="warehouse-modal-content">
    <div class="warehouse-modal-header">
      <h3><i class="fa-solid fa-warehouse"></i> Chọn kho xuất hàng</h3>
      <button class="warehouse-modal-close" onclick="closeWarehouseModal()">×</button>
    </div>
    <div class="warehouse-modal-body">
      <div style="margin-bottom:15px;">
        <strong style="font-size:16px;">Sản phẩm: <span id="modalProductName"></span></strong><br>
        <span style="color:#666;font-size:14px;">Số lượng cần xuất: <strong style="color:#ff9800;" id="modalRequiredQty"></strong> <span id="modalBaseUnit"></span></span>
      </div>
      
      <div id="warehouseList">
        <div style="text-align:center;padding:40px;color:#999;">
          <i class="fa-solid fa-spinner fa-spin" style="font-size:32px;"></i><br>
          Đang tải danh sách kho...
        </div>
      </div>
      
      <div class="summary-box" id="summaryBox" style="display:none;">
        <div class="summary-item">
          <span>Tổng số kho đã chọn:</span>
          <strong id="summaryWarehouseCount">0</strong>
        </div>
        <div class="summary-item">
          <span>Tổng số lượng xuất:</span>
          <strong id="summaryTotalQty">0</strong>
        </div>
        <div class="summary-item total">
          <span style="color:#666;">Còn thiếu:</span>
          <strong id="summaryRemaining" style="color:#dc3545;">0</strong>
        </div>
        
        <!-- Error message inline -->
        <div id="selectionError" style="display:none;margin-top:12px;padding:12px;background:#fee2e2;border:2px solid #ef4444;border-left:4px solid #dc2626;border-radius:8px;">
          <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-exclamation-circle" style="color:#dc2626;font-size:18px;"></i>
            <span style="color:#991b1b;font-weight:600;font-size:14px;" id="errorMessage"></span>
          </div>
        </div>
      </div>
    </div>
    <div class="warehouse-modal-footer">
      <button type="button" class="btn btn-cancel" onclick="closeWarehouseModal()">Đóng</button>
      <button type="button" class="btn btn-submit" onclick="confirmWarehouseSelection()">
        <i class="fa-solid fa-check"></i> Xác nhận
      </button>
    </div>
  </div>
</div>

<script>
let currentProductId = null;
let currentRequiredQty = 0;
let currentBaseUnit = '';
let currentDestinationWarehouse = null;
let warehouseSelections = {};

// Make warehouseSelections globally accessible
window.warehouseSelections = warehouseSelections;

document.addEventListener('DOMContentLoaded', function() {
  console.log('Warehouse modal script loaded');
  
  document.querySelectorAll('.btn-choose-warehouse').forEach(btn => {
    btn.addEventListener('click', function() {
      openWarehouseModal(
        this.dataset.productId,
        this.dataset.productName,
        parseInt(this.dataset.requiredQty),
        this.dataset.baseUnit,
        this.dataset.destinationWarehouse
      );
    });
  });
  
  // Update global reference when warehouseSelections changes
  const originalConfirmWarehouseSelection = confirmWarehouseSelection;
  window.confirmWarehouseSelection = function() {
    originalConfirmWarehouseSelection();
    window.warehouseSelections = warehouseSelections;
    console.log('Updated window.warehouseSelections:', window.warehouseSelections);
  };
});

function openWarehouseModal(productId, productName, requiredQty, baseUnit, destinationWarehouse) {
  currentProductId = productId;
  currentRequiredQty = requiredQty;
  currentBaseUnit = baseUnit;
  currentDestinationWarehouse = destinationWarehouse;
  
  document.getElementById('modalProductName').textContent = productName;
  document.getElementById('modalRequiredQty').textContent = requiredQty.toLocaleString();
  document.getElementById('modalBaseUnit').textContent = baseUnit;
  document.getElementById('warehouseModal').classList.add('active');
  
  loadWarehouseStock(productId, destinationWarehouse);
}

function closeWarehouseModal() {
  document.getElementById('warehouseModal').classList.remove('active');
  hideError(); // Clear error when closing
}

async function loadWarehouseStock(productId, destinationWarehouse) {
  try {
    console.log('Loading stock for product:', productId, 'excluding warehouse:', destinationWarehouse);
    
    // Simple relative path
    const url = 'get_warehouse_stock.php';
    
    console.log('Fetching from URL:', url);
    console.log('Request body:', JSON.stringify({
      product_id: productId,
      destination_warehouse: destinationWarehouse
    }));
    
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        product_id: productId,
        destination_warehouse: destinationWarehouse
      })
    });
    
    console.log('Response status:', response.status);
    console.log('Response headers:', [...response.headers.entries()]);
    
    const text = await response.text();
    console.log('Response text (first 500 chars):', text.substring(0, 500));
    
    // Check if response is HTML (error page)
    if (text.trim().startsWith('<') || text.trim().startsWith('<!')) {
      console.error('Received HTML instead of JSON. Full response:', text);
      throw new Error('Server trả về HTML thay vì JSON. Có thể có lỗi PHP. Kiểm tra console để xem chi tiết.');
    }
    
    let data;
    try {
      data = JSON.parse(text);
    } catch (parseError) {
      console.error('JSON parse error:', parseError);
      console.error('Response text:', text);
      throw new Error('Không thể parse JSON: ' + parseError.message);
    }
    
    console.log('Parsed data:', data);
    
    if (!data.success) {
      const errorMsg = data.error || 'Failed to load';
      const errorDetails = data.file ? ` (File: ${data.file}, Line: ${data.line})` : '';
      throw new Error(errorMsg + errorDetails);
    }
    
    // Lọc bỏ kho đích (kho yêu cầu) khỏi danh sách
    const filteredWarehouses = data.warehouses.filter(wh => wh.warehouse_id !== destinationWarehouse);
    console.log('Filtered warehouses (excluding destination):', filteredWarehouses);
    
    renderWarehouseList(filteredWarehouses);
  } catch (error) {
    console.error('Error in loadWarehouseStock:', error);
    document.getElementById('warehouseList').innerHTML = `
      <div style="text-align:center;padding:40px;color:#dc3545;">
        <i class="fa-solid fa-exclamation-circle" style="font-size:32px;margin-bottom:16px;"></i><br>
        <strong style="font-size:16px;">Lỗi: ${error.message}</strong><br>
        <small style="color:#666;margin-top:12px;display:block;font-size:13px;">
          Vui lòng mở Console (F12) để xem chi tiết lỗi
        </small>
      </div>
    `;
  }
}

function renderWarehouseList(warehouses) {
  const listContainer = document.getElementById('warehouseList');
  const existingSelections = warehouseSelections[currentProductId] || {};
  
  let html = '';
  warehouses.forEach(wh => {
    if (wh.available_qty > 0) {
      const selectedQty = existingSelections[wh.warehouse_id] || 0;
      html += `
        <div class="warehouse-item">
          <div class="warehouse-info">
            <div class="name">
              ${wh.warehouse_name}
              ${wh.warehouse_type === 'central' ? '<span style="background:#ff9800;color:#fff;font-size:11px;padding:2px 8px;border-radius:4px;margin-left:8px;">KHO TỔNG</span>' : ''}
            </div>
            <div class="stock">
              Tồn kho: <strong>${wh.available_qty.toLocaleString()}</strong> ${currentBaseUnit}
            </div>
          </div>
          <div class="warehouse-qty-input">
            <label style="font-size:14px;color:#666;">Số lượng xuất:</label>
            <input type="number" class="warehouse-qty-field"
                   data-warehouse-id="${wh.warehouse_id}"
                   data-available-qty="${wh.available_qty}"
                   min="0" max="${wh.available_qty}"
                   value="${selectedQty}"
                   onchange="updateSummary()" placeholder="0">
          </div>
        </div>
      `;
    }
  });
  
  listContainer.innerHTML = html || '<div style="text-align:center;padding:40px;color:#999;">Không có kho nào có hàng</div>';
  document.getElementById('summaryBox').style.display = 'block';
  updateSummary();
}

function updateSummary() {
  const inputs = document.querySelectorAll('.warehouse-qty-field');
  let totalQty = 0, warehouseCount = 0;
  
  // Clear error when updating
  hideError();
  
  inputs.forEach(input => {
    const qty = parseInt(input.value) || 0;
    const maxQty = parseInt(input.dataset.availableQty);
    
    if (qty > maxQty) {
      input.value = maxQty;
      showError(`Số lượng không được vượt quá tồn kho (${maxQty})`);
      return;
    }
    
    if (qty > 0) {
      totalQty += qty;
      warehouseCount++;
    }
  });
  
  const remaining = currentRequiredQty - totalQty;
  document.getElementById('summaryWarehouseCount').textContent = warehouseCount;
  document.getElementById('summaryTotalQty').textContent = totalQty.toLocaleString();
  document.getElementById('summaryRemaining').textContent = remaining.toLocaleString();
  document.getElementById('summaryRemaining').style.color = remaining <= 0 ? '#28a745' : '#dc3545';
}

function showError(message) {
  const errorDiv = document.getElementById('selectionError');
  const errorMsg = document.getElementById('errorMessage');
  if (errorDiv && errorMsg) {
    errorMsg.textContent = message;
    errorDiv.style.display = 'block';
    
    // Scroll to error
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
}

function hideError() {
  const errorDiv = document.getElementById('selectionError');
  if (errorDiv) {
    errorDiv.style.display = 'none';
  }
}

function confirmWarehouseSelection() {
  const inputs = document.querySelectorAll('.warehouse-qty-field');
  const selections = {};
  let totalQty = 0;
  
  inputs.forEach(input => {
    const qty = parseInt(input.value) || 0;
    if (qty > 0) {
      selections[input.dataset.warehouseId] = qty;
      totalQty += qty;
    }
  });
  
  console.log('confirmWarehouseSelection - selections:', selections);
  console.log('confirmWarehouseSelection - totalQty:', totalQty);
  
  if (totalQty === 0) {
    showError('Vui lòng chọn ít nhất 1 kho và nhập số lượng!');
    return;
  }
  
  if (totalQty < currentRequiredQty) {
    showError(`Tổng số lượng (${totalQty}) nhỏ hơn yêu cầu (${currentRequiredQty}). Vui lòng kiểm tra lại!`);
    return;
  }
  
  warehouseSelections[currentProductId] = selections;
  window.warehouseSelections = warehouseSelections;
  
  console.log('Updated warehouseSelections:', warehouseSelections);
  console.log('Updated window.warehouseSelections:', window.warehouseSelections);
  
  updateProductSelectionInfo(currentProductId, Object.keys(selections).length, totalQty);
  closeWarehouseModal();
}

function updateProductSelectionInfo(productId, warehouseCount, totalQty) {
  const row = document.querySelector(`tr[data-product-id="${productId}"]`);
  if (!row) return;
  
  const infoDiv = row.querySelector('.warehouse-selection-info .selected-count');
  if (infoDiv) {
    infoDiv.innerHTML = warehouseCount > 0 
      ? `<strong style="color:#28a745;">${warehouseCount} kho | ${totalQty.toLocaleString()} ${currentBaseUnit}</strong>`
      : 'Chưa chọn';
  }
}

// Attach warehouse_selections to form before submit
document.addEventListener('DOMContentLoaded', function() {
  console.log('Setting up form submit handler');
  
  const exportForm = document.getElementById('exportForm');
  if (exportForm) {
    console.log('Export form found, adding submit listener');
    
    exportForm.addEventListener('submit', function(e) {
      console.log('Form submit event triggered');
      
      // Add warehouse selections as hidden input
      const existingInput = this.querySelector('input[name="warehouse_selections"]');
      if (existingInput) {
        existingInput.remove();
        console.log('Removed existing warehouse_selections input');
      }
      
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'warehouse_selections';
      input.value = JSON.stringify(window.warehouseSelections || warehouseSelections || {});
      this.appendChild(input);
      
      console.log('Form submit - warehouse_selections:', input.value);
    });
  } else {
    console.error('Export form with id="exportForm" not found!');
  }
});
</script>
