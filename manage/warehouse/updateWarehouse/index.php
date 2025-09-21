<?php
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");

$cWarehouse = new CWarehouse();
$warehouse = null;

if (isset($_GET['id'])) {
    $warehouseId = $_GET['id'];
    $warehouses = $cWarehouse->getAllWarehouses();

    foreach ($warehouses as $w) {
        if ($w['warehouse_id'] === $warehouseId) {
            $warehouse = $w;
            break;
        }
    }
}

if (!$warehouse) {
    echo "<script>
        alert('Không tìm thấy kho.');
        window.location.href = '../index.php?page=warehouse';
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật kho</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            color: #333;
            margin: 0;
        }

        .container {
            width: 90%;
            max-width: 800px;
            margin: 20px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 25px 30px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 18px;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 18px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 5px rgba(59,130,246,0.3);
        }

        .form-actions {
            text-align: center;
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .form-actions button,
        .form-actions a {
            background-color: #3b82f6;
            color: #fff;
            padding: 10px 20px;
            font-size: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .form-actions button:hover,
        .form-actions a:hover {
            background-color: #2563eb;
        }

        .form-actions .btn-secondary {
            background-color: #6b7280;
        }

        .form-actions .btn-secondary:hover {
            background-color: #4b5563;
        }

        .form-actions .btn-success {
            background-color: #16a34a;
        }

        .form-actions .btn-success:hover {
            background-color: #15803d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Cập nhật kho</h2>
        <form action="process.php" method="post">
            <input type="hidden" name="warehouse_id" value="<?php echo $warehouse['warehouse_id']; ?>">

            <div class="form-group">
                <label for="warehouse_name">Tên kho</label>
                <input type="text" id="warehouse_name" name="warehouse_name" value="<?php echo $warehouse['warehouse_name']; ?>" required>
            </div>

            <div class="form-group">
                <label for="address">Địa chỉ</label>
                <input type="text" id="address" name="address" value="<?php echo $warehouse['address']; ?>" required>
            </div>

            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" required>
                    <option value="1" <?php echo $warehouse['status'] == 1 ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="0" <?php echo $warehouse['status'] == 0 ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                </select>
            </div>

            <div class="form-actions">
                <a href="../manage/index.php?page=warehouse" class="btn-secondary">Quay lại</a>
                <button type="submit" name="btnUpdate" class="btn-success">Cập nhật</button>
            </div>
        </form>
    </div>
</body>
</html>