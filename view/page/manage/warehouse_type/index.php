<?php
include_once(__DIR__ . "/../../../../controller/cWarehouse_type.php");
$cWarehouseType = new CWarehouseType();

$warehouseTypes = $cWarehouseType->getAllWarehouseTypes();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý loại kho</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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

        .header-warehouse {
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .qlkho-search-container {
            position: relative;
        }

        .qlkho-search {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #fff;
            width: 400px;
        }

        .qlkho-search i {
            color: #666;
            font-size: 18px;
        }

        .qlkho-search input {
            border: none;
            outline: none;
            font-size: 14px;
            padding: 4px 6px;
            flex: 1;
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

        .btn-add-warehouse {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-add-warehouse a {
            color: white;
            text-decoration: none;
        }

        .btn-add-warehouse:hover {
            background: #2563eb;
        }

        @media (max-width: 600px) {
            .header-warehouse {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .header-right {
                width: 100%;
                justify-content: flex-start;
                gap: 8px;
            }

            .qlkho-search input {
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

        td a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 18px;
        }

        td a:hover {
            color: #2563eb;
        }

        .status-active, .status-inactive {
            background: none;
            color: #333;
            padding: 0;
            font-size: 14px;
            font-weight: normal;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-buttons a {
            text-decoration: none;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background-color: #10b981;
        }

        .btn-edit:hover {
            background-color: #059669;
        }

        .btn-delete {
            margin-left: 10px;
            color: red;
        }

        .btn-delete:hover {
            color: #dc2626;
        }

        .btn-edit i, .btn-delete i {
            font-size: 16px;
        }
    </style>
</head>
<body>
        <div class="header-warehouse">
            <div class="header-left">
                <h3>QUẢN LÝ LOẠI KHO</h3>
                <p>Danh sách các loại kho</p>
            </div>

            <div class="header-right">
                <div class="qlkho-search-container">
                    <div class="qlkho-search">
                        <i class="fas fa-search"></i>
                        <input id="searchInput" type="text" placeholder="Tìm kiếm...">
                    </div>
                    <div id="searchResult"></div>
                </div>

                <button class="btn-add-warehouse">
                    <a href="index.php?page=warehouse_type/createWarehouse_type">+ Thêm loại kho</a>
                </button>
            </div>
    <div class="container qlkho">
        <?php

        if (is_array($warehouseTypes) && !empty($warehouseTypes)) {
            echo '<table>';
            echo '
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã loại kho</th>
                        <th>Tên loại kho</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
            ';
            foreach ($warehouseTypes as $type) {
                echo "
                    <tr>
                        <td>{$type['id']}</td>
                        <td>{$type['warehouse_type_id']}</td>
                        <td>{$type['name']}</td>
                        <td>
                            <a href='index.php?page=warehouse_type/updateWarehouse_type&id={$type['warehouse_type_id']}' title='Sửa' style='margin-right:10px; color:#3b82f6;'>
                                <i class='fas fa-edit'></i>
                            </a>
                            <a href='#' class='btn-delete' data-id='" . $type['warehouse_type_id'] . "' title='Xóa' style='margin-left:10px; color:red;'>
                                <i class='fas fa-trash-alt'></i>
                            </a>
                        </td>
                    </tr>
                ";
            }
            echo '</tbody></table>';
        } else {
            echo "<p>Không có loại kho nào.</p>";
        }
        ?>
        <div class="col-md-4" style="padding-top: 20px;">
            <button>
                <a href="index.php?page=manage" style="text-decoration: none; color: inherit;">Quay lại</a>
            </button>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const searchResult = document.getElementById('searchResult');

        searchInput.addEventListener('input', function() {
            const query = searchInput.value.trim();
            if (query !== '') {
                fetch(`../../../view/page/manage/warehouse_type/searchWarehouse_type.php?q=${query}`)
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

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const warehouseTypeId = this.dataset.id;

                if (confirm('Bạn có chắc chắn muốn xóa loại kho này?')) {
                    fetch(`../../../view/page/manage/warehouse_type/deleteWarehouse_type/index.php?id=${warehouseTypeId}`)
                        .then(response => response.text())
                        .then(data => {
                            alert(data);
                            window.location.reload();
                        })
                        .catch(err => console.error('Lỗi xóa loại kho:', err));
                }
            });
        });
    </script>
</body>
</html>