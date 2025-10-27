<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once(__DIR__ . '/../../../../controller/cRoles.php');
$p = new CRoles();
$roles = $p->getAllRoles();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Quản lý vai trò</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      background: #f6f8fa;
      font-family: "Segoe UI", Tahoma, sans-serif;
      color: #333;
      margin: 0;
    }

    .role-container {
      max-width: 1000px;
      margin: 10px auto;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
      overflow: hidden;
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .header {
      padding: 25px 30px;
      border-bottom: 1px solid #e9ecef;
      background: #fff;
      text-align: center;
    }

    .header h2 {
      margin: 0;
      font-size: 26px;
      color: #333;
      font-weight: 700;
    }

    .header p {
      margin: 6px 0 0;
      color: #666;
    }

    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 35px;
      background: #fafbfc;
      border-bottom: 1px solid #eee;
      flex-wrap: wrap;
      gap: 10px;
    }

    .search-box {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 8px 14px;
      width: 350px;
    }

    .search-box input {
      border: none;
      outline: none;
      font-size: 14px;
      flex: 1;
    }

    .btn-add {
      background: #3b82f6;
      color: white;
      padding: 10px 18px;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      transition: 0.25s;
    }

    .btn-add:hover {
      background: #2563eb;
      transform: translateY(-2px);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    th, td {
      padding: 12px 14px;
      text-align: center;
      border-bottom: 1px solid #e1e4e8;
      font-size: 15px;
    }

    th {
      background: #f9fafb;
      font-weight: 600;
    }

    tr:hover td {
      background: #f1f7ff;
    }

    .actions {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .btn-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 6px;
      transition: 0.2s;
      font-size: 17px;
    }

    .btn-edit { color: #3b82f6; }
    .btn-delete { color: #ef4444; }
    .btn-icon:hover { transform: scale(1.1); }

    #searchResult {
      position: absolute;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      max-height: 200px;
      overflow-y: auto;
      display: none;
      z-index: 99;
      width: 340px;
      margin-top: 5px;
    }

    #searchResult div {
      padding: 10px;
      cursor: pointer;
    }

    #searchResult div:hover {
      background: #f1f1f1;
    }

    .no-data {
      text-align: center;
      padding: 20px;
      color: #888;
      font-style: italic;
    }
  </style>
</head>
<body>

<div class="role-container">
  <div class="header">
    <h2><i class="fa-solid fa-user-shield"></i> Quản lý vai trò</h2>
    <p>Tạo và quản lý vai trò trong hệ thống</p>
  </div>

  <div class="toolbar">
    <div class="search-container" style="position: relative;">
      <div class="search-box">
        <i class="fas fa-search" style="color:#666;"></i>
        <input type="text" id="searchInput" placeholder="Tìm kiếm vai trò...">
      </div>
      <div id="searchResult"></div>
    </div>

    <a href="index.php?page=roles/createRoles" class="btn-add">
      <i class="fa-solid fa-plus"></i> Thêm vai trò
    </a>
  </div>

  <div style="padding: 20px 30px;">
    <table>
      <thead>
        <tr>
          <th>Mã vai trò</th>
          <th>Tên vai trò</th>
          <th>Mô tả</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($roles)): ?>
          <?php foreach ($roles as $role): ?>
            <tr>
              <td><?= htmlspecialchars($role['role_id']) ?></td>
              <td><?= htmlspecialchars($role['role_name']) ?></td>
              <td><?= htmlspecialchars($role['description']) ?></td>
              <td>
                <div class="actions">
                  <a href="index.php?page=roles/updateRoles&id=<?= urlencode($role['role_id']) ?>" class="btn-icon btn-edit" title="Sửa">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a href="#" class="btn-icon btn-delete" data-id="<?= htmlspecialchars($role['role_id']) ?>" title="Xóa">
                    <i class="fas fa-trash-alt"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" class="no-data">Không có vai trò nào.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const searchResult = document.getElementById('searchResult');

// --- Tìm kiếm vai trò ---
searchInput.addEventListener('input', function() {
  const query = this.value.trim();
  if (query.length > 0) {
    fetch(`../../../view/page/manage/roles/searchRoles.php?q=${encodeURIComponent(query)}`)
      .then(res => res.text())
      .then(data => {
        searchResult.innerHTML = data;
        searchResult.style.display = 'block';
      })
      .catch(console.error);
  } else {
    searchResult.style.display = 'none';
  }
});

document.addEventListener('click', e => {
  if (!searchResult.contains(e.target) && e.target !== searchInput) {
    searchResult.style.display = 'none';
  }
});

// --- Xác nhận xóa bằng SweetAlert ---
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    const id = btn.dataset.id;

    Swal.fire({
      title: 'Xác nhận xóa vai trò?',
      text: 'Hành động này không thể hoàn tác!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Xóa',
      cancelButtonText: 'Hủy'
    }).then(result => {
      if (result.isConfirmed) {
        fetch(`../../../view/page/manage/roles/deleteRoles/deleteRoles.php?id=${id}`)
          .then(res => res.text())
          .then(data => {
            Swal.fire({
              icon: 'success',
              title: 'Đã xóa!',
              text: data,
              timer: 1200,
              showConfirmButton: false
            });
            setTimeout(() => window.location.reload(), 1300);
          })
          .catch(err => console.error('Lỗi:', err));
      }
    });
  });
});
</script>
</body>
</html>
