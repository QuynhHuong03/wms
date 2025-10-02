<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once(__DIR__ . '/../../../../controller/cRoles.php');
$p = new CRoles();
?>

<style>
/* Dùng lại CSS từ user */
body {
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 0;
}
.container.qlnl {
    width: 100%;
    max-width: 1200px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
}
button {
    border: none;
    border-radius: 10px;
    cursor: pointer;
    background-color: #3b82f6;
    color: white;
    font-weight: bold;
    transition: background-color 0.3s ease;
}
button a {
    text-decoration: none;
    color: white;
}
.header-users {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin: 20px 0;
}
.header-left h3 {
    margin: 0;
    color: #333;
}
.header-left p {
    margin: 4px 0 0;
    color: #666;
    font-size: 14px;
}
.btn-add-role {
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s;
}
.btn-add-role a {
    color: white;
    text-decoration: none;
}
.btn-add-role:hover {
    background: #2563eb;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
thead {
    background-color: #3b82f6;
    color: white;
    font-size: 16px;
    text-align: left;
}
thead th {
    padding: 10px;
}
tbody tr:nth-child(odd) { background-color: #f2f2f2; }
tbody tr:nth-child(even) { background-color: #ffffff; }
tbody td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}
td a {
    color: #3b82f6;
    text-decoration: none;
    font-size: 18px;
}

/* Modal */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; }
.modal-content {
    max-width:400px; margin:100px auto; background:#fff; padding:20px;
    border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;
}
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); }

/* Search */
.qlnl-search-container {
    position: relative;
    display: flex;
    align-items: center;
}
.qlnl-search {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: #fff;
    width: 400px; /* Kéo dài ô tìm kiếm */
}
.qlnl-search i {
    color: #666;
    font-size: 18px; /* Kích thước icon */
}
.qlnl-search input {
    border: none;
    outline: none;
    font-size: 14px;
    padding: 4px 6px;
    flex: 1; /* Để input chiếm toàn bộ không gian còn lại */
}
#searchResult {
    position: absolute;
    top: 110%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-height: 250px;
    overflow-y: auto;
    display: none;
    z-index: 99;
}
#searchResult div {
    padding: 10px;
    cursor: pointer;
}
#searchResult div:hover {
    background: #f1f1f1;
}

.action-buttons {
    display: flex;
    gap: 5px; /* Khoảng cách giữa các icon */
}

.action-buttons a {
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.btn-edit {
    color: #3b82f6;
}

.btn-delete {
     /* Màu nền icon Xóa */
    color:  #ef4444;
}


.btn-edit i, .btn-delete i {
    font-size: 18px; /* Kích thước icon */
}
</style>

<body>
<div class="header-users">
    <div class="header-left">
        <h3>QUẢN LÝ VAI TRÒ</h3>
        <p>Tạo và quản lý vai trò trong hệ thống</p>
    </div>

    <div class="header-right">
        <div class="qlnl-search-container">
            <div class="qlnl-search">
                <i class="fas fa-search"></i>
                <input id="searchInput" type="text" placeholder="Tìm kiếm vai trò...">
            </div>
            <div id="searchResult"></div>
                    <button class="btn-add-role" style="margin-left: 10px;">
            <a href="index.php?page=roles/createRoles">+ Thêm vai trò</a>
        </button>

        </div>
    </div>
</div>

<div class="container qlnl">
    <table>
        <thead>
            <tr>
                <th>Mã vai trò</th>
                <th>Tên vai trò</th>
                <th>Mô tả</th>
                <th>Thao tác</th> <!-- Thêm cột Thao tác -->
            </tr>
        </thead>
        <tbody>
            <?php
            $roles = $p->getAllRoles();
            if (!empty($roles)) {
                foreach ($roles as $role) {
                    echo "
                        <tr>
                            <td>{$role['role_id']}</td>
                            <td>{$role['role_name']}</td>
                            <td>{$role['description']}</td>
                            <td>
                                <div class='action-buttons'>
                                    <a href='index.php?page=roles/updateRoles&id={$role['role_id']}' class='btn-edit' title='Sửa'>
                                        <i class='fas fa-edit'></i>
                                    </a>
                                    <a href='#' class='btn-delete' data-id='{$role['role_id']}' title='Xóa'>
                                        <i class='fas fa-trash-alt'></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    ";
                }
            } else {
                echo "<tr><td colspan='4'>Không có vai trò nào</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Modal Xác nhận Xóa -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>Xác nhận xóa</h3>
        <p>Bạn có chắc chắn muốn xóa vai trò này?</p>
        <div style="margin-top:20px;">
            <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
            <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
        </div>
    </div>
    <div class="modal-overlay"></div>
</div>

<script>
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelBtn = document.getElementById('cancelBtn');
let deleteRoleId = null;

// Mở modal khi nhấn nút xóa
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const roleId = this.dataset.id;

        if (confirm('Bạn có chắc chắn muốn xóa vai trò này?')) {
            fetch(`../../../view/page/manage/roles/deleteRoles/deleteRoles.php?id=${roleId}`)
                .then(response => response.text())
                .then(data => {
                    alert(data);
                    window.location.reload(); // Reload lại trang sau khi xóa
                })
                .catch(err => console.error('Lỗi xóa vai trò:', err));
        }
    });
});

// Tìm kiếm vai trò
const searchInput = document.getElementById('searchInput');
const searchResult = document.getElementById('searchResult');

searchInput.addEventListener('input', function() {
    const query = searchInput.value.trim();
    if (query !== '') {
        fetch(`../../../view/page/manage/roles/searchRoles.php?q=${query}`)
            .then(response => response.text())
            .then(data => {
                searchResult.innerHTML = data;
                searchResult.style.display = 'block';
            })
            .catch(error => console.error('Error:', error));
    } else {
        searchResult.style.display = 'none';
    }
});

document.addEventListener('click', function(event) {
    if (!searchResult.contains(event.target) && event.target !== searchInput) {
        searchResult.style.display = 'none';
    }
});
</script>
</body>
