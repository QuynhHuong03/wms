<?php
include_once(__DIR__ . "/../../../../../controller/cWarehouse.php");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm kho chi nhánh</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            color: #333;
            margin: 0;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .page-header {
            width: 90%;
            max-width: 800px;
            margin: 30px auto 10px;
        }

        .page-header h2 {
            margin: 0;
            color: #222;
        }

        .page-header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 16px;
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
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert <?php echo $_SESSION['msg']['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo $_SESSION['msg']['content']; ?>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <div class="page-header">
        <h2>Thêm kho chi nhánh</h2>
        <p>Tạo mới kho chi nhánh</p>
    </div>

    <div class="container">
        <form action="warehouse/createWarehause/process.php" method="post">
            <div class="form-group">
                <label for="warehouse_id">Mã kho</label>
                <input type="text" id="warehouse_id" name="warehouse_id" required>
            </div>
            <div class="form-group">
                <label for="warehouse_name">Tên kho</label>
                <input type="text" id="warehouse_name" name="warehouse_name" required>
            </div>
            <div class="form-group">
                <label for="address">Địa chỉ</label>
                <input type="text" id="address" name="address" required>
            </div>
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" required>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Ngừng hoạt động</option>
                </select>
            </div>
            <div class="form-group">
                <label for="created_at">Ngày tạo</label>
                <input type="text" id="created_at" name="created_at" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
            </div>
            <div class="form-actions">
                <a href="../manage/index.php?page=warehouse" class="btn-secondary">Quay lại</a>
                <button type="reset" class="btn-secondary">Hủy</button>
                <button type="submit" name="btnAdd" class="btn-success">Thêm</button>
            </div>
        </form>
    </div>
</body>
</html>
