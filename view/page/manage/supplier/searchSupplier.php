<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("../../../../controller/cSupplier.php");
$cSupplier = new CSupplier();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $suppliers = $cSupplier->searchSuppliersByName($q);

    if (empty($suppliers)) {
        echo "<div class='no-result'>Không tìm thấy nhà cung cấp</div>";
    } else {
        echo "
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên nhà cung cấp</th>
                        <th>Liên hệ</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($suppliers as $supplier) {
            $id       = $supplier['supplier_id'];
            $name     = $supplier['supplier_name'];
            $contact  = $supplier['contact'];
            $status   = $supplier['status'];

            echo "
                <tr onclick=\"window.location.href='index.php?page=supplier/updateSupplier&id={$id}'\">
                    <td>{$id}</td>
                    <td>{$name}</td>
                    <td>{$contact}</td>
                    <td>" . ($status == 1 ? 'Đang hoạt động' : 'Ngừng hoạt động') . "</td>
                </tr>
            ";
        }

        echo "</tbody></table>";
    }
}
?>

<style>
    /* Kết quả tìm kiếm */
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

    .qlkho-search input {
        border: none;
        outline: none;
        font-size: 14px;
        padding: 4px 6px;
        width: 300px; /* Tăng độ rộng của ô tìm kiếm */
    }
</style>

