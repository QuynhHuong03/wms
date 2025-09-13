<?php
include_once("../../../controller/cWarehouse.php");
$cWarehouse = new CWarehouse();

$warehouses = [];
$search = '';
if (isset($_POST['btnSearch']) && !empty($_POST['txtSearch'])) {
    $search = trim($_POST['txtSearch']);
    $result = $cWarehouse->searchWarehousesByName($search);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $warehouses[] = $row;
        }
    }
} else {
    // Lấy tất cả kho như cũ
    $types = $cWarehouse->getTypes();
    if (is_array($types)) {
        foreach ($types as $type) {
            $list = $cWarehouse->getWarehousesByType($type['id']);
            if ($list && $list->num_rows > 0) {
                while ($row = $list->fetch_assoc()) {
                    $row['type_name'] = $type['name'];
                    $warehouses[] = $row;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quản lý kho</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }
        .container.qlkho {
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
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }
        .status-inactive {
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
</head>
<body>
    <div style="width: 90%; max-width: 1400px; margin: 20px auto; display:flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="color:#333; margin:0;">QUẢN LÝ KHO</h3>
            <p style="margin: 4px 0 0; color:#666; font-size:14px;">
                Danh sách kho tổng và kho chi nhánh
            </p>
        </div>
        <div>
            <a href="?page=warehouse/createWarehause" style="text-decoration:none;">
                <button style="background:#10b981; color:#fff; padding:8px 18px; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">
                    + Thêm kho chi nhánh
                </button>
            </a>
        </div>
    </div>

    <div class="container qlkho">
        <?php
        if (isset($_GET['action']) && $_GET['action'] == 'add') {
            include_once("createWarehause/index.php");
        } else {
            if (!empty($warehouses)) {
                echo '<table>';
                echo "
                    <thead>
                        <tr>
                            <th>Mã kho</th>
                            <th>Tên kho</th>
                            <th>Địa chỉ</th>
                            <th>Loại kho</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                ";
                foreach ($warehouses as $w) {
                    $statusClass = $w['status'] == 1 ? 'status-active' : 'status-inactive';
                    $statusText = $w['status'] == 1 ? 'Đang hoạt động' : 'Ngừng hoạt động';
                    echo "
                    <tr>
                        <td>{$w['warehouse_id']}</td>
                        <td>{$w['warehouse_name']}</td>
                        <td>{$w['address']}</td>
                        <td>{$w['type_name']}</td>
                        <td><span class='$statusClass'>$statusText</span></td>
                    </tr>";
                }
                echo '</table>';
            } else {
                echo "<p>Không có kho nào.</p>";
            }
        }
        ?>
    </div>
</body>
</html>