<?php
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");

$cSupplier = new CSupplier();
$supplier = null;

if (isset($_GET['id'])) {
    $supplierId = $_GET['id'];
    $supplier = $cSupplier->getSupplierById($supplierId);
}

if (!$supplier) {
    echo "<script>alert('Không tìm thấy nhà cung cấp.'); window.location.href = '../index.php?page=supplier';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật nhà cung cấp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
/* Use same styles as createSupplier for consistency */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f0f3f8;
    color: #1f2937;
    margin: 0;
    padding: 20px 0;
}

.page-header {
    width: 90%;
    max-width: 700px;
    margin: 30px auto 15px;
    padding-left: 10px;
    border-left: 4px solid #2563eb;
}

.page-header h2 { margin: 0; color: #111827; font-size: 2rem; font-weight: 700; }
.page-header p { margin: 5px 0 0; color: #6b7280; font-size: 1rem; }

.container { width: 90%; max-width: 700px; margin: 20px auto; background: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px 40px; }
.form-group { margin-bottom: 18px; }
.form-group label { display:block; margin-bottom:8px; font-weight:600; color:#374151; }
.form-group input, .form-group select { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; background:#f9fafb; box-sizing:border-box; }
.form-group input:focus, .form-group select:focus { border-color:#2563eb; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.15); }
.error-message { display:block; margin-top:8px; color:#ef4444; font-size:0.85rem; }
.form-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:20px; }
.form-actions a, .form-actions button { padding:12px 20px; border-radius:8px; font-weight:600; border:none; cursor:pointer; text-decoration:none; }
.btn-success { background:#10b981; color:#fff; }
.btn-success:hover { background:#059669; }
.btn-secondary { background:#9ca3af; color:#fff; }
.btn-secondary:hover { background:#6b7280; }
.back-btn { background:#e5e7eb; color:#4b5563; }

/* Disabled primary */
.btn-success.is-disabled { background-color:#d1d5db !important; color:#fff !important; cursor:not-allowed !important; opacity:0.8 !important; box-shadow:none !important; pointer-events:none !important; }

/* Modal */
.modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.5); }
.modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:35px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center; }
.modal-actions { display:flex; gap:12px; justify-content:center; }
#cancelModalBtn { background:#e5e7eb; color:#374151; padding:12px 28px; border-radius:8px; border:none; }
#confirmAddBtn { background:#10b981; color:#fff; padding:12px 28px; border-radius:8px; border:none; }

/* Toast notification */
.toast-notification { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 16px 24px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px; font-weight: 600; z-index: 10000; animation: slideIn 0.3s ease-out; }
.toast-notification.error { background: #ef4444; }
@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
@keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(400px); opacity: 0; } }
.toast-notification.hide { animation: slideOut 0.3s ease-out forwards; }
    </style>
</head>
<body>
    <div class="page-header">
        <h2>Cập nhật nhà cung cấp</h2>
        <p>Cập nhật thông tin nhà cung cấp</p>
    </div>

    <div class="container">
        <form action="/KLTN/view/page/manage/supplier/updateSupplier/process.php" method="post">
            <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">

            <div class="form-group">
                <label for="supplier_name">Tên nhà cung cấp</label>
                <input type="text" id="supplier_name" name="supplier_name" value="<?php echo $supplier['supplier_name']; ?>">
                <span class="error-message"></span>
            </div>

            <div class="form-group">
                <label for="contact">Liên hệ</label>
                <input type="text" id="contact" name="contact" value="<?php echo $supplier['contact']; ?>">
                <span class="error-message"></span>
            </div>

            <div class="form-group">
                <label for="contact_name">Tên người liên hệ</label>
                <input type="text" id="contact_name" name="contact_name" value="<?php echo isset($supplier['contact_name']) ? $supplier['contact_name'] : ''; ?>">
                <span class="error-message"></span>
            </div>

            <div class="form-group">
                <label for="tax_code">Mã số thuế</label>
                <input type="text" id="tax_code" name="tax_code" value="<?php echo isset($supplier['tax_code']) ? $supplier['tax_code'] : ''; ?>">
                <span class="error-message"></span>
            </div>

            <div class="form-group">
                <label for="country">Quốc gia</label>
                <input type="text" id="country" name="country" value="<?php echo isset($supplier['country']) ? $supplier['country'] : ''; ?>">
                <span class="error-message"></span>
            </div>

            <div class="form-group">
                <label for="description">Mô tả</label>
                <input type="text" id="description" name="description" value="<?php echo isset($supplier['description']) ? $supplier['description'] : ''; ?>">
                <span class="error-message"></span>
            </div>

            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status">
                    <option value="1" <?php echo ($supplier['status'] == 1) ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="0" <?php echo ($supplier['status'] == 0) ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                </select>
                <span class="error-message"></span>
            </div>

            <div class="form-actions">
                <a href="index.php?page=supplier" class="back-btn">Quay lại</a>
                <button type="reset" class="btn-secondary">Hủy</button>
                <button type="submit" class="btn-success is-disabled" id="saveBtn" name="btnUpdate" aria-disabled="true">Cập nhật</button>
            </div>
        </form>
    </div>

    <div id="confirmModal" class="modal">
      <div class="modal-content">
        <h3>Xác nhận cập nhật</h3>
        <p>Bạn có chắc chắn muốn cập nhật nhà cung cấp này không?</p>
        <div class="modal-actions">
          <button type="button" id="cancelModalBtn">Hủy</button>
          <button type="button" id="confirmAddBtn">Xác nhận</button>
        </div>
      </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const requiredIds = ['supplier_name','contact','status'];
            const saveBtn = document.getElementById('saveBtn');
            const inputs = requiredIds.map(id => document.getElementById(id)).filter(Boolean);
            // include optional fields in change detection
            const optionalIds = ['contact_name','tax_code','country','description'];
            const allFieldIds = requiredIds.concat(optionalIds);
            const allFields = allFieldIds.map(id => document.getElementById(id)).filter(Boolean);
            const confirmModal = document.getElementById('confirmModal');
            const confirmAddBtn = document.getElementById('confirmAddBtn');
            const cancelModalBtn = document.getElementById('cancelModalBtn');
            let isConfirmed = false;

            // Capture initial values for change detection
            const initialValues = {};
            allFieldIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) initialValues[id] = el.value == null ? '' : el.value;
            });

            function getCurrentValues(){
                const vals = {};
                allFieldIds.forEach(id => {
                    const el = document.getElementById(id);
                    vals[id] = el ? el.value : '';
                });
                return vals;
            }

            function isDirty(){
                const cur = getCurrentValues();
                return allFieldIds.some(id => (cur[id] || '') !== (initialValues[id] || ''));
            }

            function updateSaveButton(){
                let allFilled = true;
                requiredIds.forEach(id => { const el = document.getElementById(id); if (!el || el.value.trim() === '') allFilled = false; });
                const dirty = isDirty();
                if (allFilled && dirty) {
                    saveBtn.classList.remove('is-disabled');
                    saveBtn.removeAttribute('aria-disabled');
                } else {
                    saveBtn.classList.add('is-disabled');
                    saveBtn.setAttribute('aria-disabled','true');
                }
            }

            function validateField(field){ const err = field.parentElement.querySelector('.error-message'); if (!err) return true; err.innerText = ''; if ((field.id === 'supplier_name' || field.id === 'contact' || field.id === 'status') && field.value.trim() === '') { err.innerText = 'Trường này là bắt buộc'; return false; } return true; }

            allFields.forEach(f => {
                if (!f) return;
                f.dataset.touched = 'false';
                f.addEventListener('input', function(){ this.dataset.touched = 'true'; validateField(this); updateSaveButton(); });
                f.addEventListener('blur', function(){ this.dataset.touched = 'true'; validateField(this); updateSaveButton(); });
            });

            updateSaveButton();

            const form = document.querySelector('form');
            form.addEventListener('submit', function(e){ if (saveBtn.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return false; } // prevent when disabled
                let valid = true; inputs.forEach(f => { if (!validateField(f)) valid = false; }); if (!valid) { e.preventDefault(); return false; }
                if (!isConfirmed) { e.preventDefault(); confirmModal.style.display = 'block'; return false; }
            });

            // When reset is clicked, reset initial state after the browser applies the reset
            form.addEventListener('reset', function(){ setTimeout(function(){ // allow browser reset to complete
                // Re-evaluate button state
                updateSaveButton();
                // clear validation messages
                allFields.forEach(f => { const err = f.parentElement.querySelector('.error-message'); if (err) err.innerText = ''; });
            }, 10); });

            confirmAddBtn.addEventListener('click', function(){ isConfirmed = true; confirmModal.style.display = 'none'; saveBtn.click(); });
            cancelModalBtn.addEventListener('click', function(){ confirmModal.style.display = 'none'; isConfirmed = false; });
            window.addEventListener('click', function(e){ if (e.target === confirmModal) { confirmModal.style.display = 'none'; isConfirmed = false; } });
        });

    // toast based on URL param
    (function(){ try{ const params = new URLSearchParams(window.location.search); const msg = params.get('msg'); if (!msg) return; const toast = document.createElement('div'); toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : ''); if (msg === 'updated') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Cập nhật nhà cung cấp thành công!'; else if (msg === 'error') toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra khi cập nhật.'; else return; document.body.appendChild(toast); setTimeout(()=>{ toast.classList.add('hide'); setTimeout(()=>toast.remove(),300); },3000); const newUrl = window.location.pathname + '?page=supplier/updateSupplier&id=<?php echo $supplier["supplier_id"]; ?>'; window.history.replaceState({},'', newUrl); }catch(e){console.error(e);} })();
    </script>
</body>
</html>