<?php
// session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include_once("../../../controller/cCategories.php");

if (!isset($_SESSION["login"])) {
    header("Location: ../page/index.php?page=login");
    exit();
}

$id = $_GET['id'] ?? null;
$cCategories = new CCategories();
$category = $id ? $cCategories->getCategoryById($id) : null;

if (!$category) {
    $_SESSION['error'] = "Không tìm thấy danh mục cần cập nhật";
    header("Location: index.php?page=categories");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cập nhật danh mục</title>
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
    .form-group { margin-bottom: 18px; }
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 18px;
      color: #333;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 18px;
      transition: border-color 0.2s;
    }
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      border-color: #3b82f6;
      outline: none;
      box-shadow: 0 0 5px rgba(59,130,246,0.3);
    }
    .error-message {
      font-size: 14px;
      color: #e11d48;
      margin-top: 4px;
      display: block;
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
    .form-actions a:hover { background-color: #2563eb; }
    .form-actions .btn-secondary { background-color: #6b7280; }
    .form-actions .btn-secondary:hover { background-color: #4b5563; }
    .form-actions .btn-success { background-color: #16a34a; }
    .form-actions .btn-success:hover { background-color: #15803d; }
  </style>
</head>

<body>
  <div class="page-header">
    <h2>Cập nhật danh mục sản phẩm</h2>
    <p>Cập nhật thông tin danh mục</p>
  </div>

  <div class="container">
    <form action="categories/updateCategories/process.php" method="post" onsubmit="return validateForm()">
      <input type="hidden" name="id" value="<?php echo $category['category_id'] ?? $category['_id']; ?>">

      <!-- Mã code danh mục -->
      <div class="form-group">
        <label for="category_code">Mã code danh mục</label>
        <input type="text" id="category_code" name="category_code"
               value="<?php echo htmlspecialchars($category['category_code'] ?? ''); ?>"
               placeholder="Nhập mã code (VD: CODE123)"
               required
               onblur="validateField(this, 'Mã code không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Tên danh mục -->
      <div class="form-group">
        <label for="category_name">Tên danh mục</label>
        <input type="text" id="category_name" name="category_name"
               value="<?php echo htmlspecialchars($category['category_name']); ?>"
               placeholder="Nhập tên danh mục"
               required
               onblur="validateField(this, 'Tên danh mục không được để trống.', value => value.length > 0)">
        <span class="error-message"></span>
      </div>

      <!-- Mô tả -->
      <div class="form-group">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="Nhập mô tả danh mục"><?php echo htmlspecialchars($category['description']); ?></textarea>
        <span class="error-message"></span>
      </div>

      <!-- Trạng thái -->
      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="1" <?php echo ($category['status'] == 1) ? 'selected' : ''; ?>>Hoạt động</option>
          <option value="0" <?php echo ($category['status'] == 0) ? 'selected' : ''; ?>>Ngừng hoạt động</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Nút thao tác -->
      <div class="form-actions">
        <a href="index.php?page=categories">Quay lại</a>
        <button type="button" class="btn-secondary" id="btnCancel">Hủy</button>
        <button type="submit" class="btn-success" name="btnUpdate">Cập nhật</button>
      </div>
    </form>
  </div>

  <script>
    const form = document.querySelector("form");
    const inputs = form.querySelectorAll("input, textarea, select");
    const saveBtn = form.querySelector("button[name='btnUpdate']");
    const cancelBtn = document.getElementById("btnCancel");

    const originalData = {};
    inputs.forEach(field => originalData[field.id] = field.value);

    cancelBtn.addEventListener("click", function () {
      inputs.forEach(field => field.value = originalData[field.id]);
      const errors = form.querySelectorAll(".error-message");
      errors.forEach(error => error.innerText = "");
      saveBtn.disabled = true;
      saveBtn.style.opacity = "0.6";
      saveBtn.style.cursor = "not-allowed";
    });

    saveBtn.disabled = true;
    saveBtn.style.opacity = "0.6";
    saveBtn.style.cursor = "not-allowed";

    function validateField(field, message, rule) {
      let error = field.closest(".form-group").querySelector(".error-message");
      if (!rule(field.value.trim())) {
        error.innerText = message;
        return false;
      }
      // Validate riêng cho mã code
      // if (field.id === "category_code" && field.value.trim() !== "") {
      //   let regex = /^[A-Za-z]$/;
      //   if (!regex.test(field.value.trim())) {
      //     error.innerText = "Mã code chỉ gồm chữ, số, gạch dưới/gạch ngang (3-20 ký tự)";
      //     return false;
      //   }
      // }
      error.innerText = "";
      return true;
    }

    function validateForm() {
      let valid = true;
      inputs.forEach(field => {
        if (field.hasAttribute("required")) {
          if (!validateField(field, "Trường này không được để trống.", v => v.length > 0)) valid = false;
        }
      });
      saveBtn.disabled = !valid;
      saveBtn.style.opacity = valid ? "1" : "0.6";
      saveBtn.style.cursor = valid ? "pointer" : "not-allowed";
      return valid;
    }

    inputs.forEach(field => {
      field.addEventListener("input", () => {
        validateForm();
      });
    });
  </script>
</body>
</html>
