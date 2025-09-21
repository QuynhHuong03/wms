<?php
include_once(__DIR__ . "/../../../../../controller/cSupplier.php");

$cSupplier = new CSupplier();
$supplier = null;

if (isset($_GET['id'])) {
    $supplierId = $_GET['id'];
    $suppliers = $cSupplier->getAllSuppliers();

    foreach ($suppliers as $s) {
        if ($s['supplier_id'] == $supplierId) {
            $supplier = $s;
            break;
        }
    }
}

if (!$supplier) {
    echo "<script>
        alert('Không tìm thấy nhà cung cấp.');
        window.location.href = '../index.php?page=supplier';
    </script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật nhà cung cấp</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
body {
  font-family: 'Arial', sans-serif;
  background-color: #f9f9f9;
  color: #333;
  margin: 0;
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

/* Input group */
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

/* Error message */
.error-message {
  font-size: 14px;
  color: #e11d48;
  margin-top: 4px;
  display: block;
}

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
    </style>
</head>
<body>
    <?php
    include_once(__DIR__ . "/../../../../../controller/cSupplier.php");
    $supplierId = $_GET['id'];
    $cSupplier = new CSupplier();
    $supplier = $cSupplier->getSupplierById($supplierId);
    ?>
    <div class="page-header">
        <h2>Cập nhật nhà cung cấp</h2>
        <p>Cập nhật thông tin nhà cung cấp</p>
    </div>

    <div class="container">
        <form action="supplier/updateSupplier/process.php" method="post">
            <input type="hidden" name="supplier_id" value="<?php echo $supplier['supplier_id']; ?>">

            <!-- Tên nhà cung cấp -->
            <div class="form-group">
                <label for="supplier_name">Tên nhà cung cấp</label>
                <input type="text" id="supplier_name" name="supplier_name" value="<?php echo $supplier['supplier_name']; ?>" required>
                <span class="error-message"></span>
            </div>

            <!-- Liên hệ -->
            <div class="form-group">
                <label for="contact">Liên hệ</label>
                <input type="text" id="contact" name="contact" value="<?php echo $supplier['contact']; ?>" required>
                <span class="error-message"></span>
            </div>

            <!-- Trạng thái -->
            <div class="form-group">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" required>
                    <option value="1" <?php echo ($supplier['status'] == 1) ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="0" <?php echo ($supplier['status'] == 0) ? 'selected' : ''; ?>>Ngừng hoạt động</option>
                </select>
                <span class="error-message"></span>
            </div>

            <!-- Nút thao tác -->
            <div class="form-actions">
                <a href="../index.php?page=supplier" class="btn-secondary">Quay lại</a>
                <button type="submit" class="btn-success" name="btnUpdate">Cập nhật</button>
            </div>
        </form>
    </div>
</body>
</html>