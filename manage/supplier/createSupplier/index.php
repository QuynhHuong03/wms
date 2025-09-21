<?php
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm nhà cung cấp</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 50px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h3 {
            margin: 0 0 20px;
            color: #333;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: bold;
            color: #333;
        }

        input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        button {
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }

        button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h3>Thêm nhà cung cấp</h3>
        <form action="supplier/createSupplier/process.php" method="POST">
            <label for="supplier_name">Tên nhà cung cấp:</label>
            <input type="text" id="supplier_name" name="supplier_name" required>

            <label for="contact">Liên hệ:</label>
            <input type="text" id="contact" name="contact" required>

            <label for="created_at">Ngày tạo:</label>
            <input type="text" id="created_at" name="created_at" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>

            <button type="submit">Thêm nhà cung cấp</button>
        </form>
    </div>
</body>
</html>