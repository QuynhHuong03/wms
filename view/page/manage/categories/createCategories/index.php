<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
?>

<style>
/* CSS giống form Thêm người dùng */
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
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 18px;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: #3b82f6;
  outline: none;
  box-shadow: 0 0 5px rgba(59,130,246,0.3);
}

textarea {
  resize: vertical;
  min-height: 100px;
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

<body>
  <div class="page-header">
    <h2>Thêm danh mục</h2>
    <p>Tạo mới danh mục sản phẩm</p>
  </div>

  <div class="container">
    <form action="categories/createCategories/process.php" method="post" onsubmit="return validateForm()">
      
      <!-- Mã danh mục -->
      <div class="form-group">
        <label for="category_id">Mã danh mục</label>
        <input type="text" id="category_id" name="category_id" placeholder="Nhập mã danh mục (VD: DM001)">
        <span class="error-message"></span>
      </div>

      <!-- Tên danh mục -->
      <div class="form-group">
        <label for="category_name">Tên danh mục</label>
        <input type="text" id="category_name" name="category_name" placeholder="Nhập tên danh mục">
        <span class="error-message"></span>
      </div>

      <!-- Mã code danh mục -->
      <div class="form-group">
        <label for="category_code">Mã code danh mục</label>
        <input type="text" id="category_code" name="category_code" placeholder="Nhập mã code (VD: CODE123)">
        <span class="error-message"></span>
      </div>

      <!-- Mô tả -->
      <div class="form-group">
        <label for="description">Mô tả</label>
        <textarea id="description" name="description" placeholder="Nhập mô tả danh mục"></textarea>
        <span class="error-message"></span>
      </div>

      <!-- Trạng thái -->
      <div class="form-group">
        <label for="status">Trạng thái</label>
        <select id="status" name="status">
          <option value="">- Chọn trạng thái -</option>
          <option value="1">Hoạt động</option>
          <option value="0">Không hoạt động</option>
        </select>
        <span class="error-message"></span>
      </div>

      <!-- Nút thao tác -->
      <div class="form-actions">
        <a href="index.php?page=categories">Quay lại</a>
        <button type="reset" class="btn-secondary">Hủy</button>
        <button type="submit" class="btn-success" name="btnAdd">Thêm</button>
      </div>

    </form>
  </div>
</body>

<script>
document.addEventListener("DOMContentLoaded", function () {
  const form = document.querySelector("form");
  const inputs = form.querySelectorAll("input, select, textarea");
  const saveBtn = form.querySelector("button[name='btnAdd']");

  saveBtn.disabled = true;
  saveBtn.style.opacity = "0.6";
  saveBtn.style.cursor = "not-allowed";

  inputs.forEach((field) => (field.dataset.touched = "false"));

  function validateField(field) {
    let value = field.value.trim();
    let error = field.closest(".form-group").querySelector(".error-message");
    let valid = true;

    if (field.dataset.touched === "false") {
      error.innerText = "";
      return true;
    }

    if (["category_id","category_name"].includes(field.id) && value === "") {
      error.innerText = "Trường này là bắt buộc";
      return false;
    }

    if (field.id === "category_id") {
      let regex = /^DM[0-9]{3}$/;
      if (!regex.test(value)) {
        error.innerText = "Mã danh mục phải dạng DM001";
        valid = false;
      } else error.innerText = "";
    }

    if (field.id === "category_name") {
      let regex = /^[\p{L}\s0-9]+$/u;
      if (!regex.test(value)) {
        error.innerText = "Tên danh mục không hợp lệ";
        valid = false;
      } else error.innerText = "";
    }

    if (field.id === "category_code" && value === "") {
      error.innerText = "Trường này là bắt buộc";
      valid = false;
    } else if (field.id === "category_code") {
      let regex = /^[A-Za-z0-9_-]{3,20}$/;
      if (!regex.test(value)) {
        error.innerText = "Mã code chỉ gồm chữ, số, gạch dưới/gạch ngang (3-20 ký tự)";
        valid = false;
      } else error.innerText = "";
    }

    if (field.id === "status" && value === "") {
      error.innerText = "Vui lòng chọn trạng thái";
      valid = false;
    }

    return valid;
  }

  function validateForm() {
    let isValid = true;
    inputs.forEach((field) => {
      if (!validateField(field)) isValid = false;
    });

    saveBtn.disabled = !isValid;
    saveBtn.style.opacity = isValid ? "1" : "0.6";
    saveBtn.style.cursor = isValid ? "pointer" : "not-allowed";

    return isValid;
  }

  inputs.forEach((field) => {
    field.addEventListener("input", function () {
      field.dataset.touched = "true";
      validateField(field);
      validateForm();
    });

    field.addEventListener("blur", function () {
      field.dataset.touched = "true";
      validateField(field);
      validateForm();
    });

    field.addEventListener("change", function () {
      field.dataset.touched = "true";
      validateField(field);
      validateForm();
    });
  });

  form.addEventListener("submit", function (e) {
    if (!validateForm()) e.preventDefault();
  });
});
</script>
