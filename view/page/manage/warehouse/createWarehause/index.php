<?php
include_once("../../../controller/cWarehouse.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouse_id = $_POST['warehouse_id'];
    $warehouse_name = $_POST['warehouse_name'];
    $province = $_POST['province'];
    $district = $_POST['district'];
    $ward = $_POST['ward'];
    $status = $_POST['status'];
    $address = "$ward, $district, $province";
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
<html>
<head>
    <title>Thêm kho chi nhánh mới</title>
    <style>
        .form-add-warehouse {
            max-width: 500px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .form-add-warehouse h3 {
            text-align: center;
            margin-bottom: 20px;
        }
        .form-add-warehouse label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }
        .form-add-warehouse input, .form-add-warehouse select {
            width: 100%;
            padding: 8px 10px;
            margin-bottom: 16px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .form-add-warehouse button {
            width: 100%;
            padding: 10px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .form-add-warehouse button:hover {
            background: #2563eb;
        }
    </style>
    <script>
        const data = {
            "TP. Hồ Chí Minh": {
                "Quận 1": ["Phường Bến Nghé", "Phường Bến Thành", "Phường Cầu Kho"],
                "Quận 7": ["Phường Tân Phong", "Phường Tân Hưng"],
                "TP. Thủ Đức": ["Phường Linh Trung", "Phường Hiệp Phú"]
            },
            "Hà Nội": {
                "Quận Ba Đình": ["Phường Kim Mã", "Phường Ngọc Hà"],
                "Quận Hoàn Kiếm": ["Phường Hàng Bạc", "Phường Hàng Đào"]
            },
            "Đà Nẵng": {
                "Quận Hải Châu": ["Phường Thạch Thang", "Phường Hải Châu 1"],
                "Quận Sơn Trà": ["Phường An Hải Bắc", "Phường Mân Thái"]
            }
        };

        window.onload = function() {
            const provinceSelect = document.getElementById('province');
            const districtSelect = document.getElementById('district');
            const wardSelect = document.getElementById('ward');

            // Load tỉnh/thành
            for (let province in data) {
                provinceSelect.options.add(new Option(province, province));
            }

            provinceSelect.onchange = function() {
                districtSelect.length = 1; // clear
                wardSelect.length = 1;
                if (this.value && data[this.value]) {
                    for (let district in data[this.value]) {
                        districtSelect.options.add(new Option(district, district));
                    }
                }
            };

            districtSelect.onchange = function() {
                wardSelect.length = 1;
                let province = provinceSelect.value;
                if (province && this.value && data[province][this.value]) {
                    data[province][this.value].forEach(function(ward) {
                        wardSelect.options.add(new Option(ward, ward));
                    });
                }
            };
        };
    </script>
</head>
<body>
    <form class="form-add-warehouse" method="post">
        <h3>Thêm kho chi nhánh mới</h3>
        <label for="warehouse_id">Mã kho</label>
        <input type="text" name="warehouse_id" id="warehouse_id" required>

        <label for="warehouse_name">Tên kho</label>
        <input type="text" name="warehouse_name" id="warehouse_name" required>

        <label for="province">Tỉnh/Thành phố</label>
        <select name="province" id="province" required>
            <option value="">-- Chọn tỉnh/thành phố --</option>
        </select>

        <label for="district">Quận/Huyện</label>
        <select name="district" id="district" required>
            <option value="">-- Chọn quận/huyện --</option>
        </select>

        <label for="ward">Phường/Xã</label>
        <select name="ward" id="ward" required>
            <option value="">-- Chọn phường/xã --</option>
        </select>

        <label for="status">Trạng thái</label>
        <select name="status" id="status" required>
            <option value="1">Đang hoạt động</option>
            <option value="0">Ngừng hoạt động</option>
        </select>

        <button type="submit">Thêm kho</button>
    </form>
</body>
</html>