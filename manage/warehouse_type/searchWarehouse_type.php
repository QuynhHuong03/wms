<?php
include("../../../../controller/cWarehouse_type.php");
$cWarehouseType = new CWarehouseType();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $warehouseTypes = $cWarehouseType->searchWarehouseTypesByName($q);

    if (empty($warehouseTypes)) {
        echo "<div class='no-result'>Không tìm thấy loại kho</div>";
    } else {
        echo "
            <table>
                <thead>
                    <tr>
                        <th>Mã loại kho</th>
                        <th>Tên loại kho</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($warehouseTypes as $type) {
            $id   = $type['warehouse_type_id'];
            $name = $type['name'];

            echo "
                <tr onclick=\"window.location.href='index.php?page=warehouse_type/updateWarehouse_type&id={$id}'\">
                    <td>{$id}</td>
                    <td>{$name}</td>
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