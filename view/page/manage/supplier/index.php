<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once("../../../controller/cSupplier.php");
$cSupplier = new CSupplier();

$suppliers = $cSupplier->getAllSuppliers();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý nhà cung cấp</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
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
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .container.qlncc {
            width: 100%;
            max-width: 1400px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .header-ncc {
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

        .qlncc-search-container {
            position: relative;
        }

        .qlncc-search {
            display: flex;
            align-items: center;
            gap: 10px; /* Tăng khoảng cách giữa icon và input */
            padding: 8px 16px; /* Tăng padding */
            border-radius: 8px;
            border: 1px solid #ddd;
            background: #fff;
            width: 400px; /* Kéo dài ô tìm kiếm */
        }

        .qlncc-search i {
            color: #666; /* Màu của icon */
            font-size: 18px; /* Kích thước icon */
        }

        .qlncc-search input {
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

        #searchResult table {
            width: 100%;
            border-collapse: collapse;
        }

        #searchResult thead {
            background-color: #3b82f6;
            color: white;
            font-size: 14px;
            text-align: left;
        }

        #searchResult th, #searchResult td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        #searchResult tbody tr:hover {
            background-color: #f3f4f6;
            cursor: pointer;
        }

        #searchResult tbody tr {
            transition: background-color 0.3s ease;
        }

        #searchResult td {
            font-size: 14px;
            color: #333;
        }

        #searchResult .no-result {
            text-align: center;
            color: #999;
            padding: 12px;
        }

        .btn-add-ncc {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-add-ncc a {
            color: white;
            text-decoration: none;
        }

        .btn-add-ncc:hover {
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

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-buttons a {
            text-decoration: none;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: bold;
        }

        .action-buttons a i {
            font-size: 16px;
        }

        .action-buttons a[title="Sửa"] {
            color: #3b82f6;
        }

        .action-buttons a[title="Sửa"]:hover {
            color: #2563eb;
        }

        .action-buttons a[title="Xóa"] {
            color: red;
        }

        .action-buttons a[title="Xóa"]:hover {
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="header-ncc">
        <div class="header-left">
            <h3>Quản lý nhà cung cấp</h3>
            <p>Danh sách các nhà cung cấp</p>
        </div>
        <div class="header-right">
            <div class="qlncc-search-container">
                <div class="qlncc-search">
                    <i class="fas fa-search"></i> <!-- Icon kính lúp -->
                    <input id="searchInput" type="text" placeholder="Tìm kiếm nhà cung cấp...">
                </div>
                <div id="searchResult"></div>
            </div>
            <button class="btn-add-ncc">
                <a href="index.php?page=supplier/createSupplier">+ Thêm nhà cung cấp</a>
            </button>
        </div>
    </div>

    <div class="container qlncc">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tên nhà cung cấp</th>
                    <th>Liên hệ</th>
                    <th>Trạng thái</th> <!-- Thêm cột Status -->
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (is_array($suppliers) && !empty($suppliers)) {
                    foreach ($suppliers as $supplier) {
                        $id = $supplier['supplier_id'];
                        $name = $supplier['supplier_name'];
                        $contact = $supplier['contact'];
                        $status = $supplier['status'] == 1 ? 'Đang hoạt động' : 'Ngừng hoạt động'; // Xử lý trạng thái

                        echo "
                            <tr>
                                <td>{$id}</td>
                                <td>{$name}</td>
                                <td>{$contact}</td>
                                <td>{$status}</td> <!-- Hiển thị trạng thái -->
                                <td class='action-buttons'>
                                    <a href='index.php?page=supplier/updateSupplier&id=" . $id . "' title='Sửa'><i class='fas fa-edit'></i></a>
                                    <a href='#' class='btn-delete' data-id='" . $supplier['supplier_id'] . "' title='Xóa' style='color:red;'>
                                        <i class='fas fa-trash-alt'></i>
                                    </a>
                                </td>
                            </tr>
                        ";
                    }
                } else {
                    echo "<tr><td colspan='5' class='no-result'>Không có nhà cung cấp nào</td></tr>";
                }
                ?>
            </tbody>
        </table>
                <div class="col-md-4" style="padding-top: 20px;">
            <button>
                <a href="index.php?page=quanly" style="text-decoration: none; color: inherit;">Quay lại</a>
            </button>
        </div>
    </div>

<script>
    const searchInput = document.getElementById('searchInput');
    const searchResult = document.getElementById('searchResult');

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        if (query !== '') {
            fetch(`../../../view/page/manage/supplier/searchSupplier.php?q=${query}`)
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

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const supplierId = this.dataset.id;

            console.log(`ID: ${supplierId}`); // Kiểm tra ID
 //           console.log(`URL: ../../../view/page/manage/supplier/deleteSupplier/index.php?id=${supplierId}`); // Kiểm tra URL

            if (confirm('Bạn có chắc chắn muốn xóa nhà cung cấp này?')) {
                fetch(`../../../view/page/manage/supplier/deleteSupplier/index.php?id=${supplierId}`)
                    .then(response => response.text())
                    .then(data => {
                        alert(data);
                        window.location.reload(); // Reload lại trang sau khi xóa
                    })
                    .catch(err => console.error('Lỗi xóa nhà cung cấp:', err));
            }
        });
    });
</script>
</body>
</html>