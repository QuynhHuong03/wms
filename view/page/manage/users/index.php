<style>
body {
    font-family: Arial, sans-serif;
    background-color: #f9f9f9;
    margin: 0;
    padding: 0;
}

.container.qlnl {
    width: 100%;
    max-width: 1400px;
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

/* Phần tìm kiếm + nút thêm */
.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

/* Ô tìm kiếm */
.qlnl-search-container {
    position: relative;
}

.qlnl-search {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #ddd;
    background: #fff;
}

.qlnl-search input {
    border: none;
    outline: none;
    font-size: 14px;
    padding: 4px 6px;
    width: 200px; /* hoặc max-width: 100%; nếu muốn responsive */
}

#searchResult {
    position: absolute;
    top: 110%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    max-height: 250px;
    overflow-y: auto;
    display: none;
    z-index: 99;
}

.btn-add-user {
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s;
}

.btn-add-user a {
    color: white;
    text-decoration: none;
}

.btn-add-user:hover {
    background: #2563eb;
}

/* Responsive nhỏ */
@media (max-width: 600px) {
    .header-users {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .header-right {
        width: 100%;
        justify-content: flex-start;
        gap: 8px;
    }

    .qlnl-search input {
        width: 150px;
    }
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
    position: sticky;
    top: 0;
    z-index: 10;
}

tbody tr:nth-child(odd) {
    background-color: #f2f2f2;
}

tbody tr:nth-child(even) {
    background-color: #ffffff;
}

tbody td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

.role-admin {
    background: #fde68a;
    color: #92400e;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
}
.role-manager {
    background: #e0f2fe;
    color: #1e3a8a;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
}
.role-staff {
    background: #f3f4f6;
    color: #374151;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 600;
}

.status-working {
    background-color: #d1fae5;
    color: #065f46;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
}

.status-left {
    background-color: #fee2e2;
    color: #991b1b;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
}

td a {
    color: #3b82f6;
    text-decoration: none;
    font-size: 18px;
}

td a:hover {
    /* color: #FF7700; */
}

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

</style>

<?php
    error_reporting();
    include_once(__DIR__ . '/../../../../controller/cUsers.php');
    $p = new CUsers();
?>

<body>
    <div class="header-users">
    <div class="header-left">
        <h3>QUẢN LÝ NGƯỜI DÙNG</h3>
        <p>Tạo và quản lý tài khoản người dùng</p>
    </div>

    <div class="header-right">
        <div class="qlnl-search-container">
            <div class="qlnl-search">
                <i class="fas fa-search" style="color:#888;"></i>
                <input id="searchInput" type="text" placeholder="Tìm kiếm...">
            </div>
            <div id="searchResult"></div>
        </div>

        <button class="btn-add-user">
            <a href="index.php?page=users/createUsers">+ Thêm người dùng</a>
        </button>
    </div>
</div>



    <div class="container qlnl ">
        <div style ="overflow: auto; height: 500px;">
        <?php
            if (isset($_POST['btnTK']) && !empty($_POST['txtTK'])) {
                $tblNV = $p->getAllNVbyName($_POST['txtTK']);
            } else {
                $tblNV = $p->getAllUsers();
            }

            if ($tblNV && is_array($tblNV)) {
                echo '<table>';
                echo '
                    <thead>
                        <tr>
                            <th>Mã nhân viên</th>
                            <th>Họ và tên</th>
                            <th>Email</th>
                            <th>Giới tính</th>
                            <th>Số điện thoại</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Kho</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                ';

                foreach ($tblNV as $r) {
                    $statusClass = $r['status'] == 1 ? 'status-working' : 'status-left';
                    $statusText = $r['status'] == 1 ? 'Đang làm việc' : 'Nghỉ việc';

                    // Lấy role_name từ role_info hoặc trực tiếp từ r
                    $roleNameRaw = '';
                    if (isset($r['role_info']['role_name'])) {
                        $roleNameRaw = $r['role_info']['role_name'];
                    } elseif (isset($r['role_name'])) {
                        $roleNameRaw = $r['role_name'];
                    }
                    
                    $roleName = trim(mb_strtolower($roleNameRaw, 'UTF-8'));
                    $roleClass = '';
                    switch ($roleName) {
                        case 'admin':
                            $roleClass = 'role-admin';
                            break;
                        case 'quản lý':
                        case 'quan ly':
                            $roleClass = 'role-manager';
                            break;
                        default:
                            $roleClass = 'role-staff';
                            break;
                    }
        ?>
                    <tr>
                        <td><?php echo $r['user_id'] ?? ($r['_id'] ?? ''); ?></td>
                        <td><?php echo $r['name'] ?? ''; ?></td>
                        <td><?php echo $r['email'] ?? ''; ?></td>
                        <td><?php echo isset($r['gender']) ? ($r['gender'] == 1 ? 'Nam' : 'Nữ') : ''; ?></td>
                        <td><?php echo $r['phone'] ?? ''; ?></td>
                        <td>
                            <span class="role-badge <?php echo $roleClass; ?>">
                                <?php echo $r['role_name'] ?? ($r['role_info']['role_name'] ?? ''); ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </td>
                        <td><?php echo $r['warehouse_id'] ?? ''; ?></td>
                        <td>
                            <a href="index.php?page=users/updateUsers&id=<?php echo $r['user_id'] ?? ($r['_id'] ?? ''); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <!-- Nút Xóa -->
                             
                            <a href="#" class="btn-delete" data-id="<?php echo $r['user_id'] ?? ($r['_id'] ?? ''); ?>" title="Xóa" style="margin-left:10px; color:red;">
                                <i class="fas fa-trash-alt"></i>
                            </a>

                        </td>
                    </tr>
        <?php
                }
                echo '</tbody></table>';
            } else {
                echo "<p>Không có nhân viên nào.</p>";
            }
        ?>
        </div>

        <div class="col-md-4" style="padding-top: 20px;">
            <button>
                <a href="index.php?page=quanly" style="text-decoration: none; color: inherit;">Quay lại</a>
            </button>
        </div>
    </div>

    <!-- Modal Xác nhận Xóa -->
<div id="deleteModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:400px; margin:100px auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 8px rgba(0,0,0,0.2); text-align:center;">
        <h3 style="margin-top:0;">Xác nhận xóa</h3>
        <p>Bạn có chắc chắn muốn xóa nhân viên này?</p>
        <div style="margin-top:20px;">
            <button id="cancelBtn" style="background:#ccc; color:#333; margin-right:10px; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Hủy</button>
            <button id="confirmDeleteBtn" style="background:red; color:white; padding:8px 16px; border:none; border-radius:8px; cursor:pointer;">Xóa</button>
        </div>
    </div>
    <div class="modal-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4);"></div>
</div>

<script>
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
const cancelBtn = document.getElementById('cancelBtn');
let deleteUserId = null;

// Bắt sự kiện click nút Xóa trên bảng
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(e){
        e.preventDefault();
        deleteUserId = this.dataset.id; // lưu ID user cần xóa
        deleteModal.style.display = 'block'; // hiển thị modal
    });
});

// Hủy xóa
cancelBtn.addEventListener('click', function(){
    deleteModal.style.display = 'none';
    deleteUserId = null;
});

// Xác nhận xóa
confirmDeleteBtn.addEventListener('click', function(){
    if(deleteUserId){
        fetch('users/deleteUsers/process.php?id=' + deleteUserId)
            .then(response => response.text())
            .then(() => {
                deleteModal.style.display = 'none'; // ẩn modal
                window.location.reload(); // reload lại trang sau khi xóa
            })
            .catch(err => console.error('Lỗi xóa user:', err));
    }
});
</script>

</body>
