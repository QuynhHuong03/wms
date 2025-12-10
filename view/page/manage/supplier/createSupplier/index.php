<?php
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Reuse the create user form styles for supplier form */
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

.page-header h2 {
        margin: 0;
        color: #111827;
        font-size: 2rem;
        font-weight: 700;
}

.page-header p {
        margin: 5px 0 0;
        color: #6b7280;
        font-size: 1rem;
}

.container {
        width: 90%;
        max-width: 700px;
        margin: 20px auto;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        padding: 30px 40px;
}

.form-group { margin-bottom: 18px; }
.form-group label { display:block; margin-bottom:8px; font-weight:600; color:#374151; }
.form-group input, .form-group select {
        width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; background:#f9fafb; box-sizing:border-box;
}
.form-group input:focus { border-color:#2563eb; background:#fff; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,0.15); }

/* Error message style (validation) */
.error-message {
    display: block;
    margin-top: 8px;
    color: #ef4444;
    font-size: 0.85rem;
}

.form-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:20px; }
.form-actions a, .form-actions button { padding:12px 20px; border-radius:8px; font-weight:600; border:none; cursor:pointer; text-decoration:none; }
.btn-success { background:#10b981; color:#fff; }
.btn-success:hover { background:#059669; }
/* Disabled visual state for primary button (class-based, keeps element visible) */
.btn-success.is-disabled {
    background-color: #d1d5db !important;
    color: #ffffff !important;
    cursor: not-allowed !important;
    opacity: 0.8 !important;
    box-shadow: none !important;
    pointer-events: none !important;
}
.btn-secondary { background:#9ca3af; color:#fff; }
.btn-secondary:hover { background:#6b7280; }
.back-btn { background:#e5e7eb; color:#4b5563; }

/* Modal */
.modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.5); }
.modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:35px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center; }
.modal-actions { display:flex; gap:12px; justify-content:center; }
#cancelModalBtn { background:#e5e7eb; color:#374151; padding:12px 28px; border-radius:8px; border:none; }
#confirmAddBtn { background:#10b981; color:#fff; padding:12px 28px; border-radius:8px; border:none; }

@media (max-width:576px) {
    .container { padding:20px; }
    .page-header { padding-left:5px; margin-top:15px; }
    .page-header h2 { font-size:1.5rem; }
    .form-actions { flex-direction:column; align-items:stretch; }
}

/* Toast notification */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #10b981;
    color: white;
    padding: 16px 24px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
}
.toast-notification.error { background: #ef4444; }
@keyframes slideIn {
    from { transform: translateX(400px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(400px); opacity: 0; }
}
.toast-notification.hide { animation: slideOut 0.3s ease-out forwards; }
</style>

<div class="page-header">
    <h2>Thêm nhà cung cấp</h2>
    <p>Thêm mới nhà cung cấp vào hệ thống</p>
</div>

<div class="container">
    <form action="/view/page/manage/supplier/createSupplier/process.php" method="post" onsubmit="return validateForm()">

        <div class="form-group">
            <label for="supplier_name">Tên nhà cung cấp</label>
            <input type="text" id="supplier_name" name="supplier_name" placeholder="Nhập tên nhà cung cấp">
            <span class="error-message"></span>
        </div>

        <div class="form-group">
            <label for="contact">Liên hệ</label>
            <input type="text" id="contact" name="contact" placeholder="Số điện thoại hoặc email liên hệ">
            <span class="error-message"></span>
        </div>

        <div class="form-group">
            <label for="contact_name">Tên người liên hệ</label>
            <input type="text" id="contact_name" name="contact_name" placeholder="Tên người liên hệ">
            <span class="error-message"></span>
        </div>

        <div class="form-group">
            <label for="tax_code">Mã số thuế</label>
            <input type="text" id="tax_code" name="tax_code" placeholder="Mã số thuế (nếu có)">
            <span class="error-message"></span>
        </div>

        <div class="form-group">
            <label for="country">Quốc gia</label>
            <input type="text" id="country" name="country" placeholder="Quốc gia">
            <span class="error-message"></span>
        </div>

        <div class="form-group">
            <label for="description">Mô tả</label>
            <input type="text" id="description" name="description" placeholder="Ghi chú hoặc mô tả ngắn">
            <span class="error-message"></span>
        </div>

        <div class="form-actions">
            <a href="index.php?page=supplier" class="back-btn">Quay lại</a>
            <button type="reset" class="btn-secondary">Hủy</button>
            <button type="submit" class="btn-success is-disabled" id="saveBtn" aria-disabled="true">Thêm</button>
        </div>

    </form>
</div>

<!-- Modal xác nhận thêm -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <h3>Xác nhận thêm nhà cung cấp</h3>
        <p>Bạn có chắc chắn muốn thêm nhà cung cấp này không?</p>
        <div class="modal-actions">
            <button type="button" id="cancelModalBtn">Hủy</button>
            <button type="button" id="confirmAddBtn">Xác nhận</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const form = document.querySelector('form');
    // collect inputs we consider required (description optional)
    const requiredIds = ['supplier_name','contact','contact_name','tax_code','country'];
    const inputs = form.querySelectorAll('input');
    const saveBtn = document.getElementById('saveBtn');
    const confirmModal = document.getElementById('confirmModal');
    const confirmAddBtn = document.getElementById('confirmAddBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    let isConfirmed = false;

    // mark touched
    inputs.forEach(i => i.dataset.touched = 'false');

    function validateField(field){
        const v = field.value.trim();
        const err = field.parentElement.querySelector('.error-message');
        if (err) err.innerText = '';
        // Only validate required fields
        if (requiredIds.includes(field.id) && v === '') {
            if (err) err.innerText = 'Trường này là bắt buộc';
            return false;
        }
        return true;
    }

    function updateSaveButton(){
        // enable only when all required fields have non-empty values
        let allFilled = true;
        requiredIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el || el.value.trim() === '') allFilled = false;
        });
        if (allFilled) {
            saveBtn.classList.remove('is-disabled');
            saveBtn.removeAttribute('aria-disabled');
        } else {
            saveBtn.classList.add('is-disabled');
            saveBtn.setAttribute('aria-disabled','true');
        }
    }

    function validateForm(){
        let ok = true;
        requiredIds.forEach(id => {
            const f = document.getElementById(id);
            if (f && !validateField(f)) ok = false;
        });
        return ok;
    }

    // wire up events to validate on input/change/blur and update button
    inputs.forEach(field => {
        if (field.tagName.toLowerCase() === 'input') {
            field.addEventListener('input', function(){
                this.dataset.touched = 'true';
                validateField(this);
                updateSaveButton();
            });
            field.addEventListener('blur', function(){ this.dataset.touched = 'true'; validateField(this); updateSaveButton(); });
            field.addEventListener('change', function(){ this.dataset.touched = 'true'; validateField(this); updateSaveButton(); });
        }
    });

    // initial check
    updateSaveButton();

    form.addEventListener('submit', function(e){
        // prevent submission while button visually disabled
        if (saveBtn.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return false; }

        if (!validateForm()) { e.preventDefault(); return false; }
        if (!isConfirmed) { e.preventDefault(); confirmModal.style.display = 'block'; return false; }
        // otherwise allow submit
    });

    confirmAddBtn.addEventListener('click', function(){ isConfirmed = true; confirmModal.style.display = 'none'; saveBtn.click(); });
    cancelModalBtn.addEventListener('click', function(){ confirmModal.style.display = 'none'; isConfirmed = false; });
    window.addEventListener('click', function(e){ if (e.target === confirmModal) { confirmModal.style.display = 'none'; isConfirmed = false; } });
});

// --- Show toast notifications based on URL param `msg` ---
(function(){
    try {
        const params = new URLSearchParams(window.location.search);
        const msg = params.get('msg');
        if (!msg) return;
        const toast = document.createElement('div');
        toast.className = 'toast-notification ' + (msg === 'error' ? 'error' : '');
        if (msg === 'success') toast.innerHTML = '<i class="fa-solid fa-circle-check"></i> Thêm nhà cung cấp thành công!';
        else if (msg === 'error') toast.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Có lỗi xảy ra khi thêm nhà cung cấp.';
        else return;
        document.body.appendChild(toast);
        setTimeout(() => { toast.classList.add('hide'); setTimeout(() => toast.remove(), 300); }, 3000);
        // remove msg param from URL (keep page path)
        const newUrl = window.location.pathname + '?page=supplier/createSupplier';
        window.history.replaceState({}, '', newUrl);
    } catch(e) { console.error(e); }
})();
</script>
