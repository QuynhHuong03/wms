<?php
include_once(__DIR__ . "/../../../../../controller/cRoles.php");
$p = new CRoles();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm vai trò</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    /* Match create warehouse UI */
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

    .container {
        width: 90%; max-width: 700px; margin: 20px auto; background: #ffffff; border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08); padding: 30px 40px;
    }

    .form-group { margin-bottom: 20px; }
    .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#374151; }
    .form-group input, .form-group select { width:100%; padding:12px 15px; border:1px solid #d1d5db; border-radius:8px; font-size:1rem; background:#f9fafb; }
    .form-group input:focus, .form-group select:focus { border-color:#2563eb; outline:none; background:#fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }

    .form-actions { display:flex; justify-content:flex-end; gap:12px; margin-top:30px; }
    .form-actions a, .form-actions button { padding:12px 25px; border-radius:8px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
    .btn-success { background:#10b981; color:#fff; }
    .btn-success:hover { background:#059669; }
    .btn-secondary { background:#9ca3af; color:#fff; }
    .btn-secondary:hover { background:#6b7280; }

    /* Modal */
    .modal { display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; overflow:auto; background: rgba(0,0,0,0.5); }
    .modal-content { background:#fff; max-width:450px; margin:15vh auto; padding:35px; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.3); text-align:center; }
    .modal-actions { display:flex; gap:12px; justify-content:center; margin-top:20px; }
    #cancelModalBtn { background:#e5e7eb; color:#374151; padding:12px 28px; border-radius:8px; border:none; }
    #confirmAddBtn { background:#10b981; color:#fff; padding:12px 28px; border-radius:8px; border:none; }

    @media (max-width:576px) { .container { padding:20px; } .page-header { margin-top:15px; } .form-actions { flex-direction:column; } }
    </style>
    </head>
    <body>

    <div class="page-header">
      <h2>Thêm vai trò</h2>
      <p>Thêm mới vai trò cho hệ thống</p>
    </div>

    <div class="container">
      <form id="roleForm" action="/KLTN/view/page/manage/roles/createRoles/process.php" method="POST" onsubmit="return handleSubmit(event)">
        <div class="form-group">
          <label for="role_name">Tên vai trò</label>
          <input type="text" id="role_name" name="role_name" required>
        </div>

        <div class="form-group">
          <label for="description">Mô tả</label>
          <input type="text" id="description" name="description" required>
        </div>

        <div class="form-group">
          <label for="status">Trạng thái</label>
          <select id="status" name="status" required>
              <option value="1">Hoạt động</option>
              <option value="2">Không hoạt động</option>
          </select>
        </div>

        <div class="form-group">
          <label for="create_at">Ngày tạo</label>
          <input type="text" id="create_at" name="create_at" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
        </div>

        <div class="form-actions">
          <a href="/KLTN/view/page/manage/index.php?page=roles" class="btn-secondary">Quay lại</a>
          <button type="reset" class="btn-secondary">Hủy</button>
          <button type="submit" class="btn-success">Thêm</button>
        </div>
      </form>
    </div>

    <!-- Modal xác nhận thêm -->
    <div id="confirmModal" class="modal">
      <div class="modal-content">
        <h3>Xác nhận thêm vai trò</h3>
        <p>Bạn có chắc chắn muốn thêm vai trò này không?</p>
        <div class="modal-actions">
          <button id="cancelModalBtn" type="button">Hủy</button>
          <button id="confirmAddBtn" type="button">Xác nhận</button>
        </div>
      </div>
    </div>

    <script>
    // Modal confirm logic
    const confirmModal = document.getElementById('confirmModal');
    const confirmAddBtn = document.getElementById('confirmAddBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const form = document.getElementById('roleForm');
    let isConfirmed = false;

    function handleSubmit(e) {
      if (!isConfirmed) {
        e.preventDefault();
        confirmModal.style.display = 'block';
        return false;
      }
      return true;
    }

    confirmAddBtn.addEventListener('click', function() {
        isConfirmed = true;
        confirmModal.style.display = 'none';
        form.submit();
    });

    cancelModalBtn.addEventListener('click', function() {
        confirmModal.style.display = 'none';
        isConfirmed = false;
    });

    window.addEventListener('click', function(event) {
        if (event.target === confirmModal) {
            confirmModal.style.display = 'none';
            isConfirmed = false;
        }
    });
    </script>

    </body>
    </html>
