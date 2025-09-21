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
td a:hover { color: #FF7700; }

/* Modal */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; }
.modal-content {
    max-width:400px; margin:100px auto; background:#fff; padding:20px;
    border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;
}
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); }
</style>

<body>
<div class="header-users">
    <div class="header-left">
        <h3>QUẢN LÝ VAI TRÒ</h3>
        <p>Tạo và quản lý vai trò trong hệ thống</p>
    </div>

    <div class="header-right">
        <button class="btn-add-role">
            <a href="index.php?page=roles/createRoles">+ Thêm vai trò</a>
        </button>
    </div>
</div>

<div class="container qlnl">
    <div style="overflow:auto; height: 500px;">
        <?php
            $roles = $p->getAllRoles();
            var_dump($roles);
            if ($roles && is_array($roles)) {
                echo '<table>';
                echo '
                    <thead>
                        <tr>
                            <th>Mã vai trò</th>
                            <th>Tên vai trò</th>
                            <th>Mô tả</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                ';
                foreach ($roles as $r) {
        ?>
                    <tr>
                        <td><?php echo $r['role_id'] ?? ($r['_id'] ?? ''); ?></td>
                        <td><?php echo $r['role_name'] ?? ''; ?></td>
                        <td><?php echo $r['description'] ?? ''; ?></td>
                        <td>
                            <a href="index.php?page=roles/updateRoles&id=<?php echo $r['role_id'] ?? ($r['_id'] ?? ''); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="#" class="btn-delete" data-id="<?php echo $r['role_id'] ?? ($r['_id'] ?? ''); ?>" title="Xóa" style="margin-left:10px; color:red;">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
        <?php
                }
                echo '</tbody></table>';
            } else {
                echo "<p>Không có vai trò nào.</p>";
            }
        ?>
    </div>
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
    btn.addEventListener('click', function(e){
        e.preventDefault();
        deleteRoleId = this.dataset.id;
        deleteModal.style.display = 'block';
    });
});

// Hủy
cancelBtn.addEventListener('click', function(){
    deleteModal.style.display = 'none';
    deleteRoleId = null;
});

// Xác nhận xóa
confirmDeleteBtn.addEventListener('click', function(){
    if(deleteRoleId){
        fetch('roles/deleteRoles/process.php?id=' + deleteRoleId)
            .then(response => response.text())
            .then(() => {
                deleteModal.style.display = 'none';
                window.location.reload();
            })
            .catch(err => console.error('Lỗi xóa role:', err));
    }
});
</script>
</body>
