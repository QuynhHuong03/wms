<?php
include("../../../../controller/cWarehouse.php");
$cWarehouse = new CWarehouse();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $warehouses = $cWarehouse->searchWarehousesByName($q);

    if (empty($warehouses)) {
        echo "<div class='no-result'>Không tìm thấy kho</div>";
    } else {
        echo "
            <table>
                <thead>
                    <tr>
                        <th>Mã kho</th>
                        <th>Tên kho</th>
                        <th>Địa chỉ</th>
                        <th>Loại kho</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($warehouses as $warehouse) {
            $id     = $warehouse['warehouse_id'];
            $name   = $warehouse['warehouse_name'];
            $addr   = $warehouse['address'];
            $type   = $warehouse['type_name'];
            $status = $warehouse['status'];

            echo "
                <tr onclick=\"window.location.href='index.php?page=warehouse/updateWarehouse&id={$id}'\">
                    <td>{$id}</td>
                    <td>{$name}</td>
                    <td>{$addr}</td>
                    <td>{$type}</td>
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

<script>
    const searchInput = document.getElementById('searchInput');
    const searchResult = document.getElementById('searchResult');

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        if (query !== '') {
            fetch(`../../../view/page/manage/warehouse/searchWarehouse.php?q=${query}`)
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