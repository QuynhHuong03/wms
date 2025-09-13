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

.qlnl-search input {
    border: 1px solid #ccc;
    border-radius: 5px;
    outline: none;
    width: 300px;
    margin-right: 10px;
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
    color: #FF7700;
}
</style>

<?php
    error_reporting();
    include("../../../controller/cUsers.php");
    $p = new CUsers();
?>

<body>
    <div style="width: 90%; max-width: 1400px; margin: 20px auto; display:flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="color:#333; margin:0;">QUẢN LÝ NGƯỜI DÙNG</h3>
            <p style="margin: 4px 0 0; color:#666; font-size:14px;">
                Tạo và quản lý tài khoản người dùng
            </p>
        </div>
        
        <button style="background-color: #3b82f6; border-radius: 10px; padding:8px 16px; border:none;">
            <a href="index.php?page=users/createUsers" style="color:white; text-decoration:none;">+ Thêm người dùng</a>
        </button>
    </div>

    <!-- Form tìm kiếm -->
    <form action="" method="post" name="frmSearch">
        <div class="qlnl-search" style="display: flex;">
            <input name="txtTK" placeholder="Tìm kiếm theo tên..." type="text" 
                value="<?php echo isset($_POST['txtTK']) ? $_POST['txtTK'] : ''; ?>" />
            <button name="btnTK" type="submit" style="background: #3b82f6; color: white;">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </form>

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

                    $roleName = trim(mb_strtolower($r['role_name'], 'UTF-8'));
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
                        <td><?php echo $r['fullname'] ?? ''; ?></td>
                        <td><?php echo $r['email'] ?? ''; ?></td>
                        <td><?php echo isset($r['gender']) ? ($r['gender'] == 1 ? 'Nam' : 'Nữ') : ''; ?></td>
                        <td><?php echo $r['phone'] ?? ''; ?></td>
                        <td>
                            <span class="role-badge <?php echo $roleClass; ?>">
                                <?php echo $r['role_name'] ?? ''; ?>
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
</body>
