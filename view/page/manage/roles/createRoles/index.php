<?php
include_once(__DIR__ . "/../../../../../controller/cRoles.php");
$p = new CRoles();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm vai trò</title>
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 18px;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 5px rgba(59,130,246,0.3);
        }

<<<<<<< Updated upstream
        .form-actions {
            text-align: center;
            margin-top: 20px;
        }

        .form-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            background-color: #3b82f6;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .form-actions button:hover {
            background-color: #2563eb;
        }
=======
        /* Button group */
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
>>>>>>> Stashed changes
    </style>
</head>
<body>
    <div class="container">
        <h2>Thêm vai trò</h2>
        <form action="roles/createRoles/process.php" method="post">
            <div class="form-group">
                <label for="role_name">Tên vai trò</label>
                <input type="text" id="role_name" name="role_name" required>
            </div>
            <div class="form-group">
                <label for="description">Mô tả</label>
                <input type="text" id="description" name="description" required>
            </div>
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" required>
                    <option value="1">Hoạt động</option>
                    <option value="2">Không hoạt động</option>
                </select>
            </div>
            <div class="form-group">
                <label for="create_at">Ngày tạo</label>
                <input type="text" id="create_at" name="create_at" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly>
            </div>
            <div class="form-actions">
<<<<<<< Updated upstream
=======
                <a href="index.php?page=roles">Quay lại</a>
                <button type="reset" class="btn-secondary">Hủy</button>
>>>>>>> Stashed changes
                <button type="submit" name="btnAdd">Thêm vai trò</button>
            </div>
        </form>
    </div>
</body>
</html>