<?php
include("../../../../controller/cProduct.php");
$cProduct = new CProduct();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q !== '') {
    $products = $cProduct->searchProductsByName($q);

    if (empty($products)) {
        echo "<div class='no-result'>Không tìm thấy sản phẩm</div>";
    } else {
        echo "
            <table>
                <thead>
                    <tr>
                        <th>Mã sản phẩm</th>
                        <th>Tên sản phẩm</th>
                        <th>Barcode</th>
                        <th>Loại</th>
                        <th>Nhà cung cấp</th>
                        <th>Kho</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach ($products as $product) {
            $id     = $product['sku'];
            $name   = $product['product_name'];
            $barcode= $product['barcode'];
            $type   = $product['category_name'] ?? '';
            $supplier = $product['supplier_name'] ?? '';
            $warehouse = $product['warehouse_id'] ?? '';
            $status = $product['status'];

            echo "
                <tr onclick=\"window.location.href='index.php?page=products/updateProduct&id={$id}'\">
                    <td>{$id}</td>
                    <td>{$name}</td>
                    <td>{$barcode}</td>
                    <td>{$type}</td>
                    <td>{$supplier}</td>
                    <td>{$warehouse}</td>
                    <td>" . ($status == 1 ? 'Đang bán' : 'Ngừng bán') . "</td>
                </tr>
            ";
        }

        echo "</tbody></table>";
    }
}
?>

<style>
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
    .qlproduct-search input {
        border: none;
        outline: none;
        font-size: 14px;
        padding: 4px 6px;
        width: 300px;
    }
</style>

<script>
    const searchInput = document.getElementById('searchInput');
    const searchResult = document.getElementById('searchResult');

    searchInput.addEventListener('input', function() {
        const query = searchInput.value.trim();
        if (query !== '') {
            fetch(`../../../view/page/manage/products/searchProduct.php?q=${query}`)
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
