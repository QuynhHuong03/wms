<?php
include_once("../../../controller/cWarehouse.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouse_id   = $_POST['warehouse_id'];
    $warehouse_name = $_POST['warehouse_name'];
    $province       = $_POST['province_name'];
    $ward           = $_POST['ward_name'];
    $street         = $_POST['street'];
    $status         = $_POST['status'];

    // Ghép địa chỉ đầy đủ (không có quận/huyện nữa)
    $address = "$street, $ward, $province";

    $cWarehouse = new CWarehouse();
    $result = $cWarehouse->addBranchWarehouse($warehouse_id, $warehouse_name, $address, $status);

    if ($result) {
        echo "<script>alert('Thêm kho chi nhánh thành công!');window.location='../index.php';</script>";
        exit;
    } else {
        echo "<script>alert('Thêm kho thất bại!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm kho chi nhánh mới</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 450px; background: #fff; margin: 50px auto; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .container h2 { text-align: center; margin-bottom: 20px; }
        .group { margin-bottom: 15px; }
        .group label { display: block; margin-bottom: 5px; }
        .group input, .group select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #007bff; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #0069d9; }
    </style>
</head>
<body>
<div class="container">
    <h2>Thêm kho chi nhánh</h2>
    <form id="warehouseForm" method="POST">
        <div class="group">
            <label>Mã kho</label>
            <input type="text" name="warehouse_id" required>
        </div>
        <div class="group">
            <label>Tên kho</label>
            <input type="text" name="warehouse_name" required>
        </div>
        <div class="group">
            <label>Tỉnh/Thành phố</label>
            <select id="province" required>
                <option value="">-- Chọn tỉnh/thành phố --</option>
            </select>
            <input type="hidden" name="province_name" id="province_name">
        </div>
        <div class="group">
            <label>Phường/Xã</label>
            <select id="ward" required disabled>
                <option value="">-- Chọn phường/xã --</option>
            </select>
            <input type="hidden" name="ward_name" id="ward_name">
        </div>
        <div class="group">
            <label>Tên đường</label>
            <input type="text" name="street" id="street" placeholder="Nhập tên đường..." required>
        </div>
        <div class="group">
            <label>Trạng thái</label>
            <select name="status" required>
                <option value="1">Đang hoạt động</option>
                <option value="0">Ngừng hoạt động</option>
            </select>
        </div>
        <button type="submit">Thêm kho</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const provinceSelect = document.getElementById('province');
    const wardSelect     = document.getElementById('ward');
    const provinceName   = document.getElementById('province_name');
    const wardName       = document.getElementById('ward_name');

    let wardsCache = [];

    // 1. Load tỉnh/thành phố (API v2 -> có field results)
    fetch('https://provinces.open-api.vn/api/v2/p/')
        .then(res => res.json())
        .then(data => {
            const provinces = data.results || data; // nếu là v2 thì có results
            provinces.forEach(p => {
                const opt = new Option(p.name, p.code);
                opt.dataset.name = p.name;
                provinceSelect.add(opt);
            });
        })
        .catch(err => console.error("Lỗi load tỉnh/thành:", err));

    // 2. Chọn tỉnh → load tất cả phường/xã (gộp từ các quận)
    provinceSelect.addEventListener('change', async function() {
        wardSelect.length = 1;
        wardSelect.disabled = true;

        provinceName.value = this.selectedOptions[0]?.dataset.name || '';
        if (!this.value) return;

        try {
            const res = await fetch(`https://provinces.open-api.vn/api/v2/p/${this.value}?depth=2`);
            const province = await res.json();

            wardsCache = [];
            if (province.districts) {
                for (let d of province.districts) {
                    if (Array.isArray(d.wards)) {
                        wardsCache = wardsCache.concat(d.wards);
                    }
                }
            }

            wardsCache.forEach(w => {
                const opt = new Option(w.name, w.code);
                opt.dataset.name = w.name;
                wardSelect.add(opt);
            });
            wardSelect.disabled = false;
        } catch (err) {
            console.error("Lỗi load phường/xã:", err);
        }
    });

    // 3. Chọn phường/xã → set hidden input
    wardSelect.addEventListener('change', function() {
        wardName.value = this.selectedOptions[0]?.dataset.name || '';
    });
});
</script>

</body>
</html>
